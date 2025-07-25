<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid'] == 0)) {
    header('location:logout.php');
} else {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Manage Teacher</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <style>
        .tag-badge {
            display: inline-block;
            padding: 5px 10px;
            background-color: #17a2b8;
            color: #fff;
            font-size: 12px;
            border-radius: 20px;
            margin: 2px 4px 2px 0;
        }
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
                                <h1>Teacher Management 
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
                                        <th>Full Name</th>
                                        <th>Employment Type</th>
                                        <th>Skills</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT 
                                                t.TeacherID, t.FirstName, t.LastName, t.EmploymentType,
                                                GROUP_CONCAT(s.SkillName SEPARATOR ', ') AS Skills
                                            FROM tblteacher t
                                            LEFT JOIN tblskills s ON t.TeacherID = s.TeacherID
                                            GROUP BY t.TeacherID, t.FirstName, t.LastName, t.EmploymentType
                                            ORDER BY t.TeacherID DESC";
                                    $query = $dbh->prepare($sql);
                                    $query->execute();
                                    $results = $query->fetchAll(PDO::FETCH_OBJ);
                                    $cnt = 1;
                                    foreach ($results as $row) {
                                    ?>
                                        <tr>
                                            <td><?= $cnt++ ?></td>
                                            <td><?= htmlentities($row->LastName . ', ' . $row->FirstName) ?></td>
                                            <td><?= htmlentities($row->EmploymentType) ?></td>
                                            <td>
                                                <?php
                                                $skills = explode(',', $row->Skills ?? '');
                                                foreach ($skills as $skill) {
                                                    $trimmed = trim($skill);
                                                    if (!empty($trimmed)) {
                                                        echo "<span class='tag-badge'>" . htmlentities($trimmed) . "</span>";
                                                    }
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="teacher-profile.php?viewid=<?= $row->TeacherID ?>"><i class="ti-eye color-primary"></i></a>
                                                <a href="manage-teacher.php?delid=<?= $row->TeacherID ?>" onclick="return confirm('Do you really want to Delete ?');"><i class="ti-trash color-danger"></i></a>
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
        $(document).ready(function () {
            $('#teacherTable').DataTable();
        });
    </script>
</body>
</html>
<?php } ?>
