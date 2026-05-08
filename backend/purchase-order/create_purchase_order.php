<?php
/**
 * Create Purchase Order Backend
 */

session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';
require_once __DIR__ . '/purchase_order_schema.php';

$redirect_url = '../../app/frontend/purchase-order.php';

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
    ensurePurchaseOrdersTable($pdo);

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $order_date = trim((string)($_POST['order_date'] ?? ''));
    $expected_delivery_date = trim((string)($_POST['expected_delivery_date'] ?? ($_POST['delivery_date'] ?? '')));
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));
    $notes = $notes === '' ? null : $notes;

    if ($supplier_id <= 0) {
        throw new Exception('Please select a supplier.');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $order_date) || strtotime($order_date) === false) {
        throw new Exception('Please provide a valid order date.');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected_delivery_date) || strtotime($expected_delivery_date) === false) {
        throw new Exception('Please provide a valid expected delivery date.');
    }

    if ($expected_delivery_date < $order_date) {
        throw new Exception('Expected delivery date cannot be earlier than order date.');
    }

    if ($total_amount <= 0) {
        throw new Exception('Total amount must be greater than zero.');
    }

    if ($notes !== null) {
        $safe_len = function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes);
        if ($safe_len > 2000) {
            throw new Exception('Notes must not exceed 2000 characters.');
        }
    }

    $supplier_stmt = $pdo->prepare("
        SELECT supplier_id, supplier_name, status
        FROM suppliers
        WHERE supplier_id = :supplier_id
        LIMIT 1
    ");
    $supplier_stmt->execute([':supplier_id' => $supplier_id]);
    $supplier = $supplier_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        throw new Exception('Selected supplier was not found.');
    }
    if (strtolower((string)($supplier['status'] ?? 'inactive')) !== 'active') {
        throw new Exception('Selected supplier is inactive. Please choose an active supplier.');
    }

    $generatePONumber = static function (PDO $pdo) {
        $prefix = 'PO-' . date('Ymd') . '-';
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $candidate = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $check_stmt = $pdo->prepare('SELECT po_id FROM purchase_orders WHERE po_number = :po_number LIMIT 1');
            $check_stmt->execute([':po_number' => $candidate]);
            if (!$check_stmt->fetch()) {
                return $candidate;
            }
        }

        throw new Exception('Unable to generate purchase order number. Please try again.');
    };

    $pdo->beginTransaction();

    $po_number = $generatePONumber($pdo);

    $insert_stmt = $pdo->prepare("
        INSERT INTO purchase_orders (
            po_number,
            supplier_id,
            order_date,
            expected_delivery_date,
            total_amount,
            status,
            notes,
            created_by,
            created_at,
            updated_at
        ) VALUES (
            :po_number,
            :supplier_id,
            :order_date,
            :expected_delivery_date,
            :total_amount,
            'pending',
            :notes,
            :created_by,
            NOW(),
            NOW()
        )
    ");
    $insert_stmt->execute([
        ':po_number' => $po_number,
        ':supplier_id' => $supplier_id,
        ':order_date' => $order_date,
        ':expected_delivery_date' => $expected_delivery_date,
        ':total_amount' => $total_amount,
        ':notes' => $notes,
        ':created_by' => $_SESSION['user_id'] ?? null
    ]);

    $po_id = (int)$pdo->lastInsertId();

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
                'CREATE',
                'purchase_order',
                :entity_id,
                NULL,
                :new_value,
                'Created purchase order',
                NOW(),
                :ip_address,
                :user_agent,
                'minc_system'
            )
        ");

        $audit_stmt->execute([
            ':user_id' => $_SESSION['user_id'] ?? null,
            ':session_username' => $_SESSION['full_name'] ?? ($_SESSION['fname'] ?? 'System'),
            ':entity_id' => (string)$po_id,
            ':new_value' => json_encode([
                'po_number' => $po_number,
                'supplier_id' => $supplier_id,
                'supplier_name' => $supplier['supplier_name'] ?? null,
                'order_date' => $order_date,
                'expected_delivery_date' => $expected_delivery_date,
                'total_amount' => $total_amount,
                'status' => 'pending'
            ]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $audit_exception) {
        error_log('Audit log failed in create_purchase_order.php: ' . $audit_exception->getMessage());
    }

    $pdo->commit();

    $_SESSION['success_message'] = 'Purchase order created successfully.';
    header('Location: ' . $redirect_url);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Error creating purchase order: ' . $e->getMessage();
    header('Location: ' . $redirect_url);
    exit;
}
?>
