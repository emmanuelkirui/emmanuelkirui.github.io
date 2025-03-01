<?php
session_start();

// Set timezone to East Africa Time (Nairobi, Kenya, UTC+3)
date_default_timezone_set('Africa/Nairobi');

// Initialize session data with error handling
if (!isset($_SESSION['teamStats'])) {
    $_SESSION['teamStats'] = [];
}

$apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c';
$baseUrl = 'http://api.football-data.org/v4/';
$teamStats = &$_SESSION['teamStats'];

// Basic HTML structure to prevent white screen
// Add Navigation Bar
echo "<nav class='navbar' style='width: 100%; position: relative;'>";
echo "<div class='hamburger' onclick='toggleMenu()' style='display: inline-block; cursor: pointer; padding: 10px; font-size: 20px;'>☰</div>";
echo "<div class='nav-menu' id='navMenu' style='display: none;'>"; // Changed from inline-block to none
echo "<a href='valmanu' class='nav-link' style='padding: 10px; text-decoration: none; color: #000; display: inline-block;'>Home</a>";
echo "<a href='liv' class='nav-link' style='padding: 10px; text-decoration: none; color: #000; display: inline-block;'>More Predictions</a>";
echo "<a href='javascript:history.back()' class='nav-link' style='padding: 10px; text-decoration: none; color: #000; display: inline-block;'>Back</a>";
echo "</div>";
echo "</nav>";

// JavaScript remains mostly the same
echo "<script>
    function toggleMenu() {
        const menu = document.getElementById('navMenu');
        const currentDisplay = menu.style.display;
        if (currentDisplay === 'none') {
            menu.style.display = 'inline-block';
        } else {
            menu.style.display = 'none';
        }
    }
    
    // Optional: Show menu on larger screens
    window.addEventListener('resize', function() {
        const menu = document.getElementById('navMenu');
        if (window.innerWidth > 768) {
            menu.style.display = 'inline-block';
        }
    });
</script>";

    // Error handling function
function handleError($message) {
    echo "<div style='text-align: center; padding: 20px; color: #dc3545;'>";
    echo "<h2>Error</h2><p>" . htmlspecialchars($message) . "</p>";
    echo "</div></body></html>";
    exit;
}
// Enhanced fetch function with infinite retry mechanism for 429 errors
function fetchWithRetry($url, $apiKey) {
    $attempt = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $apiKey"]);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return ['error' => true, 'message' => 'Connection error: ' . curl_error($ch)];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);

    if ($httpCode == 429) {
        $retrySeconds = min(pow(2, $attempt), 32);
        $nextAttempt = $attempt + 1;
        
        preg_match('/Retry-After: (\d+)/i', $headers, $matches);
        if (!empty($matches[1])) {
            $retrySeconds = max($retrySeconds, (int)$matches[1]);
        }
        
        // Display styled retry message with countdown
        echo "<script>
            var retryAttempt = $nextAttempt;
            document.addEventListener('DOMContentLoaded', function() {
                let timeLeft = $retrySeconds;
                const retryDiv = document.createElement('div');
                retryDiv.id = 'retry-message';
                retryDiv.className = 'retry-message countdown-box'; // Added countdown-box class
                retryDiv.innerHTML = '<span class=\"retry-text\">Rate limit exceeded. Retry attempt ' + retryAttempt + '. Retrying in </span><span id=\"countdown\" class=\"countdown-timer\">' + timeLeft + '</span><span class=\"retry-text\"> seconds...</span>';
                document.body.insertBefore(retryDiv, document.body.firstChild.nextSibling);
                
                const timer = setInterval(() => {
                    timeLeft--;
                    document.getElementById('countdown').textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        let url = window.location.pathname + window.location.search;
                        url += (window.location.search ? '&' : '?') + 'attempt=' + retryAttempt;
                        window.location.href = url;
                    }
                }, 1000);
            });
        </script>";
        return ['error' => true, 'retry' => true];
    } elseif ($httpCode == 200) {
        return ['error' => false, 'data' => json_decode($body, true)];
    } else {
        return ['error' => true, 'message' => "API Error: HTTP $httpCode"];
    }
}

