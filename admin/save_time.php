<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_type'], $_POST['days'])) {
    $scheduleType = trim($_POST['schedule_type']);
    $daysOfWeek = trim($_POST['days']);

    try {
        $dbh->beginTransaction();

        if ($scheduleType === 'Regular') {
            if (!isset($_POST['start_time'], $_POST['end_time'])) {
                throw new Exception('Start and end time required for Regular schedule.');
            }
            $startTime = trim($_POST['start_time']);
            $endTime = trim($_POST['end_time']);

            $checkSql = "SELECT id FROM schedules WHERE schedule_type = 'Regular'";
            $stmt = $dbh->prepare($checkSql);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $updateSql = "UPDATE schedules SET days_of_week = :days, start_time = :start, end_time = :end, updated_at = NOW() WHERE schedule_type = 'Regular'";
                $stmt = $dbh->prepare($updateSql);
                $stmt->execute([
                    ':days' => $daysOfWeek,
                    ':start' => $startTime,
                    ':end' => $endTime
                ]);
            } else {
                $insertSql = "INSERT INTO schedules (schedule_type, days_of_week, start_time, end_time) VALUES ('Regular', :days, :start, :end)";
                $stmt = $dbh->prepare($insertSql);
                $stmt->execute([
                    ':days' => $daysOfWeek,
                    ':start' => $startTime,
                    ':end' => $endTime
                ]);
            }

        } elseif ($scheduleType === 'Part-time') {
            $fields = ['morning_start', 'morning_end', 'afternoon_start', 'afternoon_end', 'night_start', 'night_end'];
            $times = [];
            foreach ($fields as $field) {
                $times[$field] = trim($_POST[$field] ?? '');
            }

            $checkSql = "SELECT id FROM parttime_schedules LIMIT 1";
            $stmt = $dbh->prepare($checkSql);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $updateSql = "UPDATE parttime_schedules SET 
                                days_of_week = :days,
                                morning_start = :morning_start,
                                morning_end = :morning_end,
                                afternoon_start = :afternoon_start,
                                afternoon_end = :afternoon_end,
                                night_start = :night_start,
                                night_end = :night_end,
                                updated_at = NOW()
                              WHERE id = (SELECT id FROM parttime_schedules LIMIT 1)";
                $stmt = $dbh->prepare($updateSql);
            } else {
                $updateSql = "INSERT INTO parttime_schedules 
                                (days_of_week, morning_start, morning_end, afternoon_start, afternoon_end, night_start, night_end)
                              VALUES 
                                (:days, :morning_start, :morning_end, :afternoon_start, :afternoon_end, :night_start, :night_end)";
                $stmt = $dbh->prepare($updateSql);
            }

            $stmt->execute([
                ':days' => $daysOfWeek,
                ':morning_start' => $times['morning_start'],
                ':morning_end' => $times['morning_end'],
                ':afternoon_start' => $times['afternoon_start'],
                ':afternoon_end' => $times['afternoon_end'],
                ':night_start' => $times['night_start'],
                ':night_end' => $times['night_end']
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
}

$_SESSION['msg'] = 'Invalid request.';
$_SESSION['msg_type'] = 'danger';
header('Location: time.php');
exit;
?>
