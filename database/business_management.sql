-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 08, 2026 at 04:09 PM
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
-- Database: `business_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_membership_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `request_id` char(36) DEFAULT NULL,
  `ip_address` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `business_id`, `actor_user_id`, `actor_membership_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `request_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 1, NULL, 'BUSINESS_APPROVED', 'business', '1', '{\"approval_status\": \"PENDING\"}', '{\"approval_status\": \"APPROVED\"}', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1', 0x7f000001, 'seed-script/1.0', '2026-08-01 08:30:00.000000'),
(2, 1, 2, 1, 'EMPLOYEE_CREATED', 'business_membership', '3', NULL, '{\"employee_number\": \"EMP-0002\", \"role\": \"CASHIER\"}', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2', 0x7f000001, 'seed-script/1.0', '2026-08-01 09:05:00.000000'),
(3, 1, 4, 3, 'SALE_COMPLETED', 'sale', '1', NULL, '{\"sale_number\": \"SAL-2026-0001\", \"total_amount\": 12000}', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3', 0x7f000001, 'seed-script/1.0', '2026-08-05 09:15:00.000000'),
(4, 1, 5, 4, 'STOCK_TAKE_COMPLETED', 'stock_take', '1', NULL, '{\"stock_take_number\": \"ST-2026-0001\", \"water_difference\": -2, \"oil_difference\": 1}', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4', 0x7f000001, 'seed-script/1.0', '2026-08-08 07:30:00.000000');

-- --------------------------------------------------------

--
-- Table structure for table `auth_credentials`
--

CREATE TABLE `auth_credentials` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_changed_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `failed_login_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_credentials`
--

INSERT INTO `auth_credentials` (`user_id`, `password_hash`, `password_changed_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES
(1, '$2b$12$Z3d.vre5LsoKfqsaziYE7usNbC3FaZqf.WrXTBpngQOChkiaGPxYC', '2026-08-08 13:06:11.689037', 0, NULL, '2026-08-08 13:06:11.689037', '2026-08-08 13:06:11.689037'),
(2, '$2b$12$0WP1Dtea1XgTnH8Jl4sRgeXkb1aSIpsqTW8iWMPMPzdGBEsTqN40m', '2026-08-08 13:06:11.724840', 0, NULL, '2026-08-08 13:06:11.724840', '2026-08-08 13:06:11.724840'),
(3, '$2b$12$o40wxZfXmY7uU9bszECMcefF6dDIN1aoiuAr3OhpAmGQfQXIVCsAC', '2026-08-08 13:06:11.737933', 0, NULL, '2026-08-08 13:06:11.737933', '2026-08-08 13:06:11.737933'),
(4, '$2b$12$o40wxZfXmY7uU9bszECMcefF6dDIN1aoiuAr3OhpAmGQfQXIVCsAC', '2026-08-08 13:06:11.752513', 0, NULL, '2026-08-08 13:06:11.752513', '2026-08-08 13:06:11.752513'),
(5, '$2b$12$o40wxZfXmY7uU9bszECMcefF6dDIN1aoiuAr3OhpAmGQfQXIVCsAC', '2026-08-08 13:06:11.769003', 0, NULL, '2026-08-08 13:06:11.769003', '2026-08-08 13:06:11.769003'),
(6, '$2b$12$8y/LKzFSxtRTiRl.sssYjOnmvsZvE4zm.z.TooZ//pIg2lEuTzbCW', '2026-08-08 13:06:11.788833', 0, NULL, '2026-08-08 13:06:11.788833', '2026-08-08 13:06:11.788833');

-- --------------------------------------------------------

--
-- Table structure for table `batch_inventory_balances`
--

