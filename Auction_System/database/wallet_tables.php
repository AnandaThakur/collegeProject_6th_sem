<?php
// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Get database connection
$conn = getDbConnection();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to create wallet tables
function createWalletTables() {
    global $conn;
    $success = true;
    $messages = [];

    // Create wallet_balances table
    $sql = "CREATE TABLE IF NOT EXISTS wallet_balances (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) !== TRUE) {
        $success = false;
        $messages[] = "Error creating wallet_balances table: " . $conn->error;
    } else {
        $messages[] = "wallet_balances table created successfully";
    }

    // Create wallet_transactions table
    $sql = "CREATE TABLE IF NOT EXISTS wallet_transactions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50) NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        type ENUM('deposit', 'withdrawal', 'bid', 'win', 'refund', 'deduct', 'commission') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'failed', 'cancelled', 'reversed') NOT NULL DEFAULT 'pending',
        description TEXT,
        admin_id INT UNSIGNED,
        reference_id VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (transaction_id),
        INDEX (user_id),
        INDEX (status),
        INDEX (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) !== TRUE) {
        $success = false;
        $messages[] = "Error creating wallet_transactions table: " . $conn->error;
    } else {
        $messages[] = "wallet_transactions table created successfully";
    }

    // Create payment_settings table
    $sql = "CREATE TABLE IF NOT EXISTS payment_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        gateway_name VARCHAR(50) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        api_key VARCHAR(255),
        secret_key VARCHAR(255),
        mode ENUM('sandbox', 'live') NOT NULL DEFAULT 'sandbox',
        settings_json TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (gateway_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) !== TRUE) {
        $success = false;
        $messages[] = "Error creating payment_settings table: " . $conn->error;
    } else {
        $messages[] = "payment_settings table created successfully";
    }

    // Insert default payment gateways if they don't exist
    $gateways = [
        ['Khalti', 'sandbox'],
        ['Bank Transfer', 'live']
    ];

    foreach ($gateways as $gateway) {
        $gatewayName = $gateway[0];
        $mode = $gateway[1];
        
        // Check if gateway already exists
        $stmt = $conn->prepare("SELECT id FROM payment_settings WHERE gateway_name = ?");
        $stmt->bind_param("s", $gatewayName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Insert gateway
            $stmt = $conn->prepare("INSERT INTO payment_settings (gateway_name, mode) VALUES (?, ?)");
            $stmt->bind_param("ss", $gatewayName, $mode);
            
            if ($stmt->execute()) {
                $messages[] = "Added default gateway: $gatewayName";
            } else {
                $messages[] = "Error adding default gateway $gatewayName: " . $stmt->error;
            }
        }
    }

    // Initialize wallet balances for existing users
    $sql = "INSERT IGNORE INTO wallet_balances (user_id, balance)
            SELECT id, 0.00 FROM users WHERE id NOT IN (SELECT user_id FROM wallet_balances)";
    
    if ($conn->query($sql) !== TRUE) {
        $messages[] = "Warning: Could not initialize wallet balances for all users: " . $conn->error;
    } else {
        $count = $conn->affected_rows;
        if ($count > 0) {
            $messages[] = "Initialized wallet balances for $count users";
        }
    }

    // Create system_settings table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) !== TRUE) {
        $success = false;
        $messages[] = "Error creating system_settings table: " . $conn->error;
    } else {
        $messages[] = "system_settings table created successfully";
    }

    // Insert default system settings if not exists
    $systemSettings = [
        'khalti_public_key' => 'test_public_key_123456789',
        'khalti_secret_key' => 'test_secret_key_123456789',
        'min_deposit_amount' => '100',
        'max_deposit_amount' => '50000',
        'wallet_enabled' => '1'
    ];

    foreach ($systemSettings as $key => $value) {
        $stmt = $conn->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);
            if ($stmt->execute()) {
                $messages[] = "Added default system setting: $key";
            } else {
                $messages[] = "Error adding default system setting $key: " . $stmt->error;
            }
        }
    }

    return ['success' => $success, 'messages' => $messages];
}

// Execute table creation
$result = createWalletTables();

// Display results if script is accessed directly
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    echo "<h1>Wallet Tables Setup</h1>";
    
    if ($result['success']) {
        echo "<div style='color: green; margin-bottom: 10px;'>Tables created successfully!</div>";
    } else {
        echo "<div style='color: red; margin-bottom: 10px;'>There were errors creating the tables.</div>";
    }
    
    echo "<ul>";
    foreach ($result['messages'] as $message) {
        echo "<li>" . htmlspecialchars($message) . "</li>";
    }
    echo "</ul>";
    
    echo "<p><a href='../admin/wallet-management.php'>Go to Wallet Management</a></p>";
}

echo "Wallet tables created successfully!";
?>
