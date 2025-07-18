<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid']==0)) {
  header('location:logout.php');
} else {
    if(isset($_POST['submit'])) {
        $tsasaid = $_SESSION['tsasaid'];
        $bname = $_POST['branchname'];
        $cname = $_POST['coursename'];

        $sql = "insert into tblcourse(BranchName,CourseName)values(:branchname,:coursename)";
        $query = $dbh->prepare($sql);
        $query->bindParam(':branchname', $bname, PDO::PARAM_STR);
        $query->bindParam(':coursename', $cname, PDO::PARAM_STR);
        $query->execute();

        $LastInsertId = $dbh->lastInsertId();
        if ($LastInsertId > 0) {
            echo '<script>alert("Course has been added.")</script>';
            echo "<script>window.location.href ='course.php'</script>";
        } else {
            echo '<script>alert("Something Went Wrong. Please try again")</script>';
        }
    }

    if(isset($_GET['delid'])) {
        $rid = intval($_GET['delid']);
        $sql = "delete from tblcourse where ID=:rid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':rid', $rid, PDO::PARAM_STR);
        $query->execute();
        echo "<script>alert('Data deleted');</script>"; 
        echo "<script>window.location.href = 'course.php'</script>";
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Course List</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
</head>
<body>
<?php include_once('includes/sidebar.php');?>
<?php include_once('includes/header.php');?>
<div class="content-wrap">
    <div class="main">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="page-header">
                        <div class="page-title">
                            <h1>Course <a href="add_course.php" class="btn btn-warning btn-sm ml-3">+ Add Course</a></h1>
                        </div>
                    </div>
                    <div class="card alert">
                        <div class="card-header">
                            <h4>All Courses</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="courseTable" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Course Name</th>
                                            <th>Total Units</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
<?php
$sql = "SELECT * FROM tblcourse ORDER BY ID DESC";
$query = $dbh->prepare($sql);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
$cnt = 1;
foreach($results as $row): ?>
<tr>
    <td><?= $cnt++ ?></td>
    <td><?= htmlentities($row->CourseName) ?></td>
    <td><?= htmlentities($row->TotalUnits) ?></td>
    <td>
        <a href="edit-course.php?editid=<?= $row->ID ?>"><i class="ti-pencil-alt color-success"></i></a>
        <a href="course.php?delid=<?= $row->ID ?>" onclick="return confirm('Do you really want to Delete ?');"><i class="ti-trash color-danger"></i></a>
    </td>
</tr>
<?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php include_once('includes/footer.php');?>
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
    $('#courseTable').DataTable();
});
</script>
</body>
</html>
<?php } ?>