CREATE TABLE `batch_inventory_balances` (
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED NOT NULL,
  `quantity_on_hand` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `reserved_quantity` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `available_quantity` decimal(18,4) GENERATED ALWAYS AS (`quantity_on_hand` - `reserved_quantity`) STORED,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `batch_inventory_balances`
--

INSERT INTO `batch_inventory_balances` (`business_id`, `location_id`, `batch_id`, `quantity_on_hand`, `reserved_quantity`, `updated_at`) VALUES
(1, 1, 1, 60.0000, 0.0000, '2026-08-08 13:06:13.353664'),
(1, 1, 2, 40.0000, 0.0000, '2026-08-08 13:06:13.353664');

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `public_id` char(36) NOT NULL,
  `business_name` varchar(200) NOT NULL,
  `company_logo_path` varchar(500) DEFAULT NULL,
  `legal_name` varchar(200) DEFAULT NULL,
  `phone` varchar(32) NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `summary` varchar(1000) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'RW',
  `currency_code` char(3) NOT NULL DEFAULT 'RWF',
  `timezone` varchar(64) NOT NULL DEFAULT 'Africa/Kigali',
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state_region` varchar(120) DEFAULT NULL,
  `postal_code` varchar(32) DEFAULT NULL,
  `approval_status` enum('PENDING','APPROVED','REJECTED','SUSPENDED') NOT NULL DEFAULT 'PENDING',
  `submitted_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `approved_at` datetime(6) DEFAULT NULL,
  `rejected_at` datetime(6) DEFAULT NULL,
  `approved_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`id`, `public_id`, `business_name`, `company_logo_path`, `legal_name`, `phone`, `email`, `summary`, `registration_number`, `tax_number`, `country_code`, `currency_code`, `timezone`, `address_line1`, `address_line2`, `city`, `state_region`, `postal_code`, `approval_status`, `submitted_at`, `approved_at`, `rejected_at`, `approved_by_user_id`, `created_by_user_id`, `created_at`, `updated_at`) VALUES
(1, '11111111-1111-4111-8111-111111111111', 'Kigali Retail Hub', NULL, 'Kigali Retail Hub Ltd', '+250788555100', 'info@kigaliretail.demo', 'Demo multi-user retail business used for development, QA, stock, sales and reporting tests.', 'DEMO-RDB-2026-001', 'TIN-DEMO-100001', 'RW', 'RWF', 'Africa/Kigali', 'KN 5 Road', NULL, 'Kigali', 'Kigali City', NULL, 'APPROVED', '2026-08-01 08:20:00.000000', '2026-08-01 08:30:00.000000', NULL, 1, 2, '2026-08-08 13:06:11.793938', '2026-08-08 13:06:11.793938'),
(2, '22222222-2222-4222-8222-222222222222', 'Pending Demo Boutique', NULL, NULL, '+250788555200', 'pending.business@demo.local', 'Business waiting for Super Admin approval.', 'DEMO-RDB-2026-PENDING', NULL, 'RW', 'RWF', 'Africa/Kigali', NULL, NULL, 'Musanze', NULL, NULL, 'PENDING', '2026-08-08 08:00:00.000000', NULL, NULL, NULL, 6, '2026-08-08 13:06:11.836942', '2026-08-08 13:06:11.836942');

-- --------------------------------------------------------

--
-- Table structure for table `business_accounting_settings`
--

CREATE TABLE `business_accounting_settings` (
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `inventory_valuation_method` enum('WEIGHTED_AVERAGE','FIFO') NOT NULL DEFAULT 'WEIGHTED_AVERAGE',
  `default_tax_rate` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `fiscal_year_start_month` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `allow_negative_stock` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `business_accounting_settings`
--

INSERT INTO `business_accounting_settings` (`business_id`, `inventory_valuation_method`, `default_tax_rate`, `fiscal_year_start_month`, `allow_negative_stock`, `created_at`, `updated_at`) VALUES
(1, 'WEIGHTED_AVERAGE', 0.0000, 1, 0, '2026-08-08 13:06:12.093573', '2026-08-08 13:06:12.093573');

-- --------------------------------------------------------

--
-- Table structure for table `business_approval_events`
--

CREATE TABLE `business_approval_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `event_type` enum('SUBMITTED','APPROVED','REJECTED','SUSPENDED','REACTIVATED') NOT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `actor_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_approval_events`
--

INSERT INTO `business_approval_events` (`id`, `business_id`, `event_type`, `reason`, `actor_user_id`, `created_at`) VALUES
(1, 1, 'SUBMITTED', NULL, 2, '2026-08-01 08:20:00.000000'),
(2, 1, 'APPROVED', NULL, 1, '2026-08-01 08:30:00.000000'),
(3, 2, 'SUBMITTED', NULL, 6, '2026-08-08 08:00:00.000000');

-- --------------------------------------------------------

--
-- Table structure for table `business_locations`
--

CREATE TABLE `business_locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `location_type` enum('STORE','WAREHOUSE','OFFICE','OTHER') NOT NULL DEFAULT 'STORE',
  `address` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_locations`
--

INSERT INTO `business_locations` (`id`, `business_id`, `code`, `name`, `location_type`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'MAIN', 'Kigali Main Store', 'STORE', 'KN 5 Road, Kigali', 1, '2026-08-08 13:06:12.102994', '2026-08-08 13:06:12.102994'),
(2, 1, 'WH1', 'Kigali Warehouse', 'WAREHOUSE', 'KG 11 Avenue, Kigali', 1, '2026-08-08 13:06:12.113185', '2026-08-08 13:06:12.113185');

-- --------------------------------------------------------

--
-- Table structure for table `business_memberships`
--

CREATE TABLE `business_memberships` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `member_type` enum('OWNER','EMPLOYEE') NOT NULL,
  `status` enum('PENDING','ACTIVE','SUSPENDED','TERMINATED') NOT NULL DEFAULT 'PENDING',
  `joined_at` datetime(6) DEFAULT NULL,
  `invited_by_membership_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_memberships`
--

INSERT INTO `business_memberships` (`id`, `business_id`, `user_id`, `member_type`, `status`, `joined_at`, `invited_by_membership_id`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'OWNER', 'ACTIVE', '2026-08-01 08:30:00.000000', NULL, '2026-08-08 13:06:11.864456', '2026-08-08 13:06:11.864456'),
(2, 1, 3, 'EMPLOYEE', 'ACTIVE', '2026-08-01 09:00:00.000000', 1, '2026-08-08 13:06:11.886115', '2026-08-08 13:06:11.886115'),
(3, 1, 4, 'EMPLOYEE', 'ACTIVE', '2026-08-01 09:05:00.000000', 1, '2026-08-08 13:06:11.896963', '2026-08-08 13:06:11.896963'),
(4, 1, 5, 'EMPLOYEE', 'ACTIVE', '2026-08-01 09:10:00.000000', 1, '2026-08-08 13:06:11.907317', '2026-08-08 13:06:11.907317'),
(5, 2, 6, 'OWNER', 'PENDING', NULL, NULL, '2026-08-08 13:06:11.918459', '2026-08-08 13:06:11.918459');

-- --------------------------------------------------------

--
-- Table structure for table `business_roles`
--

CREATE TABLE `business_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_roles`
--

INSERT INTO `business_roles` (`id`, `business_id`, `code`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 1, 'OWNER', 'Owner', 'Full business access.', 1, '2026-08-08 13:06:11.968566', '2026-08-08 13:06:11.968566'),
(2, 1, 'MANAGER', 'Manager', 'Operational management access.', 0, '2026-08-08 13:06:11.979260', '2026-08-08 13:06:11.979260'),
(3, 1, 'CASHIER', 'Cashier', 'Point-of-sale and customer access.', 0, '2026-08-08 13:06:11.987745', '2026-08-08 13:06:11.987745'),
(4, 1, 'STOCK_KEEPER', 'Stock Keeper', 'Purchasing receipt and inventory operations.', 0, '2026-08-08 13:06:11.994110', '2026-08-08 13:06:11.994110');

-- --------------------------------------------------------

--
-- Table structure for table `business_role_permissions`
--

CREATE TABLE `business_role_permissions` (
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `business_role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_role_permissions`
--

INSERT INTO `business_role_permissions` (`business_id`, `business_role_id`, `permission_id`) VALUES
(1, 1, 6),
(1, 1, 7),
(1, 1, 8),
(1, 1, 9),
(1, 1, 10),
(1, 1, 11),
(1, 1, 12),
(1, 1, 13),
(1, 1, 14),
(1, 1, 15),
(1, 1, 16),
(1, 1, 17),
(1, 1, 18),
(1, 1, 19),
(1, 1, 20),
(1, 1, 21),
(1, 1, 22),
(1, 1, 23),
(1, 1, 24),
(1, 1, 25),
(1, 1, 26),
(1, 1, 27),
(1, 1, 28),
(1, 1, 29),
(1, 1, 30),
(1, 1, 31),
(1, 1, 32),
(1, 1, 33),
(1, 1, 34),
(1, 1, 35),
(1, 1, 36),
(1, 1, 37),
(1, 1, 38),
(1, 1, 39),
(1, 1, 40),
(1, 1, 41),
(1, 1, 42),
(1, 1, 43),
(1, 1, 44),
(1, 1, 45),
(1, 1, 46),
(1, 1, 47),
(1, 1, 48),
(1, 2, 6),
(1, 2, 7),
(1, 2, 8),
(1, 2, 9),
(1, 2, 12),
(1, 2, 13),
(1, 2, 14),
(1, 2, 15),
(1, 2, 16),
(1, 2, 17),
(1, 2, 18),
(1, 2, 19),
(1, 2, 20),
(1, 2, 21),
(1, 2, 22),
(1, 2, 23),
(1, 2, 24),
(1, 2, 25),
(1, 2, 26),
(1, 2, 27),
(1, 2, 28),
(1, 2, 29),
(1, 2, 30),
(1, 2, 31),
(1, 2, 32),
(1, 2, 33),
(1, 2, 34),
(1, 2, 35),
(1, 2, 36),
(1, 2, 38),
(1, 2, 39),
(1, 2, 41),
(1, 2, 42),
(1, 2, 43),
(1, 2, 44),
(1, 2, 45),
(1, 2, 46),
(1, 3, 6),
(1, 3, 12),
(1, 3, 18),
(1, 3, 19),
(1, 3, 20),
(1, 3, 26),
(1, 3, 27),
(1, 3, 30),
(1, 3, 42),
(1, 3, 43),
(1, 4, 6),
(1, 4, 12),
(1, 4, 13),
(1, 4, 14),
(1, 4, 15),
(1, 4, 16),
(1, 4, 17),
(1, 4, 21),
(1, 4, 22),
(1, 4, 23),
(1, 4, 25),
(1, 4, 30),
(1, 4, 31),
(1, 4, 32),
(1, 4, 42),
(1, 4, 43);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `customer_code` varchar(64) NOT NULL,
  `name` varchar(200) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `business_id`, `customer_code`, `name`, `phone`, `email`, `tax_number`, `address`, `notes`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'CUS-001', 'Jean Claude', '+250788700001', 'jean.customer@demo.local', NULL, 'Kigali', NULL, 1, '2026-08-08 13:06:12.287808', '2026-08-08 13:06:12.287808', NULL),
(2, 1, 'CUS-002', 'Umutoni Alice', '+250788700002', 'umutoni.customer@demo.local', NULL, 'Kigali', NULL, 1, '2026-08-08 13:06:12.299241', '2026-08-08 13:06:12.299241', NULL),
(3, 1, 'CUS-003', 'Mugisha Patrick', '+250788700003', 'mugisha.customer@demo.local', NULL, 'Musanze', NULL, 1, '2026-08-08 13:06:12.306910', '2026-08-08 13:06:12.306910', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee_leave_balances`
--

CREATE TABLE `employee_leave_balances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `membership_id` bigint(20) UNSIGNED NOT NULL,
  `leave_type_id` bigint(20) UNSIGNED NOT NULL,
  `leave_year` smallint(5) UNSIGNED NOT NULL,
  `allocated_days` decimal(7,2) NOT NULL DEFAULT 0.00,
  `carried_forward_days` decimal(7,2) NOT NULL DEFAULT 0.00,
  `used_days` decimal(7,2) NOT NULL DEFAULT 0.00,
  `pending_days` decimal(7,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `employee_leave_balances`
--

INSERT INTO `employee_leave_balances` (`id`, `business_id`, `membership_id`, `leave_type_id`, `leave_year`, `allocated_days`, `carried_forward_days`, `used_days`, `pending_days`, `updated_at`) VALUES
(1, 1, 2, 1, 2026, 18.00, 0.00, 2.00, 0.00, '2026-08-08 13:06:13.682811'),
(2, 1, 3, 1, 2026, 18.00, 0.00, 0.00, 3.00, '2026-08-08 13:06:13.693518'),
(3, 1, 4, 1, 2026, 18.00, 0.00, 0.00, 0.00, '2026-08-08 13:06:13.701142'),
(4, 1, 2, 2, 2026, 10.00, 0.00, 0.00, 0.00, '2026-08-08 13:06:13.709369');

-- --------------------------------------------------------

--
-- Table structure for table `employee_profiles`
--

CREATE TABLE `employee_profiles` (
  `membership_id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `employee_number` varchar(64) NOT NULL,
  `job_title` varchar(120) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `emergency_contact_name` varchar(200) DEFAULT NULL,
  `emergency_contact_phone` varchar(32) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `employee_profiles`
--

INSERT INTO `employee_profiles` (`membership_id`, `business_id`, `employee_number`, `job_title`, `department`, `hire_date`, `termination_date`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES
(2, 1, 'EMP-0001', 'Operations Manager', 'Management', '2026-08-01', NULL, 'Demo Contact', '+250780900001', '2026-08-08 13:06:11.933603', '2026-08-08 13:06:11.933603'),
(3, 1, 'EMP-0002', 'Cashier', 'Sales', '2026-08-01', NULL, 'Demo Contact', '+250780900002', '2026-08-08 13:06:11.952143', '2026-08-08 13:06:11.952143'),
(4, 1, 'EMP-0003', 'Storekeeper', 'Inventory', '2026-08-01', NULL, 'Demo Contact', '+250780900003', '2026-08-08 13:06:11.960877', '2026-08-08 13:06:11.960877');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `expense_category_id` bigint(20) UNSIGNED NOT NULL,
  `expense_number` varchar(64) NOT NULL,
  `expense_date` datetime(6) NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(19,4) NOT NULL,
  `payment_method` enum('CASH','CARD','BANK_TRANSFER','MOBILE_MONEY','CHEQUE','OTHER') DEFAULT NULL,
  `payee` varchar(200) DEFAULT NULL,
  `description` varchar(2000) DEFAULT NULL,
  `receipt_reference` varchar(255) DEFAULT NULL,
  `status` enum('DRAFT','POSTED','VOIDED') NOT NULL DEFAULT 'POSTED',
  `recorded_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `approved_by_membership_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `business_id`, `location_id`, `expense_category_id`, `expense_number`, `expense_date`, `amount`, `tax_amount`, `total_amount`, `payment_method`, `payee`, `description`, `receipt_reference`, `status`, `recorded_by_membership_id`, `approved_by_membership_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'EXP-2026-0001', '2026-08-01 10:30:00.000000', 150000.0000, 0.0000, 150000.0000, 'BANK_TRANSFER', 'Demo Property Ltd', 'August shop rent', 'RENT-AUG-2026', 'POSTED', 1, 1, '2026-08-08 13:06:13.405233', '2026-08-08 13:06:13.405233'),
(2, 1, 1, 2, 'EXP-2026-0002', '2026-08-05 14:00:00.000000', 45000.0000, 0.0000, 45000.0000, 'MOBILE_MONEY', 'Utility Provider', 'Electricity and water expense', 'UTIL-0805', 'POSTED', 2, 1, '2026-08-08 13:06:13.418459', '2026-08-08 13:06:13.418459'),
(3, 1, 2, 3, 'EXP-2026-0003', '2026-08-07 10:00:00.000000', 20000.0000, 0.0000, 20000.0000, 'CASH', 'Local Transport', 'Stock delivery transport', 'TRANS-0807', 'POSTED', 4, 2, '2026-08-08 13:06:13.429933', '2026-08-08 13:06:13.429933');

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `business_id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 1, 'Rent', 'Premises rent and occupancy costs', 1, '2026-08-08 13:06:13.363287'),
(2, 1, 'Utilities', 'Electricity, water and other utilities', 1, '2026-08-08 13:06:13.373371'),
(3, 1, 'Transport', 'Delivery and business transport costs', 1, '2026-08-08 13:06:13.381363'),
(4, 1, 'Marketing', 'Advertising and promotion', 1, '2026-08-08 13:06:13.387875');

-- --------------------------------------------------------

--
-- Table structure for table `generated_reports`
--

CREATE TABLE `generated_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `report_schedule_id` bigint(20) UNSIGNED DEFAULT NULL,
  `report_type` enum('BUSINESS_SUMMARY','INVENTORY','SALES','PURCHASES','EXPENSES','PROFIT_LOSS','LEAVE') NOT NULL,
  `period_start` datetime(6) NOT NULL,
  `period_end` datetime(6) NOT NULL,
  `status` enum('GENERATING','READY','FAILED') NOT NULL DEFAULT 'GENERATING',
  `total_purchases` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_sales` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_revenue` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_cogs` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_expenses` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `inventory_loss_value` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `gross_profit` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `net_profit_loss` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `file_uri` varchar(1000) DEFAULT NULL,
  `error_message` varchar(2000) DEFAULT NULL,
  `generated_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

--
-- Dumping data for table `generated_reports`
--

INSERT INTO `generated_reports` (`id`, `business_id`, `report_schedule_id`, `report_type`, `period_start`, `period_end`, `status`, `total_purchases`, `total_sales`, `total_revenue`, `total_cogs`, `total_expenses`, `inventory_loss_value`, `gross_profit`, `net_profit_loss`, `file_uri`, `error_message`, `generated_at`, `created_at`) VALUES
(1, 1, 3, 'PROFIT_LOSS', '2026-08-01 00:00:00.000000', '2026-08-08 12:00:00.000000', 'READY', 573800.0000, 71600.0000, 71600.0000, 53300.0015, 215000.0000, 4513.3334, 18299.9985, -201213.3349, 'seed://reports/kigali-retail-2026-08-08-profit-loss.pdf', NULL, '2026-08-08 12:05:00.000000', '2026-08-08 13:06:13.536034');

-- --------------------------------------------------------

--
-- Table structure for table `idempotency_keys`
--

CREATE TABLE `idempotency_keys` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `idempotency_key` varchar(128) NOT NULL,
  `operation` varchar(100) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `response_code` smallint(5) UNSIGNED DEFAULT NULL,
  `response_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_body`)),
  `expires_at` datetime(6) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `idempotency_keys`
--

INSERT INTO `idempotency_keys` (`id`, `business_id`, `idempotency_key`, `operation`, `request_hash`, `response_code`, `response_body`, `expires_at`, `created_at`) VALUES
(1, 1, 'seed-sale-request-0001', 'CREATE_SALE', '32653453b3fcafd6fb914c5f24f5e1cf7efcbc2018d785dbc3bf691dde36d56a', 201, '{\"sale_number\": \"SAL-2026-0001\", \"status\": \"COMPLETED\"}', '2026-09-08 00:00:00.000000', '2026-08-08 13:06:13.837582');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_balances`
--

CREATE TABLE `inventory_balances` (
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `quantity_on_hand` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `reserved_quantity` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `available_quantity` decimal(18,4) GENERATED ALWAYS AS (`quantity_on_hand` - `reserved_quantity`) STORED,
  `average_unit_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `stock_value` decimal(23,4) GENERATED ALWAYS AS (`quantity_on_hand` * `average_unit_cost`) STORED,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `inventory_balances`
--

INSERT INTO `inventory_balances` (`business_id`, `location_id`, `product_id`, `quantity_on_hand`, `reserved_quantity`, `average_unit_cost`, `updated_at`) VALUES
(1, 1, 1, 118.0000, 0.0000, 506.6667, '2026-08-08 13:06:13.271809'),
(1, 1, 2, 28.0000, 0.0000, 8000.0000, '2026-08-08 13:06:13.271809'),
(1, 1, 3, 48.0000, 0.0000, 2500.0000, '2026-08-08 13:06:13.271809'),
(1, 1, 4, 100.0000, 0.0000, 706.6667, '2026-08-08 13:06:13.271809'),
(1, 1, 5, 55.0000, 0.0000, 800.0000, '2026-08-08 13:06:13.271809'),
(1, 2, 1, 40.0000, 0.0000, 500.0000, '2026-08-08 13:06:13.271809'),
(1, 2, 5, 20.0000, 0.0000, 800.0000, '2026-08-08 13:06:13.271809');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_cost_allocations`
--

CREATE TABLE `inventory_cost_allocations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `sale_item_id` bigint(20) UNSIGNED NOT NULL,
  `cost_layer_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` decimal(18,4) NOT NULL,
  `unit_cost` decimal(19,4) NOT NULL,
  `cogs_amount` decimal(23,4) GENERATED ALWAYS AS (`quantity` * `unit_cost`) STORED,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_cost_layers`
--

CREATE TABLE `inventory_cost_layers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purchase_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `received_at` datetime(6) NOT NULL,
  `original_quantity` decimal(18,4) NOT NULL,
  `remaining_quantity` decimal(18,4) NOT NULL,
  `unit_cost` decimal(19,4) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `movement_type` enum('PURCHASE_RECEIPT','PURCHASE_RETURN','SALE','SALE_RETURN','MANUAL_IN','MANUAL_OUT','STOCKTAKE_GAIN','STOCKTAKE_LOSS','DAMAGE','EXPIRY','OPENING','CORRECTION_IN','CORRECTION_OUT') NOT NULL,
  `quantity_delta` decimal(18,4) NOT NULL,
  `unit_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `occurred_at` datetime(6) NOT NULL,
  `purchase_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purchase_return_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_return_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `stock_adjustment_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `stock_take_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

--
-- Dumping data for table `inventory_movements`
--

INSERT INTO `inventory_movements` (`id`, `business_id`, `location_id`, `product_id`, `batch_id`, `movement_type`, `quantity_delta`, `unit_cost`, `occurred_at`, `purchase_item_id`, `purchase_return_item_id`, `sale_item_id`, `sale_return_item_id`, `stock_adjustment_item_id`, `stock_take_item_id`, `created_by_membership_id`, `notes`, `created_at`) VALUES
(1, 1, 1, 1, NULL, 'PURCHASE_RECEIPT', 100.0000, 500.0000, '2026-08-02 09:00:00.000000', 1, NULL, NULL, NULL, NULL, NULL, 4, 'PUR-2026-0001 received', '2026-08-08 13:06:12.705192'),
(2, 1, 1, 2, NULL, 'PURCHASE_RECEIPT', 30.0000, 8000.0000, '2026-08-02 09:00:00.000000', 2, NULL, NULL, NULL, NULL, NULL, 4, 'PUR-2026-0001 received', '2026-08-08 13:06:12.727981'),
(3, 1, 1, 3, NULL, 'PURCHASE_RECEIPT', 50.0000, 2500.0000, '2026-08-02 09:00:00.000000', 3, NULL, NULL, NULL, NULL, NULL, 4, 'PUR-2026-0001 received', '2026-08-08 13:06:12.739323'),
(4, 1, 1, 4, 1, 'PURCHASE_RECEIPT', 80.0000, 700.0000, '2026-08-02 09:00:00.000000', 4, NULL, NULL, NULL, NULL, NULL, 4, 'PUR-2026-0001 received', '2026-08-08 13:06:12.748921'),
(5, 1, 1, 5, NULL, 'PURCHASE_RECEIPT', 60.0000, 800.0000, '2026-08-02 09:00:00.000000', 5, NULL, NULL, NULL, NULL, NULL, 4, 'PUR-2026-0001 received', '2026-08-08 13:06:12.759142'),
(6, 1, 1, 1, NULL, 'PURCHASE_RECEIPT', 50.0000, 520.0000, '2026-08-04 08:00:00.000000', 6, NULL, NULL, NULL, NULL, NULL, 4, 'PUR-2026-0002 received', '2026-08-08 13:06:12.769238'),
(7, 1, 1, 4, 2, 'PURCHASE_RECEIPT', 40.0000, 720.0000, '2026-08-04 08:00:00.000000', 7, NULL, NULL, NULL, NULL, NULL, 4, 'PUR-2026-0002 received', '2026-08-08 13:06:12.779986'),
(8, 1, 1, 1, NULL, 'SALE', -10.0000, 506.6667, '2026-08-05 09:15:00.000000', NULL, NULL, 1, NULL, NULL, NULL, 3, 'SAL-2026-0001', '2026-08-08 13:06:12.872670'),
(9, 1, 1, 4, 1, 'SALE', -5.0000, 706.6667, '2026-08-05 09:15:00.000000', NULL, NULL, 2, NULL, NULL, NULL, 3, 'SAL-2026-0001', '2026-08-08 13:06:12.884564'),
(10, 1, 1, 2, NULL, 'SALE', -2.0000, 8000.0000, '2026-08-06 12:30:00.000000', NULL, NULL, 3, NULL, NULL, NULL, 3, 'SAL-2026-0002', '2026-08-08 13:06:12.952904'),
(11, 1, 1, 3, NULL, 'SALE', -3.0000, 2500.0000, '2026-08-06 12:30:00.000000', NULL, NULL, 4, NULL, NULL, NULL, 3, 'SAL-2026-0002', '2026-08-08 13:06:12.961937'),
(12, 1, 1, 5, NULL, 'SALE', -5.0000, 800.0000, '2026-08-06 12:30:00.000000', NULL, NULL, 5, NULL, NULL, NULL, 3, 'SAL-2026-0002', '2026-08-08 13:06:12.970950'),
(13, 1, 1, 4, 1, 'SALE', -10.0000, 706.6667, '2026-08-07 15:20:00.000000', NULL, NULL, 6, NULL, NULL, NULL, 2, 'SAL-2026-0003', '2026-08-08 13:06:13.030631'),
(14, 1, 1, 1, NULL, 'SALE', -20.0000, 506.6667, '2026-08-07 15:20:00.000000', NULL, NULL, 7, NULL, NULL, NULL, 2, 'SAL-2026-0003', '2026-08-08 13:06:13.040122'),
(15, 1, 2, 1, NULL, 'OPENING', 40.0000, 500.0000, '2026-08-01 10:00:00.000000', NULL, NULL, NULL, NULL, 1, NULL, 4, 'Opening stock', '2026-08-08 13:06:13.096756'),
(16, 1, 2, 5, NULL, 'OPENING', 20.0000, 800.0000, '2026-08-01 10:00:00.000000', NULL, NULL, NULL, NULL, 2, NULL, 4, 'Opening stock', '2026-08-08 13:06:13.106211'),
(17, 1, 1, 4, 1, 'DAMAGE', -2.0000, 700.0000, '2026-08-07 17:00:00.000000', NULL, NULL, NULL, NULL, 3, NULL, 4, 'Damaged units removed from saleable stock', '2026-08-08 13:06:13.145847'),
(18, 1, 1, 4, 1, 'EXPIRY', -3.0000, 700.0000, '2026-08-08 06:30:00.000000', NULL, NULL, NULL, NULL, 4, NULL, 4, 'Expiry workflow test', '2026-08-08 13:06:13.182319'),
(19, 1, 1, 1, NULL, 'STOCKTAKE_LOSS', -2.0000, 506.6667, '2026-08-08 07:30:00.000000', NULL, NULL, NULL, NULL, NULL, 1, 4, 'ST-2026-0001 shortage', '2026-08-08 13:06:13.237285'),
(20, 1, 1, 3, NULL, 'STOCKTAKE_GAIN', 1.0000, 2500.0000, '2026-08-08 07:30:00.000000', NULL, NULL, NULL, NULL, NULL, 2, 4, 'ST-2026-0001 gain', '2026-08-08 13:06:13.248178');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `membership_id` bigint(20) UNSIGNED NOT NULL,
  `leave_type_id` bigint(20) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `requested_days` decimal(7,2) NOT NULL,
  `reason` varchar(2000) DEFAULT NULL,
  `status` enum('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `current_approver_membership_id` bigint(20) UNSIGNED DEFAULT NULL,
  `submitted_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `decided_at` datetime(6) DEFAULT NULL,
  `decision_note` varchar(1000) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `business_id`, `membership_id`, `leave_type_id`, `start_date`, `end_date`, `requested_days`, `reason`, `status`, `current_approver_membership_id`, `submitted_at`, `decided_at`, `decision_note`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 1, '2026-07-20', '2026-07-21', 2.00, 'Personal leave', 'APPROVED', 1, '2026-07-10 08:00:00.000000', '2026-07-10 10:00:00.000000', 'Approved', '2026-08-08 13:06:13.717224', '2026-08-08 13:06:13.717224'),
(2, 1, 3, 1, '2026-08-17', '2026-08-19', 3.00, 'Family commitment', 'PENDING', 2, '2026-08-08 08:15:00.000000', NULL, NULL, '2026-08-08 13:06:13.765493', '2026-08-08 13:06:13.765493');

-- --------------------------------------------------------

--
-- Table structure for table `leave_request_actions`
--

CREATE TABLE `leave_request_actions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `leave_request_id` bigint(20) UNSIGNED NOT NULL,
  `action` enum('SUBMITTED','APPROVED','REJECTED','CANCELLED','COMMENTED') NOT NULL,
  `actor_membership_id` bigint(20) UNSIGNED NOT NULL,
  `comment` varchar(1000) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leave_request_actions`
--

INSERT INTO `leave_request_actions` (`id`, `business_id`, `leave_request_id`, `action`, `actor_membership_id`, `comment`, `created_at`) VALUES
(1, 1, 1, 'SUBMITTED', 2, 'Submitted annual leave', '2026-07-10 08:00:00.000000'),
(2, 1, 1, 'APPROVED', 1, 'Approved', '2026-07-10 10:00:00.000000'),
(3, 1, 2, 'SUBMITTED', 3, 'Request submitted for approval', '2026-08-08 08:15:00.000000');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(120) NOT NULL,
  `default_days_per_year` decimal(7,2) NOT NULL DEFAULT 0.00,
  `is_paid` tinyint(1) NOT NULL DEFAULT 1,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `business_id`, `code`, `name`, `default_days_per_year`, `is_paid`, `requires_approval`, `is_active`, `created_at`) VALUES
(1, 1, 'ANNUAL', 'Annual Leave', 18.00, 1, 1, 1, '2026-08-08 13:06:13.650824'),
(2, 1, 'SICK', 'Sick Leave', 10.00, 1, 1, 1, '2026-08-08 13:06:13.661411'),
(3, 1, 'UNPAID', 'Unpaid Leave', 0.00, 0, 1, 1, '2026-08-08 13:06:13.670092');

-- --------------------------------------------------------

--
-- Table structure for table `membership_permission_overrides`
--

CREATE TABLE `membership_permission_overrides` (
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `membership_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `effect` enum('ALLOW','DENY') NOT NULL,
  `assigned_by_membership_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `membership_permission_overrides`
--

INSERT INTO `membership_permission_overrides` (`business_id`, `membership_id`, `permission_id`, `effect`, `assigned_by_membership_id`, `created_at`) VALUES
(1, 3, 29, 'DENY', 1, '2026-08-08 13:06:12.085611'),
(1, 3, 38, 'ALLOW', 1, '2026-08-08 13:06:12.065974');

-- --------------------------------------------------------

--
-- Table structure for table `membership_roles`
--

CREATE TABLE `membership_roles` (
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `membership_id` bigint(20) UNSIGNED NOT NULL,
  `business_role_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_by_membership_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `membership_roles`
--

INSERT INTO `membership_roles` (`business_id`, `membership_id`, `business_role_id`, `assigned_by_membership_id`, `assigned_at`) VALUES
(1, 1, 1, 1, '2026-08-08 13:06:12.052645'),
(1, 2, 2, 1, '2026-08-08 13:06:12.052645'),
(1, 3, 3, 1, '2026-08-08 13:06:12.052645'),
(1, 4, 4, 1, '2026-08-08 13:06:12.052645');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `scope` enum('PLATFORM','BUSINESS') NOT NULL,
  `code` varchar(100) NOT NULL,
  `module` varchar(64) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `scope`, `code`, `module`, `name`, `description`, `created_at`) VALUES
(1, 'PLATFORM', 'platform.businesses.view', 'platform', 'View business registrations', NULL, '2026-08-08 13:06:11.165622'),
(2, 'PLATFORM', 'platform.businesses.approve', 'platform', 'Approve business registrations', NULL, '2026-08-08 13:06:11.165622'),
(3, 'PLATFORM', 'platform.businesses.reject', 'platform', 'Reject business registrations', NULL, '2026-08-08 13:06:11.165622'),
(4, 'PLATFORM', 'platform.businesses.suspend', 'platform', 'Suspend businesses', NULL, '2026-08-08 13:06:11.165622'),
(5, 'PLATFORM', 'platform.users.view', 'platform', 'View platform users', NULL, '2026-08-08 13:06:11.165622'),
(6, 'BUSINESS', 'dashboard.view', 'dashboard', 'View dashboard', NULL, '2026-08-08 13:06:11.165622'),
(7, 'BUSINESS', 'employees.view', 'employees', 'View employees', NULL, '2026-08-08 13:06:11.165622'),
(8, 'BUSINESS', 'employees.create', 'employees', 'Create employees', NULL, '2026-08-08 13:06:11.165622'),
(9, 'BUSINESS', 'employees.update', 'employees', 'Update employees', NULL, '2026-08-08 13:06:11.165622'),
(10, 'BUSINESS', 'employees.suspend', 'employees', 'Suspend employees', NULL, '2026-08-08 13:06:11.165622'),
(11, 'BUSINESS', 'employees.permissions.manage', 'employees', 'Manage employee roles and permissions', NULL, '2026-08-08 13:06:11.165622'),
(12, 'BUSINESS', 'products.view', 'products', 'View products', NULL, '2026-08-08 13:06:11.165622'),
(13, 'BUSINESS', 'products.create', 'products', 'Create products', NULL, '2026-08-08 13:06:11.165622'),
(14, 'BUSINESS', 'products.update', 'products', 'Update products', NULL, '2026-08-08 13:06:11.165622'),
(15, 'BUSINESS', 'suppliers.view', 'suppliers', 'View suppliers', NULL, '2026-08-08 13:06:11.165622'),
(16, 'BUSINESS', 'suppliers.create', 'suppliers', 'Create suppliers', NULL, '2026-08-08 13:06:11.165622'),
(17, 'BUSINESS', 'suppliers.update', 'suppliers', 'Update suppliers', NULL, '2026-08-08 13:06:11.165622'),
(18, 'BUSINESS', 'customers.view', 'customers', 'View customers', NULL, '2026-08-08 13:06:11.165622'),
(19, 'BUSINESS', 'customers.create', 'customers', 'Create customers', NULL, '2026-08-08 13:06:11.165622'),
(20, 'BUSINESS', 'customers.update', 'customers', 'Update customers', NULL, '2026-08-08 13:06:11.165622'),
(21, 'BUSINESS', 'purchases.view', 'purchases', 'View purchases', NULL, '2026-08-08 13:06:11.165622'),
(22, 'BUSINESS', 'purchases.create', 'purchases', 'Create purchases', NULL, '2026-08-08 13:06:11.165622'),
(23, 'BUSINESS', 'purchases.update', 'purchases', 'Update draft purchases', NULL, '2026-08-08 13:06:11.165622'),
(24, 'BUSINESS', 'purchases.approve', 'purchases', 'Approve purchases', NULL, '2026-08-08 13:06:11.165622'),
(25, 'BUSINESS', 'purchases.receive', 'purchases', 'Receive purchased stock', NULL, '2026-08-08 13:06:11.165622'),
(26, 'BUSINESS', 'sales.view', 'sales', 'View sales', NULL, '2026-08-08 13:06:11.165622'),
(27, 'BUSINESS', 'sales.create', 'sales', 'Create sales', NULL, '2026-08-08 13:06:11.165622'),
(28, 'BUSINESS', 'sales.void', 'sales', 'Void completed sales', NULL, '2026-08-08 13:06:11.165622'),
(29, 'BUSINESS', 'sales.refund', 'sales', 'Process sales returns/refunds', NULL, '2026-08-08 13:06:11.165622'),
(30, 'BUSINESS', 'inventory.view', 'inventory', 'View inventory', NULL, '2026-08-08 13:06:11.165622'),
(31, 'BUSINESS', 'inventory.adjust', 'inventory', 'Create stock adjustments', NULL, '2026-08-08 13:06:11.165622'),
(32, 'BUSINESS', 'inventory.stocktake', 'inventory', 'Perform stock takes', NULL, '2026-08-08 13:06:11.165622'),
(33, 'BUSINESS', 'inventory.approve', 'inventory', 'Approve stock adjustments/stock takes', NULL, '2026-08-08 13:06:11.165622'),
(34, 'BUSINESS', 'expenses.view', 'expenses', 'View expenses', NULL, '2026-08-08 13:06:11.165622'),
(35, 'BUSINESS', 'expenses.create', 'expenses', 'Create expenses', NULL, '2026-08-08 13:06:11.165622'),
(36, 'BUSINESS', 'expenses.approve', 'expenses', 'Approve expenses', NULL, '2026-08-08 13:06:11.165622'),
(37, 'BUSINESS', 'expenses.void', 'expenses', 'Void expenses', NULL, '2026-08-08 13:06:11.165622'),
(38, 'BUSINESS', 'reports.view', 'reports', 'View reports', NULL, '2026-08-08 13:06:11.165622'),
(39, 'BUSINESS', 'reports.generate', 'reports', 'Generate reports', NULL, '2026-08-08 13:06:11.165622'),
(40, 'BUSINESS', 'reports.schedule', 'reports', 'Manage scheduled reports', NULL, '2026-08-08 13:06:11.165622'),
(41, 'BUSINESS', 'reports.export', 'reports', 'Export reports', NULL, '2026-08-08 13:06:11.165622'),
(42, 'BUSINESS', 'leave.self.create', 'leave', 'Submit own leave request', NULL, '2026-08-08 13:06:11.165622'),
(43, 'BUSINESS', 'leave.self.view', 'leave', 'View own leave requests/history', NULL, '2026-08-08 13:06:11.165622'),
(44, 'BUSINESS', 'leave.team.view', 'leave', 'View employee leave requests', NULL, '2026-08-08 13:06:11.165622'),
(45, 'BUSINESS', 'leave.approve', 'leave', 'Approve or reject leave requests', NULL, '2026-08-08 13:06:11.165622'),
(46, 'BUSINESS', 'settings.view', 'settings', 'View business settings', NULL, '2026-08-08 13:06:11.165622'),
(47, 'BUSINESS', 'settings.update', 'settings', 'Update business settings', NULL, '2026-08-08 13:06:11.165622'),
(48, 'BUSINESS', 'audit.view', 'audit', 'View business audit logs', NULL, '2026-08-08 13:06:11.165622');

-- --------------------------------------------------------

--
-- Table structure for table `platform_roles`
--

CREATE TABLE `platform_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_roles`
--

INSERT INTO `platform_roles` (`id`, `code`, `name`, `is_system`, `created_at`) VALUES
(1, 'SUPER_ADMIN', 'Super Admin', 1, '2026-08-08 13:06:11.328851');

-- --------------------------------------------------------

--
-- Table structure for table `platform_role_permissions`
--

CREATE TABLE `platform_role_permissions` (
  `platform_role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_role_permissions`
--

INSERT INTO `platform_role_permissions` (`platform_role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `uom_id` bigint(20) UNSIGNED NOT NULL,
  `sku` varchar(100) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `default_purchase_price` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `default_selling_price` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `reorder_level` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `track_batches` tinyint(1) NOT NULL DEFAULT 0,
  `track_expiry` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL
) ;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `business_id`, `category_id`, `uom_id`, `sku`, `barcode`, `name`, `description`, `default_purchase_price`, `default_selling_price`, `reorder_level`, `track_batches`, `track_expiry`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 'WATER-500', '616100000001', 'Mineral Water 500ml', 'Bottled mineral water', 500.0000, 700.0000, 30.0000, 0, 0, 1, '2026-08-08 13:06:12.175415', '2026-08-08 13:06:12.175415', NULL),
(2, 1, 2, 1, 'RICE-5KG', '616100000002', 'Premium Rice 5kg', 'Five kilogram rice bag', 8000.0000, 10000.0000, 10.0000, 0, 0, 1, '2026-08-08 13:06:12.187247', '2026-08-08 13:06:12.187247', NULL),
(3, 1, 2, 1, 'OIL-1L', '616100000003', 'Cooking Oil 1L', 'One litre cooking oil', 2500.0000, 3200.0000, 15.0000, 0, 0, 1, '2026-08-08 13:06:12.195109', '2026-08-08 13:06:12.195109', NULL),
(4, 1, 1, 1, 'MILK-500', '616100000004', 'Fresh Milk 500ml', 'Fresh milk with batch and expiry tracking', 700.0000, 1000.0000, 25.0000, 1, 1, 1, '2026-08-08 13:06:12.203373', '2026-08-08 13:06:12.203373', NULL),
(5, 1, 3, 1, 'SOAP-100', '616100000005', 'Bath Soap 100g', 'Personal care soap bar', 800.0000, 1200.0000, 20.0000, 0, 0, 1, '2026-08-08 13:06:12.211836', '2026-08-08 13:06:12.211836', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_batches`
--

CREATE TABLE `product_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `lot_number` varchar(100) NOT NULL,
  `manufactured_at` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

--
-- Dumping data for table `product_batches`
--

INSERT INTO `product_batches` (`id`, `business_id`, `product_id`, `lot_number`, `manufactured_at`, `expires_at`, `created_at`) VALUES
(1, 1, 4, 'MILK-2026-08-A', '2026-08-01', '2026-09-15', '2026-08-08 13:06:12.232724'),
(2, 1, 4, 'MILK-2026-08-B', '2026-08-03', '2026-10-15', '2026-08-08 13:06:12.244970');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `business_id`, `parent_id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Beverages', 'Drinks and liquid refreshments', 1, '2026-08-08 13:06:12.138890', '2026-08-08 13:06:12.138890'),
(2, 1, NULL, 'Groceries', 'General grocery products', 1, '2026-08-08 13:06:12.151051', '2026-08-08 13:06:12.151051'),
(3, 1, NULL, 'Personal Care', 'Personal and household care products', 1, '2026-08-08 13:06:12.160060', '2026-08-08 13:06:12.160060');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_number` varchar(64) NOT NULL,
  `supplier_invoice_number` varchar(100) DEFAULT NULL,
  `status` enum('DRAFT','ORDERED','PARTIALLY_RECEIVED','RECEIVED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `purchase_date` datetime(6) NOT NULL,
  `expected_date` date DEFAULT NULL,
  `received_at` datetime(6) DEFAULT NULL,
  `subtotal` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `shipping_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `amount_paid` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `notes` varchar(2000) DEFAULT NULL,
  `created_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `approved_by_membership_id` bigint(20) UNSIGNED DEFAULT NULL,
  `received_by_membership_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `business_id`, `location_id`, `supplier_id`, `purchase_number`, `supplier_invoice_number`, `status`, `purchase_date`, `expected_date`, `received_at`, `subtotal`, `discount_amount`, `tax_amount`, `shipping_amount`, `total_amount`, `amount_paid`, `notes`, `created_by_membership_id`, `approved_by_membership_id`, `received_by_membership_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'PUR-2026-0001', 'INV-RWD-1001', 'RECEIVED', '2026-08-02 07:30:00.000000', '2026-08-02', '2026-08-02 09:00:00.000000', 519000.0000, 0.0000, 0.0000, 0.0000, 519000.0000, 519000.0000, 'Initial August stock purchase', 4, 1, 4, '2026-08-08 13:06:12.321639', '2026-08-08 13:06:12.321639'),
(2, 1, 1, 2, 'PUR-2026-0002', 'INV-FFR-2001', 'RECEIVED', '2026-08-04 06:45:00.000000', '2026-08-04', '2026-08-04 08:00:00.000000', 54800.0000, 0.0000, 0.0000, 0.0000, 54800.0000, 54800.0000, 'Replenishment purchase', 4, 2, 4, '2026-08-08 13:06:12.652341', '2026-08-08 13:06:12.652341');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ordered_quantity` decimal(18,4) NOT NULL,
  `received_quantity` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` decimal(19,4) NOT NULL,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(19,4) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `business_id`, `purchase_id`, `product_id`, `batch_id`, `ordered_quantity`, `received_quantity`, `unit_cost`, `discount_amount`, `tax_amount`, `line_total`, `created_at`) VALUES
(1, 1, 1, 1, NULL, 100.0000, 100.0000, 500.0000, 0.0000, 0.0000, 50000.0000, '2026-08-08 13:06:12.452623'),
(2, 1, 1, 2, NULL, 30.0000, 30.0000, 8000.0000, 0.0000, 0.0000, 240000.0000, '2026-08-08 13:06:12.581176'),
(3, 1, 1, 3, NULL, 50.0000, 50.0000, 2500.0000, 0.0000, 0.0000, 125000.0000, '2026-08-08 13:06:12.591962'),
(4, 1, 1, 4, 1, 80.0000, 80.0000, 700.0000, 0.0000, 0.0000, 56000.0000, '2026-08-08 13:06:12.601886'),
(5, 1, 1, 5, NULL, 60.0000, 60.0000, 800.0000, 0.0000, 0.0000, 48000.0000, '2026-08-08 13:06:12.611188'),
(6, 1, 2, 1, NULL, 50.0000, 50.0000, 520.0000, 0.0000, 0.0000, 26000.0000, '2026-08-08 13:06:12.669657'),
(7, 1, 2, 4, 2, 40.0000, 40.0000, 720.0000, 0.0000, 0.0000, 28800.0000, '2026-08-08 13:06:12.678411');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_payments`
--

CREATE TABLE `purchase_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  `payment_method` enum('CASH','CARD','BANK_TRANSFER','MOBILE_MONEY','CHEQUE','OTHER') NOT NULL,
  `reference_number` varchar(120) DEFAULT NULL,
  `paid_at` datetime(6) NOT NULL,
  `recorded_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

--
-- Dumping data for table `purchase_payments`
--

INSERT INTO `purchase_payments` (`id`, `business_id`, `purchase_id`, `amount`, `payment_method`, `reference_number`, `paid_at`, `recorded_by_membership_id`, `notes`, `created_at`) VALUES
(1, 1, 1, 519000.0000, 'BANK_TRANSFER', 'BANK-DEMO-PUR-0001', '2026-08-02 09:10:00.000000', 1, 'Full payment', '2026-08-08 13:06:12.639444'),
(2, 1, 2, 54800.0000, 'MOBILE_MONEY', 'MOMO-DEMO-PUR-0002', '2026-08-04 08:05:00.000000', 2, 'Full payment', '2026-08-08 13:06:12.693852');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_returns`
--

CREATE TABLE `purchase_returns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_id` bigint(20) UNSIGNED NOT NULL,
  `return_number` varchar(64) NOT NULL,
  `return_date` datetime(6) NOT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `status` enum('DRAFT','COMPLETED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `created_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_return_items`
--

CREATE TABLE `purchase_return_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_return_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_item_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` decimal(18,4) NOT NULL,
  `unit_cost` decimal(19,4) NOT NULL,
  `line_total` decimal(19,4) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `report_deliveries`
--

CREATE TABLE `report_deliveries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `generated_report_id` bigint(20) UNSIGNED NOT NULL,
  `channel` enum('EMAIL') NOT NULL,
  `destination` varchar(254) NOT NULL,
  `status` enum('PENDING','SENT','FAILED') NOT NULL DEFAULT 'PENDING',
  `provider_message_id` varchar(255) DEFAULT NULL,
  `attempted_at` datetime(6) DEFAULT NULL,
  `sent_at` datetime(6) DEFAULT NULL,
  `error_message` varchar(1000) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_delivery_settings`
--

CREATE TABLE `report_delivery_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `smtp_host` varchar(255) NOT NULL,
  `smtp_port` smallint(5) UNSIGNED NOT NULL DEFAULT 587,
  `smtp_encryption` enum('TLS','SMTPS','NONE') NOT NULL DEFAULT 'TLS',
  `smtp_username_encrypted` text DEFAULT NULL,
  `smtp_password_encrypted` text DEFAULT NULL,
  `from_email` varchar(254) NOT NULL,
  `from_name` varchar(200) DEFAULT NULL,
  `reply_to_email` varchar(254) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `configured_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_deliveries`
--

INSERT INTO `report_deliveries` (`id`, `business_id`, `generated_report_id`, `channel`, `destination`, `status`, `provider_message_id`, `attempted_at`, `sent_at`, `error_message`, `created_at`) VALUES
(1, 1, 1, 'EMAIL', 'owner@kigaliretail.demo', 'SENT', 'SEED-MSG-0001', '2026-08-08 12:06:00.000000', '2026-08-08 12:06:01.000000', NULL, '2026-08-08 13:06:13.639442');

-- --------------------------------------------------------

--
-- Table structure for table `report_schedules`
--

CREATE TABLE `report_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `frequency` enum('DAILY','WEEKLY','MONTHLY','YEARLY') NOT NULL,
  `weekday` tinyint(3) UNSIGNED DEFAULT NULL COMMENT '1=Monday ... 7=Sunday',
  `day_of_month` tinyint(3) UNSIGNED DEFAULT NULL,
  `month_of_year` tinyint(3) UNSIGNED DEFAULT NULL,
  `send_time` time NOT NULL DEFAULT '08:00:00',
  `timezone` varchar(64) NOT NULL DEFAULT 'Africa/Kigali',
  `report_format` enum('PDF','CSV','XLSX') NOT NULL DEFAULT 'PDF',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `next_run_at` datetime(6) DEFAULT NULL,
  `last_run_at` datetime(6) DEFAULT NULL,
  `created_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `report_schedules`
--

INSERT INTO `report_schedules` (`id`, `business_id`, `name`, `frequency`, `weekday`, `day_of_month`, `month_of_year`, `send_time`, `timezone`, `report_format`, `is_active`, `next_run_at`, `last_run_at`, `created_by_membership_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'Daily Sales Snapshot', 'DAILY', NULL, NULL, NULL, '18:00:00', 'Africa/Kigali', 'PDF', 1, '2026-08-08 16:00:00.000000', NULL, 1, '2026-08-08 13:06:13.439461', '2026-08-08 13:06:13.439461'),
(2, 1, 'Weekly Operations Report', 'WEEKLY', 1, NULL, NULL, '08:00:00', 'Africa/Kigali', 'PDF', 1, '2026-08-10 06:00:00.000000', NULL, 1, '2026-08-08 13:06:13.451876', '2026-08-08 13:06:13.451876'),
(3, 1, 'Monthly Profit and Loss', 'MONTHLY', NULL, 1, NULL, '08:00:00', 'Africa/Kigali', 'PDF', 1, '2026-09-01 06:00:00.000000', NULL, 1, '2026-08-08 13:06:13.461905', '2026-08-08 13:06:13.461905'),
(4, 1, 'Yearly Business Summary', 'YEARLY', NULL, 1, 1, '09:00:00', 'Africa/Kigali', 'XLSX', 1, '2027-01-01 07:00:00.000000', NULL, 1, '2026-08-08 13:06:13.471458', '2026-08-08 13:06:13.471458');

-- --------------------------------------------------------

--
-- Table structure for table `report_schedule_recipients`
--

CREATE TABLE `report_schedule_recipients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `report_schedule_id` bigint(20) UNSIGNED NOT NULL,
  `channel` enum('EMAIL') NOT NULL,
  `destination` varchar(254) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_schedule_recipients`
--

INSERT INTO `report_schedule_recipients` (`id`, `business_id`, `report_schedule_id`, `channel`, `destination`, `created_at`) VALUES
(1, 1, 1, 'EMAIL', 'owner@kigaliretail.demo', '2026-08-08 13:06:13.523839'),
(2, 1, 2, 'EMAIL', 'owner@kigaliretail.demo', '2026-08-08 13:06:13.523839'),
(3, 1, 3, 'EMAIL', 'owner@kigaliretail.demo', '2026-08-08 13:06:13.523839'),
(4, 1, 4, 'EMAIL', 'owner@kigaliretail.demo', '2026-08-08 13:06:13.523839');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_number` varchar(64) NOT NULL,
  `status` enum('DRAFT','COMPLETED','VOIDED','PARTIALLY_REFUNDED','REFUNDED') NOT NULL DEFAULT 'DRAFT',
  `sold_at` datetime(6) NOT NULL,
  `subtotal` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `total_cogs` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `gross_profit` decimal(19,4) GENERATED ALWAYS AS (`total_amount` - `tax_amount` - `total_cogs`) STORED,
  `amount_paid` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `notes` varchar(1000) DEFAULT NULL,
  `cashier_membership_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `business_id`, `location_id`, `customer_id`, `sale_number`, `status`, `sold_at`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `total_cogs`, `amount_paid`, `notes`, `cashier_membership_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'SAL-2026-0001', 'COMPLETED', '2026-08-05 09:15:00.000000', 12000.0000, 0.0000, 0.0000, 12000.0000, 8600.0005, 12000.0000, 'Customer sale', 3, '2026-08-08 13:06:12.791881', '2026-08-08 13:06:12.791881'),
(2, 1, 1, NULL, 'SAL-2026-0002', 'COMPLETED', '2026-08-06 12:30:00.000000', 35600.0000, 0.0000, 0.0000, 35600.0000, 27500.0000, 35600.0000, 'Walk-in sale', 3, '2026-08-08 13:06:12.895223', '2026-08-08 13:06:12.895223'),
(3, 1, 1, 2, 'SAL-2026-0003', 'COMPLETED', '2026-08-07 15:20:00.000000', 24000.0000, 0.0000, 0.0000, 24000.0000, 17200.0010, 24000.0000, 'Customer sale', 2, '2026-08-08 13:06:12.986142', '2026-08-08 13:06:12.986142');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` decimal(18,4) NOT NULL,
  `unit_price` decimal(19,4) NOT NULL,
  `discount_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `tax_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `net_sales_amount` decimal(19,4) NOT NULL,
  `line_total` decimal(19,4) NOT NULL,
  `unit_cost_at_sale` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `cogs_total` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `gross_profit` decimal(19,4) GENERATED ALWAYS AS (`net_sales_amount` - `cogs_total`) STORED,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `business_id`, `sale_id`, `product_id`, `batch_id`, `quantity`, `unit_price`, `discount_amount`, `tax_amount`, `net_sales_amount`, `line_total`, `unit_cost_at_sale`, `cogs_total`, `created_at`) VALUES
(1, 1, 1, 1, NULL, 10.0000, 700.0000, 0.0000, 0.0000, 7000.0000, 7000.0000, 506.6667, 5066.6670, '2026-08-08 13:06:12.817568'),
(2, 1, 1, 4, 1, 5.0000, 1000.0000, 0.0000, 0.0000, 5000.0000, 5000.0000, 706.6667, 3533.3335, '2026-08-08 13:06:12.843453'),
(3, 1, 2, 2, NULL, 2.0000, 10000.0000, 0.0000, 0.0000, 20000.0000, 20000.0000, 8000.0000, 16000.0000, '2026-08-08 13:06:12.909086'),
(4, 1, 2, 3, NULL, 3.0000, 3200.0000, 0.0000, 0.0000, 9600.0000, 9600.0000, 2500.0000, 7500.0000, '2026-08-08 13:06:12.918180'),
(5, 1, 2, 5, NULL, 5.0000, 1200.0000, 0.0000, 0.0000, 6000.0000, 6000.0000, 800.0000, 4000.0000, '2026-08-08 13:06:12.926175'),
(6, 1, 3, 4, 1, 10.0000, 1000.0000, 0.0000, 0.0000, 10000.0000, 10000.0000, 706.6667, 7066.6670, '2026-08-08 13:06:12.997905'),
(7, 1, 3, 1, NULL, 20.0000, 700.0000, 0.0000, 0.0000, 14000.0000, 14000.0000, 506.6667, 10133.3340, '2026-08-08 13:06:13.005775');

-- --------------------------------------------------------

--
-- Table structure for table `sale_payments`
--

CREATE TABLE `sale_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(19,4) NOT NULL,
  `payment_method` enum('CASH','CARD','BANK_TRANSFER','MOBILE_MONEY','CHEQUE','CREDIT','OTHER') NOT NULL,
  `reference_number` varchar(120) DEFAULT NULL,
  `paid_at` datetime(6) NOT NULL,
  `recorded_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

--
-- Dumping data for table `sale_payments`
--

INSERT INTO `sale_payments` (`id`, `business_id`, `sale_id`, `amount`, `payment_method`, `reference_number`, `paid_at`, `recorded_by_membership_id`, `created_at`) VALUES
(1, 1, 1, 12000.0000, 'MOBILE_MONEY', 'MOMO-SAL-0001', '2026-08-05 09:16:00.000000', 3, '2026-08-08 13:06:12.861187'),
(2, 1, 2, 35600.0000, 'CASH', 'CASH-SAL-0002', '2026-08-06 12:30:00.000000', 3, '2026-08-08 13:06:12.944262'),
(3, 1, 3, 24000.0000, 'CARD', 'CARD-SAL-0003', '2026-08-07 15:20:00.000000', 2, '2026-08-08 13:06:13.022429');

-- --------------------------------------------------------

--
-- Table structure for table `sale_returns`
--

CREATE TABLE `sale_returns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `return_number` varchar(64) NOT NULL,
  `returned_at` datetime(6) NOT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `status` enum('DRAFT','COMPLETED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `refund_amount` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `created_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ;

-- --------------------------------------------------------

--
-- Table structure for table `sale_return_items`
--

CREATE TABLE `sale_return_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `sale_return_id` bigint(20) UNSIGNED NOT NULL,
  `sale_item_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` decimal(18,4) NOT NULL,
  `unit_price` decimal(19,4) NOT NULL,
  `unit_cost_at_sale` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(19,4) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `adjustment_number` varchar(64) NOT NULL,
  `adjustment_type` enum('STOCK_IN','STOCK_OUT','GAIN','LOSS','DAMAGED','EXPIRED','OPENING','CORRECTION_IN','CORRECTION_OUT') NOT NULL,
  `occurred_at` datetime(6) NOT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `status` enum('DRAFT','POSTED','VOIDED') NOT NULL DEFAULT 'DRAFT',
  `created_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `approved_by_membership_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_adjustments`
--

INSERT INTO `stock_adjustments` (`id`, `business_id`, `location_id`, `adjustment_number`, `adjustment_type`, `occurred_at`, `reason`, `status`, `created_by_membership_id`, `approved_by_membership_id`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'ADJ-OPEN-0001', 'OPENING', '2026-08-01 10:00:00.000000', 'Opening warehouse balance', 'POSTED', 4, 1, '2026-08-08 13:06:13.048804', '2026-08-08 13:06:13.048804'),
(2, 1, 1, 'ADJ-DMG-0001', 'DAMAGED', '2026-08-07 17:00:00.000000', 'Packaging damaged during handling', 'POSTED', 4, 2, '2026-08-08 13:06:13.117124', '2026-08-08 13:06:13.117124'),
(3, 1, 1, 'ADJ-EXP-0001', 'EXPIRED', '2026-08-08 06:30:00.000000', 'QA demo expiry/unsellable disposal event', 'POSTED', 4, 2, '2026-08-08 13:06:13.156900', '2026-08-08 13:06:13.156900');

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustment_items`
--

CREATE TABLE `stock_adjustment_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `stock_adjustment_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` decimal(18,4) NOT NULL,
  `unit_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `notes` varchar(500) DEFAULT NULL
) ;

--
-- Dumping data for table `stock_adjustment_items`
--

INSERT INTO `stock_adjustment_items` (`id`, `business_id`, `stock_adjustment_id`, `product_id`, `batch_id`, `quantity`, `unit_cost`, `notes`) VALUES
(1, 1, 1, 1, NULL, 40.0000, 500.0000, 'Warehouse opening water'),
(2, 1, 1, 5, NULL, 20.0000, 800.0000, 'Warehouse opening soap'),
(3, 1, 2, 4, 1, 2.0000, 700.0000, 'Two milk units damaged'),
(4, 1, 3, 4, 1, 3.0000, 700.0000, 'Three units disposed for expiry workflow testing');

-- --------------------------------------------------------

--
-- Table structure for table `stock_takes`
--

CREATE TABLE `stock_takes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `stock_take_number` varchar(64) NOT NULL,
  `status` enum('DRAFT','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `started_at` datetime(6) DEFAULT NULL,
  `completed_at` datetime(6) DEFAULT NULL,
  `created_by_membership_id` bigint(20) UNSIGNED NOT NULL,
  `approved_by_membership_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `stock_takes`
--

INSERT INTO `stock_takes` (`id`, `business_id`, `location_id`, `stock_take_number`, `status`, `started_at`, `completed_at`, `created_by_membership_id`, `approved_by_membership_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'ST-2026-0001', 'COMPLETED', '2026-08-08 07:00:00.000000', '2026-08-08 07:30:00.000000', 4, 1, 'Morning cycle count', '2026-08-08 13:06:13.192013', '2026-08-08 13:06:13.192013');

-- --------------------------------------------------------

--
-- Table structure for table `stock_take_items`
--

CREATE TABLE `stock_take_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `stock_take_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `system_quantity` decimal(18,4) NOT NULL,
  `counted_quantity` decimal(18,4) NOT NULL,
  `difference_quantity` decimal(18,4) GENERATED ALWAYS AS (`counted_quantity` - `system_quantity`) STORED,
  `unit_cost` decimal(19,4) NOT NULL DEFAULT 0.0000,
  `notes` varchar(500) DEFAULT NULL
) ;

