<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

$editid = intval($_GET['editid'] ?? 0);

// Fetch subject, tags, time schedule, and preferred teachers
$sql = "SELECT s.*, q.tags, s.time_duration FROM tblsubject s
        LEFT JOIN tblqualification q ON s.id = q.subject_id
        WHERE s.id = :editid";
$query = $dbh->prepare($sql);
$query->bindParam(':editid', $editid, PDO::PARAM_INT);
$query->execute();
$subject = $query->fetch(PDO::FETCH_OBJ);

if (!$subject) {
    $_SESSION['msg'] = "Subject not found.";
    $_SESSION['msg_type'] = "danger";
    header("Location: subject.php");
    exit;
}

// Fetch teachers for dropdown
$teachers = [];
$teacherSql = "SELECT TeacherID, FirstName, LastName FROM tblteacher ORDER BY LastName, FirstName";
$teacherQuery = $dbh->prepare($teacherSql);
$teacherQuery->execute();
$teachers = $teacherQuery->fetchAll(PDO::FETCH_OBJ);

// Fetch preferred teachers for this subject
$preferred_teachers_selected = [];
$prefSql = "SELECT teacher_id FROM subject_teachers WHERE subject_id = :subject_id";
$prefQuery = $dbh->prepare($prefSql);
$prefQuery->bindParam(':subject_id', $editid, PDO::PARAM_INT);
$prefQuery->execute();
while ($row = $prefQuery->fetch(PDO::FETCH_ASSOC)) {
    $preferred_teachers_selected[] = $row['teacher_id'];
}

