<?php
$url = 'https://api.football-data.org/v4/competitions/SA/scorers';
$token = 'd2ef1a157a0d4c83ba4023d1fbd28b5c';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20); // Increased to 20 sec
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Auth-Token: ' . $token]);
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
?>
<!DOCTYPE html>
<html>
<head><title>API Test</title></head>
<body>
    <h1>Football-Data API Test</h1>
    <p><strong>URL:</strong> <?php echo htmlspecialchars($url); ?></p>
    <p><strong>HTTP Status Code:</strong> <?php echo $httpCode; ?></p>
    <?php if ($error): ?>
        <p><strong>cURL Error (<?php echo $errno; ?>):</strong> <?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <h2>Response:</h2>
    <?php if ($httpCode == 200): ?>
        <pre><?php echo json_encode(json_decode($body), JSON_PRETTY_PRINT); ?></pre>
    <?php else: ?>
        <p><strong>Error Response:</strong> <?php echo htmlspecialchars($body); ?></p>
        <p><strong>Headers:</strong></p>
        <pre><?php echo htmlspecialchars($headers); ?></pre>
    <?php endif; ?>
    <h2>Verbose Log:</h2>
    <pre><?php echo htmlspecialchars($verboseLog); ?></pre>
</body>
</html>
