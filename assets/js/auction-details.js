import { Toast } from "@/components/ui/toast"
/**
 * Auction Details JavaScript
 *
 * This file contains the JavaScript code for the auction details page.
 */

// Wait for DOM to be fully loaded
document.addEventListener("DOMContentLoaded", () => {
  // Initialize variables
  const bidForm = document.getElementById("bidForm")
  const messageInput = document.getElementById("messageInput")
  const sendMessageBtn = document.getElementById("sendMessageBtn")
  const openChatBtn = document.getElementById("openChatBtn")
  const chatMessages = document.getElementById("chatMessages")
  let messageInterval = null
  let swiper // Declare swiper variable
  let bootstrap // Declare bootstrap variable

  // Initialize Swiper if it exists
  if (typeof Swiper !== "undefined" && document.querySelector(".swiper")) {
    swiper = new Swiper(".swiper", {
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
      },
    })

    // Thumbnail click handling
    const thumbnails = document.querySelectorAll(".auction-thumb")
    thumbnails.forEach((thumb) => {
      thumb.addEventListener("click", function () {
        const index = Number.parseInt(this.dataset.index)
        swiper.slideTo(index)

        // Update active class
        thumbnails.forEach((t) => t.classList.remove("active"))
        this.classList.add("active")
      })
    })

    // Update thumbnails when slide changes
    swiper.on("slideChange", () => {
      thumbnails.forEach((t) => t.classList.remove("active"))
      const activeThumb = document.querySelector(`.auction-thumb[data-index="${swiper.activeIndex}"]`)
      if (activeThumb) {
        activeThumb.classList.add("active")
      }
    })
  }

  // Toast notification function
  function showToast(message, title = "Notification", type = "success") {
    const toastEl = document.getElementById("liveToast")
    const toastTitle = document.getElementById("toastTitle")
    const toastMessage = document.getElementById("toastMessage")
    const toastTime = document.getElementById("toastTime")

    if (!toastEl || !toastTitle || !toastMessage || !toastTime) {
      console.error("Toast elements not found")
      return
    }

    // Set content
    toastTitle.textContent = title
    toastMessage.textContent = message
    toastTime.textContent = new Date().toLocaleTimeString()

    // Set toast color based on type
    toastEl.classList.remove("bg-success", "bg-danger", "bg-warning", "text-white")
    if (type === "success") {
      toastEl.classList.add("bg-success", "text-white")
    } else if (type === "error") {
      toastEl.classList.add("bg-danger", "text-white")
    } else if (type === "warning") {
      toastEl.classList.add("bg-warning")
    }

    // Show toast
    bootstrap = bootstrap || {} // Ensure bootstrap is defined
    bootstrap.Toast = bootstrap.Toast || Toast // Use the global Toast if bootstrap.Toast is not defined
    const toast = new bootstrap.Toast(toastEl)
    toast.show()
  }

  // Handle bid form submission
  if (bidForm) {
    bidForm.addEventListener("submit", function (e) {
      e.preventDefault()

      // Disable the submit button to prevent double submissions
      const submitBtn = this.querySelector('button[type="submit"]')
      if (submitBtn) submitBtn.disabled = true

      const formData = new FormData(this)

      // Log the form data for debugging
      console.log("Submitting bid with data:", Object.fromEntries(formData))

      // Show a loading message
      showToast("Processing your bid...", "Please wait", "info")

      fetch("../api/bids.php", {
        method: "POST",
        body: formData,
        credentials: "same-origin", // Include cookies
      })
        .then((response) => {
          console.log("Response status:", response.status)
          // Check if the response is valid JSON
          const contentType = response.headers.get("content-type")
          if (contentType && contentType.includes("application/json")) {
            return response.json()
          } else {
            // If not JSON, get the text and log it
            return response.text().then((text) => {
              console.error("Non-JSON response:", text)
              throw new Error("Invalid response format")
            })
          }
        })
        .then((data) => {
          console.log("Response data:", data)
          if (data.success) {
            showToast("Bid placed successfully!", "Success", "success")
            setTimeout(() => {
              location.reload()
            }, 2000)
          } else {
            showToast(data.message || "An error occurred while placing your bid.", "Error", "error")
            if (submitBtn) submitBtn.disabled = false
          }
        })
        .catch((error) => {
          console.error("Error:", error)
          showToast("An error occurred while placing your bid. Please try again.", "Error", "error")
          if (submitBtn) submitBtn.disabled = false
        })
    })
  }

  // Function to send a message
  function sendMessage() {
    if (!messageInput || !messageInput.value.trim()) {
      console.log("Message input is empty, not sending")
      return
    }

    // Disable the send button to prevent double submissions
    if (sendMessageBtn) sendMessageBtn.disabled = true

    const message = messageInput.value.trim()
    const formData = new FormData()
    formData.append("action", "send_message")
    formData.append("auction_id", document.querySelector('input[name="auction_id"]').value)
    formData.append("recipient_id", document.querySelector('input[name="recipient_id"]').value)
    formData.append("message", message)

    // Log the form data for debugging
    console.log("Sending message with data:", Object.fromEntries(formData))

    // Show a loading message
    showToast("Sending message...", "Please wait", "info")

    fetch("../api/chat-actions.php", {
      method: "POST",
      body: formData,
      credentials: "same-origin", // Include cookies
    })
      .then((response) => {
        console.log("Response status:", response.status)
        // Check if the response is valid JSON
        const contentType = response.headers.get("content-type")
        if (contentType && contentType.includes("application/json")) {
          return response.json()
        } else {
          // If not JSON, get the text and log it
          return response.text().then((text) => {
            console.error("Non-JSON response:", text)
            throw new Error("Invalid response format")
          })
        }
      })
      .then((data) => {
        console.log("Response data:", data)
        if (data.success) {
          // Add message to chat
          addMessageToChat(message, true)
          messageInput.value = ""
          showToast("Message sent successfully", "Success", "success")
        } else {
          showToast(data.message || "Failed to send message", "Error", "error")
        }
        // Re-enable the send button
        if (sendMessageBtn) sendMessageBtn.disabled = false
      })
      .catch((error) => {
        console.error("Error:", error)
        showToast("An error occurred while sending your message. Please try again.", "Error", "error")
        // Re-enable the send button
        if (sendMessageBtn) sendMessageBtn.disabled = false
      })
  }

  // Function to add a message to the chat
  function addMessageToChat(message, isSender = false) {
    if (!chatMessages) return

    // Clear "no messages" text if it exists
    const noMessagesText = chatMessages.querySelector(".text-muted")
    if (noMessagesText) {
      chatMessages.innerHTML = ""
    }

    const messageElement = document.createElement("div")
    messageElement.className = `message ${isSender ? "message-sender" : "message-receiver"}`

    const messageContent = document.createTextNode(message)
    messageElement.appendChild(messageContent)

    const timeElement = document.createElement("div")
    timeElement.className = "message-time"
    timeElement.textContent = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
    messageElement.appendChild(timeElement)

    chatMessages.appendChild(messageElement)

    // Scroll to bottom
    chatMessages.scrollTop = chatMessages.scrollHeight
  }

  // Event listeners for chat
  if (sendMessageBtn) {
    sendMessageBtn.addEventListener("click", () => {
      console.log("Send button clicked")
      sendMessage()
    })
  }

  if (messageInput) {
    messageInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        console.log("Enter key pressed in message input")
        e.preventDefault()
        sendMessage()
      }
    })
  }

  if (openChatBtn) {
    openChatBtn.addEventListener("click", () => {
      console.log("Open chat button clicked")
      // Scroll to chat section
      const chatContainer = document.querySelector(".chat-container")
      if (chatContainer) {
        chatContainer.scrollIntoView({ behavior: "smooth" })
        if (messageInput) messageInput.focus()
      }
    })
  }

  // Function to fetch new messages
  function fetchMessages() {
    if (!chatMessages) {
      console.log("Chat messages container not found, skipping fetch")
      return
    }

    const auctionId = document.querySelector('input[name="auction_id"]').value
    const recipientId = document.querySelector('input[name="recipient_id"]').value

    if (!auctionId || !recipientId) {
      console.log("Missing auction ID or recipient ID, skipping fetch")
      return
    }

    const url = `../api/chat-actions.php?action=get_messages&auction_id=${auctionId}&recipient_id=${recipientId}&timestamp=${new Date().getTime()}`
    console.log("Fetching messages from:", url)

    fetch(url, {
      credentials: "same-origin", // Include cookies
    })
      .then((response) => {
        console.log("Fetch messages response status:", response.status)
        // Check if the response is valid JSON
        const contentType = response.headers.get("content-type")
        if (contentType && contentType.includes("application/json")) {
          return response.json()
        } else {
          // If not JSON, get the text and log it
          return response.text().then((text) => {
            console.error("Non-JSON response:", text)
            return { success: false, message: "Invalid response format" }
          })
        }
      })
      .then((data) => {
        console.log("Fetch messages response data:", data)

        // Check if we have valid data structure
        if (data.success && data.data && data.data.messages) {
          const messages = data.data.messages

          if (messages.length > 0) {
            // Clear chat if it's the first load
            if (chatMessages.querySelector(".text-muted")) {
              chatMessages.innerHTML = ""
            }

            // Add new messages
            let hasNewMessages = false
            const userId = Number.parseInt(document.querySelector('meta[name="user-id"]')?.content || "0")

            messages.forEach((message) => {
              // Check if message already exists
              const messageId = `message-${message.message_id}`
              if (!document.getElementById(messageId)) {
                const messageElement = document.createElement("div")
                messageElement.id = messageId
                messageElement.className = `message ${message.user_id == userId ? "message-sender" : "message-receiver"}`

                const messageContent = document.createTextNode(message.message_content)
                messageElement.appendChild(messageContent)

                const timeElement = document.createElement("div")
                timeElement.className = "message-time"
                timeElement.textContent = new Date(message.timestamp).toLocaleTimeString([], {
                  hour: "2-digit",
                  minute: "2-digit",
                })
                messageElement.appendChild(timeElement)

                chatMessages.appendChild(messageElement)
                hasNewMessages = true
              }
            })

            // Scroll to bottom if new messages
            if (hasNewMessages) {
              chatMessages.scrollTop = chatMessages.scrollHeight
            }
          }
        } else {
          console.log("No new messages or invalid response format")
        }
      })
      .catch((error) => {
        console.error("Error fetching messages:", error)
      })
  }

  // Fetch messages initially and then every 5 seconds
  if (chatMessages) {
    console.log("Setting up message fetching")
    fetchMessages()
    messageInterval = setInterval(fetchMessages, 5000)

    // Clean up interval when page is unloaded
    window.addEventListener("beforeunload", () => {
      if (messageInterval) {
        clearInterval(messageInterval)
      }
    })

    // Scroll to bottom initially
    chatMessages.scrollTop = chatMessages.scrollHeight
  }

  // Update time remaining
  const timeRemainingElement = document.getElementById("timeRemaining")
  if (timeRemainingElement) {
    const endDateElement = document.querySelector('meta[name="auction-end-date"]')
    if (endDateElement) {
      const endDate = new Date(endDateElement.content)

      function updateTimeRemaining() {
        const now = new Date()
        const diff = endDate - now

        if (diff <= 0) {
          timeRemainingElement.innerHTML = '<span class="text-danger">Auction has ended</span>'
          return
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24))
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
        const seconds = Math.floor((diff % (1000 * 60)) / 1000)

        let timeString = ""
        if (days > 0) {
          timeString = `${days} days, ${hours} hours, ${minutes} minutes`
        } else if (hours > 0) {
          timeString = `${hours} hours, ${minutes} minutes`
        } else {
          timeString = `${minutes} minutes, ${seconds} seconds`
        }

        timeRemainingElement.textContent = timeString
      }

      // Update immediately and then every second
      updateTimeRemaining()
      setInterval(updateTimeRemaining, 1000)
    }
  }
})
