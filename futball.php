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

// Fetch data for the selected competition and date
$selected_competition = isset($_POST['competition']) ? $_POST['competition'] : null;
$selected_date = isset($_POST['dateFilter']) ? $_POST['dateFilter'] : 'today';

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
            max-width: 1200px;
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
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
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
        .action-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 4px;
            margin-right: 5px;
        }
        .action-button:hover {
            background-color: #0056b3;
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

            <button type="submit">Fetch Fixtures</button>
        </form>

        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST" && $fixtures_data) {
            echo "<div class='table-container'>
                    <h2>Fixtures</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Home Team</th>
                                <th>Away Team</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>";

            foreach ($fixtures_data['matches'] as $match) {
                $homeTeam = $match['homeTeam']['name'];
                $awayTeam = $match['awayTeam']['name'];
                $matchDate = date('Y-m-d H:i', strtotime($match['utcDate']));
                $status = $match['status'];

                echo "<tr>
                        <td>$homeTeam</td>
                        <td>$awayTeam</td>
                        <td>$matchDate</td>
                        <td>$status</td>
                        <td>
                            <button class='action-button' onclick='findSharedOpponents(\"$homeTeam\", \"$awayTeam\")'>Find Shared Opponents</button>
                            <button class='action-button' onclick='predictMatch(\"$homeTeam\", \"$awayTeam\")'>Predict</button>
                        </td>
                      </tr>";
            }

            echo "</tbody>
                  </table>
                  </div>";
        }
        ?>
    </div>

    <script>
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

        // Function to find shared opponents
        function findSharedOpponents(homeTeam, awayTeam) {
            alert(`Finding shared opponents for ${homeTeam} and ${awayTeam}`);
            // Add logic to find shared opponents here
        }

        // Function to predict match outcome
        function predictMatch(homeTeam, awayTeam) {
            alert(`Predicting match between ${homeTeam} and ${awayTeam}`);
            // Add logic to predict match outcome here
        }
    </script>
</body>
</html>
