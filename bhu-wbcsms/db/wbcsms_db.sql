-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 24, 2026 at 07:06 PM
-- Server version: 10.1.38-MariaDB
-- PHP Version: 7.3.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wbcsms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `ac_year_id` int(11) NOT NULL,
  `year_code` varchar(20) NOT NULL,
  `year_name` varchar(50) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT '0',
  `status` enum('active','closed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`ac_year_id`, `year_code`, `year_name`, `start_date`, `end_date`, `is_current`, `status`, `created_at`) VALUES
(1, '2024-25', 'Academic Year 2024/2025', '2024-09-01', '2025-08-31', 1, 'active', '2026-08-04 18:28:34'),
(2, '2025-26', 'ACY 2025/26', '2025-09-01', '2026-09-30', 0, 'active', '2026-08-04 21:33:14');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `module` varchar(50) DEFAULT NULL,
  `status` enum('success','failure') DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action_type`, `action_description`, `ip_address`, `user_agent`, `module`, `status`, `created_at`) VALUES
(1, 1, 'LOGIN', 'User admin logged in', NULL, NULL, NULL, 'success', '2026-08-04 19:06:22'),
(2, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:06:22'),
(3, 1, 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:14:33'),
(4, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:14:52'),
(5, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:40:30'),
(6, 1, 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:40:30'),
(7, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:41:39'),
(8, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:41:44'),
(9, 1, 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:41:44'),
(10, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:42:09'),
(11, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:44:20'),
(12, 1, 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:44:20'),
(13, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:44:41'),
(14, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:44:43'),
(15, 1, 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:44:43'),
(16, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:47:27'),
(17, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:47:31'),
(18, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:48:28'),
(19, 1, 'UPDATE_SETTINGS', 'System settings updated', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 19:56:47'),
(20, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 20:13:40'),
(21, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 20:13:56'),
(22, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 20:26:04'),
(23, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 20:26:23'),
(24, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 21:04:25'),
(25, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 21:06:04'),
(26, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 21:07:11'),
(27, 2, 'LOGIN', 'User abdi logged in', NULL, NULL, NULL, 'success', '2026-08-04 21:07:29'),
(28, 2, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 21:07:29'),
(29, 2, 'LOGOUT', 'User abdi logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 21:07:40'),
(30, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 21:07:59'),
(31, 1, 'CREATE_DEPARTMENT', 'Created department: Information Technology', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 21:30:11'),
(32, 1, 'CREATE_ACADEMIC_YEAR', 'Created: ACY 2025/26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-04 21:33:14'),
(33, 1, 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 08:45:32'),
(34, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 08:46:52'),
(35, 1, 'UPDATE_DEPARTMENT', 'Updated department: Information Technology', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 08:48:12'),
(36, 1, 'UPDATE_DEPARTMENT', 'Updated department: Mechanical and Chemical Engineering', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 08:48:42'),
(37, 1, 'POST_NOTICE', 'Posted notice: Deadline', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 08:52:05'),
(38, 1, 'UPDATE_NOTICE', 'Updated notice: Deadline', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 08:52:39'),
(39, 1, 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 17:23:47'),
(40, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 17:25:25'),
(41, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 17:31:13'),
(42, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 17:32:04'),
(43, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 17:55:33'),
(44, 2, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 17:55:59'),
(45, 2, 'CREATE_STUDENT', 'Added student: Lalistu Tola Abdi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:06:54'),
(46, 2, 'APPROVE_APPLICATION', 'Approved application #6 for Test Student', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:10:02'),
(47, 2, 'APPROVE_APPLICATION', 'Approved application #5 for Test Student', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:10:15'),
(48, 2, 'APPROVE_APPLICATION', 'Approved application #12 for Test Student', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:10:31'),
(49, 2, 'LOGOUT', 'User abdi logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:11:43'),
(50, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:12:06'),
(51, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:13:10'),
(52, 2, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:14:01'),
(53, 2, 'APPROVE_APPLICATION', 'Approved application #7 for Test Student', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:21:07'),
(54, 2, 'APPROVE_APPLICATION', 'Approved application #7 for Test Student', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:33:57'),
(55, 2, 'APPROVE_APPLICATION', 'Approved application #8 for Test Student', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:34:15'),
(56, 2, 'APPROVE_APPLICATION', 'Approved application #8 for Test Student', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:34:42'),
(57, 2, 'REJECT_APPLICATION', 'Rejected application #3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:35:04'),
(58, 2, 'REGISTER_COST_SHARE', 'Registered cost-share for student ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 18:39:15'),
(59, 2, 'APPROVE_APPLICATION', 'Approved application #10 for Test Student', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:03:14'),
(60, 2, 'POST_NOTICE', 'Posted notice: Payment', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:09:58'),
(61, 2, 'UPDATE_PREFERENCES', 'Updated preferences', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:10:49'),
(62, 2, 'UPDATE_PREFERENCES', 'Updated preferences', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:10:56'),
(63, 2, 'LOGOUT', 'User abdi logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:11:06'),
(64, 2, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:12:59'),
(65, 2, 'LOGOUT', 'User abdi logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:13:23'),
(66, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:24:35'),
(67, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:26:30'),
(68, 3, 'LOGIN', 'User jaaloo logged in', NULL, NULL, NULL, 'success', '2026-08-05 19:26:50'),
(69, 3, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:26:50'),
(70, 3, 'LOGOUT', 'User jaaloo logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 19:59:48'),
(71, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:00:04'),
(72, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:02:12'),
(73, 4, 'LOGIN', 'User bikila logged in', NULL, NULL, NULL, 'success', '2026-08-05 20:02:33'),
(74, 4, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:02:33'),
(75, 4, 'CREATE_DISBURSEMENT', 'Created disbursement #2 for Test Student', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:06:39'),
(76, 4, 'UPDATE_PROFILE', 'Updated profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:09:00'),
(77, 4, 'LOGOUT', 'User bikila logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:09:25'),
(78, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:17:15'),
(79, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:19:22'),
(80, 5, 'LOGIN', 'User caalee logged in', NULL, NULL, NULL, 'success', '2026-08-05 20:19:40'),
(81, 5, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:19:40'),
(82, 5, 'LOGOUT', 'User caalee logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:23:39'),
(83, 5, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:23:57'),
(84, 5, 'LOGOUT', 'User caalee logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:24:19'),
(85, 5, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:25:23'),
(86, 5, 'APPLY_COST_SHARE', 'Applied for cost-share: ETB 20,000.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:32:23'),
(87, 5, 'SUBMIT_PAYMENT', 'Submitted payment of ETB 10,000.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:34:38'),
(88, 5, 'LOGOUT', 'User caalee logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:47:12'),
(89, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:47:38'),
(90, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:48:44'),
(91, 2, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:49:06'),
(92, 2, 'UPDATE_STUDENT', 'Updated student ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:50:03'),
(93, 2, 'LOGOUT', 'User abdi logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:50:41'),
(94, 3, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:51:23'),
(95, 3, 'LOGOUT', 'User jaaloo logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:52:29'),
(96, 5, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:52:56'),
(97, 5, 'SUBMIT_FEEDBACK', 'Submitted feedback: Appeal Regarding', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 20:59:18'),
(98, 5, 'SUBMIT_FEEDBACK', 'Submitted feedback: Appeal Regarding', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-05 21:07:42'),
(99, 5, 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-06 06:45:14'),
(100, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-06 06:52:57'),
(101, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-06 14:58:32'),
(102, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 17:50:45'),
(103, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 17:50:54'),
(104, 5, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 17:51:53'),
(105, 5, 'LOGOUT', 'User caalee logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:08:57'),
(106, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:09:13'),
(107, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:12:10'),
(108, 5, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:12:29'),
(109, 5, 'LOGOUT', 'User caalee logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:13:08'),
(110, 6, 'LOGIN', 'User hod_business logged in', NULL, NULL, NULL, 'success', '2026-08-07 18:13:51'),
(111, 6, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:13:51'),
(112, 2, 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:18:06'),
(113, 6, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:18:52'),
(114, 6, 'HOD_APPROVE', 'Approved #12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:22:36'),
(115, 6, 'HOD_APPROVE', 'Approved #4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:22:45'),
(116, 6, 'HOD_APPROVE', 'Approved #7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:22:53'),
(117, 6, 'HOD_APPROVE', 'Approved #10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:22:56'),
(118, 6, 'HOD_APPROVE', 'Approved #5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:23:02'),
(119, 6, 'HOD_APPROVE', 'Approved #8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:23:04'),
(120, 6, 'HOD_APPROVE', 'Approved #6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:23:07'),
(121, 6, 'LOGOUT', 'User hod_business logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:23:35'),
(122, 5, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:24:55'),
(123, 5, 'REQUEST_FORM', 'Requested cost-share form', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:25:21'),
(124, 6, 'APPROVE_FORM_REQUEST', 'Approved request #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:25:44'),
(125, 5, 'SUBMIT_FORM', 'Submitted cost-share form', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:26:31'),
(126, 6, 'APPROVE_FORM', 'Approved form #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:26:56'),
(127, 6, 'APPROVE_FORM', 'Approved form #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:27:00'),
(128, 5, 'SUBMIT_FEEDBACK', 'Submitted feedback: Appeal Regarding', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:37:43'),
(129, 6, 'LOGOUT', 'User hod_business logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:38:32'),
(130, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:38:48'),
(131, 1, 'UPDATE_FEEDBACK', 'Updated feedback ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:39:30'),
(132, 1, 'UPDATE_FEEDBACK', 'Updated feedback ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:43:01'),
(133, 5, 'LOGOUT', 'User caalee logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-07 18:57:19'),
(134, 1, 'UPDATE_FEEDBACK', 'Updated feedback ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 18:58:37'),
(135, 1, 'CREATE_BACKUP', 'Created backup: backup_2026-08-07_22-01-53.sql.gz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 19:01:53'),
(136, 1, 'DELETE_BACKUP', 'Deleted backup: backup_2026-08-07_22-01-53.sql.gz', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-07 19:03:48'),
(137, 1, 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, 'success', '2026-08-15 09:09:50'),
(138, 1, 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-20 19:21:55'),
(139, 1, 'LOGOUT', 'User admin logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', NULL, 'success', '2026-08-20 19:26:06');

-- --------------------------------------------------------

--
-- Table structure for table `cost_share_applications`
--

CREATE TABLE `cost_share_applications` (
  `application_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `ac_year_id` int(11) NOT NULL,
  `semester` int(11) NOT NULL,
  `application_date` date NOT NULL,
  `requested_amount` decimal(15,2) NOT NULL,
  `reason` text,
  `supporting_documents` varchar(255) DEFAULT NULL,
  `status` enum('pending','officer_approved','hod_approved','approved','rejected','disbursed') DEFAULT 'pending',
  `officer_comment` text,
  `hod_comment` text,
  `finance_comment` text,
  `officer_approved_by` int(11) DEFAULT NULL,
  `hod_approved_by` int(11) DEFAULT NULL,
  `finance_approved_by` int(11) DEFAULT NULL,
  `finance_approval_date` datetime DEFAULT NULL,
  `officer_approval_date` datetime DEFAULT NULL,
  `hod_approval_date` datetime DEFAULT NULL,
  `final_approval_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `cost_share_applications`
--

INSERT INTO `cost_share_applications` (`application_id`, `student_id`, `ac_year_id`, `semester`, `application_date`, `requested_amount`, `reason`, `supporting_documents`, `status`, `officer_comment`, `hod_comment`, `finance_comment`, `officer_approved_by`, `hod_approved_by`, `finance_approved_by`, `finance_approval_date`, `officer_approval_date`, `hod_approval_date`, `final_approval_date`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2026-03-05', '15000.00', NULL, NULL, 'disbursed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 23:06:39', '2026-03-04 21:00:00', '2026-08-05 20:06:39'),
(2, 1, 1, 1, '2026-04-05', '20000.00', NULL, NULL, 'approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-04 21:00:00', '2026-08-04 21:35:47'),
(3, 1, 1, 1, '2026-05-05', '12000.00', NULL, NULL, 'rejected', 'not complete', NULL, NULL, 2, NULL, NULL, NULL, '2026-08-05 21:35:04', NULL, NULL, '2026-05-04 21:00:00', '2026-08-05 18:35:04'),
(4, 1, 1, 1, '2026-06-05', '18000.00', NULL, NULL, 'hod_approved', NULL, NULL, NULL, NULL, 6, NULL, NULL, NULL, '2026-08-07 21:22:45', NULL, '2026-06-04 21:00:00', '2026-08-07 18:22:45'),
(5, 1, 1, 1, '2026-07-05', '25000.00', NULL, NULL, 'hod_approved', 'arywetjfkx ukxtuktck', NULL, NULL, 2, 6, NULL, NULL, '2026-08-05 21:10:15', '2026-08-07 21:23:02', NULL, '2026-07-04 21:00:00', '2026-08-07 18:23:02'),
(6, 1, 1, 1, '2026-08-05', '30000.00', NULL, NULL, 'hod_approved', 'arywetjfkx ukxtuktck', NULL, NULL, 2, 6, NULL, NULL, '2026-08-05 21:10:02', '2026-08-07 21:23:07', NULL, '2026-08-04 21:00:00', '2026-08-07 18:23:07'),
(7, 1, 1, 1, '2026-08-05', '10061.00', NULL, NULL, 'hod_approved', 'jhbjhgj', NULL, NULL, 2, 6, NULL, NULL, '2026-08-05 21:33:57', '2026-08-07 21:22:53', NULL, '2026-08-04 21:00:00', '2026-08-07 18:22:53'),
(8, 1, 1, 1, '2026-07-05', '12995.00', NULL, NULL, 'hod_approved', '', NULL, NULL, 2, 6, NULL, NULL, '2026-08-05 21:34:42', '2026-08-07 21:23:04', NULL, '2026-07-04 21:00:00', '2026-08-07 18:23:04'),
(9, 1, 1, 1, '2026-06-05', '26361.00', NULL, NULL, 'disbursed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 21:00:00', '2026-08-04 21:41:49'),
(10, 1, 1, 1, '2026-05-05', '13912.00', NULL, NULL, 'hod_approved', '', NULL, NULL, 2, 6, NULL, NULL, '2026-08-05 22:03:14', '2026-08-07 21:22:56', NULL, '2026-05-04 21:00:00', '2026-08-07 18:22:56'),
(11, 1, 1, 1, '2026-04-05', '17083.00', NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-04 21:00:00', '2026-08-04 21:41:49'),
(12, 1, 1, 1, '2026-03-05', '29368.00', NULL, NULL, 'hod_approved', '', NULL, NULL, 2, 6, NULL, NULL, '2026-08-05 21:10:31', '2026-08-07 21:22:36', NULL, '2026-03-04 21:00:00', '2026-08-07 18:22:36'),
(13, 3, 1, 1, '2026-08-05', '20000.00', 'I don&#039;t have money', '1785961943_Nwodchkz.png', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 20:32:23', '2026-08-05 20:32:23'),
(14, 6, 1, 1, '2026-08-07', '0.00', '', NULL, 'hod_approved', NULL, NULL, NULL, NULL, 6, NULL, NULL, NULL, '2026-08-07 21:26:56', NULL, '2026-08-07 18:26:56', '2026-08-07 18:26:56'),
(15, 6, 1, 2, '2026-08-07', '30000.00', 'semester break', NULL, 'hod_approved', NULL, NULL, NULL, NULL, 6, NULL, NULL, NULL, '2026-08-07 21:27:00', NULL, '2026-08-07 18:27:00', '2026-08-07 18:27:00');

-- --------------------------------------------------------

--
-- Table structure for table `cost_share_forms`
--

CREATE TABLE `cost_share_forms` (
  `form_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `semester` int(11) NOT NULL,
  `form_data` text,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `hod_comment` text,
  `hod_approved_by` int(11) DEFAULT NULL,
  `hod_approval_date` datetime DEFAULT NULL,
  `student_submitted_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `cost_share_forms`
--

INSERT INTO `cost_share_forms` (`form_id`, `student_id`, `academic_year_id`, `semester`, `form_data`, `status`, `hod_comment`, `hod_approved_by`, `hod_approval_date`, `student_submitted_date`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 1, NULL, 'approved', '', 6, '2026-08-07 21:26:56', NULL, '2026-08-07 18:25:44', '2026-08-07 18:26:56'),
(2, 6, 1, 2, '{\"requested_amount\":30000,\"reason\":\"semester break\",\"academic_year\":\"2024-25\",\"semester\":2}', 'approved', '', 6, '2026-08-07 21:27:00', '2026-08-07 21:26:31', '2026-08-07 18:26:31', '2026-08-07 18:27:00');

-- --------------------------------------------------------

--
-- Table structure for table `cost_share_form_requests`
--

CREATE TABLE `cost_share_form_requests` (
  `request_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `hod_id` int(11) NOT NULL,
  `request_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `hod_comment` text,
  `response_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `cost_share_form_requests`
--

INSERT INTO `cost_share_form_requests` (`request_id`, `student_id`, `hod_id`, `request_date`, `status`, `hod_comment`, `response_date`, `created_at`) VALUES
(1, 6, 6, '2026-08-07 21:25:21', 'approved', 'fill it', '2026-08-07 21:25:44', '2026-08-07 18:25:21');

-- --------------------------------------------------------

--
-- Table structure for table `cost_share_records`
--

CREATE TABLE `cost_share_records` (
  `record_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `ac_year_id` int(11) NOT NULL,
  `semester` int(11) NOT NULL,
  `total_cost_share` decimal(15,2) NOT NULL,
  `student_share` decimal(15,2) NOT NULL,
  `university_share` decimal(15,2) NOT NULL,
  `payment_status` enum('pending','partial','paid','verified','disbursed') DEFAULT 'pending',
  `student_payment` decimal(15,2) DEFAULT '0.00',
  `university_payment` decimal(15,2) DEFAULT '0.00',
  `payment_deadline` date DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `proof_verified` tinyint(1) DEFAULT '0',
  `verified_by` int(11) DEFAULT NULL,
  `verification_date` datetime DEFAULT NULL,
  `disbursement_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `cost_share_records`
--

INSERT INTO `cost_share_records` (`record_id`, `student_id`, `ac_year_id`, `semester`, `total_cost_share`, `student_share`, `university_share`, `payment_status`, `student_payment`, `university_payment`, `payment_deadline`, `payment_proof`, `proof_verified`, `verified_by`, `verification_date`, `disbursement_date`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 2, '19000.00', '0.00', '19000.00', 'pending', '0.00', '0.00', '2026-08-22', NULL, 0, NULL, NULL, NULL, '2026-08-05 18:39:15', '2026-08-05 18:39:15'),
(2, 1, 1, 1, '15000.00', '0.00', '15000.00', 'disbursed', '0.00', '15000.00', NULL, NULL, 0, NULL, NULL, '2026-08-05 23:06:39', '2026-08-05 20:06:39', '2026-08-05 20:06:39'),
(3, 3, 1, 1, '0.00', '0.00', '0.00', 'partial', '10000.00', '0.00', NULL, NULL, 0, NULL, NULL, NULL, '2026-08-05 20:34:38', '2026-08-05 20:34:38'),
(4, 6, 1, 1, '0.00', '0.00', '0.00', 'pending', '0.00', '0.00', NULL, NULL, 0, NULL, NULL, NULL, '2026-08-05 21:16:20', '2026-08-05 21:16:20');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL,
  `dept_code` varchar(20) NOT NULL,
  `dept_name` varchar(100) NOT NULL,
  `faculty` varchar(100) DEFAULT NULL,
  `budget_allocation` decimal(15,2) DEFAULT '0.00',
  `hod_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_code`, `dept_name`, `faculty`, `budget_allocation`, `hod_user_id`, `created_at`, `status`) VALUES
(1, 'CSE', 'Computer Science and Engineering', 'Faculty of Technology', '500000.00', NULL, '2026-08-04 18:28:34', 'active'),
(2, 'EEE', 'Electrical and Electronics Engineering', 'Faculty of Technology', '450000.00', NULL, '2026-08-04 18:28:34', 'active'),
(3, 'MCE', 'Mechanical and Chemical Engineering', 'Faculty of Engineering', '400000.00', NULL, '2026-08-04 18:28:34', 'active'),
(4, 'BBA', 'Business Administration', 'Faculty of Business', '350000.00', NULL, '2026-08-04 18:28:34', 'active'),
(5, 'ITD', 'Information Technology', 'Faculty of Engineering', '1000000.00', NULL, '2026-08-04 21:30:11', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `disbursements`
--

CREATE TABLE `disbursements` (
  `disbursement_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `disbursement_date` date NOT NULL,
  `disbursement_method` enum('bank_transfer','check','cash') DEFAULT 'bank_transfer',
  `bank_account` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `finance_user_id` int(11) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `disbursements`
--

INSERT INTO `disbursements` (`disbursement_id`, `student_id`, `record_id`, `application_id`, `amount`, `disbursement_date`, `disbursement_method`, `bank_account`, `reference_number`, `status`, `finance_user_id`, `remarks`, `created_at`, `completed_at`) VALUES
(2, 1, 2, 1, '15000.00', '2026-08-05', 'bank_transfer', '1000094408567', 'SASWE345', 'pending', 4, '', '2026-08-05 20:06:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `category` enum('general','complaint','suggestion','inquiry') DEFAULT 'general',
  `status` enum('pending','read','responded','resolved') DEFAULT 'pending',
  `response` text,
  `responded_by` int(11) DEFAULT NULL,
  `response_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `student_id`, `subject`, `message`, `category`, `status`, `response`, `responded_by`, `response_date`, `created_at`) VALUES
(1, 3, 'Appeal Regarding', 'Why the costshare amount increased', 'complaint', 'resolved', NULL, NULL, NULL, '2026-08-05 20:59:18'),
(2, 3, 'Appeal Regarding', 'Why the costshare amount increased', 'complaint', 'resolved', NULL, NULL, NULL, '2026-08-05 21:07:42'),
(3, 6, 'Appeal Regarding', 'The money is too much', 'inquiry', 'resolved', NULL, NULL, NULL, '2026-08-07 18:37:43');

-- --------------------------------------------------------

--
-- Table structure for table `notices`
--

CREATE TABLE `notices` (
  `notice_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `notice_type` enum('general','deadline','payment','disbursement','alert') DEFAULT 'general',
  `target_audience` enum('all','students','staff','department') DEFAULT 'all',
  `dept_id` int(11) DEFAULT NULL,
  `posted_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `expiration_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `notices`
--

INSERT INTO `notices` (`notice_id`, `title`, `content`, `notice_type`, `target_audience`, `dept_id`, `posted_by`, `is_active`, `expiration_date`, `created_at`, `updated_at`) VALUES
(1, 'Deadline', 'by this this semester the date you have to fill you cost share is by 29/10/2026', 'deadline', 'all', NULL, 1, 1, '2026-08-20', '2026-08-05 08:52:04', '2026-08-05 08:52:39'),
(2, 'Payment', 'By next semester the payment will be upgraded', 'payment', 'students', NULL, 2, 1, '2026-08-29', '2026-08-05 19:09:58', '2026-08-05 19:09:58');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT '0',
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `type`, `is_read`, `link`, `created_at`) VALUES
(1, 5, 'Welcome to WBCSMS!', 'info', 0, NULL, '2026-08-05 20:27:03'),
(2, 5, 'Welcome to WBCSMS! Your student account is ready.', 'info', 0, NULL, '2026-08-05 20:30:00'),
(3, 1, 'Welcome to WBCSMS! Your staff account is ready.', 'info', 0, NULL, '2026-08-05 20:30:00'),
(4, 2, 'Welcome to WBCSMS! Your staff account is ready.', 'info', 0, NULL, '2026-08-05 20:30:00'),
(5, 3, 'Welcome to WBCSMS! Your staff account is ready.', 'info', 0, NULL, '2026-08-05 20:30:00'),
(6, 4, 'Welcome to WBCSMS! Your staff account is ready.', 'info', 0, NULL, '2026-08-05 20:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('bank_transfer','cash','check','online') DEFAULT 'bank_transfer',
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verification_date` datetime DEFAULT NULL,
  `verification_comment` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `student_id`, `record_id`, `amount`, `payment_date`, `payment_method`, `transaction_id`, `payment_proof`, `status`, `verified_by`, `verification_date`, `verification_comment`, `created_at`) VALUES
(1, 3, 3, '10000.00', '2026-08-05', 'bank_transfer', 'TXCBR12', '1785962078_h4kLAvaV.png', 'pending', NULL, NULL, NULL, '2026-08-05 20:34:38');

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `before_payment_update` BEFORE UPDATE ON `payments` FOR EACH ROW BEGIN
    IF NEW.status = 'verified' AND OLD.status != 'verified' THEN
        UPDATE cost_share_records 
        SET payment_status = 'paid',
            student_payment = student_payment + NEW.amount
        WHERE record_id = NEW.record_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permission_id` int(11) NOT NULL,
  `permission_name` varchar(50) NOT NULL,
  `permission_description` text,
  `module` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`permission_id`, `permission_name`, `permission_description`, `module`, `created_at`) VALUES
(1, 'manage_users', 'Create, update, delete users', 'admin', '2026-08-04 18:28:34'),
(2, 'manage_roles', 'Assign and manage roles', 'admin', '2026-08-04 18:28:34'),
(3, 'manage_settings', 'Configure system settings', 'admin', '2026-08-04 18:28:34'),
(4, 'manage_students', 'Add, edit, delete students', 'officer', '2026-08-04 18:28:34'),
(5, 'register_cost_share', 'Register cost-share amounts', 'officer', '2026-08-04 18:28:34'),
(6, 'approve_cost_share', 'Approve cost-share applications', 'officer', '2026-08-04 18:28:34'),
(7, 'verify_payments', 'Verify student payments', 'finance', '2026-08-04 18:28:34'),
(8, 'disburse_funds', 'Release funds to students', 'finance', '2026-08-04 18:28:34'),
(9, 'hod_approve', 'Second-level approval', 'hod', '2026-08-04 18:28:34'),
(10, 'apply_cost_share', 'Apply for cost-share', 'student', '2026-08-04 18:28:34'),
(11, 'view_dashboard', 'View dashboard', 'all', '2026-08-04 18:28:34');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_description`, `created_at`) VALUES
(1, 'admin', 'System Administrator - Full System Control', '2026-08-04 18:28:33'),
(2, 'officer', 'Cost-Share Officer - Core Operations', '2026-08-04 18:28:33'),
(3, 'hod', 'Department Head - Departmental Approval', '2026-08-04 18:28:33'),
(4, 'finance', 'Finance Department - Financial Operations', '2026-08-04 18:28:33'),
(5, 'student', 'Student - Self-Service Portal', '2026-08-04 18:28:33');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_permission_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_permission_id`, `role_id`, `permission_id`, `created_at`) VALUES
(1, 1, 1, '2026-08-04 18:28:34'),
(2, 1, 2, '2026-08-04 18:28:34'),
(3, 1, 3, '2026-08-04 18:28:34'),
(4, 1, 4, '2026-08-04 18:28:34'),
(5, 1, 5, '2026-08-04 18:28:34'),
(6, 1, 6, '2026-08-04 18:28:34'),
(7, 1, 7, '2026-08-04 18:28:34'),
(8, 1, 8, '2026-08-04 18:28:34'),
(9, 1, 9, '2026-08-04 18:28:34'),
(10, 1, 10, '2026-08-04 18:28:34'),
(11, 1, 11, '2026-08-04 18:28:34'),
(12, 2, 4, '2026-08-04 18:28:34'),
(13, 2, 5, '2026-08-04 18:28:34'),
(14, 2, 6, '2026-08-04 18:28:34'),
(15, 2, 11, '2026-08-04 18:28:34'),
(16, 3, 9, '2026-08-04 18:28:34'),
(17, 3, 11, '2026-08-04 18:28:34'),
(18, 4, 7, '2026-08-04 18:28:34'),
(19, 4, 8, '2026-08-04 18:28:34'),
(20, 4, 11, '2026-08-04 18:28:34'),
(21, 5, 10, '2026-08-04 18:28:34'),
(22, 5, 11, '2026-08-04 18:28:34');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `student_code` varchar(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
  `date_of_birth` date DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Ethiopian',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `program` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL,
  `year_of_study` int(11) DEFAULT '1',
  `semester` int(11) DEFAULT '1',
  `academic_year` varchar(20) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `enrollment_status` enum('active','graduated','suspended','withdrawn') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `form_request_status` enum('not_requested','pending','approved','rejected') DEFAULT 'not_requested',
  `form_request_date` datetime DEFAULT NULL,
  `form_approval_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `student_code`, `user_id`, `full_name`, `gender`, `date_of_birth`, `nationality`, `phone`, `email`, `program`, `department_id`, `year_of_study`, `semester`, `academic_year`, `gpa`, `enrollment_status`, `created_at`, `updated_at`, `form_request_status`, `form_request_date`, `form_approval_date`) VALUES
