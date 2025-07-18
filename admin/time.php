<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['tsasaid'] ?? '') == 0) {
    header('location:logout.php');
    exit();
}

// Fetch saved schedules from DB
$schedules = [
    'Regular' => ['days' => [], 'start_time' => '', 'end_time' => ''],
    'Part-time' => ['days' => [], 'start_time' => '', 'end_time' => '']
];

$sql = "SELECT schedule_type, days_of_week, start_time, end_time FROM schedules";
$query = $dbh->prepare($sql);
$query->execute();
$result = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($result as $row) {
    $type = $row['schedule_type'];
    $schedules[$type]['days'] = explode(',', $row['days_of_week']);
    $schedules[$type]['start_time'] = $row['start_time'];
    $schedules[$type]['end_time'] = $row['end_time'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>TSAS : Time Management</title>
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .day-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .day-pill {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 25px;
            border: 1px solid #17a2b8;
            background-color: #fff;
            color: #17a2b8;
            cursor: pointer;
            user-select: none;
            transition: all 0.2s ease-in-out;
        }
        .day-pill.selected {
            background-color: #17a2b8;
            color: #fff;
        }
        .time-row {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .time-row label {
            margin-bottom: 0;
        }
        .time-row input[type="time"] {
            max-width: 200px;
            display: inline-block;
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
                            <h1>Time Management</h1>
                        </div>
                    </div>

                    <!-- Regular Schedule -->
                    <div class="card alert">
                        <div class="card-header">
                            <h4>Set Regular Schedule</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="save_time.php">
                                <input type="hidden" name="schedule_type" value="Regular">
                                <input type="hidden" name="days" id="regular_days_input">

                                <!-- Select Days (Pills) -->
                                <div class="form-group">
                                    <label>Select Day(s):</label>
                                    <div class="day-selector" id="regular_day_selector">
                                        <?php
                                        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                        foreach ($daysOfWeek as $day) {
                                            $selected = in_array($day, $schedules['Regular']['days']) ? 'selected' : '';
                                            echo "<div class='day-pill $selected' data-day='$day'>$day</div>";
                                        }
                                        ?>
                                    </div>
                                    <small class="form-text text-muted">Click to select one or multiple days.</small>
                                </div>

                                <!-- Start and End Time -->
                                <div class="form-group time-row">
                                    <div>
                                        <label for="regular_start">Start Time:</label>
                                        <input type="time" name="start_time" id="regular_start" class="form-control"
                                               value="<?= htmlspecialchars($schedules['Regular']['start_time']) ?>" required>
                                    </div>
                                    <div>
                                        <label for="regular_end">End Time:</label>
                                        <input type="time" name="end_time" id="regular_end" class="form-control"
                                               value="<?= htmlspecialchars($schedules['Regular']['end_time']) ?>" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">Save Regular Schedule</button>
                            </form>
                        </div>
                    </div>

                    <!-- Part-time Schedule -->
                    <div class="card alert mt-4">
                        <div class="card-header">
                            <h4>Set Part-time Schedule</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="save_time.php">
                                <input type="hidden" name="schedule_type" value="Part-time">
                                <input type="hidden" name="days" id="parttime_days_input">

                                <!-- Select Days (Pills) -->
                                <div class="form-group">
                                    <label>Select Day(s):</label>
                                    <div class="day-selector" id="parttime_day_selector">
                                        <?php
                                        foreach ($daysOfWeek as $day) {
                                            $selected = in_array($day, $schedules['Part-time']['days']) ? 'selected' : '';
                                            echo "<div class='day-pill $selected' data-day='$day'>$day</div>";
                                        }
                                        ?>
                                    </div>
                                    <small class="form-text text-muted">Click to select one or multiple days.</small>
                                </div>

                                <!-- Start and End Time -->
                                <div class="form-group time-row">
                                    <div>
                                        <label for="parttime_start">Start Time:</label>
                                        <input type="time" name="start_time" id="parttime_start" class="form-control"
                                               value="<?= htmlspecialchars($schedules['Part-time']['start_time']) ?>" required>
                                    </div>
                                    <div>
                                        <label for="parttime_end">End Time:</label>
                                        <input type="time" name="end_time" id="parttime_end" class="form-control"
                                               value="<?= htmlspecialchars($schedules['Part-time']['end_time']) ?>" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">Save Part-time Schedule</button>
                            </form>
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
<script src="../assets/js/lib/bootstrap.min.js"></script>
<script src="../assets/js/scripts.js"></script>
<script>
$(document).ready(function() {
    // Toggle day-pill selection
    $('.day-selector').on('click', '.day-pill', function() {
        $(this).toggleClass('selected');
    });

    // Before submitting form, collect selected days
    $('form').submit(function() {
        let selectedDays = [];
        $(this).find('.day-pill.selected').each(function() {
            selectedDays.push($(this).data('day'));
        });
        $(this).find('input[name="days"]').val(selectedDays.join(','));
    });
});
</script>
</body>
</html>
