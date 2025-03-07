<?php
session_start();
require_once 'recaptcha_handler.php';

// Set timezone to East Africa Time (Nairobi, Kenya, UTC+3)
date_default_timezone_set('Africa/Nairobi');

// Initialize session data with error handling
if (!isset($_SESSION['teamStats'])) {
    $_SESSION['teamStats'] = [];
}

// DB credentials
define('DB_HOST', 'sql105.infinityfree.com');
define('DB_USER', 'if0_37772405');
define('DB_PASS', 'hMCWvBjYOKjDE');
define('DB_NAME', 'if0_37772405_cps');

// Initialize PDO connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    handleError("Database connection failed: " . $e->getMessage());
}

// Ensure tables exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS team_results (
        team_id INT PRIMARY KEY,
        results JSON,
        form VARCHAR(10),
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS standings (
        competition_code VARCHAR(10),
        team_id INT,
        position INT,
        goals_for INT,
        goal_difference INT,
        points INT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (competition_code, team_id)
    )
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS matches (
        match_id INT PRIMARY KEY,
        competition_code VARCHAR(10),
        home_team_id INT,
        away_team_id INT,
        prediction_data JSON,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

$apiKey = 'd4c9fea41bf94bb29cade8f12952b3d8';
$baseUrl = 'https://api.football-data.org/v4/';
$teamStats = &$_SESSION['teamStats'];

// Error handling functions
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

function fetchWithRetry($url, $apiKey, $isAjax = false, $attempt = 0, $pdo = null) {
    global $pdo; // Use global PDO if not passed
    $maxAttempts = 3;
    $baseTimeout = 15;

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
        error_log("Attempt $attempt/$maxAttempts - Failed fetching URL: $url - Error: $errorMessage (Code: $errorCode)");
        curl_close($ch);

        if ($errorCode == CURLE_OPERATION_TIMEDOUT && $attempt < $maxAttempts) {
            $retrySeconds = min(pow(2, $attempt), 8);
            error_log("Retrying in $retrySeconds seconds due to timeout...");
            sleep($retrySeconds);
            return fetchWithRetry($url, $apiKey, $isAjax, $attempt + 1, $pdo);
        }

        if ($attempt >= $maxAttempts) {
            return fetchFromDatabase($url, $pdo);
        }

        return ['error' => true, 'message' => "Connection error: $errorMessage", 'attempts' => $attempt + 1];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);

    if ($httpCode == 429) {
        $retrySeconds = min(pow(2, $attempt), 32);
        preg_match('/Retry-After: (\d+)/i', $headers, $matches);
        if (!empty($matches[1])) $retrySeconds = max($retrySeconds, (int)$matches[1]);
        error_log("Rate limit exceeded for URL: $url - HTTP 429 - Retrying in $retrySeconds seconds (Attempt " . ($attempt + 1) . ")");

        if ($isAjax) {
            return ['error' => true, 'retry' => true, 'retrySeconds' => $retrySeconds, 'nextAttempt' => $attempt + 1];
        } else {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    let timeLeft = $retrySeconds;
                    const retryDiv = document.createElement('div');
                    retryDiv.id = 'retry-message';
                    retryDiv.className = 'retry-message countdown-box';
                    retryDiv.innerHTML = '<span class=\"retry-text\">Rate limit exceeded. Retry attempt ' + " . ($attempt + 1) . " + '. Retrying in </span><span id=\"countdown\" class=\"countdown-timer\">' + timeLeft + '</span><span class=\"retry-text\"> seconds...</span>';
                    document.body.insertBefore(retryDiv, document.body.firstChild.nextSibling);
                    
                    const timer = setInterval(() => {
                        timeLeft--;
                        document.getElementById('countdown').textContent = timeLeft;
                        if (timeLeft <= 0) {
                            clearInterval(timer);
                            let url = window.location.pathname + window.location.search;
                            url += (window.location.search ? '&' : '?') + 'attempt=' + " . ($attempt + 1) . ";
                            window.location.href = url;
                        }
                    }, 1000);
                });
            </script>";
            return ['error' => true, 'retry' => true];
        }
    } elseif ($httpCode == 200) {
        $data = json_decode($body, true);
        cacheToDatabase($url, $data, $pdo);
        error_log("Successfully fetched URL: $url - HTTP 200 (Attempt $attempt)");
        return ['error' => false, 'data' => $data];
    } else {
        error_log("Failed fetching URL: $url - HTTP $httpCode (Attempt $attempt)");
        return ['error' => true, 'message' => "API Error: HTTP $httpCode"];
    }
}

