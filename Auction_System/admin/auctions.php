<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    error_log("Unauthorized access attempt to auction management: " . ($_SESSION['email'] ?? 'Unknown user'));
    header("Location: ../login.php?admin=true");
    exit;
}

// Debug information for auction management
error_log("Admin accessing auction management. User ID: " . $_SESSION['user_id'] . ", Email: " . $_SESSION['email']);

// Get database connection
$conn = getDbConnection();

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
} else {
    error_log("Database connection successful for auction management");
}

// Get auction counts by status
try {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_auctions,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_auctions,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_auctions,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_auctions,
        COUNT(CASE WHEN status = 'paused' THEN 1 END) as paused_auctions,
        COUNT(CASE WHEN status = 'ongoing' THEN 1 END) as ongoing_auctions,
        COUNT(CASE WHEN status = 'ended' THEN 1 END) as ended_auctions
        FROM auctions");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $auctionStats = $result->fetch_assoc();
    
    error_log("Successfully retrieved auction statistics: " . json_encode($auctionStats));
} catch (Exception $e) {
    error_log("Error retrieving auction statistics: " . $e->getMessage());
    $auctionStats = [
        'total_auctions' => 0,
        'pending_auctions' => 0,
        'approved_auctions' => 0,
        'rejected_auctions' => 0,
        'paused_auctions' => 0,
        'ongoing_auctions' => 0,
        'ended_auctions' => 0
    ];
}

// Get all auctions with seller and highest bid information
try {
    $query = "SELECT a.*, 
        u.email as seller_email, 
        u.first_name as seller_first_name, 
        u.last_name as seller_last_name,
        COALESCE((SELECT MAX(b.bid_amount) FROM bids b WHERE b.auction_id = a.id), a.start_price) as highest_bid,
        (SELECT u2.email FROM bids b2 
            JOIN users u2 ON b2.user_id = u2.id 
            WHERE b2.auction_id = a.id 
            ORDER BY b2.bid_amount DESC LIMIT 1) as highest_bidder_email,
        (SELECT CONCAT(u3.first_name, ' ', u3.last_name) FROM bids b3 
            JOIN users u3 ON b3.user_id = u3.id 
            WHERE b3.auction_id = a.id 
            ORDER BY b3.bid_amount DESC LIMIT 1) as highest_bidder_name
        FROM auctions a
        JOIN users u ON a.seller_id = u.id
        ORDER BY a.created_at DESC";

    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Failed to execute query: " . $conn->error);
    }
    
    $auctions = [];
    while ($row = $result->fetch_assoc()) {
        $auctions[] = $row;
    }
    
    error_log("Successfully retrieved " . count($auctions) . " auctions");
} catch (Exception $e) {
    error_log("Error retrieving auctions: " . $e->getMessage());
    $auctions = [];
}

