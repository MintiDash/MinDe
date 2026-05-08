<?php
/**
 * Email Verification Migration
 * Path: database/migration_email_verification.php
 * 
 * This migration adds email verification functionality to the system.
 * Run this once to set up the necessary tables and columns.
 */

require_once __DIR__ . '/../database/connect_database.php';

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Add email verification columns to users table
    $alterUsersSQL = "
        ALTER TABLE `users` 
        ADD COLUMN `is_email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `user_status`,
        ADD COLUMN `email_verified_at` TIMESTAMP NULL AFTER `is_email_verified`
    ";
    
    // Check if columns exist before adding
    $checkColumns = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'is_email_verified'");
    if ($checkColumns->rowCount() === 0) {
        $pdo->exec($alterUsersSQL);
        echo "✓ Added email verification columns to users table\n";
    } else {
        echo "✓ Email verification columns already exist in users table\n";
    }
    
    // 2. Create email_verification_tokens table
    $createTokensTableSQL = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createTokensTableSQL);
    echo "✓ Created email_verification_tokens table\n";
    
    // 3. Create password_reset_tokens table (bonus for future password reset feature)
    $createResetTokensTableSQL = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createResetTokensTableSQL);
    echo "✓ Created password_reset_tokens table\n";
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✓ Email verification migration completed successfully!\n";
    
} catch (PDOException $e) {
    // Rollback on error
    $pdo->rollBack();
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Close connection
$pdo = null;
?>
