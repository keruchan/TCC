<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('includes/dbconnection.php');

// Session check
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

// Validate editid
$eid = isset($_GET['editid']) ? intval($_GET['editid']) : 0;
if ($eid <= 0) {
    echo '<script>alert("Invalid course ID.");window.location.href="course.php";</script>';
    exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coursename'], $_POST['coursedesc'])) {
    $coursename = trim($_POST['coursename']);
    $coursedesc = trim($_POST['coursedesc']);

    $sql = "UPDATE tblcourse SET CourseName=:coursename, CourseDesc=:coursedesc WHERE ID=:eid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':coursename', $coursename, PDO::PARAM_STR);
    $query->bindParam(':coursedesc', $coursedesc, PDO::PARAM_STR);
    $query->bindParam(':eid', $eid, PDO::PARAM_INT);
    $query->execute();

    $_SESSION['msg'] = "Course has been updated";
    $_SESSION['msg_type'] = "success";
    echo "<script>window.location.href = 'edit-course.php?editid=$eid';</script>";
    exit;
}

// Fetch course info
$sql = "SELECT CourseName, CourseDesc FROM tblcourse WHERE ID = :eid";
$query = $dbh->prepare($sql);
$query->bindParam(':eid', $eid, PDO::PARAM_INT);
$query->execute();
$row = $query->fetch(PDO::FETCH_OBJ);

if (!$row) {
    echo '<script>alert("Course not found.");window.location.href="course.php";</script>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Edit Course</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include_once('includes/sidebar.php'); ?>
<?php include_once('includes/header.php'); ?>

<div class="content-wrap">
    <div class="main">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card alert">
                        <div class="card-header">
                            <h4>Edit Course</h4>
                        </div>
                        <div class="card-body">
                            <?php
                            if (!empty($_SESSION['msg'])) {
                                $msgType = $_SESSION['msg_type'] ?? 'info';
                                echo '<div class="alert alert-' . htmlspecialchars($msgType) . '">' . htmlspecialchars($_SESSION['msg']) . '</div>';
                                unset($_SESSION['msg'], $_SESSION['msg_type']);
                            }
                            ?>
                            <form method="post" autocomplete="off">
                                <div class="form-group">
                                    <label for="coursename">Course Name</label>
                                    <input type="text" name="coursename" class="form-control" required
                                           value="<?= htmlentities($row->CourseName ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="coursedesc">Course Description</label>
                                    <textarea name="coursedesc" class="form-control" rows="4"><?= htmlentities($row->CourseDesc ?? '') ?></textarea>
                                </div>
                                <button type="submit" name="submit" class="btn btn-primary">Update</button>
                                <a href="course.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once('includes/footer.php'); ?>
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
