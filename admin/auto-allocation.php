<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('includes/dbconnection.php');

// Authentication
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

$admin_id = $_SESSION['tsasaid'] ?? null;
$selected_year = $_POST['academic_year'] ?? '';
$selected_sem = $_POST['semester'] ?? '';
$priorities = explode(',', $_POST['priorities'] ?? 'skills,educ,exp');

// Priority log
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

// List all instructors (qualified and not)
$teachers = [];
$teacher_stmt = $dbh->prepare("SELECT * FROM tblteacher");
$teacher_stmt->execute();
while ($t = $teacher_stmt->fetch(PDO::FETCH_ASSOC)) {
    $id = $t['TeacherID'];
    $teachers[$id] = $t;
    $teachers[$id]['skills'] = [];
    $teachers[$id]['experience'] = [];
    $teachers[$id]['preferred_times'] = [];
    $teachers[$id]['assigned_sections'] = 0;
    $teachers[$id]['schedule_blocks'] = [];
}

// Fetch all skills (verified and not)
$skill_stmt = $dbh->prepare("SELECT TeacherID, SkillName FROM tblskills");
$skill_stmt->execute();
while ($row = $skill_stmt->fetch(PDO::FETCH_ASSOC)) {
    $skills = array_map('trim', explode(',', strtolower($row['SkillName'])));
    if (isset($teachers[$row['TeacherID']])) {
        $teachers[$row['TeacherID']]['skills'] = array_merge($teachers[$row['TeacherID']]['skills'], $skills);
    }
}

// Experience
$exp_stmt = $dbh->prepare("SELECT TeacherID, SubjectsTaught FROM tblteachingload");
$exp_stmt->execute();
while ($row = $exp_stmt->fetch(PDO::FETCH_ASSOC)) {
    $subjects = array_map('trim', explode(',', strtolower($row['SubjectsTaught'])));
    if (isset($teachers[$row['TeacherID']])) {
        $teachers[$row['TeacherID']]['experience'] = array_merge($teachers[$row['TeacherID']]['experience'], $subjects);
    }
}

// Teacher preferred times
$pt_stmt = $dbh->prepare("SELECT teacher_id, day_of_week, time_slot FROM teacher_preferred_times");
$pt_stmt->execute();
while ($row = $pt_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($teachers[$row['teacher_id']])) {
        $teachers[$row['teacher_id']]['preferred_times'][] = strtolower($row['day_of_week']) . '-' . strtolower($row['time_slot']);
    }
}

// Fetch subject skill tags (for fuzzy/related matching)
$subject_skill_tags = [];
$qual_stmt = $dbh->prepare("SELECT subject_id, tags FROM tblqualification");
$qual_stmt->execute();
while ($row = $qual_stmt->fetch(PDO::FETCH_ASSOC)) {
    $tags = array_map('trim', explode(',', strtolower($row['tags'])));
    $subject_skill_tags[$row['subject_id']] = $tags;
}

// Day and slot/time mapping
$available_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$schedule_times = [
    'morning' => ['start' => '07:30', 'end' => '11:30'],
    'afternoon' => ['start' => '13:00', 'end' => '17:00'],
    'evening' => ['start' => '17:30', 'end' => '20:30'],
];

