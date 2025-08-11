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

// 1. Save prioritization used for this run
if ($selected_year && $selected_sem && !empty($priorities)) {
    $priority_order = implode(',', $priorities);
    $priority_log_stmt = $dbh->prepare("INSERT INTO tblallocation_priority_log (academic_year, semester, priority_order, generated_by, date_generated) VALUES (?, ?, ?, ?, NOW())");
    $priority_log_stmt->execute([$selected_year, $selected_sem, $priority_order, $admin_id]);
}

// 2. Get all subject-section assignments to allocate
$sql = "SELECT 
            cl.id AS section_id,
            cl.course_id, 
            c.CourseName, 
            cur.subject_id, 
            s.subject_name AS SubjectName, 
            s.description AS SubjectDesc,
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

// Preload: teacher loads, skills, education, experience, preferred times, employment type, max load
$teachers = [];
$teacher_stmt = $dbh->prepare("SELECT * FROM tblteacher WHERE 1");
$teacher_stmt->execute();
while ($t = $teacher_stmt->fetch(PDO::FETCH_ASSOC)) {
    $id = $t['TeacherID'];
    $teachers[$id] = $t;
    $teachers[$id]['skills'] = [];
    $teachers[$id]['experience'] = [];
    $teachers[$id]['preferred_times'] = [];
    $teachers[$id]['current_load'] = 0;
    $teachers[$id]['current_subjects'] = 0;
}
// Skills
$skill_stmt = $dbh->prepare("SELECT SkillID, TeacherID, SkillName, Verified FROM tblskills WHERE Verified=1");
$skill_stmt->execute();
while ($row = $skill_stmt->fetch(PDO::FETCH_ASSOC)) {
    $skills = array_map('trim', explode(',', strtolower($row['SkillName'])));
    $teachers[$row['TeacherID']]['skills'] = array_merge($teachers[$row['TeacherID']]['skills'], $skills);
}
// Teaching Experience
$exp_stmt = $dbh->prepare("SELECT TeacherID, SubjectsTaught, Verified FROM tblteachingload WHERE Verified=1");
$exp_stmt->execute();
while ($row = $exp_stmt->fetch(PDO::FETCH_ASSOC)) {
    $subjects = array_map('trim', explode(',', strtolower($row['SubjectsTaught'])));
    $teachers[$row['TeacherID']]['experience'] = array_merge($teachers[$row['TeacherID']]['experience'], $subjects);
}
// Preferred Times
$pt_stmt = $dbh->prepare("SELECT teacher_id, day_of_week, time_slot FROM teacher_preferred_times");
$pt_stmt->execute();
while ($row = $pt_stmt->fetch(PDO::FETCH_ASSOC)) {
    $teachers[$row['teacher_id']]['preferred_times'][] = strtolower($row['day_of_week']) . '-' . strtolower($row['time_slot']);
}
// Max Load (initialize with value from teacher table)
foreach ($teachers as $id => &$t) {
    $t['max_load'] = $t['MaxLoad'] ?? 0;
    if (!$t['max_load'] || intval($t['max_load']) <= 0) $t['max_load'] = 30; // Default if not set
}

// Current Load: count all existing allocations for this year/sem
$load_stmt = $dbh->prepare("SELECT teacher_id, SUM(allocated_units) AS units, COUNT(*) as subjects
    FROM subject_allocations
    WHERE allocation_status='allocated'
    GROUP BY teacher_id");
$load_stmt->execute();
while ($row = $load_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($teachers[$row['teacher_id']])) {
        $teachers[$row['teacher_id']]['current_load'] = intval($row['units']);
        $teachers[$row['teacher_id']]['current_subjects'] = intval($row['subjects']);
    }
}

