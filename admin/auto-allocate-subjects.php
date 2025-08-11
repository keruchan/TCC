<?php
// Assumes $dbh (PDO), $selected_year, $selected_sem are already set and valid

function auto_allocate_subjects($dbh, $selected_year, $selected_sem) {
    // Fetch all sections (class records) for this year and semester
    $classes = $dbh->prepare("SELECT * FROM tblclass WHERE academic_year=? AND semester=?");
    $classes->execute([$selected_year, $selected_sem]);
    $class_rows = $classes->fetchAll(PDO::FETCH_ASSOC);

    foreach ($class_rows as $class) {
        $course_id = $class['course_id'];
        $year_level = $class['year_level'];
        $section_count = $class['section'];

        // For this course/year/semester, fetch all subjects from curriculum
        $curric = $dbh->prepare("SELECT c.subject_id, s.SubjectTitle, s.SubjectDescription, c.subject_skill_pref, c.preferred_instructor_id
                                 FROM tblcurriculum c
                                 JOIN tblsubject s ON s.ID = c.subject_id
                                 WHERE c.course_id=? AND c.year_level=? AND c.semester=?");
        $curric->execute([$course_id, $year_level, $selected_sem]);
        $subjects = $curric->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subjects as $subject) {
            $subject_id = $subject['subject_id'];

            for ($section_num = 1; $section_num <= $section_count; $section_num++) {

                // If a preferred instructor is set and available, allocate immediately
                if ($subject['preferred_instructor_id']) {
                    $instructor_id = $subject['preferred_instructor_id'];
                } else {
                    // Find qualified instructors for this subject
                    $sql = "SELECT t.TeacherID, t.MaxLoad, t.EmploymentType, t.Bachelors, t.Masters, t.Doctorate,
                                   (SELECT COUNT(*) FROM tblteachingload WHERE TeacherID = t.TeacherID AND Verified=1) AS teaching_exp,
                                   (SELECT COUNT(*) FROM tblskills WHERE TeacherID = t.TeacherID AND Verified=1 AND FIND_IN_SET(?, SkillName)) AS skill_match,
                                   t.PreferredTime
                            FROM tblteacher t
                            JOIN tblskills s ON t.TeacherID = s.TeacherID
                            WHERE s.Verified=1
                              AND FIND_IN_SET(?, t.CoursesLoad) > 0
                            GROUP BY t.TeacherID";
                    $stmt = $dbh->prepare($sql);
                    $stmt->execute([$subject['subject_skill_pref'], $course_id]);
                    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Score each candidate
                    $best_score = -1;
                    $best_teacher = null;
                    foreach ($candidates as $cand) {
                        $score = 0;
                        // Skill match is highest priority
                        if ($cand['skill_match']) $score += 100;
                        // Education bonus
                        if ($cand['Doctorate']) $score += 40;
                        elseif ($cand['Masters']) $score += 30;
                        elseif ($cand['Bachelors']) $score += 10;
                        // Teaching experience
                        $score += min($cand['teaching_exp'] * 2, 20);
                        // Preferred time match (optional, you’ll need to check overlap with section time if available)
                        // Employment type
                        if ($cand['EmploymentType'] === 'Regular') $score += 8;
                        // Not overloaded
                        if ($cand['MaxLoad'] > 0) {
                            // You should count current load and compare to MaxLoad here
                            $currLoadStmt = $dbh->prepare("SELECT COUNT(*) FROM tblallocation WHERE TeacherID=? AND academic_year=? AND semester=?");
                            $currLoadStmt->execute([$cand['TeacherID'], $selected_year, $selected_sem]);
                            $curr_load = $currLoadStmt->fetchColumn();
                            if ($curr_load < $cand['MaxLoad']) $score += 20;
                            else $score -= 50;
                        }
                        if ($score > $best_score) {
                            $best_score = $score;
                            $best_teacher = $cand['TeacherID'];
                        }
                    }
                    $instructor_id = $best_teacher;
                }
                // Save allocation
                if ($instructor_id) {
                    $ins = $dbh->prepare("INSERT INTO tblallocation (class_id, subject_id, section_num, teacher_id, academic_year, semester, date_allocated)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id)");
                    $ins->execute([$class['ID'], $subject_id, $section_num, $instructor_id, $selected_year, $selected_sem]);
                }
            }
        }
    }
    return true;
}

// Usage example (after section save, or on button click):
// auto_allocate_subjects($dbh, $selected_year, $selected_sem);
?>