document.addEventListener("DOMContentLoaded", function () {
    // Create notification container only if it doesn't exist
    let reconnectNotice = document.getElementById("reconnect-notice");
    if (!reconnectNotice) {
        reconnectNotice = document.createElement("div");
        reconnectNotice.id = "reconnect-notice";
        document.body.appendChild(reconnectNotice);
    }
    reconnectNotice.className = "fade-in";

    // Create/update spinner and message elements
    let spinner = reconnectNotice.querySelector(".spinner");
    if (!spinner) {
        spinner = document.createElement("div");
        spinner.className = "spinner";
        reconnectNotice.appendChild(spinner);
    }

    let message = reconnectNotice.querySelector(".status-message");
    if (!message) {
        message = document.createElement("span");
        message.className = "status-message";
        reconnectNotice.appendChild(message);
    }

    // Connection states
    const states = {
        ONLINE: "online",
        OFFLINE: "offline",
        SLOW: "slow",
        ERROR: "error"
    };

    // Connection status configuration
    const statusConfig = {
        [states.ONLINE]: { display: "none", message: "", spinner: false },
        [states.OFFLINE]: { display: "flex", message: "Offline - No Internet Connection", spinner: true },
        [states.SLOW]: { display: "flex", message: "Slow Connection Detected", spinner: true },
        [states.ERROR]: { display: "flex", message: "Connection Error", spinner: true }
    };

    // Enhanced connection speed test with fallback
    async function testConnectionSpeed() {
        const startTime = performance.now();
        const testUrl = window.location.hostname === 'localhost' 
            ? '/ping' 
            : 'https://speed.cloudflare.com/__down?bytes=1000';
        
        try {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 5000);
            
            await fetch(testUrl, {
                cache: 'no-store',
                mode: 'no-cors',
                signal: controller.signal
            });
            
            clearTimeout(timeout);
            return performance.now() - startTime;
        } catch (error) {
            return Infinity;
        }
    }

    // Update UI with spinner control
    function updateUI(status) {
        const config = statusConfig[status];
        reconnectNotice.style.display = config.display;
        message.textContent = config.message;
        
        // Control spinner visibility and animation
        if (config.spinner) {
            spinner.style.display = 'block';
            spinner.classList.add('active');
        } else {
            spinner.style.display = 'none';
            spinner.classList.remove('active');
        }
        
        reconnectNotice.className = `fade-in ${status}`;
        
        // Ensure visibility on page
        reconnectNotice.style.zIndex = '9999';
        reconnectNotice.style.position = 'fixed';
    }

    // Enhanced network status check
    async function checkNetworkStatus() {
        // Force spinner during check
        spinner.classList.add('active');
        
        if (!navigator.onLine) {
            updateUI(states.OFFLINE);
            return;
        }

        const latency = await testConnectionSpeed();
        
        if (latency === Infinity) {
            updateUI(states.ERROR);
        } else if (latency > 1500) { // Adjusted threshold for better sensitivity
            updateUI(states.SLOW);
        } else {
            updateUI(states.ONLINE);
        }
    }

    // Throttled checking with cleanup
    let lastCheck = 0;
    const CHECK_INTERVAL = 5000;
    let intervalId = null;

    function throttleCheck() {
        const now = Date.now();
        if (now - lastCheck >= CHECK_INTERVAL) {
            lastCheck = now;
            checkNetworkStatus();
        }
    }

    // Event listeners with cleanup
    function setupListeners() {
        window.addEventListener("online", checkNetworkStatus);
        window.addEventListener("offline", checkNetworkStatus);
        window.addEventListener("load", checkNetworkStatus);
        
        // Clear previous interval if exists
        if (intervalId) clearInterval(intervalId);
        intervalId = setInterval(throttleCheck, 1000);
    }

    // Cleanup function
    function cleanup() {
        window.removeEventListener("online", checkNetworkStatus);
        window.removeEventListener("offline", checkNetworkStatus);
        window.removeEventListener("load", checkNetworkStatus);
        if (intervalId) clearInterval(intervalId);
    }

    // Initialize
    setupListeners();
    checkNetworkStatus();

    // Cleanup on page unload
    window.addEventListener('unload', cleanup);
});

// Updated CSS with improved responsiveness
const styles = `
    #reconnect-notice {
        position: fixed;
        top: 1rem;
        left: 50%;
        transform: translateX(-50%);
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        z-index: 9999;
        max-width: 90vw;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: clamp(0.875rem, 2.5vw, 1rem);
        box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.2);
        pointer-events: none;
    }

    .spinner {
        width: clamp(1rem, 3vw, 1.5rem);
        height: clamp(1rem, 3vw, 1.5rem);
        border: 0.2rem solid #fff;
        border-top: 0.2rem solid transparent;
        border-radius: 50%;
        display: none;
    }

    .spinner.active {
        display: block;
        animation: spin 1s linear infinite;
    }

    .status-message {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 70vw;
    }

    .fade-in {
        transition: opacity 0.3s ease, transform 0.3s ease;
        opacity: 0;
        will-change: opacity, transform;
    }

    .fade-in.online { opacity: 0; transform: translateY(-100%); }
    .fade-in.offline { opacity: 1; background: #dc3545; transform: translateY(0); }
    .fade-in.slow { opacity: 1; background: #ffc107; transform: translateY(0); }
    .fade-in.error { opacity: 1; background: #dc3545; transform: translateY(0); }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    @media (max-width: 768px) {
        #reconnect-notice {
            top: 0.5rem;
            padding: 0.5rem 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        #reconnect-notice {
            top: 0;
            left: 0;
            width: 100%;
            transform: none;
            border-radius: 0;
        }
    }
`;

// Inject styles with check for existing
if (!document.querySelector('style[data-network-monitor]')) {
    const styleSheet = document.createElement("style");
    styleSheet.setAttribute('data-network-monitor', 'true');
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);
}