// 3. Get subject requirements (skills, preferred teacher, qualification)
$subject_requirements = [];
$qual_stmt = $dbh->prepare("SELECT subject_id, tags FROM tblqualification");
$qual_stmt->execute();
while ($row = $qual_stmt->fetch(PDO::FETCH_ASSOC)) {
    $subject_requirements[$row['subject_id']] = array_map('trim', explode(',', strtolower($row['tags'])));
}
$subject_preferred_teachers = [];
$pref_stmt = $dbh->prepare("SELECT subject_id, teacher_id FROM subject_teachers");
$pref_stmt->execute();
while ($row = $pref_stmt->fetch(PDO::FETCH_ASSOC)) {
    $subject_preferred_teachers[$row['subject_id']][] = $row['teacher_id'];
}

// 4. Time slots for Regular and Part-Time
$schedule_times = [
    'Regular' => [],
    'Part-Time' => []
];
$reg_sched_stmt = $dbh->prepare("SELECT days_of_week, start_time, end_time FROM schedules WHERE schedule_type='Regular' LIMIT 1");
$reg_sched_stmt->execute();
if ($row = $reg_sched_stmt->fetch(PDO::FETCH_ASSOC)) {
    $days = explode(',', $row['days_of_week']);
    foreach ($days as $d) {
        $schedule_times['Regular'][] = [trim($d), $row['start_time'], $row['end_time']];
    }
}
$pt_sched_stmt = $dbh->prepare("SELECT days_of_week, morning_start, morning_end, afternoon_start, afternoon_end, night_start, night_end FROM parttime_schedules LIMIT 1");
$pt_sched_stmt->execute();
if ($row = $pt_sched_stmt->fetch(PDO::FETCH_ASSOC)) {
    $days = explode(',', $row['days_of_week']);
    foreach ($days as $d) {
        if ($row['morning_start'] && $row['morning_end']) {
            $schedule_times['Part-Time'][] = [trim($d), $row['morning_start'], $row['morning_end']];
        }
        if ($row['afternoon_start'] && $row['afternoon_end']) {
            $schedule_times['Part-Time'][] = [trim($d), $row['afternoon_start'], $row['afternoon_end']];
        }
        if ($row['night_start'] && $row['night_end']) {
            $schedule_times['Part-Time'][] = [trim($d), $row['night_start'], $row['night_end']];
        }
    }
}

