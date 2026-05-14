-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 14, 2026 at 02:07 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `portal_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL COMMENT 'Unique ID for each admin',
  `username` varchar(50) NOT NULL COMMENT 'Admin''s login name',
  `password` varchar(255) NOT NULL COMMENT 'Admin''s password (needs to be long for security)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`) VALUES
(1, 'alice', '$2y$10$9rMzzcUZeK8Xr9w.xd/tjO4If0o0quKp8APkGrvLb06FEdGttPEHK'),
(3, 'aaa', '$2y$10$h3ErMhpEG0lc7Hy903IebefE18Qe0oj1ll61PZVNo30Mb7V9opxFu'),
(5, 'new', '$2y$10$RDWd.5QgXaZpSuwfchLbQ.HTzlWeuERlyej6jcqPyp0R5ru.Wo1sa');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `marked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `subject_id`, `attendance_date`, `status`, `marked_by`, `created_at`) VALUES
(7, 12, 9, '2026-05-14', 'present', NULL, '2026-05-14 08:51:19'),
(8, 7, 7, '2026-05-14', 'present', 11, '2026-05-14 08:51:39'),
(9, 12, 7, '2026-05-14', 'present', 11, '2026-05-14 08:51:39'),
(10, 12, 3, '2026-05-14', 'excused', NULL, '2026-05-14 08:51:53'),
(11, 12, 4, '2026-05-14', 'present', NULL, '2026-05-14 08:52:05'),
(12, 7, 6, '2026-05-14', 'present', NULL, '2026-05-14 08:52:16'),
(13, 7, 8, '2026-05-14', 'late', 11, '2026-05-14 09:58:16'),
(14, 12, 8, '2026-05-14', 'absent', 11, '2026-05-14 09:58:16'),
(17, 7, 6, '2026-05-22', 'present', 8, '2026-05-14 10:05:05'),
(18, 12, 6, '2026-05-22', 'present', 8, '2026-05-14 10:05:05');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `subject_id`, `enrolled_at`) VALUES
(6, 7, 9, '2026-04-30 19:05:13'),
(7, 12, 9, '2026-05-14 08:39:44'),
(9, 12, 7, '2026-05-14 08:48:08'),
(10, 7, 7, '2026-05-14 08:48:18'),
(11, 12, 4, '2026-05-14 08:48:45'),
(12, 12, 3, '2026-05-14 08:48:56'),
(13, 7, 5, '2026-05-14 08:49:34'),
(14, 7, 8, '2026-05-14 08:50:01'),
(15, 12, 8, '2026-05-14 08:50:08'),
(16, 7, 6, '2026-05-14 09:10:33'),
(17, 12, 6, '2026-05-14 09:10:42');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment` varchar(50) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) NOT NULL DEFAULT 100.00,
  `graded_by` int(11) DEFAULT NULL,
  `graded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `student_id`, `subject_id`, `assessment`, `score`, `max_score`, `graded_by`, `graded_at`) VALUES
(6, 12, 9, 'Test', 60.00, 100.00, 11, '2026-05-14 09:13:36'),
(7, 12, 8, 'exam', 56.00, 100.00, 11, '2026-05-14 09:14:06'),
(8, 12, 8, 'Control Work', 70.00, 100.00, 11, '2026-05-14 09:14:36'),
(9, 7, 9, 'Exam', 9.00, 10.00, 11, '2026-05-14 09:15:04'),
(10, 7, 9, 'Test', 40.00, 100.00, 11, '2026-05-14 09:15:47'),
(11, 7, 9, 'Exam', 62.00, 100.00, 11, '2026-05-14 09:16:22'),
(12, 7, 9, 'Mid-term', 7.00, 10.00, 11, '2026-05-14 09:35:23'),
(13, 7, 9, 'Exercise', 10.00, 10.00, 11, '2026-05-14 09:35:41'),
(14, 7, 8, 'Exam', 8.00, 10.00, 11, '2026-05-14 09:36:18'),
(15, 7, 8, 'Mid-term', 8.00, 10.00, 11, '2026-05-14 09:36:42'),
(16, 7, 8, 'Exercise', 4.00, 10.00, 11, '2026-05-14 09:36:52'),
(17, 12, 9, 'Exam', 10.00, 10.00, 11, '2026-05-14 09:37:50'),
(18, 12, 9, 'Exercise', 6.00, 10.00, 11, '2026-05-14 09:38:22'),
(19, 12, 8, 'Mid-term', 10.00, 10.00, 11, '2026-05-14 09:38:49'),
(20, 12, 8, 'Exercise', 4.00, 10.00, 11, '2026-05-14 09:40:12'),
(21, 7, 7, 'Exam', 8.00, 10.00, 11, '2026-05-14 09:40:36'),
(22, 12, 7, 'Exam', 8.00, 10.00, 11, '2026-05-14 09:40:46'),
(23, 7, 7, 'Exercise', 6.00, 10.00, 11, '2026-05-14 09:41:05'),
(24, 12, 7, 'Exercise', 7.00, 10.00, 11, '2026-05-14 09:41:16'),
(25, 7, 6, 'Exam', 10.00, 10.00, 8, '2026-05-14 10:06:38'),
(26, 7, 6, 'Exercise', 8.00, 10.00, 8, '2026-05-14 10:06:49'),
(27, 7, 6, 'Mid-term', 10.00, 10.00, 8, '2026-05-14 10:07:03'),
(28, 12, 6, 'Exam', 10.00, 10.00, 8, '2026-05-14 10:07:21'),
(29, 12, 6, 'Mid-term', 9.00, 10.00, 8, '2026-05-14 10:07:33'),
(30, 12, 6, 'Exercise', 10.00, 10.00, 8, '2026-05-14 10:07:44'),
(31, 12, 4, 'Exam', 9.00, 10.00, 8, '2026-05-14 10:08:17'),
(32, 12, 4, 'Mid-term', 7.00, 10.00, 8, '2026-05-14 10:08:28'),
(33, 12, 4, 'Exercise', 10.00, 10.00, 8, '2026-05-14 10:08:37'),
(34, 12, 3, 'Exam', 6.00, 10.00, 8, '2026-05-14 10:08:53'),
(35, 12, 3, 'Mid-term', 4.00, 10.00, 8, '2026-05-14 10:09:01'),
(36, 12, 3, 'Exercise', 10.00, 10.00, 8, '2026-05-14 10:09:16');

-- --------------------------------------------------------

--
-- Table structure for table `homework`
--

CREATE TABLE `homework` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `homework`
--

INSERT INTO `homework` (`id`, `subject_id`, `teacher_id`, `title`, `description`, `due_date`, `created_at`) VALUES
(1, 4, 8, 'control work', 'if you want to pass, make sure to uploade before the D day', '2026-04-30', '2026-04-27 06:17:34'),
(2, 3, 8, 'exam', '', '2026-04-27', '2026-04-27 07:00:25'),
(3, 9, 11, 'Week 4 Homework', 'Please submit your work before time', '2026-06-01', '2026-05-14 10:02:04'),
(4, 8, 11, 'Final Exam', 'Please be present', '2026-05-29', '2026-05-14 10:02:43'),
(5, 7, 11, 'Presentation', 'Group Work', '2026-06-05', '2026-05-14 10:03:39'),
(6, 6, 8, 'Essay', '', '2026-06-01', '2026-05-14 10:10:42'),
(7, 5, 8, 'Web development', '', '2026-06-14', '2026-05-14 10:11:21'),
(8, 4, 8, 'Python', '', '2026-05-14', '2026-05-14 10:12:27');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `teacher_id`, `created_at`) VALUES
(3, 'thesis', '0022', 8, '2026-04-27 04:13:01'),
(4, 'programming', '5555', 8, '2026-04-27 06:09:28'),
(5, 'ICT', '2222', 8, '2026-04-27 06:18:47'),
(6, 'english', '88888', 8, '2026-04-30 19:04:23'),
(7, 'Public Speaking', '1234', 11, '2026-05-14 08:32:59'),
(8, 'Maths', '1122', 11, '2026-05-14 08:34:01'),
(9, 'Law', '1133', 11, '2026-05-14 08:36:06');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_attendance`
--

