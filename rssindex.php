<?php
// Load environment variables
function loadEnv($file)
{
    if (!file_exists($file)) {
        die(".env file not found!");
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, ' "');
        $_ENV[$name] = $value;
    }
}

loadEnv(__DIR__ . '/.env');

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

// Fetch cities from the database
$cities = [];
$city_query = "SELECT cityID, cityName, cityCountry, Latitude, Longitude, Population, Currency, funFact FROM city";
$city_result = $conn->query($city_query);

while ($city = $city_result->fetch_assoc()) {
    $cityID = $city['cityID'];
    $cities[$cityID] = [
        "name" => "{$city['cityName']}, {$city['cityCountry']}",
        "lat" => $city['Latitude'],
        "lon" => $city['Longitude'],
        "population" => $city['Population'],
        "currency" => $city['Currency'],
        "funFact" => $city['funFact'],
        "points_of_interest" => []
    ];

    // Fetch points of interest for this city with detailed information
    $poi_query = "SELECT poi.PlaceName, poi.Latitude, poi.Longitude, poi.PlaceType, poi.Capacity, poi.YearEstablished, poi.HoursOfOperation
                  FROM placeofinterest poi
                  WHERE poi.cityID = $cityID";

    $poi_result = $conn->query($poi_query);

    while ($poi = $poi_result->fetch_assoc()) {
        // Fetch the Flickr image URL for this point of interest
        $imageUrl = fetchFlickrImage($poi['PlaceName'], $_ENV['flickr_api_key']);

        $cities[$cityID]["points_of_interest"][] = [
            "name" => $poi['PlaceName'],
            "lat" => $poi['Latitude'],
            "lon" => $poi['Longitude'],
            "image_url" => $imageUrl, // Store the image URL here
            "place_type" => $poi['PlaceType'],
            "capacity" => $poi['Capacity'],
            "year_established" => $poi['YearEstablished'],
            "hours_of_operation" => $poi['HoursOfOperation']
        ];
    }
}

$conn->close();

// Get selected city
$selectedCity = $_GET['city'] ?? array_key_first($cities);
if (!isset($cities[$selectedCity])) {
    $selectedCity = array_key_first($cities);
}

$cityData = $cities[$selectedCity];
$cityDataJSON = json_encode($cityData);

$mapToken = $_ENV['map_token'] ?? null;
$weatherToken = $_ENV['weather_token'] ?? null;

// Function to fetch image URL from Flickr
function fetchFlickrImage($placeName, $flickrApiKey) {
    // Use the name of the place as the search term
    $searchTerm = urlencode($placeName);
    $url = "https://api.flickr.com/services/rest/?method=flickr.photos.search&api_key={$flickrApiKey}&text={$searchTerm}&format=json&nojsoncallback=1&per_page=1";

    // Fetch and decode the JSON response
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    // Check if any photos are returned
    if (isset($data['photos']['photo'][0])) {
        $photo = $data['photos']['photo'][0];
        $photoUrl = "https://farm{$photo['farm']}.staticflickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}_b.jpg";
        return $photoUrl;
    }

    // Return a default image if no photo is found
    return 'path/to/default/image.jpg'; // Replace with your default city image URL
}
?>

<html>
<head>
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.3.1/mapbox-gl.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.3.1/mapbox-gl.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />

    <h1>TWIN CITIES</h1>

    <section id="navigation">
        <nav>
            <ul>
                <?php foreach ($cities as $id => $city): ?>
                    <li><a href="?city=<?php echo $id; ?>"><?php echo $city['name']; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </section>
</head>

<body>

<h2>Currently Viewing: <?php echo $cityData['name']; ?></h2>

<div id="map"></div>
<div id="weather-box">Loading weather...</div>

<div>
    <p><strong>Population:</strong> <?php echo $cityData['population']; ?></p>
    <p><strong>Currency:</strong> <?php echo $cityData['currency']; ?></p>
    <p><strong>Fun Fact:</strong> <?php echo $cityData['funFact']; ?></p>
</div>

<!-- Container to display detailed information about the selected POI -->
<div id="poi-details">
    <h3>Details:</h3>
    <p><strong>Place Type:</strong> <span id="poi-place-type"></span></p>
    <p><strong>Capacity:</strong> <span id="poi-capacity"></span></p>
    <p><strong>Year Established:</strong> <span id="poi-year-established"></span></p>
    <p><strong>Hours of Operation:</strong> <span id="poi-hours-of-operation"></span></p>
</div>

<script>
    const cityData = <?php echo $cityDataJSON; ?>;
    const weatherToken = '<?php echo $weatherToken; ?>';

    // Function to display detailed information when a POI is clicked
    function displayPoiDetails(poi) {
        document.getElementById("poi-place-type").textContent = poi.place_type;
        document.getElementById("poi-capacity").textContent = poi.capacity;
        document.getElementById("poi-year-established").textContent = poi.year_established;
        document.getElementById("poi-hours-of-operation").textContent = poi.hours_of_operation;
    }

    mapboxgl.accessToken = '<?php echo $mapToken; ?>';
    const map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/streets-v11',
        center: [cityData.lon, cityData.lat],
        zoom: 10
    });

    new mapboxgl.Marker()
        .setLngLat([cityData.lon, cityData.lat])
        .setPopup(new mapboxgl.Popup({ offset: 25 }).setText(cityData.name))
        .addTo(map);

    cityData.points_of_interest.forEach(poi => {
    const marker = new mapboxgl.Marker({ color: 'red' })
        .setLngLat([poi.lon, poi.lat])
        .setPopup(new mapboxgl.Popup({ offset: 25 }).setHTML(
            `<h4>${poi.name}</h4>
            <img src="${poi.image_url}" alt="${poi.name}" style="width: 100%;"/>`
        ))
        .addTo(map);

    // Add click event to update POI details section
    marker.getElement().addEventListener('click', () => {
        displayPoiDetails(poi);
    });
});

    const apiEndpoint = `https://api.openweathermap.org/data/2.5/forecast?lat=${cityData.lat}&lon=${cityData.lon}&appid=${weatherToken}`;
    fetch(apiEndpoint)
        .then(response => response.json())
        .then(data => {
            const { city, list } = data;
            let weatherContent = `<h3>Weather in ${city.name}</h3><div class="forecast"><h4>5-Day Forecast:</h4>`;
            const dailyForecasts = {};

            list.forEach(entry => {
                const date = new Date(entry.dt * 1000).toISOString().split('T')[0];
                if (!dailyForecasts[date]) {
                    dailyForecasts[date] = entry;
                }
            });

            Object.values(dailyForecasts).slice(0, 5).forEach(entry => {
                const date = new Date(entry.dt * 1000).toLocaleDateString('en-GB', { weekday: 'long', month: 'short', day: 'numeric' });
                const temp = (entry.main.temp - 273.15).toFixed(2);
                const description = entry.weather[0].description;
                weatherContent += `<div class="forecast-entry"><p><strong>${date}:</strong> ${temp} °C, ${description}</p></div>`;
            });

            weatherContent += '</div>';
            document.getElementById("weather-box").innerHTML = weatherContent;
        })
        .catch(error => {
            document.getElementById("weather-box").innerHTML = `<p>Error fetching weather data</p>`;
            console.error("Error fetching weather data:", error);
        });
</script>

</body>
</html>
