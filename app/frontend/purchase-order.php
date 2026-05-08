<?php
/**
 * Purchase Order Management Frontend
 * File: d:\XAMPP\htdocs\pages\MinC_Project\app\frontend\purchase-order.php
 */

include_once '../../backend/auth.php';
include_once '../../database/connect_database.php';
require_once '../../backend/purchase-order/purchase_order_schema.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    header('Location: ../../index.php?error=' . $validation['reason']);
    exit;
}

// Check if user has permission
if (!isITStaff() && !isOwner()) {
    $_SESSION['error_message'] = 'Access denied. Only IT Personnel and Owner can manage purchase orders.';
    header('Location: dashboard.php');
    exit;
}

// Get current user data
$user_data = [
    'id' => $_SESSION['user_id'] ?? null,
    'name' => $_SESSION['full_name'] ?? $_SESSION['fname'] ?? 'Guest User',
    'user_type' => $_SESSION['user_type_name'] ?? 'User'
];

// Set custom title
$custom_title = 'Purchase Orders - MinC Project';

// Ensure table exists for environments where schema is not yet applied.
$po_table_ready = true;
$po_table_error = null;
try {
    ensurePurchaseOrdersTable($pdo);
} catch (PDOException $e) {
    $po_table_ready = false;
    $po_table_error = 'Purchase order table is unavailable: ' . $e->getMessage();
}

// Active suppliers list (used by PO create flow)
try {
    $supplier_query = "
        SELECT supplier_id, supplier_name
        FROM suppliers
        WHERE status = 'active'
        ORDER BY supplier_name ASC
    ";
    $supplier_result = $pdo->query($supplier_query);
    $active_suppliers = $supplier_result ? $supplier_result->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $active_suppliers = [];
}

// Fetch purchase orders
try {
    if (!$po_table_ready) {
        throw new PDOException('purchase_orders table not ready');
    }

    $po_query = "
        SELECT
            po.po_id,
            po.po_number,
            po.supplier_id,
            s.supplier_name,
            po.order_date,
            po.expected_delivery_date,
            po.total_amount,
            po.status,
            po.created_at
        FROM purchase_orders po
        LEFT JOIN suppliers s ON s.supplier_id = po.supplier_id
        ORDER BY po.order_date DESC, po.po_id DESC
        LIMIT 100
    ";
    $po_result = $pdo->query($po_query);
    $purchase_orders = $po_result ? $po_result->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $purchase_orders = [];
}

// Get statistics
$total_orders = count($purchase_orders);
$pending_orders = count(array_filter($purchase_orders, static function ($o) {
    return strtolower((string)($o['status'] ?? '')) === 'pending';
}));
$completed_orders = count(array_filter($purchase_orders, static function ($o) {
    return strtolower((string)($o['status'] ?? '')) === 'completed';
}));
$total_po_amount = array_sum(array_column($purchase_orders, 'total_amount'));
$has_active_suppliers = !empty($active_suppliers);

// Custom styles
$additional_styles = '
<style>
    .po-status-pending {
        background-color: #FEF3C7;
        color: #92400E;
    }

    .po-status-completed {
        background-color: #D1FAE5;
        color: #065F46;
    }

    .po-status-cancelled {
        background-color: #FEE2E2;
        color: #991B1B;
    }

    .po-row:hover {
        background-color: rgba(8, 65, 92, 0.05);
    }

    .po-header {
        background: linear-gradient(135deg, rgba(8, 65, 92, 0.1) 0%, rgba(10, 82, 115, 0.1) 100%);
    }
</style>';

