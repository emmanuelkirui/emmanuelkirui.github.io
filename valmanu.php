<?php
session_start();
if (!isset($_SESSION['teamStats'])) $_SESSION['teamStats'] = [];

$apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c';
$baseUrl = 'http://api.football-data.org/v4/';

// Fetch available competitions
$competitionsUrl = $baseUrl . 'competitions';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $competitionsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $apiKey"]);
$compResponse = curl_exec($ch);
curl_close($ch);
$competitions = isset(json_decode($compResponse, true)['competitions']) ? json_decode($compResponse, true)['competitions'] : [];

// Handle user selections with isset
$selectedComp = isset($_GET['competition']) ? $_GET['competition'] : (isset($competitions[0]['code']) ? $competitions[0]['code'] : 'PL');
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$customStart = isset($_GET['start']) ? $_GET['start'] : '';
$customEnd = isset($_GET['end']) ? $_GET['end'] : '';
$showPast = isset($_GET['showPast']) && $_GET['showPast'] == 1 ? true : false;

// Advanced date options
$dateOptions = [
    'yesterday' => ['label' => 'Yesterday', 'date' => date('Y-m-d', strtotime('-1 day'))],
    'today' => ['label' => 'Today', 'date' => date('Y-m-d')],
    'tomorrow' => ['label' => 'Tomorrow', 'date' => date('Y-m-d', strtotime('+1 day'))],
    'week' => ['label' => 'This Week', 'from' => date('Y-m-d', strtotime('monday this week')), 'to' => date('Y-m-d', strtotime('sunday this week'))],
    'upcoming' => ['label' => 'Next 7 Days', 'from' => date('Y-m-d'), 'to' => date('Y-m-d', strtotime('+7 days'))],
    'custom' => ['label' => 'Custom Range']
];

// Determine display label for the filter button
$filterLabel = ($filter === 'custom' && $customStart && $customEnd) 
    ? "Custom: $customStart to $customEnd" 
    : (isset($dateOptions[$filter]['label']) ? $dateOptions[$filter]['label'] : 'Select Date');

$fromDate = $toDate = date('Y-m-d');
if (isset($dateOptions[$filter])) {
    if (isset($dateOptions[$filter]['date'])) {
        $fromDate = $toDate = $dateOptions[$filter]['date'];
    } elseif (isset($dateOptions[$filter]['from']) && isset($dateOptions[$filter]['to'])) {
        $fromDate = $dateOptions[$filter]['from'];
        $toDate = $dateOptions[$filter]['to'];
    } elseif ($filter === 'custom' && $customStart && $customEnd) {
        $fromDate = $customStart;
        $toDate = $customEnd;
    }
}

// Fetch matches
$matchesUrl = $baseUrl . "competitions/$selectedComp/matches?dateFrom=$fromDate&dateTo=$toDate";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $matchesUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $apiKey"]);
$matchResponse = curl_exec($ch);
curl_close($ch);
$allMatches = isset(json_decode($matchResponse, true)['matches']) ? json_decode($matchResponse, true)['matches'] : [];

// Debug: Show number of matches fetched and their details
echo "Total matches fetched: " . count($allMatches) . "<br>";
foreach ($allMatches as $idx => $match) {
    echo "Match " . ($idx + 1) . ": " . (isset($match['homeTeam']['name']) ? $match['homeTeam']['name'] : 'TBD') . " vs " . 
         (isset($match['awayTeam']['name']) ? $match['awayTeam']['name'] : 'TBD') . " - Status: " . 
         (isset($match['status']) ? $match['status'] : 'Unknown') . "<br>";
}

// Team stats functions
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
    return isset(json_decode($response, true)['matches']) ? json_decode($response, true)['matches'] : [];
}

