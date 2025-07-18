<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid']==0)) {
  header('location:logout.php');
} else {
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>TSAS : Manage Teacher</title>
    
    <!-- Styles -->
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
                                <h1>Teacher Management 
                                    <!-- Add Teacher Button like in reference -->
                                    <a href="add-teacher.php" class="btn btn-warning btn-sm ml-3">+ Add Teacher</a>
                                </h1>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card alert">
                    <div class="card-header">
                        <h4>All Teachers</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="teacherTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Teacher Name</th>
                                        <th>Employee ID</th>
                                        <th>Email</th>
                                        <th>Mobile Number</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT * FROM tblteacher ORDER BY ID DESC";
                                    $query = $dbh->prepare($sql);
                                    $query->execute();
                                    $results = $query->fetchAll(PDO::FETCH_OBJ);
                                    $cnt = 1;
                                    foreach ($results as $row) { ?>
                                        <tr>
                                            <td><?= $cnt++ ?></td>
                                            <td><?= htmlentities($row->FirstName . ' ' . $row->LastName) ?></td>
                                            <td><?= htmlentities($row->EmpID) ?></td>
                                            <td><?= htmlentities($row->Email) ?></td>
                                            <td><?= htmlentities($row->MobileNumber) ?></td>
                                            <td>
                                                <a href="edit-teacher.php?editid=<?= $row->ID ?>"><i class="ti-pencil-alt color-success"></i></a>
                                                <a href="manage-teacher.php?delid=<?= $row->ID ?>" onclick="return confirm('Do you really want to Delete ?');"><i class="ti-trash color-danger"></i></a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
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
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#teacherTable').DataTable();
        });
    </script>
</body>

</html>
<?php } ?>
