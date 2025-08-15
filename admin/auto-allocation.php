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

// Fetch academic years and semesters for dropdowns
$ay_stmt = $dbh->prepare("SELECT DISTINCT academic_year FROM tblclass ORDER BY academic_year DESC");
$ay_stmt->execute();
$academic_years = $ay_stmt->fetchAll(PDO::FETCH_COLUMN);

$sem_stmt = $dbh->prepare("SELECT DISTINCT semester FROM tblclass ORDER BY semester ASC");
$sem_stmt->execute();
$semesters = $sem_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get selected year/sem (from POST or default to latest)
$selected_year = $_POST['academic_year'] ?? ($academic_years[0] ?? '');
$selected_sem = $_POST['semester'] ?? ($semesters[0] ?? '');
$priorities = explode(',', $_POST['priorities'] ?? 'skills,educ,exp');

// Only process allocation if form is submitted (View Allocations pressed)
$show_table = ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['academic_year']) && isset($_POST['semester']));

// Priority log
if ($show_table && !empty($priorities)) {
    $priority_order = implode(',', $priorities);
    $priority_log_stmt = $dbh->prepare("INSERT INTO tblallocation_priority_log (academic_year, semester, priority_order, generated_by, date_generated) VALUES (?, ?, ?, ?, NOW())");
    $priority_log_stmt->execute([$selected_year, $selected_sem, $priority_order, $_SESSION['tsasaid']]);
}

// Prepare allocation variables
$subject_sections = [];
$subject_preferred_teachers = [];
$teachers = [];
$subject_skill_tags = [];
$allocations = []; // array of arrays per subject-section
$no_instructor_subjects = []; // array of arrays per subject-section
$allocation_errors = [];
$alloc_cnt = 1;
$section_letters = range('A', 'Z');
$instructor_dropdown = [];

