<?php
session_start();
error_reporting(0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('includes/dbconnection.php');

if (strlen($_SESSION['tsasaid']) == 0) {
    header('location:logout.php');
    exit;
} else {
    if (isset($_POST['submit'])) {
        $fname = $_POST['fname'];
        $lname = $_POST['lname'];
        $email = $_POST['email'];
        $bachelors = $_POST['bachelors'];
        $masters = $_POST['masters'];
        $doctorate = $_POST['doctorate'];
        $has_experience = $_POST['has_experience'];

        $preferred_time = isset($_POST['preferred_time']) ? $_POST['preferred_time'] : [];

        $propic = $_FILES['propic']['name'];
        $bachelors_tor = $_FILES['bachelors_tor']['name'];
        $masters_tor = $_FILES['masters_tor']['name'];
        $doctorate_tor = $_FILES['doctorate_tor']['name'];
        $teaching_load_files = isset($_FILES['teaching_load']['name']) ? $_FILES['teaching_load']['name'] : [];

        $extension = substr($propic, strrpos($propic, "."));
        $allowed_extensions = [".jpg", ".jpeg", ".png", ".gif"];

        if (!in_array(strtolower($extension), $allowed_extensions)) {
            echo "<script>alert('Invalid profile picture format. Allowed: jpg/jpeg/png/gif');</script>";
        } else {
            $propic_new = md5($propic) . time() . $extension;
            move_uploaded_file($_FILES['propic']['tmp_name'], "images/" . $propic_new);

            if ($bachelors_tor) move_uploaded_file($_FILES["bachelors_tor"]["tmp_name"], "uploads/" . $bachelors_tor);
            if ($masters_tor) move_uploaded_file($_FILES["masters_tor"]["tmp_name"], "uploads/" . $masters_tor);
            if ($doctorate_tor) move_uploaded_file($_FILES["doctorate_tor"]["tmp_name"], "uploads/" . $doctorate_tor);

            $teaching_load_uploaded = [];
            foreach ($teaching_load_files as $index => $file_name) {
                if ($file_name) {
                    $new_name = md5($file_name . time() . rand()) . "_" . $file_name;
                    move_uploaded_file($_FILES["teaching_load"]["tmp_name"][$index], "uploads/" . $new_name);
                    $teaching_load_uploaded[] = $new_name;
                }
            }

            $ret = "SELECT Email FROM tblteacher WHERE Email = :email";
            $query = $dbh->prepare($ret);
            $query->bindParam(':email', $email, PDO::PARAM_STR);
            $query->execute();

            if ($query->rowCount() == 0) {
                $dbh->beginTransaction();

                try {
                    $sql = "INSERT INTO tblteacher(FirstName, LastName, Email, ProfilePic, Bachelors, Masters, Doctorate, BachelorsTOR, MastersTOR, DoctorateTOR, HasExperience) 
                            VALUES(:fname, :lname, :email, :propic, :bachelors, :masters, :doctorate, :bachelors_tor, :masters_tor, :doctorate_tor, :has_experience)";
                    $query = $dbh->prepare($sql);
                    $query->bindParam(':fname', $fname, PDO::PARAM_STR);
                    $query->bindParam(':lname', $lname, PDO::PARAM_STR);
                    $query->bindParam(':email', $email, PDO::PARAM_STR);
                    $query->bindParam(':propic', $propic_new, PDO::PARAM_STR);
                    $query->bindParam(':bachelors', $bachelors, PDO::PARAM_STR);
                    $query->bindParam(':masters', $masters, PDO::PARAM_STR);
                    $query->bindParam(':doctorate', $doctorate, PDO::PARAM_STR);
                    $query->bindParam(':bachelors_tor', $bachelors_tor, PDO::PARAM_STR);
                    $query->bindParam(':masters_tor', $masters_tor, PDO::PARAM_STR);
                    $query->bindParam(':doctorate_tor', $doctorate_tor, PDO::PARAM_STR);
                    $query->bindParam(':has_experience', $has_experience, PDO::PARAM_STR);
                    $query->execute();

                    $teacher_id = $dbh->lastInsertId();

                    // Save preferred time per day into teacher_preferred_times
                    if (!empty($preferred_time) && is_array($preferred_time)) {
                        $stmtPref = $dbh->prepare("INSERT IGNORE INTO teacher_preferred_times (teacher_id, day_of_week, time_slot) VALUES (:teacher_id, :day, :slot)");
                        foreach ($preferred_time as $day => $slots) {
                            foreach ($slots as $slot) {
                                $stmtPref->execute([
                                    ':teacher_id' => $teacher_id,
                                    ':day' => $day,
                                    ':slot' => $slot
                                ]);
                            }
                        }
                    }

                    if ($has_experience == "Yes" && $teacher_id) {
                        $subjects_taught = isset($_POST['subjects_taught']) ? $_POST['subjects_taught'] : [];
                        $chunked_subjects = array_chunk($subjects_taught, ceil(count($subjects_taught)/count($teaching_load_uploaded)));

                        foreach ($teaching_load_uploaded as $idx => $load_file) {
                            $subjects = isset($chunked_subjects[$idx]) ? $chunked_subjects[$idx] : [];
                            $subjects_str = implode(', ', $subjects);

                            $sql_load = "INSERT INTO tblteachingload(TeacherID, TeachingLoadFile, SubjectsTaught) 
                                         VALUES(:teacher_id, :file, :subjects)";
                            $query_load = $dbh->prepare($sql_load);
                            $query_load->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
                            $query_load->bindParam(':file', $load_file, PDO::PARAM_STR);
                            $query_load->bindParam(':subjects', $subjects_str, PDO::PARAM_STR);
                            $query_load->execute();
                        }
                    }

                    foreach ($_POST as $key => $skills) {
                        if (preg_match('/^skill_tag_([0-9]+)$/', $key, $matches)) {
                            $group_index = $matches[1];
                            $skill_str = implode(', ', $skills);

                            $proof_names = [];
                            if (isset($_FILES["skills_proof_file_".$group_index])) {
                                $files = $_FILES["skills_proof_file_".$group_index];
                                for ($i = 0; $i < count($files['name']); $i++) {
                                    if ($files['name'][$i]) {
                                        $orig = $files['name'][$i];
                                        $new_name = md5($orig . time() . rand()) . "_" . $orig;
                                        move_uploaded_file($files['tmp_name'][$i], "uploads/" . $new_name);
                                        $proof_names[] = $new_name;
                                    }
                                }
                            }

                            $proof_csv = implode(', ', $proof_names);
                            $sql_skill = "INSERT INTO tblskills(TeacherID, SkillName, ProofFile) VALUES(:teacher_id, :skills, :proofs)";
                            $query_skill = $dbh->prepare($sql_skill);
                            $query_skill->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
                            $query_skill->bindParam(':skills', $skill_str, PDO::PARAM_STR);
                            $query_skill->bindParam(':proofs', $proof_csv, PDO::PARAM_STR);
                            $query_skill->execute();
                        }
                    }

                    $dbh->commit();
                    echo '<script>alert("Teacher added successfully.")</script>';
                    echo "<script>window.location.href ='add-teacher.php'</script>";
                } catch (Exception $e) {
                    $dbh->rollBack();
                    error_log("Error: " . $e->getMessage());
                    echo '<script>alert("Something went wrong while saving teacher.")</script>';
                    echo "<script>window.location.href ='add-teacher.php'</script>";
                }
            } else {
                echo "<script>alert('Email already exists.');</script>";
                echo "<script>window.location.href ='add-teacher.php'</script>";
            }
        }
    }
}
?>