--
-- Dumping data for table `stock_take_items`
--

INSERT INTO `stock_take_items` (`id`, `business_id`, `stock_take_id`, `product_id`, `batch_id`, `system_quantity`, `counted_quantity`, `unit_cost`, `notes`) VALUES
(1, 1, 1, 1, NULL, 120.0000, 118.0000, 506.6667, 'Two units shortage'),
(2, 1, 1, 3, NULL, 47.0000, 48.0000, 2500.0000, 'One unit stock gain');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_code` varchar(64) NOT NULL,
  `name` varchar(200) NOT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `deleted_at` datetime(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `business_id`, `supplier_code`, `name`, `contact_person`, `phone`, `email`, `tax_number`, `address`, `notes`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'SUP-001', 'Rwanda Wholesale Distribution', 'Claudine Demo', '+250788600001', 'sales@rwd.demo', NULL, 'Kigali Special Economic Zone', NULL, 1, '2026-08-08 13:06:12.260875', '2026-08-08 13:06:12.260875', NULL),
(2, 1, 'SUP-002', 'Fresh Foods Rwanda', 'Emmanuel Demo', '+250788600002', 'orders@freshfoods.demo', NULL, 'Kicukiro, Kigali', NULL, 1, '2026-08-08 13:06:12.273018', '2026-08-08 13:06:12.273018', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `units_of_measure`
--

CREATE TABLE `units_of_measure` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(32) NOT NULL,
  `name` varchar(100) NOT NULL,
  `symbol` varchar(20) DEFAULT NULL,
  `decimal_places` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ;

--
-- Dumping data for table `units_of_measure`
--

INSERT INTO `units_of_measure` (`id`, `code`, `name`, `symbol`, `decimal_places`) VALUES
(1, 'UNIT', 'Unit', 'pc', 0),
(2, 'BOX', 'Box', 'box', 0),
(3, 'KG', 'Kilogram', 'kg', 3),
(4, 'L', 'Litre', 'L', 3);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `status` enum('PENDING_APPROVAL','ACTIVE','SUSPENDED','DISABLED') NOT NULL DEFAULT 'PENDING_APPROVAL',
  `email_verified_at` datetime(6) DEFAULT NULL,
  `phone_verified_at` datetime(6) DEFAULT NULL,
  `last_login_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `phone`, `first_name`, `last_name`, `status`, `email_verified_at`, `phone_verified_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'superadmin@demo.local', '+250780000001', 'System', 'Administrator', 'ACTIVE', '2026-08-01 08:00:00.000000', NULL, NULL, '2026-08-08 13:06:11.575399', '2026-08-08 13:06:11.575399'),
(2, 'owner@kigaliretail.demo', '+250780100001', 'Aline', 'Uwimana', 'ACTIVE', '2026-08-01 08:15:00.000000', '2026-08-01 08:15:00.000000', NULL, '2026-08-08 13:06:11.708804', '2026-08-08 13:06:11.708804'),
(3, 'manager@kigaliretail.demo', '+250780100002', 'Eric', 'Nshimiyimana', 'ACTIVE', '2026-08-01 09:00:00.000000', NULL, NULL, '2026-08-08 13:06:11.729798', '2026-08-08 13:06:11.729798'),
(4, 'cashier@kigaliretail.demo', '+250780100003', 'Diane', 'Mukamana', 'ACTIVE', '2026-08-01 09:05:00.000000', NULL, NULL, '2026-08-08 13:06:11.743653', '2026-08-08 13:06:11.743653'),
(5, 'storekeeper@kigaliretail.demo', '+250780100004', 'Patrick', 'Habimana', 'ACTIVE', '2026-08-01 09:10:00.000000', NULL, NULL, '2026-08-08 13:06:11.758624', '2026-08-08 13:06:11.758624'),
(6, 'pending.owner@demo.local', '+250780200001', 'Jean', 'Demo', 'PENDING_APPROVAL', NULL, NULL, NULL, '2026-08-08 13:06:11.774448', '2026-08-08 13:06:11.774448');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `type` enum('INFO','SUCCESS','WARNING','DANGER') NOT NULL DEFAULT 'INFO',
  `link_url` varchar(500) DEFAULT NULL,
  `read_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_platform_roles`
--

CREATE TABLE `user_platform_roles` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `platform_role_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `assigned_by_user_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_platform_roles`
--

