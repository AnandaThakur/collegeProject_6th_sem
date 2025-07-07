document.addEventListener("DOMContentLoaded", () => {
    // Get elements
    const refreshBtn = document.getElementById("refreshReviewsBtn")
    const reviewFilters = document.querySelectorAll(".filter-reviews")
  
    // Initialize Bootstrap toast
    const toastElement = document.getElementById("liveToast")
    const toast = new bootstrap.Toast(toastElement)
    const toastTitle = document.getElementById("toastTitle")
    const toastMessage = document.getElementById("toastMessage")
    const toastTime = document.getElementById("toastTime")
  
    // Set current time for toast
    function updateToastTime() {
      const now = new Date()
      toastTime.textContent = now.toLocaleTimeString()
    }
  
    // Show toast notification
    function showToast(title, message, type = "success") {
      toastTitle.textContent = title
      toastMessage.textContent = message
      updateToastTime()
  
      // Remove existing background classes
      toastElement.classList.remove("bg-success", "bg-danger", "bg-warning", "text-white")
  
      // Add appropriate background class
      if (type === "success") {
        toastElement.classList.add("bg-success", "text-white")
      } else if (type === "error") {
        toastElement.classList.add("bg-danger", "text-white")
      } else if (type === "warning") {
        toastElement.classList.add("bg-warning")
      }
  
      toast.show()
    }
  
    // Format date
    function formatDate(dateString) {
      const date = new Date(dateString)
      return date.toLocaleString()
    }
  
    // Load reviews
    function loadReviews(status = "all") {
      const reviewsContainer = document.getElementById("reviewsTableContainer")
      if (!reviewsContainer) return
  
      reviewsContainer.innerHTML = `
              <div class="text-center p-5">
                  <div class="spinner-border text-primary" role="status">
                      <span class="visually-hidden">Loading...</span>
                  </div>
                  <p class="mt-2">Loading reviews...</p>
              </div>
          `
  
      fetch("../api/review-actions.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `action=get_reviews&status=${status}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            updateReviewsTable(data.reviews)
          } else {
            showToast("Error", data.message, "error")
            reviewsContainer.innerHTML = `<div class="alert alert-danger">Failed to load reviews: ${data.message}</div>`
          }
        })
        .catch((error) => {
          console.error("Error:", error)
          showToast("Error", "Failed to load reviews", "error")
          reviewsContainer.innerHTML = `<div class="alert alert-danger">Failed to load reviews: ${error.message}</div>`
        })
    }
  
    // Update reviews table
    function updateReviewsTable(reviews) {
      const reviewsContainer = document.getElementById("reviewsTableContainer")
      if (!reviewsContainer) return
  
      let tableContent = `
              <table class="table table-striped table-hover" id="reviewsTable">
                  <thead>
                      <tr>
                          <th>ID</th>
                          <th>Product</th>
                          <th>User</th>
                          <th>Rating</th>
                          <th>Review</th>
                          <th>Date</th>
                          <th>Status</th>
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody>
          `
  
      if (reviews.length === 0) {
        tableContent += '<tr><td colspan="8" class="text-center">No reviews found</td></tr>'
      } else {
        reviews.forEach((review) => {
          let rowClass = ""
          let statusBadge = ""
  
          if (review.status === "pending") {
            rowClass = "table-warning"
            statusBadge = '<span class="badge bg-warning text-dark">Pending</span>'
          } else if (review.status === "approved") {
            statusBadge = '<span class="badge bg-success">Approved</span>'
          } else if (review.status === "deleted") {
            rowClass = "table-danger"
            statusBadge = '<span class="badge bg-danger">Deleted</span>'
          }
  
          // User status badge
          let userStatusBadge = ""
          if (review.user_status === "banned") {
            userStatusBadge = '<span class="badge bg-danger ms-1">Banned</span>'
          }
  
          // Star rating display
          let starRating = ""
          for (let i = 1; i <= 5; i++) {
            if (i <= review.rating) {
              starRating += '<i class="fas fa-star text-warning"></i>'
            } else {
              starRating += '<i class="far fa-star"></i>'
            }
          }
  
          // Action buttons
          let actionButtons = ""
          if (review.status === "pending") {
            actionButtons += `<button class="btn btn-sm btn-outline-success approve-review" data-id="${review.review_id}"><i class="fas fa-check"></i></button> `
          }
  
          if (review.status !== "deleted") {
            actionButtons += `<button class="btn btn-sm btn-outline-danger delete-review" data-id="${review.review_id}"><i class="fas fa-trash"></i></button> `
          }
  
          if (review.user_status !== "banned") {
            actionButtons += `<button class="btn btn-sm btn-outline-danger ban-user" data-id="${review.user_id}"><i class="fas fa-user-slash"></i></button>`
          }
  
          tableContent += `
                      <tr class="${rowClass}" data-review-id="${review.review_id}">
                          <td>${review.review_id}</td>
                          <td><a href="../admin/auctions.php?id=${review.product_id}">${escapeHTML(review.product_name)}</a></td>
                          <td><a href="../admin/user-details.php?id=${review.user_id}">${escapeHTML(review.username)}</a>${userStatusBadge}</td>
                          <td>${starRating}</td>
                          <td>${escapeHTML(review.review_content)}</td>
                          <td>${formatDate(review.timestamp)}</td>
                          <td>${statusBadge}</td>
                          <td>${actionButtons}</td>
                      </tr>
                  `
        })
      }
  
      tableContent += `
                  </tbody>
              </table>
          `
  
      reviewsContainer.innerHTML = tableContent
  
      // Add event listeners to new buttons
      addReviewActionEventListeners()
    }
  
    // Add event listeners to review action buttons
    function addReviewActionEventListeners() {
      document.querySelectorAll(".approve-review").forEach((button) => {
        button.addEventListener("click", handleApproveReview)
      })
  
      document.querySelectorAll(".delete-review").forEach((button) => {
        button.addEventListener("click", handleDeleteReview)
      })
  
      document.querySelectorAll(".ban-user").forEach((button) => {
        button.addEventListener("click", handleBanUser)
      })
    }
  
    // Handler functions
    function handleApproveReview(e) {
      const reviewId = e.currentTarget.dataset.id
  
      fetch("../api/review-actions.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `action=approve_review&review_id=${reviewId}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showToast("Success", data.message)
            const activeFilter = document.querySelector(".filter-reviews.active")
            const status = activeFilter ? activeFilter.dataset.status : "all"
            loadReviews(status)
          } else {
            showToast("Error", data.message, "error")
          }
        })
        .catch((error) => {
          console.error("Error:", error)
          showToast("Error", "Failed to approve review", "error")
        })
    }
  
    function handleDeleteReview(e) {
      const reviewId = e.currentTarget.dataset.id
  
      if (confirm("Are you sure you want to delete this review?")) {
        fetch("../api/review-actions.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `action=delete_review&review_id=${reviewId}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast("Success", data.message)
              const activeFilter = document.querySelector(".filter-reviews.active")
              const status = activeFilter ? activeFilter.dataset.status : "all"
              loadReviews(status)
            } else {
              showToast("Error", data.message, "error")
            }
          })
          .catch((error) => {
            console.error("Error:", error)
            showToast("Error", "Failed to delete review", "error")
          })
      }
    }
  
    function handleBanUser(e) {
      const userId = e.currentTarget.dataset.id
  
      if (confirm("Are you sure you want to ban this user?")) {
        fetch("../api/review-actions.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `action=ban_user&user_id=${userId}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast("Success", data.message)
              const activeFilter = document.querySelector(".filter-reviews.active")
              const status = activeFilter ? activeFilter.dataset.status : "all"
              loadReviews(status)
            } else {
              showToast("Error", data.message, "error")
            }
          })
          .catch((error) => {
            console.error("Error:", error)
            showToast("Error", "Failed to ban user", "error")
          })
      }
    }
  
    // Helper function
    function escapeHTML(str) {
      return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;")
    }
  
    // Event listeners
    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => {
        const activeFilter = document.querySelector(".filter-reviews.active")
        const status = activeFilter ? activeFilter.dataset.status : "all"
        loadReviews(status)
      })
    }
  
    // Filter reviews
    reviewFilters.forEach((filter) => {
      filter.addEventListener("click", function (e) {
        e.preventDefault()
  
        // Update active class
        reviewFilters.forEach((f) => f.classList.remove("active"))
        this.classList.add("active")
  
        // Load reviews with selected filter
        const status = this.dataset.status
        loadReviews(status)
      })
    })
  
    // Load initial data
    loadReviews("all")
  })
  