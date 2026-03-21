-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Mar 21, 2026 at 08:52 PM
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
-- Database: `task_management_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `att_date` date DEFAULT NULL,
  `total_hours` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `att_date`, `total_hours`, `created_at`, `time_in`, `time_out`, `organization_id`) VALUES
(1, 5, '2026-03-02', 0.14, '2026-03-02 14:49:26', '22:49:26', '22:57:50', 2),
(2, 4, '2026-03-02', 0.11, '2026-03-02 14:51:20', '22:51:19', '22:57:58', 2),
(3, 5, '2026-03-02', 0.09, '2026-03-02 15:17:19', '23:17:19', '23:22:39', 2),
(4, 4, '2026-03-02', 0.09, '2026-03-02 15:17:30', '23:17:30', '23:22:45', 2),
(5, 5, '2026-03-05', 0.08, '2026-03-04 20:06:28', '04:06:28', '04:11:01', 2),
(6, 5, '2026-03-05', 0.04, '2026-03-04 20:11:03', '04:11:03', '04:13:14', 2),
(7, 5, '2026-03-06', 0.00, '2026-03-06 09:52:07', '17:52:07', '17:52:15', 2),
(8, 5, '2026-03-08', 0.04, '2026-03-07 16:25:28', '00:25:28', '00:27:50', 2),
(9, 5, '2026-03-08', 0.05, '2026-03-07 18:07:50', '02:07:50', '02:10:35', 2),
(10, 5, '2026-03-08', 0.36, '2026-03-07 18:14:50', '02:14:50', '02:36:22', 2),
(11, 5, '2026-03-08', 0.02, '2026-03-07 18:37:01', '02:37:01', '02:38:03', 2),
(12, 5, '2026-03-08', 0.02, '2026-03-07 18:53:54', '02:53:54', '02:56:06', 2),
(13, 4, '2026-03-08', 0.03, '2026-03-07 18:58:01', '02:58:01', '03:01:02', 2),
(14, 4, '2026-03-08', 0.06, '2026-03-07 19:01:47', '03:01:47', '03:18:39', 2),
(15, 4, '2026-03-08', 0.00, '2026-03-07 19:18:43', '03:18:43', '03:19:04', 2),
(16, 4, '2026-03-08', 0.01, '2026-03-07 19:19:17', '03:19:17', '03:19:40', 2),
(17, 4, '2026-03-08', 0.02, '2026-03-07 19:19:41', '03:19:41', '03:46:52', 2),
(18, 4, '2026-03-08', 0.12, '2026-03-07 19:52:43', '03:52:43', '03:59:53', 2),
(19, 4, '2026-03-08', 0.03, '2026-03-07 19:59:59', '03:59:59', '04:01:52', 2),
(20, 4, '2026-03-08', 0.01, '2026-03-07 20:01:59', '04:01:59', '04:02:26', 2),
(21, 4, '2026-03-08', 0.02, '2026-03-07 20:02:44', '04:02:44', '04:04:11', 2),
(22, 5, '2026-03-08', 0.00, '2026-03-07 20:09:22', '04:09:22', '04:09:30', 2),
(23, 5, '2026-03-08', 0.00, '2026-03-07 20:09:32', '04:09:32', '04:09:37', 2),
(24, 5, '2026-03-08', 0.00, '2026-03-07 20:10:41', '04:10:41', '04:10:44', 2),
(25, 5, '2026-03-08', 0.00, '2026-03-07 20:11:30', '04:11:30', '04:11:34', 2),
(26, 5, '2026-03-08', 0.03, '2026-03-08 07:20:54', '15:20:54', '15:22:24', 2),
(27, 5, '2026-03-08', 0.05, '2026-03-08 07:22:59', '15:22:59', '15:28:09', 2),
(28, 5, '2026-03-08', 0.00, '2026-03-08 07:29:32', '15:29:32', '15:29:37', 2),
(29, 5, '2026-03-08', 0.03, '2026-03-08 15:44:14', '23:44:14', '23:46:04', 2),
(30, 5, '2026-03-09', 0.01, '2026-03-08 16:00:15', '00:00:15', '00:00:43', 2),
(31, 5, '2026-03-09', 0.02, '2026-03-08 16:00:44', '00:00:44', '00:01:55', 2),
(32, 5, '2026-03-09', 0.12, '2026-03-08 16:51:51', '00:51:51', '00:58:53', 2),
(33, 5, '2026-03-09', 0.01, '2026-03-08 16:59:18', '00:59:18', '01:00:01', 2),
(34, 5, '2026-03-09', 0.00, '2026-03-08 17:00:17', '01:00:17', '01:00:20', 2),
(35, 5, '2026-03-09', 0.00, '2026-03-08 17:00:24', '01:00:24', '01:00:28', 2),
(36, 5, '2026-03-09', 0.00, '2026-03-08 17:00:46', '01:00:46', '01:00:59', 2),
(37, 5, '2026-03-09', 0.00, '2026-03-08 17:01:48', '01:01:48', '01:01:51', 2),
(38, 5, '2026-03-09', 0.00, '2026-03-08 17:02:38', '01:02:38', '01:02:40', 2),
(39, 5, '2026-03-09', 0.00, '2026-03-08 17:12:54', '01:12:54', '01:12:54', 2),
(40, 5, '2026-03-09', 0.00, '2026-03-08 17:13:03', '01:13:03', '01:13:03', 2),
(41, 5, '2026-03-09', 0.00, '2026-03-08 17:13:14', '01:13:14', '01:13:14', 2),
(42, 5, '2026-03-09', 0.00, '2026-03-08 17:13:16', '01:13:16', '01:13:16', 2),
(43, 5, '2026-03-09', 0.00, '2026-03-08 17:13:21', '01:13:21', '01:13:21', 2),
(44, 5, '2026-03-09', 0.00, '2026-03-08 17:14:20', '01:14:20', '01:14:27', 2),
(45, 5, '2026-03-09', 0.00, '2026-03-08 17:14:30', '01:14:30', '01:14:30', 2),
(46, 5, '2026-03-09', 0.00, '2026-03-08 17:14:32', '01:14:32', '01:14:32', 2),
(47, 5, '2026-03-09', 0.00, '2026-03-08 17:14:39', '01:14:39', '01:14:56', 2),
(48, 5, '2026-03-09', 0.00, '2026-03-08 17:14:59', '01:14:59', '01:14:59', 2),
(49, 5, '2026-03-09', 0.39, '2026-03-08 17:21:45', '01:21:45', '01:45:21', 2),
(50, 5, '2026-03-09', 0.15, '2026-03-08 18:06:44', '02:06:44', '02:15:32', 2),
(51, 5, '2026-03-09', 0.04, '2026-03-08 18:17:33', '02:17:33', '02:19:55', 2),
(52, 5, '2026-03-09', 0.04, '2026-03-08 18:26:42', '02:26:42', '02:29:03', 2),
(53, 5, '2026-03-09', 0.04, '2026-03-08 18:30:16', '02:30:16', '02:32:39', 2),
(54, 5, '2026-03-09', 0.04, '2026-03-08 18:33:40', '02:33:40', '02:35:58', 2),
(55, 5, '2026-03-09', 0.04, '2026-03-08 18:45:37', '02:45:37', '02:47:49', 2),
(56, 5, '2026-03-09', 0.04, '2026-03-08 18:51:28', '02:51:28', '02:53:58', 2),
(57, 5, '2026-03-09', 0.04, '2026-03-08 18:54:52', '02:54:52', '02:57:01', 2),
(58, 5, '2026-03-09', 0.05, '2026-03-08 18:57:29', '02:57:29', '03:00:31', 2),
(59, 5, '2026-03-09', 0.05, '2026-03-08 19:10:40', '03:10:40', '03:13:32', 2),
(60, 4, '2026-03-09', 0.04, '2026-03-08 19:15:02', '03:15:02', '03:17:22', 2),
(61, 4, '2026-03-09', 0.04, '2026-03-08 19:22:12', '03:22:12', '03:24:37', 2),
(62, 5, '2026-03-09', 0.04, '2026-03-08 19:32:11', '03:32:11', '03:34:34', 2),
(63, 4, '2026-03-09', 0.04, '2026-03-08 19:40:16', '03:40:16', '03:42:51', 2),
(64, 5, '2026-03-09', 0.04, '2026-03-08 19:48:26', '03:48:26', '03:50:44', 2),
(65, 5, '2026-03-09', 0.02, '2026-03-08 20:04:09', '04:04:09', '04:05:36', 2),
(66, 5, '2026-03-09', 0.43, '2026-03-08 20:06:36', '04:06:36', '04:35:36', 2),
(67, 5, '2026-03-10', 0.26, '2026-03-09 17:35:41', '01:35:41', '01:59:54', 2),
(68, 5, '2026-03-10', 0.18, '2026-03-09 18:03:26', '02:03:26', '02:13:57', 2),
(69, 5, '2026-03-10', 0.00, '2026-03-09 18:13:59', '02:13:59', '02:14:08', 2),
(70, 5, '2026-03-10', 0.00, '2026-03-09 18:14:12', '02:14:12', '02:14:22', 2),
(71, 5, '2026-03-10', 0.00, '2026-03-09 18:14:37', '02:14:37', '02:14:45', 2),
(72, 5, '2026-03-10', 0.00, '2026-03-09 18:35:42', '02:35:42', '02:35:51', 2),
(73, 5, '2026-03-10', 0.23, '2026-03-09 18:36:29', '02:36:29', '02:50:06', 2),
(74, 5, '2026-03-10', 0.00, '2026-03-09 18:50:26', '02:50:26', '02:50:32', 2),
(75, 5, '2026-03-10', 0.00, '2026-03-09 18:50:43', '02:50:43', '02:50:49', 2),
(76, 5, '2026-03-10', 0.00, '2026-03-09 18:50:53', '02:50:53', '02:50:55', 2),
(77, 5, '2026-03-10', 0.00, '2026-03-09 18:50:57', '02:50:57', '02:51:08', 2),
(78, 5, '2026-03-10', 0.00, '2026-03-09 18:51:09', '02:51:09', '02:51:15', 2),
(79, 5, '2026-03-10', 0.00, '2026-03-09 18:51:19', '02:51:19', '02:51:25', 2),
(80, 5, '2026-03-10', 0.07, '2026-03-09 18:51:35', '02:51:35', '02:55:51', 2),
(81, 5, '2026-03-10', 0.00, '2026-03-09 18:58:10', '02:58:10', '02:58:16', 2),
(82, 5, '2026-03-10', 0.00, '2026-03-09 18:58:47', '02:58:47', '02:58:53', 2),
(83, 5, '2026-03-10', 0.00, '2026-03-09 18:59:00', '02:59:00', '02:59:06', 2),
(84, 5, '2026-03-10', 0.11, '2026-03-09 18:59:14', '02:59:14', '03:05:45', 2),
(85, 5, '2026-03-10', 0.00, '2026-03-10 12:44:54', '20:44:54', '20:45:06', 2),
(86, 5, '2026-03-10', 0.00, '2026-03-10 12:45:12', '20:45:12', '20:45:17', 2),
(87, 5, '2026-03-10', 0.00, '2026-03-10 12:45:38', '20:45:38', '20:45:43', 2),
(88, 5, '2026-03-10', 0.00, '2026-03-10 12:47:25', '20:47:25', '20:47:30', 2),
(89, 5, '2026-03-10', 0.01, '2026-03-10 12:47:33', '20:47:33', '20:48:19', 2),
(90, 5, '2026-03-10', 0.00, '2026-03-10 12:50:55', '20:50:55', '20:51:01', 2),
(91, 5, '2026-03-10', 0.02, '2026-03-10 13:02:32', '21:02:32', '21:03:33', 2),
(92, 5, '2026-03-10', 0.09, '2026-03-10 13:03:38', '21:03:38', '21:08:57', 2),
(93, 5, '2026-03-10', 0.12, '2026-03-10 13:08:58', '21:08:58', '21:16:24', 2),
(94, 5, '2026-03-10', 0.99, '2026-03-10 13:16:58', '21:16:58', '22:16:18', 2),
(95, 5, '2026-03-12', 0.00, '2026-03-11 19:24:32', '03:24:32', '03:24:45', 2),
(96, 5, '2026-03-12', 0.17, '2026-03-11 19:25:14', '03:25:14', '03:35:24', 2),
(97, 5, '2026-03-13', 0.01, '2026-03-12 19:39:54', '03:39:54', '03:40:20', 2),
(98, 7, '2026-03-13', 0.01, '2026-03-13 03:48:06', '11:48:06', '11:48:27', 1),
(99, 7, '2026-03-13', 0.03, '2026-03-13 04:00:32', '12:00:32', '12:02:30', 1),
(100, 7, '2026-03-13', 0.27, '2026-03-13 04:20:09', '12:20:09', '12:36:29', 1),
(101, 7, '2026-03-13', 2.02, '2026-03-13 05:45:26', '13:45:26', '15:46:25', 1),
(102, 5, '2026-03-20', 0.10, '2026-03-20 11:44:27', '19:44:27', '21:28:42', 2),
(103, 5, '2026-03-20', 0.01, '2026-03-20 13:37:54', '21:37:54', '21:38:16', 2),
(104, 5, '2026-03-21', 0.80, '2026-03-21 06:50:03', '14:50:03', '15:38:18', 2),
(105, 5, '2026-03-21', 0.69, '2026-03-21 10:23:59', '18:23:59', '19:05:14', 2),
(106, 4, '2026-03-21', 0.17, '2026-03-21 11:07:34', '19:07:34', '19:17:41', 2),
(107, 5, '2026-03-21', 4.38, '2026-03-21 11:07:49', '19:07:49', '23:30:51', 2),
(108, 5, '2026-03-21', 0.26, '2026-03-21 15:43:36', '23:43:36', '23:59:13', 2);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_adjustments`
--

