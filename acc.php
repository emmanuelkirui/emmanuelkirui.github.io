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
// Function to fetch standings data
function fetchStandingsData($leagueId) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://v3.football.api-sports.io/standings?league=' . $leagueId . '&season=2023',
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

// Set default date values
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Check if form is submitted
$selectedFixturesData = null;
$selectedStandingsData = null;
$selectedLeagueId = null;
$fromDate = $today;
$toDate = $today;
$dateOption = 'today';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['league_id'])) {
    $selectedLeagueId = $_POST['league_id'];
    $dateOption = $_POST['date_option'];

    switch ($dateOption) {
        case 'yesterday':
            $fromDate = $yesterday;
            $toDate = $yesterday;
            break;
        case 'today':
            $fromDate = $today;
            $toDate = $today;
            break;
        case 'tomorrow':
            $fromDate = $tomorrow;
            $toDate = $tomorrow;
            break;
        case 'custom':
            $fromDate = $_POST['from_date'];
            $toDate = $_POST['to_date'];
            break;
    }

    $selectedFixturesData = fetchFixturesData($selectedLeagueId, $fromDate, $toDate);
    $selectedStandingsData = fetchStandingsData($selectedLeagueId);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Leagues Fixtures and Standings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        select, button, input[type="date"] {
            width: 220px;
            padding: 8px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
            transition: border-color 0.3s;
            margin-bottom: 10px;
        }
        select:focus, button:focus, input[type="date"]:focus {
            border-color: #007BFF;
            outline: none;
        }
        button {
            cursor: pointer;
            background-color: #007BFF;
            color: white;
            border: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
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
    <br>
    <select name="date_option" onchange="toggleCustomDateFields(this.value)">
        <option value="yesterday" <?php echo $dateOption === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
        <option value="today" <?php echo $dateOption === 'today' ? 'selected' : ''; ?>>Today</option>
        <option value="tomorrow" <?php echo $dateOption === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
        <option value="custom" <?php echo $dateOption === 'custom' ? 'selected' : ''; ?>>Custom</option>
    </select>
    <br>
    <div id="customDateFields" style="display: <?php echo $dateOption === 'custom' ? 'block' : 'none'; ?>;">
        <label for="from_date">From Date:</label>
        <input type="date" name="from_date" value="<?php echo $fromDate; ?>">
        <br>
        <label for="to_date">To Date:</label>
        <input type="date" name="to_date" value="<?php echo $toDate; ?>">
        <br>
    </div>
    <button type="submit">Get Data</button>
</form>

<script>
function toggleCustomDateFields(value) {
    const customDateFields = document.getElementById('customDateFields');
    if (value === 'custom') {
        customDateFields.style.display = 'block';
    } else {
        customDateFields.style.display = 'none';
    }
}
</script>
    <?php
if ($selectedFixturesData) {
    if (empty($selectedFixturesData['response'])) {
        echo '<p>No fixtures data available for the selected league and date range. Please try a different league or check your API plan.</p>';
    } else {
        echo '<h2>Fixtures Data:</h2>';
        echo '<table>';
        echo '<tr><th>Date</th><th>Home Team</th><th>Away Team</th><th>Score</th><th>Status</th></tr>';
        foreach ($selectedFixturesData['response'] as $fixture) {
            $date = date('Y-m-d H:i', strtotime($fixture['fixture']['date']));
            $homeTeam = $fixture['teams']['home']['name'];
            $awayTeam = $fixture['teams']['away']['name'];
            $homeScore = $fixture['goals']['home'] !== null ? $fixture['goals']['home'] : '-';
            $awayScore = $fixture['goals']['away'] !== null ? $fixture['goals']['away'] : '-';
            $status = $fixture['fixture']['status']['long'];
            echo "<tr><td>$date</td><td>$homeTeam</td><td>$awayTeam</td><td>$homeScore - $awayScore</td><td>$status</td></tr>";
        }
        echo '</table>';
    }
}

if ($selectedStandingsData) {
    if (empty($selectedStandingsData['response'])) {
        echo '<p>No standings data available for the selected league. Please try a different league or check your API plan.</p>';
    } else {
        echo '<h2>Standings Data:</h2>';
        echo '<table>';
        echo '<tr><th>Position</th><th>Team</th><th>Points</th><th>Played</th><th>Won</th><th>Drawn</th><th>Lost</th></tr>';
        foreach ($selectedStandingsData['response'][0]['league']['standings'][0] as $standing) {
            $position = $standing['rank'];
            $team = $standing['team']['name'];
            $points = $standing['points'];
            $played = $standing['all']['played'];
            $won = $standing['all']['win'];
            $drawn = $standing['all']['draw'];
            $lost = $standing['all']['lose'];
            echo "<tr><td>$position</td><td>$team</td><td>$points</td><td>$played</td><td>$won</td><td>$drawn</td><td>$lost</td></tr>";
        }
        echo '</table>';
    }
}
?>

</body>
</html>