function fetchFromDatabase($url, $pdo) {
    global $baseUrl;
    if (strpos($url, 'teams/') !== false && strpos($url, '/matches') !== false) {
        preg_match('/teams\/(\d+)/', $url, $matches);
        $teamId = $matches[1] ?? 0;
        $stmt = $pdo->prepare("SELECT results FROM team_results WHERE team_id = :teamId AND last_updated > NOW() - INTERVAL 1 DAY");
        $stmt->execute(['teamId' => $teamId]);
        $row = $stmt->fetch();
        if ($row && $row['results']) {
            error_log("Falling back to database for team $teamId results");
            return ['error' => false, 'data' => ['matches' => json_decode($row['results'], true)]];
        }
    } elseif (strpos($url, 'competitions/') !== false && strpos($url, '/standings') !== false) {
        preg_match('/competitions\/([^\/]+)/', $url, $matches);
        $compCode = $matches[1] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM standings WHERE competition_code = :compCode AND last_updated > NOW() - INTERVAL 1 DAY");
        $stmt->execute(['compCode' => $compCode]);
        $rows = $stmt->fetchAll();
        if ($rows) {
            $standings = array_map(function($row) {
                return [
                    'team' => ['id' => $row['team_id']],
                    'position' => $row['position'],
                    'goalsFor' => $row['goals_for'],
                    'goalDifference' => $row['goal_difference'],
                    'points' => $row['points']
                ];
            }, $rows);
            error_log("Falling back to database for competition $compCode standings");
            return ['error' => false, 'data' => ['standings' => [['table' => $standings]]]];
        }
    }
    error_log("No recent database fallback available for URL: $url");
    return ['error' => true, 'message' => 'API unreachable and no recent data in database'];
}

