-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 23, 2026 at 03:46 PM
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
-- Database: `mdrrmo_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `type` enum('general','disaster') NOT NULL DEFAULT 'general',
  `disaster_id` int(10) UNSIGNED DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `body`, `type`, `disaster_id`, `is_pinned`, `published_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'pogi ni marte', 'sdasdaasdasd', 'disaster', NULL, 0, '2026-03-07 22:48:31', 2, '2026-03-07 22:48:31', '2026-03-07 22:48:31');

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `municipality` varchar(100) NOT NULL DEFAULT 'San Ildefonso',
  `province` varchar(100) NOT NULL DEFAULT 'Bulacan',
  `center_lat` decimal(10,7) DEFAULT NULL,
  `center_lng` decimal(10,7) DEFAULT NULL,
  `boundary_polygon` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`boundary_polygon`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`id`, `name`, `municipality`, `province`, `center_lat`, `center_lng`, `boundary_polygon`, `is_active`) VALUES
(1, 'Akle', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(2, 'Alagao', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(3, 'Bagong Barrio', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(4, 'Bubulong Malaki', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(5, 'Calasag', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(6, 'Garlang', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(7, 'Makapilapil', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(8, 'Malipampang', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(9, 'Anyatam', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(10, 'Palapala', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(11, 'Basuit', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(12, 'Bubulong Munti', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(13, 'Buhol na Mangga', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(14, 'Pulong Tamo', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(15, 'Sapang Dayap', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(16, 'Sapang Putol', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(17, 'Sapang Putik', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(18, 'Bulusukan', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(19, 'Telepatio', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(20, 'Calawitan', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(21, 'Casalat', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(22, 'Gabihan', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(23, 'Lapnit', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(24, 'Maasim', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(25, 'Mataas na Parang', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(26, 'Matimbubong', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(27, 'Nabaong Garlang', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(28, 'Pasong Bakal', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(29, 'Pinaod', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(30, 'San Juan', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(31, 'Sumandig', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(32, 'Umpucan', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(33, 'Upig', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(34, 'Poblacion', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(35, 'Santa Catalina Bata', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1),
(36, 'Santa Catalina Matanda', 'San Ildefonso', 'Bulacan', NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `citizen_household`
--

CREATE TABLE `citizen_household` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `adults` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Including the account owner',
  `children` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `seniors` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `pwds` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `total_members` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Computed: adults+children+seniors+pwds',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `citizen_household`
--

INSERT INTO `citizen_household` (`id`, `user_id`, `adults`, `children`, `seniors`, `pwds`, `total_members`, `updated_at`) VALUES
(1, 3, 1, 4, 0, 0, 5, '2026-04-22 22:17:51');

-- --------------------------------------------------------

--
-- Table structure for table `disasters`
--

CREATE TABLE `disasters` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` enum('typhoon','flood','earthquake','heat','landslide','other') NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `status` enum('planned','ongoing','resolved') NOT NULL DEFAULT 'planned',
  `description` text DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `disasters`
--

INSERT INTO `disasters` (`id`, `type`, `level`, `title`, `status`, `description`, `started_at`, `ended_at`, `created_at`, `updated_at`) VALUES
(2, 'typhoon', 1, 'new update evacuee', 'ongoing', 'new update evacuee', '2026-04-23 15:39:00', NULL, '2026-04-23 15:39:47', '2026-04-23 15:39:47');

-- --------------------------------------------------------

--
-- Table structure for table `evacuation_centers`
--

CREATE TABLE `evacuation_centers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `address` varchar(255) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `max_capacity_people` int(10) UNSIGNED NOT NULL,
  `max_capacity_families` int(10) UNSIGNED DEFAULT 0,
  `status` enum('available','near_capacity','full','temp_shelter','closed') NOT NULL DEFAULT 'available',
  `coordinator_user_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evacuation_centers`
--

INSERT INTO `evacuation_centers` (`id`, `name`, `barangay_id`, `address`, `lat`, `lng`, `max_capacity_people`, `max_capacity_families`, `status`, `coordinator_user_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'sample', 6, 'San Ildefonso', 15.1112000, 120.9459000, 10, 0, 'available', 4, 'dasda', '2026-03-07 22:44:23', '2026-04-01 22:01:24'),
(2, 'ETivac Sample', 7, 'dyan lang', 15.1169570, 120.9475340, 20, 20, 'available', 5, 'dito na sila babe', '2026-03-08 00:54:43', '2026-03-27 23:14:33'),
(3, 'Sample center', 2, 'Malapit sa COurt', 15.0834100, 120.9462390, 10, 10, 'available', 5, '', '2026-03-11 22:37:47', '2026-03-27 23:14:33');

-- --------------------------------------------------------

--
-- Table structure for table `evacuation_intentions`
--

CREATE TABLE `evacuation_intentions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `evacuation_center_id` int(10) UNSIGNED NOT NULL,
  `household_size` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `adults` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `children` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `seniors` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `pwds` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('going','arrived','cancelled') NOT NULL DEFAULT 'going',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evac_navigation_tracking`
--

CREATE TABLE `evac_navigation_tracking` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `center_id` int(10) UNSIGNED NOT NULL,
  `disaster_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('navigating','arrived','cancelled') NOT NULL DEFAULT 'navigating',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evac_navigation_tracking`
--

INSERT INTO `evac_navigation_tracking` (`id`, `user_id`, `center_id`, `disaster_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 2, 'navigating', '2026-03-12 23:12:25', '2026-04-23 21:40:12');

-- --------------------------------------------------------

--
-- Table structure for table `evac_registrations`
--

CREATE TABLE `evac_registrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `center_id` int(10) UNSIGNED NOT NULL,
  `family_head_name` varchar(150) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `adults` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `children` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `seniors` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pwds` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_members` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evac_registrations_archive`
--

CREATE TABLE `evac_registrations_archive` (
  `id` int(10) UNSIGNED NOT NULL,
  `original_id` int(10) UNSIGNED NOT NULL COMMENT 'PK from evac_registrations',
  `center_id` int(10) UNSIGNED NOT NULL,
  `family_head_name` varchar(150) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `adults` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `children` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `seniors` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pwds` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_members` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL,
  `archive_label` varchar(200) NOT NULL COMMENT 'Human-readable label, e.g. Typhoon Bagyong Nonoy',
  `disaster_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Linked disaster at time of archive',
  `archived_by` int(10) UNSIGNED NOT NULL COMMENT 'Admin user who triggered the archive',
  `archived_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evac_registrations_archive`
--

INSERT INTO `evac_registrations_archive` (`id`, `original_id`, `center_id`, `family_head_name`, `contact_number`, `birthday`, `barangay_id`, `adults`, `children`, `seniors`, `pwds`, `total_members`, `created_by`, `created_at`, `archive_label`, `disaster_id`, `archived_by`, `archived_at`) VALUES
(1, 1, 1, 'Jusin pogi', NULL, NULL, 2, 2, 2, 2, 2, 8, 4, '2026-03-08 00:29:37', 'sample bagyo 1233', 1, 2, '2026-03-27 23:14:33'),
(2, 2, 1, 'sample', NULL, NULL, 5, 1, 0, 0, 0, 1, 4, '2026-04-01 21:23:29', 'updated evacuees record', 2, 2, '2026-04-23 15:56:57'),
(3, 3, 1, 'Juan Dele Cruz', NULL, NULL, 2, 2, 3, 0, 0, 5, 4, '2026-04-23 15:36:35', 'updated evacuees record', 2, 2, '2026-04-23 15:56:57'),
(5, 4, 1, 'Juan Dele Cruz', NULL, NULL, 2, 1, 4, 0, 0, 5, 4, '2026-04-23 16:54:43', 'sample version 2 with evacuee details', 2, 2, '2026-04-23 16:55:15'),
(6, 5, 1, 'james pogi', '09686971314', '2005-06-09', 7, 1, 0, 0, 0, 1, 4, '2026-04-23 18:08:51', 'update sample 3?', 2, 2, '2026-04-23 20:07:16'),
(7, 6, 1, 'Juan Dele Cruz', '09686971314', '2005-06-09', 2, 1, 3, 0, 0, 4, 4, '2026-04-23 21:27:07', 'sample demo while meeting', 2, 2, '2026-04-23 21:33:24');

-- --------------------------------------------------------

--
-- Table structure for table `family_profiles`
--

CREATE TABLE `family_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `adults` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `children` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `seniors` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `pwds` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `total_members` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `family_profiles`
--

INSERT INTO `family_profiles` (`id`, `user_id`, `adults`, `children`, `seniors`, `pwds`, `total_members`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 2, 0, 0, 3, '2026-04-23 15:34:58', '2026-04-23 21:22:49');

-- --------------------------------------------------------

--
-- Table structure for table `ready_bag_templates`
--

CREATE TABLE `ready_bag_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `disaster_type` enum('typhoon','flood','earthquake','heat','landslide','general') NOT NULL,
  `level_min` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `level_max` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ready_bag_templates`
--

INSERT INTO `ready_bag_templates` (`id`, `disaster_type`, `level_min`, `level_max`, `title`, `message`) VALUES
(1, 'typhoon', 1, 1, 'Level 1 typhoon – light rain', 'A low-level typhoon is approaching.\n\n- Bring an umbrella and raincoat when going outside.\n- Secure lightweight objects around your home.\n- Monitor MDRRMO announcements for updates.'),
(2, 'typhoon', 2, 3, 'Moderate typhoon – be prepared', 'A moderate typhoon is expected.\n\n- Prepare your go bag (water, food, flashlights, medicines, important documents).\n- Charge mobile phones and power banks.\n- Identify the nearest evacuation center in case water rises.'),
(3, 'typhoon', 4, 5, 'Severe typhoon – high risk', 'A severe typhoon is affecting San Ildefonso.\n\n- Keep your go bag ready near the door.\n- Stay indoors away from windows and glass.\n- Be ready to evacuate immediately when instructed by authorities.\n- Check on seniors, PWDs, and children in your household.'),
(4, 'flood', 1, 2, 'Flood watch – monitor water level', 'Low to moderate flooding is possible.\n\n- Monitor canals and rivers near your area.\n- Move valuables to higher shelves.\n- Avoid walking or driving through floodwater if possible.'),
(5, 'flood', 3, 5, 'Severe flooding – possible evacuation', 'Severe flooding is expected or ongoing.\n\n- Keep your go bag and important documents in a waterproof container.\n- Disconnect electrical appliances if water is entering your home.\n- Follow MDRRMO instructions on when and where to evacuate.'),
(6, 'heat', 1, 1, 'Warm weather – stay comfortable', 'Weather is warm.\n\n- Drink water regularly.\n- Use light clothing.\n- Avoid staying long in direct sunlight.'),
(7, 'heat', 2, 3, 'High heat index – take precautions', 'Heat index is high.\n\n- Drink more water than usual; avoid sugary drinks and alcohol.\n- Limit time outdoors during midday.\n- Check on children, seniors, and PWDs for signs of heat stress.'),
(8, 'heat', 4, 5, 'Extreme heat – health risk', 'Heat index is at an extreme level.\n\n- Stay indoors in cool, shaded, or air‑conditioned areas as much as possible.\n- Postpone outdoor activities.\n- Immediately seek medical help if someone feels dizzy, confused, or faints.'),
(9, 'earthquake', 1, 5, 'Earthquake preparedness', 'Earthquakes can happen without warning.\n\n- Secure heavy furniture and appliances.\n- Know safe spots to \"Drop, Cover, and Hold On\" inside your home.\n- Prepare a go bag with essentials in case evacuation is needed.'),
(10, 'general', 1, 5, 'General emergency go bag', 'For any emergency, prepare a go bag with:\n\n- Drinking water and ready‑to‑eat food\n- Flashlight and extra batteries\n- Basic medicines and first‑aid kit\n- Extra clothes and blanket\n- Copies of important documents (IDs, medical records)\n- Whistle, face masks, and hygiene items.');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `birthday` date DEFAULT NULL COMMENT 'Date of birth — used to auto-compute age',
  `sex` enum('male','female','prefer_not_to_say') DEFAULT NULL COMMENT 'Biological sex of account owner',
  `password_hash` varchar(255) NOT NULL,
  `role` enum('citizen','admin','coordinator') NOT NULL DEFAULT 'citizen',
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `house_number` varchar(50) NOT NULL,
  `is_email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `otp_code_hash` varchar(255) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `contact_number`, `birthday`, `sex`, `password_hash`, `role`, `barangay_id`, `house_number`, `is_email_verified`, `otp_code_hash`, `otp_expires_at`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'System Administrator', 'admin@system.com', NULL, NULL, NULL, '$2y$10$8x6M4nDkYq7YJ7Ew3LhF8eQxP3yP0mV5m9v0oQj7c7s8T1k1QwL7C', 'admin', 1, 'Admin Office', 1, NULL, NULL, 1, '2026-03-07 22:07:21', '2026-03-07 22:07:21'),
(2, 'System Administrator', 'admin@example.com', NULL, NULL, NULL, '$2y$10$XTTaYPYqjRG.i.YeeD/wxuSy28yygjcWz4B/InJApnGBWj/0GPQli', 'admin', 1, 'Admin Office', 1, NULL, NULL, 1, '2026-03-07 22:21:37', '2026-03-07 22:21:37'),
(3, 'Juan Dele Cruz', 'marteflores07@gmail.com', '09686971314', '2005-06-09', 'male', '$2y$10$4lt1uymzeZlUXamB1IYA4.45aXITSsqiBF5d51ySdSG11dZeUFLi.', 'citizen', 2, '0325', 1, NULL, NULL, 1, '2026-03-07 22:32:15', '2026-04-23 21:22:49'),
(4, 'coordinator1', 'martefloresjr09@gmail.com', NULL, NULL, NULL, '$2y$10$2UeQpO1nyrNfZQr2qJo0JuFvBD3ON4E2QLHD5mGFUhGU6VCkACPOG', 'coordinator', 4, '0326', 1, NULL, NULL, 1, '2026-03-07 23:40:55', '2026-03-07 23:40:55'),
(5, 'Marte Flores Jr.', 'truckflores09@gmail.com', '09686971314', NULL, NULL, '$2y$10$AUXnxr9tFqnLjLxWR8kKDe9Qs28FJGlgve66n56ucduVY0zRRE4B.', 'coordinator', 30, '0327', 1, NULL, NULL, 1, '2026-03-11 18:21:48', '2026-03-11 18:21:48'),
(10, 'sample123', 'bsau.studentjudicialreporting@gmail.com', NULL, NULL, NULL, '$2y$10$45NOdyFXf6kI00bpo1qhRecZZyABM5CEKMBLd/b33eGZ/TCEMGhfK', 'citizen', 24, '0325', 0, '$2y$10$ZSg.21vu19JKFvTA5sdR.OvqJAid7NeXSQtaNmLWvHBeR6Om1ezYC', '2026-03-28 01:25:04', 1, '2026-03-28 01:10:04', '2026-03-28 01:10:04');

-- --------------------------------------------------------

--
-- Table structure for table `weather_snapshots`
--

CREATE TABLE `weather_snapshots` (
  `id` int(10) UNSIGNED NOT NULL,
  `temp_c` decimal(5,2) NOT NULL,
  `humidity` decimal(5,2) NOT NULL,
  `heat_index` decimal(5,2) DEFAULT NULL,
  `condition_text` varchar(255) NOT NULL,
  `level` enum('low','medium','high','extreme') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `weather_snapshots`
--

INSERT INTO `weather_snapshots` (`id`, `temp_c`, `humidity`, `heat_index`, `condition_text`, `level`, `created_at`) VALUES
(1, 26.47, 74.00, 26.47, 'clear sky', 'low', '2026-03-07 23:27:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ann_disaster` (`disaster_id`),
  ADD KEY `fk_ann_creator` (`created_by`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_barangay_name` (`name`);

--
-- Indexes for table `citizen_household`
--
ALTER TABLE `citizen_household`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_household_user` (`user_id`);

--
-- Indexes for table `disasters`
--
ALTER TABLE `disasters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `evacuation_centers`
--
ALTER TABLE `evacuation_centers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_centers_barangay` (`barangay_id`),
  ADD KEY `fk_centers_coordinator` (`coordinator_user_id`);

--
-- Indexes for table `evacuation_intentions`
--
ALTER TABLE `evacuation_intentions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_active` (`user_id`,`status`),
  ADD KEY `idx_ei_center_status` (`evacuation_center_id`,`status`);

--
-- Indexes for table `evac_navigation_tracking`
--
ALTER TABLE `evac_navigation_tracking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tracking_user` (`user_id`),
  ADD KEY `fk_tracking_center` (`center_id`),
  ADD KEY `fk_tracking_disaster` (`disaster_id`),
  ADD KEY `fk_tracking_user` (`user_id`);

--
-- Indexes for table `evac_registrations`
--
ALTER TABLE `evac_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_evac_center` (`center_id`),
  ADD KEY `fk_evac_barangay` (`barangay_id`),
  ADD KEY `fk_evac_creator` (`created_by`);

--
-- Indexes for table `evac_registrations_archive`
--
ALTER TABLE `evac_registrations_archive`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_archive_disaster` (`disaster_id`),
  ADD KEY `idx_archive_center` (`center_id`),
  ADD KEY `idx_archive_archived_at` (`archived_at`);

--
-- Indexes for table `family_profiles`
--
ALTER TABLE `family_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fp_user` (`user_id`);

--
-- Indexes for table `ready_bag_templates`
--
ALTER TABLE `ready_bag_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `fk_users_barangay` (`barangay_id`);

--
-- Indexes for table `weather_snapshots`
--
ALTER TABLE `weather_snapshots`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `citizen_household`
--
ALTER TABLE `citizen_household`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `disasters`
--
ALTER TABLE `disasters`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `evacuation_centers`
--
ALTER TABLE `evacuation_centers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `evacuation_intentions`
--
ALTER TABLE `evacuation_intentions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evac_navigation_tracking`
--
ALTER TABLE `evac_navigation_tracking`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `evac_registrations`
--
ALTER TABLE `evac_registrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `evac_registrations_archive`
--
ALTER TABLE `evac_registrations_archive`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `family_profiles`
--
ALTER TABLE `family_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ready_bag_templates`
--
ALTER TABLE `ready_bag_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `weather_snapshots`
--
ALTER TABLE `weather_snapshots`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_ann_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ann_disaster` FOREIGN KEY (`disaster_id`) REFERENCES `disasters` (`id`);

--
-- Constraints for table `citizen_household`
--
ALTER TABLE `citizen_household`
  ADD CONSTRAINT `fk_household_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evacuation_centers`
--
ALTER TABLE `evacuation_centers`
  ADD CONSTRAINT `fk_centers_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `fk_centers_coordinator` FOREIGN KEY (`coordinator_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `evacuation_intentions`
--
ALTER TABLE `evacuation_intentions`
  ADD CONSTRAINT `fk_ei_center` FOREIGN KEY (`evacuation_center_id`) REFERENCES `evacuation_centers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ei_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evac_navigation_tracking`
--
ALTER TABLE `evac_navigation_tracking`
  ADD CONSTRAINT `fk_tracking_center` FOREIGN KEY (`center_id`) REFERENCES `evacuation_centers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tracking_disaster` FOREIGN KEY (`disaster_id`) REFERENCES `disasters` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tracking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evac_registrations`
--
ALTER TABLE `evac_registrations`
  ADD CONSTRAINT `fk_evac_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `fk_evac_center` FOREIGN KEY (`center_id`) REFERENCES `evacuation_centers` (`id`),
  ADD CONSTRAINT `fk_evac_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `family_profiles`
--
ALTER TABLE `family_profiles`
  ADD CONSTRAINT `fk_fp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
