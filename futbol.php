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
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .opponent-group {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            background-color: #f4f4f4;
            border-radius: 5px;
        }
        .add-opponent {
            margin-bottom: 15px;
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 5px;
        }
        .add-opponent:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Football Fixture Predictor</h1>
        <form method="post" id="predictionForm">
            <div class="form-group">
                <label for="team1">Team 1</label>
                <input type="text" id="team1" name="team1" required>
            </div>
            <div class="form-group">
                <label for="team2">Team 2</label>
                <input type="text" id="team2" name="team2" required>
            </div>

            <!-- Dynamic Opponent Inputs -->
            <div id="opponentsContainer">
                <div class="opponent-group">
                    <div class="form-group">
                        <label for="opponent1">Shared Opponent</label>
                        <input type="text" name="opponents[]" required>
                    </div>
                    <div class="form-group">
                        <label for="score1">Score (Team 1 vs Opponent, e.g., 2-0)</label>
                        <input type="text" name="scores[]" placeholder="e.g., 2-0" required>
                    </div>
                    <div class="form-group">
                        <label for="location1">Team 1 Played</label>
                        <select name="locations[]" required>
                            <option value="home">Home</option>
                            <option value="away">Away</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="score2">Score (Team 2 vs Opponent, e.g., 1-1)</label>
                        <input type="text" name="scores[]" placeholder="e.g., 1-1" required>
                    </div>
                    <div class="form-group">
                        <label for="location2">Team 2 Played</label>
                        <select name="locations[]" required>
                            <option value="home">Home</option>
                            <option value="away">Away</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="button" class="add-opponent" onclick="addOpponent()">Add Another Opponent</button>
            <button type="submit">Predict</button>
        </form>

        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $team1 = $_POST['team1'];
            $team2 = $_POST['team2'];
            $opponents = $_POST['opponents'];
            $scores = $_POST['scores'];
            $locations = $_POST['locations'];

            // Combine data into a string (e.g., JSON)
            $data = [
                'team1' => $team1,
                'team2' => $team2,
                'opponents' => $opponents,
                'scores' => $scores,
                'locations' => $locations,
            ];
            $dataString = json_encode($data, JSON_PRETTY_PRINT);

            // Parse scores and calculate totals
            $team1_total_goals = 0;
            $team2_total_goals = 0;

            for ($i = 0; $i < count($scores); $i += 2) {
                // Team 1's score vs opponent
                list($team1_goals, $opponent_goals) = explode('-', $scores[$i]);
                $team1_total_goals += (int)$team1_goals;

                // Team 2's score vs opponent
                list($opponent_goals, $team2_goals) = explode('-', $scores[$i + 1]);
                $team2_total_goals += (int)$team2_goals;
            }

            // Simple prediction logic
            if ($team1_total_goals > $team2_total_goals) {
                $prediction = "$team1 is predicted to win against $team2.";
            } elseif ($team1_total_goals < $team2_total_goals) {
                $prediction = "$team2 is predicted to win against $team1.";
            } else {
                $prediction = "The match between $team1 and $team2 is predicted to be a draw.";
            }

            echo "<div class='result'>
                    <h2>Prediction Result</h2>
                    <p>$prediction</p>
                    <h3>Data Saved as String:</h3>
                    <pre>" . htmlspecialchars($dataString) . "</pre>
                  </div>";
        }
        ?>

        <script>
            let opponentCount = 1;

            function addOpponent() {
                opponentCount++;
                const container = document.getElementById('opponentsContainer');
                const newOpponentGroup = document.createElement('div');
                newOpponentGroup.className = 'opponent-group';
                newOpponentGroup.innerHTML = `
                    <div class="form-group">
                        <label for="opponent${opponentCount}">Shared Opponent</label>
                        <input type="text" name="opponents[]" required>
                    </div>
                    <div class="form-group">
                        <label for="score${opponentCount}">Score (Team 1 vs Opponent, e.g., 2-0)</label>
                        <input type="text" name="scores[]" placeholder="e.g., 2-0" required>
                    </div>
                    <div class="form-group">
                        <label for="location${opponentCount}">Team 1 Played</label>
                        <select name="locations[]" required>
                            <option value="home">Home</option>
                            <option value="away">Away</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="score${opponentCount}">Score (Team 2 vs Opponent, e.g., 1-1)</label>
                        <input type="text" name="scores[]" placeholder="e.g., 1-1" required>
                    </div>
                    <div class="form-group">
                        <label for="location${opponentCount}">Team 2 Played</label>
                        <select name="locations[]" required>
                            <option value="home">Home</option>
                            <option value="away">Away</option>
                        </select>
                    </div>
                `;
                container.appendChild(newOpponentGroup);
            }
        </script>
    </div>
</body>
</html>
