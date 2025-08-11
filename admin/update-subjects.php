<?php
session_start();
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid']) == 0) {
    header('Location: logout.php');
    exit();
}

$teachingLoadID = $_POST['teachingloadid'] ?? 0;
$subjects = trim($_POST['subjects'] ?? '');

if (!$teachingLoadID || $subjects === '') {
    echo "<script>alert('Invalid request: Subjects cannot be empty.');window.history.back();</script>";
    exit;
}

$sql = "UPDATE tblteachingload SET SubjectsTaught = :subjects WHERE TeachingLoadID = :tid";
$query = $dbh->prepare($sql);
$query->bindParam(':subjects', $subjects, PDO::PARAM_STR);
$query->bindParam(':tid', $teachingLoadID, PDO::PARAM_INT);

if ($query->execute()) {
    // Fetch teacher ID to use as viewid GET param
    $stmt = $dbh->prepare("SELECT TeacherID FROM tblteachingload WHERE TeachingLoadID = :tid");
    $stmt->bindParam(':tid', $teachingLoadID, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $viewid = $row ? $row['TeacherID'] : '';
    echo "<script>alert('Subjects updated successfully!');window.location.href='teacher-profile.php?viewid=$viewid';</script>";
} else {
    echo "<script>alert('Failed to update subjects.');window.history.back();</script>";
}
?>