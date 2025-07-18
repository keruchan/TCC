<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $_POST['course_id'] ?? null;
    $subject_id = $_POST['subject_id'] ?? null;
    $year_level = $_POST['year_level'] ?? null;
    $semester = $_POST['semester'] ?? null;

    if ($course_id && $subject_id && $year_level && $semester) {
        // Check if already exists
        $check = $dbh->prepare("SELECT COUNT(*) FROM tblcurriculum WHERE course_id = :course AND subject_id = :subject AND year_level = :year AND semester = :sem");
        $check->execute([
            ':course' => $course_id,
            ':subject' => $subject_id,
            ':year' => $year_level,
            ':sem' => $semester
        ]);

        if ($check->fetchColumn() > 0) {
            $_SESSION['msg'] = "Duplicate curriculum entry.";
            $_SESSION['msg_type'] = "warning";
        } else {
            $insert = $dbh->prepare("INSERT INTO tblcurriculum (course_id, subject_id, year_level, semester) VALUES (:course, :subject, :year, :sem)");
            $insert->execute([
                ':course' => $course_id,
                ':subject' => $subject_id,
                ':year' => $year_level,
                ':sem' => $semester
            ]);
            $_SESSION['msg'] = "Subject added to curriculum.";
            $_SESSION['msg_type'] = "success";
        }
    } else {
        $_SESSION['msg'] = "All fields are required.";
        $_SESSION['msg_type'] = "danger";
    }

    header('Location: curriculum.php');
    exit;
} else {
    header('Location: curriculum.php');
    exit;
}
?>
