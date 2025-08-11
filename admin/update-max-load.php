<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid']) == 0) {
    header('location:logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacherID = isset($_POST['teacherid']) ? intval($_POST['teacherid']) : 0;
    $maxLoad = isset($_POST['maxload']) ? intval($_POST['maxload']) : null;
    $defaultMaxLoad = 18;

    // Validation: Max load should not be negative, allow 0 for "unlimited"
    if ($maxLoad === null || $maxLoad === '') {
        $maxLoad = $defaultMaxLoad;
    } elseif ($maxLoad < 0) {
        $_SESSION['error'] = "Invalid max load value.";
        header("Location: teacher-profile.php?viewid=$teacherID");
        exit;
    }

    // Update the teacher's MaxLoad
    $sql = "UPDATE tblteacher SET MaxLoad = :maxload WHERE TeacherID = :tid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':maxload', $maxLoad, PDO::PARAM_INT);
    $query->bindParam(':tid', $teacherID, PDO::PARAM_INT);

    if ($query->execute()) {
        $_SESSION['success'] = "Maximum teaching load updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update maximum teaching load.";
    }

    header("Location: teacher-profile.php?viewid=$teacherID");
    exit;
} else {
    // If not a POST request, redirect back
    header('location:manage-teacher.php');
    exit;
}
?>