<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Ensure the session is active
if (strlen($_SESSION['tsasaid']) == 0) {
    echo "Unauthorized access.";
    exit;
}

// Get the posted data
$teacherID = $_POST['teacherid'] ?? 0;
$employmentType = $_POST['employment_type'] ?? '';

// Check if teacherID and employment_type are provided
if (empty($teacherID) || empty($employmentType)) {
    echo 'Missing teacher ID or employment type';
    exit;
}

// Check if the teacher exists in the database
$sql = "SELECT * FROM tblteacher WHERE TeacherID = :tid";
$query = $dbh->prepare($sql);
$query->bindParam(':tid', $teacherID, PDO::PARAM_INT);
$query->execute();
$teacher = $query->fetch(PDO::FETCH_OBJ);

if (!$teacher) {
    echo 'Teacher not found';
    exit;
}

// Update the employment type for the teacher
$sql = "UPDATE tblteacher SET EmploymentType = :employmentType WHERE TeacherID = :tid";
$query = $dbh->prepare($sql);
$query->bindParam(':employmentType', $employmentType, PDO::PARAM_STR);
$query->bindParam(':tid', $teacherID, PDO::PARAM_INT);

if ($query->execute()) {
    // If the update is successful, redirect back with a success alert
    echo "<script>
            alert('Employment type updated successfully!');
            window.location.href = 'teacher-profile.php?viewid=" . $teacherID . "';
          </script>";
} else {
    // If there's an error, show an alert
    echo "<script>
            alert('There was an error updating the employment type.');
            window.location.href = 'teacher-profile.php?viewid=" . $teacherID . "';
          </script>";
}
?>
