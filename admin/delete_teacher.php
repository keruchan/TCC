<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid']) == 0) {
    header('location:logout.php');
    exit;
}

if (isset($_GET['delid']) && is_numeric($_GET['delid'])) {
    $teacherID = intval($_GET['delid']);

    try {
        $dbh->beginTransaction();

        // Delete related records first
        $stmt1 = $dbh->prepare("DELETE FROM tblteachingload WHERE TeacherID = :tid");
        $stmt1->bindParam(':tid', $teacherID, PDO::PARAM_INT);
        $stmt1->execute();

        $stmt2 = $dbh->prepare("DELETE FROM tblskills WHERE TeacherID = :tid");
        $stmt2->bindParam(':tid', $teacherID, PDO::PARAM_INT);
        $stmt2->execute();

        $stmt3 = $dbh->prepare("DELETE FROM teacher_preferred_times WHERE teacher_id = :tid");
        $stmt3->bindParam(':tid', $teacherID, PDO::PARAM_INT);
        $stmt3->execute();

        $stmt4 = $dbh->prepare("DELETE FROM tblteacher WHERE TeacherID = :tid");
        $stmt4->bindParam(':tid', $teacherID, PDO::PARAM_INT);
        $stmt4->execute();

        $dbh->commit();
        echo "<script>alert('Teacher deleted successfully.'); window.location.href='manage-teacher.php';</script>";
    } catch (Exception $e) {
        $dbh->rollBack();
        echo "<script>alert('Failed to delete teacher: " . $e->getMessage() . "'); window.location.href='manage-teacher.php';</script>";
    }
} else {
    echo "<script>alert('Invalid request'); window.location.href='manage-teacher.php';</script>";
}
?>
