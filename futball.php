<?php
// Set the timezone to East Africa Time
date_default_timezone_set('Africa/Nairobi'); // EAT (UTC+3)

// API Configuration
$apiBaseUrl = "https://api.football-data.org/v4";
$apiKey = "d2ef1a157a0d4c83ba4023d1fbd28b5c"; // Replace with your API key

// Function to fetch competitions from the API
function fetchCompetitions() {
    global $apiBaseUrl, $apiKey;
    $apiUrl = "$apiBaseUrl/competitions";
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

// Function to fetch fixtures from the API
function fetchFixtures($competitionCode, $dateFrom = null, $dateTo = null) {
    global $apiBaseUrl, $apiKey;
    $apiUrl = "$apiBaseUrl/competitions/$competitionCode/matches";
    if ($dateFrom && $dateTo) {
        $apiUrl .= "?dateFrom=$dateFrom&dateTo=$dateTo";
    }
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

// Fetch competitions for the dropdown
$competitions = fetchCompetitions();
$competitionsList = [];
foreach ($competitions['competitions'] as $competition) {
    if (isset($competition['name'], $competition['code'])) {
        $competitionsList[$competition['code'] = $competition['name'];
    }
}

// Handle form submission
$selectedCompetition = $_GET['competition'] ?? 'PL'; // Default to Premier League
$dateFilter = $_GET['date_filter'] ?? 'all'; // Default to all dates
$customDate = $_GET['custom_date'] ?? '';

// Calculate date range based on filter
$dateFrom = $dateTo = null;
switch ($dateFilter) {
    case 'yesterday':
        $dateFrom = date('Y-m-d', strtotime('-1 day'));
        $dateTo = $dateFrom;
        break;
    case 'today':
        $dateFrom = date('Y-m-d');
        $dateTo = $dateFrom;
        break;
    case 'tomorrow':
        $dateFrom = date('Y-m-d', strtotime('+1 day'));
        $dateTo = $dateFrom;
        break;
    case 'custom':
        if (!empty($customDate)) {
            $dateFrom = $customDate;
            $dateTo = $customDate;
        }
        break;
}

// Fetch fixtures based on selected competition and date filter
$fixtures = fetchFixtures($selectedCompetition, $dateFrom, $dateTo);
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
        .custom-date-input {
            display: none;
        }
    </style>
    <script>
        function toggleCustomDateInput() {
            const dateFilter = document.getElementById('date_filter').value;
            const customDateInput = document.getElementById('custom_date_input');
            customDateInput.style.display = (dateFilter === 'custom') ? 'block' : 'none';
        }

        function reloadPage() {
            const competition = document.getElementById('competition').value;
            const dateFilter = document.getElementById('date_filter').value;
            const customDate = document.getElementById('custom_date').value;

            if (dateFilter === 'custom' && customDate) {
                window.location.href = `?competition=${competition}&date_filter=custom&custom_date=${customDate}`;
            } else {
                window.location.href = `?competition=${competition}&date_filter=${dateFilter}`;
            }
        }
    </script>
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Football Predictions</h1>
            <h2 class="subtitle">Predictions Based on Shared Opponents</h2>

            <!-- Filters Form -->
            <form onchange="reloadPage()">
                <div class="field">
                    <label class="label">Select Competition:</label>
                    <div class="control">
                        <div class="select">
                            <select id="competition" name="competition">
                                <?php foreach ($competitionsList as $code => $name): ?>
                                    <option value="<?= $code; ?>" <?= ($selectedCompetition == $code) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Select Date:</label>
                    <div class="control">
                        <div class="select">
                            <select id="date_filter" name="date_filter" onchange="toggleCustomDateInput()">
                                <option value="all" <?= ($dateFilter == 'all') ? 'selected' : ''; ?>>All</option>
                                <option value="yesterday" <?= ($dateFilter == 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                                <option value="today" <?= ($dateFilter == 'today') ? 'selected' : ''; ?>>Today</option>
                                <option value="tomorrow" <?= ($dateFilter == 'tomorrow') ? 'selected' : ''; ?>>Tomorrow</option>
                                <option value="custom" <?= ($dateFilter == 'custom') ? 'selected' : ''; ?>>Custom</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="field custom-date-input" id="custom_date_input" style="display: <?= ($dateFilter === 'custom') ? 'block' : 'none'; ?>;">
                    <label class="label">Pick a Date:</label>
                    <div class="control">
                        <input class="input" type="date" id="custom_date" name="custom_date" value="<?= $customDate; ?>">
                    </div>
                </div>
            </form>

            <!-- Fixtures Table -->
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
                <div class="notification is-warning">No fixtures found for the selected filter.</div>
            <?php endif; ?>
        </div>
    </section>
</body>
</html>
