<?php
session_start();
if (!isset($_SESSION['teamStats'])) $_SESSION['teamStats'] = [];

$apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c';
$baseUrl = 'http://api.football-data.org/v4/';
$teamStats = &$_SESSION['teamStats'];

// Add Navigation Bar
echo "<nav style='background-color: #f8f9fa; padding: 10px; text-align: center;'>";
echo "<a href='liv' style='margin: 0 15px; text-decoration: none; color: #007bff;'>Home</a>";
echo "<a href='valmanu' style='margin: 0 15px; text-decoration: none; color: #007bff;'>More Predictions</a>";
echo "</nav>";

// Handle different actions based on query parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'main';

function fetchWithRetry($url, $apiKey, $maxRetries = 3) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $apiKey"]);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $attempt = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);

    if ($httpCode == 429 && $attempt < $maxRetries) {
        echo "<script>var retryAttempt = " . ($attempt + 1) . "; var maxRetries = $maxRetries;</script>";
        return false;
    } elseif ($httpCode == 200) {
        return json_decode($body, true);
    } else {
        return false;
    }
}

function fetchTeamResults($teamId, $apiKey, $baseUrl) {
    $pastDate = date('Y-m-d', strtotime('-60 days'));
    $url = $baseUrl . "teams/$teamId/matches?dateFrom=$pastDate&dateTo=" . date('Y-m-d') . "&limit=10&status=FINISHED";
    $data = fetchWithRetry($url, $apiKey);
    return isset($data['matches']) ? $data['matches'] : [];
}

function calculateTeamStrength($teamId, $apiKey, $baseUrl, &$teamStats) {
    if (!isset($teamStats[$teamId]) || empty($teamStats[$teamId]['results']) || empty($teamStats[$teamId]['form'])) {
        $results = fetchTeamResults($teamId, $apiKey, $baseUrl);
        if ($results === false) {
            $teamStats[$teamId] = ['results' => [], 'form' => '', 'needsRetry' => true];
            return $teamStats[$teamId];
        }
        
        $stats = [
            'wins' => 0, 
            'draws' => 0, 
            'goalsScored' => 0, 
            'goalsConceded' => 0, 
            'games' => 0, 
            'results' => [],
            'form' => '',
            'needsRetry' => false
        ];
        
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
        $formArray = [];
        foreach (array_reverse($results) as $match) {
            $homeId = isset($match['homeTeam']['id']) ? $match['homeTeam']['id'] : 0;
            $awayId = isset($match['awayTeam']['id']) ? $match['awayTeam']['id'] : 0;
            $homeGoals = isset($match['score']['fullTime']['home']) ? $match['score']['fullTime']['home'] : 0;
            $awayGoals = isset($match['score']['fullTime']['away']) ? $match['score']['fullTime']['away'] : 0;
            
            if ($teamId == $homeId) {
                if ($homeGoals > $awayGoals) $formArray[] = 'W';
                elseif ($homeGoals == $awayGoals) $formArray[] = 'D';
                else $formArray[] = 'L';
            } elseif ($teamId == $awayId) {
                if ($awayGoals > $homeGoals) $formArray[] = 'W';
                elseif ($homeGoals == $awayGoals) $formArray[] = 'D';
                else $formArray[] = 'L';
            }
        }
        $stats['form'] = implode('', array_slice($formArray, 0, 6));
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

    if (!$homeTeamId || !$awayTeamId) return ["N/A", "0%", "", "0-0"];

    $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats);
    $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats);

    if (isset($homeStats['needsRetry']) && $homeStats['needsRetry']) {
        echo "<script>var incompleteTeams = (typeof incompleteTeams === 'undefined' ? [] : incompleteTeams); incompleteTeams.push($homeTeamId);</script>";
    }
    if (isset($awayStats['needsRetry']) && $awayStats['needsRetry']) {
        echo "<script>var incompleteTeams = (typeof incompleteTeams === 'undefined' ? [] : incompleteTeams); incompleteTeams.push($awayTeamId);</script>";
    }

    if (empty($homeStats['results']) || empty($homeStats['form']) || empty($awayStats['results']) || empty($awayStats['form'])) {
        return ["Loading...", "N/A", "", "N/A"];
    }

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

    $predictedHomeGoals = round($homeGoalAvg * (1 + $diff/100));
    $predictedAwayGoals = round($awayGoalAvg * (1 - $diff/100));
    $predictedScore = "$predictedHomeGoals-$predictedAwayGoals";

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

    return [$prediction, $confidence, $resultIndicator, $predictedScore];
}

