<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Add Subject</title>
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
        .small-input {
            max-width: 200px;
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
                            <h4>Create New Subject</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="insert_subject.php" onsubmit="return validateTags();">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="subject_name">Subject Name</label>
                                        <input type="text" name="subject_name" class="form-control" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="subject_code">Subject Code</label>
                                        <input type="text" name="subject_code" class="form-control" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="units">Units</label>
                                        <input type="number" name="units" class="form-control" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="description">Description / Objectives</label>
                                    <textarea name="description" rows="5" class="form-control" required></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Number of Meetings per Week</label>
                                    <select name="num_meetings" id="num_meetings" class="form-control small-input" required>
                                        <option value="">Select</option>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <option value="<?= $i ?>"><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div id="time_duration_fields"></div>

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

                                <button type="submit" name="submit" class="btn btn-primary">Save Subject</button>
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

$('#num_meetings').change(function() {
    var count = $(this).val();
    var html = '';
    for (var i = 1; i <= count; i++) {
        html += '<div class="form-group">';
        html += '<label>Duration (Minutes) for Meeting ' + i + '</label>';
        html += '<input type="number" name="time_duration_' + i + '" class="form-control small-input" placeholder="e.g., 90" required>';
        html += '</div>';
    }
    $('#time_duration_fields').html(html);
});

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
</script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
</body>
</html>
