<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid']) == 0) {
    header('location:logout.php');
    exit;
}

$teacherID = $_GET['viewid'] ?? 0;
$sql = "SELECT * FROM tblteacher WHERE TeacherID = :tid";
$query = $dbh->prepare($sql);
$query->bindParam(':tid', $teacherID, PDO::PARAM_INT);
$query->execute();
$teacher = $query->fetch(PDO::FETCH_OBJ);

if (!$teacher) {
    echo "<script>alert('Teacher not found.'); window.location.href='manage-teacher.php';</script>";
    exit();
}

$skillsSql = "SELECT SkillName, ProofFile FROM tblskills WHERE TeacherID = :tid";
$skillsQuery = $dbh->prepare($skillsSql);
$skillsQuery->bindParam(':tid', $teacherID, PDO::PARAM_INT);
$skillsQuery->execute();
$skills = $skillsQuery->fetchAll(PDO::FETCH_OBJ);

$loadsSql = "SELECT TeachingLoadFile, SubjectsTaught FROM tblteachingload WHERE TeacherID = :tid";
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
    <style>
        .tag {
            display: inline-block;
            background: #17a2b8;
            color: white;
            padding: 5px 12px;
            margin: 3px;
            border-radius: 15px;
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
        }
        .profile-header {
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
        .section {
            margin-top: 30px;
        }
        .section h4 {
            border-bottom: 2px solid #eee;
            padding-bottom: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .section p {
            margin-bottom: 10px;
        }
        .back-button {
            margin-top: 40px;
            text-align: center;
        }
    </style>
</head>
<body>
<?php include_once('includes/sidebar.php'); ?>
<?php include_once('includes/header.php'); ?>

<div class="content-wrap">
    <div class="main">
        <div class="profile-card">
            <div class="profile-header">
                <img src="images/<?= htmlentities($teacher->ProfilePic) ?>" alt="Profile Picture">
                <div>
                    <h3><?= htmlentities($teacher->FirstName . ' ' . $teacher->LastName) ?></h3>
                    <p><strong>Email:</strong> <?= htmlentities($teacher->Email) ?></p>
                    <p><strong>Employment Type:</strong> <?= htmlentities($teacher->EmploymentType) ?></p>
                </div>
            </div>

            <div class="section">
                <h4>Educational Background</h4>
                <p><strong>Bachelor's Degree:</strong> <?= htmlentities($teacher->Bachelors) ?></p>
                <p><strong>Master's Degree:</strong> <?= htmlentities($teacher->Masters) ?></p>
                <p><strong>Doctorate Degree:</strong> <?= htmlentities($teacher->Doctorate) ?></p>
            </div>

            <div class="section">
                <h4>Teaching Experience</h4>
                <p><strong>Has Experience:</strong> <?= htmlentities($teacher->HasExperience) ?></p>
                <?php if (!empty($teachingLoads)): ?>
                    <ul>
                        <?php foreach ($teachingLoads as $load): ?>
                            <li><strong>Subjects:</strong> <?= htmlentities($load->SubjectsTaught) ?> |
                            <a href="uploads/<?= htmlentities($load->TeachingLoadFile) ?>" target="_blank">View Load</a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="section">
                <h4>Skills</h4>
                <?php foreach ($skills as $skill): ?>
                    <?php
                        $skillNames = explode(',', $skill->SkillName);
                        foreach ($skillNames as $sk):
                    ?>
                        <span class="tag"><?= htmlentities(trim($sk)) ?></span>
                    <?php endforeach; ?>
                    <?php if (!empty($skill->ProofFile)): ?>
                        <div><strong>Proof:</strong>
                        <?php foreach (explode(',', $skill->ProofFile) as $file): ?>
                            <a href="uploads/<?= trim($file) ?>" target="_blank">[View]</a>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
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

            <div class="section">
                <h4>Uploaded Documents</h4>
                <p><strong>Bachelor's TOR:</strong> <a href="uploads/<?= htmlentities($teacher->BachelorsTOR) ?>" target="_blank">View</a></p>
                <p><strong>Master's TOR:</strong> <a href="uploads/<?= htmlentities($teacher->MastersTOR) ?>" target="_blank">View</a></p>
                <p><strong>Doctorate TOR:</strong> <a href="uploads/<?= htmlentities($teacher->DoctorateTOR) ?>" target="_blank">View</a></p>
            </div>

            <div class="back-button">
                <a href="manage-teacher.php" class="btn btn-primary">← Back to List</a>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/lib/jquery.min.js"></script>
<script src="../assets/js/lib/bootstrap.min.js"></script>
<script src="../assets/js/scripts.js"></script>
</body>
</html>
