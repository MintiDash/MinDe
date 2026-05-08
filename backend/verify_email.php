<?php
/**
 * Email Verification Endpoint
 * Path: backend/verify_email.php
 * 
 * Verifies user email address via token from email link
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../database/connect_database.php';
require_once __DIR__ . '/../library/TokenGenerator.php';

// Get token from query parameter
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No verification token provided'
    ]);
    exit;
}

try {
    // Check if this is an AJAX request or page request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    // Find the verification token in database
    $stmt = $pdo->prepare("
        SELECT 
            tvt.token_id,
            tvt.user_id,
            tvt.token_hash,
            tvt.expires_at,
            tvt.is_used,
            tvt.verified_at,
            u.email,
            u.fname,
            u.lname,
            u.is_email_verified
        FROM email_verification_tokens tvt
        JOIN users u ON tvt.user_id = u.user_id
        WHERE tvt.token = :token
        LIMIT 1
    ");
    
    $stmt->execute([':token' => $token]);
    $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenRecord) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired verification link'
        ]);
        exit;
    }
    
    // Check if token has already been used
    if ($tokenRecord['is_used'] == 1) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'This verification link has already been used',
            'redirectToLogin' => true
        ]);
        exit;
    }
    
    // Check if token has expired
    if (strtotime($tokenRecord['expires_at']) < time()) {
        http_response_code(410);
        echo json_encode([
            'success' => false,
            'message' => 'Verification link has expired. Please request a new one.',
            'expired' => true
        ]);
        exit;
    }
    
    // Verify the token hash
    if (!TokenGenerator::verifyToken($token, $tokenRecord['token_hash'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid verification token'
        ]);
        exit;
    }
    
    // Token is valid, proceed with email verification
    try {
        $pdo->beginTransaction();
        
        // Update user as email verified
        $updateUserStmt = $pdo->prepare("
            UPDATE users 
            SET is_email_verified = 1, email_verified_at = NOW()
            WHERE user_id = :user_id
        ");
        
        $updateUserStmt->execute([':user_id' => $tokenRecord['user_id']]);
        
        // Mark token as used
        $markUsedStmt = $pdo->prepare("
            UPDATE email_verification_tokens 
            SET is_used = 1, verified_at = NOW()
            WHERE token_id = :token_id
        ");
        
        $markUsedStmt->execute([':token_id' => $tokenRecord['token_id']]);
        
        // Send welcome email
        require_once __DIR__ . '/../library/EmailService.php';
        $emailService = new EmailService();
        $emailService->sendWelcomeEmail(
            $tokenRecord['email'],
            $tokenRecord['fname'] . ' ' . $tokenRecord['lname']
        );
        
        // Log to audit trail
        $auditLog = [
            'fname' => $tokenRecord['fname'],
            'lname' => $tokenRecord['lname'],
            'email' => $tokenRecord['email'],
            'is_email_verified' => 1,
            'email_verified_at' => date('Y-m-d H:i:s')
        ];
        
        $auditStmt = $pdo->prepare("
            INSERT INTO audit_trail 
            (user_id, session_username, action, entity_type, entity_id, old_value, new_value, change_reason, ip_address, user_agent, system_id) 
            VALUES 
            (:user_id, :session_username, :action, :entity_type, :entity_id, :old_value, :new_value, :change_reason, :ip_address, :user_agent, :system_id)
        ");
        
        $auditStmt->execute([
            ':user_id' => $tokenRecord['user_id'],
            ':session_username' => $tokenRecord['fname'] . ' ' . $tokenRecord['lname'],
            ':action' => 'email_verified',
            ':entity_type' => 'user',
            ':entity_id' => $tokenRecord['user_id'],
            ':old_value' => json_encode(['is_email_verified' => 0]),
            ':new_value' => json_encode(['is_email_verified' => 1, 'email_verified_at' => date('Y-m-d H:i:s')]),
            ':change_reason' => 'Email verification completed',
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':system_id' => 'minc_system'
        ]);
        
        $pdo->commit();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Email verified successfully! You can now login to your account.',
            'redirectToLogin' => true
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Email verification error: " . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred during email verification. Please try again.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in verify_email.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
}

// Close connection
$pdo = null;
?>
