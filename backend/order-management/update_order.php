<?php
/**
 * Update order and payment states from Order Management.
 */

header('Content-Type: application/json');

require_once '../auth.php';
require_once '../../database/connect_database.php';
require_once 'order_workflow_helper.php';
require_once '../../library/EmailService.php';

$validation = validateSession(false);
if (!$validation['valid']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isManagementLevel()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

function buildStatusUpdateEmail(array $order, $headline, $message, $extraNote = '') {
    $orderNumber = htmlspecialchars((string)($order['order_number'] ?? ''));
    $customerName = htmlspecialchars((string)($order['customer_name'] ?? 'Customer'));
    $statusLabel = htmlspecialchars(mincDescribeOrderStatus($order['order_status'] ?? '', $order['delivery_method'] ?? 'shipping'));
    $paymentLabel = htmlspecialchars(mincDescribePaymentStatus($order['payment_status'] ?? '', $order['payment_method'] ?? 'cod', $order['payment_proof_path'] ?? ''));
    $extraNoteHtml = $extraNote !== ''
        ? '<p style="margin-top:14px;padding:12px;border:1px solid #fed7d7;background:#fff5f5;border-radius:8px;">' . htmlspecialchars($extraNote) . '</p>'
        : '';

    return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; background:#f8fafc; color:#1f2937; }
                .container { max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; }
                .header { background: linear-gradient(135deg, #08415c 0%, #0a5273 100%); color:#ffffff; padding:24px; }
                .content { padding:24px; }
                .summary { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin-top:16px; }
                .footer { padding:0 24px 24px; font-size:12px; color:#6b7280; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2 style="margin:0 0 8px;">' . htmlspecialchars($headline) . '</h2>
                    <p style="margin:0;">MinC Auto Supply</p>
                </div>
                <div class="content">
                    <p>Hello <strong>' . $customerName . '</strong>,</p>
                    <p>' . htmlspecialchars($message) . '</p>
                    <div class="summary">
                        <p><strong>Order Number:</strong> ' . $orderNumber . '</p>
                        <p><strong>Order Status:</strong> ' . $statusLabel . '</p>
                        <p><strong>Payment Status:</strong> ' . $paymentLabel . '</p>
                    </div>
                    ' . $extraNoteHtml . '
                </div>
                <div class="footer">This is an automated update from MinC Auto Supply.</div>
            </div>
        </body>
        </html>';
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new Exception('Invalid request payload');
    }

    $order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
    $newOrderStatus = $input['order_status'] ?? '';
    $newPaymentStatus = $input['payment_status'] ?? '';
    $tracking_number = mincNormalizeWhitespace($input['tracking_number'] ?? '');
    $reason = mincNormalizeWhitespace($input['reason'] ?? '');
    $send_email = isset($input['send_email']) ? (bool)$input['send_email'] : true;

    if ($order_id <= 0 || $newOrderStatus === '' || $newPaymentStatus === '') {
        throw new Exception('Order ID, Order Status, and Payment Status are required');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        SELECT o.*, CONCAT(c.first_name, " ", c.last_name) AS customer_name, c.email AS customer_email
        FROM orders o
        INNER JOIN customers c ON c.customer_id = o.customer_id
        WHERE o.order_id = :order_id
        FOR UPDATE
    ');
    $stmt->execute([':order_id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    $orderColumns = mincGetTableColumns($pdo, 'orders');

    $updateParts =[
        'order_status = :order_status',
        'payment_status = :payment_status',
        'updated_at = NOW()'
    ];
    $updateParams =[
        ':order_status' => $newOrderStatus,
        ':payment_status' => $newPaymentStatus,
        ':order_id' => $order_id
    ];

    if ($tracking_number !== '' && in_array('tracking_number', $orderColumns, true)) {
        $updateParts[] = 'tracking_number = :tracking_number';
        $updateParams[':tracking_number'] = $tracking_number;
    }

    // Auto-generate receipt if marked as delivered
    if ($newOrderStatus === 'delivered' && in_array('receipt_path', $orderColumns, true) && empty($order['receipt_path'])) {
        $updateParts[] = 'receipt_path = :receipt_path';
        $updateParts[] = 'receipt_uploaded_at = NOW()';
        $updateParts[] = 'completed_at = NOW()';
        $updateParams[':receipt_path'] = 'html/order-receipt.php?order=' . rawurlencode((string)$order['order_number']);
    }

    // Handle cancellation
    if ($newOrderStatus === 'cancelled' && in_array('cancel_reason', $orderColumns, true) && $reason !== '') {
        $updateParts[] = 'cancel_reason = :cancel_reason';
        $updateParts[] = 'cancelled_at = NOW()';
        $updateParts[] = 'cancelled_by = :cancelled_by';
        $updateParams[':cancel_reason'] = $reason;
        $updateParams[':cancelled_by'] = $_SESSION['user_id'] ?? null;
    }

    // Append admin notes
    if ($reason !== '') {
        $timestamp = date('Y-m-d H:i:s');
        $actor = trim((string)(($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? '')));
        $existingNotes = trim((string)($order['notes'] ?? ''));
        $combinedNote = trim($existingNotes . "\n[{$timestamp}] Status updated by {$actor}: {$reason}");
        $updateParts[] = 'notes = :notes';
        $updateParams[':notes'] = $combinedNote;
    }

    $update = $pdo->prepare('UPDATE orders SET ' . implode(', ', $updateParts) . ' WHERE order_id = :order_id');
    $update->execute($updateParams);

    // Log to Audit Trail
    $audit = $pdo->prepare('
        INSERT INTO audit_trail
        (user_id, session_username, action, entity_type, entity_id, old_value, new_value, change_reason, ip_address, user_agent)
        VALUES
        (:user_id, :session_username, :action, :entity_type, :entity_id, :old_value, :new_value, :change_reason, :ip_address, :user_agent)
    ');
    $audit->execute([
        ':user_id' => $_SESSION['user_id'] ?? null,
        ':session_username' => trim((string)(($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? ''))),
        ':action' => 'update_order_state',
        ':entity_type' => 'order',
        ':entity_id' => $order_id,
        ':old_value' => json_encode([
            'order_status' => $order['order_status'],
            'payment_status' => $order['payment_status']
        ]),
        ':new_value' => json_encode([
            'order_status' => $newOrderStatus,
            'payment_status' => $newPaymentStatus,
            'tracking_number' => $tracking_number
        ]),
        ':change_reason' => $reason !== '' ? $reason : 'Manual status update via dropdown',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $pdo->commit();

    // Send Email if checked
    if ($send_email && ($newOrderStatus !== $order['order_status'] || $newPaymentStatus !== $order['payment_status'])) {
        try {
            $order['order_status'] = $newOrderStatus;
            $order['payment_status'] = $newPaymentStatus;
            
            $statusEmailHeadline = 'Order Update';
            $statusEmailMessage = 'Your order status has been updated by our team.';
            $emailExtraNote = $reason !== '' ? 'Note from staff: ' . $reason : '';

            $emailService = new EmailService();
            $emailBody = buildStatusUpdateEmail($order, $statusEmailHeadline, $statusEmailMessage, $emailExtraNote);
            $emailService->send((string)$order['customer_email'], 'MinC order update: ' . $statusEmailHeadline, $emailBody);
        } catch (Exception $emailError) {
            error_log('Order status email failed: ' . $emailError->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order updated successfully.',
        'order' =>[
            'order_id' => $order_id,
            'order_status' => $newOrderStatus,
            'payment_status' => $newPaymentStatus
        ]
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
