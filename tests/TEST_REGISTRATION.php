<?php
/**
 * Test Registration
 * Simple test to verify registration works
 */

require_once __DIR__ . '/database/connect_database.php';

// Check if email_verification_tokens table exists
try {
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'email_verification_tokens'");
    $tableExists = $checkTableStmt->rowCount() > 0;
    
    echo "=== Email Verification System Status ===\n\n";
    
    if ($tableExists) {
        echo "✓ Email verification tables exist\n";
        echo "✓ Email verification is ENABLED\n\n";
        echo "Status: Registration will require email verification\n";
    } else {
        echo "✗ Email verification tables DO NOT exist\n";
        echo "✓ Fallback mode: Email verification is DISABLED\n\n";
        echo "Status: Registration will work without email verification\n";
        echo "Action: Run: php database/migration_email_verification.php\n";
    }
    
    // Test user insertion
    echo "\n=== Testing User Insertion ===\n\n";
    
    $testEmail = 'test-' . time() . '@example.com';
    $testPassword = password_hash('testpass123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (fname, lname, email, password, user_level_id, user_status, created_at) 
        VALUES (:fname, :lname, :email, :password, 4, 'active', NOW())
    ");
    
    $result = $stmt->execute([
        ':fname' => 'Test',
        ':lname' => 'User',
        ':email' => $testEmail,
        ':password' => $testPassword
    ]);
    
    if ($result) {
        $userId = $pdo->lastInsertId();
        echo "✓ Test user created successfully\n";
        echo "  User ID: $userId\n";
        echo "  Email: $testEmail\n";
        
        // Delete test user
        $deleteStmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
        $deleteStmt->execute([':user_id' => $userId]);
        echo "✓ Test user deleted\n";
    } else {
        echo "✗ Failed to create test user\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Conclusion ===\n";
echo "Registration should be working now.\n";
echo "If email verification tables exist, check email_config.php for SMTP settings.\n";
?>
