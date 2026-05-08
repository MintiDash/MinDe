<?php
session_start();
require_once '../database/connect_database.php';
require_once '../backend/order-management/order_workflow_helper.php';

$order_number = isset($_GET['order']) ? trim((string)$_GET['order']) : '';
$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_management_user = isset($_SESSION['user_level_id']) && (int)$_SESSION['user_level_id'] <= 2;

if ($order_number === '' || $current_user_id <= 0) {
    header('Location: ../index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT
            o.*,
            c.user_id AS customer_user_id,
            c.first_name,
            c.last_name,
            c.email
        FROM orders o
        INNER JOIN customers c ON o.customer_id = c.customer_id
        WHERE o.order_number = ?
        LIMIT 1
    ');
    $stmt->execute([$order_number]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Location: my-orders.php');
        exit;
    }

    if (!$is_management_user && (int)($order['customer_user_id'] ?? 0) !== $current_user_id) {
        header('Location: my-orders.php');
        exit;
    }

    $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC');
    $itemsStmt->execute([(int)$order['order_id']]);
    $order_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Order success page error: ' . $e->getMessage());
    header('Location: my-orders.php');
    exit;
}

$delivery_method = strtolower(trim((string)($order['delivery_method'] ?? 'shipping')));
$payment_method = strtolower(trim((string)($order['payment_method'] ?? 'cod')));
$order_status_label = mincDescribeOrderStatus($order['order_status'] ?? '', $delivery_method);
$payment_status_label = mincDescribePaymentStatus($order['payment_status'] ?? '', $payment_method, $order['payment_proof_path'] ?? '');
$payment_method_label = mincDescribePaymentMethod($payment_method);
$proof_url = !empty($order['payment_proof_path']) ? mincPublicAssetUrl($order['payment_proof_path']) : '';
$receipt_url = !empty($order['receipt_path'])
    ? (preg_match('/^(https?:)?\//i', (string)$order['receipt_path']) ? $order['receipt_path'] : mincPublicAssetUrl($order['receipt_path']))
    : '';

$next_step_message = 'Your order is now in the queue for processing.';
if (mincPaymentMethodRequiresProof($payment_method)) {
    $next_step_message = !empty($order['payment_proof_path'])
        ? 'Your proof of payment is attached and waiting for admin review. The order will move to Received once staff confirms it.'
        : 'Upload proof of payment so the admin account can review and confirm the order.';
} elseif ($payment_method === 'cod') {
    $next_step_message = $delivery_method === 'pickup'
        ? 'Payment will be collected at the store before the order is marked Completed.'
        : 'Payment will be collected by staff upon release or delivery before the order is marked Completed.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Submitted - MinC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/ca30ddfff9.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .btn-primary-custom {
            background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
            transition: all 0.3s ease;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(8, 65, 92, 0.4);
        }
        .hero-gradient {
            background: linear-gradient(135deg, #08415c 0%, #0a5273 50%, #08415c 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'components/navbar.php'; ?>

    <section class="hero-gradient mt-20 py-12 px-4">
        <div class="max-w-5xl mx-auto">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white/10 rounded-full mb-5">
                <i class="fas fa-check text-white text-4xl"></i>
            </div>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">Order Submitted</h1>
            <p class="text-blue-100 text-lg">Reference your order number and track the next steps from My Orders.</p>
        </div>
    </section>

    <main class="max-w-5xl mx-auto px-4 py-10">
        <div class="grid lg:grid-cols-[1.35fr,0.95fr] gap-6">
            <section class="bg-white rounded-2xl shadow-lg p-6 md:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-6 mb-6">
                    <div>
                        <p class="text-sm uppercase tracking-[0.22em] text-[#0a5273] font-semibold mb-2">Order Details</p>
                        <h2 class="text-2xl font-bold text-[#08415c]"><?php echo htmlspecialchars($order['order_number']); ?></h2>
                        <p class="text-sm text-gray-500 mt-1">Submitted on <?php echo date('F j, Y g:i A', strtotime((string)$order['created_at'])); ?></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-3 py-1 rounded-full text-sm font-semibold bg-blue-50 text-blue-700 border border-blue-100"><?php echo htmlspecialchars($order_status_label); ?></span>
                        <span class="px-3 py-1 rounded-full text-sm font-semibold bg-green-50 text-green-700 border border-green-100"><?php echo htmlspecialchars($payment_status_label); ?></span>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4 text-sm mb-6">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500 mb-2">Customer</p>
                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))); ?></p>
                        <p class="text-gray-700 mt-1"><?php echo htmlspecialchars($order['email'] ?? ''); ?></p>
                        <p class="text-gray-700"><?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500 mb-2">Payment</p>
                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($payment_method_label); ?></p>
                        <?php if (!empty($order['payment_reference'])): ?>
                            <p class="text-gray-700 mt-1">Reference: <?php echo htmlspecialchars($order['payment_reference']); ?></p>
                        <?php endif; ?>
                        <p class="text-gray-700 mt-1">Status: <?php echo htmlspecialchars($payment_status_label); ?></p>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 p-5 mb-6 bg-slate-50">
                    <h3 class="text-lg font-bold text-[#08415c] mb-3"><?php echo $delivery_method === 'pickup' ? 'Pickup Details' : 'Shipping Details'; ?></h3>
                    <?php if ($delivery_method === 'pickup'): ?>
                        <p class="text-gray-700"><strong>Pickup Location:</strong> MinC Auto Supply, 1144 Jake Gonzales Blvd, Angeles, Pampanga</p>
                        <?php if (!empty($order['pickup_date'])): ?>
                            <p class="text-gray-700 mt-1"><strong>Pickup Date:</strong> <?php echo htmlspecialchars($order['pickup_date']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['pickup_time'])): ?>
                            <p class="text-gray-700 mt-1"><strong>Pickup Time:</strong> <?php echo htmlspecialchars($order['pickup_time']); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-gray-700"><?php echo htmlspecialchars($order['shipping_address'] ?? ''); ?></p>
                        <p class="text-gray-700 mt-1"><?php echo htmlspecialchars(($order['shipping_city'] ?? '') . ', ' . ($order['shipping_province'] ?? '')); ?></p>
                        <?php if (!empty($order['shipping_postal_code'])): ?>
                            <p class="text-gray-700 mt-1"><?php echo htmlspecialchars($order['shipping_postal_code']); ?></p>
                        <?php endif; ?>
                        <p class="text-gray-700 mt-1"><strong>Shipping Fee:</strong> <?php echo ((float)($order['shipping_fee'] ?? 0) > 0) ? 'PHP ' . number_format((float)$order['shipping_fee'], 2) : 'FREE'; ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <h3 class="text-lg font-bold text-[#08415c] mb-4">Order Items</h3>
                    <div class="space-y-3">
                        <?php foreach ($order_items as $item): ?>
                            <div class="flex justify-between items-center gap-4 py-3 border-b border-gray-100 last:border-b-0">
                                <div>
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                    <p class="text-sm text-gray-500">Qty: <?php echo (int)$item['quantity']; ?> x PHP <?php echo number_format((float)$item['price'], 2); ?></p>
                                </div>
                                <p class="font-semibold text-[#08415c]">PHP <?php echo number_format((float)$item['subtotal'], 2); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-6 space-y-2 text-sm">
                        <div class="flex justify-between text-gray-700"><span>Subtotal</span><span>PHP <?php echo number_format((float)$order['subtotal'], 2); ?></span></div>
                        <div class="flex justify-between text-gray-700"><span>Shipping</span><span><?php echo ((float)($order['shipping_fee'] ?? 0) > 0) ? 'PHP ' . number_format((float)$order['shipping_fee'], 2) : 'FREE'; ?></span></div>
                        <div class="flex justify-between text-lg font-bold text-[#08415c] pt-3 border-t border-gray-200"><span>Total</span><span>PHP <?php echo number_format((float)$order['total_amount'], 2); ?></span></div>
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-[#08415c] mb-3">Next Step</h3>
                    <p class="text-gray-700"><?php echo htmlspecialchars($next_step_message); ?></p>
                    <p class="text-sm text-gray-500 mt-3">Email notifications will be sent to <?php echo htmlspecialchars($order['email'] ?? ''); ?> for confirmation, cancellation, and completion updates.</p>
                </section>

                <section class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-[#08415c] mb-4">Attached Documents</h3>
                    <div class="space-y-3 text-sm">
                        <?php if ($proof_url !== ''): ?>
                            <a href="<?php echo htmlspecialchars($proof_url); ?>" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-blue-700 hover:bg-blue-100 transition">
                                <span><i class="fas fa-file-upload mr-2"></i>Proof of Payment</span>
                                <i class="fas fa-arrow-up-right-from-square"></i>
                            </a>
                        <?php elseif (mincPaymentMethodRequiresProof($payment_method)): ?>
                            <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-amber-700">
                                <i class="fas fa-clock mr-2"></i>Awaiting proof upload or review.
                            </div>
                        <?php endif; ?>

                        <?php if ($receipt_url !== ''): ?>
                            <a href="<?php echo htmlspecialchars($receipt_url); ?>" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between rounded-xl border border-green-100 bg-green-50 px-4 py-3 text-green-700 hover:bg-green-100 transition">
                                <span><i class="fas fa-file-invoice mr-2"></i>Receipt</span>
                                <i class="fas fa-arrow-up-right-from-square"></i>
                            </a>
                        <?php else: ?>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-gray-600">
                                <i class="fas fa-receipt mr-2"></i>Receipt will attach after the order is completed.
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if (!empty($order['cancel_reason'])): ?>
                    <section class="bg-red-50 border border-red-200 rounded-2xl p-6">
                        <h3 class="text-lg font-bold text-red-700 mb-2">Cancellation Reason</h3>
                        <p class="text-red-800"><?php echo htmlspecialchars($order['cancel_reason']); ?></p>
                    </section>
                <?php endif; ?>

                <section class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="grid gap-3">
                        <a href="my-orders.php" class="btn-primary-custom text-white px-6 py-3 rounded-xl font-semibold text-center">
                            <i class="fas fa-box mr-2"></i>View My Orders
                        </a>
                        <button onclick="window.print()" class="px-6 py-3 rounded-xl font-semibold border border-gray-200 text-gray-700 hover:bg-gray-50 transition">
                            <i class="fas fa-print mr-2"></i>Print / Save as PDF
                        </button>
                        <a href="../index.php" class="px-6 py-3 rounded-xl font-semibold border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-center">
                            <i class="fas fa-home mr-2"></i>Back to Home
                        </a>
                    </div>
                </section>
            </aside>
        </div>
    </main>

    <?php include 'components/footer.php'; ?>
<!-- Chat Bubble Component -->
<?php include 'components/chat_bubble.php'; ?>
</body>
</html>
