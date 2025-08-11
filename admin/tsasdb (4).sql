-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 12, 2025 at 12:22 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tsasdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `allocation_logs`
--

CREATE TABLE `allocation_logs` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `schedule_day` varchar(16) DEFAULT NULL,
  `schedule_time_slot` varchar(16) DEFAULT NULL,
  `action` varchar(32) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `allocation_shortfalls`
--

CREATE TABLE `allocation_shortfalls` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `needed_instructors` int(11) NOT NULL,
  `missing_skill_tags` text DEFAULT NULL,
  `missing_qualification` text DEFAULT NULL,
  `schedule_day` varchar(16) DEFAULT NULL,
  `schedule_time_slot` varchar(16) DEFAULT NULL,
  `report_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parttime_schedules`
--

CREATE TABLE `parttime_schedules` (
  `id` int(11) NOT NULL,
  `days_of_week` varchar(255) NOT NULL,
  `morning_start` time DEFAULT NULL,
  `morning_end` time DEFAULT NULL,
  `afternoon_start` time DEFAULT NULL,
  `afternoon_end` time DEFAULT NULL,
  `night_start` time DEFAULT NULL,
  `night_end` time DEFAULT NULL,
  `default_units` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parttime_schedules`
--

INSERT INTO `parttime_schedules` (`id`, `days_of_week`, `morning_start`, `morning_end`, `afternoon_start`, `afternoon_end`, `night_start`, `night_end`, `default_units`, `created_at`, `updated_at`) VALUES
(1, 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday', '06:00:00', '12:00:00', '12:00:00', '17:00:00', '17:30:00', '23:00:00', 30, '2025-07-26 06:03:51', '2025-08-10 14:54:23');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `schedule_type` enum('Regular','Part-time') NOT NULL,
  `days_of_week` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `default_units` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `schedule_type`, `days_of_week`, `start_time`, `end_time`, `default_units`, `created_at`, `updated_at`) VALUES
(1, 'Regular', 'Monday,Tuesday,Wednesday,Thursday,Friday', '08:00:00', '17:00:00', 12, '2025-07-18 19:13:00', '2025-08-10 06:54:34');

-- --------------------------------------------------------

--
-- Table structure for table `subject_allocations`
--

CREATE TABLE `subject_allocations` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `schedule_day` varchar(16) DEFAULT NULL,
  `schedule_time_slot` varchar(16) DEFAULT NULL,
  `allocated_units` int(11) DEFAULT 0,
  `allocation_status` enum('allocated','unallocated','partial') DEFAULT 'unallocated',
  `allocation_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subject_teachers`
--

CREATE TABLE `subject_teachers` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `preferred_time_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject_teachers`
--

INSERT INTO `subject_teachers` (`id`, `subject_id`, `teacher_id`, `section_id`, `program_id`, `preferred_time_id`) VALUES
(1, 26, 15, NULL, NULL, NULL),
(2, 26, 16, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbladmin`
--

CREATE TABLE `tbladmin` (
  `ID` int(10) NOT NULL,
  `AdminName` varchar(200) DEFAULT NULL,
  `UserName` varchar(200) DEFAULT NULL,
  `MobileNumber` bigint(10) DEFAULT NULL,
  `Email` varchar(200) DEFAULT NULL,
  `Password` varchar(200) DEFAULT NULL,
  `AdminRegdate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbladmin`
--

INSERT INTO `tbladmin` (`ID`, `AdminName`, `UserName`, `MobileNumber`, `Email`, `Password`, `AdminRegdate`) VALUES
(1, 'SuperAdmin', 'admin', 5689784592, 'admin@gmail.com', '81dc9bdb52d04dc20036dbd8313ed055', '2023-05-25 11:58:35');

-- --------------------------------------------------------

--
-- Table structure for table `tblclass`
--

CREATE TABLE `tblclass` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `academic_year` varchar(10) NOT NULL,
  `semester` varchar(10) NOT NULL,
  `year_level` varchar(10) NOT NULL,
  `section` int(5) NOT NULL,
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblclass`
--

INSERT INTO `tblclass` (`id`, `course_id`, `academic_year`, `semester`, `year_level`, `section`, `date_created`) VALUES
(14, 18, '2024-25', '1st', '1st', 2, '2025-07-19 13:47:32'),
(15, 18, '2024-25', '1st', '2nd', 2, '2025-07-19 13:47:32'),
(16, 18, '2024-25', '1st', '3rd', 2, '2025-07-19 13:47:32'),
(17, 18, '2024-25', '1st', '4th', 2, '2025-07-19 13:47:32'),
(18, 12, '2024-25', '1st', '1st', 3, '2025-07-19 13:47:32'),
(19, 12, '2024-25', '1st', '2nd', 1, '2025-07-19 13:47:32'),
(20, 12, '2024-25', '1st', '3rd', 2, '2025-07-19 13:47:32'),
(21, 12, '2024-25', '1st', '4th', 4, '2025-07-19 13:47:32'),
(22, 13, '2024-25', '1st', '1st', 2, '2025-07-19 13:47:32'),
(23, 13, '2024-25', '1st', '2nd', 1, '2025-07-19 13:47:32'),
(24, 13, '2024-25', '1st', '3rd', 2, '2025-07-19 13:47:32'),
(25, 13, '2024-25', '1st', '4th', 2, '2025-07-19 13:47:32'),
(26, 1, '2024-25', '1st', '1st', 2, '2025-07-19 13:47:32'),
(27, 1, '2024-25', '1st', '2nd', 2, '2025-07-19 13:47:32');

-- --------------------------------------------------------

--
-- Table structure for table `tblcourse`
--

CREATE TABLE `tblcourse` (
  `ID` int(10) NOT NULL,
  `CourseName` varchar(100) DEFAULT NULL,
  `TotalUnits` int(11) DEFAULT NULL,
  `CreationDate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CourseDesc` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblcourse`
--

INSERT INTO `tblcourse` (`ID`, `CourseName`, `TotalUnits`, `CreationDate`, `CourseDesc`) VALUES
(1, 'BSIT', 120, '0000-00-00 00:00:00', NULL),
(12, 'BS Entrepreneurship', NULL, '2025-07-12 19:53:43', 'asd'),
(13, 'BS in Public Admin', NULL, '2025-07-12 19:53:58', 'asd'),
(15, 'BS in Information System', NULL, '2025-07-18 06:28:11', 'Subject Subject'),
(16, 'BS in Information Technology', NULL, '2025-07-18 06:41:24', 'Information Tech focuses on Information Technology'),
(17, 'BS in Computer Science', NULL, '2025-07-18 06:42:40', 'qwe'),
(18, 'BS Agriculture', NULL, '2025-07-19 05:34:33', 'Focuses on Agriculture');

-- --------------------------------------------------------

--
-- Table structure for table `tblcurriculum`
--

CREATE TABLE `tblcurriculum` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `year_level` enum('1st','2nd','3rd','4th') NOT NULL,
  `semester` enum('1st','2nd','summer') NOT NULL,
  `date_added` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblcurriculum`
--

INSERT INTO `tblcurriculum` (`id`, `course_id`, `subject_id`, `year_level`, `semester`, `date_added`) VALUES
(1, 16, 19, '3rd', '1st', '2025-07-18 14:41:24'),
(2, 16, 19, '2nd', '2nd', '2025-07-18 14:41:24'),
(3, 17, 20, '2nd', '1st', '2025-07-18 14:42:40'),
(5, 17, 18, '1st', '2nd', '2025-07-18 15:02:54'),
(6, 12, 20, '2nd', '1st', '2025-07-18 16:58:34'),
(7, 12, 21, '1st', '1st', '2025-07-18 16:59:16'),
(8, 12, 18, '1st', '1st', '2025-07-18 17:09:44'),
(9, 12, 20, '1st', '1st', '2025-07-18 17:10:04'),
(10, 18, 23, '1st', '1st', '2025-07-19 13:34:33'),
(11, 18, 19, '1st', '1st', '2025-07-19 13:38:51'),
(12, 18, 21, '1st', '2nd', '2025-07-19 13:39:19');

-- --------------------------------------------------------

--
-- Table structure for table `tblqualification`
--

CREATE TABLE `tblqualification` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `tags` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblqualification`
--

INSERT INTO `tblqualification` (`id`, `subject_id`, `tags`) VALUES
(2, 18, 'python,programming,c++,java'),
(3, 19, 'communicate,java,integrative'),
(4, 20, 'data,management,information'),
(5, 21, 'information,management'),
(6, 22, 'statistics,statistic'),
(7, 23, 'calculus'),
(8, 24, ''),
(10, 26, 'ml');

-- --------------------------------------------------------

--
-- Table structure for table `tblskills`
--

CREATE TABLE `tblskills` (
  `SkillID` int(11) NOT NULL,
  `TeacherID` int(11) NOT NULL,
  `SkillName` varchar(255) NOT NULL,
  `ProofFile` varchar(255) DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `Verified` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblskills`
--

INSERT INTO `tblskills` (`SkillID`, `TeacherID`, `SkillName`, `ProofFile`, `CreatedAt`, `Verified`) VALUES
(1, 3, '', '', '2025-07-26 01:33:19', 0),
(2, 5, '', '', '2025-07-26 01:36:03', 0),
(3, 7, '', '', '2025-07-26 01:39:18', 0),
(4, 9, '', '', '2025-07-26 01:42:56', 0),
(6, 11, 'Python,Programming,PHP', '82e991ce813ae69d069b4c482c516802_3168131.jpg', '2025-07-26 01:47:49', 0),
(7, 11, 'PHP, Communication', 'cad98e4a2f421e68aa0c596ba115a6a4_3168131.jpg', '2025-07-26 01:47:49', 1),
(8, 12, 'abc', '57326943f972ec09cfd37238d9cfb6d8_BSIT 2A.png', '2025-07-26 02:18:55', 0),
(9, 12, 'abc', '', '2025-07-26 02:18:55', 0),
(10, 12, '123', '', '2025-07-26 02:18:55', 0),
(11, 12, '456', '', '2025-07-26 02:18:55', 0),
(12, 12, 'filipino', '1ab6a055554251ed3b3e801fd5a6a7f3_BSIT 2D 24-25 2ND SEM.png', '2025-07-26 02:18:55', 0),
(13, 12, 'abc', '', '2025-07-26 02:18:55', 0),
(14, 13, 'Filipino, Math, Science', '4427a9814a035dd236cafac446ea4514_3168131.jpg', '2025-07-26 02:30:47', 0),
(15, 13, 'XYZ, ABC, EFG', '', '2025-07-26 02:30:47', 0),
(16, 14, 'MNB, POI', '53cfb2158204877a4482900de185ead9_BSAIS 4 ISO GRADES.png', '2025-07-26 02:31:58', 0),
(17, 14, 'ABC, EFG', '89434f39bd7ffc294b094de3f9298d5c_BSIT 2A.png', '2025-07-26 02:31:58', 0),
(18, 15, 'Java, Programming, C#, C++, C#', '4d4e0a6f0a3f150887ea1f18a84aabdd_BSAIS 4 DB GRADES.png', '2025-07-26 03:51:07', 1),
(19, 16, 'Java, Alaska, Tang', '779e1d9e2a743249217c33dc89e7413b_BSAIS 4 ISO GRADES.png', '2025-07-26 06:49:40', 0),
(20, 17, 'Science', '694cfe05fa05fb2ebe026cc2d60c8239_BSAIS 4 DB GRADES.png', '2025-08-09 14:26:35', 1),
(21, 18, 'Quantum Physics', '42f3aa4cdd938e32a7e6baa516b72ad6_3168131.jpg', '2025-08-10 16:13:08', 0),
(22, 19, 'Hunter', 'b7e64b5d0c5660113b0d264fb0c56b31_3168131.jpg', '2025-08-11 10:45:18', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tblsuballocation`
--

CREATE TABLE `tblsuballocation` (
  `ID` int(5) NOT NULL,
  `CourseID` int(5) DEFAULT NULL,
  `Teacherempid` varchar(100) DEFAULT NULL,
  `Subid` int(5) DEFAULT NULL,
  `AllocationDate` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tblsuballocation`
--

INSERT INTO `tblsuballocation` (`ID`, `CourseID`, `Teacherempid`, `Subid`, `AllocationDate`) VALUES
(1, 1, 'EMP12345', 3, '2023-05-24 06:02:24'),
(2, 2, 'Emp102', 2, '2023-05-24 06:02:50'),
(3, 2, 'Emp102', 8, '2023-05-24 06:03:05'),
(5, 3, 'Emp103', 5, '2023-05-24 06:04:09'),
(6, 1, '125', 1, '2025-05-23 18:05:31'),
(7, 1, '125', 11, '2025-05-23 18:18:33'),
(8, 1, 'Emp  666', 1, '2025-05-29 01:09:50'),
(9, 10, 'EMP 545', 12, '2025-05-29 01:48:01'),
(10, 11, 'teac123', 13, '2025-05-31 01:35:31');

-- --------------------------------------------------------

--
-- Table structure for table `tblsubject`
--

CREATE TABLE `tblsubject` (
  `ID` int(5) NOT NULL,
  `subject_name` varchar(255) DEFAULT NULL,
  `subject_code` varchar(100) DEFAULT NULL,
  `units` int(11) NOT NULL DEFAULT 3,
  `description` text DEFAULT NULL,
  `time_duration` varchar(50) DEFAULT NULL,
  `date_created` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblsubject`
--

INSERT INTO `tblsubject` (`ID`, `subject_name`, `subject_code`, `units`, `description`, `time_duration`, `date_created`) VALUES
(18, 'Fundamentals of Programming', 'ITEC 102', 3, 'abc', NULL, '2025-07-18 05:21:11'),
(19, 'Communication', 'ITEC 103', 3, 'ASDASDASDASSDDSFK FSJDKFJSD FD JKJDK FJDKFJ DKJF DKJF DKJF KD JFDK FJDK FJDKFKJD KJDKFJKFDJ KFDFDFJDKFJDK\r\nDL FJDKFJ DKF D KJFDK JFDK DJ FKDJ KDJFKDJFK DJ FKDJK DJF D  JDKJF GSHDKF', NULL, '2025-07-18 05:22:29'),
(20, 'DATA MANAGEMENT', 'itec 205', 3, 'asdasdkjasdk', NULL, '2025-07-18 05:44:26'),
(21, 'Info Management', 'ITEC 105', 3, 'INFORMATION MANAGEMENT FOCUSES ON INFORMATION MANAGEMENT', NULL, '2025-07-18 05:55:13'),
(22, 'Statistics', 'STA101', 3, 'statistics', NULL, '2025-07-19 01:50:38'),
(23, 'Calculus', 'CALC102', 3, 'CALCULUS CALCULUS', '180', '2025-07-19 02:00:38'),
(24, 'Mathematics', 'MATH101', 3, 'Math Math Math', '180,120', '2025-07-19 13:37:35'),
(25, 'abc', 'abc', 1, 'abc', '90', '2025-08-10 12:10:05'),
(26, 'ESports', 'E101', 3, 'Esports', '180', '2025-08-10 14:26:45');

-- --------------------------------------------------------

--
-- Table structure for table `tblteacher`
--

CREATE TABLE `tblteacher` (
  `TeacherID` int(11) NOT NULL,
  `FirstName` varchar(100) NOT NULL,
  `LastName` varchar(100) NOT NULL,
  `UserName` varchar(100) DEFAULT NULL,
  `Email` varchar(255) NOT NULL,
  `Password` varchar(255) DEFAULT NULL,
  `ProfilePic` varchar(255) DEFAULT NULL,
  `EmploymentType` enum('Regular','Part-Time') DEFAULT 'Part-Time',
  `Bachelors` varchar(255) DEFAULT NULL,
  `Masters` varchar(255) DEFAULT NULL,
  `Doctorate` varchar(255) DEFAULT NULL,
  `BachelorsTOR` varchar(255) DEFAULT NULL,
  `MastersTOR` varchar(255) DEFAULT NULL,
  `DoctorateTOR` varchar(255) DEFAULT NULL,
  `BachelorsVerified` tinyint(1) DEFAULT 0,
  `MastersVerified` tinyint(1) DEFAULT 0,
  `DoctorateVerified` tinyint(1) DEFAULT 0,
  `HasExperience` enum('Yes','No') DEFAULT 'No',
  `CoursesLoad` text DEFAULT NULL,
  `MaxLoad` int(11) DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblteacher`
--

INSERT INTO `tblteacher` (`TeacherID`, `FirstName`, `LastName`, `UserName`, `Email`, `Password`, `ProfilePic`, `EmploymentType`, `Bachelors`, `Masters`, `Doctorate`, `BachelorsTOR`, `MastersTOR`, `DoctorateTOR`, `BachelorsVerified`, `MastersVerified`, `DoctorateVerified`, `HasExperience`, `CoursesLoad`, `MaxLoad`, `CreatedAt`) VALUES
(1, 'Leones', 'Joshua', NULL, 'keruberos27@gmail.com', NULL, '95a6fed2c09c7e6740149b39d182b93c1753443828.jpg', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', '3168131.jpg', '3168131.jpg', '3168131.jpg', 0, 0, 0, 'Yes', NULL, NULL, '2025-07-25 19:43:48'),
(2, 'Joshua', 'Leoness', NULL, 'keruberos217@gmail.com', NULL, '95a6fed2c09c7e6740149b39d182b93c1753444100.jpg', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', '1ac13452-f6e5-4e19-b3dd-83b6c3ff4e53.png', 'BSAIS 4 DB GRADES.png', '3168131.jpg', 0, 0, 0, 'Yes', NULL, NULL, '2025-07-25 19:48:20'),
(3, 'Joshua', 'Leones', NULL, 'keruberos@gmail.com', NULL, '8c3d386401ad31d4c5f59c4c00fd9ce31753464799.png', 'Part-Time', '', '', '', '', '', '', 0, 0, 0, 'No', NULL, NULL, '2025-07-26 01:33:19'),
(4, 'Joshua', 'Leones', NULL, 'kerus27@gmail.com', NULL, '8c3d386401ad31d4c5f59c4c00fd9ce31753464885.png', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', '3168131.jpg', '3168131.jpg', 'BSIT 2D 24-25 2ND SEM.png', 0, 0, 0, 'Yes', NULL, NULL, '2025-07-26 01:34:45'),
(5, 'Joshua', 'Leones', NULL, 'keos27@gmail.com', NULL, 'c18b62b99a3365010e2c122aea9111c71753464963.png', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', 'BSAIS 3 GRADES.png', 'BSAIS 4 DB GRADES.png', 'bsit 1d.png', 0, 0, 0, 'No', NULL, NULL, '2025-07-26 01:36:03'),
(6, 'Joshua', 'Leones', NULL, 'asdasgaos27@gmail.com', NULL, '8c3d386401ad31d4c5f59c4c00fd9ce31753465014.png', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', 'BSAIS 4 DB GRADES.png', 'BSAIS 3 GRADES.png', 'BSAIS 3 GRADES.png', 0, 0, 0, 'Yes', NULL, NULL, '2025-07-26 01:36:54'),
(7, 'Joshua', 'Leones', NULL, 'asdasos27@gmail.com', NULL, '95a6fed2c09c7e6740149b39d182b93c1753465158.jpg', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', 'BSAIS 3 GRADES.png', 'BSIT 2D.png', 'BSIT 2D 24-25 2ND SEM.png', 0, 0, 0, 'Yes', NULL, NULL, '2025-07-26 01:39:18'),
(8, 'Joshua', 'Leones', NULL, 'asdasgos27@gmail.com', NULL, '2b60ff5a239c8f6fe081e80470fa74941753465229.png', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', 'BSAIS 3 GRADES.png', 'BSAIS 3 GRADES.png', 'BSIT 2D.png', 0, 0, 0, 'Yes', NULL, NULL, '2025-07-26 01:40:29'),
(9, 'Joshua', 'Leones', NULL, 'asdghrfhfdgds27@gmail.com', NULL, '8c3d386401ad31d4c5f59c4c00fd9ce31753465376.png', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', 'BSAIS 3 GRADES.png', 'BSAIS 4 DB GRADES.png', 'BSIT 2A.png', 0, 0, 0, 'Yes', NULL, NULL, '2025-07-26 01:42:56'),
(11, 'Joshua', 'Leones', NULL, 'sdfgdfgdfg27@gmail.com', NULL, 'c1ef8a1948d3a25dbf593f344ad777881753465669.png', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', '3168131.jpg', '3168131.jpg', 'BSIT 2D 24-25 2ND SEM.png', 0, 0, 0, 'Yes', NULL, NULL, '2025-07-26 01:47:49'),
(12, 'Joshua', 'Leones', NULL, 'asdffdfdkgdf7@gmail.com', NULL, '80dbf61cafc4d54e70ccd30111f886751753467535.png', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', 'BSIT 2A.png', 'BSAIS 4 ISO GRADES.png', 'BSIT 2C.png', 1, 0, 0, 'Yes', NULL, NULL, '2025-07-26 02:18:55'),
(13, 'Daniel', 'Padilla', NULL, 'Dan@gmail.com', NULL, 'c1ef8a1948d3a25dbf593f344ad777881753468247.png', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', 'BSAIS 4 ISO GRADES.png', 'BSAIS 4 ISO GRADES.png', 'BSIT 2A.png', 0, 0, 0, 'Yes', NULL, NULL, '2025-07-26 02:30:47'),
(14, 'Joshua', 'Leones', NULL, 'asdsdgfdsf27@gmail.com', NULL, '79119a46eb353640bc6104cdb78da45e1753468318.png', 'Part-Time', '', '', '', '', '', '', 0, 0, 0, 'No', NULL, NULL, '2025-07-26 02:31:58'),
(15, 'Eren', 'Yeager', NULL, 'erenyearger123@gmail.com', NULL, '95a6fed2c09c7e6740149b39d182b93c1753473067.jpg', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', 'BSAIS 4 ISO GRADES.png', 'BSAIS 4 DB GRADES.png', 'BSAIS 3 GRADES.png', 0, 0, 0, 'Yes', NULL, NULL, '2025-07-26 03:51:07'),
(16, 'Naruto', 'Uzumaki', NULL, 'naruto.uzumaki@gmail.com', NULL, '95a6fed2c09c7e6740149b39d182b93c1753483780.jpg', 'Part-Time', 'Bachelor of Science in Information System', 'Masters of Science in Information System', 'Doctor of Science in Information System', 'BSAIS 4 ISO GRADES.png', 'BSAIS 4 ISO GRADES.png', 'BSIT 2A.png', 1, 1, 0, 'Yes', NULL, NULL, '2025-07-26 06:49:40'),
(17, 'Sinichi', 'Kudo', NULL, 'Sinichikudo123@gmail.com', NULL, '95a6fed2c09c7e6740149b39d182b93c1754720795.jpg', 'Regular', 'Bachelor of Science in Information System', '', '', '1ac13452-f6e5-4e19-b3dd-83b6c3ff4e53.png', '', '', 1, 0, 0, 'No', NULL, NULL, '2025-08-09 14:26:35'),
(18, 'Bruce', 'Banner', 'bruce', 'bruce.banner@gmail.com', '$2y$10$qUi.QEVrmjLIMwaXKR.k9u9.CZZ.R3GND7MOFBpQxdsINIcG6ObPi', '95a6fed2c09c7e6740149b39d182b93c1754813588.jpg', 'Part-Time', 'Bachelor of Science in Information System', '', '', 'BSAIS 4 ISO GRADES.png', '', '', 0, 0, 0, 'No', '18,12,15,16,13,1', NULL, '2025-08-10 16:13:08'),
(19, 'Chrollo', 'Lucilfer', 'chrollo', 'hunter.hunter@gmail.com', '$2y$10$dMTU4HuoIqQ.OOarfcKLlu.AzmlB65cOq8tnStjmEgbpANYAVlQly', '95a6fed2c09c7e6740149b39d182b93c1754880318.jpg', 'Part-Time', 'Bachelor of Science in Information System', '', '', '3168131.jpg', '', '', 0, 0, 0, 'No', '18,17,15', 30, '2025-08-11 10:45:18');

-- --------------------------------------------------------

--
-- Table structure for table `tblteachingload`
--

CREATE TABLE `tblteachingload` (
  `TeachingLoadID` int(11) NOT NULL,
  `TeacherID` int(11) NOT NULL,
  `TeachingLoadFile` varchar(255) DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp(),
  `SubjectsTaught` varchar(255) DEFAULT NULL,
  `Verified` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblteachingload`
--

INSERT INTO `tblteachingload` (`TeachingLoadID`, `TeacherID`, `TeachingLoadFile`, `CreatedAt`, `SubjectsTaught`, `Verified`) VALUES
(1, 9, '0c12bd76e13dc4ef68da579ad5a0083a_BSAIS 3 GRADES.png', '2025-07-26 01:42:56', 'asdasdasdas', 0),
(2, 9, '18950636e85b41d5a4a2a14054bab5b1_BSAIS 3 GRADES.png', '2025-07-26 01:42:56', 'asdasdasd', 0),
(5, 11, '014357ebb856c2a995413a09254636a6_3168131.jpg', '2025-07-26 01:47:49', 'Programming,Information Management,Data', 1),
(6, 11, 'ed9b47158591598bd1099b376b305acb_3168131.jpg', '2025-07-26 01:47:49', 'Java', 1),
(7, 12, 'b1c383a6e4fd271c86a01850d32d7de9_BSIT 2D 24-25 2ND SEM.png', '2025-07-26 02:18:55', 'asdasd', 0),
(8, 12, '66755c4fe6bf51964d1315926c36f330_BSAIS 4 ISO GRADES.png', '2025-07-26 02:18:55', 'asdasd', 0),
(9, 13, 'b2a8f48dfeb528372d82a0ee0232d0bd_BSIT 2A.png', '2025-07-26 02:30:47', 'System Maintenance, Communication, Security', 0),
(10, 13, '85a150daf333f1d406112b1e2f61ddc6_BSAIS 4 ISO GRADES.png', '2025-07-26 02:30:47', 'Privacy, Authentication', 0),
(11, 15, '57d85d6cf27f5f93c60cc1e4d9461948_1ac13452-f6e5-4e19-b3dd-83b6c3ff4e53.png', '2025-07-26 03:51:07', 'Subject a, Subject b, Subject c', 0),
(12, 15, '8da8f068be49470959138722d075be24_8293c75b-5767-4866-ad49-99a2fe0be8e4.png', '2025-07-26 03:51:07', 'Subject d, Subject c', 0),
(13, 16, '1121ad0242626a7a8365a4fb3cf0ea26_bsoa 1b grades.png', '2025-07-26 06:49:40', 'Subject abc, Ethics', 0);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_loads`
--

CREATE TABLE `teacher_loads` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `total_units` int(11) DEFAULT 0,
  `total_subjects` int(11) DEFAULT 0,
  `last_updated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_preferred_times`
--

CREATE TABLE `teacher_preferred_times` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `time_slot` enum('morning','afternoon','night') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_preferred_times`
--

INSERT INTO `teacher_preferred_times` (`id`, `teacher_id`, `day_of_week`, `time_slot`, `created_at`) VALUES
(1, 16, 'Monday', 'morning', '2025-07-25 22:49:40'),
(2, 16, 'Monday', 'afternoon', '2025-07-25 22:49:40'),
(3, 16, 'Monday', 'night', '2025-07-25 22:49:40'),
(4, 16, 'Tuesday', 'night', '2025-07-25 22:49:40'),
(5, 16, 'Wednesday', 'night', '2025-07-25 22:49:40'),
(6, 16, 'Thursday', 'afternoon', '2025-07-25 22:49:40'),
(7, 16, 'Friday', 'afternoon', '2025-07-25 22:49:40'),
(8, 16, 'Saturday', 'afternoon', '2025-07-25 22:49:40'),
(9, 16, 'Saturday', 'night', '2025-07-25 22:49:40'),
(10, 17, 'Monday', 'morning', '2025-08-09 06:26:35'),
(11, 17, 'Tuesday', 'morning', '2025-08-09 06:26:35'),
(12, 17, 'Tuesday', 'afternoon', '2025-08-09 06:26:35'),
(13, 17, 'Wednesday', 'morning', '2025-08-09 06:26:35'),
(14, 17, 'Wednesday', 'afternoon', '2025-08-09 06:26:35'),
(15, 17, 'Thursday', 'afternoon', '2025-08-09 06:26:35'),
(16, 17, 'Friday', 'night', '2025-08-09 06:26:35'),
(17, 17, 'Saturday', 'night', '2025-08-09 06:26:35'),
(18, 18, 'Monday', 'morning', '2025-08-10 08:13:08'),
(19, 18, 'Tuesday', 'morning', '2025-08-10 08:13:08'),
(20, 18, 'Wednesday', 'morning', '2025-08-10 08:13:08'),
(21, 18, 'Thursday', 'morning', '2025-08-10 08:13:08'),
(22, 18, 'Friday', 'morning', '2025-08-10 08:13:08'),
(23, 18, 'Saturday', 'morning', '2025-08-10 08:13:08'),
(24, 19, 'Monday', 'afternoon', '2025-08-11 02:45:18'),
(25, 19, 'Monday', 'night', '2025-08-11 02:45:18'),
(26, 19, 'Tuesday', 'afternoon', '2025-08-11 02:45:18'),
(27, 19, 'Tuesday', 'night', '2025-08-11 02:45:18'),
(28, 19, 'Wednesday', 'afternoon', '2025-08-11 02:45:18'),
(29, 19, 'Wednesday', 'night', '2025-08-11 02:45:18'),
(30, 19, 'Thursday', 'afternoon', '2025-08-11 02:45:18'),
(31, 19, 'Thursday', 'night', '2025-08-11 02:45:18'),
(32, 19, 'Friday', 'afternoon', '2025-08-11 02:45:18'),
(33, 19, 'Friday', 'night', '2025-08-11 02:45:18'),
(34, 19, 'Saturday', 'afternoon', '2025-08-11 02:45:18'),
(35, 19, 'Saturday', 'night', '2025-08-11 02:45:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `allocation_logs`
--
ALTER TABLE `allocation_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `allocation_shortfalls`
--
ALTER TABLE `allocation_shortfalls`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parttime_schedules`
--
ALTER TABLE `parttime_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_schedule` (`schedule_type`);

--
-- Indexes for table `subject_allocations`
--
ALTER TABLE `subject_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_id` (`subject_id`,`section_id`,`schedule_day`,`schedule_time_slot`);

--
-- Indexes for table `subject_teachers`
--
ALTER TABLE `subject_teachers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `preferred_time_id` (`preferred_time_id`);

--
-- Indexes for table `tbladmin`
--
ALTER TABLE `tbladmin`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `tblclass`
--
ALTER TABLE `tblclass`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_class` (`course_id`,`academic_year`,`semester`,`year_level`,`section`);

--
-- Indexes for table `tblcourse`
--
ALTER TABLE `tblcourse`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `tblcurriculum`
--
ALTER TABLE `tblcurriculum`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `tblqualification`
--
ALTER TABLE `tblqualification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_qualification_subject` (`subject_id`);

--
-- Indexes for table `tblskills`
--
ALTER TABLE `tblskills`
  ADD PRIMARY KEY (`SkillID`),
  ADD KEY `TeacherID` (`TeacherID`);

--
-- Indexes for table `tblsuballocation`
--
ALTER TABLE `tblsuballocation`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `tblsubject`
--
ALTER TABLE `tblsubject`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `tblteacher`
--
ALTER TABLE `tblteacher`
  ADD PRIMARY KEY (`TeacherID`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD UNIQUE KEY `UserName` (`UserName`);

--
-- Indexes for table `tblteachingload`
--
ALTER TABLE `tblteachingload`
  ADD PRIMARY KEY (`TeachingLoadID`),
  ADD KEY `TeacherID` (`TeacherID`);

--
-- Indexes for table `teacher_loads`
--
ALTER TABLE `teacher_loads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`,`academic_year`,`semester`);

--
-- Indexes for table `teacher_preferred_times`
--
ALTER TABLE `teacher_preferred_times`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `allocation_logs`
--
ALTER TABLE `allocation_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `allocation_shortfalls`
--
ALTER TABLE `allocation_shortfalls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parttime_schedules`
--
ALTER TABLE `parttime_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subject_allocations`
--
ALTER TABLE `subject_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subject_teachers`
--
ALTER TABLE `subject_teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbladmin`
--
ALTER TABLE `tbladmin`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tblclass`
--
ALTER TABLE `tblclass`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `tblcourse`
--
ALTER TABLE `tblcourse`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `tblcurriculum`
--
ALTER TABLE `tblcurriculum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tblqualification`
--
ALTER TABLE `tblqualification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tblskills`
--
ALTER TABLE `tblskills`
  MODIFY `SkillID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `tblsuballocation`
--
ALTER TABLE `tblsuballocation`
  MODIFY `ID` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tblsubject`
--
ALTER TABLE `tblsubject`
  MODIFY `ID` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `tblteacher`
--
ALTER TABLE `tblteacher`
  MODIFY `TeacherID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `tblteachingload`
--
ALTER TABLE `tblteachingload`
  MODIFY `TeachingLoadID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `teacher_loads`
--
ALTER TABLE `teacher_loads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_preferred_times`
--
ALTER TABLE `teacher_preferred_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `subject_teachers`
--
ALTER TABLE `subject_teachers`
  ADD CONSTRAINT `subject_teachers_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `tblsubject` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_teachers_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `tblteacher` (`TeacherID`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_teachers_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `tblclass` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `subject_teachers_ibfk_4` FOREIGN KEY (`program_id`) REFERENCES `tblcourse` (`ID`) ON DELETE SET NULL,
  ADD CONSTRAINT `subject_teachers_ibfk_5` FOREIGN KEY (`preferred_time_id`) REFERENCES `teacher_preferred_times` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tblclass`
--
ALTER TABLE `tblclass`
  ADD CONSTRAINT `tblclass_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `tblcourse` (`ID`);

--
-- Constraints for table `tblcurriculum`
--
ALTER TABLE `tblcurriculum`
  ADD CONSTRAINT `tblcurriculum_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `tblcourse` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tblcurriculum_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `tblsubject` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblqualification`
--
ALTER TABLE `tblqualification`
  ADD CONSTRAINT `fk_qualification_subject` FOREIGN KEY (`subject_id`) REFERENCES `tblsubject` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblskills`
--
ALTER TABLE `tblskills`
  ADD CONSTRAINT `tblskills_ibfk_1` FOREIGN KEY (`TeacherID`) REFERENCES `tblteacher` (`TeacherID`) ON DELETE CASCADE;

--
-- Constraints for table `tblteachingload`
--
ALTER TABLE `tblteachingload`
  ADD CONSTRAINT `tblteachingload_ibfk_1` FOREIGN KEY (`TeacherID`) REFERENCES `tblteacher` (`TeacherID`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_preferred_times`
--
ALTER TABLE `teacher_preferred_times`
  ADD CONSTRAINT `teacher_preferred_times_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `tblteacher` (`TeacherID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

CREATE TABLE IF NOT EXISTS tblallocation_priority_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year VARCHAR(20) NOT NULL,
    semester VARCHAR(15) NOT NULL,
    priority_order VARCHAR(50) NOT NULL, -- e.g. "skills,educ,exp"
    generated_by INT DEFAULT NULL, -- admin ID or user ID, can be NULL if not tracked
    date_generated DATETIME DEFAULT CURRENT_TIMESTAMP,
    remark VARCHAR(255) DEFAULT NULL
);