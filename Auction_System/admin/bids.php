<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
startSession();
if (!isLoggedIn() || !isAdmin()) {
  redirect('../login.php?admin=true');
}

// Get database connection
$conn = getDbConnection();

// Get bid statistics
$stmt = $conn->prepare("SELECT 
  COUNT(*) as total_bids,
  COUNT(DISTINCT auction_id) as auctions_with_bids,
  COUNT(DISTINCT user_id) as unique_bidders,
  MAX(bid_amount) as highest_bid,
  AVG(bid_amount) as average_bid
  FROM bids");
$stmt->execute();
$bidStats = $stmt->get_result()->fetch_assoc();

// Get ongoing auctions with bid information
$query = "SELECT a.*, 
  u.email as seller_email,
  u.first_name as seller_first_name, 
  u.last_name as seller_last_name,
  (SELECT COUNT(*) FROM bids b WHERE b.auction_id = a.id) as bid_count,
  (SELECT MAX(b2.bid_amount) FROM bids b2 WHERE b2.auction_id = a.id) as highest_bid,
  (SELECT u2.email FROM bids b3 
      JOIN users u2 ON b3.user_id = u2.id 
      WHERE b3.auction_id = a.id 
      ORDER BY b3.bid_amount DESC, b3.created_at ASC LIMIT 1) as highest_bidder_email,
  (SELECT CONCAT(u3.first_name, ' ', u3.last_name) FROM bids b4 
      JOIN users u3 ON b4.user_id = u3.id 
      WHERE b4.auction_id = a.id 
      ORDER BY b4.bid_amount DESC, b4.created_at ASC LIMIT 1) as highest_bidder_name
  FROM auctions a
  JOIN users u ON a.seller_id = u.id
  WHERE a.status IN ('ongoing', 'approved')
  ORDER BY highest_bid DESC, a.end_date ASC";

$result = $conn->query($query);
$ongoingAuctions = [];
if ($result) {
  while ($row = $result->fetch_assoc()) {
      $ongoingAuctions[] = $row;
  }
}

// Get recent bids across all auctions
$recentBidsQuery = "SELECT b.*, 
  a.title as auction_title, 
  u.email as bidder_email,
  u.first_name as bidder_first_name,
  u.last_name as bidder_last_name
  FROM bids b
  JOIN auctions a ON b.auction_id = a.id
  JOIN users u ON b.user_id = u.id
  ORDER BY b.created_at DESC
  LIMIT 10";

$recentBidsResult = $conn->query($recentBidsQuery);
$recentBids = [];
if ($recentBidsResult) {
  while ($row = $recentBidsResult->fetch_assoc()) {
      $recentBids[] = $row;
  }
}

// Get auctions with bids that are not ongoing
$completedAuctionsQuery = "SELECT a.*, 
  u.email as seller_email,
  u.first_name as seller_first_name, 
  u.last_name as seller_last_name,
  (SELECT COUNT(*) FROM bids b WHERE b.auction_id = a.id) as bid_count,
  (SELECT MAX(b2.bid_amount) FROM bids b2 WHERE b2.auction_id = a.id) as highest_bid,
  (SELECT u2.email FROM bids b3 
      JOIN users u2 ON b3.user_id = u2.id 
      WHERE b3.auction_id = a.id 
      ORDER BY b3.bid_amount DESC, b3.created_at ASC LIMIT 1) as highest_bidder_email,
  (SELECT CONCAT(u3.first_name, ' ', u3.last_name) FROM bids b4 
      JOIN users u3 ON b4.user_id = u3.id 
      WHERE b4.auction_id = a.id 
      ORDER BY b4.bid_amount DESC, b4.created_at ASC LIMIT 1) as highest_bidder_name
  FROM auctions a
  JOIN users u ON a.seller_id = u.id
  WHERE a.status NOT IN ('ongoing', 'approved', 'pending')
  AND EXISTS (SELECT 1 FROM bids WHERE auction_id = a.id)
  ORDER BY a.end_date DESC";

$completedResult = $conn->query($completedAuctionsQuery);
$completedAuctions = [];
if ($completedResult) {
  while ($row = $completedResult->fetch_assoc()) {
      $completedAuctions[] = $row;
  }
}

// Debug information
debug_log("Admin bid monitoring accessed by: " . $_SESSION['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bid Monitoring - Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="../assets/css/bid-monitoring.css">
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
                  <li>
                      <a href="auctions.php">
                          <i class="fas fa-gavel"></i>
                          <span>Auction Management</span>
                      </a>
                  </li>
                  <li class="active">
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
                      <a href="wallet-management.php">
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
                      <a href="chat-monitoring.php">
                          <i class="fas fa-comments"></i>
                          <span>Chat Monitoring</span>
                      </a>
                  </li>
                  <li>
                      <a href="system-settings.php">
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
                  <h1>Bid Monitoring</h1>
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
                              <h5>Total Bids</h5>
                              <h2><?php echo number_format($bidStats['total_bids'] ?? 0); ?></h2>
                          </div>
                          <div class="stat-card-icon">
                              <i class="fas fa-gavel"></i>
                          </div>
                      </div>
                      <div class="stat-card-footer">
                          <span>All time bids</span>
                      </div>
                  </div>
                  <div class="stat-card">
                      <div class="stat-card-content">
                          <div class="stat-card-info">
                              <h5>Auctions with Bids</h5>
                              <h2><?php echo number_format($bidStats['auctions_with_bids'] ?? 0); ?></h2>
                          </div>
                          <div class="stat-card-icon">
                              <i class="fas fa-box-open"></i>
                          </div>
                      </div>
                      <div class="stat-card-footer">
                          <span>Items with activity</span>
                      </div>
                  </div>
                  <div class="stat-card">
                      <div class="stat-card-content">
                          <div class="stat-card-info">
                              <h5>Unique Bidders</h5>
                              <h2><?php echo number_format($bidStats['unique_bidders'] ?? 0); ?></h2>
                          </div>
                          <div class="stat-card-icon">
                              <i class="fas fa-users"></i>
                          </div>
                      </div>
                      <div class="stat-card-footer">
                          <span>Active participants</span>
                      </div>
                  </div>
                  <div class="stat-card">
                      <div class="stat-card-content">
                          <div class="stat-card-info">
                              <h5>Average Bid</h5>
                              <h2>Rs.<?php echo number_format($bidStats['average_bid'] ?? 0, 2); ?></h2>
                          </div>
                          <div class="stat-card-icon">
                              <i class="fas fa-dollar-sign"></i>
                          </div>
                      </div>
                      <div class="stat-card-footer">
                          <span>Across all auctions</span>
                      </div>
                  </div>
              </div>

              <!-- Live Monitoring Section -->
              <div class="card mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center">
                      <h5 class="mb-0"><i class="fas fa-broadcast-tower me-2"></i>Live Auction Monitoring</h5>
                      <div>
                          <span class="text-muted me-2" id="last-update-time">Last updated: Just now</span>
                          <button class="btn btn-sm btn-outline-primary" id="refresh-auctions">
                              <i class="fas fa-sync-alt"></i> Refresh
                          </button>
                          <div class="form-check form-switch d-inline-block ms-2">
                              <input class="form-check-input" type="checkbox" id="auto-refresh" checked>
                              <label class="form-check-label" for="auto-refresh">Auto-refresh</label>
                          </div>
                      </div>
                  </div>
                  <div class="card-body">
                      <div class="table-responsive">
                          <table class="table table-hover" id="live-auctions-table">
                              <thead>
                                  <tr>
                                      <th>Image</th>
                                      <th>Title</th>
                                      <th>Current Bid</th>
                                      <th>Bids</th>
                                      <th>Highest Bidder</th>
                                      <th>Time Left</th>
                                      <th>Min. Increment</th>
                                      <th>Actions</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php if (empty($ongoingAuctions)): ?>
                                      <tr>
                                          <td colspan="8" class="text-center">
                                              <div class="empty-state">
                                                  <i class="fas fa-hourglass-end empty-icon"></i>
                                                  <p>No ongoing auctions found</p>
                                              </div>
                                          </td>
                                      </tr>
                                  <?php else: ?>
                                      <?php foreach ($ongoingAuctions as $auction): ?>
                                          <tr data-auction-id="<?php echo $auction['id']; ?>">
                                              <td>
                                                  <img src="<?php echo !empty($auction['image_url']) ? '../' . $auction['image_url'] : '../assets/img/placeholder.jpg'; ?>" 
                                                       alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                                       class="img-thumbnail auction-thumbnail">
                                              </td>
                                              <td>
                                                  <a href="#" class="auction-title-link" data-auction-id="<?php echo $auction['id']; ?>">
                                                      <?php echo htmlspecialchars($auction['title']); ?>
                                                  </a>
                                                  <div class="small text-muted">ID: <?php echo $auction['id']; ?></div>
                                              </td>
                                              <td class="current-bid">
                                                  <?php 
                                                      $highestBid = !empty($auction['highest_bid']) ? $auction['highest_bid'] : $auction['current_price'];
                                                      echo '<span class="bid-amount">$' . number_format($highestBid, 2) . '</span>'; 
                                                  ?>
                                              </td>
                                              <td class="bid-count">
                                                  <span class="badge bg-info"><?php echo number_format($auction['bid_count']); ?></span>
                                              </td>
                                              <td class="highest-bidder">
                                                  <?php 
                                                      $bidderName = trim($auction['highest_bidder_name'] ?? '');
                                                      if (!empty($bidderName)) {
                                                          echo '<span class="bidder-name">' . htmlspecialchars($bidderName) . '</span>';
                                                      } elseif (!empty($auction['highest_bidder_email'])) {
                                                          echo '<span class="bidder-email">' . htmlspecialchars($auction['highest_bidder_email']) . '</span>';
                                                      } else {
                                                          echo '<span class="no-bids">No bids yet</span>';
                                                      }
                                                  ?>
                                              </td>
                                              <td class="time-left" data-end-time="<?php echo $auction['end_date']; ?>">
                                                  <?php 
                                                      if (!empty($auction['end_date'])) {
                                                          $endTime = new DateTime($auction['end_date']);
                                                          $now = new DateTime();
                                                          $interval = $now->diff($endTime);
                                                          
                                                          if ($endTime < $now) {
                                                              echo '<span class="text-danger">Ended</span>';
                                                          } else {
                                                              $days = $interval->format('%a');
                                                              $hours = $interval->format('%h');
                                                              $minutes = $interval->format('%i');
                                                              
                                                              if ($days > 0) {
                                                                  echo '<div class="countdown"><i class="fas fa-clock me-1"></i>' . $days . 'd ' . $hours . 'h ' . $minutes . 'm</div>';
                                                              } else if ($hours > 0) {
                                                                  echo '<div class="countdown"><i class="fas fa-clock me-1"></i>' . $hours . 'h ' . $minutes . 'm</div>';
                                                              } else {
                                                                  echo '<div class="countdown urgent"><i class="fas fa-clock me-1"></i>' . $minutes . 'm ' . $interval->format('%s') . 's</div>';
                                                              }
                                                          }
                                                      } else {
                                                          echo '<span class="text-muted">No end date</span>';
                                                      }
                                                  ?>
                                              </td>
                                              <td class="min-increment">
                                                  <div class="input-group input-group-sm">
                                                      <span class="input-group-text">$</span>
                                                      <input type="number" class="form-control min-increment-input" 
                                                             value="<?php echo number_format($auction['min_bid_increment'] ?? 1, 2, '.', ''); ?>" 
                                                             min="0.01" step="0.01" 
                                                             data-auction-id="<?php echo $auction['id']; ?>">
                                                      <button class="btn btn-outline-primary save-increment" 
                                                              data-auction-id="<?php echo $auction['id']; ?>">
                                                          <i class="fas fa-save"></i>
                                                      </button>
                                                  </div>
                                              </td>
                                              <td>
                                                  <div class="btn-group">
                                                      <button type="button" class="btn btn-sm btn-info view-bids" 
                                                              data-auction-id="<?php echo $auction['id']; ?>"
                                                              data-auction-title="<?php echo htmlspecialchars($auction['title']); ?>"
                                                              title="View Bid History">
                                                          <i class="fas fa-history"></i>
                                                      </button>
                                                      <button type="button" class="btn btn-sm btn-danger close-auction" 
                                                              data-auction-id="<?php echo $auction['id']; ?>"
                                                              data-auction-title="<?php echo htmlspecialchars($auction['title']); ?>"
                                                              title="Close Auction">
                                                          <i class="fas fa-gavel"></i>
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

              <!-- Completed Auctions Section -->
              <div class="card mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center">
                      <h5 class="mb-0"><i class="fas fa-history me-2"></i>Completed Auctions with Bids</h5>
                      <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#completedAuctionsCollapse">
                          <i class="fas fa-chevron-down"></i> Toggle View
                      </button>
                  </div>
                  <div class="collapse" id="completedAuctionsCollapse">
                      <div class="card-body">
                          <div class="table-responsive">
                              <table class="table table-striped" id="completed-auctions-table">
                                  <thead>
                                      <tr>
                                          <th>ID</th>
                                          <th>Title</th>
                                          <th>Status</th>
                                          <th>Final Bid</th>
                                          <th>Winner</th>
                                          <th>Bids</th>
                                          <th>End Date</th>
                                          <th>Actions</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                      <?php if (empty($completedAuctions)): ?>
                                          <tr>
                                              <td colspan="8" class="text-center">
                                                  <div class="empty-state">
                                                      <i class="fas fa-box-open empty-icon"></i>
                                                      <p>No completed auctions with bids found</p>
                                                  </div>
                                              </td>
                                          </tr>
                                      <?php else: ?>
                                          <?php foreach ($completedAuctions as $auction): ?>
                                              <tr>
                                                  <td><?php echo $auction['id']; ?></td>
                                                  <td>
                                                      <a href="#" class="auction-title-link" data-auction-id="<?php echo $auction['id']; ?>">
                                                          <?php echo htmlspecialchars($auction['title']); ?>
                                                      </a>
                                                  </td>
                                                  <td>
                                                      <?php 
                                                          $statusClass = '';
                                                          switch($auction['status']) {
                                                              case 'ended':
                                                                  $statusClass = 'bg-secondary';
                                                                  break;
                                                              case 'completed':
                                                                  $statusClass = 'bg-success';
                                                                  break;
                                                              case 'cancelled':
                                                                  $statusClass = 'bg-danger';
                                                                  break;
                                                              default:
                                                                  $statusClass = 'bg-info';
                                                          }
                                                      ?>
                                                      <span class="badge <?php echo $statusClass; ?>">
                                                          <?php echo ucfirst($auction['status']); ?>
                                                      </span>
                                                  </td>
                                                  <td>$<?php echo number_format($auction['highest_bid'] ?? $auction['current_price'], 2); ?></td>
                                                  <td>
                                                      <?php 
                                                          $bidderName = trim($auction['highest_bidder_name'] ?? '');
                                                          if (!empty($bidderName)) {
                                                              echo htmlspecialchars($bidderName);
                                                          } elseif (!empty($auction['highest_bidder_email'])) {
                                                              echo htmlspecialchars($auction['highest_bidder_email']);
                                                          } else {
                                                              echo '<span class="text-muted">No winner</span>';
                                                          }
                                                      ?>
                                                  </td>
                                                  <td><?php echo number_format($auction['bid_count']); ?></td>
                                                  <td><?php echo date('M d, Y H:i', strtotime($auction['end_date'])); ?></td>
                                                  <td>
                                                      <button type="button" class="btn btn-sm btn-info view-bids" 
                                                              data-auction-id="<?php echo $auction['id']; ?>"
                                                              data-auction-title="<?php echo htmlspecialchars($auction['title']); ?>"
                                                              title="View Bid History">
                                                          <i class="fas fa-history"></i>
                                                      </button>
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

              <!-- Recent Bids Section -->
              <div class="card">
                  <div class="card-header d-flex justify-content-between align-items-center">
                      <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Recent Bids</h5>
                      <button class="btn btn-sm btn-outline-primary" id="refresh-recent-bids">
                          <i class="fas fa-sync-alt"></i> Refresh
                      </button>
                  </div>
                  <div class="card-body">
                      <div class="table-responsive">
                          <table class="table table-hover" id="recent-bids-table">
                              <thead>
                                  <tr>
                                      <th>Auction</th>
                                      <th>Bidder</th>
                                      <th>Amount</th>
                                      <th>Time</th>
                                      <th>Actions</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php if (empty($recentBids)): ?>
                                      <tr>
                                          <td colspan="5" class="text-center">
                                              <div class="empty-state">
                                                  <i class="fas fa-chart-bar empty-icon"></i>
                                                  <p>No recent bids found</p>
                                              </div>
                                          </td>
                                      </tr>
                                  <?php else: ?>
                                      <?php foreach ($recentBids as $bid): ?>
                                          <tr data-bid-id="<?php echo $bid['id']; ?>">
                                              <td>
                                                  <a href="#" class="auction-title-link" data-auction-id="<?php echo $bid['auction_id']; ?>">
                                                      <?php echo htmlspecialchars($bid['auction_title']); ?>
                                                  </a>
                                              </td>
                                              <td>
                                                  <?php 
                                                      $bidderName = trim($bid['bidder_first_name'] . ' ' . $bid['bidder_last_name']);
                                                      echo !empty($bidderName) ? htmlspecialchars($bidderName) : htmlspecialchars($bid['bidder_email']); 
                                                  ?>
                                              </td>
                                              <td><span class="bid-amount">$<?php echo number_format($bid['bid_amount'], 2); ?></span></td>
                                              <td><?php echo date('M d, Y H:i:s', strtotime($bid['created_at'])); ?></td>
                                              <td>
                                                  <button type="button" class="btn btn-sm btn-info view-auction-bids" 
                                                          data-auction-id="<?php echo $bid['auction_id']; ?>"
                                                          data-auction-title="<?php echo htmlspecialchars($bid['auction_title']); ?>"
                                                          title="View All Bids">
                                                      <i class="fas fa-list"></i>
                                                  </button>
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
                      <div class="d-flex justify-content-between align-items-center mb-3">
                          <h6 id="auction-title-display" class="mb-0"></h6>
                          <div class="bid-stats">
                              <span class="badge bg-primary me-2">Total Bids: <span id="total-bids-count">0</span></span>
                              <span class="badge bg-success">Highest Bid: $<span id="highest-bid-amount">0.00</span></span>
                          </div>
                      </div>
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

  <!-- Close Auction Modal -->
  <div class="modal fade" id="closeAuctionModal" tabindex="-1" aria-labelledby="closeAuctionModalLabel" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="closeAuctionModalLabel">Close Auction</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                  <div id="close-auction-loading" class="text-center" style="display: none;">
                      <div class="spinner-border text-primary" role="status">
                          <span class="visually-hidden">Loading...</span>
                      </div>
                      <p class="mt-2">Loading auction details...</p>
                  </div>
                  <div id="close-auction-content">
                      <div class="alert alert-warning">
                          <i class="fas fa-exclamation-triangle me-2"></i>
                          <strong>Warning:</strong> You are about to manually close the auction: <strong id="close-auction-title"></strong>
                          <p class="mb-0 mt-1">This action cannot be undone.</p>
                      </div>
                      
                      <div id="winner-info" class="card mb-3">
                          <div class="card-header bg-success text-white">
                              <i class="fas fa-trophy me-2"></i>Winner Information
                          </div>
                          <div class="card-body">
                              <div id="no-winner-message" style="display: none;">
                                  <p class="text-center mb-0">There are no bids on this auction yet. Closing it will mark it as ended without a winner.</p>
                              </div>
                              <div id="winner-details" style="display: none;">
                                  <div class="mb-2">
                                      <strong>Name:</strong> <span id="winner-name" class="ms-2"></span>
                                  </div>
                                  <div class="mb-2">
                                      <strong>Email:</strong> <span id="winner-email" class="ms-2"></span>
                                  </div>
                                  <div>
                                      <strong>Winning Bid:</strong> <span class="ms-2 text-success fw-bold">$<span id="winning-bid"></span></span>
                                  </div>
                              </div>
                          </div>
                      </div>
                      
                      <div class="form-check mb-3">
                          <input class="form-check-input" type="checkbox" id="notify-participants" checked>
                          <label class="form-check-label" for="notify-participants">
                              <i class="fas fa-envelope me-1"></i> Notify all participants about auction closure
                          </label>
                      </div>
                  </div>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-danger" id="confirm-close-auction">
                      <i class="fas fa-gavel me-1"></i> Close Auction
                  </button>
              </div>
          </div>
      </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="../assets/js/admin-bids.js"></script>
</body>
</html>
