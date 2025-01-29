// Function to close the cookie consent modal
function closeCookieModal() {
    document.getElementById('cookie-consent-modal').style.display = 'none';
}

// Function to handle cookie acceptance
function acceptCookies() {
    localStorage.setItem('cookieConsent', 'accepted');
    closeCookieModal();
    alert('Cookies accepted!');
}

// Function to handle cookie rejection
function rejectCookies() {
    localStorage.setItem('cookieConsent', 'rejected');
    closeCookieModal();
    alert('Cookies rejected!');
}

// Function to check cookie consent status and show the modal if not accepted
function checkCookieConsent() {
    if (!localStorage.getItem('cookieConsent')) {
        document.getElementById('cookie-consent-modal').style.display = 'block';
    }
}

// Check the cookie consent status when the page loads
document.addEventListener('DOMContentLoaded', checkCookieConsent);