CREATE TABLE `attendance_adjustments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `att_date` date NOT NULL,
  `hours_deducted` decimal(6,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_adjustments`
--

INSERT INTO `attendance_adjustments` (`id`, `user_id`, `att_date`, `hours_deducted`, `reason`, `created_by`, `updated_by`, `organization_id`, `created_at`, `updated_at`) VALUES
(1, 4, '2026-03-09', 0.25, 'watching youtube', 3, 3, 2, '2026-03-11 19:37:27', '2026-03-11 19:37:27'),
(2, 7, '2026-03-13', 1.00, 'watching youtube', 2, 2, 1, '2026-03-13 08:42:48', '2026-03-13 08:42:48');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_pauses`
--

CREATE TABLE `attendance_pauses` (
  `id` int(11) NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `pause_reason` varchar(255) NOT NULL,
  `paused_at` datetime NOT NULL,
  `resumed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_pauses`
--

INSERT INTO `attendance_pauses` (`id`, `attendance_id`, `user_id`, `organization_id`, `pause_reason`, `paused_at`, `resumed_at`, `created_at`) VALUES
(1, 12, 5, 2, 'taking poop', '2026-03-08 02:54:29', '2026-03-08 02:55:24', '2026-03-07 18:54:29'),
(2, 13, 4, 2, 'Lunch', '2026-03-08 02:59:03', '2026-03-08 03:00:04', '2026-03-07 18:59:03'),
(3, 14, 4, 2, 'Lunch', '2026-03-08 03:01:52', '2026-03-08 03:02:32', '2026-03-07 19:01:52'),
(4, 14, 4, 2, 'Lunch', '2026-03-08 03:02:41', '2026-03-08 03:02:58', '2026-03-07 19:02:41'),
(5, 14, 4, 2, 'Lunch', '2026-03-08 03:03:31', '2026-03-08 03:03:55', '2026-03-07 19:03:31'),
(6, 14, 4, 2, 'Lunch', '2026-03-08 03:04:13', '2026-03-08 03:06:05', '2026-03-07 19:04:13'),
(7, 14, 4, 2, 'Lunch', '2026-03-08 03:06:20', '2026-03-08 03:11:07', '2026-03-07 19:06:20'),
(8, 14, 4, 2, 'Lunch', '2026-03-08 03:11:16', '2026-03-08 03:11:28', '2026-03-07 19:11:16'),
(9, 14, 4, 2, 'Lunch', '2026-03-08 03:11:32', '2026-03-08 03:15:20', '2026-03-07 19:11:32'),
(10, 14, 4, 2, 'Going outside buying food', '2026-03-08 03:15:30', '2026-03-08 03:15:43', '2026-03-07 19:15:30'),
(11, 14, 4, 2, 'Emergency, going to the drug store to buy medicine', '2026-03-08 03:16:06', '2026-03-08 03:16:36', '2026-03-07 19:16:06'),
(12, 14, 4, 2, 'Going outside to buy medicine from the drugstore', '2026-03-08 03:17:19', '2026-03-08 03:17:56', '2026-03-07 19:17:19'),
(13, 15, 4, 2, 'Lunch', '2026-03-08 03:18:58', '2026-03-08 03:19:04', '2026-03-07 19:18:58'),
(14, 17, 4, 2, 'Lunch', '2026-03-08 03:19:49', '2026-03-08 03:20:20', '2026-03-07 19:19:49'),
(15, 17, 4, 2, 'Lunch', '2026-03-08 03:20:52', '2026-03-08 03:31:40', '2026-03-07 19:20:52'),
(16, 17, 4, 2, 'Lunch', '2026-03-08 03:31:44', '2026-03-08 03:46:42', '2026-03-07 19:31:44'),
(17, 18, 4, 2, 'Lunch', '2026-03-08 03:59:44', '2026-03-08 03:59:50', '2026-03-07 19:59:44'),
(18, 21, 4, 2, 'Lunch', '2026-03-08 04:04:03', '2026-03-08 04:04:11', '2026-03-07 20:04:03'),
(19, 27, 5, 2, 'Lunch', '2026-03-08 15:26:01', '2026-03-08 15:28:09', '2026-03-08 07:26:01'),
(20, 66, 5, 2, 'Lunch', '2026-03-09 04:07:44', '2026-03-09 04:10:23', '2026-03-08 20:07:44'),
(21, 66, 5, 2, 'Lunch', '2026-03-09 04:35:13', '2026-03-09 04:35:32', '2026-03-08 20:35:13'),
(22, 67, 5, 2, 'Malibang', '2026-03-10 01:50:49', '2026-03-10 01:51:09', '2026-03-09 17:50:49'),
(23, 67, 5, 2, 'Malibang', '2026-03-10 01:51:20', '2026-03-10 01:59:52', '2026-03-09 17:51:20'),
(24, 96, 5, 2, 'Lunch', '2026-03-12 03:35:21', '2026-03-12 03:35:24', '2026-03-11 19:35:21'),
(25, 102, 5, 2, 'Mental break', '2026-03-20 19:48:22', '2026-03-20 21:18:21', '2026-03-20 11:48:22'),
(26, 102, 5, 2, 'Malibang ko sir emergency kaayo', '2026-03-20 21:19:43', '2026-03-20 21:28:10', '2026-03-20 13:19:43');

-- --------------------------------------------------------

--
-- Table structure for table `bulletin_posts`
--

CREATE TABLE `bulletin_posts` (
  `id` int(11) NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'ann',
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bulletin_posts`
--

