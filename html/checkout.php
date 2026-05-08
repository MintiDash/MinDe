<?php
session_start();
require_once '../database/connect_database.php';
require_once '../config/payment_config.php';
require_once '../backend/order-management/order_workflow_helper.php';

// Buy Now mode (checkout only one selected product)
$is_buy_now = isset($_GET['buy_now']) && $_GET['buy_now'] === '1';
$buy_now_item = null;
$payment_config = getMincPaymentConfig();
$shipping_config = $payment_config['shipping'] ?? [
    'standard_fee' => 150.00,
    'free_threshold' => 1000.00
];
$standard_shipping_fee = (float)($shipping_config['standard_fee'] ?? 150);
$free_shipping_threshold = (float)($shipping_config['free_threshold'] ?? 1000);
$shipping_coverage_note = (string)($shipping_config['coverage_note'] ?? 'Shipping is currently available only within Angeles City, Pampanga.');

if (!function_exists('mincResolveConfiguredAssetUrl')) {
    function mincResolveConfiguredAssetUrl($assetPath) {
        $assetPath = trim((string)$assetPath);
        if ($assetPath === '') {
            return '';
        }

        if (preg_match('/^(https?:)?\/\//i', $assetPath)) {
            return $assetPath;
        }

        $absolutePath = mincProjectRootPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($assetPath, '/'));
        if (!file_exists($absolutePath)) {
            return '';
        }

        return mincPublicAssetUrl($assetPath);
    }
}

$payment_config['bpi']['qr_image_url'] = mincResolveConfiguredAssetUrl($payment_config['bpi']['qr_image'] ?? '');
$payment_config['gcash']['qr_image_url'] = mincResolveConfiguredAssetUrl($payment_config['gcash']['qr_image'] ?? '');

if ($is_buy_now) {
    $buy_now_product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
    $buy_now_quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;
    if ($buy_now_quantity < 1) {
        $buy_now_quantity = 1;
    }

    if ($buy_now_product_id > 0) {
        $stmt = mysqli_prepare($connection, "
            SELECT 
                p.product_id,
                p.product_name,
                p.product_code,
                p.product_image,
                p.price,
                p.stock_quantity,
                p.stock_status,
                pl.product_line_name
            FROM products p
            LEFT JOIN product_lines pl ON p.product_line_id = pl.product_line_id
            WHERE p.product_id = ? AND p.status = 'active'
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, "i", $buy_now_product_id);
        mysqli_stmt_execute($stmt);
        $buy_now_product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($buy_now_product && (int)$buy_now_product['stock_quantity'] >= $buy_now_quantity && $buy_now_quantity > 0) {
            $buy_now_item = [
                'product_id' => (int)$buy_now_product['product_id'],
                'product_name' => $buy_now_product['product_name'],
                'product_code' => $buy_now_product['product_code'],
                'product_image' => $buy_now_product['product_image'],
                'product_line_name' => $buy_now_product['product_line_name'] ?? '',
                'price' => (float)$buy_now_product['price'],
                'quantity' => $buy_now_quantity,
                'item_total' => (float)$buy_now_product['price'] * $buy_now_quantity,
                'stock_quantity' => (int)$buy_now_product['stock_quantity'],
                'stock_status' => $buy_now_product['stock_status']
            ];
        }
    }
}

// Check if cart has items (skip cart requirement for valid buy-now checkout)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$session_id = session_id();

if (!$user_id) {
    header('Location: user-cart.php?login_required=checkout');
    exit;
}

