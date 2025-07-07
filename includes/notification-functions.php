<?php
// Notification helper functions

/**
 * Create a new notification
 * 
 * @param int $userId User ID to send notification to
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type (info, success, warning, error)
 * @return int|bool The notification ID if successful, false otherwise
 */
function createNotification($userId, $title, $message, $type = 'info') {
    $conn = getDbConnection();
    
    // Validate notification type
    $validTypes = ['info', 'success', 'warning', 'error'];
    if (!in_array($type, $validTypes)) {
        $type = 'info';
    }
    
    // Prepare and execute query
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    
    if ($stmt->execute()) {
        $notificationId = $conn->insert_id;
        
        // Check if user wants email notifications
        $settingsStmt = $conn->prepare("SELECT email_notifications, system_messages FROM notification_settings WHERE user_id = ?");
        $settingsStmt->bind_param("i", $userId);
        $settingsStmt->execute();
        $result = $settingsStmt->get_result();
        
        if ($result->num_rows > 0) {
            $settings = $result->fetch_assoc();
            
            // If user has enabled email notifications and system messages
            if ($settings['email_notifications'] && $settings['system_messages']) {
                // Get user email
                $userStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
                $userStmt->bind_param("i", $userId);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                
                if ($userResult->num_rows > 0) {
                    $user = $userResult->fetch_assoc();
                    // Send email notification
                    sendEmailNotification($user['email'], $title, $message);
                }
            }
        }
        
        return $notificationId;
    }
    
    return false;
}

/**
 * Get notifications for a user
 * 
 * @param int $userId User ID
 * @param int $limit Number of notifications to retrieve (0 for all)
 * @param bool $unreadOnly Get only unread notifications
 * @return array Notifications
 */
