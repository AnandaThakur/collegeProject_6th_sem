<?php
// Enable error reporting for debugging
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

// Get user information
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];
$userRole = $_SESSION['role'];

// Initialize database connection
$conn = getDbConnection();

// Check if wallet tables exist
$result = $conn->query("SHOW TABLES LIKE 'wallet_balances'");
if ($result->num_rows == 0) {
    // Create wallet tables
    $conn->query("CREATE TABLE IF NOT EXISTS wallet_balances (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $conn->query("CREATE TABLE IF NOT EXISTS wallet_transactions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50) NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        type ENUM('deposit', 'withdrawal', 'bid', 'win', 'refund', 'deduct') NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'completed', 'failed', 'cancelled', 'reversed') NOT NULL DEFAULT 'pending',
        description TEXT,
        admin_id INT UNSIGNED,
        reference_id VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (transaction_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Get wallet balance
function getWalletBalance($userId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT balance FROM wallet_balances WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['balance'];
    } else {
        // Create wallet balance record if it doesn't exist
        $stmt = $conn->prepare("INSERT INTO wallet_balances (user_id, balance) VALUES (?, 0.00)");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        return 0.00;
    }
}

// Get recent transactions
function getRecentTransactions($userId, $limit = 5) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM wallet_transactions 
                          WHERE user_id = ? 
                          ORDER BY created_at DESC 
                          LIMIT ?");
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    return $transactions;
}

// Get pending transactions
function getPendingTransactions($userId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM wallet_transactions 
                          WHERE user_id = ? AND status = 'pending' 
                          ORDER BY created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    return $transactions;
}

