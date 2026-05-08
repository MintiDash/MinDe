<?php
/**
 * Edit Product Line Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\product-lines\edit_product_line.php
 */

session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    $_SESSION['error_message'] = 'Session expired. Please login again.';
    header('Location: ../../index.php');
    exit;
}

// Check management level permission
if (!isManagementLevel()) {
    $_SESSION['error_message'] = 'Access denied. Insufficient permissions.';
    header('Location: ../../app/frontend/product-lines.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $slugify = static function ($value) {
            $slug = strtolower(trim((string)$value));
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
            $slug = trim($slug, '-');
            return $slug !== '' ? $slug : 'product-line';
        };

        $generateUniqueSlug = static function ($pdo, $baseSlug, $excludeId) {
            $slug = $baseSlug;
            $counter = 2;
            while (true) {
                $check = $pdo->prepare("
                    SELECT product_line_id
                    FROM product_lines
                    WHERE product_line_slug = ? AND product_line_id != ?
                    LIMIT 1
                ");
                $check->execute([$slug, $excludeId]);
                if (!$check->fetch()) {
                    return $slug;
                }
                $slug = $baseSlug . '-' . $counter;
                $counter++;
                if ($counter > 9999) {
                    throw new Exception('Unable to generate a unique product line slug.');
                }
            }
        };

        $getFallbackSubcategories = static function ($categoryName, $categorySlug = '') {
            $presets = [
                'car parts' => ['Back & Head Lights'],
                'external parts' => ['Side Mirrors', 'Wiper Blades', 'Door Handles & Locks', 'Truck Accessories', 'Window Visors'],
                'internal parts' => ['Horns & Components', 'Mobile Electronics', 'Switches & Relays'],
                'wheels & tyres' => ['Nuts', 'Tires', 'Wheel Accessories'],
            ];

            $normalize = static function ($value) {
                $value = strtolower(trim((string)$value));
                $value = str_replace(['-', '_'], ' ', $value);
                $value = preg_replace('/\s+/', ' ', $value);
                return $value;
            };

            $nameKey = $normalize($categoryName);
            $slugKey = $normalize($categorySlug);

            if (isset($presets[$nameKey])) {
                return $presets[$nameKey];
            }

            if (isset($presets[$slugKey])) {
                return $presets[$slugKey];
            }

            if (strpos($nameKey, 'external') !== false) {
                return $presets['external parts'];
            }
            if (strpos($nameKey, 'internal') !== false) {
                return $presets['internal parts'];
            }
            if (strpos($nameKey, 'wheel') !== false) {
                return $presets['wheels & tyres'];
            }
            if (strpos($nameKey, 'car') !== false) {
                return $presets['car parts'];
            }

            return [];
        };

        $appendUniqueValues = static function (array &$target, array $values) {
            $normalizedExisting = array_map(static function ($value) {
                return strtolower(trim((string)$value));
            }, $target);

            foreach ($values as $value) {
                $label = trim((string)$value);
                if ($label === '') {
                    continue;
                }

                $normalizedLabel = strtolower($label);
                if (in_array($normalizedLabel, $normalizedExisting, true)) {
                    continue;
                }

                $target[] = $label;
                $normalizedExisting[] = $normalizedLabel;
            }
        };

        $getSuggestedSubcategories = static function ($pdo, $categoryId, $categoryName, $categorySlug = '') use ($getFallbackSubcategories, $appendUniqueValues) {
            static $hasPresetTable = null;
            $suggestions = [];

            if ($hasPresetTable === null) {
                try {
                    $check = $pdo->query("SHOW TABLES LIKE 'product_line_presets'");
                    $hasPresetTable = $check && (bool)$check->fetchColumn();
                } catch (Exception $e) {
                    $hasPresetTable = false;
                }
            }

            if ($hasPresetTable) {
                try {
                    $presetQuery = $pdo->prepare("
                        SELECT preset_name
                        FROM product_line_presets
                        WHERE category_id = :category_id
                          AND status = 'active'
                        ORDER BY display_order ASC, preset_name ASC
                    ");
                    $presetQuery->execute([':category_id' => (int)$categoryId]);
                    $rows = $presetQuery->fetchAll(PDO::FETCH_COLUMN, 0);
                    $appendUniqueValues($suggestions, $rows);
                } catch (Exception $e) {
                    // Fall through to the remaining suggestion sources.
                }
            }

            try {
                $existingQuery = $pdo->prepare("
                    SELECT product_line_name
                    FROM product_lines
                    WHERE category_id = :category_id
                      AND product_line_name IS NOT NULL
                      AND TRIM(product_line_name) != ''
                    ORDER BY display_order ASC, product_line_name ASC
                ");
                $existingQuery->execute([':category_id' => (int)$categoryId]);
                $rows = $existingQuery->fetchAll(PDO::FETCH_COLUMN, 0);
                $appendUniqueValues($suggestions, $rows);
            } catch (Exception $e) {
                // Non-fatal, keep other sources.
            }

            if (empty($suggestions)) {
                $appendUniqueValues($suggestions, $getFallbackSubcategories($categoryName, $categorySlug));
            }

            return $suggestions;
        };

        $syncPresetName = static function ($pdo, $categoryId, $subcategoryName) {
            static $hasPresetTable = null;

            if ($hasPresetTable === null) {
                try {
                    $check = $pdo->query("SHOW TABLES LIKE 'product_line_presets'");
                    $hasPresetTable = $check && (bool)$check->fetchColumn();
                } catch (Exception $e) {
                    $hasPresetTable = false;
                }
            }

            if (!$hasPresetTable) {
                return;
            }

            $cleanName = trim((string)$subcategoryName);
            if ($cleanName === '') {
                return;
            }

            $lookup = $pdo->prepare("
                SELECT preset_id
                FROM product_line_presets
                WHERE category_id = :category_id
                  AND LOWER(TRIM(preset_name)) = :preset_name
                LIMIT 1
            ");
            $lookup->execute([
                ':category_id' => (int)$categoryId,
                ':preset_name' => strtolower($cleanName)
            ]);
            $existingPresetId = $lookup->fetchColumn();

            if ($existingPresetId) {
                $update = $pdo->prepare("
                    UPDATE product_line_presets
                    SET preset_name = :preset_name,
                        status = 'active',
                        updated_at = NOW()
                    WHERE preset_id = :preset_id
                ");
                $update->execute([
                    ':preset_name' => $cleanName,
                    ':preset_id' => (int)$existingPresetId
                ]);
                return;
            }

            $orderQuery = $pdo->prepare("
                SELECT COALESCE(MAX(display_order), 0) + 1
                FROM product_line_presets
                WHERE category_id = :category_id
            ");
            $orderQuery->execute([':category_id' => (int)$categoryId]);
            $nextOrder = (int)$orderQuery->fetchColumn();
            if ($nextOrder <= 0) {
                $nextOrder = 1;
            }

            $insert = $pdo->prepare("
                INSERT INTO product_line_presets (
                    category_id,
                    preset_name,
                    display_order,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    :category_id,
                    :preset_name,
                    :display_order,
                    'active',
                    NOW(),
                    NOW()
                )
            ");
            $insert->execute([
                ':category_id' => (int)$categoryId,
                ':preset_name' => $cleanName,
                ':display_order' => $nextOrder
            ]);
        };

        // Get form data
        $product_line_id = intval($_POST['product_line_id'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $product_line_name = trim($_POST['product_line_name'] ?? '');
        $product_line_description = trim($_POST['product_line_description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        // Validate required fields
        if (empty($product_line_id)) {
            throw new Exception('Product line ID is required.');
        }
        
        if (empty($category_id)) {
            throw new Exception('Category is required.');
        }
        
        if (empty($product_line_name)) {
            throw new Exception('Subcategory is required.');
        }

        if (strlen($product_line_name) > 255) {
            throw new Exception('Subcategory must be 255 characters or fewer.');
        }

        $baseSlug = $slugify($product_line_name);
        $product_line_slug = $generateUniqueSlug($pdo, $baseSlug, $product_line_id);
        
        // Get current product line data
        $current_query = "SELECT * FROM product_lines WHERE product_line_id = ?";
        $current_stmt = $pdo->prepare($current_query);
        $current_stmt->execute([$product_line_id]);
        $current_data = $current_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_data) {
            throw new Exception('Product line not found.');
        }
        
        // Validate category exists
        $check_category = $pdo->prepare("SELECT category_id, category_name, category_slug FROM categories WHERE category_id = ?");
        $check_category->execute([$category_id]);
        $category_data = $check_category->fetch(PDO::FETCH_ASSOC);
        if (!$category_data) {
            throw new Exception('Selected category does not exist.');
        }

        // Normalize to a suggested display label when it already exists.
        $suggested_subcategories = $getSuggestedSubcategories(
            $pdo,
            $category_id,
            $category_data['category_name'] ?? '',
            $category_data['category_slug'] ?? ''
        );
        $normalized_name = strtolower(trim($product_line_name));
        foreach ($suggested_subcategories as $suggested_name) {
            if (strtolower(trim((string)$suggested_name)) === $normalized_name) {
                $product_line_name = trim((string)$suggested_name);
                break;
            }
        }

        // Prevent duplicate names within the same category (excluding current record).
        $duplicate_check = $pdo->prepare("
            SELECT product_line_id
            FROM product_lines
            WHERE category_id = :category_id
              AND LOWER(product_line_name) = :product_line_name
              AND product_line_id != :product_line_id
            LIMIT 1
        ");
        $duplicate_check->execute([
            ':category_id' => $category_id,
            ':product_line_name' => $normalized_name,
            ':product_line_id' => $product_line_id
        ]);
        if ($duplicate_check->fetch()) {
            throw new Exception('This subcategory already exists for the selected category.');
        }
        
        // Handle image upload
        $product_line_image = $current_data['product_line_image'];
        $old_image_path = null;
        
        if (isset($_FILES['product_line_image']) && $_FILES['product_line_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../Assets/images/product-lines/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_tmp = $_FILES['product_line_image']['tmp_name'];
            $file_name = $_FILES['product_line_image']['name'];
            $file_size = $_FILES['product_line_image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file type
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, PNG, WEBP, and GIF are allowed.');
            }
            
            // Validate file size (2MB max)
            if ($file_size > 2097152) {
                throw new Exception('File size too large. Maximum size is 2MB.');
            }
            
            // Store old image path for deletion
            if ($product_line_image) {
                $old_image_path = $upload_dir . $product_line_image;
            }
            
            // Generate unique filename
            $product_line_image = 'product_line_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $product_line_image;
            
            // Move uploaded file
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                throw new Exception('Failed to upload image.');
            }
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update product line
        $update_query = "
            UPDATE product_lines SET
                category_id = ?,
                product_line_name = ?,
                product_line_slug = ?,
                product_line_description = ?,
                product_line_image = ?,
                display_order = ?,
                status = ?,
                updated_at = NOW()
            WHERE product_line_id = ?
        ";
        
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([
            $category_id,
            $product_line_name,
            $product_line_slug,
            $product_line_description,
            $product_line_image,
            $display_order,
            $status,
            $product_line_id
        ]);

        try {
            $syncPresetName($pdo, $category_id, $product_line_name);
        } catch (Exception $e) {
            error_log('Failed to sync product line preset on edit: ' . $e->getMessage());
        }
        
        // Delete old image if new one was uploaded
        if ($old_image_path && file_exists($old_image_path)) {
            unlink($old_image_path);
        }
        
        // Log audit trail
        $audit_query = "
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
            ) VALUES (?, ?, 'UPDATE', 'product_line', ?, ?, ?, 'Updated product line information', NOW(), ?, ?, 'minc_system')
        ";
        
        $old_value = json_encode([
            'category_id' => $current_data['category_id'],
            'product_line_name' => $current_data['product_line_name'],
            'product_line_slug' => $current_data['product_line_slug'],
            'product_line_description' => $current_data['product_line_description'],
            'product_line_image' => $current_data['product_line_image'],
            'display_order' => $current_data['display_order'],
            'status' => $current_data['status']
        ]);
        
        $new_value = json_encode([
            'category_id' => $category_id,
            'product_line_name' => $product_line_name,
            'product_line_slug' => $product_line_slug,
            'product_line_description' => $product_line_description,
            'product_line_image' => $product_line_image,
            'display_order' => $display_order,
            'status' => $status
        ]);
        
        $audit_stmt = $pdo->prepare($audit_query);
        $audit_stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['full_name'] ?? $_SESSION['fname'],
            $product_line_id,
            $old_value,
            $new_value,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = 'Product line updated successfully!';
        header('Location: ../../app/frontend/product-lines.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Delete newly uploaded image if exists and there was an error
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
        
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        header('Location: ../../app/frontend/product-lines.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: ../../app/frontend/product-lines.php');
    exit;
}
?>
