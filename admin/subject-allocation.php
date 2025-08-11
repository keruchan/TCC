<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

$academic_years = ['2024-25', '2025-26', '2026-27', '2027-28'];
$semesters = ['1st', '2nd', 'summer'];
$year_levels = ['1st', '2nd', '3rd', '4th'];

$selected_year = $_POST['academic_year'] ?? '';
$selected_sem = $_POST['semester'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sections'])) {
    if ($selected_year && $selected_sem && isset($_POST['sections']) && is_array($_POST['sections'])) {
        foreach ($_POST['sections'] as $course_id => $levels) {
            foreach ($levels as $year_level => $section_count) {
                if (is_numeric($section_count) && $section_count >= 0) {
                    $del = $dbh->prepare("DELETE FROM tblclass WHERE course_id = ? AND academic_year = ? AND semester = ? AND year_level = ?");
                    $del->execute([$course_id, $selected_year, $selected_sem, $year_level]);
                    if ($section_count > 0) {
                        $ins = $dbh->prepare("INSERT INTO tblclass (course_id, academic_year, semester, year_level, section, date_created) VALUES (?, ?, ?, ?, ?, NOW())");
                        $ins->execute([$course_id, $selected_year, $selected_sem, $year_level, $section_count]);
                    }
                }
            }
        }
        $msg = "Sections saved!";
    } else {
        $msg = "Fill all values, select year and semester.";
    }
}

$courses = [];
$stmt = $dbh->prepare("SELECT ID, CourseName FROM tblcourse ORDER BY CourseName");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_OBJ);

$existing = [];
if ($selected_year && $selected_sem) {
    $se = $dbh->prepare("SELECT course_id, year_level, section FROM tblclass WHERE academic_year=? AND semester=?");
    $se->execute([$selected_year, $selected_sem]);
    foreach ($se->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['course_id']][$row['year_level']] = $row['section'];
    }
}

// --- STAT CARDS LOGIC ---

// Number of courses/programs
$num_courses = $dbh->query("SELECT COUNT(*) FROM tblcourse")->fetchColumn();

// Number of subjects (in curriculum for that year and sem)
$num_subjects = 0;
if ($selected_sem) {
    $sql = "SELECT COUNT(DISTINCT subject_id) FROM tblcurriculum WHERE semester = :sem";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':sem', $selected_sem, PDO::PARAM_STR);
    $stmt->execute();
    $num_subjects = $stmt->fetchColumn();
}

// Number of sections (tblclass) for this year/semester
$num_sections = 0;
if ($selected_year && $selected_sem) {
    $sql = "SELECT COUNT(*) FROM tblclass WHERE academic_year = :ay AND semester = :sem";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':ay', $selected_year, PDO::PARAM_STR);
    $stmt->bindParam(':sem', $selected_sem, PDO::PARAM_STR);
    $stmt->execute();
    $num_sections = $stmt->fetchColumn();
}

