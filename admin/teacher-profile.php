<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid']) == 0) {
    header('location:logout.php');
    exit;
}

$teacherID = $_GET['viewid'] ?? $_SESSION['tsasaid'];

$sql = "SELECT * FROM tblteacher WHERE TeacherID = :tid";
$query = $dbh->prepare($sql);
$query->bindParam(':tid', $teacherID, PDO::PARAM_INT);
$query->execute();
$teacher = $query->fetch(PDO::FETCH_OBJ);

if (!$teacher) {
    echo "<script>alert('Teacher not found.'); window.location.href='manage-teacher.php';</script>";
    exit();
}

$skillsSql = "SELECT SkillName, ProofFile, Verified, SkillID FROM tblskills WHERE TeacherID = :tid";
$skillsQuery = $dbh->prepare($skillsSql);
$skillsQuery->bindParam(':tid', $teacherID, PDO::PARAM_INT);
$skillsQuery->execute();
$skills = $skillsQuery->fetchAll(PDO::FETCH_OBJ);

$loadsSql = "SELECT TeachingLoadFile, SubjectsTaught, Verified, TeachingLoadID FROM tblteachingload WHERE TeacherID = :tid";
$loadsQuery = $dbh->prepare($loadsSql);
$loadsQuery->bindParam(':tid', $teacherID, PDO::PARAM_INT);
$loadsQuery->execute();
$teachingLoads = $loadsQuery->fetchAll(PDO::FETCH_OBJ);

$preferredSql = "SELECT day_of_week, time_slot FROM teacher_preferred_times WHERE teacher_id = :tid ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')";
$preferredQuery = $dbh->prepare($preferredSql);
$preferredQuery->bindParam(':tid', $teacherID, PDO::PARAM_INT);
$preferredQuery->execute();
$preferredRaw = $preferredQuery->fetchAll(PDO::FETCH_ASSOC);

$preferredTimes = [];
foreach ($preferredRaw as $row) {
    $preferredTimes[$row['day_of_week']][] = $row['time_slot'];
}

