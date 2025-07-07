<?php
// Database configuration with enhanced error handling and connection management
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'auction_platform');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');
define('DB_SOCKET', ''); // Leave empty if not using socket

// Global connection variable for connection pooling
$GLOBALS['db_connection'] = null;

/**
 * Get database connection with improved error handling and connection pooling
 * 
 * @param bool $forceNew Force a new connection even if one exists
 * @return mysqli|null Database connection or null on failure
 */
function getDbConnection($forceNew = false) {
    // Use existing connection if available and not forcing new
    if (!$forceNew && isset($GLOBALS['db_connection']) && $GLOBALS['db_connection'] instanceof mysqli && $GLOBALS['db_connection']->ping()) {
        return $GLOBALS['db_connection'];
    }

    try {
        // Set error reporting mode
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        // Create connection with timeout parameters
        $conn = new mysqli(
            DB_HOST, 
            DB_USER, 
            DB_PASS, 
            null, // Don't select database yet
            DB_PORT,
            DB_SOCKET
        );
        
        // Set connection timeout
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        
        // Check connection
        if ($conn->connect_error) {
            logDatabaseError("Connection failed: " . $conn->connect_error);
            return null;
        }
        
        // Create database if it doesn't exist
        $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
        if (!$conn->query($sql)) {
            logDatabaseError("Error creating database: " . $conn->error);
            return null;
        }
        
        // Select the database
        if (!$conn->select_db(DB_NAME)) {
            logDatabaseError("Error selecting database: " . $conn->error);
            return null;
        }
        
        // Set charset to ensure proper encoding
        if (!$conn->set_charset(DB_CHARSET)) {
            logDatabaseError("Error setting charset: " . $conn->error);
            // Continue anyway as this is not critical
        }
        
        // Store connection in global variable for reuse
        $GLOBALS['db_connection'] = $conn;
        
        // Check if tables exist and create them if needed
        checkAndCreateTables($conn);
        
        return $conn;
        
    } catch (Exception $e) {
        logDatabaseError("Database connection error: " . $e->getMessage());
        return null;
    }
}

/**
 * Log database errors to error log with detailed information
 */
function logDatabaseError($message, $query = null, $data = null) {
    $errorInfo = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'query' => $query,
        'data' => $data,
        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
    ];
    
    error_log("DATABASE ERROR: " . json_encode($errorInfo, JSON_UNESCAPED_SLASHES));
}

/**
 * Execute a database query with proper error handling
 * 
 * @param mysqli $conn Database connection
 * @param string $query SQL query
 * @param array $params Parameters for prepared statement
 * @param string $types Types of parameters (i: integer, d: double, s: string, b: blob)
 * @return mysqli_result|bool Query result or false on failure
 */
function executeQuery($conn, $query, $params = [], $types = '') {
    try {
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            logDatabaseError("Prepare failed: " . $conn->error, $query, $params);
            return false;
        }
        
        // Bind parameters if any
        if (!empty($params)) {
            if (empty($types)) {
                // Auto-detect types if not provided
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } elseif (is_string($param)) {
                        $types .= 's';
                    } else {
                        $types .= 'b'; // Default to blob
                    }
                }
            }
            
            $stmt->bind_param($types, ...$params);
        }
        
        // Execute the statement
        if (!$stmt->execute()) {
            logDatabaseError("Execute failed: " . $stmt->error, $query, $params);
            $stmt->close();
            return false;
        }
        
        // Get result for SELECT queries
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result !== false ? $result : true;
        
    } catch (Exception $e) {
        logDatabaseError("Query execution error: " . $e->getMessage(), $query, $params);
        return false;
    }
}

/**
 * Begin a transaction with proper error handling
 * 
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function beginTransaction($conn) {
    try {
        // Make sure autocommit is off
        $conn->autocommit(false);
        return $conn->begin_transaction();
    } catch (Exception $e) {
        logDatabaseError("Begin transaction error: " . $e->getMessage());
        return false;
    }
}

/**
 * Commit a transaction with proper error handling
 * 
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function commitTransaction($conn) {
    try {
        $result = $conn->commit();
        $conn->autocommit(true); // Reset to autocommit mode
        return $result;
    } catch (Exception $e) {
        logDatabaseError("Commit transaction error: " . $e->getMessage());
        return false;
    }
}

/**
 * Rollback a transaction with proper error handling
 * 
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function rollbackTransaction($conn) {
    try {
        $result = $conn->rollback();
        $conn->autocommit(true); // Reset to autocommit mode
        return $result;
    } catch (Exception $e) {
        logDatabaseError("Rollback transaction error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check database connection status
 * 
 * @return bool True if connected, false otherwise
 */