function calculateTeamStrength($teamId, $apiKey, $baseUrl, &$teamStats) {
    if (!isset($teamStats[$teamId])) {
        $results = fetchTeamResults($teamId, $apiKey, $baseUrl);
        $stats = ['wins' => 0, 'draws' => 0, 'goalsScored' => 0, 'goalsConceded' => 0, 'games' => 0, 'results' => []];
        
        foreach ($results as $match) {
            $homeId = isset($match['homeTeam']['id']) ? $match['homeTeam']['id'] : 0;
            $awayId = isset($match['awayTeam']['id']) ? $match['awayTeam']['id'] : 0;
            $homeGoals = isset($match['score']['fullTime']['home']) ? $match['score']['fullTime']['home'] : 0;
            $awayGoals = isset($match['score']['fullTime']['away']) ? $match['score']['fullTime']['away'] : 0;
            $date = date('M d', strtotime($match['utcDate']));
            $resultStr = (isset($match['homeTeam']['name']) ? $match['homeTeam']['name'] : 'Unknown') . " $homeGoals - $awayGoals " . (isset($match['awayTeam']['name']) ? $match['awayTeam']['name'] : 'Unknown');

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
    $homeTeamId = isset($match['homeTeam']['id']) ? $match['homeTeam']['id'] : 0;
    $awayTeamId = isset($match['awayTeam']['id']) ? $match['awayTeam']['id'] : 0;
    $homeTeam = isset($match['homeTeam']['name']) ? $match['homeTeam']['name'] : 'TBD';
    $awayTeam = isset($match['awayTeam']['name']) ? $match['awayTeam']['name'] : 'TBD';
    $status = isset($match['status']) ? $match['status'] : 'SCHEDULED';
    $homeGoals = isset($match['score']['fullTime']['home']) ? $match['score']['fullTime']['home'] : null;
    $awayGoals = isset($match['score']['fullTime']['away']) ? $match['score']['fullTime']['away'] : null;

    if (!$homeTeamId || !$awayTeamId) return ["N/A", "0%", ""];

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

    $prediction = "";
    $resultIndicator = "";

    if ($diff > 15) {
        $prediction = "$homeTeam to win";
        $confidence = sprintf("%.1f%%", $confidence);
    } elseif ($diff < -15) {
        $prediction = "$awayTeam to win";
        $confidence = sprintf("%.1f%%", $confidence);
    } else {
        $prediction = "Draw";
        $confidence = sprintf("%.1f%%", min(70, $confidence));
    }

    if ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null) {
        $actualResult = ($homeGoals > $awayGoals) ? "$homeTeam to win" : (($homeGoals < $awayGoals) ? "$awayTeam to win" : "Draw");
        $resultIndicator = ($prediction === $actualResult) ? "✅" : "❌";
    }

    return [$prediction, $confidence, $resultIndicator];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Football Predictions</title>
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

        .theme-toggle, select, .toggle-btn {
            padding: 10px 20px;
            margin: 5px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .theme-toggle:hover, select:hover, .toggle-btn:hover {
            background-color: var(--secondary-color);
        }

        .filter-container {
            position: relative;
            display: inline-block;
            margin: 5px;
        }

        .filter-dropdown-btn {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            min-width: 120px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filter-dropdown-btn::after {
            content: '▼';
            margin-left: 10px;
            font-size: 0.8em;
        }

        .filter-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--shadow);
            min-width: 200px;
            z-index: 10;
            display: none;
            margin-top: 5px;
        }

        .filter-dropdown.active {
            display: block;
        }

        .filter-option {
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .filter-option:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .filter-option.selected {
            background-color: var(--secondary-color);
            color: white;
        }

        .custom-date-range {
            padding: 15px;
            border-top: 1px solid rgba(0,0,0,0.1);
            display: none;
        }

        .custom-date-range.active {
            display: block;
        }

        .custom-date-range input[type="date"] {
            width: 100%;
            margin: 5px 0;
            padding: 5px;
            border-radius: 5px;
            border: 1px solid var(--text-color);
        }

        .custom-date-range button {
            width: 100%;
            margin-top: 10px;
            padding: 8px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
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

        .result-indicator {
            font-size: 1.2em;
            margin-left: 5px;
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
            <h1>Advanced Football Predictions</h1>
            <p>Select Competition and Date Range</p>
        </div>

        <div class="controls">
            <select onchange="updateUrl(this.value, '<?php echo $filter; ?>', <?php echo $showPast ? 1 : 0; ?>)">
                <?php
                foreach ($competitions as $comp) {
                    $code = isset($comp['code']) ? $comp['code'] : '';
                    $name = isset($comp['name']) ? $comp['name'] : 'Unknown';
                    $selected = $code === $selectedComp ? 'selected' : '';
                    echo "<option value='$code' $selected>$name</option>";
                }
                ?>
            </select>

            <div class="filter-container">
                <button class="filter-dropdown-btn"><?php echo $filterLabel; ?></button>
                <div class="filter-dropdown">
                    <?php
                    foreach ($dateOptions as $key => $option) {
                        $selectedClass = $filter === $key ? 'selected' : '';
                        echo "<div class='filter-option $selectedClass' data-filter='$key' onclick='selectFilter(\"$key\")'>";
                        echo isset($option['label']) ? $option['label'] : '';
                        echo "</div>";
                    }
                    ?>
                    <form class="custom-date-range" method="GET">
                        <input type="date" name="start" value="<?php echo $customStart; ?>">
                        <input type="date" name="end" value="<?php echo $customEnd; ?>">
                        <input type="hidden" name="filter" value="custom">
                        <input type="hidden" name="competition" value="<?php echo $selectedComp; ?>">
                        <input type="hidden" name="showPast" value="<?php echo $showPast ? 1 : 0; ?>">
                        <button type="submit">Apply</button>
                    </form>
                </div>
            </div>

            <button class="toggle-btn" onclick="updateUrl('<?php echo $selectedComp; ?>', '<?php echo $filter; ?>', <?php echo $showPast ? 0 : 1; ?>)">
                <?php echo $showPast ? 'Hide' : 'Show'; ?> Past Results
            </button>
        </div>

        <div class="match-grid">
            <?php
            if (!empty($allMatches)) {
                foreach ($allMatches as $match) {
                    if (isset($match['status'])) {
                        $homeTeam = isset($match['homeTeam']['name']) ? $match['homeTeam']['name'] : 'TBD';
                        $awayTeam = isset($match['awayTeam']['name']) ? $match['awayTeam']['name'] : 'TBD';
                        $date = isset($match['utcDate']) ? date('M d, Y H:i', strtotime($match['utcDate'])) : 'TBD';
                        $homeCrest = isset($match['homeTeam']['crest']) ? $match['homeTeam']['crest'] : '';
                        $awayCrest = isset($match['awayTeam']['crest']) ? $match['awayTeam']['crest'] : '';
                        $status = $match['status'];
                        $homeGoals = isset($match['score']['fullTime']['home']) ? $match['score']['fullTime']['home'] : null;
                        $awayGoals = isset($match['score']['fullTime']['away']) ? $match['score']['fullTime']['away'] : null;
                        [$prediction, $confidence, $resultIndicator] = predictMatch($match, $apiKey, $baseUrl, $teamStats);
                        $homeStats = calculateTeamStrength(isset($match['homeTeam']['id']) ? $match['homeTeam']['id'] : 0, $apiKey, $baseUrl, $teamStats);
                        $awayStats = calculateTeamStrength(isset($match['awayTeam']['id']) ? $match['awayTeam']['id'] : 0, $apiKey, $baseUrl, $teamStats);

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
                                <p>$date (" . $status . ")" . ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null ? " - $homeGoals : $awayGoals" : "") . "</p>
                            </div>
                            <div class='prediction'>
                                <p>Prediction: $prediction <span class='result-indicator'>$resultIndicator</span></p>
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
                echo "<p>No matches available for the selected competition and date range.</p>";
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

        function updateUrl(comp, filter, showPast) {
            let url = `?competition=${comp}&filter=${filter}&showPast=${showPast}`;
            if (filter === 'custom') {
                const start = document.querySelector('input[name="start"]').value;
                const end = document.querySelector('input[name="end"]').value;
                if (start && end) {
                    url += `&start=${start}&end=${end}`;
                }
            }
            window.location.href = url;
        }

        function selectFilter(filter) {
            if (filter !== 'custom') {
                updateUrl('<?php echo $selectedComp; ?>', filter, <?php echo $showPast ? 1 : 0; ?>);
            } else {
                document.querySelector('.custom-date-range').classList.add('active');
            }
        }

        document.querySelector('.filter-dropdown-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.querySelector('.filter-dropdown');
            dropdown.classList.toggle('active');
            const customRange = document.querySelector('.custom-date-range');
            if ('<?php echo $filter; ?>' === 'custom') {
                customRange.classList.add('active');
            } else {
                customRange.classList.remove('active');
            }
        });

        document.addEventListener('click', function(e) {
            const container = document.querySelector('.filter-container');
            if (!container.contains(e.target)) {
                document.querySelector('.filter-dropdown').classList.remove('active');
            }
        });

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

            const currentFilter = '<?php echo $filter; ?>';
            document.querySelectorAll('.filter-option').forEach(option => {
                if (option.getAttribute('data-filter') === currentFilter) {
                    option.classList.add('selected');
                } else {
                    option.classList.remove('selected');
                }
            });

            if (currentFilter === 'custom') {
                document.querySelector('.custom-date-range').classList.add('active');
            }
        }
    </script>
</body>
</html>
