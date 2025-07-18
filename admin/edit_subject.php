<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}

$editid = intval($_GET['editid'] ?? 0);

// Fetch subject and tags
$sql = "SELECT s.*, q.tags FROM tblsubject s
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

if (isset($_POST['submit'])) {
    $subject_name = trim($_POST['subject_name']);
    $subject_code = trim($_POST['subject_code']);
    $units = intval($_POST['units']);
    $description = trim($_POST['description']);
    $tags = $_POST['tags'] ?? [];
    try {
        $sql = "UPDATE tblsubject SET subject_name = :subject_name, subject_code = :subject_code,
                units = :units, description = :description WHERE id = :editid";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':subject_name', $subject_name, PDO::PARAM_STR);
        $stmt->bindParam(':subject_code', $subject_code, PDO::PARAM_STR);
        $stmt->bindParam(':units', $units, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':editid', $editid, PDO::PARAM_INT);
        $stmt->execute();

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

        $_SESSION['msg'] = "Subject successfully updated.";
        $_SESSION['msg_type'] = "success";
        header("Location: subject.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['msg'] = "Error: " . $e->getMessage();
        $_SESSION['msg_type'] = "danger";
        header("Location: subject.php");
        exit;
    }
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
        .full-width-form .card-body {
            width: 100%;
            max-width: 100%;
        }
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
                                    <label>Tags</label>
                                    <div class="input-group mb-2">
                                        <input type="text" id="tag-input" class="form-control" placeholder="Enter tag">
                                        <div class="input-group-append">
                                            <button type="button" id="add-tag-btn" class="btn btn-info">+ Add Tag</button>
                                        </div>
                                    </div>
                                    <div id="tags-container"></div>
                                </div>

                                <button type="submit" name="submit" class="btn btn-primary">Update Subject</button>
                                <a href="subject.php" class="btn btn-secondary">Cancel</a>
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
const container = document.getElementById('tags-container');
const tagInput = document.getElementById('tag-input');

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

// Prefill tags from PHP
document.addEventListener('DOMContentLoaded', () => {
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
    ?>
});

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

document.getElementById('add-tag-btn').addEventListener('click', function () {
    const value = tagInput.value.trim().toLowerCase();
    if (value !== '' && !tags.has(value)) {
        tags.add(value);
        container.appendChild(createTagElement(value));
        tagInput.value = '';
    }
});

function validateTags() {
    if (tags.size === 0) {
        alert('Please add at least one tag.');
        return false;
    }
    return true;
}

// Load suggestions on page load
fetchSuggestions();
</script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
</body>
</html>