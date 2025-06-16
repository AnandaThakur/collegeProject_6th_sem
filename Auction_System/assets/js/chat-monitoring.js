document.addEventListener("DOMContentLoaded", () => {
    // Get elements
    const refreshBtn = document.getElementById("refreshBtn")
    const reviewFilters = document.querySelectorAll(".filter-reviews")
    const addFlaggedWordForm = document.getElementById("addFlaggedWordForm")
  
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
  
    // Load chat messages
    function loadChatMessages() {
      fetch("../api/chat-actions.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=get_chat_messages",
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            updateChatTable(data.messages)
          } else {
            showToast("Error", data.message, "error")
          }
        })
        .catch((error) => {
          console.error("Error:", error)
          showToast("Error", "Failed to load chat messages", "error")
        })
    }
  
    // Update chat table
    function updateChatTable(messages) {
      const tableBody = document.querySelector("#chatMessagesTable tbody")
      if (!tableBody) return
  
      // Get flagged words
      let flaggedWords = []
      fetch("../api/chat-actions.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=get_flagged_words",
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            flaggedWords = data.words.map((word) => word.word)
  
            let tableContent = ""
  
            if (messages.length === 0) {
              tableContent = '<tr><td colspan="7" class="text-center">No chat messages found</td></tr>'
            } else {
              messages.forEach((message) => {
                let messageContent = escapeHTML(message.message_content)
                let rowClass = ""
                let statusBadge = ""
  
                // Check if message contains any flagged words
                let containsFlagged = false
                flaggedWords.forEach((word) => {
                  if (messageContent.toLowerCase().includes(word.toLowerCase())) {
                    containsFlagged = true
                    const regex = new RegExp("(" + escapeRegExp(word) + ")", "gi")
                    messageContent = messageContent.replace(regex, '<span class="text-danger fw-bold">$1</span>')
                  }
                })
  
                if (message.is_flagged == 1) {
                  rowClass = "table-warning"
                  statusBadge = '<span class="badge bg-warning text-dark">Flagged</span>'
                } else if (containsFlagged) {
                  rowClass = "table-warning"
                  statusBadge = '<span class="badge bg-info text-dark">Contains flagged words</span>'
                }
  
                if (message.status === "deleted") {
                  rowClass = "table-danger"
                  statusBadge = '<span class="badge bg-danger">Deleted</span>'
                }
  
                // User status badge
                let userStatusBadge = ""
                if (message.user_status === "banned") {
                  userStatusBadge = '<span class="badge bg-danger ms-1">Banned</span>'
                }
  
                // Action buttons
                let actionButtons = ""
                if (message.status !== "deleted") {
                  actionButtons += `<button class="btn btn-sm btn-outline-danger delete-message" data-id="${message.message_id}"><i class="fas fa-trash"></i></button>`
                }
  
                if (message.is_flagged == 0 && message.status !== "deleted") {
                  actionButtons += ` <button class="btn btn-sm btn-outline-warning flag-message" data-id="${message.message_id}"><i class="fas fa-flag"></i></button>`
                } else if (message.is_flagged == 1 && message.status !== "deleted") {
                  actionButtons += ` <button class="btn btn-sm btn-outline-secondary unflag-message" data-id="${message.message_id}"><i class="fas fa-flag-checkered"></i></button>`
                }
  
                if (message.user_status !== "banned") {
                  actionButtons += ` <button class="btn btn-sm btn-outline-danger ban-user" data-id="${message.user_id}"><i class="fas fa-user-slash"></i></button>`
                } else {
                  actionButtons += ` <button class="btn btn-sm btn-outline-success unban-user" data-id="${message.user_id}"><i class="fas fa-user-check"></i></button>`
                }
  
                tableContent += `
                              <tr class="${rowClass}" data-message-id="${message.message_id}">
                                  <td>${message.message_id}</td>
                                  <td><a href="../admin/user-details.php?id=${message.user_id}">${escapeHTML(message.username)}</a>${userStatusBadge}</td>
                                  <td><a href="../admin/auctions.php?id=${message.auction_id}">${escapeHTML(message.auction_title)}</a></td>
                                  <td>${messageContent}</td>
                                  <td>${formatDate(message.timestamp)}</td>
                                  <td>${statusBadge}</td>
                                  <td>${actionButtons}</td>
                              </tr>
                          `
              })
            }
  
            tableBody.innerHTML = tableContent
  
            // Add event listeners to new buttons
            addChatActionEventListeners()
          }
        })
    }
  
    // Load reviews
    const loadReviews = function loadReviews(status = "all") {
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
  
    // Load flagged words
    function loadFlaggedWords() {
      const flaggedWordsContainer = document.getElementById("flaggedWordsContainer")
      if (!flaggedWordsContainer) return
  
      fetch("../api/chat-actions.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=get_flagged_words",
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            updateFlaggedWordsTable(data.words)
          } else {
            showToast("Error", data.message, "error")
            flaggedWordsContainer.innerHTML = `<div class="alert alert-danger">Failed to load flagged words: ${data.message}</div>`
          }
        })
        .catch((error) => {
          console.error("Error:", error)
          showToast("Error", "Failed to load flagged words", "error")
          flaggedWordsContainer.innerHTML = `<div class="alert alert-danger">Failed to load flagged words: ${error.message}</div>`
        })
    }
  
    // Update flagged words table
    function updateFlaggedWordsTable(words) {
      const flaggedWordsContainer = document.getElementById("flaggedWordsContainer")
      if (!flaggedWordsContainer) return
  
      let tableContent = `
              <table class="table table-striped table-hover" id="flaggedWordsTable">
                  <thead>
                      <tr>
                          <th>ID</th>
                          <th>Word</th>
                          <th>Severity</th>
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody>
          `
  
      if (words.length === 0) {
        tableContent += '<tr><td colspan="4" class="text-center">No flagged words found</td></tr>'
      } else {
        words.forEach((word) => {
          let severityBadge = ""
  
          if (word.severity === "low") {
            severityBadge = '<span class="badge bg-info">Low</span>'
          } else if (word.severity === "medium") {
            severityBadge = '<span class="badge bg-warning text-dark">Medium</span>'
          } else if (word.severity === "high") {
            severityBadge = '<span class="badge bg-danger">High</span>'
          }
  
          tableContent += `
                      <tr data-word-id="${word.id}">
                          <td>${word.id}</td>
                          <td>${escapeHTML(word.word)}</td>
                          <td>${severityBadge}</td>
                          <td>
                              <button class="btn btn-sm btn-outline-danger delete-flagged-word" data-id="${word.id}">
                                  <i class="fas fa-trash"></i>
                              </button>
                          </td>
                      </tr>
                  `
        })
      }
  
      tableContent += `
                  </tbody>
              </table>
          `
  
      flaggedWordsContainer.innerHTML = tableContent
  
      // Add event listeners to delete buttons
      document.querySelectorAll(".delete-flagged-word").forEach((button) => {
        button.addEventListener("click", handleDeleteFlaggedWord)
      })
    }
  
    // Add event listeners to chat action buttons
    function addChatActionEventListeners() {
      document.querySelectorAll(".flag-message").forEach((button) => {
        button.addEventListener("click", handleFlagMessage)
      })
  
      document.querySelectorAll(".unflag-message").forEach((button) => {
        button.addEventListener("click", handleUnflagMessage)
      })
  
      document.querySelectorAll(".delete-message").forEach((button) => {
        button.addEventListener("click", handleDeleteMessage)
      })
  
      document.querySelectorAll(".ban-user").forEach((button) => {
        button.addEventListener("click", handleBanUser)
      })
  
      document.querySelectorAll(".unban-user").forEach((button) => {
        button.addEventListener("click", handleUnbanUser)
      })
    }
  
    // Add event listeners to review action buttons
    function addReviewActionEventListeners() {
      document.querySelectorAll(".approve-review").forEach((button) => {
        button.addEventListener("click", handleApproveReview)
      })
  
      document.querySelectorAll(".delete-review").forEach((button) => {
        button.addEventListener("click", handleDeleteReview)
      })
  
      // Ban user buttons in review table
      document.querySelectorAll("#reviewsTable .ban-user").forEach((button) => {
        button.addEventListener("click", handleBanUser)
      })
    }
  
    // Handler functions
    function handleFlagMessage(e) {
      const messageId = e.currentTarget.dataset.id
  
      fetch("../api/chat-actions.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `action=flag_message&message_id=${messageId}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showToast("Success", data.message)
            loadChatMessages()
          } else {
            showToast("Error", data.message, "error")
          }
        })
        .catch((error) => {
          console.error("Error:", error)
          showToast("Error", "Failed to flag message", "error")
        })
    }
  
    function handleUnflagMessage(e) {
      const messageId = e.currentTarget.dataset.id
  
      fetch("../api/chat-actions.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `action=unflag_message&message_id=${messageId}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showToast("Success", data.message)
            loadChatMessages()
          } else {
            showToast("Error", data.message, "error")
          }
        })
        .catch((error) => {
          console.error("Error:", error)
          showToast("Error", "Failed to unflag message", "error")
        })
    }
  
    function handleDeleteMessage(e) {
      const messageId = e.currentTarget.dataset.id
  
      if (confirm("Are you sure you want to delete this message?")) {
        fetch("../api/chat-actions.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `action=delete_message&message_id=${messageId}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast("Success", data.message)
              loadChatMessages()
            } else {
              showToast("Error", data.message, "error")
            }
          })
          .catch((error) => {
            console.error("Error:", error)
            showToast("Error", "Failed to delete message", "error")
          })
      }
    }
  
    function handleBanUser(e) {
      const userId = e.currentTarget.dataset.id
  
      if (confirm("Are you sure you want to ban this user?")) {
        fetch("../api/chat-actions.php", {
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
              // Reload both chat messages and reviews
              loadChatMessages()
              if (document.getElementById("reviewsTableContainer")) {
                const activeFilter = document.querySelector(".filter-reviews.active")
                const status = activeFilter ? activeFilter.dataset.status : "all"
                loadReviews(status)
              }
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
  
    function handleUnbanUser(e) {
      const userId = e.currentTarget.dataset.id
  
      if (confirm("Are you sure you want to unban this user?")) {
        fetch("../api/chat-actions.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `action=unban_user&user_id=${userId}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast("Success", data.message)
              // Reload both chat messages and reviews
              loadChatMessages()
              if (document.getElementById("reviewsTableContainer")) {
                const activeFilter = document.querySelector(".filter-reviews.active")
                const status = activeFilter ? activeFilter.dataset.status : "all"
                loadReviews(status)
              }
            } else {
              showToast("Error", data.message, "error")
            }
          })
          .catch((error) => {
            console.error("Error:", error)
            showToast("Error", "Failed to unban user", "error")
          })
      }
    }
  
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
  
    function handleDeleteFlaggedWord(e) {
      const wordId = e.currentTarget.dataset.id
  
      if (confirm("Are you sure you want to delete this flagged word?")) {
        fetch("../api/chat-actions.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `action=delete_flagged_word&word_id=${wordId}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast("Success", data.message)
              loadFlaggedWords()
            } else {
              showToast("Error", data.message, "error")
            }
          })
          .catch((error) => {
            console.error("Error:", error)
            showToast("Error", "Failed to delete flagged word", "error")
          })
      }
    }
  
    // Helper functions
    function escapeHTML(str) {
      return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;")
    }
  
    function escapeRegExp(string) {
      return string.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")
    }
  
    // Event listeners
    if (refreshBtn) {
      refreshBtn.addEventListener("click", loadChatMessages)
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
  
    // Add flagged word form
    if (addFlaggedWordForm) {
      addFlaggedWordForm.addEventListener("submit", (e) => {
        e.preventDefault()
  
        const word = document.getElementById("newFlaggedWord").value.trim()
        const severity = document.getElementById("wordSeverity").value
  
        if (word === "") {
          showToast("Error", "Word cannot be empty", "error")
          return
        }
  
        fetch("../api/chat-actions.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `action=add_flagged_word&word=${encodeURIComponent(word)}&severity=${severity}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast("Success", data.message)
              document.getElementById("newFlaggedWord").value = ""
              loadFlaggedWords()
            } else {
              showToast("Error", data.message, "error")
            }
          })
          .catch((error) => {
            console.error("Error:", error)
            showToast("Error", "Failed to add flagged word", "error")
          })
      })
    }
  
    // Tab change event listener
    const tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]')
    tabEls.forEach((tabEl) => {
      tabEl.addEventListener("shown.bs.tab", (event) => {
        if (event.target.id === "chat-tab") {
          loadChatMessages()
        } else if (event.target.id === "reviews-tab") {
          loadReviews("all")
        } else if (event.target.id === "flagged-words-tab") {
          loadFlaggedWords()
        }
      })
    })
  
    // Load initial data
    if (document.getElementById("chatMessagesTable")) {
      loadChatMessages()
    }
  
    if (
      document.getElementById("reviewsTableContainer") &&
      document.getElementById("reviews-tab").classList.contains("active")
    ) {
      loadReviews("all")
    }
  
    if (
      document.getElementById("flaggedWordsContainer") &&
      document.getElementById("flagged-words-tab").classList.contains("active")
    ) {
      loadFlaggedWords()
    }
  
    // Initialize Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))
  })
  document.addEventListener("DOMContentLoaded", () => {
    console.log("Chat monitoring script loaded")
  
    // Check if Bootstrap is available
    if (typeof bootstrap === "undefined") {
      console.error("Bootstrap JavaScript is not loaded. Adding it manually...")
  
      // Try to add Bootstrap JS dynamically
      const bootstrapScript = document.createElement("script")
      bootstrapScript.src = "https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
      bootstrapScript.integrity = "sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"
      bootstrapScript.crossOrigin = "anonymous"
      document.body.appendChild(bootstrapScript)
  
      bootstrapScript.onload = () => {
        console.log("Bootstrap loaded dynamically")
        initializeMonitoring()
      }
    } else {
      initializeMonitoring()
    }
  
    function initializeMonitoring() {
      // Toast notification function
      function showToast(message, title = "Notification", type = "info") {
        const toastEl = document.getElementById("liveToast")
        const toastTitle = document.getElementById("toastTitle")
        const toastMessage = document.getElementById("toastMessage")
  
        if (!toastEl || !toastTitle || !toastMessage) {
          console.error("Toast elements not found")
          alert(message)
          return
        }
  
        // Set toast content
        toastTitle.textContent = title
        toastMessage.textContent = message
  
        // Set toast color based on type
        toastEl.classList.remove("bg-success", "bg-danger", "bg-warning", "bg-info", "text-white")
        switch (type) {
          case "success":
            toastEl.classList.add("bg-success", "text-white")
            break
          case "error":
            toastEl.classList.add("bg-danger", "text-white")
            break
          case "warning":
            toastEl.classList.add("bg-warning")
            break
          default:
            toastEl.classList.add("bg-info", "text-white")
        }
  
        // Show toast
        const toast = new bootstrap.Toast(toastEl)
        toast.show()
      }
  
      // Confirmation modal function
      function showConfirmModal(message, callback) {
        const modalEl = document.getElementById("confirmActionModal")
        const modalBody = document.getElementById("confirmActionModalBody")
        const confirmBtn = document.getElementById("confirmActionBtn")
  
        if (!modalEl || !modalBody || !confirmBtn) {
          console.error("Modal elements not found")
          if (confirm(message)) {
            callback()
          }
          return
        }
  
        modalBody.textContent = message
  
        // Remove any existing event listeners
        const newConfirmBtn = confirmBtn.cloneNode(true)
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn)
  
        // Add new event listener
        newConfirmBtn.addEventListener("click", () => {
          const modal = bootstrap.Modal.getInstance(modalEl)
          modal.hide()
          callback()
        })
  
        // Show modal
        const modal = new bootstrap.Modal(modalEl)
        modal.show()
      }
  
      // Helper function to format dates
      function formatDate(dateString) {
        const date = new Date(dateString)
        return date.toLocaleString()
      }
  
      // Helper function to escape HTML
      function escapeHTML(str) {
        if (!str) return ""
        return str
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;")
      }
  
      // Load chat messages
      function loadChatMessages(page = 1) {
        const auctionFilter = document.getElementById("auctionFilter")
        const statusFilter = document.getElementById("statusFilter")
        const searchChat = document.getElementById("searchChat")
  
        const auctionId = auctionFilter ? auctionFilter.value : ""
        const status = statusFilter ? statusFilter.value : ""
        const search = searchChat ? searchChat.value : ""
  
        const chatMessages = document.getElementById("chatMessages")
        const chatPagination = document.getElementById("chatPagination")
  
        if (!chatMessages) {
          console.error("Chat messages element not found")
          return
        }
  
        chatMessages.innerHTML =
          '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>'
  
        // Build query string
        let queryParams = `action=getMessages&page=${page}`
        if (auctionId) queryParams += `&auction_id=${auctionId}`
        if (status) queryParams += `&status=${status}`
        if (search) queryParams += `&search=${encodeURIComponent(search)}`
  
        // Fetch messages
        fetch(`../api/chat-actions.php?${queryParams}`)
          .then((response) => {
            if (!response.ok) {
              throw new Error("Network response was not ok")
            }
            return response.json()
          })
          .then((data) => {
            if (data.success) {
              // Render messages
              if (data.messages.length === 0) {
                chatMessages.innerHTML = '<tr><td colspan="6" class="text-center">No messages found</td></tr>'
              } else {
                let html = ""
                data.messages.forEach((message) => {
                  const isFlagged = message.is_flagged == 1
                  const isDeleted = message.status === "deleted"
                  const isBanned = message.is_banned == 1
  
                  let rowClass = ""
                  if (isDeleted) rowClass = "table-danger"
                  else if (isFlagged) rowClass = "table-warning"
  
                  let statusBadge = ""
                  if (isDeleted) statusBadge = '<span class="badge bg-danger">Deleted</span>'
                  else if (isFlagged) statusBadge = '<span class="badge bg-warning text-dark">Flagged</span>'
                  else statusBadge = '<span class="badge bg-success">Active</span>'
  
                  let actions = ""
                  if (!isDeleted) {
                    actions += `<button class="btn btn-sm btn-danger me-1 delete-message" data-id="${message.message_id}"><i class="fas fa-trash"></i></button>`
  
                    if (isFlagged) {
                      actions += `<button class="btn btn-sm btn-warning me-1 unflag-message" data-id="${message.message_id}"><i class="fas fa-flag-checkered"></i></button>`
                    } else {
                      actions += `<button class="btn btn-sm btn-outline-warning me-1 flag-message" data-id="${message.message_id}"><i class="fas fa-flag"></i></button>`
                    }
  
                    if (isBanned) {
                      actions += `<button class="btn btn-sm btn-success unban-user" data-id="${message.user_id}"><i class="fas fa-user-check"></i></button>`
                    } else {
                      actions += `<button class="btn btn-sm btn-outline-danger ban-user" data-id="${message.user_id}"><i class="fas fa-user-slash"></i></button>`
                    }
                  }
  
                  html += `
                                      <tr class="${rowClass}">
                                          <td>${escapeHTML(message.username || "Unknown")} ${isBanned ? '<span class="badge bg-danger">Banned</span>' : ""}</td>
                                          <td>${escapeHTML(message.auction_title || "Unknown")}</td>
                                          <td>${escapeHTML(message.message_content)}</td>
                                          <td>${formatDate(message.timestamp)}</td>
                                          <td>${statusBadge}</td>
                                          <td>${actions}</td>
                                      </tr>
                                  `
                })
                chatMessages.innerHTML = html
  
                // Add event listeners to action buttons
                document.querySelectorAll(".flag-message").forEach((btn) => {
                  btn.addEventListener("click", function () {
                    const messageId = this.getAttribute("data-id")
                    flagMessage(messageId)
                  })
                })
  
                document.querySelectorAll(".unflag-message").forEach((btn) => {
                  btn.addEventListener("click", function () {
                    const messageId = this.getAttribute("data-id")
                    unflagMessage(messageId)
                  })
                })
  
                document.querySelectorAll(".delete-message").forEach((btn) => {
                  btn.addEventListener("click", function () {
                    const messageId = this.getAttribute("data-id")
                    deleteMessage(messageId)
                  })
                })
  
                document.querySelectorAll(".ban-user").forEach((btn) => {
                  btn.addEventListener("click", function () {
                    const userId = this.getAttribute("data-id")
                    banUser(userId)
                  })
                })
  
                document.querySelectorAll(".unban-user").forEach((btn) => {
                  btn.addEventListener("click", function () {
                    const userId = this.getAttribute("data-id")
                    unbanUser(userId)
                  })
                })
              }
  
              // Render pagination if element exists
              if (chatPagination && data.pagination) {
                renderPagination(chatPagination, data.pagination, loadChatMessages)
              }
            } else {
              chatMessages.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${data.message || "Failed to load messages"}</td></tr>`
            }
          })
          .catch((error) => {
            console.error("Error loading chat messages:", error)
            chatMessages.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Error: ${error.message}</td></tr>`
          })
      }
  
      // Load reviews
      function loadReviews(page = 1) {
        const productFilter = document.getElementById("productFilter")
        const reviewStatusFilter = document.getElementById("reviewStatusFilter")
        const searchReviews = document.getElementById("searchReviews")
  
        const productId = productFilter ? productFilter.value : ""
        const status = reviewStatusFilter ? reviewStatusFilter.value : ""
        const search = searchReviews ? searchReviews.value : ""
  
        const reviewsList = document.getElementById("reviewsList")
        const reviewsPagination = document.getElementById("reviewsPagination")
  
        if (!reviewsList) {
          console.error("Reviews list element not found")
          return
        }
  
        reviewsList.innerHTML =
          '<tr><td colspan="7" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>'
  
        // Build query string
        let queryParams = `action=getReviews&page=${page}`
        if (productId) queryParams += `&product_id=${productId}`
        if (status) queryParams += `&status=${status}`
        if (search) queryParams += `&search=${encodeURIComponent(search)}`
  
        // Fetch reviews
        fetch(`../api/review-actions.php?${queryParams}`)
          .then((response) => {
            if (!response.ok) {
              throw new Error("Network response was not ok")
            }
            return response.json()
          })
          .then((data) => {
            if (data.success) {
              // Render reviews
              if (data.reviews.length === 0) {
                reviewsList.innerHTML = '<tr><td colspan="7" class="text-center">No reviews found</td></tr>'
              } else {
                let html = ""
                data.reviews.forEach((review) => {
                  const isPending = review.status === "pending"
                  const isApproved = review.status === "approved"
                  const isDeleted = review.status === "deleted"
                  const isBanned = review.is_banned == 1
  
                  let rowClass = ""
                  if (isDeleted) rowClass = "table-danger"
                  else if (isPending) rowClass = "table-warning"
                  else if (isApproved) rowClass = "table-success"
  
                  let statusBadge = ""
                  if (isDeleted) statusBadge = '<span class="badge bg-danger">Deleted</span>'
                  else if (isPending) statusBadge = '<span class="badge bg-warning text-dark">Pending</span>'
                  else if (isApproved) statusBadge = '<span class="badge bg-success">Approved</span>'
  
                  let actions = ""
                  if (!isDeleted) {
                    actions += `<button class="btn btn-sm btn-danger me-1 delete-review" data-id="${review.review_id}"><i class="fas fa-trash"></i></button>`
  
                    if (isPending) {
                      actions += `<button class="btn btn-sm btn-success me-1 approve-review" data-id="${review.review_id}"><i class="fas fa-check-circle"></i></button>`
                    }
  
                    if (isBanned) {
                      actions += `<button class="btn btn-sm btn-success unban-reviewer" data-id="${review.user_id}"><i class="fas fa-user-check"></i></button>`
                    } else {
                      actions += `<button class="btn btn-sm btn-outline-danger ban-reviewer" data-id="${review.user_id}"><i class="fas fa-user-slash"></i></button>`
                    }
                  }
  
                  // Generate star rating
                  let stars = ""
                  for (let i = 1; i <= 5; i++) {
                    if (i <= review.rating) {
                      stars += '<i class="fas fa-star text-warning"></i>'
                    } else {
                      stars += '<i class="far fa-star"></i>'
                    }
                  }
  
                  html += `
                                      <tr class="${rowClass}">
                                          <td>${escapeHTML(review.product_title || "Unknown")}</td>
                                          <td>${escapeHTML(review.username || "Unknown")} ${isBanned ? '<span class="badge bg-danger">Banned</span>' : ""}</td>
                                          <td>${escapeHTML(review.review_content)}</td>
                                          <td>${stars} (${review.rating})</td>
                                          <td>${formatDate(review.timestamp)}</td>
                                          <td>${statusBadge}</td>
                                          <td>${actions}</td>
                                      </tr>
                                  `
                })
                reviewsList.innerHTML = html
  
                // Add event listeners to action buttons
                document.querySelectorAll(".approve-review").forEach((btn) => {
                  btn.addEventListener("click", function () {
                    const reviewId = this.getAttribute("data-id")
                    approveReview(reviewId)
                  })
                })
  
                document.querySelectorAll(".delete-review").forEach((btn) => {
                  btn.addEventListener("click", function () {
                    const reviewId = this.getAttribute("data-id")
                    deleteReview(reviewId)
                  })
                })
  
                document.querySelectorAll(".ban-reviewer").forEach((btn) => {
                  btn.addEventListener("click", function () {
                    const userId = this.getAttribute("data-id")
                    banUser(userId, "reviewer")
                  })
                })
  
                document.querySelectorAll(".unban-reviewer").forEach((btn) => {
                  btn.addEventListener("click", function () {
                    const userId = this.getAttribute("data-id")
                    unbanUser(userId, "reviewer")
                  })
                })
              }
  
              // Render pagination if element exists
              if (reviewsPagination && data.pagination) {
                renderPagination(reviewsPagination, data.pagination, loadReviews)
              }
            } else {
              reviewsList.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${data.message || "Failed to load reviews"}</td></tr>`
            }
          })
          .catch((error) => {
            console.error("Error loading reviews:", error)
            reviewsList.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error: ${error.message}</td></tr>`
          })
      }
  
      // Load flagged words
      function loadFlaggedWords(page = 1) {
        const searchWords = document.getElementById("searchWords")
        const search = searchWords ? searchWords.value : ""
  
        const flaggedWordsList = document.getElementById("flaggedWordsList")
        const wordsPagination = document.getElementById("wordsPagination")
  
        if (!flaggedWordsList) {
          console.error("Flagged words list element not found")
          return
        }
  
        flaggedWordsList.innerHTML =
          '<tr><td colspan="3" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>'
  
        // Build query string
        let queryParams = `action=getFlaggedWords&page=${page}`
        if (search) queryParams += `&search=${encodeURIComponent(search)}`
  
        // Fetch flagged words
        fetch(`../api/chat-actions.php?${queryParams}`)
          .then((response) => {
            if (!response.ok) {
              throw new Error("Network response was not ok")
            }
            return response.json()
          })
          .then((data) => {
            if (data.success) {
              // Render flagged words
              if (data.words.length === 0) {
                flaggedWordsList.innerHTML = '<tr><td colspan="3" class="text-center">No flagged words found</td></tr>'
              } else {
                let html = ""
                data.words.forEach((word) => {
                  let severityBadge = ""
                  switch (word.severity) {
                    case "high":
                      severityBadge = '<span class="badge bg-danger">High</span>'
                      break
                    case "medium":
                      severityBadge = '<span class="badge bg-warning text-dark">Medium</span>'
                      break
                    case "low":
                      severityBadge = '<span class="badge bg-info text-dark">Low</span>'
                      break
                    default:
                      severityBadge = '<span class="badge bg-secondary">Unknown</span>'
                  }
  
                  html += `
                                      <tr>
                                          <td>${escapeHTML(word.word)}</td>
                                          <td>${severityBadge}</td>
                                          <td>
                                              <button class="btn btn-sm btn-danger delete-word" data-id="${word.id}">
                                                  <i class="fas fa-trash"></i> Delete
                                              </button>
                                          </td>
                                      </tr>
                                  `
                })
                flaggedWordsList.innerHTML = html
  
                // Add event listeners to delete buttons
                document.querySelectorAll(".delete-word").forEach((btn) => {
                  btn.addEventListener("click", function () {
                    const wordId = this.getAttribute("data-id")
                    deleteFlaggedWord(wordId)
                  })
                })
              }
  
              // Render pagination if element exists
              if (wordsPagination && data.pagination) {
                renderPagination(wordsPagination, data.pagination, loadFlaggedWords)
              }
            } else {
              flaggedWordsList.innerHTML = `<tr><td colspan="3" class="text-center text-danger">${data.message || "Failed to load flagged words"}</td></tr>`
            }
          })
          .catch((error) => {
            console.error("Error loading flagged words:", error)
            flaggedWordsList.innerHTML = `<tr><td colspan="3" class="text-center text-danger">Error: ${error.message}</td></tr>`
          })
      }
  
      // Render pagination
      function renderPagination(paginationElement, paginationData, loadFunction) {
        if (!paginationElement || !paginationData) {
          return
        }
  
        const { current_page, last_page } = paginationData
  
        if (last_page <= 1) {
          paginationElement.innerHTML = ""
          return
        }
  
        let html = ""
  
        // Previous button
        html += `
                  <li class="page-item ${current_page === 1 ? "disabled" : ""}">
                      <a class="page-link" href="#" data-page="${current_page - 1}" aria-label="Previous">
                          <span aria-hidden="true">&laquo;</span>
                      </a>
                  </li>
              `
  
        // Page numbers
        const maxPages = 5
        const startPage = Math.max(1, current_page - Math.floor(maxPages / 2))
        const endPage = Math.min(last_page, startPage + maxPages - 1)
  
        for (let i = startPage; i <= endPage; i++) {
          html += `
                      <li class="page-item ${i === current_page ? "active" : ""}">
                          <a class="page-link" href="#" data-page="${i}">${i}</a>
                      </li>
                  `
        }
  
        // Next button
        html += `
                  <li class="page-item ${current_page === last_page ? "disabled" : ""}">
                      <a class="page-link" href="#" data-page="${current_page + 1}" aria-label="Next">
                          <span aria-hidden="true">&raquo;</span>
                      </a>
                  </li>
              `
  
        paginationElement.innerHTML = html
  
        // Add event listeners to pagination links
        paginationElement.querySelectorAll(".page-link").forEach((link) => {
          link.addEventListener("click", function (e) {
            e.preventDefault()
            const page = Number.parseInt(this.getAttribute("data-id") || this.getAttribute("data-page"))
            if (page && page !== current_page) {
              loadFunction(page)
            }
          })
        })
      }
  
      // Flag a message
      function flagMessage(messageId) {
        const formData = new FormData()
        formData.append("action", "flag")
        formData.append("message_id", messageId)
  
        fetch("../api/chat-actions.php", {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast(data.message, "Success", "success")
              loadChatMessages()
            } else {
              showToast(data.message, "Error", "error")
            }
          })
          .catch((error) => {
            console.error("Error flagging message:", error)
            showToast("Failed to flag message", "Error", "error")
          })
      }
  
      // Unflag a message
      function unflagMessage(messageId) {
        const formData = new FormData()
        formData.append("action", "unflag")
        formData.append("message_id", messageId)
  
        fetch("../api/chat-actions.php", {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast(data.message, "Success", "success")
              loadChatMessages()
            } else {
              showToast(data.message, "Error", "error")
            }
          })
          .catch((error) => {
            console.error("Error unflagging message:", error)
            showToast("Failed to unflag message", "Error", "error")
          })
      }
  
      // Delete a message
      function deleteMessage(messageId) {
        showConfirmModal("Are you sure you want to delete this message?", () => {
          const formData = new FormData()
          formData.append("action", "delete")
          formData.append("message_id", messageId)
  
          fetch("../api/chat-actions.php", {
            method: "POST",
            body: formData,
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.success) {
                showToast(data.message, "Success", "success")
                loadChatMessages()
              } else {
                showToast(data.message, "Error", "error")
              }
            })
            .catch((error) => {
              console.error("Error deleting message:", error)
              showToast("Failed to delete message", "Error", "error")
            })
        })
      }
  
      // Ban a user
      function banUser(userId, type = "chat") {
        showConfirmModal("Are you sure you want to ban this user?", () => {
          const formData = new FormData()
          formData.append("action", "ban")
          formData.append("user_id", userId)
  
          fetch("../api/chat-actions.php", {
            method: "POST",
            body: formData,
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.success) {
                showToast(data.message, "Success", "success")
                if (type === "chat") {
                  loadChatMessages()
                } else {
                  loadReviews()
                }
              } else {
                showToast(data.message, "Error", "error")
              }
            })
            .catch((error) => {
              console.error("Error banning user:", error)
              showToast("Failed to ban user", "Error", "error")
            })
        })
      }
  
      // Unban a user
      function unbanUser(userId, type = "chat") {
        showConfirmModal("Are you sure you want to unban this user?", () => {
          const formData = new FormData()
          formData.append("action", "unban")
          formData.append("user_id", userId)
  
          fetch("../api/chat-actions.php", {
            method: "POST",
            body: formData,
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.success) {
                showToast(data.message, "Success", "success")
                if (type === "chat") {
                  loadChatMessages()
                } else {
                  loadReviews()
                }
              } else {
                showToast(data.message, "Error", "error")
              }
            })
            .catch((error) => {
              console.error("Error unbanning user:", error)
              showToast("Failed to unban user", "Error", "error")
            })
        })
      }
  
      // Approve a review
      function approveReview(reviewId) {
        const formData = new FormData()
        formData.append("action", "approve")
        formData.append("review_id", reviewId)
  
        fetch("../api/review-actions.php", {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast(data.message, "Success", "success")
              loadReviews()
            } else {
              showToast(data.message, "Error", "error")
            }
          })
          .catch((error) => {
            console.error("Error approving review:", error)
            showToast("Failed to approve review", "Error", "error")
          })
      }
  
      // Delete a review
      function deleteReview(reviewId) {
        showConfirmModal("Are you sure you want to delete this review?", () => {
          const formData = new FormData()
          formData.append("action", "delete")
          formData.append("review_id", reviewId)
  
          fetch("../api/review-actions.php", {
            method: "POST",
            body: formData,
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.success) {
                showToast(data.message, "Success", "success")
                loadReviews()
              } else {
                showToast(data.message, "Error", "error")
              }
            })
            .catch((error) => {
              console.error("Error deleting review:", error)
              showToast("Failed to delete review", "Error", "error")
            })
        })
      }
  
      // Add a flagged word
      function addFlaggedWord(word, severity) {
        const formData = new FormData()
        formData.append("action", "addFlaggedWord")
        formData.append("word", word)
        formData.append("severity", severity)
  
        fetch("../api/chat-actions.php", {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast(data.message, "Success", "success")
              document.getElementById("newFlaggedWord").value = ""
              loadFlaggedWords()
            } else {
              showToast(data.message, "Error", "error")
            }
          })
          .catch((error) => {
            console.error("Error adding flagged word:", error)
            showToast("Failed to add flagged word", "Error", "error")
          })
      }
  
      // Delete a flagged word
      function deleteFlaggedWord(wordId) {
        showConfirmModal("Are you sure you want to delete this flagged word?", () => {
          const formData = new FormData()
          formData.append("action", "deleteFlaggedWord")
          formData.append("word_id", wordId)
  
          fetch("../api/chat-actions.php", {
            method: "POST",
            body: formData,
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.success) {
                showToast(data.message, "Success", "success")
                loadFlaggedWords()
              } else {
                showToast(data.message, "Error", "error")
              }
            })
            .catch((error) => {
              console.error("Error deleting flagged word:", error)
              showToast("Failed to delete flagged word", "Error", "error")
            })
        })
      }
  
      // Event listeners
  
      // Chat tab
      const refreshChat = document.getElementById("refreshChat")
      if (refreshChat) {
        refreshChat.addEventListener("click", () => {
          loadChatMessages()
        })
      }
  
      const auctionFilter = document.getElementById("auctionFilter")
      if (auctionFilter) {
        auctionFilter.addEventListener("change", () => {
          loadChatMessages()
        })
      }
  
      const statusFilter = document.getElementById("statusFilter")
      if (statusFilter) {
        statusFilter.addEventListener("change", () => {
          loadChatMessages()
        })
      }
  
      const searchChatBtn = document.getElementById("searchChatBtn")
      if (searchChatBtn) {
        searchChatBtn.addEventListener("click", () => {
          loadChatMessages()
        })
      }
  
      const searchChat = document.getElementById("searchChat")
      if (searchChat) {
        searchChat.addEventListener("keypress", (e) => {
          if (e.key === "Enter") {
            e.preventDefault()
            loadChatMessages()
          }
        })
      }
  
      // Reviews tab
      const refreshReviews = document.getElementById("refreshReviews")
      if (refreshReviews) {
        refreshReviews.addEventListener("click", () => {
          loadReviews()
        })
      }
  
      const productFilter = document.getElementById("productFilter")
      if (productFilter) {
        productFilter.addEventListener("change", () => {
          loadReviews()
        })
      }
  
      const reviewStatusFilter = document.getElementById("reviewStatusFilter")
      if (reviewStatusFilter) {
        reviewStatusFilter.addEventListener("change", () => {
          loadReviews()
        })
      }
  
      const searchReviewsBtn = document.getElementById("searchReviewsBtn")
      if (searchReviewsBtn) {
        searchReviewsBtn.addEventListener("click", () => {
          loadReviews()
        })
      }
  
      const searchReviews = document.getElementById("searchReviews")
      if (searchReviews) {
        searchReviews.addEventListener("keypress", (e) => {
          if (e.key === "Enter") {
            e.preventDefault()
            loadReviews()
          }
        })
      }
  
      // Flagged words tab
      const addFlaggedWordForm = document.getElementById("addFlaggedWordForm")
      if (addFlaggedWordForm) {
        addFlaggedWordForm.addEventListener("submit", (e) => {
          e.preventDefault()
          const word = document.getElementById("newFlaggedWord").value.trim()
          const severity = document.getElementById("wordSeverity").value
  
          if (word) {
            addFlaggedWord(word, severity)
          } else {
            showToast("Please enter a word to flag", "Warning", "warning")
          }
        })
      }
  
      const searchWordsBtn = document.getElementById("searchWordsBtn")
      if (searchWordsBtn) {
        searchWordsBtn.addEventListener("click", () => {
          loadFlaggedWords()
        })
      }
  
      const searchWords = document.getElementById("searchWords")
      if (searchWords) {
        searchWords.addEventListener("keypress", (e) => {
          if (e.key === "Enter") {
            e.preventDefault()
            loadFlaggedWords()
          }
        })
      }
  
      // Tab change event
      const tabButtons = document.querySelectorAll('button[data-bs-toggle="tab"]')
      if (tabButtons.length > 0) {
        tabButtons.forEach((tab) => {
          tab.addEventListener("shown.bs.tab", (e) => {
            const targetId = e.target.getAttribute("data-bs-target")
  
            if (targetId === "#chat") {
              loadChatMessages()
            } else if (targetId === "#reviews") {
              loadReviews()
            } else if (targetId === "#flagged-words") {
              loadFlaggedWords()
            }
          })
        })
      }
  
      // Initialize tooltips
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
      tooltipTriggerList.forEach((tooltipTriggerEl) => {
        new bootstrap.Tooltip(tooltipTriggerEl)
      })
  
      // Initial load
      loadChatMessages()
    }
  })
  
  /**
   * Review-related functionality is imported from review-moderation.js
   * This is just a basic structure to show the relationship between the files
   */
  document.addEventListener("DOMContentLoaded", () => {
    // Initialize review functionality if the reviews tab exists
    if (document.getElementById("reviews-tab")) {
      // Variable definitions and function declarations will be defined in review-moderation.js
      document.querySelector("#reviews-tab").addEventListener("shown.bs.tab", () => {
        if (typeof loadReviews === "function") {
          loadReviews()
        }
      })
    }
  })
  