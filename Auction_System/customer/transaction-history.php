<?php
require_once '../includes/functions.php';
require_once '../config/database.php';
require_once '../includes/wallet-functions.php';

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
$userRole = $_SESSION['role'];

// Get wallet balance
$walletBalance = getWalletBalance($userId);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Get transactions with filters
$transactions = getTransactionHistory($userId, $limit, $offset, [
    'type' => $type,
    'status' => $status,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
]);

// Get total count for pagination
$totalTransactions = countTransactions($userId, [
    'type' => $type,
    'status' => $status,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
]);

$totalPages = ceil($totalTransactions / $limit);

// Get transaction types and statuses for filter dropdowns
$transactionTypes = ['deposit', 'withdrawal', 'bid', 'win', 'refund', 'deduct'];
$transactionStatuses = ['pending', 'completed', 'failed', 'reversed'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding-bottom: 70px; /* Space for the fixed navbar */
            background-color: #f8f9fa;
        }
        .wallet-summary {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .balance-amount {
            font-size: 2rem;
            font-weight: 700;
            margin: 5px 0;
        }
        .transaction-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }
        .transaction-item:hover {
            background-color: #f9f9f9;
        }
        .transaction-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .transaction-details {
            flex-grow: 1;
        }
        .transaction-title {
            font-weight: 600;
            margin-bottom: 3px;
        }
        .transaction-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .transaction-amount {
            font-weight: 700;
            font-size: 1.1rem;
            text-align: right;
        }
        .amount-positive {
            color: #28a745;
        }
        .amount-negative {
            color: #dc3545;
        }
        .pending-badge {
            background-color: #ffc107;
            color: #212529;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        .completed-badge {
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        .failed-badge {
            background-color: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        .reversed-badge {
            background-color: #6c757d;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        .filter-card {
            border-radius: 10px;
            margin-bottom: 20px;
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
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #adb5bd;
        }
        .page-link {
            color: #6a11cb;
        }
        .page-item.active .page-link {
            background-color: #6a11cb;
            border-color: #6a11cb;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Transaction History</h1>
            <a href="wallet.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i> Back to Wallet
            </a>
        </div>
        
        <!-- Wallet Summary -->
        <div class="wallet-summary">
            <h5 class="mb-1">Current Balance</h5>
            <div class="balance-amount">Rs <?php echo number_format($walletBalance, 2); ?></div>
            <div class="d-flex justify-content-between align-items-center">
                <p class="mb-0">Total Transactions: <?php echo $totalTransactions; ?></p>
                <a href="wallet.php" class="btn btn-light btn-sm">
                    <i class="fas fa-plus-circle me-1"></i> Load Funds
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card filter-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Transactions</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-6 col-lg-3">
                        <label for="type" class="form-label">Transaction Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All Types</option>
                            <?php foreach ($transactionTypes as $transactionType): ?>
                                <option value="<?php echo $transactionType; ?>" <?php echo $type === $transactionType ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($transactionType); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <?php foreach ($transactionStatuses as $transactionStatus): ?>
                                <option value="<?php echo $transactionStatus; ?>" <?php echo $status === $transactionStatus ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($transactionStatus); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <a href="transaction-history.php" class="btn btn-outline-secondary me-2">Reset</a>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Transactions List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Transactions</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($transactions)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-receipt"></i></div>
                        <h5>No transactions found</h5>
                        <p>Try adjusting your filters or check back later</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-item">
                                <?php
                                $iconClass = '';
                                $bgClass = '';
                                
                                switch ($transaction['type']) {
                                    case 'deposit':
                                        $iconClass = 'fa-arrow-down';
                                        $bgClass = 'bg-success';
                                        break;
                                    case 'withdrawal':
                                        $iconClass = 'fa-arrow-up';
                                        $bgClass = 'bg-danger';
                                        break;
                                    case 'bid':
                                        $iconClass = 'fa-gavel';
                                        $bgClass = 'bg-primary';
                                        break;
                                    case 'win':
                                        $iconClass = 'fa-trophy';
                                        $bgClass = 'bg-warning';
                                        break;
                                    case 'refund':
                                        $iconClass = 'fa-undo';
                                        $bgClass = 'bg-info';
                                        break;
                                    case 'deduct':
                                        $iconClass = 'fa-minus-circle';
                                        $bgClass = 'bg-danger';
                                        break;
                                    default:
                                        $iconClass = 'fa-exchange-alt';
                                        $bgClass = 'bg-secondary';
                                }
                                ?>
                                <div class="transaction-icon <?php echo $bgClass; ?> text-white">
                                    <i class="fas <?php echo $iconClass; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-title">
                                        <?php echo htmlspecialchars($transaction['description']); ?>
                                        <?php if ($transaction['status'] === 'pending'): ?>
                                            <span class="pending-badge">Pending</span>
                                        <?php elseif ($transaction['status'] === 'completed'): ?>
                                            <span class="completed-badge">Completed</span>
                                        <?php elseif ($transaction['status'] === 'failed'): ?>
                                            <span class="failed-badge">Failed</span>
                                        <?php elseif ($transaction['status'] === 'reversed'): ?>
                                            <span class="reversed-badge">Reversed</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="transaction-date">
                                        <?php echo formatDate($transaction['created_at']); ?>
                                        <?php if (!empty($transaction['reference_id'])): ?>
                                            <span class="ms-2 text-muted">Ref: <?php echo $transaction['reference_id']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="transaction-amount <?php echo $transaction['amount'] >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo ($transaction['amount'] >= 0 ? '+' : ''); ?>Rs <?php echo number_format($transaction['amount'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Transaction history pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&type=<?php echo $type; ?>&status=<?php echo $status; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo $type; ?>&status=<?php echo $status; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&type=<?php echo $type; ?>&status=<?php echo $status; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
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
                <a href="wallet.php" class="nav-link active">
                    <i class="fas fa-wallet nav-icon"></i>
                    <span class="nav-text small">Wallet</span>
                </a>
            </div>
            
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