// Cancel transaction
function cancelTransaction($transactionId, $userId) {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if transaction exists and belongs to the user
        $stmt = $conn->prepare("SELECT * FROM wallet_transactions 
                              WHERE transaction_id = ? AND user_id = ? AND status = 'pending'");
        $stmt->bind_param("si", $transactionId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            throw new Exception("Transaction not found or cannot be cancelled");
        }
        
        // Update transaction status
        $stmt = $conn->prepare("UPDATE wallet_transactions 
                              SET status = 'cancelled', updated_at = NOW() 
                              WHERE transaction_id = ?");
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        
        // Create notification for user if function exists
        if (function_exists('createNotification')) {
            $userNotification = "Your transaction #" . $transactionId . " has been cancelled";
            createNotification($userId, "Transaction Cancelled", $userNotification, "info", "system");
        }
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Transaction cancelled successfully'
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Get system setting
function getSystemSetting($key) {
    global $conn;
    
    // Check if system_settings table exists
    $result = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($result->num_rows == 0) {
        // Create system_settings table
        $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            setting_type ENUM('text', 'textarea', 'dropdown', 'toggle', 'number') NOT NULL DEFAULT 'text',
            setting_options TEXT,
            setting_description TEXT,
            is_public TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    
    return '';
}

// Create notification function if it doesn't exist
if (!function_exists('createNotification')) {
    function createNotification($userId, $title, $message, $type = 'info', $source = 'system') {
        global $conn;
        
        // Check if notifications table exists
        $result = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($result->num_rows == 0) {
            // Create notifications table
            $conn->query("CREATE TABLE IF NOT EXISTS notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'info',
                source VARCHAR(50) NOT NULL DEFAULT 'system',
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, source) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $title, $message, $type, $source);
        return $stmt->execute();
    }
}

// Get wallet balance
$walletBalance = getWalletBalance($userId);

// Get recent transactions
$transactions = getRecentTransactions($userId, 10);

// Get pending transactions
$pendingTransactions = getPendingTransactions($userId);

// Handle transaction cancellation
if (isset($_POST['cancel_transaction']) && isset($_POST['transaction_id'])) {
    $transactionId = htmlspecialchars(trim($_POST['transaction_id']));
    $result = cancelTransaction($transactionId, $userId);
    
    if ($result['success']) {
        $successMessage = "Transaction cancelled successfully.";
    } else {
        $errorMessage = $result['message'];
    }
}

// Get Khalti API key from settings
$khaltiPublicKey = getSystemSetting('khalti_public_key');
if (empty($khaltiPublicKey)) {
    $khaltiPublicKey = 'test_public_key_dc74e0fd57cb46cd93832aee0a390234'; // Test key
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wallet - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding-bottom: 70px; /* Space for the fixed navbar */
            background-color: #f8f9fa;
        }
        .wallet-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .wallet-balance {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .balance-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
        .wallet-actions {
            display: flex;
            justify-content: space-around;
            padding: 15px;
            background-color: white;
        }
        .action-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #333;
            text-decoration: none;
            padding: 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .action-button:hover {
            background-color: #f0f0f0;
            transform: translateY(-3px);
        }
        .action-icon {
            font-size: 1.8rem;
            margin-bottom: 8px;
            color: #6a11cb;
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
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #6c757d;
        }
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #adb5bd;
        }
        .khalti-button {
            background-color: #5D2E8E;
            color: white;
            border: none;
        }
        .khalti-button:hover {
            background-color: #4a2470;
            color: white;
        }
        .modal-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
        }
        .modal-title {
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <!-- Wallet Balance Card -->
        <div class="wallet-card">
            <div class="wallet-balance">
                <h5 class="mb-1">Wallet Balance</h5>
                <div class="balance-amount">Rs <?php echo number_format($walletBalance, 2); ?></div>
                <p class="mb-0">Available for bidding and purchases</p>
            </div>
            <div class="wallet-actions">
                <a href="#" class="action-button" data-bs-toggle="modal" data-bs-target="#loadFundsModal">
                    <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                    <span>Load Funds</span>
                </a>
                <a href="#" class="action-button" onclick="alert('Transaction history feature coming soon!')">
                    <div class="action-icon"><i class="fas fa-history"></i></div>
                    <span>History</span>
                </a>
                <a href="#" class="action-button" data-bs-toggle="modal" data-bs-target="#helpModal">
                    <div class="action-icon"><i class="fas fa-question-circle"></i></div>
                    <span>Help</span>
                </a>
            </div>
        </div>

        <?php if (isset($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $successMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $errorMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Pending Transactions -->
        <?php if (!empty($pendingTransactions)): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Transactions</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($pendingTransactions as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-icon bg-warning text-white">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="transaction-details">
                                <div class="transaction-title">
                                    <?php echo htmlspecialchars($transaction['description'] ?? 'Pending Transaction'); ?>
                                    <span class="pending-badge">Pending</span>
                                </div>
                                <div class="transaction-date"><?php echo formatDate($transaction['created_at']); ?></div>
                            </div>
                            <div class="d-flex flex-column align-items-end">
                                <div class="transaction-amount amount-positive">Rs <?php echo number_format($transaction['amount'], 2); ?></div>
                                <form method="post" class="mt-2">
                                    <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                    <button type="submit" name="cancel_transaction" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <div class="section-title">
                    <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Recent Transactions</h5>
                    <a href="transaction-history.php" class="btn btn-sm btn-outline-primary" >View All</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($transactions)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-receipt"></i></div>
                        <h5>No transactions yet</h5>
                        <p>Your transaction history will appear here</p>
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
                                        <?php echo htmlspecialchars($transaction['description'] ?? ucfirst($transaction['type']) . ' Transaction'); ?>
                                        <?php if ($transaction['status'] === 'pending'): ?>
                                            <span class="pending-badge">Pending</span>
                                        <?php elseif ($transaction['status'] === 'completed'): ?>
                                            <span class="completed-badge">Completed</span>
                                        <?php elseif ($transaction['status'] === 'failed'): ?>
                                            <span class="failed-badge">Failed</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="transaction-date"><?php echo formatDate($transaction['created_at']); ?></div>
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
    </div>
    
    <!-- Load Funds Modal -->
    <div class="modal fade" id="loadFundsModal" tabindex="-1" aria-labelledby="loadFundsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loadFundsModalLabel">Load Funds to Wallet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="loadFundsForm">
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount (Rs)</label>
                            <input type="number" class="form-control" id="amount" min="100" step="1" required placeholder="Enter amount (minimum Rs 100)">
                            <div class="form-text">Minimum amount: Rs 100</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="khaltiMethod" value="khalti" checked>
                                    <label class="form-check-label d-flex align-items-center" for="khaltiMethod">
                                        <span class="me-2" style="color: #5D2E8E; font-weight: bold;">Khalti</span>
                                        Khalti Digital Wallet
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="bankMethod" value="bank" disabled>
                                    <label class="form-check-label d-flex align-items-center" for="bankMethod">
                                        <i class="fas fa-university me-2" style="font-size: 1.5rem;"></i>
                                        Bank Transfer (Coming Soon)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Funds will be added to your wallet after admin verification.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn khalti-button" id="payment-button">
                        <i class="fas fa-wallet me-2"></i> Pay with Khalti
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="helpModalLabel">Wallet Help</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="accordion" id="walletHelpAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    How to load funds?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#walletHelpAccordion">
                                <div class="accordion-body">
                                    <p>To load funds into your wallet:</p>
                                    <ol>
                                        <li>Click on the "Load Funds" button</li>
                                        <li>Enter the amount you wish to add (minimum Rs 100)</li>
                                        <li>Select your preferred payment method</li>
                                        <li>Complete the payment process</li>
                                        <li>Wait for admin verification (usually within 24 hours)</li>
                                    </ol>
                                    <p>Once verified, the funds will be available in your wallet.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    How are funds used for bidding?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#walletHelpAccordion">
                                <div class="accordion-body">
                                    <p>When you place a bid on an auction:</p>
                                    <ul>
                                        <li>The bid amount is reserved from your wallet balance</li>
                                        <li>If you're outbid, the amount is returned to your available balance</li>
                                        <li>If you win the auction, the amount is transferred to the seller</li>
                                    </ul>
                                    <p>You must have sufficient funds in your wallet to place a bid.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    What are pending transactions?
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#walletHelpAccordion">
                                <div class="accordion-body">
                                    <p>Pending transactions are operations that have been initiated but not yet completed:</p>
                                    <ul>
                                        <li>Fund loading requests awaiting admin verification</li>
                                        <li>Bid amounts that are currently reserved</li>
                                        <li>Withdrawals that are being processed</li>
                                    </ul>
                                    <p>You can cancel pending fund loading requests before they are approved by the admin.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFour">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    Need more help?
                                </button>
                            </h2>
                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#walletHelpAccordion">
                                <div class="accordion-body">
                                    <p>If you have any questions or issues with your wallet:</p>
                                    <ul>
                                        <li>Contact our support team at support@auction-platform.com</li>
                                        <li>Visit our Help Center for more information</li>
                                        <li>Reach out to an admin through the contact form</li>
                                    </ul>
                                    <p>We're here to help you with any wallet-related concerns.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it</button>
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
    <script>
        // Khalti Payment Integration - Simplified for testing
        document.getElementById("payment-button").addEventListener("click", function() {
            var amount = document.getElementById('amount').value;
            
            // Validate amount
            if (!amount || amount < 100) {
                alert('Please enter a valid amount (minimum Rs 100)');
                return;
            }
            
            // For now, just show a message
            alert('Khalti integration is in test mode. In production, this would open the Khalti payment gateway.');
            
            // Simulate a successful payment for testing
            setTimeout(function() {
                alert('Payment successful! Funds will be added to your wallet after admin verification.');
                
                // Create a test transaction in the database
                fetch('../api/wallet-actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'create_pending_deposit',
                        user_id: <?php echo $userId; ?>,
                        amount: amount,
                        payment_method: 'khalti',
                        description: 'Khalti deposit (test)'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }, 1000);
        });
    </script>
</body>
</html>
