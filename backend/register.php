<?php
/**
 * Registration Backend
 * Path: C:\xampp\htdocs\MinC_Project\backend\register.php
 * Starts customer registration with email OTP flow.
 */

session_start();

// Include database connection
require_once __DIR__ . '/../database/connect_database.php';
require_once __DIR__ . '/order-management/order_workflow_helper.php';

// Set response header to JSON
header('Content-Type: application/json');

function normalizeName($value) {
    $value = preg_replace('/\s+/', ' ', trim((string)$value));
    return ucwords(strtolower($value), " -'");
}

// Function to log audit trail
function logAuditTrail($pdo, $userId, $username, $action, $entityType, $entityId, $oldValue = null, $newValue = null, $changeReason = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_trail 
            (user_id, session_username, action, entity_type, entity_id, old_value, new_value, change_reason, ip_address, user_agent, system_id) 
            VALUES 
            (:user_id, :session_username, :action, :entity_type, :entity_id, :old_value, :new_value, :change_reason, :ip_address, :user_agent, :system_id)
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':session_username' => $username,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':old_value' => $oldValue ? json_encode($oldValue) : null,
            ':new_value' => $newValue ? json_encode($newValue) : null,
            ':change_reason' => $changeReason,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':system_id' => 'minc_system'
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Audit trail error: " . $e->getMessage());
        return false;
    }
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (
    !isset($input['fname']) ||
    !isset($input['lname']) ||
    !isset($input['email']) ||
    !isset($input['contact_num']) ||
    !isset($input['address'])
) {
    echo json_encode([
        'success' => false,
        'message' => 'First name, last name, email, contact number, and shipping address are required'
    ]);
    exit;
}

$fname = trim($input['fname']);
$lname = trim($input['lname']);
$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$contact_num = trim((string)($input['contact_num'] ?? ''));
$postal_code = trim((string)($input['postal_code'] ?? ''));
$shippingData = mincBuildShippingData(
    $input['address'] ?? '',
    $input['barangay'] ?? '',
    $input['city'] ?? 'Angeles City',
    $input['province'] ?? 'Pampanga',
    $postal_code
);
$address = $shippingData['address'];
$home_address = $address;
$billing_address = $address;
$barangay = $shippingData['barangay'];
$city = $shippingData['city'];
$province = $shippingData['province'];
$postal_code = $shippingData['postal_code'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

if ($fname === '' || $lname === '') {
    echo json_encode([
        'success' => false,
        'message' => 'First name and last name are required'
    ]);
    exit;
}

$fname = normalizeName($fname);
$lname = normalizeName($lname);

if ($address === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Default shipping address is required'
    ]);
    exit;
}

$normalizedContact = mincNormalizePhilippineMobile($contact_num);
if ($normalizedContact === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid contact number format. Use 09XXXXXXXXX or +63XXXXXXXXXX'
    ]);
    exit;
}
$contact_num = $normalizedContact;

if (mb_strlen($address) < 10 || mb_strlen($address) > 255) {
    echo json_encode([
        'success' => false,
        'message' => 'Shipping address must be between 10 and 255 characters'
    ]);
    exit;
}

if (!$shippingData['has_valid_barangay']) {
    echo json_encode([
        'success' => false,
        'message' => 'Include a valid Angeles City barangay in the shipping address'
    ]);
    exit;
}

if ($city === '' || mb_strlen($city) < 2 || mb_strlen($city) > 100) {
    echo json_encode([
        'success' => false,
        'message' => 'City must be between 2 and 100 characters'
    ]);
    exit;
}

if ($province === '' || mb_strlen($province) < 2 || mb_strlen($province) > 100) {
    echo json_encode([
        'success' => false,
        'message' => 'Province must be between 2 and 100 characters'
    ]);
    exit;
}

if ($postal_code !== null && $postal_code !== '') {
    $postalCodeInt = (int)$postal_code;
    $postalCodeValid = preg_match('/^\d{4}$/', $postal_code) === 1
        && $postalCodeInt >= 2000
        && $postalCodeInt <= 2100;
    if (!$postalCodeValid) {
        echo json_encode([
            'success' => false,
            'message' => 'Postal code must be a 4-digit value between 2000 and 2100'
        ]);
        exit;
    }
} else {
    $postal_code = null;
}

