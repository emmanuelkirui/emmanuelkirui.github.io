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
    document.body.insertBefore(reconnectNotice, document.body.firstChild); // Place at top of body

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
            return performance.now() - startTime;
        } catch (error) {
            return Infinity; // Indicates error
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
        } else if (latency > 1000) { // Threshold of 1 second for slow connection
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

    // Inject CSS into the document
    const styles = `
        #reconnect-notice {
            position: fixed;
            top: 0.5rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            background: rgba(0, 0, 0, 0.85);
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 2000;
            min-width: 240px;
            max-width: 90vw;
            font-size: clamp(0.875rem, 2vw, 1rem);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.2);
            pointer-events: none;
            overflow: hidden;
        }

        .spinner {
            width: clamp(1rem, 2vw, 1.25rem);
            height: clamp(1rem, 2vw, 1.25rem);
            min-width: 16px;
            min-height: 16px;
            border: 0.2rem solid #fff;
            border-top: 0.2rem solid transparent;
            border-radius: 50%;
            display: none;
            flex-shrink: 0;
            order: 1;
        }

        .spinner.active {
            display: block;
            animation: spin 1s linear infinite;
        }

        .status-message {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: calc(90vw - 48px);
            min-width: 0;
            flex-grow: 1;
            order: 2;
        }

        .fade-in {
            transition: opacity 0.3s ease;
            opacity: 0;
        }

        .fade-in.online { opacity: 0; }
        .fade-in.offline { opacity: 1; background: #ff3860; }
        .fade-in.slow { opacity: 1; background: #ffdd57; color: #000; }
        .fade-in.error { opacity: 1; background: #ff3860; }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        section.section #reconnect-notice {
            top: 4rem;
        }

        .table-container #reconnect-notice {
            position: fixed;
            top: 0.5rem;
            left: 50%;
            transform: translateX(-50%);
            width: fit-content;
            min-width: 240px;
            max-width: 90%;
        }

        @media (max-width: 768px) {
            #reconnect-notice {
                top: 0;
                padding: 0.5rem 0.75rem;
                min-width: 200px;
                flex-wrap: nowrap;
                justify-content: flex-start;
            }
            
            .spinner {
                margin-right: 0.5rem;
            }
            
            .status-message {
                max-width: calc(85vw - 40px);
            }
        }

        @media (max-width: 480px) {
            #reconnect-notice {
                width: 100%;
                max-width: 100vw;
                min-width: 180px;
                left: 0;
                transform: none;
                border-radius: 0;
            }
            
            .status-message {
                max-width: calc(85vw - 36px);
            }
            
            .spinner {
                min-width: 14px;
                min-height: 14px;
            }
        }

        @media (min-width: 1024px) {
            #reconnect-notice {
                min-width: 260px;
                padding: 1rem 1.5rem;
            }
            
            .spinner {
                min-width: 18px;
                min-height: 18px;
            }
        }
    `;

    // Create and append the style element
    const styleSheet = document.createElement("style");
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);
});
