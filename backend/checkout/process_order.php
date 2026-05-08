<?php
session_start();
require_once '../../database/connect_database.php';
require_once '../order-management/order_workflow_helper.php';
require_once '../../library/EmailService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

function checkoutJsonError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function parseCheckoutRequestPayload() {
    $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));

    if (strpos($contentType, 'multipart/form-data') !== false || strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        $payload = $_POST;
        foreach (['customer', 'shipping', 'buy_now'] as $jsonField) {
            if (isset($payload[$jsonField]) && is_string($payload[$jsonField])) {
                $decoded = json_decode($payload[$jsonField], true);
                $payload[$jsonField] = is_array($decoded) ? $decoded : null;
            }
        }
        return $payload;
    }

    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput, true);
    return is_array($decoded) ? $decoded : null;
}

function buildOrderEmailBody($customerName, array $emailContext) {
    $orderNumber = htmlspecialchars((string)($emailContext['order_number'] ?? ''));
    $totalAmount = number_format((float)($emailContext['total_amount'] ?? 0), 2);
    $deliveryMethod = htmlspecialchars((string)($emailContext['delivery_method_label'] ?? 'Shipping'));
    $paymentMethod = htmlspecialchars((string)($emailContext['payment_method_label'] ?? 'COD'));
    $proofMessage = htmlspecialchars((string)($emailContext['proof_message'] ?? ''));
    $statusMessage = htmlspecialchars((string)($emailContext['status_message'] ?? ''));
    $reference = htmlspecialchars((string)($emailContext['payment_reference'] ?? ''));

    $referenceHtml = $reference !== ''
        ? '<p><strong>Payment Reference:</strong> ' . $reference . '</p>'
        : '';
    $proofHtml = $proofMessage !== ''
        ? '<p style="margin-top:12px;padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;color:#1e3a8a;">' . $proofMessage . '</p>'
        : '';

    return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; background: #f8fafc; color: #1f2937; }
                .container { max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; }
                .header { background: linear-gradient(135deg, #08415c 0%, #0a5273 100%); color: #ffffff; padding: 24px; }
                .content { padding: 24px; }
                .summary { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; margin-top: 16px; }
                .footer { font-size: 12px; color: #6b7280; padding: 0 24px 24px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2 style="margin:0 0 8px;">Order Update</h2>
                    <p style="margin:0;">MinC Auto Supply</p>
                </div>
                <div class="content">
                    <p>Hello <strong>' . htmlspecialchars($customerName) . '</strong>,</p>
                    <p>' . $statusMessage . '</p>
                    <div class="summary">
                        <p><strong>Order Number:</strong> ' . $orderNumber . '</p>
                        <p><strong>Delivery Method:</strong> ' . $deliveryMethod . '</p>
                        <p><strong>Payment Method:</strong> ' . $paymentMethod . '</p>
                        <p><strong>Total Amount:</strong> PHP ' . $totalAmount . '</p>
                        ' . $referenceHtml . '
                    </div>
                    ' . $proofHtml . '
                    <p style="margin-top: 20px;">We will continue sending updates to this email address.</p>
                </div>
                <div class="footer">
                    This is an automated message from MinC Auto Supply.
                </div>
            </div>
        </body>
        </html>';
}

$data = parseCheckoutRequestPayload();
if (!$data) {
    checkoutJsonError('Invalid checkout data.');
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    checkoutJsonError('Please sign in before placing an order.', 401);
}

$required = ['customer', 'payment_method', 'delivery_method'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        checkoutJsonError("Missing required field: {$field}");
    }
}

$delivery_method = strtolower(trim((string)$data['delivery_method']));
if (!in_array($delivery_method, ['shipping', 'pickup'], true)) {
    checkoutJsonError('Invalid delivery method.');
}

$payment_method = mincNormalizePaymentMethodKey($data['payment_method'] ?? '');
if ($payment_method === 'bank_transfer') {
    $payment_method = 'bpi';
}
$validPaymentMethods = ['cod', 'bpi', 'gcash', 'paymaya'];
if (!in_array($payment_method, $validPaymentMethods, true)) {
    checkoutJsonError('Invalid payment method.');
}

$requiresPaymentProof = mincPaymentMethodRequiresProof($payment_method);
$payment_reference = mincNormalizeWhitespace($data['payment_reference'] ?? '');
if ($requiresPaymentProof && $payment_reference === '') {
    checkoutJsonError('Payment reference is required for online payments.');
}
if ($payment_reference !== '' && mb_strlen($payment_reference) > 120) {
    checkoutJsonError('Payment reference can be up to 120 characters only.');
}

$customer = is_array($data['customer']) ? $data['customer'] : null;
if (!$customer) {
    checkoutJsonError('Invalid customer information.');
}

$requiredCustomer = ['first_name', 'last_name', 'email', 'phone'];
foreach ($requiredCustomer as $field) {
    if (empty($customer[$field])) {
        checkoutJsonError("Missing customer information: {$field}");
    }
}

$customer['first_name'] = ucwords(strtolower(mincNormalizeWhitespace($customer['first_name'])), " -'");
$customer['last_name'] = ucwords(strtolower(mincNormalizeWhitespace($customer['last_name'])), " -'");
$customer['email'] = filter_var(trim((string)$customer['email']), FILTER_SANITIZE_EMAIL);
$customer['phone'] = mincNormalizePhilippineMobile($customer['phone']);

if (!filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
    checkoutJsonError('Invalid email format.');
}
if ($customer['phone'] === null) {
    checkoutJsonError('Invalid phone number format. Use 09XXXXXXXXX or +63XXXXXXXXXX');
}
if (mb_strlen($customer['first_name']) < 2 || mb_strlen($customer['first_name']) > 50) {
    checkoutJsonError('First name must be between 2 and 50 characters.');
}
if (mb_strlen($customer['last_name']) < 2 || mb_strlen($customer['last_name']) > 50) {
    checkoutJsonError('Last name must be between 2 and 50 characters.');
}

$notesValue = mincNormalizeWhitespace($data['notes'] ?? '');
if ($notesValue !== '' && mb_strlen($notesValue) > 500) {
    checkoutJsonError('Delivery notes can be up to 500 characters only.');
}

if ($delivery_method === 'shipping') {
    if (!isset($data['shipping']) || !is_array($data['shipping'])) {
        checkoutJsonError('Shipping details are required.');
    }

    $shipping = mincBuildShippingData(
        $data['shipping']['address'] ?? '',
        $data['shipping']['barangay'] ?? '',
        $data['shipping']['city'] ?? 'Angeles City',
        $data['shipping']['province'] ?? 'Pampanga',
        $data['shipping']['postal_code'] ?? null
    );

    if ($shipping['address'] === '') {
        checkoutJsonError('Missing shipping information: address');
    }

    if (mb_strlen($shipping['address']) < 10 || mb_strlen($shipping['address']) > 255) {
        checkoutJsonError('Complete address must be between 10 and 255 characters.');
    }
    if (!$shipping['has_valid_barangay']) {
        checkoutJsonError('Include a valid Angeles City barangay in the shipping address.');
    }
    if (mb_strlen($shipping['barangay']) < 2 || mb_strlen($shipping['barangay']) > 120) {
        checkoutJsonError('Barangay must be between 2 and 120 characters.');
    }
    if (mb_strlen($shipping['city']) < 2 || mb_strlen($shipping['city']) > 100) {
        checkoutJsonError('City must be between 2 and 100 characters.');
    }
    if (mb_strlen($shipping['province']) < 2 || mb_strlen($shipping['province']) > 100) {
        checkoutJsonError('Province must be between 2 and 100 characters.');
    }

    if ($shipping['postal_code'] !== null && $shipping['postal_code'] !== '') {
        $postalCodeInt = (int)$shipping['postal_code'];
        $postalCodeValid = preg_match('/^\d{4}$/', $shipping['postal_code']) === 1
            && $postalCodeInt >= 2000
            && $postalCodeInt <= 2100;
        if (!$postalCodeValid) {
            checkoutJsonError('Invalid postal code. Must be between 2000 and 2100.');
        }
    } else {
        $shipping['postal_code'] = null;
    }

    if ($shipping['province'] !== 'Pampanga' || $shipping['city'] !== 'Angeles City' || !in_array($shipping['barangay'], mincAllowedShippingBarangays(), true)) {
        checkoutJsonError('Shipping is only available in Angeles City, Pampanga barangays.');
    }
} else {
    $shipping = [
        'address' => 'Pickup at Store',
        'barangay' => null,
        'city' => 'Pickup',
        'province' => 'Pickup',
        'postal_code' => null
    ];
}

$pickup_date = trim((string)($data['pickup_date'] ?? ''));
$pickup_time = trim((string)($data['pickup_time'] ?? ''));
if ($delivery_method === 'pickup' && ($pickup_date === '' || $pickup_time === '')) {
    checkoutJsonError('Pickup date and time are required for store pickup.');
}

$orderColumns = mincGetTableColumns($pdo, 'orders');
$requiredEnhancedColumns = ['delivery_method', 'payment_reference', 'payment_proof_path', 'payment_proof_uploaded_at', 'payment_review_notes', 'pickup_date', 'pickup_time', 'shipping_partner', 'receipt_path', 'cancel_reason'];
foreach ($requiredEnhancedColumns as $requiredColumn) {
    if (!in_array($requiredColumn, $orderColumns, true)) {
        checkoutJsonError('The order workflow schema is outdated. Please run SETUP_DATABASE.sql on the database before using checkout.');
    }
}

$uploadedPaymentProof = null;
$paymentProofRelativePath = null;
$storedPaymentProofAbsolutePath = null;

if ($requiresPaymentProof) {
    if (!isset($_FILES['payment_proof'])) {
        checkoutJsonError('Proof of payment is required for online payments.');
    }

    try {
        $uploadedPaymentProof = mincStoreUploadedDocument(
            $_FILES['payment_proof'],
            'Assets/images/payments',
            'payment_proof_' . date('YmdHis'),
            [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf'
            ],
            5 * 1024 * 1024
        );
        $paymentProofRelativePath = $uploadedPaymentProof['relative_path'];
        $storedPaymentProofAbsolutePath = mincProjectRootPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $paymentProofRelativePath);
    } catch (Exception $uploadError) {
        checkoutJsonError($uploadError->getMessage());
    }
}

try {
    $pdo->beginTransaction();

    $session_id = session_id();
    $is_buy_now_checkout = isset($data['buy_now']) && is_array($data['buy_now']);
    $cart_id = null;
    $checkout_items = [];

    if ($is_buy_now_checkout) {
        $buy_now_product_id = isset($data['buy_now']['product_id']) ? (int)$data['buy_now']['product_id'] : 0;
        $buy_now_quantity = isset($data['buy_now']['quantity']) ? (int)$data['buy_now']['quantity'] : 0;

        if ($buy_now_product_id <= 0 || $buy_now_quantity <= 0) {
            throw new Exception('Invalid buy now request.');
        }

        $stmt = $pdo->prepare('
            SELECT product_id, product_name, product_code, price, stock_quantity
            FROM products
            WHERE product_id = ? AND status = \'active\'
            LIMIT 1
        ');
        $stmt->execute([$buy_now_product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception('Product not found or unavailable.');
        }
        if ($buy_now_quantity > (int)$product['stock_quantity']) {
            throw new Exception("Insufficient stock for product: {$product['product_name']}");
        }

        $checkout_items[] = [
            'product_id' => (int)$product['product_id'],
            'product_name' => $product['product_name'],
            'product_code' => $product['product_code'],
            'price' => (float)$product['price'],
            'quantity' => $buy_now_quantity,
            'stock_quantity' => (int)$product['stock_quantity']
        ];
    } else {
        $stmt = $pdo->prepare('SELECT cart_id FROM cart WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cart) {
            throw new Exception('Cart not found.');
        }

        $cart_id = (int)$cart['cart_id'];
        $stmt = $pdo->prepare('
            SELECT ci.*, p.product_name, p.product_code, p.stock_quantity
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.product_id
            WHERE ci.cart_id = ?
        ');
        $stmt->execute([$cart_id]);
        $checkout_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($checkout_items)) {
            throw new Exception('Cart is empty.');
        }

        foreach ($checkout_items as $item) {
            if ((int)$item['quantity'] > (int)$item['stock_quantity']) {
                throw new Exception("Insufficient stock for product: {$item['product_name']}");
            }
        }
    }

    $subtotal = 0.0;
    foreach ($checkout_items as $item) {
        $subtotal += ((float)$item['price']) * ((int)$item['quantity']);
    }

    $paymentConfig = mincGetPaymentConfigValue();
    $shippingConfig = $paymentConfig['shipping'] ?? [];
    $freeShippingThreshold = (float)($shippingConfig['free_threshold'] ?? 1000);
    $standardShippingFee = (float)($shippingConfig['standard_fee'] ?? 150);

    $shipping_fee = $delivery_method === 'shipping'
        ? ($subtotal >= $freeShippingThreshold ? 0 : $standardShippingFee)
        : 0;
    $total_amount = $subtotal + $shipping_fee;

    $shippingAddressForStorage = $shipping['address'];

    $userColumns = mincGetTableColumns($pdo, 'users');
    $userUpdateParts = ['fname = :fname', 'lname = :lname', 'contact_num = :contact_num'];
    $userUpdateParams = [
        ':fname' => $customer['first_name'],
        ':lname' => $customer['last_name'],
        ':contact_num' => $customer['phone'],
        ':user_id' => $user_id
    ];
    foreach (['address', 'barangay', 'city', 'province', 'postal_code'] as $columnName) {
        if (in_array($columnName, $userColumns, true)) {
            $userUpdateParts[] = "{$columnName} = :{$columnName}";
            $userUpdateParams[':' . $columnName] = $delivery_method === 'shipping' ? ($shipping[$columnName] ?? $shippingAddressForStorage) : ($columnName === 'address' ? 'Pickup at Store' : null);
        }
    }
    $userUpdateParts[] = 'updated_at = NOW()';
    $userUpdateStmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $userUpdateParts) . ' WHERE user_id = :user_id');
    $userUpdateStmt->execute($userUpdateParams);

    $customerLookup = $pdo->prepare('SELECT customer_id FROM customers WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
    $customerLookup->execute([$user_id]);
    $existing_customer = $customerLookup->fetch(PDO::FETCH_ASSOC);

    if ($existing_customer) {
        $customer_id = (int)$existing_customer['customer_id'];
        $customerUpdate = $pdo->prepare('
            UPDATE customers SET
                first_name = ?,
                last_name = ?,
                email = ?,
                phone = ?,
                address = ?,
                city = ?,
                province = ?,
                postal_code = ?,
                customer_type = \'registered\',
                updated_at = NOW()
            WHERE customer_id = ?
        ');
        $customerUpdate->execute([
            $customer['first_name'],
            $customer['last_name'],
            $customer['email'],
            $customer['phone'],
            $delivery_method === 'shipping' ? $shippingAddressForStorage : 'Pickup at Store',
            $shipping['city'],
            $shipping['province'],
            $shipping['postal_code'],
            $customer_id
        ]);
    } else {
        $customerInsert = $pdo->prepare('
            INSERT INTO customers (
                user_id, first_name, last_name, email, phone,
                address, city, province, postal_code, customer_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'registered\')
        ');
        $customerInsert->execute([
            $user_id,
            $customer['first_name'],
            $customer['last_name'],
            $customer['email'],
            $customer['phone'],
            $delivery_method === 'shipping' ? $shippingAddressForStorage : 'Pickup at Store',
            $shipping['city'],
            $shipping['province'],
            $shipping['postal_code']
        ]);
        $customer_id = (int)$pdo->lastInsertId();
    }

    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid('', true), -6));

    $notes = $notesValue !== '' ? $notesValue : null;
    if ($delivery_method === 'pickup' && !in_array('pickup_date', $orderColumns, true)) {
        $pickupSummary = 'Pickup: ' . trim($pickup_date . ' ' . $pickup_time);
        $notes = $notes ? ($notes . ' | ' . $pickupSummary) : $pickupSummary;
    }

    $insertColumns = [
        'customer_id',
        'customer_phone',
        'order_number',
        'subtotal',
        'shipping_fee',
        'total_amount',
        'payment_method',
        'payment_status',
        'order_status',
        'shipping_address',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'notes'
    ];
    $insertValues = [
        ':customer_id',
        ':customer_phone',
        ':order_number',
        ':subtotal',
        ':shipping_fee',
        ':total_amount',
        ':payment_method',
        ':payment_status',
        ':order_status',
        ':shipping_address',
        ':shipping_city',
        ':shipping_province',
        ':shipping_postal_code',
        ':notes'
    ];
    $insertParams = [
        ':customer_id' => $customer_id,
        ':customer_phone' => $customer['phone'],
        ':order_number' => $order_number,
        ':subtotal' => $subtotal,
        ':shipping_fee' => $shipping_fee,
        ':total_amount' => $total_amount,
        ':payment_method' => $payment_method,
        ':payment_status' => $payment_method === 'cod' ? 'pending' : 'pending',
        ':order_status' => 'pending',
        ':shipping_address' => $delivery_method === 'shipping' ? $shippingAddressForStorage : 'Pickup at Store',
        ':shipping_city' => $shipping['city'],
        ':shipping_province' => $shipping['province'],
        ':shipping_postal_code' => $shipping['postal_code'],
        ':notes' => $notes
    ];

    $optionalOrderColumns = [
        'delivery_method' => $delivery_method,
        'payment_reference' => $payment_reference !== '' ? $payment_reference : null,
        'payment_proof_path' => $paymentProofRelativePath,
        'payment_review_notes' => $requiresPaymentProof ? 'Payment proof submitted and awaiting admin review.' : 'COD order submitted. Payment to be collected upon completion.',
        'pickup_date' => $pickup_date !== '' ? $pickup_date : null,
        'pickup_time' => $pickup_time !== '' ? $pickup_time : null,
        'shipping_partner' => $delivery_method === 'pickup' ? 'store_pickup' : 'standard_shipping'
    ];

    foreach ($optionalOrderColumns as $columnName => $columnValue) {
        if (in_array($columnName, $orderColumns, true)) {
            $insertColumns[] = $columnName;
            $insertValues[] = ':' . $columnName;
            $insertParams[':' . $columnName] = $columnValue;
        }
    }

    if (in_array('payment_proof_uploaded_at', $orderColumns, true) && $paymentProofRelativePath !== null) {
        $insertColumns[] = 'payment_proof_uploaded_at';
        $insertValues[] = 'NOW()';
    }

    $orderInsert = $pdo->prepare('INSERT INTO orders (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')');
    $orderInsert->execute($insertParams);
    $order_id = (int)$pdo->lastInsertId();

    $orderItemInsert = $pdo->prepare('
        INSERT INTO order_items (
            order_id, product_id, product_name, product_code,
            quantity, price, subtotal
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $updateStock = $pdo->prepare('
        UPDATE products
        SET
            stock_quantity = stock_quantity - ?,
            stock_status = CASE
                WHEN (stock_quantity - ?) <= 0 THEN \'out_of_stock\'
                WHEN (stock_quantity - ?) <= min_stock_level THEN \'low_stock\'
                ELSE \'in_stock\'
            END
        WHERE product_id = ?
    ');

    foreach ($checkout_items as $item) {
        $itemSubtotal = ((float)$item['price']) * ((int)$item['quantity']);
        $orderItemInsert->execute([
            $order_id,
            $item['product_id'],
            $item['product_name'],
            $item['product_code'],
            $item['quantity'],
            $item['price'],
            $itemSubtotal
        ]);

        $updateStock->execute([
            $item['quantity'],
            $item['quantity'],
            $item['quantity'],
            $item['product_id']
        ]);
    }

    if (!$is_buy_now_checkout && $cart_id !== null) {
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ?');
        $stmt->execute([$cart_id]);

        $stmt = $pdo->prepare('DELETE FROM cart WHERE cart_id = ?');
        $stmt->execute([$cart_id]);
    }

    $auditStmt = $pdo->prepare('
        INSERT INTO audit_trail (
            user_id, session_username, action, entity_type, entity_id,
            new_value, change_reason, ip_address, user_agent, system_id
        ) VALUES (?, ?, \'CREATE\', \'order\', ?, ?, ?, ?, ?, \'minc_system\')
    ');
    $auditStmt->execute([
        $user_id,
        $_SESSION['username'] ?? trim((string)(($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? ''))),
        $order_id,
        json_encode([
            'order_number' => $order_number,
            'total_amount' => $total_amount,
            'payment_method' => $payment_method,
            'delivery_method' => $delivery_method,
            'payment_reference' => $payment_reference !== '' ? $payment_reference : null,
            'payment_proof_attached' => $paymentProofRelativePath !== null,
            'items_count' => count($checkout_items)
        ]),
        $requiresPaymentProof ? 'Order placed with payment proof for admin review' : 'Order placed with COD payment',
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $pdo->commit();

    try {
        $emailService = new EmailService();
        $deliveryLabel = $delivery_method === 'pickup' ? 'Store Pickup' : 'Shipping';
        $proofMessage = $requiresPaymentProof
            ? 'Your payment proof has been attached. An admin account will verify it before your order is confirmed.'
            : 'Your order will be confirmed first, and payment will be recorded when the order is released or delivered.';
        $statusMessage = $requiresPaymentProof
            ? 'Your order has been submitted and is now waiting for payment proof review.'
            : 'Your COD order has been submitted successfully.';

        $emailService->send(
            $customer['email'],
            'Your MinC order has been submitted',
            buildOrderEmailBody(trim($customer['first_name'] . ' ' . $customer['last_name']), [
                'order_number' => $order_number,
                'total_amount' => $total_amount,
                'delivery_method_label' => $deliveryLabel,
                'payment_method_label' => mincDescribePaymentMethod($payment_method),
                'proof_message' => $proofMessage,
                'status_message' => $statusMessage,
                'payment_reference' => $payment_reference
            ])
        );
    } catch (Exception $emailError) {
        error_log('Checkout confirmation email failed: ' . $emailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => $requiresPaymentProof ? 'Order submitted with payment proof for review.' : 'Order submitted successfully.',
        'order_number' => $order_number,
        'order_id' => $order_id
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($storedPaymentProofAbsolutePath && file_exists($storedPaymentProofAbsolutePath)) {
        @unlink($storedPaymentProofAbsolutePath);
    }

    error_log('Order processing error: ' . $e->getMessage());
    checkoutJsonError($e->getMessage());
}
?>
