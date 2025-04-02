-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 14, 2025 at 01:37 AM
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
-- Database: `iworld`
--

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duration` int(10) UNSIGNED NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `category` enum('IT','Non-IT','Safety & Health') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `description`, `duration`, `created_by`, `created_at`, `valid_from`, `valid_to`, `category`) VALUES
(5, 'Administering Microsoft Azure SQL Solutions (DP-300)', 'This course provides students with the knowledge and skills to administer a SQL Server database infrastructure for cloud, on-premises and hybrid relational databases and who work with the Microsoft PaaS relational database offerings. Additionally, it will be of use to individuals who develop applications that deliver content from SQL-based relational databases.', 3, 1, '2024-12-27 08:34:19', '2024-02-08', '2025-04-08', 'IT'),
(10, 'sains', 'ilmu pengetahuan yg teratur (sistematik) yg boleh diuji atau dibuktikan kebenarannya; 2. cabang ilmu pengetahuan yg berdasarkan kebenaran atau kenyataan semata-mata (fizik, kimia, biologi, dll). (Kamus Dewan Edisi Keempat)', 300, 1, '2025-01-12 13:26:11', '2025-01-12', '2025-01-13', 'Safety & Health'),
(11, 'try', 'try', 2, 1, '2025-01-13 04:12:25', '2025-01-13', '2025-01-14', 'IT');

-- --------------------------------------------------------

--
-- Table structure for table `course_assignments`
--

CREATE TABLE `course_assignments` (
  `id` int(11) NOT NULL,
  `trainer_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `course_name` varchar(255) DEFAULT NULL,
  `trainer_name` varchar(255) DEFAULT NULL,
  `assigned_by_role` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_assignments`
--

INSERT INTO `course_assignments` (`id`, `trainer_id`, `course_id`, `assigned_by`, `assigned_at`, `course_name`, `trainer_name`, `assigned_by_role`) VALUES
(69, 80, 5, 1, '2025-01-12 13:46:38', 'Administering Microsoft Azure SQL Solutions (DP-300)', 'am.hensem396', 'Admin'),
(70, 82, 5, 1, '2025-01-12 13:46:38', 'Administering Microsoft Azure SQL Solutions (DP-300)', 'am.hensem2189', 'Admin'),
(84, 56, 5, 1, '2025-01-13 04:12:39', NULL, NULL, NULL),
(85, 80, 11, 1, '2025-01-13 04:17:18', 'try', 'am.hensem396', 'Admin'),
(86, 82, 11, 1, '2025-01-13 04:17:18', 'try', 'am.hensem2189', 'Admin'),
(87, 56, 10, 1, '2025-01-13 04:17:28', 'sains', 'trainer', 'Admin'),
(88, 82, 10, 1, '2025-01-13 04:17:28', 'sains', 'am.hensem2189', 'Admin');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'Staff'),
(3, 'Trainer');

-- --------------------------------------------------------

--
-- Table structure for table `role_details`
--

CREATE TABLE `role_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `ic_passport` varchar(50) NOT NULL DEFAULT '-',
  `ttt_status` varchar(50) NOT NULL DEFAULT '-',
  `position` varchar(50) NOT NULL DEFAULT '-'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_details`
--

INSERT INTO `role_details` (`id`, `user_id`, `username`, `ic_passport`, `ttt_status`, `position`) VALUES
(29, 28, 'nurul', '-', '-', 'Position 3'),
(56, 56, 'trainer', '021128140110', '12345', '-'),
(80, 80, 'am.hensem396', '021128140110', '12345', '-'),
(81, 81, 'am.hensem567', '', '-', 'Position 3'),
(82, 82, 'am.hensem2189', '021128140110', '12345', '-'),
(105, 105, 'nurul.bintizulkiflee744', '', '-', 'Position 3');

-- --------------------------------------------------------

--
-- Table structure for table `training_sessions`
--

CREATE TABLE `training_sessions` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `trainer_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `session_end_date` date NOT NULL,
  `session_time` time NOT NULL,
  `session_end_time` time NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','completed') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `training_sessions`
--

INSERT INTO `training_sessions` (`id`, `course_id`, `venue_id`, `trainer_id`, `session_date`, `session_end_date`, `session_time`, `session_end_time`, `created_by`, `created_at`, `status`) VALUES
(32, 5, 3, 56, '2025-01-12', '2025-01-14', '07:00:00', '08:26:00', 1, '2025-01-13 09:19:16', 'completed');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `first_name`, `last_name`, `password`, `email`, `role_id`, `created_at`, `created_by`, `phone_number`) VALUES
(1, 'adminn', '', '', 'admin123', 'admin@example.com', 1, '2024-12-22 02:40:36', NULL, '0129468314'),
(28, 'nurul', '', '', '123', 'nurul@gmail.com', 2, '2024-12-24 03:02:08', 1, '0129468314'),
(56, 'trainer', '', '', 'JEbtK)4p', 'trainer@gmail.com', 3, '2025-01-03 07:59:15', 1, '0123456789'),
(80, 'am.hensem396', 'am', 'hensem', '*!aw8%wE', 'nurulafeeqah2811@gmail.com', 3, '2025-01-12 13:17:55', 1, '0129468314'),
(81, 'am.hensem567', 'am', 'hensem', 'MM*#o^e4', 'nurulafeeqah2811@gmail.com', 2, '2025-01-12 13:30:18', 1, '0129468314'),
(82, 'am.hensem2189', 'am', 'hensem 2', 'N9n1Q^bF', 'nurulafeeqah2811@gmail.com', 3, '2025-01-12 13:46:15', 1, '0129468314'),
(105, 'nurul.bintizulkiflee744', 'Nurul', 'binti Zulkiflee', 'Lre4*U^i', 'nurulafeeqah2811@gmail.com', 2, '2025-01-13 03:45:59', 1, '0129468314');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `update_username_in_role_details` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    IF OLD.username != NEW.username THEN
        UPDATE role_details
        SET username = NEW.username
        WHERE user_id = NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `id` int(11) NOT NULL,
  `venue_name` varchar(100) NOT NULL,
  `location_details` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`id`, `venue_name`, `location_details`, `created_by`, `created_at`) VALUES
(3, 'Lab 1', 'Upper Ground (UG) level.', 1, '2025-01-09 06:38:22'),
(4, 'spm', 'parking', 1, '2025-01-12 13:41:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_name` (`course_name`),
  ADD KEY `courses_ibfk_1` (`created_by`);

--
-- Indexes for table `course_assignments`
--
ALTER TABLE `course_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trainer_id` (`trainer_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_details`
--
ALTER TABLE `role_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_role` (`user_id`);

--
-- Indexes for table `training_sessions`
--
ALTER TABLE `training_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `venue_id` (`venue_id`),
  ADD KEY `trainer_id` (`trainer_id`),
  ADD KEY `training_sessions_ibfk_4` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `fk_users_created_by` (`created_by`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `venue_name` (`venue_name`),
  ADD KEY `fk_venues_created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `course_assignments`
--
ALTER TABLE `course_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `role_details`
--
ALTER TABLE `role_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `training_sessions`
--
ALTER TABLE `training_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course_assignments`
--
ALTER TABLE `course_assignments`
  ADD CONSTRAINT `course_assignments_ibfk_3` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_assignments_ibfk_4` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_details`
--
ALTER TABLE `role_details`
  ADD CONSTRAINT `role_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `training_sessions`
--
ALTER TABLE `training_sessions`
  ADD CONSTRAINT `training_sessions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `venues`
--
ALTER TABLE `venues`
  ADD CONSTRAINT `fk_venues_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
