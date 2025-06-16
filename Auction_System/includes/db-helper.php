<?php
/**
 * Database Helper Functions
 * 
 * This file contains helper functions for common database operations.
 */

// Include database configuration
require_once __DIR__ . '/../config/database.php';

/**
 * Get auction details by ID
 * 
 * @param int $auctionId Auction ID
 * @return array|null Auction details or null if not found
 */
function getAuctionDetails($auctionId) {
    $conn = getDbConnection();
    
    if (!$conn) {
        error_log("Database connection failed in getAuctionDetails");
        return null;
    }
    
    return getRow($conn, "
        SELECT a.*, 
            c.name as category_name,
            u.id as seller_id,
            u.first_name as seller_first_name, 
            u.last_name as seller_last_name,
            u.email as seller_email,
            u.profile_image as seller_profile_image
        FROM auctions a 
        LEFT JOIN categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.seller_id = u.id
        WHERE a.id = ?
    ", [$auctionId], 'i');
}

/**
 * Get auction images
 * 
 * @param int $auctionId Auction ID
 * @return array Array of image data
 */
function getAuctionImages($auctionId) {
    $conn = getDbConnection();
    
    if (!$conn) {
        error_log("Database connection failed in getAuctionImages");
        return [];
    }
    
    return getRows($conn, "
        SELECT * FROM auction_images 
        WHERE auction_id = ? 
        ORDER BY is_primary DESC
    ", [$auctionId], 'i');
}

/**
 * Get auction bids
 * 
 * @param int $auctionId Auction ID
 * @return array Array of bid data
 */
function getAuctionBids($auctionId) {
    $conn = getDbConnection();
    
    if (!$conn) {
        error_log("Database connection failed in getAuctionBids");
        return [];
    }
    
    return getRows($conn, "
        SELECT b.*, 
            u.first_name, u.last_name, u.email
        FROM bids b 
        JOIN users u ON b.user_id = u.id 
        WHERE b.auction_id = ? 
        ORDER BY b.bid_amount DESC, b.created_at ASC
    ", [$auctionId], 'i');
}

/**
 * Get user's highest bid on an auction
 * 
 * @param int $auctionId Auction ID
 * @param int $userId User ID
 * @return float|null Highest bid amount or null if no bids
 */
function getUserHighestBid($auctionId, $userId) {
    $conn = getDbConnection();
    
    if (!$conn) {
        error_log("Database connection failed in getUserHighestBid");
        return null;
    }
    
    $result = getRow($conn, "
        SELECT MAX(bid_amount) as highest_bid 
        FROM bids 
        WHERE auction_id = ? AND user_id = ?
    ", [$auctionId, $userId], 'ii');
    
    return $result ? $result['highest_bid'] : null;
}

/**
 * Get auction winner
 * 
 * @param int $auctionId Auction ID
 * @return array|null Winner data or null if no winner
 */
function getAuctionWinner($auctionId) {
    $conn = getDbConnection();
    
    if (!$conn) {
        error_log("Database connection failed in getAuctionWinner");
        return null;
    }
    
    return getRow($conn, "
        SELECT b.*, 
            u.first_name, u.last_name, u.email, u.id as user_id
        FROM bids b 
        JOIN users u ON b.user_id = u.id 
        WHERE b.auction_id = ? 
        ORDER BY b.bid_amount DESC, b.created_at ASC 
        LIMIT 1
    ", [$auctionId], 'i');
}

/**
 * Get chat messages between two users for an auction
 * 
 * @param int $auctionId Auction ID
 * @param int $userId User ID
 * @param int $recipientId Recipient ID
 * @return array Array of message data
 */
function getChatMessages($auctionId, $userId, $recipientId) {
    $conn = getDbConnection();
    
    if (!$conn) {
        error_log("Database connection failed in getChatMessages");
        return [];
    }
    
    // Ensure chat table exists
    ensureChatTableExists($conn);
    
    $messages = getRows($conn, "
        SELECT m.*, 
            u.first_name, u.last_name, u.profile_image
        FROM auction_chat_messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.auction_id = ? 
        AND ((m.user_id = ? AND m.recipient_id = ?) 
            OR (m.user_id = ? AND m.recipient_id = ?))
        AND m.status = 'active'
        ORDER BY m.timestamp ASC
    ", [$auctionId, $userId, $recipientId, $recipientId, $userId], 'iiiii');
    
    // Mark messages as read if they are sent to the current user
    foreach ($messages as $message) {
        if ($message['recipient_id'] == $userId && $message['is_read'] == 0) {
            updateData($conn, 'auction_chat_messages', 
                ['is_read' => 1], 
                'message_id = ?', 
                [$message['message_id']]
            );
        }
    }
    
    return $messages;
}

/**
 * Get similar auctions based on category
 * 
 * @param int $categoryId Category ID
 * @param int $excludeAuctionId Auction ID to exclude
 * @param int $limit Maximum number of auctions to return
 * @return array Array of auction data
 */
function getSimilarAuctions($categoryId, $excludeAuctionId, $limit = 3) {
    $conn = getDbConnection();
    
    if (!$conn) {
        error_log("Database connection failed in getSimilarAuctions");
        return [];
    }
    
    return getRows($conn, "
        SELECT a.id, a.title, a.current_price, a.start_price, a.status,
            (SELECT image_url FROM auction_images WHERE auction_id = a.id LIMIT 1) as image_url
        FROM auctions a 
        WHERE a.category_id = ? 
        AND a.id != ? 
        AND a.status IN ('ongoing', 'approved') 
        LIMIT ?
    ", [$categoryId, $excludeAuctionId, $limit], 'iii');
}

/**
 * Ensure the chat messages table exists
 */
function ensureChatTableExists($conn) {
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS auction_chat_messages (
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
        INDEX (status),
        INDEX (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($createTableSQL)) {
        error_log("Failed to create chat messages table: " . $conn->error);
    }
}

/**
 * Get user unread message count
 * 
 * @param int $userId User ID
 * @return int Number of unread messages
 */
function getUserUnreadMessageCount($userId) {
    $conn = getDbConnection();
    
    if (!$conn) {
        error_log("Database connection failed in getUserUnreadMessageCount");
        return 0;
    }
    
    // Ensure chat table exists
    ensureChatTableExists($conn);
    
    return countRecords($conn, 'auction_chat_messages', 
        'recipient_id = ? AND is_read = 0 AND status = "active"', 
        [$userId]
    );
}

/**
 * Check if auction is active and can receive bids
 * 
 * @param array $auction Auction data
 * @return bool True if auction is active, false otherwise
 */
function isAuctionActive($auction) {
    // Check if auction is in a valid status
    if ($auction['status'] !== 'ongoing' && $auction['status'] !== 'approved') {
        return false;
    }
    
    // Check auction dates
    $now = new DateTime();
    
    if (!empty($auction['start_date'])) {
        $startDate = new DateTime($auction['start_date']);
        if ($now < $startDate) {
            return false;
        }
    }
    
    if (!empty($auction['end_date'])) {
        $endDate = new DateTime($auction['end_date']);
        if ($now > $endDate) {
            return false;
        }
    }
    
    return true;
}

/**
 * Calculate minimum bid for an auction
 * 
 * @param array $auction Auction data
 * @return float Minimum bid amount
 */
function calculateMinimumBid($auction) {
    $conn = getDbConnection();
    
    if (!$conn) {
        error_log("Database connection failed in calculateMinimumBid");
        return $auction['start_price'] + ($auction['min_bid_increment'] ?? 1);
    }
    
    // Get current highest bid
    $highestBid = getRow($conn, "
        SELECT MAX(bid_amount) as highest_bid 
        FROM bids 
        WHERE auction_id = ?
    ", [$auction['id']], 'i');
    
    $currentHighestBid = $highestBid ? $highestBid['highest_bid'] : 0;
    
    // Calculate minimum bid
    $currentPrice = max($currentHighestBid, $auction['start_price']);
    $minIncrement = $auction['min_bid_increment'] ?? 1;
    
    return $currentPrice + $minIncrement;
}
?>
