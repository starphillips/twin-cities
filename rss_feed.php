<?php
// Load configuration file
$config = parse_ini_file('env.txt');

// Database connection
$host = $config['db_host'];
$username = $config['db_username'];
$password = $config['db_password'];
$database = $config['db_database'];

$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set the correct header for RSS
header("Content-Type: application/rss+xml; charset=UTF-8");

// Start XML structure
echo "<?xml version='1.0' encoding='UTF-8'?>";
echo "<rss version='2.0'>";
echo "<channel>";
echo "<title>Twin Cities RSS Feed</title>";
echo "<link>http://localhost/twincities/rss_feed.php</link>";
echo "<description>Latest city and place updates</description>";

// Fetch all cities
$city_query = "SELECT cityID, cityName, cityCountry, Population, Currency, funFact FROM city";
$city_result = $conn->query($city_query);

while ($city = $city_result->fetch_assoc()) {
    echo "<item>";
    echo "<title>{$city['cityName']}, {$city['cityCountry']}</title>";
    echo "<description>Population: {$city['Population']}, Currency: {$city['Currency']}, Fun Fact: {$city['funFact']}</description>";
    echo "<link>http://localhost/twincities/city.php?cityID={$city['cityID']}</link>";
    echo "</item>";

    // Fetch places of interest for this city
    $place_query = "SELECT PlaceName, PlaceType, YearEstablished, HoursOfOperation FROM placeofinterest WHERE cityID = {$city['cityID']}";
    $place_result = $conn->query($place_query);

    while ($place = $place_result->fetch_assoc()) {
        echo "<item>";
        echo "<title>{$place['PlaceName']} - {$city['cityName']}</title>";
        echo "<description>Type: {$place['PlaceType']}, Established: {$place['YearEstablished']}, Hours: {$place['HoursOfOperation']}</description>";
        echo "<link>http://localhost/twincities/place.php?placeName=" . urlencode($place['PlaceName']) . "</link>";
        echo "</item>";
    }
}

// Close XML structure
echo "</channel>";
echo "</rss>";

$conn->close();
?>
