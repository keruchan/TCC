<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

$admin_id = $_SESSION['tsasaid'] ?? null;
$selected_year = $_POST['academic_year'] ?? '';
$selected_sem = $_POST['semester'] ?? '';
$priorities = explode(',', $_POST['priorities'] ?? 'skills,educ,exp');

// Prioritization log
if ($selected_year && $selected_sem && !empty($priorities)) {
    $priority_order = implode(',', $priorities);
    $priority_log_stmt = $dbh->prepare("INSERT INTO tblallocation_priority_log (academic_year, semester, priority_order, generated_by, date_generated) VALUES (?, ?, ?, ?, NOW())");
    $priority_log_stmt->execute([$selected_year, $selected_sem, $priority_order, $admin_id]);
}

// Fetch all subject-sections, including time_duration
$sql = "SELECT 
            cl.id AS section_id,
            cl.course_id, 
            c.CourseName, 
            cur.subject_id, 
            s.subject_name AS SubjectName, 
            s.subject_code AS SubjectCode,
            s.description AS SubjectDesc,
            s.time_duration AS time_duration,
            cur.year_level, 
            cl.section
        FROM tblclass cl
        JOIN tblcourse c ON cl.course_id = c.ID
        JOIN tblcurriculum cur ON cur.course_id = cl.course_id AND cur.year_level = cl.year_level AND cur.semester = cl.semester
        JOIN tblsubject s ON cur.subject_id = s.ID
        WHERE cl.academic_year = ? AND cl.semester = ?
        ORDER BY c.CourseName, cl.year_level, s.subject_name, cl.section";
$stmt = $dbh->prepare($sql);
$stmt->execute([$selected_year, $selected_sem]);
$subject_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preferred instructors for each subject
$subject_preferred_teachers = [];
$pref_stmt = $dbh->prepare("SELECT subject_id, teacher_id FROM subject_teachers");
$pref_stmt->execute();
while ($row = $pref_stmt->fetch(PDO::FETCH_ASSOC)) {
    $subject_preferred_teachers[$row['subject_id']][] = $row['teacher_id'];
}

// List all qualified instructors (bachelor's degree verified, at least 1 verified skill)
$teachers = [];
$teacher_stmt = $dbh->prepare("SELECT * FROM tblteacher 
    WHERE BachelorsVerified=1 AND TeacherID IN (SELECT DISTINCT TeacherID FROM tblskills WHERE Verified=1)");
$teacher_stmt->execute();
while ($t = $teacher_stmt->fetch(PDO::FETCH_ASSOC)) {
    $id = $t['TeacherID'];
    $teachers[$id] = $t;
    $teachers[$id]['skills'] = [];
    $teachers[$id]['experience'] = [];
    $teachers[$id]['preferred_times'] = [];
    $teachers[$id]['current_load'] = 0;
    $teachers[$id]['assigned_dayslots'] = [];
    $teachers[$id]['assigned_sections'] = 0;
}

$skill_stmt = $dbh->prepare("SELECT TeacherID, SkillName FROM tblskills WHERE Verified=1");
$skill_stmt->execute();
while ($row = $skill_stmt->fetch(PDO::FETCH_ASSOC)) {
    $skills = array_map('trim', explode(',', strtolower($row['SkillName'])));
    if (isset($teachers[$row['TeacherID']])) {
        $current_skills = $teachers[$row['TeacherID']]['skills'] ?? [];
        $teachers[$row['TeacherID']]['skills'] = array_merge($current_skills, $skills);
    }
}
$exp_stmt = $dbh->prepare("SELECT TeacherID, SubjectsTaught FROM tblteachingload WHERE Verified=1");
$exp_stmt->execute();
while ($row = $exp_stmt->fetch(PDO::FETCH_ASSOC)) {
    $subjects = array_map('trim', explode(',', strtolower($row['SubjectsTaught'])));
    if (isset($teachers[$row['TeacherID']])) {
        $current_exp = $teachers[$row['TeacherID']]['experience'] ?? [];
        $teachers[$row['TeacherID']]['experience'] = array_merge($current_exp, $subjects);
    }
}
$pt_stmt = $dbh->prepare("SELECT teacher_id, day_of_week, time_slot FROM teacher_preferred_times");
$pt_stmt->execute();
while ($row = $pt_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($teachers[$row['teacher_id']])) {
        $teachers[$row['teacher_id']]['preferred_times'][] = strtolower($row['day_of_week']) . '-' . strtolower($row['time_slot']);
    }
}

