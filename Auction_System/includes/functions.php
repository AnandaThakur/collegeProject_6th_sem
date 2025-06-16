<?php
// Start session if not already started
function startSession() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate password (at least 8 characters, contains letters and numbers)
function validatePassword($password) {
    return strlen($password) >= 8 && 
           preg_match('/[A-Za-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// Check if user is logged in
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Enhance the isAdmin function to be more robust

// Replace the isAdmin function with this improved version:
function isAdmin() {
    startSession();
    
    // Check if role is set and is explicitly 'admin'
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    // Log the admin check for debugging
    if (isset($_SESSION['email'])) {
        error_log("isAdmin check for user: " . $_SESSION['email'] . " - Result: " . ($isAdmin ? "true" : "false"));
    }
    
    return $isAdmin;
}

// Enhance the isVerified function to be more robust
function isVerified() {
    startSession();
    
    // Check if status is set and is 'approved'
    $isVerified = isset($_SESSION['status']) && $_SESSION['status'] === 'approved';
    
    // Log the verification check for debugging
    if (isset($_SESSION['email'])) {
        error_log("isVerified check for user: " . $_SESSION['email'] . " - Result: " . ($isVerified ? "true" : "false"));
    }
    
    return $isVerified;
}

// Redirect to a specific page
function redirect($page) {
    header("Location: $page");
    exit;
}

// Return JSON response
function jsonResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Get user status badge HTML
function getUserStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'approved':
            return '<span class="badge bg-success">Approved</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejected</span>';
        case 'deactivated':
            return '<span class="badge bg-secondary">Deactivated</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

// Debug function to log messages
function debug_log($message, $data = null) {
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
    
    if ($data !== null) {
        $log_message .= ' - ' . (is_array($data) || is_object($data) ? json_encode($data) : $data);
    }
    
    error_log($log_message);
}

// Log admin actions
function logAdminAction($action, $userId = null, $details = null) {
    startSession();
    $adminId = $_SESSION['user_id'] ?? 0;
    $adminEmail = $_SESSION['email'] ?? 'unknown';
    
    $logMessage = "ADMIN ACTION: $action | Admin: $adminEmail (ID: $adminId)";
    
    if ($userId) {
        $logMessage .= " | User ID: $userId";
    }
    
    if ($details) {
        $logMessage .= " | Details: " . (is_array($details) ? json_encode($details) : $details);
    }
    
    debug_log($logMessage);
    
    // In a production environment, you might want to log this to a database table
}

// Check and fix database structure
function checkAndFixDatabaseStructure() {
    $conn = getDbConnection();
    
    // Check if status column exists in users table
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    if ($result->num_rows === 0) {
        // Add status column if it doesn't exist
        $conn->query("ALTER TABLE users ADD COLUMN status ENUM('pending', 'approved', 'rejected', 'deactivated') DEFAULT 'pending' AFTER role");
        error_log("Added missing status column to users table");
    } else {
        // Check if 'deactivated' is in the enum values
        $row = $result->fetch_assoc();
        if (strpos($row['Type'], 'deactivated') === false) {
            // Add 'deactivated' to the enum values
            $conn->query("ALTER TABLE users MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'deactivated') DEFAULT 'pending'");
            error_log("Updated status column to include 'deactivated' value");
        }
    }
    
    // Check if rejection_reason column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'rejection_reason'");
    if ($result->num_rows === 0) {
        // Add rejection_reason column if it doesn't exist
        $conn->query("ALTER TABLE users ADD COLUMN rejection_reason TEXT NULL AFTER is_verified");
        error_log("Added missing rejection_reason column to users table");
    }
    
    // Make sure admin user exists and is properly set up
    $adminEmail = 'admin@auction.com';
    $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("SELECT id, is_verified, status FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", $adminEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Create admin user if it doesn't exist
        $stmt = $conn->prepare("INSERT INTO users (email, password, role, status, is_verified) VALUES (?, ?, 'admin', 'approved', 1)");
        $stmt->bind_param("ss", $adminEmail, $adminPassword);
        $stmt->execute();
        error_log("Created missing admin user");
    } else {
        // Update admin user to ensure it's verified and approved
        $admin = $result->fetch_assoc();
        if ($admin['is_verified'] != 1 || !isset($admin['status']) || $admin['status'] !== 'approved') {
            $stmt = $conn->prepare("UPDATE users SET is_verified = 1, status = 'approved' WHERE email = ? AND role = 'admin'");
            $stmt->bind_param("s", $adminEmail);
            $stmt->execute();
            error_log("Fixed admin user verification status");
        }
        
        // Update admin password to ensure it's correct
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
        $stmt->bind_param("ss", $adminPassword, $adminEmail);
        $stmt->execute();
        error_log("Updated admin password");
    }
    
    return true;
}

// Get user count by status
function getUserCountByStatus($status = null) {
    $conn = getDbConnection();
    
    if ($status) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE status = ? AND role != 'admin'");
        $stmt->bind_param("s", $status);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Get user count by role
function getUserCountByRole($role) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Format date for display
function formatDate($dateString, $format = 'M d, Y H:i') {
    $date = new DateTime($dateString);
    return $date->format($format);
}

// Send email notification (placeholder - implement with actual email service)
function sendEmailNotification($to, $subject, $message) {
    try {
        // In a production environment, you would use a proper email service
        // For now, we'll just log the email
        debug_log("EMAIL NOTIFICATION: To: $to | Subject: $subject | Message: $message");
        
        // Return true to simulate successful sending
        return true;
    } catch (Exception $e) {
        error_log("Error sending email notification: " . $e->getMessage());
        return false;
    }
}

// Add a function to send email notifications to users when their status changes
function sendUserStatusNotification($userEmail, $status, $reason = '') {
    $subject = '';
    $message = '';
    
    switch ($status) {
        case 'approved':
            $subject = 'Your Account Has Been Approved';
            $message = "Dear User,\n\nCongratulations! Your account has been approved. You can now log in and access all features of the Auction Platform.\n\nThank you,\nAuction Platform Team";
            break;
            
        case 'rejected':
            $subject = 'Your Account Registration Status';
            $message = "Dear User,\n\nWe regret to inform you that your account registration has been rejected";
            if (!empty($reason)) {
                $message .= " for the following reason:\n\n$reason";
            }
            $message .= "\n\nIf you believe this is an error, please contact our support team.\n\nThank you,\nAuction Platform Team";
            break;
            
        case 'deactivated':
            $subject = 'Your Account Has Been Deactivated';
            $message = "Dear User,\n\nYour account has been deactivated. If you believe this is an error, please contact our support team.\n\nThank you,\nAuction Platform Team";
            break;
    }
    
    if (!empty($subject) && !empty($message)) {
        return sendEmailNotification($userEmail, $subject, $message);
    }
    
    return false;
}

// Enhance the function to handle user approval
function approveUser($userId) {
    $conn = getDbConnection();
    
    // Get user email for notification
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $userEmail = $result->fetch_assoc()['email'];
    
    // Update user status to approved
    $stmt = $conn->prepare("UPDATE users SET status = 'approved', is_verified = 1 WHERE id = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        // Send notification email
        sendUserStatusNotification($userEmail, 'approved');
        
        // Log the action
        logAdminAction('APPROVE_USER', $userId, "Approved user: $userEmail");
        
        return true;
    }
    
    return false;
}

// Enhance the function to handle user rejection
function rejectUser($userId, $reason = '') {
    $conn = getDbConnection();
    
    // Get user email for notification
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $userEmail = $result->fetch_assoc()['email'];
    
    // Update user status to rejected
    $stmt = $conn->prepare("UPDATE users SET status = 'rejected', rejection_reason = ? WHERE id = ?");
    $stmt->bind_param("si", $reason, $userId);
    
    if ($stmt->execute()) {
        // Send notification email
        sendUserStatusNotification($userEmail, 'rejected', $reason);
        
        // Log the action
        logAdminAction('REJECT_USER', $userId, "Rejected user: $userEmail. Reason: $reason");
        
        return true;
    }
    
    return false;
}

// Get user details by ID
function getUserById($userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT id, email, role, status, is_verified, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

// Get auction status badge HTML
function getAuctionStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'approved':
            return '<span class="badge bg-info">Approved</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejected</span>';
        case 'paused':
            return '<span class="badge bg-secondary">Paused</span>';
        case 'ongoing':
            return '<span class="badge bg-success">Ongoing</span>';
        case 'ended':
            return '<span class="badge bg-dark">Ended</span>';
        default:
            return '<span class="badge bg-secondary">Unknown (' . htmlspecialchars($status) . ')</span>';
    }
}

// Debug function for auction actions
function debugAuctionAction($action, $auctionId, $data = null) {
    $message = "AUCTION ACTION: $action | Auction ID: $auctionId";
    
    if ($data !== null) {
        $message .= " | Data: " . (is_array($data) ? json_encode($data) : $data);
    }
    
    error_log($message);
}

// Check if auction exists
function auctionExists($auctionId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT id FROM auctions WHERE id = ?");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// Get auction by ID
function getAuctionById($auctionId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT * FROM auctions WHERE id = ?");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

// Get auction count by status
function getAuctionCountByStatus($status = null) {
    $conn = getDbConnection();
    
    if ($status) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM auctions WHERE status = ?");
        $stmt->bind_param("s", $status);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM auctions");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Get highest bid for an auction
function getHighestBid($auctionId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT MAX(bid_amount) as highest_bid FROM bids WHERE auction_id = ?");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['highest_bid'] ?? 0;
}

// Get highest bidder for an auction
function getHighestBidder($auctionId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT u.id, u.email, u.first_name, u.last_name 
                           FROM bids b 
                           JOIN users u ON b.user_id = u.id 
                           WHERE b.auction_id = ? 
                           ORDER BY b.bid_amount DESC 
                           LIMIT 1");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

// Get all bids for an auction
function getAuctionBids($auctionId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT b.*, u.email, u.first_name, u.last_name 
                           FROM bids b 
                           JOIN users u ON b.user_id = u.id 
                           WHERE b.auction_id = ? 
                           ORDER BY b.bid_amount DESC");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bids = [];
    while ($row = $result->fetch_assoc()) {
        $bids[] = $row;
    }
    
    return $bids;
}

// Update auction status
function updateAuctionStatus($auctionId, $status) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE auctions SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $auctionId);
    
    return $stmt->execute();
}

// Check if auction is active (can receive bids)
function isAuctionActive($auctionId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT status, start_date, end_date FROM auctions WHERE id = ?");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $auction = $result->fetch_assoc();
    $now = new DateTime();
    
    // Check if auction is ongoing and within start/end dates
    if ($auction['status'] === 'ongoing' || $auction['status'] === 'approved') {
        if (!empty($auction['start_date']) && !empty($auction['end_date'])) {
            $startDate = new DateTime($auction['start_date']);
            $endDate = new DateTime($auction['end_date']);
            
            return $now >= $startDate && $now <= $endDate;
        }
        
        // If dates are not set, only check status
        return true;
    }
    
    return false;
}

// Check and update auction statuses based on dates
function updateAuctionStatusesByDate() {
    $conn = getDbConnection();
    $now = date('Y-m-d H:i:s');
    
    // Update auctions that should be ongoing (approved and past start date)
    $stmt = $conn->prepare("UPDATE auctions 
                           SET status = 'ongoing', updated_at = NOW() 
                           WHERE status = 'approved' 
                           AND start_date <= ? 
                           AND (end_date IS NULL OR end_date > ?)");
    $stmt->bind_param("ss", $now, $now);
    $stmt->execute();
    
    // Update auctions that should be ended (past end date)
    $stmt = $conn->prepare("UPDATE auctions 
                           SET status = 'ended', updated_at = NOW() 
                           WHERE status IN ('approved', 'ongoing') 
                           AND end_date IS NOT NULL 
                           AND end_date <= ?");
    $stmt->bind_param("s", $now);
    $stmt->execute();
    
    return true;
}

// Get all categories
function getAllCategories() {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    return $categories;
}

// Get category by ID
function getCategoryById($categoryId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

// Get child categories
function getChildCategories($parentId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
    $stmt->bind_param("i", $parentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    return $categories;
}

// Get auction images
function getAuctionImages($auctionId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT * FROM auction_images WHERE auction_id = ? ORDER BY is_primary DESC");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    
    return $images;
}

// Get primary auction image
function getPrimaryAuctionImage($auctionId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT * FROM auction_images WHERE auction_id = ? AND is_primary = 1 LIMIT 1");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // If no primary image, get the first image
        $stmt = $conn->prepare("SELECT * FROM auction_images WHERE auction_id = ? LIMIT 1");
        $stmt->bind_param("i", $auctionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
    }
    
    return $result->fetch_assoc();
}
?>
