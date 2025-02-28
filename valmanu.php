<?php
session_start(); // Start session for caching
if (!isset($_SESSION['teamStats'])) $_SESSION['teamStats'] = [];

// API configuration
$apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c'; // Replace with your football-data.org API key
$baseUrl = 'http://api.football-data.org/v4/';

// Fetch available competitions
$competitionsUrl = $baseUrl . 'competitions';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $competitionsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $apiKey"]);
$compResponse = curl_exec($ch);
curl_close($ch);
$competitions = json_decode($compResponse, true)['competitions'] ?? [];

// Handle user selections
$selectedComp = $_GET['competition'] ?? ($competitions[0]['code'] ?? 'PL');
$filter = $_GET['filter'] ?? 'today';
$customStart = $_GET['start'] ?? '';
$customEnd = $_GET['end'] ?? '';
$showPast = isset($_GET['showPast']) ? true : false;

// Date handling for upcoming matches
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$fromDate = $toDate = $today;

switch ($filter) {
    case 'yesterday':
        $fromDate = $toDate = $yesterday;
        break;
    case 'tomorrow':
        $fromDate = $toDate = $tomorrow;
        break;
    case 'custom':
        $fromDate = $customStart ?: $today;
        $toDate = $customEnd ?: $today;
        break;
}

// Fetch upcoming matches for selected competition
$matchesUrl = $baseUrl . "competitions/$selectedComp/matches?dateFrom=$fromDate&dateTo=$toDate";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $matchesUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $apiKey"]);
$matchResponse = curl_exec($ch);
curl_close($ch);
$upcomingMatches = json_decode($matchResponse, true)['matches'] ?? [];

// Team stats caching and fetching
$teamStats = &$_SESSION['teamStats'];
function fetchTeamResults($teamId, $apiKey, $baseUrl) {
    $pastDate = date('Y-m-d', strtotime('-30 days'));
    $url = $baseUrl . "teams/$teamId/matches?dateFrom=$pastDate&dateTo=" . date('Y-m-d') . "&limit=5&status=FINISHED";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $apiKey"]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['matches'] ?? [];
}

function calculateTeamStrength($teamId, $apiKey, $baseUrl, &$teamStats) {
    if (!isset($teamStats[$teamId])) {
        $results = fetchTeamResults($teamId, $apiKey, $baseUrl);
        $stats = ['wins' => 0, 'draws' => 0, 'goalsScored' => 0, 'goalsConceded' => 0, 'games' => 0, 'results' => []];
        
        foreach ($results as $match) {
            $homeId = $match['homeTeam']['id'];
            $awayId = $match['awayTeam']['id'];
            $homeGoals = $match['score']['fullTime']['home'] ?? 0;
            $awayGoals = $match['score']['fullTime']['away'] ?? 0;
            $date = date('M d', strtotime($match['utcDate']));
            $resultStr = "{$match['homeTeam']['name']} $homeGoals - $awayGoals {$match['awayTeam']['name']}";

            if ($teamId == $homeId) {
                $stats['goalsScored'] += $homeGoals;
                $stats['goalsConceded'] += $awayGoals;
                if ($homeGoals > $awayGoals) $stats['wins']++;
                elseif ($homeGoals == $awayGoals) $stats['draws']++;
                $stats['results'][] = "$date: $resultStr";
            } elseif ($teamId == $awayId) {
                $stats['goalsScored'] += $awayGoals;
                $stats['goalsConceded'] += $homeGoals;
                if ($awayGoals > $homeGoals) $stats['wins']++;
                elseif ($homeGoals == $awayGoals) $stats['draws']++;
                $stats['results'][] = "$date: $resultStr";
            }
            $stats['games']++;
        }
        $teamStats[$teamId] = $stats;
    }
    return $teamStats[$teamId];
}

