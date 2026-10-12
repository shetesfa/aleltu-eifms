-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 12, 2026 at 09:03 PM
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
-- Database: `aleltu_eifms`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_at` datetime DEFAULT NULL,
  `check_out_at` datetime DEFAULT NULL,
  `status` enum('present','absent','on_leave','half_day') NOT NULL DEFAULT 'present'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `check_in_at`, `check_out_at`, `status`) VALUES
(1, 1, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(2, 2, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(3, 3, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(4, 4, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(5, 5, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(6, 6, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(7, 7, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(8, 8, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(9, 9, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(10, 10, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(11, 11, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(12, 12, '2026-09-19', '2026-09-19 07:55:00', '2026-09-19 17:05:00', 'present'),
(13, 1, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(14, 2, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(15, 3, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(16, 4, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(17, 5, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(18, 6, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(19, 7, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(20, 8, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(21, 9, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(22, 10, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(23, 11, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(24, 12, '2026-09-20', '2026-09-20 07:55:00', '2026-09-20 17:05:00', 'present'),
(25, 1, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(26, 2, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(27, 3, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(28, 4, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(29, 5, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(30, 6, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(31, 7, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(32, 8, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(33, 9, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(34, 10, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(35, 11, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(36, 12, '2026-09-21', '2026-09-21 07:55:00', '2026-09-21 17:05:00', 'present'),
(37, 1, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(38, 2, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(39, 3, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(40, 4, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(41, 5, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(42, 6, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(43, 7, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(44, 8, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(45, 9, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(46, 10, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(47, 11, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(48, 12, '2026-09-22', '2026-09-22 07:55:00', '2026-09-22 17:05:00', 'present'),
(49, 1, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(50, 2, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(51, 3, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(52, 4, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(53, 5, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(54, 6, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(55, 7, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(56, 8, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(57, 9, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(58, 10, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(59, 11, '2026-09-23', '2026-09-23 07:55:00', '2026-09-23 17:05:00', 'present'),
(60, 12, '2026-09-23', NULL, NULL, 'on_leave'),
(61, 1, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(62, 2, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(63, 3, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(64, 4, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(65, 5, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(66, 6, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(67, 7, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(68, 8, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(69, 9, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(70, 10, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(71, 11, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(72, 12, '2026-09-24', '2026-09-24 07:55:00', '2026-09-24 17:05:00', 'present'),
(73, 1, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(74, 2, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(75, 3, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(76, 4, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(77, 5, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(78, 6, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(79, 7, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(80, 8, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(81, 9, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(82, 10, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(83, 11, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(84, 12, '2026-09-25', '2026-09-25 07:55:00', NULL, 'present'),
(85, 1, '2026-09-27', '2026-09-27 01:46:32', NULL, 'present'),
(88, 12, '2026-09-27', '2026-09-27 02:23:01', NULL, 'present'),
(93, 2, '2026-09-27', '2026-09-27 02:23:16', NULL, 'present'),
(94, 3, '2026-09-27', '2026-09-27 02:23:16', NULL, 'present'),
(95, 4, '2026-09-27', '2026-09-27 02:23:16', NULL, 'present'),
(96, 5, '2026-09-27', '2026-09-27 02:23:16', NULL, 'present'),
(97, 6, '2026-09-27', '2026-09-27 02:23:16', NULL, 'present'),
(98, 7, '2026-09-27', '2026-09-27 02:23:16', NULL, 'present'),
(99, 8, '2026-09-27', '2026-09-27 02:23:16', NULL, 'present'),
(100, 9, '2026-09-27', '2026-09-27 02:23:16', NULL, 'present'),
(101, 10, '2026-09-27', '2026-09-27 02:23:16', NULL, 'present'),
(102, 11, '2026-09-27', '2026-09-27 02:23:16', NULL, 'present'),
(116, 12, '2026-10-29', '2026-09-27 02:24:08', NULL, 'present');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `record_table` varchar(80) NOT NULL,
  `record_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `module`, `action`, `record_table`, `record_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'auth', 'login', 'users', 1, 'Super Admin logged in from local workstation', '127.0.0.1', '2026-09-25 21:35:46'),
(2, 3, 'dairy', 'create', 'dairy_milking_sessions', 1, 'Morning milking session completed: 205 Liters recorded', '127.0.0.1', '2026-09-25 21:35:46'),
(3, 8, 'warehouse', 'receive', 'wh_stock_batches', 1, 'Storekeeper received 205 Liters milk into Partition 1', '127.0.0.1', '2026-09-25 21:35:46'),
(4, 9, 'distribution', 'dispatch', 'dist_dispatches', 1, 'Dispatched 250L milk to Bole Fresh Shop', '127.0.0.1', '2026-09-25 21:35:46'),
(5, 10, 'finance', 'payment', 'dist_dispatch_payments', 1, 'Finance recorded 18,750 ETB bank payment from Bole Shop', '127.0.0.1', '2026-09-25 21:35:46');

-- --------------------------------------------------------

--
-- Table structure for table `dairy_breeding_records`
--

CREATE TABLE `dairy_breeding_records` (
  `id` int(11) NOT NULL,
  `cow_id` int(11) NOT NULL,
  `method` enum('natural','artificial_insemination') NOT NULL,
  `sire_reference` varchar(100) DEFAULT NULL,
  `breeding_date` date NOT NULL,
  `expected_calving_date` date DEFAULT NULL,
  `recorded_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dairy_calving_records`
--

CREATE TABLE `dairy_calving_records` (
  `id` int(11) NOT NULL,
  `breeding_record_id` int(11) NOT NULL,
  `dam_id` int(11) NOT NULL,
  `calf_id` int(11) DEFAULT NULL,
  `calving_date` date NOT NULL,
  `outcome` enum('live_birth','stillbirth','complication') NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dairy_cows`
--

