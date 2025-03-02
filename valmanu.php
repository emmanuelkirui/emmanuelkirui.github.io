<?php
session_start();
require_once 'recaptcha_handler.php';

// Set timezone to East Africa Time (Nairobi, Kenya, UTC+3)
date_default_timezone_set('Africa/Nairobi');

// Initialize session data with error handling
if (!isset($_SESSION['teamStats'])) {
    $_SESSION['teamStats'] = [];
}

$apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c';
$baseUrl = 'http://api.football-data.org/v4/';
$teamStats = &$_SESSION['teamStats'];

// Error handling function for HTML output
function handleError($message) {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo "<!DOCTYPE html><html><body><div style='text-align: center; padding: 20px; color: #dc3545;'>";
    echo "<h2>Error</h2><p>" . htmlspecialchars($message) . "</p>";
    echo "</div></body></html>";
    exit;
}

// Error handling function for JSON output
function handleJsonError($message) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Enhanced fetch function with retry mechanism
function fetchWithRetry($url, $apiKey, $isAjax = false) {
    $maxAttempts = 5;
    $attempt = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;
    
    if ($attempt >= $maxAttempts) {
        return ['error' => true, 'message' => 'Max retry attempts reached'];
    }

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
        $retrySeconds = min(pow(2, $attempt) * 2, 60);
        $nextAttempt = $attempt + 1;
        
        preg_match('/Retry-After: (\d+)/i', $headers, $matches);
        if (!empty($matches[1])) {
            $retrySeconds = max($retrySeconds, (int)$matches[1]);
        }
        
        if ($isAjax) {
            return [
                'error' => true,
                'retry' => true,
                'retrySeconds' => $retrySeconds,
                'nextAttempt' => $nextAttempt
            ];
        } else {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    let timeLeft = $retrySeconds;
                    const retryDiv = document.createElement('div');
                    retryDiv.id = 'retry-message';
                    retryDiv.className = 'retry-message countdown-box';
                    retryDiv.innerHTML = '<span class=\"retry-text\">Rate limit hit. Attempt ' + $nextAttempt + ' of $maxAttempts. Retrying in </span><span id=\"countdown\" class=\"countdown-timer\">' + timeLeft + '</span><span class=\"retry-text\"> seconds...</span>';
                    document.body.insertBefore(retryDiv, document.body.firstChild.nextSibling);
                    
                    const timer = setInterval(() => {
                        timeLeft--;
                        document.getElementById('countdown').textContent = timeLeft;
                        if (timeLeft <= 0) {
                            clearInterval(timer);
                            let url = window.location.pathname + window.location.search;
                            url += (window.location.search ? '&' : '?') + 'attempt=' + $nextAttempt;
                            window.location.href = url;
                        }
                    }, 1000);
                });
            </script>";
            return ['error' => true, 'retry' => true];
        }
    } elseif ($httpCode == 200) {
        return ['error' => false, 'data' => json_decode($body, true)];
    } else {
        return ['error' => true, 'message' => "API Error: HTTP $httpCode"];
    }
}

// Team results fetch function
function fetchTeamResults($teamId, $apiKey, $baseUrl) {
    $pastDate = date('Y-m-d', strtotime('-60 days'));
    $currentDate = date('Y-m-d');
    $url = $baseUrl . "teams/$teamId/matches?dateFrom=$pastDate&dateTo=$currentDate&limit=10&status=FINISHED";
    
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) {
        return $response;
    }
    return ['error' => false, 'data' => $response['data']['matches'] ?? []];
}

// Fetch standings data
function fetchStandings($competition, $apiKey, $baseUrl) {
    $url = $baseUrl . "competitions/$competition/standings";
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) {
        return $response;
    }
    return ['error' => false, 'data' => $response['data']['standings'][0]['table'] ?? []];
}

// Fetch teams for autocomplete
function fetchTeams($competition, $apiKey, $baseUrl) {
    $url = $baseUrl . "competitions/$competition/teams";
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) {
        return [];
    }
    return $response['data']['teams'] ?? [];
}

