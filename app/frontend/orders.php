<?php
/**
 * Orders Management Frontend
 */

include_once '../../backend/auth.php';
include_once '../../database/connect_database.php';
include_once '../../backend/order-management/order_workflow_helper.php';

$validation = validateSession();
if (!$validation['valid']) {
    header('Location: ../../index.php?error=' . $validation['reason']);
    exit;
}

if (!isManagementLevel()) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to access this page.';
    header('Location: dashboard.php');
    exit;
}

$user_data =[
    'id' => $_SESSION['user_id'] ?? null,
    'name' => $_SESSION['full_name'] ?? $_SESSION['fname'] ?? 'Guest User',
    'user_type' => $_SESSION['user_type_name'] ?? 'User',
    'user_level_id' => $_SESSION['user_level_id'] ?? null
];

$custom_title = 'Order Management - MinC Project';

$user = [
    'full_name' => $user_data['name'],
    'user_type' => $user_data['user_type'],
    'is_logged_in' => isset($user_data['id'])
];

try {
    $orders_query = "
        SELECT 
            o.order_id, o.order_number, o.tracking_number, o.customer_id, o.customer_phone,
            c.first_name, c.last_name, CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.email as customer_email, c.customer_type, o.subtotal, o.shipping_fee, o.total_amount,
            o.payment_method, o.payment_status, o.order_status,
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'delivery_method') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'payment_reference') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'payment_proof_path') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'payment_review_notes') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'receipt_path') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'cancel_reason') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'pickup_date') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'pickup_time') . ",
            o.shipping_address, o.shipping_city, o.shipping_province, o.shipping_postal_code,
            o.delivery_date, o.notes, o.created_at, o.updated_at,
            COUNT(oi.order_item_id) as total_items, SUM(oi.quantity) as total_quantity
        FROM orders o
        INNER JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
    ";
    $orders_result = $pdo->query($orders_query);
    $orders = $orders_result->fetchAll(PDO::FETCH_ASSOC);

    $total_orders = count($orders);
    $total_revenue = array_sum(array_map(static function ($order) {
        $status = strtolower((string)($order['order_status'] ?? ''));
        if ($status === 'cancelled') return 0;
        return (float)($order['total_amount'] ?? 0);
    }, $orders));
    $pending_orders = count(array_filter($orders, function($o) { return $o['order_status'] === 'pending'; }));
    $completed_orders = count(array_filter($orders, function($o) { return $o['order_status'] === 'delivered'; }));

} catch (Exception $e) {
    $orders =[];
    $total_orders = 0; $total_revenue = 0; $pending_orders = 0; $completed_orders = 0;
}

