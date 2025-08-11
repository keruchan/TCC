<?php
session_start();
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid']) == 0) {
    header('Location: logout.php');
    exit();
}

$skillID = $_POST['skillid'] ?? 0;
$skills = trim($_POST['skills'] ?? '');

if (!$skillID || $skills === '') {
    echo "<script>alert('Invalid request: Skills cannot be empty.');window.history.back();</script>";
    exit;
}

$sql = "UPDATE tblskills SET SkillName = :skills WHERE SkillID = :sid";
$query = $dbh->prepare($sql);
$query->bindParam(':skills', $skills, PDO::PARAM_STR);
$query->bindParam(':sid', $skillID, PDO::PARAM_INT);

if ($query->execute()) {
    // Fetch teacher ID for viewid
    $stmt = $dbh->prepare("SELECT TeacherID FROM tblskills WHERE SkillID = :sid");
    $stmt->bindParam(':sid', $skillID, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $viewid = $row ? $row['TeacherID'] : '';
    echo "<script>alert('Skills updated successfully!');window.location.href='teacher-profile.php?viewid=$viewid';</script>";
} else {
    echo "<script>alert('Failed to update skills.');window.history.back();</script>";
}
?>