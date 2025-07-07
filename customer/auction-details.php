<?php
// Display all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if user is logged in
startSession();
if (!isLoggedIn()) {
    redirect('../login.php');
}

// Check if user is verified
if (!isVerified()) {
    redirect('../waiting.php');
}

// Get auction ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('auctions.php');
}

$auctionId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];
$conn = getDbConnection();

if (!$conn) {
    die("Database connection failed. Please check your database configuration.");
}

// Get auction details with seller information
$stmt = $conn->prepare("SELECT a.*, 
                      c.name as category_name,
                      u.id as seller_id,
                      u.first_name as seller_first_name, 
                      u.last_name as seller_last_name,
                      u.email as seller_email,
                      u.profile_image as seller_profile_image
                      FROM auctions a 
                      LEFT JOIN categories c ON a.category_id = c.id
                      LEFT JOIN users u ON a.seller_id = u.id
                      WHERE a.id = ?");
$stmt->bind_param("i", $auctionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('auctions.php');
}

$auction = $result->fetch_assoc();

// Determine auction status more precisely
$now = new DateTime();
$startDate = null;
$endDate = null;

if (!empty($auction['start_date'])) {
    $startDate = new DateTime($auction['start_date']);
}
if (!empty($auction['end_date'])) {
    $endDate = new DateTime($auction['end_date']);
}

// Debug date information
error_log("Auction ID: {$auctionId} | Status check");
error_log("Current time: " . $now->format('Y-m-d H:i:s'));
error_log("Start date: " . ($startDate ? $startDate->format('Y-m-d H:i:s') : 'Not set'));
error_log("End date: " . ($endDate ? $endDate->format('Y-m-d H:i:s') : 'Not set'));
error_log("Auction status from DB: " . $auction['status']);

$auctionStatus = '';
$statusClass = '';
$canBid = false;

// First check if auction is in a terminal state
if ($auction['status'] === 'ended') {
    $auctionStatus = 'Ended';
    $statusClass = 'danger';
    $canBid = false;
} elseif ($auction['status'] === 'paused') {
    $auctionStatus = 'Paused';
    $statusClass = 'warning';
    $canBid = false;
} elseif ($auction['status'] === 'pending' || $auction['status'] === 'rejected') {
    $auctionStatus = ucfirst($auction['status']);
    $statusClass = $auction['status'] === 'pending' ? 'warning' : 'danger';
    $canBid = false;
} else {
    // Auction is either 'approved' or 'ongoing'
    // Check time constraints
    $nowTimestamp = $now->getTimestamp();
    
    if ($startDate && $endDate) {
        $startTimestamp = $startDate->getTimestamp();
        $endTimestamp = $endDate->getTimestamp();
        
        error_log("Timestamps - Now: $nowTimestamp | Start: $startTimestamp | End: $endTimestamp");
        
        if ($nowTimestamp < $startTimestamp) {
            // Auction hasn't started yet
            $auctionStatus = 'Not Started';
            $statusClass = 'info';
            $canBid = false;
            error_log("Auction not started yet");
        } elseif ($nowTimestamp > $endTimestamp) {
            // Auction has ended
            $auctionStatus = 'Ended';
            $statusClass = 'danger';
            $canBid = false;
            
            // Update auction status if it hasn't been marked as ended yet
            if ($auction['status'] !== 'ended') {
                $updateStmt = $conn->prepare("UPDATE auctions SET status = 'ended', updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $auctionId);
                $updateStmt->execute();
                error_log("Updated auction status to 'ended'");
            }
        } else {
            // Auction is active
            $auctionStatus = 'Active';
            $statusClass = 'success';
            $canBid = true;
            
            // Calculate time remaining
            $interval = $now->diff($endDate);
            $hoursRemaining = ($interval->days * 24) + $interval->h;
            
            if ($hoursRemaining <= 1) {
                $auctionStatus = 'Ending Soon';
                $statusClass = 'warning';
            }
            
            error_log("Auction is active, bidding enabled");
        }
    } else {
        // If dates are not set properly, default to allowing bidding for approved/ongoing auctions
        $auctionStatus = 'Active';
        $statusClass = 'success';
        $canBid = true;
        error_log("Auction dates not properly set, defaulting to active");
    }
}

// Final check - if user is the seller, they can't bid regardless of status
$isUserSeller = ($auction['seller_id'] == $userId);
if ($isUserSeller) {
    $canBid = false;
    error_log("User is the seller, bidding disabled");
}

error_log("Final auction status: $auctionStatus | Can bid: " . ($canBid ? 'Yes' : 'No'));

// Get auction images
$images = getAuctionImages($auctionId);

// Get auction bids
$bids = getAuctionBids($auctionId);

// Get user's highest bid on this auction
$stmt = $conn->prepare("SELECT MAX(bid_amount) as highest_bid 
                      FROM bids 
                      WHERE auction_id = ? AND user_id = ?");
$stmt->bind_param("ii", $auctionId, $userId);
$stmt->execute();
$userBidResult = $stmt->get_result();
$userHighestBid = $userBidResult->fetch_assoc()['highest_bid'] ?? 0;

// Calculate minimum bid (current price + minimum increment or 1 if not set)
$minIncrement = isset($auction['min_bid_increment']) && !empty($auction['min_bid_increment']) ? $auction['min_bid_increment'] : 1;
$minimumBid = ($auction['current_price'] ?? $auction['start_price']) + $minIncrement;

// Get auction winner if ended
$winner = null;
if ($auction['status'] === 'ended') {
    $stmt = $conn->prepare("SELECT b.*, u.first_name, u.last_name, u.email 
                          FROM bids b 
                          JOIN users u ON b.user_id = u.id 
                          WHERE b.auction_id = ? 
                          ORDER BY b.bid_amount DESC, b.created_at ASC 
                          LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $auctionId);
        $stmt->execute();
        $winnerResult = $stmt->get_result();
        if ($winnerResult && $winnerResult->num_rows > 0) {
            $winner = $winnerResult->fetch_assoc();
        }
    }
}

// Calculate time remaining
function getTimeRemaining($endDate) {
    $now = new DateTime();
    $end = new DateTime($endDate);
    $interval = $now->diff($end);
    
    if ($interval->invert) {
        return '<span class="text-danger">Auction has ended</span>';
    }
    
    if ($interval->days > 0) {
        return $interval->format('%d days, %h hours, %i minutes');
    } else if ($interval->h > 0) {
        return $interval->format('%h hours, %i minutes');
    } else {
        return $interval->format('%i minutes, %s seconds');
    }
}

// Get chat messages between current user and seller
function getChatMessages($conn, $auctionId, $userId, $sellerId) {
    // Check if the auction_chat_messages table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'auction_chat_messages'");
    if ($tableCheckResult->num_rows == 0) {
        // Table doesn't exist, create it
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
            INDEX (auction_id),
            INDEX (user_id),
            INDEX (recipient_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $conn->query($createTableSQL);
    }

    $query = "SELECT m.*, 
              u.first_name, u.last_name, u.profile_image
              FROM auction_chat_messages m
              JOIN users u ON m.user_id = u.id
              WHERE m.auction_id = ? 
              AND ((m.user_id = ? AND m.recipient_id = ?) 
                   OR (m.user_id = ? AND m.recipient_id = ?))
              AND m.status = 'active'
              ORDER BY m.timestamp ASC";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("SQL Error in getChatMessages: " . $conn->error);
        return []; // Return empty array if prepare fails
    }
    
    $stmt->bind_param("iiiii", $auctionId, $userId, $sellerId, $sellerId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    return $messages;
}

$chatMessages = [];
if (!$isUserSeller) {
    try {
        $chatMessages = getChatMessages($conn, $auctionId, $userId, $auction['seller_id']);
    } catch (Exception $e) {
        // Log the error but continue
        error_log("Error getting chat messages: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($auction['title']); ?> - Auction Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css" />
    <style>
        :root {
            --primary-color: #ff6b6b;
            --primary-hover: #ff5252;
            --secondary-color: #f8f9fa;
            --text-color: #333;
            --border-color: #dee2e6;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        body {
            padding-bottom: 70px;
            background-color: #f8f9fa;
            color: var(--text-color);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .auction-header {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .auction-title {
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .auction-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .auction-meta-item {
            display: flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .auction-meta-item i {
            margin-right: 5px;
        }
        
        .auction-image-gallery {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .swiper {
            width: 100%;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .swiper-slide {
            text-align: center;
            background: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .swiper-slide img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .swiper-pagination {
            position: static;
            margin-top: 10px;
        }
        
        .auction-thumbs {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .auction-thumb {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.2s;
        }
        
        .auction-thumb.active {
            border-color: var(--primary-color);
        }
        
        .auction-details {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .auction-description {
            margin-bottom: 20px;
            white-space: pre-line;
        }
        
        .auction-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .auction-info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .auction-info-item:last-child {
            border-bottom: none;
        }
        
        .auction-info-label {
            color: #6c757d;
        }
        
        .auction-info-value {
            font-weight: 600;
        }
        
        .bid-section {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .current-price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .bid-count {
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .time-remaining {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .time-remaining-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .time-remaining-value {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .bid-form {
            margin-bottom: 20px;
        }
        
        .bid-input-group {
            margin-bottom: 15px;
        }
        
        .bid-input-group .input-group-text {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .bid-input-group .form-control {
            border: 1px solid #ced4da;
            border-left: none;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .bid-now-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
            font-weight: 500;
            transition: background-color 0.3s;
            width: 100%;
        }
        
        .bid-now-btn:hover {
            background-color: var(--primary-hover);
        }
        
        .bid-now-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        .bid-history {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .bid-history-title {
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .bid-history-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .bid-history-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .bid-history-item:last-child {
            border-bottom: none;
        }
        
        .bid-user {
            font-weight: 600;
        }
        
        .bid-amount {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .bid-time {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .bottom-nav {
            background-color: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #6c757d;
            padding: 0.5rem 0;
        }
        
        .nav-link.active {
            color: var(--primary-color);
        }
        
        .nav-icon {
            font-size: 1.5rem;
            margin-bottom: 0.2rem;
        }
        
        .seller-info {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .seller-title {
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .seller-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .seller-contact {
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .your-bid-info {
            background-color: #e9f7ef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .your-bid-label {
            font-size: 0.9rem;
            color: #2ecc71;
            margin-bottom: 5px;
        }
        
        .your-bid-value {
            font-weight: 700;
            color: #2ecc71;
            font-size: 1.2rem;
        }
        
        .auction-ended-notice {
            background-color: #f8d7da;
            color: #721c24;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .auction-ended-notice i {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .auction-ended-notice h4 {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .auction-ended-notice p {
            margin-bottom: 0;
        }
        
        .seller-notice {
            background-color: #cce5ff;
            color: #004085;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .seller-notice i {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .seller-notice h4 {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .seller-notice p {
            margin-bottom: 0;
        }
        
        .winner-notice {
            background-color: #d4edda;
            color: #155724;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .winner-notice i {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .winner-notice h4 {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .winner-notice p {
            margin-bottom: 0;
        }
        
        /* Chat styles */
        .chat-container {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 400px;
        }
        
        .chat-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .chat-header .seller-info {
            display: flex;
            align-items: center;
            background: none;
            box-shadow: none;
            padding: 0;
            margin: 0;
        }
        
        .chat-header .seller-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .message {
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 15px;
            position: relative;
            word-wrap: break-word;
        }
        
        .message-time {
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 5px;
            text-align: right;
        }
        
        .message-sender {
            align-self: flex-end;
            background-color: var(--primary-color);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message-receiver {
            align-self: flex-start;
            background-color: #f1f0f0;
            color: var(--text-color);
            border-bottom-left-radius: 5px;
        }
        
        .chat-input {
            padding: 15px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
        }
        
        .chat-input input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            outline: none;
        }
        
        .chat-input button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .chat-input button:hover {
            background-color: var(--primary-hover);
        }
        
        .chat-input button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-badge.ended {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.ending-soon {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-badge.not-started {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-badge.paused {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-badge.rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 767.98px) {
            .swiper {
                height: 250px;
            }
            
            .auction-meta {
                flex-direction: column;
                gap: 5px;
            }
            
            .chat-container {
                height: 350px;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="buyer-section.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <div>
                <a href="auctions.php" class="btn btn-outline-primary">
                    <i class="fas fa-gavel"></i> All Auctions
                </a>
            </div>
        </div>
        
        <!-- Auction Header -->
        <div class="auction-header">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h1 class="auction-title"><?php echo htmlspecialchars($auction['title']); ?></h1>
                <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $auctionStatus)); ?>">
                    <?php echo $auctionStatus; ?>
                </span>
            </div>
            <div class="auction-meta">
                <div class="auction-meta-item">
                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($auction['category_name'] ?? 'Uncategorized'); ?>
                </div>
                <div class="auction-meta-item">
                    <i class="fas fa-calendar-alt"></i> Started: <?php echo formatDate($auction['start_date']); ?>
                </div>
                <div class="auction-meta-item">
                    <i class="fas fa-clock"></i> Ends: <?php echo formatDate($auction['end_date']); ?>
                </div>
                <div class="auction-meta-item">
                    <i class="fas fa-user"></i> Seller: <?php echo htmlspecialchars($auction['seller_first_name'] . ' ' . $auction['seller_last_name']); ?>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Image Gallery -->
                <div class="auction-image-gallery">
                    <?php if (count($images) > 0): ?>
                        <div class="swiper">
                            <div class="swiper-wrapper">
                                <?php foreach ($images as $image): ?>
                                    <div class="swiper-slide">
                                        <img src="<?php echo htmlspecialchars('../' . $image['image_url']); ?>" alt="<?php echo htmlspecialchars($auction['title']); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-pagination"></div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                        </div>
                        
                        <?php if (count($images) > 1): ?>
                            <div class="auction-thumbs">
                                <?php foreach ($images as $index => $image): ?>
                                    <img src="<?php echo htmlspecialchars('../' . $image['image_url']); ?>" 
                                         alt="Thumbnail" 
                                         class="auction-thumb <?php echo $index === 0 ? 'active' : ''; ?>"
                                         data-index="<?php echo $index; ?>">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <img src="../assets/img/placeholder.jpg" alt="No Image Available" style="max-width: 100%; max-height: 300px;">
                            <p class="text-muted mt-3">No images available for this auction</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Auction Details -->
                <div class="auction-details">
                    <h3>Description</h3>
                    <div class="auction-description">
                        <?php echo nl2br(htmlspecialchars($auction['description'])); ?>
                    </div>
                    
                    <h3>Details</h3>
                    <ul class="auction-info-list">
                        <li class="auction-info-item">
                            <span class="auction-info-label">Category</span>
                            <span class="auction-info-value"><?php echo htmlspecialchars($auction['category_name'] ?? 'Uncategorized'); ?></span>
                        </li>
                        <li class="auction-info-item">
                            <span class="auction-info-label">Condition</span>
                            <span class="auction-info-value"><?php echo htmlspecialchars($auction['condition'] ?? 'Not specified'); ?></span>
                        </li>
                        <li class="auction-info-item">
                            <span class="auction-info-label">Location</span>
                            <span class="auction-info-value"><?php echo htmlspecialchars($auction['location'] ?? 'Not specified'); ?></span>
                        </li>
                        <li class="auction-info-item">
                            <span class="auction-info-label">Starting Price</span>
                            <span class="auction-info-value">$<?php echo number_format($auction['start_price'], 2); ?></span>
                        </li>
                        <li class="auction-info-item">
                            <span class="auction-info-label">Current Price</span>
                            <span class="auction-info-value">$<?php echo number_format($auction['current_price'] ?? $auction['start_price'], 2); ?></span>
                        </li>
                        <li class="auction-info-item">
                            <span class="auction-info-label">Minimum Bid Increment</span>
                            <span class="auction-info-value">$<?php echo number_format($minIncrement, 2); ?></span>
                        </li>
                        <li class="auction-info-item">
                            <span class="auction-info-label">Auction Started</span>
                            <span class="auction-info-value"><?php echo formatDate($auction['start_date']); ?></span>
                        </li>
                        <li class="auction-info-item">
                            <span class="auction-info-label">Auction Ends</span>
                            <span class="auction-info-value"><?php echo formatDate($auction['end_date']); ?></span>
                        </li>
                    </ul>
                </div>
                
                <!-- Bid History -->
                <div class="bid-history">
                    <h3 class="bid-history-title">Bid History (<?php echo count($bids); ?>)</h3>
                    
                    <?php if (count($bids) > 0): ?>
                        <ul class="bid-history-list">
                            <?php foreach ($bids as $bid): ?>
                                <li class="bid-history-item">
                                    <div>
                                        <div class="bid-user">
                                            <?php echo htmlspecialchars($bid['first_name'] . ' ' . $bid['last_name']); ?>
                                            <?php if ($bid['user_id'] == $userId): ?>
                                                <span class="badge bg-primary">You</span>
                                            <?php endif; ?>
                                            <?php if ($winner && $bid['user_id'] == $winner['user_id']): ?>
                                                <span class="badge bg-success">Winner</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="bid-time"><?php echo formatDate($bid['created_at']); ?></div>
                                    </div>
                                    <div class="bid-amount">$<?php echo number_format($bid['bid_amount'], 2); ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <p class="text-muted">No bids have been placed yet. Be the first to bid!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Chat Section (Only for non-sellers) -->
                <?php if (!$isUserSeller): ?>
                <div class="card">
                    <div class="chat-container">
                        <div class="chat-header">
                            <div class="seller-info">
                                <img src="<?php echo !empty($auction['seller_profile_image']) ? '../' . htmlspecialchars($auction['seller_profile_image']) : '../assets/img/default-avatar.png'; ?>" 
                                     alt="Seller" class="seller-avatar">
                                <span><?php echo htmlspecialchars($auction['seller_first_name'] . ' ' . $auction['seller_last_name']); ?></span>
                            </div>
                            <span class="badge bg-light text-dark"><?php echo $auctionStatus; ?></span>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <?php if (count($chatMessages) > 0): ?>
                                <?php foreach ($chatMessages as $message): ?>
                                    <div class="message <?php echo $message['user_id'] == $userId ? 'message-sender' : 'message-receiver'; ?>">
                                        <?php echo htmlspecialchars($message['message_content']); ?>
                                        <div class="message-time"><?php echo formatDate($message['timestamp'], 'M d, g:i a'); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted my-auto">
                                    <p>No messages yet. Start the conversation with the seller!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="chat-input">
                            <input type="text" id="messageInput" placeholder="Type your message..." <?php echo $canBid ? '' : 'disabled'; ?>>
                            <button id="sendMessageBtn" <?php echo $canBid ? '' : 'disabled'; ?>>
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <!-- Bid Section -->
                <div class="bid-section">
                    <div class="current-price">$<?php echo number_format($auction['current_price'] ?? $auction['start_price'], 2); ?></div>
                    <div class="bid-count"><?php echo count($bids); ?> bid<?php echo count($bids) != 1 ? 's' : ''; ?> so far</div>
                    
                    <div class="time-remaining">
                        <div class="time-remaining-label">Time Remaining</div>
                        <div class="time-remaining-value" id="timeRemaining">
                            <?php echo getTimeRemaining($auction['end_date']); ?>
                        </div>
                    </div>
                    
                    <?php if ($isUserSeller): ?>
                        <!-- Seller Notice -->
                        <div class="seller-notice">
                            <i class="fas fa-info-circle"></i>
                            <h4>You are the seller</h4>
                            <p>You cannot bid on your own auction.</p>
                        </div>
                    <?php elseif ($auction['status'] === 'ended'): ?>
                        <!-- Auction Ended Notice -->
                        <div class="auction-ended-notice">
                            <i class="fas fa-exclamation-circle"></i>
                            <h4>Auction has ended</h4>
                            <p>This auction is no longer active.</p>
                        </div>
                        
                        <?php if ($winner): ?>
                            <!-- Winner Notice -->
                            <div class="winner-notice">
                                <i class="fas fa-trophy"></i>
                                <h4>Winning Bid</h4>
                                <p>
                                    <?php echo htmlspecialchars($winner['first_name'] . ' ' . $winner['last_name']); ?>
                                    <?php if ($winner['user_id'] == $userId): ?>
                                        <span class="badge bg-success">You won!</span>
                                    <?php endif; ?>
                                </p>
                                <p class="fw-bold">$<?php echo number_format($winner['bid_amount'], 2); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <p class="text-muted">No bids were placed on this auction.</p>
                            </div>
                        <?php endif; ?>
                    <?php elseif (!$canBid): ?>
                        <!-- Cannot Bid Notice -->
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h5 class="alert-heading">Bidding Unavailable</h5>
                            <p>
                                <?php if ($auctionStatus === 'Not Started'): ?>
                                    This auction has not started yet. Bidding will be available once the auction begins on <?php echo formatDate($auction['start_date']); ?>.
                                <?php elseif ($auctionStatus === 'Paused'): ?>
                                    This auction is currently paused. Bidding will resume when the auction is active again.
                                <?php else: ?>
                                    Bidding is not available for this auction at this time.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php if ($userHighestBid > 0): ?>
                            <!-- User's Highest Bid -->
                            <div class="your-bid-info">
                                <div class="your-bid-label">Your Highest Bid</div>
                                <div class="your-bid-value">$<?php echo number_format($userHighestBid, 2); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Bid Form -->
                        <form class="bid-form" id="bidForm" action="../api/bids.php" method="POST">
                            <input type="hidden" name="action" value="place_bid">
                            <input type="hidden" name="auction_id" value="<?php echo $auctionId; ?>">
                            
                            <div class="bid-input-group input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="bid_amount" id="bidAmount" 
                                       placeholder="Enter your bid" 
                                       min="<?php echo $minimumBid; ?>" 
                                       step="0.01" 
                                       value="<?php echo $minimumBid; ?>" 
                                       required>
                            </div>
                            
                            <div class="alert alert-info small">
                                <i class="fas fa-info-circle"></i> Minimum bid: $<?php echo number_format($minimumBid, 2); ?>
                            </div>
                            
                            <button type="submit" class="btn bid-now-btn">
                                <i class="fas fa-gavel"></i> Place Bid
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <!-- Seller Info -->
                <div class="seller-info">
                    <h3 class="seller-title">Seller Information</h3>
                    <div class="d-flex align-items-center mb-3">
                        <img src="<?php echo !empty($auction['seller_profile_image']) ? '../' . htmlspecialchars($auction['seller_profile_image']) : '../assets/img/default-avatar.png'; ?>" 
                             alt="Seller" class="me-2" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <div>
                            <div class="seller-name"><?php echo htmlspecialchars($auction['seller_first_name'] . ' ' . $auction['seller_last_name']); ?></div>
                            <div class="seller-contact"><?php echo htmlspecialchars($auction['seller_email']); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!$isUserSeller): ?>
                        <button class="btn btn-primary w-100" id="openChatBtn">
                            <i class="fas fa-comments"></i> Chat with Seller
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Similar Auctions -->
                <?php
                // Get similar auctions based on category
                $stmt = $conn->prepare("SELECT a.id, a.title, a.current_price, a.start_price, a.status,
                                      (SELECT image_url FROM auction_images WHERE auction_id = a.id LIMIT 1) as image_url
                                      FROM auctions a 
                                      WHERE a.category_id = ? 
                                      AND a.id != ? 
                                      AND a.status IN ('ongoing', 'approved') 
                                      LIMIT 3");
                $stmt->bind_param("ii", $auction['category_id'], $auctionId);
                $stmt->execute();
                $similarAuctions = $stmt->get_result();
                
                if ($similarAuctions->num_rows > 0):
                ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Similar Auctions</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php while ($similar = $similarAuctions->fetch_assoc()): ?>
                            <a href="auction-details.php?id=<?php echo $similar['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <img src="<?php echo !empty($similar['image_url']) ? htmlspecialchars('../' . $similar['image_url']) : '../assets/img/placeholder.jpg'; ?>" 
                                             alt="<?php echo htmlspecialchars($similar['title']); ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;">
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($similar['title']); ?></h6>
                                        <p class="mb-1 text-primary fw-bold">$<?php echo number_format($similar['current_price'] ?? $similar['start_price'], 2); ?></p>
                                    </div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bottom Navigation -->
    <nav class="fixed-bottom bottom-nav">
        <div class="row text-center">
            <div class="col-3">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home nav-icon"></i>
                    <span class="nav-text small">Home</span>
                </a>
            </div>
            <div class="col-3">
                <a href="auctions.php" class="nav-link active">
                    <i class="fas fa-gavel nav-icon"></i>
                    <span class="nav-text small">Auctions</span>
                </a>
            </div>
            <div class="col-3">
                <a href="my-bids.php" class="nav-link">
                    <i class="fas fa-bookmark nav-icon"></i>
                    <span class="nav-text small">My Bids</span>
                </a>
            </div>
            <div class="col-3">
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user nav-icon"></i>
                    <span class="nav-text small">Profile</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Toast for notifications -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle">Notification</strong>
                <small id="toastTime">now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>
    <script>
        // Initialize Swiper
        const swiper = new Swiper('.swiper', {
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
        });
        
        // Thumbnail click handling
        const thumbnails = document.querySelectorAll('.auction-thumb');
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                swiper.slideTo(index);
                
                // Update active class
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Update thumbnails when slide changes
        swiper.on('slideChange', function() {
            thumbnails.forEach(t => t.classList.remove('active'));
            const activeThumb = document.querySelector(`.auction-thumb[data-index="${swiper.activeIndex}"]`);
            if (activeThumb) {
                activeThumb.classList.add('active');
            }
        });
        
        // Toast notification function
        function showToast(message, title = 'Notification', type = 'success') {
            const toastEl = document.getElementById('liveToast');
            const toastTitle = document.getElementById('toastTitle');
            const toastMessage = document.getElementById('toastMessage');
            const toastTime = document.getElementById('toastTime');
            
            // Set content
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            toastTime.textContent = new Date().toLocaleTimeString();
            
            // Set toast color based on type
            toastEl.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-white');
            if (type === 'success') {
                toastEl.classList.add('bg-success', 'text-white');
            } else if (type === 'error') {
                toastEl.classList.add('bg-danger', 'text-white');
            } else if (type === 'warning') {
                toastEl.classList.add('bg-warning');
            }
            
            // Show toast
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }
        
        // Handle bid form submission
        const bidForm = document.getElementById('bidForm');
        if (bidForm) {
            bidForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Disable the submit button to prevent double submissions
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                
                const formData = new FormData(this);
                
                // Log the form data for debugging
                console.log('Submitting bid with data:', Object.fromEntries(formData));
                
                // Show a loading message
                showToast('Processing your bid...', 'Please wait', 'info');
                
                fetch('../api/bids.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin' // Include cookies
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    // Check if the response is valid JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        // If not JSON, get the text and log it
                        return response.text().then(text => {
                            console.error('Non-JSON response:', text);
                            throw new Error('Invalid response format');
                        });
                    }
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        showToast('Bid placed successfully!', 'Success', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        showToast(data.message || 'An error occurred while placing your bid.', 'Error', 'error');
                        if (submitBtn) submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while placing your bid. Please try again.', 'Error', 'error');
                    if (submitBtn) submitBtn.disabled = false;
                });
            });
        }
        
        // Function to send a message
        function sendMessage() {
            if (!messageInput || !messageInput.value.trim()) {
                console.log('Message input is empty, not sending');
                return;
            }

            // Disable the send button to prevent double submissions
            if (sendMessageBtn) sendMessageBtn.disabled = true;

            const message = messageInput.value.trim();
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('auction_id', '<?php echo $auctionId; ?>');
            formData.append('recipient_id', '<?php echo $auction['seller_id']; ?>');
            formData.append('message', message);

            // Log the form data for debugging
            console.log('Sending message with data:', Object.fromEntries(formData));

            // Show a loading message
            showToast('Sending message...', 'Please wait', 'info');

            fetch('../api/chat-actions.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // Include cookies
            })
            .then(response => {
                console.log('Response status:', response.status);
                // Check if the response is valid JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, get the text and log it
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Invalid response format');
                    });
                }
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Add message to chat
                    addMessageToChat(message, true);
                    messageInput.value = '';
                    showToast('Message sent successfully', 'Success', 'success');
                } else {
                    showToast(data.message || 'Failed to send message', 'Error', 'error');
                }
                // Re-enable the send button
                if (sendMessageBtn) sendMessageBtn.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while sending your message. Please try again.', 'Error', 'error');
                // Re-enable the send button
                if (sendMessageBtn) sendMessageBtn.disabled = false;
            });
        }

        // Function to add a message to the chat
        function addMessageToChat(message, isSender = false) {
            // Clear "no messages" text if it exists
            const noMessagesText = chatMessages.querySelector('.text-muted');
            if (noMessagesText) {
                chatMessages.innerHTML = '';
            }

            const messageElement = document.createElement('div');
            messageElement.className = `message ${isSender ? 'message-sender' : 'message-receiver'}`;

            const messageContent = document.createTextNode(message);
            messageElement.appendChild(messageContent);

            const timeElement = document.createElement('div');
            timeElement.className = 'message-time';
            timeElement.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            messageElement.appendChild(timeElement);

            chatMessages.appendChild(messageElement);

            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Event listeners for chat
        if (sendMessageBtn) {
            sendMessageBtn.addEventListener('click', function() {
                console.log('Send button clicked');
                sendMessage();
            });
        }

        if (messageInput) {
            messageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    console.log('Enter key pressed in message input');
                    e.preventDefault();
                    sendMessage();
                }
            });
        }

        if (openChatBtn) {
            openChatBtn.addEventListener('click', function() {
                console.log('Open chat button clicked');
                // Scroll to chat section
                const chatContainer = document.querySelector('.chat-container');
                if (chatContainer) {
                    chatContainer.scrollIntoView({ behavior: 'smooth' });
                    if (messageInput) messageInput.focus();
                }
            });
        }

        // Function to fetch new messages
        function fetchMessages() {
            if (!chatMessages) {
                console.log('Chat messages container not found, skipping fetch');
                return;
            }

            const url = `../api/chat-actions.php?action=get_messages&auction_id=<?php echo $auctionId; ?>&recipient_id=<?php echo $auction['seller_id']; ?>&timestamp=${new Date().getTime()}`;
            console.log('Fetching messages from:', url);

            fetch(url, {
                credentials: 'same-origin' // Include cookies
            })
            .then(response => {
                console.log('Fetch messages response status:', response.status);
                // Check if the response is valid JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, get the text and log it
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        return { success: false, message: 'Invalid response format' };
                    });
                }
            })
            .then(data => {
                console.log('Fetch messages response data:', data);
                
                // Check if we have valid data structure
                if (data.success && data.data && data.data.messages) {
                    const messages = data.data.messages;
                    
                    if (messages.length > 0) {
                        // Clear chat if it's the first load
                        if (chatMessages.querySelector('.text-muted')) {
                            chatMessages.innerHTML = '';
                        }

                        // Add new messages
                        let hasNewMessages = false;
                        messages.forEach(message => {
                            // Check if message already exists
                            const messageId = `message-${message.message_id}`;
                            if (!document.getElementById(messageId)) {
                                const messageElement = document.createElement('div');
                                messageElement.id = messageId;
                                messageElement.className = `message ${message.user_id == <?php echo $userId; ?> ? 'message-sender' : 'message-receiver'}`;

                                const messageContent = document.createTextNode(message.message_content);
                                messageElement.appendChild(messageContent);

                                const timeElement = document.createElement('div');
                                timeElement.className = 'message-time';
                                timeElement.textContent = new Date(message.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                                messageElement.appendChild(timeElement);

                                chatMessages.appendChild(messageElement);
                                hasNewMessages = true;
                            }
                        });

                        // Scroll to bottom if new messages
                        if (hasNewMessages) {
                            chatMessages.scrollTop = chatMessages.scrollHeight;
                        }
                    }
                } else {
                    console.log('No new messages or invalid response format');
                }
            })
            .catch(error => {
                console.error('Error fetching messages:', error);
            });
        }

        // Fetch messages initially and then every 5 seconds
        if (chatMessages) {
            console.log('Setting up message fetching');
            fetchMessages();
            const messageInterval = setInterval(fetchMessages, 5000);
            
            // Clean up interval when page is unloaded
            window.addEventListener('beforeunload', function() {
                clearInterval(messageInterval);
            });

            // Scroll to bottom initially
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    </script>
</body>
</html>