function getUserNotifications($userId, $limit = 0, $unreadOnly = false) {
    $conn = getDbConnection();
    
    $query = "SELECT * FROM notifications WHERE user_id = ?";
    
    if ($unreadOnly) {
        $query .= " AND is_read = 0";
    }
    
    $query .= " ORDER BY created_at DESC";
    
    if ($limit > 0) {
        $query .= " LIMIT ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($limit > 0) {
        $stmt->bind_param("ii", $userId, $limit);
    } else {
        $stmt->bind_param("i", $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Get unread notification count for a user
 * 
 * @param int $userId User ID
 * @return int Number of unread notifications
 */
function getUnreadNotificationCount($userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

/**
 * Mark a notification as read
 * 
 * @param int $notificationId Notification ID
 * @param int $userId User ID (for security check)
 * @return bool True if successful, false otherwise
 */
function markNotificationAsRead($notificationId, $userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $userId);
    
    return $stmt->execute() && $stmt->affected_rows > 0;
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $userId User ID
 * @return bool True if successful, false otherwise
 */
function markAllNotificationsAsRead($userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    
    return $stmt->execute();
}

/**
 * Delete a notification
 * 
 * @param int $notificationId Notification ID
 * @param int $userId User ID (for security check)
 * @return bool True if successful, false otherwise
 */
function deleteNotification($notificationId, $userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $userId);
    
    return $stmt->execute() && $stmt->affected_rows > 0;
}

/**
 * Get notification type badge HTML
 * 
 * @param string $type Notification type
 * @return string HTML for the badge
 */
function getNotificationTypeBadge($type) {
    switch ($type) {
        case 'success':
            return '<span class="badge bg-success">Success</span>';
        case 'warning':
            return '<span class="badge bg-warning text-dark">Warning</span>';
        case 'error':
            return '<span class="badge bg-danger">Error</span>';
        case 'info':
        default:
            return '<span class="badge bg-info text-dark">Info</span>';
    }
}

/**
 * Get notification icon class based on type
 * 
 * @param string $type Notification type
 * @return string Icon class
 */
function getNotificationIconClass($type) {
    switch ($type) {
        case 'success':
            return 'fas fa-check-circle text-success';
        case 'warning':
            return 'fas fa-exclamation-triangle text-warning';
        case 'error':
            return 'fas fa-times-circle text-danger';
        case 'info':
        default:
            return 'fas fa-info-circle text-info';
    }
}

/**
 * Send notification to all users with a specific role
 * 
 * @param string $role User role (buyer, seller, admin)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type
 * @return int Number of notifications sent
 */
function sendNotificationToRole($role, $title, $message, $type = 'info') {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        if (createNotification($row['id'], $title, $message, $type)) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Send notification for user verification status change
 * 
 * @param int $userId User ID
 * @param string $status New status (approved, rejected)
 * @param string $reason Rejection reason (if applicable)
 * @return bool True if successful, false otherwise
 */
function sendVerificationNotification($userId, $status, $reason = '') {
    $title = '';
    $message = '';
    $type = '';
    
    switch ($status) {
        case 'approved':
            $title = 'Account Verified';
            $message = 'Your account has been verified. You now have full access to the platform.';
            $type = 'success';
            break;
        case 'rejected':
            $title = 'Account Verification Failed';
            $message = 'Your account verification was unsuccessful.';
            if (!empty($reason)) {
                $message .= ' Reason: ' . $reason;
            }
            $type = 'error';
            break;
        default:
            return false;
    }
    
    return createNotification($userId, $title, $message, $type) !== false;
}

/**
 * Send notification for auction status change
 * 
 * @param int $auctionId Auction ID
 * @param string $status New status
 * @param string $reason Reason (if applicable)
 * @return bool True if successful, false otherwise
 */
function sendAuctionStatusNotification($auctionId, $status) {
    $conn = getDbConnection();
    
    // Get auction details
    $stmt = $conn->prepare("SELECT title, seller_id FROM auctions WHERE id = ?");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $auction = $result->fetch_assoc();
    $title = '';
    $message = '';
    $type = '';
    
    switch ($status) {
        case 'approved':
            $title = 'Auction Approved';
            $message = 'Your auction "' . $auction['title'] . '" has been approved and is now live.';
            $type = 'success';
            break;
        case 'rejected':
            $title = 'Auction Rejected';
            $message = 'Your auction "' . $auction['title'] . '" has been rejected.';
            $type = 'error';
            break;
        case 'paused':
            $title = 'Auction Paused';
            $message = 'Your auction "' . $auction['title'] . '" has been temporarily paused.';
            $type = 'warning';
            break;
        case 'resumed':
            $title = 'Auction Resumed';
            $message = 'Your auction "' . $auction['title'] . '" has been resumed and is now active.';
            $type = 'success';
            break;
        case 'ended':
            $title = 'Auction Ended';
            $message = 'Your auction "' . $auction['title'] . '" has ended.';
            $type = 'info';
            break;
        default:
            return false;
    }
    
    return createNotification($auction['seller_id'], $title, $message, $type) !== false;
}

/**
 * Send notification to auction winner
 * 
 * @param int $auctionId Auction ID
 * @param int $winnerId Winner user ID
 * @param float $winningBid Winning bid amount
 * @return bool True if successful, false otherwise
 */
function sendAuctionWinnerNotification($auctionId, $winnerId, $winningBid) {
    $conn = getDbConnection();
    
    // Get auction details
    $stmt = $conn->prepare("SELECT title FROM auctions WHERE id = ?");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $auction = $result->fetch_assoc();
    $title = 'Auction Won!';
    $message = 'Congratulations! You won the auction "' . $auction['title'] . '" with a bid of $' . number_format($winningBid, 2) . '.';
    
    return createNotification($winnerId, $title, $message, 'success') !== false;
}

/**
 * Send notification for new bid
 * 
 * @param int $auctionId Auction ID
 * @param int $bidderId Bidder user ID
 * @param float $bidAmount Bid amount
 * @return bool True if successful, false otherwise
 */
function sendNewBidNotification($auctionId, $bidderId, $bidAmount) {
    $conn = getDbConnection();
    
    // Get auction details
    $stmt = $conn->prepare("SELECT title, seller_id FROM auctions WHERE id = ?");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $auction = $result->fetch_assoc();
    
    // Notify seller
    $sellerTitle = 'New Bid Received';
    $sellerMessage = 'A new bid of $' . number_format($bidAmount, 2) . ' has been placed on your auction "' . $auction['title'] . '".';
    
    // Notify bidder
    $bidderTitle = 'Bid Placed Successfully';
    $bidderMessage = 'Your bid of $' . number_format($bidAmount, 2) . ' for "' . $auction['title'] . '" has been placed successfully.';
    
    $sellerNotified = createNotification($auction['seller_id'], $sellerTitle, $sellerMessage, 'info') !== false;
    $bidderNotified = createNotification($bidderId, $bidderTitle, $bidderMessage, 'success') !== false;
    
    return $sellerNotified && $bidderNotified;
}

/**
 * Send notification for outbid
 * 
 * @param int $auctionId Auction ID
 * @param int $outbidUserId User ID who was outbid
 * @param float $newBidAmount New bid amount
 * @return bool True if successful, false otherwise
 */
function sendOutbidNotification($auctionId, $outbidUserId, $newBidAmount) {
    $conn = getDbConnection();
    
    // Get auction details
    $stmt = $conn->prepare("SELECT title FROM auctions WHERE id = ?");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $auction = $result->fetch_assoc();
    $title = 'You Have Been Outbid';
    $message = 'Someone has placed a higher bid of $' . number_format($newBidAmount, 2) . ' on "' . $auction['title'] . '". You can place a new bid to stay in the auction.';
    
    return createNotification($outbidUserId, $title, $message, 'warning') !== false;
}

/**
 * Update user notification settings
 * 
 * @param int $userId User ID
 * @param array $settings Associative array of settings to update
 * @return bool True if successful, false otherwise
 */
function updateNotificationSettings($userId, $settings) {
    $conn = getDbConnection();
    
    $validSettings = [
        'email_notifications',
        'browser_notifications',
        'auction_updates',
        'bid_alerts',
        'system_messages'
    ];
    
    $updates = [];
    $params = [];
    $types = '';
    
    foreach ($settings as $key => $value) {
        if (in_array($key, $validSettings)) {
            $updates[] = "$key = ?";
            $params[] = (int)!!$value; // Convert to 0 or 1
            $types .= 'i';
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $query = "UPDATE notification_settings SET " . implode(', ', $updates) . " WHERE user_id = ?";
    $params[] = $userId;
    $types .= 'i';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    return $stmt->execute();
}

/**
 * Get user notification settings
 * 
 * @param int $userId User ID
 * @return array|bool Settings array or false if not found
 */
function getUserNotificationSettings($userId) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT * FROM notification_settings WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    return $result->fetch_assoc();
}
?>
