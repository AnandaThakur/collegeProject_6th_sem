<?php
require_once '../includes/functions.php';
require_once '../config/database.php';
require_once '../includes/wallet-functions.php';

// Check if user is logged in
startSession();
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

// Validate request data
if (!isset($data['token']) || !isset($data['amount'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$token = $data['token'];
$amount = $data['amount'] / 100; // Convert from paisa to rupees

// Get Khalti secret key from settings
$khaltiSecretKey = getSystemSetting('khalti_secret_key');
if (empty($khaltiSecretKey)) {
    $khaltiSecretKey = 'test_secret_key_dc74e0fd57cb46cd93832aee0a390234'; // Test key
}

// Verify payment with Khalti
$url = "https://khalti.com/api/v2/payment/verify/";
$payload = [
    'token' => $token,
    'amount' => $data['amount'] // Amount in paisa
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Key ' . $khaltiSecretKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response_data = json_decode($response, true);

// Log the response for debugging
error_log('Khalti verification response: ' . $response);

// Check if verification was successful
if ($status_code == 200 && isset($response_data['idx'])) {
    // Create a pending transaction in our database
    $transactionId = generateTransactionId();
    $description = "Wallet fund via Khalti";
    $referenceId = $response_data['idx'];
    
    $conn = getDbConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into wallet_transactions
        $stmt = $conn->prepare("INSERT INTO wallet_transactions 
                              (transaction_id, user_id, type, amount, status, description, reference_id, created_at) 
                              VALUES (?, ?, 'deposit', ?, 'pending', ?, ?, NOW())");
        $stmt->bind_param("sidss", $transactionId, $userId, $amount, $description, $referenceId);
        $stmt->execute();
        
        // Create notification for admin
        $adminNotification = "New wallet fund request of Rs " . number_format($amount, 2) . " from user #" . $userId;
        createAdminNotification("Wallet Fund Request", $adminNotification, "wallet_fund", $userId);
        
        // Create notification for user
        $userNotification = "Your wallet fund request of Rs " . number_format($amount, 2) . " is pending admin verification";
        createNotification($userId, "Wallet Fund Request", $userNotification, "info", "system");
        
        // Commit transaction
        $conn->commit();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Payment verification successful. Funds will be added to your wallet after admin verification.',
            'transaction_id' => $transactionId
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        error_log('Error creating wallet transaction: ' . $e->getMessage());
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()]);
    }
} else {
    // Payment verification failed
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Payment verification failed. Please try again or contact support.',
        'response' => $response_data
    ]);
}

/**
 * Create notification for admin
 */
function createAdminNotification($title, $message, $type, $relatedId = null) {
    $conn = getDbConnection();
    
    // Get all admin users
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($admin = $result->fetch_assoc()) {
        createNotification($admin['id'], $title, $message, 'info', $type, $relatedId);
    }
}
?>
