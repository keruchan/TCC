<?php
include('includes/dbconnection.php');

// Assume these are set from POST or session
$selected_year = $_POST['academic_year'] ?? '';
$selected_sem = $_POST['semester'] ?? '';
$priorities = explode(',', $_POST['priorities'] ?? 'skills,educ,exp'); // from prioritization UI

// 1. Fetch all subject-section assignments needed
$sql = "SELECT 
            cl.id AS section_id,
            cl.course_id, 
            c.CourseName, 
            cur.subject_id, 
            s.subject_name AS SubjectName, 
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

$allocations = [];
foreach ($subject_sections as $ss) {
    $section_id = $ss['section_id'];
    $subject_id = $ss['subject_id'];
    $course_id = $ss['course_id'];
    $year_level = $ss['year_level'];

    // 2. Get qualified instructors, ordered by priorities
    $teachers = [];
    // Fetch all verified teachers
    $teacher_stmt = $dbh->prepare("SELECT * FROM tblteacher");
    $teacher_stmt->execute();
    $all_teachers = $teacher_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_teachers as $teacher) {
        $score = 0;
        // Priority 1
        if ($priorities[0] == 'skills') {
            $has_skill = $dbh->prepare("SELECT 1 FROM tblskills WHERE TeacherID=? AND Verified=1 AND SkillName LIKE ?");
            $has_skill->execute([$teacher['TeacherID'], '%']);
            $score += $has_skill->fetch() ? 100 : 0;
        } elseif ($priorities[0] == 'educ') {
            $score += ($teacher['BachelorsVerified'] || $teacher['MastersVerified'] || $teacher['DoctorateVerified']) ? 100 : 0;
        } elseif ($priorities[0] == 'exp') {
            $exp_stmt = $dbh->prepare("SELECT 1 FROM tblteachingload WHERE TeacherID=? AND Verified=1 AND SubjectsTaught LIKE ?");
            $exp_stmt->execute([$teacher['TeacherID'], '%']);
            $score += $exp_stmt->fetch() ? 100 : 0;
        }
        // Add more for next priorities as desired...
        $teachers[] = ['teacher' => $teacher, 'score' => $score];
    }
    // Sort teachers by score
    usort($teachers, function($a, $b) { return $b['score'] <=> $a['score']; });
    $chosen = $teachers[0]['teacher'] ?? null;

    // 3. Assign a time (for demo, just set as '-')
    $time = '-';

    // 4. Save allocation in subject_allocations if not exists
    if ($chosen) {
        // Add to allocations array for report
        $allocations[] = [
            'instructor' => $chosen['FirstName'] . ' ' . $chosen['LastName'],
            'course' => $ss['CourseName'],
            'subject' => $ss['SubjectName'],
            'year' => $ss['year_level'],
            'section' => $ss['section'],
            'time' => $time
        ];
        // Insert into subject_allocations if not yet allocated
        $insert_stmt = $dbh->prepare("INSERT IGNORE INTO subject_allocations
            (subject_id, section_id, teacher_id, allocation_status, created_at) 
            VALUES (?, ?, ?, 'allocated', NOW())");
        $insert_stmt->execute([$subject_id, $section_id, $chosen['TeacherID']]);
    }
}
?>

<!-- Allocation Table -->
<div class="card alert mb-4">
    <h5>Auto-Generated Allocation Table</h5>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
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
</div>