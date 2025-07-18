<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid'] == 0)) {
    header('location:logout.php');
} else {
    if (isset($_POST['submit'])) {
        $empid = $_POST['empid'];
        $fname = $_POST['fname'];
        $lname = $_POST['lname'];
        $mobnum = $_POST['mobnum'];
        $email = $_POST['email'];
        $gender = $_POST['gender'];
        $dob = $_POST['dob'];
        $cid = $_POST['cid'];
        $religion = $_POST['religion'];
        $address = $_POST['address'];
        $bachelors = $_POST['bachelors']; // Educational Background
        $masters = $_POST['masters']; // Educational Background
        $doctorate = $_POST['doctorate']; // Educational Background
        $work_experience = $_POST['work_experience']; // Work Experience
        
        $propic = $_FILES["propic"]["name"];
        $extension = substr($propic, strlen($propic) - 4, strlen($propic));
        $allowed_extensions = array(".jpg", ".jpeg", ".png", ".gif");
        
        if (!in_array($extension, $allowed_extensions)) {
            echo "<script>alert('Profile Pics has Invalid format. Only jpg / jpeg / png / gif format allowed');</script>";
        } else {
            $propic = md5($propic) . time() . $extension;
            move_uploaded_file($_FILES["propic"]["tmp_name"], "images/" . $propic);
            $ret = "select Email from tblteacher where Email=:email || MobileNumber=:mobnum || EmpID=:empid";
            $query = $dbh->prepare($ret);
            $query->bindParam(':empid', $empid, PDO::PARAM_STR);
            $query->bindParam(':mobnum', $mobnum, PDO::PARAM_STR);
            $query->bindParam(':email', $email, PDO::PARAM_STR);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_OBJ);
            
            if ($query->rowCount() == 0) {
                $sql = "insert into tblteacher(EmpID, FirstName, LastName, MobileNumber, Email, Gender, Dob, CourseID, Religion, Address, ProfilePic, Bachelors, Masters, Doctorate, WorkExperience) values(:empid, :fname, :lname, :mobnum, :email, :gender, :dob, :cid, :religion, :address, :propic, :bachelors, :masters, :doctorate, :work_experience)";
                $query = $dbh->prepare($sql);
                $query->bindParam(':empid', $empid, PDO::PARAM_STR);
                $query->bindParam(':fname', $fname, PDO::PARAM_STR);
                $query->bindParam(':lname', $lname, PDO::PARAM_STR);
                $query->bindParam(':mobnum', $mobnum, PDO::PARAM_STR);
                $query->bindParam(':email', $email, PDO::PARAM_STR);
                $query->bindParam(':gender', $gender, PDO::PARAM_STR);
                $query->bindParam(':dob', $dob, PDO::PARAM_STR);
                $query->bindParam(':cid', $cid, PDO::PARAM_STR);
                $query->bindParam(':religion', $religion, PDO::PARAM_STR);
                $query->bindParam(':address', $address, PDO::PARAM_STR);
                $query->bindParam(':propic', $propic, PDO::PARAM_STR);
                $query->bindParam(':bachelors', $bachelors, PDO::PARAM_STR);
                $query->bindParam(':masters', $masters, PDO::PARAM_STR);
                $query->bindParam(':doctorate', $doctorate, PDO::PARAM_STR);
                $query->bindParam(':work_experience', $work_experience, PDO::PARAM_STR);
                $query->execute();

                $LastInsertId = $dbh->lastInsertId();
                if ($LastInsertId > 0) {
                    echo '<script>alert("Teacher detail has been added.")</script>';
                    echo "<script>window.location.href ='add-teacher.php'</script>";
                } else {
                    echo '<script>alert("Something Went Wrong. Please try again")</script>';
                }
            } else {
                echo "<script>alert('Email-id, Employee Id, or Mobile Number already exist. Please try again');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>TSAS : Add Teacher Information</title>

    <link href="../assets/css/lib/calendar2/pignose.calendar.min.css" rel="stylesheet">
    <link href="../assets/css/lib/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/lib/themify-icons.css" rel="stylesheet">
    <link href="../assets/css/lib/menubar/sidebar.css" rel="stylesheet">
    <link href="../assets/css/lib/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/lib/unix.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .form-group {
            margin-bottom: 15px;
        }
        .form-control {
            border-radius: 5px;
            padding: 8px;
        }
        .form-control:focus {
            box-shadow: none;
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
                    <div class="col-lg-8 p-r-0 title-margin-right">
                        <div class="page-header">
                            <div class="page-title">
                                <h1>Add Teacher</h1>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 p-l-0 title-margin-left">
                        <div class="page-header">
                            <div class="page-title">
                                <ol class="breadcrumb text-right">
                                    <li><a href="dashboard.php">Dashboard</a></li>
                                    <li class="active">Teacher Information</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="main-content">
                    <div class="card alert">
                        <div class="card-body">
                            <form name="" method="post" action="" enctype="multipart/form-data">
                                <div class="card-header m-b-20">
                                    <h4>Teacher Information</h4>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>First Name</label>
                                            <input type="text" class="form-control" name="fname" required="true">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Last Name</label>
                                            <input type="text" class="form-control" name="lname" required="true">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Mobile Number</label>
                                            <input type="text" class="form-control" name="mobnum" maxlength="10" pattern="[0-9]+" required="true">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" class="form-control" name="email" required="true">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Gender*</label>
                                            <select class="form-control" name="gender" required="true">
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Date of Birth</label>
                                            <input type="date" class="form-control" name="dob" required="true">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Emp ID</label>
                                            <input type="text" class="form-control" name="empid" required="true">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Course</label>
                                            <select class="form-control" name="cid" required="true">
                                                <option value="">Select Course</option>
                                                <?php
                                                $sql = "SELECT * from tblcourse";
                                                $query = $dbh->prepare($sql);
                                                $query->execute();
                                                $results = $query->fetchAll(PDO::FETCH_OBJ);
                                                foreach ($results as $row) {
                                                ?>
                                                    <option value="<?= htmlentities($row->ID) ?>"><?= htmlentities($row->CourseName) ?> (<?= htmlentities($row->BranchName) ?>)</option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address, Religion -->
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Religion</label>
                                            <input type="text" class="form-control" name="religion" required="true">
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="form-group">
                                            <label>Address</label>
                                            <input type="text" class="form-control" name="address" required="true">
                                        </div>
                                    </div>
                                </div>

                                <!-- Educational Background -->
                                <h5>Educational Background</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Bachelor's Degree</label>
                                            <input type="text" class="form-control" name="bachelors">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Master's Degree</label>
                                            <input type="text" class="form-control" name="masters">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Doctorate</label>
                                            <input type="text" class="form-control" name="doctorate">
                                        </div>
                                    </div>
                                </div>

                                <!-- Work Experience -->
                                <div class="form-group">
                                    <label>Work Experience (tag qualifications, positions)</label>
                                    <textarea name="work_experience" rows="4" class="form-control" placeholder="e.g., Professor at XYZ University, 2015-2020, PhD in CS"></textarea>
                                </div>

                                <!-- Profile Image -->
                                <div class="form-group">
                                    <label>Upload Teacher Photo <span>(150 x 150)</span></label>
                                    <input type="file" name="propic" accept="image/*" class="form-control">
                                </div>

                                <button class="btn btn-warning btn-lg" type="submit" name="submit">Save</button>
                                <button class="btn btn-secondary btn-lg" type="reset">Reset</button>
                            </form>
                        </div>
                    </div>
                    <?php include_once('includes/footer.php'); ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/lib/jquery.min.js"></script>
    <script src="../assets/js/lib/bootstrap.min.js"></script>
    <script src="../assets/js/scripts.js"></script>
</body>

</html>
