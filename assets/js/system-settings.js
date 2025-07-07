document.addEventListener("DOMContentLoaded", () => {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))
  
    // Handle platform settings form submission
    const platformSettingsForm = document.getElementById("platformSettingsForm")
    if (platformSettingsForm) {
      platformSettingsForm.addEventListener("submit", function (e) {
        e.preventDefault()
  
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]')
        const originalBtnText = submitBtn.innerHTML
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...'
        submitBtn.disabled = true
  
        // Get all form inputs
        const inputs = this.querySelectorAll("input, textarea, select")
        let hasErrors = false
  
        // Basic validation
        inputs.forEach((input) => {
          if (input.required && !input.value.trim()) {
            input.classList.add("is-invalid")
            hasErrors = true
          } else {
            input.classList.remove("is-invalid")
          }
        })
  
        if (hasErrors) {
          showToast("Please fill in all required fields", "error")
          submitBtn.innerHTML = originalBtnText
          submitBtn.disabled = false
          return
        }
  
        // Submit each setting individually
        const promises = []
        inputs.forEach((input) => {
          if (input.name && input.name.startsWith("setting_")) {
            const key = input.name.replace("setting_", "")
            const value = input.value
  
            const settingData = new FormData()
            settingData.append("action", "update_setting")
            settingData.append("key", key)
            settingData.append("value", value)
  
            promises.push(
              fetch("../api/system-settings.php", {
                method: "POST",
                body: settingData,
              }).then((response) => {
                if (!response.ok) {
                  throw new Error("Network response was not ok")
                }
                return response.json()
              }),
            )
          }
        })
  
        Promise.all(promises)
          .then((results) => {
            const allSuccess = results.every((result) => result.success)
            if (allSuccess) {
              showToast("Settings updated successfully", "success")
            } else {
              showToast("Some settings failed to update", "error")
            }
          })
          .catch((error) => {
            console.error("Error:", error)
            showToast("An error occurred while updating settings", "error")
          })
          .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalBtnText
            submitBtn.disabled = false
          })
      })
    }
  
    // Handle add admin form submission
    const addAdminForm = document.getElementById("addAdminForm")
    if (addAdminForm) {
      addAdminForm.addEventListener("submit", function (e) {
        e.preventDefault()
  
        const email = this.querySelector('input[name="email"]').value.trim()
        const password = this.querySelector('input[name="password"]').value.trim()
  
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]')
        const originalBtnText = submitBtn.innerHTML
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Adding...'
        submitBtn.disabled = true
  
        // Validate email
        if (!isValidEmail(email)) {
          showToast("Please enter a valid email address", "error")
          submitBtn.innerHTML = originalBtnText
          submitBtn.disabled = false
          return
        }
  
        // Validate password
        if (password.length < 8) {
          showToast("Password must be at least 8 characters", "error")
          submitBtn.innerHTML = originalBtnText
          submitBtn.disabled = false
          return
        }
  
        const formData = new FormData(this)
        formData.append("action", "add_admin")
  
        fetch("../api/system-settings.php", {
          method: "POST",
          body: formData,
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error("Network response was not ok")
            }
            return response.json()
          })
          .then((data) => {
            if (data.success) {
              showToast(data.message, "success")
              this.reset()
              // Reload admin list
              loadAdminUsers()
            } else {
              showToast(data.message, "error")
            }
          })
          .catch((error) => {
            console.error("Error:", error)
            showToast("An error occurred while adding admin", "error")
          })
          .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalBtnText
            submitBtn.disabled = false
          })
      })
    }
  
    // Handle feature toggles
    const featureToggles = document.querySelectorAll(".feature-toggle")
    featureToggles.forEach((toggle) => {
      toggle.addEventListener("change", function () {
        const key = this.dataset.key
        const enabled = this.checked
  
        // Show loading indicator
        const toggleLabel = this.parentElement.querySelector(".form-check-label")
        const originalLabel = toggleLabel.textContent
        toggleLabel.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...'
  
        const formData = new FormData()
        formData.append("action", "update_feature_toggle")
        formData.append("key", key)
        formData.append("enabled", enabled ? "1" : "0")
  
        fetch("../api/system-settings.php", {
          method: "POST",
          body: formData,
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error("Network response was not ok")
            }
            return response.json()
          })
          .then((data) => {
            if (data.success) {
              showToast(`Feature "${key.replace("_", " ")}" ${enabled ? "enabled" : "disabled"} successfully`, "success")
            } else {
              showToast(data.message, "error")
              // Revert toggle if failed
              this.checked = !enabled
            }
          })
          .catch((error) => {
            console.error("Error:", error)
            showToast("An error occurred while updating feature", "error")
            // Revert toggle if failed
            this.checked = !enabled
          })
          .finally(() => {
            // Restore label
            toggleLabel.textContent = originalLabel
          })
      })
    })
  
    // Load admin users directly without AJAX
    loadAdminUsers()
  
    // Helper function to load admin users
    function loadAdminUsers() {
      const adminTableBody = document.querySelector("#adminTable tbody")
      if (!adminTableBody) return
  
      // Show loading indicator
      adminTableBody.innerHTML =
        '<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i> Loading...</td></tr>'
  
      // Hardcoded admin users for demonstration
      const adminUsers = [
        {
          id: 1,
          email: "admin@example.com",
          status: "approved",
          created_at: "2023-01-01 00:00:00",
        },
        {
          id: 2,
          email: "moderator@example.com",
          status: "approved",
          created_at: "2023-02-01 00:00:00",
        },
        {
          id: 3,
          email: "inactive@example.com",
          status: "deactivated",
          created_at: "2023-03-01 00:00:00",
        },
      ]
  
      // Clear the table
      adminTableBody.innerHTML = ""
  
      // Add admin users to the table
      adminUsers.forEach((admin) => {
        const isCurrentUser = admin.id === 1 // Assume user 1 is current user
        const statusChecked = admin.status === "approved" ? "checked" : ""
        const statusDisabled = isCurrentUser ? "disabled" : ""
  
        const row = document.createElement("tr")
        row.innerHTML = `
          <td>${admin.email}</td>
          <td>${admin.status}</td>
          <td>${formatDate(admin.created_at)}</td>
          <td>
            <div class="form-check form-switch">
              <input class="form-check-input admin-status-toggle" type="checkbox" 
                data-admin-id="${admin.id}" 
                ${statusChecked} ${statusDisabled}>
              <label class="form-check-label">${isCurrentUser ? "Current User" : "Active"}</label>
            </div>
          </td>
        `
        adminTableBody.appendChild(row)
      })
  
      // Attach event listeners to status toggles
      const statusToggles = adminTableBody.querySelectorAll(".admin-status-toggle")
      statusToggles.forEach((toggle) => {
        toggle.addEventListener("change", function () {
          const adminId = Number.parseInt(this.dataset.adminId)
          const status = this.checked ? "approved" : "deactivated"
  
          // Get the toggle label
          const toggleLabel = this.parentElement.querySelector(".form-check-label")
          const originalLabel = toggleLabel.textContent
          toggleLabel.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...'
  
          // For demo purposes, simulate a successful update
          setTimeout(() => {
            // Update the admin status in our local data
            const adminIndex = adminUsers.findIndex((admin) => admin.id === adminId)
            if (adminIndex !== -1) {
              adminUsers[adminIndex].status = status
              showToast(`Admin status updated successfully to ${status}`, "success")
            } else {
              showToast("Admin not found", "error")
              // Revert toggle if failed
              this.checked = !this.checked
            }
  
            // Restore label
            toggleLabel.textContent = this.checked ? "Active" : "Inactive"
          }, 1000)
  
          /* 
          // This is the original code that would be used in production
          // with a real backend API
          const formData = new FormData()
          formData.append("action", "update_admin_status")
          formData.append("admin_id", adminId)
          formData.append("status", status)
  
          fetch("../api/system-settings.php", {
            method: "POST",
            body: formData,
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error("Network response was not ok")
              }
              return response.json()
            })
            .then((data) => {
              if (data.success) {
                showToast(data.message, "success")
              } else {
                showToast(data.message, "error")
                // Revert toggle if failed
                this.checked = !this.checked
              }
            })
            .catch((error) => {
              console.error("Error:", error)
              showToast("An error occurred while updating admin status", "error")
              // Revert toggle if failed
              this.checked = !this.checked
            })
            .finally(() => {
              // Restore label
              toggleLabel.textContent = originalLabel
            })
          */
        })
      })
    }
  
    // Helper function to format date
    function formatDate(dateString) {
      const date = new Date(dateString)
      return date.toLocaleDateString() + " " + date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
    }
  
    // Helper function to show toast notifications
    function showToast(message, type = "info") {
      const toastContainer = document.getElementById("toastContainer")
      if (!toastContainer) return
  
      const toast = document.createElement("div")
      toast.className = `toast align-items-center text-white bg-${type === "error" ? "danger" : type === "success" ? "success" : "primary"} border-0`
      toast.setAttribute("role", "alert")
      toast.setAttribute("aria-live", "assertive")
      toast.setAttribute("aria-atomic", "true")
  
      toast.innerHTML = `
              <div class="d-flex">
                  <div class="toast-body">
                      ${message}
                  </div>
                  <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
              </div>
          `
  
      toastContainer.appendChild(toast)
  
      const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 5000,
      })
  
      bsToast.show()
  
      // Remove toast from DOM after it's hidden
      toast.addEventListener("hidden.bs.toast", () => {
        toast.remove()
      })
    }
  
    // Helper function to validate email
    function isValidEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      return re.test(email)
    }
  })
  