if ($action === 'fetch_team_data') {
    header('Content-Type: application/json');
    $teamId = isset($_GET['teamId']) ? (int)$_GET['teamId'] : 0;
    if (!$teamId) {
        echo json_encode(['success' => false, 'error' => 'Invalid team ID']);
        exit;
    }

    $results = fetchTeamResults($teamId, $apiKey, $baseUrl);
    if ($results === false || empty($results)) {
        echo json_encode(['success' => false, 'error' => 'No results fetched']);
        exit;
    }

    $stats = calculateTeamStrength($teamId, $apiKey, $baseUrl, $teamStats);
    $teamName = '';
    foreach ($results as $match) {
        if ($match['homeTeam']['id'] == $teamId) {
            $teamName = $match['homeTeam']['name'];
            break;
        } elseif ($match['awayTeam']['id'] == $teamId) {
            $teamName = $match['awayTeam']['name'];
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'teamName' => $teamName,
        'results' => $stats['results'],
        'form' => $stats['form']
    ]);
    exit;
}

if ($action === 'predict_match') {
    header('Content-Type: application/json');
    $homeId = isset($_GET['homeId']) ? (int)$_GET['homeId'] : 0;
    $awayId = isset($_GET['awayId']) ? (int)$_GET['awayId'] : 0;

    if (!$homeId || !$awayId) {
        echo json_encode(['success' => false, 'error' => 'Invalid team IDs']);
        exit;
    }

    $homeStats = calculateTeamStrength($homeId, $apiKey, $baseUrl, $teamStats);
    $awayStats = calculateTeamStrength($awayId, $apiKey, $baseUrl, $teamStats);

    if (empty($homeStats['results']) || empty($awayStats['results'])) {
        echo json_encode(['success' => false, 'error' => 'Team stats not loaded']);
        exit;
    }

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

    $predictedHomeGoals = round($homeGoalAvg * (1 + $diff/100));
    $predictedAwayGoals = round($awayGoalAvg * (1 - $diff/100));
    $predictedScore = "$predictedHomeGoals-$predictedAwayGoals";

    $prediction = "";
    $confidenceFormatted = "";

    if ($diff > 15) {
        $prediction = "Home team to win";
        $confidenceFormatted = sprintf("%.1f%%", $confidence);
    } elseif ($diff < -15) {
        $prediction = "Away team to win";
        $confidenceFormatted = sprintf("%.1f%%", $confidence);
    } else {
        $prediction = "Draw";
        $confidenceFormatted = sprintf("%.1f%%", min(70, $confidence));
    }

    echo json_encode([
        'success' => true,
        'prediction' => $prediction,
        'confidence' => $confidenceFormatted,
        'resultIndicator' => '',
        'predictedScore' => $predictedScore
    ]);
    exit;
}

// Main page logic
$competitionsUrl = $baseUrl . 'competitions';
$compData = fetchWithRetry($competitionsUrl, $apiKey);
if ($compData === false) exit;
$competitions = isset($compData['competitions']) ? $compData['competitions'] : [];

$selectedComp = isset($_GET['competition']) ? $_GET['competition'] : (isset($competitions[0]['code']) ? $competitions[0]['code'] : 'PL');
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$customStart = isset($_GET['start']) ? $_GET['start'] : '';
$customEnd = isset($_GET['end']) ? $_GET['end'] : '';

$dateOptions = [
    'yesterday' => ['label' => 'Yesterday', 'date' => date('Y-m-d', strtotime('-1 day'))],
    'today' => ['label' => 'Today', 'date' => date('Y-m-d')],
    'tomorrow' => ['label' => 'Tomorrow', 'date' => date('Y-m-d', strtotime('+1 day'))],
    'week' => ['label' => 'This Week', 'from' => date('Y-m-d', strtotime('monday this week')), 'to' => date('Y-m-d', strtotime('sunday this week'))],
    'upcoming' => ['label' => 'Next 7 Days', 'from' => date('Y-m-d'), 'to' => date('Y-m-d', strtotime('+7 days'))],
    'custom' => ['label' => 'Custom Range']
];

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

