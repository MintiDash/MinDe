<?php
/**
 * Verify registration OTP code.
 */

session_start();
require_once __DIR__ . '/../database/connect_database.php';
require_once __DIR__ . '/../library/TokenGenerator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? filter_var($input['email'], FILTER_SANITIZE_EMAIL) : '';
$otp = isset($input['otp']) ? trim((string)$input['otp']) : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (!preg_match('/^\d{6}$/', $otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'OTP must be 6 digits']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            tvt.token_id,
            tvt.user_id,
            tvt.token_hash,
            tvt.expires_at,
            tvt.is_used
        FROM email_verification_tokens tvt
        INNER JOIN users u ON u.user_id = tvt.user_id
        WHERE tvt.email = :email
          AND u.user_level_id = 4
        ORDER BY tvt.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenRecord) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No pending verification found for this email']);
        exit;
    }

    if ((int)$tokenRecord['is_used'] === 1 || strtotime($tokenRecord['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new code.']);
        exit;
    }

    if (!TokenGenerator::verifyToken($otp, $tokenRecord['token_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid OTP code']);
        exit;
    }

    $_SESSION['registration_verified_email'] = $email;
    $_SESSION['registration_verified_user_id'] = (int)$tokenRecord['user_id'];
    $_SESSION['registration_verified_at'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'OTP verified. You can now create your password.',
        'email' => $email
    ]);
} catch (PDOException $e) {
    error_log('OTP verification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while verifying OTP']);
}