// Helper: Time functions
function time_to_minutes($time) {
    list($h, $m) = explode(':', $time);
    return $h * 60 + $m;
}
function minutes_to_time($minutes) {
    $h = floor($minutes / 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d', $h, $m);
}
function has_time_overlap($blocks, $day, $start_min, $end_min) {
    foreach ($blocks as $block) {
        if ($block['day'] === $day) {
            if ($start_min < $block['end'] && $end_min > $block['start']) {
                return true;
            }
        }
    }
    return false;
}

// Fuzzy skill matching: typo/case-insensitive/related
function fuzzy_skill_match($teacher_skills, $subject_tags) {
    foreach ($teacher_skills as $skill) {
        foreach ($subject_tags as $tag) {
            if (strcasecmp($skill, $tag) === 0) return true;
            if (levenshtein($skill, $tag) <= 2) return true;
            if (stripos($skill, $tag) !== false || stripos($tag, $skill) !== false) return true;
        }
    }
    return false;
}

// Even distribution (not using MaxLoad)
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
$allocation_errors = [];
$alloc_cnt = 1;
$section_letters = range('A', 'Z');
$section_schedules = []; // $section_schedules[section_id][section_letter][] = [day, start, end]

foreach ($subject_sections as $ss) {
    $subject_id = $ss['subject_id'];
    $section_id = $ss['section_id'];
    $num_sections = intval($ss['section']);

    // Parse session durations from time_duration (e.g. "180,120" → [180,120])
    $durations = [];
    if (isset($ss['time_duration']) && trim($ss['time_duration']) !== '') {
        $durations = array_map('intval', array_filter(array_map('trim', explode(',', $ss['time_duration']))));
    }
    if (empty($durations)) $durations = [90, 90];
    $meetings_per_week = count($durations);

    $subject_tags = $subject_skill_tags[$subject_id] ?? [];

    // Build qualified instructor list (fuzzy/related skill match, or preferred instructor exception)
    $qualified_ids = [];
    foreach ($teachers as $tid => $tdata) {
        if (!empty($subject_preferred_teachers[$subject_id]) && in_array($tid, $subject_preferred_teachers[$subject_id])) {
            $qualified_ids[] = $tid;
            continue;
        }
        if ($tdata['BachelorsVerified'] == 1 && !empty($tdata['skills'])) {
            if (empty($subject_tags) || fuzzy_skill_match($tdata['skills'], $subject_tags)) {
                $qualified_ids[] = $tid;
            }
        }
    }
    if (empty($qualified_ids)) {
        $allocation_errors[] = "No qualified instructors found for subject '{$ss['SubjectName']}' (ID: {$subject_id}).";
        continue;
    }

    for ($sec = 0; $sec < $num_sections; $sec++) {
        $section_code = $section_letters[$sec] ?? ($sec+1);

        // Assign instructor: always prioritize preferred (even if not qualified)
        $preferred_ids = $subject_preferred_teachers[$subject_id] ?? [];
        $assigned_teacher_id = null;
        foreach ($preferred_ids as $pid) {
            if (isset($teachers[$pid])) { $assigned_teacher_id = $pid; break; }
        }
        if (!$assigned_teacher_id) {
            $assigned_teacher_id = find_least_loaded_teacher($qualified_ids, $teachers);
        }
        if (!$assigned_teacher_id) {
            $allocation_errors[] = "No instructor can be assigned for subject '{$ss['SubjectName']}', section $section_code.";
            continue;
        }
        $teacher = $teachers[$assigned_teacher_id];
        $teachers[$assigned_teacher_id]['assigned_sections']++;

        // Schedule block arrays
        $instructor_blocks = $teachers[$assigned_teacher_id]['schedule_blocks'];
        $section_blocks = isset($section_schedules[$section_id][$section_code]) ? $section_schedules[$section_id][$section_code] : [];
        $assigned_meetings = [];
        $used_days = []; // For different-day constraint

        for ($m = 0; $m < $meetings_per_week; $m++) {
            $duration_minutes = $durations[$m];
            $meeting_assigned = false;
            $day_slot_options = [];

            // Preferred slots first
            foreach ($available_days as $day) {
                if (in_array($day, $used_days)) continue; // Don't reuse day for another meeting for this section/subject
                foreach (['morning','afternoon','evening'] as $slot) {
                    $pref_key = strtolower($day) . '-' . strtolower($slot);
                    $is_preference = (isset($teacher['preferred_times']) && in_array($pref_key, $teacher['preferred_times']));
                    if (!empty($teacher['preferred_times']) && !$is_preference) continue;
                    $day_slot_options[] = [$day, $slot];
                }
            }
            // If still not found, try all slots (on unused days)
            if (empty($day_slot_options)) {
                foreach ($available_days as $day) {
                    if (in_array($day, $used_days)) continue;
                    foreach (['morning','afternoon','evening'] as $slot) {
                        $day_slot_options[] = [$day, $slot];
                    }
                }
            }

            foreach ($day_slot_options as $ds) {
                list($day, $slot) = $ds;
                $slot_time = $schedule_times[$slot];
                $slot_start_min = time_to_minutes($slot_time['start']);
                $slot_end_min = time_to_minutes($slot_time['end']);
                for ($block_start = $slot_start_min; $block_start + $duration_minutes <= $slot_end_min; $block_start += 10) {
                    $block_end = $block_start + $duration_minutes;
                    if (!has_time_overlap($instructor_blocks, $day, $block_start, $block_end)
                        && !has_time_overlap($section_blocks, $day, $block_start, $block_end)) {
                        $start_time = minutes_to_time($block_start);
                        $end_time = minutes_to_time($block_end);
                        $assigned_meetings[] = [
                            'day' => $day,
                            'time' => "{$start_time} - {$end_time}",
                            'duration' => $duration_minutes,
                            'room' => '' // default empty, editable
                        ];
                        $instructor_blocks[] = ['day'=>$day, 'start'=>$block_start, 'end'=>$block_end];
                        $section_blocks[] = ['day'=>$day, 'start'=>$block_start, 'end'=>$block_end];
                        $used_days[] = $day;
                        $meeting_assigned = true;
                        break 2;
                    }
                }
            }
            if (!$meeting_assigned) {
                $allocation_errors[] = "Could not assign meeting ".($m+1)." (duration {$duration_minutes} min) for subject '{$ss['SubjectName']}', section $section_code. No available time slot found.";
            }
        }
        $teachers[$assigned_teacher_id]['schedule_blocks'] = $instructor_blocks;
        $section_schedules[$section_id][$section_code] = $section_blocks;

        // Save allocations (one row per meeting/session)
        foreach ($assigned_meetings as $idx => $meeting) {
            $allocations[] = [
                'num' => $alloc_cnt++,
                'instructor' => $teacher['FirstName'] . ' ' . $teacher['LastName'],
                'course' => $ss['CourseName'],
                'subject' => $ss['SubjectName'],
                'subject_code' => $ss['SubjectCode'],
                'year' => $ss['year_level'],
                'section' => $section_code,
                'day' => $meeting['day'],
                'time' => $meeting['time'],
                'duration' => $meeting['duration'],
                'room' => $meeting['room']
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
                                <form method="post" action="save-rooms.php">
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
                                            <th>Room</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($allocations as $idx => $alloc): ?>
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
                                            <td>
                                                <input type="text" name="room[<?= $idx ?>]" class="form-control" value="<?= isset($alloc['room']) ? htmlentities($alloc['room']) : '' ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($allocations)): ?>
                                        <tr><td colspan="11"><i>No allocation generated.</i></td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                                <button type="submit" class="btn btn-primary">Save Rooms</button>
                                </form>
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