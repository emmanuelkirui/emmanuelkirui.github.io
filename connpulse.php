<?php
// PHP Backend Logic
set_time_limit(15);
ini_set('default_socket_timeout', 5);

// Connection status check
function isConnectionActive(): bool {
    return connection_status() === CONNECTION_NORMAL;
}

// Handle AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $startTime = time();
    $maxExecutionTime = 10;
    $progress = 0;

    while ($progress < 100) {
        if (!isConnectionActive()) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Connection lost during processing',
                'progress' => $progress
            ]);
            exit;
        }

        // Simulate progressive task
        $progress += rand(20, 40);
        $progress = min($progress, 100);
        sleep(1);

        // Send progress update
        echo json_encode([
            'status' => 'progress',
            'message' => "Processing: $progress%",
            'progress' => $progress
        ]);
        flush();
        if ($progress < 100) sleep(1);
    }

    // Final success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Processed successfully',
        'progress' => 100
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Connection Handler</title>
    <style>
        .status-container {
            margin: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-family: Arial, sans-serif;
        }
        .success { color: green; }
        .error { color: maroon; background-color: #ffe6e6; padding: 5px; border-radius: 3px; }
        .progress { color: #007bff; }
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            margin-top: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background-color: #007bff;
            transition: width 0.3s ease-in-out;
        }
        .log-container {
            margin-top: 10px;
            max-height: 200px;
            overflow-y: auto;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="status-container">
        <div id="status-output">Initializing...</div>
        <div class="progress-bar">
            <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
        </div>
        <div class="log-container" id="log-container"></div>
    </div>

    <script>
        class DynamicConnectionHandler {
            constructor() {
                this.timeout = 8000;
                this.retryAttempts = 3;
                this.retryDelay = 2000;
                this.currentAttempt = 0;
                this.isProcessing = false;
                this.outputElement = document.getElementById('status-output');
                this.progressElement = document.getElementById('progress-fill');
                this.logElement = document.getElementById('log-container');
            }

            /**
             * Add log entry with timestamp
             */
            addLog(message) {
                const timestamp = new Date().toLocaleTimeString();
                this.logElement.innerHTML += `<div>[${timestamp}] ${message}</div>`;
                this.logElement.scrollTop = this.logElement.scrollHeight;
            }

            /**
             * Update UI with status and progress
             */
            updateUI(status, message, progress = 0) {
                this.outputElement.className = status;
                this.outputElement.textContent = message;
                this.progressElement.style.width = `${progress}%`;
                this.addLog(message);
            }

            /**
             * Process the request with retry mechanism
             */
            async processRequest() {
                if (this.isProcessing) return;
                this.isProcessing = true;
                this.currentAttempt = 0;

                while (this.currentAttempt < this.retryAttempts) {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), this.timeout);

                    try {
                        const response = await fetch('?ajax=1', {
                            method: 'POST',
                            signal: controller.signal,
                            headers: { 'Content-Type': 'application/json' }
                        });

                        clearTimeout(timeoutId);
                        const data = await response.json();
                        this.updateUI(data.status, data.message, data.progress);

                        if (data.status === 'success') {
                            this.isProcessing = false;
                            return;
                        }

                    } catch (error) {
                        this.currentAttempt++;
                        if (this.currentAttempt < this.retryAttempts) {
                            this.updateUI('error', 'Connection lost. Retrying...');
                            await new Promise(resolve => setTimeout(resolve, this.retryDelay));
                            continue;
                        }
                        this.updateUI('error', 'Connection lost after all attempts');
                        this.isProcessing = false;
                        return;
                    }
                }
            }

            /**
             * Monitor network status and auto-start processing
             */
            monitorAndStart() {
                window.addEventListener('offline', () => {
                    this.updateUI('error', 'Network connection lost', 0);
                });
                window.addEventListener('online', () => {
                    this.updateUI('', 'Network connection restored');
                    this.processRequest();
                });
                // Start processing immediately
                this.processRequest();
                // Set up periodic checking
                setInterval(() => {
                    if (!this.isProcessing) this.processRequest();
                }, 15000);
            }
        }

        // Initialize and start
        const handler = new DynamicConnectionHandler();
        handler.monitorAndStart();
    </script>
</body>
</html>
