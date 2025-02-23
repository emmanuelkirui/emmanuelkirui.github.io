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
            'x-apisports-key: f8be56e9365110d1887b69f11f3db11c', // Replace with your API key
        ),
    ));
    $response = curl_exec($curl);

    if ($response === false) {
        die('Curl error: ' . curl_error($curl));
    }

    curl_close($curl);
    return json_decode($response, true);
}

// Function to fetch league data
function fetchLeagueData($leagueId) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://v3.football.api-sports.io/standings?league=' . $leagueId . '&season=2024',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'x-apisports-key: f8be56e9365110d1887b69f11f3db11c', // Replace with your API key
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
$leagues = fetchLeagues();

// Handle form submission
$selectedLeagueData = null;
$selectedLeagueId = $_POST['league_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($selectedLeagueId)) {
    $selectedLeagueData = fetchLeagueData($selectedLeagueId);
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
        .league-container {
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table, th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #007BFF;
            color: white;
        }
    </style>
</head>
<body>

<form method="POST">
    <select name="league_id">
        <option value="">Select a League</option>
        <?php
        if (!empty($leagues['response'])) {
            foreach ($leagues['response'] as $league) {
                $leagueId = $league['league']['id'];
                $leagueName = $league['league']['name'];
                $selected = ($leagueId == $selectedLeagueId) ? 'selected' : '';
                echo "<option value=\"$leagueId\" $selected>$leagueName</option>";
            }
        } else {
            echo '<option>No leagues found</option>';
        }
        ?>
    </select>
    <button type="submit">Get League Data</button>
</form>

<?php
if (!empty($selectedLeagueData) && isset($selectedLeagueData['response'][0]['league']['standings'][0])) {
    echo '<div class="league-container">';
    echo '<h2>League Standings</h2>';
    echo '<table>';
    echo '<tr><th>Rank</th><th>Team</th><th>Points</th></tr>';

    foreach ($selectedLeagueData['response'][0]['league']['standings'][0] as $team) {
        echo '<tr>';
        echo '<td>' . $team['rank'] . '</td>';
        echo '<td>' . $team['team']['name'] . '</td>';
        echo '<td>' . $team['points'] . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</div>';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<p>No data found for the selected league.</p>';
}
?>

</body>
</html>
