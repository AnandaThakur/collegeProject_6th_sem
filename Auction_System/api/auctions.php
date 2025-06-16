<?php
// Prevent any output before intended JSON response
ob_start();

// Enable error reporting for logging but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set content type to JSON before any output
header('Content-Type: application/json');

try {
    // Log access to this file for debugging
    error_log("API accessed: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown URI') . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown IP'));

    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Include required files
    if (!file_exists('../includes/functions.php') || !file_exists('../config/database.php')) {
        throw new Exception("Required files not found");
    }
    
    require_once '../includes/functions.php';
    require_once '../config/database.php';

    // Log the request for debugging
    $requestData = $_POST;
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'Unknown';
    error_log("Auction API Request: Method=$requestMethod, Data=" . json_encode($requestData));

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        error_log("User not logged in when accessing auction API");
        ob_clean();
        // Return error response
        $response = [
            'status' => 'error',
            'message' => 'You must be logged in to access this feature'
        ];
    
        echo json_encode($response);
        exit;
    }

    // Modified admin check to be more lenient for debugging
    $isAdmin = false;
    if (isset($_SESSION['role'])) {
        $isAdmin = $_SESSION['role'] === 'admin';
        error_log("User role check: " . $_SESSION['role'] . ", isAdmin: " . ($isAdmin ? "true" : "false"));
    } else {
        error_log("No role found in session for user: " . ($_SESSION['email'] ?? 'Unknown'));
    }

    // Only enforce admin check for admin-specific actions
    $adminRequiredActions = [
        'approve_auction', 'reject_auction', 'pause_auction', 
        'resume_auction', 'stop_auction', 'update_dates'
    ];

    $action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
    
    if (in_array($action, $adminRequiredActions) && !$isAdmin) {
        error_log("Non-admin user attempted to access restricted auction API action: " . ($_SESSION['email'] ?? 'Unknown user'));
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Admin privileges required for this action'
        ]);
        exit;
    }

    // Clean any buffered output to prevent JSON corruption
    ob_clean();

    // Get database connection
    $conn = getDbConnection();
    
    // Check database connection
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    error_log("Database connection successful");

    // Handle different actions
    error_log("Auction API Action: $action");

    switch ($action) {
        case 'approve':
        case 'approve_auction':
            // Approve auction
            $auctionId = isset($_POST['auction_id']) ? intval($_POST['auction_id']) : 0;
            
            if ($auctionId <= 0) {
                throw new Exception("Invalid auction ID");
            }
            
            // Update auction status
            $sql = "UPDATE auctions SET status = 'approved' WHERE id = $auctionId";
            if ($conn->query($sql)) {
                $response = [
                    'status' => 'success',
                    'message' => 'Auction approved successfully'
                ];
            } else {
                throw new Exception("Failed to approve auction: " . $conn->error);
            }
            break;
            
        case 'reject':
        case 'reject_auction':
            // Reject auction
            $auctionId = isset($_POST['auction_id']) ? intval($_POST['auction_id']) : 0;
            $reason = isset($_POST['reason']) ? $conn->real_escape_string($_POST['reason']) : '';
            
            if ($auctionId <= 0) {
                throw new Exception("Invalid auction ID");
            }
            
            // Update auction status
            $sql = "UPDATE auctions SET status = 'rejected', rejection_reason = '$reason' WHERE id = $auctionId";
            if ($conn->query($sql)) {
                $response = [
                    'status' => 'success',
                    'message' => 'Auction rejected successfully'
                ];
            } else {
                throw new Exception("Failed to reject auction: " . $conn->error);
            }
            break;
            
        case 'get_pending':
            // Get pending auctions
            $sql = "SELECT a.*, u.email as seller_email, u.first_name, u.last_name 
                    FROM auctions a 
                    JOIN users u ON a.seller_id = u.id 
                    WHERE a.status = 'pending' 
                    ORDER BY a.created_at DESC";
            
            $result = $conn->query($sql);
            
            if (!$result) {
                throw new Exception("Failed to get pending auctions: " . $conn->error);
            }
            
            $auctions = [];
            while ($row = $result->fetch_assoc()) {
                $auctions[] = $row;
            }
            
            $response = [
                'status' => 'success',
                'auctions' => $auctions
            ];
            break;
            
        case 'get_all':
            // Get all auctions
            $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
            
            $sql = "SELECT a.*, u.email as seller_email, u.first_name, u.last_name 
                    FROM auctions a 
                    JOIN users u ON a.seller_id = u.id ";
            
            if (!empty($status)) {
                $sql .= "WHERE a.status = '$status' ";
            }
            
            $sql .= "ORDER BY a.created_at DESC";
            
            $result = $conn->query($sql);
            
            if (!$result) {
                throw new Exception("Failed to get auctions: " . $conn->error);
            }
            
            $auctions = [];
            while ($row = $result->fetch_assoc()) {
                $auctions[] = $row;
            }
            
            $response = [
                'status' => 'success',
                'auctions' => $auctions
            ];
            break;
            
        case 'get_auction':
        case 'get_auction_details':
            // Get auction details
            $auctionId = isset($_GET['auction_id']) ? intval($_GET['auction_id']) : (isset($_POST['auction_id']) ? intval($_POST['auction_id']) : 0);
            
            if ($auctionId <= 0) {
                throw new Exception("Invalid auction ID");
            }
            
            $sql = "SELECT a.*, u.email as seller_email, u.first_name, u.last_name 
                    FROM auctions a 
                    JOIN users u ON a.seller_id = u.id 
                    WHERE a.id = $auctionId";
            
            $result = $conn->query($sql);
            
            if (!$result) {
                throw new Exception("Failed to get auction details: " . $conn->error);
            }
            
            if ($result->num_rows > 0) {
                $auction = $result->fetch_assoc();
                
                // Get bids for this auction
                $sql = "SELECT b.*, u.email, u.first_name, u.last_name 
                        FROM bids b 
                        JOIN users u ON b.user_id = u.id 
                        WHERE b.auction_id = $auctionId 
                        ORDER BY b.bid_amount DESC";
                
                $result = $conn->query($sql);
                
                if (!$result) {
                    throw new Exception("Failed to get bids: " . $conn->error);
                }
                
                $bids = [];
                while ($row = $result->fetch_assoc()) {
                    $bids[] = $row;
                }
                
                $auction['bids'] = $bids;
                
                $response = [
                    'status' => 'success',
                    'auction' => $auction
                ];
            } else {
                throw new Exception("Auction not found");
            }
            break;
            
        case 'pause_auction':
            // Pause auction
            $auctionId = isset($_POST['auction_id']) ? intval($_POST['auction_id']) : 0;
            
            if ($auctionId <= 0) {
                throw new Exception("Invalid auction ID");
            }
            
            $sql = "UPDATE auctions SET status = 'paused' WHERE id = $auctionId";
            if ($conn->query($sql)) {
                $response = [
                    'status' => 'success',
                    'message' => 'Auction paused successfully'
                ];
            } else {
                throw new Exception("Failed to pause auction: " . $conn->error);
            }
            break;
            
        case 'resume_auction':
            // Resume auction
            $auctionId = isset($_POST['auction_id']) ? intval($_POST['auction_id']) : 0;
            
            if ($auctionId <= 0) {
                throw new Exception("Invalid auction ID");
            }
            
            $sql = "UPDATE auctions SET status = 'ongoing' WHERE id = $auctionId";
            if ($conn->query($sql)) {
                $response = [
                    'status' => 'success',
                    'message' => 'Auction resumed successfully'
                ];
            } else {
                throw new Exception("Failed to resume auction: " . $conn->error);
            }
            break;
            
        case 'stop_auction':
            // Stop auction
            $auctionId = isset($_POST['auction_id']) ? intval($_POST['auction_id']) : 0;
            
            if ($auctionId <= 0) {
                throw new Exception("Invalid auction ID");
            }
            
            $sql = "UPDATE auctions SET status = 'ended', end_date = NOW() WHERE id = $auctionId";
            if ($conn->query($sql)) {
                $response = [
                    'status' => 'success',
                    'message' => 'Auction stopped successfully'
                ];
            } else {
                throw new Exception("Failed to stop auction: " . $conn->error);
            }
            break;
            
        case 'update_dates':
            // Update auction dates
            $auctionId = isset($_POST['auction_id']) ? intval($_POST['auction_id']) : 0;
            $startDate = isset($_POST['start_date']) ? $conn->real_escape_string($_POST['start_date']) : '';
            $endDate = isset($_POST['end_date']) ? $conn->real_escape_string($_POST['end_date']) : '';
            
            if ($auctionId <= 0) {
                throw new Exception("Invalid auction ID");
            }
            
            $sql = "UPDATE auctions SET ";
            $updates = [];
            
            if (!empty($startDate)) {
                $updates[] = "start_date = '$startDate'";
            }
            
            if (!empty($endDate)) {
                $updates[] = "end_date = '$endDate'";
            }
            
            if (empty($updates)) {
                throw new Exception("No dates provided for update");
            }
            
            $sql .= implode(", ", $updates) . " WHERE id = $auctionId";
            
            if ($conn->query($sql)) {
                $response = [
                    'status' => 'success',
                    'message' => 'Auction dates updated successfully'
                ];
            } else {
                throw new Exception("Failed to update auction dates: " . $conn->error);
            }
            break;
            
        case 'get_bids':
            // Get bids for an auction
            $auctionId = isset($_POST['auction_id']) ? intval($_POST['auction_id']) : 0;
            
            if ($auctionId <= 0) {
                throw new Exception("Invalid auction ID");
            }
            
            $sql = "SELECT b.*, u.email, u.first_name, u.last_name 
                    FROM bids b 
                    JOIN users u ON b.user_id = u.id 
                    WHERE b.auction_id = $auctionId 
                    ORDER BY b.bid_amount DESC";
            
            $result = $conn->query($sql);
            
            if (!$result) {
                throw new Exception("Failed to get bids: " . $conn->error);
            }
            
            $bids = [];
            while ($row = $result->fetch_assoc()) {
                $bids[] = $row;
            }
            
            $response = [
                'status' => 'success',
                'bids' => $bids
            ];
            break;
            
        case 'test_connection':
            // Simple test endpoint to verify API connectivity
            $response = [
                'status' => 'success',
                'message' => 'API connection successful',
                'data' => [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'user' => $_SESSION['email'] ?? 'Unknown',
                    'role' => $_SESSION['role'] ?? 'Unknown',
                    'session_id' => session_id(),
                    'is_admin' => $isAdmin
                ]
            ];
            break;
            
        default:
            error_log("Invalid action requested: $action");
            throw new Exception("Invalid action");
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Return error response
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
    
    echo json_encode($response);
}

// End output buffering and flush
ob_end_flush();
?>
