<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('includes/dbconnection.php');

// CSRF check
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['alloc_feedback'] = "Error: Invalid request or session expired. Please reload and try again.";
    header("Location: auto-allocation.php");
    exit;
}

// Authentication
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

// Get academic year and semester
$academic_year = $_POST['academic_year'] ?? '';
$semester = $_POST['semester'] ?? '';

if (!$academic_year || !$semester) {
    $_SESSION['alloc_feedback'] = "Error: Academic year or semester missing.";
    header("Location: auto-allocation.php");
    exit;
}

// Prepare for allocation insert/update
$alloc_success = 0;
$alloc_fail = 0;
$alloc_errors = [];

// Combine both qualified and no-instructor allocations
$all_sections = [];
foreach (['instructor', 'instructor_no'] as $alloc_type) {
    if (!empty($_POST[$alloc_type]) && is_array($_POST[$alloc_type])) {
        foreach ($_POST[$alloc_type] as $section_uid => $instructor_ids) {
            foreach ($instructor_ids as $row_idx => $instructor_id) {
                $row = [];
                // Room input
                $room_key = $alloc_type === 'instructor' ? "room" : "room_noinstructor";
                $room_val = $_POST[$room_key][$section_uid][$row_idx] ?? '';
                // Hidden fields
                $row['course'] = $_POST['course'][$section_uid][$row_idx] ?? '';
                $row['subject_id'] = $_POST['subject_id'][$section_uid][$row_idx] ?? '';
                $row['subject_code'] = $_POST['subject_code'][$section_uid][$row_idx] ?? '';
                $row['subject_name'] = $_POST['subject_name'][$section_uid][$row_idx] ?? '';
                $row['year_level'] = $_POST['year_level'][$section_uid][$row_idx] ?? '';
                $row['section'] = $_POST['section'][$section_uid][$row_idx] ?? '';
                $row['day_of_week'] = $_POST['day'][$section_uid][$row_idx] ?? '';
                $time_val = $_POST['time'][$section_uid][$row_idx] ?? '';
                $times = explode(' - ', $time_val);
                $row['start_time'] = $times[0] ?? '';
                $row['end_time']   = $times[1] ?? '';
                $row['duration_minutes'] = $_POST['duration'][$section_uid][$row_idx] ?? '';
                $row['instructor_id'] = $instructor_id;
                $row['room'] = $room_val;
                $all_sections[] = $row;
            }
        }
    }
}

// Get instructor names for mapping
$instructor_names = [];
$teacher_stmt = $dbh->prepare("SELECT TeacherID, CONCAT(FirstName, ' ', LastName) AS instructor_name FROM tblteacher");
$teacher_stmt->execute();
while ($row = $teacher_stmt->fetch(PDO::FETCH_ASSOC)) {
    $instructor_names[$row['TeacherID']] = $row['instructor_name'];
}
$instructor_names['xyz'] = "Professor XYZ";

// Save allocations to finalize_schedules table
foreach ($all_sections as $alloc) {
    // Skip if missing required fields
    if (
        empty($alloc['subject_id']) || empty($alloc['section']) || empty($alloc['day_of_week']) ||
        empty($alloc['start_time']) || empty($alloc['end_time']) || empty($alloc['instructor_id'])
    ) {
        $alloc_fail++;
        $alloc_errors[] = "Missing data for subject code: ".htmlentities($alloc['subject_code']).", section ".htmlentities($alloc['section']).".";
        continue;
    }
    $instructor_name = $instructor_names[$alloc['instructor_id']] ?? "Unknown";

    // Check if already exists (unique: academic_year, semester, subject_id, section, day_of_week, start_time, end_time)
    $check_sql = "SELECT id FROM finalize_schedules WHERE academic_year=? AND semester=? AND subject_id=? AND section=? AND day_of_week=? AND start_time=? AND end_time=?";
    $check_stmt = $dbh->prepare($check_sql);
    $check_stmt->execute([
        $academic_year,
        $semester,
        $alloc['subject_id'],
        $alloc['section'],
        $alloc['day_of_week'],
        $alloc['start_time'],
        $alloc['end_time']
    ]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update
        $upd_sql = "UPDATE finalize_schedules SET 
            course=?, subject_code=?, subject_name=?, year_level=?, 
            instructor_id=?, instructor_name=?, duration_minutes=?, room=?, last_updated=NOW()
            WHERE id=?";
        $upd_stmt = $dbh->prepare($upd_sql);
        $ok = $upd_stmt->execute([
            $alloc['course'],
            $alloc['subject_code'],
            $alloc['subject_name'],
            $alloc['year_level'],
            $alloc['instructor_id'],
            $instructor_name,
            $alloc['duration_minutes'],
            $alloc['room'],
            $existing['id']
        ]);
        if ($ok) $alloc_success++; else { $alloc_fail++; $alloc_errors[] = "Failed to update allocation for subject code: ".htmlentities($alloc['subject_code']).", section ".htmlentities($alloc['section'])."."; }
    } else {
        // Insert
        $ins_sql = "INSERT INTO finalize_schedules (
            academic_year, semester, course, subject_id, subject_code, subject_name, year_level, section,
            instructor_id, instructor_name, day_of_week, start_time, end_time, duration_minutes, room, last_updated
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $ins_stmt = $dbh->prepare($ins_sql);
        $ok = $ins_stmt->execute([
            $academic_year,
            $semester,
            $alloc['course'],
            $alloc['subject_id'],
            $alloc['subject_code'],
            $alloc['subject_name'],
            $alloc['year_level'],
            $alloc['section'],
            $alloc['instructor_id'],
            $instructor_name,
            $alloc['day_of_week'],
            $alloc['start_time'],
            $alloc['end_time'],
            $alloc['duration_minutes'],
            $alloc['room']
        ]);
        if ($ok) $alloc_success++; else { $alloc_fail++; $alloc_errors[] = "Failed to insert allocation for subject code: ".htmlentities($alloc['subject_code']).", section ".htmlentities($alloc['section'])."."; }
    }
}

// Feedback
if ($alloc_fail == 0) {
    $_SESSION['alloc_feedback'] = "Schedule finalized and saved successfully for $academic_year - $semester!";
} else {
    $_SESSION['alloc_feedback'] = "Schedule finalized with $alloc_fail error(s). $alloc_success successful. Errors:<br><ul><li>".implode('</li><li>', $alloc_errors)."</li></ul>";
}

header("Location: auto-allocation.php");
exit;
?>