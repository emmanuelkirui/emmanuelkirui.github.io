<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$apiKey = "d2ef1a157a0d4c83ba4023d1fbd28b5c";
$apiBaseUrl = "https://api.football-data.org/v4/";

// Function to fetch API data
function apiRequest($endpoint) {
    global $apiKey, $apiBaseUrl;
    $url = $apiBaseUrl . $endpoint;

    $headers = [
        "X-Auth-Token: $apiKey"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ["error" => "API failed with HTTP code $httpCode"];
    }

    return json_decode($response, true);
}

// Handle AJAX request
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    ob_clean();

    if ($_GET['action'] == "getLeagues") {
    $data = apiRequest("competitions");
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode($data);
    exit;
}

    if ($_GET['action'] == "getMatches") {
        $league = $_GET['league'] ?? 'PL'; // Default to Premier League
        $dateFilter = $_GET['dateFilter'] ?? 'today';

        // Determine date range
        $today = date("Y-m-d");
        $tomorrow = date("Y-m-d", strtotime("+1 day"));
        $yesterday = date("Y-m-d", strtotime("-1 day"));

        if ($dateFilter == "today") {
            $dateFrom = $dateTo = $today;
        } elseif ($dateFilter == "tomorrow") {
            $dateFrom = $dateTo = $tomorrow;
        } elseif ($dateFilter == "yesterday") {
            $dateFrom = $dateTo = $yesterday;
        } elseif ($dateFilter == "custom") {
            $dateFrom = $_GET['fromDate'] ?? $today;
            $dateTo = $_GET['toDate'] ?? $today;
        } else {
            $dateFrom = $dateTo = $today;
        }

        $data = apiRequest("matches?competitions=$league&dateFrom=$dateFrom&dateTo=$dateTo");
        echo json_encode($data['matches'] ?? []);
        exit;
    }

    echo json_encode(["error" => "Invalid action"]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Match Predictor</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; text-align: center; padding: 20px; }
        .container { width: 90%; max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0px 0px 10px rgba(0,0,0,0.1); }
        select, input, button { width: 100%; padding: 10px; margin: 10px 0; font-size: 16px; }
        button { background: #28a745; color: white; border: none; cursor: pointer; }
        button:hover { background: #218838; }
        .error { color: red; }
    </style>
</head>
<body>

<div class="container">
    <h2>Football Match Predictor</h2>
    
    <label for="league">Select League:</label>
    <select id="league"></select>

    <label for="dateFilter">Select Date:</label>
    <select id="dateFilter">
        <option value="today" selected>Today</option>
        <option value="yesterday">Yesterday</option>
        <option value="tomorrow">Tomorrow</option>
        <option value="custom">Custom Range</option>
    </select>

    <div id="customDateRange" style="display: none;">
        <input type="date" id="fromDate">
        <input type="date" id="toDate">
    </div>

    <button onclick="fetchMatches()">Get Matches</button>

    <h3>Upcoming Matches</h3>
    <div id="matches"></div>

    <p class="error" id="error"></p>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    loadLeagues();
    document.getElementById("dateFilter").addEventListener("change", function() {
        document.getElementById("customDateRange").style.display = this.value === "custom" ? "block" : "none";
    });
});

function loadLeagues() {
    fetch("futbol.php?action=getLeagues")
    .then(res => res.json())
    .then(data => {
        let leagueSelect = document.getElementById("league");
        leagueSelect.innerHTML = "";
        data.forEach(league => {
            let option = document.createElement("option");
            option.value = league.code;
            option.textContent = league.name;
            leagueSelect.appendChild(option);
        });
        leagueSelect.value = "PL"; // Default to Premier League
    })
    .catch(err => {
        document.getElementById("error").textContent = "Failed to load leagues.";
    });
}

function fetchMatches() {
    let league = document.getElementById("league").value;
    let dateFilter = document.getElementById("dateFilter").value;
    let fromDate = document.getElementById("fromDate").value;
    let toDate = document.getElementById("toDate").value;

    let url = `futbol.php?action=getMatches&league=${league}&dateFilter=${dateFilter}`;
    if (dateFilter === "custom") {
        url += `&fromDate=${fromDate}&toDate=${toDate}`;
    }

    fetch(url)
    .then(res => res.json())
    .then(data => {
        let matchesDiv = document.getElementById("matches");
        matchesDiv.innerHTML = "";
        if (data.length === 0) {
            matchesDiv.innerHTML = "<p>No matches found.</p>";
            return;
        }

        data.forEach(match => {
            let matchElement = document.createElement("p");
            matchElement.textContent = `${match.homeTeam.name} vs ${match.awayTeam.name} - ${match.utcDate}`;
            matchesDiv.appendChild(matchElement);
        });
    })
    .catch(err => {
        document.getElementById("error").textContent = "Failed to fetch matches.";
    });
}
</script>

</body>
</html>