function isDatabaseConnected() {
    if (isset($GLOBALS['db_connection']) && $GLOBALS['db_connection'] instanceof mysqli) {
        return $GLOBALS['db_connection']->ping();
    }
    return false;
}

/**
 * Close database connection
 * 
 * @return bool Success status
 */
function closeDbConnection() {
    if (isset($GLOBALS['db_connection']) && $GLOBALS['db_connection'] instanceof mysqli) {
        $result = $GLOBALS['db_connection']->close();
        $GLOBALS['db_connection'] = null;
        return $result;
    }
    return true; // Already closed
}

/**
 * Function to check and create necessary tables
 */
function checkAndCreateTables($conn) {
    // Check if auctions table exists
    $result = $conn->query("SHOW TABLES LIKE 'auctions'");
    if ($result->num_rows == 0) {
        // Create auctions table
        $sql = "CREATE TABLE IF NOT EXISTS auctions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            seller_id INT UNSIGNED NOT NULL,
            start_price DECIMAL(10, 2) NOT NULL,
            reserve_price DECIMAL(10, 2),
            current_price DECIMAL(10, 2),
            min_bid_increment DECIMAL(10, 2) DEFAULT 1.00,
            start_date DATETIME,
            end_date DATETIME,
            status ENUM('pending', 'approved', 'rejected', 'paused', 'ongoing', 'ended') DEFAULT 'pending',
            rejection_reason TEXT,
            image_url VARCHAR(255),
            category_id INT UNSIGNED,
            winner_id INT UNSIGNED DEFAULT NULL,
            winning_bid DECIMAL(10, 2) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (seller_id),
            INDEX (status),
            INDEX (category_id),
            INDEX (start_date),
            INDEX (end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($sql)) {
            logDatabaseError("Error creating auctions table: " . $conn->error);
        }
    }
    
    // Check if bids table exists
    $result = $conn->query("SHOW TABLES LIKE 'bids'");
    if ($result->num_rows == 0) {
        // Create bids table
        $sql = "CREATE TABLE IF NOT EXISTS bids (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            auction_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            bid_amount DECIMAL(10, 2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (auction_id),
            INDEX (user_id),
            INDEX (bid_amount),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($sql)) {
            logDatabaseError("Error creating bids table: " . $conn->error);
        }
    }
    
    // Check if auction_chat_messages table exists
    $result = $conn->query("SHOW TABLES LIKE 'auction_chat_messages'");
    if ($result->num_rows == 0) {
        // Create auction_chat_messages table
        $sql = "CREATE TABLE IF NOT EXISTS auction_chat_messages (
            message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            auction_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            recipient_id INT UNSIGNED NOT NULL,
            message_content TEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'deleted') DEFAULT 'active',
            is_flagged TINYINT(1) DEFAULT 0,
            is_read TINYINT(1) DEFAULT 0,
            INDEX (auction_id),
            INDEX (user_id),
            INDEX (recipient_id),
            INDEX (timestamp),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($sql)) {
            logDatabaseError("Error creating auction_chat_messages table: " . $conn->error);
        }
    }
    
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows == 0) {
        // Create users table
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) DEFAULT '',
            last_name VARCHAR(100) DEFAULT '',
            role ENUM('admin', 'buyer', 'seller') NOT NULL DEFAULT 'buyer',
            status ENUM('pending', 'approved', 'rejected', 'deactivated') DEFAULT 'pending',
            is_verified TINYINT(1) DEFAULT 0,
            profile_image VARCHAR(255) DEFAULT NULL,
            rejection_reason TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (email),
            INDEX (role),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($sql)) {
            logDatabaseError("Error creating users table: " . $conn->error);
        }
    }
    
    // Check if categories table exists
    $result = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($result->num_rows == 0) {
        // Create categories table
        $sql = "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            parent_id INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($sql)) {
            logDatabaseError("Error creating categories table: " . $conn->error);
        }
    }
    
    // Check if auction_images table exists
    $result = $conn->query("SHOW TABLES LIKE 'auction_images'");
    if ($result->num_rows == 0) {
        // Create auction_images table
        $sql = "CREATE TABLE IF NOT EXISTS auction_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            auction_id INT UNSIGNED NOT NULL,
            image_url VARCHAR(255) NOT NULL,
            is_primary TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (auction_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($sql)) {
            logDatabaseError("Error creating auction_images table: " . $conn->error);
        }
    }
}

/**
 * Get a single row from database
 * 
 * @param mysqli $conn Database connection
 * @param string $query SQL query
 * @param array $params Parameters for prepared statement
 * @param string $types Types of parameters
 * @return array|null Row data or null if not found
 */
