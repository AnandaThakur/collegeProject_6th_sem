<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
session_start();

// Include required files
require_once '../config/database.php';

// Check if functions.php exists and include it
if (file_exists('../includes/functions.php')) {
    require_once '../includes/functions.php';
} else {
    die("Error: includes/functions.php file not found");
}

// Define isAdmin function if it doesn't exist
if (!function_exists('isAdmin')) {
    function isAdmin($user_id = null) {
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Create database connection
try {
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Get auction list for filter dropdown
$auctions = [];
try {
    $auctionQuery = "SELECT id, title FROM auctions ORDER BY title ASC";
    $auctionResult = $conn->query($auctionQuery);
    
    if ($auctionResult) {
        while ($row = $auctionResult->fetch_assoc()) {
            $auctions[] = $row;
        }
    }
} catch (Exception $e) {
    // Just log the error but continue - this is not critical
    error_log("Error fetching auctions: " . $e->getMessage());
}

// Page title for header
$page_title = "Chat & Reviews Monitoring";

// Include header
include_once '../includes/admin-header.php';
?>

<!-- Bootstrap Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<!-- Custom CSS for chat monitoring -->
<link rel="stylesheet" href="../assets/css/chat-monitoring.css">

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Menu -->
        <?php 
        if (file_exists('../includes/admin-sidebar.php')) {
            include_once '../includes/admin-sidebar.php';
        } else {
            echo '<div class="col-md-2 bg-dark min-vh-100">
                    <div class="d-flex flex-column align-items-center align-items-sm-start px-3 pt-2 text-white">
                        <span class="fs-5 d-none d-sm-inline">Menu</span>
                        <ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start">
                            <li class="nav-item">
                                <a href="dashboard.php" class="nav-link align-middle px-0">
                                    <i class="fs-4 bi-speedometer2"></i> <span class="ms-1 d-none d-sm-inline">Dashboard</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>';
        }
        ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Chat & Reviews Monitoring</h1>
            </div>
            
            <!-- Tabs for navigation -->
            <ul class="nav nav-tabs" id="monitoringTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="chat-tab" data-bs-toggle="tab" data-bs-target="#chat" type="button" role="tab" aria-controls="chat" aria-selected="true">
                        Live Auction Chat
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" aria-controls="reviews" aria-selected="false">
                        Review Moderation
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="flagged-words-tab" data-bs-toggle="tab" data-bs-target="#flagged-words" type="button" role="tab" aria-controls="flagged-words" aria-selected="false">
                        Flagged Words
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="monitoringTabsContent">
                <!-- Chat Monitoring Tab -->
                <div class="tab-pane fade show active" id="chat" role="tabpanel" aria-labelledby="chat-tab">
                    <div class="row my-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Live Auction Chat Monitoring</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Filters -->
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <select id="auctionFilter" class="form-select">
                                                <option value="">All Auctions</option>
                                                <?php foreach ($auctions as $auction): ?>
                                                <option value="<?php echo $auction['id']; ?>"><?php echo htmlspecialchars($auction['title']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select id="statusFilter" class="form-select">
                                                <option value="">All Status</option>
                                                <option value="active">Active</option>
                                                <option value="deleted">Deleted</option>
                                                <option value="flagged">Flagged</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="input-group">
                                                <input type="text" id="searchChat" class="form-control" placeholder="Search messages...">
                                                <button class="btn btn-outline-secondary" id="searchChatBtn">
                                                    <i class="bi bi-search"></i> Search
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button id="refreshChat" class="btn btn-primary">
                                                <i class="bi bi-arrow-clockwise"></i> Refresh
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Messages Table -->
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Auction</th>
                                                    <th>Message</th>
                                                    <th>Time</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="chatMessages">
                                                <tr>
                                                    <td colspan="6" class="text-center">Loading messages...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <nav aria-label="Chat messages pagination">
                                        <ul class="pagination justify-content-center" id="chatPagination">
                                            <!-- Pagination will be loaded here via AJAX -->
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Review Moderation Tab -->
                <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                    <div class="row my-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Product Reviews Moderation</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Filters -->
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <select id="productFilter" class="form-select">
                                                <option value="">All Products</option>
                                                <?php foreach ($auctions as $auction): ?>
                                                <option value="<?php echo $auction['id']; ?>"><?php echo htmlspecialchars($auction['title']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select id="reviewStatusFilter" class="form-select">
                                                <option value="">All Status</option>
                                                <option value="pending">Pending</option>
                                                <option value="approved">Approved</option>
                                                <option value="deleted">Deleted</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="input-group">
                                                <input type="text" id="searchReviews" class="form-control" placeholder="Search reviews...">
                                                <button class="btn btn-outline-secondary" id="searchReviewsBtn">
                                                    <i class="bi bi-search"></i> Search
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button id="refreshReviews" class="btn btn-primary">
                                                <i class="bi bi-arrow-clockwise"></i> Refresh
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Reviews Table -->
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>User</th>
                                                    <th>Review</th>
                                                    <th>Rating</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="reviewsList">
                                                <tr>
                                                    <td colspan="7" class="text-center">Loading reviews...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <nav aria-label="Reviews pagination">
                                        <ul class="pagination justify-content-center" id="reviewsPagination">
                                            <!-- Pagination will be loaded here via AJAX -->
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Flagged Words Tab -->
                <div class="tab-pane fade" id="flagged-words" role="tabpanel" aria-labelledby="flagged-words-tab">
                    <div class="row my-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Flagged Words Management</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-7">
                                            <form id="addFlaggedWordForm" class="row g-3">
                                                <div class="col-md-6">
                                                    <input type="text" class="form-control" id="newFlaggedWord" placeholder="Enter word to flag">
                                                </div>
                                                <div class="col-md-4">
                                                    <select class="form-select" id="wordSeverity">
                                                        <option value="low">Low</option>
                                                        <option value="medium" selected>Medium</option>
                                                        <option value="high">High</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="submit" class="btn btn-success w-100">Add</button>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="input-group">
                                                <input type="text" id="searchWords" class="form-control" placeholder="Search flagged words...">
                                                <button class="btn btn-outline-secondary" id="searchWordsBtn">
                                                    <i class="bi bi-search"></i> Search
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Flagged Words Table -->
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Word</th>
                                                    <th>Severity</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="flaggedWordsList">
                                                <tr>
                                                    <td colspan="3" class="text-center">Loading flagged words...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <nav aria-label="Flagged words pagination">
                                        <ul class="pagination justify-content-center" id="wordsPagination">
                                            <!-- Pagination will be loaded here via AJAX -->
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Toast Container for Notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage"></div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmActionModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmActionModalBody">
                Are you sure you want to perform this action?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Make sure jQuery is loaded first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Then load Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Add JavaScript file -->
<script>
// Basic JavaScript to handle the page until the full script loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Chat monitoring page loaded');
    
    // Check if Bootstrap is available
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap JavaScript is not loaded. Adding it manually...');
        
        // Try to add Bootstrap JS dynamically
        const bootstrapScript = document.createElement('script');
        bootstrapScript.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js';
        bootstrapScript.integrity = 'sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p';
        bootstrapScript.crossOrigin = 'anonymous';
        document.body.appendChild(bootstrapScript);
        
        bootstrapScript.onload = function() {
            console.log('Bootstrap loaded dynamically');
            initializeComponents();
        };
    } else {
        initializeComponents();
    }
    
    function initializeComponents() {
        // Initialize Bootstrap components
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize tabs
        var tabTriggerList = [].slice.call(document.querySelectorAll('#monitoringTabs button'));
        tabTriggerList.forEach(function(tabTriggerEl) {
            new bootstrap.Tab(tabTriggerEl);
        });
    }
    
    // Show a message in the chat messages table
    const chatMessagesElement = document.getElementById('chatMessages');
    if (chatMessagesElement) {
        chatMessagesElement.innerHTML = '<tr><td colspan="6" class="text-center">Loading chat messages...</td></tr>';
    }
    
    // Show a message in the reviews list
    const reviewsListElement = document.getElementById('reviewsList');
    if (reviewsListElement) {
        reviewsListElement.innerHTML = '<tr><td colspan="7" class="text-center">Loading reviews...</td></tr>';
    }
    
    // Show a message in the flagged words list
    const flaggedWordsListElement = document.getElementById('flaggedWordsList');
    if (flaggedWordsListElement) {
        flaggedWordsListElement.innerHTML = '<tr><td colspan="3" class="text-center">Loading flagged words...</td></tr>';
    }
});
</script>

<!-- Try to load the main JavaScript file -->
<script src="../assets/js/chat-monitoring.js"></script>

<?php 
// Include footer if it exists
if (file_exists('../includes/admin-footer.php')) {
    include_once '../includes/admin-footer.php';
} else {
    echo '</body></html>';
}
?>
