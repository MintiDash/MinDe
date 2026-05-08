<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - MinC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/ca30ddfff9.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hero-gradient { background: linear-gradient(135deg, #08415c 0%, #0a5273 50%, #08415c 100%); }
        .status-badge { padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'components/navbar.php'; ?>

    <section class="hero-gradient mt-20 py-12 px-4">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">My Orders</h1>
            <p class="text-blue-100 text-lg">Track your order and payment status</p>
        </div>
    </section>

    <main class="max-w-7xl mx-auto px-4 py-10">
        <div id="loadingState" class="text-center py-12">
            <i class="fas fa-spinner fa-spin text-3xl text-[#08415c] mb-3"></i>
            <p class="text-gray-600">Loading your orders...</p>
        </div>

        <div id="emptyState" class="hidden bg-white rounded-xl shadow-lg p-10 text-center">
            <i class="fas fa-box-open text-5xl text-gray-300 mb-4"></i>
            <p class="text-xl text-gray-700 font-semibold mb-2">No orders yet</p>
            <p class="text-gray-500 mb-6">Once you place an order, it will appear here.</p>
            <a href="product.php" class="inline-block bg-[#08415c] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#0a5273] transition">Shop Now</a>
        </div>

        <div id="ordersList" class="hidden grid gap-4"></div>
    </main>

    <?php include 'components/footer.php'; ?>

    <script>
        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
        }

        function formatPeso(amount) {
            return Number(amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        function orderStatusClass(status) {
            const map = {
                pending: 'bg-yellow-100 text-yellow-800',
                confirmed: 'bg-blue-100 text-blue-800',
                processing: 'bg-indigo-100 text-indigo-800',
                shipped: 'bg-purple-100 text-purple-800',
                delivered: 'bg-green-100 text-green-800',
                cancelled: 'bg-red-100 text-red-800'
            };
            return map[status] || 'bg-gray-100 text-gray-700';
        }

        function paymentStatusClass(status) {
            const map = {
                pending: 'bg-yellow-100 text-yellow-800',
                paid: 'bg-green-100 text-green-800',
                failed: 'bg-red-100 text-red-800',
                refunded: 'bg-gray-100 text-gray-700'
            };
            return map[status] || 'bg-gray-100 text-gray-700';
        }

        function requiresPaymentProof(method) {
            return ['bpi', 'bank_transfer', 'gcash', 'paymaya'].includes(String(method || '').toLowerCase());
        }

        function displayOrderStatus(order) {
            const status = String(order.order_status || '').toLowerCase();
            const deliveryMethod = String(order.delivery_method || 'shipping').toLowerCase();

            if (status === 'pending') return 'Awaiting Review';
            if (status === 'confirmed') return 'Received';
            if (status === 'processing') return 'Preparing Items';
            if (status === 'shipped') return deliveryMethod === 'pickup' ? 'Ready for Pickup' : 'Out for Delivery';
            if (status === 'delivered') return 'Completed';
            if (status === 'cancelled') return 'Cancelled';
            return status;
        }

        function displayPaymentStatus(order) {
            const status = String(order.payment_status || '').toLowerCase();
            if (status === 'pending' && requiresPaymentProof(order.payment_method)) {
                return order.payment_proof_path ? 'Proof Under Review' : 'Awaiting Proof';
            }
            if (status === 'pending' && String(order.payment_method || '').toLowerCase() === 'cod') {
                return 'Pending Collection';
            }
            if (status === 'failed') return 'Payment Rejected';
            return status;
        }

        function resolveAssetUrl(path) {
            if (!path) return '';
            if (/^(https?:)?\//i.test(path)) return path;
            return `../${String(path).replace(/^\/+/, '')}`;
        }

        function getPaymentMethodLabel(method) {
            const normalized = String(method || '').toLowerCase();
            const labels = {
                cod: 'Cash on Delivery',
                bpi: 'BPI Bank Transfer',
                bank_transfer: 'BPI Bank Transfer',
                gcash: 'GCash',
                paymaya: 'PayMaya (Legacy)'
            };
            return labels[normalized] || normalized.replace(/_/g, ' ');
        }

        async function loadOrders() {
            const loading = document.getElementById('loadingState');
            const empty = document.getElementById('emptyState');
            const list = document.getElementById('ordersList');

            try {
                const response = await fetch('../backend/get_user_orders.php', { cache: 'no-store' });
                const data = await response.json();

                loading.classList.add('hidden');

                if (!data.success) {
                    empty.classList.remove('hidden');
                    return;
                }

                const orders = data.orders || [];
                if (!orders.length) {
                    empty.classList.remove('hidden');
                    return;
                }

                list.innerHTML = orders.map((order) => `
                    <article class="bg-white rounded-xl shadow-lg p-5">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                            <div>
                                <h2 class="text-lg font-bold text-[#08415c]">#${escapeHtml(order.order_number)}</h2>
                                <p class="text-xs text-gray-500">Placed: ${formatDate(order.created_at)}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="status-badge ${orderStatusClass(order.order_status)}">${escapeHtml(displayOrderStatus(order))}</span>
                                <span class="status-badge ${paymentStatusClass(order.payment_status)}">${escapeHtml(displayPaymentStatus(order))}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-sm">
                            <div><p class="text-gray-500">Items</p><p class="font-semibold">${order.total_items || 0}</p></div>
                            <div><p class="text-gray-500">Qty</p><p class="font-semibold">${order.total_quantity || 0}</p></div>
                            <div><p class="text-gray-500">Delivery</p><p class="font-semibold">${order.delivery_method === 'pickup' ? 'Store Pickup' : 'Shipping'}</p></div>
                            <div><p class="text-gray-500">Payment Method</p><p class="font-semibold">${escapeHtml(getPaymentMethodLabel(order.payment_method || ''))}</p></div>
                            <div><p class="text-gray-500">Shipping Fee</p><p class="font-semibold">PHP ${formatPeso(order.shipping_fee)}</p></div>
                            <div><p class="text-gray-500">Total</p><p class="font-bold text-[#08415c]">PHP ${formatPeso(order.total_amount)}</p></div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            ${order.payment_reference ? `<p class="text-gray-700"><strong>Payment Reference:</strong> ${escapeHtml(order.payment_reference)}</p>` : ''}
                            ${order.pickup_date ? `<p class="text-gray-700"><strong>Pickup Schedule:</strong> ${escapeHtml(order.pickup_date)}${order.pickup_time ? `, ${escapeHtml(order.pickup_time)}` : ''}</p>` : ''}
                            ${order.tracking_number ? `<p class="text-blue-700"><i class="fas fa-truck mr-2"></i>Tracking: ${escapeHtml(order.tracking_number)}</p>` : ''}
                            ${order.payment_proof_path ? `<p><a href="${escapeHtml(resolveAssetUrl(order.payment_proof_path))}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline"><i class="fas fa-file-upload mr-2"></i>View uploaded proof of payment</a></p>` : (requiresPaymentProof(order.payment_method) ? `<p class="text-amber-700"><i class="fas fa-clock mr-2"></i>Proof of payment is still required before admin confirmation.</p>` : '')}
                            ${order.receipt_path ? `<p><a href="${escapeHtml(resolveAssetUrl(order.receipt_path))}" target="_blank" rel="noopener noreferrer" class="text-green-700 hover:underline"><i class="fas fa-file-invoice mr-2"></i>View attached receipt</a></p>` : ''}
                            ${order.payment_review_notes ? `<p class="text-gray-700"><strong>Payment Notes:</strong> ${escapeHtml(order.payment_review_notes)}</p>` : ''}
                            ${order.cancel_reason ? `<p class="text-red-700"><strong>Cancellation Reason:</strong> ${escapeHtml(order.cancel_reason)}</p>` : ''}
                        </div>

                        ${order.notes ? `<p class="text-sm text-gray-600 mt-3"><strong>Order Notes:</strong> ${escapeHtml(order.notes)}</p>` : ''}
                    </article>
                `).join('');

                list.classList.remove('hidden');
            } catch (e) {
                loading.classList.add('hidden');
                empty.classList.remove('hidden');
            }
        }

        document.addEventListener('DOMContentLoaded', loadOrders);
    </script>
<!-- Chat Bubble Component -->
<?php include 'components/chat_bubble.php'; ?>
</body>
</html>

