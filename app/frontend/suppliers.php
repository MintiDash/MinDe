<?php
/**
 * Suppliers Management Frontend
 */

include_once '../../backend/auth.php';
include_once '../../database/connect_database.php';
require_once '../../backend/suppliers/province_options.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    header('Location: ../../index.php?error=' . $validation['reason']);
    exit;
}

// Check if user has permission
if (!isITStaff() && !isOwner()) {
    $_SESSION['error_message'] = 'Access denied. Only admin and employee accounts can manage suppliers.';
    header('Location: dashboard.php');
    exit;
}

$province_options = getSupplierProvinceOptions();

// Set custom title
$custom_title = 'Suppliers Management - MinC Project';

try {
    $suppliers_query = "
        SELECT 
            supplier_id,
            supplier_name,
            contact_person,
            email,
            phone,
            address,
            city,
            province,
            status,
            created_at
        FROM suppliers
        ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END, supplier_name ASC
    ";
    $suppliers_result = $pdo->query($suppliers_query);
    $suppliers = $suppliers_result ? $suppliers_result->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $suppliers = [];
}

$total_suppliers = count($suppliers);
$active_suppliers = count(array_filter($suppliers, static function ($supplier) {
    return strtolower((string)($supplier['status'] ?? '')) === 'active';
}));
$unique_cities = count(array_unique(array_filter(array_map(static function ($supplier) {
    return trim((string)($supplier['city'] ?? ''));
}, $suppliers))));

