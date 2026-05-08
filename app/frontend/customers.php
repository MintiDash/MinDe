<?php
/**
 * Customer Management Frontend
 * File: C:\xampp\htdocs\MinC_Project\app\frontend\customers.php
 */

// Authentication and user data
include_once '../../backend/auth.php';
include_once '../../database/connect_database.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    header('Location: ../../index.php?error=' . $validation['reason']);
    exit;
}

// Check if user has permission to access customer management
// Only IT Personnel (1), Owner (2), and Manager (3) can access
if (!isManagementLevel()) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to access this page.';
    header('Location: dashboard.php');
    exit;
}

// Get current user data
$user_data = [
    'id' => $_SESSION['user_id'] ?? null,
    'name' => $_SESSION['full_name'] ?? $_SESSION['fname'] ?? 'Guest User',
    'user_type' => $_SESSION['user_type_name'] ?? 'User',
    'user_level_id' => $_SESSION['user_level_id'] ?? null
];

// Set custom title for this page
$custom_title = 'Customer Management - MinC Project';

// Update user array to match app.php format
$user = [
    'full_name' => $user_data['name'],
    'user_type' => $user_data['user_type'],
    'is_logged_in' => isset($user_data['id'])
];

// Fetch customers data
try {
    // Get customers with their associated user information and order statistics
    $customers_query = "
        SELECT 
            c.customer_id,
            c.user_id,
            c.first_name,
            c.last_name,
            CONCAT(c.first_name, ' ', c.last_name) as full_name,
            c.email,
            c.phone,
            c.address,
            c.city,
            c.province,
            c.postal_code,
            c.customer_type,
            c.created_at,
            u.user_status,
            COUNT(DISTINCT o.order_id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_spent,
            MAX(o.created_at) as last_order_date
        FROM customers c
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN orders o ON c.customer_id = o.customer_id
        GROUP BY c.customer_id
        ORDER BY c.created_at DESC
    ";
    $customers_result = $pdo->query($customers_query);
    $customers = $customers_result->fetchAll(PDO::FETCH_ASSOC);

    // Get customer types for filtering
    $customer_types = [
        ['type' => 'guest'],
        ['type' => 'registered']
    ];

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading data: ' . $e->getMessage();
    $customers = [];
    $customer_types = [];
}

// Additional styles for customer management specific elements
$additional_styles = '
.customer-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #16a34a, #22c55e);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 16px;
}

.type-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.type-registered {
    background-color: #dbeafe;
    color: #1e40af;
}

.type-guest {
    background-color: #f3f4f6;
    color: #6b7280;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.status-active {
    background-color: #dcfce7;
    color: #166534;
}

.status-inactive {
    background-color: #fef2f2;
    color: #991b1b;
}

.table-hover tbody tr:hover {
    background-color: rgba(249, 250, 251, 0.8);
}

/* Fixed table container and table width styles */
.desktop-table {
    width: 100%;
    overflow-x: auto;
}

.desktop-table table {
    width: 100%;
    min-width: 100%;
    table-layout: auto;
}

.desktop-table th,
.desktop-table td {
    min-width: 120px;
}

.desktop-table th:first-child,
.desktop-table td:first-child {
    min-width: 220px;
}

.desktop-table th:nth-child(2),
.desktop-table td:nth-child(2) {
    min-width: 200px;
}

.desktop-table th:nth-child(3),
.desktop-table td:nth-child(3) {
    min-width: 140px;
}

.desktop-table th:nth-child(4),
.desktop-table td:nth-child(4) {
    min-width: 100px;
}

.desktop-table th:nth-child(5),
.desktop-table td:nth-child(5) {
    min-width: 120px;
}

.desktop-table th:last-child,
.desktop-table td:last-child {
    min-width: 100px;
}

@media (max-width: 768px) {
    .mobile-card {
        display: block !important;
    }
    
    .desktop-table {
        display: none !important;
    }
}

@media (min-width: 769px) {
    .mobile-card {
        display: none !important;
    }
    
    .desktop-table {
        display: block !important;
        width: 100%;
    }
}

.professional-card.table-container {
    padding: 0;
    overflow: hidden;
}

.professional-card.table-container .desktop-table {
    margin: 0;
}

.stat-card {
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}
';