try {
    $userColumns = mincGetTableColumns($pdo, 'users');

    // Email verification table must exist for OTP flow
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'email_verification_tokens'");
    $emailVerificationEnabled = $checkTableStmt->rowCount() > 0;

    if (!$emailVerificationEnabled) {
        echo json_encode([
            'success' => false,
            'message' => 'Email verification is not configured. Please run SETUP_DATABASE.sql first.'
        ]);
        exit;
    }

    $checkColumnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_email_verified'");
    if ($checkColumnStmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => "Database is missing 'is_email_verified' column. Please run SETUP_DATABASE.sql first."
        ]);
        exit;
    }

    $userIdColumnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_id'");
    $userIdColumn = $userIdColumnStmt->fetch(PDO::FETCH_ASSOC);
    if (!$userIdColumn || stripos((string)($userIdColumn['Extra'] ?? ''), 'auto_increment') === false) {
        echo json_encode([
            'success' => false,
            'message' => "Database schema issue: users.user_id must be AUTO_INCREMENT. Please run SETUP_DATABASE.sql."
        ]);
        exit;
    }

    $tokenIdColumnStmt = $pdo->query("SHOW COLUMNS FROM email_verification_tokens LIKE 'token_id'");
    $tokenIdColumn = $tokenIdColumnStmt->fetch(PDO::FETCH_ASSOC);
    if (!$tokenIdColumn || stripos((string)($tokenIdColumn['Extra'] ?? ''), 'auto_increment') === false) {
        echo json_encode([
            'success' => false,
            'message' => "Database schema issue: email_verification_tokens.token_id must be AUTO_INCREMENT. Please run SETUP_DATABASE.sql."
        ]);
        exit;
    }

    require_once __DIR__ . '/../library/EmailService.php';
    require_once __DIR__ . '/../library/TokenGenerator.php';

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id, user_level_id, is_email_verified FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $newUserId = null;
    $isExistingPending = false;

    if ($existingUser) {
        // Allow continuation only for pending customer registration
        if ((int)$existingUser['user_level_id'] === 4 && (int)$existingUser['is_email_verified'] === 0) {
            $isExistingPending = true;
            $newUserId = (int)$existingUser['user_id'];

            $updateFields = [
                'fname = :fname',
                'lname = :lname',
                'contact_num = :contact_num',
                'address = :address',
                "user_status = 'inactive'",
                'updated_at = NOW()'
            ];
            $updateParams = [
                ':fname' => $fname,
                ':lname' => $lname,
                ':contact_num' => $contact_num,
                ':address' => $address,
                ':user_id' => $newUserId
            ];

            foreach (['home_address', 'billing_address', 'barangay', 'city', 'province', 'postal_code'] as $columnName) {
                if (in_array($columnName, $userColumns, true)) {
                    $updateFields[] = "{$columnName} = :{$columnName}";
                }
            }

            if (in_array('home_address', $userColumns, true)) {
                $updateParams[':home_address'] = $home_address !== '' ? $home_address : $address;
            }
            if (in_array('billing_address', $userColumns, true)) {
                $updateParams[':billing_address'] = $billing_address !== '' ? $billing_address : $address;
            }
            if (in_array('barangay', $userColumns, true)) {
                $updateParams[':barangay'] = $barangay;
            }
            if (in_array('city', $userColumns, true)) {
                $updateParams[':city'] = $city;
            }
            if (in_array('province', $userColumns, true)) {
                $updateParams[':province'] = $province;
            }
            if (in_array('postal_code', $userColumns, true)) {
                $updateParams[':postal_code'] = $postal_code;
            }

            $updatePendingStmt = $pdo->prepare("
                UPDATE users
                SET " . implode(', ', $updateFields) . "
                WHERE user_id = :user_id
            ");
            $updatePendingStmt->execute($updateParams);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Email already registered'
            ]);
            exit;
        }
    } else {
        // Create pending account with temporary random password
        $temporaryPassword = password_hash(TokenGenerator::generateToken(24), PASSWORD_DEFAULT);

        $insertColumns = ['fname', 'lname', 'email', 'password', 'contact_num', 'address', 'user_level_id', 'user_status', 'is_email_verified', 'created_at', 'updated_at'];
        $insertValues = [':fname', ':lname', ':email', ':password', ':contact_num', ':address', '4', "'inactive'", '0', 'NOW()', 'NOW()'];
        $insertParams = [
            ':fname' => $fname,
            ':lname' => $lname,
            ':email' => $email,
            ':password' => $temporaryPassword,
            ':contact_num' => $contact_num,
            ':address' => $address
        ];

        if (in_array('home_address', $userColumns, true)) {
            $insertColumns[] = 'home_address';
            $insertValues[] = ':home_address';
            $insertParams[':home_address'] = $home_address !== '' ? $home_address : $address;
        }
        if (in_array('billing_address', $userColumns, true)) {
            $insertColumns[] = 'billing_address';
            $insertValues[] = ':billing_address';
            $insertParams[':billing_address'] = $billing_address !== '' ? $billing_address : $address;
        }
        if (in_array('barangay', $userColumns, true)) {
            $insertColumns[] = 'barangay';
            $insertValues[] = ':barangay';
            $insertParams[':barangay'] = $barangay;
        }
        if (in_array('city', $userColumns, true)) {
            $insertColumns[] = 'city';
            $insertValues[] = ':city';
            $insertParams[':city'] = $city;
        }
        if (in_array('province', $userColumns, true)) {
            $insertColumns[] = 'province';
            $insertValues[] = ':province';
            $insertParams[':province'] = $province;
        }
        if (in_array('postal_code', $userColumns, true)) {
            $insertColumns[] = 'postal_code';
            $insertValues[] = ':postal_code';
            $insertParams[':postal_code'] = $postal_code;
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO users (" . implode(', ', $insertColumns) . ")
            VALUES (" . implode(', ', $insertValues) . ")
        ");

        $insertStmt->execute($insertParams);

        $newUserId = (int)$pdo->lastInsertId();
        if ($newUserId <= 0) {
            $lookupStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email ORDER BY user_id DESC LIMIT 1");
            $lookupStmt->execute([':email' => $email]);
            $newUserId = (int)($lookupStmt->fetchColumn() ?: 0);
        }
    }

    if ($newUserId <= 0) {
        throw new Exception('User ID generation failed. users.user_id may not be AUTO_INCREMENT.');
    }

    // Invalidate previous active OTP codes for this user
    $invalidateStmt = $pdo->prepare("
        UPDATE email_verification_tokens 
        SET expires_at = NOW(), is_used = 1, verified_at = NOW()
        WHERE user_id = :user_id 
          AND is_used = 0
          AND expires_at > NOW()
    ");
    $invalidateStmt->execute([':user_id' => $newUserId]);

    // Generate OTP (6 digits), valid for 10 minutes
    $otpCode = TokenGenerator::generateVerificationCode();
    // Keep OTP user-friendly (6 digits) but store a unique token string to satisfy DB uniqueness.
    $tokenStorage = $otpCode . '-' . substr(TokenGenerator::generateToken(8), 0, 16);
    $tokenHash = TokenGenerator::hashToken($otpCode);
    $expiresAt = date('Y-m-d H:i:s', time() + (10 * 60));

    $tokenStmt = $pdo->prepare("
        INSERT INTO email_verification_tokens (user_id, token, token_hash, email, expires_at) 
        VALUES (:user_id, :token, :token_hash, :email, :expires_at)
    ");

    $tokenStmt->execute([
        ':user_id' => $newUserId,
        ':token' => $tokenStorage,
        ':token_hash' => $tokenHash,
        ':email' => $email,
        ':expires_at' => $expiresAt
    ]);

    // Send OTP email
    $emailService = new EmailService();
    $emailSent = $emailService->sendOtpVerificationEmail(
        $email,
        trim($fname . ' ' . $lname),
        $otpCode,
        10
    );

    // Keep pending registration context in session for smooth password step
    $_SESSION['registration_pending_email'] = $email;
    $_SESSION['registration_pending_user_id'] = $newUserId;

    // Log registration start in audit trail
    logAuditTrail(
        $pdo,
        $newUserId,
        $fname,
        $isExistingPending ? 'registration_otp_resent' : 'registration_started',
        'user',
        $newUserId,
        null,
        [
            'fname' => $fname,
            'lname' => $lname,
            'email' => $email,
            'address' => $address,
            'contact_num' => $contact_num,
            'home_address' => $home_address !== '' ? $home_address : $address,
            'billing_address' => $billing_address !== '' ? $billing_address : $address,
            'barangay' => $barangay,
            'city' => $city,
            'province' => $province,
            'postal_code' => $postal_code,
            'otp_sent' => $emailSent
        ],
        $isExistingPending ? 'Pending registration continued and OTP resent' : 'Customer registration started with OTP verification'
    );

    echo json_encode([
        'success' => true,
        'message' => $emailSent
            ? 'Verification code sent. Please check your email.'
            : 'Account created, but OTP email could not be sent right now. Please try resend.',
        'email' => $email,
        'user_id' => $newUserId,
        'email_sent' => $emailSent,
        'otp_expires_in_seconds' => 600
    ]);
} catch (PDOException $e) {
    error_log("Registration error: " . $e->getMessage());

    if (($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1' || ($_SERVER['REMOTE_ADDR'] ?? '') === '::1') {
        echo json_encode([
            'success' => false,
            'message' => 'Registration database error: ' . $e->getMessage()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred during registration. Please try again later.'
        ]);
    }
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close connections
closeConnections();
?>