INSERT INTO `bulletin_posts` (`id`, `type`, `title`, `body`, `created_by`, `created_at`, `organization_id`) VALUES
(1, 'ann', 'Notice to all OJT\'s', 'We will have a meeting this Monday for observation', 3, '2026-03-20 12:40:08', 2),
(2, 'rem', 'Team Meeting this Friday', 'Friday ta mag meting ninyo guys, same time ta ha', 3, '2026-03-20 13:22:46', 2);

-- --------------------------------------------------------

--
-- Table structure for table `chats`
--

CREATE TABLE `chats` (
  `chat_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `opened` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chats`
--

INSERT INTO `chats` (`chat_id`, `sender_id`, `receiver_id`, `message`, `opened`, `created_at`, `organization_id`) VALUES
(1, 5, 3, 'Boss?', 1, '2026-03-07 18:08:15', 2),
(2, 5, 4, 'Sup bro?', 1, '2026-03-07 18:08:21', 2),
(3, 3, 5, 'hello', 1, '2026-03-20 13:30:29', 2),
(4, 3, 5, '✌🏻✌🏻✌🏻✌🏻', 1, '2026-03-21 11:02:00', 2),
(5, 3, 4, 'yow', 0, '2026-03-21 12:59:58', 2),
(6, 3, 4, 'sup', 0, '2026-03-21 13:00:09', 2),
(7, 3, 5, 'bro', 1, '2026-03-21 13:02:28', 2),
(8, 5, 3, 'yes sir', 1, '2026-03-21 13:02:56', 2),
(9, 3, 5, 'sir?', 1, '2026-03-21 17:10:15', 2),
(10, 3, 4, 'bro', 0, '2026-03-21 18:24:04', 2),
(11, 3, 5, 'bai', 1, '2026-03-21 18:24:13', 2);

-- --------------------------------------------------------

--
-- Table structure for table `chat_attachments`
--

CREATE TABLE `chat_attachments` (
  `attachment_id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `attachment_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_typing_statuses`
--

CREATE TABLE `chat_typing_statuses` (
  `id` int(11) NOT NULL,
  `chat_type` varchar(10) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL DEFAULT 0,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL,
  `organization_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(50) DEFAULT 'group',
  `task_id` int(11) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `groups`
--

INSERT INTO `groups` (`id`, `name`, `created_by`, `created_at`, `type`, `task_id`, `organization_id`) VALUES
(1, 'Task Management', 3, '2026-03-05 12:42:13', 'task_chat', 1, 2),
(2, 'LMS', 3, '2026-03-20 13:21:09', 'group', NULL, 2),
(3, 'LMS', 3, '2026-03-20 18:26:21', 'task_chat', 2, 2),
(4, 'SIMS', 3, '2026-03-20 18:29:48', 'task_chat', 3, 2),
(5, 'Solar pannels farm system', 3, '2026-03-20 18:31:28', 'task_chat', 4, 2);

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(20) DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`id`, `group_id`, `user_id`, `role`, `created_at`, `organization_id`) VALUES
(1, 1, 4, 'leader', '2026-03-05 12:42:13', 2),
(2, 1, 5, 'member', '2026-03-05 12:42:13', 2),
(3, 1, 3, 'member', '2026-03-05 12:42:13', 2),
(4, 2, 4, 'leader', '2026-03-20 13:21:09', 2),
(5, 2, 5, 'member', '2026-03-20 13:21:09', 2),
(6, 3, 4, 'leader', '2026-03-20 18:26:21', 2),
(7, 3, 5, 'member', '2026-03-20 18:26:21', 2),
(8, 3, 3, 'member', '2026-03-20 18:26:21', 2),
(9, 4, 4, 'leader', '2026-03-20 18:29:48', 2),
(10, 4, 5, 'member', '2026-03-20 18:29:48', 2),
(11, 4, 3, 'member', '2026-03-20 18:29:48', 2),
(12, 5, 4, 'leader', '2026-03-20 18:31:28', 2),
(13, 5, 5, 'member', '2026-03-20 18:31:28', 2),
(14, 5, 3, 'member', '2026-03-20 18:31:28', 2);

-- --------------------------------------------------------

--
-- Table structure for table `group_messages`
--

CREATE TABLE `group_messages` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `organization_id` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_messages`
--

INSERT INTO `group_messages` (`id`, `group_id`, `sender_id`, `message`, `created_at`, `organization_id`, `deleted_at`) VALUES
(1, 1, 3, 'hello', '2026-03-06 07:57:49', 2, NULL),
(2, 1, 5, 'HI', '2026-03-07 18:08:02', 2, NULL),
(3, 1, 5, 'Kumusta?', '2026-03-07 18:08:08', 2, NULL),
(4, 1, 3, 'uy', '2026-03-07 18:11:44', 2, NULL),
(5, 1, 4, 'Sir', '2026-03-07 19:29:30', 2, NULL),
(6, 1, 3, 'Kumusta sir?', '2026-03-20 18:32:06', 2, NULL),
(7, 5, 3, 'Hello guys', '2026-03-21 13:15:59', 2, NULL),
(8, 4, 3, 'Kumusta?', '2026-03-21 13:16:06', 2, NULL),
(9, 3, 3, 'Guys', '2026-03-21 13:16:11', 2, NULL),
(10, 5, 5, 'hello guyss!', '2026-03-21 13:32:47', 2, NULL),
(11, 5, 3, 'hello sir', '2026-03-21 13:32:56', 2, NULL),
(12, 5, 5, 'Kumusta naman guys?', '2026-03-21 13:33:13', 2, NULL),
(13, 5, 3, 'Okay ra sir', '2026-03-21 13:33:20', 2, NULL),
(14, 5, 3, 'Okay ra?', '2026-03-21 13:54:02', 2, NULL),
(15, 5, 5, 'maayo', '2026-03-21 14:03:46', 2, NULL),
(16, 5, 3, 'yes sir', '2026-03-21 14:04:41', 2, NULL),
(17, 5, 5, 'goods2', '2026-03-21 14:04:53', 2, NULL),
(18, 5, 3, 'gani sir', '2026-03-21 14:05:10', 2, NULL),
(19, 5, 5, 'unya ang project?', '2026-03-21 14:05:41', 2, NULL),
(20, 5, 3, 'okay na sir', '2026-03-21 14:23:26', 2, NULL),
(21, 5, 3, 'ready na sir', '2026-03-21 14:23:59', 2, NULL),
(22, 5, 5, 'goods2', '2026-03-21 14:25:20', 2, NULL),
(23, 5, 5, '', '2026-03-21 14:37:53', 2, NULL),
(24, 5, 5, '', '2026-03-21 14:38:11', 2, '2026-03-21 16:10:33'),
(25, 5, 5, '', '2026-03-21 14:38:24', 2, NULL),
(26, 5, 5, '', '2026-03-21 14:50:48', 2, NULL),
(27, 5, 5, 'goys', '2026-03-21 15:17:46', 2, NULL),
(28, 5, 3, 'sir kumusta man?', '2026-03-21 15:18:09', 2, NULL),
(29, 5, 5, 'okay ra goys', '2026-03-21 15:18:18', 2, NULL),
(30, 5, 5, 'sir', '2026-03-21 15:19:27', 2, NULL),
(31, 5, 3, 'kumusta man guys?', '2026-03-21 15:19:47', 2, NULL),
(32, 4, 5, 'Hello', '2026-03-21 15:23:03', 2, NULL),
(33, 4, 5, 'Kumusta guys? @Neljhan Pitallar Redondo  sir', '2026-03-21 15:23:18', 2, NULL),
(34, 4, 5, 'guys', '2026-03-21 15:24:00', 2, NULL),
(35, 4, 5, 'guys', '2026-03-21 15:24:03', 2, NULL),
(36, 4, 3, 'okay ra kaayo', '2026-03-21 15:24:21', 2, NULL),
(37, 4, 5, 'okay ragyud?', '2026-03-21 15:31:46', 2, NULL),
(38, 4, 3, 'yes sir okay ra', '2026-03-21 15:32:13', 2, '2026-03-21 16:33:05'),
(39, 4, 5, 'maayo', '2026-03-21 15:32:21', 2, '2026-03-21 16:32:57'),
(40, 4, 5, 'goods', '2026-03-21 15:33:15', 2, NULL),
(41, 4, 3, 'goys', '2026-03-21 18:24:19', 2, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `group_message_attachments`
--

CREATE TABLE `group_message_attachments` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `attachment_name` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_message_attachments`
--

INSERT INTO `group_message_attachments` (`id`, `message_id`, `attachment_name`, `created_at`) VALUES
(1, 23, 'group_chat_1774103873_0_5.jpg', '2026-03-21 14:37:53'),
(2, 25, 'group_chat_1774103904_0_5.jpg', '2026-03-21 14:38:24'),
(3, 26, 'group_chat_1774104648_0_5.pdf', '2026-03-21 14:50:48');

-- --------------------------------------------------------

--
-- Table structure for table `group_message_reads`
--

CREATE TABLE `group_message_reads` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_message_id` int(11) NOT NULL,
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_message_reads`
--

INSERT INTO `group_message_reads` (`id`, `group_id`, `user_id`, `last_message_id`, `organization_id`) VALUES
(1, 1, 3, 6, 2),
(2, 1, 5, 6, 2),
(3, 1, 4, 6, 2),
(4, 5, 3, 31, 2),
(5, 4, 3, 41, 2),
(6, 3, 3, 9, 2),
(7, 3, 5, 9, 2),
(8, 4, 5, 41, 2),
(9, 5, 5, 31, 2);

-- --------------------------------------------------------

--
-- Table structure for table `leader_feedback`
--

CREATE TABLE `leader_feedback` (
  `id` bigint(20) NOT NULL,
  `task_id` int(11) NOT NULL,
  `leader_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `rating` smallint(6) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `recipient` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `date` date DEFAULT (curdate()),
  `notified_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `task_id` int(11) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `message`, `recipient`, `type`, `date`, `notified_at`, `is_read`, `task_id`, `organization_id`) VALUES
(1, '\'Task Management\' has been assigned to you as leader. Please review and start working on it', 4, 'New Task Assigned', '2026-03-05', '2026-03-05 20:42:13', 1, 1, 2),
(2, '\'Task Management\' has been assigned to you. Please review and start working on it', 5, 'New Task Assigned', '2026-03-05', '2026-03-05 20:42:13', 1, 1, 2),
(3, '\'LMS\' has been assigned to you as leader. Please review and start working on it', 4, 'New Task Assigned', '2026-03-21', '2026-03-21 02:26:21', 1, 2, 2),
(4, '\'LMS\' has been assigned to you. Please review and start working on it', 5, 'New Task Assigned', '2026-03-21', '2026-03-21 02:26:21', 1, 2, 2),
(5, '\'SIMS\' has been assigned to you as leader. Please review and start working on it', 4, 'New Task Assigned', '2026-03-21', '2026-03-21 02:29:48', 1, 3, 2),
(6, '\'SIMS\' has been assigned to you. Please review and start working on it', 5, 'New Task Assigned', '2026-03-21', '2026-03-21 02:29:48', 1, 3, 2),
(7, '\'Solar pannels farm system\' has been assigned to you as leader. Please review and start working on it', 4, 'New Task Assigned', '2026-03-21', '2026-03-21 02:31:28', 1, 4, 2),
(8, '\'Solar pannels farm system\' has been assigned to you. Please review and start working on it', 5, 'New Task Assigned', '2026-03-21', '2026-03-21 02:31:28', 1, 4, 2);

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `billing_email` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `plan_code` varchar(40) NOT NULL DEFAULT 'trial',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `theme_primary` varchar(20) DEFAULT NULL,
  `theme_secondary` varchar(20) DEFAULT NULL,
  `theme_accent` varchar(20) DEFAULT NULL,
  `theme_mode` varchar(20) NOT NULL DEFAULT 'light'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `name`, `slug`, `billing_email`, `status`, `plan_code`, `created_at`, `updated_at`, `theme_primary`, `theme_secondary`, `theme_accent`, `theme_mode`) VALUES
