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

// Error handling functions remain unchanged
function handleError($message) {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo "<!DOCTYPE html><html><body><div style='text-align: center; padding: 20px; color: #dc3545;'>";
    echo "<h2>Error</h2><p>" . htmlspecialchars($message) . "</p>";
    echo "</div></body></html>";
    exit;
}

function handleJsonError($message) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// fetchWithRetry, fetchTeamResults, fetchStandings, fetchTeams, calculateTeamStrength, predictMatch functions remain unchanged
function fetchWithRetry($url, $apiKey, $isAjax = false) {
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
                    retryDiv.innerHTML = '<span class=\"retry-text\">Rate limit exceeded. Retry attempt ' + $nextAttempt + '. Retrying in </span><span id=\"countdown\" class=\"countdown-timer\">' + timeLeft + '</span><span class=\"retry-text\"> seconds...</span>';
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

function fetchTeamResults($teamId, $apiKey, $baseUrl) {
    $pastDate = date('Y-m-d', strtotime('-60 days'));
    $currentDate = date('Y-m-d');
    $url = $baseUrl . "teams/$teamId/matches?dateFrom=$pastDate&dateTo=$currentDate&limit=10&status=FINISHED";
    
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) {
        if (isset($response['retry'])) {
            return [
                'error' => true,
                'retry' => true,
                'retrySeconds' => $response['retrySeconds'],
                'nextAttempt' => $response['nextAttempt']
            ];
        }
        return $response;
    }
    $data = $response['data'];
    error_log("Team $teamId last updated: " . ($data['lastUpdated'] ?? 'unknown'));
    return ['error' => false, 'data' => $data['matches'] ?? []];
}

function fetchStandings($competition, $apiKey, $baseUrl) {
    $url = $baseUrl . "competitions/$competition/standings";
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) {
        return $response;
    }
    return ['error' => false, 'data' => $response['data']['standings'][0]['table'] ?? []];
}

function fetchTeams($competition, $apiKey, $baseUrl) {
    $url = $baseUrl . "competitions/$competition/teams";
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) {
        return [];
    }
    return $response['data']['teams'] ?? [];
}

function calculateTeamStrength($teamId, $apiKey, $baseUrl, &$teamStats, $competition) {
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

        if (!$homeTeamId || !$awayTeamId) {
            return ["N/A", "0%", "", "0-0", "", "", ""];
        }

        $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats, $competition);
        $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats, $competition);

        if ($homeStats['needsRetry'] || $awayStats['needsRetry']) {
            $retryInfo = [];
            if ($homeStats['retry']) $retryInfo['home'] = ['retrySeconds' => $homeStats['retrySeconds'], 'nextAttempt' => $homeStats['nextAttempt']];
            if ($awayStats['retry']) $retryInfo['away'] = ['retrySeconds' => $awayStats['retrySeconds'], 'nextAttempt' => $awayStats['nextAttempt']];
            return ["Loading...", "N/A", "", "N/A", "", $homeStats['form'], $awayStats['form'], $retryInfo];
        }

        // Basic stats
        $homeGames = max($homeStats['games'], 1);
        $awayGames = max($awayStats['games'], 1);
        $homeWinRate = $homeStats['wins'] / $homeGames;
        $homeDrawRate = $homeStats['draws'] / $homeGames;
        $awayWinRate = $awayStats['wins'] / $awayGames;
        $awayDrawRate = $awayStats['draws'] / $awayGames;
        $homeGoalAvg = $homeStats['goalsScored'] / $homeGames;
        $awayGoalAvg = $awayStats['goalsScored'] / $awayGames;
        $homeConcededAvg = $homeStats['goalsConceded'] / $homeGames;
        $awayConcededAvg = $awayStats['goalsConceded'] / $awayGames;

        // Standings data
        $homeGD = $homeStats['standings']['goalDifference'] ?? 0;
        $awayGD = $awayStats['standings']['goalDifference'] ?? 0;
        $homePointsPerGame = ($homeStats['standings']['points'] ?? 0) / $homeGames;
        $awayPointsPerGame = ($awayStats['standings']['points'] ?? 0) / $awayGames;
        $homePosition = $homeStats['standings']['position'] ?? 10;
        $awayPosition = $awayStats['standings']['position'] ?? 10;

        // Dynamic Home/Away Adjustments
        $homeHomeWins = $homeStats['standings']['home']['won'] ?? $homeStats['wins'] / 2;
        $awayAwayWins = $awayStats['standings']['away']['won'] ?? $awayStats['wins'] / 2;
        $homeStrengthAdjustment = 1.0 + ($homeHomeWins / max($homeGames / 2, 1)) * 0.15;
        $awayStrengthAdjustment = 1.0 - ($awayAwayWins / max($awayGames / 2, 1)) * 0.10;

        // Dynamic Competition Factor
        $competitionBase = match ($competition) {
            'UEFA Champions League' => 1.1, // Higher scoring, more variance
            'English Championship' => 0.95, // Tighter, lower scoring
            default => 1.0 // Neutral for unknown competitions
        };
        // Average goals per game for this matchup as a proxy
        $matchGoalAvg = ($homeGoalAvg + $awayGoalAvg + $homeConcededAvg + $awayConcededAvg) / 4;
        // Variance factor from form streakiness and GD
        $homeFormArray = str_split(str_pad($homeStats['form'], 6, '-', STR_PAD_LEFT));
        $awayFormArray = str_split(str_pad($awayStats['form'], 6, '-', STR_PAD_LEFT));
        $homeStreak = abs(calculateStreak($homeFormArray));
        $awayStreak = abs(calculateStreak($awayFormArray));
        $formVariance = 1 + ($homeStreak + $awayStreak) * 0.02; // More streaky = more variance
        $gdVariance = 1 + (abs($homeGD) + abs($awayGD)) * 0.005 / max($homeGames, $awayGames); // Big GD = more blowout potential
        // Dynamic factor: base + goal avg adjustment + variance
        $competitionFactor = min(1.3, max(0.8, $competitionBase * (1 + ($matchGoalAvg - 2.5) / 5) * $formVariance * $gdVariance));

        // Dynamic Weight Calculation
        $maxWeight = 100;
        $winWeight = min(25, 10 + ($homeGames + $awayGames) * 0.2);
        $drawWeight = min(15, 5 + ($homeGames + $awayGames) * 0.1);
        $goalWeight = min(25, 10 + (abs($homeGD) + abs($awayGD)) * 0.02 / max($homeGames, $awayGames));
        $standingsWeight = min(25, 10 + ($homeGames + $awayGames) * 0.25);

        // Form with emphasis on recent games
        $formWeightBase = [0.05, 0.1, 0.15, 0.2, 0.25, 0.35];
        $homeFormScore = 0;
        $awayFormScore = 0;
        foreach (array_reverse($homeFormArray) as $i => $result) {
            $homeFormScore += ($result === 'W' ? 3 : ($result === 'D' ? 1 : 0)) * $formWeightBase[$i];
        }
        foreach (array_reverse($awayFormArray) as $i => $result) {
            $awayFormScore += ($result === 'W' ? 3 : ($result === 'D' ? 1 : 0)) * $formWeightBase[$i];
        }
        $formConsistency = min(1.4, 1 + ($homeStreak + $awayStreak) * 0.05);
        $formWeight = min(30, 15 + (strlen(trim($homeStats['form'], '-')) + strlen(trim($awayStats['form'], '-'))) * 0.5) * $formConsistency;

        // Normalize weights
        $totalDynamicWeight = $winWeight + $drawWeight + $formWeight + $goalWeight + $standingsWeight;
        if ($totalDynamicWeight > 0) {
            $normalizationFactor = $maxWeight / $totalDynamicWeight;
            $winWeight *= $normalizationFactor;
            $drawWeight *= $normalizationFactor;
            $formWeight *= $normalizationFactor;
            $goalWeight *= $normalizationFactor;
            $standingsWeight *= $normalizationFactor;
        }

        // Calculate team strength
        $homeStrength = (
            $homeWinRate * $winWeight +
            $homeDrawRate * $drawWeight +
            ($homeGoalAvg + $homeGD / $homeGames) * $goalWeight +
            $homeFormScore * $formWeight +
            $homePointsPerGame * $standingsWeight
        ) * $homeStrengthAdjustment * $competitionFactor;
        $awayStrength = (
            $awayWinRate * $winWeight +
            $awayDrawRate * $drawWeight +
            ($awayGoalAvg + $awayGD / $awayGames) * $goalWeight +
            $awayFormScore * $formWeight +
            $awayPointsPerGame * $standingsWeight
        ) * $awayStrengthAdjustment * $competitionFactor;

        // Use neutral randomness for all predictions
         $randomFactor = 0; // No randomness for upcoming or finished matches
         $homeStrength *= (1 + $randomFactor); // No change: 1 + 0 = 1
         $awayStrength *= (1 - $randomFactor); // No change: 1 - 0 = 1

        // Strength difference and confidence
        $diff = $homeStrength - $awayStrength + (20 - $homePosition) * 0.03 - (20 - $awayPosition) * 0.03;
        $totalStrength = $homeStrength + $awayStrength + 1;
        $confidenceBase = 50 + (abs($diff) / $totalStrength * 100 * $formConsistency);
        $confidence = min(85, max(55, $confidenceBase));

        // Goal prediction with defensive adjustment
        $homeAttackStrength = min(2.5, $homeGoalAvg + $homeGD / $homeGames);
        $awayAttackStrength = min(2.5, $awayGoalAvg + $awayGD / $awayGames);
        $homeDefStrength = min(2, $homeConcededAvg);
        $awayDefStrength = min(2, $awayConcededAvg);
        $expectedHomeGoals = max(0, min(4, $homeAttackStrength * (1 + $diff / 50) / ($awayDefStrength + 1) * $competitionFactor));
        $expectedAwayGoals = max(0, min(4, $awayAttackStrength * (1 - $diff / 50) / ($homeDefStrength + 1) * $competitionFactor));
        $predictedHomeGoals = max(0, round($expectedHomeGoals + $randomFactor));
        $predictedAwayGoals = max(0, round($expectedAwayGoals - $randomFactor));
        $predictedScore = "$predictedHomeGoals-$predictedAwayGoals";

        // Match details
        // With this more robust check:
        $homeTeam = $match['homeTeam']['name'] ?? 
           ($match['homeTeam']['shortName'] ?? 
           'Home Team'); // Fallback to shortName first, then a generic name
        $awayTeam = $match['awayTeam']['name'] ?? 
           ($match['awayTeam']['shortName'] ?? 
           'Away Team');
        $status = $match['status'] ?? 'SCHEDULED';
        $homeGoals = $match['score']['fullTime']['home'] ?? null;
        $awayGoals = $match['score']['fullTime']['away'] ?? null;

        // Prediction aligned with score
        if ($predictedHomeGoals > $predictedAwayGoals) {
            $prediction = "$homeTeam to win";
            $advantage = "Home Advantage";
        } elseif ($predictedHomeGoals < $predictedAwayGoals) {
            $prediction = "$awayTeam to win";
            $advantage = "Away Advantage";
        } else {
            $prediction = "Draw";
            $advantage = "Likely Draw";
            $confidence = min($confidence, 70);
        }

        $confidence = sprintf("%.1f%%", $confidence);
        $resultIndicator = "";
        if ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null) {
            $actualResult = ($homeGoals > $awayGoals) ? "$homeTeam to win" : (($homeGoals < $awayGoals) ? "$awayTeam to win" : "Draw");
            $resultIndicator = ($prediction === $actualResult) ? "✅" : "❌";
        }

        return [$prediction, $confidence, $resultIndicator, $predictedScore, $advantage, $homeStats['form'], $awayStats['form'], []];
    } catch (Exception $e) {
        return ["Error", "N/A", "", "N/A", "", "", "", []];
    }
}

