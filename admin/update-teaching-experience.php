<?php
session_start();
include('includes/dbconnection.php');

// Ensure the session is active
if (strlen($_SESSION['tsasaid']) == 0) {
    echo "Unauthorized access.";
    exit;
}

// Get the posted data
$teachingLoadID = $_POST['teachingloadid'] ?? 0;
$verified = $_POST['verified'] ?? 0; // 1 for verified, 0 for unverified

// Validate the inputs
if (empty($teachingLoadID) || !in_array($verified, [0, 1])) {
    echo 'Invalid input.';
    exit;
}

// Update the teaching load's verification status in the database
$sql = "UPDATE tblteachingload SET Verified = :verified WHERE TeachingLoadID = :teachingLoadID";
$query = $dbh->prepare($sql);
$query->bindParam(':verified', $verified, PDO::PARAM_INT);
$query->bindParam(':teachingLoadID', $teachingLoadID, PDO::PARAM_INT);

// Execute the query and check if the update was successful
if ($query->execute()) {
    echo 'success';  // Update was successful
} else {
    echo 'error';    // Something went wrong
}
?>