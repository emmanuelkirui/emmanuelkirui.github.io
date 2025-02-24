<?php
// API key from football-data.org
$apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c'; // Replace with your API key

// Base API URL
$baseUrl = 'https://api.football-data.org/v4/';

// Fetch data from API
function fetchData($url, $apiKey) {
    $context = stream_context_create([
        'http' => [
            'header' => "X-Auth-Token: $apiKey\r\n"
        ]
    ]);
    $response = file_get_contents($url, false, $context);
    if ($response === FALSE) {
        die('Error fetching data.');
    }
    return json_decode($response, true);
}

// Get leagues
$leaguesUrl = $baseUrl . 'competitions';
$leaguesData = fetchData($leaguesUrl, $apiKey);
$leagues = [];
foreach ($leaguesData['competitions'] as $comp) {
    if (isset($comp['code'])) {
        $leagues[$comp['code']] = $comp['name'];
    }
}

// Date filter logic
$dateFilter = $_GET['date_filter'] ?? 'today';
switch ($dateFilter) {
    case 'yesterday':
        $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'tomorrow':
        $dateFrom = $dateTo = date('Y-m-d', strtotime('+1 day'));
        break;
    case 'custom':
        $dateFrom = $_GET['custom_date_from'] ?? date('Y-m-d');
        $dateTo = $_GET['custom_date_to'] ?? date('Y-m-d');
        break;
    default: // today
        $dateFrom = $dateTo = date('Y-m-d');
}

// Get selected league (default to Premier League)
$league = $_GET['league'] ?? 'PL';

// Fetch matches
$matchesUrl = "{$baseUrl}matches?competitions={$league}&dateFrom={$dateFrom}&dateTo={$dateTo}";
$matchesData = fetchData($matchesUrl, $apiKey);
$matches = $matchesData['matches'] ?? [];

// Function to calculate expected goals
function calculateExpectedGoals($teamMetrics) {
    return ($teamMetrics['win_ratio'] * 1.2) + ($teamMetrics['avg_goals_scored'] * 1.5) - ($teamMetrics['avg_goals_conceded'] * 0.8);
}

// Function to predict match outcome
function predictMatch($home_metrics, $away_metrics, $advantages) {
    $home_advantage = $advantages['home_advantage'];
    $away_advantage = $advantages['away_advantage'];

    $home_expected_goals = calculateExpectedGoals($home_metrics) + $home_advantage;
    $away_expected_goals = calculateExpectedGoals($away_metrics) + $away_advantage;

    if ($home_expected_goals > $away_expected_goals + 0.5) {
        $result = "Win for Home";
    } elseif ($away_expected_goals > $home_expected_goals + 0.5) {
        $result = "Win for Away";
    } else {
        $result = "Draw";
    }

    return [
        'result' => $result,
        'home_goals' => round($home_expected_goals, 1),
        'away_goals' => round($away_expected_goals, 1)
    ];
}

// Generate predictions
$predictions = [];
foreach ($matches as $match) {
    $homeTeam = $match['homeTeam']['name'];
    $awayTeam = $match['awayTeam']['name'];
    $matchTime = date('H:i', strtotime($match['utcDate']));

    // Mocked data for teams (replace with actual stats)
    $home_metrics = ['win_ratio' => rand(30, 70) / 100, 'avg_goals_scored' => rand(1, 3), 'avg_goals_conceded' => rand(0, 2)];
    $away_metrics = ['win_ratio' => rand(30, 70) / 100, 'avg_goals_scored' => rand(1, 3), 'avg_goals_conceded' => rand(0, 2)];
    $advantages = ['home_advantage' => 0.2, 'away_advantage' => -0.1];

    $prediction = predictMatch($home_metrics, $away_metrics, $advantages);

    $predictions[] = [
        'home' => $homeTeam,
        'away' => $awayTeam,
        'time' => $matchTime,
        'prediction' => $prediction['result'],
        'home_goals' => $prediction['home_goals'],
        'away_goals' => $prediction['away_goals']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Football Match Predictions</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; background: #f4f4f4; padding: 20px; }
        h1 { color: #333; }
        select, input, button { padding: 10px; margin: 5px; }
        table { width: 80%; margin: auto; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background: #333; color: #fff; }
        tr:nth-child(even) { background: #f9f9f9; }
    </style>
</head>
<body>

<h1>🏆 Football Match Predictor</h1>

<form method="GET">
    <label for="date_filter">Date:</label>
    <select name="date_filter" id="date_filter">
        <option value="today" <?= $dateFilter == 'today' ? 'selected' : ''; ?>>Today</option>
        <option value="yesterday" <?= $dateFilter == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
        <option value="tomorrow" <?= $dateFilter == 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
        <option value="custom" <?= $dateFilter == 'custom' ? 'selected' : ''; ?>>Custom</option>
    </select>

    <div id="custom_date" style="<?= $dateFilter == 'custom' ? 'display:block;' : 'display:none;'; ?>">
        <label for="custom_date_from">From:</label>
        <input type="date" name="custom_date_from" value="<?= $dateFrom; ?>">
        <label for="custom_date_to">To:</label>
        <input type="date" name="custom_date_to" value="<?= $dateTo; ?>">
    </div>

    <label for="league">League:</label>
    <select name="league" id="league">
        <?php foreach ($leagues as $code => $name): ?>
            <option value="<?= $code; ?>" <?= $league == $code ? 'selected' : ''; ?>><?= $name; ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Get Predictions</button>
</form>

<h2>📊 Predictions</h2>
<table>
    <tr>
        <th>Match Time</th>
        <th>Home Team</th>
        <th>Away Team</th>
        <th>Prediction</th>
        <th>Expected Goals</th>
    </tr>
    <?php foreach ($predictions as $p): ?>
        <tr>
            <td><?= $p['time']; ?></td>
            <td><?= $p['home']; ?></td>
            <td><?= $p['away']; ?></td>
            <td><?= $p['prediction']; ?></td>
            <td><?= "{$p['home_goals']} - {$p['away_goals']}"; ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<script>
    document.getElementById('date_filter').addEventListener('change', function() {
        document.getElementById('custom_date').style.display = (this.value === 'custom') ? 'block' : 'none';
    });
</script>

</body>
</html>
