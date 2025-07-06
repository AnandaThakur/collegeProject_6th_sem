// Admin Auctions JavaScript
$(document).ready(() => {
  console.log("Admin auctions page loaded")

  // Initialize date pickers
  if (typeof flatpickr !== "undefined") {
    flatpickr(".date-picker", {
      enableTime: false,
      dateFormat: "Y-m-d",
    })

    // Initialize datetime pickers
    flatpickr(".datetime-picker", {
      enableTime: true,
      dateFormat: "Y-m-d H:i",
      time_24hr: true,
    })
  } else {
    console.error("Flatpickr is not loaded")
  }

  // Test API connection with retry mechanism
  let retryCount = 0
  const maxRetries = 3

  function testApiConnection() {
    console.log(`Testing API connection (attempt ${retryCount + 1})...`)

    $.ajax({
      url: "../api/test-api.php", // Use the dedicated test endpoint first
      type: "GET",
      dataType: "json",
      success: (response) => {
        console.log("Test API connection successful:", response)
        // Now test the actual auctions API
        testAuctionsApi()
      },
      error: (xhr, status, error) => {
        console.error("Test API connection failed:", status, error)
        console.error("Response Text:", xhr.responseText)
        console.error("Status Code:", xhr.status)

        if (retryCount < maxRetries) {
          retryCount++
          console.log(`Retrying connection in 1 second... (${retryCount}/${maxRetries})`)
          setTimeout(testApiConnection, 1000)
        } else {
          handleApiConnectionFailure(xhr, status, error)
        }
      },
      timeout: 5000, // 5 second timeout
    })
  }

  function testAuctionsApi() {
    $.ajax({
      url: "../api/auctions.php",
      type: "POST",
      data: {
        action: "test_connection",
      },
      dataType: "json",
      success: (response) => {
        console.log("Auctions API connection test:", response)
        if (response.success) {
          console.log("API connection successful")
          showAlert("success", "API connection successful. User: " + response.data.user)

          // Check admin status
          if (!response.data.is_admin) {
            showAlert("warning", "You don't have admin privileges. Some features may not work correctly.")
          }
        } else {
          console.error("API connection failed:", response.message)
          showAlert(
            "warning",
            "API connection issue detected. Some features may not work correctly. Error: " + response.message,
          )
        }
      },
      error: (xhr, status, error) => {
        handleApiConnectionFailure(xhr, status, error)
      },
      timeout: 5000, // 5 second timeout
    })
  }

  function handleApiConnectionFailure(xhr, status, error) {
    console.error("API connection test failed:", status, error)
    console.error("Response Text:", xhr.responseText)
    console.error("Status Code:", xhr.status)

    // Create a more detailed error message
    let errorDetails = "Status: " + status
    if (xhr.status) {
      errorDetails += ", HTTP Status: " + xhr.status
    }
    if (xhr.responseText) {
      try {
        // Try to parse as JSON
        const jsonResponse = JSON.parse(xhr.responseText)
        errorDetails += ", Response: " + JSON.stringify(jsonResponse)
      } catch (e) {
        // If not JSON, it's probably HTML or plain text
        errorDetails += ", Response: " + xhr.responseText.substring(0, 100)
      }
    }

    showAlert(
      "danger",
      "API connection failed. Please check your network connection and try again. Technical details: " + errorDetails,
    )

    // Add a debug button to help troubleshoot
    $("#alert-container").append(`
      <div class="mt-2">
        <button class="btn btn-sm btn-warning" id="debug-connection">
          <i class="fas fa-bug"></i> Debug Connection
        </button>
        <button class="btn btn-sm btn-info ms-2" id="check-session">
          <i class="fas fa-key"></i> Check Session
        </button>
        <button class="btn btn-sm btn-primary ms-2" id="init-database">
          <i class="fas fa-database"></i> Initialize Database
        </button>
      </div>
    `)

    // Add event listeners for debug buttons
    $("#debug-connection").click(() => {
      window.open("../api/test-api.php", "_blank")
    })

    $("#check-session").click(() => {
      window.open("../api/check-session.php", "_blank")
    })

    $("#init-database").click(() => {
      window.open("../database/init_tables.php", "_blank")
    })
  }

  // Start the API connection test
  testApiConnection()

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
          window.location.href = response.data.redirect
        }
      },
    })
  })

  // Refresh table
  $("#refresh-table").click(() => {
    location.reload()
  })

  // Apply filters
  $("#apply-filters").click(() => {
    // Get filter values
    const statusFilter = $("#status-filter").val()
    const sellerFilter = $("#seller-filter").val()
    const dateFrom = $("#date-from").val()
    const dateTo = $("#date-to").val()

    // Filter table rows
    $("#auctions-table tbody tr").each(function () {
      let show = true

      // Status filter
      if (
        statusFilter &&
        $(this).find(".status-cell").text().toLowerCase().indexOf(statusFilter.toLowerCase()) === -1
      ) {
        show = false
      }

      // Seller filter
      if (
        sellerFilter &&
        $(this).find("td:nth-child(3)").text().toLowerCase().indexOf(sellerFilter.toLowerCase()) === -1
      ) {
        show = false
      }

      // Date filters
      if (dateFrom || dateTo) {
        const startDateText = $(this).find(".start-date-cell").text()
        const endDateText = $(this).find(".end-date-cell").text()

        if (startDateText !== "Not set" && endDateText !== "Not set") {
          const startDate = new Date(startDateText)
          const endDate = new Date(endDateText)

          if (dateFrom && new Date(dateFrom) > endDate) {
            show = false
          }

          if (dateTo && new Date(dateTo) < startDate) {
            show = false
          }
        }
      }

      // Show or hide row
      $(this).toggle(show)
    })
  })

  // Reset filters
  $("#reset-filters").click(() => {
    $("#status-filter").val("")
    $("#seller-filter").val("")
    $("#date-from").val("")
    $("#date-to").val("")

    // Show all rows
    $("#auctions-table tbody tr").show()
  })

  // Handle approve auction
  $(document).on("click", ".approve-auction", function () {
    const auctionId = $(this).data("auction-id")
    const button = $(this)

    console.log("Approve button clicked for auction ID:", auctionId)

    // Confirm action
    if (!confirm("Are you sure you want to approve this auction?")) {
      return
    }

    // Disable button to prevent multiple clicks
    button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i>')

    // Log the request data
    console.log("Sending approve request with data:", {
      action: "approve_auction",
      auction_id: auctionId,
    })

    $.ajax({
      url: "../api/auctions.php",
      type: "POST",
      data: {
        action: "approve_auction",
        auction_id: auctionId,
      },
      dataType: "json",
      success: (response) => {
        console.log("Approve response:", response)

        if (response.success) {
          // Show success message
          showAlert("success", response.message)

          // Update row status
          const row = button.closest("tr")
          row.find(".status-cell").html('<span class="badge bg-info">Approved</span>')

          // Update action buttons
          updateActionButtons(row, "approved")
        } else {
          // Show error message
          showAlert("danger", response.message)

          // Re-enable button
          button.prop("disabled", false).html('<i class="fas fa-check"></i>')
        }
      },
      error: (xhr, status, error) => {
        // Log the error details
        console.error("AJAX Error:", status, error)
        console.error("Response Text:", xhr.responseText)
        console.error("Status Code:", xhr.status)

        let errorMessage = "An error occurred. Please try again."
        if (xhr.responseText) {
          try {
            // Try to parse as JSON first
            const jsonResponse = JSON.parse(xhr.responseText)
            errorMessage += " Details: " + (jsonResponse.message || error)
          } catch (e) {
            // If not JSON, it's probably HTML or plain text
            errorMessage += " Details: " + error + " (Server returned non-JSON response)"
            // Log the first 100 characters of the response for debugging
            console.error("Response preview:", xhr.responseText.substring(0, 100))
          }
        }

        // Show error message
        showAlert("danger", errorMessage)

        // Re-enable button
        button.prop("disabled", false).html('<i class="fas fa-check"></i>')

        // Add debug buttons
        $("#alert-container").append(`
          <div class="mt-2">
            <button class="btn btn-sm btn-warning" id="debug-approve">
              <i class="fas fa-bug"></i> Debug Approve Function
            </button>
          </div>
        `)

        // Add event listener for debug button
        $("#debug-approve").click(() => {
          window.open(`../api/check-session.php?auction_id=${auctionId}`, "_blank")
        })
      },
      timeout: 10000, // 10 second timeout
    })
  })

  // Handle reject auction modal
  $(document).on("click", ".reject-auction", function () {
    const auctionId = $(this).data("auction-id")
    console.log("Reject button clicked for auction ID:", auctionId)

    // Set auction ID in modal
    $("#reject-auction-id").val(auctionId)

    // Clear previous reason
    $("#rejection-reason").val("")

    // Show modal
    $("#rejectAuctionModal").modal("show")
  })

  // Handle confirm reject auction
  $("#confirm-reject-auction").click(function () {
    const auctionId = $("#reject-auction-id").val()
    const reason = $("#rejection-reason").val()
    const button = $(this)

    console.log("Confirm reject clicked for auction ID:", auctionId, "with reason:", reason)

    // Validate reason
    if (!reason.trim()) {
      alert("Please provide a reason for rejection.")
      return
    }

    // Disable button to prevent multiple clicks
    button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Processing...')

    // Log the request data
    console.log("Sending reject request with data:", {
      action: "reject_auction",
      auction_id: auctionId,
      reason: reason,
    })

    $.ajax({
      url: "../api/auctions.php",
      type: "POST",
      data: {
        action: "reject_auction",
        auction_id: auctionId,
        reason: reason,
      },
      dataType: "json",
      success: (response) => {
        console.log("Reject response:", response)

        if (response.success) {
          // Hide modal
          $("#rejectAuctionModal").modal("hide")

          // Show success message
          showAlert("success", response.message)

          // Update row status
          const row = $(`tr[data-auction-id="${auctionId}"]`)
          row.find(".status-cell").html('<span class="badge bg-danger">Rejected</span>')

          // Update action buttons
          updateActionButtons(row, "rejected")
        } else {
          // Show error message
          showAlert("danger", response.message)

          // Re-enable button
          button.prop("disabled", false).text("Confirm Rejection")
        }
      },
      error: (xhr, status, error) => {
        // Log the error details
        console.error("AJAX Error:", status, error)
        console.error("Response Text:", xhr.responseText)
        console.error("Status Code:", xhr.status)

        let errorMessage = "An error occurred. Please try again."
        if (xhr.responseText) {
          try {
            // Try to parse as JSON first
            const jsonResponse = JSON.parse(xhr.responseText)
            errorMessage += " Details: " + (jsonResponse.message || error)
          } catch (e) {
            // If not JSON, it's probably HTML or plain text
            errorMessage += " Details: " + error + " (Server returned non-JSON response)"
            // Log the first 100 characters of the response for debugging
            console.error("Response preview:", xhr.responseText.substring(0, 100))
          }
        }

        // Show error message
        showAlert("danger", errorMessage)

        // Re-enable button
        button.prop("disabled", false).text("Confirm Rejection")

        // Add debug buttons
        $("#alert-container").append(`
          <div class="mt-2">
            <button class="btn btn-sm btn-warning" id="debug-reject">
              <i class="fas fa-bug"></i> Debug Reject Function
            </button>
          </div>
        `)

        // Add event listener for debug button
        $("#debug-reject").click(() => {
          window.open(`../api/check-session.php?auction_id=${auctionId}`, "_blank")
        })
      },
      timeout: 10000, // 10 second timeout
    })
  })

  // Handle pause auction
  $(document).on("click", ".pause-auction", function () {
    const auctionId = $(this).data("auction-id")
    const button = $(this)

    // Confirm action
    if (!confirm("Are you sure you want to pause this auction?")) {
      return
    }

    // Disable button to prevent multiple clicks
    button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i>')

    $.ajax({
      url: "../api/auctions.php",
      type: "POST",
      data: {
        action: "pause_auction",
        auction_id: auctionId,
      },
      dataType: "json",
      success: (response) => {
        if (response.success) {
          // Show success message
          showAlert("success", response.message)

          // Update row status
          const row = button.closest("tr")
          row.find(".status-cell").html('<span class="badge bg-secondary">Paused</span>')

          // Update action buttons
          updateActionButtons(row, "paused")
        } else {
          // Show error message
          showAlert("danger", response.message)

          // Re-enable button
          button.prop("disabled", false).html('<i class="fas fa-pause"></i>')
        }
      },
      error: (xhr, status, error) => {
        // Show error message
        showAlert("danger", "An error occurred. Please try again. Details: " + error)

        // Re-enable button
        button.prop("disabled", false).html('<i class="fas fa-pause"></i>')
      },
      timeout: 10000, // 10 second timeout
    })
  })

  // Handle resume auction
  $(document).on("click", ".resume-auction", function () {
    const auctionId = $(this).data("auction-id")
    const button = $(this)

    // Confirm action
    if (!confirm("Are you sure you want to resume this auction?")) {
      return
    }

    // Disable button to prevent multiple clicks
    button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i>')

    $.ajax({
      url: "../api/auctions.php",
      type: "POST",
      data: {
        action: "resume_auction",
        auction_id: auctionId,
      },
      dataType: "json",
      success: (response) => {
        if (response.success) {
          // Show success message
          showAlert("success", response.message)

          // Update row status
          const row = button.closest("tr")
          row.find(".status-cell").html('<span class="badge bg-success">Ongoing</span>')

          // Update action buttons
          updateActionButtons(row, "ongoing")
        } else {
          // Show error message
          showAlert("danger", response.message)

          // Re-enable button
          button.prop("disabled", false).html('<i class="fas fa-play"></i>')
        }
      },
      error: (xhr, status, error) => {
        // Show error message
        showAlert("danger", "An error occurred. Please try again. Details: " + error)

        // Re-enable button
        button.prop("disabled", false).html('<i class="fas fa-play"></i>')
      },
      timeout: 10000, // 10 second timeout
    })
  })

  // Handle stop auction
  $(document).on("click", ".stop-auction", function () {
    const auctionId = $(this).data("auction-id")
    const button = $(this)

    // Confirm action
    if (!confirm("Are you sure you want to stop this auction? This action cannot be undone.")) {
      return
    }

    // Disable button to prevent multiple clicks
    button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i>')

    $.ajax({
      url: "../api/auctions.php",
      type: "POST",
      data: {
        action: "stop_auction",
        auction_id: auctionId,
      },
      dataType: "json",
      success: (response) => {
        if (response.success) {
          // Show success message
          showAlert("success", response.message)

          // Update row status
          const row = button.closest("tr")
          row.find(".status-cell").html('<span class="badge bg-dark">Ended</span>')

          // Update action buttons
          updateActionButtons(row, "ended")
        } else {
          // Show error message
          showAlert("danger", response.message)

          // Re-enable button
          button.prop("disabled", false).html('<i class="fas fa-stop"></i>')
        }
      },
      error: (xhr, status, error) => {
        // Show error message
        showAlert("danger", "An error occurred. Please try again. Details: " + error)

        // Re-enable button
        button.prop("disabled", false).html('<i class="fas fa-stop"></i>')
      },
      timeout: 10000, // 10 second timeout
    })
  })

  // Handle edit dates
  $(document).on("click", ".edit-dates", function () {
    const auctionId = $(this).data("auction-id")
    const startDate = $(this).data("start-date")
    const endDate = $(this).data("end-date")

    // Set values in modal
    $("#edit-auction-id").val(auctionId)
    $("#edit-start-date").val(startDate)
    $("#edit-end-date").val(endDate)

    // Show modal
    $("#editDatesModal").modal("show")
  })

  // Handle save dates
  $("#save-dates").click(function () {
    const auctionId = $("#edit-auction-id").val()
    const startDate = $("#edit-start-date").val()
    const endDate = $("#edit-end-date").val()
    const button = $(this)

    // Validate dates
    if (startDate && endDate && new Date(startDate) >= new Date(endDate)) {
      alert("End date must be after start date")
      return
    }

    // Disable button to prevent multiple clicks
    button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Saving...')

    $.ajax({
      url: "../api/auctions.php",
      type: "POST",
      data: {
        action: "update_dates",
        auction_id: auctionId,
        start_date: startDate,
        end_date: endDate,
      },
      dataType: "json",
      success: (response) => {
        if (response.success) {
          // Hide modal
          $("#editDatesModal").modal("hide")

          // Show success message
          showAlert("success", response.message)

          // Update row dates
          const row = $(`tr[data-auction-id="${auctionId}"]`)
          if (startDate) {
            const formattedStartDate = new Date(startDate).toLocaleString("en-US", {
              month: "short",
              day: "numeric",
              year: "numeric",
              hour: "numeric",
              minute: "numeric",
              hour12: true,
            })
            row.find(".start-date-cell").text(formattedStartDate)
          }

          if (endDate) {
            const formattedEndDate = new Date(endDate).toLocaleString("en-US", {
              month: "short",
              day: "numeric",
              year: "numeric",
              hour: "numeric",
              minute: "numeric",
              hour12: true,
            })
            row.find(".end-date-cell").text(formattedEndDate)
          }
        } else {
          // Show error message
          showAlert("danger", response.message)
        }

        // Re-enable button
        button.prop("disabled", false).text("Save Changes")
      },
      error: (xhr, status, error) => {
        // Show error message
        showAlert("danger", "An error occurred. Please try again. Details: " + error)

        // Re-enable button
        button.prop("disabled", false).text("Save Changes")
      },
      timeout: 10000, // 10 second timeout
    })
  })

  // Handle view bids
  $(document).on("click", ".view-bids", function () {
    const auctionId = $(this).data("auction-id")
    const auctionTitle = $(this).data("auction-title")

    // Set auction title in modal
    $("#auction-title-display").text(`Bid History for: ${auctionTitle}`)

    // Show loading spinner
    $("#bids-loading").show()
    $("#bids-content").hide()
    $("#no-bids-message").hide()

    // Show modal
    $("#viewBidsModal").modal("show")

    // Load bids via AJAX
    $.ajax({
      url: "../api/auctions.php",
      type: "POST",
      data: {
        action: "get_bids",
        auction_id: auctionId,
      },
      dataType: "json",
      success: (response) => {
        // Hide loading spinner
        $("#bids-loading").hide()

        if (response.success) {
          const bids = response.data.bids

          if (bids.length > 0) {
            // Clear existing rows
            $("#bids-table-body").empty()

            // Add bid rows
            bids.forEach((bid, index) => {
              const bidderName = bid.first_name && bid.last_name ? `${bid.first_name} ${bid.last_name}` : bid.email

              const bidTime = new Date(bid.created_at).toLocaleString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
                hour: "numeric",
                minute: "numeric",
                second: "numeric",
                hour12: true,
              })

              const status =
                index === 0
                  ? '<span class="badge bg-success">Highest</span>'
                  : '<span class="badge bg-secondary">Outbid</span>'

              const row = `
                <tr>
                    <td>${bidderName}</td>
                    <td>$${Number.parseFloat(bid.bid_amount).toFixed(2)}</td>
                    <td>${bidTime}</td>
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
          alert(response.message)
          $("#viewBidsModal").modal("hide")
        }
      },
      error: (xhr, status, error) => {
        // Hide loading spinner
        $("#bids-loading").hide()

        // Show error message
        alert("An error occurred while loading bids. Please try again. Error: " + error)
        $("#viewBidsModal").modal("hide")
      },
      timeout: 10000, // 10 second timeout
    })
  })

  // Handle export
  $("#confirm-export").click(() => {
    // In a real implementation, this would trigger a download
    alert(
      "Export functionality would be implemented here. This would generate a file with the auction data in the selected format.",
    )
    $("#exportModal").modal("hide")
  })

  // Function to show alert
  function showAlert(type, message) {
    const alertHtml = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
          ${message}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    `

    $("#alert-container").html(alertHtml)

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
      $(".alert").alert("close")
    }, 5000)
  }

  // Function to update action buttons based on status
  function updateActionButtons(row, status) {
    const auctionId = row.data("auction-id")
    let buttonsHtml = `
      <button type="button" class="btn btn-sm btn-info view-auction" 
              data-auction-id="${auctionId}"
              title="View Details">
          <i class="fas fa-eye"></i>
      </button>
    `

    // Add status-specific buttons
    switch (status) {
      case "pending":
        buttonsHtml += `
          <button type="button" class="btn btn-sm btn-success approve-auction" 
                  data-auction-id="${auctionId}"
                  title="Approve Auction">
              <i class="fas fa-check"></i>
          </button>
          <button type="button" class="btn btn-sm btn-danger reject-auction" 
                  data-auction-id="${auctionId}"
                  title="Reject Auction">
              <i class="fas fa-times"></i>
          </button>
        `
        break

      case "approved":
      case "ongoing":
        buttonsHtml += `
          <button type="button" class="btn btn-sm btn-warning pause-auction" 
                  data-auction-id="${auctionId}"
                  title="Pause Auction">
              <i class="fas fa-pause"></i>
          </button>
          <button type="button" class="btn btn-sm btn-danger stop-auction" 
                  data-auction-id="${auctionId}"
                  title="Stop Auction">
              <i class="fas fa-stop"></i>
          </button>
        `
        break

      case "paused":
        buttonsHtml += `
          <button type="button" class="btn btn-sm btn-success resume-auction" 
                  data-auction-id="${auctionId}"
                  title="Resume Auction">
              <i class="fas fa-play"></i>
          </button>
          <button type="button" class="btn btn-sm btn-danger stop-auction" 
                  data-auction-id="${auctionId}"
                  title="Stop Auction">
              <i class="fas fa-stop"></i>
          </button>
        `
        break
    }

    // Add common buttons for all statuses except rejected and ended
    if (status !== "rejected" && status !== "ended") {
      buttonsHtml += `
        <button type="button" class="btn btn-sm btn-primary edit-dates" 
                data-auction-id="${auctionId}"
                data-start-date="${row.find(".start-date-cell").data("date") || ""}"
                data-end-date="${row.find(".end-date-cell").data("date") || ""}"
                title="Edit Dates">
            <i class="fas fa-calendar-alt"></i>
        </button>
      `
    }

    // Add view bids button for all statuses
    buttonsHtml += `
      <button type="button" class="btn btn-sm btn-secondary view-bids" 
              data-auction-id="${auctionId}"
              data-auction-title="${row.find("td:nth-child(2)").text()}"
              title="View Bids">
          <i class="fas fa-list"></i>
      </button>
    `

    // Update buttons
    row.find("td:last-child .btn-group").html(buttonsHtml)
  }
})