$matchesUrl = $baseUrl . "competitions/$selectedComp/matches?dateFrom=$fromDate&dateTo=$toDate";
$matchData = fetchWithRetry($matchesUrl, $apiKey);
if ($matchData === false) exit;
$allMatches = isset($matchData['matches']) ? $matchData['matches'] : [];
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

        .theme-toggle, select {
            padding: 10px 20px;
            margin: 5px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .theme-toggle:hover, select:hover {
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

        .view-history-btn {
            margin-top: 10px;
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease;
        }

        .view-history-btn:hover {
            background-color: var(--secondary-color);
        }

        .form-display {
            margin-top: 5px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .form-display span {
            display: inline-block;
            width: 20px;
            text-align: center;
            border-radius: 3px;
            margin: 0 1px;
        }

        .form-display .win {
            background-color: #28a745;
            color: white;
        }

        .form-display .draw {
            background-color: #fd7e14;
            color: white;
        }

        .form-display .loss {
            background-color: #dc3545;
            color: white;
        }

        .form-display .empty {
            background-color: #6c757d;
            color: white;
        }

        .form-display .latest {
            border: 2px solid #000;
        }

        .retry-message {
            text-align: center;
            margin: 20px 0;
            font-size: 1.2em;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">
        <span class="theme-icon">☀️</span>
    </button>
    <div class="container">
        <div class="header">
            <h1>Advanced Football Predictions</h1>
            <p>Select Competition and Date Range</p>
        </div>

        <?php if ($compData === false || $matchData === false): ?>
            <div id="retry-message" class="retry-message">
                Rate limit exceeded. Retry attempt <span id="attempt-count"></span>/<span id="max-retries"></span>. Retrying in <span id="countdown">5</span> seconds...
            </div>
        <?php endif; ?>

        <div class="controls">
            <select onchange="updateUrl(this.value, '<?php echo $filter; ?>')">
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
                        <button type="submit">Apply</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="match-grid">
            <?php
            if (!empty($allMatches)) {
                foreach ($allMatches as $index => $match) {
                    if (isset($match['status'])) {
                        $homeTeamId = isset($match['homeTeam']['id']) ? $match['homeTeam']['id'] : 0;
                        $awayTeamId = isset($match['awayTeam']['id']) ? $match['awayTeam']['id'] : 0;
                        $homeTeam = isset($match['homeTeam']['name']) ? $match['homeTeam']['name'] : 'TBD';
                        $awayTeam = isset($match['awayTeam']['name']) ? $match['awayTeam']['name'] : 'TBD';
                        $date = isset($match['utcDate']) ? date('M d, Y H:i', strtotime($match['utcDate'])) : 'TBD';
                        $homeCrest = isset($match['homeTeam']['crest']) ? $match['homeTeam']['crest'] : '';
                        $awayCrest = isset($match['awayTeam']['crest']) ? $match['awayTeam']['crest'] : '';
                        $status = $match['status'];
                        $homeGoals = isset($match['score']['fullTime']['home']) ? $match['score']['fullTime']['home'] : null;
                        $awayGoals = isset($match['score']['fullTime']['away']) ? $match['score']['fullTime']['away'] : null;
                        [$prediction, $confidence, $resultIndicator, $predictedScore] = predictMatch($match, $apiKey, $baseUrl, $teamStats);
                        $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats);
                        $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats);

                        echo "
                        <div class='match-card' data-home-id='$homeTeamId' data-away-id='$awayTeamId' data-index='$index'>
                            <div class='teams'>
                                <div class='team'>
                                    " . ($homeCrest ? "<img src='$homeCrest' alt='$homeTeam'>" : "") . "
                                    <p>$homeTeam</p>
                                    <div class='form-display' id='form-home-$index'>";
                        $homeForm = str_pad($homeStats['form'], 6, '-', STR_PAD_LEFT);
                        for ($i = 0; $i < strlen($homeForm); $i++) {
                            $class = '';
                            if ($homeForm[$i] === 'W') $class = 'win';
                            elseif ($homeForm[$i] === 'D') $class = 'draw';
                            elseif ($homeForm[$i] === 'L') $class = 'loss';
                            elseif ($homeForm[$i] === '-') $class = 'empty';
                            if ($i === strlen($homeForm) - 1 && $homeForm[$i] !== '-') $class .= ' latest';
                            echo "<span class='$class'>" . $homeForm[$i] . "</span>";
                        }
                        echo "</div>
                                </div>
                                <span class='vs'>VS</span>
                                <div class='team'>
                                    " . ($awayCrest ? "<img src='$awayCrest' alt='$awayTeam'>" : "") . "
                                    <p>$awayTeam</p>
                                    <div class='form-display' id='form-away-$index'>";
                        $awayForm = str_pad($awayStats['form'], 6, '-', STR_PAD_LEFT);
                        for ($i = 0; $i < strlen($awayForm); $i++) {
                            $class = '';
                            if ($awayForm[$i] === 'W') $class = 'win';
                            elseif ($awayForm[$i] === 'D') $class = 'draw';
                            elseif ($awayForm[$i] === 'L') $class = 'loss';
                            elseif ($awayForm[$i] === '-') $class = 'empty';
                            if ($i === strlen($awayForm) - 1 && $awayForm[$i] !== '-') $class .= ' latest';
                            echo "<span class='$class'>" . $awayForm[$i] . "</span>";
                        }
                        echo "</div>
                                </div>
                            </div>
                            <div class='match-info " . (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark' : '') . "'>
                                <p>$date (" . $status . ")" . ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null ? " - $homeGoals : $awayGoals" : "") . "</p>
                            </div>
                            <div class='prediction' id='prediction-$index'>
                                <p>Prediction: $prediction <span class='result-indicator'>$resultIndicator</span></p>
                                <p class='predicted-score'>Predicted Score: $predictedScore</p>
                                <p class='confidence'>Confidence: $confidence</p>
                            </div>
                            <button class='view-history-btn' onclick='toggleHistory(this)'>👁️ View History</button>
                            <div class='past-results' id='history-$index' style='display: none;'>";
                        
                        if (!empty($homeStats['results']) && !empty($awayStats['results'])) {
                            echo "
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
                            echo "</ul>";
                        } else {
                            echo "<p>Loading history...</p>";
                        }
                        
                        echo "</div></div>";
                    }
                }
            } else if ($compData !== false && $matchData !== false) {
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
            const themeIcon = document.querySelector('.theme-icon');
            themeIcon.textContent = newTheme === 'dark' ? '☀️' : '🌙';
        }

        function toggleHistory(button) {
            const historyDiv = button.nextElementSibling;
            const isHidden = historyDiv.style.display === 'none';
            historyDiv.style.display = isHidden ? 'block' : 'none';
            button.textContent = isHidden ? '👁️ Hide History' : '👁️ View History';
        }

        function updateUrl(comp, filter) {
            let url = `?competition=${comp}&filter=${filter}`;
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
                updateUrl('<?php echo $selectedComp; ?>', filter);
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

        function fetchTeamData(teamId, index, isHome) {
            fetch(`?action=fetch_team_data&teamId=${teamId}`, {
                headers: {
                    'X-Auth-Token': '<?php echo $apiKey; ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const formElement = document.getElementById(`form-${isHome ? 'home' : 'away'}-${index}`);
                    const historyElement = document.getElementById(`history-${index}`);
                    const predictionElement = document.getElementById(`prediction-${index}`);

                    let formHtml = '';
                    const form = data.form.padStart(6, '-');
                    for (let i = 0; i < form.length; i++) {
                        let className = '';
                        if (form[i] === 'W') className = 'win';
                        else if (form[i] === 'D') className = 'draw';
                        else if (form[i] === 'L') className = 'loss';
                        else if (form[i] === '-') className = 'empty';
                        if (i === form.length - 1 && form[i] !== '-') className += ' latest';
                        formHtml += `<span class="${className}">${form[i]}</span>`;
                    }
                    if (isHome) {
                        formElement.innerHTML = formHtml;
                    } else {
                        formElement.innerHTML = formHtml;
                    }

                    let historyHtml = '';
                    if (isHome) {
                        historyHtml += `<p><strong>${data.teamName} Recent Results:</strong></p><ul>`;
                        data.results.forEach(result => {
                            historyHtml += `<li>${result}</li>`;
                        });
                        historyHtml += '</ul>';
                        historyElement.innerHTML = historyHtml + historyElement.innerHTML;
                    } else {
                        historyHtml = historyElement.innerHTML.replace('Loading history...', '');
                        historyHtml += `<p><strong>${data.teamName} Recent Results:</strong></p><ul>`;
                        data.results.forEach(result => {
                            historyHtml += `<li>${result}</li>`;
                        });
                        historyHtml += '</ul>';
                        historyElement.innerHTML = historyHtml;
                    }

                    const matchCard = document.querySelector(`.match-card[data-index="${index}"]`);
                    const otherTeamLoaded = isHome ? 
                        !document.getElementById(`form-away-${index}`).innerHTML.includes('-') : 
                        !document.getElementById(`form-home-${index}`).innerHTML.includes('-');
                    if (otherTeamLoaded) {
                        fetchPrediction(index, matchCard.dataset.homeId, matchCard.dataset.awayId);
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching team data:', error);
                setTimeout(() => fetchTeamData(teamId, index, isHome), 5000);
            });
        }

        function fetchPrediction(index, homeId, awayId) {
            fetch(`?action=predict_match&homeId=${homeId}&awayId=${awayId}`, {
                headers: {
                    'X-Auth-Token': '<?php echo $apiKey; ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const predictionElement = document.getElementById(`prediction-${index}`);
                    predictionElement.innerHTML = `
                        <p>Prediction: ${data.prediction} <span class="result-indicator">${data.resultIndicator}</span></p>
                        <p class="predicted-score">Predicted Score: ${data.predictedScore}</p>
                        <p class="confidence">Confidence: ${data.confidence}</p>
                    `;
                }
            })
            .catch(error => console.error('Error fetching prediction:', error));
        }

        window.onload = function() {
            const theme = document.cookie.split('; ')
                .find(row => row.startsWith('theme='))
                ?.split('=')[1];
            const themeIcon = document.querySelector('.theme-icon');
            if (theme) {
                document.body.setAttribute('data-theme', theme);
                document.querySelectorAll('.match-info').forEach(el => {
                    el.classList.toggle('dark', theme === 'dark');
                });
                themeIcon.textContent = theme === 'dark' ? '☀️' : '🌙';
            } else {
                themeIcon.textContent = '🌙';
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

            if (typeof retryAttempt !== 'undefined' && retryAttempt <= maxRetries) {
                document.getElementById('attempt-count').textContent = retryAttempt;
                document.getElementById('max-retries').textContent = maxRetries;
                
                let timeLeft = 5;
                const countdownElement = document.getElementById('countdown');
                countdownElement.textContent = timeLeft;

                const timer = setInterval(() => {
                    timeLeft--;
                    countdownElement.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        let url = window.location.pathname + '?competition=<?php echo $selectedComp; ?>&filter=<?php echo $filter; ?>';
                        url += '&attempt=' + retryAttempt;
                        if ('<?php echo $filter; ?>' === 'custom' && '<?php echo $customStart; ?>' && '<?php echo $customEnd; ?>') {
                            url += '&start=<?php echo $customStart; ?>&end=<?php echo $customEnd; ?>';
                        }
                        window.location.href = url;
                    }
                }, 1000);
            } else if (typeof retryAttempt !== 'undefined' && retryAttempt > maxRetries) {
                document.getElementById('retry-message').textContent = 'Max retry attempts reached. Please try again later.';
            }

            if (typeof incompleteTeams !== 'undefined' && incompleteTeams.length > 0) {
                incompleteTeams.forEach(teamId => {
                    const matchCards = document.querySelectorAll(`.match-card[data-home-id="${teamId}"], .match-card[data-away-id="${teamId}"]`);
                    matchCards.forEach(card => {
                        const index = card.dataset.index;
                        const isHome = card.dataset.homeId == teamId;
                        fetchTeamData(teamId, index, isHome);
                    });
                });
            }
        }
    </script>
</body>
</html>
