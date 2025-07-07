<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// This script will check and create the necessary tables for the auction system
// Run this script once to set up your database

// Get database connection
$conn = getDbConnection();

// Check if connection is successful
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "Connected to database successfully.<br>";

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Create users table if it doesn't exist
if (!tableExists($conn, 'users')) {
    $sql = "CREATE TABLE users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table 'users' created successfully.<br>";
    } else {
        echo "Error creating table 'users': " . $conn->error . "<br>";
    }
}

// Create categories table if it doesn't exist
if (!tableExists($conn, 'categories')) {
    $sql = "CREATE TABLE categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        parent_id INT UNSIGNED NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table 'categories' created successfully.<br>";
        
        // Insert sample categories
        $sql = "INSERT INTO categories (name) VALUES 
            ('Electronics'),
            ('Fashion'),
            ('Home & Garden'),
            ('Collectibles'),
            ('Vehicles'),
            ('Art'),
            ('Jewelry'),
            ('Sports'),
            ('Toys & Games'),
            ('Books & Media')";
            
        if ($conn->query($sql) === TRUE) {
            echo "Sample categories inserted successfully.<br>";
            
            // Insert child categories
            $sql = "INSERT INTO categories (name, parent_id) VALUES 
                ('Smartphones', 1),
                ('Laptops', 1),
                ('Cameras', 1),
                ('Men\'s Clothing', 2),
                ('Women\'s Clothing', 2),
                ('Watches', 2),
                ('Furniture', 3),
                ('Kitchen', 3),
                ('Garden', 3),
                ('Coins', 4),
                ('Stamps', 4),
                ('Memorabilia', 4)";
                
            if ($conn->query($sql) === TRUE) {
                echo "Sample child categories inserted successfully.<br>";
            } else {
                echo "Error inserting child categories: " . $conn->error . "<br>";
            }
        } else {
            echo "Error inserting sample categories: " . $conn->error . "<br>";
        }
    } else {
        echo "Error creating table 'categories': " . $conn->error . "<br>";
    }
}

// Create auctions table if it doesn't exist
if (!tableExists($conn, 'auctions')) {
    $sql = "CREATE TABLE auctions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        seller_id INT UNSIGNED NOT NULL,
        start_price DECIMAL(10, 2) NOT NULL,
        reserve_price DECIMAL(10, 2),
        current_price DECIMAL(10, 2),
        start_date DATETIME,
        end_date DATETIME,
        status ENUM('pending', 'approved', 'rejected', 'paused', 'ongoing', 'ended') DEFAULT 'pending',
        rejection_reason TEXT,
        image_url VARCHAR(255),
        category_id INT UNSIGNED,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table 'auctions' created successfully.<br>";
    } else {
        echo "Error creating table 'auctions': " . $conn->error . "<br>";
    }
}

// Create bids table if it doesn't exist
if (!tableExists($conn, 'bids')) {
    $sql = "CREATE TABLE bids (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        auction_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        bid_amount DECIMAL(10, 2) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table 'bids' created successfully.<br>";
    } else {
        echo "Error creating table 'bids': " . $conn->error . "<br>";
    }
}

// Create auction_images table if it doesn't exist
if (!tableExists($conn, 'auction_images')) {
    $sql = "CREATE TABLE auction_images (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        auction_id INT UNSIGNED NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        is_primary BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table 'auction_images' created successfully.<br>";
    } else {
        echo "Error creating table 'auction_images': " . $conn->error . "<br>";
    }
}

// Create auction_views table if it doesn't exist
if (!tableExists($conn, 'auction_views')) {
    $sql = "CREATE TABLE auction_views (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        auction_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED,
        ip_address VARCHAR(45),
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table 'auction_views' created successfully.<br>";
    } else {
        echo "Error creating table 'auction_views': " . $conn->error . "<br>";
    }
}

// Create auction_favorites table if it doesn't exist
if (!tableExists($conn, 'auction_favorites')) {
    $sql = "CREATE TABLE auction_favorites (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        auction_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY (auction_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table 'auction_favorites' created successfully.<br>";
    } else {
        echo "Error creating table 'auction_favorites': " . $conn->error . "<br>";
    }
}

// Create auction_notifications table if it doesn't exist
if (!tableExists($conn, 'auction_notifications')) {
    $sql = "CREATE TABLE auction_notifications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        auction_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        type ENUM('outbid', 'ending_soon', 'ended', 'won', 'new_bid', 'status_change') NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table 'auction_notifications' created successfully.<br>";
    } else {
        echo "Error creating table 'auction_notifications': " . $conn->error . "<br>";
    }
}

// Create admin user if it doesn't exist
$adminEmail = 'admin@auction.com';
$adminPassword = password_hash('admin123', PASSWORD_BCRYPT);

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $adminEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, role, status, is_verified) VALUES (?, ?, 'Admin', 'User', 'admin', 'approved', 1)");
    $stmt->bind_param("ss", $adminEmail, $adminPassword);
    
    if ($stmt->execute()) {
        echo "Admin user created successfully.<br>";
    } else {
        echo "Error creating admin user: " . $stmt->error . "<br>";
    }
} else {
    echo "Admin user already exists.<br>";
}

