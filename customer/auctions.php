<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Start session and check if user is logged in
startSession();
if (!isLoggedIn()) {
    redirect('../login.php');
}

// Get database connection
$conn = getDbConnection();

// Get all categories for filter
$categories = getAllCategories();

// Set up filtering
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$searchQuery = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'end_date';
$sortOrder = isset($_GET['order']) ? sanitizeInput($_GET['order']) : 'ASC';

// Validate sort parameters
$allowedSortFields = ['title', 'start_price', 'current_price', 'start_date', 'end_date'];
$allowedSortOrders = ['ASC', 'DESC'];

if (!in_array($sortBy, $allowedSortFields)) {
    $sortBy = 'end_date';
}

if (!in_array(strtoupper($sortOrder), $allowedSortOrders)) {
    $sortOrder = 'ASC';
}

// Build query
$query = "SELECT a.*, c.name as category_name, 
          (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count
          FROM auctions a
          LEFT JOIN categories c ON a.category_id = c.id
          WHERE a.status = 'approved' OR a.status = 'ongoing'";

$params = [];
$types = "";

// Add category filter
if ($categoryFilter > 0) {
    $query .= " AND (a.category_id = ? OR a.category_id IN (SELECT id FROM categories WHERE parent_id = ?))";
    $params[] = $categoryFilter;
    $params[] = $categoryFilter;
    $types .= "ii";
}

// Add search filter
if (!empty($searchQuery)) {
    $query .= " AND (a.title LIKE ? OR a.description LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

// Add sorting
$query .= " ORDER BY a.$sortBy $sortOrder";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch auctions
$auctions = [];
while ($row = $result->fetch_assoc()) {
    // Get primary image for auction
    $primaryImage = getPrimaryAuctionImage($row['id']);
    $row['image_url'] = $primaryImage ? $primaryImage['image_url'] : 'assets/img/no-image.jpg';
    
    // Format dates
    $row['formatted_start_date'] = formatDate($row['start_date']);
    $row['formatted_end_date'] = formatDate($row['end_date']);
    
    // Calculate time remaining
    $endDate = new DateTime($row['end_date']);
    $now = new DateTime();
    $interval = $now->diff($endDate);
    
    if ($endDate < $now) {
        $row['time_remaining'] = 'Ended';
    } else if ($interval->days > 0) {
        $row['time_remaining'] = $interval->format('%d days, %h hours');
    } else {
        $row['time_remaining'] = $interval->format('%h hours, %i minutes');
    }
    
    $auctions[] = $row;
}

// Function to get reverse sort order
function getReverseSortOrder($currentField, $currentSortBy, $currentOrder) {
    if ($currentField === $currentSortBy) {
        return $currentOrder === 'ASC' ? 'DESC' : 'ASC';
    }
    return 'ASC';
}

$userId = $_SESSION['user']['id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Auctions | Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding-bottom: 70px; /* Space for fixed bottom navbar */
        }
        .auction-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .auction-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .auction-image {
            height: 200px;
            object-fit: cover;
        }
        .card-footer {
            background-color: rgba(0,0,0,0.03);
        }
        .badge-corner {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .filter-sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 300px;
            height: 100%;
            background-color: white;
            z-index: 1050;
            transition: left 0.3s;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .filter-sidebar.show {
            left: 0;
        }
        .filter-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1040;
            display: none;
        }
        .filter-backdrop.show {
            display: block;
        }
        .sort-icon {
            font-size: 0.7rem;
            vertical-align: middle;
        }
        .time-remaining {
            font-size: 0.8rem;
        }
        .current-bid {
            font-weight: bold;
        }
        .no-auctions {
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
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
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Browse Auctions</h1>
            <button class="btn btn-primary" id="filter-button">
                <i class="fas fa-filter"></i> Filter & Sort
            </button>
        </div>
        
        <!-- Search Bar -->
        <div class="mb-4">
            <form action="" method="GET" class="d-flex">
                <input type="hidden" name="category" value="<?php echo $categoryFilter; ?>">
                <input type="hidden" name="sort" value="<?php echo $sortBy; ?>">
                <input type="hidden" name="order" value="<?php echo $sortOrder; ?>">
                <input type="text" name="search" class="form-control" placeholder="Search auctions..." value="<?php echo $searchQuery; ?>">
                <button type="submit" class="btn btn-primary ms-2">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        
        <!-- Active Filters Display -->
        <?php if ($categoryFilter > 0 || !empty($searchQuery)): ?>
            <div class="mb-4">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted">Active filters:</span>
                    
                    <?php if ($categoryFilter > 0): 
                        $categoryName = '';
                        foreach ($categories as $cat) {
                            if ($cat['id'] == $categoryFilter) {
                                $categoryName = $cat['name'];
                                break;
                            }
                        }
                    ?>
                        <span class="badge bg-primary">
                            Category: <?php echo $categoryName; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => 0])); ?>" class="text-white text-decoration-none ms-1">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($searchQuery)): ?>
                        <span class="badge bg-primary">
                            Search: <?php echo $searchQuery; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="text-white text-decoration-none ms-1">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    <?php endif; ?>
                    
                    <a href="auctions.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Auctions Grid -->
        <?php if (count($auctions) > 0): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($auctions as $auction): ?>
                    <div class="col">
                        <div class="card auction-card h-100">
                            <div class="position-relative">
                                <img src="../<?php echo $auction['image_url']; ?>" class="card-img-top auction-image" alt="<?php echo $auction['title']; ?>">
                                <div class="badge-corner">
                                    <span class="badge bg-<?php echo $auction['bid_count'] > 0 ? 'danger' : 'secondary'; ?>">
                                        <?php echo $auction['bid_count']; ?> Bid<?php echo $auction['bid_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $auction['title']; ?></h5>
                                <p class="card-text text-truncate"><?php echo $auction['description']; ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-info"><?php echo $auction['category_name']; ?></span>
                                    <span class="time-remaining text-<?php echo $auction['time_remaining'] === 'Ended' ? 'danger' : 'success'; ?>">
                                        <i class="fas fa-clock"></i> <?php echo $auction['time_remaining']; ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <small class="text-muted">Starting bid:</small><br>
                                        <span>$<?php echo number_format($auction['start_price'], 2); ?></span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">Current bid:</small><br>
                                        <span class="current-bid">$<?php echo number_format($auction['current_price'] ?? $auction['start_price'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="auction-details.php?id=<?php echo $auction['id']; ?>" class="btn btn-primary w-100">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-auctions">
                <i class="fas fa-search" style="font-size: 3rem; color: #ccc;"></i>
                <h3 class="mt-3">No auctions found</h3>
                <p class="text-muted">Try adjusting your filters or search criteria</p>
                <a href="auctions.php" class="btn btn-outline-primary mt-2">Clear filters</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Filter Sidebar -->
    <div class="filter-backdrop" id="filter-backdrop"></div>
    <div class="filter-sidebar p-4" id="filter-sidebar">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Filter & Sort</h4>
            <button class="btn-close" id="close-filter"></button>
        </div>
        
        <form action="" method="GET">
            <input type="hidden" name="search" value="<?php echo $searchQuery; ?>">
            
            <!-- Category Filter -->
            <div class="mb-4">
                <h5>Categories</h5>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="category" id="category-all" value="0" <?php echo $categoryFilter === 0 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="category-all">
                        All Categories
                    </label>
                </div>
                
                <?php foreach ($categories as $category): ?>
                    <?php if (!isset($category['parent_id']) || $category['parent_id'] === null): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="category" id="category-<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>" <?php echo $categoryFilter === $category['id'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="category-<?php echo $category['id']; ?>">
                                <?php echo $category['name']; ?>
                            </label>
                        </div>
                        
                        <?php 
                        // Get child categories
                        $childCategories = getChildCategories($category['id']);
                        foreach ($childCategories as $childCategory): 
                        ?>
                            <div class="form-check ms-4">
                                <input class="form-check-input" type="radio" name="category" id="category-<?php echo $childCategory['id']; ?>" value="<?php echo $childCategory['id']; ?>" <?php echo $categoryFilter === $childCategory['id'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="category-<?php echo $childCategory['id']; ?>">
                                    <?php echo $childCategory['name']; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Sort Options -->
            <div class="mb-4">
                <h5>Sort By</h5>
                
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sort" id="sort-end-date" value="end_date" <?php echo $sortBy === 'end_date' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="sort-end-date">
                        Ending Soon
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sort" id="sort-start-date" value="start_date" <?php echo $sortBy === 'start_date' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="sort-start-date">
                        Newly Listed
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sort" id="sort-price-low" value="start_price" <?php echo $sortBy === 'start_price' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="sort-price-low">
                        Price (Low to High)
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sort" id="sort-price-high" value="start_price" <?php echo $sortBy === 'start_price' && $sortOrder === 'DESC' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="sort-price-high">
                        Price (High to Low)
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sort" id="sort-title" value="title" <?php echo $sortBy === 'title' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="sort-title">
                        Title (A-Z)
                    </label>
                </div>
            </div>
            
            <!-- Sort Order -->
            <div class="mb-4">
                <h5>Sort Order</h5>
                
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="order" id="order-asc" value="ASC" <?php echo $sortOrder === 'ASC' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="order-asc">
                        Ascending
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="order" id="order-desc" value="DESC" <?php echo $sortOrder === 'DESC' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="order-desc">
                        Descending
                    </label>
                </div>
            </div>
            
            <!-- Apply Filters Button -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="auctions.php" class="btn btn-outline-secondary">Clear All Filters</a>
            </div>
        </form>
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
                <a href="auctions.php" class="nav-link active">
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
    <script>
        // Filter sidebar functionality
        const filterButton = document.getElementById('filter-button');
        const filterSidebar = document.getElementById('filter-sidebar');
        const filterBackdrop = document.getElementById('filter-backdrop');
        const closeFilter = document.getElementById('close-filter');
        
        filterButton.addEventListener('click', function() {
            filterSidebar.classList.add('show');
            filterBackdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
        
        function closeFilterSidebar() {
            filterSidebar.classList.remove('show');
            filterBackdrop.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        closeFilter.addEventListener('click', closeFilterSidebar);
        filterBackdrop.addEventListener('click', closeFilterSidebar);
        
        // Handle price sorting logic
        document.getElementById('sort-price-high').addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('order-desc').checked = true;
            }
        });
        
        document.getElementById('sort-price-low').addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('order-asc').checked = true;
            }
        });
    </script>
</body>
</html>