// Team results fetch function with error handling (dates in EAT, Nairobi, Kenya)
function fetchTeamResults($teamId, $apiKey, $baseUrl) {
    $pastDate = date('Y-m-d', strtotime('-60 days')); // Nairobi time (UTC+3)
    $currentDate = date('Y-m-d'); // Nairobi time (UTC+3)
    $url = $baseUrl . "teams/$teamId/matches?dateFrom=$pastDate&dateTo=$currentDate&limit=10&status=FINISHED";
    
    $response = fetchWithRetry($url, $apiKey);
    if ($response['error']) {
        if (isset($response['retry'])) return false;
        handleError($response['message']);
    }
    return isset($response['data']['matches']) ? $response['data']['matches'] : [];
}

// Team strength calculation with error handling (dates in EAT, Nairobi, Kenya)
function calculateTeamStrength($teamId, $apiKey, $baseUrl, &$teamStats) {
    try {
        if (!isset($teamStats[$teamId]) || empty($teamStats[$teamId]['results']) || empty($teamStats[$teamId]['form'])) {
            $results = fetchTeamResults($teamId, $apiKey, $baseUrl);
            if ($results === false) {
                $teamStats[$teamId] = ['results' => [], 'form' => '', 'needsRetry' => true];
                return $teamStats[$teamId];
            }
            
            $stats = [
                'wins' => 0, 'draws' => 0, 'goalsScored' => 0, 
                'goalsConceded' => 0, 'games' => 0, 'results' => [],
                'form' => '', 'needsRetry' => false
            ];
            
            foreach ($results as $match) {
                $homeId = $match['homeTeam']['id'] ?? 0;
                $awayId = $match['awayTeam']['id'] ?? 0;
                $homeGoals = $match['score']['fullTime']['home'] ?? 0;
                $awayGoals = $match['score']['fullTime']['away'] ?? 0;
                $date = date('M d', strtotime($match['utcDate'])); // API provides UTC, converted to EAT (Nairobi, Kenya) via timezone setting
                $resultStr = ($match['homeTeam']['name'] ?? 'Unknown') . " $homeGoals - $awayGoals " . ($match['awayTeam']['name'] ?? 'Unknown');

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
                $homeId = $match['homeTeam']['id'] ?? 0;
                $awayId = $match['awayTeam']['id'] ?? 0;
                $homeGoals = $match['score']['fullTime']['home'] ?? 0;
                $awayGoals = $match['score']['fullTime']['away'] ?? 0;
                
                if ($teamId == $homeId) {
                    $formArray[] = ($homeGoals > $awayGoals) ? 'W' : (($homeGoals == $awayGoals) ? 'D' : 'L');
                } elseif ($teamId == $awayId) {
                    $formArray[] = ($awayGoals > $homeGoals) ? 'W' : (($homeGoals == $awayGoals) ? 'D' : 'L');
                }
            }
            $stats['form'] = implode('', array_slice($formArray, 0, 6));
            $teamStats[$teamId] = $stats;
        }
        return $teamStats[$teamId];
    } catch (Exception $e) {
        handleError("Error calculating team strength: " . $e->getMessage());
    }
}

// Match prediction function with error handling
function predictMatch($match, $apiKey, $baseUrl, &$teamStats) {
    try {
        $homeTeamId = $match['homeTeam']['id'] ?? 0;
        $awayTeamId = $match['awayTeam']['id'] ?? 0;
        
        if (!$homeTeamId || !$awayTeamId) {
            return ["N/A", "0%", "", "0-0"];
        }

        $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats);
        $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats);

        if ($homeStats['needsRetry'] || $awayStats['needsRetry']) {
            echo "<script>incompleteTeams = incompleteTeams || [];";
            if ($homeStats['needsRetry']) echo "incompleteTeams.push($homeTeamId);";
            if ($awayStats['needsRetry']) echo "incompleteTeams.push($awayTeamId);";
            echo "</script>";
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

        $homeTeam = $match['homeTeam']['name'] ?? 'TBD';
        $awayTeam = $match['awayTeam']['name'] ?? 'TBD';
        $status = $match['status'] ?? 'SCHEDULED';
        $homeGoals = $match['score']['fullTime']['home'] ?? null;
        $awayGoals = $match['score']['fullTime']['away'] ?? null;

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

        $resultIndicator = "";
        if ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null) {
            $actualResult = ($homeGoals > $awayGoals) ? "$homeTeam to win" : (($homeGoals < $awayGoals) ? "$awayTeam to win" : "Draw");
            $resultIndicator = ($prediction === $actualResult) ? "✅" : "❌";
        }

        return [$prediction, $confidence, $resultIndicator, $predictedScore];
    } catch (Exception $e) {
        return ["Error", "N/A", "", "N/A"];
    }
}

