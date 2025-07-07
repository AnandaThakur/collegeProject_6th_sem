<?php
require_once '../includes/functions.php';
require_once '../config/database.php';
require_once '../includes/wallet-functions.php';

// Check if user is logged in
startSession();
if (!isLoggedIn()) {
    redirect('../login.php');
}

// Check if user is admin (redirect to admin dashboard)
if (isAdmin()) {
    error_log("Redirecting admin user from customer dashboard to admin dashboard");
    redirect('../admin/dashboard.php');
}

// Check if user is verified
if (!isVerified()) {
    error_log("Redirecting unverified user to waiting page");
    redirect('../waiting.php');
}

// Get user information
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];
$userRole = $_SESSION['role'];

// Get user's name from database
$conn = getDbConnection();
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

$fullName = trim("$firstName $lastName");
if (empty($fullName) || $fullName == "User") {
    $fullName = $userEmail;
}

// Get wallet balance
$walletBalance = getWalletBalance($userId);

// Get recent activity
$recentActivity = getRecentActivity($userId);

error_log("User accessing customer dashboard - ID: $userId, Email: $userEmail, Role: $userRole, Name: $fullName");

// Function to get recent activity
function getRecentActivity($userId) {
    $conn = getDbConnection();
    
    // Get recent bids
    $stmt = $conn->prepare("SELECT b.*, a.title FROM bids b 
                          JOIN auctions a ON b.auction_id = a.id 
                          WHERE b.user_id = ? 
                          ORDER BY b.created_at DESC LIMIT 3");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $bidsResult = $stmt->get_result();
    
    $bids = [];
    while ($bid = $bidsResult->fetch_assoc()) {
        $bid['type'] = 'bid';
        $bids[] = $bid;
    }
    
    // Get recent wallet transactions
    $stmt = $conn->prepare("SELECT * FROM wallet_transactions 
                          WHERE user_id = ? 
                          ORDER BY created_at DESC LIMIT 3");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $transactionsResult = $stmt->get_result();
    
    $transactions = [];
    while ($transaction = $transactionsResult->fetch_assoc()) {
        $transaction['type'] = 'transaction';
        $transactions[] = $transaction;
    }
    
    // Combine and sort by date
    $activity = array_merge($bids, $transactions);
    usort($activity, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($activity, 0, 5);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding-bottom: 70px; /* Space for the fixed navbar */
            background-color: #f8f9fa;
        }
        .section-card {
            border-radius: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
            height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            margin-bottom: 20px;
        }
        .section-card:hover, .section-card:focus {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .buyer-card {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
        }
        .seller-card {
            background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 99%, #fad0c4 100%);
            color: white;
        }
        .wallet-card {
            background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%);
            color: white;
        }
        .card-icon {
            font-size: 3rem;
            margin-bottom: 15px;
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
        .welcome-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 25%;
            font-size: 0.6rem;
        }
        .balance-amount {
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="welcome-card">
            <h2 class="h4 mb-0">Welcome back,</h2>
            <h1 class="h3 mb-3 fw-bold"><?php echo htmlspecialchars($fullName); ?></h1>
            <div class="d-flex justify-content-between align-items-center">
                <p class="text-muted mb-0">What would you like to do today?</p>
                <div class="text-end">
                    <p class="text-muted mb-0">Wallet Balance</p>
                    <div class="balance-amount">Rs <?php echo number_format($walletBalance, 2); ?></div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <?php if ($userRole === 'buyer' || $userRole === 'admin'): ?>
            <div class="col-<?php echo ($userRole === 'admin') ? '6' : '12'; ?>">
                <a href="buyer-section.php" class="text-decoration-none">
                    <div class="section-card buyer-card">
                        <i class="fas fa-shopping-cart card-icon"></i>
                        <h3 class="h5">Buyer Section</h3>
                        <p class="small mb-0">Browse & bid on auctions</p>
                    </div>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($userRole === 'seller' || $userRole === 'admin'): ?>
            <div class="col-<?php echo ($userRole === 'admin') ? '6' : '12'; ?>">
                <a href="seller-section.php" class="text-decoration-none">
                    <div class="section-card seller-card">
                        <i class="fas fa-tag card-icon"></i>
                        <h3 class="h5">Seller Section</h3>
                        <p class="small mb-0">Manage your listings</p>
                    </div>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="col-12">
                <a href="wallet.php" class="text-decoration-none">
                    <div class="section-card wallet-card">
                        <i class="fas fa-wallet card-icon"></i>
                        <h3 class="h5">My Wallet</h3>
                        <p class="small mb-0">Manage your funds</p>
                    </div>
                </a>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recent Activity</h5>
                        <div class="list-group list-group-flush">
                            <?php
                            $hasActivity = false;
                            foreach ($recentActivity as $activity) {
                                $hasActivity = true;
                                $date = formatDate($activity['created_at'], 'M d, H:i');
                                
                                if ($activity['type'] === 'bid') {
                                    echo '<div class="list-group-item px-0">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">Bid on "' . htmlspecialchars($activity['title']) . '"</h6>
                                                <small>' . $date . '</small>
                                            </div>
                                            <p class="mb-1">Rs ' . number_format($activity['bid_amount'], 2) . '</p>
                                          </div>';
                                } else if ($activity['type'] === 'transaction') {
                                    $transactionType = ucfirst($activity['type']);
                                    $amountClass = $activity['amount'] >= 0 ? 'text-success' : 'text-danger';
                                    $amountPrefix = $activity['amount'] >= 0 ? '+' : '';
                                    
                                    echo '<div class="list-group-item px-0">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">' . htmlspecialchars($activity['description']) . '</h6>
                                                <small>' . $date . '</small>
                                            </div>
                                            <p class="mb-1 ' . $amountClass . '">' . $amountPrefix . 'Rs ' . number_format($activity['amount'], 2) . '</p>
                                          </div>';
                                }
                            }
                            
                            if (!$hasActivity) {
                                echo '<div class="list-group-item px-0">
                                        <p class="mb-0 text-muted">No recent activity found.</p>
                                      </div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bottom Navigation -->
    <nav class="fixed-bottom bottom-nav">
        <div class="row text-center">
            <div class="col-3">
                <a href="dashboard.php" class="nav-link active">
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
                <a href="my-bids.php" class="nav-link position-relative">
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
</html