if ($show_table) {
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
    $pref_stmt = $dbh->prepare("SELECT subject_id, teacher_id FROM subject_teachers");
    $pref_stmt->execute();
    while ($row = $pref_stmt->fetch(PDO::FETCH_ASSOC)) {
        $subject_preferred_teachers[$row['subject_id']][] = $row['teacher_id'];
    }

    // List all instructors (qualified and not)
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
    $qual_stmt = $dbh->prepare("SELECT subject_id, tags FROM tblqualification");
    $qual_stmt->execute();
    while ($row = $qual_stmt->fetch(PDO::FETCH_ASSOC)) {
        $tags = array_map('trim', explode(',', strtolower($row['tags'])));
        $subject_skill_tags[$row['subject_id']] = $tags;
    }

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

    // For dropdown: all instructors + Professor XYZ
    foreach ($teachers as $tid => $t) {
        $instructor_dropdown[$tid] = $t['FirstName'].' '.$t['LastName'];
    }
    $instructor_dropdown['xyz'] = 'Professor XYZ';

    // Day and slot/time mapping
    $available_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $schedule_times = [
        'morning' => ['start' => '07:30', 'end' => '11:30'],
        'afternoon' => ['start' => '13:00', 'end' => '17:00'],
        'evening' => ['start' => '17:30', 'end' => '20:30'],
    ];

    // --- BEGIN MAIN ALLOCATION ---
    // Group allocations by subject-section for per-section dropdown sync
    $allocations_by_section = []; // [section_uid][meeting_idx] = row

    foreach ($subject_sections as $ss) {
        $subject_id = $ss['subject_id'];
        $section_id = $ss['section_id'];
        $num_sections = intval($ss['section']);
        $durations = [];
        if (isset($ss['time_duration']) && trim($ss['time_duration']) !== '') {
            $durations = array_map('intval', array_filter(array_map('trim', explode(',', $ss['time_duration']))));
        }
        if (empty($durations)) $durations = [90, 90];
        $meetings_per_week = count($durations);
        $subject_tags = $subject_skill_tags[$subject_id] ?? [];

        // Qualified instructors
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
        $no_qualified = empty($qualified_ids);

        for ($sec = 0; $sec < $num_sections; $sec++) {
            $section_code = $section_letters[$sec] ?? ($sec+1);
            $section_uid = "{$subject_id}_{$section_id}_{$section_code}";
            $rows = [];
            // Meetings for this section
            for ($m = 0; $m < $meetings_per_week; $m++) {
                $duration_minutes = $durations[$m];
                $day = $available_days[$m % count($available_days)];
                $slot = array_keys($schedule_times)[$m % count($schedule_times)];
                $slot_time = $schedule_times[$slot];
                $slot_start_min = time_to_minutes($slot_time['start']);
                $block_start = $slot_start_min;
                $start_time = minutes_to_time($block_start);
                $end_time = minutes_to_time($block_start + $duration_minutes);
                $row = [
                    'section_uid' => $section_uid,
                    'meeting_idx' => $m,
                    'num' => $alloc_cnt++,
                    'instructor_id' => $no_qualified ? 'xyz' : null,
                    'instructor' => $no_qualified ? 'Professor XYZ' : '',
                    'course' => $ss['CourseName'],
                    'subject_id' => $subject_id,
                    'subject' => $ss['SubjectName'],
                    'subject_code' => $ss['SubjectCode'],
                    'year' => $ss['year_level'],
                    'section' => $section_code,
                    'day' => $day,
                    'time' => "{$start_time} - {$end_time}",
                    'duration' => $duration_minutes,
                    'room' => '',
                    'no_qualified' => $no_qualified,
                    'section_id' => $section_id,
                ];
                $rows[] = $row;
            }
            // Assign to correct grouping
            if ($no_qualified) {
                $no_instructor_subjects[$section_uid] = $rows;
                $allocation_errors[] = "No qualified instructors found for subject '{$ss['SubjectName']}' (ID: {$subject_id}).";
            } else {
                // Assign instructor: always prioritize preferred (even if not qualified)
                $preferred_ids = $subject_preferred_teachers[$subject_id] ?? [];
                $assigned_teacher_id = null;
                foreach ($preferred_ids as $pid) {
                    if (isset($teachers[$pid])) { $assigned_teacher_id = $pid; break; }
                }
                if (!$assigned_teacher_id) {
                    $assigned_teacher_id = find_least_loaded_teacher($qualified_ids, $teachers);
                }
                if (!$assigned_teacher_id) continue;
                foreach ($rows as &$row_ref) {
                    $row_ref['instructor_id'] = $assigned_teacher_id;
                    $row_ref['instructor'] = $teachers[$assigned_teacher_id]['FirstName'].' '.$teachers[$assigned_teacher_id]['LastName'];
                }
                unset($row_ref);
                $allocations_by_section[$section_uid] = $rows;
                $teachers[$assigned_teacher_id]['assigned_sections']++;
            }
        }
    }

    // Now, for each section_uid, compute valid instructor choices (no conflict for ALL meetings)
    $dropdown_valid_instructors = [];
    foreach ($allocations_by_section as $section_uid => $meetings) {
        $valid = [];
        foreach ($instructor_dropdown as $tid => $name) {
            if ($tid === 'xyz') continue;
            $has_conflict = false;
            foreach ($meetings as $row) {
                $row_times = explode(' - ', $row['time']);
                $row_start = time_to_minutes($row_times[0]);
                $row_end   = time_to_minutes($row_times[1]);
                $teacher_blocks = [];
                foreach ($allocations_by_section as $_section_uid => $_rows) {
                    if ($_section_uid === $section_uid) break;
                    foreach ($_rows as $_row) {
                        if ($_row['instructor_id'] == $tid) {
                            $block_day = $_row['day'];
                            $block_times = explode(' - ', $_row['time']);
                            $block_start = time_to_minutes($block_times[0]);
                            $block_end   = time_to_minutes($block_times[1]);
                            $teacher_blocks[] = ['day'=>$block_day, 'start'=>$block_start, 'end'=>$block_end];
                        }
                    }
                }
                foreach ($meetings as $check_row) {
                    if ($check_row['meeting_idx'] === $row['meeting_idx']) break;
                    if ($check_row['instructor_id'] == $tid) {
                        $block_day = $check_row['day'];
                        $block_times = explode(' - ', $check_row['time']);
                        $block_start = time_to_minutes($block_times[0]);
                        $block_end   = time_to_minutes($block_times[1]);
                        $teacher_blocks[] = ['day'=>$block_day, 'start'=>$block_start, 'end'=>$block_end];
                    }
                }
                if (has_time_overlap($teacher_blocks, $row['day'], $row_start, $row_end)) {
                    $has_conflict = true;
                    break;
                }
            }
            if (!$has_conflict) $valid[$tid] = $name;
        }
        $dropdown_valid_instructors[$section_uid] = $valid;
    }
    foreach ($no_instructor_subjects as $section_uid => $meetings) {
        $valid = [];
        foreach ($instructor_dropdown as $tid => $name) {
            $has_conflict = false;
            if ($tid === 'xyz') {
                $valid[$tid] = $name;
                continue;
            }
            foreach ($meetings as $row) {
                $row_times = explode(' - ', $row['time']);
                $row_start = time_to_minutes($row_times[0]);
                $row_end   = time_to_minutes($row_times[1]);
                $teacher_blocks = [];
                foreach ($allocations_by_section as $_section_uid => $_rows) {
                    foreach ($_rows as $_row) {
                        if ($_row['instructor_id'] == $tid) {
                            $block_day = $_row['day'];
                            $block_times = explode(' - ', $_row['time']);
                            $block_start = time_to_minutes($block_times[0]);
                            $block_end   = time_to_minutes($block_times[1]);
                            $teacher_blocks[] = ['day'=>$block_day, 'start'=>$block_start, 'end'=>$block_end];
                        }
                    }
                }
                foreach ($meetings as $check_row) {
                    if ($check_row['meeting_idx'] === $row['meeting_idx']) break;
                    if ($check_row['instructor_id'] == $tid) {
                        $block_day = $check_row['day'];
                        $block_times = explode(' - ', $check_row['time']);
                        $block_start = time_to_minutes($block_times[0]);
                        $block_end   = time_to_minutes($block_times[1]);
                        $teacher_blocks[] = ['day'=>$block_day, 'start'=>$block_start, 'end'=>$block_end];
                    }
                }
                if (has_time_overlap($teacher_blocks, $row['day'], $row_start, $row_end)) {
                    $has_conflict = true;
                    break;
                }
            }
            if (!$has_conflict) $valid[$tid] = $name;
        }
        $dropdown_valid_instructors[$section_uid] = $valid;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Auto-Generated Subject Allocation Table</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .auto-table-box { background:#fff; border-radius:12px; padding:25px 25px;margin-top:25px;}
        .auto-table-box h4 { margin-bottom: 18px; }
        .auto-alloc-table th, .auto-alloc-table td { text-align:center; vertical-align:middle; }
        .no-instructor-row { background: #ffe0ef !important; }
        .dropdown-modern {
            border-radius: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            background: #f8faff;
            padding: 2px 8px;
            border: 1px solid #cfd8dc;
            font-weight: 600;
            color: #333;
            transition: border-color 0.2s;
        }
        .dropdown-modern:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 2px #1976d214;
        }
        .dropdown-modern option {
            color: #222;
            font-weight: 500;
        }
        .aysem-display {
            border-radius: 14px;
            background: #f5f7fa;
            margin: 24px 0 18px 0;
            padding: 12px 30px 12px 28px;
            display: flex;
            align-items: center;
            gap: 35px;
            box-shadow: 0 2px 12px #e3e8f0;
        }
        .aysem-display-label {
            font-weight: bold;
            color: #1976d2;
            font-size: 1.06em;
        }
        .aysem-display-value {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 2px 15px;
            font-weight: 600;
            color: #333;
            font-size: 1.04em;
        }
        .dropdown-prof {
            min-width: 165px;
            max-width: 260px;
            background: #f8faff;
        }
        @media (max-width: 767px) {
            .aysem-display { flex-direction: column; align-items: flex-start; padding: 10px 12px; gap: 8px; }
        }
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
                    <!-- HEADER PART: DO NOT REMOVE -->
                    <div class="page-header">
                        <div class="page-title">
                            <h1>Auto-Generated Subject Allocation Table</h1>
                        </div>
                    </div>
                    <!-- Academic Year & Semester: Place here as requested -->
                    <form method="post" action="auto-allocation.php" class="mb-3 mt-4">
                        <div class="aysem-display">
                            <div>
                                <span class="aysem-display-label"><i class="bi bi-calendar2-week"></i> Academic Year</span>
                                <select name="academic_year" class="dropdown-modern" style="margin-left:3px;">
                                    <?php foreach ($academic_years as $ay): ?>
                                        <option value="<?= htmlentities($ay) ?>" <?= $selected_year==$ay?'selected':'' ?>><?= htmlentities($ay) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <span class="aysem-display-label"><i class="bi bi-calendar3"></i> Semester</span>
                                <select name="semester" class="dropdown-modern" style="margin-left:3px;">
                                    <?php foreach ($semesters as $sem): ?>
                                        <option value="<?= htmlentities($sem) ?>" <?= $selected_sem==$sem?'selected':'' ?>><?= htmlentities($sem) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-arrow-repeat"></i> View Allocations
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php if ($show_table): ?>
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
                                <form method="post" action="finalize-schedule.php" id="allocForm">
                                <input type="hidden" name="academic_year" value="<?= htmlentities($selected_year) ?>">
                                <input type="hidden" name="semester" value="<?= htmlentities($selected_sem) ?>">
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
                                    <!-- For no instructor sections -->
                                    <?php foreach ($no_instructor_subjects as $section_uid => $meetings): ?>
                                        <?php foreach ($meetings as $row_idx => $alloc): ?>
                                            <tr class="no-instructor-row">
                                                <td><?= $alloc['num'] ?></td>
                                                <td>
                                                    <select name="instructor_no[<?= $section_uid ?>][]" 
                                                        class="dropdown-modern dropdown-prof instructor-dropdown"
                                                        data-section="<?= $section_uid ?>">
                                                        <?php foreach ($dropdown_valid_instructors[$section_uid] as $tid => $name): ?>
                                                            <option value="<?= $tid ?>" <?= ($alloc['instructor_id']==$tid)?'selected':'' ?>>
                                                                <?= htmlentities($name) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td><?= htmlentities($alloc['course']) ?></td>
                                                <td><?= htmlentities($alloc['subject']) ?></td>
                                                <td><?= htmlentities($alloc['subject_code']) ?></td>
                                                <td><?= htmlentities($alloc['year']) ?></td>
                                                <td><?= htmlentities($alloc['section']) ?></td>
                                                <td><?= htmlentities($alloc['day']) ?></td>
                                                <td><?= htmlentities($alloc['time']) ?></td>
                                                <td><?= htmlentities($alloc['duration']) ?></td>
                                                <td>
                                                    <input type="text" name="room_noinstructor[<?= $section_uid ?>][<?= $row_idx ?>]" class="form-control" value="<?= isset($alloc['room']) ? htmlentities($alloc['room']) : '' ?>">
                                                    <!-- Hidden fields -->
                                                    <input type="hidden" name="course[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['course']) ?>">
                                                    <input type="hidden" name="subject_id[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['subject_id']) ?>">
                                                    <input type="hidden" name="subject_code[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['subject_code']) ?>">
                                                    <input type="hidden" name="subject_name[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['subject']) ?>">
                                                    <input type="hidden" name="year_level[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['year']) ?>">
                                                    <input type="hidden" name="section[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['section']) ?>">
                                                    <input type="hidden" name="day[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['day']) ?>">
                                                    <input type="hidden" name="time[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['time']) ?>">
                                                    <input type="hidden" name="duration[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['duration']) ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                    <!-- For qualified allocations -->
                                    <?php foreach ($allocations_by_section as $section_uid => $meetings): ?>
                                        <?php foreach ($meetings as $row_idx => $alloc): ?>
                                            <tr>
                                                <td><?= $alloc['num'] ?></td>
                                                <td>
                                                    <select name="instructor[<?= $section_uid ?>][]" 
                                                        class="dropdown-modern dropdown-prof instructor-dropdown"
                                                        data-section="<?= $section_uid ?>">
                                                        <?php foreach ($dropdown_valid_instructors[$section_uid] as $tid => $name): ?>
                                                            <option value="<?= $tid ?>" <?= ($alloc['instructor_id']==$tid)?'selected':'' ?>>
                                                                <?= htmlentities($name) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td><?= htmlentities($alloc['course']) ?></td>
                                                <td><?= htmlentities($alloc['subject']) ?></td>
                                                <td><?= htmlentities($alloc['subject_code']) ?></td>
                                                <td><?= htmlentities($alloc['year']) ?></td>
                                                <td><?= htmlentities($alloc['section']) ?></td>
                                                <td><?= htmlentities($alloc['day']) ?></td>
                                                <td><?= htmlentities($alloc['time']) ?></td>
                                                <td><?= htmlentities($alloc['duration']) ?></td>
                                                <td>
                                                    <input type="text" name="room[<?= $section_uid ?>][<?= $row_idx ?>]" class="form-control" value="<?= isset($alloc['room']) ? htmlentities($alloc['room']) : '' ?>">
                                                    <!-- Hidden fields -->
                                                    <input type="hidden" name="course[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['course']) ?>">
                                                    <input type="hidden" name="subject_id[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['subject_id']) ?>">
                                                    <input type="hidden" name="subject_code[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['subject_code']) ?>">
                                                    <input type="hidden" name="subject_name[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['subject']) ?>">
                                                    <input type="hidden" name="year_level[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['year']) ?>">
                                                    <input type="hidden" name="section[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['section']) ?>">
                                                    <input type="hidden" name="day[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['day']) ?>">
                                                    <input type="hidden" name="time[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['time']) ?>">
                                                    <input type="hidden" name="duration[<?= $section_uid ?>][<?= $row_idx ?>]" value="<?= htmlentities($alloc['duration']) ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                    <?php if (empty($allocations_by_section) && empty($no_instructor_subjects)): ?>
                                        <tr><td colspan="11"><i>No allocation generated.</i></td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                                <button type="submit" class="btn btn-primary">Finalize Schedule</button>
                                </form>
                            </div>
                        </div>
                        <a href="subject-allocation.php" class="btn btn-secondary mt-3">Back to Subject Allocation</a>
                    </div>
                    <?php endif; ?>
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

    // Synchronized instructor dropdowns per section
    $('.instructor-dropdown').on('change', function() {
        var section = $(this).attr('data-section');
        var selected = $(this).val();
        $('select.instructor-dropdown[data-section="'+section+'"]').val(selected);
    });
});
</script>
</body>
</html>