-- Full Database Schema Dump
-- Generated 2026-03-18 09:28:35

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Table structure for table `activity_feed`
DROP TABLE IF EXISTS `activity_feed`;
CREATE TABLE `activity_feed` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `activity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `ad_campaigns`
DROP TABLE IF EXISTS `ad_campaigns`;
CREATE TABLE `ad_campaigns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `restaurant_id` int NOT NULL,
  `campaign_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `status` enum('active','paused','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `description` text COLLATE utf8mb4_unicode_ci,
  `campaign_type` enum('ad','happy_hour') COLLATE utf8mb4_unicode_ci DEFAULT 'ad',
  `views` int DEFAULT '0',
  `clicks` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `bookings`
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `restaurant_id` int NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `guests` int NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `review_status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `inviter_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_book_user` (`user_id`),
  KEY `fk_book_res` (`restaurant_id`),
  CONSTRAINT `fk_book_res` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`u_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4025 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `favorites`
DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `restaurant_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_fav` (`user_id`,`restaurant_id`),
  KEY `fk_fav_res` (`restaurant_id`),
  CONSTRAINT `fk_fav_res` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`u_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `restaurant_images`
DROP TABLE IF EXISTS `restaurant_images`;
CREATE TABLE `restaurant_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `restaurant_id` int NOT NULL,
  `image_path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_img_res` (`restaurant_id`),
  CONSTRAINT `fk_img_res` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `restaurant_menu`
DROP TABLE IF EXISTS `restaurant_menu`;
CREATE TABLE `restaurant_menu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `restaurant_id` int NOT NULL,
  `item_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_price` decimal(10,2) NOT NULL,
  `item_discount` decimal(5,2) DEFAULT '0.00',
  `dietary_tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=MyISAM AUTO_INCREMENT=210 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `restaurant_schedule`
DROP TABLE IF EXISTS `restaurant_schedule`;
CREATE TABLE `restaurant_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `restaurant_id` int NOT NULL,
  `day_of_week` enum('sun','mon','tue','wed','thu','fri','sat') NOT NULL,
  `is_closed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fk_sch_res` (`restaurant_id`),
  CONSTRAINT `fk_sch_res` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `restaurant_tables`
