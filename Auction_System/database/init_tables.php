<?php
// Initialize database tables
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "<h1>Initializing Database Tables</h1>";
    
    // Get database connection
    $conn = getDbConnection();
    
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    echo "<p>Connected to database successfully.</p>";
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        role ENUM('admin', 'buyer', 'seller') NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'deactivated') DEFAULT 'pending',
        is_verified TINYINT(1) DEFAULT 0,
        rejection_reason TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>Users table created or already exists.</p>";
    } else {
        throw new Exception("Error creating users table: " . $conn->error);
    }
    
    // Create auctions table - SIMPLIFIED VERSION WITHOUT FOREIGN KEYS FIRST
    $sql = "CREATE TABLE IF NOT EXISTS auctions (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        seller_id INT(11) UNSIGNED NOT NULL,
        starting_price DECIMAL(10,2) NOT NULL,
        current_price DECIMAL(10,2) DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected', 'paused', 'ongoing', 'ended') DEFAULT 'pending',
        rejection_reason TEXT NULL,
        start_date DATETIME DEFAULT NULL,
        end_date DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>Auctions table created or already exists.</p>";
        
        // Add foreign key separately
        $sql = "ALTER TABLE auctions ADD CONSTRAINT fk_seller_id 
                FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE";
        
        // Try to add the constraint, but don't fail if it already exists
        $conn->query($sql);
        
    } else {
        throw new Exception("Error creating auctions table: " . $conn->error);
    }
    
    // Create bids table - SIMPLIFIED VERSION WITHOUT FOREIGN KEYS FIRST
    $sql = "CREATE TABLE IF NOT EXISTS bids (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        auction_id INT(11) UNSIGNED NOT NULL,
        user_id INT(11) UNSIGNED NOT NULL,
        bid_amount DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>Bids table created or already exists.</p>";
        
        // Add foreign keys separately
        $sql = "ALTER TABLE bids ADD CONSTRAINT fk_auction_id 
                FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE";
        $conn->query($sql);
        
        $sql = "ALTER TABLE bids ADD CONSTRAINT fk_user_id 
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE";
        $conn->query($sql);
        
    } else {
        throw new Exception("Error creating bids table: " . $conn->error);
    }
    
    // Create auction_images table - SIMPLIFIED VERSION WITHOUT FOREIGN KEYS FIRST
    $sql = "CREATE TABLE IF NOT EXISTS auction_images (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        auction_id INT(11) UNSIGNED NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        is_primary TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>Auction images table created or already exists.</p>";
        
        // Add foreign key separately
        $sql = "ALTER TABLE auction_images ADD CONSTRAINT fk_auction_image_id 
                FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE";
        $conn->query($sql);
        
    } else {
        throw new Exception("Error creating auction_images table: " . $conn->error);
    }
    
    // Create categories table
    $sql = "CREATE TABLE IF NOT EXISTS categories (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        parent_id INT(11) UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>Categories table created or already exists.</p>";
        
        // Add foreign key constraint separately to avoid circular reference issues
        $sql = "ALTER TABLE categories ADD CONSTRAINT fk_parent_category 
                FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE";
        
        // Try to add the constraint, but don't fail if it already exists
        $conn->query($sql);
        
    } else {
        throw new Exception("Error creating categories table: " . $conn->error);
    }
    
    // Create auction_categories table (many-to-many relationship)
    $sql = "CREATE TABLE IF NOT EXISTS auction_categories (
        auction_id INT(11) UNSIGNED NOT NULL,
        category_id INT(11) UNSIGNED NOT NULL,
        PRIMARY KEY (auction_id, category_id)
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>Auction categories table created or already exists.</p>";
        
        // Add foreign keys separately
        $sql = "ALTER TABLE auction_categories ADD CONSTRAINT fk_ac_auction_id 
                FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE";
        $conn->query($sql);
        
        $sql = "ALTER TABLE auction_categories ADD CONSTRAINT fk_ac_category_id 
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE";
        $conn->query($sql);
        
    } else {
        throw new Exception("Error creating auction_categories table: " . $conn->error);
    }
    
    // Create admin user if it doesn't exist
    $adminEmail = 'admin@auction.com';
    $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $adminEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, role, status, is_verified) VALUES (?, ?, 'Admin', 'User', 'admin', 'approved', 1)");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $adminEmail, $adminPassword);
        
        if ($stmt->execute()) {
            echo "<p>Admin user created successfully.</p>";
        } else {
            throw new Exception("Error creating admin user: " . $stmt->error);
        }
    } else {
        echo "<p>Admin user already exists.</p>";
        
        // Update admin user to ensure it's verified and approved
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1, status = 'approved', password = ? WHERE email = ? AND role = 'admin'");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $adminPassword, $adminEmail);
        
        if ($stmt->execute()) {
            echo "<p>Admin user updated successfully.</p>";
        } else {
            throw new Exception("Error updating admin user: " . $stmt->error);
        }
    }
    
    // Create sample data for testing
    echo "<h2>Creating Sample Data</h2>";
    
    // Create sample categories
    $categories = [
        'Electronics' => [
            'Smartphones',
            'Laptops',
            'Cameras',
            'Audio'
        ],
        'Fashion' => [
            'Men\'s Clothing',
            'Women\'s Clothing',
            'Watches',
            'Jewelry'
        ],
        'Home & Garden' => [
            'Furniture',
            'Appliances',
            'Decor',
            'Garden'
        ],
        'Collectibles' => [
            'Coins',
            'Stamps',
            'Trading Cards',
            'Memorabilia'
        ]
    ];
    
    foreach ($categories as $mainCategory => $subCategories) {
        // Insert main category using direct query instead of prepared statement
        $mainCategoryEscaped = $conn->real_escape_string($mainCategory);
        $sql = "INSERT IGNORE INTO categories (name) VALUES ('$mainCategoryEscaped')";
        
        if ($conn->query($sql)) {
            // Get main category ID
            $mainCategoryId = $conn->insert_id;
            
            // If insert_id is 0, the category already existed, so get its ID
            if ($mainCategoryId == 0) {
                $result = $conn->query("SELECT id FROM categories WHERE name = '$mainCategoryEscaped'");
                if ($result && $result->num_rows > 0) {
                    $mainCategoryId = $result->fetch_assoc()['id'];
                }
            }
            
            // Insert subcategories
            foreach ($subCategories as $subCategory) {
                $subCategoryEscaped = $conn->real_escape_string($subCategory);
                $sql = "INSERT IGNORE INTO categories (name, parent_id) VALUES ('$subCategoryEscaped', $mainCategoryId)";
                $conn->query($sql);
            }
        }
    }
    
    echo "<p>Sample categories created.</p>";
    
    // Create sample users (buyers and sellers)
    $users = [
        [
            'email' => 'seller1@example.com',
            'password' => password_hash('password123', PASSWORD_BCRYPT),
            'first_name' => 'John',
            'last_name' => 'Seller',
            'role' => 'seller',
            'status' => 'approved',
            'is_verified' => 1
        ],
        [
            'email' => 'seller2@example.com',
            'password' => password_hash('password123', PASSWORD_BCRYPT),
            'first_name' => 'Jane',
            'last_name' => 'Seller',
            'role' => 'seller',
            'status' => 'approved',
            'is_verified' => 1
        ],
        [
            'email' => 'buyer1@example.com',
            'password' => password_hash('password123', PASSWORD_BCRYPT),
            'first_name' => 'Bob',
            'last_name' => 'Buyer',
            'role' => 'buyer',
            'status' => 'approved',
            'is_verified' => 1
        ],
        [
            'email' => 'buyer2@example.com',
            'password' => password_hash('password123', PASSWORD_BCRYPT),
            'first_name' => 'Alice',
            'last_name' => 'Buyer',
            'role' => 'buyer',
            'status' => 'approved',
            'is_verified' => 1
        ]
    ];
    
    foreach ($users as $user) {
        // Check if user exists
        $email = $conn->real_escape_string($user['email']);
        $result = $conn->query("SELECT id FROM users WHERE email = '$email'");
        
        if ($result && $result->num_rows == 0) {
            // User doesn't exist, insert it using direct query
            $password = $conn->real_escape_string($user['password']);
            $firstName = $conn->real_escape_string($user['first_name']);
            $lastName = $conn->real_escape_string($user['last_name']);
            $role = $conn->real_escape_string($user['role']);
            $status = $conn->real_escape_string($user['status']);
            $isVerified = $user['is_verified'];
            
            $sql = "INSERT INTO users (email, password, first_name, last_name, role, status, is_verified) 
                    VALUES ('$email', '$password', '$firstName', '$lastName', '$role', '$status', $isVerified)";
            
            if ($conn->query($sql)) {
                echo "<p>Created user: {$user['email']}</p>";
            } else {
                echo "<p>Error creating user {$user['email']}: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>User {$user['email']} already exists.</p>";
        }
    }
    
    echo "<p>Sample users created.</p>";
    
    // Create sample auctions
    $auctions = [
        [
            'title' => 'iPhone 13 Pro - Like New',
            'description' => 'Apple iPhone 13 Pro in excellent condition. Includes original box, charger, and accessories.',
            'seller_email' => 'seller1@example.com',
            'starting_price' => 699.99,
            'status' => 'pending',
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days'))
        ],
        [
            'title' => 'Vintage Rolex Watch',
            'description' => 'Authentic vintage Rolex watch from 1970s. Excellent working condition with minor signs of wear.',
            'seller_email' => 'seller2@example.com',
            'starting_price' => 2500.00,
            'status' => 'approved',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+14 days'))
        ],
        [
            'title' => 'MacBook Pro M1 - 16GB RAM',
            'description' => 'MacBook Pro with M1 chip, 16GB RAM, and 512GB SSD. Purchased 6 months ago, like new condition.',
            'seller_email' => 'seller1@example.com',
            'starting_price' => 1200.00,
            'status' => 'ongoing',
            'start_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+4 days'))
        ],
        [
            'title' => 'Antique Oak Dining Table',
            'description' => 'Beautiful antique oak dining table from the early 1900s. Seats 6-8 people comfortably.',
            'seller_email' => 'seller2@example.com',
            'starting_price' => 850.00,
            'status' => 'pending',
            'start_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+10 days'))
        ]
    ];
    
    foreach ($auctions as $auction) {
        // Get seller ID using direct query
        $sellerEmail = $conn->real_escape_string($auction['seller_email']);
        $result = $conn->query("SELECT id FROM users WHERE email = '$sellerEmail'");
        
        if ($result && $result->num_rows > 0) {
            $sellerId = $result->fetch_assoc()['id'];
            
            // Check if auction already exists
            $title = $conn->real_escape_string($auction['title']);
            $result = $conn->query("SELECT id FROM auctions WHERE title = '$title' AND seller_id = $sellerId");
            
            if ($result && $result->num_rows == 0) {
                // Auction doesn't exist, insert it using direct query
                $description = $conn->real_escape_string($auction['description']);
                $startingPrice = $auction['starting_price'];
                $currentPrice = $startingPrice;
                $status = $conn->real_escape_string($auction['status']);
                $startDate = $conn->real_escape_string($auction['start_date']);
                $endDate = $conn->real_escape_string($auction['end_date']);
                
                $sql = "INSERT INTO auctions (title, description, seller_id, starting_price, current_price, status, start_date, end_date) 
                        VALUES ('$title', '$description', $sellerId, $startingPrice, $currentPrice, '$status', '$startDate', '$endDate')";
                
                if ($conn->query($sql)) {
                    $auctionId = $conn->insert_id;
                    echo "<p>Created auction: {$auction['title']}</p>";
                    
                    // Add some bids for ongoing auctions
                    if ($auction['status'] == 'ongoing') {
                        // Get buyer IDs
                        $result = $conn->query("SELECT id FROM users WHERE role = 'buyer' LIMIT 2");
                        
                        $buyerIds = [];
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $buyerIds[] = $row['id'];
                            }
                        }
                        
                        if (count($buyerIds) > 0) {
                            // Add bids
                            $bidAmount = $auction['starting_price'];
                            
                            for ($i = 0; $i < 3; $i++) {
                                $bidAmount += rand(50, 100);
                                $buyerId = $buyerIds[array_rand($buyerIds)];
                                
                                $sql = "INSERT INTO bids (auction_id, user_id, bid_amount) 
                                        VALUES ($auctionId, $buyerId, $bidAmount)";
                                $conn->query($sql);
                                
                                // Update current price
                                $sql = "UPDATE auctions SET current_price = $bidAmount WHERE id = $auctionId";
                                $conn->query($sql);
                            }
                            
                            echo "<p>Added bids for auction: {$auction['title']}</p>";
                        }
                    }
                } else {
                    echo "<p>Error creating auction: " . $conn->error . "</p>";
                }
            } else {
                echo "<p>Auction '{$auction['title']}' already exists.</p>";
            }
        } else {
            echo "<p>Seller {$auction['seller_email']} not found.</p>";
        }
    }
    
    echo "<p>Sample auctions created.</p>";
    echo "<h2>Database initialization completed successfully!</h2>";
    echo "<p><a href='../index.php' class='btn btn-primary'>Return to homepage</a></p>";
    
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>";
    echo "<h2>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
    
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    
    echo "<p><a href='../index.php' class='btn btn-primary'>Return to homepage</a></p>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Initialization - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #0d6efd;
            margin-bottom: 20px;
        }
        p {
            margin-bottom: 10px;
        }
        .text-success {
            color: #198754;
        }
        .text-danger {
            color: #dc3545;
        }
        pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn-primary {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- PHP output will appear here -->
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
