<?php
// Configuration and session management
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Configuration array - you can move this to a separate config file
$config = [
    'site_name' => 'MinC Auto Supply',
    'site_short' => 'MinC',
    'version' => '1.0.0',
    'year' => date('Y')
];

// User data - replace with your authentication system
if (!isset($pdo)) {
    include_once '../../database/connect_database.php';
}

// Include auth functions
if (!function_exists('isITStaff')) {
    require_once '../../backend/auth.php';
}

$normalizeName = function ($value) {
    $value = preg_replace('/\s+/', ' ', trim((string)$value));
    return ucwords(strtolower($value), " -'");
};

// Get current user data from database
$user = [
    'full_name' => 'Guest User',
    'user_type' => 'User',
    'is_logged_in' => false,
    'user_id' => null,
    'email' => null,
    'contact_num' => null
];

if (isset($_SESSION['user_id'])) {
    try {
        $user_query = "
            SELECT 
                u.user_id,
                CONCAT(u.fname, ' ', u.lname) as full_name,
                u.fname,
                u.lname,
                u.email,
                u.contact_num,
                CASE
                    WHEN u.user_level_id = 1 THEN 'Admin'
                    WHEN u.user_level_id = 2 THEN 'Employee'
                    WHEN u.user_level_id = 3 THEN 'Supplier'
                    WHEN u.user_level_id = 4 THEN 'Customer'
                    WHEN LOWER(ul.user_type_name) IN ('it personnel', 'admin') THEN 'Admin'
                    WHEN LOWER(ul.user_type_name) IN ('owner', 'manager', 'employee', 'employees') THEN 'Employee'
                    WHEN LOWER(ul.user_type_name) IN ('supplier', 'suppliers') THEN 'Supplier'
                    WHEN LOWER(ul.user_type_name) IN ('consumer', 'customer', 'customers') THEN 'Customer'
                    ELSE ul.user_type_name
                END as user_type,
                u.user_status,
                u.user_level_id
            FROM users u
            LEFT JOIN user_levels ul ON u.user_level_id = ul.user_level_id
            WHERE u.user_id = :user_id AND u.user_status = 'active'
        ";
        
        $stmt = $pdo->prepare($user_query);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
if ($user_data) {
    $normalizedFname = $normalizeName($user_data['fname'] ?? '');
    $normalizedLname = $normalizeName($user_data['lname'] ?? '');
    $normalizedFullName = trim($normalizedFname . ' ' . $normalizedLname);
    $user = [
        'full_name' => $normalizedFullName,
        'first_name' => $normalizedFname,
        'last_name' => $normalizedLname,
        'user_type' => $user_data['user_type'],
        'is_logged_in' => true,
        'user_id' => $user_data['user_id'],
        'email' => $user_data['email'],
        'contact_num' => $user_data['contact_num'],
        'user_status' => $user_data['user_status'],
        'user_level_id' => $user_data['user_level_id']
    ];
    
    // Also update session with latest data
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['fname'] = $user['first_name'];
    $_SESSION['lname'] = $user['last_name'];
    $_SESSION['user_type_name'] = $user['user_type'];
    $_SESSION['user_level_id'] = $user['user_level_id'];
}
    } catch (PDOException $e) {
        error_log("Error fetching user data in app.php: " . $e->getMessage());
        // Keep default values if database query fails
    }
}

// Current page detection
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Page titles mapping
$page_titles = [
    'dashboard' => 'Dashboard',
    'inventory' => 'Inventory Management',
    'products' => 'Products',
    'orders' => 'Orders',
    'customers' => 'Customers',
    'suppliers' => 'Suppliers',
    'reports' => 'Reports'
];
$page_title = $page_titles[$current_page] ?? 'Dashboard';
$document_title = isset($custom_title) ? $custom_title : $page_title . ' - ' . $config['site_name'];

