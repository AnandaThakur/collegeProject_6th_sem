<?php
// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/wallet-functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Check if wallet tables exist, if not create them
if (!tableExists('wallet_balances') || !tableExists('wallet_transactions') || !tableExists('payment_settings')) {
    require_once '../database/wallet_tables.php';
}

// Function to check if table exists
function tableExists($tableName) {
    $conn = getDbConnection();
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Get payment gateways
$gateways = getPaymentGateways();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_gateway'])) {
    $gatewayId = (int)$_POST['gateway_id'];
    $data = [
        'api_key' => $_POST['api_key'] ?? '',
        'secret_key' => $_POST['secret_key'] ?? '',
        'mode' => $_POST['mode'] ?? 'sandbox',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'settings_json' => isset($_POST['settings_json']) ? $_POST['settings_json'] : null
    ];
    
    $result = updatePaymentGateway($gatewayId, $data);
    
    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
        
        // Refresh gateways list
        $gateways = getPaymentGateways();
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Settings - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .gateway-card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
        }
        .gateway-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .gateway-logo {
            height: 50px;
            width: auto;
            margin-bottom: 15px;
        }
        .gateway-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e3e6f0;
        }
        .gateway-body {
            padding: 20px;
        }
        .gateway-footer {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid #e3e6f0;
        }
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
        }
        .api-key-field {
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <a href="dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                        <span class="fs-4">Admin Panel</span>
                    </a>
                    <hr>
                    <?php require_once '../includes/admin-sidebar.php'; ?>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Payment Gateway Settings</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Payment Gateway Configuration</h6>
                            </div>
                            <div class="card-body">
                                <p>Configure payment gateways to enable wallet deposits and withdrawals. Make sure to enter valid API credentials and test thoroughly before enabling in live mode.</p>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($gateways as $gateway): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card shadow gateway-card">
                            <div class="gateway-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($gateway['gateway_name']); ?></h5>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="gateway-active-<?php echo $gateway['id']; ?>" <?php echo $gateway['is_active'] ? 'checked' : ''; ?> onchange="toggleGatewayStatus(<?php echo $gateway['id']; ?>, this.checked)">
                                        <label class="form-check-label" for="gateway-active-<?php echo $gateway['id']; ?>">
                                            <?php echo $gateway['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-<?php echo $gateway['mode'] === 'live' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($gateway['mode']); ?> Mode
                                    </span>
                                </div>
                            </div>
                            <div class="gateway-body">
                                <form method="POST" action="" id="gateway-form-<?php echo $gateway['id']; ?>">
                                    <input type="hidden" name="update_gateway" value="1">
                                    <input type="hidden" name="gateway_id" value="<?php echo $gateway['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="api-key-<?php echo $gateway['id']; ?>" class="form-label">API Key</label>
                                        <input type="text" class="form-control api-key-field" id="api-key-<?php echo $gateway['id']; ?>" name="api_key" value="<?php echo htmlspecialchars($gateway['api_key'] ?? ''); ?>" placeholder="Enter API Key">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="secret-key-<?php echo $gateway['id']; ?>" class="form-label">Secret Key</label>
                                        <input type="password" class="form-control api-key-field" id="secret-key-<?php echo $gateway['id']; ?>" name="secret_key" value="<?php echo htmlspecialchars($gateway['secret_key'] ?? ''); ?>" placeholder="Enter Secret Key">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="show-secret-<?php echo $gateway['id']; ?>" onchange="toggleSecretVisibility(<?php echo $gateway['id']; ?>)">
                                            <label class="form-check-label" for="show-secret-<?php echo $gateway['id']; ?>">
                                                Show Secret Key
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Mode</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="mode" id="mode-sandbox-<?php echo $gateway['id']; ?>" value="sandbox" <?php echo $gateway['mode'] === 'sandbox' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mode-sandbox-<?php echo $gateway['id']; ?>">
                                                Sandbox (Testing)
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="mode" id="mode-live-<?php echo $gateway['id']; ?>" value="live" <?php echo $gateway['mode'] === 'live' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mode-live-<?php echo $gateway['id']; ?>">
                                                Live (Production)
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="is-active-<?php echo $gateway['id']; ?>" class="form-label">Status</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is-active-<?php echo $gateway['id']; ?>" name="is_active" value="1" <?php echo $gateway['is_active'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is-active-<?php echo $gateway['id']; ?>">
                                                Enable this payment gateway
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <?php if ($gateway['gateway_name'] === 'PayPal'): ?>
                                    <div class="mb-3">
                                        <label for="settings-json-<?php echo $gateway['id']; ?>" class="form-label">Additional Settings</label>
                                        <textarea class="form-control" id="settings-json-<?php echo $gateway['id']; ?>" name="settings_json" rows="3" placeholder="Enter additional settings in JSON format"><?php echo htmlspecialchars($gateway['settings_json'] ?? ''); ?></textarea>
                                        <small class="form-text text-muted">Example: {"client_id":"your_client_id","webhook_id":"your_webhook_id"}</small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Settings
                                        </button>
                                        <button type="button" class="btn btn-outline-info" onclick="testGatewayConnection(<?php echo $gateway['id']; ?>)">
                                            <i class="fas fa-vial"></i> Test Connection
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="gateway-footer">
                                <small class="text-muted">Last updated: <?php echo date('M d, Y H:i', strtotime($gateway['updated_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Documentation -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Payment Gateway Documentation</h6>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="gatewayDocs">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingPayPal">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePayPal" aria-expanded="false" aria-controls="collapsePayPal">
                                        PayPal Integration Guide
                                    </button>
                                </h2>
                                <div id="collapsePayPal" class="accordion-collapse collapse" aria-labelledby="headingPayPal" data-bs-parent="#gateway  class="accordion-collapse collapse" aria-labelledby="headingPayPal" data-bs-parent="#gatewayDocs">
                                    <div class="accordion-body">
                                        <h5>Setting Up PayPal Integration</h5>
                                        <ol>
                                            <li>Go to <a href="https://developer.paypal.com/" target="_blank">PayPal Developer</a> and create a developer account if you don't have one.</li>
                                            <li>Navigate to the Dashboard and create a new app.</li>
                                            <li>Copy the Client ID and Secret Key from your app.</li>
                                            <li>Paste these credentials in the form above.</li>
                                            <li>For sandbox testing, use the sandbox credentials.</li>
                                            <li>For production, switch to live credentials when ready.</li>
                                        </ol>
                                        <p>For webhook setup and additional configuration, refer to the <a href="https://developer.paypal.com/docs/" target="_blank">PayPal Developer Documentation</a>.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingStripe">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStripe" aria-expanded="false" aria-controls="collapseStripe">
                                        Stripe Integration Guide
                                    </button>
                                </h2>
                                <div id="collapseStripe" class="accordion-collapse collapse" aria-labelledby="headingStripe" data-bs-parent="#gatewayDocs">
                                    <div class="accordion-body">
                                        <h5>Setting Up Stripe Integration</h5>
                                        <ol>
                                            <li>Go to <a href="https://dashboard.stripe.com/register" target="_blank">Stripe Dashboard</a> and create an account if you don't have one.</li>
                                            <li>Navigate to Developers > API Keys.</li>
                                            <li>Copy the Publishable Key (API Key) and Secret Key.</li>
                                            <li>Paste these credentials in the form above.</li>
                                            <li>For testing, use the test mode credentials.</li>
                                            <li>For production, switch to live mode when ready.</li>
                                        </ol>
                                        <p>For webhook setup and additional configuration, refer to the <a href="https://stripe.com/docs" target="_blank">Stripe Documentation</a>.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingBank">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBank" aria-expanded="false" aria-controls="collapseBank">
                                        Bank Transfer Setup Guide
                                    </button>
                                </h2>
                                <div id="collapseBank" class="accordion-collapse collapse" aria-labelledby="headingBank" data-bs-parent="#gatewayDocs">
                                    <div class="accordion-body">
                                        <h5>Setting Up Bank Transfer</h5>
                                        <p>Bank transfers are processed manually. You need to provide your bank account details to users and verify their payments manually.</p>
                                        <ol>
                                            <li>Enable the Bank Transfer gateway.</li>
                                            <li>In the Additional Settings field, add your bank account details in JSON format.</li>
                                            <li>Example: <code>{"account_name":"Company Name","account_number":"1234567890","bank_name":"Bank Name","swift_code":"ABCDEFGH"}</code></li>
                                            <li>When users select bank transfer, they will see these details.</li>
                                            <li>Users will need to upload proof of payment.</li>
                                            <li>Admin will need to verify the payment manually and approve the deposit.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Connection Modal -->
    <div class="modal fade" id="testConnectionModal" tabindex="-1" aria-labelledby="testConnectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="testConnectionModalLabel">Testing Gateway Connection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="testConnectionSpinner" class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Testing connection to payment gateway...</p>
                    </div>
                    <div id="testConnectionResult" class="d-none">
                        <div id="testConnectionIcon" class="text-center mb-3">
                            <i class="fas fa-check-circle fa-4x text-success"></i>
                        </div>
                        <div id="testConnectionMessage" class="alert alert-success">
                            Connection successful!
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JavaScript -->
    <script>
        // Toggle secret key visibility
        function toggleSecretVisibility(gatewayId) {
            const secretInput = document.getElementById(`secret-key-${gatewayId}`);
            if (secretInput.type === "password") {
                secretInput.type = "text";
            } else {
                secretInput.type = "password";
            }
        }
        
        // Toggle gateway status
        function toggleGatewayStatus(gatewayId, isActive) {
            const statusLabel = document.querySelector(`label[for="gateway-active-${gatewayId}"]`);
            statusLabel.textContent = isActive ? 'Active' : 'Inactive';
            
            // Also update the form checkbox
            document.getElementById(`is-active-${gatewayId}`).checked = isActive;
        }
        
        // Test gateway connection
        function testGatewayConnection(gatewayId) {
            // Get form data
            const form = document.getElementById(`gateway-form-${gatewayId}`);
            const formData = new FormData(form);
            formData.append('action', 'test_connection');
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('testConnectionModal'));
            modal.show();
            
            // Reset modal content
            document.getElementById('testConnectionSpinner').classList.remove('d-none');
            document.getElementById('testConnectionResult').classList.add('d-none');
            
            // Send AJAX request
            $.ajax({
                url: '../api/wallet-actions.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Hide spinner
                    document.getElementById('testConnectionSpinner').classList.add('d-none');
                    document.getElementById('testConnectionResult').classList.remove('d-none');
                    
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (result.success) {
                            // Show success message
                            document.getElementById('testConnectionIcon').innerHTML = '<i class="fas fa-check-circle fa-4x text-success"></i>';
                            document.getElementById('testConnectionMessage').className = 'alert alert-success';
                            document.getElementById('testConnectionMessage').textContent = result.message || 'Connection successful!';
                        } else {
                            // Show error message
                            document.getElementById('testConnectionIcon').innerHTML = '<i class="fas fa-times-circle fa-4x text-danger"></i>';
                            document.getElementById('testConnectionMessage').className = 'alert alert-danger';
                            document.getElementById('testConnectionMessage').textContent = result.message || 'Connection failed!';
                        }
                    } catch (e) {
                        // Show error message
                        document.getElementById('testConnectionIcon').innerHTML = '<i class="fas fa-exclamation-triangle fa-4x text-warning"></i>';
                        document.getElementById('testConnectionMessage').className = 'alert alert-warning';
                        document.getElementById('testConnectionMessage').textContent = 'Invalid response from server. Please check your credentials.';
                    }
                },
                error: function() {
                    // Hide spinner
                    document.getElementById('testConnectionSpinner').classList.add('d-none');
                    document.getElementById('testConnectionResult').classList.remove('d-none');
                    
                    // Show error message
                    document.getElementById('testConnectionIcon').innerHTML = '<i class="fas fa-times-circle fa-4x text-danger"></i>';
                    document.getElementById('testConnectionMessage').className = 'alert alert-danger';
                    document.getElementById('testConnectionMessage').textContent = 'Connection failed! Could not connect to the server.';
                }
            });
        }
    </script>
</body>
</html>
