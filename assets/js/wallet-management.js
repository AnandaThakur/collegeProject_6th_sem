/**
 * Wallet Management JavaScript
 *
 * This file contains all the JavaScript functions for the wallet management section.
 */

// Document ready function
document.addEventListener("DOMContentLoaded", () => {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    const tooltip = tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))
  
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    const popover = popoverTriggerList.map((popoverTriggerEl) => new bootstrap.Popover(popoverTriggerEl))
  })
  
  /**
   * Update wallet balance
   *
   * @param {number} userId - The user ID
   * @param {string} action - The action (deduct or refund)
   */
  function updateWallet(userId, action) {
    const amountInput = document.getElementById(`amount-${userId}`)
    const amount = Number.parseFloat(amountInput.value)
  
    // Validate amount
    if (isNaN(amount) || amount <= 0) {
      showAlert("danger", "Please enter a valid amount greater than 0")
      amountInput.focus()
      return
    }
  
    // Update confirmation modal
    const modalBody = document.getElementById("confirmationModalBody")
    const actionText = action === "deduct" ? "deduct from" : "refund to"
    modalBody.innerHTML = `Are you sure you want to ${actionText} user #${userId}'s wallet balance?<br><br>
                            <strong>Amount:</strong> ${formatCurrency(amount)}`
  
    // Show confirmation modal
    const modal = new bootstrap.Modal(document.getElementById("confirmationModal"))
    modal.show()
  
    // Set up confirmation button
    document.getElementById("confirmActionBtn").onclick = () => {
      processWalletUpdate(userId, action, amount)
      modal.hide()
    }
  }
  
  /**
   * Process wallet update after confirmation
   *
   * @param {number} userId - The user ID
   * @param {string} action - The action (deduct or refund)
   * @param {number} amount - The amount to deduct or refund
   */
  function processWalletUpdate(userId, action, amount) {
    // Show loading state
    const balanceElement = document.getElementById(`balance-${userId}`)
    balanceElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'
  
    // Create form data
    const formData = new FormData()
    formData.append("action", action)
    formData.append("user_id", userId)
    formData.append("amount", amount)
    formData.append("description", `Manual ${action} by admin`)
  
    // Send AJAX request
    fetch("../api/wallet-actions.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Update balance display
          balanceElement.textContent = formatCurrency(data.new_balance)
  
          // Highlight the updated balance
          balanceElement.classList.add("updated")
          setTimeout(() => {
            balanceElement.classList.remove("updated")
          }, 3000)
  
          // Clear amount input
          document.getElementById(`amount-${userId}`).value = ""
  
          // Show success message
          showAlert("success", data.message)
  
          // Refresh recent transactions
          setTimeout(() => {
            refreshRecentTransactions()
          }, 1000)
        } else {
          // Show error message
          showAlert("danger", data.message)
          balanceElement.textContent = document.getElementById(`balance-${userId}`).textContent
        }
      })
      .catch((error) => {
        console.error("Error:", error)
        showAlert("danger", "An error occurred while processing your request")
        balanceElement.textContent = document.getElementById(`balance-${userId}`).textContent
      })
  }
  
  /**
   * Refresh recent transactions
   */
  function refreshRecentTransactions() {
    fetch("../api/wallet-actions.php?action=get_recent_transactions")
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Update the recent transactions table
          const transactionsTable = document.querySelector(".card-body .table-responsive table tbody")
          let html = ""
  
          if (data.transactions.length === 0) {
            html = '<tr><td colspan="6" class="text-center">No recent transactions</td></tr>'
          } else {
            data.transactions.forEach((transaction) => {
              html += `
                      <tr>
                          <td>${transaction.transaction_id}</td>
                          <td>${escapeHtml(transaction.user_email)}</td>
                          <td>${transaction.type_badge}</td>
                          <td class="transaction-amount ${transaction.amount >= 0 ? "positive" : "negative"}">
                              ${formatCurrency(transaction.amount)}
                          </td>
                          <td>${transaction.status_badge}</td>
                          <td>${formatDate(transaction.created_at)}</td>
                      </tr>`
            })
          }
  
          transactionsTable.innerHTML = html
        }
      })
      .catch((error) => {
        console.error("Error:", error)
      })
  }
  
  /**
   * Format currency
   *
   * @param {number} amount - The amount to format
   * @returns {string} - The formatted currency string
   */
  function formatCurrency(amount) {
    return "Rs " + Number.parseFloat(amount).toFixed(2)
  }
  
  /**
   * Format date
   *
   * @param {string} dateString - The date string to format
   * @returns {string} - The formatted date string
   */
  function formatDate(dateString) {
    const date = new Date(dateString)
    const options = { year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" }
    return date.toLocaleDateString("en-US", options)
  }
  
  /**
   * Escape HTML
   *
   * @param {string} text - The text to escape
   * @returns {string} - The escaped HTML
   */
  function escapeHtml(text) {
    const div = document.createElement("div")
    div.textContent = text
    return div.innerHTML
  }
  
  /**
   * Show alert
   *
   * @param {string} type - The alert type (success, danger, warning, info)
   * @param {string} message - The alert message
   */
  function showAlert(type, message) {
    const alertDiv = document.createElement("div")
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`
    alertDiv.setAttribute("role", "alert")
    alertDiv.innerHTML = `
          ${message}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      `
  
    // Insert alert at the top of the main content
    const mainContent = document.querySelector(".col-md-9.ms-sm-auto.col-lg-10.px-md-4")
    mainContent.insertBefore(alertDiv, mainContent.firstChild)
  
    // Auto dismiss after 5 seconds
    setTimeout(() => {
      alertDiv.remove()
    }, 5000)
  }
  
  /**
   * Export to CSV
   */
  function exportToCSV() {
    window.location.href = "../api/wallet-actions.php?action=export_wallets"
  }
  
  /**
   * Export transactions to CSV
   */
  function exportTransactions() {
    // Get current filters
    const urlParams = new URLSearchParams(window.location.search)
    const userId = urlParams.get("user_id") || ""
    const type = urlParams.get("type") || ""
    const status = urlParams.get("status") || ""
    const dateFrom = urlParams.get("date_from") || ""
    const dateTo = urlParams.get("date_to") || ""
  
    // Construct export URL with filters
    const exportUrl = `../api/wallet-actions.php?action=export_transactions&user_id=${userId}&type=${type}&status=${status}&date_from=${dateFrom}&date_to=${dateTo}`
  
    // Redirect to export URL
    window.location.href = exportUrl
  }
  