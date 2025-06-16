<?php
/**
 * Bid Monitoring Database Update Script
 * 
 * This script updates the database schema to support the bid monitoring functionality.
 * It adds necessary columns and indexes to the auctions and bids tables.
 */

// Include database configuration
require_once 'config/database.php';
require_once 'includes/functions.php';

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bid Monitoring Database Update</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
            background-color: #f8f9fa;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        .operation {
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 5px;
            background-color: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .success {
            border-left: 4px solid #28a745;
        }
        .error {
            border-left: 4px solid #dc3545;
        }
        .warning {
            border-left: 4px solid #ffc107;
        }
        .info {
            border-left: 4px solid #17a2b8;
        }
        .operation-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .operation-message {
            margin-bottom: 0;
            color: #6c757d;
        }
        .success-message {
            color: #28a745;
        }
        .error-message {
            color: #dc3545;
        }
        .warning-message {
            color: #ffc107;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .btn-dashboard {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bid Monitoring Database Update</h1>
        <p class="text-muted">This script will update your database to support the bid monitoring functionality.</p>
    </div>

    <div class="operations">
<?php

// Function to log operation results
function logOperation($title, $success, $message, $type = null) {
    $class = $success ? 'success' : 'error';
    if ($type) {
        $class = $type;
    }
    $messageClass = $success ? 'success-message' : 'error-message';
    if ($type === 'warning') {
        $messageClass = 'warning-message';
    } elseif ($type === 'info') {
        $messageClass = 'text-info';
    }
    
    echo "<div class='operation $class'>";
    echo "<div class='operation-title'>$title</div>";
    echo "<p class='operation-message $messageClass'>$message</p>";
    echo "</div>";
}

// Connect to database
try {
    $conn = getDbConnection();
    logOperation("Database Connection", true, "Successfully connected to the database.");
} catch (Exception $e) {
    logOperation("Database Connection", false, "Failed to connect to the database: " . $e->getMessage());
    echo "</div><div class='footer'><p>Please fix the database connection issues and try again.</p></div></body></html>";
    exit;
}

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Function to check if a column exists in a table
function columnExists($conn, $tableName, $columnName) {
    $result = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
    return $result->num_rows > 0;
}

// Function to check if an index exists in a table
function indexExists($conn, $tableName, $indexName) {
    $result = $conn->query("SHOW INDEX FROM `$tableName` WHERE Key_name = '$indexName'");
    return $result->num_rows > 0;
}

// Check if users table exists, create if not
if (!tableExists($conn, 'users')) {
    logOperation("Checking users table", false, "Users table does not exist. Creating it now...");
    
    $sql = "CREATE TABLE `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `email` varchar(255) NOT NULL,
        `password` varchar(255) NOT NULL,
        `first_name` varchar(100) NOT NULL,
        `last_name` varchar(100) NOT NULL,
        `role` enum('user','admin') NOT NULL DEFAULT 'user',
        `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        logOperation("Creating users table", true, "Users table created successfully.");
    } else {
        logOperation("Creating users table", false, "Error creating users table: " . $conn->error);
    }
} else {
    logOperation("Checking users table", true, "Users table exists.", "info");
}

// Check if auctions table exists, create if not
if (!tableExists($conn, 'auctions')) {
    logOperation("Checking auctions table", false, "Auctions table does not exist. Creating it now...");
    
    $sql = "CREATE TABLE `auctions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `seller_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `description` text NOT NULL,
        `start_price` decimal(10,2) NOT NULL,
        `current_price` decimal(10,2) NOT NULL,
        `min_bid_increment` decimal(10,2) NOT NULL DEFAULT '1.00',
        `category_id` int(11) DEFAULT NULL,
        `image_url` varchar(255) DEFAULT NULL,
        `status` enum('pending','approved','rejected','ongoing','paused','ended','completed') NOT NULL DEFAULT 'pending',
        `start_date` datetime DEFAULT NULL,
        `end_date` datetime DEFAULT NULL,
        `winner_id` int(11) DEFAULT NULL,
        `winning_bid` decimal(10,2) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `seller_id` (`seller_id`),
        KEY `category_id` (`category_id`),
        KEY `status` (`status`),
        KEY `winner_id` (`winner_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        logOperation("Creating auctions table", true, "Auctions table created successfully.");
    } else {
        logOperation("Creating auctions table", false, "Error creating auctions table: " . $conn->error);
    }
} else {
    logOperation("Checking auctions table", true, "Auctions table exists.", "info");
}

// Check if bids table exists, create if not
if (!tableExists($conn, 'bids')) {
    logOperation("Checking bids table", false, "Bids table does not exist. Creating it now...");
    
    $sql = "CREATE TABLE `bids` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `auction_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `bid_amount` decimal(10,2) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `auction_id` (`auction_id`),
        KEY `user_id` (`user_id`),
        KEY `bid_amount` (`bid_amount`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        logOperation("Creating bids table", true, "Bids table created successfully.");
    } else {
        logOperation("Creating bids table", false, "Error creating bids table: " . $conn->error);
    }
} else {
    logOperation("Checking bids table", true, "Bids table exists.", "info");
}

// Check if categories table exists, create if not
if (!tableExists($conn, 'categories')) {
    logOperation("Checking categories table", false, "Categories table does not exist. Creating it now...");
    
    $sql = "CREATE TABLE `categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `parent_id` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `parent_id` (`parent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        logOperation("Creating categories table", true, "Categories table created successfully.");
    } else {
        logOperation("Creating categories table", false, "Error creating categories table: " . $conn->error);
    }
} else {
    logOperation("Checking categories table", true, "Categories table exists.", "info");
}