// Advanced prediction logic with draws
function predictMatch($match, $apiKey, $baseUrl, &$teamStats) {
    $homeTeamId = $match['homeTeam']['id'] ?? 0;
    $awayTeamId = $match['awayTeam']['id'] ?? 0;
    $homeTeam = $match['homeTeam']['name'] ?? 'TBD';
    $awayTeam = $match['awayTeam']['name'] ?? 'TBD';

    if (!$homeTeamId || !$awayTeamId) return ["N/A", "0%"];

    $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats);
    $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats);

    $homeWinRate = $homeStats['games'] ? $homeStats['wins'] / $homeStats['games'] : 0;
    $homeDrawRate = $homeStats['games'] ? $homeStats['draws'] / $homeStats['games'] : 0;
    $awayWinRate = $awayStats['games'] ? $awayStats['wins'] / $awayStats['games'] : 0;
    $awayDrawRate = $awayStats['games'] ? $awayStats['draws'] / $awayStats['games'] : 0;
    $homeGoalAvg = $homeStats['games'] ? $homeStats['goalsScored'] / $homeStats['games'] : 0;
    $awayGoalAvg = $awayStats['games'] ? $awayStats['goalsScored'] / $awayStats['games'] : 0;

    $homeStrength = ($homeWinRate * 50 + $homeDrawRate * 20 + $homeGoalAvg * 20) * 1.1; // Home advantage
    $awayStrength = $awayWinRate * 50 + $awayDrawRate * 20 + $awayGoalAvg * 20;

    $diff = $homeStrength - $awayStrength;
    $confidence = min(90, abs($diff) / ($homeStrength + $awayStrength + 1) * 100);

    if ($diff > 15) {
        return ["$homeTeam to win", sprintf("%.1f%%", $confidence)];
    } elseif ($diff < -15) {
        return ["$awayTeam to win", sprintf("%.1f%%", $confidence)];
    } else {
        return ["Draw", sprintf("%.1f%%", min(70, $confidence))];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Football Predictions</title>
    <style>
        :root {
            --primary-color: #2ecc71;
            --secondary-color: #3498db;
            --text-color: #333;
            --bg-color: #f4f4f4;
            --card-bg: #fff;
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        [data-theme="dark"] {
            --primary-color: #27ae60;
            --secondary-color: #2980b9;
            --text-color: #ecf0f1;
            --bg-color: #2c3e50;
            --card-bg: #34495e;
            --shadow: 0 4px 6px rgba(0,0,0,0.3);
        }

        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .theme-toggle, .filter-btn, select, .toggle-btn {
            padding: 10px 20px;
            margin: 5px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .theme-toggle:hover, .filter-btn:hover, select:hover, .toggle-btn:hover {
            background-color: var(--secondary-color);
        }

        .custom-date {
            margin: 10px 0;
        }

        .custom-date input {
            padding: 5px;
            margin: 0 5px;
            border-radius: 5px;
            border: 1px solid var(--text-color);
        }

        .match-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .match-card {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
        }

        .match-card:hover {
            transform: translateY(-5px);
        }

        .teams {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .team {
            text-align: center;
            width: 45%;
        }

        .team img {
            max-width: 50px;
            height: auto;
            margin-bottom: 10px;
        }

        .vs {
            font-size: 1.2em;
            font-weight: bold;
            color: var(--primary-color);
        }

        .match-info {
            text-align: center;
            font-size: 0.9em;
            color: #666;
        }

        .match-info.dark {
            color: #bdc3c7;
        }

        .prediction {
            margin-top: 15px;
            padding: 10px;
            background-color: rgba(46, 204, 113, 0.1);
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }

        .confidence {
            font-size: 0.8em;
            color: var(--secondary-color);
        }

        .past-results {
            margin-top: 15px;
            padding: 10px;
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 5px;
            font-size: 0.85em;
        }

        .past-results ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">Toggle Theme</button>
    <div class="container">
        <div class="header">
            <h1>Dynamic Football Predictions</h1>
            <p>Select Competition and Date Range</p>
        </div>

        <div class="controls">
            <select onchange="window.location.href='?competition='+this.value+'&filter=<?php echo $filter; ?>&showPast=<?php echo $showPast ? 1 : 0; ?>'">
                <?php
                foreach ($competitions as $comp) {
                    $code = $comp['code'];
                    $name = $comp['name'];
                    $selected = $code === $selectedComp ? 'selected' : '';
                    echo "<option value='$code' $selected>$name</option>";
                }
                ?>
            </select>

            <div>
                <button class="filter-btn" onclick="window.location.href='?competition=<?php echo $selectedComp; ?>&filter=yesterday&showPast=<?php echo $showPast ? 1 : 0; ?>'">Yesterday</button>
                <button class="filter-btn" onclick="window.location.href='?competition=<?php echo $selectedComp; ?>&filter=today&showPast=<?php echo $showPast ? 1 : 0; ?>'">Today</button>
                <button class="filter-btn" onclick="window.location.href='?competition=<?php echo $selectedComp; ?>&filter=tomorrow&showPast=<?php echo $showPast ? 1 : 0; ?>'">Tomorrow</button>
            </div>

            <form class="custom-date" method="GET">
                <input type="date" name="start" value="<?php echo $customStart; ?>">
                <input type="date" name="end" value="<?php echo $customEnd; ?>">
                <input type="hidden" name="filter" value="custom">
                <input type="hidden" name="competition" value="<?php echo $selectedComp; ?>">
                <input type="hidden" name="showPast" value="<?php echo $showPast ? 1 : 0; ?>">
                <button type="submit" class="filter-btn">Custom</button>
            </form>

            <button class="toggle-btn" onclick="window.location.href='?competition=<?php echo $selectedComp; ?>&filter=<?php echo $filter; ?>&showPast=<?php echo $showPast ? 0 : 1; ?>'">
                <?php echo $showPast ? 'Hide' : 'Show'; ?> Past Results
            </button>
        </div>

        <div class="match-grid">
            <?php
            if (!empty($upcomingMatches)) {
                foreach ($upcomingMatches as $match) {
                    if ($match['status'] !== 'FINISHED') {
                        $homeTeam = $match['homeTeam']['name'] ?? 'TBD';
                        $awayTeam = $match['awayTeam']['name'] ?? 'TBD';
                        $date = date('M d, Y H:i', strtotime($match['utcDate']));
                        $homeCrest = $match['homeTeam']['crest'] ?? '';
                        $awayCrest = $match['awayTeam']['crest'] ?? '';
                        [$prediction, $confidence] = predictMatch($match, $apiKey, $baseUrl, $teamStats);
                        $homeStats = calculateTeamStrength($match['homeTeam']['id'] ?? 0, $apiKey, $baseUrl, $teamStats);
                        $awayStats = calculateTeamStrength($match['awayTeam']['id'] ?? 0, $apiKey, $baseUrl, $teamStats);

                        echo "
                        <div class='match-card'>
                            <div class='teams'>
                                <div class='team'>
                                    " . ($homeCrest ? "<img src='$homeCrest' alt='$homeTeam'>" : "") . "
                                    <p>$homeTeam</p>
                                </div>
                                <span class='vs'>VS</span>
                                <div class='team'>
                                    " . ($awayCrest ? "<img src='$awayCrest' alt='$awayTeam'>" : "") . "
                                    <p>$awayTeam</p>
                                </div>
                            </div>
                            <div class='match-info " . (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark' : '') . "'>
                                <p>$date</p>
                            </div>
                            <div class='prediction'>
                                <p>Prediction: $prediction</p>
                                <p class='confidence'>Confidence: $confidence</p>
                            </div>";

                        if ($showPast && !empty($homeStats['results']) && !empty($awayStats['results'])) {
                            echo "<div class='past-results'>
                                <p><strong>$homeTeam Recent Results:</strong></p>
                                <ul>";
                            foreach ($homeStats['results'] as $result) {
                                echo "<li>$result</li>";
                            }
                            echo "</ul>
                                <p><strong>$awayTeam Recent Results:</strong></p>
                                <ul>";
                            foreach ($awayStats['results'] as $result) {
                                echo "<li>$result</li>";
                            }
                            echo "</ul></div>";
                        }

                        echo "</div>";
                    }
                }
            } else {
                echo "<p>No upcoming matches available for the selected competition and date range.</p>";
            }
            ?>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            body.setAttribute('data-theme', newTheme);
            document.cookie = `theme=${newTheme};path=/;max-age=31536000`;
            
            document.querySelectorAll('.match-info').forEach(el => {
                el.classList.toggle('dark', newTheme === 'dark');
            });
        }

        window.onload = function() {
            const theme = document.cookie.split('; ')
                .find(row => row.startsWith('theme='))
                ?.split('=')[1];
            
            if (theme) {
                document.body.setAttribute('data-theme', theme);
                document.querySelectorAll('.match-info').forEach(el => {
                    el.classList.toggle('dark', theme === 'dark');
                });
            }
        }
    </script>
</body>
</html>