// Team strength calculation with standings
function calculateTeamStrength($teamId, $apiKey, $baseUrl, &$teamStats, $competition) {
    try {
        if (!isset($teamStats[$teamId]) || empty($teamStats[$teamId]['results']) || empty($teamStats[$teamId]['form'])) {
            $response = fetchTeamResults($teamId, $apiKey, $baseUrl);
            if ($response['error']) {
                $teamStats[$teamId] = ['results' => [], 'form' => '', 'needsRetry' => true, 'standings' => []];
                return $teamStats[$teamId];
            }
            $results = $response['data'];

            $standingsResponse = fetchStandings($competition, $apiKey, $baseUrl);
            $standings = $standingsResponse['error'] ? [] : $standingsResponse['data'];

            $stats = [
                'wins' => 0, 'draws' => 0, 'goalsScored' => 0, 
                'goalsConceded' => 0, 'games' => 0, 'results' => [],
                'form' => '', 'needsRetry' => false, 'standings' => []
            ];

            foreach ($results as $match) {
                $homeId = $match['homeTeam']['id'] ?? 0;
                $awayId = $match['awayTeam']['id'] ?? 0;
                $homeGoals = $match['score']['fullTime']['home'] ?? 0;
                $awayGoals = $match['score']['fullTime']['away'] ?? 0;
                $date = date('M d', strtotime($match['utcDate']));
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

            foreach ($standings as $standing) {
                if ($standing['team']['id'] == $teamId) {
                    $stats['standings'] = [
                        'position' => $standing['position'],
                        'goalsScored' => $standing['goalsFor'],
                        'goalDifference' => $standing['goalDifference'],
                        'points' => $standing['points']
                    ];
                    break;
                }
            }

            $teamStats[$teamId] = $stats;
        }
        return $teamStats[$teamId];
    } catch (Exception $e) {
        return ['error' => true, 'message' => "Error calculating team strength: " . $e->getMessage()];
    }
}

// Enhanced match prediction function
function predictMatch($match, $apiKey, $baseUrl, &$teamStats, $competition) {
    try {
        $homeTeamId = $match['homeTeam']['id'] ?? 0;
        $awayTeamId = $match['awayTeam']['id'] ?? 0;

        if (!$homeTeamId || !$awayTeamId) {
            return ["N/A", "0%", "", "0-0", ""];
        }

        $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats, $competition);
        $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats, $competition);

        if ($homeStats['needsRetry'] || $awayStats['needsRetry']) {
            return ["Loading...", "N/A", "", "N/A", ""];
        }

        $homeFormWeight = calculateFormWeight($homeStats['form']);
        $awayFormWeight = calculateFormWeight($awayStats['form']);

        $homeStandingFactor = isset($homeStats['standings']['position']) ? (20 - min($homeStats['standings']['position'], 20)) / 20 : 0.5;
        $awayStandingFactor = isset($awayStats['standings']['position']) ? (20 - min($awayStats['standings']['position'], 20)) / 20 : 0.5;

        $homeWinRate = $homeStats['games'] ? $homeStats['wins'] / $homeStats['games'] : 0;
        $homeDrawRate = $homeStats['games'] ? $homeStats['draws'] / $homeStats['games'] : 0;
        $awayWinRate = $awayStats['games'] ? $awayStats['wins'] / $awayStats['games'] : 0;
        $awayDrawRate = $awayStats['games'] ? $awayStats['draws'] / $awayStats['games'] : 0;
        $homeGoalAvg = $homeStats['games'] ? $homeStats['goalsScored'] / $homeStats['games'] : 0;
        $awayGoalAvg = $awayStats['games'] ? $awayStats['goalsScored'] / $awayStats['games'] : 0;

        $homeStrength = ($homeWinRate * 40 + $homeDrawRate * 15 + $homeGoalAvg * 15 + $homeFormWeight * 20 + $homeStandingFactor * 10) * 1.1;
        $awayStrength = ($awayWinRate * 40 + $awayDrawRate * 15 + $awayGoalAvg * 15 + $awayFormWeight * 20 + $awayStandingFactor * 10);

        $diff = $homeStrength - $awayStrength;
        $confidence = min(95, abs($diff) / ($homeStrength + $awayStrength + 1) * 100);

        $predictedHomeGoals = max(0, round($homeGoalAvg * (1 + $diff / 100) + rand(-1, 1) * 0.2));
        $predictedAwayGoals = max(0, round($awayGoalAvg * (1 - $diff / 100) + rand(-1, 1) * 0.2));
        $predictedScore = "$predictedHomeGoals-$predictedAwayGoals";

        $homeTeam = $match['homeTeam']['name'] ?? 'TBD';
        $awayTeam = $match['awayTeam']['name'] ?? 'TBD';
        $status = $match['status'] ?? 'SCHEDULED';
        $homeGoals = $match['score']['fullTime']['home'] ?? null;
        $awayGoals = $match['score']['fullTime']['away'] ?? null;

        if ($diff > 20) {
            $prediction = "$homeTeam to win";
            $confidence = sprintf("%.1f%%", $confidence);
            $advantage = "Home Advantage";
        } elseif ($diff < -20) {
            $prediction = "$awayTeam to win";
            $confidence = sprintf("%.1f%%", $confidence);
            $advantage = "Away Advantage";
        } else {
            $prediction = "Draw";
            $confidence = sprintf("%.1f%%", min(75, $confidence));
            $advantage = "Likely Draw";
        }

        $resultIndicator = "";
        if ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null) {
            $actualResult = ($homeGoals > $awayGoals) ? "$homeTeam to win" : (($homeGoals < $awayGoals) ? "$awayTeam to win" : "Draw");
            $resultIndicator = ($prediction === $actualResult) ? "✅" : "❌";
        }

        return [$prediction, $confidence, $resultIndicator, $predictedScore, $advantage];
    } catch (Exception $e) {
        return ["Error", "N/A", "", "N/A", ""];
    }
}

// Helper function to weight recent form
function calculateFormWeight($form) {
    $formArray = str_split(substr($form, -3));
    $weight = 0;
    foreach ($formArray as $result) {
        $weight += ($result === 'W' ? 1 : ($result === 'D' ? 0.5 : 0));
    }
    return $weight / 3;
}

