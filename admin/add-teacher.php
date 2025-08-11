<?php
session_start();
error_reporting(0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid']) == 0) {
    header('location:logout.php');
    exit;
}

// Use PDO to fetch parttime_schedules
$schedule = null;
$default_units = 18; // fallback default
try {
    $sql = "SELECT * FROM parttime_schedules LIMIT 1";
    $query = $dbh->prepare($sql);
    $query->execute();
    $schedule = $query->fetch(PDO::FETCH_ASSOC);
    if ($schedule && isset($schedule['default_units']) && is_numeric($schedule['default_units'])) {
        $default_units = intval($schedule['default_units']);
    }
} catch (Exception $e) {
    $schedule = null;
}

// Helper for time formatting
function display_time($time) {
    return date("h:i A", strtotime($time));
}

// Get available days from schedule and make an array
$available_days = [];
if ($schedule && !empty($schedule['days_of_week'])) {
    $available_days = array_map('trim', explode(',', $schedule['days_of_week']));
}
$all_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$available_days = array_intersect($all_days, $available_days);

// --- Fetch Courses from tblcourse ---
$courses = [];
try {
    $sql = "SELECT ID, CourseName FROM tblcourse ORDER BY CourseName ASC";
    $query = $dbh->prepare($sql);
    $query->execute();
    $courses = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $courses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Add Teacher Information</title>
    <link href="../assets/css/lib/calendar2/pignose.calendar.min.css" rel="stylesheet">
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .card-section { margin-bottom: 30px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);}
        .tag-badge {
            display: inline-block;
            background-color: #17a2b8;
            color: #fff;
            padding: 5px 10px;
            margin: 2px;
            border-radius: 15px;
        }
        .tag-badge .remove-tag {
            margin-left: 8px;
            cursor: pointer;
            color: #fff;
        }
        .teaching-load-group, .skill-proof-group {
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            position: relative;
            background: #fcfcfc;
        }
        .remove-btn-teachingload, .remove-btn-skillproof {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        @media (max-width: 767px) {
            .remove-btn-teachingload, .remove-btn-skillproof {
                position: static;
                margin-top: 10px;
            }
        }
        .schedule-table { width:100%; }
        .schedule-table th, .schedule-table td { padding:8px 12px; }
        .schedule-table thead th { background:#f8f9fa; }
        .schedule-table { border-collapse: collapse; }
        .schedule-table tr { border-bottom: 1px solid #eee; }
        .schedule-table .form-check { margin-bottom:0; }
        .schedule-table label { font-weight:normal; }
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
                            <h1>Add Teacher</h1>
                        </div>
                    </div>
                </div>
            </div>

            <div id="main-content">
                <form method="post" action="insert-teacher.php" enctype="multipart/form-data">
                    <!-- TEACHER INFO CARD -->
                    <div class="card card-section">
                        <div class="card-header m-b-20">
                            <h4>Teacher Information</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- First row: Firstname, Lastname, Email -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>First Name</label>
                                        <input type="text" class="form-control" name="fname" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Last Name</label>
                                        <input type="text" class="form-control" name="lname" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <!-- Second row: Username, Password, Photo -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Password</label>
                                        <input type="password" class="form-control" name="password" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mt-2">
                                        <label>Upload Teacher Photo <span>(150 x 150)</span></label>
                                        <input type="file" name="propic" accept="image/*" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MAX LOAD CARD -->
                    <div class="card card-section">
                        <div class="card-header m-b-20">
                            <h4>Maximum Number of Load (Units)</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <label>Preferred Maximum Load (units)</label>
                                    <input type="number" class="form-control" name="max_load" min="1" max="40" value="<?= $default_units ?>" required>
                                    <small class="form-text text-muted">
                                        Specify the maximum number of units this instructor prefers for loading. Default is <?= $default_units ?> (per institutional schedule). Can be lower or higher based on admin discretion.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- EDUCATIONAL BACKGROUND CARD -->
                    <div class="card card-section">
                        <div class="card-header m-b-20">
                            <h4>Educational Background <small class="text-danger">(Please do not abbreviate degrees)</small></h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <label>Bachelor's Degree</label>
                                    <input type="text" class="form-control" name="bachelors">
                                </div>
                                <div class="col-md-6">
                                    <label>TOR</label>
                                    <input type="file" class="form-control" name="bachelors_tor" accept="application/pdf,image/*">
                                </div>
                                <div class="col-md-6">
                                    <label>Master's Degree</label>
                                    <input type="text" class="form-control" name="masters">
                                </div>
                                <div class="col-md-6">
                                    <label>TOR</label>
                                    <input type="file" class="form-control" name="masters_tor" accept="application/pdf,image/*">
                                </div>
                                <div class="col-md-6">
                                    <label>Doctorate</label>
                                    <input type="text" class="form-control" name="doctorate">
                                </div>
                                <div class="col-md-6">
                                    <label>TOR</label>
                                    <input type="file" class="form-control" name="doctorate_tor" accept="application/pdf,image/*">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TEACHING EXPERIENCE CARD -->
                    <div class="card card-section">
                        <div class="card-header m-b-20">
                            <h4>Teaching Experience</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group d-flex align-items-center">
                                <label class="mr-3 mb-0">Do you have teaching experience?</label>
                                <select name="has_experience" id="has_experience" class="form-control" style="max-width: 150px; display: inline-block;" onchange="document.getElementById('teaching_exp').style.display = this.value === 'Yes' ? 'block' : 'none';">
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                            <div id="teaching_exp" style="display:none">
                                <div id="teaching-load-fields">
                                    <div class="teaching-load-group">
                                        <button type="button" class="btn btn-secondary btn-sm remove-btn-teachingload" style="display:none;" onclick="removeTeachingLoadField(this)">Remove</button>
                                        <div class="form-group d-flex align-items-center">
                                            <label class="mr-3 mb-0">Upload Teaching Load</label>
                                            <input type="file" class="form-control" name="teaching_load[]" accept="application/pdf,image/*" style="max-width: 300px; display: inline-block;">
                                        </div>
                                        <div class="form-group">
                                            <label>Subjects Taught</label>
                                            <input type="text" class="form-control subject-input" placeholder="Type a subject and press Enter">
                                            <button type="button" class="btn btn-info btn-sm mt-2 add-subject-btn">+ Add Subject</button>
                                            <div class="subjects-container d-flex flex-wrap"></div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-info btn-sm mt-2" onclick="addTeachingLoadField()">Upload Another Teaching Load</button>
                            </div>
                        </div>
                    </div>

                    <!-- SKILLS CATALOG CARD -->
                    <div class="card card-section">
                        <div class="card-header m-b-20">
                            <h4>Skills Catalog</h4>
                        </div>
                        <div class="card-body" id="skills_section">
                            <div id="skills-proof-pairs">
                                <div class="skill-proof-group">
                                    <button type="button" class="btn btn-secondary btn-sm remove-btn-skillproof" style="display:none;" onclick="removeSkillProofField(this)">Remove</button>
                                    <div class="form-group">
                                        <label>Skills</label>
                                        <input type="text" class="form-control skill-input" placeholder="Type a skill and press Enter">
                                        <button type="button" class="btn btn-info btn-sm mt-2 add-skill-btn">+ Add Skill</button>
                                        <div class="skills-container d-flex flex-wrap"></div>
                                    </div>
                                    <div class="form-group">
                                        <label>Upload Skill Proof for above skills</label>
                                        <input type="file" class="form-control" name="skills_proof_file_0[]" multiple accept="application/pdf,image/*">
                                        <small class="form-text text-muted">Please upload proof(s) for the skill(s) above (e.g., certificates, diplomas, awards, etc.).</small>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-info btn-sm mt-2" onclick="addSkillProofField()">Add Another Skills & Proof Set</button>
                        </div>
                    </div>

                    <!-- COURSES/CAN BE LOADED TO CARD -->
                    <div class="card card-section">
                        <div class="card-header m-b-20">
                            <h4>Courses/Programs the Teacher Can Be Loaded To</h4>
                            <small class="text-muted">Select the courses/programs this teacher can be assigned to. All are checked by default.</small>
                        </div>
                        <div class="card-body">
                            <?php if(count($courses)): ?>
                            <div class="form-group">
                                <?php foreach($courses as $course): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="courses_load[]" id="course_<?php echo $course['ID']; ?>" value="<?php echo $course['ID']; ?>" checked>
                                    <label class="form-check-label" for="course_<?php echo $course['ID']; ?>">
                                        <?php echo htmlspecialchars($course['CourseName']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning">No courses/programs found. Please contact administrator.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- PREFERRED TIME CARD -->
                    <div class="card card-section">
                        <div class="card-header m-b-20">
                            <h4>Preferred Teaching Schedule</h4>
                            <small class="text-muted">Select your preferred class time(s) for each day. Time ranges are based on institutional schedule.</small>
                        </div>
                        <div class="card-body">
                            <?php if ($schedule && count($available_days)) { ?>
                            <div class="form-group">
                                <label>Available Days:</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars(implode(', ', $available_days)); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Choose Preferred Time per Day:</label>
                                <div class="table-responsive">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Day</th>
                                            <th>Morning<br><small><?php echo display_time($schedule['morning_start']).' - '.display_time($schedule['morning_end']); ?></small></th>
                                            <th>Afternoon<br><small><?php echo display_time($schedule['afternoon_start']).' - '.display_time($schedule['afternoon_end']); ?></small></th>
                                            <th>Night<br><small><?php echo display_time($schedule['night_start']).' - '.display_time($schedule['night_end']); ?></small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($available_days as $day): ?>
                                    <tr>
                                        <td><?php echo $day; ?></td>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="preferred_time[<?php echo $day; ?>][]" value="morning" id="pref_<?php echo $day; ?>_morning">
                                                <label class="form-check-label" for="pref_<?php echo $day; ?>_morning">Morning</label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="preferred_time[<?php echo $day; ?>][]" value="afternoon" id="pref_<?php echo $day; ?>_afternoon">
                                                <label class="form-check-label" for="pref_<?php echo $day; ?>_afternoon">Afternoon</label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="preferred_time[<?php echo $day; ?>][]" value="night" id="pref_<?php echo $day; ?>_night">
                                                <label class="form-check-label" for="pref_<?php echo $day; ?>_night">Night</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                            <?php } else { ?>
                            <div class="alert alert-warning">No schedule found. Please contact administrator.</div>
                            <?php } ?>
                        </div>
                    </div>
                    <!-- END PREFERRED TIME CARD -->

                    <button class="btn btn-warning btn-lg" type="submit" name="submit">Save</button>
                    <button class="btn btn-secondary btn-lg" type="reset">Reset</button>
                </form>
                <?php include_once('includes/footer.php'); ?>
            </div>
        </div>
    </div>
</div>

<script>
// Subjects as tags per teaching load field
function subjectTagElement(subject) {
    const tag = document.createElement('span');
    tag.classList.add('tag-badge');
    tag.innerHTML = `${subject}<span class="remove-tag">&times;</span>`;

    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'subjects_taught[]';
    hiddenInput.value = subject;
    tag.appendChild(hiddenInput);

    tag.querySelector('.remove-tag').addEventListener('click', () => {
        tag.remove();
    });

    return tag;
}

function setupSubjectTagging(teachingLoadGroup) {
    const input = teachingLoadGroup.querySelector('.subject-input');
    const btn = teachingLoadGroup.querySelector('.add-subject-btn');
    const container = teachingLoadGroup.querySelector('.subjects-container');

    btn.addEventListener('click', function () {
        const subject = input.value.trim();
        if (subject) {
            container.appendChild(subjectTagElement(subject));
            input.value = '';
        }
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            btn.click();
        }
    });
}

// Initial setup for first teaching-load-group
document.querySelectorAll('.teaching-load-group').forEach(setupSubjectTagging);

// Add another teaching load functionality
function addTeachingLoadField() {
    const container = document.getElementById('teaching-load-fields');
    const group = document.createElement('div');
    group.classList.add('teaching-load-group');
    group.innerHTML = `
        <button type="button" class="btn btn-secondary btn-sm remove-btn-teachingload" onclick="removeTeachingLoadField(this)">Remove</button>
        <div class="form-group d-flex align-items-center">
            <label class="mr-3 mb-0">Upload Teaching Load</label>
            <input type="file" class="form-control" name="teaching_load[]" accept="application/pdf,image/*" style="max-width: 300px; display: inline-block;">
        </div>
        <div class="form-group">
            <label>Subjects Taught</label>
            <input type="text" class="form-control subject-input" placeholder="Type a subject and press Enter">
            <button type="button" class="btn btn-info btn-sm mt-2 add-subject-btn">+ Add Subject</button>
            <div class="subjects-container d-flex flex-wrap"></div>
        </div>
    `;
    container.appendChild(group);
    setupSubjectTagging(group);
}

// Remove teaching load field
function removeTeachingLoadField(button) {
    button.parentElement.remove();
}

// --- Skills Tagging for Skill-Proof-Groups ---

function skillTagElement(skill, index) {
    const tag = document.createElement('span');
    tag.classList.add('tag-badge');
    tag.innerHTML = `${skill}<span class="remove-tag">&times;</span>`;

    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = `skill_tag_${index}[]`;
    hiddenInput.value = skill;
    tag.appendChild(hiddenInput);

    tag.querySelector('.remove-tag').addEventListener('click', () => {
        tag.remove();
    });

    return tag;
}

function setupSkillTagging(skillProofGroup, index) {
    const input = skillProofGroup.querySelector('.skill-input');
    const btn = skillProofGroup.querySelector('.add-skill-btn');
    const container = skillProofGroup.querySelector('.skills-container');

    btn.addEventListener('click', function () {
        const skill = input.value.trim();
        if (skill) {
            container.appendChild(skillTagElement(skill, index));
            input.value = '';
        }
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            btn.click();
        }
    });
}

// Initial setup for first skill-proof-group
document.querySelectorAll('.skill-proof-group').forEach(function(group, i) {
    setupSkillTagging(group, i);
});

// Add another skill+proof set functionality
function addSkillProofField() {
    const container = document.getElementById('skills-proof-pairs');
    const index = container.querySelectorAll('.skill-proof-group').length;
    const group = document.createElement('div');
    group.classList.add('skill-proof-group');
    group.innerHTML = `
        <button type="button" class="btn btn-secondary btn-sm remove-btn-skillproof" onclick="removeSkillProofField(this)">Remove</button>
        <div class="form-group">
            <label>Skills</label>
            <input type="text" class="form-control skill-input" placeholder="Type a skill and press Enter">
            <button type="button" class="btn btn-info btn-sm mt-2 add-skill-btn">+ Add Skill</button>
            <div class="skills-container d-flex flex-wrap"></div>
        </div>
        <div class="form-group">
            <label>Upload Skill Proof for above skills</label>
            <input type="file" class="form-control" name="skills_proof_file_${index}[]" multiple accept="application/pdf,image/*">
            <small class="form-text text-muted">Please upload proof(s) for the skill(s) above (e.g., certificates, diplomas, awards, etc.).</small>
        </div>
    `;
    container.appendChild(group);
    setupSkillTagging(group, index);
}

// Remove skill proof field
function removeSkillProofField(button) {
    button.parentElement.parentElement.remove();
}

// Hide remove button for first skill proof field and first teaching load group on load
document.addEventListener("DOMContentLoaded", function(){
    let firstRemoveBtnSkill = document.querySelector("#skills-proof-pairs .remove-btn-skillproof");
    if (firstRemoveBtnSkill) {
        firstRemoveBtnSkill.style.display = "none";
    }
    let firstRemoveBtnTeach = document.querySelector("#teaching-load-fields .remove-btn-teachingload");
    if (firstRemoveBtnTeach) {
        firstRemoveBtnTeach.style.display = "none";
    }
});
</script>

<script src="../assets/js/lib/jquery.min.js"></script>
<script src="../assets/js/lib/bootstrap.min.js"></script>
<script src="../assets/js/scripts.js"></script>
</body>
</html>