CREATE TABLE `dairy_cows` (
  `id` int(11) NOT NULL,
  `tag_number` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `sex` enum('female','male') NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `dam_id` int(11) DEFAULT NULL,
  `sire_id` int(11) DEFAULT NULL,
  `cow_group_id` int(11) DEFAULT NULL,
  `lifecycle_status` enum('registered','calf','heifer','milking','dry','pregnant','sold','deceased') NOT NULL DEFAULT 'registered',
  `is_sick` tinyint(1) NOT NULL DEFAULT 0,
  `acquired_date` date DEFAULT NULL,
  `source` enum('born_on_farm','purchased') DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dairy_cows`
--

INSERT INTO `dairy_cows` (`id`, `tag_number`, `name`, `photo_path`, `sex`, `date_of_birth`, `breed`, `dam_id`, `sire_id`, `cow_group_id`, `lifecycle_status`, `is_sick`, `acquired_date`, `source`, `created_at`, `updated_at`) VALUES
(1, 'COW-001', 'ቦራ (Bora)', NULL, 'female', '2021-03-15', 'Holstein Friesian', NULL, NULL, 1, 'milking', 0, '2023-01-01', 'purchased', '2026-09-25 21:35:31', '2026-09-25 21:35:31'),
(2, 'COW-002', 'ፀደይ (Tsedey)', NULL, 'female', '2021-06-20', 'Holstein Cross', NULL, NULL, 1, 'milking', 0, '2023-01-01', 'purchased', '2026-09-25 21:35:31', '2026-09-25 21:35:31'),
(3, 'COW-003', 'ሎሚ (Lomi)', '/aleltu-eifms/uploads/cows/img_6ab95d269b3040.20763542.jpg', 'female', '2021-09-10', 'Jersey Cross', NULL, NULL, 2, 'milking', 0, '2023-01-01', 'born_on_farm', '2026-09-25 21:35:32', '2026-09-27 21:15:02'),
(4, 'COW-004', 'ብርቱካን (Birtukan)', NULL, 'female', '2020-11-05', 'Holstein Friesian', NULL, NULL, 1, 'milking', 0, '2023-01-01', 'purchased', '2026-09-25 21:35:32', '2026-09-25 21:35:32'),
(5, 'COW-005', 'ምሕረት (Mihret)', NULL, 'female', '2022-01-18', 'Holstein Friesian', NULL, NULL, 1, 'milking', 0, '2023-01-01', 'born_on_farm', '2026-09-25 21:35:32', '2026-09-25 21:35:32'),
(6, 'COW-006', 'መስከረም (Meskerem)', NULL, 'female', '2020-04-12', 'Crossbreed', NULL, NULL, 3, 'dry', 0, '2023-01-01', 'purchased', '2026-09-25 21:35:32', '2026-09-25 21:35:32'),
(7, 'COW-007', 'ገነት (Genet)', NULL, 'female', '2021-08-25', 'Holstein Cross', NULL, NULL, 3, 'pregnant', 0, '2023-01-01', 'born_on_farm', '2026-09-25 21:35:32', '2026-09-25 21:35:32'),
(8, 'COW-008', 'ውበት (Wubet)', NULL, 'female', '2023-02-14', 'Jersey Cross', NULL, NULL, 4, 'heifer', 0, '2023-01-01', 'born_on_farm', '2026-09-25 21:35:32', '2026-09-25 21:35:32'),
(9, 'COW-009', 'ጥሩፋት (Tirufat)', NULL, 'female', '2024-03-01', 'Holstein', NULL, NULL, 4, 'calf', 0, '2023-01-01', 'born_on_farm', '2026-09-25 21:35:32', '2026-09-25 21:35:32'),
(10, 'COW-010', 'ባሮ (Baro)', NULL, 'male', '2024-05-10', 'Holstein Cross', NULL, NULL, 4, 'calf', 0, '2023-01-01', 'born_on_farm', '2026-09-25 21:35:32', '2026-09-25 21:35:32');

-- --------------------------------------------------------

--
-- Table structure for table `dairy_cow_exit_records`
--

CREATE TABLE `dairy_cow_exit_records` (
  `id` int(11) NOT NULL,
  `cow_id` int(11) NOT NULL,
  `exit_type` enum('death','sale') NOT NULL,
  `exit_date` date NOT NULL,
  `cause_of_death` varchar(255) DEFAULT NULL,
  `buyer_name` varchar(150) DEFAULT NULL,
  `sale_price` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dairy_cow_groups`
--

CREATE TABLE `dairy_cow_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `pen_location` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dairy_cow_groups`
--

INSERT INTO `dairy_cow_groups` (`id`, `name`, `pen_location`, `notes`) VALUES
(1, 'Group A: ከፍተኛ ምርት ሰጪ ላሞች (High Yielders)', 'Barn 1 - North Wing', 'Cows yielding > 20L/day on TMR feed ration.'),
(2, 'Group B: መካከለኛ ምርት ሰጪ ላሞች (Mid Lactation)', 'Barn 1 - South Wing', 'Average yield 15-20L/day.'),
(3, 'Group C: ያረፉና እርጉዝ ላሞች (Dry & Pregnant)', 'Barn 2 - Pasture Shed', 'Special dry cow minerals and monitoring.'),
(4, 'Group D: ጊደሮችና ጥጃዎች (Heifers & Weaned Calves)', 'Calf Pen 1-4', 'Calf starter pellets and fresh alfalfa.');

-- --------------------------------------------------------

--
-- Table structure for table `dairy_health_records`
--

CREATE TABLE `dairy_health_records` (
  `id` int(11) NOT NULL,
  `cow_id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `condition_name` varchar(150) DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `vet_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dairy_health_records`
--

INSERT INTO `dairy_health_records` (`id`, `cow_id`, `record_date`, `condition_name`, `treatment`, `vet_id`) VALUES
(1, 1, '2026-09-15', 'ቀላል የማስትታይተስ ምልክት (Mastitis)', 'በጡት ውስጥ የክሎክሳሲሊን ቅባት ተሰጥቶ ድኗል', 7),
(2, 3, '2026-09-21', 'የኮቴ ማሳጠርና ጽዳት (Hoof Care)', 'መደበኛ የኮቴ ማስተካከልና የጸረ-ባክቴሪያ እጥበት ተደርጓል', 7);

-- --------------------------------------------------------

--
-- Table structure for table `dairy_milking_sessions`
--

CREATE TABLE `dairy_milking_sessions` (
  `id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `session_type` enum('morning','evening') NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dairy_milking_sessions`
--

INSERT INTO `dairy_milking_sessions` (`id`, `session_date`, `session_type`, `recorded_by`, `closed_at`) VALUES
(1, '2026-09-24', 'morning', 3, '2026-09-25 21:35:32'),
(2, '2026-09-24', 'evening', 3, '2026-09-25 21:35:33'),
(3, '2026-09-25', 'morning', 3, '2026-09-25 21:35:33'),
(4, '2026-09-25', 'evening', 3, '2026-09-25 21:35:33'),
(5, '2026-09-27', 'evening', 3, NULL),
(6, '2026-09-26', 'evening', 3, NULL),
(7, '2026-09-30', 'evening', 3, NULL),
(8, '2026-09-26', 'morning', 3, NULL),
(9, '2026-10-01', 'morning', 1, '2026-10-01 22:18:44'),
(10, '2026-10-01', 'evening', 1, '2026-10-01 22:18:28'),
(11, '2026-10-02', 'morning', 1, NULL),
(12, '2026-10-02', 'evening', 1, NULL),
(13, '2026-10-03', 'morning', 1, NULL),
(14, '2026-10-03', 'evening', 1, NULL),
(15, '2026-10-11', 'morning', 1, NULL),
(16, '2026-10-11', 'evening', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `dairy_milk_yield_entries`
--

CREATE TABLE `dairy_milk_yield_entries` (
  `id` int(11) NOT NULL,
  `milking_session_id` int(11) NOT NULL,
  `cow_id` int(11) NOT NULL,
  `yield_liters` decimal(6,2) NOT NULL,
  `observation` varchar(255) DEFAULT NULL,
  `flagged_abnormal` tinyint(1) NOT NULL DEFAULT 0,
  `recorded_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dairy_milk_yield_entries`
--

INSERT INTO `dairy_milk_yield_entries` (`id`, `milking_session_id`, `cow_id`, `yield_liters`, `observation`, `flagged_abnormal`, `recorded_by`, `created_at`) VALUES
(1, 1, 1, 13.50, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:32'),
(2, 1, 2, 11.50, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:32'),
(3, 1, 3, 10.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:32'),
(4, 1, 4, 14.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:32'),
(5, 1, 5, 12.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:32'),
(6, 2, 1, 11.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(7, 2, 2, 9.50, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(8, 2, 3, 8.50, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(9, 2, 4, 12.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(10, 2, 5, 10.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(11, 3, 1, 13.50, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(12, 3, 2, 11.50, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(13, 3, 3, 10.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(14, 3, 4, 14.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(15, 3, 5, 12.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(16, 4, 1, 11.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(17, 4, 2, 9.50, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:33'),
(18, 4, 3, 8.50, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:34'),
(19, 4, 4, 12.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:34'),
(20, 4, 5, 10.00, 'ጤናማ የወተት ፍሰት', 0, 3, '2026-09-25 21:35:34'),
(21, 8, 1, 50.00, NULL, 0, 3, '2026-09-27 22:12:31'),
(24, 6, 1, 20.00, NULL, 0, 3, '2026-09-27 22:18:38'),
(25, 9, 1, 20.00, NULL, 0, 1, '2026-10-01 22:18:06'),
(26, 10, 1, 20.00, NULL, 0, 1, '2026-10-01 22:18:21');

-- --------------------------------------------------------

--
-- Table structure for table `dairy_vaccination_records`
--

CREATE TABLE `dairy_vaccination_records` (
  `id` int(11) NOT NULL,
  `cow_id` int(11) NOT NULL,
  `vaccine_name` varchar(150) NOT NULL,
  `scheduled_date` date NOT NULL,
  `administered_date` date DEFAULT NULL,
  `status` enum('scheduled','administered','missed') NOT NULL DEFAULT 'scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dairy_vaccination_records`
--

INSERT INTO `dairy_vaccination_records` (`id`, `cow_id`, `vaccine_name`, `scheduled_date`, `administered_date`, `status`) VALUES
(1, 1, 'የአፍ እና እግር በሽታ (FMD)', '2026-08-26', '2026-08-26', 'administered'),
(2, 2, 'የአፍ እና እግር በሽታ (FMD)', '2026-08-26', '2026-08-26', 'administered'),
(3, 4, 'የአፍ እና እግር በሽታ (FMD)', '2026-08-26', '2026-08-26', 'administered'),
(4, 6, 'የአባጎርባ ክትባት (Blackleg)', '2026-10-09', NULL, 'scheduled');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `business_unit` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `business_unit`, `created_at`) VALUES
(1, 'Management & Administration', 'MGMT', 'management', '2026-08-08 12:58:26'),
(2, 'Dairy Operations (የላም እርባታ)', 'DAIRY', 'dairy', '2026-08-08 12:58:26'),
(3, 'Poultry Operations (የዶሮ እርባታ)', 'POULTRY', 'poultry', '2026-08-08 12:58:26'),
(4, 'Fish Operations (የዓሣ እርባታ)', 'FISH', 'fish', '2026-08-08 12:58:26'),
(5, 'Feed Production & Agronomy (የመኖና ሰብል)', 'FEED', 'feed_production', '2026-08-08 12:58:26'),
(6, 'Veterinary & Animal Health (እንስሳት ሕክምና)', 'VET', 'veterinary', '2026-08-17 21:59:52'),
(7, 'Warehouse & Logistics (መጋዘንና ስርጭት)', 'WH', 'warehouse', '2026-08-17 22:01:11'),
(8, 'Finance & Accounting (ፋይናንስ)', 'FIN', 'finance', '2026-08-17 22:01:11'),
(9, 'Human Resources (የሰው ኃይል)', 'HR', 'hr', '2026-08-17 22:01:11');

-- --------------------------------------------------------

--
-- Table structure for table `dept_material_requests`
--

CREATE TABLE `dept_material_requests` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `stock_item_id` int(11) NOT NULL,
  `custom_item_name` varchar(150) DEFAULT NULL,
  `requested_quantity` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `issued_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shortage_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','partially_issued','fulfilled','shortage_purchase_raised','sent_to_department','completed','rejected') NOT NULL DEFAULT 'pending',
  `purchase_request_id` int(11) DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `fulfilled_by` int(11) DEFAULT NULL,
  `received_confirmed_at` datetime DEFAULT NULL,
  `received_confirmed_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dept_material_requests`
--

INSERT INTO `dept_material_requests` (`id`, `department_id`, `stock_item_id`, `custom_item_name`, `requested_quantity`, `note`, `issued_quantity`, `shortage_quantity`, `status`, `purchase_request_id`, `requested_by`, `fulfilled_by`, `received_confirmed_at`, `received_confirmed_by`, `created_at`) VALUES
(1, 2, 4, NULL, 450.00, 'የሳምንቱ የወተት ላሞች መኖ ፍጆታ', 450.00, 0.00, 'completed', NULL, 3, 8, NULL, NULL, '2026-09-23 21:35:45'),
(2, 3, 3, NULL, 240.00, 'የዶሮዎች መኖ ሳምንታዊ ፍጆታ', 240.00, 0.00, 'completed', NULL, 4, 8, NULL, NULL, '2026-09-24 21:35:45'),
(3, 6, 8, NULL, 5.00, 'ለታመሙ ላሞች ሕክምና የሚሆን ኦክሲ መድኃኒት', 5.00, 0.00, 'completed', NULL, 7, 8, NULL, NULL, '2026-09-22 21:35:45'),
(4, 2, 10, NULL, 4.00, 'ተጨማሪ የወተት ጣሳዎች ጥያቄ', 4.00, 0.00, 'completed', NULL, 3, 13, '2026-09-27 23:40:32', 3, '2026-09-25 21:35:45');

-- --------------------------------------------------------

--
-- Table structure for table `dist_dispatches`
--

CREATE TABLE `dist_dispatches` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `driver_name` varchar(150) DEFAULT NULL,
  `destination_pos_location` varchar(150) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `stock_item_id` int(11) NOT NULL,
  `quantity_dispatched` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `payment_notes` varchar(255) DEFAULT NULL,
  `quantity_delivered` decimal(12,2) DEFAULT NULL,
  `variance_flagged` tinyint(1) NOT NULL DEFAULT 0,
  `planned_dispatch_at` datetime NOT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `status` enum('planned','dispatched','in_transit','delivered','partially_delivered','failed') NOT NULL DEFAULT 'planned',
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dist_dispatches`
--

INSERT INTO `dist_dispatches` (`id`, `vehicle_id`, `driver_id`, `driver_name`, `destination_pos_location`, `warehouse_id`, `stock_item_id`, `quantity_dispatched`, `unit_price`, `total_price`, `amount_paid`, `payment_status`, `payment_notes`, `quantity_delivered`, `variance_flagged`, `planned_dispatch_at`, `dispatched_at`, `confirmed_at`, `status`, `created_by`, `created_at`) VALUES
(1, 1, 11, 'ሙሉጌታ ወርቁ', 'ቦሌ ፍሬሽ መሸጫ ሱቅ (Bole Fresh Corner) [ተረካቢ: አቶ ቴዎድሮስ ካሳ]', 1, 1, 250.00, 75.00, 18750.00, 18750.00, 'paid', 'በሲቢኢ ባንክ ሙሉ በሙሉ ተከፍሏል', 250.00, 0, '2026-09-23 08:30:00', '2026-09-23 09:00:00', '2026-09-23 10:30:00', 'delivered', 9, '2026-09-25 21:35:39'),
(2, 1, 11, 'ሙሉጌታ ወርቁ', 'መገናኛ የወተት ማዕከል (Megenagna Dairy Hub) [ተረካቢ: ወ/ሮ ሰላማዊት አበራ]', 1, 2, 1200.00, 13.00, 15600.00, 10000.00, 'partial', '10,000 ብር በቴሌብር ተከፍሏል፤ 5,600 ብር ቀሪ', 1200.00, 0, '2026-09-24 08:30:00', '2026-09-24 09:00:00', '2026-09-24 10:45:00', 'delivered', 9, '2026-09-25 21:35:40'),
(3, 3, 11, 'ሙሉጌታ ወርቁ', 'የእርሻው በር መሸጫ ሱቅ (Farm Gate Retail Shop) [ተረካቢ: አቶ ታምራት ደሳለኝ]', 1, 1, 100.00, 70.00, 7000.00, 7000.00, 'paid', 'በጥሬ ገንዘብ በደረሰኝ ተከፍሏል', 100.00, 0, '2026-09-25 07:00:00', '2026-09-25 07:15:00', '2026-09-25 07:30:00', 'delivered', 9, '2026-09-25 21:35:40'),
(4, 2, 9, 'ሰለሞን አበበ', 'ቀጥተኛ ገዢ፡ ኃይሌ ግራንድ ሆቴል (Haile Grand Hotel)', 1, 1, 300.00, 75.00, 22500.00, 0.00, 'unpaid', 'የወር መጨረሻ የሚከፈል የብድር ሽያጭ', 300.00, 0, '2026-09-25 06:30:00', '2026-09-25 07:00:00', '2026-09-25 08:30:00', 'delivered', 9, '2026-09-25 21:35:40');

-- --------------------------------------------------------

--
-- Table structure for table `dist_dispatch_payments`
--

CREATE TABLE `dist_dispatch_payments` (
  `id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','bank','telebirr') NOT NULL DEFAULT 'cash',
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dist_dispatch_payments`
--

INSERT INTO `dist_dispatch_payments` (`id`, `dispatch_id`, `amount`, `payment_method`, `reference_no`, `notes`, `received_by`, `created_at`) VALUES
(1, 1, 18750.00, 'bank', 'CBE-FT-994821', 'Bole shop milk delivery payment', 10, '2026-09-23 21:35:41'),
(2, 2, 10000.00, 'telebirr', 'TB-REC-884721', 'Megenagna shop partial egg payment', 10, '2026-09-24 21:35:41'),
(3, 3, 7000.00, 'cash', 'CSH-REC-0012', 'Farm gate shop cash payment', 10, '2026-09-25 21:35:41');

-- --------------------------------------------------------

--
-- Table structure for table `dist_shops`
--

CREATE TABLE `dist_shops` (
  `id` int(11) NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `manager_name` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dist_shops`
--

INSERT INTO `dist_shops` (`id`, `shop_name`, `manager_name`, `phone`, `address`, `created_at`) VALUES
(1, 'ቦሌ ፍሬሽ መሸጫ ሱቅ (Bole Fresh Corner)', 'አቶ ቴዎድሮስ ካሳ', '0911223344', 'Bole Medhanialem, Near Edna Mall, Addis Ababa', '2026-09-25 21:35:39'),
(2, 'መገናኛ የወተት ማዕከል (Megenagna Dairy Hub)', 'ወ/ሮ ሰላማዊት አበራ', '0922334455', 'Megenagna Zefmesh Grand Mall Ground Floor, Addis Ababa', '2026-09-25 21:35:39'),
(3, 'የእርሻው በር መሸጫ ሱቅ (Farm Gate Retail Shop)', 'አቶ ታምራት ደሳለኝ', '0933445566', 'Aleltu Farm Main Compound Gate, Aleltu', '2026-09-25 21:35:39');

-- --------------------------------------------------------

--
-- Table structure for table `dist_vehicles`
--

CREATE TABLE `dist_vehicles` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(30) NOT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `default_driver_id` int(11) DEFAULT NULL,
  `driver_name` varchar(150) DEFAULT NULL,
  `driver_phone` varchar(50) DEFAULT NULL,
  `capacity_kg` decimal(10,2) DEFAULT NULL,
  `status` enum('available','in_use','maintenance') NOT NULL DEFAULT 'available',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dist_vehicles`
--

INSERT INTO `dist_vehicles` (`id`, `plate_number`, `vehicle_type`, `default_driver_id`, `driver_name`, `driver_phone`, `capacity_kg`, `status`, `created_at`) VALUES
(1, 'ET-3-A12456', 'Isuzu NPR (Refrigerated)', 11, 'ሙሉጌታ ወርቁ (Mulugeta)', '0912223344', 3500.00, 'available', '2026-09-25 21:35:39'),
(2, 'ET-2-B67890', 'Toyota Hilux Pickup', 9, 'ሰለሞን አበበ (Solomon)', '0912001122', 1200.00, 'available', '2026-09-25 21:35:39'),
(3, 'ET-1-C34521', 'Bajaj Three-Wheeler Van', 11, 'ሙሉጌታ ወርቁ (Mulugeta)', '0912223344', 500.00, 'available', '2026-09-25 21:35:39');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `employee_number` varchar(30) NOT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT 'O+',
  `full_name` varchar(150) NOT NULL,
  `amharic_name` varchar(150) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `emergency_contact` varchar(150) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `phone_alt` varchar(20) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `base_salary` decimal(12,2) DEFAULT NULL,
  `is_driver` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','on_leave','terminated') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `user_id`, `department_id`, `employee_number`, `national_id`, `blood_group`, `full_name`, `amharic_name`, `job_title`, `phone`, `emergency_contact`, `photo_path`, `phone_alt`, `hire_date`, `base_salary`, `is_driver`, `status`) VALUES
(1, 1, 1, 'EMP-001', 'ETH-1001', 'O+', 'Tesfaye Bekele', 'ተስፋዬ በቀለ', 'General Manager (ዋና ሥራ አስኪያጅ)', '0911122233', '0922233344', NULL, NULL, '2023-01-01', 28000.00, 0, 'active'),
(2, 2, 1, 'EMP-002', 'ETH-1002', 'A+', 'Teklu Alemu', 'ተክሉ አለሙ', 'Operations Manager (የስራ አስኪያጅ)', '0911334455', '0922445566', NULL, NULL, '2023-01-15', 24000.00, 0, 'active'),
(3, 3, 2, 'EMP-003', 'ETH-1003', 'B+', 'Aster Melaku', 'አስቴር መላኩ', 'Dairy Farm Specialist (የወተት ላም ኃላፊ)', '0911445566', '0922556677', '/aleltu-eifms/assets/uploads/employee_photos/emp_3_1790538321_938e26.png', NULL, '2023-03-01', 19000.00, 0, 'active'),
(4, 4, 3, 'EMP-004', 'ETH-1004', 'O+', 'Dawit Kebede', 'ዳዊት ከበደ', 'Poultry Production Head (የዶሮ እርባታ ኃላፊ)', '0911556677', '0922667788', NULL, NULL, '2023-04-10', 17000.00, 0, 'active'),
(5, 5, 4, 'EMP-005', 'ETH-1005', 'A-', 'Birhanu Desta', 'ብርሃኑ ደስታ', 'Aquaculture Specialist (የዓሣ እርባታ ኃላፊ)', '0911667788', '0922778899', NULL, NULL, '2023-05-01', 16500.00, 0, 'active'),
(6, 6, 5, 'EMP-006', 'ETH-1006', 'AB+', 'Tadesse Alemu', 'ታደሰ አለሙ', 'Feed Mill & Agronomy Head (የመኖና ሰብል ኃላፊ)', '0911778899', '0922889900', NULL, NULL, '2023-02-15', 18000.00, 0, 'active'),
(7, 7, 6, 'EMP-007', 'ETH-1007', 'O+', 'Dr. Yohannes Girma', 'ዶ/ር ዮሐንስ ግርማ', 'Senior Veterinarian (ዋና የእንስሳት ሐኪም)', '0911889900', '0922990011', NULL, NULL, '2023-01-20', 22000.00, 0, 'active'),
(8, 8, 7, 'EMP-008', 'ETH-1008', 'B-', 'Sintayehu Haile', 'ስንታየሁ ኃይሌ', 'Storekeeper (የዋና መጋዘን ኃላፊ)', '0911990011', '0922001122', NULL, NULL, '2023-06-01', 14500.00, 0, 'active'),
(9, 9, 7, 'EMP-009', 'ETH-1009', 'A+', 'Solomon Abebe', 'ሰለሞን አበበ', 'Logistics Officer (የስርጭት ኦፊሰር)', '0912001122', '0923112233', NULL, NULL, '2023-06-15', 14000.00, 0, 'active'),
(10, 10, 8, 'EMP-010', 'ETH-1010', 'O+', 'Tigist Mamo', 'ትዕግሥት ማሞ', 'Chief Accountant (ዋና አካውንታንት)', '0912112233', '0923223344', NULL, NULL, '2023-01-10', 20000.00, 0, 'active'),
(11, 11, 9, 'EMP-011', 'ETH-1011', 'O-', 'Mulugeta Worku', 'ሙሉጌታ ወርቁ', 'Refrigerated Truck Driver (አሽከርካሪ)', '0912223344', '0923334455', NULL, NULL, '2023-07-01', 12000.00, 1, 'active'),
(12, 12, 2, 'EMP-012', 'ETH-1012', 'B+', 'Almaz Tadesse', 'አልማዝ ታደሰ', 'Senior Milking Attendant (የወተት አላቢ)', '0912334455', '0923445566', '/aleltu-eifms/assets/uploads/employee_photos/emp_12_1790465140.png', NULL, '2023-08-01', 8500.00, 0, 'active'),
(13, 13, 1, 'EMP-ST013', NULL, 'O+', 'tesfa', NULL, 'Farm Staff Member', NULL, NULL, NULL, NULL, '2026-09-27', NULL, 0, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `emp_status_logs`
--

CREATE TABLE `emp_status_logs` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `old_status` varchar(50) NOT NULL,
  `new_status` varchar(50) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feed_distribution_requests`
--

CREATE TABLE `feed_distribution_requests` (
  `id` int(11) NOT NULL,
  `requesting_unit` enum('dairy','poultry','fish') NOT NULL,
  `quantity_requested_kg` decimal(10,2) NOT NULL,
  `quantity_fulfilled_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','fulfilled','partially_fulfilled_backordered','triggered_purchase') NOT NULL DEFAULT 'pending',
  `purchase_request_id` int(11) DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feed_distribution_requests`
--

INSERT INTO `feed_distribution_requests` (`id`, `requesting_unit`, `quantity_requested_kg`, `quantity_fulfilled_kg`, `status`, `purchase_request_id`, `requested_by`, `created_at`) VALUES
(1, 'dairy', 500.00, 500.00, 'fulfilled', NULL, 3, '2026-09-23 00:00:00'),
(2, 'poultry', 350.00, 350.00, 'fulfilled', NULL, 4, '2026-09-24 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `feed_harvest_records`
--

CREATE TABLE `feed_harvest_records` (
  `id` int(11) NOT NULL,
  `planting_record_id` int(11) NOT NULL,
  `harvest_date` date NOT NULL,
  `yield_kg` decimal(12,2) NOT NULL,
  `sent_to_store` tinyint(1) NOT NULL DEFAULT 0,
  `recorded_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feed_harvest_records`
--

INSERT INTO `feed_harvest_records` (`id`, `planting_record_id`, `harvest_date`, `yield_kg`, `sent_to_store`, `recorded_by`) VALUES
(1, 2, '2026-09-20', 12500.00, 1, 6);

-- --------------------------------------------------------

--
-- Table structure for table `feed_planting_records`
--

CREATE TABLE `feed_planting_records` (
  `id` int(11) NOT NULL,
  `plot_id` int(11) NOT NULL,
  `crop_type` varchar(100) NOT NULL,
  `planting_date` date NOT NULL,
  `expected_harvest_date` date DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `status` enum('growing','harvested') NOT NULL DEFAULT 'growing'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feed_planting_records`
--

INSERT INTO `feed_planting_records` (`id`, `plot_id`, `crop_type`, `planting_date`, `expected_harvest_date`, `recorded_by`, `status`) VALUES
(1, 1, 'አልፋልፋ', '2026-07-27', '2026-10-25', 6, 'growing'),
(2, 2, 'ስንዴ', '2026-06-27', '2026-09-20', 6, 'harvested'),
(3, 3, 'በቆሎ', '2026-06-15', '2026-10-15', 1, 'growing');

-- --------------------------------------------------------

--
-- Table structure for table `feed_plots`
--

CREATE TABLE `feed_plots` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `area_hectares` decimal(8,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feed_plots`
--

INSERT INTO `feed_plots` (`id`, `name`, `area_hectares`, `created_at`) VALUES
(1, 'ማሳ 1 — አልፋልፋ', 5.00, '2026-09-25 21:35:36'),
(2, 'ማሳ 2 — ስንዴ', 3.00, '2026-09-25 21:35:36'),
(3, 'ማሳ 3 — በቆሎ', 8.00, '2026-09-25 21:35:36');

-- --------------------------------------------------------

--
-- Table structure for table `fin_bank_accounts`
--

CREATE TABLE `fin_bank_accounts` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(100) NOT NULL DEFAULT 'Oromia Bank',
  `account_type` enum('bank','cash','mobile') NOT NULL DEFAULT 'bank',
  `account_name` varchar(150) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `opening_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fin_bank_accounts`
--

INSERT INTO `fin_bank_accounts` (`id`, `bank_name`, `account_type`, `account_name`, `account_number`, `opening_balance`, `created_at`) VALUES
(1, 'ኦሮሚያ ባንክ (Oromia Bank)', 'bank', 'Aleltu Agricultural Enterprise - Oromia Bank', '1092837465001', 850000.00, '2026-09-26 01:56:53'),
(2, 'ቴሌብር (Telebirr)', 'mobile', 'Aleltu Farm Enterprise Telebirr Merchant', '0911002233', 150000.00, '2026-09-26 01:56:53'),
(3, 'ጥሬ ገንዘብ / ዋና ካዝና (Cash in Hand)', 'cash', 'Farm Office Cash Safe', 'CASH-SAFE-01', 50000.00, '2026-09-26 01:56:53');

-- --------------------------------------------------------

--
-- Table structure for table `fin_expenses`
--

CREATE TABLE `fin_expenses` (
  `id` int(11) NOT NULL,
  `unit_tag` enum('dairy','poultry','fish','feed_production','warehouse','distribution','general_overhead') NOT NULL,
  `category` varchar(80) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `withdrawal_type` varchar(60) NOT NULL DEFAULT 'operating_expense',
  `expense_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `recipient_name` varchar(150) DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fin_expenses`
--

INSERT INTO `fin_expenses` (`id`, `unit_tag`, `category`, `amount`, `bank_account_id`, `withdrawal_type`, `expense_date`, `description`, `recipient_name`, `requested_by`, `approved_by`, `status`, `created_at`) VALUES
(5, 'general_overhead', 'የበዓል ስጦታ (Holiday Gift)', 15000.00, 1, 'holiday_gift', '2026-09-24', 'ለአዲስ ዓመት በዓል ለቀበሌ አስተዳደርና ለህብረተሰብ መሪዎች የተሰጠ የበዓል ስጦታ', 'የቀበሌ አስተዳደርና የአካባቢ ሽማግሌዎች (Kebele Admin & Elders)', 2, 2, 'approved', '2026-09-26 02:01:31'),
(6, 'general_overhead', 'የአስተዳደር ፕሮቶኮል / ድጋፍ', 5000.00, 2, 'admin_protocol', '2026-09-21', 'የአካባቢ ሰላምና ጸጥታ ግብረ-ኃይል አስተዳደራዊ ፕሮቶኮል ድጋፍ', 'የአካባቢ ሰላምና ደህንነት አስተዳደር (Local Admin Protocol)', 2, 2, 'approved', '2026-09-26 02:01:32'),
(7, 'general_overhead', 'የምግብና እንግዳ መስተንግዶ', 8500.00, 3, 'food_withdrawal', '2026-09-23', 'ለእርሻው እንግዶችና ለበዓል ድግስ የተዘጋጀ የምግብና መስተንግዶ ወጪ', 'የእርሻ እንግዶችና የሰራተኞች በዓል ድግስ (Farm Guest Catering)', 2, 2, 'approved', '2026-09-26 02:01:32'),
(8, 'general_overhead', 'የባለቤት የግል ማውጣት', 25000.00, 1, 'owner_drawing', '2026-09-19', 'የባለቤቱ የግል ማውጣት (Non-operating owner personal withdrawal)', 'አቶ ተክሉ ጌታቸው (Farm Owner Drawing)', 1, 2, 'approved', '2026-09-26 02:01:32'),
(9, 'dairy', 'የነዳጅ ወጪ (Diesel Fuel)', 12000.00, 1, 'operating_expense', '2026-09-25', 'ለወተት ማቀዝቀዣ ጄነሬተርና መኖ ማጓጓዣ ናፍጣ', 'ኖክ ነዳጅ ማደያ (NOC Station)', 3, 2, 'approved', '2026-09-26 02:01:32'),
(10, 'poultry', 'የመብራትና ውሃ (Utilities)', 4500.00, 2, 'operating_expense', '2026-09-22', 'የዶሮ ቤቶች የሙቀትና መብራት ወርሃዊ ክፍያ', 'የኢትዮጵያ ኤሌክትሪክ አገልግሎት (EEU)', 4, 2, 'approved', '2026-09-26 02:01:32'),
(11, 'feed_production', 'ጥገናና መለዋወጫ (Repairs)', 3200.00, 3, 'operating_expense', '2026-09-26', 'የመኖ መፍጫ ማሽን ቪ-ቤልትና ቅባት ግዢ', 'መካኒክ ወርክሾፕ (Workshop)', 6, 10, 'approved', '2026-09-26 02:01:32');

-- --------------------------------------------------------

--
-- Table structure for table `fin_payroll_payments`
--

CREATE TABLE `fin_payroll_payments` (
  `id` int(11) NOT NULL,
  `payslip_id` int(11) NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `notes` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fin_payroll_runs`
--

CREATE TABLE `fin_payroll_runs` (
  `id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `status` enum('draft','attendance_locked','finalized') NOT NULL DEFAULT 'draft',
  `processed_by` int(11) NOT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fin_payroll_runs`
--

INSERT INTO `fin_payroll_runs` (`id`, `period_start`, `period_end`, `status`, `processed_by`, `finalized_at`, `created_at`) VALUES
(1, '2026-09-01', '2026-09-30', 'finalized', 10, '2026-09-25 21:35:43', '2026-09-25 21:35:43');

-- --------------------------------------------------------

--
-- Table structure for table `fin_payslips`
--

CREATE TABLE `fin_payslips` (
  `id` int(11) NOT NULL,
  `payroll_run_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `base_salary` decimal(12,2) NOT NULL,
  `daily_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `days_present` int(11) NOT NULL DEFAULT 0,
  `net_pay` decimal(12,2) NOT NULL,
  `total_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` decimal(12,2) GENERATED ALWAYS AS (`net_pay` - `total_paid`) STORED,
  `allowances` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fin_payslips`
--

INSERT INTO `fin_payslips` (`id`, `payroll_run_id`, `employee_id`, `base_salary`, `daily_rate`, `days_present`, `net_pay`, `total_paid`, `allowances`, `deductions`) VALUES
(1, 1, 1, 28000.00, 933.33, 30, 26300.00, 26300.00, 2500.00, 4200.00),
(2, 1, 2, 24000.00, 800.00, 30, 22900.00, 22900.00, 2500.00, 3600.00),
(3, 1, 3, 19000.00, 633.33, 30, 18650.00, 18650.00, 2500.00, 2850.00),
(4, 1, 4, 17000.00, 566.67, 30, 16950.00, 16950.00, 2500.00, 2550.00),
(5, 1, 5, 16500.00, 550.00, 30, 16525.00, 16525.00, 2500.00, 2475.00),
(6, 1, 6, 18000.00, 600.00, 30, 17800.00, 17800.00, 2500.00, 2700.00),
(7, 1, 7, 22000.00, 733.33, 30, 21200.00, 21200.00, 2500.00, 3300.00),
(8, 1, 8, 14500.00, 483.33, 30, 13525.00, 13525.00, 1200.00, 2175.00),
(9, 1, 9, 14000.00, 466.67, 30, 13100.00, 13100.00, 1200.00, 2100.00),
(10, 1, 10, 20000.00, 666.67, 30, 19500.00, 19500.00, 2500.00, 3000.00),
(11, 1, 11, 12000.00, 400.00, 30, 11400.00, 11400.00, 1200.00, 1800.00),
(12, 1, 12, 8500.00, 283.33, 30, 8425.00, 8425.00, 1200.00, 1275.00);

-- --------------------------------------------------------

--
-- Table structure for table `fin_purchase_orders`
--

CREATE TABLE `fin_purchase_orders` (
  `id` int(11) NOT NULL,
  `purchase_request_id` int(11) DEFAULT NULL,
  `material_request_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `stock_item_id` int(11) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total_amount` decimal(14,2) NOT NULL,
  `amount_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `status` enum('draft','pending_approval','approved','sent','pending_receipt','received','cancelled') NOT NULL DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fin_purchase_orders`
--

INSERT INTO `fin_purchase_orders` (`id`, `purchase_request_id`, `material_request_id`, `supplier_name`, `stock_item_id`, `quantity`, `unit_price`, `total_amount`, `amount_paid`, `payment_status`, `status`, `created_by`, `approved_by`, `created_at`) VALUES
(1, 1, NULL, 'Ethio-Feed Processing SC (ኢትዮ-መኖ አ.ማ)', 4, 5000.00, 28.00, 140000.00, 140000.00, 'paid', 'received', 10, 2, '2026-09-15 20:35:42'),
(2, 2, NULL, 'Vet Pharma Ethiopia (የእንስሳት መድኃኒት አቅራቢ)', 9, 100.00, 45.00, 4500.00, 4500.00, 'paid', 'received', 10, 2, '2026-09-18 20:35:42'),
(3, 3, NULL, 'Alema Koudijs Feed PLC (አለማ መኖ ፋብሪካ)', 3, 2000.00, 32.00, 64000.00, 0.00, 'unpaid', '', 10, 2, '2026-09-23 20:35:42');

-- --------------------------------------------------------

--
-- Table structure for table `fin_purchase_requests`
--

CREATE TABLE `fin_purchase_requests` (
  `id` int(11) NOT NULL,
  `material_request_id` int(11) DEFAULT NULL,
  `stock_item_id` int(11) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `justification` varchar(255) DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','converted_to_po') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fin_purchase_requests`
--

INSERT INTO `fin_purchase_requests` (`id`, `material_request_id`, `stock_item_id`, `quantity`, `justification`, `requested_by`, `status`, `created_at`) VALUES
(1, NULL, 4, 5000.00, 'የወተት ላሞች መኖ ክምችት ማነስ (Low stock replenishment)', 3, 'converted_to_po', '2026-09-15 21:35:41'),
(2, NULL, 9, 100.00, 'የአፍ እና እግር በሽታ መከላከያ ክትባት (Annual preventive vaccination)', 7, 'converted_to_po', '2026-09-18 21:35:41'),
(3, NULL, 3, 2000.00, 'የዶሮዎች መኖ ተጨማሪ ትዕዛዝ (Poultry feed shortage)', 4, 'converted_to_po', '2026-09-22 21:35:41'),
(4, NULL, 10, 10.00, 'ተጨማሪ የወተት ማጓጓዣ ጣሳዎች (Cans for new shop)', 3, 'approved', '2026-09-25 21:35:41');

-- --------------------------------------------------------

--
-- Table structure for table `fin_transactions`
--

CREATE TABLE `fin_transactions` (
  `id` int(11) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `direction` enum('inflow','outflow') NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_method` enum('bank','cash') NOT NULL DEFAULT 'bank',
  `unit_tag` enum('dairy','poultry','fish','feed_production','warehouse','distribution','general_overhead') NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `source_table` varchar(50) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fin_transactions`
--

INSERT INTO `fin_transactions` (`id`, `bank_account_id`, `direction`, `amount`, `payment_method`, `unit_tag`, `category`, `source_table`, `source_id`, `transaction_date`, `description`, `recorded_by`, `created_at`) VALUES
(1, 1, 'inflow', 18750.00, 'bank', 'distribution', 'sales_revenue', 'dist_dispatches', 1, '2026-09-23', 'የቦሌ ሱቅ ወተት ሽያጭ ገቢ', 10, '2026-09-25 21:35:45'),
(2, 1, 'inflow', 10000.00, 'bank', 'distribution', 'sales_revenue', 'dist_dispatches', 2, '2026-09-24', 'የመገናኛ ሱቅ የዕንቁላል ሽያጭ ገቢ (ቴሌብር)', 10, '2026-09-25 21:35:45'),
(3, 3, 'inflow', 7000.00, 'cash', 'distribution', 'sales_revenue', 'dist_dispatches', 3, '2026-09-25', 'የእርሻ በር ሱቅ የጥሬ ገንዘብ ሽያጭ', 10, '2026-09-25 21:35:45'),
(4, 1, 'outflow', 140000.00, 'bank', 'warehouse', 'purchase', 'fin_purchase_orders', 1, '2026-09-16', 'ለመኖ አቅራቢ (Ethio-Feed) የተከፈለ', 10, '2026-09-25 21:35:45'),
(5, 3, 'outflow', 4500.00, 'cash', 'warehouse', 'purchase', 'fin_purchase_orders', 2, '2026-09-19', 'ለእንስሳት መድኃኒት የተከፈለ በጥሬ ገንዘብ', 10, '2026-09-25 21:35:45'),
(6, 1, 'outflow', 22500.00, 'bank', 'distribution', 'expense', 'fin_expenses', 1, '2026-09-19', 'የናፍጣ ነዳጅ ክፍያ (Total)', 10, '2026-09-25 21:35:45'),
(7, 1, 'outflow', 9800.00, 'bank', 'general_overhead', 'expense', 'fin_expenses', 2, '2026-09-21', 'የኤሌክትሪክ ኃይል ክፍያ', 10, '2026-09-25 21:35:45'),
(8, 3, 'outflow', 3200.00, 'cash', 'feed_production', 'expense', 'fin_expenses', 11, '2026-09-26', 'የመኖ መፍጫ ማሽን ቪ-ቤልትና ቅባት ግዢ (ተቀባይ/አላማ: መካኒክ ወርክሾፕ (Workshop))', 10, '2026-09-27 23:52:06');

-- --------------------------------------------------------

--
-- Table structure for table `fish_feeding_logs`
--

CREATE TABLE `fish_feeding_logs` (
  `id` int(11) NOT NULL,
  `fish_stock_id` int(11) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `quantity_kg` decimal(8,2) DEFAULT NULL,
  `feed_type` varchar(80) DEFAULT NULL,
  `status` enum('scheduled','completed','missed') NOT NULL DEFAULT 'scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fish_feeding_logs`
--

INSERT INTO `fish_feeding_logs` (`id`, `fish_stock_id`, `scheduled_at`, `completed_at`, `quantity_kg`, `feed_type`, `status`) VALUES
(1, 1, '2026-09-25 21:35:36', '2026-09-25 21:35:36', 15.00, '2mm Floating Pellets', 'completed');

-- --------------------------------------------------------

--
-- Table structure for table `fish_growth_samples`
--

CREATE TABLE `fish_growth_samples` (
  `id` int(11) NOT NULL,
  `fish_stock_id` int(11) NOT NULL,
  `sample_date` date NOT NULL,
  `sample_size` int(11) NOT NULL,
  `average_weight_g` decimal(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fish_harvest_records`
--

CREATE TABLE `fish_harvest_records` (
  `id` int(11) NOT NULL,
  `fish_stock_id` int(11) NOT NULL,
  `harvest_date` date NOT NULL,
  `quantity_kg` decimal(10,2) NOT NULL,
  `grade` varchar(30) DEFAULT NULL,
  `type` enum('full','partial') NOT NULL,
  `recorded_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fish_harvest_records`
--

INSERT INTO `fish_harvest_records` (`id`, `fish_stock_id`, `harvest_date`, `quantity_kg`, `grade`, `type`, `recorded_by`) VALUES
(1, 1, '2026-09-20', 250.00, 'Grade A (350g+)', 'partial', 5);

-- --------------------------------------------------------

--
-- Table structure for table `fish_mortality_logs`
--

CREATE TABLE `fish_mortality_logs` (
  `id` int(11) NOT NULL,
  `fish_stock_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `count` int(11) NOT NULL,
  `suspected_cause` varchar(150) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fish_ponds`
--

CREATE TABLE `fish_ponds` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `area_sq_m` decimal(10,2) DEFAULT NULL,
  `status` enum('empty','stocked','partially_harvested') NOT NULL DEFAULT 'empty'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fish_ponds`
--

INSERT INTO `fish_ponds` (`id`, `name`, `area_sq_m`, `status`) VALUES
(1, 'ኩሬ 1 - የቴላፒያ ማርቢያ (Tilapia Pond A)', 500.00, 'stocked'),
(2, 'ኩሬ 2 - የቴላፒያ ማሳደጊያ (Tilapia Pond B)', 400.00, 'empty');

-- --------------------------------------------------------

--
-- Table structure for table `fish_stocks`
--

CREATE TABLE `fish_stocks` (
  `id` int(11) NOT NULL,
  `pond_id` int(11) NOT NULL,
  `species` varchar(100) NOT NULL,
  `stocking_date` date NOT NULL,
  `stocking_quantity` int(11) NOT NULL,
  `stocking_density_per_sq_m` decimal(8,2) DEFAULT NULL,
  `cumulative_mortality` int(11) NOT NULL DEFAULT 0,
  `cumulative_harvested_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','closed') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fish_stocks`
--

INSERT INTO `fish_stocks` (`id`, `pond_id`, `species`, `stocking_date`, `stocking_quantity`, `stocking_density_per_sq_m`, `cumulative_mortality`, `cumulative_harvested_kg`, `status`) VALUES
(1, 1, 'Nile Tilapia (የአባይ ቴላፒያ)', '2026-06-27', 5000, 10.00, 45, 250.00, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `fish_water_quality_readings`
--

CREATE TABLE `fish_water_quality_readings` (
  `id` int(11) NOT NULL,
  `pond_id` int(11) NOT NULL,
  `reading_at` datetime NOT NULL,
  `ph` decimal(4,2) DEFAULT NULL,
  `dissolved_oxygen_mg_l` decimal(5,2) DEFAULT NULL,
  `temperature_celsius` decimal(4,1) DEFAULT NULL,
  `within_safe_range` tinyint(1) NOT NULL DEFAULT 1,
  `recorded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fish_water_quality_readings`
--

INSERT INTO `fish_water_quality_readings` (`id`, `pond_id`, `reading_at`, `ph`, `dissolved_oxygen_mg_l`, `temperature_celsius`, `within_safe_range`, `recorded_by`) VALUES
(1, 1, '2026-09-25 21:35:36', 7.40, 6.80, 24.5, 1, 5),
(2, 2, '2026-09-25 21:35:36', 7.20, 6.20, 25.0, 1, 5);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','manager_approved','approved','rejected') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `employee_id`, `start_date`, `end_date`, `reason`, `status`) VALUES
(1, 12, '2026-09-22', '2026-09-24', 'የቤተሰብ ጉዳይ አስቸኳይ ፈቃድ', 'approved'),
(2, 9, '2026-09-30', '2026-10-02', 'ዓመታዊ የዕረፍት ፈቃድ', 'rejected');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `target_user_id` int(11) DEFAULT NULL,
  `target_role_id` int(11) DEFAULT NULL,
  `target_department_id` int(11) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `module`, `priority`, `target_user_id`, `target_role_id`, `target_department_id`, `message`, `link_url`, `is_read`, `created_at`) VALUES
(1, 'finance', 'high', 2, NULL, NULL, 'አዲስ የግዢ ጥያቄ ማጽደቂያ፡ የዶሮ እርባታ ክፍል የ 2,000 ኪ.ግ መኖ ግዢ ማጽደቅ ይጠብቃል', '/aleltu-eifms/modules/finance/purchasing.php', 0, '2026-09-25 21:35:46'),
(2, 'warehouse', 'medium', 8, NULL, NULL, 'የእቃ ርክክብ ጥያቄ፡ የግዢ ትዕዛዝ PO#3 (2,000 ኪ.ግ የዶሮ መኖ) ለመጋዘን ርክክብ ተልኳል', '/aleltu-eifms/modules/warehouse/receiving.php', 0, '2026-09-25 21:35:46'),
(3, 'distribution', 'low', 10, NULL, NULL, 'የሽያጭ ክፍያ ማረጋገጫ፡ የቦሌ ሱቅ የ 18,750 ብር የባንክ ክፍያ በሂሳብ ሹም ተረጋግጧል', '/aleltu-eifms/modules/distribution/dispatches.php', 1, '2026-09-25 21:35:46'),
(4, 'dairy', 'high', NULL, NULL, 2, 'Store has issued full 4 units of የወተት ማጠራቀሚያ ጣሳ. Please confirm physical receipt.', '/aleltu-eifms/modules/dairy/material_request.php', 0, '2026-09-27 23:39:13'),
(5, 'warehouse', 'medium', NULL, 8, NULL, 'Department confirmed receipt of የወተት ማጠራቀሚያ ጣሳ (Request #4).', '/aleltu-eifms/modules/warehouse/requisitions.php', 0, '2026-09-27 23:40:32'),
(6, 'warehouse', 'high', NULL, 8, NULL, 'Daily milk yield from Dairy (20 Liters, evening session) sent to Store Partition 1 for physical verification.', '/aleltu-eifms/modules/warehouse/receiving.php', 0, '2026-10-01 22:18:28'),
(7, 'warehouse', 'high', NULL, 8, NULL, 'Daily milk yield from Dairy (20 Liters, morning session) sent to Store Partition 1 for physical verification.', '/aleltu-eifms/modules/warehouse/receiving.php', 0, '2026-10-01 22:18:43');

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_reviews`
--

CREATE TABLE `performance_reviews` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `attendance_rate_pct` decimal(5,2) DEFAULT NULL,
  `task_completion_rate_pct` decimal(5,2) DEFAULT NULL,
  `manager_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action_code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `module`, `action_code`, `description`) VALUES
(1, 'dairy', 'view', 'View Dairy'),
(2, 'dairy', 'create', 'Create Dairy'),
(3, 'dairy', 'edit', 'Edit Dairy'),
(4, 'dairy', 'delete', 'Delete Dairy'),
(5, 'dairy', 'export', 'Export Dairy'),
(6, 'poultry', 'view', 'View Poultry'),
(7, 'poultry', 'create', 'Create Poultry'),
(8, 'poultry', 'edit', 'Edit Poultry'),
(9, 'poultry', 'delete', 'Delete Poultry'),
(10, 'poultry', 'export', 'Export Poultry'),
(11, 'fish', 'view', 'View Fish'),
(12, 'fish', 'create', 'Create Fish'),
(13, 'fish', 'edit', 'Edit Fish'),
(14, 'fish', 'delete', 'Delete Fish'),
(15, 'fish', 'export', 'Export Fish'),
(16, 'warehouse', 'view', 'View Warehouse'),
(17, 'warehouse', 'create', 'Create Warehouse'),
(18, 'warehouse', 'edit', 'Edit Warehouse'),
(19, 'warehouse', 'delete', 'Delete Warehouse'),
(20, 'warehouse', 'approve', 'Approve Warehouse'),
(21, 'finance', 'view', 'View Finance'),
(22, 'finance', 'create', 'Create Finance'),
(23, 'finance', 'edit', 'Edit Finance'),
(24, 'finance', 'delete', 'Delete Finance'),
(25, 'finance', 'approve', 'Approve Finance'),
(26, 'employees', 'view', 'View Employees'),
(27, 'employees', 'create', 'Create Employees'),
(28, 'employees', 'edit', 'Edit Employees'),
(29, 'employees', 'delete', 'Delete Employees'),
(30, 'employees', 'approve', 'Approve Employees'),
(31, 'attendance', 'view', 'View Attendance'),
(32, 'attendance', 'create', 'Create Attendance'),
(33, 'attendance', 'edit', 'Edit Attendance'),
(34, 'veterinary', 'view', 'View Veterinary'),
(35, 'veterinary', 'create', 'Create Veterinary'),
(36, 'veterinary', 'edit', 'Edit Veterinary'),
(37, 'distribution', 'view', 'View Distribution'),
(38, 'distribution', 'create', 'Create Distribution'),
(39, 'distribution', 'edit', 'Edit Distribution'),
(40, 'distribution', 'approve', 'Approve Distribution'),
(41, 'feed', 'view', 'View Feed'),
(42, 'feed', 'create', 'Create Feed'),
(43, 'feed', 'edit', 'Edit Feed'),
(44, 'reports', 'view', 'View Reports'),
(45, 'reports', 'export', 'Export Reports'),
(46, 'system', 'admin', 'Admin System');

-- --------------------------------------------------------

--
-- Table structure for table `poultry_egg_collections`
--

CREATE TABLE `poultry_egg_collections` (
  `id` int(11) NOT NULL,
  `flock_id` int(11) NOT NULL,
  `collection_date` date NOT NULL,
  `round_no` tinyint(4) NOT NULL DEFAULT 1,
  `whole_count` int(11) NOT NULL DEFAULT 0,
  `cracked_count` int(11) NOT NULL DEFAULT 0,
  `broken_count` int(11) NOT NULL DEFAULT 0,
  `recorded_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `poultry_egg_collections`
--

INSERT INTO `poultry_egg_collections` (`id`, `flock_id`, `collection_date`, `round_no`, `whole_count`, `cracked_count`, `broken_count`, `recorded_by`) VALUES
(1, 1, '2026-09-23', 1, 950, 8, 3, 4),
(2, 1, '2026-09-23', 2, 810, 5, 2, 4),
(3, 1, '2026-09-24', 1, 950, 8, 3, 4),
(4, 1, '2026-09-24', 2, 810, 5, 2, 4),
(5, 1, '2026-09-25', 1, 950, 8, 3, 4),
(6, 1, '2026-09-25', 2, 810, 5, 2, 4);

-- --------------------------------------------------------

--
-- Table structure for table `poultry_flocks`
--

CREATE TABLE `poultry_flocks` (
  `id` int(11) NOT NULL,
  `poultry_house_id` int(11) NOT NULL,
  `breed` varchar(100) NOT NULL,
  `stocking_date` date NOT NULL,
  `initial_population` int(11) NOT NULL,
  `cumulative_mortality` int(11) NOT NULL DEFAULT 0,
  `cumulative_culled_sold` int(11) NOT NULL DEFAULT 0,
  `cumulative_transfers_in` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','closed') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `poultry_flocks`
--

INSERT INTO `poultry_flocks` (`id`, `poultry_house_id`, `breed`, `stocking_date`, `initial_population`, `cumulative_mortality`, `cumulative_culled_sold`, `cumulative_transfers_in`, `status`) VALUES
(1, 1, 'Bovans Brown Layers (እንቁላል ጣይ)', '2026-05-28', 2000, 18, 0, 0, 'active'),
(2, 2, 'Cobb 500 Broilers (የስጋ ዶሮ)', '2026-08-26', 1200, 12, 0, 0, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `poultry_houses`
--

CREATE TABLE `poultry_houses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `capacity` int(11) NOT NULL,
  `quarantined` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `poultry_houses`
--

INSERT INTO `poultry_houses` (`id`, `name`, `capacity`, `quarantined`) VALUES
(1, 'ዋና የንብርብር ዶሮዎች ማቆያ (Commercial Layers House 1)', 2500, 0),
(2, 'የስጋ ዶሮዎች ማቆያ (Broiler Production Shed 2)', 1500, 0);

-- --------------------------------------------------------

--
-- Table structure for table `poultry_mortality_logs`
--

CREATE TABLE `poultry_mortality_logs` (
  `id` int(11) NOT NULL,
  `flock_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `count` int(11) NOT NULL,
  `cause_code` enum('disease','heat_stress','predator','unknown') NOT NULL,
  `recorded_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `poultry_mortality_logs`
--

INSERT INTO `poultry_mortality_logs` (`id`, `flock_id`, `log_date`, `count`, `cause_code`, `recorded_by`) VALUES
(1, 1, '2026-09-24', 2, 'heat_stress', 4),
(2, 2, '2026-09-23', 3, 'predator', 4);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'super_admin', 'Full system access'),
(2, 'general_manager', 'Company-wide read + approvals'),
(3, 'dairy_manager', 'Dairy Farm module'),
(4, 'poultry_manager', 'Poultry Farm module'),
(5, 'fish_manager', 'Fish Farm module'),
(6, 'feed_production_manager', 'Feed Production module'),
(7, 'veterinary_officer', 'Veterinary module (cross-unit: Dairy/Poultry/Fish)'),
(8, 'warehouse_keeper', 'Warehouse module'),
(9, 'distribution_officer', 'Distribution module'),
(10, 'finance_officer', 'Finance module'),
(11, 'hr_officer', 'Employee Management module'),
(12, 'field_worker', 'Mobile/quick data entry only');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(1, 24),
(1, 25),
(1, 26),
(1, 27),
(1, 28),
(1, 29),
(1, 30),
(1, 31),
(1, 32),
(1, 33),
(1, 34),
(1, 35),
(1, 36),
(1, 37),
(1, 38),
(1, 39),
(1, 40),
(1, 41),
(1, 42),
(1, 43),
(1, 44),
(1, 45),
(1, 46),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(2, 18),
(2, 19),
(2, 20),
(2, 21),
(2, 22),
(2, 23),
(2, 24),
(2, 25),
(2, 26),
(2, 27),
(2, 28),
(2, 29),
(2, 30),
(2, 31),
(2, 32),
(2, 33),
(2, 34),
(2, 35),
(2, 36),
(2, 37),
(2, 38),
(2, 39),
(2, 40),
(2, 41),
(2, 42),
(2, 43),
(2, 44),
(2, 45),
(2, 46),
(3, 1),
(3, 2),
(3, 3),
(3, 5),
(3, 26),
(3, 34),
(3, 44),
(4, 6),
(4, 7),
(4, 8),
(4, 10),
(4, 26),
(4, 34),
(4, 44),
(5, 11),
(5, 12),
(5, 13),
(5, 15),
(5, 26),
(5, 34),
(5, 44),
(6, 16),
(6, 41),
(6, 42),
(6, 43),
(6, 44),
(7, 1),
(7, 6),
(7, 11),
(7, 26),
(7, 34),
(7, 35),
(7, 36),
(8, 16),
(8, 17),
(8, 18),
(8, 20),
(8, 44),
(8, 45),
(9, 16),
(9, 37),
(9, 38),
(9, 39),
(9, 40),
(10, 21),
(10, 22),
(10, 23),
(10, 25),
(10, 26),
(10, 44),
(10, 45),
(11, 26),
(11, 27),
(11, 28),
(11, 30),
(11, 31),
(11, 32),
(11, 33),
(11, 44),
(12, 1),
(12, 2),
(12, 6),
(12, 7),
(12, 11),
(12, 31);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`) VALUES
(1, 'company_name', 'ተክሉ ጌታቸው የከብት እርባታ እና የወተት ተዋጾኦ ማቀነባበሪያ ድርጅት', NULL),
(3, 'company_location', '', NULL),
(4, 'default_locale', 'am', NULL),
(5, 'default_theme', 'dark', NULL),
(6, 'low_stock_threshold', '10', NULL),
(7, 'payroll_tax_rate', '0', NULL),
(8, 'fiscal_year_start', '01-01', NULL),
(9, 'session_timeout_min', '60', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed','missed') NOT NULL DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `api_token` varchar(64) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `locale` varchar(5) NOT NULL DEFAULT 'en',
  `theme` varchar(5) NOT NULL DEFAULT 'light',
  `two_factor_secret` varchar(64) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `api_token`, `role_id`, `department_id`, `locale`, `theme`, `two_factor_secret`, `two_factor_enabled`, `is_active`, `last_login_at`, `created_at`) VALUES
(1, 'admin', 'admin@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 1, NULL, 'am', 'dark', NULL, 0, 1, '2026-10-11 20:14:15', '2026-08-08 12:58:26'),
(2, 'manager', 'manager@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 2, NULL, 'en', 'dark', NULL, 0, 1, '2026-08-09 15:48:55', '2026-08-08 12:58:27'),
(3, 'dairy', 'dairy@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 3, 1, 'am', 'light', NULL, 0, 1, '2026-09-27 23:39:33', '2026-08-08 12:58:27'),
(4, 'poultry', 'poultry@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 4, 2, 'am', 'dark', NULL, 0, 1, '2026-09-27 22:47:16', '2026-08-08 12:58:27'),
(5, 'fish', 'fish@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 5, 3, 'am', 'dark', NULL, 0, 1, '2026-09-27 22:59:59', '2026-08-08 12:58:27'),
(6, 'feed', 'feed@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 6, 4, 'am', 'dark', NULL, 0, 1, '2026-09-27 23:12:12', '2026-08-08 12:58:27'),
(7, 'vet', 'vet@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 7, 5, 'am', 'dark', NULL, 0, 1, '2026-09-27 23:19:19', '2026-08-08 12:58:28'),
(8, 'warehouse', 'warehouse@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 8, 6, 'am', 'dark', NULL, 0, 1, '2026-09-25 17:42:00', '2026-08-08 12:58:28'),
(9, 'distribution', 'distribution@aleltufarm.com', '$2y$10$9UAh9OniZ8Z6bg24y7QpD.WcRxb7NZf5ZSuf83YvjWVUlm/GXLqN6', NULL, 9, 5, 'en', 'light', NULL, 0, 1, '2026-08-19 19:43:50', '2026-08-08 12:58:28'),
(10, 'finance', 'finance@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 10, 7, 'am', 'light', NULL, 0, 1, '2026-09-27 23:42:52', '2026-08-08 12:58:28'),
(11, 'hr', 'hr@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 11, 8, 'am', 'dark', NULL, 0, 1, '2026-09-28 01:37:59', '2026-08-08 12:58:28'),
(12, 'milker', 'milker@aleltufarm.com', '$2y$10$P9Is4V5IKXtBkTm05zTPZuW8KnJj9jliLwNQtDMWFOSd9dsFBQBM6', NULL, 12, 1, 'en', 'light', NULL, 0, 1, '2026-08-19 19:44:42', '2026-08-08 12:58:28'),
(13, 'tesfa', 'tesfa@gmail.com', '$2y$10$sHgx3RbKPwHSP9SlrGUz3.hZulJKMmf1g.UqpNWIlTY2JiADYBn.O', NULL, 8, 7, 'am', 'light', NULL, 0, 1, '2026-09-27 20:59:53', '2026-09-27 20:59:36');

-- --------------------------------------------------------

--
-- Table structure for table `user_2fa_recovery_codes`
--

CREATE TABLE `user_2fa_recovery_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vet_disease_incidents`
--

CREATE TABLE `vet_disease_incidents` (
  `id` int(11) NOT NULL,
  `subject_type` enum('dairy_cow','poultry_flock','fish_stock') NOT NULL,
  `subject_id` int(11) NOT NULL,
  `disease_name` varchar(150) NOT NULL,
  `detected_date` date NOT NULL,
  `quarantine_flag` tinyint(1) NOT NULL DEFAULT 0,
  `quarantine_cleared_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `vet_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vet_health_records`
--

CREATE TABLE `vet_health_records` (
  `id` int(11) NOT NULL,
  `subject_type` enum('dairy_cow','poultry_flock','fish_stock') NOT NULL,
  `subject_id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `condition_name` varchar(150) DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `vet_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vet_vaccination_records`
--

CREATE TABLE `vet_vaccination_records` (
  `id` int(11) NOT NULL,
  `subject_type` enum('dairy_cow','poultry_flock','fish_stock') NOT NULL,
  `subject_id` int(11) NOT NULL,
  `vaccine_name` varchar(150) NOT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time DEFAULT '09:00:00',
  `administered_date` date DEFAULT NULL,
  `status` enum('scheduled','administered','missed') NOT NULL DEFAULT 'scheduled',
  `reminder_1day_sent` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_2hr_sent` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_1hr_sent` tinyint(1) NOT NULL DEFAULT 0,
  `batch_number` varchar(100) DEFAULT NULL,
  `dosage` varchar(80) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `vet_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wh_minimum_stock_rules`
--

CREATE TABLE `wh_minimum_stock_rules` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `stock_item_id` int(11) NOT NULL,
  `minimum_threshold` decimal(12,2) NOT NULL,
  `last_alert_sent_at` datetime DEFAULT NULL,
  `daily_burn_override` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wh_minimum_stock_rules`
--

INSERT INTO `wh_minimum_stock_rules` (`id`, `warehouse_id`, `stock_item_id`, `minimum_threshold`, `last_alert_sent_at`, `daily_burn_override`) VALUES
(1, 1, 1, 100.00, NULL, NULL),
(2, 1, 2, 500.00, NULL, NULL),
(3, 1, 3, 500.00, NULL, 10.00),
(4, 1, 4, 1000.00, NULL, 150.00),
(5, 1, 8, 5.00, NULL, NULL),
(6, 1, 9, 20.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wh_stock_adjustments`
--

CREATE TABLE `wh_stock_adjustments` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `stock_item_id` int(11) NOT NULL,
  `stock_batch_id` int(11) DEFAULT NULL,
  `adjustment_type` enum('increase','decrease') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `adjusted_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wh_stock_batches`
--

CREATE TABLE `wh_stock_batches` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `partition_code` enum('finished_products','feed_nutrition','materials_supplies') NOT NULL DEFAULT 'finished_products',
  `stock_item_id` int(11) NOT NULL,
  `batch_number` varchar(80) DEFAULT NULL,
  `quantity_on_hand` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `received_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `source` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wh_stock_batches`
--

INSERT INTO `wh_stock_batches` (`id`, `warehouse_id`, `partition_code`, `stock_item_id`, `batch_number`, `quantity_on_hand`, `unit_cost`, `received_date`, `expiry_date`, `source`) VALUES
(1, 1, 'finished_products', 1, 'MILK-20260925-01', 450.00, 45.0000, '2026-09-25', '2026-09-28', 'dairy_milking'),
(2, 1, 'finished_products', 2, 'EGG-20260925-01', 3600.00, 10.5000, '2026-09-25', '2026-10-16', 'poultry_collection'),
(3, 1, 'feed_nutrition', 3, 'FEED-LYR-20260920', 2400.00, 32.0000, '2026-09-20', '2026-12-24', 'purchase'),
(4, 1, 'feed_nutrition', 4, 'FEED-DAIRY-20260920', 3800.00, 28.0000, '2026-09-20', '2026-12-24', 'purchase'),
(5, 1, 'feed_nutrition', 5, 'FEED-CALF-20260915', 650.00, 35.0000, '2026-09-15', '2026-12-24', 'purchase'),
(6, 1, 'feed_nutrition', 6, 'FEED-FISH-20260910', 450.00, 65.0000, '2026-09-10', '2027-01-23', 'purchase'),
(7, 1, 'feed_nutrition', 7, 'ALF-HAY-20260918', 180.00, 250.0000, '2026-09-18', '2027-03-24', 'harvest'),
(8, 1, 'materials_supplies', 8, 'MED-OXY-20260901', 25.00, 180.0000, '2026-09-01', '2027-09-25', 'purchase'),
(9, 1, 'materials_supplies', 9, 'VAC-FMD-20260905', 100.00, 45.0000, '2026-09-05', '2027-03-24', 'purchase'),
(10, 1, 'materials_supplies', 10, 'CAN-40L-20260810', 11.00, 1200.0000, '2026-08-11', NULL, 'purchase');

-- --------------------------------------------------------

--
-- Table structure for table `wh_stock_issues`
--

CREATE TABLE `wh_stock_issues` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `stock_item_id` int(11) NOT NULL,
  `stock_batch_id` int(11) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `destination_type` varchar(30) NOT NULL,
  `issued_by` int(11) NOT NULL,
  `issued_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wh_stock_issues`
--

INSERT INTO `wh_stock_issues` (`id`, `warehouse_id`, `stock_item_id`, `stock_batch_id`, `quantity`, `destination_type`, `issued_by`, `issued_at`) VALUES
(1, 1, 4, 4, 450.00, 'dairy', 8, '2026-09-23 21:35:38'),
(2, 1, 3, 3, 240.00, 'poultry', 8, '2026-09-24 21:35:38'),
(3, 1, 10, 10, 4.00, 'dairy', 13, '2026-09-27 23:39:13');

-- --------------------------------------------------------

--
-- Table structure for table `wh_stock_items`
--

CREATE TABLE `wh_stock_items` (
  `id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `product_class` enum('milk','feed','medicine','equipment','spare_parts','general') NOT NULL,
  `department_scope` varchar(20) DEFAULT NULL COMMENT 'NULL = shared across all departments in its product_class; otherwise a departments.code value restricting it to one department (see includes/material_request_shared.php)',
  `partition_code` enum('finished_products','feed_nutrition','materials_supplies') NOT NULL DEFAULT 'finished_products',
  `unit` varchar(20) NOT NULL,
  `is_perishable` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wh_stock_items`
--

INSERT INTO `wh_stock_items` (`id`, `sku`, `name`, `product_class`, `department_scope`, `partition_code`, `unit`, `is_perishable`) VALUES
(1, 'MILK-RAW-01', 'ጥሬ ወተት', 'milk', NULL, 'finished_products', 'L', 1),
(2, 'EGG-TABLE-01', 'የዶሮ ዕንቁላል', 'general', NULL, 'finished_products', 'pcs', 1),
(3, 'FEED-LAYER-01', 'የዶሮ መኖ', 'feed', NULL, 'feed_nutrition', 'kg', 0),
(4, 'FEED-DAIRY-01', 'የላም መኖ', 'feed', NULL, 'feed_nutrition', 'kg', 0),
(5, 'FEED-CALF-01', 'የጥጃ መኖ', 'feed', NULL, 'feed_nutrition', 'kg', 0),
(6, 'FEED-FISH-01', 'የዓሳ መኖ', 'feed', NULL, 'feed_nutrition', 'kg', 0),
(7, 'FEED-ALFALFA-01', 'አልፋልፋ ድርቆሽ', 'feed', NULL, 'feed_nutrition', 'bales', 0),
(8, 'MED-ANTIBIOTIC', 'የእንስሳት መድኃኒት (ኦክሲ)', 'medicine', NULL, 'materials_supplies', 'vials', 0),
(9, 'MED-VACCINE-01', 'የአፍ እና የእግር በሽታ ክትባት', 'medicine', NULL, 'materials_supplies', 'doses', 1),
(10, 'EQUIP-MILK-CAN', 'የወተት ማጠራቀሚያ ጣሳ', 'equipment', NULL, 'materials_supplies', 'pcs', 0),
(11, 'FISH-FRESH-01', 'ትኩስ ዓሳ (Fresh Fish)', '', NULL, 'finished_products', 'KG', 1);

-- --------------------------------------------------------

--
-- Table structure for table `wh_stock_receipts`
--

CREATE TABLE `wh_stock_receipts` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `stock_item_id` int(11) NOT NULL,
  `stock_batch_id` int(11) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `source_type` varchar(30) NOT NULL,
  `received_by` int(11) NOT NULL,
  `received_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wh_store_receiving`
--

CREATE TABLE `wh_store_receiving` (
  `id` int(11) NOT NULL,
  `dispatch_code` varchar(50) NOT NULL,
  `source_type` enum('dairy','poultry','fish','feed_production','purchase') NOT NULL,
  `source_reference` varchar(100) DEFAULT NULL,
  `material_request_id` int(11) DEFAULT NULL,
  `warehouse_id` int(11) NOT NULL,
  `stock_item_id` int(11) NOT NULL,
  `sent_quantity` decimal(12,2) NOT NULL,
  `received_quantity` decimal(12,2) DEFAULT NULL,
  `variance_quantity` decimal(12,2) GENERATED ALWAYS AS (`sent_quantity` - coalesce(`received_quantity`,0)) STORED,
  `variance_reason` varchar(255) DEFAULT NULL,
  `status` enum('pending_receipt','stored','sent_to_department','received_full','received_variance','rejected') NOT NULL DEFAULT 'pending_receipt',
  `sent_by` int(11) NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `received_by` int(11) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wh_store_receiving`
--

INSERT INTO `wh_store_receiving` (`id`, `dispatch_code`, `source_type`, `source_reference`, `material_request_id`, `warehouse_id`, `stock_item_id`, `sent_quantity`, `received_quantity`, `variance_reason`, `status`, `sent_by`, `sent_at`, `received_by`, `received_at`) VALUES
(1, 'DISP-DRY-001', 'dairy', 'Morning Milk Milking Session #1', NULL, 1, 1, 205.00, 205.00, NULL, 'received_full', 3, '2026-09-24 21:35:38', 8, '2026-09-24 21:35:38'),
(2, 'DISP-PLT-001', 'poultry', 'Daily Egg Harvest #1', NULL, 1, 2, 1760.00, 1760.00, NULL, 'received_full', 4, '2026-09-24 21:35:38', 8, '2026-09-24 21:35:38'),
(3, 'MILK-10-20261001-6285', 'dairy', 'Daily Milking (evening session, 2026-10-01)', NULL, 1, 1, 20.00, NULL, NULL, 'pending_receipt', 1, '2026-10-01 22:18:28', NULL, NULL),
(4, 'MILK-9-20261001-8201', 'dairy', 'Daily Milking (morning session, 2026-10-01)', NULL, 1, 1, 20.00, NULL, NULL, 'pending_receipt', 1, '2026-10-01 22:18:43', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wh_warehouses`
--

CREATE TABLE `wh_warehouses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `product_class` enum('milk','feed','medicine','equipment','spare_parts','general') NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `requires_cold_storage` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wh_warehouses`
--

INSERT INTO `wh_warehouses` (`id`, `name`, `product_class`, `location`, `requires_cold_storage`) VALUES
(1, 'ዋና ማዕከላዊ የእርሻ መጋዘን', 'general', 'Aleltu Farm Compound - Sector A', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_att` (`employee_id`,`attendance_date`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `dairy_breeding_records`
--
ALTER TABLE `dairy_breeding_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cow_id` (`cow_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `dairy_calving_records`
--
ALTER TABLE `dairy_calving_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `breeding_record_id` (`breeding_record_id`),
  ADD KEY `dam_id` (`dam_id`),
  ADD KEY `calf_id` (`calf_id`);

--
-- Indexes for table `dairy_cows`
--
ALTER TABLE `dairy_cows`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_number` (`tag_number`),
  ADD KEY `dam_id` (`dam_id`),
  ADD KEY `sire_id` (`sire_id`),
  ADD KEY `cow_group_id` (`cow_group_id`);

--
-- Indexes for table `dairy_cow_exit_records`
--
ALTER TABLE `dairy_cow_exit_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cow_id` (`cow_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `dairy_cow_groups`
--
ALTER TABLE `dairy_cow_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dairy_health_records`
--
ALTER TABLE `dairy_health_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cow_id` (`cow_id`),
  ADD KEY `vet_id` (`vet_id`);

--
-- Indexes for table `dairy_milking_sessions`
--
ALTER TABLE `dairy_milking_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_session` (`session_date`,`session_type`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `dairy_milk_yield_entries`
--
ALTER TABLE `dairy_milk_yield_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_entry` (`milking_session_id`,`cow_id`),
  ADD KEY `cow_id` (`cow_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `dairy_vaccination_records`
--
ALTER TABLE `dairy_vaccination_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cow_id` (`cow_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `dept_material_requests`
--
ALTER TABLE `dept_material_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `stock_item_id` (`stock_item_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `fulfilled_by` (`fulfilled_by`),
  ADD KEY `fk_dmr_received_by` (`received_confirmed_by`);

--
-- Indexes for table `dist_dispatches`
--
ALTER TABLE `dist_dispatches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `stock_item_id` (`stock_item_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `dist_dispatch_payments`
--
ALTER TABLE `dist_dispatch_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dispatch_id` (`dispatch_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `dist_shops`
--
ALTER TABLE `dist_shops`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dist_vehicles`
--
ALTER TABLE `dist_vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD KEY `fk_vehicle_default_driver` (`default_driver_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_number` (`employee_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `emp_status_logs`
--
ALTER TABLE `emp_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `feed_distribution_requests`
--
ALTER TABLE `feed_distribution_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requested_by` (`requested_by`);

--
-- Indexes for table `feed_harvest_records`
--
ALTER TABLE `feed_harvest_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `planting_record_id` (`planting_record_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `feed_planting_records`
--
ALTER TABLE `feed_planting_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plot_id` (`plot_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `feed_plots`
--
ALTER TABLE `feed_plots`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fin_bank_accounts`
--
ALTER TABLE `fin_bank_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fin_expenses`
--
ALTER TABLE `fin_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `fin_payroll_payments`
--
ALTER TABLE `fin_payroll_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payslip_id` (`payslip_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `fin_payroll_runs`
--
ALTER TABLE `fin_payroll_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `fin_payslips`
--
ALTER TABLE `fin_payslips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_payslip` (`payroll_run_id`,`employee_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `fin_purchase_orders`
--
ALTER TABLE `fin_purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_request_id` (`purchase_request_id`),
  ADD KEY `stock_item_id` (`stock_item_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `fin_purchase_requests`
--
ALTER TABLE `fin_purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_item_id` (`stock_item_id`),
  ADD KEY `requested_by` (`requested_by`);

--
-- Indexes for table `fin_transactions`
--
ALTER TABLE `fin_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_date_unit` (`transaction_date`,`unit_tag`);

--
-- Indexes for table `fish_feeding_logs`
--
ALTER TABLE `fish_feeding_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fish_stock_id` (`fish_stock_id`);

--
-- Indexes for table `fish_growth_samples`
--
ALTER TABLE `fish_growth_samples`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fish_stock_id` (`fish_stock_id`);

--
-- Indexes for table `fish_harvest_records`
--
ALTER TABLE `fish_harvest_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fish_stock_id` (`fish_stock_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `fish_mortality_logs`
--
ALTER TABLE `fish_mortality_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fish_stock_id` (`fish_stock_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `fish_ponds`
--
ALTER TABLE `fish_ponds`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fish_stocks`
--
ALTER TABLE `fish_stocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pond_id` (`pond_id`);

--
-- Indexes for table `fish_water_quality_readings`
--
ALTER TABLE `fish_water_quality_readings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pond_id` (`pond_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`notification_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_perm` (`module`,`action_code`);

--
-- Indexes for table `poultry_egg_collections`
--
ALTER TABLE `poultry_egg_collections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `flock_id` (`flock_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `poultry_flocks`
--
ALTER TABLE `poultry_flocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `poultry_house_id` (`poultry_house_id`);

--
-- Indexes for table `poultry_houses`
--
ALTER TABLE `poultry_houses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `poultry_mortality_logs`
--
ALTER TABLE `poultry_mortality_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `flock_id` (`flock_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `ux_users_name` (`name`),
  ADD UNIQUE KEY `api_token` (`api_token`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_users_api_token` (`api_token`);

--
-- Indexes for table `user_2fa_recovery_codes`
--
ALTER TABLE `user_2fa_recovery_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `vet_disease_incidents`
--
ALTER TABLE `vet_disease_incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vet_id` (`vet_id`),
  ADD KEY `idx_subject` (`subject_type`,`subject_id`);

--
-- Indexes for table `vet_health_records`
--
ALTER TABLE `vet_health_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vet_id` (`vet_id`),
  ADD KEY `idx_subject` (`subject_type`,`subject_id`);

--
-- Indexes for table `vet_vaccination_records`
--
ALTER TABLE `vet_vaccination_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vet_id` (`vet_id`),
  ADD KEY `idx_subject` (`subject_type`,`subject_id`),
  ADD KEY `idx_due` (`status`,`scheduled_date`);

--
-- Indexes for table `wh_minimum_stock_rules`
--
ALTER TABLE `wh_minimum_stock_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rule` (`warehouse_id`,`stock_item_id`),
  ADD KEY `stock_item_id` (`stock_item_id`);

--
-- Indexes for table `wh_stock_adjustments`
--
ALTER TABLE `wh_stock_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `stock_item_id` (`stock_item_id`),
  ADD KEY `adjusted_by` (`adjusted_by`);

--
-- Indexes for table `wh_stock_batches`
--
ALTER TABLE `wh_stock_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_item_id` (`stock_item_id`),
  ADD KEY `idx_fefo` (`warehouse_id`,`stock_item_id`,`expiry_date`);

--
-- Indexes for table `wh_stock_issues`
--
ALTER TABLE `wh_stock_issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `stock_item_id` (`stock_item_id`),
  ADD KEY `stock_batch_id` (`stock_batch_id`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `wh_stock_items`
--
ALTER TABLE `wh_stock_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`);

--
-- Indexes for table `wh_stock_receipts`
--
ALTER TABLE `wh_stock_receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `stock_item_id` (`stock_item_id`),
  ADD KEY `stock_batch_id` (`stock_batch_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `wh_store_receiving`
--
ALTER TABLE `wh_store_receiving`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dispatch_code` (`dispatch_code`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `stock_item_id` (`stock_item_id`),
  ADD KEY `sent_by` (`sent_by`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `wh_warehouses`
--
ALTER TABLE `wh_warehouses`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `dairy_breeding_records`
--
ALTER TABLE `dairy_breeding_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dairy_calving_records`
--
ALTER TABLE `dairy_calving_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dairy_cows`
--
ALTER TABLE `dairy_cows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `dairy_cow_exit_records`
--
ALTER TABLE `dairy_cow_exit_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dairy_cow_groups`
--
ALTER TABLE `dairy_cow_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dairy_health_records`
--
ALTER TABLE `dairy_health_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dairy_milking_sessions`
--
ALTER TABLE `dairy_milking_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `dairy_milk_yield_entries`
--
ALTER TABLE `dairy_milk_yield_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `dairy_vaccination_records`
--
ALTER TABLE `dairy_vaccination_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `dept_material_requests`
--
ALTER TABLE `dept_material_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dist_dispatches`
--
ALTER TABLE `dist_dispatches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dist_dispatch_payments`
--
ALTER TABLE `dist_dispatch_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `dist_shops`
--
ALTER TABLE `dist_shops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `dist_vehicles`
--
ALTER TABLE `dist_vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `emp_status_logs`
--
ALTER TABLE `emp_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feed_distribution_requests`
--
ALTER TABLE `feed_distribution_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `feed_harvest_records`
--
ALTER TABLE `feed_harvest_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feed_planting_records`
--
ALTER TABLE `feed_planting_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `feed_plots`
--
ALTER TABLE `feed_plots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `fin_bank_accounts`
--
ALTER TABLE `fin_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `fin_expenses`
--
ALTER TABLE `fin_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `fin_payroll_payments`
--
ALTER TABLE `fin_payroll_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fin_payroll_runs`
--
ALTER TABLE `fin_payroll_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `fin_payslips`
--
ALTER TABLE `fin_payslips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `fin_purchase_orders`
--
ALTER TABLE `fin_purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `fin_purchase_requests`
--
ALTER TABLE `fin_purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `fin_transactions`
--
ALTER TABLE `fin_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `fish_feeding_logs`
--
ALTER TABLE `fish_feeding_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `fish_growth_samples`
--
ALTER TABLE `fish_growth_samples`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fish_harvest_records`
--
ALTER TABLE `fish_harvest_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `fish_mortality_logs`
--
ALTER TABLE `fish_mortality_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fish_ponds`
--
ALTER TABLE `fish_ponds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `fish_stocks`
--
ALTER TABLE `fish_stocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `fish_water_quality_readings`
--
ALTER TABLE `fish_water_quality_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `poultry_egg_collections`
--
ALTER TABLE `poultry_egg_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `poultry_flocks`
--
ALTER TABLE `poultry_flocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `poultry_houses`
--
ALTER TABLE `poultry_houses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `poultry_mortality_logs`
--
ALTER TABLE `poultry_mortality_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_2fa_recovery_codes`
--
ALTER TABLE `user_2fa_recovery_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `vet_disease_incidents`
--
ALTER TABLE `vet_disease_incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vet_health_records`
--
ALTER TABLE `vet_health_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vet_vaccination_records`
--
ALTER TABLE `vet_vaccination_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wh_minimum_stock_rules`
--
ALTER TABLE `wh_minimum_stock_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `wh_stock_adjustments`
--
ALTER TABLE `wh_stock_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wh_stock_batches`
--
ALTER TABLE `wh_stock_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `wh_stock_issues`
--
ALTER TABLE `wh_stock_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wh_stock_items`
--
ALTER TABLE `wh_stock_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `wh_stock_receipts`
--
ALTER TABLE `wh_stock_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wh_store_receiving`
--
ALTER TABLE `wh_store_receiving`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `wh_warehouses`
--
ALTER TABLE `wh_warehouses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `dairy_breeding_records`
--
ALTER TABLE `dairy_breeding_records`
  ADD CONSTRAINT `dairy_breeding_records_ibfk_1` FOREIGN KEY (`cow_id`) REFERENCES `dairy_cows` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dairy_breeding_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `dairy_calving_records`
--
ALTER TABLE `dairy_calving_records`
  ADD CONSTRAINT `dairy_calving_records_ibfk_1` FOREIGN KEY (`breeding_record_id`) REFERENCES `dairy_breeding_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dairy_calving_records_ibfk_2` FOREIGN KEY (`dam_id`) REFERENCES `dairy_cows` (`id`),
  ADD CONSTRAINT `dairy_calving_records_ibfk_3` FOREIGN KEY (`calf_id`) REFERENCES `dairy_cows` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dairy_cows`
--
ALTER TABLE `dairy_cows`
  ADD CONSTRAINT `dairy_cows_ibfk_1` FOREIGN KEY (`dam_id`) REFERENCES `dairy_cows` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dairy_cows_ibfk_2` FOREIGN KEY (`sire_id`) REFERENCES `dairy_cows` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dairy_cows_ibfk_3` FOREIGN KEY (`cow_group_id`) REFERENCES `dairy_cow_groups` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dairy_cow_exit_records`
--
ALTER TABLE `dairy_cow_exit_records`
  ADD CONSTRAINT `dairy_cow_exit_records_ibfk_1` FOREIGN KEY (`cow_id`) REFERENCES `dairy_cows` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dairy_cow_exit_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `dairy_health_records`
--
ALTER TABLE `dairy_health_records`
  ADD CONSTRAINT `dairy_health_records_ibfk_1` FOREIGN KEY (`cow_id`) REFERENCES `dairy_cows` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dairy_health_records_ibfk_2` FOREIGN KEY (`vet_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `dairy_milking_sessions`
--
ALTER TABLE `dairy_milking_sessions`
  ADD CONSTRAINT `dairy_milking_sessions_ibfk_1` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `dairy_milk_yield_entries`
--
ALTER TABLE `dairy_milk_yield_entries`
  ADD CONSTRAINT `dairy_milk_yield_entries_ibfk_1` FOREIGN KEY (`milking_session_id`) REFERENCES `dairy_milking_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dairy_milk_yield_entries_ibfk_2` FOREIGN KEY (`cow_id`) REFERENCES `dairy_cows` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dairy_milk_yield_entries_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `dairy_vaccination_records`
--
ALTER TABLE `dairy_vaccination_records`
  ADD CONSTRAINT `dairy_vaccination_records_ibfk_1` FOREIGN KEY (`cow_id`) REFERENCES `dairy_cows` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dept_material_requests`
--
ALTER TABLE `dept_material_requests`
  ADD CONSTRAINT `dept_material_requests_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `dept_material_requests_ibfk_2` FOREIGN KEY (`stock_item_id`) REFERENCES `wh_stock_items` (`id`),
  ADD CONSTRAINT `dept_material_requests_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `dept_material_requests_ibfk_4` FOREIGN KEY (`fulfilled_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_dmr_received_by` FOREIGN KEY (`received_confirmed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `dist_dispatches`
--
ALTER TABLE `dist_dispatches`
  ADD CONSTRAINT `dist_dispatches_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `dist_vehicles` (`id`),
  ADD CONSTRAINT `dist_dispatches_ibfk_3` FOREIGN KEY (`warehouse_id`) REFERENCES `wh_warehouses` (`id`),
  ADD CONSTRAINT `dist_dispatches_ibfk_4` FOREIGN KEY (`stock_item_id`) REFERENCES `wh_stock_items` (`id`),
  ADD CONSTRAINT `dist_dispatches_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `dist_vehicles`
--
ALTER TABLE `dist_vehicles`
  ADD CONSTRAINT `fk_vehicle_default_driver` FOREIGN KEY (`default_driver_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `feed_distribution_requests`
--
ALTER TABLE `feed_distribution_requests`
  ADD CONSTRAINT `feed_distribution_requests_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `feed_harvest_records`
--
ALTER TABLE `feed_harvest_records`
  ADD CONSTRAINT `feed_harvest_records_ibfk_1` FOREIGN KEY (`planting_record_id`) REFERENCES `feed_planting_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feed_harvest_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `feed_planting_records`
--
ALTER TABLE `feed_planting_records`
  ADD CONSTRAINT `feed_planting_records_ibfk_1` FOREIGN KEY (`plot_id`) REFERENCES `feed_plots` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feed_planting_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fin_expenses`
--
ALTER TABLE `fin_expenses`
  ADD CONSTRAINT `fin_expenses_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fin_expenses_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fin_payroll_payments`
--
ALTER TABLE `fin_payroll_payments`
  ADD CONSTRAINT `fin_payroll_payments_ibfk_1` FOREIGN KEY (`payslip_id`) REFERENCES `fin_payslips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fin_payroll_payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fin_payroll_runs`
--
ALTER TABLE `fin_payroll_runs`
  ADD CONSTRAINT `fin_payroll_runs_ibfk_1` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fin_payslips`
--
ALTER TABLE `fin_payslips`
  ADD CONSTRAINT `fin_payslips_ibfk_1` FOREIGN KEY (`payroll_run_id`) REFERENCES `fin_payroll_runs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fin_payslips_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `fin_purchase_orders`
--
ALTER TABLE `fin_purchase_orders`
  ADD CONSTRAINT `fin_purchase_orders_ibfk_1` FOREIGN KEY (`purchase_request_id`) REFERENCES `fin_purchase_requests` (`id`),
  ADD CONSTRAINT `fin_purchase_orders_ibfk_2` FOREIGN KEY (`stock_item_id`) REFERENCES `wh_stock_items` (`id`),
  ADD CONSTRAINT `fin_purchase_orders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fin_purchase_orders_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fin_purchase_requests`
--
ALTER TABLE `fin_purchase_requests`
  ADD CONSTRAINT `fin_purchase_requests_ibfk_1` FOREIGN KEY (`stock_item_id`) REFERENCES `wh_stock_items` (`id`),
  ADD CONSTRAINT `fin_purchase_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fin_transactions`
--
ALTER TABLE `fin_transactions`
  ADD CONSTRAINT `fin_transactions_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `fin_bank_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fin_transactions_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fish_feeding_logs`
--
ALTER TABLE `fish_feeding_logs`
  ADD CONSTRAINT `fish_feeding_logs_ibfk_1` FOREIGN KEY (`fish_stock_id`) REFERENCES `fish_stocks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fish_growth_samples`
--
ALTER TABLE `fish_growth_samples`
  ADD CONSTRAINT `fish_growth_samples_ibfk_1` FOREIGN KEY (`fish_stock_id`) REFERENCES `fish_stocks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fish_harvest_records`
--
ALTER TABLE `fish_harvest_records`
  ADD CONSTRAINT `fish_harvest_records_ibfk_1` FOREIGN KEY (`fish_stock_id`) REFERENCES `fish_stocks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fish_harvest_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fish_mortality_logs`
--
ALTER TABLE `fish_mortality_logs`
  ADD CONSTRAINT `fish_mortality_logs_ibfk_1` FOREIGN KEY (`fish_stock_id`) REFERENCES `fish_stocks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fish_mortality_logs_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fish_stocks`
--
ALTER TABLE `fish_stocks`
  ADD CONSTRAINT `fish_stocks_ibfk_1` FOREIGN KEY (`pond_id`) REFERENCES `fish_ponds` (`id`);

--
-- Constraints for table `fish_water_quality_readings`
--
ALTER TABLE `fish_water_quality_readings`
  ADD CONSTRAINT `fish_water_quality_readings_ibfk_1` FOREIGN KEY (`pond_id`) REFERENCES `fish_ponds` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fish_water_quality_readings_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD CONSTRAINT `notification_reads_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_reads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD CONSTRAINT `performance_reviews_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `performance_reviews_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `poultry_egg_collections`
--
ALTER TABLE `poultry_egg_collections`
  ADD CONSTRAINT `poultry_egg_collections_ibfk_1` FOREIGN KEY (`flock_id`) REFERENCES `poultry_flocks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `poultry_egg_collections_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `poultry_flocks`
--
ALTER TABLE `poultry_flocks`
  ADD CONSTRAINT `poultry_flocks_ibfk_1` FOREIGN KEY (`poultry_house_id`) REFERENCES `poultry_houses` (`id`);

--
-- Constraints for table `poultry_mortality_logs`
--
ALTER TABLE `poultry_mortality_logs`
  ADD CONSTRAINT `poultry_mortality_logs_ibfk_1` FOREIGN KEY (`flock_id`) REFERENCES `poultry_flocks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `poultry_mortality_logs_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `user_2fa_recovery_codes`
--
ALTER TABLE `user_2fa_recovery_codes`
  ADD CONSTRAINT `user_2fa_recovery_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vet_disease_incidents`
--
ALTER TABLE `vet_disease_incidents`
  ADD CONSTRAINT `vet_disease_incidents_ibfk_1` FOREIGN KEY (`vet_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `vet_health_records`
--
ALTER TABLE `vet_health_records`
  ADD CONSTRAINT `vet_health_records_ibfk_1` FOREIGN KEY (`vet_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `vet_vaccination_records`
--
ALTER TABLE `vet_vaccination_records`
  ADD CONSTRAINT `vet_vaccination_records_ibfk_1` FOREIGN KEY (`vet_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `wh_minimum_stock_rules`
--
ALTER TABLE `wh_minimum_stock_rules`
  ADD CONSTRAINT `wh_minimum_stock_rules_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `wh_warehouses` (`id`),
  ADD CONSTRAINT `wh_minimum_stock_rules_ibfk_2` FOREIGN KEY (`stock_item_id`) REFERENCES `wh_stock_items` (`id`);

--
-- Constraints for table `wh_stock_batches`
--
ALTER TABLE `wh_stock_batches`
  ADD CONSTRAINT `wh_stock_batches_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `wh_warehouses` (`id`),
  ADD CONSTRAINT `wh_stock_batches_ibfk_2` FOREIGN KEY (`stock_item_id`) REFERENCES `wh_stock_items` (`id`);

--
-- Constraints for table `wh_stock_issues`
--
ALTER TABLE `wh_stock_issues`
  ADD CONSTRAINT `wh_stock_issues_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `wh_warehouses` (`id`),
  ADD CONSTRAINT `wh_stock_issues_ibfk_2` FOREIGN KEY (`stock_item_id`) REFERENCES `wh_stock_items` (`id`),
  ADD CONSTRAINT `wh_stock_issues_ibfk_3` FOREIGN KEY (`stock_batch_id`) REFERENCES `wh_stock_batches` (`id`),
  ADD CONSTRAINT `wh_stock_issues_ibfk_4` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `wh_stock_receipts`
--
ALTER TABLE `wh_stock_receipts`
  ADD CONSTRAINT `wh_stock_receipts_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `wh_warehouses` (`id`),
  ADD CONSTRAINT `wh_stock_receipts_ibfk_2` FOREIGN KEY (`stock_item_id`) REFERENCES `wh_stock_items` (`id`),
  ADD CONSTRAINT `wh_stock_receipts_ibfk_3` FOREIGN KEY (`stock_batch_id`) REFERENCES `wh_stock_batches` (`id`),
  ADD CONSTRAINT `wh_stock_receipts_ibfk_4` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `wh_store_receiving`
--
ALTER TABLE `wh_store_receiving`
  ADD CONSTRAINT `wh_store_receiving_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `wh_warehouses` (`id`),
  ADD CONSTRAINT `wh_store_receiving_ibfk_2` FOREIGN KEY (`stock_item_id`) REFERENCES `wh_stock_items` (`id`),
  ADD CONSTRAINT `wh_store_receiving_ibfk_3` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `wh_store_receiving_ibfk_4` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