// Number of candidate instructors (at least 1 verified skill in tblskills)
$num_candidate_instructors = $dbh->query("SELECT COUNT(DISTINCT t.TeacherID)
            FROM tblteacher t
            JOIN tblskills s ON t.TeacherID = s.TeacherID
            WHERE s.Verified != 0")->fetchColumn();

// --- QUALIFIED INSTRUCTORS PER COURSE ---
// Will show in the table
$qualified_per_course = [];
$sql = "SELECT tc.ID as course_id, COUNT(DISTINCT t.TeacherID) as num_teachers
        FROM tblteacher t
        JOIN tblskills s ON t.TeacherID = s.TeacherID
        JOIN tblteacher AS tc_link ON tc_link.TeacherID = t.TeacherID
        JOIN tblcourse tc ON FIND_IN_SET(tc.ID, tc_link.CoursesLoad) > 0
        WHERE s.Verified != 0
        GROUP BY tc.ID";
$stmt = $dbh->prepare($sql);
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $qualified_per_course[$row['course_id']] = $row['num_teachers'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Class Section Count</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .stat-cards-flex {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
            margin-top: px;
        }
        .stat-card-box {
            flex: 1 1 220px;
            min-width: 200px;
            max-width: 250px;
            background: #f7f7fa;
            border-radius: 8px;
            padding: 28px 12px 18px 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .stat-card-box .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #007bff;
            margin-bottom: 6px;
        }
        .stat-card-box .stat-label {
            font-size: 1.08rem;
            color: #666;
            font-weight: 500;
        }

        .section-table-header th,
        .section-table-header td {
            text-align: center;
            background: #f8f9fa;
            font-weight: 600;
        }
        .section-table th,
        .section-table td {
            text-align: center;
            vertical-align: middle;
        }
        .align-section-table {
            width: 100%;
            margin-bottom: 35px;
        }
        .course-title-row th {
            background: #ffeeba;
            font-size: 1.13rem;
            text-align: left;
            padding-left: 24px;
            letter-spacing: .02em;
        }
        @media (max-width: 991px) {
            .stat-cards-flex {
                flex-direction: column;
                gap: 10px;
            }
            .stat-card-box {
                max-width: 100%;
                min-width: 160px;
                padding: 20px 6px 13px 6px;
                margin-bottom: 0;
            }
            .align-section-table td, .align-section-table th {
                font-size: 0.98rem;
                padding: 8px !important;
            }
            .course-title-row th {
                padding-left: 10px;
            }
        }
    </style>
</head>
<body>
<?php include_once('includes/sidebar.php'); ?>
<?php include_once('includes/header.php'); ?>
<div class="content-wrap">
    <div class="main">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">

                    <div class="page-header">
                        <div class="page-title">
                            <h1>Class Sections per Year Level and Course</h1>
                        </div>
                    </div>

                    <!-- Stat Cards - perfectly aligned in one row -->
                    <div class="stat-cards-flex">
                        <div class="stat-card-box">
                            <div class="stat-number"><?= $num_courses ?></div>
                            <div class="stat-label">No. of Courses/Programs</div>
                        </div>
                        <div class="stat-card-box">
                            <div class="stat-number"><?= $num_subjects ?></div>
                            <div class="stat-label">No. of Subjects</div>
                        </div>
                        <div class="stat-card-box">
                            <div class="stat-number"><?= $num_sections ?></div>
                            <div class="stat-label">No. of Sections</div>
                        </div>
                        <div class="stat-card-box">
                            <div class="stat-number"><?= $num_candidate_instructors ?></div>
                            <div class="stat-label">No. of Qualified Instructors</div>
                        </div>
                    </div>
                    <!-- End Stat Cards -->

                    <div class="card alert mb-4">
                        <?php if (isset($msg)): ?><div class="alert alert-info"><?= htmlentities($msg) ?></div><?php endif; ?>
                        <form method="post" class="form-inline">
                            <label for="academic_year" class="mr-2">Academic Year:</label>
                            <select name="academic_year" id="academic_year" class="form-control mr-3" required>
                                <option value="">-- Select Academic Year --</option>
                                <?php foreach ($academic_years as $ay): ?>
                                    <option value="<?= htmlentities($ay) ?>" <?= $selected_year == $ay ? 'selected' : '' ?>><?= htmlentities($ay) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="semester" class="mr-2">Semester:</label>
                            <select name="semester" id="semester" class="form-control mr-3" required>
                                <option value="">-- Select Semester --</option>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?= htmlentities($sem) ?>" <?= $selected_sem == $sem ? 'selected' : '' ?>><?= htmlentities($sem) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary mr-3" name="load_sections">Load Sections</button>
                        </form>
                    </div>

                    <?php if ($selected_year && $selected_sem): ?>
                    <form method="post">
                        <input type="hidden" name="academic_year" value="<?= htmlentities($selected_year) ?>">
                        <input type="hidden" name="semester" value="<?= htmlentities($selected_sem) ?>">
                        <div class="card alert">
                            <div class="card-header">
                                <h4>Number of Sections Per Year Level and Course</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                <table class="table table-bordered align-section-table">
                                    <thead>
                                        <tr>
                                            <th style="width:25%;">Course / Program</th>
                                            <?php foreach ($year_levels as $yl_val): ?>
                                                <th><?= $yl_val ?></th>
                                            <?php endforeach; ?>
                                            <th style="width:14%;">Qualified Instructors</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr class="course-title-row">
                                            <th><?= htmlentities($course->CourseName) ?></th>
                                            <?php foreach ($year_levels as $yl_val): ?>
                                                <td>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        name="sections[<?= $course->ID ?>][<?= $yl_val ?>]"
                                                        class="form-control text-center"
                                                        style="max-width: 80px; margin: 0 auto;"
                                                        value="<?= isset($existing[$course->ID][$yl_val]) ? intval($existing[$course->ID][$yl_val]) : 0 ?>"
                                                        required
                                                    >
                                                </td>
                                            <?php endforeach; ?>
                                            <td>
                                                <?= isset($qualified_per_course[$course->ID]) ? $qualified_per_course[$course->ID] : 0 ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                                <div class="text-right">
                                    <button type="submit" name="save_sections" class="btn btn-success px-5">Save</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>

                    <?php include_once('includes/footer.php'); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../assets/js/lib/jquery.min.js"></script>
<script src="../assets/js/lib/jquery.nanoscroller.min.js"></script>
<script src="../assets/js/lib/menubar/sidebar.js"></script>
<script src="../assets/js/lib/preloader/pace.min.js"></script>
<script src="../assets/js/lib/bootstrap.min.js"></script>
<script src="../assets/js/scripts.js"></script>
</body>
</html>