INSERT INTO `user_platform_roles` (`user_id`, `platform_role_id`, `assigned_at`, `assigned_by_user_id`) VALUES
(1, 1, '2026-08-08 13:06:11.700904', 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_current_stock`
-- (See below for the actual view)
--
CREATE TABLE `v_current_stock` (
`business_id` bigint(20) unsigned
,`location_id` bigint(20) unsigned
,`product_id` bigint(20) unsigned
,`sku` varchar(100)
,`product_name` varchar(200)
,`quantity_on_hand` decimal(18,4)
,`reserved_quantity` decimal(18,4)
,`available_quantity` decimal(18,4)
,`average_unit_cost` decimal(19,4)
,`stock_value` decimal(23,4)
,`reorder_level` decimal(18,4)
,`needs_reorder` int(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_employee_effective_role_permissions`
-- (See below for the actual view)
--
CREATE TABLE `v_employee_effective_role_permissions` (
`business_id` bigint(20) unsigned
,`membership_id` bigint(20) unsigned
,`permission_id` bigint(20) unsigned
,`permission_code` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_inventory_loss_movements`
-- (See below for the actual view)
--
CREATE TABLE `v_inventory_loss_movements` (
`business_id` bigint(20) unsigned
,`location_id` bigint(20) unsigned
,`product_id` bigint(20) unsigned
,`movement_type` enum('PURCHASE_RECEIPT','PURCHASE_RETURN','SALE','SALE_RETURN','MANUAL_IN','MANUAL_OUT','STOCKTAKE_GAIN','STOCKTAKE_LOSS','DAMAGE','EXPIRY','OPENING','CORRECTION_IN','CORRECTION_OUT')
,`quantity_delta` decimal(18,4)
,`unit_cost` decimal(19,4)
,`loss_value` decimal(37,8)
,`occurred_at` datetime(6)
);

-- --------------------------------------------------------

--
-- Structure for view `v_current_stock`
--
DROP TABLE IF EXISTS `v_current_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_current_stock`  AS SELECT `ib`.`business_id` AS `business_id`, `ib`.`location_id` AS `location_id`, `ib`.`product_id` AS `product_id`, `p`.`sku` AS `sku`, `p`.`name` AS `product_name`, `ib`.`quantity_on_hand` AS `quantity_on_hand`, `ib`.`reserved_quantity` AS `reserved_quantity`, `ib`.`available_quantity` AS `available_quantity`, `ib`.`average_unit_cost` AS `average_unit_cost`, `ib`.`stock_value` AS `stock_value`, `p`.`reorder_level` AS `reorder_level`, `ib`.`available_quantity`<= `p`.`reorder_level` AS `needs_reorder` FROM (`inventory_balances` `ib` join `products` `p` on(`p`.`business_id` = `ib`.`business_id` and `p`.`id` = `ib`.`product_id`)) WHERE `p`.`deleted_at` is null ;

-- --------------------------------------------------------

--
-- Structure for view `v_employee_effective_role_permissions`
--
DROP TABLE IF EXISTS `v_employee_effective_role_permissions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_employee_effective_role_permissions`  AS SELECT `mr`.`business_id` AS `business_id`, `mr`.`membership_id` AS `membership_id`, `p`.`id` AS `permission_id`, `p`.`code` AS `permission_code` FROM ((`membership_roles` `mr` join `business_role_permissions` `brp` on(`brp`.`business_id` = `mr`.`business_id` and `brp`.`business_role_id` = `mr`.`business_role_id`)) join `permissions` `p` on(`p`.`id` = `brp`.`permission_id` and `p`.`scope` = 'BUSINESS')) ;

-- --------------------------------------------------------

--
-- Structure for view `v_inventory_loss_movements`
--
DROP TABLE IF EXISTS `v_inventory_loss_movements`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_inventory_loss_movements`  AS SELECT `im`.`business_id` AS `business_id`, `im`.`location_id` AS `location_id`, `im`.`product_id` AS `product_id`, `im`.`movement_type` AS `movement_type`, `im`.`quantity_delta` AS `quantity_delta`, `im`.`unit_cost` AS `unit_cost`, abs(`im`.`quantity_delta`) * `im`.`unit_cost` AS `loss_value`, `im`.`occurred_at` AS `occurred_at` FROM `inventory_movements` AS `im` WHERE `im`.`movement_type` in ('STOCKTAKE_LOSS','DAMAGE','EXPIRY','MANUAL_OUT','CORRECTION_OUT') AND `im`.`quantity_delta` < 0 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_business_date` (`business_id`,`created_at`),
  ADD KEY `idx_audit_actor_user_date` (`actor_user_id`,`created_at`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`,`created_at`),
  ADD KEY `idx_audit_request` (`request_id`),
  ADD KEY `fk_audit_actor_membership` (`actor_membership_id`);

--
-- Indexes for table `auth_credentials`
--
ALTER TABLE `auth_credentials`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `batch_inventory_balances`
--
ALTER TABLE `batch_inventory_balances`
  ADD PRIMARY KEY (`business_id`,`location_id`,`batch_id`),
  ADD KEY `fk_bib_batch` (`business_id`,`batch_id`);

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_businesses_public_id` (`public_id`),
  ADD UNIQUE KEY `uq_businesses_registration_number` (`registration_number`),
  ADD KEY `idx_businesses_approval_status` (`approval_status`),
  ADD KEY `fk_businesses_created_by` (`created_by_user_id`),
  ADD KEY `fk_businesses_approved_by` (`approved_by_user_id`);

--
-- Indexes for table `business_accounting_settings`
--
ALTER TABLE `business_accounting_settings`
  ADD PRIMARY KEY (`business_id`);

--
-- Indexes for table `business_approval_events`
--
ALTER TABLE `business_approval_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_business_approval_events_business_date` (`business_id`,`created_at`),
  ADD KEY `fk_bae_actor` (`actor_user_id`);

--
-- Indexes for table `business_locations`
--
ALTER TABLE `business_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_locations_code` (`business_id`,`code`),
  ADD UNIQUE KEY `uq_locations_business_id` (`business_id`,`id`);

--
-- Indexes for table `business_memberships`
--
ALTER TABLE `business_memberships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_business_membership` (`business_id`,`user_id`),
  ADD UNIQUE KEY `uq_business_membership_business_id` (`business_id`,`id`),
  ADD KEY `idx_membership_user_status` (`user_id`,`status`),
  ADD KEY `fk_membership_inviter` (`invited_by_membership_id`);

--
-- Indexes for table `business_roles`
--
ALTER TABLE `business_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_business_roles_code` (`business_id`,`code`),
  ADD UNIQUE KEY `uq_business_roles_business_id` (`business_id`,`id`);

--
-- Indexes for table `business_role_permissions`
--
ALTER TABLE `business_role_permissions`
  ADD PRIMARY KEY (`business_role_id`,`permission_id`),
  ADD KEY `idx_brp_business` (`business_id`),
  ADD KEY `fk_brp_role` (`business_id`,`business_role_id`),
  ADD KEY `fk_brp_permission` (`permission_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_customers_code` (`business_id`,`customer_code`),
  ADD UNIQUE KEY `uq_customers_business_id` (`business_id`,`id`),
  ADD KEY `idx_customers_name` (`business_id`,`name`);

--
-- Indexes for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_leave_balance` (`business_id`,`membership_id`,`leave_type_id`,`leave_year`),
  ADD KEY `fk_leave_balance_type` (`business_id`,`leave_type_id`);

--
-- Indexes for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD PRIMARY KEY (`membership_id`),
  ADD UNIQUE KEY `uq_employee_number` (`business_id`,`employee_number`),
  ADD KEY `fk_employee_profile_membership` (`business_id`,`membership_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_expense_number` (`business_id`,`expense_number`),
  ADD KEY `idx_expenses_date` (`business_id`,`expense_date`),
  ADD KEY `idx_expenses_category_date` (`business_id`,`expense_category_id`,`expense_date`),
  ADD KEY `fk_expense_location` (`business_id`,`location_id`),
  ADD KEY `fk_expense_recorder` (`business_id`,`recorded_by_membership_id`),
  ADD KEY `fk_expense_approver` (`business_id`,`approved_by_membership_id`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_expense_category_name` (`business_id`,`name`),
  ADD UNIQUE KEY `uq_expense_categories_business_id` (`business_id`,`id`);

--
-- Indexes for table `generated_reports`
--
ALTER TABLE `generated_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_generated_reports_business_id` (`business_id`,`id`),
  ADD KEY `idx_generated_reports_period` (`business_id`,`period_start`,`period_end`),
  ADD KEY `fk_generated_report_schedule` (`business_id`,`report_schedule_id`);

--
-- Indexes for table `idempotency_keys`
--
ALTER TABLE `idempotency_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_idempotency_key` (`business_id`,`idempotency_key`),
  ADD KEY `idx_idempotency_expiry` (`expires_at`);

--
-- Indexes for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  ADD PRIMARY KEY (`business_id`,`location_id`,`product_id`),
  ADD KEY `idx_inventory_balances_product` (`business_id`,`product_id`);

--
-- Indexes for table `inventory_cost_allocations`
--
ALTER TABLE `inventory_cost_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cost_allocation` (`sale_item_id`,`cost_layer_id`),
  ADD KEY `fk_ica_sale_item` (`business_id`,`sale_item_id`),
  ADD KEY `fk_ica_cost_layer` (`business_id`,`cost_layer_id`);

--
-- Indexes for table `inventory_cost_layers`
--
ALTER TABLE `inventory_cost_layers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_cost_layers_business_id` (`business_id`,`id`),
  ADD KEY `idx_fifo_layer_lookup` (`business_id`,`location_id`,`product_id`,`received_at`,`id`),
  ADD KEY `fk_icl_batch` (`business_id`,`product_id`,`batch_id`),
  ADD KEY `fk_icl_purchase_item` (`business_id`,`purchase_item_id`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_movement_stock` (`business_id`,`location_id`,`product_id`,`occurred_at`,`id`),
  ADD KEY `idx_inventory_movement_batch` (`business_id`,`batch_id`,`occurred_at`),
  ADD KEY `idx_inventory_movement_type` (`business_id`,`movement_type`,`occurred_at`),
  ADD KEY `fk_im_batch` (`business_id`,`product_id`,`batch_id`),
  ADD KEY `fk_im_purchase_item` (`business_id`,`purchase_item_id`),
  ADD KEY `fk_im_purchase_return_item` (`business_id`,`purchase_return_item_id`),
  ADD KEY `fk_im_sale_item` (`business_id`,`sale_item_id`),
  ADD KEY `fk_im_sale_return_item` (`business_id`,`sale_return_item_id`),
  ADD KEY `fk_im_adjustment_item` (`business_id`,`stock_adjustment_item_id`),
  ADD KEY `fk_im_stock_take_item` (`business_id`,`stock_take_item_id`),
  ADD KEY `fk_im_creator` (`business_id`,`created_by_membership_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_leave_requests_business_id` (`business_id`,`id`),
  ADD KEY `idx_leave_request_employee` (`business_id`,`membership_id`,`start_date`),
  ADD KEY `idx_leave_request_pending` (`business_id`,`status`,`submitted_at`),
  ADD KEY `fk_leave_request_type` (`business_id`,`leave_type_id`),
  ADD KEY `fk_leave_request_approver` (`business_id`,`current_approver_membership_id`);

--
-- Indexes for table `leave_request_actions`
--
ALTER TABLE `leave_request_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_leave_action_request` (`business_id`,`leave_request_id`,`created_at`),
  ADD KEY `fk_leave_action_actor` (`business_id`,`actor_membership_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_leave_type_code` (`business_id`,`code`),
  ADD UNIQUE KEY `uq_leave_types_business_id` (`business_id`,`id`);

--
-- Indexes for table `membership_permission_overrides`
--
ALTER TABLE `membership_permission_overrides`
  ADD PRIMARY KEY (`membership_id`,`permission_id`),
  ADD KEY `idx_mpo_business` (`business_id`),
  ADD KEY `fk_mpo_membership` (`business_id`,`membership_id`),
  ADD KEY `fk_mpo_permission` (`permission_id`),
  ADD KEY `fk_mpo_assigned_by` (`assigned_by_membership_id`);

--
-- Indexes for table `membership_roles`
--
ALTER TABLE `membership_roles`
  ADD PRIMARY KEY (`membership_id`,`business_role_id`),
  ADD KEY `idx_membership_roles_business` (`business_id`),
  ADD KEY `fk_mr_membership` (`business_id`,`membership_id`),
  ADD KEY `fk_mr_role` (`business_id`,`business_role_id`),
  ADD KEY `fk_mr_assigned_by` (`assigned_by_membership_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_code` (`code`),
  ADD KEY `idx_permissions_scope_module` (`scope`,`module`);

--
-- Indexes for table `platform_roles`
--
ALTER TABLE `platform_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_platform_roles_code` (`code`);

--
-- Indexes for table `platform_role_permissions`
--
ALTER TABLE `platform_role_permissions`
  ADD PRIMARY KEY (`platform_role_id`,`permission_id`),
  ADD KEY `fk_prp_permission` (`permission_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_sku` (`business_id`,`sku`),
  ADD UNIQUE KEY `uq_products_business_id` (`business_id`,`id`),
  ADD UNIQUE KEY `uq_products_barcode` (`business_id`,`barcode`),
  ADD KEY `idx_products_name` (`business_id`,`name`),
  ADD KEY `fk_products_category` (`business_id`,`category_id`),
  ADD KEY `fk_products_uom` (`uom_id`);

--
-- Indexes for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_batch_lot` (`business_id`,`product_id`,`lot_number`),
  ADD UNIQUE KEY `uq_product_batches_business_product_id` (`business_id`,`product_id`,`id`),
  ADD UNIQUE KEY `uq_product_batches_business_id` (`business_id`,`id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_category_name` (`business_id`,`name`),
  ADD UNIQUE KEY `uq_categories_business_id` (`business_id`,`id`),
  ADD KEY `fk_category_parent` (`business_id`,`parent_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_purchases_number` (`business_id`,`purchase_number`),
  ADD UNIQUE KEY `uq_purchases_business_id` (`business_id`,`id`),
  ADD KEY `idx_purchases_date` (`business_id`,`purchase_date`),
  ADD KEY `idx_purchases_supplier` (`business_id`,`supplier_id`,`purchase_date`),
  ADD KEY `fk_purchases_location` (`business_id`,`location_id`),
  ADD KEY `fk_purchases_created_by` (`business_id`,`created_by_membership_id`),
  ADD KEY `fk_purchases_approved_by` (`business_id`,`approved_by_membership_id`),
  ADD KEY `fk_purchases_received_by` (`business_id`,`received_by_membership_id`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_purchase_items_business_id` (`business_id`,`id`),
  ADD KEY `idx_purchase_items_purchase` (`business_id`,`purchase_id`),
  ADD KEY `idx_purchase_items_product` (`business_id`,`product_id`),
  ADD KEY `fk_purchase_items_batch` (`business_id`,`product_id`,`batch_id`);

--
-- Indexes for table `purchase_payments`
--
ALTER TABLE `purchase_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_payments_purchase` (`business_id`,`purchase_id`,`paid_at`),
  ADD KEY `fk_purchase_payment_recorder` (`business_id`,`recorded_by_membership_id`);

--
-- Indexes for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_purchase_returns_number` (`business_id`,`return_number`),
  ADD UNIQUE KEY `uq_purchase_returns_business_id` (`business_id`,`id`),
  ADD KEY `fk_purchase_return_purchase` (`business_id`,`purchase_id`),
  ADD KEY `fk_purchase_return_creator` (`business_id`,`created_by_membership_id`);

--
-- Indexes for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_purchase_return_items_business_id` (`business_id`,`id`),
  ADD KEY `fk_pri_return` (`business_id`,`purchase_return_id`),
  ADD KEY `fk_pri_purchase_item` (`business_id`,`purchase_item_id`),
  ADD KEY `fk_pri_batch` (`business_id`,`product_id`,`batch_id`);

--
-- Indexes for table `report_deliveries`
--
ALTER TABLE `report_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_report_delivery_pending` (`status`,`attempted_at`),
  ADD KEY `fk_report_delivery_report` (`business_id`,`generated_report_id`);

--
-- Indexes for table `report_delivery_settings`
--
ALTER TABLE `report_delivery_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_report_delivery_setting` (`business_id`),
  ADD KEY `fk_report_delivery_setting_creator` (`business_id`,`configured_by_membership_id`);

--
-- Indexes for table `report_schedules`
--
ALTER TABLE `report_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_report_schedule_name` (`business_id`,`name`),
  ADD UNIQUE KEY `uq_report_schedules_business_id` (`business_id`,`id`),
  ADD KEY `idx_report_schedule_due` (`is_active`,`next_run_at`),
  ADD KEY `fk_report_schedule_creator` (`business_id`,`created_by_membership_id`);

--
-- Indexes for table `report_schedule_recipients`
--
ALTER TABLE `report_schedule_recipients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_report_recipient` (`report_schedule_id`,`channel`,`destination`),
  ADD KEY `fk_rsr_schedule` (`business_id`,`report_schedule_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sales_number` (`business_id`,`sale_number`),
  ADD UNIQUE KEY `uq_sales_business_id` (`business_id`,`id`),
  ADD KEY `idx_sales_sold_at` (`business_id`,`sold_at`),
  ADD KEY `idx_sales_customer` (`business_id`,`customer_id`,`sold_at`),
  ADD KEY `fk_sales_location` (`business_id`,`location_id`),
  ADD KEY `fk_sales_cashier` (`business_id`,`cashier_membership_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sale_items_business_id` (`business_id`,`id`),
  ADD KEY `idx_sale_items_sale` (`business_id`,`sale_id`),
  ADD KEY `idx_sale_items_product` (`business_id`,`product_id`),
  ADD KEY `fk_sale_items_batch` (`business_id`,`product_id`,`batch_id`);

--
-- Indexes for table `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale_payments_sale` (`business_id`,`sale_id`,`paid_at`),
  ADD KEY `fk_sale_payment_recorder` (`business_id`,`recorded_by_membership_id`);

--
-- Indexes for table `sale_returns`
--
ALTER TABLE `sale_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sale_returns_number` (`business_id`,`return_number`),
  ADD UNIQUE KEY `uq_sale_returns_business_id` (`business_id`,`id`),
  ADD KEY `fk_sale_return_sale` (`business_id`,`sale_id`),
  ADD KEY `fk_sale_return_creator` (`business_id`,`created_by_membership_id`);

--
-- Indexes for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sale_return_items_business_id` (`business_id`,`id`),
  ADD KEY `fk_sri_return` (`business_id`,`sale_return_id`),
  ADD KEY `fk_sri_sale_item` (`business_id`,`sale_item_id`),
  ADD KEY `fk_sri_batch` (`business_id`,`product_id`,`batch_id`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stock_adjustment_number` (`business_id`,`adjustment_number`),
  ADD UNIQUE KEY `uq_stock_adjustments_business_id` (`business_id`,`id`),
  ADD KEY `idx_stock_adjustments_date` (`business_id`,`occurred_at`),
  ADD KEY `fk_stock_adjustment_location` (`business_id`,`location_id`),
  ADD KEY `fk_stock_adjustment_creator` (`business_id`,`created_by_membership_id`),
  ADD KEY `fk_stock_adjustment_approver` (`business_id`,`approved_by_membership_id`);

--
-- Indexes for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stock_adjustment_items_business_id` (`business_id`,`id`),
  ADD KEY `fk_sai_adjustment` (`business_id`,`stock_adjustment_id`),
  ADD KEY `fk_sai_batch` (`business_id`,`product_id`,`batch_id`);

--
-- Indexes for table `stock_takes`
--
ALTER TABLE `stock_takes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stock_take_number` (`business_id`,`stock_take_number`),
  ADD UNIQUE KEY `uq_stock_takes_business_id` (`business_id`,`id`),
  ADD KEY `fk_stock_take_location` (`business_id`,`location_id`),
  ADD KEY `fk_stock_take_creator` (`business_id`,`created_by_membership_id`),
  ADD KEY `fk_stock_take_approver` (`business_id`,`approved_by_membership_id`);

--
-- Indexes for table `stock_take_items`
--
ALTER TABLE `stock_take_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stock_take_items_business_id` (`business_id`,`id`),
  ADD KEY `idx_stock_take_items_product` (`business_id`,`product_id`),
  ADD KEY `fk_sti_stock_take` (`business_id`,`stock_take_id`),
  ADD KEY `fk_sti_batch` (`business_id`,`product_id`,`batch_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_suppliers_code` (`business_id`,`supplier_code`),
  ADD UNIQUE KEY `uq_suppliers_business_id` (`business_id`,`id`),
  ADD KEY `idx_suppliers_name` (`business_id`,`name`);

--
-- Indexes for table `units_of_measure`
--
ALTER TABLE `units_of_measure`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_uom_code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_phone` (`phone`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_unread` (`user_id`,`read_at`,`created_at`),
  ADD KEY `idx_notifications_business` (`business_id`,`created_at`);

--
-- Indexes for table `user_platform_roles`
--
ALTER TABLE `user_platform_roles`
  ADD PRIMARY KEY (`user_id`,`platform_role_id`),
  ADD KEY `fk_upr_role` (`platform_role_id`),
  ADD KEY `fk_upr_assigned_by` (`assigned_by_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `business_approval_events`
--
ALTER TABLE `business_approval_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `business_locations`
--
ALTER TABLE `business_locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `business_memberships`
--
ALTER TABLE `business_memberships`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `business_roles`
--
ALTER TABLE `business_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `generated_reports`
--
ALTER TABLE `generated_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `idempotency_keys`
--
ALTER TABLE `idempotency_keys`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_cost_allocations`
--
ALTER TABLE `inventory_cost_allocations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_cost_layers`
--
ALTER TABLE `inventory_cost_layers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_request_actions`
--
ALTER TABLE `leave_request_actions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `platform_roles`
--
ALTER TABLE `platform_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_batches`
--
ALTER TABLE `product_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_payments`
--
ALTER TABLE `purchase_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_deliveries`
--
ALTER TABLE `report_deliveries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `report_delivery_settings`
--
ALTER TABLE `report_delivery_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_schedules`
--
ALTER TABLE `report_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_schedule_recipients`
--
ALTER TABLE `report_schedule_recipients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_payments`
--
ALTER TABLE `sale_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_returns`
--
ALTER TABLE `sale_returns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_takes`
--
ALTER TABLE `stock_takes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_take_items`
--
ALTER TABLE `stock_take_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `units_of_measure`
--
ALTER TABLE `units_of_measure`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_actor_membership` FOREIGN KEY (`actor_membership_id`) REFERENCES `business_memberships` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_actor_user` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `auth_credentials`
--
ALTER TABLE `auth_credentials`
  ADD CONSTRAINT `fk_auth_credentials_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `batch_inventory_balances`
--
ALTER TABLE `batch_inventory_balances`
  ADD CONSTRAINT `fk_bib_batch` FOREIGN KEY (`business_id`,`batch_id`) REFERENCES `product_batches` (`business_id`, `id`),
  ADD CONSTRAINT `fk_bib_location` FOREIGN KEY (`business_id`,`location_id`) REFERENCES `business_locations` (`business_id`, `id`);

--
-- Constraints for table `businesses`
--
ALTER TABLE `businesses`
  ADD CONSTRAINT `fk_businesses_approved_by` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_businesses_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `business_accounting_settings`
--
ALTER TABLE `business_accounting_settings`
  ADD CONSTRAINT `fk_bas_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `business_approval_events`
--
ALTER TABLE `business_approval_events`
  ADD CONSTRAINT `fk_bae_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bae_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `business_locations`
--
ALTER TABLE `business_locations`
  ADD CONSTRAINT `fk_locations_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `business_memberships`
--
ALTER TABLE `business_memberships`
  ADD CONSTRAINT `fk_membership_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_membership_inviter` FOREIGN KEY (`invited_by_membership_id`) REFERENCES `business_memberships` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_membership_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `business_roles`
--
ALTER TABLE `business_roles`
  ADD CONSTRAINT `fk_business_roles_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `business_role_permissions`
--
ALTER TABLE `business_role_permissions`
  ADD CONSTRAINT `fk_brp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_brp_role` FOREIGN KEY (`business_id`,`business_role_id`) REFERENCES `business_roles` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  ADD CONSTRAINT `fk_leave_balance_member` FOREIGN KEY (`business_id`,`membership_id`) REFERENCES `business_memberships` (`business_id`, `id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_leave_balance_type` FOREIGN KEY (`business_id`,`leave_type_id`) REFERENCES `leave_types` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD CONSTRAINT `fk_employee_profile_membership` FOREIGN KEY (`business_id`,`membership_id`) REFERENCES `business_memberships` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expense_approver` FOREIGN KEY (`business_id`,`approved_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_expense_category` FOREIGN KEY (`business_id`,`expense_category_id`) REFERENCES `expense_categories` (`business_id`, `id`),
  ADD CONSTRAINT `fk_expense_location` FOREIGN KEY (`business_id`,`location_id`) REFERENCES `business_locations` (`business_id`, `id`),
  ADD CONSTRAINT `fk_expense_recorder` FOREIGN KEY (`business_id`,`recorded_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`);

--
-- Constraints for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD CONSTRAINT `fk_expense_category_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `generated_reports`
--
ALTER TABLE `generated_reports`
  ADD CONSTRAINT `fk_generated_report_schedule` FOREIGN KEY (`business_id`,`report_schedule_id`) REFERENCES `report_schedules` (`business_id`, `id`);

--
-- Constraints for table `idempotency_keys`
--
ALTER TABLE `idempotency_keys`
  ADD CONSTRAINT `fk_idempotency_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  ADD CONSTRAINT `fk_ib_location` FOREIGN KEY (`business_id`,`location_id`) REFERENCES `business_locations` (`business_id`, `id`),
  ADD CONSTRAINT `fk_ib_product` FOREIGN KEY (`business_id`,`product_id`) REFERENCES `products` (`business_id`, `id`);

--
-- Constraints for table `inventory_cost_allocations`
--
ALTER TABLE `inventory_cost_allocations`
  ADD CONSTRAINT `fk_ica_cost_layer` FOREIGN KEY (`business_id`,`cost_layer_id`) REFERENCES `inventory_cost_layers` (`business_id`, `id`),
  ADD CONSTRAINT `fk_ica_sale_item` FOREIGN KEY (`business_id`,`sale_item_id`) REFERENCES `sale_items` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_cost_layers`
--
ALTER TABLE `inventory_cost_layers`
  ADD CONSTRAINT `fk_icl_batch` FOREIGN KEY (`business_id`,`product_id`,`batch_id`) REFERENCES `product_batches` (`business_id`, `product_id`, `id`),
  ADD CONSTRAINT `fk_icl_location` FOREIGN KEY (`business_id`,`location_id`) REFERENCES `business_locations` (`business_id`, `id`),
  ADD CONSTRAINT `fk_icl_product` FOREIGN KEY (`business_id`,`product_id`) REFERENCES `products` (`business_id`, `id`),
  ADD CONSTRAINT `fk_icl_purchase_item` FOREIGN KEY (`business_id`,`purchase_item_id`) REFERENCES `purchase_items` (`business_id`, `id`);

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_im_adjustment_item` FOREIGN KEY (`business_id`,`stock_adjustment_item_id`) REFERENCES `stock_adjustment_items` (`business_id`, `id`),
  ADD CONSTRAINT `fk_im_batch` FOREIGN KEY (`business_id`,`product_id`,`batch_id`) REFERENCES `product_batches` (`business_id`, `product_id`, `id`),
  ADD CONSTRAINT `fk_im_creator` FOREIGN KEY (`business_id`,`created_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_im_location` FOREIGN KEY (`business_id`,`location_id`) REFERENCES `business_locations` (`business_id`, `id`),
  ADD CONSTRAINT `fk_im_product` FOREIGN KEY (`business_id`,`product_id`) REFERENCES `products` (`business_id`, `id`),
  ADD CONSTRAINT `fk_im_purchase_item` FOREIGN KEY (`business_id`,`purchase_item_id`) REFERENCES `purchase_items` (`business_id`, `id`),
  ADD CONSTRAINT `fk_im_purchase_return_item` FOREIGN KEY (`business_id`,`purchase_return_item_id`) REFERENCES `purchase_return_items` (`business_id`, `id`),
  ADD CONSTRAINT `fk_im_sale_item` FOREIGN KEY (`business_id`,`sale_item_id`) REFERENCES `sale_items` (`business_id`, `id`),
  ADD CONSTRAINT `fk_im_sale_return_item` FOREIGN KEY (`business_id`,`sale_return_item_id`) REFERENCES `sale_return_items` (`business_id`, `id`),
  ADD CONSTRAINT `fk_im_stock_take_item` FOREIGN KEY (`business_id`,`stock_take_item_id`) REFERENCES `stock_take_items` (`business_id`, `id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `fk_leave_request_approver` FOREIGN KEY (`business_id`,`current_approver_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_leave_request_member` FOREIGN KEY (`business_id`,`membership_id`) REFERENCES `business_memberships` (`business_id`, `id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_leave_request_type` FOREIGN KEY (`business_id`,`leave_type_id`) REFERENCES `leave_types` (`business_id`, `id`);

--
-- Constraints for table `leave_request_actions`
--
ALTER TABLE `leave_request_actions`
  ADD CONSTRAINT `fk_leave_action_actor` FOREIGN KEY (`business_id`,`actor_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_leave_action_request` FOREIGN KEY (`business_id`,`leave_request_id`) REFERENCES `leave_requests` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD CONSTRAINT `fk_leave_type_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `membership_permission_overrides`
--
ALTER TABLE `membership_permission_overrides`
  ADD CONSTRAINT `fk_mpo_assigned_by` FOREIGN KEY (`assigned_by_membership_id`) REFERENCES `business_memberships` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mpo_membership` FOREIGN KEY (`business_id`,`membership_id`) REFERENCES `business_memberships` (`business_id`, `id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mpo_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `membership_roles`
--
ALTER TABLE `membership_roles`
  ADD CONSTRAINT `fk_mr_assigned_by` FOREIGN KEY (`assigned_by_membership_id`) REFERENCES `business_memberships` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mr_membership` FOREIGN KEY (`business_id`,`membership_id`) REFERENCES `business_memberships` (`business_id`, `id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mr_role` FOREIGN KEY (`business_id`,`business_role_id`) REFERENCES `business_roles` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `platform_role_permissions`
--
ALTER TABLE `platform_role_permissions`
  ADD CONSTRAINT `fk_prp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prp_role` FOREIGN KEY (`platform_role_id`) REFERENCES `platform_roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`business_id`,`category_id`) REFERENCES `product_categories` (`business_id`, `id`),
  ADD CONSTRAINT `fk_products_uom` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measure` (`id`);

--
-- Constraints for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD CONSTRAINT `fk_batch_product` FOREIGN KEY (`business_id`,`product_id`) REFERENCES `products` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `fk_category_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_category_parent` FOREIGN KEY (`business_id`,`parent_id`) REFERENCES `product_categories` (`business_id`, `id`);

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `fk_purchases_approved_by` FOREIGN KEY (`business_id`,`approved_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_purchases_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  ADD CONSTRAINT `fk_purchases_created_by` FOREIGN KEY (`business_id`,`created_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_purchases_location` FOREIGN KEY (`business_id`,`location_id`) REFERENCES `business_locations` (`business_id`, `id`),
  ADD CONSTRAINT `fk_purchases_received_by` FOREIGN KEY (`business_id`,`received_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_purchases_supplier` FOREIGN KEY (`business_id`,`supplier_id`) REFERENCES `suppliers` (`business_id`, `id`);

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `fk_purchase_items_batch` FOREIGN KEY (`business_id`,`product_id`,`batch_id`) REFERENCES `product_batches` (`business_id`, `product_id`, `id`),
  ADD CONSTRAINT `fk_purchase_items_product` FOREIGN KEY (`business_id`,`product_id`) REFERENCES `products` (`business_id`, `id`),
  ADD CONSTRAINT `fk_purchase_items_purchase` FOREIGN KEY (`business_id`,`purchase_id`) REFERENCES `purchases` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_payments`
--
ALTER TABLE `purchase_payments`
  ADD CONSTRAINT `fk_purchase_payment_purchase` FOREIGN KEY (`business_id`,`purchase_id`) REFERENCES `purchases` (`business_id`, `id`),
  ADD CONSTRAINT `fk_purchase_payment_recorder` FOREIGN KEY (`business_id`,`recorded_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`);

--
-- Constraints for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  ADD CONSTRAINT `fk_purchase_return_creator` FOREIGN KEY (`business_id`,`created_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_purchase_return_purchase` FOREIGN KEY (`business_id`,`purchase_id`) REFERENCES `purchases` (`business_id`, `id`);

--
-- Constraints for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD CONSTRAINT `fk_pri_batch` FOREIGN KEY (`business_id`,`product_id`,`batch_id`) REFERENCES `product_batches` (`business_id`, `product_id`, `id`),
  ADD CONSTRAINT `fk_pri_product` FOREIGN KEY (`business_id`,`product_id`) REFERENCES `products` (`business_id`, `id`),
  ADD CONSTRAINT `fk_pri_purchase_item` FOREIGN KEY (`business_id`,`purchase_item_id`) REFERENCES `purchase_items` (`business_id`, `id`),
  ADD CONSTRAINT `fk_pri_return` FOREIGN KEY (`business_id`,`purchase_return_id`) REFERENCES `purchase_returns` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `report_deliveries`
--
ALTER TABLE `report_deliveries`
  ADD CONSTRAINT `fk_report_delivery_report` FOREIGN KEY (`business_id`,`generated_report_id`) REFERENCES `generated_reports` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `report_delivery_settings`
--
ALTER TABLE `report_delivery_settings`
  ADD CONSTRAINT `fk_report_delivery_setting_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_report_delivery_setting_creator` FOREIGN KEY (`business_id`,`configured_by_membership_id`) REFERENCES `business_memberships` (`business_id`,`id`);

--
-- Constraints for table `report_schedules`
--
ALTER TABLE `report_schedules`
  ADD CONSTRAINT `fk_report_schedule_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_report_schedule_creator` FOREIGN KEY (`business_id`,`created_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`);

--
-- Constraints for table `report_schedule_recipients`
--
ALTER TABLE `report_schedule_recipients`
  ADD CONSTRAINT `fk_rsr_schedule` FOREIGN KEY (`business_id`,`report_schedule_id`) REFERENCES `report_schedules` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  ADD CONSTRAINT `fk_sales_cashier` FOREIGN KEY (`business_id`,`cashier_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_sales_customer` FOREIGN KEY (`business_id`,`customer_id`) REFERENCES `customers` (`business_id`, `id`),
  ADD CONSTRAINT `fk_sales_location` FOREIGN KEY (`business_id`,`location_id`) REFERENCES `business_locations` (`business_id`, `id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sale_items_batch` FOREIGN KEY (`business_id`,`product_id`,`batch_id`) REFERENCES `product_batches` (`business_id`, `product_id`, `id`),
  ADD CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`business_id`,`product_id`) REFERENCES `products` (`business_id`, `id`),
  ADD CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`business_id`,`sale_id`) REFERENCES `sales` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD CONSTRAINT `fk_sale_payment_recorder` FOREIGN KEY (`business_id`,`recorded_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_sale_payment_sale` FOREIGN KEY (`business_id`,`sale_id`) REFERENCES `sales` (`business_id`, `id`);

--
-- Constraints for table `sale_returns`
--
ALTER TABLE `sale_returns`
  ADD CONSTRAINT `fk_sale_return_creator` FOREIGN KEY (`business_id`,`created_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_sale_return_sale` FOREIGN KEY (`business_id`,`sale_id`) REFERENCES `sales` (`business_id`, `id`);

--
-- Constraints for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  ADD CONSTRAINT `fk_sri_batch` FOREIGN KEY (`business_id`,`product_id`,`batch_id`) REFERENCES `product_batches` (`business_id`, `product_id`, `id`),
  ADD CONSTRAINT `fk_sri_product` FOREIGN KEY (`business_id`,`product_id`) REFERENCES `products` (`business_id`, `id`),
  ADD CONSTRAINT `fk_sri_return` FOREIGN KEY (`business_id`,`sale_return_id`) REFERENCES `sale_returns` (`business_id`, `id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sri_sale_item` FOREIGN KEY (`business_id`,`sale_item_id`) REFERENCES `sale_items` (`business_id`, `id`);

--
-- Constraints for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `fk_stock_adjustment_approver` FOREIGN KEY (`business_id`,`approved_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_stock_adjustment_creator` FOREIGN KEY (`business_id`,`created_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_stock_adjustment_location` FOREIGN KEY (`business_id`,`location_id`) REFERENCES `business_locations` (`business_id`, `id`);

--
-- Constraints for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  ADD CONSTRAINT `fk_sai_adjustment` FOREIGN KEY (`business_id`,`stock_adjustment_id`) REFERENCES `stock_adjustments` (`business_id`, `id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sai_batch` FOREIGN KEY (`business_id`,`product_id`,`batch_id`) REFERENCES `product_batches` (`business_id`, `product_id`, `id`),
  ADD CONSTRAINT `fk_sai_product` FOREIGN KEY (`business_id`,`product_id`) REFERENCES `products` (`business_id`, `id`);

--
-- Constraints for table `stock_takes`
--
ALTER TABLE `stock_takes`
  ADD CONSTRAINT `fk_stock_take_approver` FOREIGN KEY (`business_id`,`approved_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_stock_take_creator` FOREIGN KEY (`business_id`,`created_by_membership_id`) REFERENCES `business_memberships` (`business_id`, `id`),
  ADD CONSTRAINT `fk_stock_take_location` FOREIGN KEY (`business_id`,`location_id`) REFERENCES `business_locations` (`business_id`, `id`);

--
-- Constraints for table `stock_take_items`
--
ALTER TABLE `stock_take_items`
  ADD CONSTRAINT `fk_sti_batch` FOREIGN KEY (`business_id`,`product_id`,`batch_id`) REFERENCES `product_batches` (`business_id`, `product_id`, `id`),
  ADD CONSTRAINT `fk_sti_product` FOREIGN KEY (`business_id`,`product_id`) REFERENCES `products` (`business_id`, `id`),
  ADD CONSTRAINT `fk_sti_stock_take` FOREIGN KEY (`business_id`,`stock_take_id`) REFERENCES `stock_takes` (`business_id`, `id`) ON DELETE CASCADE;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `fk_suppliers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_platform_roles`
--
ALTER TABLE `user_platform_roles`
  ADD CONSTRAINT `fk_upr_assigned_by` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_upr_role` FOREIGN KEY (`platform_role_id`) REFERENCES `platform_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_upr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
