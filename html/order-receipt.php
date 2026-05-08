<?php
session_start();

require_once '../database/connect_database.php';
require_once '../backend/auth.php';

$validation = validateSession(false);
if (!$validation['valid']) {
    header('Location: ../index.php?error=unauthorized');
    exit;
}

$orderNumber = trim((string)($_GET['order'] ?? ''));
if ($orderNumber === '') {
    header('Location: my-orders.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT o.*, c.first_name, c.last_name, c.email, c.user_id AS customer_user_id
        FROM orders o
        INNER JOIN customers c ON c.customer_id = o.customer_id
        WHERE o.order_number = :order_number
        LIMIT 1
    ");
    $stmt->execute([':order_number' => $orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found.');
    }

    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $isManager = isManagementLevel();
    if (!$isManager && $currentUserId !== (int)($order['customer_user_id'] ?? 0)) {
        throw new Exception('Access denied.');
    }

    $itemsStmt = $pdo->prepare("
        SELECT product_name, product_code, quantity, price, subtotal
        FROM order_items
        WHERE order_id = :order_id
        ORDER BY order_item_id ASC
    ");
    $itemsStmt->execute([':order_id' => $order['order_id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: my-orders.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo htmlspecialchars($order['order_number']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6 no-print">
            <a href="javascript:history.back()" class="px-4 py-2 bg-slate-200 rounded-lg hover:bg-slate-300">Back</a>
            <button onclick="window.print()" class="px-4 py-2 bg-[#08415c] text-white rounded-lg hover:bg-[#0a5273]">Print / Save PDF</button>
        </div>

        <div class="bg-white shadow-xl rounded-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-[#08415c] to-[#0a5273] text-white px-8 py-6">
                <div class="flex items-start justify-between gap-6">
                    <div>
                        <p class="uppercase tracking-[0.2em] text-sm text-blue-100">MinC Auto Supply</p>
                        <h1 class="text-3xl font-bold mt-2">Order Receipt</h1>
                        <p class="text-blue-100 mt-2">Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
                    </div>
                    <div class="text-right text-sm">
                        <p>Date Completed</p>
                        <p class="font-semibold"><?php echo htmlspecialchars(date('F j, Y', strtotime($order['completed_at'] ?? $order['updated_at'] ?? $order['created_at']))); ?></p>
                    </div>
                </div>
            </div>

            <div class="p-8 space-y-8">
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="border border-slate-200 rounded-xl p-5">
                        <h2 class="text-sm uppercase tracking-wide text-slate-500 mb-3">Customer</h2>
                        <p class="font-semibold"><?php echo htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name'])); ?></p>
                        <p><?php echo htmlspecialchars($order['email']); ?></p>
                        <p><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                    </div>
                    <div class="border border-slate-200 rounded-xl p-5">
                        <h2 class="text-sm uppercase tracking-wide text-slate-500 mb-3">Order Summary</h2>
                        <p><span class="font-semibold">Payment Method:</span> <?php echo htmlspecialchars(strtoupper($order['payment_method'])); ?></p>
                        <p><span class="font-semibold">Payment Status:</span> <?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?></p>
                        <p><span class="font-semibold">Order Status:</span> <?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></p>
                        <?php if (!empty($order['payment_reference'])): ?>
                            <p><span class="font-semibold">Reference:</span> <?php echo htmlspecialchars($order['payment_reference']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="text-left px-4 py-3">Item</th>
                                <th class="text-left px-4 py-3">Code</th>
                                <th class="text-right px-4 py-3">Qty</th>
                                <th class="text-right px-4 py-3">Price</th>
                                <th class="text-right px-4 py-3">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr class="border-t border-slate-200">
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($item['product_code']); ?></td>
                                    <td class="px-4 py-3 text-right"><?php echo (int)$item['quantity']; ?></td>
                                    <td class="px-4 py-3 text-right">PHP <?php echo number_format((float)$item['price'], 2); ?></td>
                                    <td class="px-4 py-3 text-right">PHP <?php echo number_format((float)$item['subtotal'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div class="border border-slate-200 rounded-xl p-5">
                        <h2 class="text-sm uppercase tracking-wide text-slate-500 mb-3">Release Details</h2>
                        <p><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                        <p><?php echo htmlspecialchars($order['shipping_city']); ?>, <?php echo htmlspecialchars($order['shipping_province']); ?></p>
                        <?php if (!empty($order['shipping_postal_code'])): ?>
                            <p><?php echo htmlspecialchars($order['shipping_postal_code']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="border border-slate-200 rounded-xl p-5">
                        <h2 class="text-sm uppercase tracking-wide text-slate-500 mb-3">Totals</h2>
                        <div class="space-y-2">
                            <div class="flex justify-between"><span>Subtotal</span><span>PHP <?php echo number_format((float)$order['subtotal'], 2); ?></span></div>
                            <div class="flex justify-between"><span>Shipping Fee</span><span>PHP <?php echo number_format((float)$order['shipping_fee'], 2); ?></span></div>
                            <div class="flex justify-between font-bold text-lg border-t border-slate-200 pt-2"><span>Total</span><span>PHP <?php echo number_format((float)$order['total_amount'], 2); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
