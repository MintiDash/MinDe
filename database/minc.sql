-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               12.2.2-MariaDB - MariaDB Server
-- Server OS:                    Win64
-- HeidiSQL Version:             12.14.0.7165
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for minc
CREATE DATABASE IF NOT EXISTS `minc` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `minc`;

-- Dumping structure for table minc.audit_trail
CREATE TABLE IF NOT EXISTS `audit_trail` (
  `audit_trail_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `session_username` varchar(255) NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` varchar(100) NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `change_reason` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `system_id` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`audit_trail_id`),
  KEY `audit_trail_user_id_foreign` (`user_id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_action` (`action`),
  KEY `idx_transaction_id` (`transaction_id`),
  CONSTRAINT `audit_trail_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.audit_trail: ~70 rows (approximately)
INSERT INTO `audit_trail` (`audit_trail_id`, `user_id`, `session_username`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `change_reason`, `timestamp`, `ip_address`, `user_agent`, `system_id`, `transaction_id`) VALUES
	(1, 1, 'Root', 'create', 'user', '2', NULL, '{"fname":"Test","lname":"User","email":"test@gmail.com","username":null,"contact_num":null,"user_level_id":"1","user_status":"active"}', NULL, '2025-10-23 09:44:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(2, 1, 'Root', 'update', 'user', '2', '{"fname":"Test","lname":"User","email":"test@gmail.com","username":null,"contact_num":null,"user_level_id":1,"user_status":"active"}', '{"fname":"Test","lname":"User Edited","email":"test@gmail.com","username":null,"contact_num":null,"user_level_id":"1","user_status":"active"}', NULL, '2025-10-23 09:57:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(3, 1, 'Root', 'update', 'user', '2', '{"user_status":"active"}', '{"user_status":"inactive"}', 'User deactivated', '2025-10-23 09:58:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(4, 1, 'Root', 'update', 'user', '2', '{"user_status":"inactive"}', '{"user_status":"active"}', 'User activated', '2025-10-23 09:58:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(5, 1, 'Root', 'update', 'user', '2', '{"user_status":"active"}', '{"user_status":"inactive"}', 'User deactivated', '2025-10-23 11:12:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(6, 1, 'Root', 'CREATE', 'classroom', '8', NULL, '{"classroom_name":"Test","classroom_code":"001","building":"Main","floor":1,"capacity":40,"classroom_type":"lecture","status":"active"}', 'Added new classroom', '2025-10-23 11:34:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(7, 1, 'Root', 'CREATE', 'classroom', '9', NULL, '{"classroom_name":"Test2","classroom_code":"002","building":"Main","floor":2,"capacity":40,"classroom_type":"lecture","status":"active"}', 'Added new classroom', '2025-10-23 11:36:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(8, 1, 'Root', 'UPDATE', 'classroom', '8', '{"classroom_name":"Test","classroom_code":"001","building":"Main","floor":1,"capacity":40,"classroom_type":"lecture","description":"","status":"active"}', '{"classroom_name":"Test - Edited","classroom_code":"001","building":"Main","floor":1,"capacity":40,"classroom_type":"lecture","description":"","status":"active"}', 'Updated classroom information', '2025-10-23 11:39:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(9, 1, 'Root', 'DELETE', 'classroom', '9', '{"classroom_name":"Test2","classroom_code":"002","building":"Main","floor":2,"capacity":40,"classroom_type":"lecture","description":"","status":"active"}', NULL, 'Deleted classroom', '2025-10-23 11:40:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(10, 1, 'Root', 'CREATE', 'subject', '6', NULL, '{"subject_name":"Test","subject_code":"TEST01","grade_level":"Grade 1","description":"","status":"Active"}', NULL, '2025-10-23 12:14:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(11, 1, 'Root', 'UPDATE', 'subject', '6', '{"subject_name":"Test","subject_code":"TEST01","grade_level":"Grade 1","description":"","status":"Active"}', '{"subject_name":"Test - Edited","subject_code":"TEST01","grade_level":"Grade 1","description":"","status":"Active"}', NULL, '2025-10-23 12:17:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(12, 1, 'Root', 'DELETE', 'subject', '6', '{"subject_name":"Test - Edited","subject_code":"TEST01","grade_level":"Grade 1","description":"","status":"Active"}', NULL, NULL, '2025-10-23 12:19:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(13, 1, 'Root', 'update', 'user', '2', '{"user_status":"inactive"}', '{"user_status":"active"}', 'User activated', '2025-10-23 12:49:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(14, 1, 'Root', 'update', 'user', '2', '{"fname":"Test","lname":"User Edited","email":"test@gmail.com","username":null,"contact_num":null,"user_level_id":1,"user_status":"active"}', '{"fname":"Test","lname":"User Edited","email":"test@gmail.com","username":null,"contact_num":null,"user_level_id":"3","user_status":"active"}', NULL, '2025-10-23 12:50:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(15, 1, 'Root', 'update', 'user', '2', '{"fname":"Test","lname":"User Edited","email":"test@gmail.com","username":null,"contact_num":null,"user_level_id":3,"user_status":"active"}', '{"fname":"Test","lname":"User Edited","email":"test@gmail.com","username":null,"contact_num":null,"user_level_id":"2","user_status":"active"}', NULL, '2025-10-23 12:51:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(16, 1, 'Root', 'create', 'schedule', '1', NULL, '{"classroom_id":5,"subject_id":5,"teacher_id":2,"day_of_week":"Monday","start_time":"09:08","end_time":"09:10","status":"Active"}', 'Added new schedule', '2025-10-23 13:09:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'aclc_rfid_system', 'txn_68fa28f2c0c076.20094375'),
	(17, 1, 'Root', 'UPDATE', 'schedule', '1', '{"classroom_id":5,"subject_id":5,"teacher_id":2,"day_of_week":"Monday","start_time":"09:08:00","end_time":"09:10:00","status":"Active"}', '{"classroom_id":5,"subject_id":5,"teacher_id":2,"day_of_week":"Monday","start_time":"09:08","end_time":"09:11","status":"Active"}', 'Updated schedule', '2025-10-23 13:11:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'aclc_rfid_system', 'txn_68fa299d3cc625.37188866'),
	(18, 1, 'Root', 'DELETE', 'schedule', '1', '{"schedule_id":1,"classroom_id":5,"classroom_name":"Auditorium","classroom_code":"D-001","subject_id":5,"subject_name":"Araling Panlipunan","subject_code":"AP-01","teacher_id":2,"teacher_name":"Test User Edited","day_of_week":"Monday","start_time":"09:08:00","end_time":"09:11:00","status":"Active"}', NULL, 'Deleted schedule', '2025-10-23 13:13:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'aclc_rfid_system', 'txn_68fa2a01a236a1.51473432'),
	(19, 1, 'Root', 'create', 'schedule', '2', NULL, '{"classroom_id":5,"subject_id":5,"teacher_id":2,"day_of_week":"Monday","start_time":"07:30","end_time":"09:30","status":"Active"}', 'Added new schedule', '2025-10-23 13:34:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'aclc_rfid_system', 'txn_68fa2eeaca0af5.31514882'),
	(20, 1, 'Root', 'CREATE', 'section', '1', NULL, '{"section_name":"Grade 7 - Ei","section_code":"G7-EINSTEIN","grade_level":"Grade 7","adviser_id":2,"school_year":"2025-2024","status":"active"}', 'Added new section', '2025-10-23 14:36:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(21, 1, 'Root', 'UPDATE', 'section', '1', '{"section_name":"Grade 7 - Ei","section_code":"G7-EINSTEIN","grade_level":"Grade 7","adviser_id":2,"school_year":"2025-2024","status":"active"}', '{"section_name":"Grade 7 - EINSTEIN","section_code":"G7-EINSTEIN","grade_level":"Grade 7","adviser_id":2,"school_year":"2025-2024","status":"active"}', 'Updated section details', '2025-10-23 14:38:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(22, 1, 'Root', 'UPDATE', 'section', '1', '{"section_name":"Grade 7 - EINSTEIN","section_code":"G7-EINSTEIN","grade_level":"Grade 7","adviser_id":2,"school_year":"2025-2024","status":"active"}', '{"section_name":"EINSTEIN","section_code":"G7-EINSTEIN","grade_level":"Grade 7","adviser_id":2,"school_year":"2025-2024","status":"active"}', 'Updated section details', '2025-10-23 14:38:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(23, 1, 'Root', 'DELETE', 'section', '1', '{"section_name":"EINSTEIN","section_code":"G7-EINSTEIN","grade_level":"Grade 7","adviser_id":2,"school_year":"2025-2024","status":"active"}', NULL, 'Deleted section and associated student assignments', '2025-10-23 14:39:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(24, 1, 'Root', 'create', 'user', '3', NULL, '{"fname":"Student","lname":"Test","email":"Student@test.com","username":null,"contact_num":null,"user_level_id":"4","user_status":"active"}', NULL, '2025-10-26 03:51:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(25, 1, 'Root', 'CREATE', 'section', '2', NULL, '{"section_name":"Grade 7 - EINSTEIN","section_code":"G7-EINSTEIN","grade_level":"Grade 7","adviser_id":2,"school_year":"2025-2026","status":"active"}', 'Added new section', '2025-10-26 03:51:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(26, 1, 'Root', 'assign_student', 'section_students', '3', NULL, '{"section_id":2,"section_name":"Grade 7 - EINSTEIN","student_id":3,"student_name":"Student  Test","school_year":"2025-2026","enrollment_date":"2025-10-26","remarks":null}', 'Student assigned to section: Grade 7 - EINSTEIN', '2025-10-26 03:58:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(27, 1, 'Root', 'CREATE', 'section', '3', NULL, '{"section_name":"Grade 7 - TESLA","section_code":"G7-TESLA","grade_level":"Grade 7","adviser_id":2,"school_year":"2025-2026","status":"active"}', 'Added new section', '2025-10-26 03:58:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(28, 1, 'Root', 'transfer_student', 'section_students', '3', '{"section_id":2,"section_name":"Grade 7 - EINSTEIN","status":"active"}', '{"section_id":2,"section_name":"Grade 7 - EINSTEIN","status":"transferred"}', 'Student transferred to new section: Grade 7 - TESLA', '2025-10-26 03:59:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(29, 1, 'Root', 'assign_student', 'section_students', '4', NULL, '{"section_id":3,"section_name":"Grade 7 - TESLA","student_id":3,"student_name":"Student  Test","school_year":"2025-2026","enrollment_date":"2025-10-26","remarks":null}', 'Student assigned to section: Grade 7 - TESLA', '2025-10-26 03:59:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(30, 1, 'Root', 'remove_student', 'section_students', '4', '{"section_id":3,"section_name":"Grade 7 - TESLA","student_id":3,"student_name":"Student  Test","school_year":"2025-2026","status":"active"}', '{"section_id":3,"section_name":"Grade 7 - TESLA","student_id":3,"student_name":"Student  Test","school_year":"2025-2026","status":"dropped"}', 'Student removed from section: Grade 7 - TESLA', '2025-10-26 04:00:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(31, 1, 'Root', 'bulk_assign_student', 'section_students', '4', NULL, '{"section_id":3,"section_name":"Grade 7 - TESLA","student_id":3,"student_name":"Student  Test","school_year":"2025-2026","enrollment_date":"2025-10-26","remarks":null}', 'Student assigned to section via bulk assignment: Grade 7 - TESLA', '2025-10-26 04:03:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(32, 1, 'Root', 'UPDATE', 'schedule', '2', '{"classroom_id":5,"subject_id":5,"teacher_id":2,"section_id":null,"day_of_week":"Monday","start_time":"07:30:00","end_time":"09:30:00","status":"Active"}', '{"classroom_id":5,"subject_id":5,"teacher_id":2,"section_id":2,"day_of_week":"Monday","start_time":"07:30","end_time":"09:30","status":"Active"}', 'Updated schedule', '2025-10-26 06:49:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'aclc_rfid_system', 'txn_68fdc487aac832.57066288'),
	(33, 1, 'Root', 'create', 'user', '4', NULL, '{"fname":"Parent","lname":"Test","email":"Parent@gmail.com","username":null,"contact_num":null,"user_level_id":"3","user_status":"active"}', NULL, '2025-10-27 08:55:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(34, 1, 'Root', 'assign_parent', 'student_parents', '1', NULL, '{"student_id":3,"student_name":"Student  Test","parent_id":4,"parent_name":"Parent  Test","relationship_type":"mother","is_primary_contact":1,"is_emergency_contact":0}', 'Parent assigned to student during enrollment', '2025-10-27 08:55:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(35, 1, 'Root', 'create', 'user', '5', NULL, '{"fname":"Teacher","lname":"Test","email":"teacher@gmail.com","username":null,"contact_num":null,"user_level_id":"2","user_status":"active"}', NULL, '2025-10-27 13:21:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(36, 1, 'Root', 'update_parent_relationship', 'student_parents', '1', '{"is_primary_contact":1,"is_emergency_contact":0,"status":"active"}', '{"relationship_type":"mother","is_primary_contact":0,"is_emergency_contact":0,"status":"active"}', 'Updated parent relationship during student enrollment', '2025-10-27 13:45:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, NULL),
	(37, 1, 'Root', 'login', 'user', '1', NULL, '{"email":"root@gmail.com","user_level":"IT Personnel","login_time":"2025-11-28 11:38:57"}', 'User logged in successfully', '2025-11-28 10:38:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(38, 1, 'Root', 'logout', 'user', '1', '{"email":"root@gmail.com","logout_time":"2025-11-28 11:46:20"}', NULL, 'User logged out', '2025-11-28 10:46:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(39, 1, 'Root', 'login', 'user', '1', NULL, '{"email":"root@gmail.com","user_level":"IT Personnel","login_time":"2025-11-28 11:46:29"}', 'User logged in successfully', '2025-11-28 10:46:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(40, 1, 'Root', 'login', 'user', '1', NULL, '{"email":"root@gmail.com","user_level":"IT Personnel","login_time":"2025-11-28 11:46:56"}', 'User logged in successfully', '2025-11-28 10:46:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(41, 1, 'Root', 'login', 'user', '1', NULL, '{"email":"root@gmail.com","user_level":"IT Personnel","login_time":"2025-11-28 13:48:33"}', 'User logged in successfully', '2025-11-28 12:48:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(42, 1, 'Root', 'login', 'user', '1', NULL, '{"email":"root@gmail.com","user_level":"IT Personnel","login_time":"2025-11-29 03:36:07"}', 'User logged in successfully', '2025-11-29 02:36:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(43, 1, 'Root', 'update', 'user', '5', '{"user_status":"active"}', '{"user_status":"inactive"}', 'User deactivated', '2025-11-29 02:46:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, NULL),
	(44, 1, 'Root', 'update', 'user', '5', '{"user_status":"inactive"}', '{"user_status":"active"}', 'User activated', '2025-11-29 02:47:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, NULL),
	(45, 1, 'Root', 'update', 'user', '5', '{"fname":"Teacher","lname":"Test","email":"teacher@gmail.com","username":null,"contact_num":null,"user_level_id":2,"user_status":"active"}', '{"fname":"Teacher","lname":"Testt","email":"teacher@gmail.com","username":null,"contact_num":null,"user_level_id":"2","user_status":"active"}', NULL, '2025-11-29 02:50:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, NULL),
	(46, 1, 'Root Admin', 'UPDATE', 'category', '1', '{"status":"active"}', '{"status":"inactive"}', 'Category deactivated', '2025-11-29 04:04:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(47, 1, 'Root Admin', 'UPDATE', 'category', '1', '{"status":"inactive"}', '{"status":"active"}', 'Category activated', '2025-11-29 04:05:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(48, 1, 'Root Admin', 'UPDATE', 'category', '1', '{"category_name":"Wheels & Tyres","category_slug":"wheels-tyres","category_description":"Complete selection of wheels, tyres, and related accessories","category_image":"wheels-tyres.jpg","display_order":1,"status":"active"}', '{"category_name":"Wheels & Tyres","category_slug":"wheels-tyres","category_description":"Complete selection of wheels, tyres, and related accessories","category_image":"category_1764389174_692a713674299.webp","display_order":1,"status":"active"}', 'Updated category information', '2025-11-29 04:06:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(49, 1, 'Root Admin', 'UPDATE', 'product_line', '1', '{"status":"active"}', '{"status":"inactive"}', 'Product line deactivated', '2025-11-29 04:26:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(50, 1, 'Root Admin', 'UPDATE', 'product_line', '1', '{"status":"inactive"}', '{"status":"active"}', 'Product line activated', '2025-11-29 04:26:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(51, 1, 'Root', 'login', 'user', '1', NULL, '{"email":"root@gmail.com","user_level":"IT Personnel","login_time":"2025-11-29 05:36:30"}', 'User logged in successfully', '2025-11-29 04:36:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(52, 1, 'Root Admin', 'UPDATE', 'product', '1', '{"status":"active"}', '{"status":"inactive"}', 'Product deactivated', '2025-11-29 05:00:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(53, 1, 'Root Admin', 'UPDATE', 'product_line', '7', '{"category_id":3,"product_line_name":"Mobile Electronics","product_line_slug":"mobile-electronics","product_line_description":"Variety of Mobile Electronics","product_line_image":"mobile-electronics.png","display_order":1,"status":"active"}', '{"category_id":3,"product_line_name":"Mobile Electronics","product_line_slug":"mobile-electronics","product_line_description":"Variety of Mobile Electronics","product_line_image":"product_line_1764393130_692a80aa7bb88.png","display_order":1,"status":"active"}', 'Updated product line information', '2025-11-29 05:12:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(54, 1, 'Root Admin', 'UPDATE', 'product_line', '1', '{"category_id":1,"product_line_name":"Wheel Accessories","product_line_slug":"wheel-accessories","product_line_description":"Variety of Wheel Accessories","product_line_image":"product_line_692a754617070.jpg","display_order":1,"status":"active"}', '{"category_id":1,"product_line_name":"Wheel Accessories","product_line_slug":"wheel-accessories","product_line_description":"Variety of Wheel Accessories","product_line_image":"product_line_1764393147_692a80bb1fd0c.jpg","display_order":1,"status":"active"}', 'Updated product line information', '2025-11-29 05:12:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(55, 1, 'Root Admin', 'UPDATE', 'product_line', '2', '{"category_id":1,"product_line_name":"Nuts","product_line_slug":"nuts","product_line_description":"Variety of Nuts","product_line_image":"nuts.png","display_order":2,"status":"active"}', '{"category_id":1,"product_line_name":"Nuts","product_line_slug":"nuts","product_line_description":"Variety of Nuts","product_line_image":"product_line_1764393158_692a80c670c28.png","display_order":2,"status":"active"}', 'Updated product line information', '2025-11-29 05:12:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(56, 1, 'Root Admin', 'UPDATE', 'product_line', '3', '{"category_id":1,"product_line_name":"Tyres","product_line_slug":"tyres","product_line_description":"Variety of Tyre Accessories","product_line_image":"tyre.png","display_order":3,"status":"active"}', '{"category_id":1,"product_line_name":"Tires","product_line_slug":"tyres","product_line_description":"Variety of Tyre Accessories","product_line_image":"product_line_1764393179_692a80db60f82.png","display_order":3,"status":"active"}', 'Updated product line information', '2025-11-29 05:12:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(57, 1, 'Root Admin', 'UPDATE', 'product_line', '4', '{"category_id":2,"product_line_name":"Window Visors","product_line_slug":"window-visors","product_line_description":"Variety of Window Visor Accessories","product_line_image":"window-visors.png","display_order":1,"status":"active"}', '{"category_id":2,"product_line_name":"Window Visors","product_line_slug":"window-visors","product_line_description":"Variety of Window Visor Accessories","product_line_image":"product_line_1764393193_692a80e9349d0.png","display_order":1,"status":"active"}', 'Updated product line information', '2025-11-29 05:13:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(58, 1, 'Root Admin', 'UPDATE', 'product_line', '5', '{"category_id":2,"product_line_name":"Door Handles & Locks","product_line_slug":"door-handles-locks","product_line_description":"Various Door Accessories","product_line_image":"door-accessories.png","display_order":2,"status":"active"}', '{"category_id":2,"product_line_name":"Door Handles & Locks","product_line_slug":"door-handles-locks","product_line_description":"Various Door Accessories","product_line_image":"product_line_1764393230_692a810e5a5fc.png","display_order":2,"status":"active"}', 'Updated product line information', '2025-11-29 05:13:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(59, 1, 'Root Admin', 'UPDATE', 'product_line', '6', '{"category_id":2,"product_line_name":"Truck Accessories","product_line_slug":"truck-accessories","product_line_description":"Variety of Truck Accessories","product_line_image":"truck-accessories.png","display_order":3,"status":"active"}', '{"category_id":2,"product_line_name":"Truck Accessories","product_line_slug":"truck-accessories","product_line_description":"Variety of Truck Accessories","product_line_image":"truck-accessories.png","display_order":3,"status":"active"}', 'Updated product line information', '2025-11-29 05:14:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(60, 1, 'Root Admin', 'UPDATE', 'product_line', '6', '{"category_id":2,"product_line_name":"Truck Accessories","product_line_slug":"truck-accessories","product_line_description":"Variety of Truck Accessories","product_line_image":"truck-accessories.png","display_order":3,"status":"active"}', '{"category_id":2,"product_line_name":"Truck Accessories","product_line_slug":"truck-accessories","product_line_description":"Variety of Truck Accessories","product_line_image":"product_line_1764393258_692a812aa66b8.png","display_order":3,"status":"active"}', 'Updated product line information', '2025-11-29 05:14:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(61, 1, 'Root Admin', 'UPDATE', 'product_line', '8', '{"category_id":3,"product_line_name":"Horns & Components","product_line_slug":"horns-components","product_line_description":"Variety of Horns and Components","product_line_image":"car-horn.png","display_order":2,"status":"active"}', '{"category_id":3,"product_line_name":"Horns & Components","product_line_slug":"horns-components","product_line_description":"Variety of Horns and Components","product_line_image":"product_line_1764393272_692a8138e3132.png","display_order":2,"status":"active"}', 'Updated product line information', '2025-11-29 05:14:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(62, 1, 'Root Admin', 'UPDATE', 'product_line', '10', '{"category_id":4,"product_line_name":"Back & Head Lights","product_line_slug":"back-head-lights","product_line_description":"Variety of Lighting Accessories","product_line_image":"car-headlight.png","display_order":1,"status":"active"}', '{"category_id":4,"product_line_name":"Back & Head Lights","product_line_slug":"back-head-lights","product_line_description":"Variety of Lighting Accessories","product_line_image":"product_line_1764393303_692a8157248a5.png","display_order":1,"status":"active"}', 'Updated product line information', '2025-11-29 05:15:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(63, 1, 'Root Admin', 'UPDATE', 'product_line', '10', '{"category_id":4,"product_line_name":"Back & Head Lights","product_line_slug":"back-head-lights","product_line_description":"Variety of Lighting Accessories","product_line_image":"product_line_1764393303_692a8157248a5.png","display_order":1,"status":"active"}', '{"category_id":4,"product_line_name":"Back & Head Lights","product_line_slug":"back-head-lights","product_line_description":"Variety of Lighting Accessories","product_line_image":"product_line_1764393303_692a81579cc0d.png","display_order":1,"status":"active"}', 'Updated product line information', '2025-11-29 05:15:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(64, 1, 'Root Admin', 'UPDATE', 'product_line', '9', '{"category_id":3,"product_line_name":"Switches & Relays","product_line_slug":"switches-relays","product_line_description":"Variety of Wiring Components","product_line_image":"car-relay.png","display_order":3,"status":"active"}', '{"category_id":3,"product_line_name":"Switches & Relays","product_line_slug":"switches-relays","product_line_description":"Variety of Wiring Components","product_line_image":"product_line_1764393315_692a8163cc3c4.png","display_order":3,"status":"active"}', 'Updated product line information', '2025-11-29 05:15:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(65, 1, 'Root Admin', 'UPDATE', 'product', '1', '{"status":"inactive"}', '{"status":"active"}', 'Product activated', '2025-11-29 05:19:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'minc_system', NULL),
	(66, 5, 'Jhoren', 'CREATE', 'order', '1', NULL, '{"order_number":"ORD-20260508-568030","total_amount":5398,"payment_method":"gcash","delivery_method":"shipping","payment_reference":"65468546546565","payment_proof_attached":true,"items_count":2}', 'Order placed with payment proof for admin review', '2026-05-08 07:22:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(67, 5, 'Jhoren', 'logout', 'user', '5', '{"email":"melton07catacutan@gmail.com","logout_time":"2026-05-08 09:37:40"}', NULL, 'User logged out', '2026-05-08 07:37:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(68, 6, 'Jhoren', 'registration_started', 'user', '6', NULL, '{"fname":"Jhoren","lname":"Dollete","email":"melton07catacutan@gmail.com","address":"Blk 2 Lot 3 Bulaon Resettlement, Pulungbulu, Angeles City, Pampanga","contact_num":"09067188100","home_address":"Blk 2 Lot 3 Bulaon Resettlement, Pulungbulu, Angeles City, Pampanga","billing_address":"Blk 2 Lot 3 Bulaon Resettlement, Pulungbulu, Angeles City, Pampanga","barangay":"Pulungbulu","city":"Angeles City","province":"Pampanga","postal_code":"2035","otp_sent":true}', 'Customer registration started with OTP verification', '2026-05-08 07:52:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(69, 6, 'Jhoren', 'registration_otp_resent', 'user', '6', NULL, '{"fname":"Jhoren","lname":"Dollete","email":"melton07catacutan@gmail.com","address":"Blk 2 Lot 3 Bulaon Resettlement, Pulungbulu, Angeles City, Pampanga","contact_num":"09067188100","home_address":"Blk 2 Lot 3 Bulaon Resettlement, Pulungbulu, Angeles City, Pampanga","billing_address":"Blk 2 Lot 3 Bulaon Resettlement, Pulungbulu, Angeles City, Pampanga","barangay":"Pulungbulu","city":"Angeles City","province":"Pampanga","postal_code":"2035","otp_sent":false}', 'Pending registration continued and OTP resent', '2026-05-08 07:52:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(70, 1, 'Root', 'login', 'user', '1', NULL, '{"email":"root@gmail.com","user_level":"Admin","login_time":"2026-05-08 09:52:42","user_type":"admin"}', 'User logged in successfully', '2026-05-08 07:52:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(71, 1, 'Root Admin', 'update_order_state', 'order', '1', '{"order_status":"pending","payment_status":"pending"}', '{"order_status":"shipped","payment_status":"paid","tracking_number":""}', 'Manual status update via dropdown', '2026-05-08 07:57:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, NULL),
	(72, 1, 'Root Admin', 'update_order_state', 'order', '1', '{"order_status":"shipped","payment_status":"paid"}', '{"order_status":"delivered","payment_status":"paid","tracking_number":""}', 'Manual status update via dropdown', '2026-05-08 07:57:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, NULL),
	(73, 1, 'Root', 'CREATE', 'order', '2', NULL, '{"order_number":"ORD-20260508-193727","total_amount":6498,"payment_method":"cod","delivery_method":"pickup","payment_reference":null,"payment_proof_attached":false,"items_count":2}', 'Order placed with COD payment', '2026-05-08 08:05:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(74, 1, 'Root', 'CREATE', 'order', '3', NULL, '{"order_number":"ORD-20260508-874015","total_amount":2999,"payment_method":"cod","delivery_method":"shipping","payment_reference":null,"payment_proof_attached":false,"items_count":1}', 'Order placed with COD payment', '2026-05-08 08:06:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(75, 1, 'Root', 'CREATE', 'order', '4', NULL, '{"order_number":"ORD-20260508-918510","total_amount":2499,"payment_method":"cod","delivery_method":"shipping","payment_reference":null,"payment_proof_attached":false,"items_count":1}', 'Order placed with COD payment', '2026-05-08 08:06:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(76, 1, 'Root', 'CREATE', 'order', '5', NULL, '{"order_number":"ORD-20260508-463601","total_amount":3499,"payment_method":"cod","delivery_method":"shipping","payment_reference":null,"payment_proof_attached":false,"items_count":1}', 'Order placed with COD payment', '2026-05-08 08:07:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(77, 1, 'Root Admin', 'update_order_state', 'order', '2', '{"order_status":"pending","payment_status":"pending"}', '{"order_status":"delivered","payment_status":"paid","tracking_number":""}', 'Manual status update via dropdown', '2026-05-08 08:08:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, NULL),
	(78, 1, 'Root', 'update', 'user', '6', '{"user_status":"inactive"}', '{"user_status":"active"}', 'User activated', '2026-05-08 08:14:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, NULL),
	(79, 1, 'Root Admin', 'update_order_state', 'order', '3', '{"order_status":"pending","payment_status":"pending"}', '{"order_status":"delivered","payment_status":"paid","tracking_number":""}', 'Manual status update via dropdown', '2026-05-08 08:15:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, NULL),
	(80, 1, 'Root Admin', 'update_order_state', 'order', '4', '{"order_status":"pending","payment_status":"pending"}', '{"order_status":"shipped","payment_status":"paid","tracking_number":""}', 'Manual status update via dropdown', '2026-05-08 08:15:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, NULL),
	(81, 1, 'Root Admin', 'CREATE', 'supplier', '1', NULL, '{"supplier_name":"swwww","contact_person":"Jhoren Boseto Dollete","email":"melton07catacutan@gmail.com","phone":"09067188100","address":"Blk 2 Lot 3 Bulaon Resettlement","city":"San fernando","province":"Pampanga","status":"active"}', 'Added new supplier', '2026-05-08 08:21:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(82, 1, 'Root Admin', 'CREATE', 'purchase_order', '1', NULL, '{"po_number":"PO-20260508-3817","supplier_id":1,"supplier_name":"swwww","order_date":"2026-05-08","expected_delivery_date":"2026-05-16","total_amount":22222,"status":"pending"}', 'Created purchase order', '2026-05-08 08:21:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(83, 1, 'Root Admin', 'update_order_state', 'order', '4', '{"order_status":"shipped","payment_status":"paid"}', '{"order_status":"delivered","payment_status":"paid","tracking_number":""}', 'Manual status update via dropdown', '2026-05-08 08:31:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, NULL),
	(84, 1, 'Root Admin', 'update_order_state', 'order', '5', '{"order_status":"pending","payment_status":"pending"}', '{"order_status":"delivered","payment_status":"paid","tracking_number":""}', 'Manual status update via dropdown', '2026-05-08 08:31:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, NULL),
	(85, 1, 'Root Admin', 'update_order_state', 'order', '5', '{"order_status":"delivered","payment_status":"paid"}', '{"order_status":"delivered","payment_status":"paid","tracking_number":""}', 'Manual status update via dropdown', '2026-05-08 08:31:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, NULL),
	(86, 1, 'Root', 'logout', 'user', '1', '{"email":"root@gmail.com","logout_time":"2026-05-08 11:23:12"}', NULL, 'User logged out', '2026-05-08 09:23:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(87, 1, 'Root', 'login', 'user', '1', NULL, '{"email":"root@gmail.com","user_level":"Admin","login_time":"2026-05-08 11:49:41","user_type":"admin"}', 'User logged in successfully', '2026-05-08 09:49:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(88, 1, 'Root', 'logout', 'user', '1', '{"email":"root@gmail.com","logout_time":"2026-05-08 11:49:53"}', NULL, 'User logged out', '2026-05-08 09:49:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(89, 1, 'Root', 'login', 'user', '1', NULL, '{"email":"root@gmail.com","user_level":"Admin","login_time":"2026-05-08 11:50:02","user_type":"admin"}', 'User logged in successfully', '2026-05-08 09:50:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(90, 1, 'Root', 'logout', 'user', '1', '{"email":"root@gmail.com","logout_time":"2026-05-08 12:01:53"}', NULL, 'User logged out', '2026-05-08 10:01:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(91, 2, 'Test', 'login', 'user', '2', NULL, '{"email":"test@gmail.com","user_level":"Employee","login_time":"2026-05-08 12:02:08","user_type":"admin"}', 'User logged in successfully', '2026-05-08 10:02:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(92, 2, 'Test', 'logout', 'user', '2', '{"email":"test@gmail.com","logout_time":"2026-05-08 12:02:47"}', NULL, 'User logged out', '2026-05-08 10:02:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(93, 2, 'Test', 'login', 'user', '2', NULL, '{"email":"test@gmail.com","user_level":"Employee","login_time":"2026-05-08 12:03:06","user_type":"admin"}', 'User logged in successfully', '2026-05-08 10:03:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(94, 2, 'Test', 'logout', 'user', '2', '{"email":"test@gmail.com","logout_time":"2026-05-08 12:04:09"}', NULL, 'User logged out', '2026-05-08 10:04:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(95, 1, 'Root', 'login', 'user', '1', NULL, '{"email":"root@gmail.com","user_level":"Admin","login_time":"2026-05-08 12:04:17","user_type":"admin"}', 'User logged in successfully', '2026-05-08 10:04:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL),
	(96, 1, 'Root', 'logout', 'user', '1', '{"email":"root@gmail.com","logout_time":"2026-05-08 12:09:11"}', NULL, 'User logged out', '2026-05-08 10:09:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'minc_system', NULL);

-- Dumping structure for table minc.cart
CREATE TABLE IF NOT EXISTS `cart` (
  `cart_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cart_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_session` (`session_id`),
  CONSTRAINT `cart_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.cart: ~1 rows (approximately)

-- Dumping structure for table minc.cart_items
CREATE TABLE IF NOT EXISTS `cart_items` (
  `cart_item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cart_item_id`),
  KEY `cart_id` (`cart_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `cart_items_cart_id_foreign` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`cart_id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.cart_items: ~1 rows (approximately)

-- Dumping structure for table minc.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(255) NOT NULL,
  `category_slug` varchar(255) NOT NULL,
  `category_description` text DEFAULT NULL,
  `category_image` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_slug` (`category_slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.categories: ~4 rows (approximately)
INSERT INTO `categories` (`category_id`, `category_name`, `category_slug`, `category_description`, `category_image`, `display_order`, `status`, `created_at`, `updated_at`) VALUES
	(1, 'Wheels & Tyres', 'wheels-tyres', 'Complete selection of wheels, tyres, and related accessories', 'category_1764389174_692a713674299.webp', 1, 'active', '2025-11-29 03:30:30', '2025-11-29 04:06:14'),
	(2, 'External Parts', 'external-parts', 'Exterior components and accessories for your vehicle', 'external-parts.jpg', 2, 'active', '2025-11-29 03:30:30', '2025-11-29 03:30:30'),
	(3, 'Internal Parts', 'internal-parts', 'Interior components, electronics, and accessories', 'internal-parts.jpg', 3, 'active', '2025-11-29 03:30:30', '2025-11-29 03:30:30'),
	(4, 'Car Parts', 'car-parts', 'Essential automotive parts and components', 'car-parts.jpg', 4, 'active', '2025-11-29 03:30:30', '2025-11-29 03:30:30');

-- Dumping structure for table minc.chat_messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `message_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint(20) unsigned DEFAULT NULL,
  `sender_name` varchar(255) NOT NULL,
  `sender_email` varchar(255) DEFAULT NULL,
  `sender_type` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `message_content` longtext NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `sender_type` (`sender_type`),
  KEY `created_at` (`created_at`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.chat_messages: ~0 rows (approximately)
INSERT INTO `chat_messages` (`message_id`, `sender_id`, `sender_name`, `sender_email`, `sender_type`, `message_content`, `is_read`, `read_at`, `created_at`, `session_id`) VALUES
	(1, NULL, 'Customer', NULL, 'customer', 'nh', 1, '2026-05-08 09:54:43', '2026-05-08 09:32:13', 'session_1778199341042_nxhhdlhn4'),
	(2, NULL, 'Customer', NULL, 'customer', 'order', 1, '2026-05-08 09:54:43', '2026-05-08 09:41:36', 'session_1778199341042_nxhhdlhn4'),
	(3, NULL, 'Customer', NULL, 'customer', 'hello', 1, '2026-05-08 09:54:43', '2026-05-08 09:50:47', 'session_1778199341042_nxhhdlhn4'),
	(4, NULL, 'Customer', NULL, 'customer', 'hellooo??', 1, '2026-05-08 09:54:43', '2026-05-08 09:52:23', 'session_1778199341042_nxhhdlhn4'),
	(5, NULL, 'MinC Support', NULL, 'admin', 'hello user', 0, NULL, '2026-05-08 09:55:13', 'session_1778199341042_nxhhdlhn4'),
	(6, NULL, 'Customer', NULL, 'customer', 'sada', 1, '2026-05-08 10:02:12', '2026-05-08 10:00:35', 'session_1778199341042_nxhhdlhn4'),
	(7, NULL, 'MinC Support', NULL, 'admin', 'helloo', 0, NULL, '2026-05-08 10:00:47', 'session_1778199341042_nxhhdlhn4');

-- Dumping structure for table minc.customers
CREATE TABLE IF NOT EXISTS `customers` (
  `customer_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `customer_type` enum('guest','registered') NOT NULL DEFAULT 'guest',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_email` (`email`),
  KEY `idx_email_customer_type` (`email`,`customer_type`),
  CONSTRAINT `customers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.customers: ~1 rows (approximately)
INSERT INTO `customers` (`customer_id`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `city`, `province`, `postal_code`, `customer_type`, `created_at`, `updated_at`) VALUES
	(1, 5, 'Teacher', 'Testt', 'teacher@gmail.com', '09067188100', 'a, Pulung Maragul, Angeles City, Pampanga', 'Angeles City', 'Pampanga', '2035', 'registered', '2026-05-08 07:22:20', '2026-05-08 07:22:20'),
	(2, 1, 'Root', 'Admin', 'root@gmail.com', '09067188100', 'a, Pulung Maragul, Angeles City, Pampanga', 'Angeles City', 'Pampanga', '2035', 'registered', '2026-05-08 08:05:49', '2026-05-08 08:07:08');

-- Dumping structure for table minc.email_verification_tokens
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
  `token_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `email` (`email`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `fk_verification_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.email_verification_tokens: ~2 rows (approximately)
INSERT INTO `email_verification_tokens` (`token_id`, `user_id`, `token`, `token_hash`, `email`, `created_at`, `expires_at`, `verified_at`, `is_used`) VALUES
	(1, 6, '100488-9e00e52dcf1a82d1', '21a1e3e69d5dbb6192981fbfb613e31cb1cde8684c697bdffb2a556124acc72b', 'melton07catacutan@gmail.com', '2026-05-08 07:52:09', '2026-05-08 02:02:09', NULL, 0),
	(2, 6, '714143-c2692694392ce828', '54cb07bf6d1dd0e919cde5578f12913ee066a92b3cca16d732691c301eab44d2', 'melton07catacutan@gmail.com', '2026-05-08 07:52:13', '2026-05-08 02:02:13', NULL, 0);

-- Dumping structure for table minc.order_items
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.order_items: ~2 rows (approximately)
INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `product_name`, `product_code`, `quantity`, `price`, `subtotal`, `created_at`) VALUES
	(1, 1, 29, 'LED Strip Tail Lights', 'LIGHT-003', 1, 1899.00, 1899.00, '2026-05-08 07:22:20'),
	(2, 1, 28, 'Tail Light Assembly', 'LIGHT-002', 1, 3499.00, 3499.00, '2026-05-08 07:22:20'),
	(3, 2, 28, 'Tail Light Assembly', 'LIGHT-002', 1, 3499.00, 3499.00, '2026-05-08 08:05:49'),
	(4, 2, 27, 'LED Headlight Bulbs H7', 'LIGHT-001', 1, 2999.00, 2999.00, '2026-05-08 08:05:49'),
	(5, 3, 27, 'LED Headlight Bulbs H7', 'LIGHT-001', 1, 2999.00, 2999.00, '2026-05-08 08:06:14'),
	(6, 4, 31, 'Power Side Mirror Right', 'MIRROR-002', 1, 2499.00, 2499.00, '2026-05-08 08:06:39'),
	(7, 5, 20, 'Dash Camera HD', 'ME-002', 1, 3499.00, 3499.00, '2026-05-08 08:07:08');

-- Dumping structure for table minc.orders
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) unsigned NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cod','bank_transfer','gcash','paymaya') NOT NULL DEFAULT 'cod',
  `delivery_method` enum('shipping','pickup') NOT NULL DEFAULT 'shipping',
  `payment_reference` varchar(120) DEFAULT NULL,
  `payment_proof_path` varchar(255) DEFAULT NULL,
  `payment_proof_uploaded_at` timestamp NULL DEFAULT NULL,
  `payment_reviewed_at` timestamp NULL DEFAULT NULL,
  `payment_reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `payment_review_notes` text DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `order_status` enum('pending','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `shipping_address` text NOT NULL,
  `shipping_city` varchar(100) NOT NULL,
  `shipping_province` varchar(100) NOT NULL,
  `shipping_postal_code` varchar(20) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `pickup_date` date DEFAULT NULL,
  `pickup_time` varchar(50) DEFAULT NULL,
  `shipping_partner` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `receipt_uploaded_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_order_status` (`order_status`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.orders: ~5 rows (approximately)
INSERT INTO `orders` (`order_id`, `customer_id`, `customer_phone`, `order_number`, `tracking_number`, `subtotal`, `shipping_fee`, `total_amount`, `payment_method`, `delivery_method`, `payment_reference`, `payment_proof_path`, `payment_proof_uploaded_at`, `payment_reviewed_at`, `payment_reviewed_by`, `payment_review_notes`, `payment_status`, `order_status`, `shipping_address`, `shipping_city`, `shipping_province`, `shipping_postal_code`, `delivery_date`, `pickup_date`, `pickup_time`, `shipping_partner`, `notes`, `cancel_reason`, `cancelled_at`, `cancelled_by`, `confirmed_at`, `receipt_path`, `receipt_uploaded_at`, `completed_at`, `created_at`, `updated_at`) VALUES
	(1, 1, '09067188100', 'ORD-20260508-568030', NULL, 5398.00, 0.00, 5398.00, 'gcash', 'shipping', '65468546546565', 'Assets/images/payments/payment_proof_20260508092220_8079e055feff591e.png', '2026-05-08 07:22:20', NULL, NULL, 'Payment proof submitted and awaiting admin review.', 'paid', 'delivered', 'a, Pulung Maragul, Angeles City, Pampanga', 'Angeles City', 'Pampanga', '2035', NULL, NULL, NULL, 'standard_shipping', NULL, NULL, NULL, NULL, NULL, 'html/order-receipt.php?order=ORD-20260508-568030', '2026-05-08 07:57:47', '2026-05-08 07:57:47', '2026-05-08 07:22:20', '2026-05-08 07:57:47'),
	(2, 2, '09067188100', 'ORD-20260508-193727', NULL, 6498.00, 0.00, 6498.00, 'cod', 'pickup', NULL, NULL, NULL, NULL, NULL, 'COD order submitted. Payment to be collected upon completion.', 'paid', 'delivered', 'Pickup at Store', 'Pickup', 'Pickup', NULL, NULL, '2026-05-09', '05:00-06:00', 'store_pickup', NULL, NULL, NULL, NULL, NULL, 'html/order-receipt.php?order=ORD-20260508-193727', '2026-05-08 08:08:10', '2026-05-08 08:08:10', '2026-05-08 08:05:49', '2026-05-08 08:08:10'),
	(3, 2, '09067188100', 'ORD-20260508-874015', NULL, 2999.00, 0.00, 2999.00, 'cod', 'shipping', NULL, NULL, NULL, NULL, NULL, 'COD order submitted. Payment to be collected upon completion.', 'paid', 'delivered', 'a, Pulung Maragul, Angeles City, Pampanga', 'Angeles City', 'Pampanga', '2035', NULL, NULL, NULL, 'standard_shipping', NULL, NULL, NULL, NULL, NULL, 'html/order-receipt.php?order=ORD-20260508-874015', '2026-05-08 08:15:12', '2026-05-08 08:15:12', '2026-05-08 08:06:14', '2026-05-08 08:15:12'),
	(4, 2, '09067188100', 'ORD-20260508-918510', NULL, 2499.00, 0.00, 2499.00, 'cod', 'shipping', NULL, NULL, NULL, NULL, NULL, 'COD order submitted. Payment to be collected upon completion.', 'paid', 'delivered', 'a, Pulung Maragul, Angeles City, Pampanga', 'Angeles City', 'Pampanga', '2035', NULL, NULL, NULL, 'standard_shipping', NULL, NULL, NULL, NULL, NULL, 'html/order-receipt.php?order=ORD-20260508-918510', '2026-05-08 08:31:03', '2026-05-08 08:31:03', '2026-05-08 08:06:39', '2026-05-08 08:31:03'),
	(5, 2, '09067188100', 'ORD-20260508-463601', NULL, 3499.00, 0.00, 3499.00, 'cod', 'shipping', NULL, NULL, NULL, NULL, NULL, 'COD order submitted. Payment to be collected upon completion.', 'paid', 'delivered', 'a, Pulung Maragul, Angeles City, Pampanga', 'Angeles City', 'Pampanga', '2035', NULL, NULL, NULL, 'standard_shipping', NULL, NULL, NULL, NULL, NULL, 'html/order-receipt.php?order=ORD-20260508-463601', '2026-05-08 08:31:36', '2026-05-08 08:31:36', '2026-05-08 08:07:08', '2026-05-08 08:31:40');

-- Dumping structure for table minc.password_reset_tokens
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `reset_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `unique_reset_token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `fk_reset_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.password_reset_tokens: ~0 rows (approximately)

