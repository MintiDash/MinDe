<?php
// Prevent direct access to sidebar.php
if (!isset($config) || !isset($user)) {
    if (basename($_SERVER['PHP_SELF']) === 'sidebar.php') {
        header('Location: ../dashboard.php');
        exit();
    }
    
    $config = $config ?? [
        'site_name' => 'MINC Auto Supply Parts',
        'site_short' => 'MINC',
        'version' => '1.0.0',
        'year' => date('Y')
    ];

    $user = $user ?? [
        'full_name' => 'Guest User',
        'user_type' => 'User',
        'department' => 'General',
        'is_logged_in' => false,
        'employee_id' => null,
        'email' => null,
        'contact_num' => null
    ];
    
    $current_page = $current_page ?? basename($_SERVER['PHP_SELF'], '.php');
}

$is_employee_sidebar = function_exists('isEmployee')
    ? isEmployee()
    : (isset($_SESSION['user_level_id']) && (int)$_SESSION['user_level_id'] === 2);

?>

<!-- Sidebar Overlay (Mobile) -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-40 lg:hidden backdrop-blur-sm"></div>

<!-- Sidebar -->
<div id="sidebar" 
     class="fixed inset-y-0 left-0 z-50 flex flex-col w-64 transition-all duration-300 ease-in-out overflow-y-auto overflow-x-hidden bg-gray-100 shadow-xl sidebar-expanded lg:translate-x-0 -translate-x-full"
     style="width: 250px;">
    
    <!-- Sidebar Header -->
    <div class="p-4 border-b border-gray-300 bg-gray-50 relative z-10">
        <button id="toggle-sidebar" type="button" class="w-full text-left rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]/30">
            <div class="relative h-10 w-full transition-all duration-300">
                <div class="full-logo-wrapper flex items-center relative transition-all duration-300">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#08415c] to-[#0a5273] flex items-center justify-center mr-3 shadow-md">
                        <i class="fas fa-car-side text-white text-sm"></i>
                    </div>
                    <div class="text-gray-700">
                        <div class="font-bold text-sm"><?php echo htmlspecialchars($config['site_short']); ?></div>
                        <div class="text-xs text-gray-500">Auto Parts</div>
                    </div>
                </div>
                <div class="small-logo-wrapper absolute inset-0 opacity-0 flex justify-center items-center transition transition-all duration-300">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#08415c] to-[#0a5273] flex items-center justify-center shadow-md">
                        <i class="fas fa-car-side text-white text-sm"></i>
                    </div>
                </div>
            </div>
        </button>
    </div>
    
    <!-- Navigation Links -->
    <div class="py-4 flex-1 relative z-10 bg-gray-100">

        <?php if (!$is_employee_sidebar): ?>
        <!-- Main Navigation -->
        <div class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 sidebar-heading transition-all duration-300">Main Navigation</div>
        <ul class="space-y-1 px-2">
            <li>
                <a href="dashboard.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'dashboard' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-tachometer-alt text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Dashboard</span>
                </a>
            </li>

            <?php if (isITStaff() || isOwner() || isManager()): ?>
            <li>
                <a href="audit-trail.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'audit-trail' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-history text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Audit Trail</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isITStaff() || isOwner()): ?>
            <li>
                <a href="user-management.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'user-management' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-users-cog text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">User Management</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <?php if (!$is_employee_sidebar): ?>
        <!-- Commerce Section -->
        <div class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 mt-6 sidebar-heading transition-all duration-300">Commerce</div>
        <ul class="space-y-1 px-2">
        
                    <?php if (isITStaff() || isOwner()): ?>
            <li>
                <a href="categories.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'categories' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-tags text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Categories</span>
                </a>
            </li>
            <?php endif; ?>

        <?php if (isITStaff() || isOwner()): ?>
<li>
    <a href="product-lines.php" 
       class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] 
       transition-all duration-200 rounded-lg 
       <?php echo $current_page === 'product-lines' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
       
        <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 
                     group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
            <i class="fas fa-layer-group text-sm"></i>
        </span>

        <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">
            Product Lines
        </span>
    </a>
</li>
<?php endif; ?>

            <?php if (isITStaff() || isOwner()): ?>
            <li>
                <a href="products.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'products' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-box text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Products</span>
                </a>
            </li>
            <?php endif; ?>



            <li>
                <a href="orders.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'orders' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-shopping-cart text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Orders</span>
                </a>
            </li>

            <?php if (isITStaff() || isOwner() || isManager()): ?>
            <li>
                <a href="customers.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'customers' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-user-friends text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Customers</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <!-- Inventory Section -->
        <div class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 mt-6 sidebar-heading transition-all duration-300">Inventory</div>
        <ul class="space-y-1 px-2">
            <?php if (isITStaff()): ?>
            <li>
                <a href="stock-management.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'stock-management' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-warehouse text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Stock Management</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isITStaff()): ?>
            <li>
                <a href="suppliers.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'suppliers' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-truck text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Suppliers</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (isITStaff() || $is_employee_sidebar): ?>
            <li>
                <a href="purchase-order.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'purchase-order' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-file-invoice-dollar text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Purchase Order</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <?php if (isITStaff() || $is_employee_sidebar): ?>
        <!-- Reports Section -->
        <div class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 mt-6 sidebar-heading transition-all duration-300">Reports</div>
        <ul class="space-y-1 px-2">
            <li>
                <a href="sales-report.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'sales-report' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-chart-line text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Sales Report</span>
                </a>
            </li>

            <li>
                <a href="inventory-report.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'inventory-report' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-clipboard-list text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Inventory Report</span>
                </a>
            </li>

            <?php if (isITStaff() || $is_employee_sidebar): ?>
            <li>
                <a href="generate_report.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo $current_page === 'generate_report' ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-file-pdf text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Generate Report</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <?php if ($is_employee_sidebar): ?>
        <!-- Customer Service Section -->
        <div class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 mt-6 sidebar-heading transition-all duration-300">Customer Service</div>
        <ul class="space-y-1 px-2">
            <?php if ($is_employee_sidebar): ?>
            <li>
                <a href="customer-messages.php" class="nav-link group flex items-center px-3 py-2.5 text-gray-700 hover:bg-gray-200 hover:text-[#08415c] transition-all duration-200 rounded-lg <?php echo in_array($current_page, ['customer-messages', 'chat-admin'], true) ? 'active-link text-[#08415c] bg-gray-200' : ''; ?>">
                    <span class="inline-flex justify-center items-center w-8 h-8 rounded-lg bg-gray-200 group-hover:bg-gray-300 nav-icon transition-all duration-300 group-hover:text-[#08415c]">
                        <i class="fas fa-comment-dots text-sm"></i>
                    </span>
                    <span class="ml-3 whitespace-nowrap transition-all duration-300 link-text font-medium">Customer Messages</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar Footer -->
    <div id="sidebar-footer" class="w-full py-3 px-4 text-center text-xs border-t border-gray-300 transition-all duration-300 relative z-10 bg-gray-50">
        <div class="flex items-center justify-between link-text">
            <span class="text-gray-600 whitespace-nowrap overflow-hidden text-ellipsis">© <?php echo htmlspecialchars($config['year']); ?> <?php echo htmlspecialchars($config['site_short']); ?></span>
            <span class="text-gray-500 text-xs link-text">v<?php echo htmlspecialchars($config['version']); ?></span>
        </div>
    </div>
</div>