(1, 'STU2024001', 1, 'Test Student', 'Male', NULL, 'Ethiopian', '0919520016', NULL, 'Computer Science', 4, 2, 1, '2024-25', NULL, 'active', '2026-08-04 21:35:47', '2026-08-05 21:15:42', 'not_requested', NULL, NULL),
(2, 'STU202600', 2, 'Lalistu Tola Abdi', 'Female', '2010-01-22', 'Ethiopian', '+251919520010', 'lalisa.itafa@ddu.ac.com', 'BSc', 5, 1, 1, '2025/2026', '3.50', 'active', '2026-08-05 18:06:54', '2026-08-05 21:15:07', 'not_requested', NULL, NULL),
(3, 'STU20260005', 3, 'Chaltu Yadasa Namo', 'Female', '2010-01-22', 'Ethiopian', '+251919522310', 'chaltu@bhu.edu.et', 'BSc', 4, 1, 1, '2025/2026', '3.50', 'active', '2026-08-05 20:24:58', '2026-08-05 21:15:14', 'not_requested', NULL, NULL),
(6, 'STU20260005_1', 5, 'Chaltu Yadasa Namo', 'Male', NULL, 'Ethiopian', '+251919545011', 'chaltu@bhu.edu.et', 'Deploma', 4, 1, 1, NULL, NULL, 'active', '2026-08-05 21:10:14', '2026-08-05 21:15:54', 'not_requested', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text,
  `setting_group` varchar(50) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_group`, `description`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'WBCSMS - Bule Hora University', 'general', 'Site Name', '2026-08-04 18:28:34', '2026-08-04 18:28:34'),
(2, 'max_cost_share', '100000', 'financial', 'Maximum cost-share amount per student', '2026-08-04 18:28:34', '2026-08-04 18:28:34'),
(3, 'application_deadline_days', '100', 'academic', 'Days before deadline', '2026-08-04 18:28:34', '2026-08-04 19:56:47'),
(4, 'semester_fee', '5000', 'financial', 'Default semester fee', '2026-08-04 18:28:34', '2026-08-04 18:28:34'),
(5, 'maintenance_mode', 'true', 'system', 'Maintenance mode status', '2026-08-04 18:28:34', '2026-08-04 19:56:47'),
(10, 'notifications', '1', 'officer_preferences', 'Officer preference', '2026-08-05 19:10:56', '2026-08-05 19:10:56'),
(11, 'email_alerts', '1', 'officer_preferences', 'Officer preference', '2026-08-05 19:10:56', '2026-08-05 19:10:56'),
(12, 'items_per_page', '25', 'officer_preferences', 'Officer preference', '2026-08-05 19:10:56', '2026-08-05 19:10:56'),
(13, 'default_view', 'compact', 'officer_preferences', 'Officer preference', '2026-08-05 19:10:56', '2026-08-05 19:10:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role_id` int(11) NOT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_verified` tinyint(1) DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `remember_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `full_name`, `role_id`, `dept_id`, `student_id`, `phone`, `profile_image`, `is_active`, `is_verified`, `last_login`, `created_at`, `updated_at`, `remember_token`) VALUES
(1, 'admin', 'admin@bulehora.edu.et', '$2y$12$EeoIWJZf3BoGp1VD2ZY05OCPk5EbQRYW70ZSYjnfNMFz2BbacnFeS', 'System Administrator', 1, 2, NULL, '', NULL, 1, 1, '2026-08-20 22:21:55', '2026-08-04 18:28:34', '2026-08-20 19:21:55', NULL),
(2, 'abdi', 'abdi@bulehora.edu.et', '$2y$12$OSZs200g9FCsrpx8PtfD.eLhcUuPwewgpRnMCnJlFCkt9YT/GaI.m', 'Abdi Kenesa Tola', 2, 4, NULL, '+251919520019', NULL, 1, 1, '2026-08-05 23:49:06', '2026-08-04 19:08:52', '2026-08-05 20:50:41', NULL),
(3, 'jaaloo', 'geletatilahun@dadu.edu.et', '$2y$12$FDg4grdiKrNlv8Rb3ERYWeuhVLfLouEDdw3i614gdGvK8.yLeSRPu', 'Geleta Tilahun Nemera', 3, 5, NULL, '+251919520019', NULL, 1, 1, '2026-08-05 23:51:23', '2026-08-04 19:43:58', '2026-08-05 20:51:23', NULL),
(4, 'bikila', 'bikila@bhu.edu.et', '$2y$12$kEOu8pJ4dxdKSFIUSndgj.goP4SnTpggkZnRj6BalN43ayFomE2XW', 'Bikila Gada Jiraata', 4, 4, NULL, '+251919520011', NULL, 1, 1, '2026-08-05 23:02:33', '2026-08-05 20:01:21', '2026-08-05 20:02:33', NULL),
(5, 'caalee', 'chaltu@bhu.edu.et', '$2y$12$XxAGbglL00iQrS9PLaJIUeQDEanHjSbu2xFeHtRl4EJ/a9CmK9GXy', 'Chaltu Yadasa Namo', 5, 2, '6', '+251919545011', NULL, 1, 1, '2026-08-07 21:24:55', '2026-08-05 20:18:45', '2026-08-07 18:24:55', '$2y$10$MoeiTF8YD9raGijiwMoMJO.1I71ebgfyCiU80MtDIL70b74Z0vSIu'),
(6, 'hod_business', 'hod@bulehora.edu.et', '$2y$12$CWqqpLTIGxl2G91CIxPpbOSD5tEKsWMRFUBr4pIBqAJ2uJHjVqdHu', 'HOD - Business Administration', 3, 4, NULL, '', NULL, 1, 1, '2026-08-07 21:18:52', '2026-08-07 18:05:06', '2026-08-07 18:18:52', NULL),
(10, 'Caalaa', 'chaalaa@bhu.edu.et', '$2y$12$EOzHwnr7h0o8Jx3Z4i5yeOEGDgO1YZvIvwkfbqCtSlYRvJj3ZAz2y', 'Caalaa Mitafa', 3, 2, NULL, '+251919525011', NULL, 1, 1, NULL, '2026-08-07 18:10:57', '2026-08-07 18:12:02', NULL);

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `after_user_login` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    IF NEW.last_login IS NOT NULL AND OLD.last_login IS NULL THEN
        INSERT INTO audit_logs (user_id, action_type, action_description, status)
        VALUES (NEW.user_id, 'LOGIN', CONCAT('User ', NEW.username, ' logged in'), 'success');
    END IF;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`ac_year_id`),
  ADD UNIQUE KEY `year_code` (`year_code`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cost_share_applications`
--
ALTER TABLE `cost_share_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `ac_year_id` (`ac_year_id`),
  ADD KEY `fk_finance_approved_by` (`finance_approved_by`);

--
-- Indexes for table `cost_share_forms`
--
ALTER TABLE `cost_share_forms`
  ADD PRIMARY KEY (`form_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `hod_approved_by` (`hod_approved_by`),
  ADD KEY `academic_year_id` (`academic_year_id`);

--
-- Indexes for table `cost_share_form_requests`
--
ALTER TABLE `cost_share_form_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `hod_id` (`hod_id`);

--
-- Indexes for table `cost_share_records`
--
ALTER TABLE `cost_share_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `ac_year_id` (`ac_year_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD UNIQUE KEY `dept_code` (`dept_code`);

--
-- Indexes for table `disbursements`
--
ALTER TABLE `disbursements`
  ADD PRIMARY KEY (`disbursement_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`notice_id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `record_id` (`record_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_permission_id`),
  ADD UNIQUE KEY `role_id` (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `student_code` (`student_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `dept_id` (`dept_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `ac_year_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `cost_share_applications`
--
ALTER TABLE `cost_share_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `cost_share_forms`
--
ALTER TABLE `cost_share_forms`
  MODIFY `form_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cost_share_form_requests`
--
ALTER TABLE `cost_share_form_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cost_share_records`
--
ALTER TABLE `cost_share_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `disbursements`
--
ALTER TABLE `disbursements`
  MODIFY `disbursement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notices`
--
ALTER TABLE `notices`
  MODIFY `notice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `role_permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `cost_share_applications`
--
ALTER TABLE `cost_share_applications`
  ADD CONSTRAINT `cost_share_applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `cost_share_applications_ibfk_2` FOREIGN KEY (`ac_year_id`) REFERENCES `academic_years` (`ac_year_id`),
  ADD CONSTRAINT `fk_finance_approved_by` FOREIGN KEY (`finance_approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `cost_share_forms`
--
ALTER TABLE `cost_share_forms`
  ADD CONSTRAINT `cost_share_forms_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `cost_share_forms_ibfk_2` FOREIGN KEY (`hod_approved_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `cost_share_forms_ibfk_3` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`ac_year_id`);

--
-- Constraints for table `cost_share_form_requests`
--
ALTER TABLE `cost_share_form_requests`
  ADD CONSTRAINT `cost_share_form_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `cost_share_form_requests_ibfk_2` FOREIGN KEY (`hod_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `cost_share_records`
--
ALTER TABLE `cost_share_records`
  ADD CONSTRAINT `cost_share_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `cost_share_records_ibfk_2` FOREIGN KEY (`ac_year_id`) REFERENCES `academic_years` (`ac_year_id`);

--
-- Constraints for table `disbursements`
--
ALTER TABLE `disbursements`
  ADD CONSTRAINT `disbursements_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `disbursements_ibfk_2` FOREIGN KEY (`record_id`) REFERENCES `cost_share_records` (`record_id`),
  ADD CONSTRAINT `disbursements_ibfk_3` FOREIGN KEY (`application_id`) REFERENCES `cost_share_applications` (`application_id`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`);

--
-- Constraints for table `notices`
--
ALTER TABLE `notices`
  ADD CONSTRAINT `notices_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`record_id`) REFERENCES `cost_share_records` (`record_id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`dept_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
