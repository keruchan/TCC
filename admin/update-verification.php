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
$type = $_POST['type'] ?? '';
$verified = $_POST['verified'] ?? 0; // 1 for verified, 0 for unverified

// Validate the inputs
if (empty($teacherID) || !in_array($verified, [0, 1]) || !in_array($type, ['degree', 'load', 'skill'])) {
    echo 'Invalid input.';
    exit;
}

if ($type === 'degree') {
    $degree = $_POST['degree'] ?? ''; // Degree type: bachelor, master, or doctorate
    if (empty($degree) || !in_array($degree, ['bachelor', 'master', 'doctorate'])) {
        echo 'Invalid degree type.';
        exit;
    }
    // Set the column based on the degree type
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
    }
    // Update the verification status for the degree
    $sql = "UPDATE tblteacher SET $column = :verified WHERE TeacherID = :teacherID";
    $query = $dbh->prepare($sql);
    $query->bindParam(':teacherID', $teacherID, PDO::PARAM_INT);
    $query->bindParam(':verified', $verified, PDO::PARAM_INT);

} elseif ($type === 'load') {
    $teachingLoadID = $_POST['id'] ?? 0;
    if (empty($teachingLoadID)) {
        echo 'Invalid teaching load ID.';
        exit;
    }
    // Update the verification status for the teaching load
    $sql = "UPDATE tblteachingload SET Verified = :verified WHERE TeachingLoadID = :teachingLoadID AND TeacherID = :teacherID";
    $query = $dbh->prepare($sql);
    $query->bindParam(':teacherID', $teacherID, PDO::PARAM_INT);
    $query->bindParam(':teachingLoadID', $teachingLoadID, PDO::PARAM_INT);
    $query->bindParam(':verified', $verified, PDO::PARAM_INT);

} elseif ($type === 'skill') {
    $skillID = $_POST['id'] ?? 0;
    if (empty($skillID)) {
        echo 'Invalid skill ID.';
        exit;
    }
    // Update the verification status for the skill
    $sql = "UPDATE tblskills SET Verified = :verified WHERE SkillID = :skillID AND TeacherID = :teacherID";
    $query = $dbh->prepare($sql);
    $query->bindParam(':teacherID', $teacherID, PDO::PARAM_INT);
    $query->bindParam(':skillID', $skillID, PDO::PARAM_INT);
    $query->bindParam(':verified', $verified, PDO::PARAM_INT);
}

// Execute the query and check if the update was successful
if ($query->execute()) {
    echo 'success';
} else {
    echo 'error';
}
?>
