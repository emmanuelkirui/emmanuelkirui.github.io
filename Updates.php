<?php
session_start();
require_once 'recaptcha_handler.php';

// Set timezone to East Africa Time (Nairobi, Kenya, UTC+3)
date_default_timezone_set('Africa/Nairobi');

// Initialize session data for rate limiting and queueing
if (!isset($_SESSION['api_requests'])) {
    $_SESSION['api_requests'] = [];
}
if (!isset($_SESSION['teamStats'])) {
    $_SESSION['teamStats'] = [];
}
if (!isset($_SESSION['request_queue'])) {
    $_SESSION['request_queue'] = [];
}

$apiKey = 'd4c9fea41bf94bb29cade8f12952b3d8';
$baseUrl = 'https://api.football-data.org/v4/';
$teamStats = &$_SESSION['teamStats'];

// Rate limit constants
const REQUESTS_PER_MINUTE = 10;
const MINUTE_IN_SECONDS = 60;

// Rate limiting and queueing functions (unchanged)
function enforceRateLimit($url = null) {
    $currentTime = time();
    $requests = &$_SESSION['api_requests'];
    $requests = array_filter($requests, fn($ts) => ($currentTime - $ts) < MINUTE_IN_SECONDS);
    $requests = array_values($requests);

    if (count($requests) >= REQUESTS_PER_MINUTE) {
        $oldestRequestTime = $requests[0];
        $timeToWait = MINUTE_IN_SECONDS - ($currentTime - $oldestRequestTime);
        if ($url) {
            $_SESSION['request_queue'][] = ['url' => $url, 'timestamp' => $currentTime + $timeToWait];
            error_log("Rate limit reached. Queued request for $url. Waiting $timeToWait seconds.");
            return ['queued' => true, 'delay' => $timeToWait];
        } elseif ($timeToWait > 0) {
            error_log("Rate limit reached. Delaying execution by $timeToWait seconds.");
            sleep($timeToWait);
        }
    }

    if (!$url || !isset($_SESSION['request_queue'])) {
        $requests[] = $currentTime;
        $_SESSION['api_requests'] = $requests;
    }
    return ['queued' => false];
}

function processQueue() {
    $currentTime = time();
    $queue = &$_SESSION['request_queue'];
    if (empty($queue)) return;

    $requests = &$_SESSION['api_requests'];
    $requests = array_filter($requests, fn($ts) => ($currentTime - $ts) < MINUTE_IN_SECONDS);
    $requests = array_values($requests);

    $processed = 0;
    while ($processed < 1 && count($requests) < REQUESTS_PER_MINUTE && !empty($queue)) {
        $nextRequest = array_shift($queue);
        if ($nextRequest['timestamp'] <= $currentTime) {
            $requests[] = $currentTime;
            $_SESSION['api_requests'] = $requests;
            error_log("Processing queued request: " . $nextRequest['url']);
            $processed++;
            sleep(6);
        } else {
            array_unshift($queue, $nextRequest);
            break;
        }
    }
    $_SESSION['request_queue'] = $queue;
}

// Error handling, fetch functions, calculateTeamStrength, predictMatch (unchanged except predictMatch)
function handleError($message) {
    if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html><body><div style='text-align: center; padding: 20px; color: #dc3545;'>";
    echo "<h2>Error</h2><p>" . htmlspecialchars($message) . "</p></div></body></html>";
    exit;
}

function handleJsonError($message) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function fetchWithRetry($url, $apiKey, $isAjax = false, $attempt = 0) {
    // Unchanged implementation
    $maxAttempts = 3;
    $baseTimeout = 15;
    $rateLimitCheck = enforceRateLimit($url);
    if ($rateLimitCheck['queued']) return ['error' => true, 'queued' => true, 'delay' => $rateLimitCheck['delay']];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $apiKey"]);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $baseTimeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $errorCode = curl_errno($ch);
        $errorMessage = curl_error($ch);
        error_log("Attempt $attempt/$maxAttempts - Failed: $url - Error: $errorMessage (Code: $errorCode)");
        curl_close($ch);
        if ($errorCode == CURLE_OPERATION_TIMEDOUT && $attempt < $maxAttempts) {
            $retrySeconds = min(pow(2, $attempt), 8);
            sleep($retrySeconds);
            return fetchWithRetry($url, $apiKey, $isAjax, $attempt + 1);
        }
        return ['error' => true, 'message' => "Connection error: $errorMessage"];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);

    if ($httpCode == 429) {
        $retrySeconds = MINUTE_IN_SECONDS;
        preg_match('/Retry-After: (\d+)/i', $headers, $matches);
        if (!empty($matches[1])) $retrySeconds = max($retrySeconds, (int)$matches[1]);
        error_log("429 Rate Limit hit for $url at " . date('Y-m-d H:i:s') . ". Waiting $retrySeconds seconds.");
        if ($isAjax) return ['error' => true, 'retry' => true, 'delay' => $retrySeconds];
        sleep($retrySeconds);
        return fetchWithRetry($url, $apiKey, $isAjax, $attempt);
    } elseif ($httpCode == 200) {
        error_log("Success: $url - HTTP 200 (Attempt $attempt)");
        return ['error' => false, 'data' => json_decode($body, true)];
    } else {
        error_log("Failed: $url - HTTP $httpCode (Attempt $attempt)");
        return ['error' => true, 'message' => "API Error: HTTP $httpCode"];
    }
}

function fetchTeamResults($teamId, $apiKey, $baseUrl) {
    $pastDate = date('Y-m-d', strtotime('-60 days'));
    $currentDate = date('Y-m-d');
    $url = $baseUrl . "teams/$teamId/matches?dateFrom=$pastDate&dateTo=$currentDate&limit=10&status=FINISHED";
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) {
        if (isset($response['retry'])) return ['error' => true, 'retry' => true, 'delay' => $response['delay']];
        return $response;
    }
    return ['error' => false, 'data' => $response['data']['matches'] ?? []];
}

function fetchStandings($competition, $apiKey, $baseUrl) {
    $url = $baseUrl . "competitions/$competition/standings";
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) return $response;
    return ['error' => false, 'data' => $response['data']['standings'][0]['table'] ?? []];
}

function fetchTeams($competition, $apiKey, $baseUrl) {
    $url = $baseUrl . "competitions/$competition/teams";
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) return [];
    return $response['data']['teams'] ?? [];
}

