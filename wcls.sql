-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 21, 2025 at 05:58 PM
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
-- Database: `wcls`
--

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `cart_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `program` varchar(255) NOT NULL,
  `course_code` varchar(255) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_price` float NOT NULL,
  `course_description` text DEFAULT NULL,
  `default_capacity` int(255) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL COMMENT 'FK to teachers.id',
  `year_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `room_number` int(255) NOT NULL,
  `id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`program`, `course_code`, `course_name`, `course_price`, `course_description`, `default_capacity`, `teacher_id`, `year_id`, `term_id`, `room_number`, `id`, `created_at`) VALUES
('Afterschool', 'MLP202', 'Maliping 1', 300, 'Ma Liping Chinese course', 12, 2, 5, 27, 301, 2, '2025-10-21 15:48:07'),
('Sunday', 'C901', 'AP Chinese', 300, '中文AP课程', 12, 2, 5, 37, 210, 7, '2025-10-21 15:48:07'),
('Sunday', 'MLP9', 'Maliping 9', 300, 'MaLiping Chinese Grade9', 12, 2, 5, 37, 209, 19, '2025-10-21 15:48:07'),
('Sunday', 'MT101', 'Math1', 300, 'Math Class', 12, 2, 5, 37, 303, 20, '2025-10-21 15:48:07'),
('Sunday', 'CS101', 'Coding1', 350, 'Begineer coding class', 12, 2, 5, 37, 116, 28, '2025-10-21 15:48:07'),
('Sunday', 'BL101', 'Bilingual1', 290, 'Biligual Chinese', 12, 2, 5, 38, 115, 29, '2025-10-21 15:48:07'),
('Afterschool', 'MLP202', 'Maliping 1', 300, 'Ma Liping Chinese course', 12, 2, 2, 15, 303, 32, '2025-10-03 21:09:41'),
('Sunday', 'C901', 'AP Chinese', 300, '中文AP课程', 12, 2, 2, 25, 202, 33, '2025-10-11 21:39:52'),
('Sunday', 'MLP9', 'Maliping 9', 300, 'MaLiping Chinese Grade9', 12, 2, 2, 25, 909, 34, '2025-10-03 21:09:41'),
('Sunday', 'MT101', 'Math1', 300, 'Math Class', 12, 2, 2, 25, 303, 35, '2025-10-03 21:09:41'),
('Sunday', 'CS101', 'Coding1', 350, 'Begineer coding class', 12, 2, 2, 25, 116, 36, '2025-10-03 21:09:41'),
('Sunday', 'BL101', 'Bilingual1', 290, 'Biligual Chinese', 10, 2, 2, 26, 115, 37, '2025-10-03 21:09:41'),
('Sunday', 'MLP301', 'MLP3', 300, 'Maliping course 3', 12, 2, 5, 37, 210, 38, '2025-10-21 15:48:07'),
('Sunday', 'PS101', '口才班', 300, 'public speaking', 12, 2, 5, 38, 204, 39, '2025-10-21 15:48:07'),
('Sunday', 'CS201', 'Medium Coding', 300, 'Medium Coding course', 12, 2, 5, 38, 206, 40, '2025-10-21 15:48:07'),
('Sunday', 'DR101', 'Itro to Drawing', 300, 'Drawing Class', 12, 2, 5, 37, 216, 41, '2025-10-21 15:48:07'),
('Sunday', 'PS101', '口才班', 300, 'Public Speaking Class', 10, 2, 2, 25, 210, 42, '2025-10-11 21:40:45'),
('Afterschool', 'BL201', 'BilinguL2', 300, 'Biligual Chinese', 12, 2, 5, 27, 101, 43, '2025-10-21 15:48:07');

-- --------------------------------------------------------

--
-- Table structure for table `courses_bak_2025_2026_20251021`
--

