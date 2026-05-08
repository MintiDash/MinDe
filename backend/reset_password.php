<?php
/**
 * Reset password using reset token.
 */

require_once __DIR__ . '/../database/connect_database.php';

header('Content-Type: application/json');

function isStrongPasswordForReset($password) {
    if (strlen($password) < 8) return false;
    if (strtolower($password) === '123456') return false;
    if (!preg_match('/[A-Za-z]/', $password)) return false;
    if (!preg_match('/\d/', $password)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = isset($input['token']) ? trim((string)$input['token']) : '';
$password = isset($input['password']) ? (string)$input['password'] : '';

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid reset token']);
    exit;
}

if (!isStrongPasswordForReset($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters and include a letter, number, and special character.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT user_id
        FROM users
        WHERE reset_token = :reset_token
          AND reset_expires_at IS NOT NULL
          AND reset_expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([':reset_token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Reset link is invalid or expired.']);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $updateStmt = $pdo->prepare("
        UPDATE users
        SET password = :password,
            reset_token = NULL,
            reset_expires_at = NULL,
            updated_at = NOW()
        WHERE user_id = :user_id
    ");
    $updateStmt->execute([
        ':password' => $hashedPassword,
        ':user_id' => $user['user_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully. You can now login.'
    ]);
} catch (Exception $e) {
    error_log('Reset password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while resetting password.'
    ]);
}

