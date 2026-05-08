<?php
/**
 * Update Supplier Backend
 */

session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';
require_once __DIR__ . '/province_options.php';

$redirect_url = '../../app/frontend/suppliers.php';

$validation = validateSession();
if (!$validation['valid']) {
    $_SESSION['error_message'] = 'Session expired. Please login again.';
    header('Location: ../../index.php');
    exit;
}

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
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $supplier_name = trim((string)($_POST['supplier_name'] ?? ''));
    $contact_person = $normalizeOptional($_POST['contact_person'] ?? null);
    $email = $normalizeOptional($_POST['email'] ?? null);
    $phone = $normalizeOptional($_POST['phone'] ?? null);
    $address = $normalizeOptional($_POST['address'] ?? null);
    $city = $normalizeOptional($_POST['city'] ?? null);
    $province = $normalizeOptional($_POST['province'] ?? null);

    if ($supplier_id <= 0) {
        throw new Exception('Invalid supplier.');
    }

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

    if ($address !== null && $safeLength($address) > 255) {
        throw new Exception('Address must not exceed 255 characters.');
    }

    if ($city !== null && $safeLength($city) > 100) {
        throw new Exception('City must not exceed 100 characters.');
    }

    $allowed_provinces = getSupplierProvinceOptions();
    if ($province === null || !in_array($province, $allowed_provinces, true)) {
        throw new Exception('Please select a valid province.');
    }

    $current_stmt = $pdo->prepare('SELECT * FROM suppliers WHERE supplier_id = :supplier_id LIMIT 1');
    $current_stmt->execute([':supplier_id' => $supplier_id]);
    $current = $current_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        throw new Exception('Supplier not found.');
    }

    $duplicate_stmt = $pdo->prepare("
        SELECT supplier_id
        FROM suppliers
        WHERE LOWER(TRIM(supplier_name)) = LOWER(TRIM(:supplier_name))
          AND supplier_id != :supplier_id
        LIMIT 1
    ");
    $duplicate_stmt->execute([
        ':supplier_name' => $supplier_name,
        ':supplier_id' => $supplier_id
    ]);
    if ($duplicate_stmt->fetch()) {
        throw new Exception('Supplier name already exists.');
    }

    $pdo->beginTransaction();

    $update_stmt = $pdo->prepare("
        UPDATE suppliers
        SET supplier_name = :supplier_name,
            contact_person = :contact_person,
            email = :email,
            phone = :phone,
            address = :address,
            city = :city,
            province = :province,
            updated_at = NOW()
        WHERE supplier_id = :supplier_id
        LIMIT 1
    ");
    $update_stmt->execute([
        ':supplier_name' => $supplier_name,
        ':contact_person' => $contact_person,
        ':email' => $email,
        ':phone' => $phone,
        ':address' => $address,
        ':city' => $city,
        ':province' => $province,
        ':supplier_id' => $supplier_id
    ]);

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
                'UPDATE',
                'supplier',
                :entity_id,
                :old_value,
                :new_value,
                'Updated supplier information',
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
            ':old_value' => json_encode([
                'supplier_name' => $current['supplier_name'] ?? null,
                'contact_person' => $current['contact_person'] ?? null,
                'email' => $current['email'] ?? null,
                'phone' => $current['phone'] ?? null,
                'address' => $current['address'] ?? null,
                'city' => $current['city'] ?? null,
                'province' => $current['province'] ?? null,
                'status' => $current['status'] ?? null
            ]),
            ':new_value' => json_encode([
                'supplier_name' => $supplier_name,
                'contact_person' => $contact_person,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'province' => $province,
                'status' => $current['status'] ?? null
            ]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $audit_exception) {
        error_log('Audit log failed in update_supplier.php: ' . $audit_exception->getMessage());
    }

    $pdo->commit();

    $_SESSION['success_message'] = 'Supplier updated successfully.';
    header('Location: ' . $redirect_url);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Error updating supplier: ' . $e->getMessage();
    header('Location: ' . $redirect_url);
    exit;
}
?>