// Get unread notification count for the current user
// Get unread notification count for the current user
$unread_notifications = 0;
if ($user['is_logged_in'] && isset($user['user_id'])) {
    try {
        // Check if notifications table exists first
        $table_check = $pdo->query("SHOW TABLES LIKE 'notifications'");
        
        if ($table_check->rowCount() > 0) {
            $notification_query = "
                SELECT COUNT(*) as unread_count 
                FROM notifications 
                WHERE recipient_id = :user_id AND is_read = 0
            ";
            
            $notification_stmt = $pdo->prepare($notification_query);
            $notification_stmt->bindParam(':user_id', $user['user_id'], PDO::PARAM_INT);
            $notification_stmt->execute();
            $notification_result = $notification_stmt->fetch(PDO::FETCH_ASSOC);
            
            $unread_notifications = (int)($notification_result['unread_count'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log("Error fetching notification count: " . $e->getMessage());
        $unread_notifications = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($document_title); ?></title>
    <link rel="icon" type="image/png" href="../../resources/images/favicon.ico">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
 <style>
    /* MinC Brand Colors - Teal Theme */
    .minc-dark-blue { background-color: #08415c; }
    .minc-blue { background-color: #0a5273; }
    .minc-light-blue { background-color: #1a6d9e; }
    .minc-accent { background-color: #08415c; }
    
    /* Professional card design */
    .professional-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    /* Glassmorphism sidebar - Teal */
    .glassmorphism {
        background: linear-gradient(135deg, 
            rgba(8, 65, 92, 0.95) 0%, 
            rgba(10, 82, 115, 0.95) 50%, 
            rgba(8, 65, 92, 0.95) 100%
        );
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 4px 0 24px rgba(0, 0, 0, 0.2);
    }
    
    /* Water background animation - Teal tones */
    .water-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, 
            rgba(8, 65, 92, 0.1) 0%,
            rgba(10, 82, 115, 0.1) 25%,
            rgba(26, 109, 158, 0.1) 50%,
            rgba(8, 65, 92, 0.1) 75%,
            rgba(8, 65, 92, 0.1) 100%
        );
        background-size: 400% 400%;
        animation: waterFlow 15s ease-in-out infinite;
        opacity: 0.3;
    }
    
    @keyframes waterFlow {
        0%, 100% { background-position: 0% 50%; }
        25% { background-position: 100% 25%; }
        50% { background-position: 100% 100%; }
        75% { background-position: 0% 75%; }
    }
    
    .dashboard-bg {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        min-height: 100vh;
    }
    
    /* Teal accent elements */
    .blue-accent {
        background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
    }
    
    .blue-hover:hover {
        background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(8, 65, 92, 0.25);
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-float {
        animation: float 6s ease-in-out infinite;
    }
    
    .animate-fadeIn {
        animation: fadeIn 0.6s ease-out forwards;
    }
    
    /* Professional hover effects */
    .hover-lift:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }
    
    /* Active link indicator - Blue theme */
    .active-indicator {
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 0;
        background: linear-gradient(135deg, #60a5fa, #3b82f6);
        border-radius: 0 2px 2px 0;
        transition: height 0.3s ease;
    }
    
    .active-link .active-indicator {
        height: 24px;
    }
    
/* Sidebar collapsed styles */
.sidebar-collapsed {
    width: 88px !important;
}

/* Ensure content area takes full available width */
#content {
    width: 100%;
    min-width: 0; /* Allow content to shrink below its minimum content size */
}

    .sidebar-collapsed .full-logo-wrapper {
        opacity: 0;
    }

    .sidebar-collapsed .small-logo-wrapper {
        opacity: 1;
    }

    .sidebar-collapsed .link-text,
    .sidebar-collapsed .sidebar-heading,
    .sidebar-collapsed .user-info {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }

    .sidebar-collapsed .nav-icon {
        margin: 0 auto;
        width: 2.5rem;
        height: 2.5rem;
    }

    .sidebar-collapsed .nav-link {
        justify-content: center;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }

    /* Custom scrollbar - Blue theme */
    #sidebar::-webkit-scrollbar {
        width: 6px;
    }

    #sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 3px;
        margin: 8px 0;
    }

    #sidebar::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, 
            rgba(59, 130, 246, 0.4) 0%, 
            rgba(37, 99, 235, 0.4) 50%,
            rgba(96, 165, 250, 0.4) 100%
        );
        border-radius: 3px;
        transition: all 0.3s ease;
    }

    #sidebar::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, 
            rgba(59, 130, 246, 0.7) 0%, 
            rgba(37, 99, 235, 0.7) 50%,
            rgba(96, 165, 250, 0.7) 100%
        );
        width: 8px;
    }

    #sidebar {
        scrollbar-width: thin;
        scrollbar-color: rgba(59, 130, 246, 0.4) rgba(255, 255, 255, 0.05);
    }

    #sidebar::-webkit-scrollbar {
        width: 0px;
        transition: width 0.3s ease;
    }

    #sidebar:hover::-webkit-scrollbar {
        width: 6px;
    }
    
    /* Updated Topbar Styles - Teal */
    .minc-topbar {
        background-color: #08415c;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    .user-dropdown {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }
    
    /* Professional button styles - Teal theme */
    .btn-primary {
        background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
        color: white;
        border: none;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #062d42 0%, #08415c 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(8, 65, 92, 0.3);
    }
    
    /* Status indicators */
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    
    .status-active { background-color: #22c55e; }
    .status-pending { background-color: #f59e0b; }
    .status-completed { background-color: #3b82f6; }
    
    /* Mobile responsiveness */
    @media (max-width: 1024px) {
        #content {
            margin-left: 0 !important;
        }
        
        #sidebar {
            position: fixed;
            z-index: 50;
        }
    }

    /* Desktop - sidebar should push content */
    @media (min-width: 1024px) {
        #content {
            transition: margin-left 0.3s ease-in-out;
        }
        
        #sidebar {
            position: fixed;
            left: 0;
            transform: translateX(0) !important;
        }
    }

    /* Alert styles */
    .alert-success {
        background-color: rgb(240 253 244);
        border-color: rgb(187 247 208);
        color: rgb(22 101 52);
    }
    
    .alert-error {
        background-color: rgb(254 242 242);
        border-color: rgb(254 202 202);
        color: rgb(153 27 27);
    }
    
    /* Nav link hover effects - Teal theme */
    .nav-link:hover .nav-icon {
        transform: scale(1.1);
    }
    
    .nav-link.active-link .nav-icon {
        background: rgba(8, 65, 92, 0.2) !important;
        color: #08415c !important;
    }

    .minc-toast-container {
        position: fixed !important;
        inset: 0 !important;
        display: flex !important;
        justify-content: center !important;
        align-items: flex-start !important;
        padding-top: 50px !important;
        pointer-events: none !important;
        z-index: 10000 !important;
    }

    .swal2-container.minc-toast-container > .swal2-popup.minc-toast-popup {
        margin: 0 auto !important;
    }

    .swal2-popup.minc-toast-popup {
        width: min(92vw, 420px) !important;
        min-height: 0 !important;
        padding: 0.55rem 0.8rem !important;
        border-radius: 12px !important;
        background: linear-gradient(135deg, #08415c 0%, #0a5273 100%) !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        color: #fff !important;
        pointer-events: auto !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.22) !important;
    }

    .swal2-popup.minc-toast-popup .swal2-title.minc-toast-title {
        margin: 0 !important;
        font-size: 0.92rem !important;
        font-weight: 600 !important;
        line-height: 1.25 !important;
    }

    .swal2-popup.minc-toast-popup .swal2-close.minc-toast-close {
        color: rgba(255, 255, 255, 0.9) !important;
        font-size: 1.2rem !important;
        width: 1.5rem !important;
        height: 1.5rem !important;
    }

    .swal2-popup.minc-toast-popup .swal2-icon {
        margin: 0 0.55rem 0 0 !important;
        transform: scale(0.78);
    }

    .swal2-popup.minc-toast-popup .swal2-timer-progress-bar {
        background: rgba(255, 255, 255, 0.4) !important;
    }

    .swal2-popup.minc-toast-popup.minc-toast-info,
    .swal2-popup.minc-toast-popup.minc-toast-success,
    .swal2-popup.minc-toast-popup.minc-toast-warning,
    .swal2-popup.minc-toast-popup.minc-toast-error,
    .swal2-popup.minc-toast-popup.minc-toast-question {
        background: linear-gradient(135deg, #08415c 0%, #0a5273 100%) !important;
        color: #fff !important;
    }
    
    /* Additional custom styles */
    <?php if (isset($additional_styles)): ?>
    <?php echo $additional_styles; ?>
    <?php endif; ?>
</style>
    <link rel="stylesheet" href="components/extension-projects.css">
    <link rel="stylesheet" href="components/terminal-report.css">
</head>
<body class="dashboard-bg font-sans flex min-h-screen overflow-x-hidden">
<!-- Background decorative elements -->
<div class="fixed inset-0 overflow-hidden pointer-events-none">
    <div class="absolute top-20 -left-20 w-64 h-64 bg-blue-200/30 rounded-full filter blur-3xl animate-float"></div>
    <div class="absolute bottom-10 -right-20 w-80 h-80 bg-blue-300/20 rounded-full filter blur-3xl animate-float" style="animation-delay: -2s;"></div>
</div>

    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div id="content" class="flex-1 transition-all duration-300 ease-in-out ml-0 lg:ml-64">
<!-- Updated Top Navigation Bar -->
<nav class="minc-topbar text-white shadow-lg sticky top-0 z-20 no-print">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Left Side with Icon -->
            <div class="flex items-center">
                <!-- Mobile Menu Button -->
                <button id="mobile-toggle" class="lg:hidden w-10 h-10 flex items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/30 transition-all duration-200 mr-3">
                    <i class="fas fa-bars menu-icon"></i>
                </button>
                
                <!-- Home Button -->
                <a href="../../index.php" class="mr-4 w-10 h-10 flex items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/30 transition-all duration-200" title="Back to Home">
                    <i class="fas fa-home"></i>
                </a>
                
            </div>
                    
                    <!-- Right Side -->
                    <div class="flex items-center space-x-4">
                        <!-- User Profile Dropdown -->
                        <div class="relative">
                            <button id="user-menu-button" class="flex items-center p-2 rounded-xl bg-white/10 hover:bg-white/20 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-white/30">
<div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center mr-3" title="<?php echo htmlspecialchars($user['full_name']); ?>">
    <?php 
    // Show user initials if available
    if (!empty($user['full_name']) && $user['full_name'] !== 'Guest User') {
        $name_parts = explode(' ', trim($user['full_name']));
        $initials = '';
        foreach ($name_parts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
                if (strlen($initials) >= 2) break; // Limit to 2 initials
            }
        }
        echo '<span class="text-white text-xs font-semibold">' . htmlspecialchars($initials) . '</span>';
    } else {
        echo '<i class="fas fa-user text-white text-sm"></i>';
    }
    ?>