CREATE TABLE `teacher_attendance` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `marked_by_admin` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_attendance`
--

INSERT INTO `teacher_attendance` (`id`, `teacher_id`, `attendance_date`, `status`, `marked_by_admin`, `created_at`) VALUES
(1, 8, '2026-04-10', 'excused', 5, '2026-04-27 06:06:48'),
(3, 8, '2026-04-30', 'present', 5, '2026-04-30 19:06:57'),
(4, 11, '2026-05-14', 'present', 5, '2026-05-14 08:52:32'),
(5, 8, '2026-05-14', 'present', 5, '2026-05-14 08:52:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL COMMENT 'Unique ID for each user',
  `username` varchar(50) NOT NULL COMMENT 'User''s login name',
  `password` varchar(255) NOT NULL COMMENT 'User''s password',
  `name` varchar(100) DEFAULT NULL COMMENT 'User''s first name',
  `surname` varchar(100) DEFAULT NULL COMMENT 'User''s last name',
  `birth_year` year(4) DEFAULT NULL COMMENT 'User''s year of birth',
  `description` text DEFAULT NULL COMMENT 'A long text about the user',
  `photo` varchar(255) DEFAULT NULL COMMENT 'The file path of the user''s photo',
  `role` enum('student','teacher') NOT NULL DEFAULT 'student'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `surname`, `birth_year`, `description`, `photo`, `role`) VALUES
(7, 'old', '$2y$10$bARgZz5vE2LrQpUcfnHrcuh9O2DuN0aqctAIAW1XMKLuw/Aw3oeYa', 'old', 'here', '2001', 'i used 00000 as password', '', 'student'),
(8, 'okay', '$2y$10$Yu9DAEgJ7oM7/Ek5oiRZku/rOqfoFgmxNZHR5tWx9ahkHjJL84s7K', 'okay', 'okay', '1989', 'do not stress me', 'uploads/8_1777429920.jpeg', 'teacher'),
(11, 'Ama Teacher', '$2y$10$rt1tK.Cr/STEEUZh1T0L6OC3T8cpIZHL0K80QczIaGuNwfGyadtCK', '', '', '0000', '', '', 'teacher'),
(12, 'Yoo', '$2y$10$npUtv/pSYBKrIM92m.bKbe1.2R9pYNlUjLA5MJjlAUuwbnXUM3Iw2', 'hoo', 'Yoo', '2005', 'do not forget your password 00000', 'uploads/12_1778746991.jpeg', 'student');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_att` (`student_id`,`subject_id`,`attendance_date`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `marked_by` (`marked_by`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_enroll` (`student_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `graded_by` (`graded_by`);

--
-- Indexes for table `homework`
--
ALTER TABLE `homework`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_subject_code` (`subject_code`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_teacher_att` (`teacher_id`,`attendance_date`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `ta_ibfk_2` (`marked_by_admin`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique ID for each admin', AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `homework`
--
ALTER TABLE `homework`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique ID for each user', AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `homework`
--
ALTER TABLE `homework`
  ADD CONSTRAINT `hw_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hw_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD CONSTRAINT `ta_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ta_ibfk_2` FOREIGN KEY (`marked_by_admin`) REFERENCES `admins` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
