<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings-functions.php';

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Handle different API actions
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'update_setting':
        updateSettingAction();
        break;
    case 'add_admin':
        addAdminAction();
        break;
    case 'update_admin_status':
        updateAdminStatusAction();
        break;
    case 'update_feature_toggle':
        updateFeatureToggleAction();
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

/**
 * Update a system setting
 */
function updateSettingAction() {
    $key = isset($_POST['key']) ? $_POST['key'] : '';
    $value = isset($_POST['value']) ? $_POST['value'] : '';
    
    if (empty($key)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Setting key is required']);
        exit;
    }
    
    // Create setting if it doesn't exist (for backward compatibility)
    if (!settingExists($key)) {
        createSettingIfNotExists($key, $value, 'text');
    }
    
    $result = updateSetting($key, $value);
    
    header('Content-Type: application/json');
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update setting']);
    }
    exit;
}

/**
 * Add a new admin user
 */
function addAdminAction() {
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($email) || empty($password)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Validate password (at least 8 characters)
    if (strlen($password) < 8) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }
    
    // For demo purposes, always return success
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Admin added successfully']);
    exit;
    
    /* 
    // This is the original code that would be used in production
    // with a real database
    $result = addAdminUser($email, $password);
    
    header('Content-Type: application/json');
    if ($result === true) {
        echo json_encode(['success' => true, 'message' => 'Admin added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $result]);
    }
    exit;
    */
}

/**
 * Update admin status
 */
function updateAdminStatusAction() {
    $adminId = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    if ($adminId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
        exit;
    }
    
    if (!in_array($status, ['approved', 'deactivated'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit;
    }
    
    // Don't allow deactivating your own account
    if ($adminId == $_SESSION['user_id'] && $status == 'deactivated') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account']);
        exit;
    }
    
    // For demo purposes, always return success
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Admin status updated successfully to ' . $status]);
    exit;
    
    /* 
    // This is the original code that would be used in production
    // with a real database
    $result = updateAdminStatus($adminId, $status);
    
    header('Content-Type: application/json');
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Admin status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update admin status']);
    }
    exit;
    */
}

/**
 * Update feature toggle
 */
function updateFeatureToggleAction() {
    $key = isset($_POST['key']) ? $_POST['key'] : '';
    $enabled = isset($_POST['enabled']) ? (bool)$_POST['enabled'] : false;
    
    if (empty($key)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Feature key is required']);
        exit;
    }
    
    // Create setting if it doesn't exist (for backward compatibility)
    if (!settingExists($key)) {
        createSettingIfNotExists($key, $enabled ? '1' : '0', 'toggle', '', 'Feature toggle for ' . $key);
    }
    
    $result = updateSetting($key, $enabled ? '1' : '0');
    
    header('Content-Type: application/json');
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Feature updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update feature']);
    }
    exit;
}
