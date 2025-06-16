<?php
// Set proper headers to prevent caching and ensure JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * Chat Actions API
 * 
 * This file handles all AJAX requests for the chat functionality.
 */

// Display all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session and check authentication
startSession();
if (!isLoggedIn()) {
    sendJsonResponse(false, 'Unauthorized access', null, 401);
    exit;
}

// Get database connection
$conn = getDbConnection();
if (!$conn) {
    sendJsonResponse(false, 'Database connection failed. Please try again later.', null, 500);
    exit;
}

// Log the request for debugging
error_log("Chat API request: " . json_encode($_REQUEST));

// Ensure the chat messages table exists
ensureChatTableExists($conn);

// Get action from request
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// Process based on action
switch ($action) {
    case 'send_message':
        sendMessage($conn);
        break;
        
    case 'get_messages':
        getMessages($conn);
        break;
        
    case 'delete_message':
        deleteMessage($conn);
        break;
        
    case 'flag_message':
        flagMessage($conn);
        break;
        
    case 'mark_as_read':
        markAsRead($conn);
        break;
        
    case 'get_unread_count':
        getUnreadCount($conn);
        break;
        
    default:
        sendJsonResponse(false, 'Invalid action: ' . $action, null, 400);
        break;
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
 * Log detailed information about a message attempt
 */
function logMessageDetails($conn, $auctionId, $userId, $recipientId, $message) {
    error_log("=== MESSAGE ATTEMPT DETAILS ===");
    error_log("User ID: $userId");
    error_log("Recipient ID: $recipientId");
    error_log("Auction ID: $auctionId");
    error_log("Message: $message");
    
    // Log session info
    error_log("Session data: " . json_encode($_SESSION));
    
    // Log auction details
    $auction = getRow($conn, "SELECT * FROM auctions WHERE id = ?", [$auctionId], 'i');
    if ($auction) {
        error_log("Auction details: " . json_encode($auction));
    } else {
        error_log("Auction not found or query failed");
    }
    
    // Log recipient details
    $recipient = getRow($conn, "SELECT id, email, first_name, last_name FROM users WHERE id = ?", [$recipientId], 'i');
    if ($recipient) {
        error_log("Recipient details: " . json_encode($recipient));
    } else {
        error_log("Recipient not found or query failed");
    }
    
    // Log database connection status
    error_log("Database connected: " . (isDatabaseConnected() ? 'Yes' : 'No'));
    
    error_log("=== END MESSAGE DETAILS ===");
}

/**
 * Send a new message
 */
function sendMessage($conn) {
    // Log function call for debugging
    error_log("sendMessage function called with data: " . json_encode($_POST));
    
    // Check required parameters
    if (!isset($_POST['auction_id']) || !isset($_POST['recipient_id']) || !isset($_POST['message'])) {
        error_log("Missing required parameters for sendMessage: " . json_encode($_POST));
        sendJsonResponse(false, 'Missing required parameters');
        return;
    }
    
    $auctionId = intval($_POST['auction_id']);
    $recipientId = intval($_POST['recipient_id']);
    $message = trim($_POST['message']);
    $userId = $_SESSION['user_id'];
    
    // Log detailed information for debugging
    logMessageDetails($conn, $auctionId, $userId, $recipientId, $message);
    
    // Validate message
    if (empty($message)) {
        sendJsonResponse(false, 'Message cannot be empty');
        return;
    }
    
    try {
        // Check if auction exists
        $auction = getRow($conn, "SELECT id, status FROM auctions WHERE id = ?", [$auctionId], 'i');
        
        if (!$auction) {
            throw new Exception("Auction not found: $auctionId");
        }
        
        // Check if recipient exists
        $recipient = getRow($conn, "SELECT id FROM users WHERE id = ?", [$recipientId], 'i');
        
        if (!$recipient) {
            throw new Exception("Recipient not found: $recipientId");
        }
        
        // Insert message
        $messageId = insertData($conn, 'auction_chat_messages', [
            'auction_id' => $auctionId,
            'user_id' => $userId,
            'recipient_id' => $recipientId,
            'message_content' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'active',
            'is_read' => 0
        ]);
        
        if (!$messageId) {
            throw new Exception("Failed to send message");
        }
        
        // Get user details for response
        $user = getRow($conn, "SELECT first_name, last_name FROM users WHERE id = ?", [$userId], 'i');
        
        // Format sender name
        $senderName = $user ? trim($user['first_name'] . ' ' . $user['last_name']) : 'User';
        if (empty($senderName)) {
            $senderName = 'User';
        }
        
        // Create notification for recipient
        createChatNotification($conn, $recipientId, $auctionId, $userId, $message);
        
        error_log("Message sent successfully: ID $messageId");
        
        sendJsonResponse(true, 'Message sent successfully', [
            'message' => [
                'message_id' => $messageId,
                'auction_id' => $auctionId,
                'user_id' => $userId,
                'recipient_id' => $recipientId,
                'message_content' => $message,
                'timestamp' => date('Y-m-d H:i:s'),
                'sender_name' => $senderName,
                'is_sender' => true
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error in sendMessage: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage());
    }
}

/**
 * Create a notification for the recipient when a message is sent
 */
function createChatNotification($conn, $recipientId, $auctionId, $senderId, $message) {
    try {
        // Get auction title
        $auction = getRow($conn, "SELECT title FROM auctions WHERE id = ?", [$auctionId], 'i');
        
        if (!$auction) {
            error_log("Could not find auction for notification: $auctionId");
            return false;
        }
        
        // Get sender name
        $sender = getRow($conn, "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?", [$senderId], 'i');
        
        $senderName = $sender ? $sender['name'] : 'Someone';
        
        // Create notification
        $title = "New Message";
        $notificationMessage = "$senderName sent you a message about auction: " . $auction['title'];
        
        // Check if notifications table exists
        $result = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($result->num_rows > 0) {
            // Insert notification
            insertData($conn, 'notifications', [
                'user_id' => $recipientId,
                'title' => $title,
                'message' => $notificationMessage,
                'type' => 'info',
                'source' => 'chat',
                'related_id' => $auctionId,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            error_log("Notifications table does not exist, skipping notification creation");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating chat notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get messages for a conversation
 */
function getMessages($conn) {
    // Log function call for debugging
    error_log("getMessages function called with data: " . json_encode($_GET));
    
    // Check required parameters
    if (!isset($_GET['auction_id']) || !isset($_GET['recipient_id'])) {
        error_log("Missing required parameters for getMessages: " . json_encode($_GET));
        sendJsonResponse(false, 'Missing required parameters');
        return;
    }
    
    $auctionId = intval($_GET['auction_id']);
    $recipientId = intval($_GET['recipient_id']);
    $userId = $_SESSION['user_id'];
    
    try {
        // Get messages with simplified query
        $messages = getRows($conn, "
            SELECT m.*, 
                u.first_name, u.last_name
            FROM auction_chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.auction_id = ? 
            AND ((m.user_id = ? AND m.recipient_id = ?) 
                OR (m.user_id = ? AND m.recipient_id = ?))
            AND m.status = 'active'
            ORDER BY m.timestamp ASC
        ", [$auctionId, $userId, $recipientId, $recipientId, $userId], 'iiiii');
        
        // Format message data
        $formattedMessages = [];
        foreach ($messages as $message) {
            // Format sender name
            $senderName = trim($message['first_name'] . ' ' . $message['last_name']);
            if (empty($senderName)) {
                $senderName = 'User';
            }
            
            $formattedMessages[] = [
                'message_id' => $message['message_id'],
                'auction_id' => $message['auction_id'],
                'user_id' => $message['user_id'],
                'recipient_id' => $message['recipient_id'],
                'message_content' => $message['message_content'],
                'timestamp' => $message['timestamp'],
                'sender_name' => $senderName,
                'is_sender' => ($message['user_id'] == $userId)
            ];
            
            // Mark messages as read if they are sent to the current user
            if ($message['recipient_id'] == $userId && $message['is_read'] == 0) {
                updateData($conn, 'auction_chat_messages', 
                    ['is_read' => 1], 
                    'message_id = ?', 
                    [$message['message_id']]
                );
            }
        }
        
        error_log("Retrieved " . count($messages) . " messages for auction $auctionId between users $userId and $recipientId");
        
        sendJsonResponse(true, 'Messages retrieved successfully', [
            'messages' => $formattedMessages,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getMessages: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage());
    }
}

/**
 * Delete a message
 */
function deleteMessage($conn) {
    // Check required parameters
    if (!isset($_POST['message_id'])) {
        sendJsonResponse(false, 'Message ID is required');
        return;
    }
    
    $messageId = intval($_POST['message_id']);
    $userId = $_SESSION['user_id'];
    
    try {
        // Check if user is the sender of the message
        $message = getRow($conn, "SELECT user_id FROM auction_chat_messages WHERE message_id = ?", [$messageId], 'i');
        
        if (!$message) {
            throw new Exception("Message not found");
        }
        
        // Only allow sender or admin to delete
        if ($message['user_id'] != $userId && !isAdmin()) {
            throw new Exception("You do not have permission to delete this message");
        }
        
        // Update message status to deleted
        $updated = updateData($conn, 'auction_chat_messages', 
            ['status' => 'deleted'], 
            'message_id = ?', 
            [$messageId]
        );
        
        if (!$updated) {
            throw new Exception("Failed to delete message");
        }
        
        sendJsonResponse(true, 'Message deleted successfully');
        
    } catch (Exception $e) {
        error_log("Error in deleteMessage: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage());
    }
}

/**
 * Flag a message for moderation
 */
function flagMessage($conn) {
    // Check required parameters
    if (!isset($_POST['message_id'])) {
        sendJsonResponse(false, 'Message ID is required');
        return;
    }
    
    $messageId = intval($_POST['message_id']);
    $userId = $_SESSION['user_id'];
    
    try {
        // Check if message exists
        $message = getRow($conn, "SELECT message_id FROM auction_chat_messages WHERE message_id = ?", [$messageId], 'i');
        
        if (!$message) {
            throw new Exception("Message not found");
        }
        
        // Flag the message
        $updated = updateData($conn, 'auction_chat_messages', 
            ['is_flagged' => 1], 
            'message_id = ?', 
            [$messageId]
        );
        
        if (!$updated) {
            throw new Exception("Failed to flag message");
        }
        
        sendJsonResponse(true, 'Message flagged for moderation');
        
    } catch (Exception $e) {
        error_log("Error in flagMessage: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage());
    }
}

/**
 * Mark messages as read
 */
function markAsRead($conn) {
    // Check required parameters
    if (!isset($_POST['auction_id']) || !isset($_POST['sender_id'])) {
        sendJsonResponse(false, 'Auction ID and sender ID are required');
        return;
    }
    
    $auctionId = intval($_POST['auction_id']);
    $senderId = intval($_POST['sender_id']);
    $userId = $_SESSION['user_id'];
    
    try {
        // Mark messages as read
        $updated = updateData($conn, 'auction_chat_messages', 
            ['is_read' => 1], 
            'auction_id = ? AND user_id = ? AND recipient_id = ? AND is_read = 0', 
            [$auctionId, $senderId, $userId]
        );
        
        sendJsonResponse(true, 'Messages marked as read');
        
    } catch (Exception $e) {
        error_log("Error in markAsRead: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage());
    }
}

/**
 * Get unread message count
 */
function getUnreadCount($conn) {
    $userId = $_SESSION['user_id'];
    
    try {
        // Get unread message count
        $count = countRecords($conn, 'auction_chat_messages', 
            'recipient_id = ? AND is_read = 0 AND status = "active"', 
            [$userId]
        );
        
        sendJsonResponse(true, 'Unread count retrieved successfully', [
            'count' => $count
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getUnreadCount: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage());
    }
}

/**
 * Send JSON response
 */
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    // Ensure proper JSON encoding with error handling
    $jsonResponse = json_encode($response);
    if ($jsonResponse === false) {
        error_log("JSON encoding error: " . json_last_error_msg());
        // Try to send a simpler response
        echo json_encode([
            'success' => false,
            'message' => 'Error encoding response: ' . json_last_error_msg()
        ]);
    } else {
        echo $jsonResponse;
    }
    exit;
}
?>
