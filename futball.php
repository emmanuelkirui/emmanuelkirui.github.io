<?php
session_start(); // Start the session

// Function to fetch data from the API
function fetchAPI($url, $api_key) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-Auth-Token: $api_key"
        ],
    ]);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Get the HTTP response code
    curl_close($curl);

    if ($http_code != 200) { // Check if the HTTP code is not OK (200)
        header('Location: error');
        exit;
    }

    return json_decode($response, true);
}

// Fetch all competitions only once and store in session
if (!isset($_SESSION['competitions'])) {
    $api_key = 'd2ef1a157a0d4c83ba4023d1fbd28b5c'; // Your API key
    $competitions_url = 'https://api.football-data.org/v4/competitions';
    $_SESSION['competitions'] = fetchAPI($competitions_url, $api_key)['competitions'];
}

// Fetch data for the selected competition
$selected_competition = isset($_POST['competition']) ? $_POST['competition'] : null;
if ($selected_competition) {
    $competition_id = $selected_competition;
    $fixtures_url = "https://api.football-data.org/v4/competitions/$competition_id/matches";
    $fixtures_data = fetchAPI($fixtures_url, $api_key);
} else {
    $fixtures_data = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Fixture Predictor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .add-opponent {
            margin-bottom: 15px;
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 16px;
        }
        .add-opponent:hover {
            background-color: #218838;
        }
        .result {
            margin-top: 20px;
            padding: 20px;
            background-color: #f4f4f4;
            border-radius: 5px;
        }
        .result h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .result p {
            font-size: 18px;
            color: #555;
        }
        .table-container {
            overflow-x: auto;
            margin-top: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .toggle-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            margin-top: 10px;
        }
        .toggle-button:hover {
            background-color: #0056b3;
        }
        .data-string {
            display: none;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Football Fixture Predictor</h1>
        <form method="post" id="predictionForm">
            <!-- Competition Selection Dropdown -->
            <div class="form-group">
                <label for="competition">Competition</label>
                <select id="competition" name="competition" required>
                    <option value="">Select a competition</option>
                    <?php
                    if (isset($_SESSION['competitions'])) {
                        foreach ($_SESSION['competitions'] as $competition) {
                            $selected = ($selected_competition == $competition['id']) ? 'selected' : '';
                            echo "<option value='{$competition['id']}' $selected>{$competition['name']}</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <!-- Team Selection Dropdowns -->
            <div class="form-group">
                <label for="team1">Team 1</label>
                <select id="team1" name="team1" required>
                    <option value="">Select Team 1</option>
                    <?php
                    if ($fixtures_data) {
                        $teams = [];
                        foreach ($fixtures_data['matches'] as $match) {
                            $teams[$match['homeTeam']['id']] = $match['homeTeam']['name'];
                            $teams[$match['awayTeam']['id']] = $match['awayTeam']['name'];
                        }
                        foreach ($teams as $id => $name) {
                            echo "<option value='$id'>$name</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="team2">Team 2</label>
                <select id="team2" name="team2" required>
                    <option value="">Select Team 2</option>
                    <?php
                    if ($fixtures_data) {
                        foreach ($teams as $id => $name) {
                            echo "<option value='$id'>$name</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <!-- Date Filters -->
            <div class="form-group">
                <label for="dateFilter">Date Filter</label>
                <select id="dateFilter" name="dateFilter" required onchange="updateDateRange()">
                    <option value="yesterday">Yesterday</option>
                    <option value="today" selected>Today</option>
                    <option value="tomorrow">Tomorrow</option>
                    <option value="custom">Custom</option>
                </select>
            </div>

            <!-- Custom Date Range -->
            <div class="form-group" id="customDateRange" style="display: none;">
                <label for="dateFrom">Date From</label>
                <input type="date" id="dateFrom" name="dateFrom">
                <label for="dateTo">Date To</label>
                <input type="date" id="dateTo" name="dateTo">
            </div>

            <!-- Button to Find Opponents -->
            <button type="button" class="add-opponent" onclick="fetchFixturesAndTeams()">Find Fixtures and Opponents</button>
            <button type="submit">Predict</button>
        </form>

        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $team1 = $_POST['team1'];
            $team2 = $_POST['team2'];

            // Simple prediction logic
            $prediction = "The match between $team1 and $team2 is predicted to be a draw."; // Default prediction

            echo "<div class='result'>
                    <h2>Prediction Result</h2>
                    <p>$prediction</p>
                  </div>";
        }
        ?>
    </div>

    <script>
        const apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c'; // Your API key

        // Function to update date range based on selected filter
        function updateDateRange() {
            const dateFilter = document.getElementById('dateFilter').value;
            const customDateRange = document.getElementById('customDateRange');

            if (dateFilter === 'custom') {
                customDateRange.style.display = 'block';
            } else {
                customDateRange.style.display = 'none';
            }
        }

        // Function to fetch fixtures and teams
        async function fetchFixturesAndTeams() {
            const competitionId = document.getElementById('competition').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            let dateRange = '';
            switch (dateFilter) {
                case 'yesterday':
                    const yesterday = new Date();
                    yesterday.setDate(yesterday.getDate() - 1);
                    dateRange = `dateFrom=${yesterday.toISOString().split('T')[0]}&dateTo=${yesterday.toISOString().split('T')[0]}`;
                    break;
                case 'today':
                    const today = new Date();
                    dateRange = `dateFrom=${today.toISOString().split('T')[0]}&dateTo=${today.toISOString().split('T')[0]}`;
                    break;
                case 'tomorrow':
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    dateRange = `dateFrom=${tomorrow.toISOString().split('T')[0]}&dateTo=${tomorrow.toISOString().split('T')[0]}`;
                    break;
                case 'custom':
                    if (!dateFrom || !dateTo) {
                        alert('Please select a date range.');
                        return;
                    }
                    dateRange = `dateFrom=${dateFrom}&dateTo=${dateTo}`;
                    break;
                default:
                    alert('Invalid date filter.');
                    return;
            }

            try {
                // Fetch fixtures for the selected competition and date range
                const fixturesResponse = await fetch(
                    `https://api.football-data.org/v4/competitions/${competitionId}/matches?${dateRange}`,
                    {
                        headers: {
                            'X-Auth-Token': apiKey
                        }
                    }
                );

                if (!fixturesResponse.ok) {
                    throw new Error(`HTTP error! Status: ${fixturesResponse.status}`);
                }

                const fixturesData = await fixturesResponse.json();

                // Extract unique teams from fixtures
                const teams = new Set();
                fixturesData.matches.forEach(match => {
                    teams.add(match.homeTeam.name);
                    teams.add(match.awayTeam.name);
                });

                // Populate team dropdowns
                const team1Dropdown = document.getElementById('team1');
                const team2Dropdown = document.getElementById('team2');
                team1Dropdown.innerHTML = '<option value="">Select Team 1</option>';
                team2Dropdown.innerHTML = '<option value="">Select Team 2</option>';

                teams.forEach(team => {
                    const option1 = document.createElement('option');
                    option1.value = team;
                    option1.textContent = team;
                    team1Dropdown.appendChild(option1);

                    const option2 = document.createElement('option');
                    option2.value = team;
                    option2.textContent = team;
                    team2Dropdown.appendChild(option2);
                });
            } catch (error) {
                console.error('Error fetching fixtures and teams:', error);
                alert('Failed to fetch fixtures and teams. Check the console for details.');
            }
        }
    </script>
</body>
</html>
