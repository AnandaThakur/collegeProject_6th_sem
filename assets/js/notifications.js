/**
 * Notifications JavaScript
 * Handles notification functionality for the auction platform
 */

document.addEventListener("DOMContentLoaded", () => {
  // Mark single notification as read
  const markReadButtons = document.querySelectorAll(".mark-read")
  markReadButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault()
      e.stopPropagation()
      const notificationId = this.getAttribute("data-id")
      markAsRead(notificationId, this)
    })
  })

  // Mark all notifications as read
  const markAllReadButton = document.getElementById("mark-all-read")
  if (markAllReadButton) {
    markAllReadButton.addEventListener("click", (e) => {
      e.preventDefault()
      markAllAsRead()
    })
  }

  // Delete notification
  const deleteButtons = document.querySelectorAll(".delete-notification")
  deleteButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault()
      e.stopPropagation()
      const notificationId = this.getAttribute("data-id")

      // Show confirmation modal
      const deleteModalElement = document.getElementById("deleteModal")
      const deleteModal = new bootstrap.Modal(deleteModalElement)
      document.getElementById("confirm-delete").setAttribute("data-id", notificationId)
      deleteModal.show()
    })
  })

  // Confirm delete
  const confirmDeleteButton = document.getElementById("confirm-delete")
  if (confirmDeleteButton) {
    confirmDeleteButton.addEventListener("click", function () {
      const notificationId = this.getAttribute("data-id")
      deleteNotification(notificationId)
    })
  }

  // Notification item click
  const notificationItems = document.querySelectorAll(".notification-item")
  notificationItems.forEach((item) => {
    item.addEventListener("click", function (e) {
      // Don't trigger if clicking on a button
      if (e.target.tagName === "BUTTON" || e.target.closest("button")) {
        return
      }

      const notificationId = this.getAttribute("data-id")
      if (!this.classList.contains("unread")) {
        return
      }

      markAsRead(notificationId, null, this)
    })
  })

  // Filter notifications
  const filterForm = document.getElementById("notification-filter-form")
  if (filterForm) {
    filterForm.addEventListener("submit", (e) => {
      e.preventDefault()
      loadNotifications(1)
    })
  }

  // Reset filters
  const resetFiltersButton = document.getElementById("reset-filters")
  if (resetFiltersButton) {
    resetFiltersButton.addEventListener("click", (e) => {
      e.preventDefault()
      if (filterForm) {
        filterForm.reset()
        loadNotifications(1)
      }
    })
  }

  // Load more notifications
  const loadMoreButton = document.getElementById("load-more")
  if (loadMoreButton) {
    loadMoreButton.addEventListener("click", function () {
      const currentPage = Number.parseInt(this.getAttribute("data-page") || "1")
      loadNotifications(currentPage + 1)
    })
  }

  // Function to load notifications with filters
  function loadNotifications(page = 1) {
    if (!filterForm) return

    const formData = new FormData(filterForm)
    formData.append("action", "get_notifications")
    formData.append("page", page)

    fetch("../api/notifications.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          const notificationList = document.getElementById("notification-list")

          // If it's page 1, replace the content
          if (page === 1) {
            let html = ""

            if (data.data.notifications.length === 0) {
              html = `
                <div class="notification-empty">
                  <i class="fas fa-bell-slash"></i>
                  <h5>No Notifications</h5>
                  <p>No notifications match your filter criteria.</p>
                </div>
              `
            } else {
              data.data.notifications.forEach((notification) => {
                html += createNotificationHTML(notification)
              })
            }

            notificationList.innerHTML = html
          } else {
            // Otherwise append the content
            let html = ""
            data.data.notifications.forEach((notification) => {
              html += createNotificationHTML(notification)
            })

            // Remove the load more button if it exists
            const loadMoreContainer = document.querySelector(".load-more-container")
            if (loadMoreContainer) {
              loadMoreContainer.remove()
            }

            // Append the new notifications
            notificationList.insertAdjacentHTML("beforeend", html)
          }

          // Add load more button if there are more notifications
          if (data.data.has_more) {
            const loadMoreHTML = `
              <div class="text-center p-3 load-more-container">
                <button id="load-more" class="btn btn-outline-primary" data-page="${page}">Load More</button>
              </div>
            `
            notificationList.insertAdjacentHTML("beforeend", loadMoreHTML)

            // Add event listener to the new load more button
            document.getElementById("load-more").addEventListener("click", function () {
              const currentPage = Number.parseInt(this.getAttribute("data-page") || "1")
              loadNotifications(currentPage + 1)
            })
          }

          // Update notification count
          updateNotificationCount(data.data.unread_count)

          // Update stats
          const totalCountElement = document.getElementById("total-count")
          const unreadCountElement = document.getElementById("unread-count")

          if (totalCountElement) {
            totalCountElement.textContent = data.data.total_count
          }

          if (unreadCountElement) {
            unreadCountElement.textContent = data.data.unread_count
          }

          // Reattach event listeners
          attachEventListeners()
        }
      })
      .catch((error) => {
        console.error("Error loading notifications:", error)
      })
  }

  // Function to create notification HTML
  function createNotificationHTML(notification) {
    return `
      <div class="notification-item d-flex ${notification.is_read ? "" : "unread"}" data-id="${notification.id}">
        <div class="notification-icon">
          <i class="${notification.icon_class}"></i>
        </div>
        <div class="notification-content">
          <div class="notification-title">
            ${notification.title}
            ${!notification.is_read ? '<span class="badge bg-primary notification-badge">New</span>' : ""}
          </div>
          <div class="notification-message">${notification.message}</div>
          <div class="notification-meta">
            <span><i class="${notification.source_icon} me-1"></i> ${notification.source}</span>
            <span>${notification.formatted_date}</span>
            <span>${notification.type_badge}</span>
          </div>
        </div>
        <div class="notification-actions">
          ${
            !notification.is_read
              ? `
            <button class="btn btn-sm btn-outline-primary mark-read" data-id="${notification.id}" title="Mark as Read">
              <i class="fas fa-check"></i>
            </button>
          `
              : ""
          }
          <button class="btn btn-sm btn-outline-danger delete-notification" data-id="${notification.id}" title="Delete">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>
    `
  }

  // Function to attach event listeners to dynamically created elements
  function attachEventListeners() {
    // Mark as read buttons
    document.querySelectorAll(".mark-read").forEach((button) => {
      button.addEventListener("click", function (e) {
        e.preventDefault()
        e.stopPropagation()
        const notificationId = this.getAttribute("data-id")
        markAsRead(notificationId, this)
      })
    })

    // Delete buttons
    document.querySelectorAll(".delete-notification").forEach((button) => {
      button.addEventListener("click", function (e) {
        e.preventDefault()
        e.stopPropagation()
        const notificationId = this.getAttribute("data-id")

        // Show confirmation modal
        const deleteModalElement = document.getElementById("deleteModal")
        const deleteModal = bootstrap.Modal.getInstance(deleteModalElement)
        document.getElementById("confirm-delete").setAttribute("data-id", notificationId)
        deleteModal.show()
      })
    })

    // Notification items
    document.querySelectorAll(".notification-item").forEach((item) => {
      item.addEventListener("click", function (e) {
        // Don't trigger if clicking on a button
        if (e.target.tagName === "BUTTON" || e.target.closest("button")) {
          return
        }

        const notificationId = this.getAttribute("data-id")
        if (!this.classList.contains("unread")) {
          return
        }

        markAsRead(notificationId, null, this)
      })
    })
  }

  // Function to mark notification as read
  function markAsRead(notificationId, button, item = null) {
    const formData = new FormData()
    formData.append("action", "mark_as_read")
    formData.append("notification_id", notificationId)

    fetch("../api/notifications.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (button) {
            button.remove()
          }

          if (item) {
            item.classList.remove("unread")
            const markReadBtn = item.querySelector(".mark-read")
            if (markReadBtn) {
              markReadBtn.remove()
            }
          } else if (!item && button) {
            const notificationItem = button.closest(".notification-item")
            if (notificationItem) {
              notificationItem.classList.remove("unread")
            }
          }

          updateNotificationCount(data.data.unread_count)

          // Update unread count in stats
          const unreadCountElement = document.getElementById("unread-count")
          if (unreadCountElement) {
            unreadCountElement.textContent = data.data.unread_count
          }
        }
      })
      .catch((error) => {
        console.error("Error marking notification as read:", error)
      })
  }

  // Function to mark all notifications as read
  function markAllAsRead() {
    const formData = new FormData()
    formData.append("action", "mark_all_as_read")

    fetch("../api/notifications.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          const unreadItems = document.querySelectorAll(".notification-item.unread")
          unreadItems.forEach((item) => {
            item.classList.remove("unread")
            const markReadButton = item.querySelector(".mark-read")
            if (markReadButton) {
              markReadButton.remove()
            }
          })

          updateNotificationCount(0)

          // Update unread count in stats
          const unreadCountElement = document.getElementById("unread-count")
          if (unreadCountElement) {
            unreadCountElement.textContent = "0"
          }

          // Hide the mark all as read button
          if (markAllReadButton) {
            markAllReadButton.style.display = "none"
          }
        }
      })
      .catch((error) => {
        console.error("Error marking all notifications as read:", error)
      })
  }

  // Function to delete notification
  function deleteNotification(notificationId) {
    const formData = new FormData()
    formData.append("action", "delete_notification")
    formData.append("notification_id", notificationId)

    fetch("../api/notifications.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Hide modal
          const deleteModalElement = document.getElementById("deleteModal")
          const deleteModal = bootstrap.Modal.getInstance(deleteModalElement)
          if (deleteModal) {
            deleteModal.hide()
          }

          // Remove notification from list
          const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`)
          if (notificationItem) {
            notificationItem.remove()

            // Check if there are no notifications left
            const notificationItems = document.querySelectorAll(".notification-item")
            if (notificationItems.length === 0) {
              const notificationList = document.getElementById("notification-list")
              if (notificationList) {
                notificationList.innerHTML = `
                  <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <h5>No Notifications</h5>
                    <p>You don't have any notifications yet.</p>
                  </div>
                `
              }
            }
          }

          updateNotificationCount(data.data.unread_count)

          // Update counts in stats
          const totalCountElement = document.getElementById("total-count")
          const unreadCountElement = document.getElementById("unread-count")

          if (totalCountElement) {
            const currentTotal = Number.parseInt(totalCountElement.textContent)
            totalCountElement.textContent = currentTotal - 1
          }

          if (unreadCountElement) {
            unreadCountElement.textContent = data.data.unread_count
          }
        }
      })
      .catch((error) => {
        console.error("Error deleting notification:", error)
      })
  }

  // Function to update notification count in header
  function updateNotificationCount(count) {
    const countElements = document.querySelectorAll(".notification-count")
    countElements.forEach((element) => {
      if (count > 0) {
        element.textContent = count
        element.style.display = "inline-block"
      } else {
        element.style.display = "none"
      }
    })
  }

  // Check for new notifications periodically (every 60 seconds)
  function checkForNewNotifications() {
    const formData = new FormData()
    formData.append("action", "get_unread_count")

    fetch("../api/notifications.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          updateNotificationCount(data.data.unread_count)

          // Update unread count in stats
          const unreadCountElement = document.getElementById("unread-count")
          if (unreadCountElement) {
            unreadCountElement.textContent = data.data.unread_count
          }

          // Show/hide mark all as read button
          if (markAllReadButton) {
            markAllReadButton.style.display = data.data.unread_count > 0 ? "inline-block" : "none"
          }
        }
      })
      .catch((error) => {
        console.error("Error checking for new notifications:", error)
      })
  }

  // Initial check and then set interval
  checkForNewNotifications()
  setInterval(checkForNewNotifications, 60000) // Check every minute

  // Handle notification settings form
  const notificationSettingsForm = document.getElementById("notification-settings-form")
  if (notificationSettingsForm) {
    notificationSettingsForm.addEventListener("submit", function (e) {
      e.preventDefault()

      const formData = new FormData(this)
      formData.append("action", "update_settings")

      // Add checkboxes with false values if they're not checked
      if (!formData.has("email_notifications")) formData.append("email_notifications", "0")
      if (!formData.has("browser_notifications")) formData.append("browser_notifications", "0")
      if (!formData.has("auction_updates")) formData.append("auction_updates", "0")
      if (!formData.has("bid_alerts")) formData.append("bid_alerts", "0")
      if (!formData.has("system_messages")) formData.append("system_messages", "0")

      fetch("../api/notifications.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Show success message
            const settingsAlert = document.getElementById("settings-alert")
            if (settingsAlert) {
              settingsAlert.classList.remove("d-none")
              settingsAlert.classList.add("alert-success")
              settingsAlert.innerHTML =
                '<i class="fas fa-check-circle me-2"></i> Notification settings updated successfully!'

              // Hide the alert after 3 seconds
              setTimeout(() => {
                settingsAlert.classList.add("d-none")
              }, 3000)
            } else {
              alert("Notification settings updated successfully!")
            }
          } else {
            alert("Failed to update notification settings: " + (data.message || "Unknown error"))
          }
        })
        .catch((error) => {
          console.error("Error updating notification settings:", error)
          alert("An error occurred while updating notification settings.")
        })
    })
  }
})
