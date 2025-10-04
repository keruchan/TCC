<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

$academic_years = ['2024-25', '2025-26', '2026-27', '2027-28', '2028-29', '2029-30', '2030-31'];
$semesters = ['1st', '2nd', 'summer'];
$year_levels = ['1st', '2nd', '3rd', '4th'];

$selected_year = $_POST['academic_year'] ?? '';
$selected_sem = $_POST['semester'] ?? '';

// --- STAT CARDS LOGIC ---
$num_courses = $dbh->query("SELECT COUNT(*) FROM tblcourse")->fetchColumn();

$num_subjects = 0;
if ($selected_sem) {
    $sql = "SELECT COUNT(DISTINCT subject_id) FROM tblcurriculum WHERE semester = :sem";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':sem', $selected_sem, PDO::PARAM_STR);
    $stmt->execute();
    $num_subjects = $stmt->fetchColumn();
}

$num_sections = 0;
if ($selected_year && $selected_sem) {
    $sql = "SELECT COUNT(*) FROM tblclass WHERE academic_year = :ay AND semester = :sem";
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(':ay', $selected_year, PDO::PARAM_STR);
    $stmt->bindParam(':sem', $selected_sem, PDO::PARAM_STR);
    $stmt->execute();
    $num_sections = $stmt->fetchColumn();
}

$num_candidate_instructors = $dbh->query("SELECT COUNT(DISTINCT t.TeacherID)
            FROM tblteacher t
            JOIN tblskills s ON t.TeacherID = s.TeacherID
            WHERE s.Verified != 0")->fetchColumn();

// --- SUBJECT OVERVIEW LOGIC ---
$subject_overview = [];
if ($selected_year && $selected_sem) {
    $sql = "SELECT 
                c.CourseName, 
                s.subject_name AS SubjectName, 
                s.units AS Units, 
                cur.year_level, 
                cl.section,
                s.ID AS subject_id,
                cl.id AS section_id
            FROM tblcurriculum cur
            JOIN tblcourse c ON cur.course_id = c.ID
            JOIN tblsubject s ON cur.subject_id = s.ID
            LEFT JOIN tblclass cl 
                ON cl.course_id = c.ID
                AND cl.academic_year = :ay
                AND cl.semester = :sem
                AND cl.year_level = cur.year_level
            WHERE cur.semester = :sem
            ORDER BY c.CourseName, cur.year_level, s.subject_name";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([
        ':ay' => $selected_year,
        ':sem' => $selected_sem
    ]);
    $subject_overview = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get subject fulfillment status (number of instructors assigned per subject-section)
