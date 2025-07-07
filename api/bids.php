<?php
// Set proper headers to prevent caching and ensure JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * Bid API
 * 
 * This file handles all AJAX requests for bidding functionality.
 */

// Include required files
require_once '../config/database.php';
require_once '../includes/functions.php';

// Display all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
error_log("Bids API request: " . json_encode($_REQUEST));

// Get action from request
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// Process based on action
switch ($action) {
    case 'place_bid':
        placeBid($conn);
        break;
        
    case 'get_ongoing_auctions':
        if (!isAdmin()) {
            sendJsonResponse(false, 'Unauthorized access', null, 401);
            exit;
        }
        getOngoingAuctions($conn);
        break;
        
    case 'get_recent_bids':
        if (!isAdmin()) {
            sendJsonResponse(false, 'Unauthorized access', null, 401);
            exit;
        }
        getRecentBids($conn);
        break;
        
    case 'get_auction_bids':
        if (!isAdmin()) {
            sendJsonResponse(false, 'Unauthorized access', null, 401);
            exit;
        }
        getAuctionBids($conn);
        break;
        
    case 'get_auction_winner':
        if (!isAdmin()) {
            sendJsonResponse(false, 'Unauthorized access', null, 401);
            exit;
        }
        getAuctionWinner($conn);
        break;
        
    case 'update_min_increment':
        if (!isAdmin()) {
            sendJsonResponse(false, 'Unauthorized access', null, 401);
            exit;
        }
        updateMinIncrement($conn);
        break;
        
    case 'close_auction':
        if (!isAdmin()) {
            sendJsonResponse(false, 'Unauthorized access', null, 401);
            exit;
        }
        closeAuction($conn);
        break;
        
    case 'get_bid_history':
        if (!isAdmin()) {
            sendJsonResponse(false, 'Unauthorized access', null, 401);
            exit;
        }
        getBidHistory($conn);
        break;
        
    default:
        sendJsonResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * Place a bid on an auction
 */
function placeBid($conn) {
    // Log the start of the function for debugging
    error_log("placeBid function called with data: " . json_encode($_POST));
    
    // Check required parameters
    if (!isset($_POST['auction_id']) || !isset($_POST['bid_amount'])) {
        error_log("Missing required parameters for placeBid");
        sendJsonResponse(false, 'Auction ID and bid amount are required');
        return;
    }
    
    $auctionId = intval($_POST['auction_id']);
    $bidAmount = floatval($_POST['bid_amount']);
    $userId = $_SESSION['user_id'];
    
    // Log detailed information for debugging
    logBidDetails($conn, $auctionId, $userId, $bidAmount);
    
    // Validate bid amount
    if ($bidAmount <= 0) {
        error_log("Invalid bid amount: $bidAmount");
        sendJsonResponse(false, 'Bid amount must be greater than zero');
        return;
    }
    
    try {
        // First check if the auction exists
        $auction = getRow($conn, 
            "SELECT * FROM auctions WHERE id = ?", 
            [$auctionId], 
            'i'
        );
        
        if (!$auction) {
            throw new Exception("Auction not found: $auctionId");
        }
        
        // Debug auction data
        error_log("Auction data: " . json_encode($auction));
        
        // Check if auction is active
        if ($auction['status'] !== 'ongoing' && $auction['status'] !== 'approved') {
            throw new Exception("This auction is not active (status: {$auction['status']})");
        }
        
        // Check if user is the seller
        if ($auction['seller_id'] == $userId) {
            throw new Exception("You cannot bid on your own auction");
        }
        
        // Check auction dates
        $now = new DateTime();
        
        if (!empty($auction['start_date'])) {
            $startDate = new DateTime($auction['start_date']);
            if ($now < $startDate) {
                throw new Exception("This auction has not started yet");
            }
        }
        
        if (!empty($auction['end_date'])) {
            $endDate = new DateTime($auction['end_date']);
            if ($now > $endDate) {
                throw new Exception("This auction has already ended");
            }
        }
        
        // Get current highest bid
        $highestBid = getRow($conn, 
            "SELECT MAX(bid_amount) as highest_bid FROM bids WHERE auction_id = ?", 
            [$auctionId], 
            'i'
        );
        
        $currentHighestBid = $highestBid ? $highestBid['highest_bid'] : 0;
        
        // Calculate minimum bid
        $currentPrice = max($currentHighestBid, $auction['start_price']);
        $minIncrement = $auction['min_bid_increment'] ?? 1;
        $minimumBid = $currentPrice + $minIncrement;
        
        error_log("Bid validation - Current price: $currentPrice | Min increment: $minIncrement | Minimum bid: $minimumBid | User bid: $bidAmount");
        
        // Check if bid is high enough
        if ($bidAmount < $minimumBid) {
            throw new Exception("Your bid must be at least $" . number_format($minimumBid, 2));
        }
        
        // Start transaction
        if (!beginTransaction($conn)) {
            throw new Exception("Failed to start transaction");
        }
        
        // Insert bid
        $bidInserted = insertData($conn, 'bids', [
            'auction_id' => $auctionId,
            'user_id' => $userId,
            'bid_amount' => $bidAmount,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$bidInserted) {
            throw new Exception("Failed to place bid");
        }
        
        // Update auction current price
        $updated = updateData($conn, 'auctions', 
            ['current_price' => $bidAmount], 
            'id = ?', 
            [$auctionId]
        );
        
        if (!$updated) {
            throw new Exception("Failed to update auction price");
        }
        
        // Commit transaction
        if (!commitTransaction($conn)) {
            throw new Exception("Failed to commit transaction");
        }
        
        error_log("Bid placed successfully - User: $userId | Auction: $auctionId | Amount: $bidAmount");
        
        // Create notification for seller
        createBidNotification($conn, $auction['seller_id'], $auctionId, $bidAmount, $userId);
        
        sendJsonResponse(true, 'Bid placed successfully', [
            'bid' => [
                'auction_id' => $auctionId,
                'user_id' => $userId,
                'bid_amount' => $bidAmount,
                'formatted_amount' => '$' . number_format($bidAmount, 2),
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error if it was started
        if ($conn && $conn->inTransaction()) {
            rollbackTransaction($conn);
        }
        
        error_log("Error in placeBid: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage());
    }
}

/**
 * Create a notification for the seller when a bid is placed
 */
function createBidNotification($conn, $sellerId, $auctionId, $bidAmount, $bidderId) {
    try {
        // Get auction title
        $auction = getRow($conn, 
            "SELECT title FROM auctions WHERE id = ?", 
            [$auctionId], 
            'i'
        );
        
        if (!$auction) {
            error_log("Could not find auction for notification: $auctionId");
            return false;
        }
        
        // Get bidder name
        $bidder = getRow($conn, 
            "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?", 
            [$bidderId], 
            'i'
        );
        
        $bidderName = $bidder ? $bidder['name'] : 'Someone';
        
        // Create notification
        $title = "New Bid on Your Auction";
        $message = "$bidderName placed a bid of $" . number_format($bidAmount, 2) . " on your auction: " . $auction['title'];
        
        // Check if notifications table exists
        $result = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($result->num_rows > 0) {
            // Insert notification
            insertData($conn, 'notifications', [
                'user_id' => $sellerId,
                'title' => $title,
                'message' => $message,
                'type' => 'info',
                'source' => 'bid',
                'related_id' => $auctionId,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            error_log("Notifications table does not exist, skipping notification creation");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating bid notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Log detailed information about a bid attempt
 */
function logBidDetails($conn, $auctionId, $userId, $bidAmount) {
    error_log("=== BID ATTEMPT DETAILS ===");
    error_log("User ID: $userId");
    error_log("Auction ID: $auctionId");
    error_log("Bid Amount: $bidAmount");
    
    // Log session info
    error_log("Session data: " . json_encode($_SESSION));
    
    // Log auction details
    $auction = getRow($conn, "SELECT * FROM auctions WHERE id = ?", [$auctionId], 'i');
    if ($auction) {
        error_log("Auction details: " . json_encode($auction));
    } else {
        error_log("Auction not found or query failed");
    }
    
    // Log highest bid
    $highestBid = getRow($conn, "SELECT MAX(bid_amount) as highest_bid FROM bids WHERE auction_id = ?", [$auctionId], 'i');
    if ($highestBid) {
        error_log("Highest bid: " . json_encode($highestBid));
    } else {
        error_log("No bids found or query failed");
    }
    
    // Log database connection status
    error_log("Database connected: " . (isDatabaseConnected() ? 'Yes' : 'No'));
    
    error_log("=== END BID DETAILS ===");
}

/**
 * Get ongoing auctions with bid information
 */
function getOngoingAuctions($conn) {
    try {
        $auctions = getRows($conn, "
            SELECT a.*, 
                u.email as seller_email,
                u.first_name as seller_first_name, 
                u.last_name as seller_last_name,
                c.name as category_name,
                (SELECT COUNT(*) FROM bids b WHERE b.auction_id = a.id) as bid_count,
                (SELECT MAX(b2.bid_amount) FROM bids b2 WHERE b2.auction_id = a.id) as highest_bid
            FROM auctions a
            LEFT JOIN users u ON a.seller_id = u.id
            LEFT JOIN categories c ON a.category_id = c.id
            WHERE a.status IN ('ongoing', 'approved')
            ORDER BY a.end_date ASC
        ");
        
        // Get highest bidder information separately to avoid complex subqueries
        foreach ($auctions as &$auction) {
            $highestBid = getRow($conn, "
                SELECT b.*, u.email, u.first_name, u.last_name, u.id as user_id
                FROM bids b
                JOIN users u ON b.user_id = u.id
                WHERE b.auction_id = ?
                ORDER BY b.bid_amount DESC, b.created_at ASC
                LIMIT 1
            ", [$auction['id']], 'i');
            
            if ($highestBid) {
                $auction['highest_bidder_email'] = $highestBid['email'];
                $auction['highest_bidder_name'] = trim($highestBid['first_name'] . ' ' . $highestBid['last_name']);
                $auction['highest_bidder_id'] = $highestBid['user_id'];
            }
            
            // Format data for display
            $auction['image_url'] = !empty($auction['image_url']) ? '../' . $auction['image_url'] : '../assets/img/placeholder.jpg';
            $auction['formatted_bid'] = '$' . number_format($auction['highest_bid'] ?? $auction['start_price'], 2);
            $auction['min_bid_increment'] = floatval($auction['min_bid_increment'] ?? 1.00);
            
            // Format time left
            if (!empty($auction['end_date'])) {
                $endTime = new DateTime($auction['end_date']);
                $now = new DateTime();
                
                if ($endTime < $now) {
                    $auction['time_left'] = 'Ended';
                } else {
                    $interval = $now->diff($endTime);
                    $days = $interval->format('%a');
                    $hours = $interval->format('%h');
                    $minutes = $interval->format('%i');
                    $seconds = $interval->format('%s');
                    
                    if ($days > 0) {
                        $auction['time_left'] = $days . 'd ' . $hours . 'h ' . $minutes . 'm';
                    } else if ($hours > 0) {
                        $auction['time_left'] = $hours . 'h ' . $minutes . 'm';
                    } else {
                        $auction['time_left'] = $minutes . 'm ' . $seconds . 's';
                    }
                }
            } else {
                $auction['time_left'] = 'No end date';
            }
        }
        
        sendJsonResponse(true, 'Auctions retrieved successfully', [
            'auctions' => $auctions,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getOngoingAuctions: " . $e->getMessage());
        sendJsonResponse(false, 'Failed to retrieve auctions: ' . $e->getMessage());
    }
}

/**
 * Get recent bids across all auctions
 */
function getRecentBids($conn) {
    try {
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        
        $bids = getRows($conn, "
            SELECT b.*, 
                a.title as auction_title, 
                u.email as bidder_email,
                u.first_name as bidder_first_name,
                u.last_name as bidder_last_name
            FROM bids b
            JOIN auctions a ON b.auction_id = a.id
            JOIN users u ON b.user_id = u.id
            ORDER BY b.created_at DESC
            LIMIT ?
        ", [$limit], 'i');
        
        // Format bid data
        foreach ($bids as &$bid) {
            // Format bidder name
            $bid['bidder_name'] = trim($bid['bidder_first_name'] . ' ' . $bid['bidder_last_name']);
            if (empty($bid['bidder_name'])) {
                $bid['bidder_name'] = $bid['bidder_email'];
            }
            
            // Format amount and time
            $bid['formatted_amount'] = '$' . number_format($bid['bid_amount'], 2);
            $bid['formatted_time'] = date('M d, Y H:i:s', strtotime($bid['created_at']));
        }
        
        sendJsonResponse(true, 'Recent bids retrieved successfully', [
            'bids' => $bids
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getRecentBids: " . $e->getMessage());
        sendJsonResponse(false, 'Failed to retrieve recent bids: ' . $e->getMessage());
    }
}

/**
 * Get bid history for a specific auction
 */
function getAuctionBids($conn) {
    try {
        if (!isset($_POST['auction_id'])) {
            sendJsonResponse(false, 'Auction ID is required');
            return;
        }
        
        $auctionId = intval($_POST['auction_id']);
        
        // Get auction details
        $auction = getRow($conn, "SELECT title FROM auctions WHERE id = ?", [$auctionId], 'i');
        
        if (!$auction) {
            sendJsonResponse(false, 'Auction not found');
            return;
        }
        
        // Get highest bid amount
        $highestBidRow = getRow($conn, "SELECT MAX(bid_amount) as highest_bid FROM bids WHERE auction_id = ?", [$auctionId], 'i');
        $highestBid = $highestBidRow ? $highestBidRow['highest_bid'] : 0;
        
        // Get bids for this auction
        $bids = getRows($conn, "
            SELECT b.*, 
                u.email as bidder_email,
                u.first_name as bidder_first_name,
                u.last_name as bidder_last_name
            FROM bids b
            JOIN users u ON b.user_id = u.id
            WHERE b.auction_id = ?
            ORDER BY b.bid_amount DESC, b.created_at ASC
        ", [$auctionId], 'i');
        
        // Format bid data
        foreach ($bids as &$bid) {
            // Format bidder name
            $bid['bidder_name'] = trim($bid['bidder_first_name'] . ' ' . $bid['bidder_last_name']);
            if (empty($bid['bidder_name'])) {
                $bid['bidder_name'] = $bid['bidder_email'];
            }
            
            // Format amount and time
            $bid['formatted_amount'] = '$' . number_format($bid['bid_amount'], 2);
            $bid['formatted_time'] = date('M d, Y H:i:s', strtotime($bid['created_at']));
            
            // Mark highest bid
            $bid['is_highest'] = ($bid['bid_amount'] == $highestBid);
        }
        
        sendJsonResponse(true, 'Bid history retrieved successfully', [
            'bids' => $bids,
            'total_bids' => count($bids),
            'highest_bid' => number_format($highestBid, 2)
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getAuctionBids: " . $e->getMessage());
        sendJsonResponse(false, 'Failed to retrieve bid history: ' . $e->getMessage());
    }
}

/**
 * Get winner information for an auction
 */
function getAuctionWinner($conn) {
    try {
        if (!isset($_POST['auction_id'])) {
            sendJsonResponse(false, 'Auction ID is required');
            return;
        }
        
        $auctionId = intval($_POST['auction_id']);
        
        // Get auction details
        $auction = getRow($conn, "SELECT title FROM auctions WHERE id = ?", [$auctionId], 'i');
        
        if (!$auction) {
            sendJsonResponse(false, 'Auction not found');
            return;
        }
        
        // Get highest bidder
        $winner = getRow($conn, "
            SELECT b.*, 
                u.id as user_id,
                u.email,
                u.first_name,
                u.last_name
            FROM bids b
            JOIN users u ON b.user_id = u.id
            WHERE b.auction_id = ?
            ORDER BY b.bid_amount DESC, b.created_at ASC
            LIMIT 1
        ", [$auctionId], 'i');
        
        if (!$winner) {
            // No bids yet
            sendJsonResponse(true, 'No bids yet', [
                'has_winner' => false
            ]);
            return;
        }
        
        // Format winner data
        $winnerName = trim($winner['first_name'] . ' ' . $winner['last_name']);
        if (empty($winnerName)) {
            $winnerName = $winner['email'];
        }
        
        sendJsonResponse(true, 'Winner information retrieved successfully', [
            'has_winner' => true,
            'winner' => [
                'user_id' => $winner['user_id'],
                'name' => $winnerName,
                'email' => $winner['email'],
                'bid_amount' => $winner['bid_amount'],
                'formatted_amount' => '$' . number_format($winner['bid_amount'], 2)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getAuctionWinner: " . $e->getMessage());
        sendJsonResponse(false, 'Failed to retrieve winner information: ' . $e->getMessage());
    }
}

/**
 * Update minimum bid increment for an auction
 */
function updateMinIncrement($conn) {
    try {
        if (!isset($_POST['auction_id']) || !isset($_POST['min_increment'])) {
            sendJsonResponse(false, 'Auction ID and minimum increment are required');
            return;
        }
        
        $auctionId = intval($_POST['auction_id']);
        $minIncrement = floatval($_POST['min_increment']);
        
        // Validate minimum increment
        if ($minIncrement < 0.01) {
            sendJsonResponse(false, 'Minimum increment must be at least $0.01');
            return;
        }
        
        // Update auction
        $updated = updateData($conn, 'auctions', 
            ['min_bid_increment' => $minIncrement], 
            'id = ?', 
            [$auctionId]
        );
        
        if (!$updated) {
            sendJsonResponse(false, 'Auction not found or no changes made');
            return;
        }
        
        sendJsonResponse(true, 'Minimum bid increment updated successfully');
        
        // Log the action
        debug_log("Admin {$_SESSION['email']} updated minimum bid increment for auction ID {$auctionId} to ${$minIncrement}");
        
    } catch (Exception $e) {
        error_log("Error in updateMinIncrement: " . $e->getMessage());
        sendJsonResponse(false, 'Failed to update minimum bid increment: ' . $e->getMessage());
    }
}

/**
 * Close an auction and assign a winner
 */
function closeAuction($conn) {
    try {
        if (!isset($_POST['auction_id'])) {
            sendJsonResponse(false, 'Auction ID is required');
            return;
        }
        
        $auctionId = intval($_POST['auction_id']);
        $winnerId = isset($_POST['winner_id']) ? intval($_POST['winner_id']) : null;
        $winningBid = isset($_POST['winning_bid']) ? floatval($_POST['winning_bid']) : null;
        $notifyParticipants = isset($_POST['notify_participants']) && $_POST['notify_participants'] == 1;
        
        // Start transaction
        if (!beginTransaction($conn)) {
            throw new Exception("Failed to start transaction");
        }
        
        // Update auction status
        $updated = updateData($conn, 'auctions', 
            [
                'status' => 'ended',
                'winner_id' => $winnerId,
                'winning_bid' => $winningBid,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$auctionId]
        );
        
        if (!$updated) {
            throw new Exception('Auction not found or no changes made');
        }
        
        // If notification is enabled, send emails
        if ($notifyParticipants) {
            // Get auction details
            $auction = getRow($conn, "
                SELECT a.title, a.description, a.end_date, 
                    u.email as seller_email, 
                    u.first_name as seller_first_name, 
                    u.last_name as seller_last_name
                FROM auctions a
                JOIN users u ON a.seller_id = u.id
                WHERE a.id = ?
            ", [$auctionId], 'i');
            
            if (!$auction) {
                throw new Exception('Auction details not found');
            }
            
            // Get all bidders
            $bidders = getRows($conn, "
                SELECT DISTINCT u.email, u.first_name, u.last_name
                FROM bids b
                JOIN users u ON b.user_id = u.id
                WHERE b.auction_id = ?
            ", [$auctionId], 'i');
            
            // Get winner details if exists
            $winnerName = '';
            $winnerEmail = '';
            
            if ($winnerId) {
                $winner = getRow($conn, "
                    SELECT email, first_name, last_name 
                    FROM users 
                    WHERE id = ?
                ", [$winnerId], 'i');
                
                if ($winner) {
                    $winnerName = trim($winner['first_name'] . ' ' . $winner['last_name']);
                    $winnerEmail = $winner['email'];
                }
            }
            
            // Send notifications (this would be implemented in a real system)
            // For now, just log the action
            debug_log("Auction closed notification would be sent to seller: {$auction['seller_email']}");
            
            if ($winnerId) {
                debug_log("Winner notification would be sent to: {$winnerEmail}");
            }
            
            foreach ($bidders as $bidder) {
                debug_log("Bidder notification would be sent to: {$bidder['email']}");
            }
        }
        
        // Commit transaction
        if (!commitTransaction($conn)) {
            throw new Exception("Failed to commit transaction");
        }
        
        sendJsonResponse(true, 'Auction closed successfully');
        
        // Log the action
        debug_log("Admin {$_SESSION['email']} closed auction ID {$auctionId}" . 
            ($winnerId ? " with winner ID {$winnerId} and winning bid ${$winningBid}" : " with no winner"));
            
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn && $conn->inTransaction()) {
            rollbackTransaction($conn);
        }
        
        error_log("Error in closeAuction: " . $e->getMessage());
        sendJsonResponse(false, 'Failed to close auction: ' . $e->getMessage());
    }
}

/**
 * Get bid history for an auction (alternative endpoint)
 */
function getBidHistory($conn) {
    try {
        if (!isset($_GET['auction_id'])) {
            sendJsonResponse(false, 'Auction ID is required');
            return;
        }
        
        $auctionId = intval($_GET['auction_id']);
        
        // Get auction details
        $auction = getRow($conn, "SELECT title FROM auctions WHERE id = ?", [$auctionId], 'i');
        
        if (!$auction) {
            sendJsonResponse(false, 'Auction not found');
            return;
        }
        
        // Get highest bid amount
        $highestBidRow = getRow($conn, "SELECT MAX(bid_amount) as highest_bid FROM bids WHERE auction_id = ?", [$auctionId], 'i');
        $highestBid = $highestBidRow ? $highestBidRow['highest_bid'] : 0;
        
        // Get bids for this auction
        $bids = getRows($conn, "
            SELECT b.*, 
                u.email as bidder_email,
                u.first_name as bidder_first_name,
                u.last_name as bidder_last_name
            FROM bids b
            JOIN users u ON b.user_id = u.id
            WHERE b.auction_id = ?
            ORDER BY b.bid_amount DESC, b.created_at ASC
        ", [$auctionId], 'i');
        
        // Format bid data
        $formattedBids = [];
        foreach ($bids as $bid) {
            // Format bidder name
            $bidderName = trim($bid['bidder_first_name'] . ' ' . $bid['bidder_last_name']);
            if (empty($bidderName)) {
                $bidderName = $bid['bidder_email'];
            }
            
            $formattedBids[] = [
                'bidder_name' => $bidderName,
                'amount' => $bid['bid_amount'],
                'amount_formatted' => '$' . number_format($bid['bid_amount'], 2),
                'date' => $bid['created_at'],
                'date_formatted' => date('M d, Y H:i:s', strtotime($bid['created_at'])),
                'is_highest' => ($bid['bid_amount'] == $highestBid)
            ];
        }
        
        sendJsonResponse(true, 'Bid history retrieved successfully', [
            'auction' => $auction,
            'bids' => $formattedBids,
            'total_bids' => count($bids)
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getBidHistory: " . $e->getMessage());
        sendJsonResponse(false, 'Failed to retrieve bid history: ' . $e->getMessage());
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