(1, 'FireGuard', 'fireguard', 'fireguardcore@gmail.com', 'active', 'starter', '2026-02-27 05:14:01', '2026-03-16 16:36:49', '#334155', '#64748b', '#475569', 'light'),
(2, 'Nehemiah Solutions Corporation', 'nehemiah', 'rneljhan@gmail.com', 'active', 'starter', '2026-02-27 05:26:14', '2026-03-21 18:40:21', '#e11d48', '#f43f5e', '#fb7185', 'dark'),
(3, 'DNSC', 'dnsc', 'key.neyttt@gmail.com', 'active', 'starter', '2026-03-04 19:03:52', '2026-03-04 19:03:52', NULL, NULL, NULL, 'light');

-- --------------------------------------------------------

--
-- Table structure for table `organization_members`
--

CREATE TABLE `organization_members` (
  `id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organization_members`
--

INSERT INTO `organization_members` (`id`, `organization_id`, `user_id`, `role`, `created_at`) VALUES
(1, 1, 2, 'owner', '2026-02-27 05:14:01'),
(2, 2, 3, 'owner', '2026-02-27 05:26:14'),
(3, 2, 4, 'member', '2026-03-02 14:47:29'),
(4, 2, 5, 'member', '2026-03-02 14:48:18'),
(5, 3, 6, 'owner', '2026-03-04 19:03:52'),
(6, 1, 7, 'member', '2026-03-13 03:46:08');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_timeline_phases`
--

CREATE TABLE `project_timeline_phases` (
  `id` int(11) NOT NULL,
  `timeline_task_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(40) NOT NULL DEFAULT 'fa-circle',
  `color` varchar(7) NOT NULL DEFAULT '#6C3CE1',
  `start_day` int(11) NOT NULL DEFAULT 1,
  `duration_days` int(11) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_timeline_phases`
--

INSERT INTO `project_timeline_phases` (`id`, `timeline_task_id`, `name`, `description`, `icon`, `color`, `start_day`, `duration_days`, `sort_order`, `created_by`, `organization_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'Wireframe', 'Create wireframes for each page mockup', 'fa-search', '#10B981', 17, 1, 1, 3, 2, '2026-03-20 11:36:59', '2026-03-20 18:15:22'),
(2, 1, 'Coding', 'Code the UI', 'fa-code', '#0EA5E9', 12, 1, 2, 3, 2, '2026-03-20 11:37:41', '2026-03-20 18:17:50'),
(3, 2, 'Plan DB structure', 'create db structure/architecture', 'fa-database', '#8B5CF6', 1, 3, 1, 3, 2, '2026-03-20 16:12:54', '2026-03-20 16:12:54'),
(4, 3, 'Wireframe', 'Debug sync call', 'fa-circle', '#6C3CE1', 1, 13, 1, 3, 2, '2026-03-20 18:27:24', '2026-03-21 12:57:25');

