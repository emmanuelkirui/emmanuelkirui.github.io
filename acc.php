<?php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$apiKey = 'f8be56e9365110d1887b69f11f3db11c'; // Replace with your API key

// Function to fetch leagues
function fetchLeagues($apiKey) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://v3.football.api-sports.io/leagues?current=true',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'x-apisports-key: ' . $apiKey,
        ),
    ));
    $response = curl_exec($curl);

    if ($response === false) {
        die('Curl error: ' . curl_error($curl));
    }

    curl_close($curl);
    return json_decode($response, true);
}

// Function to fetch league details
function fetchLeagueDetails($leagueId, $apiKey) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://v3.football.api-sports.io/leagues?id=$leagueId",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'x-apisports-key: ' . $apiKey,
        ),
    ));
    $response = curl_exec($curl);

    if ($response === false) {
        die('Curl error: ' . curl_error($curl));
    }

    curl_close($curl);
    return json_decode($response, true);
}

// Fetch leagues
$leagues = fetchLeagues($apiKey);

// Fetch league details if form is submitted
$selectedLeague = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['league_id'])) {
    $leagueId = $_POST['league_id'];
    $selectedLeague = fetchLeagueDetails($leagueId, $apiKey);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Leagues</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        select, button {
            width: 220px;
            padding: 8px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
            transition: border-color 0.3s;
        }
        select:focus, button:focus {
            border-color: #007BFF;
            outline: none;
        }
        button {
            cursor: pointer;
            background-color: #007BFF;
            color: white;
            border: none;
        }
        .league-info {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f1f1f1;
            max-width: 400px;
        }
    </style>
</head>
<body>

<h2>Select a League</h2>
<form method="POST">
    <select name="league_id">
        <?php
        if (isset($leagues['response'])) {
            foreach ($leagues['response'] as $league) {
                echo '<option value="' . $league['league']['id'] . '">' . $league['league']['name'] . '</option>';
            }
        } else {
            echo '<option>No leagues found</option>';
        }
        ?>
    </select>
    <button type="submit">Get League Data</button>
</form>

<?php if ($selectedLeague && isset($selectedLeague['response'][0])): ?>
    <div class="league-info">
        <h3>League Details</h3>
        <p><strong>Name:</strong> <?php echo $selectedLeague['response'][0]['league']['name']; ?></p>
        <p><strong>Country:</strong> <?php echo $selectedLeague['response'][0]['country']['name']; ?></p>
        <p><strong>Season:</strong> <?php echo $selectedLeague['response'][0]['seasons'][0]['year']; ?></p>
        <p><strong>Type:</strong> <?php echo $selectedLeague['response'][0]['league']['type']; ?></p>
        <img src="<?php echo $selectedLeague['response'][0]['league']['logo']; ?>" alt="League Logo" width="100">
    </div>
<?php endif; ?>

</body>
</html>
