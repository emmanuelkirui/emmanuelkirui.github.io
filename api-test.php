<?php
$urls = [
    'Football-Data API' => 'https://api.football-data.org/v4/competitions/SA/scorers',
    'Google Test' => 'https://www.google.com'
];
$token = 'd2ef1a157a0d4c83ba4023d1fbd28b5c';

foreach ($urls as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($name === 'Football-Data API') {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Auth-Token: ' . $token]);
    }
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    fclose($verbose);

    curl_close($ch);

    // Output for each test
    echo "<h1>$name Test</h1>";
    echo "<p><strong>URL:</strong> " . htmlspecialchars($url) . "</p>";
    echo "<p><strong>HTTP Status Code:</strong> $httpCode</p>";
    if ($error) {
        echo "<p><strong>cURL Error ($errno):</strong> " . htmlspecialchars($error) . "</p>";
    }
    echo "<h2>Response:</h2>";
    if ($httpCode == 200) {
        if ($name === 'Football-Data API') {
            $json = json_decode($body);
            echo "<pre>" . ($json ? json_encode($json, JSON_PRETTY_PRINT) : "Invalid JSON") . "</pre>";
        } else {
            echo "<pre>" . htmlspecialchars($body) . "</pre>"; // Handle HTML for Google
        }
    } else {
        echo "<p><strong>Error Response:</strong> " . htmlspecialchars($body) . "</p>";
        echo "<p><strong>Headers:</strong></p><pre>" . htmlspecialchars($headers) . "</pre>";
    }
    echo "<h2>Verbose Log:</h2><pre>" . htmlspecialchars($verboseLog) . "</pre>";
}
?>
<!DOCTYPE html>
<html>
<head><title>API Test</title></head>
<body>
</body>
</html>
