<?php
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
?>