</div>

<div class="text-right">
    <p class="font-semibold text-sm" title="<?php echo htmlspecialchars($user['full_name']); ?>">
        <?php 
        // Truncate long names for better display
        $display_name = strlen($user['full_name']) > 20 
            ? substr($user['full_name'], 0, 17) . '...' 
            : $user['full_name'];
        echo htmlspecialchars($display_name); 
        ?>
    </p>
<p class="text-xs opacity-80 capitalize" title="User ID: <?php echo htmlspecialchars($user['user_id'] ?? 'N/A'); ?>">
    <?php echo htmlspecialchars($user['user_type']); ?>
</p>
</div>
                                <i class="fas fa-chevron-down ml-2 text-sm"></i>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="user-menu" class="absolute right-0 mt-2 w-56 user-dropdown rounded-xl py-2 z-50 hidden">
                                <!-- User Info Header -->
<div class="px-4 py-3 border-b border-gray-200">
    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></p>
    <p class="text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($user['user_type']); ?></p>
    <p class="text-xs text-gray-400 mt-1">
        <i class="fas fa-id-badge mr-1"></i>User ID: <?php echo htmlspecialchars($user['user_id'] ?? 'N/A'); ?>
    </p>
    <p class="text-xs text-gray-400">
        <i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($user['email'] ?: 'No email'); ?>
    </p>
    <?php if (!empty($user['contact_num'])): ?>
    <p class="text-xs text-gray-400">
        <i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($user['contact_num']); ?>
    </p>
    <?php endif; ?>
