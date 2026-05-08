<?php
/**
 * Deactivate authenticated user's own account
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
    $password = isset($input['password']) ? (string)$input['password'] : '';

    if ($password === '') {
        echo json_encode(['success' => false, 'message' => 'Password is required']);
        exit;
    }

    $userStmt = $pdo->prepare("SELECT user_id, password, user_status, email FROM users WHERE user_id = :user_id LIMIT 1");
    $userStmt->execute([':user_id' => $user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if (!password_verify($password, (string)$user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Password is incorrect']);
        exit;
    }

    if ($user['user_status'] !== 'inactive') {
        $updateStmt = $pdo->prepare("UPDATE users SET user_status = 'inactive', updated_at = NOW() WHERE user_id = :user_id");
        $updateStmt->execute([':user_id' => $user_id]);
    }

    $auditStmt = $pdo->prepare("
        INSERT INTO audit_trail 
        (user_id, session_username, action, entity_type, entity_id, old_value, new_value, change_reason, ip_address, user_agent)
        VALUES
        (:user_id, :session_username, :action, :entity_type, :entity_id, :old_value, :new_value, :change_reason, :ip_address, :user_agent)
    ");
    $auditStmt->execute([
        ':user_id' => $user_id,
        ':session_username' => trim(($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? '')),
        ':action' => 'deactivate_own_account',
        ':entity_type' => 'user',
        ':entity_id' => $user_id,
        ':old_value' => json_encode(['user_status' => $user['user_status']]),
        ':new_value' => json_encode(['user_status' => 'inactive']),
        ':change_reason' => 'User deactivated own account',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    session_unset();
    session_destroy();

    $projectBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/backend/deactivate_account.php', 2)), '/');
    if ($projectBase === '') {
        $projectBase = '/';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Account deactivated successfully',
        'redirect' => rtrim($projectBase, '/') . '/index.php'
    ]);
} catch (Exception $e) {
    error_log('Error in deactivate_account.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deactivating account'
    ]);
}

ob_end_flush();
?>