$subject_fulfillment = [];
$subject_instructor_names = [];
if ($selected_year && $selected_sem && !empty($subject_overview)) {
    foreach ($subject_overview as $row) {
        $subject_id = $row['subject_id'];
        $section_count = intval($row['section'] ?? 0);
        $section_id = $row['section_id'];
        $fulfilled_instructors = 0;
        $instructor_names = [];
        // Fetch all sections for this course/year/semester/level
        if ($section_count > 0) {
            // For each section, check if there are allocations with instructors
            for ($sec = 1; $sec <= $section_count; $sec++) {
                // Try to find class id for this section number
                $class_stmt = $dbh->prepare("SELECT id FROM tblclass 
                    WHERE course_id = (SELECT cur.course_id FROM tblcurriculum cur WHERE cur.subject_id = ? LIMIT 1)
                        AND academic_year = ?
                        AND semester = ?
                        AND year_level = ?
                        AND section = ?");
                $class_stmt->execute([
                    $subject_id, $selected_year, $selected_sem, $row['year_level'], $sec
                ]);
                $class_result = $class_stmt->fetch(PDO::FETCH_ASSOC);
                $class_id = $class_result ? $class_result['id'] : null;
                if ($class_id) {
                    // Count number of allocated instructors for this subject-section
                    $sql = "SELECT sa.teacher_id, t.FirstName, t.LastName 
                            FROM subject_allocations sa 
                            LEFT JOIN tblteacher t ON t.TeacherID = sa.teacher_id
                            WHERE sa.subject_id = :subject_id 
                                AND sa.section_id = :section_id
                                AND sa.allocation_status = 'allocated'";
                    $stmt2 = $dbh->prepare($sql);
                    $stmt2->execute([
                        ':subject_id' => $subject_id,
                        ':section_id' => $class_id
                    ]);
                    $teachers = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($teachers as $teacher) {
                        $fullname = trim($teacher['FirstName'] . " " . $teacher['LastName']);
                        if ($fullname) {
                            $instructor_names[] = $fullname;
                        }
                        $fulfilled_instructors++;
                    }
                }
            }
        }
        // Remove duplicate instructor names
        $instructor_names = array_unique($instructor_names);
        $subject_fulfillment[$subject_id . '-' . $row['year_level']] = [
            'num' => $fulfilled_instructors,
            'total' => $section_count,
        ];
        $subject_instructor_names[$subject_id . '-' . $row['year_level']] = $instructor_names;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Subject Allocation</title>
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
        .prioritization-block {
            margin-bottom: 24px;
        }
        .prioritization-block label {
            font-weight: 600;
        }
        .prioritization-guide {
            color: #555;
            background: #ffeeba;
            border-radius: 5px;
            margin-bottom: 14px;
            padding: 10px 20px 10px 20px;
            font-size: 1.05rem;
        }
        .prioritization-title {
            font-size: 1.13rem;
            font-weight: 700;
            color: #0066aa;
            margin-bottom: 3px;
        }
        .dd-priorities {
            list-style: none;
            margin: 0;
            padding: 0;
            width: 100%;
            max-width: 430px;
        }
        .dd-priorities li {
            margin-bottom: 12px;
            background: #f7f7fa;
            padding: 15px 18px;
            border-radius: 6px;
            font-size: 1.07rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            cursor: move;
            border: 1px solid #e2e2e2;
        }
        .dd-priorities li .dd-grip {
            margin-right: 12px;
            font-size: 1.4em;
            color: #888;
            cursor: grab;
        }
        .dd-priorities li.dragging {
            opacity: 0.5;
            background: #e9ecef;
        }
        .fulfilled-yes {
            color: #2da85d;
            font-weight: 700;
        }
        .fulfilled-no {
            color: #c9302c;
            font-weight: 700;
        }
        .instructor-list {
            font-size: 0.98em;
            color: #444;
        }
        .generate-btn {
            margin-top: 18px;
            font-size: 1.1rem;
            padding-left: 32px;
            padding-right: 32px;
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
                            <h1>Subject Allocation (Auto Generation Setup)</h1>
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
                        <!-- Prioritization Section with Drag-and-Drop -->
                        <div class="card alert prioritization-block">
                            <div class="prioritization-title">Prioritization Settings</div>
                            <div class="prioritization-guide">
                                <b>Guide:</b> Set your prioritization for auto-assigning instructors to subjects.<br>
                                <b>Default/Suggested priority:</b> <span style="color:#007bff;">Matched Skills</span>, <span style="color:#007bff;">Educational Background</span>, <span style="color:#007bff;">Teaching Experience</span> (from highest to lowest).
                                <br>Drag to reorder. Top is the highest priority. No duplicates are possible.
                            </div>
                            <div>
                                <form id="prioritization-form" method="post" class="mt-2" action="auto-allocation.php" target="_blank">
                                    <input type="hidden" name="academic_year" value="<?= htmlentities($selected_year) ?>">
                                    <input type="hidden" name="semester" value="<?= htmlentities($selected_sem) ?>">
                                    <ol class="dd-priorities" id="priority-list">
                                        <li draggable="true" data-value="skills"><span class="dd-grip">&#9776;</span>Matched Skills</li>
                                        <li draggable="true" data-value="educ"><span class="dd-grip">&#9776;</span>Educational Background</li>
                                        <li draggable="true" data-value="exp"><span class="dd-grip">&#9776;</span>Teaching Experience (same subject)</li>
                                    </ol>
                                    <input type="hidden" name="priorities" id="priorities" value="skills,educ,exp">
                                    <div class="mt-3">
                                        <button type="submit" name="generate_allocation" class="btn btn-success generate-btn">
                                            <i class="fa fa-magic mr-1"></i> Generate Allocation
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Subjects Overview Table -->
                        <div class="card alert mb-4">
                            <h5>Subjects Overview (for Preparation)</h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                    <tr>
                                        <th>Course/Program</th>
                                        <th>Subject</th>
                                        <th>Units</th>
                                        <th>Year Level</th>
                                        <th>No. of Sections</th>
                                        <th style="min-width:155px;">Fulfilled (Instructor(s) Assigned)</th>
                                        <th>Instructor(s)</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($subject_overview) > 0): ?>
                                        <?php foreach($subject_overview as $row): ?>
                                            <?php
                                            $key = $row['subject_id'] . '-' . $row['year_level'];
                                            $fulfilled = $subject_fulfillment[$key]['num'] ?? 0;
                                            $total = $subject_fulfillment[$key]['total'] ?? 0;
                                            $instructors = $subject_instructor_names[$key] ?? [];
                                            ?>
                                            <tr>
                                                <td><?= htmlentities($row['CourseName']) ?></td>
                                                <td><?= htmlentities($row['SubjectName']) ?></td>
                                                <td><?= htmlentities($row['Units']) ?></td>
                                                <td><?= htmlentities($row['year_level']) ?></td>
                                                <td><?= htmlentities($row['section'] ?? 0) ?></td>
                                                <td class="<?= ($fulfilled >= $total && $total > 0) ? 'fulfilled-yes' : 'fulfilled-no' ?>">
                                                    <?php
                                                    if ($total > 0) {
                                                        echo "{$fulfilled}/{$total} section" . ($total > 1 ? "s" : "");
                                                    } else {
                                                        echo "0/0";
                                                    }
                                                    ?>
                                                </td>
                                                <td class="instructor-list">
                                                    <?php
                                                    if (!empty($instructors)) {
                                                        echo implode('<br>', array_map('htmlentities', $instructors));
                                                    } else {
                                                        echo "<span style='color:#888'>-</span>";
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7">No subjects found for the selected year and semester.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
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
<script>
// Drag and drop prioritization logic (no duplicate priorities allowed)
document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('priority-list');
    let draggedItem = null;

    list.addEventListener('dragstart', function(e) {
        if (e.target.tagName === 'LI') {
            draggedItem = e.target;
            setTimeout(() => {
                e.target.classList.add('dragging');
            }, 0);
        }
    });
    list.addEventListener('dragend', function(e) {
        if (e.target.tagName === 'LI') {
            e.target.classList.remove('dragging');
        }
    });
    list.addEventListener('dragover', function(e) {
        e.preventDefault();
        const afterElement = getDragAfterElement(list, e.clientY);
        if (afterElement == null) {
            list.appendChild(draggedItem);
        } else {
            list.insertBefore(draggedItem, afterElement);
        }
    });

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('li:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    // Update hidden input on order change
    list.addEventListener('drop', updatePriorities);
    list.addEventListener('dragend', updatePriorities);
    function updatePriorities() {
        const values = [];
        list.querySelectorAll('li').forEach(li => {
            values.push(li.getAttribute('data-value'));
        });
        document.getElementById('priorities').value = values.join(',');
    }
});
</script>
</body>
</html>