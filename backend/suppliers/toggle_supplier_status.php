<?php
/**
 * Toggle Supplier Status Backend
 */

session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';

$redirect_url = '../../app/frontend/suppliers.php';

$validation = validateSession();
if (!$validation['valid']) {
    $_SESSION['error_message'] = 'Session expired. Please login again.';
    header('Location: ../../index.php');
    exit;
}

if (!isITStaff() && !isOwner()) {
    $_SESSION['error_message'] = 'Access denied. Insufficient permissions.';
    header('Location: ' . $redirect_url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: ' . $redirect_url);
    exit;
}

try {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    if ($supplier_id <= 0) {
        throw new Exception('Invalid supplier.');
    }

    $current_stmt = $pdo->prepare('SELECT * FROM suppliers WHERE supplier_id = :supplier_id LIMIT 1');
    $current_stmt->execute([':supplier_id' => $supplier_id]);
    $current = $current_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        throw new Exception('Supplier not found.');
    }

    $current_status = strtolower((string)($current['status'] ?? 'inactive')) === 'active' ? 'active' : 'inactive';
    $new_status = $current_status === 'active' ? 'inactive' : 'active';

    $pdo->beginTransaction();

    $update_stmt = $pdo->prepare("
        UPDATE suppliers
        SET status = :status, updated_at = NOW()
        WHERE supplier_id = :supplier_id
        LIMIT 1
    ");
    $update_stmt->execute([
        ':status' => $new_status,
        ':supplier_id' => $supplier_id
    ]);

    try {
        $audit_stmt = $pdo->prepare("
            INSERT INTO audit_trail (
                user_id,
                session_username,
                action,
                entity_type,
                entity_id,
                old_value,
                new_value,
                change_reason,
                timestamp,
                ip_address,
                user_agent,
                system_id
            ) VALUES (
                :user_id,
                :session_username,
                'UPDATE',
                'supplier',
                :entity_id,
                :old_value,
                :new_value,
                :change_reason,
                NOW(),
                :ip_address,
                :user_agent,
                'minc_system'
            )
        ");

        $audit_stmt->execute([
            ':user_id' => $_SESSION['user_id'] ?? null,
            ':session_username' => $_SESSION['full_name'] ?? ($_SESSION['fname'] ?? 'System'),
            ':entity_id' => (string)$supplier_id,
            ':old_value' => json_encode(['status' => $current_status]),
            ':new_value' => json_encode(['status' => $new_status]),
            ':change_reason' => $new_status === 'active' ? 'Activated supplier' : 'Deactivated supplier',
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $audit_exception) {
        error_log('Audit log failed in toggle_supplier_status.php: ' . $audit_exception->getMessage());
    }

    $pdo->commit();

    $_SESSION['success_message'] = $new_status === 'active'
        ? 'Supplier activated successfully.'
        : 'Supplier deactivated successfully.';
    header('Location: ' . $redirect_url);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Error updating supplier status: ' . $e->getMessage();
    header('Location: ' . $redirect_url);
    exit;
}
?>