$additional_styles = '
<style>
    .supplier-row:hover {
        background-color: rgba(8, 65, 92, 0.05);
    }

    .action-btn {
        transition: all 0.2s ease;
    }

    .action-btn:hover {
        transform: scale(1.08);
    }

    .supplier-modal {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
</style>';

ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-[#08415c] mb-2 flex items-center">
                <i class="fas fa-truck text-teal-600 mr-3"></i>
                Suppliers Management
            </h2>
            <p class="text-gray-600">
                Manage supplier information and contacts
            </p>
        </div>
        <button type="button" onclick="openAddSupplierModal()" class="px-4 py-2 bg-gradient-to-r from-[#08415c] to-[#0a5273] text-white rounded-lg hover:shadow-lg transition flex items-center">
            <i class="fas fa-plus mr-2"></i>Add Supplier
        </button>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Suppliers</p>
                <p class="text-2xl font-bold text-[#08415c]"><?php echo $total_suppliers; ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-[#08415c] to-[#0a5273] text-white rounded-lg">
                <i class="fas fa-people-carry text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Active Suppliers</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $active_suppliers; ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-green-500 to-green-700 text-white rounded-lg">
                <i class="fas fa-check-circle text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Suppliers in City</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $unique_cities; ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-700 text-white rounded-lg">
                <i class="fas fa-map-marker-alt text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Suppliers List -->
<div class="professional-card rounded-xl p-6">
    <h3 class="text-lg font-bold text-[#08415c] mb-4">Supplier Directory</h3>

    <?php if (!empty($suppliers)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Supplier Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Contact Person</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($suppliers as $supplier): ?>
                        <?php
                        $status = strtolower((string)($supplier['status'] ?? 'inactive'));
                        $is_active = $status === 'active';
                        $address_text = trim((string)($supplier['address'] ?? ''));
                        $city_text = trim((string)($supplier['city'] ?? ''));
                        $province_text = trim((string)($supplier['province'] ?? ''));
                        $location_parts = array_filter([$address_text, $city_text, $province_text], static function ($value) {
                            return $value !== '';
                        });
                        $location_display = !empty($location_parts) ? implode(', ', $location_parts) : 'N/A';
                        ?>
                        <tr class="supplier-row transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($supplier['supplier_name']); ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($supplier['contact_person'] ?: 'N/A'); ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($supplier['email'] ?: 'N/A'); ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($supplier['phone'] ?: 'N/A'); ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($location_display); ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="action-btn text-blue-600 hover:text-blue-900"
                                        title="Edit Supplier"
                                        onclick="openEditSupplierModal(this)"
                                        data-id="<?php echo (int)$supplier['supplier_id']; ?>"
                                        data-name="<?php echo htmlspecialchars((string)$supplier['supplier_name'], ENT_QUOTES); ?>"
                                        data-contact="<?php echo htmlspecialchars((string)$supplier['contact_person'], ENT_QUOTES); ?>"
                                        data-email="<?php echo htmlspecialchars((string)$supplier['email'], ENT_QUOTES); ?>"
                                        data-phone="<?php echo htmlspecialchars((string)$supplier['phone'], ENT_QUOTES); ?>"
                                        data-address="<?php echo htmlspecialchars((string)$supplier['address'], ENT_QUOTES); ?>"
                                        data-city="<?php echo htmlspecialchars((string)$supplier['city'], ENT_QUOTES); ?>"
                                        data-province="<?php echo htmlspecialchars((string)$supplier['province'], ENT_QUOTES); ?>"
                                    >
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <form
                                        method="POST"
                                        action="../../backend/suppliers/toggle_supplier_status.php"
                                        class="inline supplier-action-form"
                                        data-confirm-title="<?php echo $is_active ? 'Deactivate Supplier' : 'Activate Supplier'; ?>"
                                        data-confirm-message="<?php echo $is_active ? 'Are you sure you want to deactivate this supplier?' : 'Are you sure you want to activate this supplier?'; ?>"
                                    >
                                        <input type="hidden" name="supplier_id" value="<?php echo (int)$supplier['supplier_id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo htmlspecialchars($status); ?>">
                                        <button type="submit" class="action-btn <?php echo $is_active ? 'text-amber-600 hover:text-amber-900' : 'text-green-600 hover:text-green-900'; ?>" title="<?php echo $is_active ? 'Deactivate' : 'Activate'; ?> Supplier">
                                            <i class="fas fa-<?php echo $is_active ? 'ban' : 'check-circle'; ?>"></i>
                                        </button>
                                    </form>

                                    <form
                                        method="POST"
                                        action="../../backend/suppliers/delete_supplier.php"
                                        class="inline supplier-action-form"
                                        data-confirm-title="Delete Supplier"
                                        data-confirm-message="Delete this supplier? This action cannot be undone."
                                    >
                                        <input type="hidden" name="supplier_id" value="<?php echo (int)$supplier['supplier_id']; ?>">
                                        <button type="submit" class="action-btn text-red-600 hover:text-red-900" title="Delete Supplier">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center py-12">
            <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
            <p class="text-gray-500">No suppliers added yet</p>
            <button type="button" onclick="openAddSupplierModal()" class="mt-4 px-4 py-2 bg-[#08415c] text-white rounded-lg hover:bg-[#0a5273] transition">
                Add First Supplier
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Add Supplier Modal -->
<div id="addSupplierModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 supplier-modal p-4">
    <div class="bg-white rounded-xl p-6 max-w-lg w-full max-h-screen overflow-y-auto">
        <h3 class="text-lg font-bold text-[#08415c] mb-4">Add New Supplier</h3>
        <form id="addSupplierForm" class="space-y-4" action="../../backend/suppliers/add_supplier.php" method="POST">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier Name *</label>
                <input type="text" name="supplier_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" minlength="2" maxlength="255" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                <input type="text" name="contact_person" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" maxlength="255">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" maxlength="255">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="tel" name="phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" maxlength="50">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <input type="text" name="address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" maxlength="255">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                    <input type="text" name="city" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" maxlength="100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                    <select name="province" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" required>
                        <option value="">Select Province</option>
                        <?php foreach ($province_options as $province_option): ?>
                            <option value="<?php echo htmlspecialchars($province_option); ?>">
                                <?php echo htmlspecialchars($province_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex space-x-3 justify-end mt-6">
                <button type="button" onclick="closeAddSupplierModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-[#08415c] text-white rounded-lg hover:bg-[#0a5273] transition">
                    Add Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div id="editSupplierModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 supplier-modal p-4">
    <div class="bg-white rounded-xl p-6 max-w-lg w-full max-h-screen overflow-y-auto">
        <h3 class="text-lg font-bold text-[#08415c] mb-4">Edit Supplier</h3>
        <form id="editSupplierForm" class="space-y-4" action="../../backend/suppliers/update_supplier.php" method="POST">
            <input type="hidden" name="supplier_id" id="edit_supplier_id">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier Name *</label>
                <input type="text" name="supplier_name" id="edit_supplier_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" minlength="2" maxlength="255" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                <input type="text" name="contact_person" id="edit_contact_person" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" maxlength="255">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" id="edit_email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" maxlength="255">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="tel" name="phone" id="edit_phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" maxlength="50">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <input type="text" name="address" id="edit_address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" maxlength="255">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                    <input type="text" name="city" id="edit_city" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" maxlength="100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                    <select name="province" id="edit_province" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent" required>
                        <?php foreach ($province_options as $province_option): ?>
                            <option value="<?php echo htmlspecialchars($province_option); ?>">
                                <?php echo htmlspecialchars($province_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex space-x-3 justify-end mt-6">
                <button type="button" onclick="closeEditSupplierModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-[#08415c] text-white rounded-lg hover:bg-[#0a5273] transition">
                    Update Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddSupplierModal() {
    const addForm = document.getElementById('addSupplierForm');
    if (addForm) {
        addForm.reset();
    }
    document.getElementById('addSupplierModal').classList.remove('hidden');
}

function closeAddSupplierModal() {
    document.getElementById('addSupplierModal').classList.add('hidden');
}

function openEditSupplierModal(button) {
    document.getElementById('edit_supplier_id').value = button.dataset.id || '';
    document.getElementById('edit_supplier_name').value = button.dataset.name || '';
    document.getElementById('edit_contact_person').value = button.dataset.contact || '';
    document.getElementById('edit_email').value = button.dataset.email || '';
    document.getElementById('edit_phone').value = button.dataset.phone || '';
    document.getElementById('edit_address').value = button.dataset.address || '';
    document.getElementById('edit_city').value = button.dataset.city || '';
    document.getElementById('edit_province').value = button.dataset.province || 'Pampanga';

    document.getElementById('editSupplierModal').classList.remove('hidden');
}

function closeEditSupplierModal() {
    document.getElementById('editSupplierModal').classList.add('hidden');
}

async function handleSupplierActionSubmit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form) {
        return;
    }

    const confirmMessage = (form.dataset.confirmMessage || 'Are you sure?').trim();
    const confirmTitle = (form.dataset.confirmTitle || 'Please Confirm').trim();

    let confirmed = false;
    if (typeof window.showConfirmModal === 'function') {
        confirmed = await window.showConfirmModal(confirmMessage, confirmTitle);
    } else {
        confirmed = window.confirm(confirmMessage);
    }

    if (!confirmed) {
        return;
    }

    HTMLFormElement.prototype.submit.call(form);
}

document.querySelectorAll('.supplier-action-form').forEach((form) => {
    form.addEventListener('submit', handleSupplierActionSubmit);
});
</script>

<?php
$suppliers_content = ob_get_clean();
$content = $suppliers_content;
$current_page = 'suppliers';
include 'app.php';
?>
