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
            message: "",
            spinner: false
        },
        [states.OFFLINE]: { 
            display: "flex", 
            message: "Offline - No Internet Connection",
            spinner: true
        },
        [states.SLOW]: { 
            display: "flex", 
            message: "Slow Connection Detected",
            spinner: true
        },
        [states.ERROR]: { 
            display: "flex", 
            message: "Connection Error",
            spinner: true
        }
    };

    // Measure connection speed (in ms)
    async function testConnectionSpeed() {
        const startTime = performance.now();
        try {
            await fetch('https://speed.cloudflare.com/__down?bytes=1000', { 
                cache: 'no-store',
                mode: 'no-cors'
            });
            const endTime = performance.now();
            return endTime - startTime;
        } catch (error) {
            return Infinity;
        }
    }

    // Update UI based on status
    function updateUI(status) {
        const config = statusConfig[status];
        reconnectNotice.style.display = config.display;
        message.textContent = config.message;
        
        // Spinner control
        if (config.spinner) {
            spinner.style.display = 'block';
            spinner.classList.add('active');
        } else {
            spinner.style.display = 'none';
            spinner.classList.remove('active');
        }
        
        reconnectNotice.className = `fade-in ${status}`;
    }

    // Main network status check
    async function checkNetworkStatus() {
        spinner.classList.add('active');
        
        if (!navigator.onLine) {
            updateUI(states.OFFLINE);
            return;
        }

        const latency = await testConnectionSpeed();
        
        if (latency === Infinity) {
            updateUI(states.ERROR);
        } else if (latency > 1000) {
            updateUI(states.SLOW);
        } else {
            updateUI(states.ONLINE);
        }
    }

    // Periodic checking
    let lastCheck = 0;
    const CHECK_INTERVAL = 5000;

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
    
    setInterval(throttleCheck, 1000);

    // Initial check
    checkNetworkStatus();
});

// Updated CSS to handle long text and stabilize spinner
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
        align-items: center;
        gap: 0.75rem;
        z-index: 1000;
        min-width: 220px;
        max-width: 90vw;
        font-size: clamp(0.875rem, 2.5vw, 1rem);
        box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.2);
        display: flex;
        pointer-events: none;
        overflow: hidden; /* Prevents content from spilling out */
    }

    .spinner {
        width: clamp(1rem, 2.5vw, 1.5rem);
        height: clamp(1rem, 2.5vw, 1.5rem);
        min-width: 16px;
        min-height: 16px;
        border: 0.2rem solid #fff;
        border-top: 0.2rem solid transparent;
        border-radius: 50%;
        display: none;
        flex-shrink: 0;
        order: 1; /* Ensures spinner stays on the left */
    }

    .spinner.active {
        display: block;
        animation: spin 1s linear infinite;
    }

    .status-message {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: calc(90vw - 40px); /* Space for spinner and padding */
        min-width: 0; /* Allows proper flex shrinking */
        flex-grow: 1;
        order: 2; /* Ensures message follows spinner */
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

    /* Card layout adjustments */
    .card #reconnect-notice,
    .card-container #reconnect-notice,
    [class*="card"] #reconnect-notice {
        position: absolute;
        top: 0.5rem;
        left: 0;
        right: 0;
        margin: 0 auto;
        width: fit-content;
        min-width: 220px;
        max-width: 90%;
        transform: none;
        padding: 0.5rem 1rem;
        overflow: hidden;
    }

    /* Tablet and below */
    @media (max-width: 768px) {
        #reconnect-notice {
            top: 0.5rem;
            padding: 0.5rem 1rem;
            flex-wrap: nowrap; /* Prevents wrapping to keep spinner stable */
            justify-content: flex-start;
            gap: 0.5rem;
            min-width: 200px;
        }
        
        .spinner {
            margin-right: 0.5rem; /* Consistent spacing */
        }
    }

    /* Mobile and card-specific mobile */
    @media (max-width: 480px) {
        #reconnect-notice {
            top: 0;
            left: 0;
            transform: none;
            width: 100%;
            max-width: 100vw;
            min-width: 180px;
            border-radius: 0;
            padding: 0.5rem;
            font-size: 0.875rem;
        }
        
        .card #reconnect-notice,
        .card-container #reconnect-notice,
        [class*="card"] #reconnect-notice {
            width: 100%;
            min-width: 180px;
            padding: 0.5rem;
        }
        
        .status-message {
            max-width: calc(85vw - 30px);
        }
        
        .spinner {
            min-width: 14px;
            min-height: 14px;
        }
    }

    /* Large screens */
    @media (min-width: 1200px) {
        #reconnect-notice {
            padding: 1rem 2rem;
            gap: 1rem;
            min-width: 250px;
        }
        
        .spinner {
            width: clamp(1.5rem, 2vw, 1.75rem);
            height: clamp(1.5rem, 2vw, 1.75rem);
            min-width: 20px;
            min-height: 20px;
        }
    }
`;

const styleSheet = document.createElement("style");
styleSheet.textContent = styles;
document.head.appendChild(styleSheet);
