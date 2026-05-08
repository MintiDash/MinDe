<?php
/**
 * Request password reset link via email.
 */

require_once __DIR__ . '/../database/connect_database.php';
require_once __DIR__ . '/../library/TokenGenerator.php';
require_once __DIR__ . '/../library/EmailService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? filter_var($input['email'], FILTER_SANITIZE_EMAIL) : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT user_id, fname, lname, email FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return generic message even if user not found.
    if (!$user) {
        echo json_encode([
            'success' => true,
            'message' => 'If this email exists, a password recovery link has been sent.'
        ]);
        exit;
    }

    $token = TokenGenerator::generateToken(32);
    $expiresAt = date('Y-m-d H:i:s', time() + (60 * 60)); // 1 hour

    $updateStmt = $pdo->prepare("
        UPDATE users
        SET reset_token = :reset_token,
            reset_expires_at = :reset_expires_at,
            updated_at = NOW()
        WHERE user_id = :user_id
    ");
    $updateStmt->execute([
        ':reset_token' => $token,
        ':reset_expires_at' => $expiresAt,
        ':user_id' => $user['user_id']
    ]);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $projectBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/backend/request_password_reset.php', 2)), '/');
    $resetLink = $scheme . '://' . $host . rtrim($projectBase, '/') . '/html/reset_password.php?token=' . urlencode($token);

    $emailService = new EmailService();
    $emailService->sendPasswordResetEmail(
        $user['email'],
        trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? '')),
        $resetLink
    );

    echo json_encode([
        'success' => true,
        'message' => 'If this email exists, a password recovery link has been sent.'
    ]);
} catch (Exception $e) {
    error_log('Password reset request error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while requesting password reset.'
    ]);
}
