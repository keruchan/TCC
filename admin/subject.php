<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit();
}

// Delete subject if delid is present
if (isset($_GET['delid'])) {
    $rid = intval($_GET['delid']);
    $sql = "DELETE FROM tblsubject WHERE id = :rid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':rid', $rid, PDO::PARAM_INT);
    $query->execute();
    echo "<script>alert('Subject deleted');</script>";
    echo "<script>window.location.href='subject.php'</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Subject List</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
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
                            <h1>Subjects <a href="add_subject.php" class="btn btn-warning btn-sm ml-3">+ Add Subject</a></h1>
                        </div>
                    </div>
                    <div class="card alert">
                        <div class="card-header">
                            <h4>All Subjects</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="subjectTable" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Subject Name</th>
                                            <th>Code</th>
                                            <th>Units</th>
                                            <th>Tags</th>
                                            <th>Created</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
<?php
$sql = "SELECT s.id, s.subject_name, s.subject_code, s.units, s.description, s.date_created, q.tags
        FROM tblsubject s
        LEFT JOIN tblqualification q ON s.id = q.subject_id
        ORDER BY s.date_created DESC";
$query = $dbh->prepare($sql);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
$cnt = 1;
foreach ($results as $row): ?>
<tr>
    <td><?= $cnt++ ?></td>
    <td><?= htmlentities($row->subject_name) ?></td>
    <td><?= htmlentities($row->subject_code) ?></td>
    <td><?= htmlentities($row->units) ?></td>
    <td>
        <?php if (!empty($row->tags)): ?>
            <?php foreach (explode(',', $row->tags) as $tag): ?>
                <span class="badge badge-info"><?= htmlentities(trim($tag)) ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </td>
    <td><?= htmlentities($row->date_created) ?></td>
    <td>
        <a href="edit_subject.php?editid=<?= $row->id ?>"><i class="ti-pencil-alt color-success"></i></a>
        <a href="subject.php?delid=<?= $row->id ?>" onclick="return confirm('Delete this subject?');"><i class="ti-trash color-danger"></i></a>
    </td>
</tr>
<?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php include_once('includes/footer.php'); ?>
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
    $('#subjectTable').DataTable();
});
</script>
</body>
</html>
