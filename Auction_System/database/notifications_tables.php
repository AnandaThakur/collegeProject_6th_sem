<?php
require_once '../config/database.php';

// Get database connection
$conn = getDbConnection();

// Check if connection is successful
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Check if users table exists
if (!tableExists($conn, 'users')) {
    die("Error: The 'users' table does not exist. Please run fix-database.php first.");
}

// Get the data type of the id column in the users table
$result = $conn->query("DESCRIBE users id");
$row = $result->fetch_assoc();
$idDataType = $row['Type'];

// Determine if id is unsigned
$isUnsigned = strpos($idDataType, 'unsigned') !== false;
$userIdType = $isUnsigned ? "int(11) UNSIGNED" : "int(11)";

echo "Detected user ID type: $userIdType<br>";

// Create notifications table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Notifications table created successfully or already exists.<br>";
} else {
    echo "Error creating notifications table: " . $conn->error . "<br>";
}

// Create notification_settings table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS notification_settings (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NOT NULL,
    email_notifications TINYINT(1) DEFAULT 1,
    browser_notifications TINYINT(1) DEFAULT 1,
    auction_updates TINYINT(1) DEFAULT 1,
    bid_alerts TINYINT(1) DEFAULT 1,
    system_messages TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Notification settings table created successfully or already exists.<br>";
} else {
    echo "Error creating notification settings table: " . $conn->error . "<br>";
}

// Insert default notification settings for existing users
$sql = "INSERT IGNORE INTO notification_settings (user_id)
        SELECT id FROM users WHERE id NOT IN (SELECT user_id FROM notification_settings)";

if ($conn->query($sql) === TRUE) {
    echo "Default notification settings added for existing users.<br>";
} else {
    echo "Error adding default notification settings: " . $conn->error . "<br>";
}

echo "Database setup complete.";

$conn->close();
?>