// Customer management content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                <i class="fas fa-users text-green-600 mr-3"></i>
                Customer Management
            </h2>
            <p class="text-gray-600">
                View and manage customer information and order history.
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="exportCustomers()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium flex items-center transition-colors duration-200">
                <i class="fas fa-download mr-2"></i>
                Export Data
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="professional-card rounded-xl p-6 stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Total Customers</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo count($customers); ?></p>
            </div>
            <div class="p-4 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl shadow-lg">
                <i class="fas fa-users text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Registered</p>
                <p class="text-3xl font-bold text-blue-600">
                    <?php echo count(array_filter($customers, function($c) { return $c['customer_type'] === 'registered'; })); ?>
                </p>
            </div>
            <div class="p-4 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg">
                <i class="fas fa-user-check text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Guest Customers</p>
                <p class="text-3xl font-bold text-gray-600">
                    <?php echo count(array_filter($customers, function($c) { return $c['customer_type'] === 'guest'; })); ?>
                </p>
            </div>
            <div class="p-4 bg-gradient-to-br from-gray-500 to-gray-600 rounded-xl shadow-lg">
                <i class="fas fa-user-clock text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Total Revenue</p>
                <p class="text-3xl font-bold text-green-600">
                    ₱<?php echo number_format(array_sum(array_column($customers, 'total_spent')), 2); ?>
                </p>
            </div>
            <div class="p-4 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl shadow-lg">
                <i class="fas fa-peso-sign text-white text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="search_customers" class="block text-sm font-medium text-gray-700 mb-2">Search Customers</label>
            <div class="relative">
                <input type="text" id="search_customers" placeholder="Search by name or email..." 
                       class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
        </div>
        
        <div>
            <label for="type_filter" class="block text-sm font-medium text-gray-700 mb-2">Customer Type</label>
            <select id="type_filter" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                <option value="">All Types</option>
                <option value="registered">Registered</option>
                <option value="guest">Guest</option>
            </select>
        </div>
        
        <div>
            <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select id="status_filter" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
    </div>
</div>