// Handle AJAX requests
$action = $_GET['action'] ?? 'main';
switch ($action) {
    case 'fetch_team_data':
        header('Content-Type: application/json');
        $teamId = (int)($_GET['teamId'] ?? 0);
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
            if (($match['homeTeam']['id'] ?? 0) == $teamId) {
                $teamName = $match['homeTeam']['name'] ?? '';
                break;
            } elseif (($match['awayTeam']['id'] ?? 0) == $teamId) {
                $teamName = $match['awayTeam']['name'] ?? '';
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

    case 'predict_match':
        header('Content-Type: application/json');
        $homeId = (int)($_GET['homeId'] ?? 0);
        $awayId = (int)($_GET['awayId'] ?? 0);
        
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
        
        $predictionData = predictMatch([
            'homeTeam' => ['id' => $homeId],
            'awayTeam' => ['id' => $awayId]
        ], $apiKey, $baseUrl, $teamStats);
        
        echo json_encode([
            'success' => true,
            'prediction' => $predictionData[0],
            'confidence' => $predictionData[1],
            'resultIndicator' => $predictionData[2],
            'predictedScore' => $predictionData[3]
        ]);
        exit;
}

// Main page logic (dates in EAT, Nairobi, Kenya)
try {
    $competitionsUrl = $baseUrl . 'competitions';
    $compResponse = fetchWithRetry($competitionsUrl, $apiKey);
    if ($compResponse['error']) {
        if (isset($compResponse['retry'])) {
            echo "</body></html>";
            exit;
        }
        handleError($compResponse['message']);
    }
    $competitions = $compResponse['data']['competitions'] ?? [];

    $selectedComp = $_GET['competition'] ?? ($competitions[0]['code'] ?? 'PL');
    $filter = $_GET['filter'] ?? 'upcoming';
    $customStart = $_GET['start'] ?? '';
    $customEnd = $_GET['end'] ?? '';

    $dateOptions = [
        'yesterday' => ['label' => 'Yesterday', 'date' => date('Y-m-d', strtotime('-1 day'))], // Nairobi time (UTC+3)
        'today' => ['label' => 'Today', 'date' => date('Y-m-d')], // Nairobi time (UTC+3)
        'tomorrow' => ['label' => 'Tomorrow', 'date' => date('Y-m-d', strtotime('+1 day'))], // Nairobi time (UTC+3)
        'week' => ['label' => 'This Week', 'from' => date('Y-m-d', strtotime('monday this week')), 'to' => date('Y-m-d', strtotime('sunday this week'))], // Nairobi time
        'upcoming' => ['label' => 'Next 7 Days', 'from' => date('Y-m-d'), 'to' => date('Y-m-d', strtotime('+7 days'))], // Nairobi time
        'custom' => ['label' => 'Custom Range']
    ];

    $filterLabel = ($filter === 'custom' && $customStart && $customEnd) 
        ? "Custom: $customStart to $customEnd" 
        : ($dateOptions[$filter]['label'] ?? 'Select Date');

    $fromDate = $toDate = date('Y-m-d'); // Nairobi time (UTC+3)
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
    $matchResponse = fetchWithRetry($matchesUrl, $apiKey);
    if ($matchResponse['error']) {
        if (isset($matchResponse['retry'])) {
            echo "</body></html>";
            exit;
        }
        handleError($matchResponse['message']);
    }
    $allMatches = $matchResponse['data']['matches'] ?? [];
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

        /* New styles for countdown output */
        .countdown-box {
            background-color: rgba(220, 53, 69, 0.1); /* Light red background */
            border: 2px solid #dc3545; /* Red border */
            border-radius: 10px;
            padding: 15px;
            margin: 20px auto;
            max-width: 500px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Arial', sans-serif;
        }

        .retry-text {
            color: #dc3545; /* Red text */
            font-weight: bold;
        }

        .countdown-timer {
            display: inline-block;
            background-color: #dc3545; /* Red background for timer */
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            margin: 0 5px;
            font-size: 1.4em;
            min-width: 40px;
            text-align: center;
            transition: all 0.3s ease;
        }

        /* Dark theme adjustments */
        [data-theme="dark"] .countdown-box {
            background-color: rgba(220, 53, 69, 0.2);
            border-color: #e74c3c;
        }

        [data-theme="dark"] .retry-text {
            color: #e74c3c;
        }

        [data-theme="dark"] .countdown-timer {
            background-color: #e74c3c;
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
            <p>Select Competition and Date Range (Times in EAT - Nairobi, Kenya)</p>
        </div>

        <div class="controls">
            <select onchange="updateUrl(this.value, '<?php echo $filter; ?>')">
                <?php
                foreach ($competitions as $comp) {
                    $code = $comp['code'] ?? '';
                    $name = $comp['name'] ?? 'Unknown';
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
                        echo $option['label'] ?? '';
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
                        $homeTeamId = $match['homeTeam']['id'] ?? 0;
                        $awayTeamId = $match['awayTeam']['id'] ?? 0;
                        $homeTeam = $match['homeTeam']['name'] ?? 'TBD';
                        $awayTeam = $match['awayTeam']['name'] ?? 'TBD';
                        $date = $match['utcDate'] ?? 'TBD' ? date('M d, Y H:i', strtotime($match['utcDate'])) : 'TBD'; // API UTC converted to EAT (Nairobi, Kenya)
                        $homeCrest = $match['homeTeam']['crest'] ?? '';
                        $awayCrest = $match['awayTeam']['crest'] ?? '';
                        $status = $match['status'];
                        $homeGoals = $match['score']['fullTime']['home'] ?? null;
                        $awayGoals = $match['score']['fullTime']['away'] ?? null;
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
                        $homeForm = substr($homeStats['form'], -6);
                        $homeForm = str_pad($homeForm, 6, '-', STR_PAD_LEFT);
                        $homeForm = strrev($homeForm);
                        for ($i = 0; $i < 6; $i++) {
                            $class = $homeForm[$i] === 'W' ? 'win' : ($homeForm[$i] === 'D' ? 'draw' : ($homeForm[$i] === 'L' ? 'loss' : 'empty'));
                            if ($i === 5 && $homeForm[$i] !== '-' && strlen(trim($homeStats['form'], '-')) > 0) $class .= ' latest';
                            echo "<span class='$class'>" . $homeForm[$i] . "</span>";
                        }
                        echo "</div>
                                </div>
                                <span class='vs'>VS</span>
                                <div class='team'>
                                    " . ($awayCrest ? "<img src='$awayCrest' alt='$awayTeam'>" : "") . "
                                    <p>$awayTeam</p>
                                    <div class='form-display' id='form-away-$index'>";
                        $awayForm = substr($awayStats['form'], -6);
                        $awayForm = str_pad($awayForm, 6, '-', STR_PAD_LEFT);
                        $awayForm = strrev($awayForm);
                        for ($i = 0; $i < 6; $i++) {
                            $class = $awayForm[$i] === 'W' ? 'win' : ($awayForm[$i] === 'D' ? 'draw' : ($awayForm[$i] === 'L' ? 'loss' : 'empty'));
                            if ($i === 5 && $awayForm[$i] !== '-' && strlen(trim($awayStats['form'], '-')) > 0) $class .= ' latest';
                            echo "<span class='$class'>" . $awayForm[$i] . "</span>";
                        }
                        echo "</div>
                                </div>
                            </div>
                            <div class='match-info " . (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark' : '') . "'>
                                <p>$date ($status)" . ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null ? " - $homeGoals : $awayGoals" : "") . "</p>
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
                if (start && end) url += `&start=${start}&end=${end}`;
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
                headers: { 'X-Auth-Token': '<?php echo $apiKey; ?>' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const formElement = document.getElementById(`form-${isHome ? 'home' : 'away'}-${index}`);
                    const historyElement = document.getElementById(`history-${index}`);
                    const predictionElement = document.getElementById(`prediction-${index}`);

                    let formHtml = '';
                    const form = data.form.slice(-6).padStart(6, '-');
                    const reversedForm = form.split('').reverse().join('');
                    for (let i = 0; i < 6; i++) {
                        let className = reversedForm[i] === 'W' ? 'win' : (reversedForm[i] === 'D' ? 'draw' : (reversedForm[i] === 'L' ? 'loss' : 'empty'));
                        if (i === 5 && reversedForm[i] !== '-' && form.trim('-').length > 0) className += ' latest';
                        formHtml += `<span class="${className}">${reversedForm[i]}</span>`;
                    }
                    formElement.innerHTML = formHtml;

                    let historyHtml = '';
                    if (isHome) {
                        historyHtml += `<p><strong>${data.teamName} Recent Results:</strong></p><ul>`;
                        data.results.forEach(result => historyHtml += `<li>${result}</li>`);
                        historyHtml += '</ul>';
                        historyElement.innerHTML = historyHtml + historyElement.innerHTML;
                    } else {
                        historyHtml = historyElement.innerHTML.replace('Loading history...', '');
                        historyHtml += `<p><strong>${data.teamName} Recent Results:</strong></p><ul>`;
                        data.results.forEach(result => historyHtml += `<li>${result}</li>`);
                        historyHtml += '</ul>';
                        historyElement.innerHTML = historyHtml;
                    }

                    const matchCard = document.querySelector(`.match-card[data-index="${index}"]`);
                    const otherTeamLoaded = isHome ? 
                        !document.getElementById(`form-away-${index}`).innerHTML.includes('-') : 
                        !document.getElementById(`form-home-${index}`).innerHTML.includes('-');
                    if (otherTeamLoaded) fetchPrediction(index, matchCard.dataset.homeId, matchCard.dataset.awayId);
                }
            })
            .catch(error => {
                console.error('Error fetching team data:', error);
                setTimeout(() => fetchTeamData(teamId, index, isHome), 5000);
            });
        }

        function fetchPrediction(index, homeId, awayId) {
            fetch(`?action=predict_match&homeId=${homeId}&awayId=${awayId}`, {
                headers: { 'X-Auth-Token': '<?php echo $apiKey; ?>' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`prediction-${index}`).innerHTML = `
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
                document.querySelectorAll('.match-info').forEach(el => el.classList.toggle('dark', theme === 'dark'));
                themeIcon.textContent = theme === 'dark' ? '☀️' : '🌙';
            } else {
                themeIcon.textContent = '🌙';
            }

            const currentFilter = '<?php echo $filter; ?>';
            document.querySelectorAll('.filter-option').forEach(option => {
                option.classList.toggle('selected', option.getAttribute('data-filter') === currentFilter);
            });

            if (currentFilter === 'custom') {
                document.querySelector('.custom-date-range').classList.add('active');
            }

            if (typeof incompleteTeams !== 'undefined' && incompleteTeams.length > 0) {
                incompleteTeams.forEach(teamId => {
                    document.querySelectorAll(`.match-card[data-home-id="${teamId}"], .match-card[data-away-id="${teamId}"]`)
                        .forEach(card => fetchTeamData(teamId, card.dataset.index, card.dataset.homeId == teamId));
                });
            }
        }
    </script>
</body>
</html>
<?php
} catch (Exception $e) {
    handleError("Unexpected error: " . $e->getMessage());
}
?>
    <?php include 'back-to-top.php'; ?>
<?php include 'connpulse.php'; ?>
    
