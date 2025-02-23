<?php
$api_key = "f8be56e9365110d1887b69f11f3db11c"; // Replace with your actual API key

// Fetch leagues dynamically
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://v3.football.api-sports.io/leagues?current=true",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array("x-apisports-key: $api_key"),
));
$response = curl_exec($curl);
curl_close($curl);

$leagues = json_decode($response, true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Matches Accumulator</title>
</head>
<body>

<h2>Select a League to View Matches</h2>

<!-- League Selection Form -->
<form method="POST" action="">
    <label for="league">Select League:</label>
    <select name="league" id="league" required>
        <?php
        if (isset($leagues['response'])) {
            foreach ($leagues['response'] as $league) {
                echo "<option value='" . $league['league']['id'] . "'>" . $league['league']['name'] . "</option>";
            }
        } else {
            echo "<option>No leagues available</option>";
        }
        ?>
    </select>
    <input type="submit" value="Show Matches">
</form>

<?php
// If a league is selected, fetch matches
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['league'])) {
    $selected_league_id = $_POST['league'];

    // Fetch matches for the selected league
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://v3.football.api-sports.io/fixtures?league=$selected_league_id&season=2023", // Adjust season as needed
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array("x-apisports-key: $api_key"),
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    $matches = json_decode($response, true);

    // Display matches
    echo "<h2>Upcoming Matches</h2>";
    if (isset($matches['response']) && count($matches['response']) > 0) {
        echo "<ul>";
        foreach ($matches['response'] as $match) {
            echo "<li>" . $match['teams']['home']['name'] . " vs " . $match['teams']['away']['name'] . " - " . date("d M Y, H:i", strtotime($match['fixture']['date'])) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No matches found for the selected league.</p>";
    }
}
?>

</body>
</html>
