<?php
// Load configuration file
$config = parse_ini_file('.env');

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
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true; // Enable pretty-printing

// Create root <rss> element
$rss = $xml->createElement('rss');
$rss->setAttribute('version', '2.0');
$rss->setAttribute('xmlns:geo', 'http://www.w3.org/2003/01/geo/wgs84_pos#');
$xml->appendChild($rss);

// Create <channel> element
$channel = $xml->createElement('channel');
$rss->appendChild($channel);

// Add <title>, <link>, and <description> to <channel>
$channel->appendChild($xml->createElement('title', 'Twin Cities RSS Feed'));
$channel->appendChild($xml->createElement('link', 'http://localhost/twincities/rss_feed.php'));
$channel->appendChild($xml->createElement('description', 'Latest city and place updates'));

// Fetch all cities
$city_query = "SELECT cityID, cityName, cityCountry, Population, Longitude, Latitude, Currency, funFact FROM city";
$city_result = $conn->query($city_query);

while ($city = $city_result->fetch_assoc()) {
    // Create <item> for city
    $item = $xml->createElement('item');
    $channel->appendChild($item);
    
    $item->appendChild($xml->createElement('title', "{$city['cityName']}, {$city['cityCountry']}"));
    $item->appendChild($xml->createElement('description', "Population: {$city['Population']}, Currency: {$city['Currency']}, Fun Fact: {$city['funFact']}"));
    
    $lat = $xml->createElement('geo:lat', $city['Latitude']);
    $long = $xml->createElement('geo:long', $city['Longitude']);
    $item->appendChild($lat);
    $item->appendChild($long);

    // Fetch places of interest for this city
    $place_query = "SELECT PlaceName, PlaceType, Capacity, Longitude, Latitude, YearEstablished, HoursOfOperation, poi_URL FROM placeofinterest WHERE cityID = ?";
    $stmt = $conn->prepare($place_query);
    $stmt->bind_param("i", $city['cityID']);
    $stmt->execute();
    $place_result = $stmt->get_result();

    while ($place = $place_result->fetch_assoc()) {
        // Create <item> for place of interest
        $place_item = $xml->createElement('item');
        $channel->appendChild($place_item);

        $place_item->appendChild($xml->createElement('title', "{$place['PlaceName']} - {$city['cityName']}"));
        $place_item->appendChild($xml->createElement('description', "Type: {$place['PlaceType']}, Capacity: {$place['Capacity']}, Established: {$place['YearEstablished']}, Hours: {$place['HoursOfOperation']}"));
        
        $place_lat = $xml->createElement('geo:lat', $place['Latitude']);
        $place_long = $xml->createElement('geo:long', $place['Longitude']);
        $place_item->appendChild($place_lat);
        $place_item->appendChild($place_long);
        
        $place_item->appendChild($xml->createElement('link', $place['poi_URL']));

    }
    $stmt->close();
}

// Output formatted XML
echo $xml->saveXML();

$conn->close();
?>