function calculateStreak($formArray) {
    $streak = 0;
    $lastResult = null;
    foreach (array_reverse($formArray) as $result) {
        if ($result === '-') break;
        if ($lastResult === null || $result === $lastResult) {
            $streak = $result === 'W' ? $streak + 1 : ($result === 'L' ? $streak - 1 : $streak);
        } else {
            break;
        }
        $lastResult = $result;
    }
    return $streak;
}
            
function fetchTopScorers($competition, $apiKey, $baseUrl) {
    $url = $baseUrl . "competitions/$competition/scorers";
    $response = fetchWithRetry($url, $apiKey, true);
    if ($response['error']) {
        return $response;
    }
    return ['error' => false, 'data' => $response['data']['scorers'] ?? []];
}

// AJAX handling remains unchanged
$action = $_GET['action'] ?? 'main';
if (isset($_GET['ajax']) || in_array($action, ['fetch_team_data', 'predict_match', 'search_teams'])) {
    header('Content-Type: application/json');
    switch ($action) {
        case 'fetch_team_data':
            $teamId = (int)($_GET['teamId'] ?? 0);
            if (!$teamId) {
                handleJsonError('Invalid team ID');
            }
            
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
            if (empty($results)) {
                handleJsonError('No results fetched');
            }
            
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
            
            if (!$homeId || !$awayId) {
                handleJsonError('Invalid team IDs');
            }
            
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
            
            if (!empty($predictionData[7])) {
                echo json_encode(['success' => false, 'retry' => true, 'retryInfo' => $predictionData[7]]);
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
    }
}

// Navigation bar remains unchanged
if (!isset($_GET['ajax'])) {
    echo "<nav class='navbar'>";
    echo "<div class='navbar-container'>";
    echo "<div class='navbar-brand'>CPS Football</div>";
    echo "<div class='hamburger' onclick='toggleMenu()'><span></span><span></span><span></span></div>";
    echo "<div class='nav-menu' id='navMenu'>";
    echo "<a href='valmanu' class='nav-link'>Home</a>";
    echo "<a href='liv' class='nav-link'>Predictions</a>";
    echo "<a href='javascript:history.back()' class='nav-link'>Back</a>";
    echo"<button onclick="openModal()">Login/Signup</button>";
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

// Main page logic remains largely unchanged
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

        /* Existing navbar styles remain unchanged */
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
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .nav-link {
            color: var(--text-color);
            text-decoration: none;
            font-size: 1.1em;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            gap: 5px;
            padding: 10px;
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background-color: var(--text-color);
            border-radius: 2px;
            transition: all 0.3s ease;
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
            transition: all 0.3s ease;
            font-size: 1.3em;
        }

        .theme-toggle:hover {
            background-color: var(--secondary-color);
            transform: scale(1.1);
        }

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

        .search-container {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 5px;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid var(--primary-color);
            border-radius: 25px;
            font-size: 1em;
            background-color: var(--card-bg);
            color: var(--text-color);
            outline: none;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
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
            z-index: 100;
            display: none;
        }

        .autocomplete-item {
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .autocomplete-item:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .autocomplete-item img {
            width: 20px;
            height: 20px;
            object-fit: contain;
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
            transition: transform 0.2s ease;
        }

        .match-card:hover {
            transform: translateY(-5px);
        }

        /* New table view styles */
        .view-toggle {
    display: flex;
    flex-wrap: wrap; /* Wraps only when there's not enough space */
    gap: 10px; /* Consistent spacing between buttons */
    justify-content: center; /* Centers buttons when on one line */
    padding: 10px;
    width: 100%; /* Ensures it respects container width */
}

.view-btn {
    padding: 8px 16px;
    border: 1px solid #ccc;
    border-radius: 4px;
    background-color: #fff;
    cursor: pointer;
    transition: background-color 0.2s;
    flex: 0 0 auto; /* Keeps buttons at their natural width */
    white-space: nowrap; /* Prevents button text from wrapping */
}

.view-btn:hover {
    background-color: #f5f5f5;
}

.view-btn.active {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}

/* Overlay background */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5); /* Semi-transparent overlay */
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    z-index: 10;
}

/* Modal container */
.modal-container {
    width: 350px;
    padding: 30px;
    background: rgba(255, 255, 255, 0.15); /* Frosted glass effect */
    backdrop-filter: blur(10px);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    color: #333;
    font-size: 1rem;
    text-align: center;
    position: relative;
}

/* Close button */
.modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    color: #fff;
    font-size: 1.5rem;
    cursor: pointer;
}

