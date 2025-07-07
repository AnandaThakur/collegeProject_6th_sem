<?php
require_once 'includes/functions.php';

// Debug session information if requested
if (isset($_GET['session_debug']) && $_GET['session_debug'] === 'true') {
    startSession();
    echo "<pre>";
    echo "Session Information:\n";
    print_r($_SESSION);
    echo "\nPHP Session Status: " . session_status() . "\n";
    echo "Session ID: " . session_id() . "\n";
    echo "</pre>";
    exit;
}

// Check and fix database structure
require_once 'config/database.php';
checkAndFixDatabaseStructure();

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else if (isVerified()) {
        redirect('customer/dashboard.php');
    } else {
        redirect('waiting.php');
    }
}

// Check if it's admin login
$isAdminLogin = isset($_GET['admin']) && $_GET['admin'] === 'true';

// Add debug information for troubleshooting
if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
    echo "<pre>";
    echo "PHP Version: " . phpversion() . "\n";
    
    echo "Session Status: " . session_status() . "\n";
    
    $conn = getDbConnection();
    $result = $conn->query("SELECT id, email, role, is_verified, status, password FROM users WHERE role = 'admin'");
    echo "Admin Users:\n";
    while ($row = $result->fetch_assoc()) {
        // Don't show the full password hash for security
        $row['password'] = substr($row['password'], 0, 10) . '...';
        print_r($row);
    }
    echo "</pre>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isAdminLogin ? 'Admin Login' : 'Customer Login'; ?> - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card login-card">
                    <div class="row g-0">
                        <div class="col-md-5 login-bg">
                            <div class="login-sidebar-content">
                                <div class="login-pill"><?php echo $isAdminLogin ? 'ADMIN LOGIN' : 'LOGIN'; ?></div>
                                <p class="text-white mt-3">SIGN IN</p>
                                <?php if (!$isAdminLogin): ?>
                                <p class="text-white mt-3">Don't have an account?</p>
                                <a href="signup.php" class="btn btn-outline-light mt-2">Sign Up</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="card-body p-5">
                                <div class="text-center mb-4">
                                    <div class="logo-container">
                                        <svg class="logo-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M30 30 L70 30 L70 70 L30 70 Z" stroke="#FF6B6B" stroke-width="6" fill="none" />
                                            <path d="M20 20 L80 20 L80 80 L20 80 Z" stroke="#FF6B6B" stroke-width="6" fill="none" transform="rotate(45 50 50)" />
                                        </svg>
                                    </div>
                                    <h2 class="login-title"><?php echo $isAdminLogin ? 'ADMIN LOGIN' : 'LOGIN'; ?></h2>
                                </div>
                                
                                <div id="login-alert" class="alert d-none" role="alert"></div>
                                
                                <?php if ($isAdminLogin): ?>
                                <div class="alert alert-info">
                                    <strong>Admin Login</strong><br>
                                    Email: admin@auction.com<br>
                                    Password: admin123
                                </div>
                                <?php endif; ?>
                                
                                <form id="login-form">
                                    <input type="hidden" name="action" value="login">
                                    
                                    <div class="input-group mb-4">
                                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" required <?php echo $isAdminLogin ? 'value="admin@auction.com"' : ''; ?>>
                                    </div>
                                    <div class="invalid-feedback" id="email-error"></div>
                                    
                                    <div class="input-group mb-4">
                                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required <?php echo $isAdminLogin ? 'value="admin123"' : ''; ?>>
                                    </div>
                                    <div class="invalid-feedback" id="password-error"></div>
                                    
                                    <div class="mb-4 clearfix">
                                        <a href="#" class="forgot-password">Forgot Password?</a>
                                        <button type="submit" class="btn btn-login">LOGIN</button>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <a href="login.php?session_debug=true" class="text-muted small">Session Debug</a>
                                    </div>
                                </form>
                                
                                <?php if (!$isAdminLogin): ?>
                                <div class="or-divider">Or Login with</div>
                                
                                <div class="social-login">
                                    <a href="#" class="btn">
                                        <svg class="social-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12.24 10.285V14.4h6.806c-.275 1.765-2.056 5.174-6.806 5.174-4.095 0-7.439-3.389-7.439-7.574s3.345-7.574 7.439-7.574c2.33 0 3.891.989 4.785 1.849l3.254-3.138C18.189 1.186 15.479 0 12.24 0c-6.635 0-12 5.365-12 12s5.365 12 12 12c6.926 0 11.52-4.869 11.52-11.726 0-.788-.085-1.39-.189-1.989H12.24z" fill="#4285F4"/>
                                        </svg>
                                        Google
                                    </a>
                                    <a href="#" class="btn">
                                        <svg class="social-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M23.9981 11.9991C23.9981 5.37216 18.626 0 11.9991 0C5.37216 0 0 5.37216 0 11.9991C0 17.9882 4.38789 22.9522 10.1242 23.8524V15.4676H7.07758V11.9991H10.1242V9.35553C10.1242 6.34826 11.9156 4.68714 14.6564 4.68714C15.9692 4.68714 17.3424 4.92149 17.3424 4.92149V7.87439H15.8294C14.3388 7.87439 13.8739 8.79933 13.8739 9.74824V11.9991H17.2018L16.6698 15.4676H13.8739V23.8524C19.6103 22.9522 23.9981 17.9882 23.9981 11.9991Z" fill="#1877F2"/>
                                        </svg>
                                        Facebook
                                    </a>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-4 text-center">
                                    <?php if ($isAdminLogin): ?>
                                        <a href="login.php" class="text-muted">Customer Login</a>
                                    <?php else: ?>
                                        <a href="login.php?admin=true" class="text-muted">Admin Login</a>
                                    <?php endif; ?>
                                
                                </div>
                                
                                <?php if ($isAdminLogin): ?>
                                <div class="mt-3 text-center">
                                    <a href="login.php?debug=true" class="text-muted small">Debug Info</a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Login form submission
            $("#login-form").submit(function(e) {
                e.preventDefault();
                
                // Reset previous errors
                $(".is-invalid").removeClass("is-invalid");
                $("#login-alert").addClass("d-none");
                
                // Get form data
                const formData = $(this).serialize();
                
                // Client-side validation
                let isValid = true;
                const email = $("#email").val().trim();
                const password = $("#password").val();
                
                if (!email) {
                    $("#email").addClass("is-invalid");
                    $("#email-error").text("Email is required");
                    isValid = false;
                } else if (!isValidEmail(email)) {
                    $("#email").addClass("is-invalid");
                    $("#email-error").text("Invalid email format");
                    isValid = false;
                }
                
                if (!password) {
                    $("#password").addClass("is-invalid");
                    $("#password-error").text("Password is required");
                    isValid = false;
                }
                
                if (!isValid) {
                    return;
                }
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.text();
                submitBtn.prop("disabled", true).text("Logging in...");
                
                // Submit form via AJAX
                $.ajax({
                    url: "api/auth.php",
                    type: "POST",
                    data: formData,
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            $("#login-alert").removeClass("d-none alert-danger").addClass("alert-success").text(response.message);
                            
                            // Redirect after a short delay
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1000);
                        } else {
                            // Show error message
                            $("#login-alert").removeClass("d-none alert-success").addClass("alert-danger").text(response.message);
                            submitBtn.prop("disabled", false).text(originalText);
                            
                            // Handle redirect for pending users
                            if (response.data && response.data.redirect && response.data.status === "pending") {
                                setTimeout(function() {
                                    window.location.href = response.data.redirect;
                                }, 2000);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        let errorMessage = "An error occurred. Please try again later.";
                        
                        // Try to get more detailed error information
                        if (xhr.responseText) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.message) {
                                    errorMessage = response.message;
                                }
                            } catch (e) {
                                // If we can't parse the JSON, use the raw response text
                                if (xhr.responseText.length < 100) {
                                    errorMessage = xhr.responseText;
                                }
                            }
                        }
                        
                        $("#login-alert").removeClass("d-none alert-success").addClass("alert-danger").text(errorMessage);
                        submitBtn.prop("disabled", false).text(originalText);
                    }
                });
            });
            
            // Helper functions
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
        });
    </script>
</body>
</html>