DROP TABLE IF EXISTS `restaurant_tables`;
CREATE TABLE `restaurant_tables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `restaurant_id` int NOT NULL,
  `table_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` int NOT NULL,
  `x_pos` int DEFAULT '0',
  `y_pos` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `restaurants`
DROP TABLE IF EXISTS `restaurants`;
CREATE TABLE `restaurants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `manager_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `cuisine` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `primary_image` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `seating_type` enum('inside','outside','both') DEFAULT NULL,
  `atmosphere` varchar(50) DEFAULT NULL,
  `amenities` text,
  `avg_price` decimal(10,2) DEFAULT NULL,
  `max_guests` int DEFAULT '20',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `is_published` tinyint(1) DEFAULT '0',
  `is_paused` tinyint(1) DEFAULT '0',
  `is_featured` tinyint(1) DEFAULT '0',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `menu_file` varchar(255) DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `is_blocked` tinyint(1) DEFAULT '0',
  `block_message` text,
  PRIMARY KEY (`id`),
  KEY `fk_res_manager` (`manager_id`),
  CONSTRAINT `fk_res_manager` FOREIGN KEY (`manager_id`) REFERENCES `tbl_manager` (`m_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `reviews`
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `user_id` int NOT NULL,
  `restaurant_id` int NOT NULL,
  `rating` int NOT NULL,
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_verified` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `fk_rev_user` (`user_id`),
  KEY `fk_rev_res` (`restaurant_id`),
  CONSTRAINT `fk_rev_book` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_res` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`u_id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_chk_1` CHECK (((`rating` >= 1) and (`rating` <= 5)))
) ENGINE=InnoDB AUTO_INCREMENT=1236 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `saved_restaurants`
DROP TABLE IF EXISTS `saved_restaurants`;
CREATE TABLE `saved_restaurants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `restaurant_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`restaurant_id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `staff`
DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id` int NOT NULL AUTO_INCREMENT,
  `restaurant_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `performance_score` decimal(3,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `tbl_activity_logs`
DROP TABLE IF EXISTS `tbl_activity_logs`;
CREATE TABLE `tbl_activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `restaurant_id` int DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `restaurant_id` (`restaurant_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `tbl_admin`
DROP TABLE IF EXISTS `tbl_admin`;
CREATE TABLE `tbl_admin` (
  `a_id` int NOT NULL AUTO_INCREMENT,
  `a_firstname` varchar(50) NOT NULL,
  `a_lastname` varchar(50) NOT NULL,
  `a_email` varchar(100) NOT NULL,
  `a_password` varchar(255) NOT NULL,
  `a_gender` enum('Male','Female','Other') DEFAULT NULL,
  `a_dob` date DEFAULT NULL,
  `a_image` varchar(255) DEFAULT NULL,
  `a_bio` text,
  `a_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`a_id`),
  UNIQUE KEY `a_email` (`a_email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `tbl_mail_log`
DROP TABLE IF EXISTS `tbl_mail_log`;
CREATE TABLE `tbl_mail_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recipient` varchar(255) NOT NULL,
  `email_to` varchar(255) NOT NULL,
  `email_type` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `error_message` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `tbl_manager`
DROP TABLE IF EXISTS `tbl_manager`;
CREATE TABLE `tbl_manager` (
  `m_id` int NOT NULL AUTO_INCREMENT,
  `m_firstname` varchar(50) NOT NULL,
  `m_lastname` varchar(50) NOT NULL,
  `m_email` varchar(100) NOT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `m_password` varchar(255) DEFAULT NULL,
  `m_phone` varchar(20) DEFAULT NULL,
  `m_gender` enum('Male','Female','Other') DEFAULT NULL,
  `m_dob` date DEFAULT NULL,
  `m_image` varchar(255) DEFAULT NULL,
  `m_bio` text,
  `m_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`m_id`),
  UNIQUE KEY `m_email` (`m_email`)
) ENGINE=InnoDB AUTO_INCREMENT=215 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `tbl_users`
DROP TABLE IF EXISTS `tbl_users`;
CREATE TABLE `tbl_users` (
  `u_id` int NOT NULL AUTO_INCREMENT,
  `u_firstname` varchar(50) NOT NULL,
  `u_lastname` varchar(50) NOT NULL,
  `u_email` varchar(100) NOT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `u_password` varchar(255) DEFAULT NULL,
  `u_phone` varchar(20) DEFAULT NULL,
  `u_gender` enum('Male','Female','Other') DEFAULT NULL,
  `u_dob` date DEFAULT NULL,
  `u_image` varchar(255) DEFAULT NULL,
  `u_bio` text,
  `u_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `loyalty_points` int DEFAULT '0',
  `language_pref` varchar(5) DEFAULT 'en',
  PRIMARY KEY (`u_id`),
  UNIQUE KEY `u_email` (`u_email`)
) ENGINE=InnoDB AUTO_INCREMENT=216 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `timeslot_discounts`
DROP TABLE IF EXISTS `timeslot_discounts`;
CREATE TABLE `timeslot_discounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `restaurant_id` int NOT NULL,
  `day_of_week` enum('sun','mon','tue','wed','thu','fri','sat') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `discount_percentage` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_disc_res` (`restaurant_id`),
  CONSTRAINT `fk_disc_res` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timeslot_discounts_chk_1` CHECK (((`discount_percentage` >= 0) and (`discount_percentage` <= 100)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table structure for table `waitlist`
DROP TABLE IF EXISTS `waitlist`;
CREATE TABLE `waitlist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `restaurant_id` int NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `guests` int NOT NULL,
  `status` enum('pending','notified','expired') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

INSERT INTO `tbl_admin` (`a_id`, `a_firstname`, `a_lastname`, `a_email`, `a_password`, `a_gender`, `a_dob`, `a_image`, `a_bio`, `a_created_at`) VALUES (1, 'Admin', 'User', 'admin@quicktable.com', '.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, NULL, CURRENT_TIMESTAMP);

