// Function to check if cookie consent is already given
function checkCookieConsent() {
    if (!localStorage.getItem("cookieConsent")) {
        showModal();
    }
}

// Function to show the modal
function showModal() {
    const cookieModal = document.getElementById("cookie-consent-modal");
    cookieModal.classList.add("show");
    cookieModal.style.display = "block";  // Ensure the modal is visible
}

// Function to hide the modal
function hideModal() {
    const cookieModal = document.getElementById("cookie-consent-modal");
    cookieModal.classList.remove("show");
    cookieModal.style.display = "none";  // Ensure the modal is hidden
}

// Function to handle cookie acceptance
function acceptCookies() {
    localStorage.setItem("cookieConsent", "accepted");
    hideModal();
    alert("Cookies accepted!");
}

// Function to handle cookie rejection
function rejectCookies() {
    localStorage.setItem("cookieConsent", "rejected");
    hideModal();
    alert("Cookies rejected!");
}

// Select the buttons and assign event listeners
document.getElementById("accept-cookies").addEventListener("click", acceptCookies);
document.getElementById("reject-cookies").addEventListener("click", rejectCookies);

// Check cookie consent status when the page loads
document.addEventListener("DOMContentLoaded", checkCookieConsent);
