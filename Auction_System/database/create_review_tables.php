<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once '../config/database.php';

// Get database connection
$conn = getDbConnection();

if (!$conn) {
    die("Database connection failed.");
}

// Check if tables already exist to avoid errors
$tables_exist = false;
$result = $conn->query("SHOW TABLES LIKE 'auction_chat_messages'");
if ($result && $result->num_rows > 0) {
    $tables_exist = true;
}

if ($tables_exist) {
    echo "Tables already exist. If you want to recreate them, drop them first.<br>";
} else {
    // Get the column type for user_id from users table
    $userIdType = "INT";
    $result = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'id'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $userIdType = $row['Type'];
    }
    
    // Get the column type for auction_id from auctions table
    $auctionIdType = "INT";
    $result = $conn->query("SHOW COLUMNS FROM auctions WHERE Field = 'id'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $auctionIdType = $row['Type'];
    }
    
    // Create auction_chat_messages table
    $sql = "CREATE TABLE IF NOT EXISTS auction_chat_messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id $userIdType NOT NULL,
        auction_id $auctionIdType NOT NULL,
        message_content TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_flagged TINYINT(1) DEFAULT 0,
        status ENUM('active', 'deleted') DEFAULT 'active',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table auction_chat_messages created successfully<br>";
    } else {
        echo "Error creating table auction_chat_messages: " . $conn->error . "<br>";
    }

    // Create product_reviews table
    $sql = "CREATE TABLE IF NOT EXISTS product_reviews (
        review_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id $userIdType NOT NULL,
        product_id $auctionIdType NOT NULL,
        review_content TEXT NOT NULL,
        rating INT(1) NOT NULL CHECK (rating BETWEEN 1 AND 5),
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'approved', 'deleted') DEFAULT 'pending',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES auctions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table product_reviews created successfully<br>";
    } else {
        echo "Error creating table product_reviews: " . $conn->error . "<br>";
    }

    // Create flagged_words table
    $sql = "CREATE TABLE IF NOT EXISTS flagged_words (
        id INT AUTO_INCREMENT PRIMARY KEY,
        word VARCHAR(50) NOT NULL UNIQUE,
        severity ENUM('low', 'medium', 'high') DEFAULT 'medium'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) === TRUE) {
        echo "Table flagged_words created successfully<br>";
        
        // Insert default flagged words
        $default_words = [
            ['profanity', 'high'],
            ['obscene', 'high'],
            ['offensive', 'medium'],
            ['inappropriate', 'medium'],
            ['spam', 'low'],
            ['scam', 'high'],
            ['fraud', 'high']
        ];
        
        $insert_sql = "INSERT IGNORE INTO flagged_words (word, severity) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_sql);
        
        if ($stmt) {
            $stmt->bind_param("ss", $word, $severity);
            
            foreach ($default_words as $word_data) {
                $word = $word_data[0];
                $severity = $word_data[1];
                $stmt->execute();
            }
            
            echo "Default flagged words inserted<br>";
        } else {
            echo "Error preparing statement: " . $conn->error . "<br>";
        }
    } else {
        echo "Error creating table flagged_words: " . $conn->error . "<br>";
    }
}

// Close connection
$conn->close();

echo "<p>Database setup completed. <a href='../admin/chat-monitoring.php'>Go to Chat Monitoring</a></p>";
?>
