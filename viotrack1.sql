-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 08, 2025 at 04:34 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `viotrack`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late') NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `attendance_date`, `status`, `recorded_at`) VALUES
(1, 'm22-0962-208', '2025-09-08', 'Present', '2025-09-08 11:16:36');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `grade` varchar(20) NOT NULL,
  `section` varchar(50) NOT NULL,
  `status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `qr_code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `name`, `grade`, `section`, `status`, `qr_code`, `created_at`, `updated_at`) VALUES
(5, '123', 'asas', 'Grade 8', 'Section 1', 'Active', 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=http%3A%2F%2Flocalhost%2Fviotrack1%2Fviolationss.php%3Fstudent_id%3D123', '2025-09-06 01:15:55', '2025-09-08 11:00:26'),
(1, '2023-001', 'John Doe', 'Grade 7', 'Section 1', 'Active', 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=http%3A%2F%2Flocalhost%2Fviotrack1%2Fviolationss.php%3Fstudent_id%3D2023-001', '2025-09-06 01:15:30', '2025-09-08 11:02:24'),
(2, '2023-002', 'Jane Smith', 'Grade 9', 'Section 3', 'Active', NULL, '2025-09-06 01:15:30', '2025-09-06 01:15:30'),
(3, '2023-003', 'Mike Johnson', 'Grade 7', 'Section 9', 'Active', NULL, '2025-09-06 01:15:30', '2025-09-06 01:15:30'),
(4, '2023-004', 'Sarah Wilson', 'Grade 8', 'Section 3', 'Active', NULL, '2025-09-06 01:15:30', '2025-09-06 01:15:30'),
(0, 'm22-0962-208', 'Carpio, Ber Andrei S.', 'Grade 10', 'Section 1', 'Active', 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=http%3A%2F%2Flocalhost%2Fviotrack1%2Fviolationss.php%3Fstudent_id%3Dm22-0962-208', '2025-09-08 11:12:18', '2025-09-08 11:12:18');

-- --------------------------------------------------------

--
-- Table structure for table `violations`
--

CREATE TABLE `violations` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `violation_type` varchar(100) NOT NULL,
  `violation_category` enum('Minor','Serious','Major') NOT NULL,
  `violation_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Resolved') DEFAULT 'Active',
  `recorded_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violations`
--

INSERT INTO `violations` (`id`, `student_id`, `violation_type`, `violation_category`, `violation_date`, `status`, `recorded_by`, `notes`, `created_at`) VALUES
(1, '123', 'Forgery or document tampering', 'Major', '2025-09-08 18:59:25', 'Active', 'Andrei', '', '2025-09-08 10:59:25'),
(2, '123', 'Rough or dangerous play', 'Serious', '2025-09-08 20:02:47', 'Active', 'Andrei', '', '2025-09-08 12:02:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`student_id`,`attendance_date`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `violations`
--
ALTER TABLE `violations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `violations`
--
ALTER TABLE `violations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `violations`
--
ALTER TABLE `violations`
  ADD CONSTRAINT `violations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
