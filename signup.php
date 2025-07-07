<?php
require_once 'includes/functions.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Auction Platform</title>
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
                                <div class="login-pill">SIGN UP</div>
                                <p class="text-white mt-3">CREATE ACCOUNT</p>
                                <p class="text-white mt-3">Already have an account?</p>
                                <a href="login.php" class="btn btn-outline-light mt-2">Sign In</a>
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
                                    <h2 class="login-title">SIGN UP</h2>
                                </div>
                                
                                <div id="signup-alert" class="alert d-none" role="alert"></div>
                                
                                <form id="signup-form">
                                    <input type="hidden" name="action" value="signup">
                                    
                                    <div class="input-group mb-4">
                                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                                    </div>
                                    <div class="invalid-feedback" id="email-error"></div>
                                    
                                    <div class="input-group mb-4">
                                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                    </div>
                                    <div class="invalid-feedback" id="password-error"></div>
                                    <small class="form-text text-muted mb-3 d-block">Password must be at least 8 characters and contain both letters and numbers.</small>
                                    
                                    <div class="input-group mb-4">
                                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                    </div>
                                    <div class="invalid-feedback" id="confirm-password-error"></div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label">Register as:</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="role" id="role-buyer" value="buyer" checked>
                                            <label class="form-check-label" for="role-buyer">
                                                Buyer
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="role" id="role-seller" value="seller">
                                            <label class="form-check-label" for="role-seller">
                                                Seller
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-login w-100">SIGN UP</button>
                                    </div>
                                </form>
                                
                                <div class="or-divider">Or Sign Up with</div>

                                <!-- Add this notification about the approval process -->
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> All new accounts require administrator approval before access is granted. You will be notified once your account has been reviewed.
                                </div>
                                
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
            // Signup form submission
            $("#signup-form").submit(function(e) {
                e.preventDefault();
                
                // Reset previous errors
                $(".is-invalid").removeClass("is-invalid");
                $("#signup-alert").addClass("d-none");
                
                // Get form data
                const formData = $(this).serialize();
                
                // Client-side validation
                let isValid = true;
                const email = $("#email").val().trim();
                const password = $("#password").val();
                const confirmPassword = $("#confirm_password").val();
                
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
                } else if (!isValidPassword(password)) {
                    $("#password").addClass("is-invalid");
                    $("#password-error").text("Password must be at least 8 characters and contain both letters and numbers");
                    isValid = false;
                }
                
                if (!confirmPassword) {
                    $("#confirm_password").addClass("is-invalid");
                    $("#confirm-password-error").text("Please confirm your password");
                    isValid = false;
                } else if (password !== confirmPassword) {
                    $("#confirm_password").addClass("is-invalid");
                    $("#confirm-password-error").text("Passwords do not match");
                    isValid = false;
                }
                
                if (!isValid) {
                    return;
                }
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.text();
                submitBtn.prop("disabled", true).text("Signing up...");
                
                // Submit form via AJAX
                $.ajax({
                    url: "api/auth.php",
                    type: "POST",
                    data: formData,
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            $("#signup-alert").removeClass("d-none alert-danger").addClass("alert-success").text(response.message);
                            
                            // Clear form
                            $("#signup-form")[0].reset();
                            
                            // Redirect to waiting page
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 2000);
                        } else {
                            // Show error message
                            $("#signup-alert").removeClass("d-none alert-success").addClass("alert-danger").text(response.message);
                            submitBtn.prop("disabled", false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        $("#signup-alert")
                            .removeClass("d-none alert-success")
                            .addClass("alert-danger")
                            .text("An error occurred. Please try again later.");
                        submitBtn.prop("disabled", false).text(originalText);
                    }
                });
            });
            
            // Helper functions
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            function isValidPassword(password) {
                // At least 8 characters, contains letters and numbers
                return password.length >= 8 && /[A-Za-z]/.test(password) && /[0-9]/.test(password);
            }
        });
    </script>
</body>
</html>