// 5. Allocation
$allocations = [];
foreach ($subject_sections as $ss) {
    $section_id = $ss['section_id'];
    $subject_id = $ss['subject_id'];
    $course_id = $ss['course_id'];
    $year_level = $ss['year_level'];
    $subject_name = $ss['SubjectName'];
    $subject_desc = strtolower($ss['SubjectDesc'] ?? '');
    $num_section = intval($ss['section']);
    $require_skills = $subject_requirements[$subject_id] ?? [];
    $preferred_teachers = $subject_preferred_teachers[$subject_id] ?? [];
    $subject_keywords = array_unique(array_filter(array_merge($require_skills, explode(' ', strtolower($subject_name)), explode(' ', $subject_desc))));

    // For each section (e.g., 2 sections per year)
    for ($sec = 1; $sec <= $num_section; $sec++) {
        // 5.1 Build candidate pool
        $candidates = [];
        foreach ($teachers as $tid => $t) {
            // Only verified instructor (has at least 1 skill and not over max load)
            if (empty($t['skills'])) continue;
            if ($t['current_subjects'] >= $t['max_load']) continue; // Max subject load
            // Compute score based on priorities
            $score = 0;
            foreach ($priorities as $i => $p) {
                $weight = 100 - $i * 25;
                switch ($p) {
                    case 'skills':
                        // If any skill matches subject requirements/tags/keywords
                        $matched = count(array_intersect($t['skills'], $subject_keywords));
                        $score += $matched * $weight;
                        break;
                    case 'exp':
                        // If taught this subject or similar before
                        foreach ($t['experience'] as $exp) {
                            if (stripos($subject_name, $exp) !== false || stripos($exp, $subject_name) !== false) {
                                $score += $weight;
                                break;
                            }
                        }
                        break;
                    case 'educ':
                        // Related degree (very basic check, can be improved)
                        if (
                            (stripos($t['Bachelors'], $subject_name) !== false && $t['BachelorsVerified']) ||
                            (stripos($t['Masters'], $subject_name) !== false && $t['MastersVerified']) ||
                            (stripos($t['Doctorate'], $subject_name) !== false && $t['DoctorateVerified'])
                        ) {
                            $score += $weight;
                        }
                        break;
                }
            }
            // Employee Type: preference for Regular/Part-Time if section time fits
            $emp_type = $t['EmploymentType'];
            if ($emp_type == 'Regular' && !empty($schedule_times['Regular'])) $score += 10;
            if ($emp_type == 'Part-Time' && !empty($schedule_times['Part-Time'])) $score += 10;
            // Preferred instructor for the subject
            if (in_array($tid, $preferred_teachers)) $score += 200;
            // TODO: check preferred time, only if we assign time slot below

            $candidates[] = [
                'teacher_id' => $tid,
                'teacher' => $t,
                'score' => $score,
            ];
        }
        // No candidate? Skip and log shortfall if needed
        if (empty($candidates)) continue;
        // Sort by score DESC
        usort($candidates, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        $chosen = $candidates[0]['teacher'];
        $chosen_id = $chosen['TeacherID'];

        // Assign a time slot that fits their preference and schedule
        $emp_type = $chosen['EmploymentType'];
        $assigned_time = '-';
        $assigned_day = '';
        $found_time = false;
        foreach ($schedule_times[$emp_type] as $sched) {
            list($day, $start, $end) = $sched;
            // If teacher prefers this day/slot
            $slots = ['morning', 'afternoon', 'night'];
            foreach ($slots as $slot) {
                $pref_key = strtolower($day) . '-' . $slot;
                if (in_array($pref_key, $chosen['preferred_times'])) {
                    $assigned_time = "$start - $end ($slot)";
                    $assigned_day = $day;
                    $found_time = true;
                    break 2;
                }
            }
        }
        if (!$found_time && !empty($schedule_times[$emp_type])) {
            // fallback: just pick first available slot
            $sched = $schedule_times[$emp_type][0];
            $assigned_day = $sched[0];
            $assigned_time = "{$sched[1]} - {$sched[2]}";
        }

        // Save to allocations for report
        $allocations[] = [
            'instructor' => $chosen['FirstName'] . ' ' . $chosen['LastName'],
            'course' => $ss['CourseName'],
            'subject' => $subject_name,
            'year' => $year_level,
            'section' => $sec,
            'time' => $assigned_day . ' ' . $assigned_time
        ];

        // Insert or update in subject_allocations
        $insert_stmt = $dbh->prepare("INSERT INTO subject_allocations
            (subject_id, section_id, teacher_id, schedule_day, schedule_time_slot, allocated_units, allocation_status, created_at)
            VALUES (?, ?, ?, ?, ?, 0, 'allocated', NOW())
            ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id), schedule_day=VALUES(schedule_day), schedule_time_slot=VALUES(schedule_time_slot), allocation_status='allocated'");
        $insert_stmt->execute([$subject_id, $section_id, $chosen_id, $assigned_day, $assigned_time]);

        // Update teacher's load for this run
        $teachers[$chosen_id]['current_subjects']++;
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
                    <div class="auto-table-box">
                        <h4>Auto-Generated Subject Allocation Table</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered auto-alloc-table">
                                <thead>
                                    <tr>
                                        <th>Instructor</th>
                                        <th>Course</th>
                                        <th>Subject</th>
                                        <th>Year</th>
                                        <th>Section</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($allocations as $alloc): ?>
                                    <tr>
                                        <td><?= htmlentities($alloc['instructor']) ?></td>
                                        <td><?= htmlentities($alloc['course']) ?></td>
                                        <td><?= htmlentities($alloc['subject']) ?></td>
                                        <td><?= htmlentities($alloc['year']) ?></td>
                                        <td><?= htmlentities($alloc['section']) ?></td>
                                        <td><?= htmlentities($alloc['time']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($allocations)): ?>
                                    <tr><td colspan="6"><i>No allocation generated.</i></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
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
</body>
</html>