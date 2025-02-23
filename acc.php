<?php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to fetch leagues
function fetchLeagues() {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://v3.football.api-sports.io/leagues?current=true',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'x-apisports-key: f8be56e9365110d1887b69f11f3db11c', // Your actual API key
        ),
    ));
    $response = curl_exec($curl);

    // Check for cURL errors
    if ($response === false) {
        die('Curl error: ' . curl_error($curl));
    }

    curl_close($curl);
    return json_decode($response, true);
}
// Function to fetch fixtures data
function fetchFixturesData($leagueId) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://v3.football.api-sports.io/fixtures?league=' . $leagueId . '&season=2023', // Use a supported season
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'x-apisports-key: f8be56e9365110d1887b69f11f3db11c', // Your actual API key
        ),
    ));
    $response = curl_exec($curl);

    // Check for cURL errors
    if ($response === false) {
        die('Curl error: ' . curl_error($curl));
    }

    curl_close($curl);
    return json_decode($response, true);
}

// Fetch leagues
$leagues = fetchLeagues();

// Check if form is submitted
$selectedFixturesData = null;
$selectedLeagueId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['league_id'])) {
    $selectedLeagueId = $_POST['league_id'];
    $selectedFixturesData = fetchFixturesData($selectedLeagueId);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Leagues Dropdown</title>
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
    </style>
</head>
<body>
<form method="POST">
    <select name="league_id">
        <?php
        if (isset($leagues['response'])) {
            foreach ($leagues['response'] as $league) {
                $isSelected = ($league['league']['id'] == $selectedLeagueId) ? 'selected' : '';
                echo '<option value="' . $league['league']['id'] . '" ' . $isSelected . '>' . $league['league']['name'] . '</option>';
            }
        } else {
            echo '<option>No leagues found</option>';
        }
        ?>
    </select>
    <button type="submit">Get Fixtures Data</button>
</form>

<?php
if ($selectedFixturesData) {
    if (empty($selectedFixturesData['response'])) {
        echo '<p>No fixtures data available for the selected league. Please try a different league or check your API plan.</p>';
    } else {
        echo '<h2>Fixtures Data:</h2>';
        echo '<pre>';
        print_r($selectedFixturesData);
        echo '</pre>';
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo '<p>No data found for the selected league or an error occurred.</p>';
    }
}
?>
</body>
</html>
