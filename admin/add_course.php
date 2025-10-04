<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

// Fetch subjects for dropdown
$subject_stmt = $dbh->prepare("SELECT id, subject_name FROM tblsubject ORDER BY subject_name ASC");
$subject_stmt->execute();
$subjects = $subject_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Add Course</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .card.alert { margin-top: 15px; }
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
                        <div class="card-header">
                            <h4>Add New Course</h4>
                        </div>
                        <div class="card-body">
                            <?php
                            if (!empty($_SESSION['msg'])) {
                                $msgType = $_SESSION['msg_type'] ?? 'info';
                                echo '<div class="alert alert-' . htmlspecialchars($msgType) . '">' . htmlspecialchars($_SESSION['msg']) . '</div>';
                                unset($_SESSION['msg'], $_SESSION['msg_type']);
                            }
                            ?>
                            <form method="post" action="insert_course.php">
                                <div class="form-group">
                                    <label for="coursename">Course Name</label>
                                    <input type="text" name="coursename" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="coursedesc">Course Description</label>
                                    <textarea name="coursedesc" class="form-control" rows="4" required></textarea>
                                </div>

                                <!-- Subject Section -->
                                <div class="form-group">
                                    <label>Curriculum Subjects</label>
                                    <div id="subject-container"></div>
                                    <button type="button" id="add-subject-btn" class="btn btn-sm btn-info mt-2">+ Add Subject</button>
                                </div>

                                <button type="submit" name="submit" class="btn btn-primary">Save</button>
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
<script>
document.getElementById('add-subject-btn').addEventListener('click', function() {
    const container = document.getElementById('subject-container');
    const subjectGroup = document.createElement('div');
    subjectGroup.classList.add('form-group', 'border', 'p-3', 'mb-2');

    const index = container.children.length;

    subjectGroup.innerHTML = `
        <label>Subject</label>
        <select name="subject_id[]" class="form-control mb-2 subject-select" required>
            <option value="">-- Select Subject --</option>
            <?php foreach ($subjects as $sub): ?>
                <option value="<?= $sub['id'] ?>"><?= htmlentities($sub['subject_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Year Level</label>
        <select name="year_level[]" class="form-control mb-2 year-select" required>
            <option value="">-- Select Year --</option>
            <option value="1st">1st</option>
            <option value="2nd">2nd</option>
            <option value="3rd">3rd</option>
            <option value="4th">4th</option>
        </select>

        <label>Semester</label>
        <select name="semester[]" class="form-control mb-2 sem-select" required>
            <option value="">-- Select Semester --</option>
            <option value="1st">1st</option>
            <option value="2nd">2nd</option>
        </select>

        <button type="button" class="btn btn-danger btn-sm mt-2 remove-subject">Remove</button>
    `;

    container.appendChild(subjectGroup);

    subjectGroup.querySelector('.remove-subject').addEventListener('click', () => {
        container.removeChild(subjectGroup);
    });

    subjectGroup.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', checkDuplicates);
    });
});

function checkDuplicates() {
    const entries = new Set();
    document.querySelectorAll('#subject-container > .form-group').forEach(group => {
        const subject = group.querySelector('.subject-select').value;
        const year = group.querySelector('.year-select').value;
        const sem = group.querySelector('.sem-select').value;
        const warning = group.querySelector('.duplicate-warning');

        const key = `${subject}|${year}|${sem}`;
        if (subject && year && sem) {
            if (entries.has(key)) {
                warning.classList.remove('d-none');
            } else {
                entries.add(key);
                warning.classList.add('d-none');
            }
        } else {
            warning.classList.add('d-none');
        }
    });
}
</script>
</body>
</html>
