<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coursename'], $_POST['coursedesc'])) {
    $coursename = trim($_POST['coursename']);
    $coursedesc = trim($_POST['coursedesc']);

    try {
        $dbh->beginTransaction();

        $sql = "INSERT INTO tblcourse(CourseName, CourseDesc) VALUES (:coursename, :coursedesc)";
        $query = $dbh->prepare($sql);
        $query->bindParam(':coursename', $coursename, PDO::PARAM_STR);
        $query->bindParam(':coursedesc', $coursedesc, PDO::PARAM_STR);
        $query->execute();

        $courseId = $dbh->lastInsertId();

        if ($courseId > 0 && isset($_POST['subject_id'], $_POST['year_level'], $_POST['semester'])) {
            $subjects = $_POST['subject_id'];
            $years = $_POST['year_level'];
            $sems = $_POST['semester'];

            $curriculumStmt = $dbh->prepare("INSERT INTO tblcurriculum (course_id, subject_id, year_level, semester) VALUES (:course_id, :subject_id, :year_level, :semester)");

            for ($i = 0; $i < count($subjects); $i++) {
                if (!empty($subjects[$i]) && !empty($years[$i]) && !empty($sems[$i])) {
                    $curriculumStmt->execute([
                        ':course_id' => $courseId,
                        ':subject_id' => $subjects[$i],
                        ':year_level' => $years[$i],
                        ':semester' => $sems[$i]
                    ]);
                }
            }
        }

        $dbh->commit();
        $_SESSION['msg'] = 'Course and curriculum successfully added.';
        $_SESSION['msg_type'] = 'success';
    } catch (Exception $e) {
        $dbh->rollBack();
        $_SESSION['msg'] = 'Error: ' . $e->getMessage();
        $_SESSION['msg_type'] = 'danger';
    }

    header('Location: add_course.php');
    exit;
} else {
    $_SESSION['msg'] = 'Invalid request.';
    $_SESSION['msg_type'] = 'danger';
    header('Location: add_course.php');
    exit;
}
?>
