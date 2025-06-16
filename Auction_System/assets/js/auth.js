import $ from "jquery"

$(document).ready(() => {
  // Login form submission
  $("#login-form").submit(function (e) {
    e.preventDefault()

    // Reset previous errors
    $(".is-invalid").removeClass("is-invalid")
    $("#login-alert").addClass("d-none")

    // Get form data
    const formData = $(this).serialize()

    // Client-side validation
    let isValid = true
    const email = $("#email").val().trim()
    const password = $("#password").val()

    if (!email) {
      $("#email").addClass("is-invalid")
      $("#email-error").text("Email is required")
      isValid = false
    } else if (!isValidEmail(email)) {
      $("#email").addClass("is-invalid")
      $("#email-error").text("Invalid email format")
      isValid = false
    }

    if (!password) {
      $("#password").addClass("is-invalid")
      $("#password-error").text("Password is required")
      isValid = false
    }

    if (!isValid) {
      return
    }

    // Show loading state
    const submitBtn = $(this).find('button[type="submit"]')
    const originalText = submitBtn.text()
    submitBtn.prop("disabled", true).text("Logging in...")

    // Submit form via AJAX
    $.ajax({
      url: "api/auth.php",
      type: "POST",
      data: formData,
      dataType: "json",
      success: (response) => {
        if (response.success) {
          // Show success message
          $("#login-alert").removeClass("d-none alert-danger").addClass("alert-success").text(response.message)

          // Redirect after a short delay
          setTimeout(() => {
            window.location.href = response.data.redirect
          }, 1000)
        } else {
          // Show error message
          $("#login-alert").removeClass("d-none alert-success").addClass("alert-danger").text(response.message)
          submitBtn.prop("disabled", false).text(originalText)

          // Handle redirect for pending users
          if (response.data && response.data.redirect && response.data.status === "pending") {
            setTimeout(() => {
              window.location.href = response.data.redirect
            }, 2000)
          }
        }
      },
      error: (xhr, status, error) => {
        console.error("AJAX Error:", status, error)
        let errorMessage = "An error occurred. Please try again later."

        // Try to get more detailed error information
        if (xhr.responseText) {
          try {
            const response = JSON.parse(xhr.responseText)
            if (response.message) {
              errorMessage = response.message
            }
          } catch (e) {
            // If we can't parse the JSON, use the raw response text
            if (xhr.responseText.length < 100) {
              errorMessage = xhr.responseText
            }
          }
        }

        $("#login-alert").removeClass("d-none alert-success").addClass("alert-danger").text(errorMessage)
        submitBtn.prop("disabled", false).text(originalText)
      },
    })
  })

  // Signup form submission
  $("#signup-form").submit(function (e) {
    e.preventDefault()

    // Reset previous errors
    $(".is-invalid").removeClass("is-invalid")
    $("#signup-alert").addClass("d-none")

    // Get form data
    const formData = $(this).serialize()

    // Client-side validation
    let isValid = true
    const email = $("#email").val().trim()
    const password = $("#password").val()
    const confirmPassword = $("#confirm_password").val()

    if (!email) {
      $("#email").addClass("is-invalid")
      $("#email-error").text("Email is required")
      isValid = false
    } else if (!isValidEmail(email)) {
      $("#email").addClass("is-invalid")
      $("#email-error").text("Invalid email format")
      isValid = false
    }

    if (!password) {
      $("#password").addClass("is-invalid")
      $("#password-error").text("Password is required")
      isValid = false
    } else if (!isValidPassword(password)) {
      $("#password").addClass("is-invalid")
      $("#password-error").text("Password must be at least 8 characters and contain both letters and numbers")
      isValid = false
    }

    if (!confirmPassword) {
      $("#confirm_password").addClass("is-invalid")
      $("#confirm-password-error").text("Please confirm your password")
      isValid = false
    } else if (password !== confirmPassword) {
      $("#confirm_password").addClass("is-invalid")
      $("#confirm-password-error").text("Passwords do not match")
      isValid = false
    }

    if (!isValid) {
      return
    }

    // Show loading state
    const submitBtn = $(this).find('button[type="submit"]')
    const originalText = submitBtn.text()
    submitBtn.prop("disabled", true).text("Signing up...")

    // Submit form via AJAX
    $.ajax({
      url: "api/auth.php",
      type: "POST",
      data: formData,
      dataType: "json",
      success: (response) => {
        if (response.success) {
          // Show success message
          $("#signup-alert").removeClass("d-none alert-danger").addClass("alert-success").text(response.message)

          // Clear form
          $("#signup-form")[0].reset()

          // Redirect to login page after a delay
          setTimeout(() => {
            window.location.href = "login.php"
          }, 3000)
        } else {
          // Show error message
          $("#signup-alert").removeClass("d-none alert-success").addClass("alert-danger").text(response.message)
          submitBtn.prop("disabled", false).text(originalText)
        }
      },
      error: (xhr, status, error) => {
        console.error("AJAX Error:", status, error)
        $("#signup-alert")
          .removeClass("d-none alert-success")
          .addClass("alert-danger")
          .text("An error occurred. Please try again later.")
        submitBtn.prop("disabled", false).text(originalText)
      },
    })
  })

  // Helper functions
  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return emailRegex.test(email)
  }

  function isValidPassword(password) {
    // At least 8 characters, contains letters and numbers
    return password.length >= 8 && /[A-Za-z]/.test(password) && /[0-9]/.test(password)
  }
})