// Progress stream for incomplete data
if (isset($_GET['action']) && $_GET['action'] === 'progress_stream') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $teamIds = json_decode($_GET['teamIds'] ?? '[]', true);
    $progress = 0;
    $totalTeams = count($teamIds);

    foreach ($teamIds as $index => $teamId) {
        $progress = ($index + 1) / $totalTeams * 100;
        $stats = calculateTeamStrength($teamId, $apiKey, $baseUrl, $teamStats, $_GET['competition'] ?? 'PL');

        if ($stats['needsRetry']) {
            echo "data: " . json_encode([
                'teamId' => $teamId,
                'progress' => $progress,
                'status' => 'retrying',
                'retrySeconds' => min(pow(2, $index), 32)
            ]) . "\n\n";
            ob_flush();
            flush();
            sleep(2);
            continue;
        }

        echo "data: " . json_encode([
            'teamId' => $teamId,
            'progress' => $progress,
            'status' => 'complete',
            'form' => $stats['form'],
            'results' => $stats['results'],
            'standings' => $stats['standings'],
            'updatedPrediction' => predictMatchForTeam($teamId, $teamStats, $_GET['competition'] ?? 'PL', $apiKey, $baseUrl)
        ]) . "\n\n";
        ob_flush();
        flush();
    }

    echo "data: " . json_encode(['complete' => true]) . "\n\n";
    ob_flush();
    flush();
    exit;
}

// Helper function to re-predict matches involving this team
function predictMatchForTeam($teamId, &$teamStats, $competition, $apiKey, $baseUrl) {
    $matchesUrl = $baseUrl . "competitions/$competition/matches?dateFrom=" . date('Y-m-d') . "&dateTo=" . date('Y-m-d', strtotime('+7 days'));
    $matchResponse = fetchWithRetry($matchesUrl, $apiKey);
    if ($matchResponse['error']) return null;

    foreach ($matchResponse['data']['matches'] as $match) {
        if ($match['homeTeam']['id'] == $teamId || $match['awayTeam']['id'] == $teamId) {
            return predictMatch($match, $apiKey, $baseUrl, $teamStats, $competition);
        }
    }
    return null;
}

// Handle AJAX requests
$action = $_GET['action'] ?? 'main';
if (isset($_GET['ajax']) || in_array($action, ['fetch_team_data', 'predict_match', 'search_teams', 'submit_feedback'])) {
    header('Content-Type: application/json');
    switch ($action) {
        case 'fetch_team_data':
            $teamId = (int)($_GET['teamId'] ?? 0);
            if (!$teamId) handleJsonError('Invalid team ID');
            
            $response = fetchTeamResults($teamId, $apiKey, $baseUrl);
            if ($response['error']) {
                if (isset($response['retry'])) {
                    echo json_encode([
                        'success' => false,
                        'retry' => true,
                        'retrySeconds' => $response['retrySeconds'],
                        'nextAttempt' => $response['nextAttempt']
                    ]);
                    exit;
                }
                handleJsonError($response['message']);
            }
            $results = $response['data'];
            if (empty($results)) handleJsonError('No results fetched');
            
            $stats = calculateTeamStrength($teamId, $apiKey, $baseUrl, $teamStats, $_GET['competition'] ?? 'PL');
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
                'form' => $stats['form'],
                'standings' => $stats['standings']
            ]);
            exit;

        case 'predict_match':
            $homeId = (int)($_GET['homeId'] ?? 0);
            $awayId = (int)($_GET['awayId'] ?? 0);
            
            if (!$homeId || !$awayId) handleJsonError('Invalid team IDs');
            
            $homeStats = calculateTeamStrength($homeId, $apiKey, $baseUrl, $teamStats, $_GET['competition'] ?? 'PL');
            $awayStats = calculateTeamStrength($awayId, $apiKey, $baseUrl, $teamStats, $_GET['competition'] ?? 'PL');
            
            if ($homeStats['needsRetry'] || $awayStats['needsRetry']) {
                echo json_encode(['success' => false, 'retry' => true]);
                exit;
            }
            
            if (empty($homeStats['results']) || empty($awayStats['results'])) {
                handleJsonError('Team stats not loaded');
            }
            
            $predictionData = predictMatch([
                'homeTeam' => ['id' => $homeId],
                'awayTeam' => ['id' => $awayId]
            ], $apiKey, $baseUrl, $teamStats, $_GET['competition'] ?? 'PL');
            
            echo json_encode([
                'success' => true,
                'prediction' => $predictionData[0],
                'confidence' => $predictionData[1],
                'resultIndicator' => $predictionData[2],
                'predictedScore' => $predictionData[3],
                'advantage' => $predictionData[4]
            ]);
            exit;

        case 'search_teams':
            $query = strtolower($_GET['query'] ?? '');
            $competition = $_GET['competition'] ?? 'PL';
            $teams = fetchTeams($competition, $apiKey, $baseUrl);
            $filteredTeams = array_filter($teams, function($team) use ($query) {
                return stripos($team['name'], $query) !== false || stripos($team['shortName'] ?? '', $query) !== false;
            });
            echo json_encode(array_values(array_map(function($team) {
                return ['id' => $team['id'], 'name' => $team['name'], 'crest' => $team['crest'] ?? ''];
            }, $filteredTeams)));
            exit;

        case 'submit_feedback':
            $matchIndex = $_GET['matchIndex'] ?? '';
            $prediction = $_GET['prediction'] ?? '';
            $feedback = $_GET['feedback'] ?? '';
            if ($matchIndex && $prediction && $feedback) {
                file_put_contents('feedback.log', "$matchIndex|$prediction|$feedback|" . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid feedback data']);
            }
            exit;
    }
}