function calculateTeamStrength($teamId, $apiKey, $baseUrl, &$teamStats, $competition) {
    // Unchanged implementation
    // ... (keeping your original function)
    try {
        $forceRefresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] === 'true';

        if (!isset($teamStats[$teamId]) || empty($teamStats[$teamId]['results']) || empty($teamStats[$teamId]['form']) || $forceRefresh) {
            $response = fetchTeamResults($teamId, $apiKey, $baseUrl);
            if ($response['error']) {
                $teamStats[$teamId] = ['results' => [], 'form' => '', 'needsRetry' => true, 'standings' => []];
                if (isset($response['retry'])) {
                    $teamStats[$teamId]['retry'] = true;
                    $teamStats[$teamId]['retrySeconds'] = $response['retrySeconds'];
                    $teamStats[$teamId]['nextAttempt'] = $response['nextAttempt'];
                }
                return $teamStats[$teamId];
            }
            $results = $response['data'];

            $standingsResponse = fetchStandings($competition, $apiKey, $baseUrl);
            $attempt = 0;
            $maxDelay = 32;
            while ($standingsResponse['error']) {
                $attempt++;
                $delay = min(pow(2, $attempt), $maxDelay);
                error_log("Standings fetch failed for $competition (attempt $attempt): " . $standingsResponse['message'] . ". Retrying in $delay seconds...");
                sleep($delay);
                $standingsResponse = fetchStandings($competition, $apiKey, $baseUrl);

                if (isset($standingsResponse['retrySeconds'])) {
                    $delay = max($delay, $standingsResponse['retrySeconds']);
                    error_log("Using Retry-After delay of $delay seconds from API header.");
                    sleep($delay - $delay);
                    $standingsResponse = fetchStandings($competition, $apiKey, $baseUrl);
                }
            }
            $standings = $standingsResponse['data'];
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

function predictMatch($match, $apiKey, $baseUrl, &$teamStats, $competition) {
    try {
        $homeTeamId = $match['homeTeam']['id'] ?? 0;
        $awayTeamId = $match['awayTeam']['id'] ?? 0;

        if (!$homeTeamId || !$awayTeamId) return ["N/A", "0%", "", "0-0", "", "", "", []];

        $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats, $competition);
        $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats, $competition);

        if ($homeStats['needsRetry'] || $awayStats['needsRetry']) {
            $retryInfo = [];
            if ($homeStats['retry']) $retryInfo['home'] = ['retrySeconds' => $homeStats['retrySeconds'], 'nextAttempt' => $homeStats['nextAttempt']];
            if ($awayStats['retry']) $retryInfo['away'] = ['retrySeconds' => $awayStats['retrySeconds'], 'nextAttempt' => $awayStats['nextAttempt']];
            return ["Loading...", "N/A", "", "N/A", "", $homeStats['form'] ?? "", $awayStats['form'] ?? "", $retryInfo];
        }

        // Basic stats with defaults (unchanged)
        $homeGames = max($homeStats['games'] ?? 1, 1);
        $awayGames = max($awayStats['games'] ?? 1, 1);
        $homeWinRate = ($homeStats['wins'] ?? 0) / $homeGames;
        $homeDrawRate = ($homeStats['draws'] ?? 0) / $homeGames;
        $awayWinRate = ($awayStats['wins'] ?? 0) / $awayGames;
        $awayDrawRate = ($awayStats['draws'] ?? 0) / $awayGames;
        $homeGoalAvg = ($homeStats['goalsScored'] ?? 0) / $homeGames;
        $awayGoalAvg = ($awayStats['goalsScored'] ?? 0) / $awayGames;
        $homeConcededAvg = ($homeStats['goalsConceded'] ?? 0) / $homeGames;
        $awayConcededAvg = ($awayStats['goalsConceded'] ?? 0) / $awayGames;

        // Use actual standings from API (unchanged)
        $homePosition = $homeStats['standings']['position'] ?? 0;
        $awayPosition = $awayStats['standings']['position'] ?? 0;
        $homePoints = $homeStats['standings']['points'] ?? 0;
        $awayPoints = $awayStats['standings']['points'] ?? 0;
        $homeGD = $homeStats['standings']['goalDifference'] ?? 0;
        $awayGD = $awayStats['standings']['goalDifference'] ?? 0;
        $homeHomeWins = $homeStats['standings']['home']['won'] ?? (int)($homeStats['wins'] / 2);
        $awayAwayWins = $awayStats['standings']['away']['won'] ?? (int)($awayStats['wins'] / 2);

        $totalTeams = $match['competition']['numberOfTeams'] ?? 20;
        if ($homePosition == 0) $homePosition = ($homePoints > 0) ? min($totalTeams, max(1, round($totalTeams - ($homePoints / 3)))) : (int)($totalTeams / 2);
        if ($awayPosition == 0) $awayPosition = ($awayPoints > 0) ? min($totalTeams, max(1, round($totalTeams - ($awayPoints / 3)))) : (int)($totalTeams / 2);

        $homePointsPerGame = $homePoints / $homeGames;
        $awayPointsPerGame = $awayPoints / $awayGames;

        // Adjusted calculations (unchanged)
        $homeStrengthAdjustment = 1.0 + ($homeHomeWins / max($homeGames / 2, 1)) * 0.05;
        $awayStrengthAdjustment = 0.98 - ($awayAwayWins / max($awayGames / 2, 1)) * 0.02;

        $competitionFactor = match ($competition) {
            'UEFA Champions League' => 1.1,
            'English Championship' => 0.95,
            default => 1.0
        };

        $homeFormArray = str_split(str_pad($homeStats['form'] ?? "------", 6, '-', STR_PAD_LEFT));
        $awayFormArray = str_split(str_pad($awayStats['form'] ?? "------", 6, '-', STR_PAD_LEFT));
        $momentumWeights = [0.1, 0.2, 0.3];
        $homeMomentum = $awayMomentum = 0;
        for ($i = 0; $i < 3 && $i < count($homeFormArray); $i++) {
            $result = array_reverse($homeFormArray)[$i];
            $homeMomentum += ($result === 'W' ? 3 : ($result === 'D' ? 1 : 0)) * $momentumWeights[$i];
        }
        for ($i = 0; $i < 3 && $i < count($awayFormArray); $i++) {
            $result = array_reverse($awayFormArray)[$i];
            $awayMomentum += ($result === 'W' ? 3 : ($result === 'D' ? 1 : 0)) * $momentumWeights[$i];
        }
        $momentumFactor = 1 + ($homeMomentum - $awayMomentum) * 0.05;

        $homeOpponentStrength = min(1.2, max(0.8, 1 - (($homePosition - ($totalTeams / 2)) / ($totalTeams / 2)) * 0.1));
        $awayOpponentStrength = min(1.2, max(0.8, 1 - (($awayPosition - ($totalTeams / 2)) / ($totalTeams / 2)) * 0.1));

        $formWeightBase = [0.05, 0.10, 0.15, 0.25, 0.30, 0.40];
        $homeFormScore = $awayFormScore = 0;
        foreach (array_reverse($homeFormArray) as $i => $result) {
            $homeFormScore += ($result === 'W' ? 3 : ($result === 'D' ? 1 : 0)) * $formWeightBase[$i] * $homeOpponentStrength;
        }
        foreach (array_reverse($awayFormArray) as $i => $result) {
            $awayFormScore += ($result === 'W' ? 3 : ($result === 'D' ? 1 : 0)) * $formWeightBase[$i] * $awayOpponentStrength;
        }

        $maxWeight = 100;
        $winWeight = min(15, 10 + ($homeGames + $awayGames) * 0.10);
        $drawWeight = min(15, 10 + ($homeGames + $awayGames) * 0.08);
        $goalWeight = min(20, 15 + (abs($homeGD) + abs($awayGD)) * 0.015 / max($homeGames, $awayGames));
        $standingsWeight = min(20, 15 + ($homeGames + $awayGames) * 0.15);
        $formWeight = min(25, 15 + (strlen(trim($homeStats['form'] ?? '', '-')) + strlen(trim($awayStats['form'] ?? '', '-'))) * 0.4);

        $totalDynamicWeight = $winWeight + $drawWeight + $formWeight + $goalWeight + $standingsWeight;
        if ($totalDynamicWeight > 0) {
            $normalizationFactor = $maxWeight / $totalDynamicWeight;
            $winWeight *= $normalizationFactor;
            $drawWeight *= $normalizationFactor;
            $formWeight *= $normalizationFactor;
            $goalWeight *= $normalizationFactor;
            $standingsWeight *= $normalizationFactor;
        }

        $homeStrength = (
            $homeWinRate * $winWeight +
            $homeDrawRate * $drawWeight +
            ($homeGoalAvg + $homeGD / $homeGames) * $goalWeight +
            $homeFormScore * $formWeight +
            $homePointsPerGame * $standingsWeight
        ) * $homeStrengthAdjustment * $competitionFactor * $momentumFactor;
        $awayStrength = (
            $awayWinRate * $winWeight +
            $awayDrawRate * $drawWeight +
            ($awayGoalAvg + $awayGD / $awayGames) * $goalWeight +
            $awayFormScore * $formWeight +
            $awayPointsPerGame * $standingsWeight
        ) * $awayStrengthAdjustment * $competitionFactor / $momentumFactor;

        $diff = $homeStrength - $awayStrength + ($totalTeams - $homePosition) * 0.03 - ($totalTeams - $awayPosition) * 0.03;
        $totalStrength = $homeStrength + $awayStrength + 1;

        $formConsistency = min(strlen(trim($homeStats['form'], '-')), strlen(trim($awayStats['form'], '-'))) / 6;
        $goalDiffFactor = abs($homeGD - $awayGD) / max($homeGames, $awayGames);
        $confidenceBase = 50 + (abs($diff) / $totalStrength * 50) * $formConsistency + $goalDiffFactor * 10;
        $confidence = min(75, max(45, $confidenceBase));

        $recentHomeGoals = !empty($homeStats['results']) ? array_slice(array_map(fn($r) => (int)(explode(':', $r)[0] ?? 0), $homeStats['results']), 0, 3) : [];
        $recentAwayGoals = !empty($awayStats['results']) ? array_slice(array_map(fn($r) => (int)(explode(':', $r)[1] ?? 0), $awayStats['results']), 0, 3) : [];
        $homeGoalTrend = count($recentHomeGoals) ? array_sum($recentHomeGoals) / count($recentHomeGoals) : $homeGoalAvg;
        $awayGoalTrend = count($recentAwayGoals) ? array_sum($recentAwayGoals) / count($recentAwayGoals) : $awayGoalAvg;

        $homeAttackStrength = min(1.8, ($homeGoalTrend * 0.5 + $homeGoalAvg * 0.5) * $homeOpponentStrength);
        $awayAttackStrength = min(1.8, ($awayGoalTrend * 0.5 + $awayGoalAvg * 0.5) * $awayOpponentStrength);
        $homeDefStrength = min(1.6, ($homeConcededAvg * 0.9 + 0.1) * $awayOpponentStrength);
        $awayDefStrength = min(1.6, ($awayConcededAvg * 0.9 + 0.1) * $homeOpponentStrength);

        $expectedHomeGoals = max(0, min(3.0, ($homeAttackStrength * (1 + $diff / 50)) / ($awayDefStrength + 0.5) * $competitionFactor));
        $expectedAwayGoals = max(0, min(3.0, ($awayAttackStrength * (1 - $diff / 50)) / ($homeDefStrength + 0.5) * $competitionFactor));
        $predictedHomeGoals = round($expectedHomeGoals * 0.9 + $homeGoalTrend * 0.1);
        $predictedAwayGoals = round($expectedAwayGoals * 0.9 + $awayGoalTrend * 0.1);
        $predictedScore = "$predictedHomeGoals-$predictedAwayGoals";

        $homeTeam = $match['homeTeam']['tla'] ?? ($match['homeTeam']['shortName'] ?? ($match['homeTeam']['name'] ?? 'Home Team'));
        $awayTeam = $match['awayTeam']['tla'] ?? ($match['awayTeam']['shortName'] ?? ($match['awayTeam']['name'] ?? 'Away Team'));
        $status = $match['status'] ?? 'SCHEDULED';
        $homeGoals = $match['score']['fullTime']['home'] ?? null;
        $awayGoals = $match['score']['fullTime']['away'] ?? null;

        if ($diff > 0.35) {
            $prediction = "$homeTeam to win";
            $advantage = "Home Advantage";
        } elseif ($diff < -0.35) {
            $prediction = "$awayTeam to win";
            $advantage = "Away Advantage";
        } else {
            $prediction = "Draw";
            $advantage = "Tight Match";
            $confidence = min($confidence, 60);
        }

        $confidence = sprintf("%.1f%%", $confidence);
        $resultIndicator = "";
        if ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null) {
            $actualResult = ($homeGoals > $awayGoals) ? "$homeTeam to win" : (($homeGoals < $awayGoals) ? "$awayTeam to win" : "Draw");
            $resultIndicator = ($prediction === $actualResult) ? "<span style='color: green;'>✓</span>" : "<span style='color: red;'>✕</span>";
        }

        return [$prediction, $confidence, $resultIndicator, $predictedScore, $advantage, $homeStats['form'] ?? "", $awayStats['form'] ?? "", []];
    } catch (Exception $e) {
        error_log("Error in predictMatch: " . $e->getMessage());
        return ["Error", "N/A", "", "N/A", "", "", "", []];
    }
}