/* Tab buttons */
.tab-buttons {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
}

.tab-buttons button {
    background: none;
    border: none;
    font-size: 1rem;
    color: #fff;
    padding: 10px;
    cursor: pointer;
    transition: color 0.3s;
}

.tab-buttons button.active {
    font-weight: bold;
    color: #4caf50;
    border-bottom: 2px solid #4caf50;
}

/* Form elements */
.modal-container input[type="text"],
.modal-container input[type="password"] {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border-radius: 8px;
    border: none;
    background: rgba(255, 255, 255, 0.3);
    color: #333;
    font-size: 0.9rem;
}

.modal-container button.submit-btn {
    width: 100%;
    padding: 10px;
    margin-top: 15px;
    border-radius: 8px;
    border: none;
    background: #4caf50;
    color: white;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.3s;
}

.modal-container button.submit-btn:hover {
    background: #45a049;
}

/* Show modal when active */
.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Hide/show forms */
.auth-form {
    display: none;
}

.auth-form.active {
    display: block;
}

/* Section Styling */
.section {
    width: 90%;
    max-width: 1200px;
    margin: 20px auto;
    background: rgba(0, 0, 0, 0.85);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.5);
}

/* Headings */
.title {
    color: #f8e71c;
    text-align: center;
    font-size: 2rem;
    font-weight: 700;
    text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.8);
    margin-bottom: 20px;
}


/* Forms */
form {
    margin-top: 20px;
    text-align: center;
}

form .field {
    margin-bottom: 20px;
}

form label {
    font-size: 1.2rem;
    color: #f8e71c;
}

form .select {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 8px 12px;
    border-radius: 6px;
    color: #ffffff;
    font-size: 1rem;
    width: auto;
}

form .select:hover,
form .select:focus {
    border-color: #f8e71c;
    outline: none;
    cursor: pointer;
}   
        .match-table {
            width: 100%;
            overflow-x: auto;
        }

        .match-table table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--card-bg);
            box-shadow: var(--shadow);
            border-radius: 10px;
            overflow: hidden;
        }

        .match-table th,
        .match-table td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .match-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
        }

        .match-table tr:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .match-table .form-display {
            justify-content: center;
            margin-top: 5px;
            font-size: 14px;
        }

        [data-theme="dark"] .match-table tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        /* Add these to your existing CSS in the <style> section */
.match-table .form-display span.win {
    color: #28a745; /* Green */
}

.match-table .form-display span.draw {
    color: #fd7e14; /* Orange */
}

.match-table .form-display span.loss {
    color: #dc3545; /* Red */
}

.match-table .form-display span.latest {
    text-decoration: underline;
}
        .top-scorers-table {
    width: 100%;
    overflow-x: auto;
    margin-top: 20px;
}

.top-scorers-table table {
    width: 100%;
    border-collapse: collapse;
    background-color: var(--card-bg);
    box-shadow: var(--shadow);
    border-radius: 10px;
    overflow: hidden;
}