// Purchase order content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-[#08415c] mb-2 flex items-center">
                <i class="fas fa-file-invoice-dollar text-teal-600 mr-3"></i>
                Purchase Orders
            </h2>
            <p class="text-gray-600">
                Manage supplier purchase orders and deliveries
            </p>
        </div>
        <button onclick="openCreatePOModal()" class="px-4 py-2 bg-gradient-to-r from-[#08415c] to-[#0a5273] text-white rounded-lg hover:shadow-lg transition flex items-center">
            <i class="fas fa-plus mr-2"></i>Create PO
        </button>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Orders</p>
                <p class="text-2xl font-bold text-[#08415c]"><?= $total_orders ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-[#08415c] to-[#0a5273] text-white rounded-lg">
                <i class="fas fa-clipboard-list text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Pending</p>
                <p class="text-2xl font-bold text-amber-600"><?= $pending_orders ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-amber-500 to-amber-700 text-white rounded-lg">
                <i class="fas fa-hourglass-half text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Completed</p>
                <p class="text-2xl font-bold text-green-600"><?= $completed_orders ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-green-500 to-green-700 text-white rounded-lg">
                <i class="fas fa-check-circle text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Amount</p>
                <p class="text-2xl font-bold text-[#08415c]">&#8369;<?= number_format($total_po_amount, 2) ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-purple-500 to-purple-700 text-white rounded-lg">
                <i class="fas fa-money-bill-wave text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Orders Table -->
<div class="professional-card rounded-xl p-6">
    <h3 class="text-lg font-bold text-[#08415c] mb-4">Purchase Orders List</h3>

    <?php if (!$has_active_suppliers): ?>
        <div class="mb-4 p-3 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-sm">
            No active suppliers found. Add or activate a supplier first before creating purchase orders.
        </div>
    <?php endif; ?>

    <?php if ($po_table_error !== null): ?>
        <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 border border-red-200 text-sm">
            <?= htmlspecialchars($po_table_error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($purchase_orders)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="po-header">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">PO Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Order Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Expected Delivery</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($purchase_orders as $po): ?>
                        <?php $status = strtolower((string)($po['status'] ?? 'pending')); ?>
                        <tr class="po-row hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm font-medium text-[#08415c]"><?= htmlspecialchars((string)$po['po_number']) ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm text-gray-700 font-medium"><?= htmlspecialchars((string)($po['supplier_name'] ?: 'Unknown Supplier')) ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm text-gray-600"><?= strtotime((string)$po['order_date']) ? date('M d, Y', strtotime((string)$po['order_date'])) : 'N/A' ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm text-gray-600"><?= strtotime((string)$po['expected_delivery_date']) ? date('M d, Y', strtotime((string)$po['expected_delivery_date'])) : 'N/A' ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm font-semibold text-gray-900">&#8369;<?= number_format((float)$po['total_amount'], 2) ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    <?php
                                    if ($status === 'pending') {
                                        echo 'po-status-pending';
                                    } elseif ($status === 'completed') {
                                        echo 'po-status-completed';
                                    } else {
                                        echo 'po-status-cancelled';
                                    }
                                    ?>">
                                    <?= ucfirst($status) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="text-gray-400">-</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center py-12">
            <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
            <p class="text-gray-500">No purchase orders found</p>
            <button onclick="openCreatePOModal()" class="mt-4 px-4 py-2 bg-[#08415c] text-white rounded-lg hover:bg-[#0a5273] transition">
                Create First PO
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Create PO Modal -->
<div id="createPOModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
    <div class="bg-white rounded-xl p-6 w-[96vw] max-w-3xl max-h-[88vh] overflow-y-auto shadow-2xl">
        <h3 class="text-lg font-bold text-[#08415c] mb-4">Create Purchase Order</h3>
        <form id="createPOForm" class="space-y-4" action="../../backend/purchase-order/create_purchase_order.php" method="POST">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier *</label>
                <select name="supplier_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" required>
                    <option value="">Select a supplier...</option>
                    <?php foreach ($active_suppliers as $supplier): ?>
                        <option value="<?= (int)$supplier['supplier_id'] ?>"><?= htmlspecialchars((string)$supplier['supplier_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Order Date *</label>
                <input type="date" id="po_order_date" name="order_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Expected Delivery Date *</label>
                <input type="date" id="po_expected_delivery_date" name="expected_delivery_date" value="<?= htmlspecialchars(date('Y-m-d', strtotime('+7 days'))) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount *</label>
                <input type="number" name="total_amount" step="0.01" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="3" maxlength="2000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" placeholder="Optional purchase order notes"></textarea>
            </div>
            <div class="flex space-x-3 justify-end mt-6">
                <button type="button" onclick="closeCreatePOModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-[#08415c] text-white rounded-lg hover:bg-[#0a5273] transition">
                    Create PO
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const HAS_ACTIVE_SUPPLIERS = <?= $has_active_suppliers ? 'true' : 'false' ?>;

    function openCreatePOModal() {
        if (!HAS_ACTIVE_SUPPLIERS) {
            showAlertModal('No active suppliers available. Add or activate a supplier first.', 'warning', 'Suppliers Required');
            return;
        }

        const orderDateInput = document.getElementById('po_order_date');
        const deliveryDateInput = document.getElementById('po_expected_delivery_date');

        if (orderDateInput) {
            orderDateInput.min = '<?= htmlspecialchars(date('Y-m-d')) ?>';
            if (!orderDateInput.value) {
                orderDateInput.value = '<?= htmlspecialchars(date('Y-m-d')) ?>';
            }
        }

        if (deliveryDateInput && orderDateInput) {
            deliveryDateInput.min = orderDateInput.value || '<?= htmlspecialchars(date('Y-m-d')) ?>';
            if (!deliveryDateInput.value || deliveryDateInput.value < deliveryDateInput.min) {
                deliveryDateInput.value = '<?= htmlspecialchars(date('Y-m-d', strtotime('+7 days'))) ?>';
            }
        }

        document.getElementById('createPOModal').classList.remove('hidden');
    }

    function closeCreatePOModal() {
        document.getElementById('createPOModal').classList.add('hidden');
    }

    document.getElementById('po_order_date')?.addEventListener('change', function () {
        const deliveryDateInput = document.getElementById('po_expected_delivery_date');
        if (!deliveryDateInput) {
            return;
        }
        deliveryDateInput.min = this.value;
        if (deliveryDateInput.value < this.value) {
            deliveryDateInput.value = this.value;
        }
    });
</script>

<?php
$purchase_order_content = ob_get_clean();
$content = $purchase_order_content;
$current_page = 'purchase-order';
include 'app.php';
?>
