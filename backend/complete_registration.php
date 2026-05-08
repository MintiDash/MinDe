<?php
/**
 * Complete registration by setting password after OTP verification.
 */

session_start();
require_once __DIR__ . '/../database/connect_database.php';

header('Content-Type: application/json');

function isStrongPasswordForRegistration($password) {
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
$email = isset($input['email']) ? filter_var($input['email'], FILTER_SANITIZE_EMAIL) : '';
$password = isset($input['password']) ? (string)$input['password'] : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (!isStrongPasswordForRegistration($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters and include a letter, number, and special character'
    ]);
    exit;
}

if (
    !isset($_SESSION['registration_verified_email'], $_SESSION['registration_verified_user_id'], $_SESSION['registration_verified_at']) ||
    $_SESSION['registration_verified_email'] !== $email
) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please verify your OTP first']);
    exit;
}

if ((time() - (int)$_SESSION['registration_verified_at']) > 900) {
    unset($_SESSION['registration_verified_email'], $_SESSION['registration_verified_user_id'], $_SESSION['registration_verified_at']);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Verification expired. Please verify OTP again.']);
    exit;
}

try {
    $hasVerifiedColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_email_verified'")->rowCount() > 0;
    $hasVerifiedAtColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified_at'")->rowCount() > 0;

    if (!$hasVerifiedColumn || !$hasVerifiedAtColumn) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => "Database schema incomplete. Please run SETUP_DATABASE.sql first."
        ]);
        exit;
    }

    $userId = (int)$_SESSION['registration_verified_user_id'];

    $userStmt = $pdo->prepare("
        SELECT user_id, user_level_id
        FROM users
        WHERE user_id = :user_id AND email = :email
        LIMIT 1
    ");
    $userStmt->execute([
        ':user_id' => $userId,
        ':email' => $email
    ]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int)$user['user_level_id'] !== 4) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Registration record not found']);
        exit;
    }

    $pdo->beginTransaction();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $updateStmt = $pdo->prepare("
        UPDATE users
        SET password = :password,
            is_email_verified = 1,
            email_verified_at = NOW(),
            user_status = 'active',
            updated_at = NOW()
        WHERE user_id = :user_id
    ");
    $updateStmt->execute([
        ':password' => $hashedPassword,
        ':user_id' => $userId
    ]);

    $markTokensStmt = $pdo->prepare("
        UPDATE email_verification_tokens
        SET is_used = 1, verified_at = NOW()
        WHERE user_id = :user_id AND is_used = 0
    ");
    $markTokensStmt->execute([':user_id' => $userId]);

    $pdo->commit();

    unset($_SESSION['registration_pending_email'], $_SESSION['registration_pending_user_id']);
    unset($_SESSION['registration_verified_email'], $_SESSION['registration_verified_user_id'], $_SESSION['registration_verified_at']);

    echo json_encode([
        'success' => true,
        'message' => 'Account setup complete. You can now login.'
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Complete registration error: ' . $e->getMessage());
    http_response_code(500);

    if (($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1' || ($_SERVER['REMOTE_ADDR'] ?? '') === '::1') {
        echo json_encode(['success' => false, 'message' => 'Complete registration database error: ' . $e->getMessage()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'An error occurred while setting your password']);
    }
}
