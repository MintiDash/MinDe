<?php
/**
 * Add Supplier Backend
 * File: d:\XAMPP\htdocs\pages\MinC_Project\backend\suppliers\add_supplier.php
 */

session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';
require_once __DIR__ . '/province_options.php';

$redirect_url = '../../app/frontend/suppliers.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    $_SESSION['error_message'] = 'Session expired. Please login again.';
    header('Location: ../../index.php');
    exit;
}

// Check management level permission (Admin + Employee)
if (!isITStaff() && !isOwner()) {
    $_SESSION['error_message'] = 'Access denied. Insufficient permissions.';
    header('Location: ' . $redirect_url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: ' . $redirect_url);
    exit;
}

$safeLength = static function ($value) {
    return function_exists('mb_strlen') ? mb_strlen((string)$value) : strlen((string)$value);
};

$normalizeOptional = static function ($value) {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
};

try {
    $supplier_name = trim((string)($_POST['supplier_name'] ?? ''));
    $contact_person = $normalizeOptional($_POST['contact_person'] ?? null);
    $email = $normalizeOptional($_POST['email'] ?? null);
    $phone = $normalizeOptional($_POST['phone'] ?? null);
    $address = $normalizeOptional($_POST['address'] ?? null);
    $city = $normalizeOptional($_POST['city'] ?? null);
    $province = $normalizeOptional($_POST['province'] ?? null);
    $allowed_provinces = getSupplierProvinceOptions();

    // Validation
    if ($supplier_name === '') {
        throw new Exception('Supplier name is required.');
    }
    if ($safeLength($supplier_name) < 2 || $safeLength($supplier_name) > 255) {
        throw new Exception('Supplier name must be between 2 and 255 characters.');
    }

    if ($contact_person !== null && $safeLength($contact_person) > 255) {
        throw new Exception('Contact person must not exceed 255 characters.');
    }

    if ($email !== null && (!filter_var($email, FILTER_VALIDATE_EMAIL) || $safeLength($email) > 255)) {
        throw new Exception('Please provide a valid email address.');
    }

    if ($phone !== null) {
        if ($safeLength($phone) > 50) {
            throw new Exception('Phone must not exceed 50 characters.');
        }
        if (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
            throw new Exception('Phone contains invalid characters.');
        }
    }

    if ($city !== null && $safeLength($city) > 100) {
        throw new Exception('City must not exceed 100 characters.');
    }

    if ($address !== null && $safeLength($address) > 255) {
        throw new Exception('Address must not exceed 255 characters.');
    }

    if ($province === null || !in_array($province, $allowed_provinces, true)) {
        throw new Exception('Please select a valid province.');
    }

    // Duplicate check (case-insensitive)
    $duplicate_check = $pdo->prepare("
        SELECT supplier_id
        FROM suppliers
        WHERE LOWER(TRIM(supplier_name)) = LOWER(TRIM(:supplier_name))
        LIMIT 1
    ");
    $duplicate_check->execute([':supplier_name' => $supplier_name]);
    if ($duplicate_check->fetch()) {
        throw new Exception('Supplier name already exists.');
    }

    $pdo->beginTransaction();

    // Insert supplier
    $insert_stmt = $pdo->prepare("
        INSERT INTO suppliers (
            supplier_name,
            contact_person,
            email,
            phone,
            address,
            city,
            province,
            status,
            created_at,
            updated_at
        ) VALUES (
            :supplier_name,
            :contact_person,
            :email,
            :phone,
            :address,
            :city,
            :province,
            'active',
            NOW(),
            NOW()
        )
    ");

    $insert_stmt->execute([
        ':supplier_name' => $supplier_name,
        ':contact_person' => $contact_person,
        ':email' => $email,
        ':phone' => $phone,
        ':address' => $address,
        ':city' => $city,
        ':province' => $province
    ]);

    $supplier_id = (int)$pdo->lastInsertId();

    // Audit trail is best-effort to avoid blocking business action on logging failure.
    try {
        $audit_stmt = $pdo->prepare("
            INSERT INTO audit_trail (
                user_id,
                session_username,
                action,
                entity_type,
                entity_id,
                old_value,
                new_value,
                change_reason,
                timestamp,
                ip_address,
                user_agent,
                system_id
            ) VALUES (
                :user_id,
                :session_username,
                'CREATE',
                'supplier',
                :entity_id,
                NULL,
                :new_value,
                'Added new supplier',
                NOW(),
                :ip_address,
                :user_agent,
                'minc_system'
            )
        ");

        $audit_stmt->execute([
            ':user_id' => $_SESSION['user_id'] ?? null,
            ':session_username' => $_SESSION['full_name'] ?? ($_SESSION['fname'] ?? 'System'),
            ':entity_id' => (string)$supplier_id,
            ':new_value' => json_encode([
                'supplier_name' => $supplier_name,
                'contact_person' => $contact_person,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'province' => $province,
                'status' => 'active'
            ]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $audit_exception) {
        error_log('Audit log failed in add_supplier.php: ' . $audit_exception->getMessage());
    }

    $pdo->commit();

    $_SESSION['success_message'] = 'Supplier added successfully.';
    header('Location: ' . $redirect_url);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Error adding supplier: ' . $e->getMessage();
    header('Location: ' . $redirect_url);
    exit;
}
?>
