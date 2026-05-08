-- Email Verification System - SQL Setup Script
-- Paste this entire code into phpMyAdmin SQL tab and execute

-- 1. Add columns to users table if they don't exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `is_email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `user_status`,
ADD COLUMN IF NOT EXISTS `email_verified_at` TIMESTAMP NULL AFTER `is_email_verified`;

-- 2. Create email_verification_tokens table
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
    `token_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) UNSIGNED NOT NULL,
    `token` VARCHAR(255) NOT NULL UNIQUE,
    `token_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `verified_at` TIMESTAMP NULL,
    `is_used` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`token_id`),
    UNIQUE KEY `unique_token` (`token`),
    KEY `user_id` (`user_id`),
    KEY `email` (`email`),
    KEY `expires_at` (`expires_at`),
    CONSTRAINT `fk_verification_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create password_reset_tokens table (for future password reset feature)
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `reset_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) UNSIGNED NOT NULL,
    `token` VARCHAR(255) NOT NULL UNIQUE,
    `token_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `used_at` TIMESTAMP NULL,
    `is_used` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`reset_id`),
    UNIQUE KEY `unique_token` (`token`),
    KEY `user_id` (`user_id`),
    KEY `expires_at` (`expires_at`),
    CONSTRAINT `fk_reset_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. User profile schema updates
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `address` TEXT NULL AFTER `contact_num`;

ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `profile_picture` VARCHAR(255) NULL AFTER `address`;

ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `home_address` TEXT NULL AFTER `address`,
ADD COLUMN IF NOT EXISTS `billing_address` TEXT NULL AFTER `home_address`,
ADD COLUMN IF NOT EXISTS `barangay` VARCHAR(120) NULL AFTER `billing_address`,
ADD COLUMN IF NOT EXISTS `city` VARCHAR(100) NULL AFTER `barangay`,
ADD COLUMN IF NOT EXISTS `province` VARCHAR(100) NULL AFTER `city`,
ADD COLUMN IF NOT EXISTS `postal_code` VARCHAR(20) NULL AFTER `province`;

ALTER TABLE `users`
DROP COLUMN IF EXISTS `mname`;

-- 5. Ensure users primary key auto-increments correctly
ALTER TABLE `users`
MODIFY `user_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 6. Ensure OTP token primary key auto-increments correctly
ALTER TABLE `email_verification_tokens`
MODIFY `token_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT;

-- 7. Ensure audit trail primary key auto-increments correctly
ALTER TABLE `audit_trail`
MODIFY `audit_trail_id` BIGINT(20) NOT NULL AUTO_INCREMENT;

-- 8. Normalize user roles to 3 categories: Admin, Employee, Customer
-- Legacy level 3 users are merged into Employee (level 2)
UPDATE `users`
SET `user_level_id` = 2
WHERE `user_level_id` = 3;

-- Keep legacy IDs for compatibility, but only 3 active categories
UPDATE `user_levels` SET `user_type_name` = 'Admin', `user_type_status` = 'active' WHERE `user_level_id` = 1;
UPDATE `user_levels` SET `user_type_name` = 'Employee', `user_type_status` = 'active' WHERE `user_level_id` = 2;
UPDATE `user_levels` SET `user_type_name` = 'Employee', `user_type_status` = 'inactive' WHERE `user_level_id` = 3;
UPDATE `user_levels` SET `user_type_name` = 'Customer', `user_type_status` = 'active' WHERE `user_level_id` = 4;

-- 9. Supplier database
CREATE TABLE IF NOT EXISTS `suppliers` (
    `supplier_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_name` VARCHAR(255) NOT NULL,
    `contact_person` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `province` VARCHAR(100) DEFAULT 'Pampanga',
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`supplier_id`),
    UNIQUE KEY `uniq_supplier_name` (`supplier_name`),
    KEY `idx_supplier_status` (`status`),
    KEY `idx_supplier_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Product reviews and ratings
CREATE TABLE IF NOT EXISTS `product_reviews` (
    `review_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `user_id` BIGINT(20) UNSIGNED NOT NULL,
    `rating` TINYINT(1) UNSIGNED NOT NULL,
    `review_title` VARCHAR(255) DEFAULT NULL,
    `review_text` TEXT NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`review_id`),
    UNIQUE KEY `uk_product_reviews_product_user` (`product_id`, `user_id`),
    KEY `idx_product_reviews_product_created` (`product_id`, `created_at`),
    KEY `idx_product_reviews_user` (`user_id`),
    CONSTRAINT `fk_product_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_product_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_review_reports` (
    `report_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id` BIGINT(20) UNSIGNED NOT NULL,
    `reporter_user_id` BIGINT(20) UNSIGNED NOT NULL,
    `report_reason` VARCHAR(100) NOT NULL,
    `report_details` VARCHAR(500) DEFAULT NULL,
    `report_status` ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`report_id`),
    UNIQUE KEY `uk_product_review_reports_review_user` (`review_id`, `reporter_user_id`),
    KEY `idx_product_review_reports_review` (`review_id`),
    KEY `idx_product_review_reports_status` (`report_status`),
    KEY `idx_product_review_reports_user` (`reporter_user_id`),
    CONSTRAINT `fk_product_review_reports_review` FOREIGN KEY (`review_id`) REFERENCES `product_reviews` (`review_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_product_review_reports_user` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Order workflow enhancements
ALTER TABLE `orders`
ADD COLUMN IF NOT EXISTS `delivery_method` ENUM('shipping','pickup') NOT NULL DEFAULT 'shipping' AFTER `payment_method`,
ADD COLUMN IF NOT EXISTS `payment_reference` VARCHAR(120) NULL AFTER `delivery_method`,
ADD COLUMN IF NOT EXISTS `payment_proof_path` VARCHAR(255) NULL AFTER `payment_reference`,
ADD COLUMN IF NOT EXISTS `payment_proof_uploaded_at` TIMESTAMP NULL AFTER `payment_proof_path`,
ADD COLUMN IF NOT EXISTS `payment_reviewed_at` TIMESTAMP NULL AFTER `payment_proof_uploaded_at`,
ADD COLUMN IF NOT EXISTS `payment_reviewed_by` BIGINT(20) UNSIGNED NULL AFTER `payment_reviewed_at`,
ADD COLUMN IF NOT EXISTS `payment_review_notes` TEXT NULL AFTER `payment_reviewed_by`,
ADD COLUMN IF NOT EXISTS `pickup_date` DATE NULL AFTER `delivery_date`,
ADD COLUMN IF NOT EXISTS `pickup_time` VARCHAR(50) NULL AFTER `pickup_date`,
ADD COLUMN IF NOT EXISTS `shipping_partner` VARCHAR(50) NULL AFTER `pickup_time`,
ADD COLUMN IF NOT EXISTS `receipt_path` VARCHAR(255) NULL AFTER `shipping_partner`,
ADD COLUMN IF NOT EXISTS `receipt_uploaded_at` TIMESTAMP NULL AFTER `receipt_path`,
ADD COLUMN IF NOT EXISTS `cancel_reason` TEXT NULL AFTER `notes`,
ADD COLUMN IF NOT EXISTS `cancelled_at` TIMESTAMP NULL AFTER `cancel_reason`,
ADD COLUMN IF NOT EXISTS `cancelled_by` BIGINT(20) UNSIGNED NULL AFTER `cancelled_at`,
ADD COLUMN IF NOT EXISTS `confirmed_at` TIMESTAMP NULL AFTER `cancelled_by`,
ADD COLUMN IF NOT EXISTS `completed_at` TIMESTAMP NULL AFTER `confirmed_at`;
