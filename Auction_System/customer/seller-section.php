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

// Get user's auctions
$stmt = $conn->prepare("SELECT a.*, 
                      (SELECT image_url FROM auction_images WHERE auction_id = a.id LIMIT 1) as image_url,
                      (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count
                      FROM auctions a 
                      WHERE a.seller_id = ? 
                      ORDER BY a.created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$auctions = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Section - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
        }
        .auction-image {
            height: 120px;
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
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .auction-price {
            font-weight: 700;
            color: #0d6efd;
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
            color: #0d6efd;
        }
        .nav-icon {
            font-size: 1.5rem;
            margin-bottom: 0.2rem;
        }
        .create-auction-btn {
            position: fixed;
            bottom: 80px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #0d6efd;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        .create-auction-btn i {
            font-size: 1.5rem;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .status-approved {
            background-color: #0dcaf0;
            color: #212529;
        }
        .status-ongoing {
            background-color: #198754;
            color: white;
        }
        .status-ended {
            background-color: #6c757d;
            color: white;
        }
        .status-rejected {
            background-color: #dc3545;
            color: white;
        }
        .tab-content {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">My Listings</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
        
        <ul class="nav nav-pills nav-fill mb-3" id="auctionTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-auctions" type="button" role="tab" aria-controls="all-auctions" aria-selected="true">All</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-auctions" type="button" role="tab" aria-controls="active-auctions" aria-selected="false">Active</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-auctions" type="button" role="tab" aria-controls="pending-auctions" aria-selected="false">Pending</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="ended-tab" data-bs-toggle="tab" data-bs-target="#ended-auctions" type="button" role="tab" aria-controls="ended-auctions" aria-selected="false">Ended</button>
            </li>
        </ul>
        
        <div class="tab-content" id="auctionTabsContent">
            <div class="tab-pane fade show active" id="all-auctions" role="tabpanel" aria-labelledby="all-tab">
                <div class="row">
                    <?php if ($auctions->num_rows > 0): ?>
                        <?php 
                        // Reset the result pointer
                        $auctions->data_seek(0);
                        while ($auction = $auctions->fetch_assoc()): 
                        ?>
                            <div class="col-12">
                                <div class="auction-card position-relative">
                                    <?php 
                                    $statusClass = '';
                                    switch ($auction['status']) {
                                        case 'pending':
                                            $statusClass = 'status-pending';
                                            break;
                                        case 'approved':
                                            $statusClass = 'status-approved';
                                            break;
                                        case 'ongoing':
                                            $statusClass = 'status-ongoing';
                                            break;
                                        case 'ended':
                                            $statusClass = 'status-ended';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'status-rejected';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($auction['status']); ?>
                                    </span>
                                    <img src="<?php echo !empty($auction['image_url']) ? htmlspecialchars($auction['image_url']) : '../assets/img/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                         class="auction-image">
                                    <div class="auction-info">
                                        <h3 class="auction-title"><?php echo htmlspecialchars($auction['title']); ?></h3>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <p class="auction-price mb-0">$<?php echo number_format($auction['current_price'] ?? $auction['start_price'], 2); ?></p>
                                            <span class="badge bg-primary"><?php echo $auction['bid_count']; ?> bids</span>
                                        </div>
                                        <div class="auction-meta mt-2">
                                            <span>Created: <?php echo formatDate($auction['created_at'], 'M d'); ?></span>
                                            <?php if (!empty($auction['end_date'])): ?>
                                                <span>Ends: <?php echo formatDate($auction['end_date'], 'M d, H:i'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-3">
                                            <a href="edit-auction.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline-primary btn-sm me-2">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                You haven't created any auctions yet. Click the "+" button to create your first listing!
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="tab-pane fade" id="active-auctions" role="tabpanel" aria-labelledby="active-tab">
                <div class="row">
                    <?php 
                    $auctions->data_seek(0);
                    $hasActive = false;
                    while ($auction = $auctions->fetch_assoc()): 
                        if ($auction['status'] == 'ongoing' || $auction['status'] == 'approved'):
                            $hasActive = true;
                    ?>
                            <div class="col-12">
                                <div class="auction-card position-relative">
                                    <span class="status-badge <?php echo $auction['status'] == 'ongoing' ? 'status-ongoing' : 'status-approved'; ?>">
                                        <?php echo ucfirst($auction['status']); ?>
                                    </span>
                                    <img src="<?php echo !empty($auction['image_url']) ? htmlspecialchars($auction['image_url']) : '../assets/img/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                         class="auction-image">
                                    <div class="auction-info">
                                        <h3 class="auction-title"><?php echo htmlspecialchars($auction['title']); ?></h3>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <p class="auction-price mb-0">$<?php echo number_format($auction['current_price'] ?? $auction['start_price'], 2); ?></p>
                                            <span class="badge bg-primary"><?php echo $auction['bid_count']; ?> bids</span>
                                        </div>
                                        <div class="auction-meta mt-2">
                                            <?php if (!empty($auction['end_date'])): ?>
                                                <span>Ends: <?php echo formatDate($auction['end_date'], 'M d, H:i'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-3">
                                            <a href="edit-auction.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline-primary btn-sm me-2">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php 
                        endif;
                    endwhile; 
                    
                    if (!$hasActive):
                    ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                You don't have any active auctions at the moment.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="tab-pane fade" id="pending-auctions" role="tabpanel" aria-labelledby="pending-tab">
                <div class="row">
                    <?php 
                    $auctions->data_seek(0);
                    $hasPending = false;
                    while ($auction = $auctions->fetch_assoc()): 
                        if ($auction['status'] == 'pending'):
                            $hasPending = true;
                    ?>
                            <div class="col-12">
                                <div class="auction-card position-relative">
                                    <span class="status-badge status-pending">Pending</span>
                                    <img src="<?php echo !empty($auction['image_url']) ? htmlspecialchars($auction['image_url']) : '../assets/img/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                         class="auction-image">
                                    <div class="auction-info">
                                        <h3 class="auction-title"><?php echo htmlspecialchars($auction['title']); ?></h3>
                                        <p class="auction-price">$<?php echo number_format($auction['start_price'], 2); ?></p>
                                        <div class="auction-meta">
                                            <span>Created: <?php echo formatDate($auction['created_at'], 'M d'); ?></span>
                                        </div>
                                        <div class="mt-3">
                                            <a href="edit-auction.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline-primary btn-sm me-2">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php 
                        endif;
                    endwhile; 
                    
                    if (!$hasPending):
                    ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                You don't have any pending auctions at the moment.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="tab-pane fade" id="ended-auctions" role="tabpanel" aria-labelledby="ended-tab">
                <div class="row">
                    <?php 
                    $auctions->data_seek(0);
                    $hasEnded = false;
                    while ($auction = $auctions->fetch_assoc()): 
                        if ($auction['status'] == 'ended' || $auction['status'] == 'rejected'):
                            $hasEnded = true;
                    ?>
                            <div class="col-12">
                                <div class="auction-card position-relative">
                                    <span class="status-badge <?php echo $auction['status'] == 'ended' ? 'status-ended' : 'status-rejected'; ?>">
                                        <?php echo ucfirst($auction['status']); ?>
                                    </span>
                                    <img src="<?php echo !empty($auction['image_url']) ? htmlspecialchars($auction['image_url']) : '../assets/img/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                         class="auction-image">
                                    <div class="auction-info">
                                        <h3 class="auction-title"><?php echo htmlspecialchars($auction['title']); ?></h3>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <p class="auction-price mb-0">$<?php echo number_format($auction['current_price'] ?? $auction['start_price'], 2); ?></p>
                                            <span class="badge bg-secondary"><?php echo $auction['bid_count']; ?> bids</span>
                                        </div>
                                        <div class="auction-meta mt-2">
                                            <span>Ended: <?php echo !empty($auction['end_date']) ? formatDate($auction['end_date'], 'M d') : 'N/A'; ?></span>
                                        </div>
                                        <div class="mt-3">
                                            <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                            <?php if ($auction['status'] == 'ended' && $auction['bid_count'] > 0): ?>
                                                <a href="auction-results.php?id=<?php echo $auction['id']; ?>" class="btn btn-outline-success btn-sm ms-2">
                                                    <i class="fas fa-trophy"></i> Results
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php 
                        endif;
                    endwhile; 
                    
                    if (!$hasEnded):
                    ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                You don't have any ended auctions at the moment.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Auction Button -->
    <a href="create-auction.php" class="create-auction-btn">
        <i class="fas fa-plus"></i>
    </a>
    
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
</body>
</html>