-- Dumping structure for table minc.product_line_presets
CREATE TABLE IF NOT EXISTS `product_line_presets` (
  `preset_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `preset_name` varchar(255) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`preset_id`),
  UNIQUE KEY `uk_product_line_presets_category_name` (`category_id`,`preset_name`),
  KEY `idx_product_line_presets_category_status` (`category_id`,`status`),
  CONSTRAINT `product_line_presets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.product_line_presets: ~12 rows (approximately)
INSERT INTO `product_line_presets` (`preset_id`, `category_id`, `preset_name`, `display_order`, `status`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Nuts', 1, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(2, 1, 'Tires', 2, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(3, 1, 'Wheel Accessories', 3, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(4, 2, 'Side Mirrors', 1, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(5, 2, 'Wiper Blades', 2, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(6, 2, 'Door Handles & Locks', 3, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(7, 2, 'Truck Accessories', 4, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(8, 2, 'Window Visors', 5, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(9, 3, 'Horns & Components', 1, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(10, 3, 'Mobile Electronics', 2, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(11, 3, 'Switches & Relays', 3, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(12, 4, 'Back & Head Lights', 1, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11');

-- Dumping structure for table minc.product_lines
CREATE TABLE IF NOT EXISTS `product_lines` (
  `product_line_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `product_line_name` varchar(255) NOT NULL,
  `product_line_slug` varchar(255) NOT NULL,
  `product_line_description` text DEFAULT NULL,
  `product_line_image` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_line_id`),
  UNIQUE KEY `product_line_slug` (`product_line_slug`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `product_lines_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.product_lines: ~12 rows (approximately)
INSERT INTO `product_lines` (`product_line_id`, `category_id`, `product_line_name`, `product_line_slug`, `product_line_description`, `product_line_image`, `display_order`, `status`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Wheel Accessories', 'wheel-accessories', 'Variety of Wheel Accessories', 'product_line_1764393147_692a80bb1fd0c.jpg', 1, 'active', '2025-11-29 04:12:11', '2025-11-29 05:12:27'),
	(2, 1, 'Nuts', 'nuts', 'Variety of Nuts', 'product_line_1764393158_692a80c670c28.png', 2, 'active', '2025-11-29 04:12:11', '2025-11-29 05:12:38'),
	(3, 1, 'Tires', 'tyres', 'Variety of Tyre Accessories', 'product_line_1764393179_692a80db60f82.png', 3, 'active', '2025-11-29 04:12:11', '2025-11-29 05:12:59'),
	(4, 2, 'Window Visors', 'window-visors', 'Variety of Window Visor Accessories', 'product_line_1764393193_692a80e9349d0.png', 1, 'active', '2025-11-29 04:12:11', '2025-11-29 05:13:13'),
	(5, 2, 'Door Handles & Locks', 'door-handles-locks', 'Various Door Accessories', 'product_line_1764393230_692a810e5a5fc.png', 2, 'active', '2025-11-29 04:12:11', '2025-11-29 05:13:50'),
	(6, 2, 'Truck Accessories', 'truck-accessories', 'Variety of Truck Accessories', 'product_line_1764393258_692a812aa66b8.png', 3, 'active', '2025-11-29 04:12:11', '2025-11-29 05:14:18'),
	(7, 3, 'Mobile Electronics', 'mobile-electronics', 'Variety of Mobile Electronics', 'product_line_1764393130_692a80aa7bb88.png', 1, 'active', '2025-11-29 04:12:11', '2025-11-29 05:12:10'),
	(8, 3, 'Horns & Components', 'horns-components', 'Variety of Horns and Components', 'product_line_1764393272_692a8138e3132.png', 2, 'active', '2025-11-29 04:12:11', '2025-11-29 05:14:32'),
	(9, 3, 'Switches & Relays', 'switches-relays', 'Variety of Wiring Components', 'product_line_1764393315_692a8163cc3c4.png', 3, 'active', '2025-11-29 04:12:11', '2025-11-29 05:15:15'),
	(10, 4, 'Back & Head Lights', 'back-head-lights', 'Variety of Lighting Accessories', 'product_line_1764393303_692a81579cc0d.png', 1, 'active', '2025-11-29 04:12:11', '2025-11-29 05:15:03'),
	(11, 4, 'Side Mirrors', 'side-mirrors', 'Variety of Side Mirror Accessories', 'side-mirrors.png', 2, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11'),
	(12, 4, 'Wiper Blades', 'wiper-blades', 'Variety of Wiper Accessories', 'wiper-blades.png', 3, 'active', '2025-11-29 04:12:11', '2025-11-29 04:12:11');

-- Dumping structure for table minc.product_review_reports
CREATE TABLE IF NOT EXISTS `product_review_reports` (
  `report_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `review_id` bigint(20) unsigned NOT NULL,
  `reporter_user_id` bigint(20) unsigned NOT NULL,
  `report_reason` varchar(100) NOT NULL,
  `report_details` varchar(500) DEFAULT NULL,
  `report_status` enum('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`report_id`),
  UNIQUE KEY `uk_product_review_reports_review_user` (`review_id`,`reporter_user_id`),
  KEY `idx_product_review_reports_review` (`review_id`),
  KEY `idx_product_review_reports_status` (`report_status`),
  KEY `idx_product_review_reports_user` (`reporter_user_id`),
  CONSTRAINT `fk_product_review_reports_review` FOREIGN KEY (`review_id`) REFERENCES `product_reviews` (`review_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_review_reports_user` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.product_review_reports: ~0 rows (approximately)

-- Dumping structure for table minc.product_reviews
CREATE TABLE IF NOT EXISTS `product_reviews` (
  `review_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `rating` tinyint(1) unsigned NOT NULL,
  `review_title` varchar(255) DEFAULT NULL,
  `review_text` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `uk_product_reviews_product_user` (`product_id`,`user_id`),
  KEY `idx_product_reviews_product_created` (`product_id`,`created_at`),
  KEY `idx_product_reviews_user` (`user_id`),
  CONSTRAINT `fk_product_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.product_reviews: ~0 rows (approximately)

-- Dumping structure for table minc.products
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_line_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_slug` varchar(255) NOT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `product_description` text DEFAULT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `stock_status` enum('in_stock','low_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
  `min_stock_level` int(11) NOT NULL DEFAULT 10,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive','discontinued') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `product_slug` (`product_slug`),
  UNIQUE KEY `product_code` (`product_code`),
  KEY `product_line_id` (`product_line_id`),
  KEY `idx_status` (`status`),
  KEY `idx_stock_status` (`stock_status`),
  KEY `idx_featured` (`is_featured`),
  CONSTRAINT `products_product_line_id_foreign` FOREIGN KEY (`product_line_id`) REFERENCES `product_lines` (`product_line_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.products: ~35 rows (approximately)
INSERT INTO `products` (`product_id`, `product_line_id`, `product_name`, `product_slug`, `product_code`, `product_description`, `product_image`, `price`, `stock_quantity`, `stock_status`, `min_stock_level`, `is_featured`, `display_order`, `status`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Chrome Wheel Hub Caps Set', 'chrome-wheel-hub-caps-set', 'WA-001', 'Premium chrome wheel hub caps for enhanced wheel appearance', 'wheel-hub-caps.jpg', 1299.00, 50, 'in_stock', 10, 1, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 05:19:25'),
	(2, 1, 'Aluminum Valve Stems', 'aluminum-valve-stems', 'WA-002', 'Durable aluminum valve stems for all wheel types', 'valve-stems.jpg', 299.00, 100, 'in_stock', 20, 0, 2, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(3, 1, 'Wheel Spacers Kit', 'wheel-spacers-kit', 'WA-003', 'High-quality wheel spacers for improved stance', 'wheel-spacers.jpg', 2499.00, 30, 'in_stock', 10, 1, 3, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(4, 1, 'Center Cap Set', 'center-cap-set', 'WA-004', 'Universal center caps for custom wheels', 'center-caps.jpg', 899.00, 75, 'in_stock', 15, 0, 4, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(5, 2, 'Lug Nuts Chrome Set', 'lug-nuts-chrome-set', 'NUTS-001', '20-piece chrome lug nuts with key', 'lug-nuts-chrome.jpg', 1599.00, 60, 'in_stock', 10, 1, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(6, 2, 'Black Spline Lug Nuts', 'black-spline-lug-nuts', 'NUTS-002', 'Premium black spline drive lug nuts', 'lug-nuts-black.jpg', 1799.00, 45, 'in_stock', 10, 0, 2, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(7, 2, 'Wheel Lock Set', 'wheel-lock-set', 'NUTS-003', 'Anti-theft wheel lock nuts with unique key', 'wheel-locks.jpg', 2299.00, 40, 'in_stock', 10, 1, 3, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(8, 3, 'All-Season Radial Tire 185/65R15', 'all-season-radial-tire-185-65r15', 'TYRE-001', 'High-performance all-season tire for passenger vehicles', 'tire-185-65r15.jpg', 3499.00, 25, 'in_stock', 5, 1, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(9, 3, 'Performance Tire 205/55R16', 'performance-tire-205-55r16', 'TYRE-002', 'Sport performance tire with enhanced grip', 'tire-205-55r16.jpg', 4299.00, 20, 'in_stock', 5, 1, 2, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(10, 3, 'SUV Tire 235/65R17', 'suv-tire-235-65r17', 'TYRE-003', 'Durable all-terrain tire for SUVs and trucks', 'tire-235-65r17.jpg', 5499.00, 15, 'in_stock', 5, 0, 3, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(11, 4, 'Smoke Tinted Window Visor Set', 'smoke-tinted-window-visor-set', 'WV-001', '4-piece smoke tinted window deflectors', 'window-visor-smoke.jpg', 1899.00, 35, 'in_stock', 10, 1, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(12, 4, 'Chrome Window Visor Guards', 'chrome-window-visor-guards', 'WV-002', 'Premium chrome finish window rain guards', 'window-visor-chrome.jpg', 2199.00, 30, 'in_stock', 10, 0, 2, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(13, 5, 'Chrome Door Handle Covers', 'chrome-door-handle-covers', 'DH-001', 'Universal chrome door handle trim covers', 'door-handle-chrome.jpg', 999.00, 50, 'in_stock', 10, 1, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(14, 5, 'Interior Door Lock Knobs', 'interior-door-lock-knobs', 'DH-002', 'Replacement interior door lock knobs set', 'door-lock-knobs.jpg', 599.00, 60, 'in_stock', 15, 0, 2, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(15, 5, 'Power Door Lock Actuator', 'power-door-lock-actuator', 'DH-003', 'Electronic door lock actuator mechanism', 'door-lock-actuator.jpg', 1499.00, 25, 'in_stock', 10, 0, 3, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(16, 6, 'Truck Bed Liner Mat', 'truck-bed-liner-mat', 'TA-001', 'Heavy-duty rubber truck bed mat protector', 'truck-bed-liner.jpg', 3999.00, 20, 'in_stock', 5, 1, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(17, 6, 'Tailgate Assist Shock', 'tailgate-assist-shock', 'TA-002', 'Easy-down tailgate dampening system', 'tailgate-shock.jpg', 1799.00, 30, 'in_stock', 10, 0, 2, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(18, 6, 'Truck Step Bars', 'truck-step-bars', 'TA-003', 'Stainless steel side step running boards', 'truck-step-bars.jpg', 8999.00, 10, 'in_stock', 5, 1, 3, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(19, 7, 'Car Bluetooth FM Transmitter', 'car-bluetooth-fm-transmitter', 'ME-001', 'Wireless bluetooth FM transmitter with USB charging', 'fm-transmitter.jpg', 899.00, 80, 'in_stock', 20, 1, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(20, 7, 'Dash Camera HD', 'dash-camera-hd', 'ME-002', '1080p HD dash camera with night vision', 'dash-camera.jpg', 3499.00, 39, 'in_stock', 10, 1, 2, 'active', '2025-11-29 04:33:22', '2026-05-08 08:07:08'),
	(21, 7, 'Car Phone Mount', 'car-phone-mount', 'ME-003', 'Magnetic dashboard phone holder mount', 'phone-mount.jpg', 499.00, 100, 'in_stock', 20, 0, 3, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(22, 8, 'Dual Tone Air Horn', 'dual-tone-air-horn', 'HORN-001', 'Loud dual tone electric air horn kit', 'air-horn.jpg', 1299.00, 45, 'in_stock', 10, 1, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(23, 8, 'Compact Car Horn', 'compact-car-horn', 'HORN-002', 'Universal compact electric car horn', 'car-horn-compact.jpg', 599.00, 70, 'in_stock', 15, 0, 2, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(24, 9, 'Power Window Switch', 'power-window-switch', 'SW-001', 'Universal power window control switch', 'power-window-switch.jpg', 799.00, 50, 'in_stock', 15, 0, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(25, 9, 'Automotive Relay Set', 'automotive-relay-set', 'SW-002', '12V 40A automotive relay assortment kit', 'relay-set.jpg', 899.00, 60, 'in_stock', 20, 1, 2, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(26, 9, 'Toggle Switch Panel', 'toggle-switch-panel', 'SW-003', 'LED illuminated toggle switch panel', 'toggle-switch.jpg', 1499.00, 35, 'in_stock', 10, 0, 3, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(27, 10, 'LED Headlight Bulbs H7', 'led-headlight-bulbs-h7', 'LIGHT-001', 'Super bright LED headlight conversion kit H7', 'led-headlight-h7.jpg', 2999.00, 38, 'in_stock', 10, 1, 1, 'active', '2025-11-29 04:33:22', '2026-05-08 08:06:14'),
	(28, 10, 'Tail Light Assembly', 'tail-light-assembly', 'LIGHT-002', 'Complete tail light assembly replacement', 'tail-light.jpg', 3499.00, 23, 'in_stock', 5, 0, 2, 'active', '2025-11-29 04:33:22', '2026-05-08 08:05:49'),
	(29, 10, 'LED Strip Tail Lights', 'led-strip-tail-lights', 'LIGHT-003', 'Modern LED strip brake lights', 'led-strip-lights.jpg', 1899.00, 34, 'in_stock', 10, 1, 3, 'active', '2025-11-29 04:33:22', '2026-05-08 07:22:20'),
	(30, 11, 'Power Side Mirror Left', 'power-side-mirror-left', 'MIRROR-001', 'Electric power side mirror driver side', 'side-mirror-left.jpg', 2499.00, 20, 'in_stock', 5, 0, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(31, 11, 'Power Side Mirror Right', 'power-side-mirror-right', 'MIRROR-002', 'Electric power side mirror passenger side', 'side-mirror-right.jpg', 2499.00, 19, 'in_stock', 5, 0, 2, 'active', '2025-11-29 04:33:22', '2026-05-08 08:06:39'),
	(32, 11, 'Blind Spot Mirror Set', 'blind-spot-mirror-set', 'MIRROR-003', 'Convex blind spot mirror attachments', 'blind-spot-mirror.jpg', 399.00, 90, 'in_stock', 20, 1, 3, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(33, 12, 'Premium Wiper Blades 22 inch', 'premium-wiper-blades-22-inch', 'WIPER-001', 'All-season premium wiper blade 22 inch', 'wiper-22.jpg', 599.00, 70, 'in_stock', 15, 1, 1, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(34, 12, 'Rear Wiper Blade 14 inch', 'rear-wiper-blade-14-inch', 'WIPER-002', 'Rear windshield wiper blade 14 inch', 'wiper-rear-14.jpg', 399.00, 80, 'in_stock', 20, 0, 2, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22'),
	(35, 12, 'Heavy Duty Wiper Blades', 'heavy-duty-wiper-blades', 'WIPER-003', 'Heavy duty winter wiper blade set', 'wiper-heavy-duty.jpg', 899.00, 55, 'in_stock', 15, 1, 3, 'active', '2025-11-29 04:33:22', '2025-11-29 04:33:22');

-- Dumping structure for table minc.purchase_orders
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `po_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `uk_po_number` (`po_number`),
  KEY `idx_po_supplier` (`supplier_id`),
  KEY `idx_po_order_date` (`order_date`),
  KEY `idx_po_status` (`status`),
  KEY `fk_po_created_by` (`created_by`),
  CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.purchase_orders: ~0 rows (approximately)
INSERT INTO `purchase_orders` (`po_id`, `po_number`, `supplier_id`, `order_date`, `expected_delivery_date`, `total_amount`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
	(1, 'PO-20260508-3817', 1, '2026-05-08', '2026-05-16', 22222.00, 'pending', NULL, 1, '2026-05-08 08:21:44', '2026-05-08 08:21:44');

-- Dumping structure for table minc.suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
  `supplier_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT 'Pampanga',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`supplier_id`),
  UNIQUE KEY `uniq_supplier_name` (`supplier_name`),
  KEY `idx_supplier_status` (`status`),
  KEY `idx_supplier_city` (`city`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.suppliers: ~0 rows (approximately)
INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `email`, `phone`, `address`, `city`, `province`, `status`, `created_at`, `updated_at`) VALUES
	(1, 'swwww', 'Jhoren Boseto Dollete', 'melton07catacutan@gmail.com', '09067188100', 'Blk 2 Lot 3 Bulaon Resettlement', 'San fernando', 'Pampanga', 'active', '2026-05-08 08:21:26', '2026-05-08 08:21:26');

-- Dumping structure for table minc.user_levels
CREATE TABLE IF NOT EXISTS `user_levels` (
  `user_level_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_type_name` varchar(255) NOT NULL,
  `user_type_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_level_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.user_levels: ~4 rows (approximately)
INSERT INTO `user_levels` (`user_level_id`, `user_type_name`, `user_type_status`, `created_at`, `updated_at`) VALUES
	(1, 'Admin', 'active', '2025-10-23 05:06:26', '2025-10-23 05:06:26'),
	(2, 'Employee', 'active', '2025-10-23 05:06:26', '2025-10-23 05:06:26'),
	(3, 'Supplier', 'active', '2025-10-23 05:06:26', '2025-10-23 05:06:26'),
	(4, 'Customer', 'active', '2025-10-23 05:06:26', '2025-10-23 05:06:26');

-- Dumping structure for table minc.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fname` varchar(255) NOT NULL,
  `lname` varchar(255) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `contact_num` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `home_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `barangay` varchar(120) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `user_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `user_level_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_user_level_id_foreign` (`user_level_id`),
  CONSTRAINT `users_user_level_id_foreign` FOREIGN KEY (`user_level_id`) REFERENCES `user_levels` (`user_level_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table minc.users: ~6 rows (approximately)
INSERT INTO `users` (`user_id`, `fname`, `lname`, `username`, `email`, `password`, `contact_num`, `profile_picture`, `address`, `home_address`, `billing_address`, `barangay`, `city`, `province`, `postal_code`, `user_status`, `is_email_verified`, `email_verified_at`, `user_level_id`, `created_at`, `updated_at`, `reset_token`, `reset_expires_at`) VALUES
	(1, 'Root', 'Admin', 'Root', 'root@gmail.com', '$2y$10$0D7cXDs4gW2Ki4Rtd44o7.Q.W2tUyHjU1exCdiSITyso7XsHmxI4u', '09067188100', NULL, 'a, Pulung Maragul, Angeles City, Pampanga', NULL, NULL, 'Pulung Maragul', 'Angeles City', 'Pampanga', '2035', 'active', 1, '2025-10-23 05:16:23', 1, '2025-10-23 05:16:23', '2026-05-08 08:07:08', NULL, NULL),
	(2, 'Test', 'User Edited', NULL, 'test@gmail.com', '$2y$10$0D7cXDs4gW2Ki4Rtd44o7.Q.W2tUyHjU1exCdiSITyso7XsHmxI4u', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1, '2025-10-23 09:44:23', 2, '2025-10-23 09:44:23', '2025-10-23 12:51:02', NULL, NULL),
	(3, 'Student', 'Test', NULL, 'Student@test.com', '$2y$10$DmnXCDsMEOPwEtkZd3JKrO5q/3hLibcBq7yz3sA/zSgFCgDTfKDem', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, 4, '2025-10-26 03:51:20', NULL, NULL, NULL),
	(4, 'Parent', 'Test', NULL, 'Parent@gmail.com', '$2y$10$dEo9fS4GradqSB9AHz4M4Okal2L/meSIUi/16tL44Ki5y0rd7Q1Vm', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 1, '2025-10-27 08:55:27', 2, '2025-10-27 08:55:27', NULL, NULL, NULL),
	(5, 'Teacher', 'Testt', NULL, 'teacher@gmail.com', '$2y$10$Ekm0TUyx9e22OYjEqKdBNeDIRrIAYpYFHBjHfNFCCH8mafF9qMcOe', '09067188100', NULL, 'a, Pulung Maragul, Angeles City, Pampanga', NULL, NULL, 'Pulung Maragul', 'Angeles City', 'Pampanga', '2035', 'active', 1, '2025-10-27 13:21:31', 2, '2025-10-27 13:21:31', '2026-05-08 07:22:20', NULL, NULL),
	(6, 'Jhoren', 'Dollete', NULL, 'melton07catacutan@gmail.com', '$2y$10$S87X21enMMp75zwwrI7VcuIm925fdr7B8m7zdpCbdwM0TApwXRx1G', '09067188100', NULL, 'Blk 2 Lot 3 Bulaon Resettlement, Pulungbulu, Angeles City, Pampanga', 'Blk 2 Lot 3 Bulaon Resettlement, Pulungbulu, Angeles City, Pampanga', 'Blk 2 Lot 3 Bulaon Resettlement, Pulungbulu, Angeles City, Pampanga', 'Pulungbulu', 'Angeles City', 'Pampanga', '2035', 'active', 0, NULL, 4, '2026-05-08 07:52:09', '2026-05-08 08:14:24', NULL, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
