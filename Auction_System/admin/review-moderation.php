<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$conn = getDbConnection();

// Log admin action
if (function_exists('logAdminAction')) {
    logAdminAction($_SESSION['user_id'], 'Accessed review moderation page');
}

$page_title = "Review Moderation";
include '../includes/admin-header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 bg-dark min-vh-100">
            <?php include '../includes/admin-sidebar.php'; ?>
        </div>
        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-star me-2"></i> Review Moderation</h2>
                <div>
                    <button class="btn btn-outline-secondary" id="refreshReviewsBtn">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i> Review/comment moderation. Approve or delete product reviews.
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary filter-reviews active" data-status="all">All</button>
                            <button type="button" class="btn btn-outline-secondary filter-reviews" data-status="pending">Pending</button>
                            <button type="button" class="btn btn-outline-secondary filter-reviews" data-status="approved">Approved</button>
                            <button type="button" class="btn btn-outline-secondary filter-reviews" data-status="deleted">Deleted</button>
                        </div>
                    </div>
                    
                    <div class="table-responsive" id="reviewsTableContainer">
                        <!-- Reviews will be loaded here via AJAX -->
                        <div class="text-center p-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading reviews...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast for notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="fas fa-bell me-2"></i>
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <small id="toastTime">just now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage">
            Action completed successfully.
        </div>
    </div>
</div>

<script src="../assets/js/review-moderation.js"></script>

<?php include '../includes/admin-footer.php'; ?>
