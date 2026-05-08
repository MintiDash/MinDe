<?php
/**
 * Edit User Backend with Audit Trail
 * File: C:\xampp\htdocs\MinC_Project\backend\user-management\edit_user.php
 */

session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    $_SESSION['error_message'] = 'Session invalid. Please login again.';
    header('Location: ../../app/frontend/user-management.php');
    exit;
}

// Check if user has permission
if (!isManagementLevel()) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to edit users.';
    header('Location: ../../app/frontend/user-management.php');
    exit;
}

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: ../../app/frontend/user-management.php');
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Normalize legacy/misaligned role rows to canonical 4-role model.
    $canonical_roles = [
        1 => 'Admin',
        2 => 'Employee',
        3 => 'Supplier',
        4 => 'Customer'
    ];
    foreach ($canonical_roles as $role_id => $role_name) {
        $role_stmt = $pdo->prepare("SELECT user_level_id, user_type_name, user_type_status FROM user_levels WHERE user_level_id = :user_level_id LIMIT 1");
        $role_stmt->execute([':user_level_id' => $role_id]);
        $role_row = $role_stmt->fetch(PDO::FETCH_ASSOC);

        if ($role_row) {
            if (
                strcasecmp((string)$role_row['user_type_name'], $role_name) !== 0 ||
                strtolower((string)$role_row['user_type_status']) !== 'active'
            ) {
                $update_role_stmt = $pdo->prepare("
                    UPDATE user_levels
                    SET user_type_name = :user_type_name,
                        user_type_status = 'active',
                        updated_at = NOW()
                    WHERE user_level_id = :user_level_id
                ");
                $update_role_stmt->execute([
                    ':user_level_id' => $role_id,
                    ':user_type_name' => $role_name
                ]);
            }
        } else {
            $insert_role_stmt = $pdo->prepare("
                INSERT INTO user_levels (user_level_id, user_type_name, user_type_status, created_at, updated_at)
                VALUES (:user_level_id, :user_type_name, 'active', NOW(), NOW())
            ");
            $insert_role_stmt->execute([
                ':user_level_id' => $role_id,
                ':user_type_name' => $role_name
            ]);
        }
    }

    // Get and sanitize input data
    $user_id = intval($_POST['user_id'] ?? 0);
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = !empty($_POST['username']) ? trim($_POST['username']) : null;
    $contact_num = !empty($_POST['contact_num']) ? trim($_POST['contact_num']) : null;
    $password = $_POST['password'] ?? '';
    $user_level_id = intval($_POST['user_level_id'] ?? 0);
    $user_status = $_POST['user_status'] ?? 'active';

    // Validate required fields
    if ($user_id === 0 || empty($fname) || empty($lname) || empty($email) || $user_level_id === 0) {
        throw new Exception('Please fill in all required fields.');
    }

    if (!in_array($user_level_id, [1, 2, 3, 4], true)) {
        throw new Exception('Invalid user role selected.');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format.');
    }

    // Get old user data for audit trail
    $old_data_query = "
        SELECT fname, lname, email, username, contact_num, user_level_id, user_status
        FROM users
        WHERE user_id = :user_id
    ";
    $old_data_stmt = $pdo->prepare($old_data_query);
    $old_data_stmt->execute([':user_id' => $user_id]);
    $old_data = $old_data_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old_data) {
        throw new Exception('User not found.');
    }

    // Check if email already exists for other users
    $email_check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $email_check->execute([$email, $user_id]);
    if ($email_check->fetch()) {
        throw new Exception('Email already exists for another user.');
    }

    // Check if username already exists for other users (if provided)
    if (!empty($username)) {
        $username_check = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $username_check->execute([$username, $user_id]);
        if ($username_check->fetch()) {
            throw new Exception('Username already exists for another user.');
        }
    }

    // Build update query
    $update_fields = [
        'fname = :fname',
        'lname = :lname',
        'email = :email',
        'username = :username',
        'contact_num = :contact_num',
        'user_level_id = :user_level_id',
        'user_status = :user_status',
        'updated_at = NOW()'
    ];

    $params = [
        ':user_id' => $user_id,
        ':fname' => $fname,
        ':lname' => $lname,
        ':email' => $email,
        ':username' => $username,
        ':contact_num' => $contact_num,
        ':user_level_id' => $user_level_id,
        ':user_status' => $user_status
    ];

    // Add password to update if provided
    if (!empty($password)) {
        $update_fields[] = 'password = :password';
        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $update_query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE user_id = :user_id";
    
    $stmt = $pdo->prepare($update_query);
    $stmt->execute($params);

    // Prepare audit trail data
    $old_value = [
        'fname' => $old_data['fname'],
        'lname' => $old_data['lname'],
        'email' => $old_data['email'],
        'username' => $old_data['username'],
        'contact_num' => $old_data['contact_num'],
        'user_level_id' => (int)$old_data['user_level_id'],
        'user_status' => $old_data['user_status']
    ];

    $new_value = [
        'fname' => $fname,
        'lname' => $lname,
        'email' => $email,
        'username' => $username,
        'contact_num' => $contact_num,
        'user_level_id' => (string)$user_level_id,
        'user_status' => $user_status
    ];

    // Insert audit trail
    $audit_query = "
        INSERT INTO audit_trail (
            user_id, session_username, action, entity_type, entity_id,
            old_value, new_value, timestamp, ip_address, user_agent
        ) VALUES (
            :user_id, :session_username, :action, :entity_type, :entity_id,
            :old_value, :new_value, NOW(), :ip_address, :user_agent
        )
    ";

    $audit_stmt = $pdo->prepare($audit_query);
    $audit_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':session_username' => $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'System',
        ':action' => 'update',
        ':entity_type' => 'user',
        ':entity_id' => $user_id,
        ':old_value' => json_encode($old_value),
        ':new_value' => json_encode($new_value),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success_message'] = 'User updated successfully!';
    header('Location: ../../app/frontend/user-management.php');
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = 'Error updating user: ' . $e->getMessage();
    header('Location: ../../app/frontend/user-management.php');
    exit;
}
