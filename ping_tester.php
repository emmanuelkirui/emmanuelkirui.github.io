<?php
// Fallback version without exec()
$results = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addresses'])) {
    $input = trim($_POST['addresses']);
    $addressList = array_filter(array_map('trim', explode("\n", $input)));
    
    if (empty($addressList)) {
        $errors[] = "Please enter at least one address to test.";
    } else {
        foreach ($addressList as $entry) {
            if (!empty($entry)) {
                $parts = explode(':', $entry);
                $address = $parts[0];
                $port = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 80;
                
                if (!preg_match('/^[a-zA-Z0-9\.\-]+$/', $address) || $port < 1 || $port > 65535) {
                    $errors[] = "Invalid format: " . htmlspecialchars($entry);
                    continue;
                }
                
                $timeout = 5;
                $startTime = microtime(true);
                $connection = @fsockopen($address, $port, $errno, $errstr, $timeout);
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                $results[$entry] = [
                    'address' => $address,
                    'port' => $port,
                    'status' => $connection ? 'Reachable' : 'Unreachable',
                    'response_time' => $responseTime,
                    'output' => $connection ? "Connected to port $port" : "Failed: $errstr ($errno)"
                ];
                
                if ($connection) fclose($connection);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connection Tester - CreativePulse</title>
    <style>
        /* Same styles as above */
    </style>
</head>
<body>
    <div class="container">
        <h1>Connection Tester - CreativePulse</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-group">
            <label for="addresses">Enter addresses to test (one per line, optional port):</label>
            <textarea name="addresses" id="addresses" placeholder="8.8.8.8:53
google.com:443
facebook.com"><?php echo isset($_POST['addresses']) ? htmlspecialchars($_POST['addresses']) : ''; ?></textarea>
            <div class="example">Example: address:port (e.g., google.com:443), default port is 80</div>
            <button type="submit">Run Test</button>
        </form>

        <?php if (!empty($results)): ?>
            <div class="results">
                <h2>Results</h2>
                <?php foreach ($results as $entry => $result): ?>
                    <div class="result-card">
                        <h3><?php echo htmlspecialchars($result['address']); ?> (Port: <?php echo $result['port']; ?>)</h3>
                        <p>Status: 
                            <span class="status-<?php echo strtolower($result['status']); ?>">
                                <?php echo $result['status']; ?>
                            </span>
                        </p>
                        <p>Response Time: <span class="response-time"><?php echo $result['response_time']; ?> ms</span></p>
                        <pre><?php echo htmlspecialchars($result['output']); ?></pre>
                    </div>
                <?php endforeach; ?>
                <p>Test completed: <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
