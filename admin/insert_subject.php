<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

if (isset($_POST['submit'])) {
    $subject_name    = trim($_POST['subject_name']);
    $subject_code    = trim($_POST['subject_code']);
    $units           = intval($_POST['units']);
    $description     = trim($_POST['description']);
    $tags            = $_POST['tags'] ?? [];
    $tsasaid         = $_SESSION['tsasaid'];

    // Combine time durations
    $num_meetings    = intval($_POST['num_meetings']);
    $time_durations  = [];
    for ($i = 1; $i <= $num_meetings; $i++) {
        $duration = intval($_POST['time_duration_' . $i] ?? 0);
        if ($duration > 0) {
            $time_durations[] = $duration;
        }
    }
    $combined_duration = implode(',', $time_durations);

    // Capture preferred teachers
    $preferred_teachers = $_POST['preferred_teachers'] ?? [];

    try {
        // Insert into tblsubject with time_duration
        $sql = "INSERT INTO tblsubject(subject_name, subject_code, units, description, time_duration, date_created)
                VALUES(:subject_name, :subject_code, :units, :description, :time_duration, NOW())";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':subject_name', $subject_name, PDO::PARAM_STR);
        $stmt->bindParam(':subject_code', $subject_code, PDO::PARAM_STR);
        $stmt->bindParam(':units', $units, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':time_duration', $combined_duration, PDO::PARAM_STR);
        $stmt->execute();
        $subject_id = $dbh->lastInsertId();

        // Insert tags into tblqualification
        if ($subject_id > 0 && !empty($tags)) {
            $tagString = strtolower(implode(',', array_unique(array_map('trim', $tags))));
            $qsql = "INSERT INTO tblqualification(subject_id, tags) VALUES(:subject_id, :tags)";
            $qstmt = $dbh->prepare($qsql);
            $qstmt->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
            $qstmt->bindParam(':tags', $tagString, PDO::PARAM_STR);
            $qstmt->execute();
        }

        // Insert preferred teachers into subject_teachers table
        if ($subject_id > 0 && !empty($preferred_teachers)) {
            foreach ($preferred_teachers as $teacher_id) {
                $teacher_sql = "INSERT INTO subject_teachers(subject_id, teacher_id) VALUES(:subject_id, :teacher_id)";
                $teacher_stmt = $dbh->prepare($teacher_sql);
                $teacher_stmt->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
                $teacher_stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
                $teacher_stmt->execute();
            }
        }

        $_SESSION['msg'] = "Subject successfully added.";
        $_SESSION['msg_type'] = "success";
        header("Location: subject.php");
        exit;

    } catch (PDOException $e) {
        $_SESSION['msg'] = "Error: " . $e->getMessage();
        $_SESSION['msg_type'] = "danger";
        header("Location: subject.php");
        exit;
    }
} else {
    header("Location: subject.php");
    exit;
}
?>