function getRow($conn, $query, $params = [], $types = '') {
    $result = executeQuery($conn, $query, $params, $types);
    
    if ($result && $result !== true && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get multiple rows from database
 * 
 * @param mysqli $conn Database connection
 * @param string $query SQL query
 * @param array $params Parameters for prepared statement
 * @param string $types Types of parameters
 * @return array Array of rows
 */
function getRows($conn, $query, $params = [], $types = '') {
    $result = executeQuery($conn, $query, $params, $types);
    
    if ($result && $result !== true) {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    return [];
}

/**
 * Insert data into database
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int|bool Last insert ID or false on failure
 */
function insertData($conn, $table, $data) {
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    
    $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $params = array_values($data);
    
    $result = executeQuery($conn, $query, $params);
    
    if ($result) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Update data in database
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @param string $where Where clause
 * @param array $whereParams Parameters for where clause
 * @return int|bool Affected rows or false on failure
 */
function updateData($conn, $table, $data, $where, $whereParams = []) {
    $set = [];
    foreach (array_keys($data) as $column) {
        $set[] = "$column = ?";
    }
    
    $query = "UPDATE $table SET " . implode(', ', $set) . " WHERE $where";
    $params = array_merge(array_values($data), $whereParams);
    
    $result = executeQuery($conn, $query, $params);
    
    if ($result) {
        return $conn->affected_rows;
    }
    
    return false;
}

/**
 * Delete data from database
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $where Where clause
 * @param array $params Parameters for where clause
 * @return int|bool Affected rows or false on failure
 */
function deleteData($conn, $table, $where, $params = []) {
    $query = "DELETE FROM $table WHERE $where";
    
    $result = executeQuery($conn, $query, $params);
    
    if ($result) {
        return $conn->affected_rows;
    }
    
    return false;
}

/**
 * Check if a record exists in database
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $where Where clause
 * @param array $params Parameters for where clause
 * @return bool True if exists, false otherwise
 */
function recordExists($conn, $table, $where, $params = []) {
    $query = "SELECT 1 FROM $table WHERE $where LIMIT 1";
    
    $result = executeQuery($conn, $query, $params);
    
    return $result && $result !== true && $result->num_rows > 0;
}

/**
 * Count records in database
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $where Where clause
 * @param array $params Parameters for where clause
 * @return int Count of records
 */
function countRecords($conn, $table, $where = '1', $params = []) {
    $query = "SELECT COUNT(*) as count FROM $table WHERE $where";
    
    $result = executeQuery($conn, $query, $params);
    
    if ($result && $result !== true && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (int)$row['count'];
    }
    
    return 0;
}

/**
 * Check database structure and fix if needed
 */
function checkDatabaseStructure() {
    $conn = getDbConnection();
    
    if (!$conn) {
        error_log("Failed to get database connection in checkDatabaseStructure");
        return false;
    }
    
    // Check if all required tables exist
    checkAndCreateTables($conn);
    
    // Check if min_bid_increment column exists in auctions table
    $result = $conn->query("SHOW COLUMNS FROM auctions LIKE 'min_bid_increment'");
    if ($result->num_rows === 0) {
        // Add min_bid_increment column if it doesn't exist
        $conn->query("ALTER TABLE auctions ADD COLUMN min_bid_increment DECIMAL(10,2) NOT NULL DEFAULT '1.00' AFTER current_price");
        error_log("Added missing min_bid_increment column to auctions table");
    }
    
    // Check if winner_id column exists in auctions table
    $result = $conn->query("SHOW COLUMNS FROM auctions LIKE 'winner_id'");
    if ($result->num_rows === 0) {
        // Add winner_id column if it doesn't exist
        $conn->query("ALTER TABLE auctions ADD COLUMN winner_id INT UNSIGNED DEFAULT NULL AFTER category_id");
        error_log("Added missing winner_id column to auctions table");
    }
    
    // Check if winning_bid column exists in auctions table
    $result = $conn->query("SHOW COLUMNS FROM auctions LIKE 'winning_bid'");
    if ($result->num_rows === 0) {
        // Add winning_bid column if it doesn't exist
        $conn->query("ALTER TABLE auctions ADD COLUMN winning_bid DECIMAL(10,2) DEFAULT NULL AFTER winner_id");
        error_log("Added missing winning_bid column to auctions table");
    }
    
    // Check if is_read column exists in auction_chat_messages table
    $result = $conn->query("SHOW TABLES LIKE 'auction_chat_messages'");
    if ($result->num_rows > 0) {
        $result = $conn->query("SHOW COLUMNS FROM auction_chat_messages LIKE 'is_read'");
        if ($result->num_rows === 0) {
            // Add is_read column if it doesn't exist
            $conn->query("ALTER TABLE auction_chat_messages ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER is_flagged");
            error_log("Added missing is_read column to auction_chat_messages table");
        }
    }
    
    return true;
}

// Run database structure check on include
checkDatabaseStructure();
