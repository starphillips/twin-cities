<?php
// Load environment variables
function loadEnv($file)
{

    if (!file_exists($file)) {
        die(".env file not found!"); // Terminate script if it cannot load the file
    }

    // Function then reads entire file into an array, each element is a line from the file
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
    foreach ($lines as $line) {                                                           
        if (strpos(trim($line), '#') === 0) continue;           
        list($name, $value) = explode('=', $line, 2);           
        $name = trim($name);                                                      
        $value = trim($value, ' "');                                  
        $_ENV[$name] = $value;                                                           
    }
}

loadEnv(__DIR__ . '/.env'); // Calls the function and passes our config file through


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

    // Fetch points of interest for this city
    $poi_query = "SELECT poi.PlaceName, poi.Latitude, poi.Longitude, poi.PlaceType, poi.Capacity, poi.YearEstablished, poi.HoursOfOperation, poi.poi_url
                  FROM placeofinterest poi    
                  WHERE poi.cityID = $cityID"; // Filters to only obtain poi from the selected city, placeofinterest shortened to poi for ease 

    $poi_result = $conn->query($poi_query); 

    while ($poi = $poi_result->fetch_assoc()) { 
        $cities[$cityID]["points_of_interest"][] = [
            "name" => $poi['PlaceName'],
            "lat" => $poi['Latitude'],
            "lon" => $poi['Longitude'],
            "place_type" => $poi['PlaceType'],
            "capacity" => $poi['Capacity'],
            "year_established" => $poi['YearEstablished'],
            "hours_of_operation" => $poi['HoursOfOperation'],
            "poi_url" => $poi['poi_url']
        ];
    }
}

$conn->close();

// Get selected city
if (isset($_GET['city'])) {
    $selectedCity = $_GET['city'];
} else {
    $selectedCity = array_key_first($cities);
}
if (!isset($cities[$selectedCity])) { 
    $selectedCity = array_key_first($cities);
}

$cityData = $cities[$selectedCity];
$cityDataJSON = json_encode($cityData);

$mapToken = isset($_ENV['map_token']) ? $_ENV['map_token'] : null;
$weatherToken = isset($_ENV['weather_token']) ? $_ENV['weather_token'] : null;
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
            <?php
            foreach ($cities as $id => $city) {
                echo '<li><a href="?city=' . $id . '">' . $city['name'] . '</a></li>';
            }
            ?>
        </ul>
    </nav>
</section>
</head>

<body>

<h2>Currently Viewing: <?php echo $cityData['name']; ?></h2> 

<div id="map"></div> <!-- Map location which will be populated by JavaScript later -->
<div id="weather-box">Loading weather...</div> <!-- Same for weather -->

<!-- Takes data from PHP, to populate this based on what city -->
<div>
    <p><strong>Population:</strong> <?php echo $cityData['population']; ?></p> 
    <p><strong>Currency:</strong> <?php echo $cityData['currency']; ?></p>
    <p><strong>Fun Fact:</strong> <?php echo $cityData['funFact']; ?></p>
</div>

<!-- Container for POI details - JavaScript will populate this based on when the user clicks on a POI -->
<div id="poi-details">
    <h3>Place of Interest Details:</h3>
    <p><strong>Place of Interest:</strong> <span id="poi-name"></span></p>
    <p><strong>Place Type:</strong> <span id="poi-place-type"></span></p>
    <p><strong>Capacity:</strong> <span id="poi-capacity"></span></p>
    <p><strong>Year Established:</strong> <span id="poi-year-established"></span></p>
    <p><strong>Hours of Operation:</strong> <span id="poi-hours-of-operation"></span></p>
</div>

<!-- Placeholder for the image - JavaScript will populate this based on when the user clicks on a POI -->
<div id="poi-image-box" style="width: 300px; height: 200px; border: 1px solid #ddd; display: inline-block; margin-left: 20px;">
    <img id="poi-image" src="path/to/placeholder-image.jpg" alt="Place of interest image" style="width: 100%; height: 100%; object-fit: cover;" />
</div>

