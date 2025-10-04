<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid']==0)) {
  header('location:logout.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>BCC Admin : Dashboard</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .dashboard-card {
            border-radius: 15px;
            box-shadow: 0 3px 24px 0 #e3e7ed;
            transition: box-shadow 0.2s;
            margin-bottom: 32px;
            padding: 0;
        }
        .dashboard-card .media {
            padding: 28px 25px;
            align-items: center;
        }
        .dashboard-card .media-left {
            border-radius: 50%;
            background: #f5f7fa;
            padding: 15px;
            margin-right: 20px;
        }
        .dashboard-card .media-body h4 {
            margin: 0 0 8px 0;
            font-weight: 700;
            font-size: 1.5em;
        }
        .dashboard-card .media-body h5 {
            margin-bottom: 0;
        }
        .dashboard-metrics {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
        }
        .dashboard-metrics > div {
            flex: 1 1 270px;
        }
        .alloc-table-section {
            margin-top: 36px;
            background: #fff;
            box-shadow: 0 3px 24px 0 #e3e7ed;
            border-radius: 15px;
            padding: 28px 24px 32px 24px;
        }
        .alloc-table-section h4 {
            margin-bottom: 18px;
        }
        table.alloc-table {
            width: 100%;
            border-collapse: collapse;
            background: #f9fafc;
        }
        table.alloc-table th, table.alloc-table td {
            border: 1px solid #e0e2e7;
            padding: 8px 12px;
            text-align: center;
        }
        table.alloc-table th {
            background: #e3f2fd;
            font-weight: bold;
        }
        @media (max-width: 900px) {
            .dashboard-metrics { flex-direction: column; }
        }
    </style>
</head>
<body>
    <?php include_once('includes/sidebar.php');?>
    <?php include_once('includes/header.php');?>
    <div class="content-wrap">
        <div class="main">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-8 p-r-0 title-margin-right">
                        <div class="page-header">
                            <div class="page-title">
                                <h1>Dashboard</h1>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 p-l-0 title-margin-left">
                        <div class="page-header">
                            <div class="page-title">
                                <ol class="breadcrumb text-right">
                                    <li><a href="#">Dashboard</a></li>
                                    <li class="active">Home</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="main-content">
                    <!-- Metrics Cards -->
                    <div class="dashboard-metrics">
                        <div>
                            <div class="card dashboard-card">
                                <div class="media">
                                    <div class="media-left meida media-middle">
                                        <span><i class="ti-file f-s-28 color-primary border-primary round-widget"></i></span>
                                    </div>
                                    <div class="media-body media-text-right">
                                        <?php
                                        $sql1 ="SELECT * from tblcourse";
                                        $query1 = $dbh->prepare($sql1);
                                        $query1->execute();
                                        $totcourse = $query1->rowCount();
                                        ?>
                                        <h4 style="color:#1976d2">Total Courses</h4>
                                        <h4 style="color:#1976d2"><?php echo htmlentities($totcourse);?></h4>
                                        <a href="course.php"><h5>View Detail</h5></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="card dashboard-card">
                                <div class="media">
                                    <div class="media-left meida media-middle">
                                        <span><i class="ti-book f-s-28 color-warning border-warning round-widget"></i></span>
                                    </div>
                                    <div class="media-body media-text-right">
                                        <?php
                                        $sql2 ="SELECT * from tblsubject";
                                        $query2 = $dbh->prepare($sql2);
                                        $query2->execute();
                                        $totsub = $query2->rowCount();
                                        ?>
                                        <h4 style="color:#FFA000">Total Subjects</h4>
                                        <h4 style="color:#FFA000"><?php echo htmlentities($totsub);?></h4>
                                        <a href="subject.php"><h5>View Detail</h5></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="card dashboard-card">
                                <div class="media">
                                    <div class="media-left meida media-middle">
                                        <span><i class="ti-user f-s-28 color-success border-success round-widget"></i></span>
                                    </div>
                                    <div class="media-body media-text-right">
                                        <?php
                                        $sql3 ="SELECT * from tblteacher";
                                        $query3 = $dbh->prepare($sql3);
                                        $query3->execute();
                                        $totteacher = $query3->rowCount();
                                        ?>
                                        <h4 style="color:#388e3c">Total Teachers</h4>
                                        <h4 style="color:#388e3c"><?php echo htmlentities($totteacher);?></h4>
                                        <a href="manage-teacher.php"><h5>View Detail</h5></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End Metrics -->

                    <!-- Successful Allocations Table -->
                    <div class="alloc-table-section">
                        <h4>Successful Allocations</h4>
                        <table class="alloc-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Academic Year</th>
                                    <th>Semester</th>
                                    <th>Total Allocated</th>
                                    <th>Unique Instructors</th>
                                    <th>View Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // GROUP BY year and semester, only count allocations with instructor_id not null/empty/'xyz'
                                $alloc_stmt = $dbh->prepare("
                                    SELECT academic_year, semester, 
                                        COUNT(*) AS total,
                                        COUNT(DISTINCT CASE WHEN instructor_id IS NOT NULL AND instructor_id <> '' AND instructor_id <> 'xyz' THEN instructor_id END) AS unique_instructors
                                    FROM finalize_schedules
                                    WHERE instructor_id IS NOT NULL AND instructor_id <> '' AND instructor_id <> 'xyz'
                                    GROUP BY academic_year, semester
                                    ORDER BY academic_year DESC, semester DESC
                                ");
                                $alloc_stmt->execute();
                                $allocs = $alloc_stmt->fetchAll(PDO::FETCH_ASSOC);
                                $rownum = 1;
                                if ($allocs && count($allocs) > 0) {
                                    foreach($allocs as $alloc) {
                                        echo '<tr>';
                                        echo '<td>' . ($rownum++) . '</td>';
                                        echo '<td>' . htmlentities($alloc['academic_year']) . '</td>';
                                        echo '<td>' . htmlentities($alloc['semester']) . '</td>';
                                        echo '<td>' . htmlentities($alloc['total']) . '</td>';
                                        echo '<td>' . htmlentities($alloc['unique_instructors']) . '</td>';
                                        // Reroute to auto-allocation.php with year/sem parameters
                                        echo '<td><a href="auto-allocation.php?academic_year='.urlencode($alloc['academic_year']).'&semester='.urlencode($alloc['semester']).'" class="btn btn-info btn-sm">View</a></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6"><i>No successful allocations found.</i></td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- End Successful Allocations Table -->

                    <?php include_once('includes/footer.php');?>
                </div>
            </div>
        </div>
    </div>

    <!-- jquery vendor -->
    <script src="../assets/js/lib/jquery.min.js"></script>
    <script src="../assets/js/lib/jquery.nanoscroller.min.js"></script>
    <script src="../assets/js/lib/menubar/sidebar.js"></script>
    <script src="../assets/js/lib/bootstrap.min.js"></script>
    <script src="../assets/js/scripts.js"></script>
</body>
</html>