-- --------------------------------------------------------

--
-- Table structure for table `project_timeline_tasks`
--

CREATE TABLE `project_timeline_tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `assignee_user_id` int(11) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_timeline_tasks`
--

INSERT INTO `project_timeline_tasks` (`id`, `project_id`, `title`, `assignee_user_id`, `sort_order`, `created_by`, `organization_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'UI design', 5, 1, 4, 2, '2026-03-07 19:18:21', '2026-03-07 19:18:21'),
(2, 1, 'Backend', 4, 2, 3, 2, '2026-03-20 16:11:21', '2026-03-20 16:11:21'),
(3, 2, 'UI design', 5, 1, 3, 2, '2026-03-20 18:26:52', '2026-03-20 18:26:52'),
(4, 3, 'UI design', 5, 1, 3, 2, '2026-03-20 18:30:18', '2026-03-20 18:30:18');

-- --------------------------------------------------------

--
-- Table structure for table `screenshots`
--

CREATE TABLE `screenshots` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `attendance_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `taken_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `screenshots`
--

INSERT INTO `screenshots` (`id`, `user_id`, `attendance_id`, `image_path`, `taken_at`, `organization_id`) VALUES
(85, 7, 98, 'screenshots/7_98_1773373694_69b388fec8ce2.png', '2026-03-13 03:48:14', 1),
(86, 7, 99, 'screenshots/7_99_1773374441_69b38be942e35.png', '2026-03-13 04:00:41', 1),
(87, 7, 99, 'screenshots/7_99_1773374550_69b38c562f001.png', '2026-03-13 04:02:30', 1),
(88, 7, 100, 'screenshots/7_100_1773375615_69b3907f1f49a.png', '2026-03-13 04:20:15', 1),
(89, 7, 101, 'screenshots/7_101_1773380773_69b3a4a525b8f.png', '2026-03-13 05:46:13', 1),
(90, 7, 101, 'screenshots/7_101_1773382101_69b3a9d53e2b8.png', '2026-03-13 06:08:21', 1),
(91, 7, 101, 'screenshots/7_101_1773383727_69b3b02f5294a.png', '2026-03-13 06:35:27', 1),
(92, 7, 101, 'screenshots/7_101_1773385444_69b3b6e42ea55.png', '2026-03-13 07:04:04', 1),
(93, 7, 101, 'screenshots/7_101_1773387251_69b3bdf338e52.png', '2026-03-13 07:34:12', 1),
(94, 5, 102, 'screenshots/5_102_1774007076_69bd3324c7b34.png', '2026-03-20 11:44:36', 2),
(95, 5, 102, 'screenshots/5_102_1774012702_69bd491eea7d9.png', '2026-03-20 13:18:23', 2),
(96, 5, 102, 'screenshots/5_102_1774013291_69bd4b6b00563.png', '2026-03-20 13:28:11', 2),
(97, 5, 103, 'screenshots/5_103_1774013886_69bd4dbeef2c4.png', '2026-03-20 13:38:07', 2),
(98, 5, 104, 'screenshots/5_104_1774075808_69be3fa003918.png', '2026-03-21 06:50:08', 2),
(99, 5, 104, 'screenshots/5_104_1774077090_69be44a289c5c.png', '2026-03-21 07:11:30', 2),
(100, 5, 104, 'screenshots/5_104_1774078411_69be49cb079f3.png', '2026-03-21 07:33:31', 2),
(101, 5, 105, 'screenshots/5_105_1774088643_69be71c3999c7.png', '2026-03-21 10:24:03', 2),
(102, 5, 105, 'screenshots/5_105_1774090242_69be7802cc52d.png', '2026-03-21 10:50:42', 2),
(103, 4, 106, 'screenshots/4_106_1774091257_69be7bf9aeeb9.png', '2026-03-21 11:07:37', 2),
(104, 4, 106, 'screenshots/4_106_1774091860_69be7e5449928.png', '2026-03-21 11:17:40', 2),
(105, 5, 108, 'screenshots/5_108_1774107819_69bebcab6850c.png', '2026-03-21 15:43:39', 2),
(106, 5, 108, 'screenshots/5_108_1774108753_69bec0519d598.png', '2026-03-21 15:59:13', 2);

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `provider` varchar(30) NOT NULL DEFAULT 'manual',
  `provider_subscription_id` varchar(120) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'trialing',
  `seat_limit` int(11) NOT NULL DEFAULT 3,
  `trial_ends_at` datetime DEFAULT NULL,
  `current_period_end` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `organization_id`, `provider`, `provider_subscription_id`, `status`, `seat_limit`, `trial_ends_at`, `current_period_end`, `created_at`, `updated_at`) VALUES
(1, 1, 'dummy', 'dummy-signup-1-1772169250', 'active', 10, '2026-03-01 06:14:01', '2026-04-27 06:14:01', '2026-02-27 05:14:01', '2026-03-04 18:59:59'),
(2, 2, 'dummy', 'dummy-2-1774013538', 'active', 10, '2026-03-01 06:26:14', '2026-05-27 06:26:14', '2026-02-27 05:26:14', '2026-03-20 13:32:18'),
(3, 3, 'manual', NULL, 'trialing', 10, '2026-03-06 20:03:52', '2026-04-04 20:03:52', '2026-03-04 19:03:52', '2026-03-04 19:03:52');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions_backup_trial_20260226`
--

CREATE TABLE `subscriptions_backup_trial_20260226` (
  `id` int(11) NOT NULL DEFAULT 0,
  `organization_id` int(11) NOT NULL,
  `provider` varchar(30) NOT NULL DEFAULT 'manual',
  `provider_subscription_id` varchar(120) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'trialing',
  `seat_limit` int(11) NOT NULL DEFAULT 3,
  `trial_ends_at` datetime DEFAULT NULL,
  `current_period_end` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions_backup_trial_20260226`
--

INSERT INTO `subscriptions_backup_trial_20260226` (`id`, `organization_id`, `provider`, `provider_subscription_id`, `status`, `seat_limit`, `trial_ends_at`, `current_period_end`, `created_at`, `updated_at`) VALUES
(1, 1, 'manual', NULL, 'trialing', 20, '2026-03-06 18:52:37', '2026-03-20 18:52:37', '2026-02-20 17:52:37', '2026-02-24 15:47:42'),
(2, 2, 'manual', NULL, 'trialing', 10, '2026-03-07 17:22:05', '2026-03-21 17:22:05', '2026-02-21 16:22:05', '2026-02-21 16:22:05'),
(3, 3, 'manual', NULL, 'trialing', 10, '2026-03-08 14:40:48', '2026-03-22 14:40:48', '2026-02-22 13:40:48', '2026-02-22 13:40:48'),
(4, 4, 'manual', NULL, 'trialing', 10, '2026-03-10 16:32:23', '2026-03-24 16:32:23', '2026-02-24 15:32:23', '2026-02-24 15:32:23'),
(5, 5, 'manual', NULL, 'trialing', 10, '2026-03-10 16:41:47', '2026-03-24 16:41:47', '2026-02-24 15:41:47', '2026-02-24 16:00:06');

-- --------------------------------------------------------

--
-- Table structure for table `subtasks`
--

CREATE TABLE `subtasks` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `due_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `submission_file` varchar(255) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submission_note` text DEFAULT NULL,
  `score` smallint(6) DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `timeline_phase_id` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subtasks`
--

INSERT INTO `subtasks` (`id`, `task_id`, `member_id`, `description`, `due_date`, `status`, `submission_file`, `feedback`, `created_at`, `updated_at`, `submission_note`, `score`, `organization_id`, `timeline_phase_id`, `reviewed_by`, `reviewed_at`) VALUES
(1, 1, 5, 'Wireframe', '2026-03-21', 'pending', NULL, NULL, '2026-03-21 07:00:24', '2026-03-21 15:31:07', NULL, NULL, 2, 1, NULL, NULL),
(2, 1, 5, 'Coding', '2026-03-16', 'pending', NULL, NULL, '2026-03-21 07:00:24', '2026-03-21 15:31:07', NULL, NULL, 2, 2, NULL, NULL),
(3, 2, 5, 'Wireframe', '2026-04-02', 'pending', NULL, NULL, '2026-03-21 07:03:26', '2026-03-21 15:31:07', NULL, NULL, 2, 4, NULL, NULL),
(5, 1, 4, 'Plan DB structure', '2026-03-07', 'pending', NULL, NULL, '2026-03-21 07:09:52', '2026-03-21 15:31:07', NULL, NULL, 2, 3, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `submission_file` varchar(255) DEFAULT NULL,
  `template_file` varchar(255) DEFAULT NULL,
  `review_comment` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `due_date` date NOT NULL,
  `submission_note` text DEFAULT NULL,
  `rating` int(11) DEFAULT 0,
  `leader_rating` smallint(6) DEFAULT NULL,
  `leader_review_comment` text DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `assigned_to`, `status`, `submission_file`, `template_file`, `review_comment`, `reviewed_by`, `reviewed_at`, `created_at`, `due_date`, `submission_note`, `rating`, `leader_rating`, `leader_review_comment`, `organization_id`) VALUES
(1, 'Task Management', 'Task management with the following:\r\nmake sure to follow the instructions, For leaders, manage your group accordingly, be responsible', 4, 'pending', NULL, 'uploads/template_1772714533_DNSC-Your-Last-Name_Weeks_1-4_Team_Lead_Eval (1).docx', NULL, NULL, '2026-03-05 12:42:13', '2026-03-05 12:42:13', '2026-03-21', NULL, 0, NULL, NULL, 2),
(2, 'LMS', 'Learning management System', 4, 'pending', NULL, 'uploads/template_1774031181_bulk-invite-template.xlsx', NULL, NULL, '2026-03-20 18:26:21', '2026-03-20 18:26:21', '2026-04-21', NULL, 0, NULL, NULL, 2),
(3, 'SIMS', 'Integration for the LMS software', 4, 'pending', NULL, 'uploads/template_1774031388_REDONDO CV.pdf', NULL, NULL, '2026-03-20 18:29:48', '2026-03-20 18:29:48', '2026-06-21', NULL, 0, NULL, NULL, 2),
(4, 'Solar pannels farm system', 'Solar pannel farm system', 4, 'pending', NULL, 'uploads/template_1774031487_Reports _ TaskFlow-2.pdf', NULL, NULL, '2026-03-20 18:31:27', '2026-03-20 18:31:27', '2026-08-21', NULL, 0, NULL, NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `task_assignees`
--

CREATE TABLE `task_assignees` (
  `id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'member',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `performance_rating` smallint(6) DEFAULT NULL,
  `rating_comment` text DEFAULT NULL,
  `rated_by` int(11) DEFAULT NULL,
  `rated_at` timestamp NULL DEFAULT NULL,
  `organization_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_assignees`
--

INSERT INTO `task_assignees` (`id`, `task_id`, `user_id`, `role`, `assigned_at`, `performance_rating`, `rating_comment`, `rated_by`, `rated_at`, `organization_id`) VALUES
(1, 1, 4, 'leader', '2026-03-05 12:42:13', NULL, NULL, NULL, NULL, 2),
(2, 1, 5, 'member', '2026-03-05 12:42:13', NULL, NULL, NULL, NULL, 2),
(3, 2, 4, 'leader', '2026-03-20 18:26:21', NULL, NULL, NULL, NULL, 2),
(4, 2, 5, 'member', '2026-03-20 18:26:21', NULL, NULL, NULL, NULL, 2),
(5, 3, 4, 'leader', '2026-03-20 18:29:48', NULL, NULL, NULL, NULL, 2),
(6, 3, 5, 'member', '2026-03-20 18:29:48', NULL, NULL, NULL, NULL, 2),
(7, 4, 4, 'leader', '2026-03-20 18:31:27', NULL, NULL, NULL, NULL, 2),
(8, 4, 5, 'member', '2026-03-20 18:31:27', NULL, NULL, NULL, NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_active_at` datetime DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT 'default.png',
  `must_change_password` tinyint(1) DEFAULT 0,
  `bio` text DEFAULT NULL,
  `organization_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password`, `role`, `created_at`, `last_active_at`, `phone`, `address`, `skills`, `profile_image`, `must_change_password`, `bio`, `organization_id`) VALUES
(1, 'Administrator', 'admin', '$2y$10$exUBIWSVjASnXQ3k40r3eeGu3YI0LEZGW0HD4fvAws0y7tH1orRM6', 'admin', '2026-02-27 05:13:20', NULL, NULL, NULL, NULL, 'default.png', 0, NULL, 0),
(2, 'Fire Guard', 'fireguardcore@gmail.com', '$2y$10$8w5Qb1W1KPq84HS3Plh0H.y4dhWX3vavJSsWUzBrH.qaSj9MoGLd.', 'admin', '2026-02-27 05:14:01', NULL, NULL, NULL, NULL, 'default.png', 0, NULL, 1),
(3, 'Neljhan Pitallar Redondo', 'rneljhan@gmail.com', '$2y$10$H73eGo1867oqy/A8hNGZ4O0fXbZeu2NJK5w0an.3GT3Wq1TVFYmRO', 'admin', '2026-02-27 05:26:14', '2026-03-22 03:00:56', '', '', '', 'IMG-69a97a5c7dcfb1.98706663.png', 0, '', 2),
(4, 'sogola nagood', 'sogolanagood0@gmail.com', '$2y$10$mouOztiiqymvuUvWu9syxu7xqgjtKXtLKBsaKn87ICSrlUXRqCyJK', 'employee', '2026-03-02 14:47:29', NULL, '', '', '', 'IMG-69a97ac7c4b530.16868965.jpg', 0, '', 2),
(5, 'neljhan redondo', 'redondo.neljhan@dnsc.edu.ph', '$2y$10$CNUVLSa9yKzWy5a9sqj.EOW9uLZVOvAtKcLmpZyABWth.jaCx1ODi', 'employee', '2026-03-02 14:48:18', NULL, '', '', '', 'IMG-69a97a952c3502.72775713.png', 0, '', 2),
(6, 'Keyneth Malumbaga', 'key.neyttt@gmail.com', '$2y$10$ABMHdWd1CPPSHXiNSiuCNe2Q8Xt7og.eCuIDlxWUSxQtO.gG2tNBW', 'admin', '2026-03-04 19:03:52', NULL, NULL, NULL, NULL, 'default.png', 0, NULL, 3),
(7, 'Jane Doe', 'redondorosemariebiz@gmail.com', '$2y$10$.xHxgmuoKqioFA4HOKSrWuXx5pa1UsOS.IHKlAuPK3ifuWV4zNY1C', 'employee', '2026-03-13 03:46:08', NULL, NULL, NULL, NULL, 'default.png', 0, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_login_verifications`
--

CREATE TABLE `user_login_verifications` (
  `user_id` int(11) NOT NULL,
  `code_hash` varchar(255) DEFAULT NULL,
  `code_expires_at` datetime DEFAULT NULL,
  `last_code_sent_at` datetime DEFAULT NULL,
  `verify_attempts` int(11) NOT NULL DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_login_verifications`
--

INSERT INTO `user_login_verifications` (`user_id`, `code_hash`, `code_expires_at`, `last_code_sent_at`, `verify_attempts`, `verified_at`, `created_at`, `updated_at`) VALUES
(2, NULL, NULL, '2026-02-27 13:14:26', 0, '2026-02-27 13:15:05', '2026-02-27 05:14:01', '2026-02-27 05:15:05'),
(3, NULL, NULL, '2026-02-27 13:26:30', 0, '2026-02-27 13:27:04', '2026-02-27 05:26:14', '2026-02-27 05:27:04'),
(6, NULL, NULL, '2026-03-05 03:05:10', 0, '2026-03-05 03:05:28', '2026-03-04 19:03:52', '2026-03-04 19:05:28'),
(9, NULL, NULL, '2026-02-24 23:34:06', 0, '2026-02-24 23:35:07', '2026-02-24 15:32:23', '2026-02-24 15:35:07'),
(10, NULL, NULL, '2026-02-24 23:42:01', 0, '2026-02-24 23:43:06', '2026-02-24 15:41:47', '2026-02-24 15:43:06'),
(11, NULL, NULL, '2026-02-27 12:59:52', 0, '2026-02-27 13:00:33', '2026-02-27 04:59:39', '2026-02-27 05:00:33');

-- --------------------------------------------------------

--
-- Table structure for table `workspace_invites`
--

CREATE TABLE `workspace_invites` (
  `id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `invited_by` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `full_name` varchar(120) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'employee',
  `token` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `accepted_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workspace_invites`
--

INSERT INTO `workspace_invites` (`id`, `organization_id`, `invited_by`, `email`, `full_name`, `role`, `token`, `status`, `expires_at`, `accepted_at`, `accepted_user_id`, `created_at`, `updated_at`) VALUES
(1, 2, 14, 'sogolanagood0@gmail.com', 'So Gool', 'employee', '68f82345ae4dfba6bbe2a04437c12fc154da6284d1b7a0030815813fdf0e5ab1', 'accepted', '2026-02-22 21:15:18', '2026-02-16 04:16:07', 16, '2026-02-15 20:15:18', '2026-02-15 20:16:07'),
(3, 4, 18, 'redondorosemariebiz@gmail.com', 'Biz ness', 'employee', 'f745fe9da894899c6255b808bbb2cd9582e9408c0ad35bb7872e074bf7940abc', 'accepted', '2026-02-23 12:42:51', '2026-02-16 21:01:08', 19, '2026-02-16 11:42:51', '2026-02-16 13:01:08'),
(4, 1, 8, 'espano.sherwin@dnsc.edu.ph', 'Sherwin Españo', 'employee', '77d865f1e8d24a42931c3cdfa9c4e76c5b0c0870e9b22eb94aef467af4c0dd62', 'revoked', '2026-02-24 16:22:18', NULL, NULL, '2026-02-17 15:22:18', '2026-02-20 18:00:25'),
(5, 2, 14, 'espano.sherwin@dnsc.edu.ph', 'Sherwin Españo', 'employee', 'c1e954e7b9faa07d1cf8c2a3283f069bb8161e38f9f4d1d71cae06ea80cf3327', 'expired', '2026-02-24 16:24:04', NULL, NULL, '2026-02-17 15:24:04', '2026-03-02 14:39:49'),
(6, 12, 27, 'malumbagakenneth16@gmail.com', 'bryan malumbaga', 'employee', '844ecdec260e04291345f4afa96faca15a6542819ec432b031fe137d5e9affc5', 'revoked', '2026-02-26 18:52:13', NULL, NULL, '2026-02-19 17:52:13', '2026-02-19 18:04:10'),
(7, 12, 27, 'torrecampomaryzhane@gmail.com', 'zhane torrecampo', 'employee', 'd8f56634a737bdf055f1b146f6fc2d394ff9710d6d1fd67e80e945fbbfaece1e', 'revoked', '2026-02-26 18:52:18', NULL, NULL, '2026-02-19 17:52:18', '2026-02-19 18:04:07'),
(8, 12, 27, 'malumbagakenneth16@gmail.com', 'bryan malumbaga', 'employee', 'c6958998df3f1c0c32a11f80c3c6df67d0f759bee0ed582fd87d30c49cb6446e', 'accepted', '2026-02-26 19:04:18', '2026-02-20 02:05:09', 28, '2026-02-19 18:04:18', '2026-02-19 18:05:09'),
(9, 12, 27, 'torrecampomaryzhane@gmail.com', 'zhane torrecampo', 'employee', '014d8613ec1b9218991de09a69c9c01d6db95e887d7765feda80a7107c59d16c', 'accepted', '2026-02-26 19:04:23', '2026-02-20 02:06:28', 29, '2026-02-19 18:04:23', '2026-02-19 18:06:28'),
(10, 1, 2, 'malumbagakenneth16@gmail.com', 'bryan malumbaga', 'employee', '2e4c6e54f920eb7ab3c10d1e7175a030f6cf8659da4d86ad554f08b6ab524d54', 'accepted', '2026-02-27 18:56:15', '2026-02-21 01:58:44', 4, '2026-02-20 17:56:15', '2026-02-20 17:58:44'),
(11, 1, 2, 'torrecampomaryzhane@gmail.com', 'zhane torrecampo', 'employee', 'ec8e053d9603665b40d54a8090ca11bc4788da60840e258d98d99dbd54acd5e8', 'accepted', '2026-02-27 18:56:21', '2026-02-21 01:58:24', 3, '2026-02-20 17:56:21', '2026-02-20 17:58:24'),
(13, 1, 2, 'key.neyttt@gmail.com', 'Key Neyt', 'employee', '528ef43acf80fbb11090dc1782c01e03ffacb896db593ee08ad1fb3757fa0287', 'accepted', '2026-02-27 19:01:09', '2026-02-21 02:01:50', 5, '2026-02-20 18:01:09', '2026-02-20 18:01:50'),
(14, 1, 2, 'maling.kenshie@dnsc.edu.ph', 'kenshie maling', 'employee', 'ca70958488ccd99b70f34d9f82fd035338866203c954e809076b4882e4bdb2d7', 'accepted', '2026-02-27 19:02:00', '2026-02-21 02:02:26', 6, '2026-02-20 18:02:00', '2026-02-20 18:02:26'),
(15, 1, 2, '__open_link__+9abfde4564482ff911386e890702e1d6fc64e99bd82eb3e0a6125be2d93e3466@join.taskflow.local', NULL, 'employee', '9abfde4564482ff911386e890702e1d6fc64e99bd82eb3e0a6125be2d93e3466', 'expired', '2026-03-05 19:43:35', NULL, NULL, '2026-02-26 18:43:35', '2026-03-12 19:33:44'),
(16, 2, 3, 'sogolanagood0@gmail.com', 'sogola nagood', 'employee', '302eef99319916bf34dda83a5e67dca66eaa4ea65cd8a90f702760cc0ea6d147', 'accepted', '2026-03-09 15:43:40', '2026-03-02 22:47:29', 4, '2026-03-02 14:43:40', '2026-03-02 14:47:29'),
(42, 2, 3, 'jane.doe@gmail.com', 'Jane Doe', 'employee', '892b3f776f5d46e8757442088dda22ebaa308b313172949b88e1a848fb4d2784', 'expired', '2026-03-14 15:08:32', NULL, NULL, '2026-03-07 14:08:32', '2026-03-16 12:26:42'),
(43, 2, 3, 'john.smith@gmail.com', 'John Smith', 'employee', 'b3599728f10057e01635f5500fd23b93ff54fc65a0a9ef1e18078e62e74625ff', 'expired', '2026-03-14 15:08:36', NULL, NULL, '2026-03-07 14:08:36', '2026-03-16 12:26:42'),
(44, 2, 3, 'maria.garcia@gmail.com', 'Maria Garcia', 'employee', 'bc774ebdb7a2b2cfb9df00de290bd1093ddc703b2b0e0e433897d11bfe1f97c1', 'expired', '2026-03-14 15:08:41', NULL, NULL, '2026-03-07 14:08:41', '2026-03-16 12:26:42'),
(45, 1, 2, 'redondorosemariebiz@gmail.com', 'Jane Doe', 'employee', 'a828d90afc4d17f2b000032d062e3f1698530503b19bd44d9e0ee7aaf3e3a8f8', 'accepted', '2026-03-20 04:45:30', '2026-03-13 11:46:08', 7, '2026-03-13 03:45:30', '2026-03-13 03:46:08'),
(46, 2, 3, '__open_link__+7e705fac8972e26fe2ca28d9b40484f798302f038e454d57d83e958eaba6d06b@join.taskflow.local', NULL, 'employee', '7e705fac8972e26fe2ca28d9b40484f798302f038e454d57d83e958eaba6d06b', 'pending', '2026-03-23 16:10:18', NULL, NULL, '2026-03-16 15:10:18', '2026-03-16 15:10:18'),
(47, 2, 3, '__open_link__+7851343f5640c1526e28b7f53cbae514f7eb8ee6baba6f014eafc8725d33d504@join.taskflow.local', NULL, 'employee', '7851343f5640c1526e28b7f53cbae514f7eb8ee6baba6f014eafc8725d33d504', 'revoked', '2026-03-27 14:09:12', NULL, NULL, '2026-03-20 13:09:12', '2026-03-20 13:32:08'),
(48, 2, 3, '__open_link__+17288c43be99476634e275c01b6f1f1268ff18bc0f4e28a398614e553279994d@join.taskflow.local', NULL, 'employee', '17288c43be99476634e275c01b6f1f1268ff18bc0f4e28a398614e553279994d', 'revoked', '2026-03-27 14:32:01', NULL, NULL, '2026-03-20 13:32:01', '2026-03-20 15:31:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_user_id_fkey` (`user_id`),
  ADD KEY `idx_attendance_org_id` (`organization_id`);

--
-- Indexes for table `attendance_adjustments`
--
ALTER TABLE `attendance_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_att_adj_user_date` (`user_id`,`att_date`),
  ADD KEY `idx_att_adj_org` (`organization_id`);

--
-- Indexes for table `attendance_pauses`
--
ALTER TABLE `attendance_pauses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attendance_pauses_attendance_id` (`attendance_id`),
  ADD KEY `idx_attendance_pauses_user_id` (`user_id`),
  ADD KEY `idx_attendance_pauses_open` (`attendance_id`,`resumed_at`);

--
-- Indexes for table `bulletin_posts`
--
ALTER TABLE `bulletin_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bulletin_posts_created_at` (`created_at`),
  ADD KEY `idx_bulletin_posts_org` (`organization_id`);

--
-- Indexes for table `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`chat_id`),
  ADD KEY `idx_chats_org_id` (`organization_id`);

--
-- Indexes for table `chat_attachments`
--
ALTER TABLE `chat_attachments`
  ADD PRIMARY KEY (`attachment_id`),
  ADD KEY `chat_attachments_chat_id_fkey` (`chat_id`);

--
-- Indexes for table `chat_typing_statuses`
--
ALTER TABLE `chat_typing_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_chat_typing_direct` (`chat_type`,`sender_id`,`receiver_id`,`group_id`,`organization_id`),
  ADD KEY `idx_chat_typing_direct_lookup` (`chat_type`,`receiver_id`,`updated_at`),
  ADD KEY `idx_chat_typing_group_lookup` (`chat_type`,`group_id`,`updated_at`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_groups_task_chat_task_id` (`type`,`task_id`),
  ADD KEY `groups_task_id_fkey` (`task_id`),
  ADD KEY `idx_groups_org_id` (`organization_id`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_members_group_user_key` (`group_id`,`user_id`),
  ADD KEY `fk_group_members_user` (`user_id`),
  ADD KEY `idx_group_members_org_id` (`organization_id`);

--
-- Indexes for table `group_messages`
--
ALTER TABLE `group_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_group_messages_group` (`group_id`),
  ADD KEY `fk_group_messages_sender` (`sender_id`),
  ADD KEY `idx_group_messages_org_id` (`organization_id`);

--
-- Indexes for table `group_message_attachments`
--
ALTER TABLE `group_message_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_group_msg_attach_msg` (`message_id`);

--
-- Indexes for table `group_message_reads`
--
ALTER TABLE `group_message_reads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_message_reads_group_id_fkey` (`group_id`),
  ADD KEY `group_message_reads_user_id_fkey` (`user_id`),
  ADD KEY `idx_group_message_reads_org_id` (`organization_id`);

--
-- Indexes for table `leader_feedback`
--
ALTER TABLE `leader_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `leader_feedback_unique` (`task_id`,`leader_id`,`member_id`),
  ADD KEY `idx_leader_feedback_org_id` (`organization_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_recipient_fkey` (`recipient`),
  ADD KEY `notifications_task_id_fkey` (`task_id`),
  ADD KEY `idx_notifications_org_id` (`organization_id`),
  ADD KEY `idx_notifications_recipient_notified_at` (`recipient`,`notified_at`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `organizations_slug_key` (`slug`);

--
-- Indexes for table `organization_members`
--
ALTER TABLE `organization_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `organization_members_org_user_key` (`organization_id`,`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_password_resets_org_id` (`organization_id`);

--
-- Indexes for table `project_timeline_phases`
--
ALTER TABLE `project_timeline_phases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_timeline_phases_task` (`timeline_task_id`),
  ADD KEY `idx_project_timeline_phases_org_task` (`organization_id`,`timeline_task_id`),
  ADD KEY `fk_project_timeline_phases_created_by` (`created_by`);

--
-- Indexes for table `project_timeline_tasks`
--
ALTER TABLE `project_timeline_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_timeline_tasks_project` (`project_id`),
  ADD KEY `idx_project_timeline_tasks_org_project` (`organization_id`,`project_id`),
  ADD KEY `fk_project_timeline_tasks_assignee` (`assignee_user_id`),
  ADD KEY `fk_project_timeline_tasks_created_by` (`created_by`);

--
-- Indexes for table `screenshots`
--
ALTER TABLE `screenshots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `screenshots_attendance_id_fkey` (`attendance_id`),
  ADD KEY `screenshots_user_id_fkey` (`user_id`),
  ADD KEY `idx_screenshots_org_id` (`organization_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subscriptions_org_key` (`organization_id`);

--
-- Indexes for table `subtasks`
--
ALTER TABLE `subtasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subtasks_member_id` (`member_id`),
  ADD KEY `idx_subtasks_task_id` (`task_id`),
  ADD KEY `idx_subtasks_org_id` (`organization_id`),
  ADD KEY `idx_subtasks_timeline_phase_id` (`timeline_phase_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_assigned_to_fkey` (`assigned_to`),
  ADD KEY `tasks_reviewed_by_fkey` (`reviewed_by`),
  ADD KEY `idx_tasks_org_id` (`organization_id`);

--
-- Indexes for table `task_assignees`
--
ALTER TABLE `task_assignees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `task_assignees_task_id_user_id_key` (`task_id`,`user_id`),
  ADD KEY `task_assignees_user_id_fkey` (`user_id`),
  ADD KEY `idx_task_assignees_org_id` (`organization_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_username_key` (`username`),
  ADD KEY `idx_users_org_id` (`organization_id`);

--
-- Indexes for table `user_login_verifications`
--
ALTER TABLE `user_login_verifications`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `workspace_invites`
--
ALTER TABLE `workspace_invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `workspace_invites_token_key` (`token`),
  ADD KEY `workspace_invites_invited_by_fk` (`invited_by`),
  ADD KEY `workspace_invites_accepted_user_fk` (`accepted_user_id`),
  ADD KEY `idx_workspace_invites_org_status` (`organization_id`,`status`),
  ADD KEY `idx_workspace_invites_email_status` (`email`,`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `attendance_adjustments`
--
ALTER TABLE `attendance_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance_pauses`
--
ALTER TABLE `attendance_pauses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `bulletin_posts`
--
ALTER TABLE `bulletin_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `chats`
--
ALTER TABLE `chats`
  MODIFY `chat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `chat_attachments`
--
ALTER TABLE `chat_attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_typing_statuses`
--
ALTER TABLE `chat_typing_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `group_messages`
--
ALTER TABLE `group_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `group_message_attachments`
--
ALTER TABLE `group_message_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `group_message_reads`
--
ALTER TABLE `group_message_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `leader_feedback`
--
ALTER TABLE `leader_feedback`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `organization_members`
--
ALTER TABLE `organization_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_timeline_phases`
--
ALTER TABLE `project_timeline_phases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project_timeline_tasks`
--
ALTER TABLE `project_timeline_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `screenshots`
--
ALTER TABLE `screenshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subtasks`
--
ALTER TABLE `subtasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `task_assignees`
--
ALTER TABLE `task_assignees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `workspace_invites`
--
ALTER TABLE `workspace_invites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `chat_attachments`
--
ALTER TABLE `chat_attachments`
  ADD CONSTRAINT `chat_attachments_chat_id_fkey` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`chat_id`) ON DELETE CASCADE;

--
-- Constraints for table `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `groups_task_id_fkey` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `fk_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_messages`
--
ALTER TABLE `group_messages`
  ADD CONSTRAINT `fk_group_messages_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_message_attachments`
--
ALTER TABLE `group_message_attachments`
  ADD CONSTRAINT `fk_group_msg_attach_msg` FOREIGN KEY (`message_id`) REFERENCES `group_messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_message_reads`
--
ALTER TABLE `group_message_reads`
  ADD CONSTRAINT `group_message_reads_group_id_fkey` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_message_reads_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_recipient_fkey` FOREIGN KEY (`recipient`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notifications_task_id_fkey` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_timeline_phases`
--
ALTER TABLE `project_timeline_phases`
  ADD CONSTRAINT `fk_project_timeline_phases_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_project_timeline_phases_task` FOREIGN KEY (`timeline_task_id`) REFERENCES `project_timeline_tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_timeline_tasks`
--
ALTER TABLE `project_timeline_tasks`
  ADD CONSTRAINT `fk_project_timeline_tasks_assignee` FOREIGN KEY (`assignee_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_project_timeline_tasks_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_project_timeline_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `screenshots`
--
ALTER TABLE `screenshots`
  ADD CONSTRAINT `screenshots_attendance_id_fkey` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`),
  ADD CONSTRAINT `screenshots_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `subtasks`
--
ALTER TABLE `subtasks`
  ADD CONSTRAINT `subtasks_member_id_fkey` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subtasks_task_id_fkey` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_assigned_to_fkey` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tasks_reviewed_by_fkey` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_assignees`
--
ALTER TABLE `task_assignees`
  ADD CONSTRAINT `task_assignees_task_id_fkey` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_assignees_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
