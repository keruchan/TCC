<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

// Dropdown data
$academic_years = ['2024-25', '2025-26', '2026-27', '2027-28'];
$semesters = ['1st', '2nd', 'summer'];
$year_levels = ['1st', '2nd', '3rd', '4th'];

// Get selection from POST
$selected_year = $_POST['academic_year'] ?? '';
$selected_sem = $_POST['semester'] ?? '';

// Save sections
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sections'])) {
    if ($selected_year && $selected_sem && isset($_POST['sections']) && is_array($_POST['sections'])) {
        foreach ($_POST['sections'] as $course_id => $levels) {
            foreach ($levels as $year_level => $section_count) {
                if (is_numeric($section_count) && $section_count >= 0) {
                    // Remove old record
                    $del = $dbh->prepare("DELETE FROM tblclass WHERE course_id = ? AND academic_year = ? AND semester = ? AND year_level = ?");
                    $del->execute([$course_id, $selected_year, $selected_sem, $year_level]);
                    // Insert new value if >0
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

// Get courses (fix column names to match your table)
$courses = [];
$stmt = $dbh->prepare("SELECT ID, CourseName FROM tblcourse ORDER BY CourseName");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_OBJ);

// Get existing sections for selected year/sem
$existing = [];
if ($selected_year && $selected_sem) {
    $se = $dbh->prepare("SELECT course_id, year_level, section FROM tblclass WHERE academic_year=? AND semester=?");
    $se->execute([$selected_year, $selected_sem]);
    foreach ($se->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['course_id']][$row['year_level']] = $row['section'];
    }
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
                                <?php foreach ($courses as $course): ?>
                                    <table class="table table-bordered mb-4">
                                        <thead class="thead-light">
                                            <tr>
                                                <th colspan="2"><?= htmlentities($course->CourseName) ?></th>
                                            </tr>
                                            <tr>
                                                <th>Year Level</th>
                                                <th>Number of Sections</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($year_levels as $yl_val): ?>
                                            <tr>
                                                <td><?= $yl_val ?></td>
                                                <td>
                                                    <input 
                                                        type="number" 
                                                        min="0" 
                                                        name="sections[<?= $course->ID ?>][<?= $yl_val ?>]" 
                                                        class="form-control" 
                                                        value="<?= isset($existing[$course->ID][$yl_val]) ? intval($existing[$course->ID][$yl_val]) : 0 ?>"
                                                        required
                                                    >
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endforeach; ?>
                                </div>
                                <button type="submit" name="save_sections" class="btn btn-success">Save</button>
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
<script src="../assets/js/lib/bootstrap.min.js"></script>
</body>
</html>