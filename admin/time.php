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
    'Part-time' => [
        'days' => [],
        'morning_start' => '', 'morning_end' => '',
        'afternoon_start' => '', 'afternoon_end' => '',
        'night_start' => '', 'night_end' => ''
    ]
];

$sql = "SELECT schedule_type, days_of_week, start_time, end_time FROM schedules";
$query = $dbh->prepare($sql);
$query->execute();
$result = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($result as $row) {
    $type = $row['schedule_type'];
    if ($type === 'Regular') {
        $schedules[$type]['days'] = explode(',', $row['days_of_week']);
        $schedules[$type]['start_time'] = $row['start_time'];
        $schedules[$type]['end_time'] = $row['end_time'];
    }
}

$sql = "SELECT * FROM parttime_schedules LIMIT 1";
$query = $dbh->prepare($sql);
$query->execute();
$parttime = $query->fetch(PDO::FETCH_ASSOC);
if ($parttime) {
    $schedules['Part-time']['days'] = explode(',', $parttime['days_of_week']);
    $schedules['Part-time']['morning_start'] = $parttime['morning_start'];
    $schedules['Part-time']['morning_end'] = $parttime['morning_end'];
    $schedules['Part-time']['afternoon_start'] = $parttime['afternoon_start'];
    $schedules['Part-time']['afternoon_end'] = $parttime['afternoon_end'];
    $schedules['Part-time']['night_start'] = $parttime['night_start'];
    $schedules['Part-time']['night_end'] = $parttime['night_end'];
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
            flex-wrap: wrap;
        }
        .time-slot {
            flex: 1;
            min-width: 250px;
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

                                <div class="form-group time-row">
                                    <div>
                                        <label for="regular_start">Start Time:</label>
                                        <input type="time" name="start_time" id="regular_start" class="form-control" value="<?= htmlspecialchars($schedules['Regular']['start_time']) ?>" required>
                                    </div>
                                    <div>
                                        <label for="regular_end">End Time:</label>
                                        <input type="time" name="end_time" id="regular_end" class="form-control" value="<?= htmlspecialchars($schedules['Regular']['end_time']) ?>" required>
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

                                <div class="form-group">
                                    <label>Morning Class (AM only):</label>
                                    <div class="time-row">
                                        <div class="time-slot">
                                            <label>Start:</label>
                                            <input type="time" name="morning_start" class="form-control" max="12:00" value="<?= htmlspecialchars($schedules['Part-time']['morning_start']) ?>">
                                        </div>
                                        <div class="time-slot">
                                            <label>End:</label>
                                            <input type="time" name="morning_end" class="form-control" max="12:00" value="<?= htmlspecialchars($schedules['Part-time']['morning_end']) ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Afternoon Class (12:00 PM - 5:00 PM):</label>
                                    <div class="time-row">
                                        <div class="time-slot">
                                            <label>Start:</label>
                                            <input type="time" name="afternoon_start" class="form-control" min="12:00" max="17:00" value="<?= htmlspecialchars($schedules['Part-time']['afternoon_start']) ?>">
                                        </div>
                                        <div class="time-slot">
                                            <label>End:</label>
                                            <input type="time" name="afternoon_end" class="form-control" min="12:00" max="17:00" value="<?= htmlspecialchars($schedules['Part-time']['afternoon_end']) ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Night Class (5:00 PM and later):</label>
                                    <div class="time-row">
                                        <div class="time-slot">
                                            <label>Start:</label>
                                            <input type="time" name="night_start" class="form-control" min="17:00" value="<?= htmlspecialchars($schedules['Part-time']['night_start']) ?>">
                                        </div>
                                        <div class="time-slot">
                                            <label>End:</label>
                                            <input type="time" name="night_end" class="form-control" min="17:00" value="<?= htmlspecialchars($schedules['Part-time']['night_end']) ?>">
                                        </div>
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
    $('.day-selector').on('click', '.day-pill', function() {
        $(this).toggleClass('selected');
    });

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
