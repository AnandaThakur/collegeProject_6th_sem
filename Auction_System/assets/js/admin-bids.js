/**
 * Admin Bid Monitoring System
 * Handles real-time updates, bid history viewing, and auction management
 */

$(document).ready(() => {
  // Global variables
  let autoRefreshInterval
  const REFRESH_INTERVAL = 10000 // 10 seconds

  // Initialize auto-refresh
  if ($("#auto-refresh").is(":checked")) {
    startAutoRefresh()
  }

  // Toggle sidebar
  $(".menu-toggle").click(() => {
    $(".admin-container").toggleClass("sidebar-collapsed")
  })

  // Handle logout
  $("#logout-link").click((e) => {
    e.preventDefault()
    $.ajax({
      url: "../api/auth.php",
      type: "POST",
      data: {
        action: "logout",
      },
      dataType: "json",
      success: (response) => {
        if (response.success) {
          window.location.href = response.redirect || "../login.php"
        }
      },
    })
  })

  // Toggle auto-refresh
  $("#auto-refresh").change(function () {
    if ($(this).is(":checked")) {
      startAutoRefresh()
      showToast("success", "Auto-refresh enabled", "Data will refresh every " + REFRESH_INTERVAL / 1000 + " seconds")
    } else {
      stopAutoRefresh()
      showToast("info", "Auto-refresh disabled", "You'll need to refresh manually")
    }
  })

  // Manual refresh buttons
  $("#refresh-auctions").click(function () {
    $(this).html('<i class="fas fa-sync-alt fa-spin"></i> Refreshing...')
    $(this).prop("disabled", true)

    refreshAuctions().then(() => {
      $(this).html('<i class="fas fa-sync-alt"></i> Refresh')
      $(this).prop("disabled", false)
      showToast("success", "Data refreshed", "Auction data has been updated")
    })
  })

  $("#refresh-recent-bids").click(function () {
    $(this).html('<i class="fas fa-sync-alt fa-spin"></i> Refreshing...')
    $(this).prop("disabled", true)

    refreshRecentBids().then(() => {
      $(this).html('<i class="fas fa-sync-alt"></i> Refresh')
      $(this).prop("disabled", false)
    })
  })

  // Save minimum bid increment
  $(document).on("click", ".save-increment", function () {
    const auctionId = $(this).data("auction-id")
    const inputElement = $(this).closest(".input-group").find(".min-increment-input")
    const minIncrement = inputElement.val()
    const button = $(this)

    // Validate input
    if (!minIncrement || minIncrement < 0.01) {
      showAlert("danger", "Minimum increment must be at least $0.01")
      inputElement.addClass("is-invalid")
      return
    }

    inputElement.removeClass("is-invalid")

    // Disable button to prevent multiple clicks
    button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i>')

    $.ajax({
      url: "../api/bids.php",
      type: "POST",
      data: {
        action: "update_min_increment",
        auction_id: auctionId,
        min_increment: minIncrement,
      },
      dataType: "json",
      success: (response) => {
        if (response.success) {
          showAlert("success", response.message || "Minimum bid increment updated successfully")
          button.html('<i class="fas fa-check"></i>')
          button.removeClass("btn-outline-primary").addClass("btn-success")

          setTimeout(() => {
            button.html('<i class="fas fa-save"></i>')
            button.removeClass("btn-success").addClass("btn-outline-primary")
          }, 2000)
        } else {
          showAlert("danger", response.message || "Failed to update minimum bid increment")
          button.html('<i class="fas fa-times"></i>')
          button.removeClass("btn-outline-primary").addClass("btn-danger")

          setTimeout(() => {
            button.html('<i class="fas fa-save"></i>')
            button.removeClass("btn-danger").addClass("btn-outline-primary")
          }, 2000)
        }
      },
      error: () => {
        showAlert("danger", "Server error. Please try again.")
        button.html('<i class="fas fa-exclamation-triangle"></i>')
        button.removeClass("btn-outline-primary").addClass("btn-danger")

        setTimeout(() => {
          button.html('<i class="fas fa-save"></i>')
          button.removeClass("btn-danger").addClass("btn-outline-primary")
        }, 2000)
      },
      complete: () => {
        // Re-enable button
        button.prop("disabled", false)
      },
    })
  })

  // View bid history
  $(document).on("click", ".view-bids, .view-auction-bids", function () {
    const auctionId = $(this).data("auction-id")
    const auctionTitle = $(this).data("auction-title")

    // Set auction title in modal
    $("#auction-title-display").text(auctionTitle)

    // Show loading spinner
    $("#bids-loading").show()
    $("#bids-content").hide()
    $("#no-bids-message").hide()

    // Show modal
    $("#viewBidsModal").modal("show")

    // Load bids via AJAX
    $.ajax({
      url: "../api/bids.php",
      type: "POST",
      data: {
        action: "get_auction_bids",
        auction_id: auctionId,
      },
      dataType: "json",
      success: (response) => {
        // Hide loading spinner
        $("#bids-loading").hide()

        if (response.success) {
          const bids = response.data.bids || []

          // Update bid stats
          $("#total-bids-count").text(response.data.total_bids || 0)
          $("#highest-bid-amount").text(response.data.highest_bid || "0.00")

          if (bids.length > 0) {
            // Clear existing rows
            $("#bids-table-body").empty()

            // Add bid rows
            bids.forEach((bid, index) => {
              const status = bid.is_highest
                ? '<span class="badge bg-success">Highest</span>'
                : '<span class="badge bg-secondary">Outbid</span>'

              const row = `
                                <tr class="${index === 0 ? "table-success" : ""}">
                                    <td>${escapeHtml(bid.bidder_name)}</td>
                                    <td>${bid.formatted_amount}</td>
                                    <td>${bid.formatted_time}</td>
                                    <td>${status}</td>
                                </tr>
                            `

              $("#bids-table-body").append(row)
            })

            // Show bids table
            $("#bids-content").show()
          } else {
            // Show no bids message
            $("#no-bids-message").show()
            $("#bids-content").show()
          }
        } else {
          // Show error message
          $("#no-bids-message")
            .text(response.message || "Failed to load bid history")
            .show()
          $("#bids-content").hide()
        }
      },
      error: () => {
        // Hide loading spinner
        $("#bids-loading").hide()

        // Show error message
        $("#no-bids-message").text("Server error. Please try again.").show()
        $("#bids-content").hide()
      },
    })
  })

  // Close auction button
  $(document).on("click", ".close-auction", function () {
    const auctionId = $(this).data("auction-id")
    const auctionTitle = $(this).data("auction-title")

    // Set auction title in modal
    $("#close-auction-title").text(auctionTitle)

    // Show loading spinner
    $("#close-auction-loading").show()
    $("#close-auction-content").hide()

    // Show modal
    $("#closeAuctionModal").modal("show")

    // Load winner information
    $.ajax({
      url: "../api/bids.php",
      type: "POST",
      data: {
        action: "get_auction_winner",
        auction_id: auctionId,
      },
      dataType: "json",
      success: (response) => {
        // Hide loading spinner
        $("#close-auction-loading").hide()
        $("#close-auction-content").show()

        if (response.success) {
          if (response.data.has_winner) {
            // Show winner details
            $("#no-winner-message").hide()
            $("#winner-details").show()

            $("#winner-name").text(response.data.winner.name)
            $("#winner-email").text(response.data.winner.email)
            $("#winning-bid").text(response.data.winner.formatted_amount.replace("$", ""))

            // Store winner data for confirmation
            $("#confirm-close-auction").data("winner-id", response.data.winner.user_id)
            $("#confirm-close-auction").data("winning-bid", response.data.winner.bid_amount)
          } else {
            // Show no winner message
            $("#winner-details").hide()
            $("#no-winner-message").show()
          }

          // Store auction ID for confirmation
          $("#confirm-close-auction").data("auction-id", auctionId)
        } else {
          // Show error message
          showAlert("danger", response.message || "Failed to load auction details")
          $("#closeAuctionModal").modal("hide")
        }
      },
      error: () => {
        // Hide loading spinner
        $("#close-auction-loading").hide()

        // Show error message
        showAlert("danger", "Server error. Please try again.")
        $("#closeAuctionModal").modal("hide")
      },
    })
  })

  // Confirm close auction
  $("#confirm-close-auction").click(function () {
    const auctionId = $(this).data("auction-id")
    const winnerId = $(this).data("winner-id") || 0
    const winningBid = $(this).data("winning-bid") || 0
    const notifyParticipants = $("#notify-participants").is(":checked")
    const button = $(this)

    // Disable button to prevent multiple clicks
    button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Processing...')

    $.ajax({
      url: "../api/bids.php",
      type: "POST",
      data: {
        action: "close_auction",
        auction_id: auctionId,
        winner_id: winnerId,
        winning_bid: winningBid,
        notify_participants: notifyParticipants ? 1 : 0,
      },
      dataType: "json",
      success: (response) => {
        if (response.success) {
          // Hide modal
          $("#closeAuctionModal").modal("hide")

          // Show success message
          showAlert("success", response.message || "Auction has been closed successfully")

          // Refresh auctions
          refreshAuctions()
        } else {
          // Show error message
          showAlert("danger", response.message || "Failed to close auction")

          // Re-enable button
          button.prop("disabled", false).html('<i class="fas fa-gavel"></i> Close Auction')
        }
      },
      error: () => {
        // Show error message
        showAlert("danger", "Server error. Please try again.")

        // Re-enable button
        button.prop("disabled", false).html('<i class="fas fa-gavel"></i> Close Auction')
      },
    })
  })

  // Function to start auto-refresh
  function startAutoRefresh() {
    // Clear any existing interval
    stopAutoRefresh()

    // Set new interval
    autoRefreshInterval = setInterval(() => {
      refreshAuctions()
      refreshRecentBids()
    }, REFRESH_INTERVAL)

    // Initial refresh
    refreshAuctions()
    refreshRecentBids()
  }

  // Function to stop auto-refresh
  function stopAutoRefresh() {
    if (autoRefreshInterval) {
      clearInterval(autoRefreshInterval)
      autoRefreshInterval = null
    }
  }

  // Function to refresh auctions
  function refreshAuctions() {
    return new Promise((resolve, reject) => {
      $.ajax({
        url: "../api/bids.php",
        type: "POST",
        data: {
          action: "get_ongoing_auctions",
        },
        dataType: "json",
        success: (response) => {
          if (response.success) {
            updateAuctionsTable(response.data.auctions)
            $("#last-update-time").text("Last updated: " + formatDateTime(new Date()))
            resolve()
          } else {
            reject()
          }
        },
        error: () => {
          reject()
        },
      })
    })
  }

  // Function to refresh recent bids
  function refreshRecentBids() {
    return new Promise((resolve, reject) => {
      $.ajax({
        url: "../api/bids.php",
        type: "POST",
        data: {
          action: "get_recent_bids",
          limit: 10,
        },
        dataType: "json",
        success: (response) => {
          if (response.success) {
            updateRecentBidsTable(response.data.bids)
            resolve()
          } else {
            reject()
          }
        },
        error: () => {
          reject()
        },
      })
    })
  }

  // Function to update auctions table
  function updateAuctionsTable(auctions) {
    const tableBody = $("#live-auctions-table tbody")

    // If no auctions, show empty message
    if (!auctions || auctions.length === 0) {
      tableBody.html(`
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-hourglass-end empty-icon"></i>
                            <p>No ongoing auctions found</p>
                        </div>
                    </td>
                </tr>
            `)
      return
    }

    // Clear table if there are no existing rows (first load) or if it only has the empty message
    if (
      tableBody.find("tr").length === 0 ||
      (tableBody.find("tr").length === 1 && tableBody.find("tr td").length === 1)
    ) {
      tableBody.empty()
    }

    // Process each auction
    auctions.forEach((auction) => {
      const existingRow = tableBody.find(`tr[data-auction-id="${auction.id}"]`)

      if (existingRow.length) {
        // Update existing row
        const currentBidCell = existingRow.find(".current-bid")
        const bidCountCell = existingRow.find(".bid-count")
        const highestBidderCell = existingRow.find(".highest-bidder")
        const timeLeftCell = existingRow.find(".time-left")

        // Check if values have changed and update them
        const currentBidText = currentBidCell.text().trim()
        const newBidText = "$" + Number.parseFloat(auction.highest_bid || auction.current_price).toFixed(2)

        if (currentBidText !== newBidText) {
          currentBidCell.html(`<span class="bid-amount">${newBidText}</span>`)
          highlightChanges(currentBidCell)
        }

        const currentBidCount = bidCountCell.text().trim()
        if (currentBidCount !== auction.bid_count.toString()) {
          bidCountCell.html(`<span class="badge bg-info">${auction.bid_count}</span>`)
          highlightChanges(bidCountCell)
        }

        // Update highest bidder
        let bidderHtml = ""
        if (auction.highest_bidder_name && auction.highest_bidder_name.trim()) {
          bidderHtml = `<span class="bidder-name">${escapeHtml(auction.highest_bidder_name)}</span>`
        } else if (auction.highest_bidder_email) {
          bidderHtml = `<span class="bidder-email">${escapeHtml(auction.highest_bidder_email)}</span>`
        } else {
          bidderHtml = `<span class="no-bids">No bids yet</span>`
        }

        if (highestBidderCell.html() !== bidderHtml) {
          highestBidderCell.html(bidderHtml)
          highlightChanges(highestBidderCell)
        }

        // Update time left
        updateTimeLeft(timeLeftCell, auction.end_date)
      } else {
        // Create new row
        let timeLeftHtml = ""
        if (auction.end_date) {
          const endTime = new Date(auction.end_date)
          const now = new Date()
          const interval = getTimeRemaining(endTime, now)

          if (endTime < now) {
            timeLeftHtml = '<span class="text-danger">Ended</span>'
          } else {
            if (interval.days > 0) {
              timeLeftHtml = `<div class="countdown"><i class="fas fa-clock me-1"></i>${interval.days}d ${interval.hours}h ${interval.minutes}m</div>`
            } else if (interval.hours > 0) {
              timeLeftHtml = `<div class="countdown"><i class="fas fa-clock me-1"></i>${interval.hours}h ${interval.minutes}m</div>`
            } else {
              timeLeftHtml = `<div class="countdown urgent"><i class="fas fa-clock me-1"></i>${interval.minutes}m ${interval.seconds}s</div>`
            }
          }
        } else {
          timeLeftHtml = '<span class="text-muted">No end date</span>'
        }

        let bidderHtml = ""
        if (auction.highest_bidder_name && auction.highest_bidder_name.trim()) {
          bidderHtml = `<span class="bidder-name">${escapeHtml(auction.highest_bidder_name)}</span>`
        } else if (auction.highest_bidder_email) {
          bidderHtml = `<span class="bidder-email">${escapeHtml(auction.highest_bidder_email)}</span>`
        } else {
          bidderHtml = `<span class="no-bids">No bids yet</span>`
        }

        const newRow = `
                    <tr data-auction-id="${auction.id}">
                        <td>
                            <img src="${auction.image_url || "../assets/img/placeholder.jpg"}" 
                                 alt="${escapeHtml(auction.title)}" 
                                 class="auction-thumbnail">
                        </td>
                        <td>
                            <a href="#" class="auction-title-link" data-auction-id="${auction.id}">
                                ${escapeHtml(auction.title)}
                            </a>
                            <div class="small text-muted">ID: ${auction.id}</div>
                        </td>
                        <td class="current-bid">
                            <span class="bid-amount">$${Number.parseFloat(auction.highest_bid || auction.current_price).toFixed(2)}</span>
                        </td>
                        <td class="bid-count">
                            <span class="badge bg-info">${auction.bid_count}</span>
                        </td>
                        <td class="highest-bidder">
                            ${bidderHtml}
                        </td>
                        <td class="time-left" data-end-time="${auction.end_date}">
                            ${timeLeftHtml}
                        </td>
                        <td class="min-increment">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control min-increment-input" 
                                       value="${Number.parseFloat(auction.min_bid_increment || 1).toFixed(2)}" 
                                       min="0.01" step="0.01" 
                                       data-auction-id="${auction.id}">
                                <button class="btn btn-outline-primary save-increment" 
                                        data-auction-id="${auction.id}">
                                    <i class="fas fa-save"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-info view-bids" 
                                        data-auction-id="${auction.id}"
                                        data-auction-title="${escapeHtml(auction.title)}"
                                        title="View Bid History">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger close-auction" 
                                        data-auction-id="${auction.id}"
                                        data-auction-title="${escapeHtml(auction.title)}"
                                        title="Close Auction">
                                    <i class="fas fa-gavel"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `

        tableBody.append(newRow)
      }
    })

    // Remove rows for auctions that are no longer ongoing
    tableBody.find("tr").each(function () {
      const rowAuctionId = $(this).data("auction-id")

      if (rowAuctionId) {
        const stillExists = auctions.some((auction) => auction.id === rowAuctionId)

        if (!stillExists) {
          $(this).fadeOut(500, function () {
            $(this).remove()
          })
        }
      }
    })
  }

  // Function to update recent bids table
  function updateRecentBidsTable(bids) {
    const tableBody = $("#recent-bids-table tbody")

    // If no bids, show empty message
    if (!bids || bids.length === 0) {
      tableBody.html(`
                <tr>
                    <td colspan="5" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-chart-bar empty-icon"></i>
                            <p>No recent bids found</p>
                        </div>
                    </td>
                </tr>
            `)
      return
    }

    // Clear table if there are no existing rows (first load) or if it only has the empty message
    if (
      tableBody.find("tr").length === 0 ||
      (tableBody.find("tr").length === 1 && tableBody.find("tr td").length === 1)
    ) {
      tableBody.empty()
    }

    // Keep track of existing bid IDs
    const existingBidIds = []
    tableBody.find("tr").each(function () {
      existingBidIds.push($(this).data("bid-id"))
    })

    // Process each bid
    bids.forEach((bid, index) => {
      // Only add new bids at the top
      if (!existingBidIds.includes(bid.id)) {
        const newRow = `
                    <tr data-bid-id="${bid.id}" style="display: none;">
                        <td>
                            <a href="#" class="auction-title-link" data-auction-id="${bid.auction_id}">
                                ${escapeHtml(bid.auction_title)}
                            </a>
                        </td>
                        <td>${escapeHtml(bid.bidder_name || bid.bidder_email)}</td>
                        <td><span class="bid-amount">$${Number.parseFloat(bid.bid_amount).toFixed(2)}</span></td>
                        <td>${formatDateTime(new Date(bid.created_at))}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info view-auction-bids" 
                                    data-auction-id="${bid.auction_id}"
                                    data-auction-title="${escapeHtml(bid.auction_title)}"
                                    title="View All Bids">
                                <i class="fas fa-list"></i>
                            </button>
                        </td>
                    </tr>
                `

        tableBody.prepend(newRow)
        tableBody.find(`tr[data-bid-id="${bid.id}"]`).fadeIn(500)
      }
    })

    // Limit to 10 rows
    if (tableBody.find("tr").length > 10) {
      tableBody.find("tr").slice(10).remove()
    }
  }

  // Function to highlight changes
  function highlightChanges(element) {
    element.addClass("highlight-change")
    setTimeout(() => {
      element.removeClass("highlight-change")
    }, 2000)
  }

  // Function to show alert
  function showAlert(type, message, autoHide = true) {
    const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `

    $("#alert-container").append(alertHtml)

    // Auto-dismiss after 5 seconds if autoHide is true
    if (autoHide) {
      setTimeout(() => {
        $("#alert-container .alert").first().alert("close")
      }, 5000)
    }
  }

  // Function to show toast notification
  function showToast(type, title, message) {
    // Create toast container if it doesn't exist
    if ($("#toast-container").length === 0) {
      $("body").append(
        '<div id="toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;"></div>',
      )
    }

    const toastId = "toast-" + Date.now()
    const iconClass =
      type === "success"
        ? "fa-check-circle"
        : type === "danger"
          ? "fa-exclamation-circle"
          : type === "warning"
            ? "fa-exclamation-triangle"
            : "fa-info-circle"

    const toastHtml = `
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-${type} text-white">
                    <i class="fas ${iconClass} me-2"></i>
                    <strong class="me-auto">${title}</strong>
                    <small>${formatDateTime(new Date(), true)}</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `

    $("#toast-container").append(toastHtml)

    const toastElement = document.getElementById(toastId)
    const toast = new bootstrap.Toast(toastElement, {
      autohide: true,
      delay: 5000,
    })

    toast.show()
  }

  // Function to format date and time
  function formatDateTime(date, timeOnly = false) {
    if (timeOnly) {
      return date.toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
      })
    }

    return date.toLocaleString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "numeric",
      second: "numeric",
      hour12: true,
    })
  }

  // Function to escape HTML
  function escapeHtml(text) {
    if (!text) return ""
    return text
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;")
  }

  // Function to get time remaining
  function getTimeRemaining(endTime, currentTime) {
    const total = endTime - currentTime
    const seconds = Math.floor((total / 1000) % 60)
    const minutes = Math.floor((total / 1000 / 60) % 60)
    const hours = Math.floor((total / (1000 * 60 * 60)) % 24)
    const days = Math.floor(total / (1000 * 60 * 60 * 24))

    return {
      total,
      days,
      hours,
      minutes,
      seconds,
    }
  }

  // Function to update time left
  function updateTimeLeft(element, endTimeStr) {
    if (!endTimeStr) {
      element.html('<span class="text-muted">No end date</span>')
      return
    }

    const endTime = new Date(endTimeStr)
    const now = new Date()

    if (endTime <= now) {
      element.html('<span class="text-danger">Ended</span>')
      return
    }

    const interval = getTimeRemaining(endTime, now)
    let timeLeftHtml = ""

    if (interval.days > 0) {
      timeLeftHtml = `<div class="countdown"><i class="fas fa-clock me-1"></i>${interval.days}d ${interval.hours}h ${interval.minutes}m</div>`
    } else if (interval.hours > 0) {
      timeLeftHtml = `<div class="countdown"><i class="fas fa-clock me-1"></i>${interval.hours}h ${interval.minutes}m</div>`
    } else {
      timeLeftHtml = `<div class="countdown urgent"><i class="fas fa-clock me-1"></i>${interval.minutes}m ${interval.seconds}s</div>`
    }

    element.html(timeLeftHtml)
  }

  // Update time left counters every second
  setInterval(() => {
    $(".time-left").each(function () {
      const endTimeStr = $(this).data("end-time")
      if (endTimeStr) {
        updateTimeLeft($(this), endTimeStr)
      }
    })
  }, 1000)
})

/**
 * Create the update-bid-monitoring.php file
 * This file will update the database schema to support bid monitoring
 */
