<?php
// Set the timezone to East Africa Time
date_default_timezone_set('Africa/Nairobi'); // EAT (UTC+3)

// API Configuration
$apiBaseUrl = "https://api.football-data.org/v4";
$apiKey = "d2ef1a157a0d4c83ba4023d1fbd28b5c"; // Replace with your API key

// Function to fetch fixtures from the API
function fetchFixtures($competitionCode = 'PL') {
    global $apiBaseUrl, $apiKey;
    $apiUrl = "$apiBaseUrl/competitions/$competitionCode/matches";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Auth-Token: $apiKey"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Function to fetch historical matches for a team
function fetchTeamMatches($teamId) {
    global $apiBaseUrl, $apiKey;
    $apiUrl = "$apiBaseUrl/teams/$teamId/matches";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Auth-Token: $apiKey"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Function to identify common opponents
function getCommonOpponents($homeTeamId, $awayTeamId) {
    $homeMatches = fetchTeamMatches($homeTeamId);
    $awayMatches = fetchTeamMatches($awayTeamId);

    $homeOpponents = [];
    foreach ($homeMatches['matches'] as $match) {
        if ($match['homeTeam']['id'] == $homeTeamId) {
            $homeOpponents[] = $match['awayTeam']['id'];
        } else {
            $homeOpponents[] = $match['homeTeam']['id'];
        }
    }

    $awayOpponents = [];
    foreach ($awayMatches['matches'] as $match) {
        if ($match['homeTeam']['id'] == $awayTeamId) {
            $awayOpponents[] = $match['awayTeam']['id'];
        } else {
            $awayOpponents[] = $match['homeTeam']['id'];
        }
    }

    return array_intersect($homeOpponents, $awayOpponents);
}

// Function to analyze performance against common opponents
function analyzePerformance($teamId, $opponentIds) {
    $matches = fetchTeamMatches($teamId);
    $performance = ['wins' => 0, 'draws' => 0, 'losses' => 0];

    foreach ($matches['matches'] as $match) {
        if (in_array($match['homeTeam']['id'], $opponentIds) || in_array($match['awayTeam']['id'], $opponentIds)) {
            if ($match['score']['winner'] == $teamId) {
                $performance['wins']++;
            } elseif ($match['score']['winner'] == 'DRAW') {
                $performance['draws']++;
            } else {
                $performance['losses']++;
            }
        }
    }

    return $performance;
}

// Function to predict match outcome based on shared opponents
function predictMatch($homeTeamId, $awayTeamId) {
    $commonOpponents = getCommonOpponents($homeTeamId, $awayTeamId);
    if (empty($commonOpponents)) {
        return "No common opponents found for prediction.";
    }

    $homePerformance = analyzePerformance($homeTeamId, $commonOpponents);
    $awayPerformance = analyzePerformance($awayTeamId, $commonOpponents);

    $homeScore = $homePerformance['wins'] * 3 + $homePerformance['draws'];
    $awayScore = $awayPerformance['wins'] * 3 + $awayPerformance['draws'];

    if ($homeScore > $awayScore) {
        return "Home team is predicted to win based on shared opponents.";
    } elseif ($awayScore > $homeScore) {
        return "Away team is predicted to win based on shared opponents.";
    } else {
        return "The match is predicted to be a draw based on shared opponents.";
    }
}

// Fetch fixtures
$fixtures = fetchFixtures();
$matches = $fixtures['matches'] ?? [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Predictions</title>
    <link rel="stylesheet" href="css/bulma.min.css"> <!-- Bulma CSS -->
    <style>
        body {
            padding: 20px;
        }
        .prediction-result {
            margin-top: 20px;
            padding: 20px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .fixture-table {
            width: 100%;
            margin-top: 20px;
        }
        .fixture-table th, .fixture-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
    </style>
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Football Predictions</h1>
            <h2 class="subtitle">Predictions Based on Shared Opponents</h2>

            <?php if (!empty($matches)): ?>
                <table class="fixture-table">
                    <thead>
                        <tr>
                            <th>Home Team</th>
                            <th>Away Team</th>
                            <th>Match Date</th>
                            <th>Prediction</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matches as $match): ?>
                            <?php
                                $homeTeam = $match['homeTeam'];
                                $awayTeam = $match['awayTeam'];
                                $homeTeamId = $homeTeam['id'];
                                $awayTeamId = $awayTeam['id'];
                                $matchDate = date('Y-m-d H:i', strtotime($match['utcDate']));
                                $prediction = predictMatch($homeTeamId, $awayTeamId);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($homeTeam['name']); ?></td>
                                <td><?= htmlspecialchars($awayTeam['name']); ?></td>
                                <td><?= $matchDate; ?></td>
                                <td><?= $prediction; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="notification is-warning">No fixtures found.</div>
            <?php endif; ?>
        </div>
    </section>
</body>
</html>
