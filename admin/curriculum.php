<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

// Handle deletion
if (isset($_GET['delid'])) {
    $id = intval($_GET['delid']);
    $del = $dbh->prepare("DELETE FROM tblcurriculum WHERE id = :id");
    $del->bindParam(':id', $id, PDO::PARAM_INT);
    $del->execute();
    header('Location: curriculum.php');
    exit;
}

$subjects = $dbh->query("SELECT id, subject_name, subject_code, units FROM tblsubject ORDER BY subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$courses = $dbh->query("SELECT ID, CourseName FROM tblcourse ORDER BY CourseName ASC")->fetchAll(PDO::FETCH_ASSOC);

$sql = "
SELECT 
    cur.id,
    c.ID as course_id,
    c.CourseName,
    s.subject_name,
    s.subject_code,
    s.units,
    cur.year_level,
    cur.semester
FROM tblcurriculum cur
JOIN tblcourse c ON cur.course_id = c.ID
JOIN tblsubject s ON cur.subject_id = s.ID
ORDER BY c.CourseName, cur.year_level, cur.semester, s.subject_name
";
$query = $dbh->prepare($sql);
$query->execute();
$curriculums = $query->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
$totalCourseUnits = [];
foreach ($courses as $course) {
    $grouped[$course['CourseName']] = [];
    $totalCourseUnits[$course['CourseName']] = 0;
}
foreach ($curriculums as $row) {
    $course = $row['CourseName'];
    $year = $row['year_level'];
    $sem = $row['semester'];
    $grouped[$course][$year][$sem][] = [
        'id' => $row['id'],
        'name' => $row['subject_name'],
        'code' => $row['subject_code'],
        'units' => $row['units']
    ];
    $totalCourseUnits[$course] += $row['units'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Curriculum View</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .curriculum-table th, .curriculum-table td {
            vertical-align: middle;
            white-space: nowrap;
        }
        .curriculum-table th:nth-child(1) { width: 40px; }
        .curriculum-table th:nth-child(2) { width: 40%; }
        .curriculum-table th:nth-child(3) { width: 15%; }
        .curriculum-table th:nth-child(4) { width: 10%; }
        .curriculum-table th:nth-child(5) { width: 10%; }
        .card-header.bg-info strong {
            font-size: 1.7rem;
        }
        .ti-trash {
            font-size: 1.2rem;
        }
        .modal-header .close {
            font-size: 1.5rem;
        }
        .collapse {
            transition: height 0.3s ease;
        }
        .semester-toggle {
            display: block;
            width: 100%;
            text-align: left;
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
                    <div class="card alert">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4>Curriculum Overview</h4>
                            <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#addModal">+ Add Subject</button>
                        </div>
                        <div class="card-body">
                            <?php foreach ($grouped as $course => $years): ?>
                                <div class="card my-3">
                                    <div class="card-header bg-info text-white">
                                        <strong><?= htmlentities($course) ?> (Total: <?= $totalCourseUnits[$course] ?> Units)</strong>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($years as $year => $sems): ?>
                                            <?php foreach ($sems as $sem => $subs): ?>
                                                <?php if (empty($subs)) continue; ?>
                                                <?php $totalUnits = array_sum(array_column($subs, 'units')); ?>
                                                <?php $toggleId = md5($course . $year . $sem); ?>
                                                <button class="btn btn-sm btn-light mb-2 semester-toggle" type="button" data-toggle="collapse" data-target="#<?= $toggleId ?>" aria-expanded="false" aria-controls="<?= $toggleId ?>">
                                                    <?= $year ?> Year - <?= $sem ?> Semester (<?= $totalUnits ?> Units)
                                                </button>
                                                <div class="collapse" id="<?= $toggleId ?>">
                                                    <table class="table table-bordered curriculum-table">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Subject Name</th>
                                                                <th>Code</th>
                                                                <th>Units</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php $cnt = 1; foreach ($subs as $sub): ?>
                                                                <tr>
                                                                    <td><?= $cnt++ ?></td>
                                                                    <td><?= htmlentities($sub['name']) ?></td>
                                                                    <td><?= htmlentities($sub['code']) ?></td>
                                                                    <td><?= htmlentities($sub['units']) ?></td>
                                                                    <td>
                                                                        <a href="?delid=<?= $sub['id'] ?>" class="text-danger" onclick="return confirm('Remove this subject?');">
                                                                            <i class="ti-trash"></i>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php include_once('includes/footer.php'); ?>
        </div>
    </div>
</div>
<script src="../assets/js/lib/jquery.min.js"></script>
<script src="../assets/js/lib/bootstrap.min.js"></script>
<script src="../assets/js/scripts.js"></script>
</body>
</html>

<!-- Add Subject Modal -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="post" action="insert_curriculum.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addModalLabel">Add Subject to Curriculum</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
            <label>Course</label>
            <select name="course_id" class="form-control" required>
                <option value="">Select Course</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['ID'] ?>"><?= htmlentities($c['CourseName']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Subject</label>
            <select name="subject_id" class="form-control" required>
                <option value="">Select Subject</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlentities($s['subject_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Year Level</label>
            <select name="year_level" class="form-control" required>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
            </select>
        </div>
        <div class="form-group">
            <label>Semester</label>
            <select name="semester" class="form-control" required>
                <option value="1">1st Semester</option>
                <option value="2">2nd Semester</option>
            </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">Add Subject</button>
      </div>
    </form>
  </div>
</div>