<!-- Customers Table/Cards -->
<div class="professional-card table-container rounded-xl overflow-hidden animate-fadeIn">
    <!-- Desktop Table View -->
    <div class="desktop-table">
        <table class="w-full table-hover">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Spent</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-users text-4xl mb-4 text-gray-300"></i>
                            <p>No customers found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr class="customer-row" 
                            data-name="<?php echo strtolower($customer['full_name']); ?>" 
                            data-email="<?php echo strtolower($customer['email']); ?>"
                            data-type="<?php echo $customer['customer_type']; ?>"
                            data-status="<?php echo $customer['user_status'] ?? 'active'; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="customer-avatar mr-4">
                                        <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($customer['full_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            ID: #<?php echo str_pad($customer['customer_id'], 5, '0', STR_PAD_LEFT); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($customer['email']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($customer['phone'] ?? 'No phone'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($customer['city'] ?? 'N/A'); ?>, 
                                    <?php echo htmlspecialchars($customer['province'] ?? 'N/A'); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="type-badge type-<?php echo $customer['customer_type']; ?>">
                                    <?php echo ucfirst($customer['customer_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo $customer['total_orders']; ?> orders</div>
                                <?php if ($customer['last_order_date']): ?>
                                    <div class="text-xs text-gray-500">
                                        Last: <?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-bold text-green-600">
                                    ₱<?php echo number_format($customer['total_spent'], 2); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewCustomerDetails(<?php echo $customer['customer_id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-900 p-2 rounded-lg hover:bg-blue-50 transition-colors duration-200" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="mobile-card p-4 space-y-4">
        <?php if (empty($customers)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-users text-4xl mb-4 text-gray-300"></i>
                <p>No customers found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($customers as $customer): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-4 customer-card" 
                     data-name="<?php echo strtolower($customer['full_name']); ?>" 
                     data-email="<?php echo strtolower($customer['email']); ?>"
                     data-type="<?php echo $customer['customer_type']; ?>"
                     data-status="<?php echo $customer['user_status'] ?? 'active'; ?>">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center">
                            <div class="customer-avatar mr-3">
                                <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($customer['full_name']); ?></h4>
                                <p class="text-sm text-gray-500">ID: #<?php echo str_pad($customer['customer_id'], 5, '0', STR_PAD_LEFT); ?></p>
                            </div>
                        </div>
                        <span class="type-badge type-<?php echo $customer['customer_type']; ?>">
                            <?php echo ucfirst($customer['customer_type']); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Email</p>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($customer['email']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Phone</p>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($customer['phone'] ?? 'No phone'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Orders</p>
                            <p class="text-sm text-gray-900"><?php echo $customer['total_orders']; ?> orders</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Total Spent</p>
                            <p class="text-sm font-bold text-green-600">₱<?php echo number_format($customer['total_spent'], 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button onclick="viewCustomerDetails(<?php echo $customer['customer_id']; ?>)" 
                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200" 
                                title="View Details">
                            <i class="fas fa-eye mr-2"></i>View Details
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Customer Details Modal -->
<div id="customerDetailsModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center p-4" style="backdrop-filter: blur(10px);">
    <div class="professional-card rounded-xl max-w-3xl w-full max-h-screen overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Customer Details</h3>
                <button type="button" onclick="closeCustomerDetails()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="customerDetailsContent">
                <!-- Content will be loaded dynamically -->
                <div class="text-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mx-auto"></div>
                    <p class="text-gray-600 mt-4">Loading customer details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
// Initialize everything on page load
document.addEventListener("DOMContentLoaded", function() {
    console.log("DOM loaded, initializing filters");
    initializeFilters();
});

// Filter initialization
function initializeFilters() {
    const searchInput = document.getElementById("search_customers");
    const typeFilter = document.getElementById("type_filter");
    const statusFilter = document.getElementById("status_filter");

    function applyFilters() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
        const selectedType = typeFilter ? typeFilter.value : "";
        const selectedStatus = statusFilter ? statusFilter.value : "";
        
        const customerRows = document.querySelectorAll(".customer-row");
        const customerCards = document.querySelectorAll(".customer-card");

        function filterElement(element) {
            const name = element.getAttribute("data-name") || "";
            const email = element.getAttribute("data-email") || "";
            const type = element.getAttribute("data-type") || "";
            const status = element.getAttribute("data-status") || "";

            const matchesSearch = searchTerm === "" || 
                                name.includes(searchTerm) || 
                                email.includes(searchTerm);
            
            const matchesType = selectedType === "" || type === selectedType;
            const matchesStatus = selectedStatus === "" || status === selectedStatus;

            const isVisible = matchesSearch && matchesType && matchesStatus;
            element.style.display = isVisible ? "" : "none";
        }

        customerRows.forEach(filterElement);
        customerCards.forEach(filterElement);
    }

    if (searchInput) searchInput.addEventListener("input", applyFilters);
    if (typeFilter) typeFilter.addEventListener("change", applyFilters);
    if (statusFilter) statusFilter.addEventListener("change", applyFilters);
}

// View customer details
function viewCustomerDetails(customerId) {
    console.log("Viewing customer details for ID:", customerId);
    const modal = document.getElementById("customerDetailsModal");
    const content = document.getElementById("customerDetailsContent");
    
    if (!modal) {
        console.error("Customer details modal not found");
        return;
    }
    
    modal.classList.remove("hidden");
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
    
    // Show loading state
    content.innerHTML = `
        <div class="text-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mx-auto"></div>
            <p class="text-gray-600 mt-4">Loading customer details...</p>
        </div>
    `;
    
    // Fetch customer details with proper error handling
    fetch(`../../backend/customer-management/get_customer.php?id=${customerId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Customer data received:", data);
            
            if (data.success) {
                displayCustomerDetails(data.customer, data.orders);
            } else {
                content.innerHTML = `
                    <div class="text-center py-12">
                        <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
                        <p class="text-gray-600">${escapeHtml(data.message || 'Failed to load customer details')}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error("Error:", error);
            content.innerHTML = `
                <div class="text-center py-12">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
                    <p class="text-gray-600">Error loading customer details: ${escapeHtml(error.message)}</p>
                    <button onclick="viewCustomerDetails(${customerId})" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Retry
                    </button>
                </div>
            `;
        });
}

// Display customer details
function displayCustomerDetails(customer, orders) {
    const content = document.getElementById("customerDetailsContent");
    
    let ordersHtml = '';
    if (orders && orders.length > 0) {
        ordersHtml = orders.map(order => `
            <div class="bg-gray-50 rounded-lg p-4 mb-3">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <p class="font-medium text-gray-900">Order #${escapeHtml(order.order_number)}</p>
                        <p class="text-sm text-gray-500">${formatDate(order.created_at)}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium ${getOrderStatusClass(order.order_status)}">
                        ${capitalizeFirst(order.order_status)}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <p class="text-sm text-gray-600">${order.total_items} item(s)</p>
                    <p class="font-bold text-green-600">₱${formatNumber(order.total_amount)}</p>
                </div>
            </div>
        `).join('');
    } else {
        ordersHtml = '<p class="text-gray-500 text-center py-8">No orders yet</p>';
    }
    
    content.innerHTML = `
        <div class="space-y-6">
            <!-- Customer Info -->
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Personal Information</h4>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-gray-500">Full Name</p>
                            <p class="text-sm font-medium text-gray-900">${escapeHtml(customer.full_name)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Email</p>
                            <p class="text-sm font-medium text-gray-900">${escapeHtml(customer.email)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Phone</p>
                            <p class="text-sm font-medium text-gray-900">${escapeHtml(customer.phone || 'N/A')}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Customer Type</p>
                            <span class="type-badge type-${customer.customer_type}">
                                ${capitalizeFirst(customer.customer_type)}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Address Information</h4>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-gray-500">Address</p>
                            <p class="text-sm font-medium text-gray-900">${escapeHtml(customer.address || 'N/A')}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">City</p>
                            <p class="text-sm font-medium text-gray-900">${escapeHtml(customer.city || 'N/A')}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Province</p>
                            <p class="text-sm font-medium text-gray-900">${escapeHtml(customer.province || 'N/A')}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Postal Code</p>
                            <p class="text-sm font-medium text-gray-900">${escapeHtml(customer.postal_code || 'N/A')}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Statistics -->
            <div class="border-t pt-6">
                <h4 class="text-sm font-medium text-gray-500 mb-4">Order Statistics</h4>
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-blue-600">${customer.total_orders}</p>
                        <p class="text-xs text-gray-600">Total Orders</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-green-600">₱${formatNumber(customer.total_spent)}</p>
                        <p class="text-xs text-gray-600">Total Spent</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-purple-600">₱${formatNumber(customer.avg_order_value || 0)}</p>
                        <p class="text-xs text-gray-600">Avg Order</p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="border-t pt-6">
                <h4 class="text-sm font-medium text-gray-500 mb-4">Recent Orders</h4>
                ${ordersHtml}
            </div>
        </div>
    `;
}

// Close customer details modal
function closeCustomerDetails() {
    const modal = document.getElementById("customerDetailsModal");
    if (modal) {
        modal.classList.add("hidden");
        document.body.style.overflow = ''; // Restore scrolling
    }
}

// Export customers data
function exportCustomers() {
    console.log("Exporting customers...");
    
    // Show a loading indicator (optional)
    const exportBtn = event.target.closest('button');
    const originalContent = exportBtn.innerHTML;
    exportBtn.disabled = true;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Exporting...';
    
    // Create a temporary link and trigger download
    const exportUrl = '../../backend/customer-management/export_customers.php';
    
    // Use fetch to check if the request is successful
    fetch(exportUrl, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Export failed');
        }
        return response.blob();
    })
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `customers_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        // Reset button
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalContent;
        
        // Show success message
        showNotification('Customers exported successfully!', 'success');
    })
    .catch(error => {
        console.error('Export error:', error);
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalContent;
        showNotification('Failed to export customers. Please try again.', 'error');
    });
}

// Helper function to show notifications
function showNotification(message, type = 'info') {
    if (typeof window.showAppToast === 'function') {
        window.showAppToast(message, type);
        return;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top',
            icon: type,
            title: String(message || ''),
            showConfirmButton: false,
            timer: 3200,
            timerProgressBar: true
        });
        return;
    }

    alert(String(message || ''));
}

// Helper functions
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatNumber(number) {
    return parseFloat(number).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function capitalizeFirst(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function getOrderStatusClass(status) {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'confirmed': 'bg-blue-100 text-blue-800',
        'processing': 'bg-indigo-100 text-indigo-800',
        'shipped': 'bg-purple-100 text-purple-800',
        'delivered': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
}

// Close modal when clicking outside
document.addEventListener("click", function(event) {
    const modal = document.getElementById("customerDetailsModal");
    if (event.target === modal) {
        closeCustomerDetails();
    }
});

// Close modal on ESC key
document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        closeCustomerDetails();
    }
});
</script>
<?php
$customer_management_content = ob_get_clean();

// Set the content for app.php
$content = $customer_management_content;

// Include the app.php layout
include 'app.php';
?>
