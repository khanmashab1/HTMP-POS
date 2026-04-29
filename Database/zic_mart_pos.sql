-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 19, 2025 at 01:51 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `zic_mart_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Beverages', 'Soft drinks, juices, water', '2025-12-16 15:30:14'),
(2, 'Snacks', 'Chips, biscuits, chocolates', '2025-12-16 15:30:14'),
(3, 'Cigarettes', 'Cigarette brands', '2025-12-16 15:30:14'),
(4, 'Groceries', 'Basic grocery items', '2025-12-16 15:30:14'),
(5, 'Personal Care', 'Soap, shampoo, toothpaste', '2025-12-16 15:30:14'),
(6, 'Stationery', 'Pens, notebooks, etc', '2025-12-16 15:30:14'),
(7, 'Others', 'Miscellaneous items', '2025-12-16 15:30:14');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `barcode` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `category_id` int DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT '0.00',
  `sale_price` decimal(10,2) NOT NULL,
  `stock_quantity` int DEFAULT '0',
  `min_stock_alert` int DEFAULT '5',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `category_id` (`category_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `barcode`, `name`, `category_id`, `purchase_price`, `sale_price`, `stock_quantity`, `min_stock_alert`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '8965320014941', 'lemon', 2, 0.00, 200.00, 96, 5, 1, '2025-12-16 15:53:15', '2025-12-19 12:44:24'),
(2, '8965320013012', 'Snoper', 2, 0.00, 150.00, 94, 10, 1, '2025-12-17 07:13:11', '2025-12-18 15:16:31'),
(3, '12345678123', 'Bear', 1, 0.00, 1000.00, 8, 5, 1, '2025-12-18 14:43:32', '2025-12-18 15:16:31');

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

DROP TABLE IF EXISTS `returns`;
CREATE TABLE IF NOT EXISTS `returns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `original_sale_id` int DEFAULT NULL,
  `return_bill_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `total_items` int DEFAULT '0',
  `total_amount` decimal(10,2) NOT NULL,
  `reason` text,
  `reason_details` text,
  `return_date` date NOT NULL,
  `return_time` time NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_bill_number` (`return_bill_number`),
  UNIQUE KEY `return_bill_number_2` (`return_bill_number`),
  UNIQUE KEY `return_bill_number_3` (`return_bill_number`),
  KEY `original_sale_id` (`original_sale_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `returns`
--

INSERT INTO `returns` (`id`, `original_sale_id`, `return_bill_number`, `user_id`, `total_items`, `total_amount`, `reason`, `reason_details`, `return_date`, `return_time`, `created_at`) VALUES
(7, 9, 'RET-ZIC-20251217-0001-001', 2, 1, 200.00, 'Expired Product', '', '2025-12-17', '11:59:46', '2025-12-17 06:59:46'),
(8, 11, 'RET-ZIC-20251217-0003-001', 2, 1, 150.00, 'Wrong Item', '', '2025-12-17', '16:39:03', '2025-12-17 11:39:03'),
(9, 10, 'RET-ZIC-20251217-0002-001', 2, 1, 150.00, 'Expired Product', '', '2025-12-17', '17:24:22', '2025-12-17 12:24:22'),
(10, 12, 'RET-ZIC-20251218-0001-001', 1, 1, 150.00, 'Customer Changed Mind', '', '2025-12-18', '19:40:07', '2025-12-18 14:40:07'),
(11, 15, 'RET-ZIC-20251219-0001-001', 1, 1, 200.00, 'Expired Product', '', '2025-12-19', '17:44:24', '2025-12-19 12:44:24');

-- --------------------------------------------------------

--
-- Table structure for table `return_items`
--

DROP TABLE IF EXISTS `return_items`;
CREATE TABLE IF NOT EXISTS `return_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `return_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `return_id` (`return_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `return_items`
--

INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `product_name`, `barcode`, `quantity`, `unit_price`, `total_price`) VALUES
(11, 11, 1, 'lemon', '8965320014941', 1, 200.00, 200.00),
(10, 10, 2, 'Snoper', '8965320013012', 1, 150.00, 150.00),
(9, 9, 2, 'Snoper', '8965320013012', 1, 150.00, 150.00),
(8, 8, 2, 'Snoper', '8965320013012', 1, 150.00, 150.00),
(7, 7, 1, 'lemon', '8965320014941', 1, 200.00, 200.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
CREATE TABLE IF NOT EXISTS `sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bill_number` varchar(20) NOT NULL,
  `user_id` int DEFAULT NULL,
  `total_items` int DEFAULT '0',
  `total_amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `tax` decimal(10,2) DEFAULT '0.00',
  `net_amount` decimal(10,2) NOT NULL,
  `cash_received` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card') DEFAULT 'cash',
  `sale_date` date NOT NULL,
  `sale_time` time NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `bill_number`, `user_id`, `total_items`, `total_amount`, `discount`, `tax`, `net_amount`, `cash_received`, `change_amount`, `payment_method`, `sale_date`, `sale_time`, `created_at`) VALUES
