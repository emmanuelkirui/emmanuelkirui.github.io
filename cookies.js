// Select modal and buttons
const cookieModal = document.getElementById("cookie-consent-modal");
const acceptBtn = document.getElementById("accept-cookies");
const rejectBtn = document.getElementById("reject-cookies");

// Show the modal
function showModal() {
    cookieModal.classList.add("show");
}

// Hide the modal
function hideModal() {
    cookieModal.classList.remove("show");
}

// Check cookie consent status
if (!localStorage.getItem("cookieConsent")) {
    showModal();
}

// Accept cookies
acceptBtn.addEventListener("click", () => {
    localStorage.setItem("cookieConsent", "accepted");
    hideModal();
});

// Reject cookies
rejectBtn.addEventListener("click", () => {
    localStorage.setItem("cookieConsent", "rejected");
    hideModal();
});
