<?php
session_start();
include('includes/dbconnection.php');

// Ensure the session is active
if (strlen($_SESSION['tsasaid']) == 0) {
    echo "Unauthorized access.";
    exit;
}

// Get the posted data
$teacherID = $_POST['teacherid'] ?? 0;
$degree = $_POST['degree'] ?? '';
$verified = $_POST['verified'] ?? 0; // 1 for verified, 0 for unverified

// Validate the inputs
if (empty($teacherID) || empty($degree) || !in_array($degree, ['bachelor', 'master', 'doctorate'])) {
    echo 'Invalid input or degree type.';
    exit;
}

// Determine the corresponding column based on the degree
$column = '';
switch ($degree) {
    case 'bachelor':
        $column = 'BachelorsVerified';
        break;
    case 'master':
        $column = 'MastersVerified';
        break;
    case 'doctorate':
        $column = 'DoctorateVerified';
        break;
    default:
        echo 'Invalid degree type.';
        exit;
}

// Update the teacher's verification status in the database
$sql = "UPDATE tblteacher SET $column = :verified WHERE TeacherID = :teacherID";
$query = $dbh->prepare($sql);
$query->bindParam(':verified', $verified, PDO::PARAM_INT);
$query->bindParam(':teacherID', $teacherID, PDO::PARAM_INT);

// Execute the query and check if the update was successful
if ($query->execute()) {
    echo 'success';  // Update was successful
} else {
    echo 'error';    // Something went wrong
}
?>