function cacheToDatabase($url, $data, $pdo) {
    if (strpos($url, 'teams/') !== false && strpos($url, '/matches') !== false) {
        preg_match('/teams\/(\d+)/', $url, $matches);
        $teamId = $matches[1] ?? 0;
        $stmt = $pdo->prepare("
            INSERT INTO team_results (team_id, results, form) 
            VALUES (:teamId, :results, :form) 
            ON DUPLICATE KEY UPDATE results = :results, form = :form, last_updated = NOW()
        ");
        $form = calculateFormFromMatches($data['matches'] ?? [], $teamId);
        $stmt->execute([
            'teamId' => $teamId,
            'results' => json_encode($data['matches'] ?? []),
            'form' => $form
        ]);
    } elseif (strpos($url, 'competitions/') !== false && strpos($url, '/standings') !== false) {
        preg_match('/competitions\/([^\/]+)/', $url, $matches);
        $compCode = $matches[1] ?? '';
        $stmt = $pdo->prepare("
            INSERT INTO standings (competition_code, team_id, position, goals_for, goal_difference, points) 
            VALUES (:compCode, :teamId, :position, :goalsFor, :goalDiff, :points) 
            ON DUPLICATE KEY UPDATE position = :position, goals_for = :goalsFor, goal_difference = :goalDiff, points = :points, last_updated = NOW()
        ");
        foreach ($data['standings'][0]['table'] ?? [] as $team) {
            $stmt->execute([
                'compCode' => $compCode,
                'teamId' => $team['team']['id'],
                'position' => $team['position'],
                'goalsFor' => $team['goalsFor'],
                'goalDiff' => $team['goalDifference'],
                'points' => $team['points']
            ]);
        }
    }
}

function calculateFormFromMatches($matches, $teamId) {
    $formArray = [];
    foreach (array_reverse($matches) as $match) {
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
    return implode('', array_slice($formArray, 0, 6));
}

function fetchTeamResults($teamId, $apiKey, $baseUrl, $pdo) {
    $pastDate = date('Y-m-d', strtotime('-60 days'));
    $currentDate = date('Y-m-d');
    $url = $baseUrl . "teams/$teamId/matches?dateFrom=$pastDate&dateTo=$currentDate&limit=10&status=FINISHED";
    $response = fetchWithRetry($url, $apiKey, true, 0, $pdo);
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

function fetchStandings($competition, $apiKey, $baseUrl, $pdo) {
    $url = $baseUrl . "competitions/$competition/standings";
    $response = fetchWithRetry($url, $apiKey, true, 0, $pdo);
    if ($response['error']) {
        return $response;
    }
    return ['error' => false, 'data' => $response['data']['standings'][0]['table'] ?? []];
}

function fetchTeams($competition, $apiKey, $baseUrl, $pdo) {
    $url = $baseUrl . "competitions/$competition/teams";
    $response = fetchWithRetry($url, $apiKey, true, 0, $pdo);
    if ($response['error']) {
        return [];
    }
    return $response['data']['teams'] ?? [];
}

function calculateTeamStrength($teamId, $apiKey, $baseUrl, &$teamStats, $competition, $pdo) {
    try {
        $forceRefresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] === 'true';

        if (!isset($teamStats[$teamId]) || empty($teamStats[$teamId]['results']) || empty($teamStats[$teamId]['form']) || $forceRefresh) {
            $response = fetchTeamResults($teamId, $apiKey, $baseUrl, $pdo);
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

            $standingsResponse = fetchStandings($competition, $apiKey, $baseUrl, $pdo);
            $attempt = 0;
            $maxDelay = 32;
            while ($standingsResponse['error'] && $attempt < 3) {
                $attempt++;
                $delay = min(pow(2, $attempt), $maxDelay);
                error_log("Standings fetch failed for $competition (attempt $attempt): " . $standingsResponse['message'] . ". Retrying in $delay seconds...");
                sleep($delay);
                $standingsResponse = fetchStandings($competition, $apiKey, $baseUrl, $pdo);
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

function predictMatch($match, $apiKey, $baseUrl, &$teamStats, $competition, $pdo) {
    try {
        $homeTeamId = $match['homeTeam']['id'] ?? 0;
        $awayTeamId = $match['awayTeam']['id'] ?? 0;

        if (!$homeTeamId || !$awayTeamId) {
            return ["N/A", "0%", "", "0-0", "", "", "", []];
        }

        $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats, $competition, $pdo);
        $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats, $competition, $pdo);

        if ($homeStats['needsRetry'] || $awayStats['needsRetry']) {
            $retryInfo = [];
            if ($homeStats['retry']) $retryInfo['home'] = ['retrySeconds' => $homeStats['retrySeconds'], 'nextAttempt' => $homeStats['nextAttempt']];
            if ($awayStats['retry']) $retryInfo['away'] = ['retrySeconds' => $awayStats['retrySeconds'], 'nextAttempt' => $awayStats['nextAttempt']];
            return ["Loading...", "N/A", "", "N/A", "", $homeStats['form'], $awayStats['form'], $retryInfo];
        }

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

        $homeGD = $homeStats['standings']['goalDifference'] ?? 0;
        $awayGD = $awayStats['standings']['goalDifference'] ?? 0;
        $homePointsPerGame = ($homeStats['standings']['points'] ?? 0) / $homeGames;
        $awayPointsPerGame = ($awayStats['standings']['points'] ?? 0) / $awayGames;
        $homePosition = $homeStats['standings']['position'] ?? 10;
        $awayPosition = $awayStats['standings']['position'] ?? 10;

        $homeHomeWins = $homeStats['standings']['home']['won'] ?? $homeStats['wins'] / 2;
        $awayAwayWins = $awayStats['standings']['away']['won'] ?? $awayStats['wins'] / 2;
        $homeStrengthAdjustment = 1.0 + ($homeHomeWins / max($homeGames / 2, 1)) * 0.15;
        $awayStrengthAdjustment = 1.0 - ($awayAwayWins / max($awayGames / 2, 1)) * 0.10;

        $competitionBase = match ($competition) {
            'UEFA Champions League' => 1.1,
            'English Championship' => 0.95,
            default => 1.0
        };
        $matchGoalAvg = ($homeGoalAvg + $awayGoalAvg + $homeConcededAvg + $awayConcededAvg) / 4;
        $homeFormArray = str_split(str_pad($homeStats['form'], 6, '-', STR_PAD_LEFT));
        $awayFormArray = str_split(str_pad($awayStats['form'], 6, '-', STR_PAD_LEFT));
        $homeStreak = abs(calculateStreak($homeFormArray));
        $awayStreak = abs(calculateStreak($awayFormArray));
        $formVariance = 1 + ($homeStreak + $awayStreak) * 0.02;
        $gdVariance = 1 + (abs($homeGD) + abs($awayGD)) * 0.005 / max($homeGames, $awayGames);
        $competitionFactor = min(1.3, max(0.8, $competitionBase * (1 + ($matchGoalAvg - 2.5) / 5) * $formVariance * $gdVariance));

        $maxWeight = 100;
        $winWeight = min(25, 10 + ($homeGames + $awayGames) * 0.2);
        $drawWeight = min(15, 5 + ($homeGames + $awayGames) * 0.1);
        $goalWeight = min(25, 10 + (abs($homeGD) + abs($awayGD)) * 0.02 / max($homeGames, $awayGames));
        $standingsWeight = min(25, 10 + ($homeGames + $awayGames) * 0.25);

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
        ) * $homeStrengthAdjustment * $competitionFactor;
        $awayStrength = (
            $awayWinRate * $winWeight +
            $awayDrawRate * $drawWeight +
            ($awayGoalAvg + $awayGD / $awayGames) * $goalWeight +
            $awayFormScore * $formWeight +
            $awayPointsPerGame * $standingsWeight
        ) * $awayStrengthAdjustment * $competitionFactor;

        $randomFactor = 0;
        $homeStrength *= (1 + $randomFactor);
        $awayStrength *= (1 - $randomFactor);

        $diff = $homeStrength - $awayStrength + (20 - $homePosition) * 0.03 - (20 - $awayPosition) * 0.03;
        $totalStrength = $homeStrength + $awayStrength + 1;
        $confidenceBase = 50 + (abs($diff) / $totalStrength * 100 * $formConsistency);
        $confidence = min(85, max(55, $confidenceBase));

        $homeAttackStrength = min(2.5, $homeGoalAvg + $homeGD / $homeGames);
        $awayAttackStrength = min(2.5, $awayGoalAvg + $awayGD / $awayGames);
        $homeDefStrength = min(2, $homeConcededAvg);
        $awayDefStrength = min(2, $awayConcededAvg);
        $expectedHomeGoals = max(0, min(4, $homeAttackStrength * (1 + $diff / 50) / ($awayDefStrength + 1) * $competitionFactor));
        $expectedAwayGoals = max(0, min(4, $awayAttackStrength * (1 - $diff / 50) / ($homeDefStrength + 1) * $competitionFactor));
        $predictedHomeGoals = max(0, round($expectedHomeGoals + $randomFactor));
        $predictedAwayGoals = max(0, round($expectedAwayGoals - $randomFactor));
        $predictedScore = "$predictedHomeGoals-$predictedAwayGoals";

        $homeTeam = $match['homeTeam']['name'] ?? ($match['homeTeam']['shortName'] ?? 'Home Team');
        $awayTeam = $match['awayTeam']['name'] ?? ($match['awayTeam']['shortName'] ?? 'Away Team');
        $status = $match['status'] ?? 'SCHEDULED';
        $homeGoals = $match['score']['fullTime']['home'] ?? null;
        $awayGoals = $match['score']['fullTime']['away'] ?? null;

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

function fetchTopScorers($competition, $apiKey, $baseUrl, $pdo) {
    $url = $baseUrl . "competitions/$competition/scorers";
    $response = fetchWithRetry($url, $apiKey, true, 0, $pdo);
    if ($response['error']) {
        return $response;
    }
    return ['error' => false, 'data' => $response['data']['scorers'] ?? []];
}

// AJAX handling
$action = $_GET['action'] ?? 'main';
if (isset($_GET['ajax']) || in_array($action, ['fetch_team_data', 'predict_match', 'search_teams'])) {
    header('Content-Type: application/json');
    switch ($action) {
        case 'fetch_team_data':
            $teamId = (int)($_GET['teamId'] ?? 0);
            if (!$teamId) {
                handleJsonError('Invalid team ID');
            }
            
            $response = fetchTeamResults($teamId, $apiKey, $baseUrl, $pdo);
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
            
            $stats = calculateTeamStrength($teamId, $apiKey, $baseUrl, $teamStats, $_GET['competition'] ?? 'PL', $pdo);
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
            ], $apiKey, $baseUrl, $teamStats, $_GET['competition'] ?? 'PL', $pdo);
            
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
            $teams = fetchTeams($competition, $apiKey, $baseUrl, $pdo);
            $filteredTeams = array_filter($teams, function($team) use ($query) {
                return stripos($team['name'], $query) !== false || stripos($team['shortName'] ?? '', $query) !== false;
            });
            echo json_encode(array_values(array_map(function($team) {
                return ['id' => $team['id'], 'name' => $team['name'], 'crest' => $team['crest'] ?? ''];
            }, $filteredTeams)));
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
    if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        echo "<div class='user-menu'>";
        echo "<button class='nav-link user-btn' onclick='toggleUserMenu()'>" . htmlspecialchars($_SESSION['username']) . " ▼</button>";
        echo "<div class='user-dropdown' id='userDropdown'>";
        echo "<a href='#settings' class='dropdown-item'>Settings</a>";
        echo "<a href='?logout=true' class='dropdown-item'>Logout</a>";
        echo "</div>";
        echo "</div>";
    } else {
        echo "<button class='nav-link auth-btn' onclick='openModal()'>Login/Signup</button>";
    }
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
    $compResponse = fetchWithRetry($competitionsUrl, $apiKey, false, 0, $pdo);
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
    $matchResponse = fetchWithRetry($matchesUrl, $apiKey, false, 0, $pdo);
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

        .view-toggle {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            padding: 10px;
            width: 100%;
        }

        .view-btn {
            padding: 8px 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #fff;
            cursor: pointer;
            transition: background-color 0.2s;
            flex: 0 0 auto;
            white-space: nowrap;
        }

        .view-btn:hover {
            background-color: #f5f5f5;
        }

        .view-btn.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
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

        .match-table .form-display span.win {
            color: #28a745;
        }

        .match-table .form-display span.draw {
            color: #fd7e14;
        }

        .match-table .form-display span.loss {
            color: #dc3545;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CPS Football Predictions</h1>
        </div>

        <div class="controls">
            <select onchange="window.location.href='?competition='+this.value+'&filter=<?php echo $filter; ?>'">
                <?php foreach ($competitions as $comp): ?>
                    <option value="<?php echo $comp['code']; ?>" <?php echo $selectedComp == $comp['code'] ? 'selected' : ''; ?>>
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

        <div class="match-grid" id="match-grid">
            <?php
            if (!empty($allMatches)) {
                foreach ($allMatches as $index => $match) {
                    if (isset($match['status'])) {
                        $homeTeamId = $match['homeTeam']['id'] ?? 0;
                        $awayTeamId = $match['awayTeam']['id'] ?? 0;
                        $homeTeam = $match['homeTeam']['name'] ?? ($match['homeTeam']['shortName'] ?? "Home Team {$match['homeTeam']['id']}");
                        $awayTeam = $match['awayTeam']['name'] ?? ($match['awayTeam']['shortName'] ?? "Away Team {$match['awayTeam']['id']}");
                        $date = $match['utcDate'] ?? 'TBD' ? date('M d, H:i', strtotime($match['utcDate'])) : 'TBD';
                        $status = $match['status'];
                        $homeGoals = $match['score']['fullTime']['home'] ?? null;
                        $awayGoals = $match['score']['fullTime']['away'] ?? null;
                        [$prediction, $confidence, $resultIndicator, $predictedScore, $advantage, $homeForm, $awayForm] = predictMatch($match, $apiKey, $baseUrl, $teamStats, $selectedComp, $pdo);

                        echo "<div class='match-card' data-index='$index' data-home-id='$homeTeamId' data-away-id='$awayTeamId' data-status='$status' data-advantage='$advantage'>";
                        echo "<div class='teams'>";
                        echo "<div class='team home-team'><p>$homeTeam</p><div class='form-display' id='form-home-$index'><div class='loading-spinner'></div></div></div>";
                        echo "<div class='vs'>vs</div>";
                        echo "<div class='team away-team'><p>$awayTeam</p><div class='form-display' id='form-away-$index'><div class='loading-spinner'></div></div></div>";
                        echo "</div>";
                        echo "<div class='match-info'><p>$date" . ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null ? " - $homeGoals : $awayGoals" : "") . "</p></div>";
                        echo "<div class='prediction' id='prediction-$index'><p>Loading prediction...</p></div>";
                        echo "<div class='past-results' id='history-$index' style='display:none;'>Loading history...</div>";
                        echo "<button class='view-history-btn' onclick='toggleHistory(this)'>👁️ View History</button>";
                        echo "</div>";
                    }
                }
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
                                $homeTeam = $match['homeTeam']['name'] ?? ($match['homeTeam']['shortName'] ?? "Home Team {$match['homeTeam']['id']}");
                                $awayTeam = $match['awayTeam']['name'] ?? ($match['awayTeam']['shortName'] ?? "Away Team {$match['awayTeam']['id']}");
                                $date = $match['utcDate'] ?? 'TBD' ? date('M d, H:i', strtotime($match['utcDate'])) : 'TBD';
                                $status = $match['status'];
                                $homeGoals = $match['score']['fullTime']['home'] ?? null;
                                $awayGoals = $match['score']['fullTime']['away'] ?? null;
                                [$prediction, $confidence, $resultIndicator, $predictedScore, $advantage, $homeForm, $awayForm] = predictMatch($match, $apiKey, $baseUrl, $teamStats, $selectedComp, $pdo);
                                $homeStats = calculateTeamStrength($homeTeamId, $apiKey, $baseUrl, $teamStats, $selectedComp, $pdo);
                                $awayStats = calculateTeamStrength($awayTeamId, $apiKey, $baseUrl, $teamStats, $selectedComp, $pdo);

                                $homeFormDisplay = str_pad(substr($homeStats['form'], -6), 6, '-', STR_PAD_LEFT);
                                $homeFormDisplay = strrev($homeFormDisplay);
                                $homeFormHtml = '';
                                for ($i = 0; $i < 6; $i++) {
                                    $class = $homeFormDisplay[$i] === 'W' ? 'win' : ($homeFormDisplay[$i] === 'D' ? 'draw' : ($homeFormDisplay[$i] === 'L' ? 'loss' : 'empty'));
                                    if ($i === 5 && $homeFormDisplay[$i] !== '-' && strlen(trim($homeStats['form'], '-')) > 0) $class .= ' latest';
                                    $homeFormHtml .= "<span class='$class'>{$homeFormDisplay[$i]}</span>";
                                }

                                $awayFormDisplay = str_pad(substr($awayStats['form'], -6), 6, '-', STR_PAD_LEFT);
                                $awayFormDisplay = strrev($awayFormDisplay);
                                $awayFormHtml = '';
                                for ($i = 0; $i < 6; $i++) {
                                    $class = $awayFormDisplay[$i] === 'W' ? 'win' : ($awayFormDisplay[$i] === 'D' ? 'draw' : ($awayFormDisplay[$i] === 'L' ? 'loss' : 'empty'));
                                    if ($i === 5 && $awayFormDisplay[$i] !== '-' && strlen(trim($awayStats['form'], '-')) > 0) $class .= ' latest';
                                    $awayFormHtml .= "<span class='$class'>{$awayFormDisplay[$i]}</span>";
                                }

                                echo "<tr data-index='$index' data-home-id='$homeTeamId' data-away-id='$awayTeamId' data-status='$status'>";
                                echo "<td>$date</td>";
                                echo "<td>$homeTeam<div class='form-display' id='table-form-home-$index'>$homeFormHtml</div></td>";
                                echo "<td>" . ($status === 'FINISHED' && $homeGoals !== null && $awayGoals !== null ? "$homeGoals - $awayGoals" : "-") . "</td>";
                                echo "<td>$awayTeam<div class='form-display' id='table-form-away-$index'>$awayFormHtml</div></td>";
                                echo "<td id='table-prediction-$index'>$prediction $resultIndicator</td>";
                                echo "<td id='table-confidence-$index'>$confidence</td>";
                                echo "<td id='table-predicted-score-$index'>$predictedScore</td>";
                                echo "<td><div class='form-display'>$homeFormHtml</div><div class='form-display'>$awayFormHtml</div></td>";
                                echo "</tr>";
                            }
                        }
                    } else {
                        echo "<tr><td colspan='8'>No matches found for the selected criteria.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="standings-table" id="standings-table" style="display: none;">
            <div class="table-header">
                <h3>League Standings</h3>
                <button id="share-standings-btn" class="share-btn" title="Share Standings">
                    <span class="share-icon">📤</span> Share
                </button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Team</th>
                        <th>Points</th>
                        <th>Goals For</th>
                        <th>Goal Difference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $standingsResponse = fetchStandings($selectedComp, $apiKey, $baseUrl, $pdo);
                    if (!$standingsResponse['error']) {
                        $standings = $standingsResponse['data'];
                        foreach ($standings as $team) {
                            echo "<tr>";
                            echo "<td>{$team['position']}</td>";
                            echo "<td>" . htmlspecialchars($team['team']['name']) . "</td>";
                            echo "<td>{$team['points']}</td>";
                            echo "<td>{$team['goalsFor']}</td>";
                            echo "<td>{$team['goalDifference']}</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>Unable to load standings: {$standingsResponse['message']}</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="top-scorers-table" id="top-scorers-table" style="display: none;">
            <div class="table-header">
                <h3>Top Scorers</h3>
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
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $scorersResponse = fetchTopScorers($selectedComp, $apiKey, $baseUrl, $pdo);
                    if (!$scorersResponse['error']) {
                        $scorers = $scorersResponse['data'];
                        foreach ($scorers as $index => $scorer) {
                            echo "<tr>";
                            echo "<td>" . ($index + 1) . "</td>";
                            echo "<td>" . htmlspecialchars($scorer['player']['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($scorer['team']['name']) . "</td>";
                            echo "<td>{$scorer['goals']}</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>Unable to load top scorers: {$scorersResponse['message']}</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let currentView = 'grid';
        let activeFilters = '<?php echo $filter; ?>';

        function switchView(view) {
            document.getElementById('match-grid').style.display = view === 'grid' ? 'grid' : 'none';
            document.getElementById('match-table').style.display = view === 'table' ? 'block' : 'none';
            document.getElementById('standings-table').style.display = view === 'standings' ? 'block' : 'none';
            document.getElementById('top-scorers-table').style.display = view === 'scorers' ? 'block' : 'none';

            document.getElementById('grid-view-btn').classList.toggle('active', view === 'grid');
            document.getElementById('table-view-btn').classList.toggle('active', view === 'table');
            document.getElementById('standings-view-btn').classList.toggle('active', view === 'standings');
            document.getElementById('scorers-view-btn').classList.toggle('active', view === 'scorers');

            currentView = view;
        }

        function selectFilter(filter) {
            activeFilters = filter;
            const options = document.querySelectorAll('.filter-option');
            options.forEach(opt => opt.classList.remove('selected'));
            document.querySelector(`.filter-option[data-filter="${filter}"]`).classList.add('selected');

            const customRange = document.querySelector('.custom-date-range');
            customRange.classList.toggle('active', filter === 'custom');

            if (filter !== 'custom') {
                updateUrl('<?php echo $selectedComp; ?>', filter);
            }
        }

        function updateUrl(competition, filter) {
            let url = `?competition=${competition}&filter=${filter}`;
            if (filter === 'custom') {
                const start = document.querySelector('input[name="start"]').value;
                const end = document.querySelector('input[name="end"]').value;
                if (start && end) {
                    url += `&start=${start}&end=${end}`;
                }
            }
            window.location.href = url;
        }

        function toggleHistory(button) {
            const card = button.closest('.match-card');
            const historyDiv = card.querySelector('.past-results');
            const index = card.dataset.index;
            const isVisible = historyDiv.style.display === 'block';

            if (!isVisible) {
                fetchTeamData(card.dataset.homeId, index, 'home');
                fetchTeamData(card.dataset.awayId, index, 'away');
            }
            historyDiv.style.display = isVisible ? 'none' : 'block';
            button.textContent = isVisible ? '👁️ View History' : '🙈 Hide History';
        }

        function fetchTeamData(teamId, index, teamType) {
            fetch(`?ajax=true&action=fetch_team_data&teamId=${teamId}&competition=<?php echo $selectedComp; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const historyDiv = document.getElementById(`history-${index}`);
                        const formDiv = document.getElementById(`form-${teamType}-${index}`);
                        const tableFormDiv = document.getElementById(`table-form-${teamType}-${index}`);

                        if (teamType === 'home') {
                            historyDiv.innerHTML = `<h4>${data.teamName} Recent Results</h4><ul>${data.results.map(r => `<li>${r}</li>`).join('')}</ul>`;
                            if (data.standings && Object.keys(data.standings).length > 0) {
                                historyDiv.innerHTML += `<div class="standings"><span>Pos: ${data.standings.position}</span><span>Points: ${data.standings.points}</span><span>GD: ${data.standings.goalDifference}</span></div>`;
                            }
                        } else {
                            historyDiv.innerHTML += `<h4>${data.teamName} Recent Results</h4><ul>${data.results.map(r => `<li>${r}</li>`).join('')}</ul>`;
                            if (data.standings && Object.keys(data.standings).length > 0) {
                                historyDiv.innerHTML += `<div class="standings"><span>Pos: ${data.standings.position}</span><span>Points: ${data.standings.points}</span><span>GD: ${data.standings.goalDifference}</span></div>`;
                            }
                        }

                        let formDisplay = data.form.padStart(6, '-');
                        formDisplay = formDisplay.split('').reverse().join('');
                        let formHtml = '';
                        for (let i = 0; i < 6; i++) {
                            const result = formDisplay[i];
                            const className = result === 'W' ? 'win' : (result === 'D' ? 'draw' : (result === 'L' ? 'loss' : 'empty'));
                            formHtml += `<span class="${className}${i === 5 && result !== '-' && data.form.length > 0 ? ' latest' : ''}">${result}</span>`;
                        }
                        formDiv.innerHTML = formHtml;
                        formDiv.classList.add('updated');
                        setTimeout(() => formDiv.classList.remove('updated'), 1000);

                        if (tableFormDiv) {
                            tableFormDiv.innerHTML = formHtml;
                            tableFormDiv.classList.add('updated');
                            setTimeout(() => tableFormDiv.classList.remove('updated'), 1000);
                        }
                    } else if (data.retry) {
                        retryFetch(teamId, index, teamType, data.retrySeconds, data.nextAttempt);
                    }
                })
                .catch(error => console.error('Error fetching team data:', error));
        }

        function retryFetch(teamId, index, teamType, retrySeconds, nextAttempt) {
            setTimeout(() => {
                fetch(`?ajax=true&action=fetch_team_data&teamId=${teamId}&competition=<?php echo $selectedComp; ?>&attempt=${nextAttempt}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            fetchTeamData(teamId, index, teamType);
                        } else if (data.retry) {
                            retryFetch(teamId, index, teamType, data.retrySeconds, data.nextAttempt);
                        }
                    });
            }, retrySeconds * 1000);
        }

        function updatePrediction(index) {
            const card = document.querySelector(`.match-card[data-index="${index}"]`);
            const homeId = card.dataset.homeId;
            const awayId = card.dataset.awayId;
            const status = card.dataset.status;
            const homeGoals = card.dataset.homeGoals || null;
            const awayGoals = card.dataset.awayGoals || null;

            fetch(`?ajax=true&action=predict_match&homeId=${homeId}&awayId=${awayId}&status=${status}&homeGoals=${homeGoals}&awayGoals=${awayGoals}&competition=<?php echo $selectedComp; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const predictionDiv = document.getElementById(`prediction-${index}`);
                        const tablePrediction = document.getElementById(`table-prediction-${index}`);
                        const tableConfidence = document.getElementById(`table-confidence-${index}`);
                        const tablePredictedScore = document.getElementById(`table-predicted-score-${index}`);

                        predictionDiv.innerHTML = `<p>${data.prediction} <span class="confidence">(${data.confidence})</span> ${data.resultIndicator}</p><p>Predicted: ${data.predictedScore}</p>`;
                        if (tablePrediction) tablePrediction.innerHTML = `${data.prediction} ${data.resultIndicator}`;
                        if (tableConfidence) tableConfidence.textContent = data.confidence;
                        if (tablePredictedScore) tablePredictedScore.textContent = data.predictedScore;

                        card.classList.toggle('draw-likely', data.advantage === 'Likely Draw');
                        card.querySelector('.home-team').classList.toggle('home-advantage', data.advantage === 'Home Advantage');
                        card.querySelector('.away-team').classList.toggle('away-advantage', data.advantage === 'Away Advantage');
                    } else if (data.retry) {
                        setTimeout(() => updatePrediction(index), data.retryInfo.home?.retrySeconds * 1000 || data.retryInfo.away?.retrySeconds * 1000);
                    }
                })
                .catch(error => console.error('Error updating prediction:', error));
        }

        document.querySelectorAll('.match-card').forEach(card => {
            const index = card.dataset.index;
            updatePrediction(index);
            fetchTeamData(card.dataset.homeId, index, 'home');
            fetchTeamData(card.dataset.awayId, index, 'away');
        });

        const searchInput = document.querySelector('.search-input');
        const autocompleteDropdown = document.querySelector('.autocomplete-dropdown');
        let debounceTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                autocompleteDropdown.innerHTML = '';
                autocompleteDropdown.parentElement.classList.remove('active');
                return;
            }

            debounceTimeout = setTimeout(() => {
                fetch(`?ajax=true&action=search_teams&query=${encodeURIComponent(query)}&competition=<?php echo $selectedComp; ?>`)
                    .then(response => response.json())
                    .then(teams => {
                        if (teams.length > 0) {
                            autocompleteDropdown.innerHTML = teams.map(team => `
                                <div class="autocomplete-item" data-team="${team.name}">
                                    ${team.crest ? `<img src="${team.crest}" alt="${team.name} crest">` : ''}
                                    ${team.name}
                                </div>
                            `).join('');
                            autocompleteDropdown.parentElement.classList.add('active');

                            document.querySelectorAll('.autocomplete-item').forEach(item => {
                                item.addEventListener('click', function() {
                                    searchInput.value = this.dataset.team;
                                    autocompleteDropdown.innerHTML = '';
                                    autocompleteDropdown.parentElement.classList.remove('active');
                                    window.location.href = `?competition=<?php echo $selectedComp; ?>&filter=<?php echo $filter; ?>&team=${encodeURIComponent(this.dataset.team)}`;
                                });
                            });
                        } else {
                            autocompleteDropdown.innerHTML = '<div class="autocomplete-item">No teams found</div>';
                            autocompleteDropdown.parentElement.classList.add('active');
                        }
                    })
                    .catch(error => console.error('Error searching teams:', error));
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !autocompleteDropdown.contains(e.target)) {
                autocompleteDropdown.parentElement.classList.remove('active');
            }
            if (!document.querySelector('.filter-container').contains(e.target)) {
                document.querySelector('.filter-dropdown').classList.remove('active');
            }
        });

        document.querySelector('.filter-dropdown-btn').addEventListener('click', function() {
            document.querySelector('.filter-dropdown').classList.toggle('active');
        });

        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            body.setAttribute('data-theme', newTheme);
            document.querySelector('.theme-icon').textContent = newTheme === 'dark' ? '🌙' : '☀️';
            localStorage.setItem('theme', newTheme);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            document.querySelector('.theme-icon').textContent = savedTheme === 'dark' ? '🌙' : '☀️';

            const savedView = localStorage.getItem('view') || 'grid';
            switchView(savedView);

            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', () => localStorage.setItem('view', currentView));
            });

            document.querySelector(`.filter-option[data-filter="${activeFilters}"]`)?.classList.add('selected');
            if (activeFilters === 'custom') {
                document.querySelector('.custom-date-range').classList.add('active');
            }
        });

        function shareTable(elementId, title) {
            const element = document.getElementById(elementId);
            html2canvas(element, { backgroundColor: document.body.getAttribute('data-theme') === 'dark' ? '#2c3e50' : '#f4f4f4' }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const link = document.createElement('a');
                link.href = imgData;
                link.download = `${title}_${new Date().toISOString().split('T')[0]}.png`;
                link.click();
            });
        }

        document.getElementById('share-table-btn').addEventListener('click', () => shareTable('match-table', 'Match_Predictions'));
        document.getElementById('share-standings-btn').addEventListener('click', () => shareTable('standings-table', 'League_Standings'));
        document.getElementById('share-scorers-btn').addEventListener('click', () => shareTable('top-scorers-table', 'Top_Scorers'));
    </script>
</body>
</html>
<?php
} catch (Exception $e) {
    handleError("An unexpected error occurred: " . $e->getMessage());
}
?>
<?php include 'back-to-top.php'; ?>
<script src="network-status.js"></script>
<script src="tab-title-switcher.js"></script>
<?php include 'global-footer.php'; ?>
    