</div>
                                <!-- Menu Items -->
                                <a href="../../html/profile.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                                    <i class="fas fa-user mr-3 w-4"></i>My Profile
                                </a>
                                <a href="../../html/profile.php#settings" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                                    <i class="fas fa-cog mr-3 w-4"></i>Account Settings
                                </a>
                                <a href="javascript:void(0)" onclick="showHelpSupport()" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors duration-200 cursor-pointer">
                                    <i class="fas fa-question-circle mr-3 w-4"></i>Help & Support
                                </a>
                                
                                <hr class="my-2">
                                
<a href="../../backend/logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors duration-200">
    <i class="fas fa-sign-out-alt mr-3 w-4"></i>Sign Out
</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="p-6">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="mb-6 p-4 alert-success border rounded-xl flex items-center animate-fadeIn">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="mb-6 p-4 alert-error border rounded-xl flex items-center animate-fadeIn">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
         <!-- Page specific content goes here -->
<?php if (isset($content) && !empty($content)): ?>
    <?php echo $content; ?>
<?php elseif (isset($content_file) && file_exists($content_file)): ?>
    <?php include $content_file; ?>
<?php else: ?>
    <!-- Default content or include specific page content -->
    <div class="professional-card rounded-xl p-6 animate-fadeIn">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Welcome to <?php echo htmlspecialchars($config['site_name']); ?></h3>
        <p class="text-gray-600">This is the main content area. Include your page-specific content here.</p>
    </div>