function calculateStreak($formArray) {
    // Unchanged
    $streak = 0;
    $lastResult = null;
    foreach (array_reverse($formArray) as $result) {
        if ($result === '-') break;
        if ($lastResult === null || $result === $lastResult) {
            $streak = $result === 'W' ? $streak + 1 : ($result === 'L' ? $streak - 1 : $streak);
        } else break;
        $lastResult = $result;
    }
    return $streak;
}

function fetchTopScorers($competition, $apiKey, $baseUrl) {
    // Unchanged
    $url = $baseUrl . "competitions/$competition/scorers";
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) return $response;
    return ['error' => false, 'data' => $response['data']['scorers'] ?? []];
}

// AJAX handling (unchanged except for fetch_team_data and predict_match)
$action = $_GET['action'] ?? 'main';
if (isset($_GET['ajax']) || in_array($action, ['fetch_team_data', 'predict_match', 'search_teams', 'process_queue'])) {
    header('Content-Type: application/json');
    switch ($action) {
        case 'fetch_team_data':
            $teamId = (int)($_GET['teamId'] ?? 0);
            if (!$teamId) handleJsonError('Invalid team ID');
            
            $response = fetchTeamResults($teamId, $apiKey, $baseUrl);
            if ($response['queued']) {
                echo json_encode(['success' => false, 'queued' => true, 'delay' => $response['delay']]);
                exit;
            }
            if ($response['error']) {
                if (isset($response['retry'])) {
                    echo json_encode(['success' => false, 'retry' => true, 'delay' => $response['delay']]);
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
            
            $predictionData = predictMatch([
                'homeTeam' => ['id' => $homeId],
                'awayTeam' => ['id' => $awayId],
                'status' => $_GET['status'] ?? 'SCHEDULED',
                'score' => [
                    'fullTime' => [
                        'home' => $_GET['homeGoals'] ?? null,
                        'away' => $_GET['awayGoals'] ?? null
                    ]
                ]
            ], $apiKey, $baseUrl, $teamStats, $_GET['competition'] ?? 'PL');
            
            if (!empty($predictionData[7]['queued'])) {
                echo json_encode(['success' => false, 'queued' => true, 'delay' => $predictionData[7]['delay']]);
                exit;
            }
            if (!empty($predictionData[7]['retry'])) {
                echo json_encode(['success' => false, 'retry' => true, 'delay' => $predictionData[7]['delay']]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'prediction' => $predictionData[0],
                'confidence' => $predictionData[1],
                'resultIndicator' => $predictionData[2],
                'predictedScore' => $predictionData[3],
                'advantage' => $predictionData[4],
                'homeForm' => $predictionData[5],
                'awayForm' => $predictionData[6]
            ]);
            exit;

        case 'search_teams':
            // Unchanged
            $query = strtolower($_GET['query'] ?? '');
            $competition = $_GET['competition'] ?? 'PL';
            $teams = fetchTeams($competition, $apiKey, $baseUrl);
            if (!empty($teams['queued'])) {
                echo json_encode(['success' => false, 'queued' => true, 'delay' => $teams['delay']]);
                exit;
            }
            $filteredTeams = array_filter($teams, fn($team) => stripos($team['name'], $query) !== false || stripos($team['shortName'] ?? '', $query) !== false);
            echo json_encode(array_values(array_map(fn($team) => ['id' => $team['id'], 'name' => $team['name'], 'crest' => $team['crest'] ?? ''], $filteredTeams)));
            exit;

        case 'process_queue':
            processQueue();
            echo json_encode(['processed' => true]);
            exit;
    }
}

// Navigation bar (unchanged)
if (!isset($_GET['ajax'])) {
    echo "<nav class='navbar'>";
    echo "<div class='navbar-container'>";
    echo "<div class='navbar-brand'>CPS Football</div>";
    echo "<div class='hamburger' onclick='toggleMenu()'><span></span><span></span><span></span></div>";
    echo "<div class='nav-menu' id='navMenu'>";
    echo "<a href='valmanu' class='nav-link'>Home</a>";
    echo "<a href='liv' class='nav-link'>Predictions</a>";
    echo "<a href='javascript:history.back()' class='nav-link'>Back</a>";
    if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        echo "<div class='user-menu'>";
        echo "<button class='nav-link user-btn' onclick='toggleUserMenu()'>" . htmlspecialchars($_SESSION['username']) . " ▼</button>";
        echo "<div class='user-dropdown' id='userDropdown'>";
        echo "<a href='#settings' class='dropdown-item'>Settings</a>";
        echo "<a href='#' class='dropdown-item' onclick='handleLogout(event)'>Logout</a>";
        echo "</div>";
        echo "</div>";
    } else {
        echo "<button class='nav-link auth-btn' onclick='openModal()'>Login/Signup</button>";
    }
    echo "<button class='theme-toggle' onclick='toggleTheme()'><span class='theme-icon'>☀️</span></button>";
    echo "</div>";
    echo "</div>";
    echo "</nav>";

    // Navigation JS (unchanged)
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

// Main page logic with correct predictions
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
    $filter = $_GET['filter'] ?? 'today';
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

    $matchesUrl = $baseUrl . "competitions/$selectedComp/matches?dateFrom=$fromDate&dateTo=$toDate&limit=4";
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
        $allMatches = array_filter($allMatches, fn($match) => 
            stripos($match['homeTeam']['name'] ?? '', $searchTeam) !== false ||
            stripos($match['awayTeam']['name'] ?? '', $searchTeam) !== false
        );
    }

    // Calculate correct predictions
    $correctPredictions = 0;
    $totalFinishedMatches = 0;
    foreach ($allMatches as $index => $match) {
        if ($match['status'] === 'FINISHED' && isset($match['score']['fullTime']['home']) && isset($match['score']['fullTime']['away'])) {
            $totalFinishedMatches++;
            $predictionData = predictMatch($match, $apiKey, $baseUrl, $teamStats, $selectedComp);
            $prediction = $predictionData[0];
            $homeGoals = $match['score']['fullTime']['home'];
            $awayGoals = $match['score']['fullTime']['away'];
            $actualResult = ($homeGoals > $awayGoals) ? "{$match['homeTeam']['name']} to win" :
                           (($homeGoals < $awayGoals) ? "{$match['awayTeam']['name']} to win" : "Draw");
            if ($prediction === $actualResult) $correctPredictions++;
        }
    }
    $predictionAccuracy = $totalFinishedMatches > 0 ? "($correctPredictions/$totalFinishedMatches)" : "(0/0)";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPS Football Predictions</title>
    <link rel="preconnect" href="http://api.football-data.org">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
            /* Unchanged */
            width: 100%;
            background-color: var(--card-bg);
            box-shadow: var(--shadow);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        /* Existing navbar styles unchanged */
        .navbar-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; height: 60px; }
        .navbar-brand { font-size: 1.5em; font-weight: bold; color: var(--primary-color); text-decoration: none; }
        .nav-menu { display: flex; align-items: center; gap: 20px; margin: 0; padding: 0; list-style: none; }
        .nav-link { color: var(--text-color); text-decoration: none; font-size: 1.1em; font-weight: 600; padding: 12px 20px; border-radius: 8px; transition: all 0.3s ease; }
        .nav-link:hover { background-color: var(--primary-color); color: white; transform: translateY(-2px); }
        .hamburger { display: none; flex-direction: column; cursor: pointer; gap: 5px; padding: 10px; }
        .hamburger span { width: 25px; height: 3px; background-color: var(--text-color); border-radius: 2px; transition: all 0.3s ease; }
        .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(7px, -7px); }
        .theme-toggle { width: 40px; height: 40px; background-color: var(--primary-color); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; justify-content: center; align-items: center; transition: all 0.3s ease; font-size: 1.3em; }
        .theme-toggle:hover { background-color: var(--secondary-color); transform: scale(1.1); }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 80px 20px 20px;
            transition: padding-top 0.3s ease;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .prediction-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            font-size: 1.1em;
            color: var(--text-color);
            background-color: var(--card-bg);
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        #correct-predictions {
            font-weight: bold;
            color: var(--primary-color);
        }

        #last-updated {
            font-size: 0.9em;
            color: #666;
        }

        [data-theme="dark"] #last-updated {
            color: #bdc3c7;
        }

        .controls {
            /* Unchanged */
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        select {
            padding: 10px 20px;
            margin: 5px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        select:hover {
            background-color: var(--secondary-color);
        }

        /* Rest of your CSS unchanged */
        .filter-container { position: relative; display: inline-block; margin: 5px; }
        .filter-dropdown-btn { padding: 10px 20px; background-color: var(--primary-color); color: white; border: none; border-radius: 20px; cursor: pointer; min-width: 120px; display: flex; justify-content: space-between; align-items: center; }
        .filter-dropdown-btn::after { content: '▼'; margin-left: 10px; font-size: 0.8em; }
        .filter-dropdown { position: absolute; top: 100%; left: 0; background-color: var(--card-bg); border-radius: 10px; box-shadow: var(--shadow); min-width: 200px; z-index: 10; display: none; margin-top: 5px; }
        .filter-dropdown.active { display: block; }
        .filter-option { padding: 10px 15px; cursor: pointer; transition: background-color 0.2s; }
        .filter-option:hover { background-color: var(--primary-color); color: white; }
        .filter-option.selected { background-color: var(--secondary-color); color: white; }
        .custom-date-range { padding: 15px; border-top: 1px solid rgba(0,0,0,0.1); display: none; }
        .custom-date-range.active { display: block; }
        .custom-date-range input[type="date"] { width: 100%; margin: 5px 0; padding: 5px; border-radius: 5px; border: 1px solid var(--text-color); }
        .custom-date-range button { width: 100%; margin-top: 10px; padding: 8px; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; }
        .search-container { position: relative; width: 100%; max-width: 400px; margin: 5px; }
        .search-input { width: 100%; padding: 12px 20px; border: 2px solid var(--primary-color); border-radius: 25px; font-size: 1em; background-color: var(--card-bg); color: var(--text-color); outline: none; transition: border-color 0.3s ease; }
        .search-input:focus { border-color: var(--secondary-color); box-shadow: 0 0 5px rgba(52, 152, 219, 0.5); }
        .autocomplete-dropdown { position: absolute; top: 100%; left: 0; width: 100%; background-color: var(--card-bg); border-radius: 10px; box-shadow: var(--shadow); max-height: 200px; overflow-y: auto; z-index: 100; display: none; }
        .autocomplete-item { padding: 10px 15px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: background-color 0.2s ease; }
        .autocomplete-item:hover { background-color: var(--primary-color); color: white; }
        .autocomplete-item img { width: 20px; height: 20px; object-fit: contain; }
        .search-container.active .autocomplete-dropdown { display: block; }
        .match-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .match-card { background-color: var(--card-bg); border-radius: 10px; padding: 20px; box-shadow: var(--shadow); transition: transform 0.2s ease; }
        .match-card:hover { transform: translateY(-5px); }
        .view-toggle { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; padding: 10px; width: 100%; }
        .view-btn { padding: 8px 16px; border: 1px solid #ccc; border-radius: 4px; background-color: #fff; cursor: pointer; transition: background-color 0.2s; flex: 0 0 auto; white-space: nowrap; }
        .view-btn:hover { background-color: #f5f5f5; }
        .view-btn.active { background-color: #007bff; color: white; border-color: #007bff; }
        .match-table { width: 100%; overflow-x: auto; }
        .match-table table { width: 100%; border-collapse: collapse; background-color: var(--card-bg); box-shadow: var(--shadow); border-radius: 10px; overflow: hidden; }
        .match-table th, .match-table td { padding: 15px; text-align: center; border-bottom: 1px solid rgba(0, 0, 0, 0.1); }
        .match-table th { background-color: var(--primary-color); color: white; font-weight: bold; }
        .match-table tr:hover { background-color: rgba(0, 0, 0, 0.05); }
        [data-theme="dark"] .match-table tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .match-table .form-display { justify-content: center; margin-top: 5px; font-size: 14px; }
        .match-table .form-display span.win { color: #28a745; }
        .match-table .form-display span.draw { color: #fd7e14; }
        .match-table .form-display span.loss { color: #dc3545; }
        .match-table .form-display span.latest { text-decoration: underline; }
        .top-scorers-table { width: 100%; overflow-x: auto; margin-top: 20px; }
        .top-scorers-table table { width: 100%; border-collapse: collapse; background-color: var(--card-bg); box-shadow: var(--shadow); border-radius: 10px; overflow: hidden; }
        .top-scorers-table th, .top-scorers-table td { padding: 15px; text-align: center; border-bottom: 1px solid rgba(0, 0, 0, 0.1); }
        .top-scorers-table th { background-color: var(--primary-color); color: white; font-weight: bold; }
        .top-scorers-table tr:hover { background-color: rgba(0, 0, 0, 0.05); }
        [data-theme="dark"] .top-scorers-table tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .teams { display: flex; justify-content: center; align-items: center; margin-bottom: 15px; gap: 2px; margin-left: 5px; margin-right: 5px; }
        .team { text-align: center; flex: 1; max-width: 48%; }
        .home-team { padding-right: 0.1em; }
        .away-team { padding-left: 0.1em; }
        .team img { max-width: 50px; height: auto; margin-bottom: 10px; }
        .vs { font-size: 1.2em; font-weight: bold; color: var(--primary-color); text-align: center; min-width: 15px; padding: 0 1px; }
        .match-info { text-align: center; font-size: 0.9em; color: #666; }
        .match-info.dark { color: #bdc3c7; }
        .prediction { margin-top: 15px; padding: 10px; background-color: rgba(46, 204, 113, 0.1); border-radius: 5px; text-align: center; font-weight: bold; }
        .confidence { font-size: 0.8em; color: var(--secondary-color); }
        .result-indicator { font-size: 1.2em; margin-left: 5px; }
        .past-results { margin-top: 15px; padding: 10px; background-color: rgba(52, 152, 219, 0.1); border-radius: 5px; font-size: 0.85em; }
        .past-results ul { list-style: none; padding: 0; margin: 0; }
        .standings { margin-top: 10px; font-size: 0.9em; color: var(--text-color); }
        .standings span { margin-right: 10px; font-weight: bold; }
        .view-history-btn { margin-top: 10px; padding: 8px 15px; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; width: 100%; transition: background-color 0.3s ease; }
        .view-history-btn:hover { background-color: var(--secondary-color); }
        .form-display { display: flex; justify-content: center; align-items: center; font-family: 'monospace'; font-size: 16px; line-height: 1; padding: 2px; background-color: rgba(0, 0, 0, 0.05); border-radius: 4px; }
        .form-display span { display: block; width: 16px; text-align: center; margin: 0; padding: 0; border: none; }
        .form-display .latest { border: 2px solid #3498db; border-radius: 2px; font-weight: bold; background-color: rgba(52, 152, 219, 0.1); }
        .form-display .win { color: #28a745; }
        .form-display .draw { color: #fd7e14; }
        .form-display .loss { color: #dc3545; }
        .form-display .empty { color: #6c757d; }
        .form-display.updated { animation: pulse 0.5s ease-in-out 2; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .share-btn { padding: 8px 16px; background-color: var(--primary-color); color: white; border: none; border-radius: 20px; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: background-color 0.3s ease; }
        .share-btn:hover { background-color: var(--secondary-color); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CPS Football Predictions</h1>
            <div class="prediction-stats">
                <span id="correct-predictions">Correct Predictions: <?php echo $predictionAccuracy; ?></span>
                <span id="last-updated">Last updated: <?php echo date('H:i:s'); ?></span>
            </div>
        </div>

        <div class="controls">
            <select onchange="updateUrl(this.value, '<?php echo $filter; ?>')">
                <?php foreach ($competitions as $comp): ?>
                    <option value="<?php echo $comp['code']; ?>" <?php echo $comp['code'] === $selectedComp ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($comp['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="filter-container">
                <button class="filter-dropdown-btn"><?php echo $filterLabel; ?></button>
                <div class="filter-dropdown">
                    <?php foreach ($dateOptions as $key => $option): ?>
                        <div class="filter-option" data-filter="<?php echo $key; ?>" onclick="selectFilter('<?php echo $key; ?>')">
                            <?php echo $option['label']; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="custom-date-range">
                        <input type="date" name="start" value="<?php echo $customStart; ?>">
                        <input type="date" name="end" value="<?php echo $customEnd; ?>">
                        <button onclick="updateUrl('<?php echo $selectedComp; ?>', 'custom')">Apply</button>
                    </div>
                </div>
            </div>

            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search teams..." value="<?php echo htmlspecialchars($searchTeam); ?>">
                <div class="autocomplete-dropdown"></div>
            </div>
        </div>

        <div class="view-toggle">
            <button id="grid-view-btn" class="view-btn" onclick="switchView('grid')">Grid View</button>
            <button id="table-view-btn" class="view-btn" onclick="switchView('table')">Table View</button>
            <button id="standings-view-btn" class="view-btn" onclick="switchView('standings')">Standings</button>
            <button id="scorers-view-btn" class="view-btn" onclick="switchView('scorers')">Top Scorers</button>
        </div>

        <div id="match-grid" class="match-grid">
            <?php
            foreach ($allMatches as $index => $match) {
                $predictionData = predictMatch($match, $apiKey, $baseUrl, $teamStats, $selectedComp);
                [$prediction, $confidence, $resultIndicator, $predictedScore, $advantage, $homeForm, $awayForm] = $predictionData;

                echo "<div class='match-card' data-index='$index' data-home-id='{$match['homeTeam']['id']}' data-away-id='{$match['awayTeam']['id']}' data-status='{$match['status']}' data-home-name='{$match['homeTeam']['name']}' data-away-name='{$match['awayTeam']['name']}' data-advantage='$advantage'>";
                echo "<div class='teams'>";
                echo "<div class='team home-team'><img src='{$match['homeTeam']['crest']}' alt='{$match['homeTeam']['name']}'><p>{$match['homeTeam']['name']}</p></div>";
                echo "<div class='vs'>vs</div>";
                echo "<div class='team away-team'><img src='{$match['awayTeam']['crest']}' alt='{$match['awayTeam']['name']}'><p>{$match['awayTeam']['name']}</p></div>";
                echo "</div>";
                echo "<div class='match-info'><p>" . date('M d, H:i', strtotime($match['utcDate'])) . " - " . ($match['score']['fullTime']['home'] ?? 'N/A') . " : " . ($match['score']['fullTime']['away'] ?? 'N/A') . "</p></div>";
                echo "<div class='form-display' id='form-home-$index'></div>";
                echo "<div class='form-display' id='form-away-$index'></div>";
                echo "<div class='prediction' id='prediction-$index'>";
                echo "<p>Prediction: $prediction <span class='result-indicator'>$resultIndicator</span></p>";
                echo "<p class='predicted-score'>Predicted Score: $predictedScore</p>";
                echo "<p class='confidence'>Confidence: $confidence</p>";
                echo "<p class='advantage advantage-" . strtolower(str_replace(' ', '-', $advantage)) . "'>$advantage</p>";
                echo "</div>";
                echo "<div class='past-results' id='history-$index' style='display: none;'><p>Loading history... <span class='loading-spinner'>⏳</span></p></div>";
                echo "<button class='view-history-btn' onclick='toggleHistory(this)'>👁️ View History</button>";
                echo "</div>";
            }
            ?>
        </div>

        <div id="match-table" class="match-table" style="display: none;">
            <div class="table-header">
                <h3><?php echo $selectedComp; ?> Matches</h3>
                <button id="share-table-btn" class="share-btn" title="Share Table"><span class="share-icon">📤</span> Share</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Home</th>
                        <th>Away</th>
                        <th>Score</th>
                        <th>Home Form</th>
                        <th>Away Form</th>
                        <th>Prediction</th>
                        <th>Confidence</th>
                        <th>Predicted Score</th>
                        <th>Form</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($allMatches as $index => $match) {
                        $predictionData = predictMatch($match, $apiKey, $baseUrl, $teamStats, $selectedComp);
                        [$prediction, $confidence, $resultIndicator, $predictedScore, $advantage, $homeForm, $awayForm] = $predictionData;

                        $homeFormHtml = "<div class='form-display'>";
                        for ($i = 0; $i < 6; $i++) {
                            $char = str_pad($homeForm, 6, '-', STR_PAD_LEFT)[$i];
                            $class = $char === 'W' ? 'win' : ($char === 'D' ? 'draw' : ($char === 'L' ? 'loss' : 'empty'));
                            if ($i === 5 && $char !== '-') $class .= ' latest';
                            $homeFormHtml .= "<span class='$class'>$char</span>";
                        }
                        $homeFormHtml .= "</div>";

                        $awayFormHtml = "<div class='form-display'>";
                        for ($i = 0; $i < 6; $i++) {
                            $char = str_pad($awayForm, 6, '-', STR_PAD_LEFT)[$i];
                            $class = $char === 'W' ? 'win' : ($char === 'D' ? 'draw' : ($char === 'L' ? 'loss' : 'empty'));
                            if ($i === 5 && $char !== '-') $class .= ' latest';
                            $awayFormHtml .= "<span class='$class'>$char</span>";
                        }
                        $awayFormHtml .= "</div>";

                        echo "<tr data-index='$index' data-home-id='{$match['homeTeam']['id']}' data-away-id='{$match['awayTeam']['id']}' data-status='{$match['status']}' data-home-name='{$match['homeTeam']['name']}' data-away-name='{$match['awayTeam']['name']}'>";
                        echo "<td>{$match['homeTeam']['name']}</td>";
                        echo "<td>{$match['awayTeam']['name']}</td>";
                        echo "<td>" . ($match['score']['fullTime']['home'] ?? 'N/A') . " - " . ($match['score']['fullTime']['away'] ?? 'N/A') . "</td>";
                        echo "<td id='table-form-home-$index'>$homeFormHtml</td>";
                        echo "<td id='table-form-away-$index'>$awayFormHtml</td>";
                        echo "<td id='table-prediction-$index'>$prediction $resultIndicator</td>";
                        echo "<td id='table-confidence-$index'>$confidence</td>";
                        echo "<td id='table-predicted-score-$index'>$predictedScore</td>";
                        echo "<td>$homeForm / $awayForm</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Standings and Top Scorers tables unchanged -->
        <div class="standings-table" id="standings-table" style="display: none;">
            <div class="table-header">
                <h3><?php echo $selectedComp; ?> Standings</h3>
                <button id="share-standings-btn" class="share-btn" title="Share Standings"><span class="share-icon">📤</span> Share</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>POS</th><th>Team</th><th>P</th><th>W</th><th>D</th><th>L</th><th>GS</th><th>GC</th><th>GD</th><th>PTS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $standings = fetchStandings($selectedComp, $apiKey, $baseUrl);
                    if (!$standings['error'] && !empty($standings['data'])) {
                        $totalTeams = count($standings['data']);
                        $upperLimit = ceil($totalTeams * 0.25);
                        $lowerLimit = floor($totalTeams * 0.75);
                        foreach ($standings['data'] as $team) {
                            $position = $team['position'];
                            $tierClass = $position <= $upperLimit ? 'upper-table' : ($position > $lowerLimit ? 'lower-table' : 'mid-table');
                            echo "<tr class='$tierClass'>";
                            echo "<td>{$team['position']}</td>";
                            echo "<td>" . ($team['team']['name'] ?? 'Unknown') . "</td>";
                            echo "<td>{$team['playedGames']}</td>";
                            echo "<td>{$team['won']}</td>";
                            echo "<td>{$team['draw']}</td>";
                            echo "<td>{$team['lost']}</td>";
                            echo "<td>{$team['goalsFor']}</td>";
                            echo "<td>{$team['goalsAgainst']}</td>";
                            echo "<td>{$team['goalDifference']}</td>";
                            echo "<td>{$team['points']}</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="top-scorers-table" id="top-scorers-table" style="display: none;">
            <div class="table-header">
                <h3><?php echo $selectedComp; ?> Top Scorers</h3>
                <button id="share-scorers-btn" class="share-btn" title="Share Top Scorers"><span class="share-icon">📤</span> Share</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th><th>Player</th><th>Team</th><th>Goals</th><th>Assists</th><th>Matches</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $scorers = fetchTopScorers($selectedComp, $apiKey, $baseUrl);
                    if (!$scorers['error'] && !empty($scorers['data'])) {
                        foreach ($scorers['data'] as $index => $scorer) {
                            echo "<tr>";
                            echo "<td>" . ($index + 1) . "</td>";
                            echo "<td>" . ($scorer['player']['name'] ?? 'Unknown') . "</td>";
                            echo "<td>" . ($scorer['team']['name'] ?? 'Unknown') . "</td>";
                            echo "<td>" . ($scorer['goals'] ?? 'N/A') . "</td>";
                            echo "<td>" . ($scorer['assists'] ?? 'N/A') . "</td>";
                            echo "<td>" . ($scorer['playedMatches'] ?? 'N/A') . "</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal unchanged -->
    <div class="modal-overlay" id="auth-modal">
        <div class="modal-container">
            <button class="modal-close" onclick="closeModal()">×</button>
            <div class="tab-buttons">
                <button id="login-tab" onclick="showForm('login')" class="active">Login</button>
                <button id="signup-tab" onclick="showForm('signup')">Sign Up</button>
                <button id="reset-tab" onclick="showForm('reset')">Reset Password</button>
            </div>
            <div id="login-form" class="auth-form active">
                <h2>Login</h2>
                <form id="loginForm" action="auth.php" method="POST">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit" name="login" class="submit-btn">Log In</button>
                    <div class="message" id="loginMessage"></div>
                </form>
            </div>
            <div id="signup-form" class="auth-form">
                <h2>Sign Up</h2>
                <form id="signupForm" action="auth.php" method="POST">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                    <button type="submit" name="signup" class="submit-btn">Sign Up</button>
                    <div class="message" id="signupMessage"></div>
                </form>
            </div>
            <div id="reset-form" class="auth-form">
                <h2>Reset Password</h2>
                <form id="resetRequestForm" action="auth.php" method="POST">
                    <input type="email" name="email" placeholder="Email" required>
                    <button type="submit" name="reset_request" class="submit-btn">Send Reset Link</button>
                    <div class="message" id="resetMessage"></div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function switchView(view) {
            const gridView = document.getElementById('match-grid');
            const tableView = document.getElementById('match-table');
            const standingsView = document.getElementById('standings-table');
            const scorersView = document.getElementById('top-scorers-table');
            const gridBtn = document.getElementById('grid-view-btn');
            const tableBtn = document.getElementById('table-view-btn');
            const standingsBtn = document.getElementById('standings-view-btn');
            const scorersBtn = document.getElementById('scorers-view-btn');

            gridView.style.display = 'none';
            tableView.style.display = 'none';
            standingsView.style.display = 'none';
            scorersView.style.display = 'none';
            gridBtn.classList.remove('active');
            tableBtn.classList.remove('active');
            standingsBtn.classList.remove('active');
            scorersBtn.classList.remove('active');

            if (view === 'grid') {
                gridView.style.display = 'grid';
                gridBtn.classList.add('active');
            } else if (view === 'table') {
                tableView.style.display = 'block';
                tableBtn.classList.add('active');
            } else if (view === 'standings') {
                standingsView.style.display = 'block';
                standingsBtn.classList.add('active');
            } else if (view === 'scorers') {
                scorersView.style.display = 'block';
                scorersBtn.classList.add('active');
            }
            localStorage.setItem('matchView', view);
        }

        // Modal and user menu JS
        function toggleUserMenu() {
            document.getElementById('userDropdown')?.classList.toggle('active');
        }

        document.addEventListener('click', e => {
            const userMenu = document.querySelector('.user-menu');
            if (userMenu && !userMenu.contains(e.target)) {
                document.getElementById('userDropdown')?.classList.remove('active');
            }
        });

        function openModal() {
            document.getElementById('auth-modal')?.classList.add('active');
        }

        function closeModal() {
            document.getElementById('auth-modal')?.classList.remove('active');
            resetForms();
        }

        function showForm(type) {
            const forms = ['login', 'signup', 'reset'];
            forms.forEach(f => {
                const tab = document.getElementById(`${f}-tab`);
                const form = document.getElementById(`${f}-form`);
                if (f === type) {
                    tab?.classList.add('active');
                    form?.classList.add('active');
                } else {
                    tab?.classList.remove('active');
                    form?.classList.remove('active');
                }
            });
            resetForms();
        }

        function resetForms() {
            document.querySelectorAll('.auth-form .message').forEach(m => m.innerHTML = '');
            document.querySelectorAll('.auth-form input').forEach(i => i.value = '');
        }

        function handleLogout(event) {
            event.preventDefault();
            fetch('auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'logout=true'
            }).then(response => response.json()).then(data => {
                if (data.success) window.location.reload();
            });
        }

        // Theme toggle
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.querySelector('.theme-icon');
            if (body.dataset.theme === 'dark') {
                body.dataset.theme = 'light';
                themeIcon.textContent = '☀️';
                localStorage.setItem('theme', 'light');
            } else {
                body.dataset.theme = 'dark';
                themeIcon.textContent = '🌙';
                localStorage.setItem('theme', 'dark');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'light';
            const themeIcon = document.querySelector('.theme-icon');
            document.body.dataset.theme = savedTheme;
            themeIcon.textContent = savedTheme === 'dark' ? '🌙' : '☀️';

            const savedView = localStorage.getItem('matchView') || 'grid';
            switchView(savedView);
        });

        // Filter dropdown
        function selectFilter(filter) {
            const dropdown = document.querySelector('.filter-dropdown');
            const customRange = document.querySelector('.custom-date-range');
            document.querySelectorAll('.filter-option').forEach(opt => {
                opt.classList.toggle('selected', opt.dataset.filter === filter);
            });
            if (filter === 'custom') {
                customRange.classList.add('active');
            } else {
                customRange.classList.remove('active');
                updateUrl('<?php echo $selectedComp; ?>', filter);
            }
        }

        document.querySelector('.filter-dropdown-btn')?.addEventListener('click', () => {
            const dropdown = document.querySelector('.filter-dropdown');
            dropdown.classList.toggle('active');
        });

        document.addEventListener('click', e => {
            const filterContainer = document.querySelector('.filter-container');
            if (filterContainer && !filterContainer.contains(e.target)) {
                filterContainer.querySelector('.filter-dropdown')?.classList.remove('active');
            }
        });

        function updateUrl(comp, filter) {
            const url = new URL(window.location);
            url.searchParams.set('competition', comp);
            url.searchParams.set('filter', filter);
            if (filter === 'custom') {
                const start = document.querySelector('input[name="start"]').value;
                const end = document.querySelector('input[name="end"]').value;
                if (start && end) {
                    url.searchParams.set('start', start);
                    url.searchParams.set('end', end);
                }
            } else {
                url.searchParams.delete('start');
                url.searchParams.delete('end');
            }
            const search = document.querySelector('.search-input').value;
            if (search) url.searchParams.set('team', search);
            window.location = url;
        }

        // Team search autocomplete
        const searchInput = document.querySelector('.search-input');
        const autocompleteDropdown = document.querySelector('.autocomplete-dropdown');
        let debounceTimeout;

        searchInput?.addEventListener('input', () => {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const query = searchInput.value.trim();
                if (query.length < 2) {
                    autocompleteDropdown.innerHTML = '';
                    autocompleteDropdown.parentElement.classList.remove('active');
                    return;
                }

                fetch(`?ajax=true&action=search_teams&competition=<?php echo $selectedComp; ?>&query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(teams => {
                        autocompleteDropdown.innerHTML = '';
                        if (teams.length) {
                            teams.forEach(team => {
                                const div = document.createElement('div');
                                div.className = 'autocomplete-item';
                                div.innerHTML = `<img src="${team.crest}" alt="${team.name}"> ${team.name}`;
                                div.onclick = () => {
                                    searchInput.value = team.name;
                                    autocompleteDropdown.innerHTML = '';
                                    autocompleteDropdown.parentElement.classList.remove('active');
                                    updateUrl('<?php echo $selectedComp; ?>', '<?php echo $filter; ?>');
                                };
                                autocompleteDropdown.appendChild(div);
                            });
                            autocompleteDropdown.parentElement.classList.add('active');
                        } else {
                            autocompleteDropdown.parentElement.classList.remove('active');
                        }
                    });
            }, 300);
        });

        document.addEventListener('click', e => {
            if (!searchInput?.contains(e.target) && !autocompleteDropdown?.contains(e.target)) {
                autocompleteDropdown.parentElement.classList.remove('active');
            }
        });

        // History toggle and fetch
        function toggleHistory(btn) {
            const card = btn.closest('.match-card');
            const historyDiv = card.querySelector('.past-results');
            const index = card.dataset.index;
            const homeId = card.dataset.homeId;
            const awayId = card.dataset.awayId;

            if (historyDiv.style.display === 'none' || !historyDiv.dataset.fetched) {
                historyDiv.style.display = 'block';
                btn.textContent = 'Hide History';
                if (!historyDiv.dataset.fetched) {
                    fetchTeamData(homeId, awayId, index);
                    historyDiv.dataset.fetched = 'true';
                }
            } else {
                historyDiv.style.display = 'none';
                btn.textContent = '👁️ View History';
            }
        }

        function fetchTeamData(homeId, awayId, index) {
            fetch(`?ajax=true&action=fetch_team_data&teamId=${homeId}&competition=<?php echo $selectedComp; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const historyDiv = document.getElementById(`history-${index}`);
                        historyDiv.innerHTML = `<p><strong>${data.teamName}</strong></p><ul>` + 
                            data.results.map(r => `<li>${r}</li>`).join('') + 
                            `</ul><p>Form: ${data.form}</p>` +
                            (data.standings.position ? `<p>Position: ${data.standings.position} | Points: ${data.standings.points} | GD: ${data.standings.goalDifference}</p>` : '');
                    } else if (data.queued) {
                        setTimeout(() => fetchTeamData(homeId, awayId, index), data.delay * 1000);
                    }
                });
        }

        // Debounced fetch prediction
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        const debouncedFetchPrediction = debounce((index, homeId, awayId) => {
            const card = document.querySelector(`.match-card[data-index="${index}"]`) || 
                         document.querySelector(`tr[data-index="${index}"]`);
            const status = card.dataset.status;
            const homeGoals = card.dataset.homeGoals || null;
            const awayGoals = card.dataset.awayGoals || null;

            fetch(`?ajax=true&action=predict_match&homeId=${homeId}&awayId=${awayId}&status=${status}&homeGoals=${homeGoals}&awayGoals=${awayGoals}&competition=<?php echo $selectedComp; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateMatchUI(card, index, data);
                    } else if (data.queued) {
                        setTimeout(() => debouncedFetchPrediction(index, homeId, awayId), data.delay * 1000);
                    }
                });
        }, 500);

        function updateMatchUI(element, index, data) {
            if (element.classList.contains('match-card')) {
                const predictionDiv = document.getElementById(`prediction-${index}`);
                predictionDiv.querySelector('p:first-child').innerHTML = `Prediction: ${data.prediction} <span class="result-indicator">${data.resultIndicator}</span>`;
                predictionDiv.querySelector('.predicted-score').textContent = `Predicted Score: ${data.predictedScore}`;
                predictionDiv.querySelector('.confidence').textContent = `Confidence: ${data.confidence}`;
                predictionDiv.querySelector('.advantage').textContent = data.advantage;

                const homeFormDiv = document.getElementById(`form-home-${index}`);
                const awayFormDiv = document.getElementById(`form-away-${index}`);
                updateFormDisplay(homeFormDiv, data.homeForm);
                updateFormDisplay(awayFormDiv, data.awayForm);
            } else {
                document.getElementById(`table-prediction-${index}`).innerHTML = `${data.prediction} ${data.resultIndicator}`;
                document.getElementById(`table-confidence-${index}`).textContent = data.confidence;
                document.getElementById(`table-predicted-score-${index}`).textContent = data.predictedScore;

                const homeFormDiv = document.getElementById(`table-form-home-${index}`).querySelector('.form-display');
                const awayFormDiv = document.getElementById(`table-form-away-${index}`).querySelector('.form-display');
                updateFormDisplay(homeFormDiv, data.homeForm);
                updateFormDisplay(awayFormDiv, data.awayForm);
            }

            // Update correct predictions
            if (data.resultIndicator) {
                const correctPredictionsElement = document.getElementById('correct-predictions');
                let [correct, total] = correctPredictionsElement.textContent.match(/(\d+)\/(\d+)/)?.slice(1) || [0, 0];
                total = parseInt(total) || 0;
                correct = parseInt(correct) || 0;

                if (!element.dataset.counted) {
                    total++;
                    const predictedResult = data.prediction;
                    const actualResult = data.resultIndicator.includes('✓') ? predictedResult : 
                                        (predictedResult.includes('Draw') ? 
                                            (data.predictedScore.split('-')[0] > data.predictedScore.split('-')[1] ? `${element.dataset.homeName} to win` : `${element.dataset.awayName} to win`) : 
                                            (predictedResult.includes(element.dataset.homeName) ? `${element.dataset.awayName} to win` : `${element.dataset.homeName} to win`));
                    if (data.resultIndicator.includes('✓')) {
                        correct++;
                    }
                    element.dataset.counted = 'true';
                }

                correctPredictionsElement.textContent = `Correct Predictions: (${correct}/${total})`;
            }
        }

        function updateFormDisplay(formDiv, form) {
            if (!formDiv || !form) return;
            formDiv.innerHTML = '';
            const formArray = form.padStart(6, '-').split('');
            formArray.forEach((char, i) => {
                const span = document.createElement('span');
                span.textContent = char;
                span.className = char === 'W' ? 'win' : (char === 'D' ? 'draw' : (char === 'L' ? 'loss' : 'empty'));
                if (i === formArray.length - 1 && char !== '-') span.classList.add('latest');
                formDiv.appendChild(span);
            });
            formDiv.classList.add('updated');
            setTimeout(() => formDiv.classList.remove('updated'), 1000);
        }

        // Polling for updates
        let pollingInterval;
        function startMatchPolling() {
            if (pollingInterval) clearInterval(pollingInterval);
            pollingInterval = setInterval(() => {
                processQueue();
                const matches = document.querySelectorAll('.match-card, .match-table tr');
                const maxRequestsPerPoll = Math.min(5, matches.length);
                let requestsMade = 0;

                matches.forEach(element => {
                    if (requestsMade >= maxRequestsPerPoll) return;
                    const homeId = element.dataset.homeId;
                    const awayId = element.dataset.awayId;
                    const index = element.dataset.index;
                    const status = element.dataset.status;

                    if (status === 'FINISHED' && element.querySelector('.result-indicator')) return;

                    debouncedFetchPrediction(index, homeId, awayId);
                    requestsMade++;
                });

                const lastUpdated = document.getElementById('last-updated');
                if (lastUpdated) {
                    lastUpdated.textContent = `Last updated: ${new Date().toLocaleTimeString()}`;
                }
            }, 120000); // Poll every 2 minutes
        }

        function processQueue() {
            fetch(`?ajax=true&action=process_queue`)
                .then(response => response.json())
                .then(data => {
                    if (data.processed) console.log('Queue processed');
                });
        }

        // Share functionality
        function shareContent(elementId, title) {
            const element = document.getElementById(elementId);
            html2canvas(element, { backgroundColor: document.body.dataset.theme === 'dark' ? '#2c3e50' : '#f4f4f4' }).then(canvas => {
                canvas.toBlob(blob => {
                    const file = new File([blob], `${title}.png`, { type: 'image/png' });
                    const shareData = { files: [file], title: title };
                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        navigator.share(shareData).catch(err => console.error('Share failed:', err));
                    } else {
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = `${title}.png`;
                        link.click();
                    }
                });
            });
        }

        document.getElementById('share-table-btn')?.addEventListener('click', () => shareContent('match-table', 'Match Predictions'));
        document.getElementById('share-standings-btn')?.addEventListener('click', () => shareContent('standings-table', 'League Standings'));
        document.getElementById('share-scorers-btn')?.addEventListener('click', () => shareContent('top-scorers-table', 'Top Scorers'));

        // Start polling on load
        document.addEventListener('DOMContentLoaded', () => {
            startMatchPolling();
            const matches = document.querySelectorAll('.match-card, .match-table tr');
            matches.forEach(match => {
                const index = match.dataset.index;
                const homeFormDiv = match.querySelector(`#form-home-${index}`) || match.querySelector(`#table-form-home-${index} .form-display`);
                const awayFormDiv = match.querySelector(`#form-away-${index}`) || match.querySelector(`#table-form-away-${index} .form-display`);
                updateFormDisplay(homeFormDiv, '<?php echo $allMatches[$index]["homeForm"] ?? ""; ?>');
                updateFormDisplay(awayFormDiv, '<?php echo $allMatches[$index]["awayForm"] ?? ""; ?>');
            });
        });
    </script>
</body>
</html>

<?php
} catch (Exception $e) {
    handleError("An unexpected error occurred: " . $e->getMessage());
}
?>