if (!$buy_now_item) {
    // Get cart
    if ($user_id) {
        $stmt = mysqli_prepare($connection, "SELECT cart_id FROM cart WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    } else {
        $stmt = mysqli_prepare($connection, "SELECT cart_id FROM cart WHERE session_id = ?");
        mysqli_stmt_bind_param($stmt, "s", $session_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        header('Location: user-cart.php');
        exit;
    }

    $cart_id = mysqli_fetch_assoc($result)['cart_id'];

    // Get cart items count
    $stmt = mysqli_prepare($connection, "SELECT COUNT(*) as count FROM cart_items WHERE cart_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $cart_id);
    mysqli_stmt_execute($stmt);
    $count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'];

    if ($count === 0) {
        header('Location: user-cart.php');
        exit;
    }
}

// Get user data if logged in
$user_data = null;
$saved_shipping_info = null;
if ($user_id) {
    $availableUserColumns = [];
    $columnsResult = mysqli_query($connection, "SHOW COLUMNS FROM users");
    if ($columnsResult) {
        while ($column = mysqli_fetch_assoc($columnsResult)) {
            if (!empty($column['Field'])) {
                $availableUserColumns[] = $column['Field'];
            }
        }
    }

    $shippingSelectParts = [
        in_array('address', $availableUserColumns, true) ? "address" : "NULL AS address",
        in_array('home_address', $availableUserColumns, true) ? "home_address" : "NULL AS home_address",
        in_array('billing_address', $availableUserColumns, true) ? "billing_address" : "NULL AS billing_address",
        in_array('barangay', $availableUserColumns, true) ? "barangay" : "NULL AS barangay",
        in_array('city', $availableUserColumns, true) ? "city" : "NULL AS city",
        in_array('province', $availableUserColumns, true) ? "province" : "NULL AS province",
        in_array('postal_code', $availableUserColumns, true) ? "postal_code" : "NULL AS postal_code"
    ];

    $userQuery = "SELECT fname, lname, email, contact_num, " . implode(", ", $shippingSelectParts) . " FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($connection, $userQuery);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($user_data) {
        $saved_shipping_info = mincBuildShippingData(
            $user_data['address'] ?? '',
            $user_data['barangay'] ?? '',
            $user_data['city'] ?? 'Angeles City',
            $user_data['province'] ?? 'Pampanga',
            $user_data['postal_code'] ?? null
        );
    }
}

$has_saved_shipping_info = false;
if (is_array($saved_shipping_info)) {
    $has_saved_shipping_info =
        mb_strlen((string)$saved_shipping_info['address']) >= 10 &&
        !empty($saved_shipping_info['has_valid_barangay']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - MinC Computer Parts</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/ca30ddfff9.js" crossorigin="anonymous"></script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .hero-gradient {
            background: linear-gradient(135deg, #08415c 0%, #0a5273 50%, #08415c 100%);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(8, 65, 92, 0.4);
        }

        .btn-primary-custom:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .step {
            flex: 1;
            text-align: center;
            padding: 1rem;
            position: relative;
            min-width: 100px;
        }

        @media (max-width: 768px) {
            .step {
                flex: 0 1 calc(33.333% - 0.5rem);
                padding: 0.5rem;
            }
            .step .text-sm {
                font-size: 0.75rem;
            }
            .step-number {
                width: 32px;
                height: 32px;
                font-size: 0.875rem;
            }
        }

        .step::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e5e7eb;
            z-index: 0;
        }

        .step:first-child::before {
            left: 50%;
        }

        .step:last-child::before {
            right: 50%;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            z-index: 1;
            margin-bottom: 0.5rem;
        }

        .step.active .step-number {
            background: #08415c;
            color: white;
        }

        .step.completed .step-number {
            background: #10b981;
            color: white;
        }

        #loader {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
        }

        #loader.active {
            display: flex;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }
    </style>
</head>

<body class="bg-gray-50">

    <!-- Loader -->
    <div id="loader" class="flex items-center justify-center">
        <div class="text-center">
            <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-[#08415c] mx-auto mb-4"></div>
            <p class="text-[#08415c] font-semibold">Processing...</p>
        </div>
    </div>

    <!-- Navigation Component -->
    <?php include 'components/navbar.php'; ?>

    <!-- Checkout Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Progress Steps -->
        <div class="step-indicator mt-16 mb-8">
            <div class="step active" id="step1-indicator">
                <div class="step-number">1</div>
                <div class="text-sm font-medium">Contact Info</div>
            </div>
            <div class="step" id="step2-indicator">
                <div class="step-number">2</div>
                <div class="text-sm font-medium">Shipping</div>
            </div>
            <div class="step" id="step3-indicator">
                <div class="step-number">3</div>
                <div class="text-sm font-medium">Payment</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Checkout Form -->
            <div class="lg:col-span-2">
                <form id="checkoutForm" class="bg-white rounded-xl shadow-lg p-6">
                    
                    <!-- Step 1: Contact Information -->
                    <div id="step1" class="form-section active">
                        <h2 class="text-2xl font-bold text-[#08415c] mb-6">Contact Information</h2>
                        
                        <div class="mb-6 p-4 bg-blue-50 border border-blue-100 rounded-lg text-sm text-gray-700">
                            <p><i class="fas fa-envelope text-[#08415c] mr-2"></i>Your email is used for order, payment, and cancellation notifications.</p>
                            <p class="mt-2"><i class="fas fa-phone text-[#08415c] mr-2"></i>Contact number is required before the order can be submitted.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">First Name *</label>
                                <input type="text" id="firstName" required 
                                       minlength="2" maxlength="50"
                                       value="<?php echo $user_data ? htmlspecialchars($user_data['fname']) : ''; ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Last Name *</label>
                                <input type="text" id="lastName" required 
                                       minlength="2" maxlength="50"
                                       value="<?php echo $user_data ? htmlspecialchars($user_data['lname']) : ''; ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-gray-700 font-medium mb-2">Email Address *</label>
                            <input type="email" id="email" required 
                                   value="<?php echo $user_data ? htmlspecialchars($user_data['email']) : ''; ?>"
                                   <?php echo $user_data ? 'readonly' : ''; ?>
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c] <?php echo $user_data ? 'bg-gray-50' : ''; ?>">
                            <p class="text-sm text-gray-500 mt-1">Order confirmation will be sent to this email</p>
                        </div>

                        <div class="mt-4">
                            <label class="block text-gray-700 font-medium mb-2">Phone Number *</label>
                            <input type="tel" id="phone" required 
                                   minlength="11" maxlength="13"
                                   value="<?php echo $user_data && $user_data['contact_num'] ? htmlspecialchars($user_data['contact_num']) : ''; ?>"
                                   placeholder="09XX XXX XXXX"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="button" onclick="nextStep(2)" class="btn-primary-custom text-white px-8 py-3 rounded-lg font-semibold">
                                Continue to Shipping
                                <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Shipping Information -->
                    <div id="step2" class="form-section">
                        <h2 class="text-2xl font-bold text-[#08415c] mb-6">Delivery Method</h2>

                        <?php if (!$user_id): ?>
                        <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Guest checkout supports <strong>Pickup + COD only</strong>. Please sign in to use shipping and online payment methods.
                        </div>
                        <?php endif; ?>

                        <div class="mb-6 space-y-3">
                            <label id="shippingOptionLabel" class="block p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-[#08415c] transition" onclick="toggleDeliveryMethod('shipping')">
                                <input type="radio" name="deliveryMethod" value="shipping" checked class="mr-3" onchange="toggleDeliveryFields()">
                                <span class="font-semibold"><i class="fas fa-truck text-blue-600 mr-2"></i>Delivery (Shipping)</span>
                                <p class="text-sm text-gray-600 ml-8 mt-1">Get your order delivered to your address</p>
                            </label>
                            <p class="text-xs text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 ml-8">
                                Free shipping for orders over ₱<?php echo number_format($free_shipping_threshold, 2); ?>. Orders below that have a ₱<?php echo number_format($standard_shipping_fee, 2); ?> shipping fee.
                            </p>
                            <p class="text-xs text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 ml-8">
                                <?php echo htmlspecialchars($shipping_coverage_note); ?>
                            </p>

                            <label id="pickupOptionLabel" class="block p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-[#08415c] transition" onclick="toggleDeliveryMethod('pickup')">
                                <input type="radio" name="deliveryMethod" value="pickup" class="mr-3" onchange="toggleDeliveryFields()">
                                <span class="font-semibold"><i class="fas fa-store text-green-600 mr-2"></i>Pickup</span>
                                <p class="text-sm text-gray-600 ml-8 mt-1">Pick up your order at our store (No shipping fee)</p>
                            </label>
                        </div>

                        <div id="shippingFields" class="block">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4 mt-6">Shipping Address</h3>

                            <?php if ($user_id): ?>
                            <div id="shippingAddressChooser" class="mb-4 space-y-3">
                                <label id="savedAddressOptionLabel" class="block p-4 border border-gray-300 rounded-lg cursor-pointer hover:border-[#08415c] transition">
                                    <input type="radio" name="shippingInfoMode" value="saved" class="mr-2"
                                           <?php echo $has_saved_shipping_info ? 'checked' : 'disabled'; ?>>
                                    <span class="font-semibold text-gray-800">Use my current delivery information</span>
                                </label>

                                <div id="savedAddressCard" class="<?php echo $has_saved_shipping_info ? '' : 'hidden'; ?> p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars(($user_data['fname'] ?? '') . ' ' . ($user_data['lname'] ?? '')); ?></p>
                                            <p class="text-gray-700"><?php echo htmlspecialchars($saved_shipping_info['address'] ?? ''); ?></p>
                                            <p class="text-sm text-gray-500 mt-2">Detected delivery area: <?php echo htmlspecialchars(($saved_shipping_info['barangay'] ?? '') . ', ' . ($saved_shipping_info['city'] ?? '') . ', ' . ($saved_shipping_info['province'] ?? '')); ?></p>
                                            <?php if (!empty($saved_shipping_info['postal_code'])): ?>
                                            <p class="text-sm text-gray-500">Postal code: <?php echo htmlspecialchars($saved_shipping_info['postal_code']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" id="changeShippingInfoBtn" class="text-[#08415c] text-sm font-semibold hover:underline">Change</button>
                                    </div>
                                </div>

                                <label class="block p-4 border border-gray-300 rounded-lg cursor-pointer hover:border-[#08415c] transition">
                                    <input type="radio" name="shippingInfoMode" value="new" class="mr-2"
                                           <?php echo !$has_saved_shipping_info ? 'checked' : ''; ?>>
                                    <span class="font-semibold text-gray-800">Add a new delivery address</span>
                                </label>
                            </div>
                            <?php endif; ?>

                            <div id="manualShippingFields" class="<?php echo ($user_id && $has_saved_shipping_info) ? 'hidden' : ''; ?>">
                                <div class="mb-4">
                                    <label class="block text-gray-700 font-medium mb-2">Complete Address *</label>
                                    <textarea id="address" rows="3"
                                              minlength="10" maxlength="255"
                                              placeholder="House/Unit No., Street, Barangay, Angeles City, Pampanga"
                                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]"></textarea>
                                    <p class="mt-2 text-xs text-gray-500">Write the full delivery address in one field, or choose from the location options below. Any location changes update the address automatically.</p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">Barangay</label>
                                        <select id="shippingBarangay"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c] bg-white">
                                            <option value="">Select barangay</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">City</label>
                                        <select id="shippingCity"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c] bg-white">
                                            <option value="">Select city</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-medium mb-2">Province</label>
                                        <select id="shippingProvince"
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c] bg-white">
                                            <option value="">Select province</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="block text-gray-700 font-medium mb-2">Postal Code</label>
                                    <input type="text" id="postalCode"
                                           inputmode="numeric"
                                           maxlength="4"
                                           pattern="^\d{4}$"
                                           oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)"
                                           placeholder="eg. 2019"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                                </div>
                            </div>

                            <div class="mt-6">
                                <label class="block text-gray-700 font-medium mb-2">Delivery Notes (Optional)</label>
                                <textarea id="notes" rows="2"
                                          maxlength="500"
                                          placeholder="Special instructions for delivery..."
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]"></textarea>
                            </div>
                        </div>

                        <div id="pickupFields" class="hidden">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4 mt-6">Pickup Information</h3>
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-gray-700 font-medium mb-2">
                                    <i class="fas fa-map-marker-alt text-blue-600 mr-2"></i>MinC Auto Supply Store
                                </p>
                                <p class="text-sm text-gray-600">Address: 1144 Jake Gonzales Blvd, Angeles, 2009 Pampanga</p>
                                <p class="text-sm text-gray-600">Phone: (+63) 908-819-3464</p>
                                <p class="text-sm text-gray-600 mt-2">Business Hours: Mon-Sat 8:30 AM - 6:30 PM</p>
                            </div>
                            <div class="mt-4">
                                <label class="block text-gray-700 font-medium mb-2">Preferred Pickup Date *</label>
                                <input type="date" id="pickupDate" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                                <p class="text-sm text-gray-500 mt-1">Select a date within the next 7 days</p>
                            </div>
                            <div class="mt-4">
                                <label class="block text-gray-700 font-medium mb-2">Preferred Pickup Time *</label>
                                <select id="pickupTime" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                                    <option value="">Select pickup time</option>
                                    <option value="09:00-11:00">9:00 AM - 11:00 AM</option>
                                    <option value="11:00-01:00">11:00 AM - 1:00 PM</option>
                                    <option value="01:00-03:00">1:00 PM - 3:00 PM</option>
                                    <option value="03:00-05:00">3:00 PM - 5:00 PM</option>
                                    <option value="05:00-06:00">5:00 PM - 6:00 PM</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-between">
                            <button type="button" onclick="prevStep(1)" class="bg-gray-100 text-gray-700 px-8 py-3 rounded-lg font-semibold hover:bg-gray-200 transition">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back
                            </button>
                            <button type="button" onclick="nextStep(3)" class="btn-primary-custom text-white px-8 py-3 rounded-lg font-semibold">
                                Continue to Payment
                                <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Payment Method -->
                    <div id="step3" class="form-section">
                        <h2 class="text-2xl font-bold text-[#08415c] mb-6">Payment Method</h2>

                        <div class="space-y-4">
                            <label class="block p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-[#08415c] transition">
                                <input type="radio" name="paymentMethod" value="cod" checked class="mr-3">
                                <span class="font-semibold"><i class="fas fa-money-bill-wave text-green-600 mr-2"></i>Cash on Delivery (COD)</span>
                                <p class="text-sm text-gray-600 ml-8 mt-1">Pay when you receive your order</p>
                            </label>

                            <?php if (!empty($payment_config['bpi']['enabled'])): ?>
                            <label id="bpiOptionLabel" class="block p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-[#08415c] transition">
                                <input type="radio" name="paymentMethod" value="bpi" class="mr-3">
                                <span class="font-semibold"><i class="fas fa-building-columns text-rose-600 mr-2"></i>BPI Bank Transfer</span>
                                <p class="text-sm text-gray-600 ml-8 mt-1">Scan the BPI QR code or transfer manually to the BPI account</p>
                            </label>
                            <?php endif; ?>

                            <?php if (!empty($payment_config['gcash']['enabled'])): ?>
                            <label id="gcashOptionLabel" class="block p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-[#08415c] transition">
                                <input type="radio" name="paymentMethod" value="gcash" class="mr-3">
                                <span class="font-semibold"><i class="fas fa-mobile-screen-button text-blue-500 mr-2"></i>GCash</span>
                                <p class="text-sm text-gray-600 ml-8 mt-1">Scan the GCash QR code or send to the GCash wallet</p>
                            </label>
                            <?php endif; ?>
                        </div>

                        <div id="electronicPaymentPanel" class="hidden mt-6 p-5 bg-slate-50 border border-slate-200 rounded-xl">
                            <div class="flex items-start gap-3">
                                <div class="mt-1 text-[#08415c]">
                                    <i class="fas fa-receipt text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-[#08415c]">Complete Payment Before Confirmation</h3>
                                    <p class="text-sm text-gray-600 mt-1">BPI and GCash payments require a payment reference and proof of payment before the order can be confirmed by the admin account.</p>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-4">
                                <div class="payment-method-card hidden rounded-xl border border-slate-200 bg-white p-5" data-payment-card="bpi">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-slate-500 mb-1">BPI Payment</p>
                                            <p class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($payment_config['bpi']['label'] ?? 'BPI Bank Transfer'); ?></p>
                                        </div>
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 border border-rose-100">Scan QR or Transfer</span>
                                    </div>
                                    <p class="mt-3 text-sm text-gray-600"><?php echo htmlspecialchars($payment_config['bpi']['instructions'] ?? 'Scan the BPI QR code or transfer manually, then upload your proof of payment.'); ?></p>
                                    <div class="mt-4 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_220px] gap-5 items-start">
                                        <div class="space-y-3">
                                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
                                                <p class="text-xs uppercase tracking-wide text-slate-500 mb-2">Account Details</p>
                                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($payment_config['bpi']['bank_name'] ?? 'BPI'); ?></p>
                                                <p class="text-sm text-gray-700 mt-1">Account Name: <?php echo htmlspecialchars($payment_config['bpi']['account_name'] ?? 'Update BPI Account Name'); ?></p>
                                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                                    <p class="text-sm text-gray-700">Account Number: <span class="font-semibold"><?php echo htmlspecialchars($payment_config['bpi']['account_number'] ?? 'Update BPI Account Number'); ?></span></p>
                                                    <button type="button" class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 transition" data-copy-value="<?php echo htmlspecialchars($payment_config['bpi']['account_number'] ?? '', ENT_QUOTES); ?>">
                                                        <i class="fas fa-copy"></i>Copy
                                                    </button>
                                                </div>
                                                <p class="text-sm text-gray-700 mt-1">Branch: <?php echo htmlspecialchars($payment_config['bpi']['branch'] ?? 'Update BPI Branch'); ?></p>
                                            </div>
                                            <?php if (!empty($payment_config['bpi']['qr_link'])): ?>
                                                <a href="<?php echo htmlspecialchars($payment_config['bpi']['qr_link']); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-sm font-semibold text-[#08415c] hover:text-[#0a5273]">
                                                    <i class="fas fa-arrow-up-right-from-square"></i>
                                                    <?php echo htmlspecialchars($payment_config['bpi']['qr_link_label'] ?? 'Open BPI Payment Link'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if (!empty($payment_config['bpi']['qr_image_url'])): ?>
                                                <button type="button" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 hover:border-[#08415c] transition text-left" data-qr-preview-url="<?php echo htmlspecialchars($payment_config['bpi']['qr_image_url']); ?>" data-qr-preview-title="BPI QR Code">
                                                    <img src="<?php echo htmlspecialchars($payment_config['bpi']['qr_image_url']); ?>" alt="BPI QR Code" class="w-full rounded-lg bg-white object-contain">
                                                    <p class="mt-3 text-xs text-slate-500">Tap to enlarge. If you are paying on the same phone, open the full image and use your banking app's scan-from-gallery feature.</p>
                                                </button>
                                            <?php else: ?>
                                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                                                    <p class="font-semibold text-slate-800">BPI QR image not configured yet</p>
                                                    <p class="mt-2">Add your QR image file at <span class="font-mono text-xs">Assets/images/payments/bpi-qr.png</span> or update <span class="font-mono text-xs">config/payment_config.php</span>.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="payment-method-card hidden rounded-xl border border-slate-200 bg-white p-5" data-payment-card="gcash">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-slate-500 mb-1">GCash Payment</p>
                                            <p class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($payment_config['gcash']['label'] ?? 'GCash'); ?></p>
                                        </div>
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 border border-blue-100">Scan QR or Send</span>
                                    </div>
                                    <p class="mt-3 text-sm text-gray-600"><?php echo htmlspecialchars($payment_config['gcash']['instructions'] ?? 'Scan the GCash QR code or send payment manually, then upload your proof of payment.'); ?></p>
                                    <div class="mt-4 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_220px] gap-5 items-start">
                                        <div class="space-y-3">
                                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
                                                <p class="text-xs uppercase tracking-wide text-slate-500 mb-2">Wallet Details</p>
                                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($payment_config['gcash']['account_name'] ?? 'Update GCash Account Name'); ?></p>
                                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                                    <p class="text-sm text-gray-700">Number: <span class="font-semibold"><?php echo htmlspecialchars($payment_config['gcash']['account_number'] ?? 'Update GCash Number'); ?></span></p>
                                                    <button type="button" class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 transition" data-copy-value="<?php echo htmlspecialchars($payment_config['gcash']['account_number'] ?? '', ENT_QUOTES); ?>">
                                                        <i class="fas fa-copy"></i>Copy
                                                    </button>
                                                </div>
                                            </div>
                                            <?php if (!empty($payment_config['gcash']['qr_link'])): ?>
                                                <a href="<?php echo htmlspecialchars($payment_config['gcash']['qr_link']); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-sm font-semibold text-[#08415c] hover:text-[#0a5273]">
                                                    <i class="fas fa-arrow-up-right-from-square"></i>
                                                    <?php echo htmlspecialchars($payment_config['gcash']['qr_link_label'] ?? 'Open GCash Payment Link'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if (!empty($payment_config['gcash']['qr_image_url'])): ?>
                                                <button type="button" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 hover:border-[#08415c] transition text-left" data-qr-preview-url="<?php echo htmlspecialchars($payment_config['gcash']['qr_image_url']); ?>" data-qr-preview-title="GCash QR Code">
                                                    <img src="<?php echo htmlspecialchars($payment_config['gcash']['qr_image_url']); ?>" alt="GCash QR Code" class="w-full rounded-lg bg-white object-contain">
                                                    <p class="mt-3 text-xs text-slate-500">Tap to enlarge. If you are paying on the same phone, open the full image and use GCash scan-from-gallery.</p>
                                                </button>
                                            <?php else: ?>
                                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                                                    <p class="font-semibold text-slate-800">GCash QR image not configured yet</p>
                                                    <p class="mt-2">Add your QR image file at <span class="font-mono text-xs">Assets/images/payments/gcash-qr.png</span> or update <span class="font-mono text-xs">config/payment_config.php</span>.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-gray-700 font-medium mb-2" id="paymentReferenceLabel">Payment Reference *</label>
                                <input type="text" id="paymentReference" maxlength="120" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]" placeholder="Enter the reference number shown by your bank or wallet">
                            </div>

                            <div class="mt-4">
                                <label class="block text-gray-700 font-medium mb-2">Proof of Payment *</label>
                                <input type="file" id="paymentProof" accept=".jpg,.jpeg,.png,.webp,.pdf" class="w-full px-4 py-3 border border-dashed border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                                <p class="text-xs text-gray-500 mt-2">Accepted files: JPG, PNG, WEBP, or PDF up to 5MB. The uploaded proof stays attached to the order even after confirmation.</p>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg space-y-2 text-sm text-gray-700">
                            <p><i class="fas fa-info-circle text-yellow-600 mr-2"></i><strong>Confirm Order</strong> means the admin reviewed your payment proof and approved the order for processing.</p>
                            <p><i class="fas fa-money-check text-yellow-600 mr-2"></i><strong>Complete Payment</strong> is used by staff when recording final payment collection, typically for COD.</p>
                            <p><i class="fas fa-times-circle text-yellow-600 mr-2"></i><strong>Cancel</strong> in the order confirmation window just closes the prompt so you can keep editing checkout details.</p>
                            <p><i class="fas fa-arrow-right-arrow-left text-yellow-600 mr-2"></i>BPI / GCash flow: Scan QR or send payment -> enter the payment reference -> attach proof -> submit order -> admin confirms payment -> order is prepared -> order is completed with receipt attached.</p>
                        </div>

                        <div class="mt-6 flex justify-between">
                            <button type="button" onclick="prevStep(2)" class="bg-gray-100 text-gray-700 px-8 py-3 rounded-lg font-semibold hover:bg-gray-200 transition">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back
                            </button>
                            <button type="submit" id="placeOrderBtn" class="btn-primary-custom text-white px-8 py-3 rounded-lg font-semibold">
                                <i class="fas fa-check mr-2"></i>
                                Submit Order
                            </button>
                        </div>
                    </div>

                </form>
            </div>

            <!-- Order Summary (Sticky) -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-6 sticky top-4">
                    <h3 class="text-xl font-bold text-[#08415c] mb-4">Order Summary</h3>
                    
                    <div id="orderItems" class="space-y-3 mb-4 max-h-60 overflow-y-auto">
                        <!-- Will be populated by JavaScript -->
                    </div>

                    <hr class="my-4">

                    <div class="space-y-2">
                        <div class="flex justify-between text-gray-700">
                            <span>Subtotal:</span>
                            <span class="font-semibold">₱<span id="summarySubtotal">0.00</span></span>
                        </div>
                        <div class="flex justify-between text-gray-700">
                            <span>Shipping:</span>
                            <span class="font-semibold">₱<span id="summaryShipping">0.00</span></span>
                        </div>
                        <hr class="my-2">
                        <div class="flex justify-between text-lg font-bold text-[#08415c]">
                            <span>Total:</span>
                            <span>₱<span id="summaryTotal">0.00</span></span>
                        </div>
                    </div>

                    <div class="mt-6 p-3 bg-green-50 rounded-lg">
                        <p class="text-sm text-green-800">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Your payment information is secure
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="qrPreviewModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 p-4">
        <div class="w-full max-w-xl rounded-2xl bg-white p-4 shadow-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <h3 id="qrPreviewTitle" class="text-lg font-bold text-[#08415c]">Payment QR Code</h3>
                <button type="button" id="closeQrPreviewModal" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="pt-4">
                <img id="qrPreviewImage" src="" alt="Payment QR code" class="mx-auto max-h-[70vh] w-full rounded-xl bg-slate-50 object-contain">
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600">Open this image full-screen if you need to scan it from another device or import it from your gallery.</p>
                    <a id="qrPreviewOpenLink" href="#" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                        <i class="fas fa-arrow-up-right-from-square"></i>
                        Open Image
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal (if needed) -->
    <div id="loginModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 relative">
            <button onclick="closeLoginModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>

            <h2 class="text-3xl font-bold mb-6 text-[#08415c]">Sign In</h2>
            <form onsubmit="handleLogin(event)">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Email</label>
                    <input type="email" id="loginEmail" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Password</label>
                    <input type="password" id="loginPassword" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#08415c]">
                </div>
                <button type="submit" class="w-full btn-primary-custom text-white py-3 rounded-lg font-semibold">
                    Login
                </button>
            </form>
        </div>
    </div>

    <script>
        // Global variables
        let cartItems = [];
        let subtotal = 0;
        const SHIPPING_FEE = <?php echo json_encode($standard_shipping_fee); ?>;
        const FREE_SHIPPING_THRESHOLD = <?php echo json_encode($free_shipping_threshold); ?>;
        const IS_GUEST_CHECKOUT = false;
        const IS_BUY_NOW_CHECKOUT = <?php echo $buy_now_item ? 'true' : 'false'; ?>;
        const BUY_NOW_ITEM = <?php echo $buy_now_item ? json_encode($buy_now_item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null'; ?>;
        const SAVED_SHIPPING = <?php echo $saved_shipping_info ? json_encode($saved_shipping_info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null'; ?>;
        const HAS_SAVED_SHIPPING = <?php echo $has_saved_shipping_info ? 'true' : 'false'; ?>;
        const PAYMENT_CONFIG = <?php echo json_encode($payment_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const ANGELES_CITY_BARANGAYS = Array.isArray(window.MINC_ALLOWED_BARANGAYS) ? window.MINC_ALLOWED_BARANGAYS.slice() : [];
        const FIELD_LIMITS = {
            firstName: { min: 2, max: 50 },
            lastName: { min: 2, max: 50 },
            address: { min: 10, max: 255 },
            barangay: { min: 2, max: 120 },
            city: { min: 2, max: 100 },
            province: { min: 2, max: 100 },
            notesMax: 500
        };
        let currentStep = 1;
        let checkoutShippingControls = null;

        // Format currency
        function formatPeso(amount) {
            return parseFloat(amount).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Escape HTML
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

        function hasSavedShippingData() {
            return !IS_GUEST_CHECKOUT && HAS_SAVED_SHIPPING && SAVED_SHIPPING;
        }

        function getSelectedShippingMode() {
            if (IS_GUEST_CHECKOUT) return 'new';
            const selected = document.querySelector('input[name="shippingInfoMode"]:checked');
            return selected ? selected.value : (hasSavedShippingData() ? 'saved' : 'new');
        }

        function isWithinLength(value, min, max) {
            const len = (value || '').length;
            return len >= min && len <= max;
        }

        function validatePostalCode(value) {
            if (!value) return true;
            if (!/^\d{4}$/.test(value)) return false;
            const numeric = Number(value);
            return numeric >= 2000 && numeric <= 2100;
        }

        function paymentMethodRequiresProof(paymentMethod) {
            return ['bpi', 'bank_transfer', 'gcash', 'paymaya'].includes(String(paymentMethod || '').toLowerCase());
        }

        function getShippingParser() {
            return typeof window.mincParseShippingAddress === 'function'
                ? window.mincParseShippingAddress
                : null;
        }

        function getCurrentPaymentMethod() {
            const selected = document.querySelector('input[name="paymentMethod"]:checked');
            return selected ? selected.value : 'cod';
        }

        function getPaymentReferenceLabel(paymentMethod) {
            const method = String(paymentMethod || '').toLowerCase();
            if (method === 'bpi' || method === 'bank_transfer') {
                return PAYMENT_CONFIG?.bpi?.reference_label || 'BPI Reference Number';
            }
            if (method === 'gcash') {
                return PAYMENT_CONFIG?.gcash?.reference_label || 'GCash Reference Number';
            }
            if (method === 'paymaya') {
                return 'Maya Reference Number';
            }
            return 'Payment Reference';
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

        async function copyPaymentValue(value, successLabel) {
            const trimmedValue = String(value || '').trim();
            if (!trimmedValue || /^update /i.test(trimmedValue)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Payment Detail Not Configured',
                    text: 'Update the payment account details first before using copy.',
                    confirmButtonColor: '#08415c'
                });
                return;
            }

            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(trimmedValue);
                } else {
                    const tempInput = document.createElement('input');
                    tempInput.value = trimmedValue;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Copied',
                    text: `${successLabel} copied to clipboard.`,
                    timer: 1400,
                    showConfirmButton: false
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Copy Failed',
                    text: 'Unable to copy the payment detail on this browser.',
                    confirmButtonColor: '#08415c'
                });
            }
        }

        function openQrPreviewModal(imageUrl, title) {
            const modal = document.getElementById('qrPreviewModal');
            const image = document.getElementById('qrPreviewImage');
            const titleNode = document.getElementById('qrPreviewTitle');
            const openLink = document.getElementById('qrPreviewOpenLink');

            if (!modal || !image || !titleNode || !openLink || !imageUrl) {
                return;
            }

            image.src = imageUrl;
            image.alt = title || 'Payment QR code';
            titleNode.textContent = title || 'Payment QR Code';
            openLink.href = imageUrl;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeQrPreviewModal() {
            const modal = document.getElementById('qrPreviewModal');
            const image = document.getElementById('qrPreviewImage');
            const openLink = document.getElementById('qrPreviewOpenLink');

            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');

            if (image) {
                image.src = '';
            }
            if (openLink) {
                openLink.href = '#';
            }
        }

        function initializePaymentActionButtons() {
            document.querySelectorAll('[data-copy-value]').forEach((button) => {
                button.addEventListener('click', () => {
                    copyPaymentValue(button.getAttribute('data-copy-value') || '', 'Payment detail');
                });
            });

            document.querySelectorAll('[data-qr-preview-url]').forEach((button) => {
                button.addEventListener('click', () => {
                    openQrPreviewModal(
                        button.getAttribute('data-qr-preview-url') || '',
                        button.getAttribute('data-qr-preview-title') || 'Payment QR Code'
                    );
                });
            });

            const closeButton = document.getElementById('closeQrPreviewModal');
            const modal = document.getElementById('qrPreviewModal');

            if (closeButton) {
                closeButton.addEventListener('click', closeQrPreviewModal);
            }
            if (modal) {
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        closeQrPreviewModal();
                    }
                });
            }
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeQrPreviewModal();
                }
            });
        }

        function updatePaymentGuidance() {
            const paymentMethod = getCurrentPaymentMethod();
            const requiresProof = paymentMethodRequiresProof(paymentMethod);
            const paymentPanel = document.getElementById('electronicPaymentPanel');
            const referenceLabel = document.getElementById('paymentReferenceLabel');
            const referenceInput = document.getElementById('paymentReference');
            const proofInput = document.getElementById('paymentProof');

            if (paymentPanel) {
                paymentPanel.classList.toggle('hidden', !requiresProof);
            }

            document.querySelectorAll('[data-payment-card]').forEach((card) => {
                card.classList.toggle('hidden', card.getAttribute('data-payment-card') !== paymentMethod);
            });

            if (referenceLabel) {
                referenceLabel.textContent = `${getPaymentReferenceLabel(paymentMethod)} *`;
            }

            if (referenceInput) {
                referenceInput.required = requiresProof;
            }

            if (proofInput) {
                proofInput.required = requiresProof;
            }
        }

        function getShippingDataFromForm() {
            const parseAddress = getShippingParser();
            const rawAddress = document.getElementById('address').value.trim();
            const selectedBarangay = document.getElementById('shippingBarangay').value.trim();
            const selectedCity = document.getElementById('shippingCity').value.trim() || 'Angeles City';
            const selectedProvince = document.getElementById('shippingProvince').value.trim() || 'Pampanga';
            const parsedAddress = parseAddress
                ? parseAddress(rawAddress, selectedCity, selectedProvince)
                : { address: rawAddress, barangay: selectedBarangay, city: selectedCity, province: selectedProvince, hasValidBarangay: Boolean(selectedBarangay) };

            return {
                address: parsedAddress.address || rawAddress,
                barangay: parsedAddress.barangay || selectedBarangay,
                city: parsedAddress.city || selectedCity,
                province: parsedAddress.province || selectedProvince,
                hasValidBarangay: Boolean(parsedAddress.hasValidBarangay || selectedBarangay),
                postal_code: document.getElementById('postalCode').value.trim()
            };
        }

        function getSelectedShippingData() {
            if (hasSavedShippingData() && getSelectedShippingMode() === 'saved') {
                return {
                    address: (SAVED_SHIPPING.address || '').trim(),
                    barangay: (SAVED_SHIPPING.barangay || '').trim(),
                    city: (SAVED_SHIPPING.city || '').trim(),
                    province: (SAVED_SHIPPING.province || '').trim(),
                    hasValidBarangay: Boolean(SAVED_SHIPPING.has_valid_barangay || SAVED_SHIPPING.barangay),
                    postal_code: (SAVED_SHIPPING.postal_code || '').trim()
                };
            }
            return getShippingDataFromForm();
        }

        function applyShippingInfoMode() {
            const manualFields = document.getElementById('manualShippingFields');
            const savedCard = document.getElementById('savedAddressCard');
            const useSaved = hasSavedShippingData() && getSelectedShippingMode() === 'saved';

            if (manualFields) {
                manualFields.classList.toggle('hidden', useSaved);
            }
            if (savedCard) {
                savedCard.classList.toggle('hidden', !useSaved);
            }

            if (!useSaved && checkoutShippingControls && typeof checkoutShippingControls.syncFromAddress === 'function') {
                checkoutShippingControls.syncFromAddress();
            }
        }

        function initializeShippingAddressChooser() {
            if (IS_GUEST_CHECKOUT) return;

            const modeInputs = document.querySelectorAll('input[name="shippingInfoMode"]');
            modeInputs.forEach((input) => {
                input.addEventListener('change', applyShippingInfoMode);
            });

            const changeBtn = document.getElementById('changeShippingInfoBtn');
            if (changeBtn) {
                changeBtn.addEventListener('click', function() {
                    const newModeRadio = document.querySelector('input[name="shippingInfoMode"][value="new"]');
                    if (newModeRadio) {
                        newModeRadio.checked = true;
                        applyShippingInfoMode();
                        const addressField = document.getElementById('address');
                        if (addressField) {
                            addressField.focus();
                        }
                    }
                });
            }

            applyShippingInfoMode();
        }

        // Load cart data
        async function loadCart() {
            if (IS_BUY_NOW_CHECKOUT && BUY_NOW_ITEM) {
                cartItems = [BUY_NOW_ITEM];
                subtotal = parseFloat(BUY_NOW_ITEM.item_total || 0);
                displayOrderSummary();
                return;
            }

            try {
                const response = await fetch('../backend/cart/cart_get.php');
                const data = await response.json();

                if (data.success) {
                    cartItems = data.cart_items || [];
                    subtotal = parseFloat(data.subtotal) || 0;
                    
                    displayOrderSummary();
                } else {
                    window.location.href = 'user-cart.php';
                }
            } catch (error) {
                console.error('Error loading cart:', error);
                window.location.href = 'user-cart.php';
            }
        }

        // Display order summary
        function displayOrderSummary() {
            const container = document.getElementById('orderItems');
            
            container.innerHTML = cartItems.map(item => `
                <div class="flex gap-3">
                    <img src="../Assets/images/products/${item.product_image || 'placeholder.svg'}" 
                         alt="${escapeHtml(item.product_name)}" 
                         class="w-16 h-16 object-cover rounded"
                         onerror="this.src='../Assets/images/website-images/placeholder.svg'">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800">${escapeHtml(item.product_name)}</p>
                        <p class="text-xs text-gray-500">Qty: ${item.quantity}</p>
                        <p class="text-sm font-bold text-[#08415c]">₱${formatPeso(item.item_total)}</p>
                    </div>
                </div>
            `).join('');

            updateSummary();
        }

        // Update summary
        function updateSummary() {
            let shippingFee = 0;
            const deliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked').value;
            
            if (deliveryMethod === 'shipping') {
                shippingFee = subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_FEE;
            } else {
                shippingFee = 0; // No shipping fee for pickup
            }
            
            const total = subtotal + shippingFee;

            document.getElementById('summarySubtotal').textContent = formatPeso(subtotal);
            document.getElementById('summaryShipping').textContent = shippingFee === 0 ? 'FREE' : formatPeso(shippingFee);
            document.getElementById('summaryTotal').textContent = formatPeso(total);
        }

        // Next step
        function nextStep(step) {
            // Validate current step
            if (step === 2) {
                const firstName = document.getElementById('firstName').value.trim();
                const lastName = document.getElementById('lastName').value.trim();
                const email = document.getElementById('email').value.trim();
                const phone = document.getElementById('phone').value.trim();

                if (!firstName || !lastName || !email || !phone) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Missing Information',
                        text: 'Please fill in all required fields',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (!isWithinLength(firstName, FIELD_LIMITS.firstName.min, FIELD_LIMITS.firstName.max)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid First Name',
                        text: 'First name must be between 2 and 50 characters.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (!isWithinLength(lastName, FIELD_LIMITS.lastName.min, FIELD_LIMITS.lastName.max)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Last Name',
                        text: 'Last name must be between 2 and 50 characters.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                // Validate email
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Email',
                        text: 'Please enter a valid email address',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                // Validate phone (Philippine format): 09XXXXXXXXX, 63XXXXXXXXXX, +63XXXXXXXXXX
                const cleanedPhone = phone.replace(/[\s\-\(\)]/g, '');
                const phoneRegex = /^(09\d{9}|(\+?63)\d{10})$/;
                if (!phoneRegex.test(cleanedPhone)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Phone Number',
                        text: 'Please enter a valid mobile number (09XXXXXXXXX or +63XXXXXXXXXX)',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }
            }

            if (step === 3) {
                const deliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked').value;

                if (deliveryMethod === 'shipping') {
                    const shippingData = getSelectedShippingData();
                    const address = shippingData.address;
                    const city = shippingData.city;
                    const province = shippingData.province;
                    const barangay = shippingData.barangay;
                    const postalCode = shippingData.postal_code;

                    if (!address) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Missing Information',
                            text: 'Please enter your shipping address.',
                            confirmButtonColor: '#08415c'
                        });
                        return;
                    }

                    if (!isWithinLength(address, FIELD_LIMITS.address.min, FIELD_LIMITS.address.max)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Address',
                            text: 'Complete address must be between 10 and 255 characters.',
                            confirmButtonColor: '#08415c'
                        });
                        return;
                    }

                    if (!shippingData.hasValidBarangay) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Incomplete Shipping Address',
                            text: 'Include a valid Angeles City barangay in the shipping address.',
                            confirmButtonColor: '#08415c'
                        });
                        return;
                    }

                    if (!isWithinLength(barangay, FIELD_LIMITS.barangay.min, FIELD_LIMITS.barangay.max)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Barangay',
                            text: 'Barangay must be between 2 and 120 characters.',
                            confirmButtonColor: '#08415c'
                        });
                        return;
                    }

                    if (!isWithinLength(city, FIELD_LIMITS.city.min, FIELD_LIMITS.city.max)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid City',
                            text: 'City must be between 2 and 100 characters.',
                            confirmButtonColor: '#08415c'
                        });
                        return;
                    }

                    if (!isWithinLength(province, FIELD_LIMITS.province.min, FIELD_LIMITS.province.max)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Province',
                            text: 'Province must be between 2 and 100 characters.',
                            confirmButtonColor: '#08415c'
                        });
                        return;
                    }

                    if (!validatePostalCode(postalCode)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Postal Code',
                            text: 'Postal code must be a 4-digit value between 2000 and 2100.',
                            confirmButtonColor: '#08415c'
                        });
                        return;
                    }

                    if (province !== 'Pampanga' || city !== 'Angeles City' || !ANGELES_CITY_BARANGAYS.includes(barangay)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Shipping Location',
                            text: 'Shipping is only available in Angeles City, Pampanga barangays.',
                            confirmButtonColor: '#08415c'
                        });
                        return;
                    }
                } else if (deliveryMethod === 'pickup') {
                    const pickupDate = document.getElementById('pickupDate').value;
                    const pickupTime = document.getElementById('pickupTime').value;

                    if (!pickupDate || !pickupTime) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Missing Information',
                            text: 'Please select a pickup date and time',
                            confirmButtonColor: '#08415c'
                        });
                        return;
                    }
                }
            }

            // Update step indicators
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}-indicator`).classList.remove('active');
            document.getElementById(`step${currentStep}-indicator`).classList.add('completed');

            currentStep = step;

            document.getElementById(`step${currentStep}`).classList.add('active');
            document.getElementById(`step${currentStep}-indicator`).classList.add('active');

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Previous step
        function prevStep(step) {
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}-indicator`).classList.remove('active');

            currentStep = step;

            document.getElementById(`step${currentStep}`).classList.add('active');
            document.getElementById(`step${currentStep}-indicator`).classList.add('active');
            document.getElementById(`step${currentStep}-indicator`).classList.remove('completed');

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Toggle delivery method display
        function toggleDeliveryMethod(method) {
            const shippingFields = document.getElementById('shippingFields');
            const pickupFields = document.getElementById('pickupFields');

            if (method === 'pickup') {
                shippingFields.classList.add('hidden');
                pickupFields.classList.remove('hidden');
            } else {
                shippingFields.classList.remove('hidden');
                pickupFields.classList.add('hidden');
                applyShippingInfoMode();
                if (checkoutShippingControls && typeof checkoutShippingControls.syncFromAddress === 'function') {
                    checkoutShippingControls.syncFromAddress();
                }
                // Clear pickup validation
                document.getElementById('pickupDate').value = '';
                document.getElementById('pickupTime').value = '';
            }
        }

        // Toggle delivery fields based on radio selection
        function toggleDeliveryFields() {
            const deliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked').value;
            toggleDeliveryMethod(deliveryMethod);
            updateSummary();
        }

        // Handle checkout form submission
        document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const deliveryMethod = document.querySelector('input[name="deliveryMethod"]:checked').value;
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
            const requiresProof = paymentMethodRequiresProof(paymentMethod);
            const paymentReference = document.getElementById('paymentReference').value.trim();
            const paymentProofInput = document.getElementById('paymentProof');
            const paymentProofFile = paymentProofInput && paymentProofInput.files ? paymentProofInput.files[0] : null;

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const cleanedPhone = phone.replace(/[\s\-\(\)]/g, '');
            const phoneRegex = /^(09\d{9}|(\+?63)\d{10})$/;
            if (!firstName || !lastName || !email || !phone) {
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Information',
                    text: 'First name, last name, email, and contact number are required.',
                    confirmButtonColor: '#08415c'
                });
                return;
            }

            if (!isWithinLength(firstName, FIELD_LIMITS.firstName.min, FIELD_LIMITS.firstName.max)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid First Name',
                    text: 'First name must be between 2 and 50 characters.',
                    confirmButtonColor: '#08415c'
                });
                return;
            }

            if (!isWithinLength(lastName, FIELD_LIMITS.lastName.min, FIELD_LIMITS.lastName.max)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Last Name',
                    text: 'Last name must be between 2 and 50 characters.',
                    confirmButtonColor: '#08415c'
                });
                return;
            }

            if (!emailRegex.test(email)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Email',
                    text: 'Please enter a valid email address.',
                    confirmButtonColor: '#08415c'
                });
                return;
            }

            if (!phoneRegex.test(cleanedPhone)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Phone Number',
                    text: 'Please enter a valid mobile number (09XXXXXXXXX or +63XXXXXXXXXX).',
                    confirmButtonColor: '#08415c'
                });
                return;
            }

            const orderData = {
                customer: {
                    first_name: firstName,
                    last_name: lastName,
                    email: email,
                    phone: phone
                },
                delivery_method: deliveryMethod,
                payment_method: paymentMethod
            };

            let orderDetails = `
                <div class="text-left space-y-2">
                    <p><strong>Name:</strong> ${escapeHtml(firstName)} ${escapeHtml(lastName)}</p>
                    <p><strong>Email:</strong> ${escapeHtml(email)}</p>
                    <p><strong>Phone:</strong> ${escapeHtml(phone)}</p>
                    <p><strong>Delivery:</strong> ${deliveryMethod === 'pickup' ? 'Store Pickup' : 'Shipping'}</p>
            `;

            if (IS_BUY_NOW_CHECKOUT && BUY_NOW_ITEM) {
                orderData.buy_now = {
                    product_id: parseInt(BUY_NOW_ITEM.product_id, 10),
                    quantity: parseInt(BUY_NOW_ITEM.quantity, 10)
                };
            }

            if (deliveryMethod === 'shipping') {
                const shippingData = getSelectedShippingData();
                const address = shippingData.address;
                const city = shippingData.city;
                const province = shippingData.province;
                const barangay = shippingData.barangay;
                const postalCode = shippingData.postal_code;
                const notes = document.getElementById('notes').value.trim();

                if (!address) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Missing Shipping Information',
                        text: 'Complete shipping address is required.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (!isWithinLength(address, FIELD_LIMITS.address.min, FIELD_LIMITS.address.max)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Address',
                        text: 'Complete address must be between 10 and 255 characters.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (!shippingData.hasValidBarangay) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Incomplete Shipping Address',
                        text: 'Include a valid Angeles City barangay in the shipping address.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (!isWithinLength(barangay, FIELD_LIMITS.barangay.min, FIELD_LIMITS.barangay.max)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Barangay',
                        text: 'Barangay must be between 2 and 120 characters.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (!isWithinLength(city, FIELD_LIMITS.city.min, FIELD_LIMITS.city.max)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid City',
                        text: 'City must be between 2 and 100 characters.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (!isWithinLength(province, FIELD_LIMITS.province.min, FIELD_LIMITS.province.max)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Province',
                        text: 'Province must be between 2 and 100 characters.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (!validatePostalCode(postalCode)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Postal Code',
                        text: 'Postal code must be a 4-digit value between 2000 and 2100.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (province !== 'Pampanga' || city !== 'Angeles City' || !ANGELES_CITY_BARANGAYS.includes(barangay)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Shipping Location',
                        text: 'Shipping is only available in Angeles City, Pampanga barangays.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (notes.length > FIELD_LIMITS.notesMax) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Notes Too Long',
                        text: 'Delivery notes can be up to 500 characters only.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                orderData.shipping = {
                    address: address,
                    barangay: barangay,
                    city: city,
                    province: province,
                    postal_code: postalCode
                };
                orderData.notes = notes;

                orderDetails += `<p><strong>Shipping Address:</strong> ${escapeHtml(address)}${postalCode ? ` ${escapeHtml(postalCode)}` : ''}</p>`;
                orderDetails += `<p><strong>Shipping Fee:</strong> ${document.getElementById('summaryShipping').textContent === 'FREE' ? 'FREE' : `PHP ${document.getElementById('summaryShipping').textContent}`}</p>`;
                if (notes) {
                    orderDetails += `<p><strong>Delivery Notes:</strong> ${escapeHtml(notes)}</p>`;
                }
            } else {
                const pickupDate = document.getElementById('pickupDate').value;
                const pickupTime = document.getElementById('pickupTime').value;

                if (!pickupDate || !pickupTime) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Missing Pickup Schedule',
                        text: 'Pickup date and pickup time are required for store pickup.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                orderData.pickup_date = pickupDate;
                orderData.pickup_time = pickupTime;
                orderDetails += `<p><strong>Pickup Date:</strong> ${escapeHtml(pickupDate)}</p>`;
                orderDetails += `<p><strong>Pickup Time:</strong> ${escapeHtml(pickupTime)}</p>`;
            }

            if (requiresProof) {
                if (!paymentReference) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Missing Payment Reference',
                        text: 'Online payments require a payment reference before the order can be submitted.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (paymentReference.length > 120) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Reference Too Long',
                        text: 'Payment reference can be up to 120 characters only.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (!paymentProofFile) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Proof Required',
                        text: 'Please attach proof of payment before submitting the order.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (paymentProofFile.size > (5 * 1024 * 1024)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large',
                        text: 'Proof of payment must be 5MB or less.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                if (!/\.(jpe?g|png|webp|pdf)$/i.test(paymentProofFile.name || '')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Unsupported File Type',
                        text: 'Proof of payment must be JPG, PNG, WEBP, or PDF.',
                        confirmButtonColor: '#08415c'
                    });
                    return;
                }

                orderData.payment_reference = paymentReference;
                orderDetails += `<p><strong>Payment Method:</strong> ${escapeHtml(getPaymentMethodLabel(paymentMethod))}</p>`;
                orderDetails += `<p><strong>Payment Reference:</strong> ${escapeHtml(paymentReference)}</p>`;
                orderDetails += `<p><strong>Proof of Payment:</strong> Attached for admin review</p>`;
            } else {
                orderDetails += `<p><strong>Payment Method:</strong> Cash on Delivery</p>`;
                orderDetails += `<p class="text-sm text-gray-600">Payment will be collected by staff before the order is marked completed.</p>`;
            }

            orderDetails += `<p class="pt-3 text-lg"><strong>Total:</strong> PHP ${document.getElementById('summaryTotal').textContent}</p></div>`;

            const confirmResult = await Swal.fire({
                title: 'Submit Order?',
                html: orderDetails,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#08415c',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Submit Order',
                cancelButtonText: 'Keep Editing'
            });

            if (!confirmResult.isConfirmed) {
                return;
            }

            document.getElementById('loader').classList.add('active');
            document.getElementById('placeOrderBtn').disabled = true;

            try {
                const formData = new FormData();
                formData.append('customer', JSON.stringify(orderData.customer));
                formData.append('delivery_method', orderData.delivery_method);
                formData.append('payment_method', orderData.payment_method);

                if (orderData.shipping) {
                    formData.append('shipping', JSON.stringify(orderData.shipping));
                }
                if (orderData.buy_now) {
                    formData.append('buy_now', JSON.stringify(orderData.buy_now));
                }
                if (orderData.notes) {
                    formData.append('notes', orderData.notes);
                }
                if (orderData.pickup_date) {
                    formData.append('pickup_date', orderData.pickup_date);
                }
                if (orderData.pickup_time) {
                    formData.append('pickup_time', orderData.pickup_time);
                }
                if (orderData.payment_reference) {
                    formData.append('payment_reference', orderData.payment_reference);
                }
                if (requiresProof && paymentProofFile) {
                    formData.append('payment_proof', paymentProofFile);
                }

                const response = await fetch('../backend/checkout/process_order.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to submit order.');
                }

                Swal.fire({
                    icon: 'success',
                    title: requiresProof ? 'Order Submitted for Review' : 'Order Submitted Successfully',
                    html: `
                        <p class="mb-3">Order Number: <strong>${escapeHtml(data.order_number)}</strong></p>
                        <p>${requiresProof ? 'Your proof of payment is attached and waiting for admin review.' : 'A confirmation email has been sent to your account email.'}</p>
                    `,
                    confirmButtonColor: '#08415c',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = `order-success.php?order=${encodeURIComponent(data.order_number)}`;
                });
            } catch (error) {
                console.error('Checkout error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Order Failed',
                    text: error.message || 'An error occurred while placing your order. Please try again.',
                    confirmButtonColor: '#08415c'
                });
            } finally {
                document.getElementById('loader').classList.remove('active');
                document.getElementById('placeOrderBtn').disabled = false;
            }
        });
    // Login modal functions
    function openLoginModal() {
        document.getElementById('loginModal').classList.remove('hidden');
    }

    function closeLoginModal() {
        document.getElementById('loginModal').classList.add('hidden');
    }

    async function handleLogin(e) {
        e.preventDefault();
        
        const email = document.getElementById('loginEmail').value;
        const password = document.getElementById('loginPassword').value;
        
        Swal.fire({
            title: 'Logging in...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            const response = await fetch('../backend/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Login Successful!',
                    text: 'Redirecting...',
                    confirmButtonColor: '#08415c',
                    timer: 1000
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Login Failed',
                    text: data.message,
                    confirmButtonColor: '#08415c'
                });
            }
        } catch (error) {
            console.error('Login error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred during login.',
                confirmButtonColor: '#08415c'
            });
        }
    }

        function applyGuestCheckoutRestrictions() {
            if (!IS_GUEST_CHECKOUT) return;

        const shippingRadio = document.querySelector('input[name="deliveryMethod"][value="shipping"]');
        const pickupRadio = document.querySelector('input[name="deliveryMethod"][value="pickup"]');
        const nonCodPaymentMethods = document.querySelectorAll('input[name="paymentMethod"]:not([value="cod"])');
        const shippingLabel = document.getElementById('shippingOptionLabel');
        const nonCodLabels = [
            document.getElementById('bpiOptionLabel'),
            document.getElementById('gcashOptionLabel'),
        ];

        if (shippingRadio) {
            shippingRadio.checked = false;
            shippingRadio.disabled = true;
        }
        if (pickupRadio) {
            pickupRadio.checked = true;
        }
        if (shippingLabel) {
            shippingLabel.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
        }

        nonCodPaymentMethods.forEach((paymentInput) => {
            paymentInput.checked = false;
            paymentInput.disabled = true;
        });
        nonCodLabels.forEach((label) => {
            if (label) label.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
        });

        const codRadio = document.querySelector('input[name="paymentMethod"][value="cod"]');
        if (codRadio) codRadio.checked = true;

        toggleDeliveryMethod('pickup');
        updateSummary();
    }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            checkoutShippingControls = typeof window.mincInitializeShippingControls === 'function'
                ? window.mincInitializeShippingControls({
                    addressId: 'address',
                    barangayId: 'shippingBarangay',
                    cityId: 'shippingCity',
                    provinceId: 'shippingProvince'
                })
                : null;
            if (hasSavedShippingData()) {
                document.getElementById('address').value = SAVED_SHIPPING.address || '';
                document.getElementById('postalCode').value = SAVED_SHIPPING.postal_code || '';
                if (checkoutShippingControls && typeof checkoutShippingControls.syncFromAddress === 'function') {
                    checkoutShippingControls.syncFromAddress();
                }
            }
            document.querySelectorAll('input[name="paymentMethod"]').forEach((input) => {
                input.addEventListener('change', updatePaymentGuidance);
            });
            initializePaymentActionButtons();
            initializeShippingAddressChooser();
            loadCart();
            updatePaymentGuidance();
            applyGuestCheckoutRestrictions();
        });
</script>
<!-- Chat Bubble Component -->
<?php include 'components/chat_bubble.php'; ?>
</body>
</html>



