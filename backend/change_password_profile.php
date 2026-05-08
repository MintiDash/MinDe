<?php
/**
 * Change password for authenticated user from profile page
 */

ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once '../database/connect_database.php';
    require_once 'auth.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $validation = validateSession(false);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'message' => 'Session invalid']);
        exit;
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'User ID not found in session']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = isset($input['current_password']) ? (string)$input['current_password'] : '';
    $newPassword = isset($input['new_password']) ? (string)$input['new_password'] : '';

    if ($currentPassword === '' || $newPassword === '') {
        echo json_encode(['success' => false, 'message' => 'Current password and new password are required']);
        exit;
    }

    if (strlen($newPassword) < 8 ||
        $newPassword === '123456' ||
        !preg_match('/[A-Za-z]/', $newPassword) ||
        !preg_match('/\d/', $newPassword) ||
        !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 8 characters and include a letter, number, and special character.'
        ]);
        exit;
    }

    $userStmt = $pdo->prepare("SELECT user_id, password, user_status, email FROM users WHERE user_id = :user_id LIMIT 1");
    $userStmt->execute([':user_id' => $user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if ($user['user_status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Account is not active']);
        exit;
    }

    if (!password_verify($currentPassword, (string)$user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    if (password_verify($newPassword, (string)$user['password'])) {
        echo json_encode(['success' => false, 'message' => 'New password must be different from current password']);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

    $updateStmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE user_id = :user_id");
    $updateStmt->execute([
        ':password' => $newHash,
        ':user_id' => $user_id
    ]);

    $auditStmt = $pdo->prepare("
        INSERT INTO audit_trail 
        (user_id, session_username, action, entity_type, entity_id, old_value, new_value, change_reason, ip_address, user_agent)
        VALUES
        (:user_id, :session_username, :action, :entity_type, :entity_id, :old_value, :new_value, :change_reason, :ip_address, :user_agent)
    ");
    $auditStmt->execute([
        ':user_id' => $user_id,
        ':session_username' => trim(($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? '')),
        ':action' => 'change_password',
        ':entity_type' => 'user',
        ':entity_id' => $user_id,
        ':old_value' => json_encode(['password' => 'hidden']),
        ':new_value' => json_encode(['password' => 'updated']),
        ':change_reason' => 'User changed own password',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
} catch (Exception $e) {
    error_log('Error in change_password_profile.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while changing password'
    ]);
}

ob_end_flush();
?>