.top-scorers-table th,
.top-scorers-table td {
    padding: 15px;
    text-align: center;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.top-scorers-table th {
    background-color: var(--primary-color);
    color: white;
    font-weight: bold;
}

.top-scorers-table tr:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .top-scorers-table tr:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

@media (max-width: 768px) {
    .top-scorers-table table {
        font-size: 0.9em;
    }
    
    .top-scorers-table th,
    .top-scorers-table td {
        padding: 10px;
    }
}

        /* Existing match card styles */
        .teams {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
            gap: 2px;
            margin-left: 5px;
            margin-right: 5px;
        }

        .team {
            text-align: center;
            flex: 1;
            max-width: 48%;
        }

        .home-team {
            padding-right: 0.1em;
        }

        .away-team {
            padding-left: 0.1em;
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
            text-align: center;
            min-width: 15px;
            padding: 0 1px;
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

        .standings {
            margin-top: 10px;
            font-size: 0.9em;
            color: var(--text-color);
        }

        .standings span {
            margin-right: 10px;
            font-weight: bold;
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
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'monospace';
            font-size: 16px;
            line-height: 1;
            padding: 2px;
            background-color: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
        }

        .form-display span {
            display: block;
            width: 16px;
            text-align: center;
            margin: 0;
            padding: 0;
            border: none;
        }

        .form-display .latest {
            border: 2px solid #3498db;
            border-radius: 2px;
            font-weight: bold;
            background-color: rgba(52, 152, 219, 0.1);
        }

        .form-display .win {
            color: #28a745;
        }

        .form-display .draw {
            color: #fd7e14;
        }

        .form-display .loss {
            color: #dc3545;
        }

        .form-display .empty {
            color: #6c757d;
        }

        .form-display.updated {
            animation: pulse 0.5s ease-in-out 2;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.share-btn {
    padding: 8px 16px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: background-color 0.3s ease;
}

.share-btn:hover {
    background-color: var(--secondary-color);
}

.share-icon {
    font-size: 1.2em;
}
        .standings-table {
    width: 100%;
    overflow-x: auto;
    margin-top: 20px;
}

.standings-table table {
    width: 100%;
    border-collapse: collapse;
    background-color: var(--card-bg);
    box-shadow: var(--shadow);
    border-radius: 10px;
    overflow: hidden;
}

.standings-table th,
.standings-table td {
    padding: 15px;
    text-align: center;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.standings-table th {
    background-color: var(--primary-color);
    color: white;
    font-weight: bold;
}

.standings-table tr:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .standings-table tr:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

@media (max-width: 768px) {
    .standings-table table {
        font-size: 0.9em;
    }
    
    .standings-table th,
    .standings-table td {
        padding: 10px;
    }
}

        /* Rest of your existing styles remain unchanged */
        .retry-message {
            text-align: center;
            margin: 2rem 0;
            font-size: 1.25rem;
            color: #dc3545;
            line-height: 1.5;
        }

        .countdown-box {
            background-color: rgba(220, 53, 69, 0.1);
            border: 2px solid #dc3545;
            border-radius: 0.625rem;
            padding: 1.25rem;
            margin: 2rem auto;
            max-width: 31.25rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: center;
            gap: 0.625rem;
        }

        .retry-text {
            color: #dc3545;
            font-weight: 600;
            flex: 0 1 auto;
        }

        .countdown-timer {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #dc3545;
            color: #ffffff;
            padding: 0.375rem 0.75rem;
            border-radius: 0.3125rem;
            margin: 0 0.3125rem;
            font-size: 1.5rem;
            min-width: 2.5rem;
            text-align: center;
            flex: 0 0 auto;
        }

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
            overflow: hidden;
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
            display: flex;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            justify-content: center;
            align-items: center;
        }

        [data-theme="dark"] .team.home-advantage {
            background-color: rgba(46, 204, 113, 0.3);
        }

        [data-theme="dark"] .team.away-advantage {
            background-color: rgba(231, 76, 60, 0.3);
        }

        [data-theme="dark"] .match-card.draw-likely .teams {
            background-color: rgba(241, 196, 15, 0.3);
        }

        .advantage {
            font-size: 0.9em;
            font-weight: bold;
            margin-top: 5px;
        }

        .advantage-home-advantage {
            color: var(--primary-color);
        }

        .advantage-away-advantage {
            color: #e74c3c;
        }

        .advantage-likely-draw {
            color: #f1c40f;
        }

        @media (max-width: 768px) {
            .hamburger {
                display: flex;
            }

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
                padding: 0 20px;
                gap: 10px;
            }

            .nav-menu.active {
                max-height: 500px;
            }

            .nav-link {
                width: 100%;
                text-align: left;
                padding: 12px 20px;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            }

            .nav-link:last-child {
                border-bottom: none;
            }

            .theme-toggle {
                width: 40px;
                height: 40px;
                margin: 10px 0;
            }

            .search-container {
                max-width: 100%;
            }

            .match-table table {
                font-size: 0.9em;
            }
            
            .match-table th,
            .match-table td {
                padding: 10px;
            }
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

            <div class="view-toggle">
                <button id="grid-view-btn" class="view-btn active" onclick="switchView('grid')">Grid View</button>
                <button id="table-view-btn" class="view-btn" onclick="switchView('table')">Table View</button>
                <button id="standings-view-btn" class="view-btn" onclick="switchView('standings')">Standings</button>
                <button id="scorers-view-btn" class="view-btn" onclick="switchView('scorers')" title="Top Scorers">⚽</button>
            </div>
        </div>

        <div class="match-container">
            <div class="match-grid" id="match-grid">
                <?php
                if (!empty($allMatches)) {
                    foreach ($allMatches as $index => $match) {
                        if (isset($match['status'])) {
                            $homeTeamId = $match['homeTeam']['id'] ?? 0;
                            $awayTeamId = $match['awayTeam']['id'] ?? 0;
                            $homeTeam = $match['homeTeam']['name'] ?? 
                                ($match['homeTeam']['shortName'] ?? 
                                "Home Team {$match['homeTeam']['id']}");
                            $awayTeam = $match['awayTeam']['name'] ?? 
                                ($match['awayTeam']['shortName'] ?? 
                                "Away Team {$match['awayTeam']['id']}");
                            $date = $match['utcDate'] ?? 'TBD' ? date('M d, Y H:i', strtotime($match['utcDate'])) : 'TBD';
                            $homeCrest = $match['homeTeam']['crest'] ?? '';
                            $awayCrest = $match['awayTeam']['crest'] ?? '';
                            $status = $match['status'];
                            $homeGoals = $match['score']['fullTime']['home'] ?? null;
                            $awayGoals = $match['score']['fullTime']['away'] ?? null;
                            [$prediction, $confidence, $resultIndicator, $predictedScore, $advantage, $homeForm, $awayForm, $retryInfo] = predictMatch($match, $apiKey, $baseUrl, $teamStats, $selectedComp);
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
                            <div class='match-card' data-home-id='$homeTeamId' data-away-id='$awayTeamId' data-index='$index' data-advantage='$advantage' data-status='$status'>
                                <div class='teams'>
                                    <div class='team home-team'>
                                        " . ($homeCrest ? "<img src='$homeCrest' alt='$homeTeam'>" : "") . "
                                        <p>$homeTeam</p>
                                        <div class='form-display' id='form-home-$index' data-form='$homeForm'>";
                            if ($homeStats['needsRetry']) {
                                echo "<div class='loading-spinner'></div>";
                            } else {
                                $homeFormDisplay = str_pad(substr($homeStats['form'], -6), 6, '-', STR_PAD_LEFT);
                                $homeFormDisplay = strrev($homeFormDisplay);
                                for ($i = 0; $i < 6; $i++) {
                                    $class = $homeFormDisplay[$i] === 'W' ? 'win' : ($homeFormDisplay[$i] === 'D' ? 'draw' : ($homeFormDisplay[$i] === 'L' ? 'loss' : 'empty'));
                                    if ($i === 5 && $homeFormDisplay[$i] !== '-' && strlen(trim($homeStats['form'], '-')) > 0) $class .= ' latest';
                                    echo "<span class='$class'>$homeFormDisplay[$i]</span>";
                                }
                            }
                            echo "</div>
                                    </div>
                                    <span class='vs'>VS</span>
                                    <div class='team away-team'>
                                        " . ($awayCrest ? "<img src='$awayCrest' alt='$awayTeam'>" : "") . "
                                        <p>$awayTeam</p>
                                        <div class='form-display' id='form-away-$index' data-form='$awayForm'>";
                            if ($awayStats['needsRetry']) {
                                echo "<div class='loading-spinner'></div>";
                            } else {
                                $awayFormDisplay = str_pad(substr($awayStats['form'], -6), 6, '-', STR_PAD_LEFT);
                                $awayFormDisplay = strrev($awayFormDisplay);
                                for ($i = 0; $i < 6; $i++) {
                                    $class = $awayFormDisplay[$i] === 'W' ? 'win' : ($awayFormDisplay[$i] === 'D' ? 'draw' : ($awayFormDisplay[$i] === 'L' ? 'loss' : 'empty'));
                                    if ($i === 5 && $awayFormDisplay[$i] !== '-' && strlen(trim($awayStats['form'], '-')) > 0) $class .= ' latest';
                                    echo "<span class='$class'>$awayFormDisplay[$i]</span>";
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
                                      <p class='advantage advantage-" . strtolower(str_replace(' ', '-', $advantage)) . "'>$advantage</p>";
                            }
                            echo "</div>
                                <button class='view-history-btn' onclick='toggleHistory(this)'>👁️ View History</button>
                                <div class='past-results' id='history-$index' style='display: none;'>";
                            
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

              <div class="match-table" id="match-table" style="display: none;">
    <div class="table-header">
        <h3>Match Predictions</h3>
        <button id="share-table-btn" class="share-btn" title="Share Table">
            <span class="share-icon">📤</span> Share
        </button>
    </div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Home Team</th>
                <th>Score</th>
                <th>Away Team</th>
                <th>Prediction</th>
                <th>Confidence</th>
                <th>Predicted Score</th>
                <th>Form (H/A)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (!empty($allMatches)) {
                foreach ($allMatches as $index => $match) {
                    if (isset($match['status'])) {
                        $homeTeamId = $match['homeTeam']['id'] ?? 0;
                        $awayTeamId = $match['awayTeam']['id'] ?? 0;
                        $homeTeam = $match['homeTeam']['name'] ?? 
                         ($match['homeTeam']['shortName'] ?? 
                         "Home Team {$match['homeTeam']['id']}");
                        $awayTeam = $match['awayTeam']['name'] ?? 
                          ($match['awayTeam']['shortName'] ?? 
                          "Away Team {$match['awayTeam']['id']}");
                        $date = $match['utcDate'] ?? 'TBD' ? date('M d, H:i', strtotime($match['utcDate'])) : 'TBD';
                        $status = $match['status'];
                        $homeGoals = $match['score']['fullTime']['home'] ?? null;
                        $awayGoals = $match['score']['fullTime']['away'] ?? null;
                        [$prediction, $confidence, $resultIndicator, $predictedScore, $advantage, $homeForm, $awayForm] = predictMatch($match, $apiKey, $baseUrl, $teamStats, $selectedComp);
                        $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats, $selectedComp);
                        $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats, $selectedComp);

                        // Process home form - latest on right
                         $homeFormDisplay = str_pad(substr($homeStats['form'], -6), 6, '-', STR_PAD_LEFT);
                         $homeFormDisplay = strrev($homeFormDisplay); // Reverse to show oldest on left, newest on right
                         $homeFormHtml = '';
                         for ($i = 0; $i < 6; $i++) {
                            $class = $homeFormDisplay[$i] === 'W' ? 'win' : ($homeFormDisplay[$i] === 'D' ? 'draw' : ($homeFormDisplay[$i] === 'L' ? 'loss' : 'empty'));
                            if ($i === 5 && $homeFormDisplay[$i] !== '-' && strlen(trim($homeStats['form'], '-')) > 0) $class .= ' latest';
                            $homeFormHtml .= "<span class='$class'>{$homeFormDisplay[$i]}</span>";
                        }

                        // Process away form - latest on right
                        $awayFormDisplay = str_pad(substr($awayStats['form'], -6), 6, '-', STR_PAD_LEFT);
                        $awayFormDisplay = strrev($awayFormDisplay); // Reverse to show oldest on left, newest on right
                        $awayFormHtml = '';
                        for ($i = 0; $i < 6; $i++) {
                           $class = $awayFormDisplay[$i] === 'W' ? 'win' : ($awayFormDisplay[$i] === 'D' ? 'draw' : ($awayFormDisplay[$i] === 'L' ? 'loss' : 'empty'));
                           if ($i === 5 && $awayFormDisplay[$i] !== '-' && strlen(trim($awayStats['form'], '-')) > 0) $class .= ' latest';
                           $awayFormHtml .= "<span class='$class'>{$awayFormDisplay[$i]}</span>";
                        }

                        echo "<tr data-index='$index' data-home-id='$homeTeamId' data-away-id='$awayTeamId' data-status='$status'>
                            <td>$date</td>
                            <td>$homeTeam<div class='form-display' id='table-form-home-$index'>$homeFormHtml</div></td>
                            <td>" . ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null ? "$homeGoals - $awayGoals" : "-") . "</td>
                            <td>$awayTeam<div class='form-display' id='table-form-away-$index'>$awayFormHtml</div></td>
                            <td id='table-prediction-$index'>$prediction $resultIndicator</td>
                            <td id='table-confidence-$index'>$confidence</td>
                            <td id='table-predicted-score-$index'>$predictedScore</td>
                            <td>$homeForm / $awayForm</td>
                        </tr>";
                    }
                }
            }
            ?>
        </tbody>
    </table>
</div                   
        </div>
    </div>
<div class="standings-table" id="standings-table" style="display: none;">
    <div class="table-header">
        <h3><?php echo $selectedComp; ?> Standings</h3>
        <button id="share-standings-btn" class="share-btn" title="Share Standings">
            <span class="share-icon">📤</span> Share
        </button>
    </div>
    <table>
        <thead>
            <tr>
                <th>POS</th>
                <th>Team</th>
                <th>P</th>
                <th>W</th>
                <th>D</th>
                <th>L</th>
                <th>GS</th>
                <th>GC</th>
                <th>GD</th>
                <th>PTS</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $standings = fetchStandings($selectedComp, $apiKey, $baseUrl);
            if (!$standings['error'] && !empty($standings['data'])) {
                foreach ($standings['data'] as $team) {
                    echo "<tr>";
                    echo "<td>" . $team['position'] . "</td>";
                    echo "<td>" . ($team['team']['name'] ?? 'Unknown') . "</td>";
                    echo "<td>" . $team['playedGames'] . "</td>";
                    echo "<td>" . $team['won'] . "</td>";
                    echo "<td>" . $team['draw'] . "</td>";
                    echo "<td>" . $team['lost'] . "</td>";
                    echo "<td>" . $team['goalsFor'] . "</td>";
                    echo "<td>" . $team['goalsAgainst'] . "</td>";
                    echo "<td>" . $team['goalDifference'] . "</td>";
                    echo "<td>" . $team['points'] . "</td>";
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
        <button id="share-scorers-btn" class="share-btn" title="Share Top Scorers">
            <span class="share-icon">📤</span> Share
        </button>
    </div>
    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Player</th>
                <th>Team</th>
                <th>Goals</th>
                <th>Assists</th>
                <th>Matches</th>
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


<!-- Modal Overlay -->
<div class="modal-overlay" id="auth-modal">
    <div class="modal-container">
        <!-- Close Button -->
        <button class="modal-close" onclick="closeModal()">&times;</button>

        <!-- Tab buttons to toggle Login and Signup forms -->
        <div class="tab-buttons">
            <button id="login-tab" onclick="showForm('login')" class="active">Login</button>
            <button id="signup-tab" onclick="showForm('signup')">Sign Up</button>
        </div>

        <!-- Login Form -->
        <div id="login-form" class="auth-form active">
            <h2>Login</h2>
            <form>
                <input type="text" placeholder="Username" required>
                <input type="password" placeholder="Password" required>
                <button type="submit" class="submit-btn">Log In</button>
            </form>
        </div>

        <!-- Signup Form -->
        <div id="signup-form" class="auth-form">
            <h2>Sign Up</h2>
            <form>
                <input type="text" placeholder="Username" required>
                <input type="password" placeholder="Password" required>
                <input type="password" placeholder="Confirm Password" required>
                <button type="submit" class="submit-btn">Sign Up</button>
            </form>
        </div>
    </div>
</div>
<script>
    function openModal() {
    document.getElementById("auth-modal").classList.add("active");
}

function closeModal() {
    document.getElementById("auth-modal").classList.remove("active");
}

function showForm(formType) {
    // Toggle active class for forms
    document.getElementById("login-form").classList.remove("active");
    document.getElementById("signup-form").classList.remove("active");
    document.getElementById(formType + "-form").classList.add("active");

    // Toggle active class for tabs
    document.getElementById("login-tab").classList.remove("active");
    document.getElementById("signup-tab").classList.remove("active");
    document.getElementById(formType + "-tab").classList.add("active");
}

</script>

                
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

        function shareScorersAsImage() {
    const tableElement = document.querySelector('#top-scorers-table table');
    const shareBtn = document.getElementById('share-scorers-btn');
    
    shareBtn.disabled = true;
    shareBtn.innerHTML = '<span class="share-icon">⏳</span> Processing...';

    html2canvas(tableElement, {
        backgroundColor: getComputedStyle(document.body).getPropertyValue('--card-bg'),
        scale: 2
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const fileName = `CPS_TopScorers_${new Date().toISOString().replace(/[-:T]/g, '').split('.')[0]}_${Math.random().toString(36).substring(2, 6)}.png`;

        if (navigator.share && navigator.canShare && navigator.canShare({ files: [] })) {
            canvas.toBlob(blob => {
                const file = new File([blob], fileName, { type: 'image/png' });
                navigator.share({
                    title: `${ '<?php echo $selectedComp; ?>' } Top Scorers`,
                    text: 'Check out the top scorers!',
                    files: [file]
                }).then(() => console.log('Top Scorers shared successfully'))
                  .catch(err => {
                      console.error('Share failed:', err);
                      fallbackDownload(imgData, fileName);
                  });
            });
        } else {
            fallbackDownload(imgData, fileName);
        }
    }).catch(error => {
        console.error('Error generating image:', error);
        alert('Failed to generate top scorers image. Please try again.');
    }).finally(() => {
        shareBtn.disabled = false;
        shareBtn.innerHTML = '<span class="share-icon">📤</span> Share';
    });
}

document.getElementById('share-scorers-btn').addEventListener('click', shareScorersAsImage);

document.getElementById('scorers-view-btn').addEventListener('click', function() {
    switchView('scorers');
    setTimeout(() => {
        const table = document.getElementById('top-scorers-table');
        if (table.style.display !== 'none') {
            table.scrollIntoView({ behavior: 'smooth' });
        }
    }, 100);
});
        function shareStandingsAsImage() {
    const tableElement = document.querySelector('#standings-table table');
    const shareBtn = document.getElementById('share-standings-btn');
    
    shareBtn.disabled = true;
    shareBtn.innerHTML = '<span class="share-icon">⏳</span> Processing...';

    html2canvas(tableElement, {
        backgroundColor: getComputedStyle(document.body).getPropertyValue('--card-bg'),
        scale: 2
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const fileName = `CPS_Standings_${new Date().toISOString().replace(/[-:T]/g, '').split('.')[0]}_${Math.random().toString(36).substring(2, 6)}.png`;

        if (navigator.share && navigator.canShare && navigator.canShare({ files: [] })) {
            canvas.toBlob(blob => {
                const file = new File([blob], fileName, { type: 'image/png' });
                navigator.share({
                    title: `${ '<?php echo $selectedComp; ?>' } Standings`,
                    text: 'Check out the latest standings!',
                    files: [file]
                }).then(() => console.log('Standings shared successfully'))
                  .catch(err => {
                      console.error('Share failed:', err);
                      fallbackDownload(imgData, fileName);
                  });
            });
        } else {
            fallbackDownload(imgData, fileName);
        }
    }).catch(error => {
        console.error('Error generating image:', error);
        alert('Failed to generate standings image. Please try again.');
    }).finally(() => {
        shareBtn.disabled = false;
        shareBtn.innerHTML = '<span class="share-icon">📤</span> Share';
    });
}

document.getElementById('share-standings-btn').addEventListener('click', shareStandingsAsImage);

document.getElementById('standings-view-btn').addEventListener('click', function() {
    switchView('standings');
    setTimeout(() => {
        const table = document.getElementById('standings-table');
        if (table.style.display !== 'none') {
            table.scrollIntoView({ behavior: 'smooth' });
        }
    }, 100);
});

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

        function fetchTeamData(teamId, index, isHome, attempt = 0, maxAttempts = 10) {
            const delay = Math.min(Math.pow(2, attempt) * 1000, 10000);
            const formElement = document.getElementById(`form-${isHome ? 'home' : 'away'}-${index}`);
            const tableFormElement = document.getElementById(`table-form-${isHome ? 'home' : 'away'}-${index}`);
            const historyElement = document.getElementById(`history-${index}`);
            const predictionElement = document.getElementById(`prediction-${index}`);
            const progressBar = predictionElement.querySelector('.progress-fill') || document.createElement('div');

            if (!progressBar.classList.contains('progress-fill')) {
                progressBar.classList.add('progress-fill');
                const progressContainer = document.createElement('div');
                progressContainer.classList.add('progress-bar');
                progressContainer.appendChild(progressBar);
                predictionElement.appendChild(progressContainer);
            }

            if (attempt === 5) {
                const retryNotice = document.createElement('div');
                retryNotice.className = 'retry-notice';
                retryNotice.innerHTML = 'Still trying to load data for this team. Please wait...';
                retryNotice.style.cssText = `
                    background-color: #fff3cd; 
                    border: 1px solid #ffeeba; 
                    color: #856404; 
                    padding: 10px; 
                    margin-top: 10px; 
                    border-radius: 5px; 
                    text-align: center;
                    font-size: 0.9em;
                `;
                predictionElement.appendChild(retryNotice);
                setTimeout(() => retryNotice.remove(), 5000);
            }

            fetch(`?action=fetch_team_data&teamId=${teamId}&competition=<?php echo $selectedComp; ?>&force_refresh=true&attempt=${attempt}`, {
                headers: { 'X-Auth-Token': '<?php echo $apiKey; ?>' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTeamUI(data, index, isHome, formElement, tableFormElement, historyElement, predictionElement);
                    progressBar.parentElement.remove();

                    const matchCard = document.querySelector(`.match-card[data-index="${index}"]`);
                    const otherTeamLoaded = isHome ?
                        !document.getElementById(`form-away-${index}`).querySelector('.loading-spinner') :
                        !document.getElementById(`form-home-${index}`).querySelector('.loading-spinner');
                    if (otherTeamLoaded) {
                        fetchPrediction(index, matchCard.dataset.homeId, matchCard.dataset.awayId);
                    }
                } else if (data.retry && attempt < maxAttempts) {
                    progressBar.style.width = `${(attempt + 1) / maxAttempts * 100}%`;
                    setTimeout(() => fetchTeamData(teamId, index, isHome, attempt + 1, maxAttempts), delay);
                } else {
                    console.error(`Max retries reached for team ${teamId}`);
                    formElement.innerHTML = '<p>Error loading data</p>';
                    tableFormElement.innerHTML = '<p>Error</p>';
                    progressBar.parentElement.remove();
                    predictionElement.querySelector('.retry-notice')?.remove();
                }
            })
            .catch(error => {
                console.error('Error fetching team data:', error);
                if (attempt < maxAttempts) {
                    progressBar.style.width = `${(attempt + 1) / maxAttempts * 100}%`;
                    setTimeout(() => fetchTeamData(teamId, index, isHome, attempt + 1, maxAttempts), delay);
                } else {
                    formElement.innerHTML = '<p>Failed to load data</p>';
                    tableFormElement.innerHTML = '<p>Failed</p>';
                    progressBar.parentElement.remove();
                    predictionElement.querySelector('.retry-notice')?.remove();
                }
            });
        }

        function updateTeamUI(data, index, isHome, formElement, tableFormElement, historyElement, predictionElement) {
            let formHtml = '';
            const form = data.form.slice(-6).padStart(6, '-');
            const reversedForm = form.split('').reverse().join('');
            for (let i = 0; i < 6; i++) {
                let className = reversedForm[i] === 'W' ? 'win' : (reversedForm[i] === 'D' ? 'draw' : (reversedForm[i] === 'L' ? 'loss' : 'empty'));
                if (i === 5 && reversedForm[i] !== '-' && data.form.trim('-').length > 0) className += ' latest';
                formHtml += `<span class="${className}">${reversedForm[i]}</span>`;
            }
            formElement.innerHTML = formHtml;
            tableFormElement.innerHTML = formHtml;
            formElement.classList.add('updated');
            tableFormElement.classList.add('updated');
            setTimeout(() => {
                formElement.classList.remove('updated');
                tableFormElement.classList.remove('updated');
            }, 2000);

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
        }

        function fetchPrediction(index, homeId, awayId, attempt = 0, maxAttempts = 10) {
            const delay = Math.min(Math.pow(2, attempt) * 1000, 10000);
            const predictionElement = document.getElementById(`prediction-${index}`);
            const tablePrediction = document.getElementById(`table-prediction-${index}`);
            const tableConfidence = document.getElementById(`table-confidence-${index}`);
            const tablePredictedScore = document.getElementById(`table-predicted-score-${index}`);
            const progressBar = predictionElement.querySelector('.progress-fill') || document.createElement('div');

            if (!progressBar.classList.contains('progress-fill')) {
                progressBar.classList.add('progress-fill');
                const progressContainer = document.createElement('div');
                progressContainer.classList.add('progress-bar');
                progressContainer.appendChild(progressBar);
                predictionElement.appendChild(progressContainer);
            }

            if (attempt === 5) {
                const retryNotice = document.createElement('div');
                retryNotice.className = 'retry-notice';
                retryNotice.innerHTML = 'Still predicting match outcome. Please wait...';
                retryNotice.style.cssText = `
                    background-color: #fff3cd; 
                    border: 1px solid #ffeeba; 
                    color: #856404; 
                    padding: 10px; 
                    margin-top: 10px; 
                    border-radius: 5px; 
                    text-align: center;
                    font-size: 0.9em;
                `;
                predictionElement.appendChild(retryNotice);
                setTimeout(() => retryNotice.remove(), 5000);
            }

            fetch(`?action=predict_match&homeId=${homeId}&awayId=${awayId}&competition=<?php echo $selectedComp; ?>&attempt=${attempt}`, {
                headers: { 'X-Auth-Token': '<?php echo $apiKey; ?>' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    predictionElement.innerHTML = `
                        <p>Prediction: ${data.prediction} <span class="result-indicator">${data.resultIndicator}</span></p>
                        <p class="predicted-score">Predicted Score: ${data.predictedScore}</p>
                        <p class="confidence">Confidence: ${data.confidence}</p>
                        <p class="advantage advantage-${data.advantage.toLowerCase().replace(' ', '-')}">${data.advantage}</p>
                    `;
                    tablePrediction.innerHTML = `${data.prediction} ${data.resultIndicator}`;
                    tableConfidence.innerHTML = data.confidence;
                    tablePredictedScore.innerHTML = data.predictedScore;
                    const matchCard = document.querySelector(`.match-card[data-index="${index}"]`);
                    applyAdvantageHighlight(matchCard, data.advantage);
                    progressBar.parentElement.remove();
                    predictionElement.querySelector('.retry-notice')?.remove();
                } else if (data.retry && attempt < maxAttempts) {
                    progressBar.style.width = `${(attempt + 1) / maxAttempts * 100}%`;
                    setTimeout(() => fetchPrediction(index, homeId, awayId, attempt + 1, maxAttempts), delay);
                } else {
                    console.error(`Max retries reached for prediction ${index}`);
                    progressBar.parentElement.remove();
                    predictionElement.querySelector('.retry-notice')?.remove();
                }
            })
            .catch(error => {
                console.error('Error fetching prediction:', error);
                if (attempt < maxAttempts) {
                    progressBar.style.width = `${(attempt + 1) / maxAttempts * 100}%`;
                    setTimeout(() => fetchPrediction(index, homeId, awayId, attempt + 1, maxAttempts), delay);
                } else {
                    progressBar.parentElement.remove();
                    predictionElement.querySelector('.retry-notice')?.remove();
                }
            });
        }

        function applyAdvantageHighlight(matchCard, advantage) {
            const homeTeam = matchCard.querySelector('.teams .team:first-child');
            const awayTeam = matchCard.querySelector('.teams .team:last-child');
            
            homeTeam.classList.remove('home-advantage');
            awayTeam.classList.remove('away-advantage');
            matchCard.classList.remove('draw-likely');

            if (advantage === 'Home Advantage') {
                homeTeam.classList.add('home-advantage');
            } else if (advantage === 'Away Advantage') {
                awayTeam.classList.add('away-advantage');
            } else if (advantage === 'Likely Draw') {
                matchCard.classList.add('draw-likely');
            }
        }

         function startMatchPolling() {
    setInterval(() => {
        document.querySelectorAll('.match-card, .match-table tr').forEach(element => {
            const homeId = element.dataset.homeId;
            const awayId = element.dataset.awayId;
            const index = element.dataset.index;
            const status = element.dataset.status;
            const matchInfo = element.classList.contains('match-card') 
                ? element.querySelector('.match-info p').textContent 
                : element.cells[0].textContent;

            if (matchInfo.includes('FINISHED') && element.querySelector('.result-indicator')) return;

            fetch(`?action=predict_match&homeId=${homeId}&awayId=${awayId}&competition=<?php echo $selectedComp; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (element.classList.contains('match-card')) {
                            // Existing match-card update code remains unchanged
                            const predictionElement = document.getElementById(`prediction-${index}`);
                            const homeFormElement = document.getElementById(`form-home-${index}`);
                            const awayFormElement = document.getElementById(`form-away-${index}`);
                            const matchInfoElement = element.querySelector('.match-info p');

                            predictionElement.innerHTML = `
                                <p>Prediction: ${data.prediction} <span class="result-indicator">${data.resultIndicator}</span></p>
                                <p class="predicted-score">Predicted Score: ${data.predictedScore}</p>
                                <p class="confidence">Confidence: ${data.confidence}</p>
                                <p class="advantage advantage-${data.advantage.toLowerCase().replace(' ', '-')}">${data.advantage}</p>
                            `;
                            applyAdvantageHighlight(element, data.advantage);

                            if (data.resultIndicator) {
                                element.dataset.status = 'FINISHED';
                                const currentText = matchInfoElement.textContent.split(' - ')[0];
                                fetch(`?action=fetch_team_data&teamId=${homeId}&competition=<?php echo $selectedComp; ?>&force_refresh=true`)
                                    .then(res => res.json())
                                    .then(homeData => {
                                        const homeGoals = homeData.results[0]?.match(/(\d+) - (\d+)/)?.[1] || 'N/A';
                                        const awayGoals = homeData.results[0]?.match(/(\d+) - (\d+)/)?.[2] || 'N/A';
                                        matchInfoElement.textContent = `${currentText} - ${homeGoals} : ${awayGoals}`;
                                    });

                                const homeForm = data.homeForm.slice(-6).padStart(6, '-').split('').reverse().join('');
                                let homeFormHtml = '';
                                for (let i = 0; i < 6; i++) {
                                    let className = homeForm[i] === 'W' ? 'win' : (homeForm[i] === 'D' ? 'draw' : (homeForm[i] === 'L' ? 'loss' : 'empty'));
                                    if (i === 5 && homeForm[i] !== '-' && data.homeForm.trim('-').length > 0) className += ' latest';
                                    homeFormHtml += `<span class="${className}">${homeForm[i]}</span>`;
                                }
                                homeFormElement.innerHTML = homeFormHtml;
                                homeFormElement.dataset.form = data.homeForm;

                                const awayForm = data.awayForm.slice(-6).padStart(6, '-').split('').reverse().join('');
                                let awayFormHtml = '';
                                for (let i = 0; i < 6; i++) {
                                    let className = awayForm[i] === 'W' ? 'win' : (awayForm[i] === 'D' ? 'draw' : (awayForm[i] === 'L' ? 'loss' : 'empty'));
                                    if (i === 5 && awayForm[i] !== '-' && data.awayForm.trim('-').length > 0) className += ' latest';
                                    awayFormHtml += `<span class="${className}">${awayForm[i]}</span>`;
                                }
                                awayFormElement.innerHTML = awayFormHtml;
                                awayFormElement.dataset.form = data.awayForm;

                                [homeFormElement, awayFormElement].forEach(el => {
                                    el.classList.add('updated');
                                    setTimeout(() => el.classList.remove('updated'), 2000);
                                });
                            }
                        } else {
                            const tablePrediction = document.getElementById(`table-prediction-${index}`);
                            const tableConfidence = document.getElementById(`table-confidence-${index}`);
                            const tablePredictedScore = document.getElementById(`table-predicted-score-${index}`);
                            const tableHomeForm = document.getElementById(`table-form-home-${index}`);
                            const tableAwayForm = document.getElementById(`table-form-away-${index}`);

                            tablePrediction.innerHTML = `${data.prediction} ${data.resultIndicator}`;
                            tableConfidence.innerHTML = data.confidence;
                            tablePredictedScore.innerHTML = data.predictedScore;

                            if (data.resultIndicator) {
                                element.dataset.status = 'FINISHED';
                                element.cells[2].textContent = `${data.homeGoals || 'N/A'} - ${data.awayGoals || 'N/A'}`;

                                // Updated table form display - latest on right
                                // For home team (Cardiff City FC)
const homeForm = data.homeForm.slice(-6).padStart(6, '-');
let homeFormHtml = '';
const homeFormLength = data.homeForm.trim('-').length;
for (let i = 0; i < 6; i++) {
    let className = homeForm[i] === 'W' ? 'win' : (homeForm[i] === 'D' ? 'draw' : (homeForm[i] === 'L' ? 'loss' : 'empty'));
    // Mark the latest result (rightmost non-dash) as 'latest'
    if (i === homeFormLength - 1 && homeForm[i] !== '-' && homeFormLength > 0) className += ' latest';
    homeFormHtml += `<span class="${className}">${homeForm[i]}</span>`;
}
tableHomeForm.innerHTML = homeFormHtml;

// For away team (Burnley FC)
const awayForm = data.awayForm.slice(-6).padStart(6, '-');
let awayFormHtml = '';
const awayFormLength = data.awayForm.trim('-').length;
for (let i = 0; i < 6; i++) {
    let className = awayForm[i] === 'W' ? 'win' : (awayForm[i] === 'D' ? 'draw' : (awayForm[i] === 'L' ? 'loss' : 'empty'));
    // Mark the latest result (rightmost non-dash) as 'latest'
    if (i === awayFormLength - 1 && awayForm[i] !== '-' && awayFormLength > 0) className += ' latest';
    awayFormHtml += `<span class="${className}">${awayForm[i]}</span>`;
}
                                tableAwayForm.innerHTML = awayFormHtml;

                                element.cells[7].textContent = `${data.homeForm} / ${data.awayForm}`;
                            }
                        }
                    }
                })
                .catch(error => console.error('Polling error:', error));
        });
    }, 60000);
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
                    })
                    .catch(error => {
                        console.error('Error fetching teams:', error);
                        autocompleteDropdown.innerHTML = '<div class="autocomplete-item">Error loading teams</div>';
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
            if (!searchContainer.contains(e.target)) {
                searchContainer.classList.remove('active');
            }
        });

        // Function to generate a unique filename
function generateUniqueFilename() {
    const now = new Date();
    const timestamp = now.toISOString().replace(/[-:T]/g, '').split('.')[0]; // e.g., 20250304_123456
    const randomStr = Math.random().toString(36).substring(2, 6); // 4-char random string, e.g., "ab4x"
    return `CPS#manu_${timestamp}_${randomStr}.png`;
}

// Function to convert table to image and share
function shareTableAsImage() {
    const tableElement = document.querySelector('#match-table table');
    const shareBtn = document.getElementById('share-table-btn');
    
    // Disable button during processing
    shareBtn.disabled = true;
    shareBtn.innerHTML = '<span class="share-icon">⏳</span> Processing...';

    html2canvas(tableElement, {
        backgroundColor: getComputedStyle(document.body).getPropertyValue('--card-bg'), // Match theme
        scale: 2 // Increase resolution
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const fileName = generateUniqueFilename(); // Use unique filename

        // Web Share API
        if (navigator.share && navigator.canShare && navigator.canShare({ files: [] })) {
            canvas.toBlob(blob => {
                const file = new File([blob], fileName, { type: 'image/png' });
                navigator.share({
                    title: 'CPS Football Predictions',
                    text: 'Check out these football match predictions!',
                    files: [file]
                }).then(() => {
                    console.log('Table shared successfully');
                }).catch(err => {
                    console.error('Share failed:', err);
                    fallbackDownload(imgData, fileName);
                });
            });
        } else {
            // Fallback: Download the image
            fallbackDownload(imgData, fileName);
        }
    }).catch(error => {
        console.error('Error generating image:', error);
        alert('Failed to generate table image. Please try again.');
    }).finally(() => {
        // Re-enable button
        shareBtn.disabled = false;
        shareBtn.innerHTML = '<span class="share-icon">📤</span> Share';
    });
}

// Fallback function to download image
function fallbackDownload(imgData, fileName) {
    const link = document.createElement('a');
    link.href = imgData;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Event listener for share button
document.getElementById('share-table-btn').addEventListener('click', shareTableAsImage);

// Ensure table view is visible when sharing
document.getElementById('table-view-btn').addEventListener('click', function() {
    switchView('table');
    setTimeout(() => {
        const table = document.getElementById('match-table');
        if (table.style.display !== 'none') {
            table.scrollIntoView({ behavior: 'smooth' });
        }
    }, 100);
});
        
        function adjustTeamSpacing() {
            document.querySelectorAll('.match-card').forEach(card => {
                const teamsContainer = card.querySelector('.teams');
                const homeTeam = card.querySelector('.home-team');
                const awayTeam = card.querySelector('.away-team');
                const vsElement = card.querySelector('.vs');
                const cardWidth = card.offsetWidth;

                const vsPadding = Math.max(5, cardWidth * 0.03);
                vsElement.style.padding = `0 ${vsPadding}px`;

                const homeTextWidth = homeTeam.querySelector('p').scrollWidth;
                const awayTextWidth = awayTeam.querySelector('p').scrollWidth;
                const maxTextWidth = Math.max(homeTextWidth, awayTextWidth);
                const extraPadding = Math.min(10, maxTextWidth * 0.05);
                homeTeam.style.paddingRight = `${0.5 + extraPadding / 16}em`;
                awayTeam.style.paddingLeft = `${0.5 + extraPadding / 16}em`;
            });
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

            document.querySelectorAll('.match-card').forEach(matchCard => {
                const advantage = matchCard.dataset.advantage;
                if (advantage && !matchCard.querySelector('.prediction').innerHTML.includes('Loading')) {
                    applyAdvantageHighlight(matchCard, advantage);
                }
            });

            const savedView = localStorage.getItem('matchView') || 'grid';
            switchView(savedView);

            adjustTeamSpacing();
            window.addEventListener('resize', adjustTeamSpacing);
            startMatchPolling();

            if (typeof incompleteTeams !== 'undefined' && incompleteTeams.length > 0) {
                incompleteTeams.forEach(teamId => {
                    document.querySelectorAll(`.match-card[data-home-id="${teamId}"], .match-card[data-away-id="${teamId}"]`).forEach(card => {
                        const index = card.dataset.index;
                        const homeId = card.dataset.homeId;
                        const awayId = card.dataset.awayId;

                        if (homeId == teamId) fetchTeamData(homeId, index, true);
                        if (awayId == teamId) fetchTeamData(awayId, index, false);
                    });
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
<script src="network-status.js"></script>
<script src="tab-title-switcher.js"></script>
<?php include 'global-footer.php'; ?>
    
