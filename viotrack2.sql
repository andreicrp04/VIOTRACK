-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 09, 2025 at 06:14 AM
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
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `title` varchar(255) NOT NULL,
  `time` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('meeting','event','academic','holiday','violation_meeting') DEFAULT 'meeting',
  `status` enum('pending','done','cancelled') DEFAULT 'pending',
  `student_id` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `appointment_date`, `title`, `time`, `description`, `type`, `status`, `student_id`, `created_at`, `updated_at`) VALUES
(4, '2025-09-15', 'Parent-Teacher Conference', '14:00', 'Quarterly parent-teacher meeting for Grade 10', 'meeting', 'pending', NULL, '2025-09-09 04:09:50', '2025-09-09 04:09:50'),
(5, '2025-09-20', 'Faculty Meeting', '15:00', 'Monthly faculty discussion', 'meeting', 'pending', NULL, '2025-09-09 04:09:50', '2025-09-09 04:09:50'),
(6, '2025-09-25', 'Science Fair', '09:00', 'Annual science exhibition', 'event', 'pending', NULL, '2025-09-09 04:09:50', '2025-09-09 04:09:50'),
(7, '2025-10-01', 'National Day', '00:00', 'Public holiday', 'holiday', 'pending', NULL, '2025-09-09 04:09:50', '2025-09-09 04:09:50'),
(8, '2025-10-05', 'Mid-term Exams Begin', '08:00', 'First semester examinations start', 'academic', 'pending', NULL, '2025-09-09 04:09:50', '2025-09-09 04:09:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