// Debug information
debug_log("Admin auction management accessed by: " . $_SESSION['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <svg class="logo-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <path d="M30 30 L70 30 L70 70 L30 70 Z" stroke="#FFF" stroke-width="6" fill="none" />
                        <path d="M20 20 L80 20 L80 80 L20 80 Z" stroke="#FFF" stroke-width="6" fill="none" transform="rotate(45 50 50)" />
                    </svg>
                    <span>Auction Admin</span>
                </div>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li>
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php">
                            <i class="fas fa-users"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="auctions.php">
                            <i class="fas fa-gavel"></i>
                            <span>Auction Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="bids.php">
                            <i class="fas fa-chart-line"></i>
                            <span>Bid Monitoring</span>
                        </a>
                    </li>
                    <li>
                        <a href="notifications.php">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li>
                        <a href="wallet.php">
                            <i class="fas fa-wallet"></i>
                            <span>Wallet Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports & Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="chat.php">
                            <i class="fas fa-comments"></i>
                            <span>Chat Monitoring</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i>
                            <span>System Settings</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="#" id="logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Top Navbar -->
            <div class="admin-navbar">
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="navbar-right">
                    <div class="notifications">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </div>
                    <div class="admin-profile">
                        <span><?php echo $_SESSION['email']; ?></span>
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="content-header">
                    <h1>Auction Management</h1>
                    <div class="date-range">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F d, Y'); ?></span>
                    </div>
                </div>

                <!-- Alert container for AJAX responses -->
                <div id="alert-container"></div>

                <!-- Stats Cards -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Total Auctions</h5>
                                <h2><?php echo $auctionStats['total_auctions'] ?? 0; ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-gavel"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span>All time auctions</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Ongoing Auctions</h5>
                                <h2><?php echo $auctionStats['ongoing_auctions'] ?? 0; ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span>Currently active</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Pending Approval</h5>
                                <h2><?php echo $auctionStats['pending_auctions'] ?? 0; ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span>Awaiting review</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Completed Auctions</h5>
                                <h2><?php echo $auctionStats['ended_auctions'] ?? 0; ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span>Successfully ended</span>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filter Auctions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="status-filter" class="form-label">Status</label>
                                <select id="status-filter" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="paused">Paused</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="ended">Ended</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="seller-filter" class="form-label">Seller</label>
                                <input type="text" id="seller-filter" class="form-control" placeholder="Seller name or email">
                            </div>
                            <div class="col-md-3">
                                <label for="date-from" class="form-label">Date From</label>
                                <input type="text" id="date-from" class="form-control date-picker" placeholder="Select date">
                            </div>
                            <div class="col-md-3">
                                <label for="date-to" class="form-label">Date To</label>
                                <input type="text" id="date-to" class="form-control date-picker" placeholder="Select date">
                            </div>
                            <div class="col-12">
                                <button id="apply-filters" class="btn btn-primary">Apply Filters</button>
                                <button id="reset-filters" class="btn btn-outline-secondary ms-2">Reset</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Auctions Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Auctions</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" id="refresh-table">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="auctions-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Title</th>
                                        <th>Seller</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Highest Bid</th>
                                        <th>Highest Bidder</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($auctions)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No auctions found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($auctions as $auction): ?>
                                            <tr data-auction-id="<?php echo $auction['id']; ?>">
                                                <td>
                                                    <img src="<?php echo !empty($auction['image_url']) ? '../' . $auction['image_url'] : '../assets/img/placeholder.jpg'; ?>" 
                                                         alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                                         class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                                </td>
                                                <td><?php echo htmlspecialchars($auction['title']); ?></td>
                                                <td>
                                                    <?php 
                                                        $sellerName = trim($auction['seller_first_name'] . ' ' . $auction['seller_last_name']);
                                                        echo !empty($sellerName) ? htmlspecialchars($sellerName) : htmlspecialchars($auction['seller_email']); 
                                                    ?>
                                                </td>
                                                <td class="start-date-cell" data-date="<?php echo $auction['start_date']; ?>">
                                                    <?php echo !empty($auction['start_date']) ? date('M d, Y H:i', strtotime($auction['start_date'])) : 'Not set'; ?>
                                                </td>
                                                <td class="end-date-cell" data-date="<?php echo $auction['end_date']; ?>">
                                                    <?php echo !empty($auction['end_date']) ? date('M d, Y H:i', strtotime($auction['end_date'])) : 'Not set'; ?>
                                                </td>
                                                <td class="status-cell">
                                                    <?php echo getAuctionStatusBadge($auction['status']); ?>
                                                </td>
                                                <td class="highest-bid-cell">
                                                    <?php 
                                                        echo !empty($auction['highest_bid']) 
                                                            ? '$' . number_format($auction['highest_bid'], 2) 
                                                            : '$' . number_format($auction['start_price'], 2) . ' (Starting price)'; 
                                                    ?>
                                                </td>
                                                <td class="highest-bidder-cell">
                                                    <?php 
                                                        echo !empty($auction['highest_bidder_name']) 
                                                            ? htmlspecialchars($auction['highest_bidder_name']) 
                                                            : (!empty($auction['highest_bidder_email']) 
                                                                ? htmlspecialchars($auction['highest_bidder_email']) 
                                                                : 'N/A'); 
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-info view-auction" 
                                                                data-auction-id="<?php echo $auction['id']; ?>"
                                                                title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if ($auction['status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-sm btn-success approve-auction" 
                                                                    data-auction-id="<?php echo $auction['id']; ?>"
                                                                    title="Approve Auction">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger reject-auction" 
                                                                    data-auction-id="<?php echo $auction['id']; ?>"
                                                                    title="Reject Auction">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (in_array($auction['status'], ['approved', 'ongoing'])): ?>
                                                            <button type="button" class="btn btn-sm btn-warning pause-auction" 
                                                                    data-auction-id="<?php echo $auction['id']; ?>"
                                                                    title="Pause Auction">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($auction['status'] === 'paused'): ?>
                                                            <button type="button" class="btn btn-sm btn-success resume-auction" 
                                                                    data-auction-id="<?php echo $auction['id']; ?>"
                                                                    title="Resume Auction">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (in_array($auction['status'], ['approved', 'ongoing', 'paused'])): ?>
                                                            <button type="button" class="btn btn-sm btn-danger stop-auction" 
                                                                    data-auction-id="<?php echo $auction['id']; ?>"
                                                                    title="Stop Auction">
                                                                <i class="fas fa-stop"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button type="button" class="btn btn-sm btn-primary edit-dates" 
                                                                data-auction-id="<?php echo $auction['id']; ?>"
                                                                data-start-date="<?php echo $auction['start_date']; ?>"
                                                                data-end-date="<?php echo $auction['end_date']; ?>"
                                                                title="Edit Dates">
                                                            <i class="fas fa-calendar-alt"></i>
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-sm btn-secondary view-bids" 
                                                                data-auction-id="<?php echo $auction['id']; ?>"
                                                                data-auction-title="<?php echo htmlspecialchars($auction['title']); ?>"
                                                                title="View Bids">
                                                            <i class="fas fa-list"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Dates Modal -->
    <div class="modal fade" id="editDatesModal" tabindex="-1" aria-labelledby="editDatesModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDatesModalLabel">Edit Auction Dates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-dates-form">
                        <input type="hidden" id="edit-auction-id" name="auction_id">
                        <div class="mb-3">
                            <label for="edit-start-date" class="form-label">Start Date and Time:</label>
                            <input type="text" class="form-control datetime-picker" id="edit-start-date" name="start_date" placeholder="Select start date and time">
                        </div>
                        <div class="mb-3">
                            <label for="edit-end-date" class="form-label">End Date and Time:</label>
                            <input type="text" class="form-control datetime-picker" id="edit-end-date" name="end_date" placeholder="Select end date and time">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-dates">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Auction Modal -->
    <div class="modal fade" id="rejectAuctionModal" tabindex="-1" aria-labelledby="rejectAuctionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectAuctionModalLabel">Reject Auction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to reject this auction. Please provide a reason:</p>
                    <form id="reject-auction-form">
                        <input type="hidden" id="reject-auction-id" name="auction_id">
                        <div class="mb-3">
                            <label for="rejection-reason" class="form-label">Rejection Reason:</label>
                            <textarea class="form-control" id="rejection-reason" name="reason" rows="3" placeholder="Please provide a reason for rejection"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-reject-auction">Confirm Rejection</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Bids Modal -->
    <div class="modal fade" id="viewBidsModal" tabindex="-1" aria-labelledby="viewBidsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewBidsModalLabel">Bid History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center" id="bids-loading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading bid history...</p>
                    </div>
                    <div id="bids-content" style="display: none;">
                        <h6 id="auction-title-display" class="mb-3"></h6>
                        <div class="table-responsive">
                            <table class="table table-striped" id="bids-table">
                                <thead>
                                    <tr>
                                        <th>Bidder</th>
                                        <th>Bid Amount</th>
                                        <th>Bid Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="bids-table-body">
                                    <!-- Bid data will be loaded here via AJAX -->
                                </tbody>
                            </table>
                        </div>
                        <div id="no-bids-message" class="text-center" style="display: none;">
                            <p>No bids have been placed on this auction yet.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">Export Auctions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="export-form">
                        <div class="mb-3">
                            <label class="form-label">Export Format</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_format" id="export-csv" value="csv" checked>
                                <label class="form-check-label" for="export-csv">
                                    CSV
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_format" id="export-excel" value="excel">
                                <label class="form-check-label" for="export-excel">
                                    Excel
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_format" id="export-pdf" value="pdf">
                                <label class="form-check-label" for="export-pdf">
                                    PDF
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data to Export</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="export_data[]" id="export-all" value="all" checked>
                                <label class="form-check-label" for="export-all">
                                    All Data
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="export_data[]" id="export-current" value="current">
                                <label class="form-check-label" for="export-current">
                                    Current Filter Results Only
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-export">Export</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Debug Info Modal -->
    <div class="modal fade" id="debugInfoModal" tabindex="-1" aria-labelledby="debugInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="debugInfoModalLabel">Debug Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <p><strong>Server Information:</strong></p>
                        <pre id="server-info"><?php echo json_encode([
                            'PHP Version' => phpversion(),
                            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                            'Database Connection' => $conn->connect_errno ? 'Failed' : 'Success',
                            'Session Status' => session_status() == PHP_SESSION_ACTIVE ? 'Active' : 'Inactive',
                            'User ID' => $_SESSION['user_id'] ?? 'Not set',
                            'User Role' => $_SESSION['role'] ?? 'Not set',
                            'Request Method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
                        ], JSON_PRETTY_PRINT); ?></pre>
                    </div>
                    <div class="alert alert-warning">
                        <p><strong>Troubleshooting Steps:</strong></p>
                        <ol>
                            <li>Check PHP error logs for detailed error messages</li>
                            <li>Verify database connection and table structure</li>
                            <li>Ensure proper permissions for API endpoints</li>
                            <li>Check browser console for JavaScript errors</li>
                            <li>Verify AJAX requests are properly formatted</li>
                        </ol>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../assets/js/admin-auctions.js"></script>
    
    <!-- Debug button (only visible in development) -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] === 'true'): ?>
    <div style="position: fixed; bottom: 20px; right: 20px;">
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#debugInfoModal">
            <i class="fas fa-bug"></i> Debug Info
        </button>
    </div>
    <?php endif; ?>
</body>
</html>
