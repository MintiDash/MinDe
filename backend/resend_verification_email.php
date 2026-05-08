<?php
/**
 * Resend Verification Email Endpoint
 * Path: backend/resend_verification_email.php
 * 
 * Allows users to request a new OTP verification code for registration.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../database/connect_database.php';
require_once __DIR__ . '/../library/EmailService.php';
require_once __DIR__ . '/../library/TokenGenerator.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$email = isset($input['email']) ? filter_var($input['email'], FILTER_SANITIZE_EMAIL) : '';

if (empty($email)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email address is required'
    ]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

try {
    // Find user by email
    $stmt = $pdo->prepare("
        SELECT user_id, fname, lname, is_email_verified, user_level_id
        FROM users 
        WHERE email = :email 
        LIMIT 1
    ");
    
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // For security, don't reveal if email exists
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No account found with this email address'
        ]);
        exit;
    }
    
    // Only pending consumer registration is allowed
    if ((int)$user['user_level_id'] !== 4 || (int)$user['is_email_verified'] === 1) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'This account is already verified. You can login to your account.'
        ]);
        exit;
    }
    
    // Check if there's a recent unused token (avoid spam)
    $recentToken = $pdo->prepare("
        SELECT token_id, created_at 
        FROM email_verification_tokens 
        WHERE user_id = :user_id 
        AND is_used = 0 
        AND expires_at > NOW()
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $recentToken->execute([':user_id' => $user['user_id']]);
    $existingToken = $recentToken->fetch(PDO::FETCH_ASSOC);
    
    // If a valid token exists and was created less than 60 seconds ago, don't send new one
    if ($existingToken) {
        $timeSinceCreation = time() - strtotime($existingToken['created_at']);
        if ($timeSinceCreation < 60) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Please wait before requesting a new OTP.',
                'retryAfter' => 60 - $timeSinceCreation
            ]);
            exit;
        }
    }
    
    // Invalidate previous tokens
    $invalidateStmt = $pdo->prepare("
        UPDATE email_verification_tokens 
        SET expires_at = NOW() 
        WHERE user_id = :user_id 
        AND is_used = 0 
        AND expires_at > NOW()
    ");
    
    $invalidateStmt->execute([':user_id' => $user['user_id']]);
    
    // Generate new verification token
    $token = TokenGenerator::generateVerificationCode();
    $tokenStorage = $token . '-' . substr(TokenGenerator::generateToken(8), 0, 16);
    $tokenHash = TokenGenerator::hashToken($token);
    $expiresAt = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes
    
    // Store new verification token
    $tokenStmt = $pdo->prepare("
        INSERT INTO email_verification_tokens (user_id, token, token_hash, email, expires_at) 
        VALUES (:user_id, :token, :token_hash, :email, :expires_at)
    ");
    
    $tokenStmt->execute([
        ':user_id' => $user['user_id'],
        ':token' => $tokenStorage,
        ':token_hash' => $tokenHash,
        ':email' => $email,
        ':expires_at' => $expiresAt
    ]);
    
    // Send OTP email
    $emailService = new EmailService();
    $emailSent = $emailService->sendOtpVerificationEmail(
        $email,
        $user['fname'] . ' ' . $user['lname'],
        $token,
        10
    );
    
    // Log to audit trail (non-blocking)
    try {
        $auditStmt = $pdo->prepare("
            INSERT INTO audit_trail 
            (user_id, session_username, action, entity_type, entity_id, old_value, new_value, change_reason, ip_address, user_agent, system_id) 
            VALUES 
            (:user_id, :session_username, :action, :entity_type, :entity_id, :old_value, :new_value, :change_reason, :ip_address, :user_agent, :system_id)
        ");
        
        $auditStmt->execute([
            ':user_id' => $user['user_id'],
            ':session_username' => $user['fname'] . ' ' . $user['lname'],
            ':action' => 'resend_registration_otp',
            ':entity_type' => 'user',
            ':entity_id' => $user['user_id'],
            ':old_value' => null,
            ':new_value' => json_encode(['email' => $email, 'email_sent' => $emailSent]),
            ':change_reason' => 'User requested OTP resend',
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':system_id' => 'minc_system'
        ]);
    } catch (Throwable $auditError) {
        error_log("Resend verification audit log error: " . $auditError->getMessage());
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $emailSent 
            ? 'A new OTP has been sent. Please check your email.'
            : 'OTP could not be sent right now. Please try again.',
        'email_sent' => $emailSent,
        'otp_expires_in_seconds' => 600
    ]);
    
} catch (Throwable $e) {
    error_log("Resend verification email error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}

// Close connection
$pdo = null;
?>
