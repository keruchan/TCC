<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid']) == 0) {
    header('location:logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacherid'])) {
    $teacherID = intval($_POST['teacherid']);
    // Accept an array or blank
    $courses_load = (isset($_POST['courses_load']) && is_array($_POST['courses_load']))
        ? implode(',', array_map('intval', $_POST['courses_load']))
        : '';

    // Update the field in tblteacher
    $sql = "UPDATE tblteacher SET CoursesLoad = :courses_load WHERE TeacherID = :teacherid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':courses_load', $courses_load, PDO::PARAM_STR);
    $query->bindParam(':teacherid', $teacherID, PDO::PARAM_INT);

    if ($query->execute()) {
        echo "<script>alert('Programs/Courses updated successfully.'); window.location='teacher-profile.php?viewid=" . $teacherID . "';</script>";
        exit;
    } else {
        echo "<script>alert('Failed to update programs/courses.'); window.location='teacher-profile.php?viewid=" . $teacherID . "';</script>";
        exit;
    }
} else {
    echo "<script>alert('Invalid request.'); window.location='manage-teacher.php';</script>";
    exit;
}
?>