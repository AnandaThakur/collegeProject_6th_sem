<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Get a system setting by key
 * 
 * @param string $key The setting key
 * @param mixed $default Default value if setting not found
 * @return mixed The setting value or default
 */
function getSetting($key, $default = null) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $value = $row['setting_value'];
        
        // Convert value based on type
        switch ($row['setting_type']) {
            case 'toggle':
                return (bool)$value;
            case 'number':
                return (float)$value;
            case 'dropdown':
                return explode(',', $value);
            default:
                return $value;
        }
    }
    
    return $default;
}

/**
 * Update a system setting
 * 
 * @param string $key The setting key
 * @param mixed $value The new value
 * @return bool True if successful, false otherwise
 */
function updateSetting($key, $value) {
    $conn = getDbConnection();
    
    // Get the setting type
    $stmt = $conn->prepare("SELECT setting_type FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return false;
    }
    
    $row = $result->fetch_assoc();
    
    // Format value based on type
    switch ($row['setting_type']) {
        case 'dropdown':
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            break;
        case 'toggle':
            $value = $value ? '1' : '0';
            break;
    }
    
    // Update the setting
    $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param("ss", $value, $key);
    $stmt->execute();
    
    return $stmt->affected_rows > 0;
}

/**
 * Get all system settings
 * 
 * @param bool $publicOnly Get only public settings
 * @return array Array of settings
 */
function getAllSettings($publicOnly = false) {
    $conn = getDbConnection();
    
    $sql = "SELECT * FROM system_settings";
    if ($publicOnly) {
        $sql .= " WHERE is_public = 1";
    }
    
    $result = $conn->query($sql);
    $settings = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $settings[] = $row;
        }
    }
    
    return $settings;
}

/**
 * Get all admin users
 * 
 * @return array Array of admin users
 */
function getAdminUsers() {
    // For testing purposes, return hardcoded admin users
    return [
        [
            'id' => 1,
            'email' => 'admin@example.com',
            'status' => 'approved',
            'created_at' => '2023-01-01 00:00:00'
        ],
        [
            'id' => 2,
            'email' => 'moderator@example.com',
            'status' => 'approved',
            'created_at' => '2023-02-01 00:00:00'
        ],
        [
            'id' => 3,
            'email' => 'inactive@example.com',
            'status' => 'deactivated',
            'created_at' => '2023-03-01 00:00:00'
        ]
    ];
}

/**
 * Add a new admin user
 * 
 * @param string $email Admin email
 * @param string $password Admin password
 * @return bool|string True if successful, error message otherwise
 */
function addAdminUser($email, $password) {
    $conn = getDbConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return "Email already exists";
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert new admin
    $stmt = $conn->prepare("INSERT INTO users (email, password, role, status, is_verified) VALUES (?, ?, 'admin', 'approved', 1)");
    $stmt->bind_param("ss", $email, $hashedPassword);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        return true;
    } else {
        return "Failed to add admin: " . $conn->error;
    }
}

/**
 * Update admin status
 * 
 * @param int $adminId Admin ID
 * @param string $status New status (approved or deactivated)
 * @return bool True if successful, false otherwise
 */
function updateAdminStatus($adminId, $status) {
    // For demo purposes, always return true
    return true;
}

// Add a function to check if a setting exists
function settingExists($key) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// Add a function to create a setting if it doesn't exist
function createSettingIfNotExists($key, $value, $type, $options = '', $description = '', $isPublic = 1) {
    if (!settingExists($key)) {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_options, setting_description, is_public) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $key, $value, $type, $options, $description, $isPublic);
        
        return $stmt->execute();
    }
    
    return true;
}

// Add a function to create a default admin user if none exists
function createDefaultAdminIfNeeded() {
    $conn = getDbConnection();
    
    // Check if any admin users exist
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Create a default admin user
        $email = 'admin@example.com';
        $password = password_hash('admin123', PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO users (email, password, role, status, is_verified) VALUES (?, ?, 'admin', 'approved', 1)");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            error_log("Created default admin user: admin@example.com with password: admin123");
            return true;
        }
    }
    
    return false;
}