// --- Fetch Courses/Programs the Teacher Can Be Loaded To ---
$coursesLoaded = [];
if (!empty($teacher->CoursesLoad)) {
    $ids = explode(',', $teacher->CoursesLoad);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $courseStmt = $dbh->prepare("SELECT ID, CourseName FROM tblcourse WHERE ID IN ($placeholders) ORDER BY CourseName");
        foreach ($ids as $k => $id) {
            $courseStmt->bindValue($k + 1, trim($id), PDO::PARAM_INT);
        }
        $courseStmt->execute();
        $coursesLoaded = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Fetch all courses for the edit modal
$allCourses = [];
$stmtAll = $dbh->prepare("SELECT ID, CourseName FROM tblcourse ORDER BY CourseName");
$stmtAll->execute();
$allCourses = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

/**
 * Calculate verification percentage
 */
$totalToVerify = 0;
$totalVerified = 0;

// Bachelor's degree always available for verification
$totalToVerify++;
if (!empty($teacher->Bachelors) && isset($teacher->BachelorsVerified) && $teacher->BachelorsVerified) $totalVerified++;
elseif (!empty($teacher->Bachelors) && isset($teacher->BachelorsVerified)) ; // included in totalToVerify

// Master's if present
if (!empty($teacher->Masters)) {
    $totalToVerify++;
    if (isset($teacher->MastersVerified) && $teacher->MastersVerified) $totalVerified++;
}
// Doctorate if present
if (!empty($teacher->Doctorate)) {
    $totalToVerify++;
    if (isset($teacher->DoctorateVerified) && $teacher->DoctorateVerified) $totalVerified++;
}

// Each teaching load record is one verification slot
foreach ($teachingLoads as $load) {
    $totalToVerify++;
    if ($load->Verified) $totalVerified++;
}

// Each skills record is one verification slot
foreach ($skills as $skill) {
    $totalToVerify++;
    if ($skill->Verified) $totalVerified++;
}

// Avoid division by zero
$verificationPercent = $totalToVerify > 0 ? round(($totalVerified / $totalToVerify) * 100) : 0;

// --- Handle Max Load Section ---
$maxLoad = $teacher->MaxLoad ?? '';
$defaultMaxLoad = 18; // Default value, change as required
if ($maxLoad === '' || $maxLoad === null) {
    $maxLoad = $defaultMaxLoad;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Teacher Profile</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .tag {
            display: inline-block;
            background: #17a2b8;
            color: white;
            padding: 3px 10px;
            margin: 0 3px;
            border-radius: 12px;
            font-size: 12px;
        }
        .profile-card {
            max-width: 900px;
            margin: 50px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 70px;
            position: relative;
        }
        .profile-header {
            display: flex;
            gap: 20px;
            align-items: center;
            justify-content: space-between;
        }
        .profile-header-content {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .profile-header img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #eee;
        }
        .doughnut-container {
            position: absolute;
            top: 30px;
            right: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .doughnut-label {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            text-align: center;
            pointer-events: none;
            user-select: none;
            margin-top: -65px;
        }
        .doughnut-verified-text {
            font-size: 14px;
            font-weight: normal;
            color: #007bff;
            text-align: center;
            margin-top: -5px;
        }
        @media (max-width: 1200px) {
            .doughnut-container {
                position: static;
                margin-bottom: 20px;
            }
            .profile-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        .verify-icon {
            cursor: pointer;
            font-size: 18px;
            margin-left: 8px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
        }
        .verify-icon.unverified { background-color: #d9534f; }
        .verify-icon.verified { background-color: #007bff; }
        .verify-icon i { color: white; }
        .list-row { padding: 10px 0; border-bottom: 1px solid #f1f1f1; display: flex; align-items: center; }
        .list-row:last-child { border-bottom: none; }
        .subjects-label, .skills-label { font-weight: bold; margin-right: 8px; }
        .load-actions, .skill-actions { display: flex; align-items: center; gap: 6px; }
        .skills-catalog-title { margin-bottom: 10px; }
        .skill-proof { margin-bottom: 0; margin-left: 5px; font-size: 12px; }
        .modal-header { border-bottom: 1px solid #eee; }
        .editable-tags { margin-bottom: 10px; }
        .editable-tags .tag { margin-bottom: 3px; margin-right: 5px; }
        .remove-tag-btn { color: #d9534f; background: none; border: none; font-size: 14px; margin-left: 4px; cursor: pointer; vertical-align: middle; }
        .add-tag-input { width: 160px; display: inline-block; margin-right: 5px; margin-bottom: 5px; }
        .teacher-user-info {
            margin: 12px 0 0px 0;
            font-size: 15px;
        }
        .teacher-user-info strong {
            color: #007bff;
            font-weight: 600;
        }
        .section-programs {
            margin-bottom: 25px;
        }
        .edit-link-programs {
            font-size: 13px;
            margin-left: 10px;
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        /* Max Load styles */
        .section-maxload {
            margin-bottom: 20px;
        }
        .edit-link-maxload {
            font-size: 13px;
            margin-left: 10px;
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
    </style>
</head>
<body>
<?php include_once('includes/sidebar.php'); ?>
<?php include_once('includes/header.php'); ?>

<div class="content-wrap">
    <div class="main">
        <div class="profile-card">
            <div class="doughnut-container" style="width:110px;height:110px;">
                <canvas id="verificationDoughnut" width="90" height="90"></canvas>
                <div class="doughnut-label" id="doughnutLabel"><?= $verificationPercent ?>%</div>
                <div class="doughnut-verified-text">Verified</div>
            </div>
            <div class="profile-header">
                <div class="profile-header-content">
                    <img src="images/<?= htmlentities($teacher->ProfilePic) ?>" alt="Profile Picture">
                    <div>
                        <h3><?= htmlentities($teacher->FirstName . ' ' . $teacher->LastName) ?></h3>
                        <div class="teacher-user-info">
                            <div><strong>Username:</strong> <?= htmlentities($teacher->UserName) ?></div>
                            <div><strong>Email:</strong> <?= htmlentities($teacher->Email) ?></div>
                        </div>
                        <p><strong>Employment Type:</strong> <?= htmlentities($teacher->EmploymentType) ?>
                        <a href="javascript:void(0);" class="btn btn-link" data-toggle="modal" data-target="#employmentModal" onclick="setEmploymentType(<?= $teacher->TeacherID ?>)">Edit</a></p>
                    </div>
                </div>
            </div>

            <!-- Max Load section -->
            <div class="section section-maxload">
                <h4>
                    Maximum Teaching Load
                    <button type="button" class="edit-link-maxload" data-toggle="modal" data-target="#editMaxLoadModal">
                        Edit
                    </button>
                </h4>
                <span><?= htmlentities($maxLoad) ?> units</span>
                <span style="color:#888; font-size:12px;">
                    <?= ($teacher->MaxLoad === null || $teacher->MaxLoad === '') ? "(Default: $defaultMaxLoad units)" : "" ?>
                </span>
            </div>

            <!-- Programs/Courses section -->
            <div class="section section-programs">
                <h4>
                    Programs/Courses the Teacher Can Be Loaded To
                    <button type="button" class="edit-link-programs" data-toggle="modal" data-target="#editProgramsModal">
                        Edit
                    </button>
                </h4>
                <?php if (!empty($coursesLoaded)): ?>
                    <?php foreach ($coursesLoaded as $c): ?>
                        <span class="tag"><?= htmlentities($c['CourseName']) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span style="color:#888;">None assigned</span>
                <?php endif; ?>
            </div>

            <div class="section">
                <h4>Educational Background</h4>
                <p><strong>Bachelor's Degree:</strong> <?= htmlentities($teacher->Bachelors) ?> 
                    <a href="uploads/<?= htmlentities($teacher->BachelorsTOR) ?>" target="_blank" class="btn btn-link view-attachments-link">View Attachments</a>
                    <span class="verify-icon <?= $teacher->BachelorsVerified ? 'verified' : 'unverified' ?>" id="bachelor-verify" data-degree="bachelor" data-type="degree" data-id="<?= $teacher->TeacherID ?>">
                        <i class="fa <?= $teacher->BachelorsVerified ? 'fa-check' : 'fa-times' ?>"></i>
                    </span>
                </p>
                <?php if (!empty($teacher->Masters)): ?>
                <p><strong>Master's Degree:</strong> <?= htmlentities($teacher->Masters) ?> 
                    <?php if (!empty($teacher->MastersTOR)): ?>
                    <a href="uploads/<?= htmlentities($teacher->MastersTOR) ?>" target="_blank" class="btn btn-link view-attachments-link">View Attachments</a>
                    <?php endif; ?>
                    <span class="verify-icon <?= $teacher->MastersVerified ? 'verified' : 'unverified' ?>" id="master-verify" data-degree="master" data-type="degree" data-id="<?= $teacher->TeacherID ?>">
                        <i class="fa <?= $teacher->MastersVerified ? 'fa-check' : 'fa-times' ?>"></i>
                    </span>
                </p>
                <?php endif; ?>
                <?php if (!empty($teacher->Doctorate)): ?>
                <p><strong>Doctorate Degree:</strong> <?= htmlentities($teacher->Doctorate) ?> 
                    <?php if (!empty($teacher->DoctorateTOR)): ?>
                    <a href="uploads/<?= htmlentities($teacher->DoctorateTOR) ?>" target="_blank" class="btn btn-link view-attachments-link">View Attachments</a>
                    <?php endif; ?>
                    <span class="verify-icon <?= $teacher->DoctorateVerified ? 'verified' : 'unverified' ?>" id="doctorate-verify" data-degree="doctorate" data-type="degree" data-id="<?= $teacher->TeacherID ?>">
                        <i class="fa <?= $teacher->DoctorateVerified ? 'fa-check' : 'fa-times' ?>"></i>
                    </span>
                </p>
                <?php endif; ?>
            </div>

            <div class="section">
                <h4>Teaching Experience</h4>
                <div>
                    <?php if (!empty($teachingLoads)): ?>
                        <?php foreach ($teachingLoads as $load): ?>
                            <div class="list-row">
                                <span class="subjects-label">Subjects:</span>
                                <span>
                                    <?php
                                        $subjects = array_map('trim', explode(',', $load->SubjectsTaught));
                                        foreach ($subjects as $i => $subject):
                                            if ($subject === "") continue;
                                    ?>
                                        <span class="tag"><?= htmlentities($subject) ?></span><?= ($i < count($subjects)-1) ? ',' : '' ?>
                                    <?php endforeach; ?>
                                </span>
                                <span class="load-actions" style="margin-left:15px;">
                                    | <a href="uploads/<?= htmlentities($load->TeachingLoadFile) ?>" target="_blank" class="view-attachments-link">View Load</a>
                                    | <a href="javascript:void(0);" class="edit-link edit-load-link" data-id="<?= $load->TeachingLoadID ?>" data-subjects="<?= htmlentities($load->SubjectsTaught) ?>">Edit</a>
                                    <span class="verify-icon <?= $load->Verified ? 'verified' : 'unverified' ?>" id="load-verify-<?= $load->TeachingLoadID ?>" data-type="load" data-id="<?= $load->TeachingLoadID ?>">
                                        <i class="fa <?= $load->Verified ? 'fa-check' : 'fa-times' ?>"></i>
                                    </span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="color:#888;">No teaching experience found.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section">
                <h4 class="skills-catalog-title">Skills Catalog</h4>
                <div>
                <?php if (!empty($skills)): ?>
                <?php foreach ($skills as $skill): ?>
                    <div class="list-row">
                        <span class="skills-label">Skills:</span>
                        <span>
                            <?php
                                $skillNames = array_map('trim', explode(',', $skill->SkillName));
                                foreach ($skillNames as $i => $sk):
                                    if ($sk === "") continue;
                            ?>
                                <span class="tag"><?= htmlentities($sk) ?></span><?= ($i < count($skillNames)-1) ? ',' : '' ?>
                            <?php endforeach; ?>
                        </span>
                        <span class="skill-actions" style="margin-left:15px;">
                            | <?php if (!empty($skill->ProofFile)): ?>
                                <span class="skill-proof"><strong>Proof:</strong>
                                    <?php foreach (explode(',', $skill->ProofFile) as $file): ?>
                                        <?php $file = trim($file); if ($file !== ""): ?>
                                        <a href="uploads/<?= $file ?>" target="_blank" class="proof-view-link">View</a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </span>
                                |
                            <?php endif; ?>
                            <a href="javascript:void(0);" class="edit-link edit-skill-link" data-id="<?= $skill->SkillID ?>" data-skills="<?= htmlentities($skill->SkillName) ?>">Edit</a>
                            <span class="verify-icon <?= $skill->Verified ? 'verified' : 'unverified' ?>" id="skill-verify-<?= $skill->SkillID ?>" data-type="skill" data-id="<?= $skill->SkillID ?>">
                                <i class="fa <?= $skill->Verified ? 'fa-check' : 'fa-times' ?>"></i>
                            </span>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div style="color:#888;">No skills found.</div>
                <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($preferredTimes)): ?>
            <div class="section">
                <h4>Preferred Teaching Time</h4>
                <?php foreach ($preferredTimes as $day => $slots): ?>
                    <p><strong><?= htmlentities($day) ?>:</strong>
                        <?php foreach ($slots as $slot): ?>
                            <span class="tag"><?= htmlentities(ucfirst($slot)) ?></span>
                        <?php endforeach; ?>
                    </p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="back-button">
                <a href="manage-teacher.php" class="btn btn-primary">← Back to List</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Editing Max Load -->
<div class="modal fade" id="editMaxLoadModal" tabindex="-1" role="dialog" aria-labelledby="editMaxLoadModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="POST" action="update-max-load.php" id="editMaxLoadForm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMaxLoadModalLabel">Edit Maximum Teaching Load</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="teacherid" value="<?= $teacherID ?>">
                <div class="form-group">
                    <label for="maxload">Max Load (units)</label>
                    <input type="number" name="maxload" id="maxload" class="form-control" min="0" max="50" value="<?= htmlentities($teacher->MaxLoad ?? $defaultMaxLoad) ?>" required>
                    <small class="form-text text-muted">Set to 0 for unlimited. Default: <?= $defaultMaxLoad ?> units if left empty.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </form>
</div>

<!-- Modal for Editing Programs/Courses -->
<div class="modal fade" id="editProgramsModal" tabindex="-1" role="dialog" aria-labelledby="editProgramsModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="POST" action="update-teacher-courses.php" id="editProgramsForm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProgramsModalLabel">Edit Programs/Courses the Teacher Can Be Loaded To</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="teacherid" value="<?= $teacherID ?>">
                <div class="form-group">
                    <?php foreach($allCourses as $course): ?>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="courses_load[]"
                                id="modal_course_<?= $course['ID'] ?>"
                                value="<?= $course['ID'] ?>"
                                <?= (in_array($course['ID'], array_column($coursesLoaded, 'ID'))) ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="modal_course_<?= $course['ID'] ?>">
                                <?= htmlentities($course['CourseName']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </form>
</div>

<!-- Modal for Editing Employment Type -->
<div class="modal fade" id="employmentModal" tabindex="-1" role="dialog" aria-labelledby="employmentModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="employmentModalLabel">Edit Employment Type</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="updateEmploymentForm" method="POST" action="update-employee-type.php">
          <input type="hidden" id="teacherID" name="teacherid" value="<?= $teacher->TeacherID ?>">
          <div class="form-group">
            <label for="employment_type">Employment Type</label>
            <select id="employment_type" name="employment_type" class="form-control" required>
                <option value="Part-time" <?= $teacher->EmploymentType === 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                <option value="Regular" <?= $teacher->EmploymentType === 'Regular' ? 'selected' : '' ?>>Regular</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Update</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal for Editing Teaching Experience Subjects -->
<div class="modal fade" id="editSubjectsModal" tabindex="-1" role="dialog" aria-labelledby="editSubjectsLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="editSubjectsForm" method="POST" action="update-subjects.php">
        <div class="modal-header">
          <h5 class="modal-title" id="editSubjectsLabel">Edit Subjects</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="teachingloadid" id="modalTeachingLoadID">
            <div class="editable-tags" id="subjectsTags"></div>
            <input type="text" id="subjectInput" class="add-tag-input form-control" placeholder="Add new subject">
            <button type="button" class="btn btn-sm btn-primary" id="addSubjectBtn">Add</button>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal for Editing Skills -->
<div class="modal fade" id="editSkillsModal" tabindex="-1" role="dialog" aria-labelledby="editSkillsLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="editSkillsForm" method="POST" action="update-skills.php">
        <div class="modal-header">
          <h5 class="modal-title" id="editSkillsLabel">Edit Skills</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="skillid" id="modalSkillID">
            <div class="editable-tags" id="skillsTags"></div>
            <input type="text" id="skillInput" class="add-tag-input form-control" placeholder="Add new skill">
            <button type="button" class="btn btn-sm btn-primary" id="addSkillBtn">Add</button>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/js/lib/jquery.min.js"></script>
<script src="../assets/js/lib/bootstrap.min.js"></script>
<script src="../assets/js/scripts.js"></script>

<script>
    // Doughnut chart for verification
    document.addEventListener("DOMContentLoaded", function() {
        var ctx = document.getElementById('verificationDoughnut').getContext('2d');
        var percent = <?= $verificationPercent ?>;
        var other = 100 - percent;
        var doughnut = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Verified', 'Unverified'],
                datasets: [{
                    data: [percent, other],
                    backgroundColor: [
                        '#007bff',
                        '#e9ecef'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: false,
                cutout: "70%",
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
        // Label is already in HTML, but if you want to update: 
        document.getElementById('doughnutLabel').textContent = percent + "%";
    });

    // Pass the TeacherID to the modal form when 'Edit' is clicked
    function setEmploymentType(teacherID) {
        document.getElementById('teacherID').value = teacherID;
    }

    $(document).ready(function() {
        // Degree verification
        $('.verify-icon[data-type="degree"]').click(function() {
            var type = $(this).data('type');
            var id = $(this).data('id');
            var degree = $(this).data('degree');
            var verified = $(this).find('i').hasClass('fa-times') ? 1 : 0;

            $.ajax({
                url: 'update-degree-verification.php',
                type: 'POST',
                data: {
                    teacherid: id,
                    degree: degree,
                    verified: verified
                },
                success: function(response) {
                    if (response === 'success') {
                        $('#' + degree + '-verify').toggleClass('verified unverified')
                                                  .html('<i class="fa ' + (verified ? 'fa-check' : 'fa-times') + '"></i>');
                        alert(verified ? 'Successfully verified!' : 'Successfully unverified!');
                        location.reload();
                    } else {
                        alert('There was an error updating the verification status.');
                    }
                }
            });
        });

        // Teaching load verification (update-teaching-experience.php)
        $('.verify-icon[data-type="load"]').click(function() {
            var teachingLoadID = $(this).data('id');
            var verified = $(this).find('i').hasClass('fa-times') ? 1 : 0;

            $.ajax({
                url: 'update-teaching-experience.php',
                type: 'POST',
                data: {
                    teachingloadid: teachingLoadID,
                    verified: verified
                },
                success: function(response) {
                    if (response === 'success') {
                        $('#load-verify-' + teachingLoadID).toggleClass('verified unverified')
                            .html('<i class="fa ' + (verified ? 'fa-check' : 'fa-times') + '"></i>');
                        alert(verified ? 'Successfully verified!' : 'Successfully unverified!');
                        location.reload();
                    } else {
                        alert('There was an error updating the verification status.');
                    }
                }
            });
        });

        // Skill verification (update-skill-verification.php)
        $('.verify-icon[data-type="skill"]').click(function() {
            var skillID = $(this).data('id');
            var verified = $(this).find('i').hasClass('fa-times') ? 1 : 0;

            $.ajax({
                url: 'update-skill-verification.php',
                type: 'POST',
                data: {
                    skillid: skillID,
                    verified: verified
                },
                success: function(response) {
                    if (response === 'success') {
                        $('#skill-verify-' + skillID).toggleClass('verified unverified')
                            .html('<i class="fa ' + (verified ? 'fa-check' : 'fa-times') + '"></i>');
                        alert(verified ? 'Successfully verified!' : 'Successfully unverified!');
                        location.reload();
                    } else {
                        alert('There was an error updating the verification status.');
                    }
                }
            });
        });

        // Edit Subjects Modal
        $('.edit-load-link').click(function() {
            var id = $(this).data('id');
            var subjects = $(this).data('subjects') ? $(this).data('subjects').split(',').map(function(s){return s.trim();}) : [];
            $('#modalTeachingLoadID').val(id);
            renderTags('subjectsTags', subjects, 'subject');
            $('#editSubjectsModal').modal('show');
        });

        // Edit Skills Modal
        $('.edit-skill-link').click(function() {
            var id = $(this).data('id');
            var skills = $(this).data('skills') ? $(this).data('skills').split(',').map(function(s){return s.trim();}) : [];
            $('#modalSkillID').val(id);
            renderTags('skillsTags', skills, 'skill');
            $('#editSkillsModal').modal('show');
        });

        // Add/Remove Subjects
        $('#addSubjectBtn').click(function() {
            var val = $('#subjectInput').val().trim();
            if(val) {
                addTag('subjectsTags', val, 'subject');
                $('#subjectInput').val('');
            }
        });
        $(document).on('click', '.remove-tag-btn[data-type="subject"]', function() {
            $(this).closest('.tag').remove();
        });

        // Add/Remove Skills
        $('#addSkillBtn').click(function() {
            var val = $('#skillInput').val().trim();
            if(val) {
                addTag('skillsTags', val, 'skill');
                $('#skillInput').val('');
            }
        });
        $(document).on('click', '.remove-tag-btn[data-type="skill"]', function() {
            $(this).closest('.tag').remove();
        });

        // On form submit, set hidden input with CSV value, and prevent empty
        $('#editSubjectsForm').submit(function() {
            var tags = [];
            $('#subjectsTags .tag-text').each(function() {
                tags.push($(this).text());
            });
            $(this).find('input[name="subjects"]').remove();
            $('<input type="hidden" name="subjects">').val(tags.join(',')).appendTo(this);
            if (tags.length === 0) {
                alert('Please add at least one subject.');
                return false;
            }
        });
        $('#editSkillsForm').submit(function() {
            var tags = [];
            $('#skillsTags .tag-text').each(function() {
                tags.push($(this).text());
            });
            $(this).find('input[name="skills"]').remove();
            $('<input type="hidden" name="skills">').val(tags.join(',')).appendTo(this);
            if (tags.length === 0) {
                alert('Please add at least one skill.');
                return false;
            }
        });

        // Utility to render tags
        function renderTags(container, arr, type) {
            var html = '';
            arr.filter(Boolean).forEach(function(tag) {
                html += '<span class="tag"><span class="tag-text">' + $('<div/>').text(tag).html() + '</span><button type="button" class="remove-tag-btn" data-type="' + type + '">&times;</button></span>';
            });
            $('#' + container).html(html);
        }
        function addTag(container, text, type) {
            var exists = false;
            $('#' + container + ' .tag-text').each(function() {
                if($(this).text().toLowerCase() === text.toLowerCase()) exists = true;
            });
            if(!exists) {
                $('#' + container).append('<span class="tag"><span class="tag-text">' + $('<div/>').text(text).html() + '</span><button type="button" class="remove-tag-btn" data-type="' + type + '">&times;</button></span>');
            }
        }
    });
</script>
</body>
</html>