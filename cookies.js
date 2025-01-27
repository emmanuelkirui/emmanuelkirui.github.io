// Function to display the cookie consent modal
function showCookieConsentModal() {
    const cookieModal = document.getElementById('cookie-consent-modal');
    const acceptBtn = document.getElementById('accept-cookies');
    const rejectBtn = document.getElementById('reject-cookies');

    // Check if the user has already accepted or rejected cookies
    if (!localStorage.getItem('cookieConsent')) {
        cookieModal.classList.add('show'); // Show the modal if not accepted/rejected
    }

    // Handle accepting cookies
    acceptBtn.addEventListener('click', () => {
        localStorage.setItem('cookieConsent', 'accepted'); // Save consent
        cookieModal.classList.remove('show'); // Hide the modal
    });

    // Handle rejecting cookies
    rejectBtn.addEventListener('click', () => {
        localStorage.setItem('cookieConsent', 'rejected'); // Save rejection
        cookieModal.classList.remove('show'); // Hide the modal
    });

    // If the user has made a decision, don't show the modal again
    if (localStorage.getItem('cookieConsent') === 'accepted' || localStorage.getItem('cookieConsent') === 'rejected') {
        cookieModal.classList.remove('show');
    }
}

// Call the function to show the modal when the page loads
window.onload = function() {
    showCookieConsentModal();
};
