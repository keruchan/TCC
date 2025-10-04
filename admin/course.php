<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

// Delete course
if (isset($_GET['delid'])) {
    $rid = intval($_GET['delid']);
    $sql = "DELETE FROM tblcourse WHERE ID=:rid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':rid', $rid, PDO::PARAM_INT);
    $query->execute();

    $_SESSION['msg'] = "Course deleted successfully.";
    $_SESSION['msg_type'] = "danger";
    header("Location: course.php");
    exit;
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
<?php include_once('includes/sidebar.php'); ?>
<?php include_once('includes/header.php'); ?>

<div class="content-wrap">
    <div class="main">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">

                    <div class="page-header">
                        <div class="page-title">
                            <h1>
                                Course 
                                <a href="add_course.php" class="btn btn-warning btn-sm ml-3">+ Add Course</a>
                            </h1>
                        </div>
                    </div>

                    <?php if (!empty($_SESSION['msg'])): ?>
                        <div class="alert alert-<?= htmlspecialchars($_SESSION['msg_type']) ?> alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['msg']) ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
                    <?php endif; ?>

                    <div class="card alert">
                        <div class="card-header">
                            <h4>All Courses</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="courseTable" class="table table-striped table-hover">
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
foreach ($results as $row):
    // Calculate total units
    $unitsql = "
        SELECT SUM(s.units) AS total_units
        FROM tblcurriculum c
        JOIN tblsubject s ON s.ID = c.subject_id
        WHERE c.course_id = :courseid
    ";
    $unitq = $dbh->prepare($unitsql);
    $unitq->bindParam(':courseid', $row->ID, PDO::PARAM_INT);
    $unitq->execute();
    $unitres = $unitq->fetch(PDO::FETCH_OBJ);
    $totalUnits = $unitres && $unitres->total_units ? $unitres->total_units : 0;

    $jsCourseName = htmlspecialchars($row->CourseName, ENT_QUOTES);
    $jsCourseDesc = htmlspecialchars($row->CourseDesc ?? '', ENT_QUOTES);
?>
<tr>
    <td><?= $cnt++ ?></td>
    <td><?= htmlentities($row->CourseName) ?></td>
    <td><?= $totalUnits ?></td>
    <td>
        <a href="javascript:void(0);" class="edit-course"
           data-id="<?= $row->ID ?>"
           data-name="<?= $jsCourseName ?>"
           data-units="<?= $totalUnits ?>"
           data-desc="<?= $jsCourseDesc ?>">
           <i class="ti-pencil-alt color-success"></i>
        </a>
        <a href="course.php?delid=<?= $row->ID ?>" onclick="return confirm('Do you really want to delete this course?');">
            <i class="ti-trash color-danger"></i>
        </a>
    </td>
</tr>
<?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Editable Modal -->
                    <div class="modal fade" id="editCourseModal" tabindex="-1" role="dialog" aria-labelledby="editCourseModalLabel" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content shadow-lg border-0">
                          <form method="POST" action="update_course.php">
                            <div class="modal-header bg-primary text-white">
                              <h5 class="modal-title" id="editCourseModalLabel">
                                <i class="ti-pencil-alt mr-2"></i> Edit Course
                              </h5>
                              <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                              </button>
                            </div>
                            <div class="modal-body p-4">
                              <input type="hidden" name="course_id" id="modalCourseId">

                              <div class="form-group">
                                <label for="modalCourseNameInput"><i class="ti-bookmark-alt mr-1"></i> Course Name</label>
                                <input type="text" name="coursename" id="modalCourseNameInput" class="form-control" required>
                              </div>

                              <div class="form-group">
                                <label for="modalTotalUnits"><i class="ti-ruler-pencil mr-1"></i> Total Units (Auto)</label>
                                <input type="text" id="modalTotalUnits" class="form-control" readonly>
                              </div>

                              <div class="form-group">
                                <label for="modalCourseDescInput"><i class="ti-align-left mr-1"></i> Course Description</label>
                                <textarea name="coursedesc" id="modalCourseDescInput" rows="4" class="form-control"></textarea>
                              </div>
                            </div>
                            <div class="modal-footer bg-light">
                              <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                <i class="ti-close mr-1"></i> Cancel
                              </button>
                              <button type="submit" class="btn btn-success">
                                <i class="ti-save mr-1"></i> Save Changes
                              </button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    <!-- End Editable Modal -->

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
    $('#courseTable').DataTable();

    $('.edit-course').on('click', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var units = $(this).data('units');
        var desc = $(this).data('desc');

        $('#modalCourseId').val(id);
        $('#modalCourseNameInput').val(name);
        $('#modalTotalUnits').val(units);
        $('#modalCourseDescInput').val(desc);

        $('#editCourseModal').modal('show');
    });
});
</script>
</body>
</html>
