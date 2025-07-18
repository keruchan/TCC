<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_type'], $_POST['days'], $_POST['start_time'], $_POST['end_time'])) {
    $scheduleType = trim($_POST['schedule_type']);
    $daysOfWeek = trim($_POST['days']); // comma-separated string like "Mon,Tue,Fri"
    $startTime = trim($_POST['start_time']);
    $endTime = trim($_POST['end_time']);

    if (!in_array($scheduleType, ['Regular', 'Part-time'])) {
        $_SESSION['msg'] = 'Invalid schedule type.';
        $_SESSION['msg_type'] = 'danger';
        header('Location: time.php');
        exit;
    }

    try {
        $dbh->beginTransaction();

        // Check if this schedule type already exists
        $checkSql = "SELECT id FROM schedules WHERE schedule_type = :schedule_type";
        $checkStmt = $dbh->prepare($checkSql);
        $checkStmt->bindParam(':schedule_type', $scheduleType, PDO::PARAM_STR);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            // Update existing schedule
            $updateSql = "UPDATE schedules SET days_of_week = :days_of_week, start_time = :start_time, end_time = :end_time, updated_at = NOW() WHERE schedule_type = :schedule_type";
            $updateStmt = $dbh->prepare($updateSql);
            $updateStmt->execute([
                ':days_of_week' => $daysOfWeek,
                ':start_time' => $startTime,
                ':end_time' => $endTime,
                ':schedule_type' => $scheduleType
            ]);
        } else {
            // Insert new schedule
            $insertSql = "INSERT INTO schedules (schedule_type, days_of_week, start_time, end_time) VALUES (:schedule_type, :days_of_week, :start_time, :end_time)";
            $insertStmt = $dbh->prepare($insertSql);
            $insertStmt->execute([
                ':schedule_type' => $scheduleType,
                ':days_of_week' => $daysOfWeek,
                ':start_time' => $startTime,
                ':end_time' => $endTime
            ]);
        }

        $dbh->commit();
        $_SESSION['msg'] = ucfirst($scheduleType) . ' schedule saved successfully.';
        $_SESSION['msg_type'] = 'success';
    } catch (Exception $e) {
        $dbh->rollBack();
        $_SESSION['msg'] = 'Error saving schedule: ' . $e->getMessage();
        $_SESSION['msg_type'] = 'danger';
    }

    header('Location: time.php');
    exit;
} else {
    $_SESSION['msg'] = 'Invalid request.';
    $_SESSION['msg_type'] = 'danger';
    header('Location: time.php');
    exit;
}
?>