$additional_styles = '
.order-status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; text-transform: uppercase; }
.status-pending { background-color: #fef3c7; color: #92400e; }
.status-confirmed, .status-processing { background-color: #e0e7ff; color: #4338ca; }
.status-shipped { background-color: #ddd6fe; color: #6b21a8; }
.status-delivered { background-color: #dcfce7; color: #166534; }
.status-cancelled { background-color: #fef2f2; color: #991b1b; }
.payment-status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; text-transform: uppercase; }
.payment-pending { background-color: #fef3c7; color: #92400e; }
.payment-paid { background-color: #dcfce7; color: #166534; }
.payment-failed { background-color: #fef2f2; color: #991b1b; }
.payment-refunded { background-color: #f3f4f6; color: #6b7280; }
.payment-method-badge { padding: 3px 10px; border-radius: 15px; font-size: 11px; font-weight: 500; background-color: #f3f4f6; color: #374151; }
.table-hover tbody tr:hover { background-color: rgba(249, 250, 251, 0.8); }
.desktop-table { width: 100%; overflow-x: auto; }
.desktop-table table { width: 100%; min-width: 100%; table-layout: auto; }
.professional-card.table-container { padding: 0; overflow: hidden; }
.professional-card.table-container .desktop-table { margin: 0; }
@media (max-width: 768px) { .mobile-card { display: block !important; } .desktop-table { display: none !important; } }
@media (min-width: 769px) { .mobile-card { display: none !important; } .desktop-table { display: block !important; width: 100%; } }
';

ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center"><i class="fas fa-shopping-cart text-green-600 mr-3"></i>Order Management</h2>
            <p class="text-gray-600">View and track all customer orders and their status.</p>
        </div>
        <div class="flex items-center space-x-3">
            <button type="button" data-export-orders class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium flex items-center transition-colors duration-200">
                <i class="fas fa-download mr-2"></i>Export Data
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="professional-card rounded-xl p-6"><p class="text-sm font-medium text-gray-600 mb-1">Total Orders</p><p class="text-3xl font-bold text-gray-900"><?php echo $total_orders; ?></p></div>
    <div class="professional-card rounded-xl p-6"><p class="text-sm font-medium text-gray-600 mb-1">Total Revenue</p><p class="text-3xl font-bold text-green-600">₱<?php echo number_format($total_revenue, 2); ?></p></div>
    <div class="professional-card rounded-xl p-6"><p class="text-sm font-medium text-gray-600 mb-1">Pending Orders</p><p class="text-3xl font-bold text-yellow-600"><?php echo $pending_orders; ?></p></div>
    <div class="professional-card rounded-xl p-6"><p class="text-sm font-medium text-gray-600 mb-1">Completed</p><p class="text-3xl font-bold text-purple-600"><?php echo $completed_orders; ?></p></div>
</div>

<!-- Filters -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label for="search_orders" class="block text-sm font-medium text-gray-700 mb-2">Search Orders</label>
            <input type="text" id="search_orders" placeholder="Search by order number or customer..." class="w-full px-4 py-3 border border-gray-300 rounded-lg">
        </div>
        <div>
            <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-2">Order Status</label>
            <select id="status_filter" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="processing">Processing</option>
                <option value="shipped">Shipped</option>
                <option value="delivered">Delivered</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div>
            <label for="payment_status_filter" class="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
            <select id="payment_status_filter" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                <option value="">All Payment Status</option>
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
                <option value="failed">Failed</option>
                <option value="refunded">Refunded</option>
            </select>
        </div>
        <div>
            <label for="payment_method_filter" class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
            <select id="payment_method_filter" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                <option value="">All Methods</option>
                <option value="cod">Cash on Delivery</option>
                <option value="bpi">BPI Bank Transfer</option>
                <option value="gcash">GCash</option>
            </select>
        </div>
    </div>
</div>

<!-- Orders Table -->
<div class="professional-card table-container rounded-xl overflow-hidden animate-fadeIn">
    <div class="desktop-table">
        <table class="w-full table-hover">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Order Details</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr class="order-row" 
                            data-order-id="<?php echo (int)$order['order_id']; ?>"
                            data-order-number="<?php echo strtolower($order['order_number']); ?>" 
                            data-customer="<?php echo strtolower($order['customer_name']); ?>"
                            data-status="<?php echo $order['order_status']; ?>"
                            data-payment-status="<?php echo $order['payment_status']; ?>"
                            data-payment-method="<?php echo in_array($order['payment_method'],['bpi', 'bank_transfer'], true) ? 'bpi' : $order['payment_method']; ?>">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo $order['total_items']; ?> item(s)</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-gray-900">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="payment-method-badge"><?php echo ucfirst($order['payment_method']); ?></span>
                                <span class="payment-status-badge payment-<?php echo $order['payment_status']; ?> block mt-1 w-fit"><?php echo ucfirst($order['payment_status']); ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="order-status-badge status-<?php echo $order['order_status']; ?>"><?php echo ucfirst($order['order_status']); ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium">
                                <button type="button" data-order-view="<?php echo (int)$order['order_id']; ?>" class="text-blue-600 hover:text-blue-900 p-2 rounded-lg hover:bg-blue-50 transition-colors">
                                    <i class="fas fa-edit"></i> Update
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile View -->
    <div class="mobile-card p-4 space-y-4">
        <?php foreach ($orders as $order): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-4 order-card" 
                 data-order-number="<?php echo strtolower($order['order_number']); ?>" 
                 data-customer="<?php echo strtolower($order['customer_name']); ?>"
                 data-status="<?php echo $order['order_status']; ?>"
                 data-payment-status="<?php echo $order['payment_status']; ?>"
                 data-payment-method="<?php echo in_array($order['payment_method'],['bpi', 'bank_transfer'], true) ? 'bpi' : $order['payment_method']; ?>">
                <div class="flex justify-between mb-3">
                    <h4 class="font-medium text-gray-900">#<?php echo htmlspecialchars($order['order_number']); ?></h4>
                    <span class="order-status-badge status-<?php echo $order['order_status']; ?>"><?php echo ucfirst($order['order_status']); ?></span>
                </div>
                <div class="flex justify-end">
                    <button type="button" data-order-view="<?php echo (int)$order['order_id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg">
                        <i class="fas fa-edit mr-2"></i>Update
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderDetailsModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center p-4" style="backdrop-filter: blur(10px);">
    <div class="professional-card rounded-xl max-w-5xl w-full max-h-screen overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Order Management</h3>
                <button type="button" data-close-order-details class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div id="orderDetailsContent"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    initializeFilters();
});

function initializeFilters() {
    const searchInput = document.getElementById("search_orders");
    const statusFilter = document.getElementById("status_filter");
    const paymentStatusFilter = document.getElementById("payment_status_filter");
    const paymentMethodFilter = document.getElementById("payment_method_filter");

    function applyFilters() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
        const selectedStatus = statusFilter ? statusFilter.value : "";
        const selectedPaymentStatus = paymentStatusFilter ? paymentStatusFilter.value : "";
        const selectedPaymentMethod = paymentMethodFilter ? paymentMethodFilter.value : "";
        
        document.querySelectorAll(".order-row, .order-card").forEach(element => {
            const orderNumber = element.getAttribute("data-order-number") || "";
            const customer = element.getAttribute("data-customer") || "";
            const status = element.getAttribute("data-status") || "";
            const paymentStatus = element.getAttribute("data-payment-status") || "";
            const paymentMethod = element.getAttribute("data-payment-method") || "";

            const matchesSearch = searchTerm === "" || orderNumber.includes(searchTerm) || customer.includes(searchTerm);
            const matchesStatus = selectedStatus === "" || status === selectedStatus;
            const matchesPaymentStatus = selectedPaymentStatus === "" || paymentStatus === selectedPaymentStatus;
            const matchesPaymentMethod = selectedPaymentMethod === "" || paymentMethod === selectedPaymentMethod;

            element.style.display = (matchesSearch && matchesStatus && matchesPaymentStatus && matchesPaymentMethod) ? "" : "none";
        });
    }

    if (searchInput) searchInput.addEventListener("input", applyFilters);
    if (statusFilter) statusFilter.addEventListener("change", applyFilters);
    if (paymentStatusFilter) paymentStatusFilter.addEventListener("change", applyFilters);
    if (paymentMethodFilter) paymentMethodFilter.addEventListener("change", applyFilters);
}

function viewOrderDetails(orderId) {
    const modal = document.getElementById("orderDetailsModal");
    const content = document.getElementById("orderDetailsContent");
    
    modal.classList.remove("hidden");
    document.body.style.overflow = 'hidden';
    content.innerHTML = `<div class="text-center py-12"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#08415c] mx-auto"></div></div>`;
    
    fetch(`../../backend/order-management/get_order.php?id=${encodeURIComponent(orderId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) displayOrderDetails(data.order, data.items);
            else content.innerHTML = `<p class="text-center text-red-500">${escapeHtml(data.message)}</p>`;
        });
}

function displayOrderDetails(order, items) {
    const content = document.getElementById("orderDetailsContent");
    
    let itemsHtml = items.map(item => `
        <div class="bg-gray-50 rounded-lg p-4 mb-2 flex justify-between">
            <div>
                <p class="font-medium text-gray-900">${escapeHtml(item.product_name)}</p>
                <span class="text-sm text-gray-600">Qty: ${item.quantity}</span>
            </div>
            <p class="font-bold text-gray-900">₱${parseFloat(item.subtotal).toLocaleString('en-PH', {minimumFractionDigits:2})}</p>
        </div>
    `).join('');
    
    content.innerHTML = `
        <div class="space-y-6">
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h5 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-edit mr-2 text-[#08415c]"></i>Update Order Status
                </h5>
                <form onsubmit="submitOrderStatusUpdate(event, ${order.order_id})">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Order Status</label>
                            <select id="edit_order_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c]">
                                <option value="pending" ${order.order_status === 'pending' ? 'selected' : ''}>Pending (Awaiting Review)</option>
                                <option value="processing" ${order.order_status === 'processing' || order.order_status === 'confirmed' ? 'selected' : ''}>Processing (Preparing Items)</option>
                                <option value="shipped" ${order.order_status === 'shipped' ? 'selected' : ''}>Shipped / Ready for Pickup</option>
                                <option value="delivered" ${order.order_status === 'delivered' ? 'selected' : ''}>Delivered / Completed</option>
                                <option value="cancelled" ${order.order_status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Payment Status</label>
                            <select id="edit_payment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c]">
                                <option value="pending" ${order.payment_status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="paid" ${order.payment_status === 'paid' ? 'selected' : ''}>Paid</option>
                                <option value="failed" ${order.payment_status === 'failed' ? 'selected' : ''}>Failed / Rejected</option>
                                <option value="refunded" ${order.payment_status === 'refunded' ? 'selected' : ''}>Refunded</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tracking Number (if shipped)</label>
                            <input type="text" id="edit_tracking_number" value="${escapeHtml(order.tracking_number || '')}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c]" placeholder="Enter tracking number...">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Admin Note / Cancellation Reason</label>
                            <input type="text" id="edit_order_note" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c]" placeholder="Optional note for this update...">
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 border-t pt-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="edit_send_email" class="w-4 h-4 text-[#08415c] rounded" checked>
                            <label for="edit_send_email" class="ml-2 text-sm text-gray-700">Send email notification to customer</label>
                        </div>
                        <button type="submit" class="bg-[#08415c] text-white px-6 py-2 rounded-lg font-semibold hover:bg-[#0a5273] transition">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h5 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-user mr-2 text-[#08415c]"></i>Customer Information</h5>
                    <p class="text-sm"><strong>Name:</strong> ${escapeHtml(order.customer_name)}</p>
                    <p class="text-sm"><strong>Email:</strong> ${escapeHtml(order.customer_email)}</p>
                    <p class="text-sm"><strong>Phone:</strong> ${escapeHtml(order.customer_phone)}</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h5 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-map-marker-alt mr-2 text-[#08415c]"></i>Delivery Information</h5>
                    <p class="text-sm"><strong>Method:</strong> ${order.delivery_method === 'pickup' ? 'Store Pickup' : 'Shipping'}</p>
                    <p class="text-sm"><strong>Address:</strong> ${escapeHtml(order.shipping_address)}</p>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h5 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-credit-card mr-2 text-[#08415c]"></i>Payment & Documents</h5>
                <p class="text-sm"><strong>Method:</strong> ${escapeHtml(order.payment_method)}</p>
                ${order.payment_reference ? `<p class="text-sm"><strong>Reference:</strong> ${escapeHtml(order.payment_reference)}</p>` : ''}
                ${order.payment_proof_path ? `<a href="../../${escapeHtml(order.payment_proof_path)}" target="_blank" class="text-sm text-blue-600 underline">View Uploaded Proof of Payment</a>` : ''}
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h5 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-box mr-2 text-[#08415c]"></i>Order Items (${items.length})</h5>
                ${itemsHtml}
                <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between items-center">
                    <span class="text-lg font-semibold">Total Amount</span>
                    <span class="text-2xl font-bold text-green-600">₱${parseFloat(order.total_amount).toLocaleString('en-PH', {minimumFractionDigits:2})}</span>
                </div>
            </div>
        </div>
    `;
}

async function submitOrderStatusUpdate(event, orderId) {
    event.preventDefault();

    const orderStatus = document.getElementById('edit_order_status').value;
    const paymentStatus = document.getElementById('edit_payment_status').value;
    const trackingNumber = document.getElementById('edit_tracking_number').value.trim();
    const reason = document.getElementById('edit_order_note').value.trim();
    const sendEmail = document.getElementById('edit_send_email').checked;

    if (!(await showConfirmModal('Are you sure you want to update this order?', 'Confirm Update'))) return;

    try {
        const response = await fetch('../../backend/order-management/update_order.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: orderId,
                order_status: orderStatus,
                payment_status: paymentStatus,
                tracking_number: trackingNumber,
                reason: reason,
                send_email: sendEmail
            })
        });

        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Failed to update order');

        if (typeof window.showAppToast === 'function') window.showAppToast(data.message || 'Order updated', 'success');
        setTimeout(() => window.location.reload(), 700);
    } catch (error) {
        if (typeof window.showAppToast === 'function') window.showAppToast(error.message || 'Failed to update order', 'error');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

function closeOrderDetails() {
    const modal = document.getElementById("orderDetailsModal");
    if (modal) {
        modal.classList.add("hidden");
        document.body.style.overflow = '';
    }
}

document.addEventListener('click', function(event) {
    const viewTrigger = event.target.closest('[data-order-view]');
    if (viewTrigger) {
        event.preventDefault();
        viewOrderDetails(Number(viewTrigger.getAttribute('data-order-view')));
    }
    const closeTrigger = event.target.closest('[data-close-order-details]');
    if (closeTrigger) {
        event.preventDefault();
        closeOrderDetails();
    }
    const exportTrigger = event.target.closest('[data-export-orders]');
    if (exportTrigger) {
        event.preventDefault();
        window.location.href = '../../backend/order-management/export_orders.php';
    }
    const modal = document.getElementById("orderDetailsModal");
    if (event.target === modal) closeOrderDetails();
});
</script>
<?php
$order_management_content = ob_get_clean();
$content = $order_management_content;
include 'app.php';
?>