(15, 'ZIC-20251219-0001', 1, 0, 0.00, 0.00, 32.00, 32.00, 1000.00, 768.00, 'cash', '2025-12-19', '17:42:53', '2025-12-19 12:42:53'),
(14, 'ZIC-20251218-0003', 3, 4, 1500.00, 0.00, 240.00, 1740.00, 2000.00, 260.00, 'cash', '2025-12-18', '20:16:31', '2025-12-18 15:16:31'),
(13, 'ZIC-20251218-0002', 3, 3, 1350.00, 0.00, 216.00, 1566.00, 2000.00, 434.00, 'cash', '2025-12-18', '20:15:03', '2025-12-18 15:15:03'),
(12, 'ZIC-20251218-0001', 1, 1, 150.00, 0.00, 48.00, 198.00, 1000.00, 652.00, 'cash', '2025-12-18', '19:38:59', '2025-12-18 14:38:59'),
(11, 'ZIC-20251217-0003', 2, 1, 150.00, 0.00, 48.00, 198.00, 1000.00, 652.00, 'cash', '2025-12-17', '12:22:07', '2025-12-17 07:22:07'),
(10, 'ZIC-20251217-0002', 2, 1, 150.00, 0.00, 48.00, 198.00, 1000.00, 652.00, 'cash', '2025-12-17', '12:15:06', '2025-12-17 07:15:06'),
(9, 'ZIC-20251217-0001', 2, 0, 0.00, 0.00, 32.00, 32.00, 232.00, 0.00, 'cash', '2025-12-17', '11:50:14', '2025-12-17 06:50:14');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `product_name`, `barcode`, `quantity`, `unit_price`, `total_price`) VALUES
(14, 12, 2, 'Snoper', '8965320013012', 1, 150.00, 150.00),
(13, 11, 2, 'Snoper', '8965320013012', 1, 150.00, 150.00),
(12, 11, 2, 'Snoper', '8965320013012', 1, 150.00, 150.00),
(11, 10, 2, 'Snoper', '8965320013012', 1, 150.00, 150.00),
(10, 10, 2, 'Snoper', '8965320013012', 1, 150.00, 150.00),
(9, 9, 1, 'lemon', '8965320014941', 1, 200.00, 200.00),
(15, 12, 2, 'Snoper', '8965320013012', 1, 150.00, 150.00),
(16, 13, 1, 'lemon', '8965320014941', 1, 200.00, 200.00),
(17, 13, 3, 'Bear', '12345678123', 1, 1000.00, 1000.00),
(18, 13, 2, 'Snoper', '8965320013012', 1, 150.00, 150.00),
(19, 14, 1, 'lemon', '8965320014941', 1, 200.00, 200.00),
(20, 14, 2, 'Snoper', '8965320013012', 2, 150.00, 300.00),
(21, 14, 3, 'Bear', '12345678123', 1, 1000.00, 1000.00),
(22, 15, 1, 'lemon', '8965320014941', 1, 200.00, 200.00);

-- --------------------------------------------------------

--
-- Table structure for table `store_settings`
--

DROP TABLE IF EXISTS `store_settings`;
CREATE TABLE IF NOT EXISTS `store_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `store_name` varchar(200) DEFAULT NULL,
  `store_address` text,
  `store_phone` varchar(15) DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT '0.00',
  `receipt_footer` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `store_settings`
--

INSERT INTO `store_settings` (`id`, `store_name`, `store_address`, `store_phone`, `tax_rate`, `receipt_footer`, `created_at`, `updated_at`) VALUES
(1, 'ZIC Mart', 'ZIC Petrol Pump, Murree Road, Abbottabad', '0313-5881633', 16.00, 'Thank you for shopping with us!', '2025-12-16 15:33:23', '2025-12-17 10:58:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_role` enum('admin','cashier') DEFAULT 'cashier',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password`, `user_role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Ali Ahmed', 'ali', '$2y$10$g4CnvD.6kLQaP0FbJmPGDeSF0r03RPaPgaieP9zXv3fsrGvum7qCq', 'cashier', 1, '2025-12-17 17:40:26', '2025-12-17 17:40:26'),
(2, 'Mashab Jadoon', 'mashab', '$2y$10$YGCLpMpk4qLWDSAEdQTZGux0wAPUY2XPFYj//atk/izy8W2izJIrS', 'admin', 1, '2025-12-17 17:40:47', '2025-12-17 17:40:47'),
(3, 'Junaid Sattar Abbasi', 'junaid', '$2y$10$YcX/xURaYKVQ05n/uB5apO3hxPpkkHhEjVBUrAlbfnhlMnplFFEGC', 'cashier', 1, '2025-12-18 19:41:23', '2025-12-18 19:41:23');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
