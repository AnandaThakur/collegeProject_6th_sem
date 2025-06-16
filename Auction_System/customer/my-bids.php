<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Start session and check if user is logged in
startSession();
if (!isLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getDbConnection();

// Get user ID from session
$userId = $_SESSION['user_id'];

// Get user's bids grouped by auction
$query = "SELECT b.*, a.title as auction_title, a.end_date, a.status as auction_status, 
          a.current_price, a.seller_id,
          (SELECT MAX(bid_amount) FROM bids WHERE auction_id = b.auction_id) as highest_bid,
          (SELECT user_id FROM bids WHERE auction_id = b.auction_id ORDER BY bid_amount DESC LIMIT 1) as highest_bidder_id
          FROM bids b
          JOIN auctions a ON b.auction_id = a.id
          WHERE b.user_id = ?
          GROUP BY b.auction_id
          ORDER BY a.end_date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

// Organize bids by status
$activeBids = [];
$wonBids = [];
$lostBids = [];

while ($row = $result->fetch_assoc()) {
    // Get primary image for auction
    $primaryImage = getPrimaryAuctionImage($row['auction_id']);
    $row['image_url'] = $primaryImage ? $primaryImage['image_url'] : 'assets/img/no-image.jpg';
    
    // Format dates
    $row['formatted_end_date'] = formatDate($row['end_date']);
    
    // Calculate time remaining
    $endDate = new DateTime($row['end_date']);
    $now = new DateTime();
    $interval = $now->diff($endDate);
    
    if ($endDate < $now) {
        $row['time_remaining'] = 'Ended';
    } else if ($interval->days > 0) {
        $row['time_remaining'] = $interval->format('%d days, %h hours');
    } else {
        $row['time_remaining'] = $interval->format('%h hours, %i minutes');
    }
    
    // Determine bid status
    $isHighestBidder = $row['highest_bidder_id'] == $userId;
    
    if ($row['auction_status'] === 'ended') {
        if ($isHighestBidder) {
            $wonBids[] = $row;
        } else {
            $lostBids[] = $row;
        }
    } else {
        $activeBids[] = $row;
    }
}

// Get total counts
$totalActive = count($activeBids);
$totalWon = count($wonBids);
$totalLost = count($lostBids);
$totalBids = $totalActive + $totalWon + $totalLost;

// Get user's bid statistics
$query = "SELECT 
          COUNT(DISTINCT auction_id) as total_auctions_bid,
          COUNT(*) as total_bids_placed,
          MAX(bid_amount) as highest_bid_amount,
          AVG(bid_amount) as average_bid_amount
          FROM bids
          WHERE user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$bidStats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bids | Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"> -->
    <style>
        body {
            padding-bottom: 70px; /* Space for fixed bottom navbar */
        }
        .bid-card {
            transition: transform 0.2s;
        }
        .bid-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .auction-image {
            height: 100px;
            width: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        .bid-status {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .time-remaining {
            font-size: 0.8rem;
        }
        .bid-amount {
            font-weight: bold;
            font-size: 1.1rem;
        }
        .highest-bid {
            color: #28a745;
        }
        .outbid {
            color: #dc3545;
        }
        .stats-card {
            border-left: 4px solid #007bff;
            background-color: #f8f9fa;
        }
        .no-bids {
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #6c757d;
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
            color: #0d6efd;
        }
        .nav-icon {
            font-size: 1.5rem;
            margin-bottom: 0.2rem;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 25%;
            font-size: 0.6rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <h1 class="h3 mb-4">My Bids</h1>
        
        <!-- Bid Statistics -->
        <div class="card mb-4 stats-card">
            <div class="card-body">
                <h5 class="card-title">Your Bidding Statistics</h5>
                <div class="row text-center g-3">
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded">
                            <div class="h4"><?php echo $bidStats['total_auctions_bid']; ?></div>
                            <div class="small text-muted">Auctions Bid</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded">
                            <div class="h4"><?php echo $bidStats['total_bids_placed']; ?></div>
                            <div class="small text-muted">Total Bids</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded">
                            <div class="h4">$<?php echo number_format($bidStats['highest_bid_amount'] ?? 0, 2); ?></div>
                            <div class="small text-muted">Highest Bid</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded">
                            <div class="h4">$<?php echo number_format($bidStats['average_bid_amount'] ?? 0, 2); ?></div>
                            <div class="small text-muted">Average Bid</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bid Tabs -->
        <ul class="nav nav-tabs mb-4" id="bidTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" aria-controls="active" aria-selected="true">
                    Active <span class="badge bg-primary"><?php echo $totalActive; ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="won-tab" data-bs-toggle="tab" data-bs-target="#won" type="button" role="tab" aria-controls="won" aria-selected="false">
                    Won <span class="badge bg-success"><?php echo $totalWon; ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="lost-tab" data-bs-toggle="tab" data-bs-target="#lost" type="button" role="tab" aria-controls="lost" aria-selected="false">
                    Lost <span class="badge bg-secondary"><?php echo $totalLost; ?></span>
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="bidTabsContent">
            <!-- Active Bids Tab -->
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <?php if (count($activeBids) > 0): ?>
                    <?php foreach ($activeBids as $bid): ?>
                        <div class="card mb-3 bid-card">
                            <div class="card-body position-relative">
                                <div class="row">
                                    <div class="col-4 col-md-2">
                                        <img src="../<?php echo $bid['image_url']; ?>" class="auction-image" alt="<?php echo $bid['auction_title']; ?>">
                                    </div>
                                    <div class="col-8 col-md-10">
                                        <h5 class="card-title"><?php echo $bid['auction_title']; ?></h5>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="time-remaining text-<?php echo $bid['time_remaining'] === 'Ended' ? 'danger' : 'success'; ?>">
                                                <i class="fas fa-clock"></i> <?php echo $bid['time_remaining']; ?>
                                            </span>
                                            <span class="bid-status">
                                                <?php if ($bid['highest_bidder_id'] == $userId): ?>
                                                    <span class="badge bg-success">Highest Bidder</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Outbid</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-6">
                                                <small class="text-muted">Your Bid:</small><br>
                                                <span class="bid-amount">$<?php echo number_format($bid['bid_amount'], 2); ?></span>
                                            </div>
                                            <div class="col-6 text-end">
                                                <small class="text-muted">Current Highest:</small><br>
                                                <span class="bid-amount <?php echo $bid['highest_bidder_id'] == $userId ? 'highest-bid' : 'outbid'; ?>">
                                                    $<?php echo number_format($bid['highest_bid'], 2); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn btn-primary btn-sm">View Auction</a>
                                            <?php if ($bid['highest_bidder_id'] != $userId): ?>
                                                <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>#bid-form" class="btn btn-outline-danger btn-sm">Place Higher Bid</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small>Ends: <?php echo $bid['formatted_end_date']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-bids">
                        <i class="fas fa-meh" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">No active bids</h5>
                        <p>You don't have any active bids at the moment.</p>
                        <a href="auctions.php" class="btn btn-primary mt-2">Browse Auctions</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Won Bids Tab -->
            <div class="tab-pane fade" id="won" role="tabpanel" aria-labelledby="won-tab">
                <?php if (count($wonBids) > 0): ?>
                    <?php foreach ($wonBids as $bid): ?>
                        <div class="card mb-3 bid-card">
                            <div class="card-body position-relative">
                                <div class="row">
                                    <div class="col-4 col-md-2">
                                        <img src="../<?php echo $bid['image_url']; ?>" class="auction-image" alt="<?php echo $bid['auction_title']; ?>">
                                    </div>
                                    <div class="col-8 col-md-10">
                                        <h5 class="card-title"><?php echo $bid['auction_title']; ?></h5>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-success">Won</span>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-6">
                                                <small class="text-muted">Your Winning Bid:</small><br>
                                                <span class="bid-amount highest-bid">$<?php echo number_format($bid['bid_amount'], 2); ?></span>
                                            </div>
                                            <div class="col-6 text-end">
                                                <small class="text-muted">Final Price:</small><br>
                                                <span class="bid-amount">$<?php echo number_format($bid['current_price'], 2); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                            <a href="contact-seller.php?auction_id=<?php echo $bid['auction_id']; ?>&seller_id=<?php echo $bid['seller_id']; ?>" class="btn btn-outline-success btn-sm">Contact Seller</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small>Ended: <?php echo $bid['formatted_end_date']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-bids">
                        <i class="fas fa-trophy" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">No won auctions yet</h5>
                        <p>You haven't won any auctions yet. Keep bidding!</p>
                        <a href="auctions.php" class="btn btn-primary mt-2">Browse Auctions</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Lost Bids Tab -->
            <div class="tab-pane fade" id="lost" role="tabpanel" aria-labelledby="lost-tab">
                <?php if (count($lostBids) > 0): ?>
                    <?php foreach ($lostBids as $bid): ?>
                        <div class="card mb-3 bid-card">
                            <div class="card-body position-relative">
                                <div class="row">
                                    <div class="col-4 col-md-2">
                                        <img src="../<?php echo $bid['image_url']; ?>" class="auction-image" alt="<?php echo $bid['auction_title']; ?>">
                                    </div>
                                    <div class="col-8 col-md-10">
                                        <h5 class="card-title"><?php echo $bid['auction_title']; ?></h5>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-secondary">Lost</span>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-6">
                                                <small class="text-muted">Your Bid:</small><br>
                                                <span class="bid-amount">$<?php echo number_format($bid['bid_amount'], 2); ?></span>
                                            </div>
                                            <div class="col-6 text-end">
                                                <small class="text-muted">Winning Bid:</small><br>
                                                <span class="bid-amount outbid">$<?php echo number_format($bid['highest_bid'], 2); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <a href="auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                            <a href="auctions.php?category=<?php echo $bid['category_id']; ?>" class="btn btn-outline-secondary btn-sm">Similar Items</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small>Ended: <?php echo $bid['formatted_end_date']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-bids">
                        <i class="fas fa-smile" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">No lost auctions</h5>
                        <p>You haven't lost any auctions yet. Good luck with your active bids!</p>
                        <a href="auctions.php" class="btn btn-primary mt-2">Browse Auctions</a>
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
                <a href="auctions.php" class="nav-link">
                    <i class="fas fa-gavel nav-icon"></i>
                    <span class="nav-text small">Auctions</span>
                </a>
            </div>
            <div class="col-3">
                <a href="my-bids.php" class="nav-link active position-relative">
                    <i class="fas fa-bookmark nav-icon"></i>
                    <span class="nav-text small">My Bids</span>
                    <?php
                    // Count active bids
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bids b 
                                          JOIN auctions a ON b.auction_id = a.id 
                                          WHERE b.user_id = ? AND a.status = 'ongoing'");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $activeBids = $result->fetch_assoc()['count'];
                    
                    if ($activeBids > 0) {
                        echo '<span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger notification-badge">' . $activeBids . '</span>';
                    }
                    ?>
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
</body>
</html>