// Create sample seller user if it doesn't exist
$sellerEmail = 'seller@auction.com';
$sellerPassword = password_hash('seller123', PASSWORD_BCRYPT);

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $sellerEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, role, status, is_verified) VALUES (?, ?, 'Sample', 'Seller', 'seller', 'approved', 1)");
    $stmt->bind_param("ss", $sellerEmail, $sellerPassword);
    
    if ($stmt->execute()) {
        echo "Sample seller user created successfully.<br>";
        
        // Get the seller ID
        $sellerId = $conn->insert_id;
        
        // Create sample auctions
        $auctions = [
            [
                'title' => 'Vintage Camera Collection',
                'description' => 'A collection of 5 vintage cameras from the 1950s-1970s. All in working condition.',
                'start_price' => 199.99,
                'reserve_price' => 299.99,
                'category_id' => 3, // Cameras
                'status' => 'pending'
            ],
            [
                'title' => 'Apple MacBook Pro 2022',
                'description' => 'Slightly used MacBook Pro with M1 chip, 16GB RAM, 512GB SSD. Includes charger and original box.',
                'start_price' => 1299.99,
                'reserve_price' => 1499.99,
                'category_id' => 2, // Laptops
                'status' => 'approved'
            ],
            [
                'title' => 'Antique Gold Pocket Watch',
                'description' => 'Beautiful 18k gold pocket watch from the 1890s. Still keeps perfect time.',
                'start_price' => 599.99,
                'reserve_price' => 799.99,
                'category_id' => 6, // Watches
                'status' => 'ongoing'
            ],
            [
                'title' => 'Handcrafted Wooden Dining Table',
                'description' => 'Solid oak dining table, handcrafted by local artisan. Seats 6-8 people.',
                'start_price' => 899.99,
                'reserve_price' => 1199.99,
                'category_id' => 7, // Furniture
                'status' => 'paused'
            ],
            [
                'title' => 'Rare Comic Book Collection',
                'description' => 'Collection of 25 rare comic books from the 1960s-1980s. All in excellent condition.',
                'start_price' => 499.99,
                'reserve_price' => 699.99,
                'category_id' => 12, // Memorabilia
                'status' => 'ended'
            ]
        ];
        
        foreach ($auctions as $auction) {
            $stmt = $conn->prepare("INSERT INTO auctions (title, description, seller_id, start_price, reserve_price, category_id, status, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Set dates based on status
            $startDate = null;
            $endDate = null;
            
            switch ($auction['status']) {
                case 'approved':
                    $startDate = date('Y-m-d H:i:s', strtotime('+1 day'));
                    $endDate = date('Y-m-d H:i:s', strtotime('+7 days'));
                    break;
                case 'ongoing':
                    $startDate = date('Y-m-d H:i:s', strtotime('-1 day'));
                    $endDate = date('Y-m-d H:i:s', strtotime('+6 days'));
                    break;
                case 'paused':
                    $startDate = date('Y-m-d H:i:s', strtotime('-2 days'));
                    $endDate = date('Y-m-d H:i:s', strtotime('+5 days'));
                    break;
                case 'ended':
                    $startDate = date('Y-m-d H:i:s', strtotime('-10 days'));
                    $endDate = date('Y-m-d H:i:s', strtotime('-3 days'));
                    break;
            }
            
            $stmt->bind_param("ssiddiiss", 
                $auction['title'], 
                $auction['description'], 
                $sellerId, 
                $auction['start_price'], 
                $auction['reserve_price'], 
                $auction['category_id'], 
                $auction['status'],
                $startDate,
                $endDate
            );
            
            if ($stmt->execute()) {
                echo "Sample auction '{$auction['title']}' created successfully.<br>";
            } else {
                echo "Error creating sample auction: " . $stmt->error . "<br>";
            }
        }
    } else {
        echo "Error creating sample seller user: " . $stmt->error . "<br>";
    }
} else {
    echo "Sample seller user already exists.<br>";
}

// Create sample buyer user if it doesn't exist
$buyerEmail = 'buyer@auction.com';
$buyerPassword = password_hash('buyer123', PASSWORD_BCRYPT);

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $buyerEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, role, status, is_verified) VALUES (?, ?, 'Sample', 'Buyer', 'buyer', 'approved', 1)");
    $stmt->bind_param("ss", $buyerEmail, $buyerPassword);
    
    if ($stmt->execute()) {
        echo "Sample buyer user created successfully.<br>";
        
        // Get the buyer ID
        $buyerId = $conn->insert_id;
        
        // Add some sample bids
        $stmt = $conn->prepare("SELECT id FROM auctions WHERE status IN ('ongoing', 'paused', 'ended') LIMIT 3");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $auctionId = $row['id'];
            
            // Get the start price
            $stmt2 = $conn->prepare("SELECT start_price FROM auctions WHERE id = ?");
            $stmt2->bind_param("i", $auctionId);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $auction = $result2->fetch_assoc();
            
            // Create a bid 10% higher than the start price
            $bidAmount = $auction['start_price'] * 1.1;
            
            $stmt2 = $conn->prepare("INSERT INTO bids (auction_id, user_id, bid_amount) VALUES (?, ?, ?)");
            $stmt2->bind_param("iid", $auctionId, $buyerId, $bidAmount);
            
            if ($stmt2->execute()) {
                echo "Sample bid created for auction ID $auctionId.<br>";
                
                // Update the current price
                $stmt3 = $conn->prepare("UPDATE auctions SET current_price = ? WHERE id = ?");
                $stmt3->bind_param("di", $bidAmount, $auctionId);
                $stmt3->execute();
            } else {
                echo "Error creating sample bid: " . $stmt2->error . "<br>";
            }
        }
    } else {
        echo "Error creating sample buyer user: " . $stmt->error . "<br>";
    }
} else {
    echo "Sample buyer user already exists.<br>";
}

echo "<br>Database setup completed successfully!";
echo "<br><a href='index.php'>Go to Homepage</a>";
?>