// Add min_bid_increment column to auctions table if it doesn't exist
if (!columnExists($conn, 'auctions', 'min_bid_increment')) {
    logOperation("Checking min_bid_increment column", false, "min_bid_increment column does not exist. Adding it now...");
    
    $sql = "ALTER TABLE `auctions` ADD COLUMN `min_bid_increment` decimal(10,2) NOT NULL DEFAULT '1.00' AFTER `current_price`";
    
    if ($conn->query($sql)) {
        logOperation("Adding min_bid_increment column", true, "min_bid_increment column added successfully.");
        
        // Set default values for existing auctions
        $updateSql = "UPDATE `auctions` SET `min_bid_increment` = '1.00' WHERE `min_bid_increment` = 0";
        if ($conn->query($updateSql)) {
            logOperation("Setting default min_bid_increment values", true, "Default min_bid_increment values set successfully.");
        } else {
            logOperation("Setting default min_bid_increment values", false, "Error setting default min_bid_increment values: " . $conn->error);
        }
    } else {
        logOperation("Adding min_bid_increment column", false, "Error adding min_bid_increment column: " . $conn->error);
    }
} else {
    logOperation("Checking min_bid_increment column", true, "min_bid_increment column already exists.", "info");
}

// Add winner_id column to auctions table if it doesn't exist
if (!columnExists($conn, 'auctions', 'winner_id')) {
    logOperation("Checking winner_id column", false, "winner_id column does not exist. Adding it now...");
    
    $sql = "ALTER TABLE `auctions` ADD COLUMN `winner_id` int(11) DEFAULT NULL AFTER `end_date`";
    
    if ($conn->query($sql)) {
        logOperation("Adding winner_id column", true, "winner_id column added successfully.");
        
        // Add foreign key constraint
        $fkSql = "ALTER TABLE `auctions` ADD CONSTRAINT `fk_winner_id` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL";
        if ($conn->query($fkSql)) {
            logOperation("Adding winner_id foreign key", true, "winner_id foreign key added successfully.");
        } else {
            logOperation("Adding winner_id foreign key", false, "Error adding winner_id foreign key: " . $conn->error, "warning");
        }
    } else {
        logOperation("Adding winner_id column", false, "Error adding winner_id column: " . $conn->error);
    }
} else {
    logOperation("Checking winner_id column", true, "winner_id column already exists.", "info");
}

// Add winning_bid column to auctions table if it doesn't exist
if (!columnExists($conn, 'auctions', 'winning_bid')) {
    logOperation("Checking winning_bid column", false, "winning_bid column does not exist. Adding it now...");
    
    $sql = "ALTER TABLE `auctions` ADD COLUMN `winning_bid` decimal(10,2) DEFAULT NULL AFTER `winner_id`";
    
    if ($conn->query($sql)) {
        logOperation("Adding winning_bid column", true, "winning_bid column added successfully.");
    } else {
        logOperation("Adding winning_bid column", false, "Error adding winning_bid column: " . $conn->error);
    }
} else {
    logOperation("Checking winning_bid column", true, "winning_bid column already exists.", "info");
}

// Add index on auction_id in bids table if it doesn't exist
if (tableExists($conn, 'bids') && !indexExists($conn, 'bids', 'auction_id')) {
    logOperation("Checking auction_id index", false, "auction_id index does not exist. Adding it now...");
    
    $sql = "ALTER TABLE `bids` ADD INDEX `auction_id` (`auction_id`)";
    
    if ($conn->query($sql)) {
        logOperation("Creating index on auction_id", true, "Index on auction_id created successfully.");
    } else {
        logOperation("Creating index on auction_id", false, "Error creating index on auction_id: " . $conn->error);
    }
} else if (tableExists($conn, 'bids')) {
    logOperation("Checking auction_id index", true, "auction_id index already exists.", "info");
}

// Add index on created_at in bids table if it doesn't exist
if (tableExists($conn, 'bids') && !indexExists($conn, 'bids', 'created_at')) {
    logOperation("Checking created_at index", false, "created_at index does not exist. Adding it now...");
    
    $sql = "ALTER TABLE `bids` ADD INDEX `created_at` (`created_at`)";
    
    if ($conn->query($sql)) {
        logOperation("Creating index on created_at", true, "Index on created_at created successfully.");
    } else {
        logOperation("Creating index on created_at", false, "Error creating index on created_at: " . $conn->error);
    }
} else if (tableExists($conn, 'bids')) {
    logOperation("Checking created_at index", true, "created_at index already exists.", "info");
}

// Add index on bid_amount in bids table if it doesn't exist
if (tableExists($conn, 'bids') && !indexExists($conn, 'bids', 'bid_amount')) {
    logOperation("Checking bid_amount index", false, "bid_amount index does not exist. Adding it now...");
    
    $sql = "ALTER TABLE `bids` ADD INDEX `bid_amount` (`bid_amount`)";
    
    if ($conn->query($sql)) {
        logOperation("Creating index on bid_amount", true, "Index on bid_amount created successfully.");
    } else {
        logOperation("Creating index on bid_amount", false, "Error creating index on bid_amount: " . $conn->error);
    }
} else if (tableExists($conn, 'bids')) {
    logOperation("Checking bid_amount index", true, "bid_amount index already exists.", "info");
}

// Close database connection
$conn->close();

?>
    </div>

    <div class="footer">
        <h4>Database Update Complete</h4>
        <p>Your database has been updated to support the bid monitoring functionality.</p>
        <a href="admin/bids.php" class="btn btn-primary btn-dashboard">Go to Bid Monitoring Dashboard</a>
    </div>
</body>
</html>