CREATE TABLE `courses_bak_2025_2026_20251021` (
  `program` varchar(255) NOT NULL,
  `course_code` varchar(255) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_price` float NOT NULL,
  `course_description` text DEFAULT NULL,
  `default_capacity` int(255) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL COMMENT 'FK to teachers.id',
  `year_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `room_number` int(255) NOT NULL,
  `id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses_bak_2025_2026_20251021`
--

INSERT INTO `courses_bak_2025_2026_20251021` (`program`, `course_code`, `course_name`, `course_price`, `course_description`, `default_capacity`, `teacher_id`, `year_id`, `term_id`, `room_number`, `id`, `created_at`) VALUES
('Afterschool', 'MLP202', 'Maliping 1', 300, 'Ma Liping Chinese course', 12, 2, 1, 3, 301, 2, '2025-10-11 21:41:08'),
('Sunday', 'C901', 'AP Chinese', 300, '中文AP课程', 12, 2, 1, 13, 210, 7, '2025-10-18 20:18:24'),
('Sunday', 'MLP9', 'Maliping 9', 300, 'MaLiping Chinese Grade9', 12, 2, 1, 13, 209, 19, '2025-10-11 21:41:21'),
('Sunday', 'MT101', 'Math1', 300, 'Math Class', 12, 2, 1, 13, 303, 20, '2025-09-26 16:08:59'),
('Sunday', 'CS101', 'Coding1', 350, 'Begineer coding class', 12, 2, 1, 13, 116, 28, '2025-09-26 16:08:59'),
('Sunday', 'BL101', 'Bilingual1', 290, 'Biligual Chinese', 12, 2, 1, 14, 115, 29, '2025-10-10 19:45:32'),
('Sunday', 'MLP301', 'MLP3', 300, 'Maliping course 3', 12, 2, 1, 13, 210, 38, '2025-10-06 15:25:59'),
('Sunday', 'PS101', '口才班', 300, 'public speaking', 12, 2, 1, 14, 204, 39, '2025-10-07 20:04:24'),
('Sunday', 'CS201', 'Medium Coding', 300, 'Medium Coding course', 12, 2, 1, 14, 206, 40, '2025-10-08 20:50:55'),
('Sunday', 'DR101', 'Itro to Drawing', 300, 'Drawing Class', 12, 2, 1, 13, 216, 41, '2025-10-11 20:54:25'),
('Afterschool', 'BL201', 'BilinguL2', 300, 'Biligual Chinese', 12, 2, 1, 3, 101, 43, '2025-10-18 20:19:16');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `final_grade` varchar(16) DEFAULT NULL,
  `final_comment` text DEFAULT NULL,
  `final_updated` datetime DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `payment_status` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `families`
--

CREATE TABLE `families` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `relationship` varchar(255) DEFAULT NULL,
  `mobile_number` varchar(15) DEFAULT NULL,
  `home_address` varchar(255) DEFAULT NULL,
  `home_city` varchar(255) DEFAULT NULL,
  `home_state` varchar(255) DEFAULT NULL,
  `home_zip` varchar(10) DEFAULT NULL,
  `emergency_contact_name` varchar(128) DEFAULT NULL,
  `emergency_contact_number` varchar(15) DEFAULT NULL,
  `registration_due` date DEFAULT NULL,
  `registration_payment` float DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `families`
--

INSERT INTO `families` (`id`, `user_id`, `relationship`, `mobile_number`, `home_address`, `home_city`, `home_state`, `home_zip`, `emergency_contact_name`, `emergency_contact_number`, `registration_due`, `registration_payment`, `created_at`) VALUES
(1, 7, 'Guardian', '6174177604', '11 Strathmore Road', 'Wellesley', 'MA', '02482', 'Hongye Li', '6174177604', NULL, NULL, '2025-08-26 22:27:35');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `order_total` float DEFAULT NULL,
  `order_status` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_lines`
--

CREATE TABLE `order_lines` (
  `id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `price` float DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset`
--

CREATE TABLE `password_reset` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `family_id` int(11) DEFAULT NULL,
  `first_name` varchar(128) DEFAULT NULL,
  `last_name` varchar(128) DEFAULT NULL,
  `DOB` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `bio` varchar(1000) NOT NULL,
  `title` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL COMMENT 'FK to users.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `image`, `bio`, `title`, `created_at`, `user_id`) VALUES
(2, 'n/a', '初级Coding老师', '李红叶', '2025-10-18 17:17:52', 8);

-- --------------------------------------------------------

--
-- Table structure for table `terms`
--

CREATE TABLE `terms` (
  `id` int(11) NOT NULL,
  `year_id` int(11) NOT NULL,
  `program` enum('Sunday','Afterschool') NOT NULL,
  `term_no` tinyint(4) NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `total_school_days` int(11) DEFAULT NULL,
  `total_blocks` int(11) DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `terms`
--

INSERT INTO `terms` (`id`, `year_id`, `program`, `term_no`, `starts_on`, `ends_on`, `total_school_days`, `total_blocks`, `is_current`, `name`) VALUES
(3, 1, 'Afterschool', 1, '2025-08-27', '2025-09-22', NULL, NULL, 0, 'Block1'),
(4, 1, 'Afterschool', 2, '2025-09-24', '2025-10-21', NULL, NULL, 0, 'Block2'),
(5, 1, 'Afterschool', 3, '2025-10-22', '2025-11-17', NULL, NULL, 0, 'Block3'),
(6, 1, 'Afterschool', 4, '2025-11-18', '2025-12-16', NULL, NULL, 0, 'Block4'),
(7, 1, 'Afterschool', 5, '2025-12-17', '2026-01-22', NULL, NULL, 0, 'Block5'),
(8, 1, 'Afterschool', 6, '2026-01-23', '2026-02-24', NULL, NULL, 0, 'Block6'),
(9, 1, 'Afterschool', 7, '2026-02-25', '2026-03-23', NULL, NULL, 0, 'Block7'),
(10, 1, 'Afterschool', 8, '2026-03-23', '2026-04-17', NULL, NULL, 0, 'Block8'),
(11, 1, 'Afterschool', 9, '2026-04-27', '2026-05-20', NULL, NULL, 0, 'Block9'),
(12, 1, 'Afterschool', 10, '2026-05-21', '2026-06-24', NULL, NULL, 0, 'Block10'),
(13, 1, 'Sunday', 1, '2025-09-07', '2026-02-15', NULL, NULL, 0, 'Fall'),
(14, 1, 'Sunday', 2, '2026-02-22', '2026-06-07', NULL, NULL, 0, 'Spring'),
(15, 2, 'Afterschool', 1, '2026-08-27', '2026-09-22', NULL, NULL, 0, 'Block1'),
(16, 2, 'Afterschool', 2, '2026-09-24', '2026-10-21', NULL, NULL, 0, 'Block2'),
(17, 2, 'Afterschool', 3, '2026-10-22', '2026-11-17', NULL, NULL, 0, 'Block3'),
(18, 2, 'Afterschool', 4, '2026-11-18', '2026-12-16', NULL, NULL, 0, 'Block4'),
(19, 2, 'Afterschool', 5, '2026-12-17', '2027-01-22', NULL, NULL, 0, 'Block5'),
(20, 2, 'Afterschool', 6, '2027-01-23', '2027-02-24', NULL, NULL, 0, 'Block6'),
(21, 2, 'Afterschool', 7, '2027-02-25', '2027-03-23', NULL, NULL, 0, 'Block7'),
(22, 2, 'Afterschool', 8, '2027-03-24', '2027-04-17', NULL, NULL, 0, 'Block8'),
(23, 2, 'Afterschool', 9, '2027-04-27', '2027-05-20', NULL, NULL, 0, 'Block9'),
(24, 2, 'Afterschool', 10, '2027-05-21', '2027-06-24', NULL, NULL, 0, 'Block10'),
(25, 2, 'Sunday', 1, '2026-09-07', '2027-02-15', NULL, NULL, 0, 'Fall'),
(26, 2, 'Sunday', 2, '2027-02-22', '2027-06-07', NULL, NULL, 0, 'Spring'),
(27, 5, 'Afterschool', 1, '2024-08-27', '2024-09-22', NULL, NULL, 0, 'Block1'),
(28, 5, 'Afterschool', 2, '2024-09-24', '2024-10-21', NULL, NULL, 0, 'Block2'),
(29, 5, 'Afterschool', 3, '2024-10-22', '2024-11-17', NULL, NULL, 0, 'Block3'),
(30, 5, 'Afterschool', 4, '2024-11-18', '2024-12-16', NULL, NULL, 0, 'Block4'),
(31, 5, 'Afterschool', 5, '2024-12-17', '2025-01-22', NULL, NULL, 0, 'Block5'),
(32, 5, 'Afterschool', 6, '2025-01-23', '2025-02-24', NULL, NULL, 0, 'Block6'),
(33, 5, 'Afterschool', 7, '2025-02-25', '2025-03-23', NULL, NULL, 0, 'Block7'),
(34, 5, 'Afterschool', 8, '2025-03-23', '2025-04-17', NULL, NULL, 0, 'Block8'),
(35, 5, 'Afterschool', 9, '2025-04-27', '2025-05-20', NULL, NULL, 0, 'Block9'),
(36, 5, 'Afterschool', 10, '2025-05-21', '2025-06-24', NULL, NULL, 0, 'Block10'),
(37, 5, 'Sunday', 1, '2024-09-07', '2025-02-15', NULL, NULL, 0, 'Fall'),
(38, 5, 'Sunday', 2, '2025-02-22', '2025-06-07', NULL, NULL, 0, 'Spring');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(128) NOT NULL,
  `last_name` varchar(128) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `account_type` varchar(128) NOT NULL,
  `username` varchar(128) NOT NULL,
  `verify_token` varchar(64) NOT NULL,
  `verify_status` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `account_type`, `username`, `verify_token`, `verify_status`, `created_at`) VALUES
(6, 'Hongye', 'Li', 'hli15@wpi.edu', '$2y$10$Gv9g04h.jkOMbbjAc2XoCeI9ljWFuNr/XS98w9sUypV1NmInH1sDe', 'Admin', '', '922f77681486d779fdbfa282223f4a72', 0, '2025-08-26 11:09:32'),
(7, 'Hongye', 'Li', 'phoebehongye@gmail.com', '$2y$10$hiiIcakShKOnJGWkYexxyOvBVUND2yXnO8Mz5t4kjU/26p2krfDjG', 'Parent', '', '738bddbb8743f18ea18a4a0fec974c5c', 0, '2025-08-26 15:58:44'),
(8, 'Hongye', 'Li', 'phoebehongye2@gmail.com', '$2y$10$gD3YlcfyGCc2XxsMNo5QZOyMdUGtWpwvLkb0LnQm/S/S.9m25E8Ae', 'Teacher', '', 'c30d0691c0c9bdcb94fe66208524e9ad', 0, '2025-08-28 09:39:20');

-- --------------------------------------------------------

--
-- Table structure for table `years`
--

CREATE TABLE `years` (
  `id` int(11) NOT NULL,
  `start_year` int(11) NOT NULL,
  `label` varchar(9) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `years`
--

INSERT INTO `years` (`id`, `start_year`, `label`, `start_date`, `end_date`, `is_current`, `created_at`) VALUES
(1, 2025, '2025-2026', '2025-08-14', '2026-07-15', 1, '2025-09-13 16:51:51'),
(2, 2026, '2026-2027', '2026-08-15', '2027-07-15', 0, '2025-09-13 16:51:51'),
(3, 2027, '2027-2028', '2027-08-15', '2028-07-15', 0, '2025-09-13 16:51:51'),
(5, 2024, '2024-2025', '2024-08-14', '2025-07-15', 0, '2025-10-21 15:48:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_id` (`cart_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_course_code_per_term` (`year_id`,`term_id`,`course_code`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `idx_courses_year_id` (`year_id`),
  ADD KEY `idx_courses_term_id` (`term_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `families`
--
ALTER TABLE `families`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_lines`
--
ALTER TABLE `order_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `family_id` (`family_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id_indx` (`user_id`);

--
-- Indexes for table `terms`
--
ALTER TABLE `terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_terms` (`program`,`year_id`,`term_no`),
  ADD KEY `idx_terms_name` (`name`),
  ADD KEY `idx_terms_year` (`year_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_index` (`email`) USING BTREE;

--
-- Indexes for table `years`
--
ALTER TABLE `years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_years_start_year` (`start_year`),
  ADD UNIQUE KEY `uq_years_label` (`label`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `families`
--
ALTER TABLE `families`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_lines`
--
ALTER TABLE `order_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `terms`
--
ALTER TABLE `terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `years`
--
ALTER TABLE `years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`),
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `cart_items_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_courses_term` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_courses_year` FOREIGN KEY (`year_id`) REFERENCES `years` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `families`
--
ALTER TABLE `families`
  ADD CONSTRAINT `families_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_lines`
--
ALTER TABLE `order_lines`
  ADD CONSTRAINT `order_lines_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `order_lines_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`);

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
