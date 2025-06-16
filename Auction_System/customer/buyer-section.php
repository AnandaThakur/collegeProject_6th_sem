<?php
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

// Get user information
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];
$conn = getDbConnection();

// Get user's name from database
$stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$firstName = "User";
$lastName = "";

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $firstName = $user['first_name'] ?? "User";
    $lastName = $user['last_name'] ?? "";
}

// Get categories for filter
$categories = getAllCategories();

// Get active auctions with more details
$stmt = $conn->prepare("SELECT a.*, 
                      c.name as category_name,
                      (SELECT image_url FROM auction_images WHERE auction_id = a.id LIMIT 1) as image_url,
                      (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count,
                      (SELECT MAX(bid_amount) FROM bids WHERE auction_id = a.id) as highest_bid,
                      u.first_name as seller_first_name, 
                      u.last_name as seller_last_name
                      FROM auctions a 
                      LEFT JOIN categories c ON a.category_id = c.id
                      LEFT JOIN users u ON a.seller_id = u.id
                      WHERE a.status = 'ongoing' 
                      ORDER BY a.end_date ASC 
                      LIMIT 12");
$stmt->execute();
$auctions = $stmt->get_result();

// Get user's recent bids
$stmt = $conn->prepare("SELECT b.*, a.title as auction_title, a.end_date,
                      (SELECT image_url FROM auction_images WHERE auction_id = a.id LIMIT 1) as image_url
                      FROM bids b
                      JOIN auctions a ON b.auction_id = a.id
                      WHERE b.user_id = ?
                      ORDER BY b.created_at DESC
                      LIMIT 5");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentBids = $stmt->get_result();

// Get featured auctions (those with most bids)
$stmt = $conn->prepare("SELECT a.*, 
                      c.name as category_name,
                      (SELECT image_url FROM auction_images WHERE auction_id = a.id LIMIT 1) as image_url,
                      (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count
                      FROM auctions a 
                      LEFT JOIN categories c ON a.category_id = c.id
                      WHERE a.status = 'ongoing'
                      ORDER BY bid_count DESC, a.end_date ASC
                      LIMIT 4");
$stmt->execute();
$featuredAuctions = $stmt->get_result();

// Get ending soon auctions
$stmt = $conn->prepare("SELECT a.*, 
                      (SELECT image_url FROM auction_images WHERE auction_id = a.id LIMIT 1) as image_url,
                      (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count
                      FROM auctions a 
                      WHERE a.status = 'ongoing' AND a.end_date > NOW()
                      ORDER BY a.end_date ASC
                      LIMIT 4");
$stmt->execute();
$endingSoonAuctions = $stmt->get_result();

// Calculate time remaining for auctions
function getTimeRemaining($endDate) {
    $now = new DateTime();
    $end = new DateTime($endDate);
    $interval = $now->diff($end);
    
    if ($interval->invert) {
        return '<span class="text-danger">Ended</span>';
    }
    
    if ($interval->days > 0) {
        return $interval->format('%d days, %h hrs');
    } else if ($interval->h > 0) {
        return $interval->format('%h hrs, %i mins');
    } else {
        return $interval->format('%i mins, %s secs');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Dashboard - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff6b6b;
            --primary-hover: #ff5252;
            --secondary-color: #f8f9fa;
        }
        
        body {
            padding-bottom: 70px;
            background-color: #f8f9fa;
        }
        
        .auction-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            background-color: white;
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        
        .auction-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .auction-image {
            height: 180px;
            object-fit: cover;
            width: 100%;
        }
        
        .auction-info {
            padding: 15px;
        }
        
        .auction-title {
            font-weight: 600;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            height: 48px;
        }
        
        .auction-price {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .auction-meta {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
            font-size: 0.85rem;
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
        
        .category-filter {
            overflow-x: auto;
            white-space: nowrap;
            padding: 10px 0;
            margin-bottom: 15px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .category-filter::-webkit-scrollbar {
            display: none;
        }
        
        .category-item {
            display: inline-block;
            padding: 8px 15px;
            margin-right: 10px;
            background-color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #495057;
            border: 1px solid #dee2e6;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .category-item:hover {
            background-color: #f1f1f1;
        }
        
        .category-item.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .search-bar {
            margin-bottom: 20px;
            position: relative;
        }
        
        .search-bar .form-control {
            border-radius: 20px;
            padding-left: 40px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
        }
        
        .section-title {
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .view-all {
            font-size: 0.9rem;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .view-all:hover {
            text-decoration: underline;
        }
        
        .badge-corner {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
        
        .time-remaining {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background-color: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .bid-now-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 8px 20px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .bid-now-btn:hover {
            background-color: var(--primary-hover);
        }
        
        .recent-bid-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .recent-bid-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 10px;
            margin-right: 15px;
        }
        
        .recent-bid-info {
            flex: 1;
        }
        
        .recent-bid-title {
            font-weight: 600;
            margin-bottom: 0;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .recent-bid-amount {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .featured-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1;
        }
        
        .ending-soon-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: #ffc107;
            color: #212529;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-color) 0%, #ff8e8e 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .welcome-title {
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .welcome-subtitle {
            opacity: 0.9;
            margin-bottom: 15px;
        }
        
        .welcome-btn {
            background-color: white;
            color: var(--primary-color);
            border: none;
            border-radius: 20px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .welcome-btn:hover {
            background-color: rgba(255,255,255,0.9);
            transform: translateY(-2px);
        }
        
        /* Bid Modal Styles */
        .bid-modal .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .bid-modal .modal-header {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .bid-modal .modal-title {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .bid-modal .modal-footer {
            border-top: none;
            padding-top: 0;
        }
        
        .bid-input-group {
            margin-bottom: 20px;
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
        
        .bid-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .bid-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .bid-info-label {
            color: #6c757d;
        }
        
        .bid-info-value {
            font-weight: 600;
        }
        
        .bid-submit-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 10px 30px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .bid-submit-btn:hover {
            background-color: var(--primary-hover);
        }
        
        .bid-cancel-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 10px 30px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .bid-cancel-btn:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2 class="welcome-title">Welcome back, <?php echo htmlspecialchars($firstName); ?>!</h2>
            <p class="welcome-subtitle">Discover exciting auctions and place your bids today.</p>
            <a href="auctions.php" class="btn welcome-btn">Explore All Auctions</a>
        </div>
        
        <!-- Search Bar -->
        <div class="search-bar">
            <form action="auctions.php" method="GET">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="form-control" name="search" placeholder="Search for auctions...">
            </form>
        </div>
        
        <!-- Category Filter -->
        <div class="category-filter">
            <a href="auctions.php" class="category-item active">All</a>
            <?php foreach ($categories as $category): ?>
                <?php if (!isset($category['parent_id']) || $category['parent_id'] === null): ?>
                <a href="auctions.php?category=<?php echo $category['id']; ?>" class="category-item"><?php echo htmlspecialchars($category['name']); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <!-- Featured Auctions -->
        <div class="mb-4">
            <h3 class="section-title">
                Featured Auctions
                <a href="auctions.php?sort=bid_count&order=DESC" class="view-all">View All <i class="fas fa-chevron-right"></i></a>
            </h3>
            
            <div class="row">
                <?php if ($featuredAuctions->num_rows > 0): ?>
                    <?php while ($auction = $featuredAuctions->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-3 mb-4">
                            <div class="auction-card h-100">
                                <div class="position-relative">
                                    <span class="featured-badge">
                                        <i class="fas fa-star"></i> Featured
                                    </span>
                                    <span class="badge-corner badge bg-<?php echo $auction['bid_count'] > 0 ? 'danger' : 'secondary'; ?>">
                                        <?php echo $auction['bid_count']; ?> Bid<?php echo $auction['bid_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                    <img src="<?php echo !empty($auction['image_url']) ? htmlspecialchars('../' . $auction['image_url']) : '../assets/img/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                         class="auction-image">
                                    <div class="time-remaining">
                                        <i class="far fa-clock me-1"></i> <?php echo getTimeRemaining($auction['end_date']); ?>
                                    </div>
                                </div>
                                <div class="auction-info">
                                    <h3 class="auction-title"><?php echo htmlspecialchars($auction['title']); ?></h3>
                                    <p class="auction-price">$<?php echo number_format($auction['current_price'] ?? $auction['start_price'], 2); ?></p>
                                    <div class="auction-meta mb-3">
                                        <span><i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($auction['category_name'] ?? 'Uncategorized'); ?></span>
                                        <span><i class="fas fa-gavel me-1"></i> <?php echo $auction['bid_count']; ?> bids</span>
                                    </div>
                                    <div class="d-grid">
                                        <?php
                                        $now = new DateTime();
                                        $startDate = new DateTime($auction['start_date']);
                                        $hasStarted = $now >= $startDate;
                                        ?>
                                        <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn bid-now-btn <?php echo !$hasStarted ? 'disabled' : ''; ?>">
                                            <?php if (!$hasStarted): ?>
                                                <i class="fas fa-clock"></i> Not Started Yet
                                            <?php else: ?>
                                                View Details
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            No featured auctions available at the moment.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ending Soon Auctions -->
        <div class="mb-4">
            <h3 class="section-title">
                Ending Soon
                <a href="auctions.php?sort=end_date&order=ASC" class="view-all">View All <i class="fas fa-chevron-right"></i></a>
            </h3>
            
            <div class="row">
                <?php if ($endingSoonAuctions->num_rows > 0): ?>
                    <?php while ($auction = $endingSoonAuctions->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-3 mb-4">
                            <div class="auction-card h-100">
                                <div class="position-relative">
                                    <span class="ending-soon-badge">
                                        <i class="fas fa-hourglass-half"></i> Ending Soon
                                    </span>
                                    <span class="badge-corner badge bg-<?php echo $auction['bid_count'] > 0 ? 'danger' : 'secondary'; ?>">
                                        <?php echo $auction['bid_count']; ?> Bid<?php echo $auction['bid_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                    <img src="<?php echo !empty($auction['image_url']) ? htmlspecialchars('../' . $auction['image_url']) : '../assets/img/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                         class="auction-image">
                                    <div class="time-remaining">
                                        <i class="far fa-clock me-1"></i> <?php echo getTimeRemaining($auction['end_date']); ?>
                                    </div>
                                </div>
                                <div class="auction-info">
                                    <h3 class="auction-title"><?php echo htmlspecialchars($auction['title']); ?></h3>
                                    <p class="auction-price">$<?php echo number_format($auction['current_price'] ?? $auction['start_price'], 2); ?></p>
                                    <div class="auction-meta mb-3">
                                        <span><i class="fas fa-gavel me-1"></i> <?php echo $auction['bid_count']; ?> bids</span>
                                        <span><i class="far fa-clock me-1"></i> Hurry!</span>
                                    </div>
                                    <div class="d-grid">
                                        <?php
                                        $now = new DateTime();
                                        $startDate = new DateTime($auction['start_date']);
                                        $hasStarted = $now >= $startDate;
                                        ?>
                                        <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn bid-now-btn <?php echo !$hasStarted ? 'disabled' : ''; ?>">
                                            <?php if (!$hasStarted): ?>
                                                <i class="fas fa-clock"></i> Not Started Yet
                                            <?php else: ?>
                                                View Details
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            No auctions ending soon at the moment.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Browse All Auctions -->
        <div class="mb-4">
            <h3 class="section-title">
                Browse Auctions
                <a href="auctions.php" class="view-all">View All <i class="fas fa-chevron-right"></i></a>
            </h3>
            
            <div class="row">
                <?php if ($auctions->num_rows > 0): ?>
                    <?php while ($auction = $auctions->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-3 mb-4">
                            <div class="auction-card h-100">
                                <div class="position-relative">
                                    <span class="badge-corner badge bg-<?php echo $auction['bid_count'] > 0 ? 'danger' : 'secondary'; ?>">
                                        <?php echo $auction['bid_count']; ?> Bid<?php echo $auction['bid_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                    <img src="<?php echo !empty($auction['image_url']) ? htmlspecialchars('../' . $auction['image_url']) : '../assets/img/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                         class="auction-image">
                                    <div class="time-remaining">
                                        <i class="far fa-clock me-1"></i> <?php echo getTimeRemaining($auction['end_date']); ?>
                                    </div>
                                </div>
                                <div class="auction-info">
                                    <h3 class="auction-title"><?php echo htmlspecialchars($auction['title']); ?></h3>
                                    <p class="auction-price">$<?php echo number_format($auction['current_price'] ?? $auction['start_price'], 2); ?></p>
                                    <div class="auction-meta mb-3">
                                        <span><i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($auction['category_name'] ?? 'Uncategorized'); ?></span>
                                        <span><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($auction['seller_first_name'] ?? 'Seller'); ?></span>
                                    </div>
                                    <div class="d-grid">
                                        <?php
                                        $now = new DateTime();
                                        $startDate = new DateTime($auction['start_date']);
                                        $hasStarted = $now >= $startDate;
                                        ?>
                                        <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn bid-now-btn <?php echo !$hasStarted ? 'disabled' : ''; ?>">
                                            <?php if (!$hasStarted): ?>
                                                <i class="fas fa-clock"></i> Not Started Yet
                                            <?php else: ?>
                                                View Details
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            No active auctions found. Check back later!
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Bids -->
        <div class="mb-4">
            <h3 class="section-title">
                Your Recent Bids
                <a href="my-bids.php" class="view-all">View All <i class="fas fa-chevron-right"></i></a>
            </h3>
            
            <div class="card">
                <div class="card-body p-0">
                    <?php if ($recentBids->num_rows > 0): ?>
                        <?php while ($bid = $recentBids->fetch_assoc()): ?>
                            <div class="recent-bid-item">
                                <img src="<?php echo !empty($bid['image_url']) ? htmlspecialchars('../' . $bid['image_url']) : '../assets/img/placeholder.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($bid['auction_title']); ?>" 
                                     class="recent-bid-img">
                                <div class="recent-bid-info">
                                    <h5 class="recent-bid-title"><?php echo htmlspecialchars($bid['auction_title']); ?></h5>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="recent-bid-amount">$<?php echo number_format($bid['bid_amount'], 2); ?></span>
                                        <small class="text-muted"><?php echo formatDate($bid['created_at'], 'M d, H:i'); ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <p class="mb-0">You haven't placed any bids yet.</p>
                            <a href="auctions.php" class="btn btn-sm btn-outline-primary mt-2">Browse Auctions</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bid Modal -->
    <div class="modal fade bid-modal" id="bidModal" tabindex="-1" aria-labelledby="bidModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bidModalLabel">Place Your Bid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="bidForm" action="../api/bids.php" method="POST">
                        <input type="hidden" name="action" value="place_bid">
                        <input type="hidden" name="auction_id" id="auctionId" value="">
                        
                        <div class="bid-input-group input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="bid_amount" id="bidAmount" placeholder="Enter your bid amount" step="0.01" required>
                        </div>
                        
                        <div class="bid-info">
                            <div class="bid-info-item">
                                <span class="bid-info-label">Current Price:</span>
                                <span class="bid-info-value" id="currentPrice">$0.00</span>
                            </div>
                            <div class="bid-info-item">
                                <span class="bid-info-label">Minimum Bid:</span>
                                <span class="bid-info-value" id="minimumBid">$0.00</span>
                            </div>
                            <div class="bid-info-item">
                                <span class="bid-info-label">Your Bid:</span>
                                <span class="bid-info-value" id="yourBid">$0.00</span>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle"></i> By placing a bid, you agree to the terms and conditions of the auction platform.
                            </small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn bid-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn bid-submit-btn">Place Bid</button>
                        </div>
                    </form>
                </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bid Modal Functionality
        const bidModal = document.getElementById('bidModal');
        const bidForm = document.getElementById('bidForm');
        const auctionIdInput = document.getElementById('auctionId');
        const bidAmountInput = document.getElementById('bidAmount');
        const currentPriceSpan = document.getElementById('currentPrice');
        const minimumBidSpan = document.getElementById('minimumBid');
        const yourBidSpan = document.getElementById('yourBid');
        
        // Update bid info when bid amount changes
        bidAmountInput.addEventListener('input', function() {
            yourBidSpan.textContent = '$' + parseFloat(this.value || 0).toFixed(2);
        });
        
        // Function to open bid modal with auction details
        function openBidModal(auctionId, currentPrice) {
            auctionIdInput.value = auctionId;
            
            // Set current price and calculate minimum bid (current price + 1)
            const price = parseFloat(currentPrice);
            const minBid = price + 1;
            
            currentPriceSpan.textContent = '$' + price.toFixed(2);
            minimumBidSpan.textContent = '$' + minBid.toFixed(2);
            
            // Set minimum bid as default value
            bidAmountInput.value = minBid.toFixed(2);
            bidAmountInput.min = minBid.toFixed(2);
            yourBidSpan.textContent = '$' + minBid.toFixed(2);
            
            // Show modal
            const modal = new bootstrap.Modal(bidModal);
            modal.show();
        }
        
        // Handle bid form submission
        bidForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Bid placed successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while placing your bid. Please try again.');
            });
        });
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>
