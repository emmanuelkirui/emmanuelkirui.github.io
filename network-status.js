document.addEventListener("DOMContentLoaded", function () {
    // Create the notification container
    const reconnectNotice = document.createElement("div");
    reconnectNotice.id = "reconnect-notice";
    reconnectNotice.className = "fade-in";

    // Create status elements
    const spinner = document.createElement("div");
    spinner.className = "spinner";

    const message = document.createElement("span");
    message.className = "status-message";

    // Append elements
    reconnectNotice.appendChild(spinner);
    reconnectNotice.appendChild(message);
    document.body.appendChild(reconnectNotice);

    // Connection states
    const states = {
        ONLINE: "online",
        OFFLINE: "offline",
        SLOW: "slow",
        ERROR: "error"
    };

    // Connection status configuration
    const statusConfig = {
        [states.ONLINE]: { 
            display: "none", 
            message: "" 
        },
        [states.OFFLINE]: { 
            display: "flex", 
            message: "Offline - No Internet Connection" 
        },
        [states.SLOW]: { 
            display: "flex", 
            message: "Slow Connection Detected" 
        },
        [states.ERROR]: { 
            display: "flex", 
            message: "Connection Error" 
        }
    };

    // Measure connection speed (in ms)
    async function testConnectionSpeed() {
        const startTime = performance.now();
        try {
            // Use a small test file or endpoint - replace with your own URL
            await fetch('https://example.com/ping', { 
                cache: 'no-store',
                mode: 'no-cors'
            });
            const endTime = performance.now();
            return endTime - startTime;
        } catch (error) {
            return Infinity; // Indicates error
        }
    }

    // Update UI based on status
    function updateUI(status) {
        const config = statusConfig[status];
        reconnectNotice.style.display = config.display;
        message.textContent = config.message;
        
        // Add status-specific styling
        reconnectNotice.className = `fade-in ${status}`;
    }

    // Main network status check
    async function checkNetworkStatus() {
        if (!navigator.onLine) {
            updateUI(states.OFFLINE);
            return;
        }

        const latency = await testConnectionSpeed();
        
        if (latency === Infinity) {
            updateUI(states.ERROR);
        } else if (latency > 1000) { // Adjust threshold as needed (1000ms = 1s)
            updateUI(states.SLOW);
        } else {
            updateUI(states.ONLINE);
        }
    }

    // Periodic checking
    let lastCheck = 0;
    const CHECK_INTERVAL = 5000; // Check every 5 seconds

    function throttleCheck() {
        const now = Date.now();
        if (now - lastCheck >= CHECK_INTERVAL) {
            lastCheck = now;
            checkNetworkStatus();
        }
    }

    // Event listeners
    window.addEventListener("online", checkNetworkStatus);
    window.addEventListener("offline", checkNetworkStatus);
    window.addEventListener("load", checkNetworkStatus);
    
    // Check periodically for slow connections
    setInterval(throttleCheck, 1000);

    // Initial check
    checkNetworkStatus();
});

// Suggested CSS to add
const styles = `
    #reconnect-notice {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        padding: 10px 20px;
        border-radius: 5px;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        align-items: center;
        gap: 10px;
        z-index: 1000;
    }

    .spinner {
        width: 20px;
        height: 20px;
        border: 3px solid #fff;
        border-top: 3px solid transparent;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    .fade-in {
        transition: opacity 0.3s ease;
        opacity: 0;
    }

    .fade-in.online { opacity: 0; }
    .fade-in.offline { opacity: 1; background: #dc3545; }
    .fade-in.slow { opacity: 1; background: #ffc107; }
    .fade-in.error { opacity: 1; background: #dc3545; }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;
