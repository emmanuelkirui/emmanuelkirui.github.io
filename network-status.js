document.addEventListener("DOMContentLoaded", function () {
    // Create the reconnect notification
    const reconnectNotice = document.createElement("div");
    reconnectNotice.id = "reconnect-notice";
    reconnectNotice.className = "fade-in";

    // Add a spinner for reconnecting
    const spinner = document.createElement("div");
    spinner.className = "spinner";

    // Add the message
    const message = document.createElement("span");
    message.textContent = "Reconnecting...";

    // Append spinner and message to the notice
    reconnectNotice.appendChild(spinner);
    reconnectNotice.appendChild(message);
    document.body.appendChild(reconnectNotice);

    // Function to check network status
    function checkNetworkStatus() {
        if (navigator.onLine) {
            reconnectNotice.style.display = "none"; // Hide notification when online
        } else {
            reconnectNotice.style.display = "flex"; // Show notification when offline
        }
    }

    // Initial check on page load
    checkNetworkStatus();

    // Event listeners for network status changes
    window.addEventListener("online", checkNetworkStatus);
    window.addEventListener("offline", checkNetworkStatus);
});