// Navigation bar
if (!isset($_GET['ajax'])) {
    echo "<nav class='navbar'>";
    echo "<div class='navbar-container'>";
    echo "<div class='navbar-brand'>CPS Football</div>";
    echo "<div class='hamburger' onclick='toggleMenu()'><span></span><span></span><span></span></div>";
    echo "<div class='nav-menu' id='navMenu'>";
    echo "<a href='valmanu' class='nav-link'>Home</a>";
    echo "<a href='liv' class='nav-link'>Predictions</a>";
    echo "<a href='javascript:history.back()' class='nav-link'>Back</a>";
    echo "<button class='theme-toggle' onclick='toggleTheme()'><span class='theme-icon'>☀️</span></button>";
    echo "</div>";
    echo "</div>";
    echo "</nav>";

    echo "<script>
    function toggleMenu() {
        const menu = document.getElementById('navMenu');
        const hamburger = document.querySelector('.hamburger');
        const container = document.querySelector('.container');
        menu.classList.toggle('active');
        hamburger.classList.toggle('active');
        
        if (window.innerWidth <= 768) {
            if (menu.classList.contains('active')) {
                const menuHeight = menu.scrollHeight + 20;
                container.style.paddingTop = (60 + menuHeight) + 'px';
            } else {
                container.style.paddingTop = '80px';
            }
        }
    }

    window.addEventListener('resize', function() {
        const menu = document.getElementById('navMenu');
        const hamburger = document.querySelector('.hamburger');
        const container = document.querySelector('.container');
        if (window.innerWidth > 768) {
            menu.classList.remove('active');
            hamburger.classList.remove('active');
            container.style.paddingTop = '80px';
        } else {
            if (menu.classList.contains('active')) {
                const menuHeight = menu.scrollHeight + 20;
                container.style.paddingTop = (60 + menuHeight) + 'px';
            } else {
                container.style.paddingTop = '80px';
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const menu = document.getElementById('navMenu');
        const container = document.querySelector('.container');
        if (window.innerWidth > 768) {
            menu.classList.remove('active');
            container.style.paddingTop = '80px';
        } else {
            container.style.paddingTop = '80px';
        }
    });
    </script>";
}

// Main page logic
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
    $searchTeam = $_GET['team'] ?? '';

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
        : ($dateOptions[$filter]['label'] ?? 'Select Date');

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
    $matchResponse = fetchWithRetry($matchesUrl, $apiKey);
    if ($matchResponse['error']) {
        if (isset($matchResponse['retry'])) {
            echo "</body></html>";
            exit;
        }
        handleError($matchResponse['message']);
    }
    $allMatches = $matchResponse['data']['matches'] ?? [];

    if ($searchTeam) {
        $allMatches = array_filter($allMatches, function($match) use ($searchTeam) {
            return stripos($match['homeTeam']['name'] ?? '', $searchTeam) !== false ||
                   stripos($match['awayTeam']['name'] ?? '', $searchTeam) !== false;
        });
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPS Football Predictions</title>
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
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .navbar {
            width: 100%;
            background-color: var(--card-bg);
            box-shadow: var(--shadow);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            height: 60px;
        }

        .navbar-brand {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-link {
            color: var(--text-color);
            text-decoration: none;
            font-size: 1.1em;
            padding: 12px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            gap: 5px;
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background-color: var(--text-color);
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        .theme-toggle {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .theme-toggle:hover {
            background-color: var(--secondary-color);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 80px 20px 20px;
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
            gap: 10px;
        }

        select {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
        }

        .filter-container {
            position: relative;
            display: inline-block;
        }

        .filter-dropdown-btn {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
        }

        .filter-dropdown-btn::after {
            content: '▼';
            margin-left: 10px;
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
        }

        .filter-dropdown.active {
            display: block;
        }

        .filter-option {
            padding: 10px 15px;
            cursor: pointer;
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
            display: none;
        }

        .custom-date-range.active {
            display: block;
        }

        .custom-date-range input[type="date"] {
            width: 100%;
            margin: 5px 0;
            padding: 5px;
        }

        .custom-date-range button {
            width: 100%;
            margin-top: 10px;
            padding: 8px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
        }

        .search-container {
            position: relative;
            width: 100%;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid var(--primary-color);
            border-radius: 25px;
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--shadow);
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        .autocomplete-item {
            padding: 10px 15px;
            cursor: pointer;
        }

        .autocomplete-item:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .search-container.active .autocomplete-dropdown {
            display: block;
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
        }

        .teams {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
            gap: 5px;
        }

        .team {
            text-align: center;
            flex: 1;
            max-width: 48%;
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
            display: none;
        }

        .past-results ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .standings {
            margin-top: 10px;
            font-size: 0.9em;
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
        }

        .feedback-btn {
            margin-top: 10px;
            padding: 6px 12px;
            background-color: #f39c12;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .form-display {
            display: flex;
            justify-content: center;
            font-family: 'monospace';
            font-size: 16px;
        }

        .form-display span {
            width: 16px;
            text-align: center;
        }

        .form-display .latest {
            border: 2px solid #3498db;
            font-weight: bold;
        }

        .form-display .win { color: #28a745; }
        .form-display .draw { color: #fd7e14; }
        .form-display .loss { color: #dc3545; }
        .form-display .empty { color: #6c757d; }

        .retry-message {
            text-align: center;
            margin: 20px 0;
            font-size: 1.2em;
            color: #dc3545;
        }

        .countdown-box {
            background-color: rgba(220, 53, 69, 0.1);
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 15px;
            margin: 20px auto;
            max-width: 500px;
            display: flex;
            justify-content: center;
        }

        .retry-text {
            color: #dc3545;
            font-weight: bold;
        }

        .countdown-timer {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            margin: 0 5px;
            font-size: 1.4em;
        }

        .loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background-color: #ddd;
            border-radius: 5px;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background-color: var(--primary-color);
            transition: width 0.5s ease;
        }

        .team.home-advantage {
            background-color: rgba(46, 204, 113, 0.2);
            border: 1px solid var(--primary-color);
            border-radius: 5px;
            padding: 2px;
        }

        .team.away-advantage {
            background-color: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
            border-radius: 5px;
            padding: 2px;
        }

        .match-card.draw-likely .teams {
            background-color: rgba(241, 196, 15, 0.2);
            border: 1px solid #f1c40f;
            border-radius: 5px;
            padding: 2px;
        }

        .advantage {
            font-size: 0.9em;
            font-weight: bold;
            margin-top: 5px;
        }

        .advantage-home-advantage { color: var(--primary-color); }
        .advantage-away-advantage { color: #e74c3c; }
        .advantage-likely-draw { color: #f1c40f; }

        @media (max-width: 768px) {
            .hamburger { display: flex; }
            .nav-menu {
                position: absolute;
                top: 60px;
                left: 0;
                width: 100%;
                flex-direction: column;
                background-color: var(--card-bg);
                box-shadow: var(--shadow);
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
            }
            .nav-menu.active { max-height: 500px; }
            .nav-link { width: 100%; text-align: left; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CPS Football Predictions</h1>
            <p>Select Competition, Date Range (EAT), or Search Teams</p>
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

            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search teams..." value="<?php echo htmlspecialchars($searchTeam); ?>">
                <div class="autocomplete-dropdown"></div>
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
                        $date = $match['utcDate'] ?? 'TBD' ? date('M d, Y H:i', strtotime($match['utcDate'])) : 'TBD';
                        $homeCrest = $match['homeTeam']['crest'] ?? '';
                        $awayCrest = $match['awayTeam']['crest'] ?? '';
                        $status = $match['status'];
                        $homeGoals = $match['score']['fullTime']['home'] ?? null;
                        $awayGoals = $match['score']['fullTime']['away'] ?? null;
                        [$prediction, $confidence, $resultIndicator, $predictedScore, $advantage] = predictMatch($match, $apiKey, $baseUrl, $teamStats, $selectedComp);
                        $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats, $selectedComp);
                        $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats, $selectedComp);

                        $needsRetry = $homeStats['needsRetry'] || $awayStats['needsRetry'];
                        if ($needsRetry) {
                            echo "<script>incompleteTeams = incompleteTeams || [];";
                            if ($homeStats['needsRetry']) echo "incompleteTeams.push($homeTeamId);";
                            if ($awayStats['needsRetry']) echo "incompleteTeams.push($awayTeamId);";
                            echo "</script>";
                        }

                        echo "
                        <div class='match-card' data-home-id='$homeTeamId' data-away-id='$awayTeamId' data-index='$index' data-advantage='$advantage'>
                            <div class='teams'>
                                <div class='team home-team'>
                                    " . ($homeCrest ? "<img src='$homeCrest' alt='$homeTeam'>" : "") . "
                                    <p>$homeTeam</p>
                                    <div class='form-display' id='form-home-$index'>";
                        if ($homeStats['needsRetry']) {
                            echo "<div class='loading-spinner'></div>";
                        } else {
                            $homeForm = str_pad(substr($homeStats['form'], -6), 6, '-', STR_PAD_LEFT);
                            $homeForm = strrev($homeForm);
                            for ($i = 0; $i < 6; $i++) {
                                $class = $homeForm[$i] === 'W' ? 'win' : ($homeForm[$i] === 'D' ? 'draw' : ($homeForm[$i] === 'L' ? 'loss' : 'empty'));
                                if ($i === 5 && $homeForm[$i] !== '-' && strlen(trim($homeStats['form'], '-')) > 0) $class .= ' latest';
                                echo "<span class='$class'>" . $homeForm[$i] . "</span>";
                            }
                        }
                        echo "</div>
                                </div>
                                <span class='vs'>VS</span>
                                <div class='team away-team'>
                                    " . ($awayCrest ? "<img src='$awayCrest' alt='$awayTeam'>" : "") . "
                                    <p>$awayTeam</p>
                                    <div class='form-display' id='form-away-$index'>";
                        if ($awayStats['needsRetry']) {
                            echo "<div class='loading-spinner'></div>";
                        } else {
                            $awayForm = str_pad(substr($awayStats['form'], -6), 6, '-', STR_PAD_LEFT);
                            $awayForm = strrev($awayForm);
                            for ($i = 0; $i < 6; $i++) {
                                $class = $awayForm[$i] === 'W' ? 'win' : ($awayForm[$i] === 'D' ? 'draw' : ($awayForm[$i] === 'L' ? 'loss' : 'empty'));
                                if ($i === 5 && $awayForm[$i] !== '-' && strlen(trim($awayStats['form'], '-')) > 0) $class .= ' latest';
                                echo "<span class='$class'>" . $awayForm[$i] . "</span>";
                            }
                        }
                        echo "</div>
                                </div>
                            </div>
                            <div class='match-info " . (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark' : '') . "'>
                                <p>$date ($status)" . ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null ? " - $homeGoals : $awayGoals" : "") . "</p>
                            </div>
                            <div class='prediction' id='prediction-$index'>";
                        if ($needsRetry) {
                            echo "<p>Loading prediction...</p>
                                  <div class='progress-bar'><div class='progress-fill' style='width: 0%;'></div></div>";
                        } else {
                            echo "<p>Prediction: $prediction <span class='result-indicator'>$resultIndicator</span></p>
                                  <p class='predicted-score'>Predicted Score: $predictedScore</p>
                                  <p class='confidence'>Confidence: $confidence</p>
                                  <p class='advantage advantage-" . strtolower(str_replace(' ', '-', $advantage)) . "'>$advantage</p>
                                  <button class='feedback-btn' onclick='provideFeedback($index, \"$prediction\")'>Feedback</button>";
                        }
                        echo "</div>
                            <button class='view-history-btn' onclick='toggleHistory(this)'>👁️ View History</button>
                            <div class='past-results' id='history-$index'>";
                        
                        if (!empty($homeStats['results']) && !empty($awayStats['results']) && !$needsRetry) {
                            echo "
                                <p><strong>$homeTeam Recent Results:</strong></p>
                                <ul>";
                            foreach ($homeStats['results'] as $result) {
                                echo "<li>$result</li>";
                            }
                            echo "</ul>
                                <div class='standings'>
                                    <span>POS: " . ($homeStats['standings']['position'] ?? 'N/A') . "</span>
                                    <span>GS: " . ($homeStats['standings']['goalsScored'] ?? 'N/A') . "</span>
                                    <span>GD: " . ($homeStats['standings']['goalDifference'] ?? 'N/A') . "</span>
                                    <span>PTS: " . ($homeStats['standings']['points'] ?? 'N/A') . "</span>
                                </div>
                                <p><strong>$awayTeam Recent Results:</strong></p>
                                <ul>";
                            foreach ($awayStats['results'] as $result) {
                                echo "<li>$result</li>";
                            }
                            echo "</ul>
                                <div class='standings'>
                                    <span>POS: " . ($awayStats['standings']['position'] ?? 'N/A') . "</span>
                                    <span>GS: " . ($awayStats['standings']['goalsScored'] ?? 'N/A') . "</span>
                                    <span>GD: " . ($awayStats['standings']['goalDifference'] ?? 'N/A') . "</span>
                                    <span>PTS: " . ($awayStats['standings']['points'] ?? 'N/A') . "</span>
                                </div>";
                        } else {
                            echo "<p>Loading history...</p>";
                        }
                        
                        echo "</div></div>";
                    }
                }
            } else {
                echo "<p>No matches available for the selected competition, date range, or team search.</p>";
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
            document.querySelector('.theme-icon').textContent = newTheme === 'dark' ? '☀️' : '🌙';
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
            const searchTeam = document.querySelector('.search-input').value.trim();
            if (searchTeam) url += `&team=${encodeURIComponent(searchTeam)}`;
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
            if ('<?php echo $filter; ?>' === 'custom') customRange.classList.add('active');
            else customRange.classList.remove('active');
        });

        document.addEventListener('click', function(e) {
            const container = document.querySelector('.filter-container');
            if (!container.contains(e.target)) document.querySelector('.filter-dropdown').classList.remove('active');
        });

        function fetchTeamData(teamId, index, isHome) {
            fetch(`?action=fetch_team_data&teamId=${teamId}&competition=<?php echo $selectedComp; ?>`, {
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
                        historyHtml += `</ul><div class='standings'>
                            <span>POS: ${data.standings.position || 'N/A'}</span>
                            <span>GS: ${data.standings.goalsScored || 'N/A'}</span>
                            <span>GD: ${data.standings.goalDifference || 'N/A'}</span>
                            <span>PTS: ${data.standings.points || 'N/A'}</span>
                        </div>`;
                        historyElement.innerHTML = historyHtml + historyElement.innerHTML;
                    } else {
                        historyHtml = historyElement.innerHTML.replace('Loading history...', '');
                        historyHtml += `<p><strong>${data.teamName} Recent Results:</strong></p><ul>`;
                        data.results.forEach(result => historyHtml += `<li>${result}</li>`);
                        historyHtml += `</ul><div class='standings'>
                            <span>POS: ${data.standings.position || 'N/A'}</span>
                            <span>GS: ${data.standings.goalsScored || 'N/A'}</span>
                            <span>GD: ${data.standings.goalDifference || 'N/A'}</span>
                            <span>PTS: ${data.standings.points || 'N/A'}</span>
                        </div>`;
                        historyElement.innerHTML = historyHtml;
                    }

                    const matchCard = document.querySelector(`.match-card[data-index="${index}"]`);
                    const otherTeamLoaded = isHome ? 
                        !document.getElementById(`form-away-${index}`).innerHTML.includes('-') : 
                        !document.getElementById(`form-home-${index}`).innerHTML.includes('-');
                    if (otherTeamLoaded) fetchPrediction(index, matchCard.dataset.homeId, matchCard.dataset.awayId);
                } else if (data.retry) {
                    const retryDiv = document.createElement('div');
                    retryDiv.className = 'retry-message countdown-box';
                    let timeLeft = data.retrySeconds;
                    retryDiv.innerHTML = `<span class="retry-text">Rate limit exceeded. Retry attempt ${data.nextAttempt}. Retrying in </span><span id="countdown-${teamId}" class="countdown-timer">${timeLeft}</span><span class="retry-text"> seconds...</span>`;
                    document.querySelector('.container').prepend(retryDiv);
                    
                    const timer = setInterval(() => {
                        timeLeft--;
                        document.getElementById(`countdown-${teamId}`).textContent = timeLeft;
                        if (timeLeft <= 0) {
                            clearInterval(timer);
                            retryDiv.remove();
                            fetchTeamData(teamId, index, isHome);
                        }
                    }, 1000);
                }
            });
        }

        function fetchPrediction(index, homeId, awayId) {
            fetch(`?action=predict_match&homeId=${homeId}&awayId=${awayId}&competition=<?php echo $selectedComp; ?>`, {
                headers: { 'X-Auth-Token': '<?php echo $apiKey; ?>' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const predictionElement = document.getElementById(`prediction-${index}`);
                    predictionElement.innerHTML = `
                        <p>Prediction: ${data.prediction} <span class="result-indicator">${data.resultIndicator}</span></p>
                        <p class="predicted-score">Predicted Score: ${data.predictedScore}</p>
                        <p class="confidence">Confidence: ${data.confidence}</p>
                        <p class="advantage advantage-${data.advantage.toLowerCase().replace(' ', '-')}">${data.advantage}</p>
                        <button class="feedback-btn" onclick="provideFeedback(${index}, '${data.prediction}')">Feedback</button>
                    `;
                    const matchCard = document.querySelector(`.match-card[data-index="${index}"]`);
                    applyAdvantageHighlight(matchCard, data.advantage);
                    animatePredictionUpdate(predictionElement);
                } else if (data.retry) {
                    setTimeout(() => fetchPrediction(index, homeId, awayId), data.retrySeconds * 1000);
                }
            });
        }

        function applyAdvantageHighlight(matchCard, advantage) {
            const homeTeam = matchCard.querySelector('.teams .team:first-child');
            const awayTeam = matchCard.querySelector('.teams .team:last-child');
            
            homeTeam.classList.remove('home-advantage');
            awayTeam.classList.remove('away-advantage');
            matchCard.classList.remove('draw-likely');

            if (advantage === 'Home Advantage') homeTeam.classList.add('home-advantage');
            else if (advantage === 'Away Advantage') awayTeam.classList.add('away-advantage');
            else if (advantage === 'Likely Draw') matchCard.classList.add('draw-likely');
        }

        function animatePredictionUpdate(element) {
            element.style.transition = 'opacity 0.5s';
            element.style.opacity = '0';
            setTimeout(() => element.style.opacity = '1', 100);
        }

        function provideFeedback(index, prediction) {
            const feedback = prompt(`Was the prediction "${prediction}" accurate? (Yes/No)`);
            if (feedback) {
                fetch(`?action=submit_feedback&matchIndex=${index}&prediction=${prediction}&feedback=${feedback}`, {
                    method: 'POST'
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) alert('Thank you for your feedback!');
                  });
            }
        }

        const searchInput = document.querySelector('.search-input');
        const autocompleteDropdown = document.querySelector('.autocomplete-dropdown');
        const searchContainer = document.querySelector('.search-container');
        let debounceTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const query = this.value.trim();
                if (query.length < 2) {
                    autocompleteDropdown.innerHTML = '';
                    searchContainer.classList.remove('active');
                    return;
                }

                fetch(`?action=search_teams&query=${encodeURIComponent(query)}&competition=<?php echo $selectedComp; ?>`)
                    .then(response => response.json())
                    .then(teams => {
                        if (teams.length === 0) {
                            autocompleteDropdown.innerHTML = '<div class="autocomplete-item">No teams found</div>';
                        } else {
                            autocompleteDropdown.innerHTML = teams.map(team => `
                                <div class="autocomplete-item" data-team-id="${team.id}" data-team-name="${team.name}">
                                    ${team.crest ? `<img src="${team.crest}" alt="${team.name}">` : ''}
                                    <span>${team.name}</span>
                                </div>
                            `).join('');
                        }
                        searchContainer.classList.add('active');
                    });
            }, 300);
        });

        autocompleteDropdown.addEventListener('click', function(e) {
            const item = e.target.closest('.autocomplete-item');
            if (item && item.dataset.teamName) {
                searchInput.value = item.dataset.teamName;
                searchContainer.classList.remove('active');
                window.location.href = `?competition=<?php echo $selectedComp; ?>&filter=<?php echo $filter; ?>&team=${encodeURIComponent(item.dataset.teamName)}`;
            }
        });

        document.addEventListener('click', function(e) {
            if (!searchContainer.contains(e.target)) searchContainer.classList.remove('active');
        });

        window.onload = function() {
            const theme = document.cookie.split('; ').find(row => row.startsWith('theme='))?.split('=')[1];
            if (theme) {
                document.body.setAttribute('data-theme', theme);
                document.querySelectorAll('.match-info').forEach(el => el.classList.toggle('dark', theme === 'dark'));
                document.querySelector('.theme-icon').textContent = theme === 'dark' ? '☀️' : '🌙';
            }

            document.querySelectorAll('.match-card').forEach(matchCard => {
                const advantage = matchCard.dataset.advantage;
                if (advantage && !matchCard.querySelector('.prediction').innerHTML.includes('Loading')) {
                    applyAdvantageHighlight(matchCard, advantage);
                }
            });

            if (typeof incompleteTeams !== 'undefined' && incompleteTeams.length > 0) {
                const eventSource = new EventSource(`?action=progress_stream&teamIds=${encodeURIComponent(JSON.stringify(incompleteTeams))}&competition=<?php echo $selectedComp; ?>`);
                const processedTeams = new Set();

                eventSource.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    
                    if (data.complete) {
                        eventSource.close();
                        return;
                    }

                    const teamId = data.teamId;
                    if (processedTeams.has(teamId)) return;
                    processedTeams.add(teamId);

                    document.querySelectorAll(`.match-card[data-home-id="${teamId}"], .match-card[data-away-id="${teamId}"]`).forEach(card => {
                        const index = card.dataset.index;
                        const isHome = card.dataset.homeId == teamId;
                        const formElement = document.getElementById(`form-${isHome ? 'home' : 'away'}-${index}`);
                        const historyElement = document.getElementById(`history-${index}`);
                        const predictionElement = document.getElementById(`prediction-${index}`);

                        if (data.status === 'retrying') {
                            predictionElement.innerHTML = `<p>Loading... Retrying in ${data.retrySeconds}s</p>`;
                            return;
                        }

                        let formHtml = '';
                        const form = data.form.slice(-6).padStart(6, '-');
                        const reversedForm = form.split('').reverse().join('');
                        for (let i = 0; i < 6; i++) {
                            let className = reversedForm[i] === 'W' ? 'win' : (reversedForm[i] === 'D' ? 'draw' : (reversedForm[i] === 'L' ? 'loss' : 'empty'));
                            if (i === 5 && reversedForm[i] !== '-' && form.trim('-').length > 0) className += ' latest';
                            formHtml += `<span class="${className}">${reversedForm[i]}</span>`;
                        }
                        formElement.innerHTML = formHtml;

                        let historyHtml = isHome ? `<p><strong>Team Recent Results:</strong></p><ul>` : historyElement.innerHTML;
                        data.results.forEach(result => historyHtml += `<li>${result}</li>`);
                        historyHtml += `</ul><div class='standings'>
                            <span>POS: ${data.standings.position || 'N/A'}</span>
                            <span>GS: ${data.standings.goalsScored || 'N/A'}</span>
                            <span>GD: ${data.standings.goalDifference || 'N/A'}</span>
                            <span>PTS: ${data.standings.points || 'N/A'}</span>
                        </div>`;
                        historyElement.innerHTML = isHome ? historyHtml + historyElement.innerHTML : historyHtml;

                        if (data.updatedPrediction) {
                            predictionElement.innerHTML = `
                                <p>Prediction: ${data.updatedPrediction[0]} <span class="result-indicator">${data.updatedPrediction[2]}</span></p>
                                <p class="predicted-score">Predicted Score: ${data.updatedPrediction[3]}</p>
                                <p class="confidence">Confidence: ${data.updatedPrediction[1]}</p>
                                <p class="advantage advantage-${data.updatedPrediction[4].toLowerCase().replace(' ', '-')}">${data.updatedPrediction[4]}</p>
                                <button class="feedback-btn" onclick="provideFeedback(${index}, '${data.updatedPrediction[0]}')">Feedback</button>
                            `;
                            applyAdvantageHighlight(card, data.updatedPrediction[4]);
                            animatePredictionUpdate(predictionElement);
                        }
                    });
                };
            }
        }
    </script>
</body>
</html>
<?php
} catch (Exception $e) {
    handleError("Unexpected error: " . $e->getMessage());
}
    <?php include 'back-to-top.php'; ?>
<script src="network-status.js"></script>
<?php include 'global-footer.php'; ?>
?>
