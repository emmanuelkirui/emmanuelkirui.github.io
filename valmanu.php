<?php
session_start();
if (!isset($_SESSION['teamStats'])) $_SESSION['teamStats'] = [];

// API configuration
$apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c'; // Replace with your football-data.org API key
$baseUrl = 'http://api.football-data.org/v4/';

// Function to fetch data with retry on 429
function fetchApiData($url, $apiKey, $retryCount = 0, $maxRetries = 3) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $apiKey"]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 429 && $retryCount < $maxRetries) {
        $waitTime = 60; // 1 minute wait (adjust based on API rate limit reset)
        $_SESSION['retryTime'] = time() + $waitTime;
        $_SESSION['retryUrl'] = $url;
        return ['retry' => true, 'waitTime' => $waitTime];
    }
    return ['data' => json_decode($response, true), 'httpCode' => $httpCode];
}

// Check for retry condition
if (isset($_SESSION['retryTime']) && time() < $_SESSION['retryTime'])) {
    $remainingTime = $_SESSION['retryTime'] - time();
} else {
    unset($_SESSION['retryTime'], $_SESSION['retryUrl']); // Clear retry state

    // Fetch competitions
    $competitionsResult = fetchApiData($baseUrl . 'competitions', $apiKey);
    if ($competitionsResult['retry'] ?? false) {
        $remainingTime = $competitionsResult['waitTime'];
    } else {
        $competitions = $competitionsResult['data']['competitions'] ?? [];
    }

    if (!isset($remainingTime)) {
        // Handle user selections
        $selectedComp = $_GET['competition'] ?? ($competitions[0]['code'] ?? 'PL');
        $filter = $_GET['filter'] ?? 'today';
        $customStart = $_GET['start'] ?? '';
        $customEnd = $_GET['end'] ?? '';
        $showPast = isset($_GET['showPast']) ? true : false;

        // Date handling
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

        // Fetch upcoming matches
        $matchesResult = fetchApiData($baseUrl . "competitions/$selectedComp/matches?dateFrom=$fromDate&dateTo=$toDate", $apiKey);
        if ($matchesResult['retry'] ?? false) {
            $remainingTime = $matchesResult['waitTime'];
        } else {
            $upcomingMatches = $matchesResult['data']['matches'] ?? [];
        }
    }
}

// Team stats functions
$teamStats = &$_SESSION['teamStats'];
function fetchTeamResults($teamId, $apiKey, $baseUrl) {
    $pastDate = date('Y-m-d', strtotime('-30 days'));
    $url = $baseUrl . "teams/$teamId/matches?dateFrom=$pastDate&dateTo=" . date('Y-m-d') . "&limit=5&status=FINISHED";
    $result = fetchApiData($url, $apiKey);
    return $result['retry'] ? [] : ($result['data']['matches'] ?? []);
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

    $homeStrength = ($homeWinRate * 50 + $homeDrawRate * 20 + $homeGoalAvg * 20) * 1.1;
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
    <title>Football Predictions Pro</title>
    <style>
        :root {
            --primary-color: #28a745;
            --secondary-color: #007bff;
            --text-color: #212529;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --shadow: 0 5px 15px rgba(0,0,0,0.1);
            --border-radius: 8px;
        }

        [data-theme="dark"] {
            --primary-color: #218838;
            --secondary-color: #0069d9;
            --text-color: #e9ecef;
            --bg-color: #212529;
            --card-bg: #343a40;
            --shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 30px;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .header h1 {
            margin: 0;
            font-size: 2.2em;
            color: var(--primary-color);
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
            padding: 15px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .theme-toggle, .filter-btn, select, .toggle-btn {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-weight: 500;
        }

        .theme-toggle:hover, .filter-btn:hover, select:hover, .toggle-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        select {
            background-color: var(--primary-color);
            appearance: none;
            padding-right: 30px;
            background-image: url('data:image/svg+xml;utf8,<svg fill="white" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        .custom-date {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .custom-date input {
            padding: 8px;
            border: 1px solid var(--text-color);
            border-radius: var(--border-radius);
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .match-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 25px;
        }

        .match-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
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
            font-weight: 500;
        }

        .team img {
            max-width: 60px;
            height: auto;
            margin-bottom: 10px;
        }

        .vs {
            font-size: 1.3em;
            font-weight: bold;
            color: var(--primary-color);
        }

        .match-info {
            text-align: center;
            font-size: 0.95em;
            color: #6c757d;
        }

        .match-info.dark {
            color: #adb5bd;
        }

        .prediction {
            margin-top: 15px;
            padding: 12px;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: var(--border-radius);
            text-align: center;
            font-weight: 500;
        }

        .confidence {
            font-size: 0.85em;
            color: var(--secondary-color);
            margin-top: 5px;
        }

        .past-results {
            margin-top: 15px;
            padding: 12px;
            background-color: rgba(0, 123, 255, 0.1);
            border-radius: var(--border-radius);
            font-size: 0.9em;
        }

        .past-results ul {
            list-style: none;
            padding: 0;
            margin: 5px 0 0;
        }

        .past-results li {
            margin: 5px 0;
        }

        .retry-message {
            text-align: center;
            padding: 20px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            color: #dc3545;
            font-weight: 500;
        }

        .retry-message #timer {
            font-size: 1.2em;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">
        <span class="light-icon" style="display: <?php echo (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'none' : 'inline'; ?>;">
            &#9728; <!-- Sun icon -->
        </span>
        <span class="dark-icon" style="display: <?php echo (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'inline' : 'none'; ?>;">
            &#9790; <!-- Moon icon -->
        </span>
    </button>

    <div class="container">
        <?php if (isset($remainingTime)): ?>
            <div class="retry-message">
                <p>API rate limit exceeded. Retrying in <span id="timer"><?php echo $remainingTime; ?></span> seconds...</p>
            </div>
        <?php else: ?>
            <div class="header">
                <h1>Football Predictions Pro</h1>
                <p>Professional Match Analysis & Forecasts</p>
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
                    echo "<p class='match-card'>No upcoming matches available for the selected competition and date range.</p>";
                }
                ?>
            </div>
        <?php endif; ?>
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

            const lightIcon = document.querySelector('.light-icon');
            const darkIcon = document.querySelector('.dark-icon');
            lightIcon.style.display = newTheme === 'dark' ? 'none' : 'inline';
            darkIcon.style.display = newTheme === 'dark' ? 'inline' : 'none';
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
                toggleThemeIcons(theme);
            }

            <?php if (isset($remainingTime)): ?>
                let timeLeft = <?php echo $remainingTime; ?>;
                const timer = document.getElementById('timer');
                const countdown = setInterval(() => {
                    timeLeft--;
                    timer.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(countdown);
                        window.location.reload(); // Retry
                    }
                }, 1000);
            <?php endif; ?>
        }

        function toggleThemeIcons(theme) {
            const lightIcon = document.querySelector('.light-icon');
            const darkIcon = document.querySelector('.dark-icon');
            lightIcon.style.display = theme === 'dark' ? 'none' : 'inline';
            darkIcon.style.display = theme === 'dark' ? 'inline' : 'none';
        }
    </script>
</body>
</html>