// Day and slot/time mapping
$available_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
// Map slot to time ranges for each type (from schedules and parttime_schedules)
$schedule_times = [
    'morning' => ['start' => '07:30', 'end' => '11:30'],
    'afternoon' => ['start' => '13:00', 'end' => '17:00'],
    'evening' => ['start' => '17:30', 'end' => '20:30'],
];

// For convenience: evenly distribute sections among qualified instructors (round-robin)
function find_least_loaded_teacher($qualified_ids, &$teachers) {
    $min = PHP_INT_MAX; $chosen = null;
    foreach ($qualified_ids as $id) {
        if ($teachers[$id]['assigned_sections'] < $min) {
            $min = $teachers[$id]['assigned_sections'];
            $chosen = $id;
        }
    }
    return $chosen;
}

$allocations = [];
$alloc_cnt = 1;
$allocation_errors = [];
$section_letters = range('A', 'Z');

foreach ($subject_sections as $ss) {
    $subject_id = $ss['subject_id'];
    $section_id = $ss['section_id'];
    $num_sections = intval($ss['section']);

    // Parse session durations from time_duration (actual values, not fixed 90)
    $durations = [];
    if (isset($ss['time_duration']) && trim($ss['time_duration']) !== '') {
        $durations = array_map('intval', array_filter(array_map('trim', explode(',', $ss['time_duration']))));
    }
    if (empty($durations)) {
        // fallback: 2 meetings of 90 minutes each
        $durations = [90, 90];
    }
    $meetings_per_week = count($durations);

    $qualified_ids = array_keys($teachers);

    if (empty($qualified_ids)) {
        $allocation_errors[] = "No qualified instructors found for subject '{$ss['SubjectName']}' (ID: {$subject_id}).";
        continue;
    }

    for ($sec = 0; $sec < $num_sections; $sec++) {
        // Preferred instructor?
        $assigned_teacher_id = null;
        if (!empty($subject_preferred_teachers[$subject_id])) {
            foreach ($subject_preferred_teachers[$subject_id] as $ptid) {
                if (isset($teachers[$ptid])) {
                    $assigned_teacher_id = $ptid;
                    break;
                }
            }
        }
        if (!$assigned_teacher_id) {
            $assigned_teacher_id = find_least_loaded_teacher($qualified_ids, $teachers);
        }
        if (!$assigned_teacher_id) {
            $allocation_errors[] = "No instructor can be assigned for subject '{$ss['SubjectName']}', section " . ($section_letters[$sec] ?? ($sec+1)) . ".";
            continue;
        }
        $teacher = $teachers[$assigned_teacher_id];
        $teachers[$assigned_teacher_id]['assigned_sections']++;

        // Assign meetings per week, distribute days, avoid double-booking on the same day/slot for the same teacher
        $assigned_dayslots = [];
        $assigned_meetings = [];
        $used_dayslots = $teacher['assigned_dayslots'] ?? [];
        $m = 0;
        $used_days_this_section = [];
        foreach ($available_days as $day) {
            if ($m >= $meetings_per_week) break;
            // Try all slots for that day, prioritize teacher's preference
            $chosen_slot = null;
            $available_slots_this_day = ['morning', 'afternoon', 'evening'];
            foreach ($available_slots_this_day as $slot) {
                $dayslot_key = $day . '-' . $slot;
                $pref_key = strtolower($day) . '-' . strtolower($slot);
                // not used by this teacher for any subject yet, and not for this section
                if (!in_array($dayslot_key, $used_dayslots) && !in_array($dayslot_key, $assigned_dayslots)) {
                    // If teacher prefers this slot, pick it
                    if (isset($teacher['preferred_times']) && in_array($pref_key, $teacher['preferred_times'])) {
                        $chosen_slot = $slot;
                        break;
                    }
                }
            }
            // If not found by preference, just pick first available slot
            if (!$chosen_slot) {
                foreach ($available_slots_this_day as $slot) {
                    $dayslot_key = $day . '-' . $slot;
                    if (!in_array($dayslot_key, $used_dayslots) && !in_array($dayslot_key, $assigned_dayslots)) {
                        $chosen_slot = $slot;
                        break;
                    }
                }
            }
            // Still nothing? Allow reuse as last resort (should only happen if all slots are filled)
            if (!$chosen_slot) {
                $chosen_slot = $available_slots_this_day[0];
            }
            $dayslot_key = $day . '-' . $chosen_slot;
            $assigned_dayslots[] = $dayslot_key;

            // Assign time start/end for this slot
            $times = $schedule_times[$chosen_slot];
            $duration_minutes = $durations[$m] ?? $durations[0];
            // Compute end time based on start and duration
            $start_time = $times['start'];
            $startObj = DateTime::createFromFormat('H:i', $start_time);
            $endObj = clone $startObj;
            $endObj->modify("+{$duration_minutes} minutes");
            // If end time exceeds slot end, just cap at slot end
            $slot_endObj = DateTime::createFromFormat('H:i', $times['end']);
            if ($endObj > $slot_endObj) $endObj = $slot_endObj;
            $end_time = $endObj->format('H:i');
            $start_time = $startObj->format('H:i');
            $assigned_meetings[] = [
                'day' => $day,
                'time' => "{$start_time} - {$end_time}",
                'duration' => $duration_minutes
            ];
            $m++;
        }
        // Add to instructor's used day-slots
        $teachers[$assigned_teacher_id]['assigned_dayslots'] = array_merge($used_dayslots, $assigned_dayslots);

        // Save allocations (one row per meeting/session)
        foreach ($assigned_meetings as $idx => $meeting) {
            $allocations[] = [
                'num' => $alloc_cnt++,
                'instructor' => $teacher['FirstName'] . ' ' . $teacher['LastName'],
                'course' => $ss['CourseName'],
                'subject' => $ss['SubjectName'],
                'subject_code' => $ss['SubjectCode'],
                'year' => $ss['year_level'],
                'section' => $section_letters[$sec] ?? ($sec+1),
                'day' => $meeting['day'],
                'time' => $meeting['time'],
                'duration' => $meeting['duration']
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Auto-Generated Allocation Table</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <style>
        .auto-table-box { background:#fff; border-radius:12px; padding:25px 25px;margin-top:25px;}
        .auto-table-box h4 { margin-bottom: 18px; }
        .auto-alloc-table th, .auto-alloc-table td { text-align:center; vertical-align:middle; }
    </style>
</head>
<body>
<?php include_once('includes/sidebar.php'); ?>
<?php include_once('includes/header.php'); ?>
<div class="content-wrap">
    <div class="main">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="page-header">
                        <div class="page-title">
                            <h1>Auto-Generated Subject Allocation Table</h1>
                        </div>
                    </div>
                    <div class="auto-table-box card alert">
                        <div class="card-header">
                            <h4>Allocation Results</h4>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($allocation_errors)): ?>
                                <div class="alert alert-danger">
                                    <b>Allocation Warnings/Errors:</b>
                                    <ul>
                                        <?php foreach ($allocation_errors as $err): ?>
                                            <li><?= htmlentities($err) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table id="allocationTable" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Instructor</th>
                                            <th>Course</th>
                                            <th>Subject</th>
                                            <th>Code</th>
                                            <th>Year</th>
                                            <th>Section</th>
                                            <th>Day</th>
                                            <th>Time (Start-End)</th>
                                            <th>Duration (min)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($allocations as $alloc): ?>
                                        <tr>
                                            <td><?= $alloc['num'] ?></td>
                                            <td><?= htmlentities($alloc['instructor']) ?></td>
                                            <td><?= htmlentities($alloc['course']) ?></td>
                                            <td><?= htmlentities($alloc['subject']) ?></td>
                                            <td><?= htmlentities($alloc['subject_code']) ?></td>
                                            <td><?= htmlentities($alloc['year']) ?></td>
                                            <td><?= htmlentities($alloc['section']) ?></td>
                                            <td><?= htmlentities($alloc['day']) ?></td>
                                            <td><?= htmlentities($alloc['time']) ?></td>
                                            <td><?= htmlentities($alloc['duration']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($allocations)): ?>
                                        <tr><td colspan="10"><i>No allocation generated.</i></td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <a href="subject-allocation.php" class="btn btn-secondary mt-3">Back to Subject Allocation</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../assets/js/lib/jquery.min.js"></script>
<script src="../assets/js/lib/jquery.nanoscroller.min.js"></script>
<script src="../assets/js/lib/menubar/sidebar.js"></script>
<script src="../assets/js/lib/preloader/pace.min.js"></script>
<script src="../assets/js/lib/bootstrap.min.js"></script>
<script src="../assets/js/scripts.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#allocationTable').DataTable();
});
</script>
</body>
</html>