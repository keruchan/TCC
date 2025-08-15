<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('includes/dbconnection.php');

$academic_year = $_POST['academic_year'] ?? '';
$semester = $_POST['semester'] ?? '';
$user = $_SESSION['tsasaid'] ?? '';

function parse_time($period) {
    $times = explode(' - ', $period);
    return [trim($times[0]), trim($times[1])];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $academic_year && $semester) {
    // Remove old schedule for the same ay/sem to avoid duplication
    $del = $dbh->prepare("DELETE FROM finalize_schedules WHERE academic_year=? AND semester=?");
    $del->execute([$academic_year, $semester]);

    // Process qualified allocations
    if (isset($_POST['instructor']) && is_array($_POST['instructor'])) {
        foreach ($_POST['instructor'] as $section_uid => $instructors) {
            foreach ($instructors as $row_idx => $instructor_id) {
                $room = $_POST['room'][$section_uid][$row_idx] ?? '';
                $course         = $_POST['course'][$section_uid][$row_idx] ?? '';
                $subject_id     = $_POST['subject_id'][$section_uid][$row_idx] ?? '';
                $subject_code   = $_POST['subject_code'][$section_uid][$row_idx] ?? '';
                $subject_name   = $_POST['subject_name'][$section_uid][$row_idx] ?? '';
                $year_level     = $_POST['year_level'][$section_uid][$row_idx] ?? '';
                $section        = $_POST['section'][$section_uid][$row_idx] ?? '';
                $day_of_week    = $_POST['day'][$section_uid][$row_idx] ?? '';
                $period         = $_POST['time'][$section_uid][$row_idx] ?? '';
                $duration       = $_POST['duration'][$section_uid][$row_idx] ?? '';

                list($start_time, $end_time) = parse_time($period);

                // Get instructor name
                if ($instructor_id === 'xyz') {
                    $instructor_name = 'Professor XYZ';
                } else {
                    $stmt = $dbh->prepare("SELECT CONCAT(FirstName, ' ', LastName) as name FROM tblteacher WHERE TeacherID = ?");
                    $stmt->execute([$instructor_id]);
                    $instructor_name = $stmt->fetchColumn() ?: '';
                }

                $stmt = $dbh->prepare("INSERT INTO finalize_schedules
                (academic_year, semester, course, subject_id, subject_code, subject_name, year_level, section,
                 instructor_id, instructor_name, day_of_week, start_time, end_time, duration_minutes, room)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $academic_year, $semester, $course, $subject_id, $subject_code, $subject_name,
                    $year_level, $section, $instructor_id, $instructor_name, $day_of_week,
                    $start_time, $end_time, $duration, $room
                ]);
            }
        }
    }
    // Process no-instructor allocations
    if (isset($_POST['instructor_no']) && is_array($_POST['instructor_no'])) {
        foreach ($_POST['instructor_no'] as $section_uid => $instructors) {
            foreach ($instructors as $row_idx => $instructor_id) {
                $room = $_POST['room_noinstructor'][$section_uid][$row_idx] ?? '';
                $course         = $_POST['course'][$section_uid][$row_idx] ?? '';
                $subject_id     = $_POST['subject_id'][$section_uid][$row_idx] ?? '';
                $subject_code   = $_POST['subject_code'][$section_uid][$row_idx] ?? '';
                $subject_name   = $_POST['subject_name'][$section_uid][$row_idx] ?? '';
                $year_level     = $_POST['year_level'][$section_uid][$row_idx] ?? '';
                $section        = $_POST['section'][$section_uid][$row_idx] ?? '';
                $day_of_week    = $_POST['day'][$section_uid][$row_idx] ?? '';
                $period         = $_POST['time'][$section_uid][$row_idx] ?? '';
                $duration       = $_POST['duration'][$section_uid][$row_idx] ?? '';

                list($start_time, $end_time) = parse_time($period);

                if ($instructor_id === 'xyz') {
                    $instructor_name = 'Professor XYZ';
                } else {
                    $stmt = $dbh->prepare("SELECT CONCAT(FirstName, ' ', LastName) as name FROM tblteacher WHERE TeacherID = ?");
                    $stmt->execute([$instructor_id]);
                    $instructor_name = $stmt->fetchColumn() ?: '';
                }

                $stmt = $dbh->prepare("INSERT INTO finalize_schedules
                (academic_year, semester, course, subject_id, subject_code, subject_name, year_level, section,
                 instructor_id, instructor_name, day_of_week, start_time, end_time, duration_minutes, room)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    $academic_year, $semester, $course, $subject_id, $subject_code, $subject_name,
                    $year_level, $section, $instructor_id, $instructor_name, $day_of_week,
                    $start_time, $end_time, $duration, $room
                ]);
            }
        }
    }

    header("Location: auto-allocation.php?finalized=1");
    exit;
}
?>