if (isset($_POST['submit'])) {
    $subject_name = trim($_POST['subject_name']);
    $subject_code = trim($_POST['subject_code']);
    $units = intval($_POST['units']);
    $description = trim($_POST['description']);
    $tags = $_POST['tags'] ?? [];
    $num_meetings = intval($_POST['num_meetings']);
    $durations = $_POST['durations'] ?? [];
    $preferred_teachers = $_POST['preferred_teachers'] ?? [];
    $showMsgHere = true;

    // Create a comma-separated string for durations
    $time_duration = implode(',', $durations);

    try {
        // Update the subject info
        $sql = "UPDATE tblsubject SET subject_name = :subject_name, subject_code = :subject_code,
                units = :units, description = :description, time_duration = :time_duration WHERE id = :editid";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':subject_name', $subject_name, PDO::PARAM_STR);
        $stmt->bindParam(':subject_code', $subject_code, PDO::PARAM_STR);
        $stmt->bindParam(':units', $units, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':time_duration', $time_duration, PDO::PARAM_STR);
        $stmt->bindParam(':editid', $editid, PDO::PARAM_INT);
        $stmt->execute();

        // Update tags
        $tagString = strtolower(implode(',', array_unique(array_map('trim', $tags))));
        $check = $dbh->prepare("SELECT id FROM tblqualification WHERE subject_id = :subject_id");
        $check->bindParam(':subject_id', $editid, PDO::PARAM_INT);
        $check->execute();

        if ($check->rowCount() > 0) {
            $qsql = "UPDATE tblqualification SET tags = :tags WHERE subject_id = :subject_id";
        } else {
            $qsql = "INSERT INTO tblqualification(subject_id, tags) VALUES(:subject_id, :tags)";
        }
        $qstmt = $dbh->prepare($qsql);
        $qstmt->bindParam(':subject_id', $editid, PDO::PARAM_INT);
        $qstmt->bindParam(':tags', $tagString, PDO::PARAM_STR);
        $qstmt->execute();

        // Update preferred teachers (delete all first, then insert)
        $dbh->prepare("DELETE FROM subject_teachers WHERE subject_id = :subject_id")
            ->execute([':subject_id' => $editid]);
        if (!empty($preferred_teachers)) {
            foreach ($preferred_teachers as $teacher_id) {
                $teacher_sql = "INSERT INTO subject_teachers(subject_id, teacher_id) VALUES(:subject_id, :teacher_id)";
                $teacher_stmt = $dbh->prepare($teacher_sql);
                $teacher_stmt->bindParam(':subject_id', $editid, PDO::PARAM_INT);
                $teacher_stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
                $teacher_stmt->execute();
            }
        }
        $preferred_teachers_selected = $preferred_teachers;

        $msg = "Subject successfully updated.";
        $msg_type = "success";
    } catch (PDOException $e) {
        $msg = "Error: " . $e->getMessage();
        $msg_type = "danger";
    }
    // Refetch updated values for the form
    $sql = "SELECT s.*, q.tags, s.time_duration FROM tblsubject s
            LEFT JOIN tblqualification q ON s.id = q.subject_id
            WHERE s.id = :editid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':editid', $editid, PDO::PARAM_INT);
    $query->execute();
    $subject = $query->fetch(PDO::FETCH_OBJ);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Edit Subject</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .full-width-form .card-body { width: 100%; max-width: 100%; }
        .form-control { font-size: 14px; }
        .small-input { max-width: 200px; }
        .tag-badge {
            display: inline-block;
            background-color: #17a2b8;
            color: #fff;
            padding: 5px 10px;
            margin: 2px;
            border-radius: 15px;
        }
        .tag-badge .remove-tag {
            margin-left: 8px;
            cursor: pointer;
            color: #fff;
        }
        .form-group { margin-bottom: 1.5rem; }
        .no-bg-alert {
            border: 1px solid #ccc;
            border-left-width: 4px;
            border-radius: 4px;
            margin-bottom: 1rem;
            padding: 12px 16px;
        }
        .no-bg-alert.success {
            border-left-color: #28a745;
            color: #28a745;
            background: none;
        }
        .no-bg-alert.danger {
            border-left-color: #dc3545;
            color: #dc3545;
            background: none;
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
                    <div class="card alert full-width-form">
                        <div class="card-header">
                            <h4>Edit Subject</h4>
                        </div>
                        <div class="card-body">

                            <?php if (!empty($msg)): ?>
                                <div class="no-bg-alert <?= $msg_type ?>"><?= htmlentities($msg) ?></div>
                            <?php endif; ?>

                            <form method="post" onsubmit="return validateTags();">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="subject_name">Subject Name</label>
                                        <input type="text" name="subject_name" class="form-control" value="<?= htmlentities($subject->subject_name) ?>" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="subject_code">Subject Code</label>
                                        <input type="text" name="subject_code" class="form-control" value="<?= htmlentities($subject->subject_code) ?>" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="units">Units</label>
                                        <input type="number" name="units" class="form-control" value="<?= htmlentities($subject->units) ?>" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="description">Description / Objectives</label>
                                    <textarea name="description" rows="5" class="form-control" required><?= htmlentities($subject->description) ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="num_meetings">Number of Meetings per Week</label>
                                    <input type="number" name="num_meetings" class="form-control small-input" id="num_meetings" value="<?= htmlentities(count(explode(',', $subject->time_duration))) ?>" required>
                                </div>

                                <div id="time_duration_fields">
                                    <?php
                                    $durations = explode(',', $subject->time_duration);
                                    foreach ($durations as $index => $duration) { ?>
                                        <div class="form-group">
                                            <label>Duration (Minutes) for Meeting <?= $index + 1 ?></label>
                                            <input type="number" name="durations[]" class="form-control small-input" value="<?= htmlentities($duration) ?>" required>
                                        </div>
                                    <?php } ?>
                                </div>

                                <div class="form-group">
                                    <label>Tags</label>
                                    <div class="input-group mb-2">
                                        <input type="text" id="tag-input" class="form-control" placeholder="Enter tag">
                                        <div class="input-group-append">
                                            <button type="button" id="add-tag-btn" class="btn btn-info">+ Add Tag</button>
                                        </div>
                                    </div>
                                    <div id="tags-container"></div>
                                </div>

                                <div class="form-group">
                                    <label>Preferred Teacher(s)</label>
                                    <div class="input-group mb-2">
                                        <select id="teacher-select" class="form-control">
                                            <option value="">Select Teacher</option>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <option value="<?= $teacher->TeacherID ?>">
                                                    <?= htmlentities($teacher->LastName . ', ' . $teacher->FirstName) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="input-group-append">
                                            <button type="button" id="add-teacher-btn" class="btn btn-info">+ Add Teacher</button>
                                        </div>
                                    </div>
                                    <div id="teachers-container" class="d-flex flex-wrap"></div>
                                </div>

                                <?php
                                // Hidden input for preferred_teachers[]
                                if (!empty($preferred_teachers_selected)) {
                                    foreach ($preferred_teachers_selected as $tid) {
                                        echo "<input type='hidden' name='preferred_teachers[]' value='" . htmlentities($tid) . "'>";
                                    }
                                }
                                ?>

                                <button type="submit" name="submit" class="btn btn-primary">Update Subject</button>
                                <a href="subject.php" class="btn btn-secondary">Back</a>
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
const tags = new Set();
const teachers = new Set(<?php echo json_encode($preferred_teachers_selected); ?>);
const container = document.getElementById('tags-container');
const teacherContainer = document.getElementById('teachers-container');
const tagInput = document.getElementById('tag-input');
const teacherSelect = document.getElementById('teacher-select');

// Prefill teacher tags from PHP
document.addEventListener('DOMContentLoaded', () => {
    // Prefill tags
    <?php
    if (!empty($subject->tags)) {
        $tags_arr = explode(',', strtolower($subject->tags));
        foreach ($tags_arr as $tag) {
            $tag = trim($tag);
            if ($tag) {
                echo "tags.add('".addslashes($tag)."');\n";
                echo "container.appendChild(createTagElement('".addslashes($tag)."'));\n";
            }
        }
    }
    // Prefill teachers
    if (!empty($preferred_teachers_selected)) {
        foreach ($preferred_teachers_selected as $tid) {
            // Find teacher name
            $tname = '';
            foreach ($teachers as $t) {
                if ($t->TeacherID == $tid) {
                    $tname = $t->LastName . ', ' . $t->FirstName;
                    break;
                }
            }
            if ($tname) {
                echo "teacherContainer.appendChild(createTeacherElement('".addslashes($tid)."', '".addslashes(htmlentities($tname))."'));\n";
            }
        }
    }
    ?>
});

// Tag logic
function createTagElement(tag) {
    const span = document.createElement('span');
    span.className = 'tag-badge';
    span.innerHTML = `${tag}<span class="remove-tag">&times;</span>`;

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'tags[]';
    hidden.value = tag.toLowerCase();
    span.appendChild(hidden);

    span.querySelector('.remove-tag').addEventListener('click', () => {
        tags.delete(tag);
        span.remove();
    });

    return span;
}

// Teacher logic
function createTeacherElement(teacherId, teacherName) {
    const div = document.createElement('div');
    div.className = 'teacher-tag tag-badge';
    div.innerHTML = `${teacherName}<span class="remove-tag" data-teacher-id="${teacherId}">&times;</span>`;

    div.querySelector('.remove-tag').addEventListener('click', () => {
        teachers.delete(teacherId);
        // Remove corresponding hidden input if exists
        const hiddenInputs = document.getElementsByName('preferred_teachers[]');
        for(let i=hiddenInputs.length-1; i>=0; i--) {
            if(hiddenInputs[i].value == teacherId) hiddenInputs[i].remove();
        }
        div.remove();
    });

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'preferred_teachers[]';
    hidden.value = teacherId;
    div.appendChild(hidden);

    return div;
}

document.getElementById('add-tag-btn').addEventListener('click', function () {
    const value = tagInput.value.trim().toLowerCase();
    if (value !== '' && !tags.has(value)) {
        tags.add(value);
        container.appendChild(createTagElement(value));
        tagInput.value = '';
    }
});

document.getElementById('add-teacher-btn').addEventListener('click', function () {
    const teacherId = teacherSelect.value;
    const teacherName = teacherSelect.options[teacherSelect.selectedIndex].text;
    if (teacherId && !teachers.has(teacherId)) {
        teachers.add(teacherId);
        teacherContainer.appendChild(createTeacherElement(teacherId, teacherName));
        teacherSelect.value = '';
    }
});

function validateTags() {
    if (tags.size === 0) {
        alert('Please add at least one tag.');
        return false;
    }
    return true;
}

// Autocomplete for tags
function fetchSuggestions() {
    fetch('get_tags.php')
        .then(res => res.json())
        .then(data => {
            $("#tag-input").autocomplete({
                source: data,
                select: function(event, ui) {
                    tagInput.value = ui.item.value;
                }
            });
        });
}
fetchSuggestions();

// Handle time schedule
document.getElementById('num_meetings').addEventListener('change', function () {
    const numMeetings = this.value;
    const timeDurationFields = document.getElementById('time_duration_fields');
    timeDurationFields.innerHTML = '';
    let durationsArr = '<?= htmlentities($subject->time_duration) ?>'.split(',');

    for (let i = 1; i <= numMeetings; i++) {
        let duration = durationsArr[i - 1] || '';
        timeDurationFields.innerHTML += `
            <div class="form-group">
                <label>Duration (Minutes) for Meeting ${i}</label>
                <input type="number" name="durations[]" class="form-control small-input" value="${duration}" required>
            </div>
        `;
    }
});
</script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
</body>
</html>