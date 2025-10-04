<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseid = intval($_POST['course_id'] ?? 0);
    $coursename = trim($_POST['coursename'] ?? '');
    $coursedesc = trim($_POST['coursedesc'] ?? '');

    if ($courseid > 0 && $coursename !== '') {
        $sql = "UPDATE tblcourse SET CourseName=:coursename, CourseDesc=:coursedesc WHERE ID=:courseid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':coursename', $coursename, PDO::PARAM_STR);
        $query->bindParam(':coursedesc', $coursedesc, PDO::PARAM_STR);
        $query->bindParam(':courseid', $courseid, PDO::PARAM_INT);

        if ($query->execute()) {
            $_SESSION['msg'] = "Course updated successfully.";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "Update failed. Try again.";
            $_SESSION['msg_type'] = "danger";
        }
    } else {
        $_SESSION['msg'] = "Invalid input.";
        $_SESSION['msg_type'] = "warning";
    }

    header("Location: course.php");
    exit;
} else {
    header("Location: course.php");
    exit;
}
?>
