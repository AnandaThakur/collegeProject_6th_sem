<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings-functions.php';

// Get database connection
$conn = getDbConnection();

// Check if the system_settings table already exists
$tableExists = $conn->query("SHOW TABLES LIKE 'system_settings'")->num_rows > 0;

if (!$tableExists) {
    // Create system_settings table
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT(11) NOT NULL AUTO_INCREMENT,
        setting_key VARCHAR(255) NOT NULL,
        setting_value TEXT,
        setting_type ENUM('text', 'textarea', 'dropdown', 'toggle', 'number') NOT NULL DEFAULT 'text',
        setting_options TEXT,
        setting_description TEXT,
        is_public TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "System settings table created successfully!<br>";
        
        // Insert default settings
        $defaultSettings = [
            [
                'key' => 'auction_rules',
                'value' => "1. All bids are final.\n2. Payment must be made within 24 hours of winning.\n3. The platform charges 5% commission on all successful auctions.\n4. Users must be verified to participate in auctions.\n5. Minimum bid increment is Rs 100.",
                'type' => 'textarea',
                'options' => '',
                'description' => 'Rules that apply to all auctions on the platform'
            ],
            [
                'key' => 'wallet_topup_methods',
                'value' => 'Khalti,Esewa,Bank Transfer,Manual',
                'type' => 'dropdown',
                'options' => 'Khalti,Esewa,Bank Transfer,Manual,Credit Card,PayPal',
                'description' => 'Available methods for topping up wallet'
            ],
            [
                'key' => 'platform_name',
                'value' => 'Online Auction Platform',
                'type' => 'text',
                'options' => '',
                'description' => 'Name of the auction platform'
            ],
            [
                'key' => 'contact_email',
                'value' => 'support@auction.com',
                'type' => 'text',
                'options' => '',
                'description' => 'Contact email for support'
            ],
            [
                'key' => 'minimum_bid_amount',
                'value' => '100',
                'type' => 'number',
                'options' => '',
                'description' => 'Minimum amount for placing a bid'
            ],
            [
                'key' => 'enable_registration',
                'value' => '1',
                'type' => 'toggle',
                'options' => '',
                'description' => 'Allow new users to register on the platform'
            ],
            [
                'key' => 'show_sidebar',
                'value' => '1',
                'type' => 'toggle',
                'options' => '',
                'description' => 'Show sidebar navigation on all pages'
            ],
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'toggle',
                'options' => '',
                'description' => 'Put the site in maintenance mode (only admins can access)'
            ]
        ];
        
        foreach ($defaultSettings as $setting) {
            createSettingIfNotExists(
                $setting['key'],
                $setting['value'],
                $setting['type'],
                $setting['options'],
                $setting['description']
            );
        }
        
        echo "Default settings added successfully!<br>";
    } else {
        echo "Error creating system_settings table: " . $conn->error . "<br>";
    }
} else {
    echo "System settings table already exists.<br>";
}

// Check if users table exists and has the required columns
$usersTableExists = $conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0;

if (!$usersTableExists) {
    // Create users table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255),
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
        status ENUM('pending', 'approved', 'deactivated') NOT NULL DEFAULT 'pending',
        is_verified TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Users table created successfully!<br>";
        
        // Create a default admin user
        $email = 'admin@example.com';
        $password = password_hash('admin123', PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO users (email, password, role, status, is_verified) VALUES (?, ?, 'admin', 'approved', 1)");
        $stmt->bind_param("ss", $email, $password);
        
        if ($stmt->execute()) {
            echo "Default admin user created successfully!<br>";
            echo "Email: admin@example.com<br>";
            echo "Password: admin123<br>";
        } else {
            echo "Error creating default admin user: " . $stmt->error . "<br>";
        }
    } else {
        echo "Error creating users table: " . $conn->error . "<br>";
    }
} else {
    // Check if role column exists
    $roleColumnExists = $conn->query("SHOW COLUMNS FROM users LIKE 'role'")->num_rows > 0;
    
    if (!$roleColumnExists) {
        // Add role column
        $sql = "ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user' AFTER password";
        
        if ($conn->query($sql) === TRUE) {
            echo "Role column added to users table successfully!<br>";
        } else {
            echo "Error adding role column: " . $conn->error . "<br>";
        }
    }
    
    // Create a default admin user if none exists
    createDefaultAdminIfNeeded();
}

echo "Database setup completed!<br>";