<script>
    const cityData = <?php echo $cityDataJSON; ?>;          // Takes PHP variables and embeds them into JavaScript for this data to be used in the front end
    const weatherToken = '<?php echo $weatherToken; ?>';    // As above

    function fetchCityImage(cityName) {
        const cityImage = document.getElementById("poi-image"); // Selects the ID for the image, so we can update the image with the POI image
        cityImage.src = "./footerimgs/spinner.gif";

        fetch(`fetch_image.php?place=${encodeURIComponent(cityName)}`) // Sends a request to fetch_image.php taking the city name so its for the correct one
            .then(response => response.json())
            .then(data => {
                cityImage.src = data.image_url;
            })
            .catch(() => {
                cityImage.src = "./footerimgs/default.jpg"; // If it cannot connect to FlickrAPI, it will return a default image
            });
    }

    function displayPoiDetails(poi) {
        document.getElementById("poi-name").textContent = poi.name;
        document.getElementById("poi-place-type").textContent = poi.place_type;
        document.getElementById("poi-capacity").textContent = poi.capacity;
        document.getElementById("poi-year-established").textContent = poi.year_established;
        document.getElementById("poi-hours-of-operation").textContent = poi.hours_of_operation;

        fetchCityImage(poi.name); // Calls to function to get image from Flickr API that will return image based on POI name
    }

    function hidePoiDetails() { // After hover it will hide the POI details
        document.getElementById("poi-name").textContent = '';
        document.getElementById("poi-place-type").textContent = '';
        document.getElementById("poi-capacity").textContent = '';
        document.getElementById("poi-year-established").textContent = '';
        document.getElementById("poi-hours-of-operation").textContent = '';
        fetchCityImage(cityData.name); // Will default to city image found on Flickr API
    }

    // Load city image on page load
    fetchCityImage(cityData.name);

    mapboxgl.accessToken = '<?php echo $mapToken; ?>'; // Retrieves the API token from the PHP
    const map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/streets-v11',
        center: [cityData.lon, cityData.lat],
        zoom: 12
    });

    new mapboxgl.Marker() // Creates mapbox marker
        .setLngLat([cityData.lon, cityData.lat]) // Marker is placed based on lon and lat retrieved from PHP
        .setPopup(new mapboxgl.Popup({ offset: 25 }).setText(cityData.name))
        .addTo(map);

    cityData.points_of_interest.forEach(poi => { 
        const marker = new mapboxgl.Marker({ color: 'red' }) // Creates marker for each POI
            .setLngLat([poi.lon, poi.lat])
            .setPopup(new mapboxgl.Popup({ offset: 25 }).setHTML(`<h4>${poi.name}</h4>`)) // Retrieves POI name from PHP
            .addTo(map);

            marker.getElement().addEventListener('mouseenter', () => { // Trigger on mouse hover
                displayPoiDetails(poi);  // Show POI details
            });

            marker.getElement().addEventListener('mouseleave', () => { // Trigger when mouse leaves the marker
                hidePoiDetails(); // Hide POI details (function to clear or hide details)
            });

            marker.getElement().addEventListener('click', () => { 
                if (poi.poi_url && poi.poi_url.trim() !== "") { // Ensure it exists, trims any spcaes and checks that it is not empty
                    window.open(poi.poi_url, '_blank'); // Open the POI's stored URL in a new tab
                } else {
                    alert("No URL available for this place.");
                }

            });


        
    });

    // Fetch weather data
    const weatherAPI = `https://api.openweathermap.org/data/2.5/forecast?lat=${cityData.lat}&lon=${cityData.lon}&appid=${weatherToken}`;
    fetch(weatherAPI)
        .then(response => response.json()) // If promise is returned, will convert response to JSON object
        .then(data => {
            const { city, list } = data; // Destructuring - extracts the city and list properties from the response and stores in separate values
            let weatherContent = `<h3>Weather in ${city.name}</h3><div class="forecast"><h4>5-Day Forecast:</h4>`; // String for HTML to display weather content
            const dailyForecasts = {}; // Initialises empty object to store the daily weather forecast

            list.forEach(entry => {
                const date = new Date(entry.dt * 1000).toISOString().split('T')[0]; 
                if (!dailyForecasts[date]) {
                    dailyForecasts[date] = entry;
                }
            });

            Object.values(dailyForecasts).slice(0, 5).forEach(entry => {
                const date = new Date(entry.dt * 1000).toLocaleDateString('en-GB', {
                    weekday: 'long', month: 'short', day: 'numeric' 
                });
                const temp = (entry.main.temp - 273.15).toFixed(2);
                const description = entry.weather[0].description;
                weatherContent += `<div class="forecast-entry">  
                    <p><strong>${date}:</strong> ${temp} °C, ${description}</p>
                </div>`; 
            });

            weatherContent += '</div>';
            document.getElementById("weather-box").innerHTML = weatherContent; // Find weather-box and puts the weather forecast into the HTML element
        })
        .catch(() => document.getElementById("weather-box").innerHTML = `<p>Error fetching weather data</p>`); // Error catching if there is any problems and puts it in weather box
</script>


</body>
</html>
