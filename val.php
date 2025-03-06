<?php
// API credentials
$user = "emmanuelkirui042";
$token = "d33e93c3e1c101feed7585ee731406b6";
$apiUrl = "https://api.soccersapi.com/v2.2/leagues/?user={$user}&token={$token}&t=list";

// Function to fetch data from API
function getSoccerData($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Get the data
$data = getSoccerData($apiUrl);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soccer API Leagues</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .pricing-info {
            background-color: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .leagues-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .leagues-table th, .leagues-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .leagues-table th {
            background-color: #4CAF50;
            color: white;
        }
        .meta-info {
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>Soccer API Leagues</h1>
    
    <div class="pricing-info">
        <h2>Affordable Price & Free Plan</h2>
        <p>Soccer's API offers the most affordable price out there in relation to the quality and quantity of data. Also we offer a Free Plan Forever which includes three major leagues (A-League, Tipico Bundesliga and SuperLiga).</p>
    </div>

    <?php if (isset($data['data']) && !empty($data['data'])): ?>
        <table class="leagues-table">
            <thead>
                <tr>
                    <th>League Name</th>
                    <th>Country</th>
                    <th>Continent</th>
                    <th>Current Season ID</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['data'] as $league): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($league['name']); ?></td>
                        <td><?php echo htmlspecialchars($league['country_name']); ?> (<?php echo htmlspecialchars($league['cc']); ?>)</td>
                        <td><?php echo htmlspecialchars($league['continent_name']); ?></td>
                        <td><?php echo htmlspecialchars($league['current_season_id']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="meta-info">
            <p>Requests Left: <?php echo $data['meta']['requests_left']; ?></p>
            <p>User: <?php echo htmlspecialchars($data['meta']['user']); ?></p>
            <p>Plan: <?php echo htmlspecialchars($data['meta']['plan']); ?></p>
        </div>
    <?php else: ?>
        <p>Error: Unable to fetch league data from the API.</p>
    <?php endif; ?>
</body>
</html>
