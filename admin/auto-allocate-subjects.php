<?php
// Assumes $dbh (PDO), $selected_year, $selected_sem are already set and valid

function time_to_minutes($time) {
    list($h, $m) = explode(':', $time);
    return $h * 60 + $m;
}
function minutes_to_time($minutes) {
    $h = floor($minutes / 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d', $h, $m);
}
function has_time_overlap($blocks, $day, $start, $end) {
    foreach ($blocks as $block) {
        if ($block['day'] === $day) {
            if ($start < $block['end'] && $end > $block['start']) {
                return true;
            }
        }
    }
    return false;
}

function auto_allocate_subjects($dbh, $selected_year, $selected_sem) {
    $available_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $schedule_times = [
        'morning' => ['start' => '07:30', 'end' => '11:30'],
        'afternoon' => ['start' => '13:00', 'end' => '17:00'],
        'evening' => ['start' => '17:30', 'end' => '20:30'],
    ];

    $classes = $dbh->prepare("SELECT * FROM tblclass WHERE academic_year=? AND semester=?");
    $classes->execute([$selected_year, $selected_sem]);
    $class_rows = $classes->fetchAll(PDO::FETCH_ASSOC);

    // Prepare qualified teachers
    $teacher_stmt = $dbh->prepare("SELECT * FROM tblteacher WHERE BachelorsVerified=1 AND TeacherID IN (SELECT DISTINCT TeacherID FROM tblskills WHERE Verified=1)");
    $teacher_stmt->execute();
    $teachers = [];
    while ($t = $teacher_stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = $t['TeacherID'];
        $teachers[$id] = $t;
        $teachers[$id]['assigned_sections'] = 0;
        $teachers[$id]['schedule_blocks'] = [];
        $teachers[$id]['preferred_times'] = [];
    }
    $pt_stmt = $dbh->prepare("SELECT teacher_id, day_of_week, time_slot FROM teacher_preferred_times");
    $pt_stmt->execute();
    while ($row = $pt_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($teachers[$row['teacher_id']])) {
            $teachers[$row['teacher_id']]['preferred_times'][] = strtolower($row['day_of_week']) . '-' . strtolower($row['time_slot']);
        }
    }
    $section_schedules = [];

    foreach ($class_rows as $class) {
        $course_id = $class['course_id'];
        $year_level = $class['year_level'];
        $section_count = $class['section'];
        $class_id = $class['id'];

        // Get subjects for this class
        $curric = $dbh->prepare("SELECT c.subject_id, s.subject_name, s.time_duration FROM tblcurriculum c JOIN tblsubject s ON s.ID = c.subject_id WHERE c.course_id=? AND c.year_level=? AND c.semester=?");
        $curric->execute([$course_id, $year_level, $selected_sem]);
        $subjects = $curric->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subjects as $subject) {
            $subject_id = $subject['subject_id'];
            for ($section_num = 1; $section_num <= $section_count; $section_num++) {
                // Find available teacher (simple round robin)
                $qualified_ids = array_keys($teachers);
                $best_teacher_id = null;
                $min_load = PHP_INT_MAX;
                foreach ($qualified_ids as $tid) {
                    if ($teachers[$tid]['assigned_sections'] < $min_load) {
                        $min_load = $teachers[$tid]['assigned_sections'];
                        $best_teacher_id = $tid;
                    }
                }
                if (!$best_teacher_id) continue;

                // Parse durations
                $durations = [];
                if (isset($subject['time_duration']) && trim($subject['time_duration']) !== '') {
                    $durations = array_map('intval', array_filter(array_map('trim', explode(',', $subject['time_duration']))));
                }
                if (empty($durations)) $durations = [90, 90];
                $meetings_per_week = count($durations);

                $instructor_blocks = $teachers[$best_teacher_id]['schedule_blocks'];
                $section_blocks = isset($section_schedules[$class_id][$section_num]) ? $section_schedules[$class_id][$section_num] : [];
                for ($m = 0; $m < $meetings_per_week; $m++) {
                    $duration_minutes = $durations[$m];
                    $meeting_assigned = false;

                    foreach ($available_days as $day) {
                        foreach (['morning','afternoon','evening'] as $slot) {
                            $slot_time = $schedule_times[$slot];
                            $slot_start_min = time_to_minutes($slot_time['start']);
                            $slot_end_min = time_to_minutes($slot_time['end']);
                            for ($block_start = $slot_start_min; $block_start + $duration_minutes <= $slot_end_min; $block_start += 10) {
                                $block_end = $block_start + $duration_minutes;
                                if (!has_time_overlap($instructor_blocks, $day, $block_start, $block_end)
                                    && !has_time_overlap($section_blocks, $day, $block_start, $block_end)) {
                                    $teachers[$best_teacher_id]['schedule_blocks'][] = ['day'=>$day, 'start'=>$block_start, 'end'=>$block_end];
                                    $section_schedules[$class_id][$section_num][] = ['day'=>$day, 'start'=>$block_start, 'end'=>$block_end];
                                    // Save allocation (to 'subject_allocations' table)
                                    $ins = $dbh->prepare("INSERT INTO subject_allocations (subject_id, section_id, teacher_id, schedule_day, schedule_time_slot, allocated_units, allocation_status, created_at)
                                        VALUES (?, ?, ?, ?, ?, ?, 'allocated', NOW())
                                        ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id), schedule_day=VALUES(schedule_day), schedule_time_slot=VALUES(schedule_time_slot), allocation_status='allocated'");
                                    $slot_range = minutes_to_time($block_start) . ' - ' . minutes_to_time($block_end);
                                    $ins->execute([$subject_id, $class_id, $best_teacher_id, $day, $slot_range, $duration_minutes]);
                                    $meeting_assigned = true;
                                    break 3; // next meeting
                                }
                            }
                        }
                    }
                    if (!$meeting_assigned) {
                        // Could not assign meeting, optionally log error
                    }
                }
                $teachers[$best_teacher_id]['assigned_sections']++;
            }
        }
    }
    return true;
}
// Usage: auto_allocate_subjects($dbh, $selected_year, $selected_sem);
?>