<?php endif; ?>
        </main>
    </div>

    <!-- JavaScript for enhanced functionality -->
    <script>
        function resolveToastVariant(icon) {
            const value = String(icon || 'info').toLowerCase();
            if (value === 'error' || value === 'success' || value === 'warning' || value === 'question' || value === 'info') {
                return value;
            }
            return 'info';
        }

        function enforceCenteredToastLayout(toastEl) {
            const container = toastEl && toastEl.parentElement ? toastEl.parentElement : null;
            if (!container) return;

            container.style.position = 'fixed';
            container.style.top = '0';
            container.style.right = '0';
            container.style.bottom = '0';
            container.style.left = '0';
            container.style.width = '100vw';
            container.style.maxWidth = '100vw';
            container.style.transform = 'none';
            container.style.margin = '0';
            container.style.display = 'flex';
            container.style.justifyContent = 'center';
            container.style.alignItems = 'flex-start';
            container.style.paddingTop = '50px';
            container.style.pointerEvents = 'none';
            container.style.zIndex = '10000';
            container.classList.remove('swal2-top-start', 'swal2-top-end');
            container.classList.add('swal2-top');

            toastEl.style.margin = '0 auto';
            toastEl.style.pointerEvents = 'auto';
        }

        function attachToastNavigation(toastEl, options = {}) {
            if (!toastEl) return;

            const href = typeof options.href === 'string' ? options.href.trim() : '';
            const onClick = typeof options.onClick === 'function' ? options.onClick : null;

            if (!href && !onClick) {
                toastEl.style.cursor = '';
                toastEl.removeAttribute('role');
                toastEl.removeAttribute('tabindex');
                return;
            }

            const activateToast = (event) => {
                if (event && event.target && typeof event.target.closest === 'function' && event.target.closest('.swal2-close')) {
                    return;
                }

                if (onClick) {
                    onClick(event);
                    return;
                }

                window.location.assign(href);
            };

            toastEl.style.cursor = 'pointer';
            toastEl.setAttribute('role', 'button');
            toastEl.setAttribute('tabindex', '0');
            toastEl.addEventListener('click', activateToast);
            toastEl.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    activateToast(event);
                }
            });
        }

        function showAppToast(message, icon = 'info', options = {}) {
            if (typeof Swal === 'undefined') {
                alert(String(message ?? ''));
                return Promise.resolve();
            }

            const variant = resolveToastVariant(icon || options.icon);
            const popupClass = `minc-toast-popup minc-toast-${variant}`;
            const originalDidOpen = options.didOpen;

            return Swal.fire(Object.assign({}, options, {
                toast: true,
                position: 'top',
                icon: variant,
                title: options.title || String(message ?? ''),
                text: options.text || undefined,
                showConfirmButton: false,
                showCloseButton: true,
                timer: options.timer ?? 3200,
                timerProgressBar: true,
                customClass: Object.assign({}, options.customClass || {}, {
                    container: 'minc-toast-container',
                    popup: popupClass,
                    title: 'minc-toast-title',
                    closeButton: 'minc-toast-close'
                }),
                didOpen: (toast) => {
                    enforceCenteredToastLayout(toast);
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                    attachToastNavigation(toast, options);
                    if (typeof originalDidOpen === 'function') {
                        originalDidOpen(toast);
                    }
                }
            }));
        }

        function applyGlobalToastDefaults() {
            if (typeof Swal === 'undefined' || Swal.__mincToastPatched) {
                return;
            }

            const originalFire = Swal.fire.bind(Swal);

            Swal.fire = function(...args) {
                if (args.length === 1 && args[0] && typeof args[0] === 'object' && args[0].toast === true) {
                    const input = args[0];
                    const variant = resolveToastVariant(input.icon);
                    const originalDidOpen = input.didOpen;
                    const existingPopup = (input.customClass && input.customClass.popup) ? String(input.customClass.popup) : '';
                    const popupClass = existingPopup.includes('minc-toast-popup')
                        ? existingPopup
                        : `${existingPopup} minc-toast-popup minc-toast-${variant}`.trim();

                    return originalFire(Object.assign({}, input, {
                        position: 'top',
                        showCloseButton: input.showCloseButton ?? true,
                        showConfirmButton: false,
                        timerProgressBar: input.timerProgressBar ?? true,
                        customClass: Object.assign({}, input.customClass || {}, {
                            container: (input.customClass && input.customClass.container) || 'minc-toast-container',
                            popup: popupClass,
                            title: (input.customClass && input.customClass.title) || 'minc-toast-title',
                            closeButton: (input.customClass && input.customClass.closeButton) || 'minc-toast-close'
                        }),
                        didOpen: (toast) => {
                            enforceCenteredToastLayout(toast);
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                            attachToastNavigation(toast, input);
                            if (typeof originalDidOpen === 'function') {
                                originalDidOpen(toast);
                            }
                        }
                    }));
                }

                return originalFire(...args);
            };

            Swal.__mincToastPatched = true;
        }

        applyGlobalToastDefaults();
        function showAlertModal(message, icon = 'info', title = 'Notice') {
            if (typeof Swal !== 'undefined') {
                return Swal.fire({
                    icon,
                    title,
                    text: String(message ?? ''),
                    confirmButtonColor: '#08415c'
                });
            }
            alert(message);
            return Promise.resolve();
        }

        async function showConfirmModal(message, title = 'Please Confirm') {
            if (typeof Swal !== 'undefined') {
                const result = await Swal.fire({
                    icon: 'question',
                    title,
                    text: String(message ?? ''),
                    showCancelButton: true,
                    confirmButtonColor: '#08415c',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Confirm'
                });
                return !!result.isConfirmed;
            }
            return confirm(message);
        }

        async function showPromptModal(title = 'Input Required', inputLabel = '') {
            if (typeof Swal !== 'undefined') {
                const result = await Swal.fire({
                    title,
                    input: 'text',
                    inputLabel,
                    inputPlaceholder: 'Type here...',
                    showCancelButton: true,
                    confirmButtonColor: '#08415c',
                    cancelButtonColor: '#d33'
                });
                return result.isConfirmed ? (result.value || '') : null;
            }
            return prompt(inputLabel || title);
        }

        window.showAppToast = showAppToast;
        window.showAlertModal = showAlertModal;
        window.showConfirmModal = showConfirmModal;
        window.showPromptModal = showPromptModal;

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            const toggleBtn = document.getElementById('toggle-sidebar');
            const mobileToggle = document.getElementById('mobile-toggle');
            const overlay = document.getElementById('sidebar-overlay');
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            const dashboardScrollKey = 'mincDashboardScrollY';

            const persistDashboardScroll = () => {
                const scrollY = window.scrollY || document.documentElement.scrollTop || 0;
                sessionStorage.setItem(dashboardScrollKey, String(scrollY));
            };

            const restoreDashboardScroll = () => {
                const savedScroll = sessionStorage.getItem(dashboardScrollKey);
                if (savedScroll === null) {
                    return;
                }

                const targetY = parseInt(savedScroll, 10);
                sessionStorage.removeItem(dashboardScrollKey);

                if (Number.isNaN(targetY) || targetY < 0) {
                    return;
                }

                // Retry a few frames so scroll restoration still works when long content renders late.
                let attempts = 0;
                const maxAttempts = 8;
                const tryRestore = () => {
                    window.scrollTo(0, targetY);
                    attempts += 1;

                    const currentY = window.scrollY || document.documentElement.scrollTop || 0;
                    if (Math.abs(currentY - targetY) > 2 && attempts < maxAttempts) {
                        requestAnimationFrame(tryRestore);
                    }
                };

                requestAnimationFrame(tryRestore);
            };

            restoreDashboardScroll();
            
            // Enhanced User Menu Toggle
            if (userMenuButton && userMenu) {
                userMenuButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userMenu.classList.toggle('hidden');
                });

                // Close menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                        userMenu.classList.add('hidden');
                    }
                });

                // Auto-hide menu after 5 seconds of inactivity
                let menuTimeout;
                userMenuButton.addEventListener('click', function() {
                    clearTimeout(menuTimeout);
                    if (!userMenu.classList.contains('hidden')) {
                        menuTimeout = setTimeout(function() {
                            userMenu.classList.add('hidden');
                        }, 5000);
                    }
                });
            }
            
            const resizeChartsIfAny = () => {
                if (!window.Chart || !window.Chart.instances) return;
                Object.values(window.Chart.instances).forEach((chartInstance) => {
                    if (chartInstance && typeof chartInstance.resize === 'function') {
                        chartInstance.resize();
                    }
                });
            };

            const applySidebarState = (collapsed, persist = true) => {
                if (!sidebar || !content) return;

                if (window.innerWidth < 1024) {
                    content.style.marginLeft = '0';
                    content.style.width = '100%';
                    return;
                }

                if (collapsed) {
                    sidebar.classList.add('sidebar-collapsed');
                    content.style.marginLeft = '88px';
                    content.style.width = 'calc(100% - 88px)';
                } else {
                    sidebar.classList.remove('sidebar-collapsed');
                    content.style.marginLeft = '250px';
                    content.style.width = 'calc(100% - 250px)';
                }

                if (persist) {
                    localStorage.setItem('sidebarState', collapsed ? 'collapsed' : 'expanded');
                }

                setTimeout(() => {
                    window.dispatchEvent(new Event('resize'));
                    resizeChartsIfAny();
                }, 320);
            };

            // Desktop sidebar toggle
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    applySidebarState(!sidebar.classList.contains('sidebar-collapsed'));
                });
            }
            
            // Mobile sidebar toggle
            if (mobileToggle) {
                mobileToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('-translate-x-full');
                    overlay.classList.toggle('opacity-0');
                    overlay.classList.toggle('pointer-events-none');
                    
                    if (!sidebar.classList.contains('-translate-x-full')) {
                        overlay.classList.remove('opacity-0', 'pointer-events-none');
                    } else {
                        overlay.classList.add('opacity-0', 'pointer-events-none');
                    }
                });
            }
            
            // Close sidebar when clicking overlay
            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                });
            }
            
            // Close sidebar on mobile when clicking a link
            const navLinks = sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function(event) {
                    const isPrimaryClick = event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
                    if (isPrimaryClick) {
                        persistDashboardScroll();
                    }

                    if (window.innerWidth < 1024) {
                        sidebar.classList.add('-translate-x-full');
                        overlay.classList.add('opacity-0', 'pointer-events-none');
                    }
                });
            });

  
            // Handle window resize
            window.addEventListener('resize', function() {
                if (!sidebar || !content) return;
                if (window.innerWidth >= 1024) {
                    applySidebarState(sidebar.classList.contains('sidebar-collapsed'), false);
                } else {
                    content.style.marginLeft = '0';
                    content.style.width = '100%';
                }
            });

            // Initialize layout state on page load
            const savedState = localStorage.getItem('sidebarState');
            applySidebarState(savedState === 'collapsed', false);
            
        });
        
        // Help & Support Modal
        function showHelpSupport() {
            const message = `Help & Support\n\n` +
                `For technical support, please contact:\n` +
                `Email: support@minc.com\n` +
                `Phone: 1-800-MINC-HELP\n` +
                `Chat: Available 24/7 on the help page\n\n` +
                `Common issues and FAQs are available in the Help section of the website.`;
            showAlertModal(message, 'info', 'Help & Support');
        }

    </script>
    
    <!-- Additional JavaScript -->
    <?php if (isset($additional_js)): ?>
    <script>
    <?php echo $additional_js; ?>
    </script>
    <?php endif; ?>
</body>
</html>
