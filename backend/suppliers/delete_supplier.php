<?php
/**
 * Delete Supplier Backend
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

    $supplier_stmt = $pdo->prepare('SELECT * FROM suppliers WHERE supplier_id = :supplier_id LIMIT 1');
    $supplier_stmt->execute([':supplier_id' => $supplier_id]);
    $supplier = $supplier_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        throw new Exception('Supplier not found.');
    }

    // Block deletion if the supplier is already used in purchase orders.
    try {
        $usage_stmt = $pdo->prepare('SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = :supplier_id');
        $usage_stmt->execute([':supplier_id' => $supplier_id]);
        $usage_count = (int)$usage_stmt->fetchColumn();
        if ($usage_count > 0) {
            throw new Exception('Supplier cannot be deleted because it is linked to purchase orders. Deactivate it instead.');
        }
    } catch (PDOException $ignored) {
        // purchase_orders table might not exist yet; skip relation check in that case.
    }

    $pdo->beginTransaction();

    $delete_stmt = $pdo->prepare('DELETE FROM suppliers WHERE supplier_id = :supplier_id LIMIT 1');
    $delete_stmt->execute([':supplier_id' => $supplier_id]);

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
                'DELETE',
                'supplier',
                :entity_id,
                :old_value,
                NULL,
                'Deleted supplier',
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
            ':old_value' => json_encode([
                'supplier_name' => $supplier['supplier_name'] ?? null,
                'contact_person' => $supplier['contact_person'] ?? null,
                'email' => $supplier['email'] ?? null,
                'phone' => $supplier['phone'] ?? null,
                'address' => $supplier['address'] ?? null,
                'city' => $supplier['city'] ?? null,
                'province' => $supplier['province'] ?? null,
                'status' => $supplier['status'] ?? null
            ]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $audit_exception) {
        error_log('Audit log failed in delete_supplier.php: ' . $audit_exception->getMessage());
    }

    $pdo->commit();

    $_SESSION['success_message'] = 'Supplier deleted successfully.';
    header('Location: ' . $redirect_url);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Error deleting supplier: ' . $e->getMessage();
    header('Location: ' . $redirect_url);
    exit;
}
?>
