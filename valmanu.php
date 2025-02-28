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
    $currentDate = date('Y-m-d');
    $url = $baseUrl . "teams/$teamId/matches?dateFrom=$pastDate&dateTo=$currentDate&limit=10&status=FINISHED";
    $data = fetchWithRetry($url, $apiKey);
    return isset($data['matches']) ? $data['matches'] : [];
}

function getLast6Matches($team_name, $fixtures) {
    $results = [];
    
    $fixtures = array_reverse($fixtures);
    
    foreach ($fixtures as $match) {
        if (strcasecmp($match['homeTeam']['name'], $team_name) === 0 || strcasecmp($match['awayTeam']['name'], $team_name) === 0) {
            if ($match['status'] === 'FINISHED' && isset($match['score']['fullTime']['home'], $match['score']['fullTime']['away'])) {
                $home_score = $match['score']['fullTime']['home'];
                $away_score = $match['score']['fullTime']['away'];

                if (strcasecmp($match['homeTeam']['name'], $team_name) === 0) {
                    if ($home_score > $away_score) {
                        $results[] = ['result' => 'W', 'color' => 'green'];
                    } elseif ($home_score < $away_score) {
                        $results[] = ['result' => 'L', 'color' => 'red'];
                    } else {
                        $results[] = ['result' => 'D', 'color' => 'blue'];
                    }
                } else {
                    if ($away_score > $home_score) {
                        $results[] = ['result' => 'W', 'color' => 'green'];
                    } elseif ($away_score < $home_score) {
                        $results[] = ['result' => 'L', 'color' => 'red'];
                    } else {
                        $results[] = ['result' => 'D', 'color' => 'blue'];
                    }
                }
            }
        }
        if (count($results) >= 6) {
            break;
        }
    }

    $results = array_reverse($results);
    
    if (!empty($results)) {
        $formatted_results = '';
        foreach ($results as $index => $result) {
            $style = $index === count($results) - 1 ? 'font-weight: bold; text-decoration: underline;' : '';
            $formatted_results .= "<span style='color: {$result['color']}; $style; display: inline-block; line-height: 1; padding: 0; margin: 0;'>{$result['result']}</span>";
        }
        return $formatted_results;
    }
    
    return "N/A";
}

function calculateTeamStrength($teamId, $apiKey, $baseUrl, &$teamStats) {
    if (!isset($teamStats[$teamId]) || empty($teamStats[$teamId]['results'])) {
        $results = fetchTeamResults($teamId, $apiKey, $baseUrl);
        if ($results === false) {
            $teamStats[$teamId] = ['results' => [], 'needsRetry' => true];
            echo "<script>var incompleteTeams = (typeof incompleteTeams === 'undefined' ? [] : incompleteTeams); incompleteTeams.push($teamId);</script>";
            return $teamStats[$teamId];
        }
        
        $stats = [
            'wins' => 0, 
            'draws' => 0, 
            'goalsScored' => 0, 
            'goalsConceded' => 0, 
            'games' => 0, 
            'results' => [],
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

    if (empty($homeStats['results']) || empty($awayStats['results'])) {
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

    $form = getLast6Matches($teamName, $results);

    echo json_encode([
        'success' => true,
        'teamName' => $teamName,
        'results' => $stats['results'],
        'form' => $form
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

if ($action === 'fetch_matches') {
    header('Content-Type: application/json');
    $competition = isset($_GET['competition']) ? $_GET['competition'] : 'PL';
    $fromDate = isset($_GET['fromDate']) ? $_GET['fromDate'] : date('Y-m-d');
    $toDate = isset($_GET['toDate']) ? $_GET['toDate'] : date('Y-m-d', strtotime('+7 days'));

    $matchesUrl = $baseUrl . "competitions/$competition/matches?dateFrom=$fromDate&dateTo=$toDate";
    $matchData = fetchWithRetry($matchesUrl, $apiKey);
    
    if ($matchData === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch matches']);
        exit;
    }
    
    $allMatches = isset($matchData['matches']) ? $matchData['matches'] : [];
    $html = '';
    
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

                $homeFixtures = fetchTeamResults($homeTeamId, $apiKey, $baseUrl);
                $awayFixtures = fetchTeamResults($awayTeamId, $apiKey, $baseUrl);
                $homeForm = getLast6Matches($homeTeam, $homeFixtures);
                $awayForm = getLast6Matches($awayTeam, $awayFixtures);

                if ($homeForm === "N/A" || empty($homeFixtures)) {
                    echo "<script>var incompleteTeams = (typeof incompleteTeams === 'undefined' ? [] : incompleteTeams); if (!incompleteTeams.includes($homeTeamId)) incompleteTeams.push($homeTeamId);</script>";
                }
                if ($awayForm === "N/A" || empty($awayFixtures)) {
                    echo "<script>var incompleteTeams = (typeof incompleteTeams === 'undefined' ? [] : incompleteTeams); if (!incompleteTeams.includes($awayTeamId)) incompleteTeams.push($awayTeamId);</script>";
                }

                $html .= "
                <div class='match-card' data-home-id='$homeTeamId' data-away-id='$awayTeamId' data-index='$index'>
                    <div class='teams'>
                        <div class='team'>
                            " . ($homeCrest ? "<img src='$homeCrest' alt='$homeTeam'>" : "") . "
                            <p>$homeTeam</p>
                            <div class='form-display' id='form-home-$index'>$homeForm</div>
                        </div>
                        <span class='vs'>VS</span>
                        <div class='team'>
                            " . ($awayCrest ? "<img src='$awayCrest' alt='$awayTeam'>" : "") . "
                            <p>$awayTeam</p>
                            <div class='form-display' id='form-away-$index'>$awayForm</div>
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
                    $html .= "
                        <p><strong>$homeTeam Recent Results:</strong></p>
                        <ul>";
                    foreach ($homeStats['results'] as $result) {
                        $html .= "<li>$result</li>";
                    }
                    $html .= "</ul>
                        <p><strong>$awayTeam Recent Results:</strong></p>
                        <ul>";
                    foreach ($awayStats['results'] as $result) {
                        $html .= "<li>$result</li>";
                    }
                    $html .= "</ul>";
                } else {
                    $html .= "<p>Loading history...</p>";
                }
                
                $html .= "</div></div>";
            }
        }
    } else {
        $html .= "<p>No matches available for the selected competition and date range.</p>";
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
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
            <select id="competition-select" onchange="updateMatches(this.value, '<?php echo $filter; ?>')">
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
                <button class="filter-dropdown-btn" id="filter-btn"><?php echo $filterLabel; ?></button>
                <div class="filter-dropdown" id="filter-dropdown">
                    <?php
                    foreach ($dateOptions as $key => $option) {
                        $selectedClass = $filter === $key ? 'selected' : '';
                        echo "<div class='filter-option $selectedClass' data-filter='$key' onclick='selectFilter(\"$key\")'>";
                        echo isset($option['label']) ? $option['label'] : '';
                        echo "</div>";
                    }
                    ?>
                    <div class="custom-date-range" id="custom-date-range">
                        <input type="date" id="custom-start" value="<?php echo $customStart; ?>">
                        <input type="date" id="custom-end" value="<?php echo $customEnd; ?>">
                        <button onclick="applyCustomDate()">Apply</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="match-grid" id="match-grid">
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

                        $homeFixtures = fetchTeamResults($homeTeamId, $apiKey, $baseUrl);
                        $awayFixtures = fetchTeamResults($awayTeamId, $apiKey, $baseUrl);
                        $homeForm = getLast6Matches($homeTeam, $homeFixtures);
                        $awayForm = getLast6Matches($awayTeam, $awayFixtures);

                        if ($homeForm === "N/A" || empty($homeFixtures)) {
                            echo "<script>var incompleteTeams = (typeof incompleteTeams === 'undefined' ? [] : incompleteTeams); if (!incompleteTeams.includes($homeTeamId)) incompleteTeams.push($homeTeamId);</script>";
                        }
                        if ($awayForm === "N/A" || empty($awayFixtures)) {
                            echo "<script>var incompleteTeams = (typeof incompleteTeams === 'undefined' ? [] : incompleteTeams); if (!incompleteTeams.includes($awayTeamId)) incompleteTeams.push($awayTeamId);</script>";
                        }

                        echo "
                        <div class='match-card' data-home-id='$homeTeamId' data-away-id='$awayTeamId' data-index='$index'>
                            <div class='teams'>
                                <div class='team'>
                                    " . ($homeCrest ? "<img src='$homeCrest' alt='$homeTeam'>" : "") . "
                                    <p>$homeTeam</p>
                                    <div class='form-display' id='form-home-$index'>$homeForm</div>
                                </div>
                                <span class='vs'>VS</span>
                                <div class='team'>
                                    " . ($awayCrest ? "<img src='$awayCrest' alt='$awayTeam'>" : "") . "
                                    <p>$awayTeam</p>
                                    <div class='form-display' id='form-away-$index'>$awayForm</div>
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

        function updateMatches(competition, filter) {
            let fromDate, toDate;
            const dateOptions = <?php echo json_encode($dateOptions); ?>;
            
            if (filter === 'custom') {
                fromDate = document.getElementById('custom-start').value;
                toDate = document.getElementById('custom-end').value;
                if (!fromDate || !toDate) return; // Wait for custom dates
            } else if (dateOptions[filter]) {
                if (dateOptions[filter].date) {
                    fromDate = toDate = dateOptions[filter].date;
                } else {
                    fromDate = dateOptions[filter].from;
                    toDate = dateOptions[filter].to;
                }
            }

            fetchMatches(competition, fromDate, toDate);
        }

        function fetchMatches(competition, fromDate, toDate) {
            window.incompleteTeams = []; // Reset incomplete teams
            fetch(`?action=fetch_matches&competition=${competition}&fromDate=${fromDate}&toDate=${toDate}`, {
                headers: {
                    'X-Auth-Token': '<?php echo $apiKey; ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('match-grid').innerHTML = data.html;
                    if (window.incompleteTeams && window.incompleteTeams.length > 0) {
                        window.incompleteTeams.forEach(teamId => {
                            const matchCards = document.querySelectorAll(`.match-card[data-home-id="${teamId}"], .match-card[data-away-id="${teamId}"]`);
                            matchCards.forEach(card => {
                                const index = card.dataset.index;
                                const isHome = card.dataset.homeId == teamId;
                                setTimeout(() => fetchTeamData(teamId, index, isHome), 5000);
                            });
                        });
                    }
                } else {
                    console.error('Error fetching matches:', data.error);
                }
            })
            .catch(error => console.error('Error fetching matches:', error));
        }

        function selectFilter(filter) {
            const filterBtn = document.getElementById('filter-btn');
            const dropdown = document.getElementById('filter-dropdown');
            const customRange = document.getElementById('custom-date-range');
            const options = document.querySelectorAll('.filter-option');
            const dateOptions = <?php echo json_encode($dateOptions); ?>;

            options.forEach(opt => {
                if (opt.getAttribute('data-filter') === filter) {
                    opt.classList.add('selected');
                    filterBtn.textContent = dateOptions[filter].label || 'Select Date';
                } else {
                    opt.classList.remove('selected');
                }
            });

            if (filter === 'custom') {
                customRange.style.display = 'block';
                dropdown.classList.add('active');
            } else {
                customRange.style.display = 'none';
                dropdown.classList.remove('active');
                updateMatches(document.getElementById('competition-select').value, filter);
            }
        }

        function applyCustomDate() {
            const competition = document.getElementById('competition-select').value;
            const fromDate = document.getElementById('custom-start').value;
            const toDate = document.getElementById('custom-end').value;
            document.getElementById('filter-btn').textContent = `Custom: ${fromDate} to ${toDate}`;
            document.getElementById('filter-dropdown').classList.remove('active');
            fetchMatches(competition, fromDate, toDate);
        }

        document.getElementById('filter-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('filter-dropdown');
            dropdown.classList.toggle('active');
            const customRange = document.getElementById('custom-date-range');
            customRange.style.display = '<?php echo $filter === 'custom' ? 'block' : 'none'; ?>';
        });

        document.addEventListener('click', function(e) {
            const container = document.querySelector('.filter-container');
            if (!container.contains(e.target)) {
                document.getElementById('filter-dropdown').classList.remove('active');
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

                    formElement.innerHTML = data.form;

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
                        document.getElementById(`form-away-${index}`).innerHTML !== 'N/A' : 
                        document.getElementById(`form-home-${index}`).innerHTML !== 'N/A';
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
                document.getElementById('custom-date-range').style.display = 'block';
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
                        fetchMatches('<?php echo $selectedComp; ?>', '<?php echo $fromDate; ?>', '<?php echo $toDate; ?>');
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
                        setTimeout(() => fetchTeamData(teamId, index, isHome), 5000);
                    });
                });
            }
        }
    </script>
</body>
</html>
