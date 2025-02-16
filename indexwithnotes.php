<?php
// Load environment variables
function loadEnv($file)
{
    // First the function checks if the config file exists 
    if (!file_exists($file)) {
        die(".env file not found!"); // Terminate script if it cannot load the file
    }

    // Function then reads entire file into an array, each element is a line from the file
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Ignores unnessesary bits
    foreach ($lines as $line) {                                                           // Loops through each line
        if (strpos(trim($line), '#') === 0) continue;           // Any line that starts with # is ignored (Find the needle(#) in the haystack(line))
        // Strpos is a built-in PHP function that finds the position of first occurence
        list($name, $value) = explode('=', $line, 2);           // Split each line into a key, value pair (explode is where it splits the line into 2)
        $name = trim($name);                                                      // Trim to remove any spaces
        $value = trim($value, ' "');                                  // Trim to remove quotes (as keys are in '')
        $_ENV[$name] = $value;                                                            // Store the key value pairs in a superglobal (a variable that is always accessible anywhere in script)
    }
}

loadEnv(__DIR__ . '/.env'); // Calls the function and passes our config file through
// DIR is a magic constant in PHP that represents the path of the scripts directory [laragon/www/twincities]

// Load configuration file
$config = parse_ini_file('.env'); 
// Now loadEnv function has extracted the keys, parse_ini_file will read the .env file and return an associative array that you can assign to a variable (you'll see later)
// Parsing is the process of reading/converting data from one format into a structured format
// We want it to be parsed in ini file function as ini files use simple key=value formatting
// Once parsed it is stored in $config, which is now an assosiative array

// Database connection
$host = $config['db_host'];
$username = $config['db_username'];
$password = $config['db_password'];
$database = $config['db_database']; // So here the config(assosiative array we created) now reads the key and creates variables based on the values from .env matching to their key

$conn = new mysqli($host, $username, $password, $database);
// Creates connection to the database by taking the variables, we created from the .env file, in as parameters
// $conn is a variable but when using 'new' it also becomes an object that will be used to interact with the database later on
// this object is an instance from the mysqli class, which provides connections to the database and the methods for interacting with the database.

// Check connection
if ($conn->connect_error) { // the '->' is syntax for object-ori in PHP, where it is used to access the object properties/methods in PHP
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch cities from the database
$cities = []; // Initialises empty array which will be used to store data for each city 
$city_query = "SELECT cityID, cityName, cityCountry, Latitude, Longitude, Population, Currency, funFact FROM city";
$city_result = $conn->query($city_query); // query method of the $conn object used to gain all the data from the database, taking city query as a parameter

while ($city = $city_result->fetch_assoc()) {                    // while loop to go through each row of results 
    // fetch_assoc method fetches a row from the results and creates assosiative array where array keys are the column and value is the data e.g. key=lat value=-4.876
    $cityID = $city['cityID'];
    $cities[$cityID] = [                                         // Retrieves city data based on what city page you are currently on
        "name" => "{$city['cityName']}, {$city['cityCountry']}", // name would be the key and the value is created of the city name and country retrieved from city database
        // The '=>' is syntax used in PHP to assign a value to a key in an associative array
        "lat" => $city['Latitude'], 
        "lon" => $city['Longitude'],
        "population" => $city['Population'],
        "currency" => $city['Currency'],
        "funFact" => $city['funFact'],
        "points_of_interest" => []                              // Empty as this will be populated based on the users click at a later date 
    ];

    // Fetch points of interest for this city
    $poi_query = "SELECT poi.PlaceName, poi.Latitude, poi.Longitude, poi.PlaceType, poi.Capacity, poi.YearEstablished, poi.HoursOfOperation, poi.poi_url
                  FROM placeofinterest poi    
                  WHERE poi.cityID = $cityID"; // Filters to only obtain poi from the selected city, placeofinterest shortened to poi for ease 

    $poi_result = $conn->query($poi_query); 

    while ($poi = $poi_result->fetch_assoc()) { 
        $cities[$cityID]["points_of_interest"][] = [ // adds data to each poi based on the cityID, the [] indicates we are adding a new poi to this array
            // Where inside this [] an associative array is created with the key-value pairs created below.
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

$conn->close(); // close method of the mysqli class used to close the database

// Get selected city
if (isset($_GET['city'])) {        // isset function checks if a variable is set, $_GET is a superglobal array that is used to retrieve data sent via query strings using get method
    $selectedCity = $_GET['city']; // It obtains what city it is from the URL (WHICH HAS CITY ID)
} else {
    $selectedCity = array_key_first($cities);  // Default to the first city
}                                                     // array_key_first is a built in PHP function that returns the first key of an array

if (!isset($cities[$selectedCity])) {                 // If the selected city does not exist then !isset becomes true and then it will default to the first city
    $selectedCity = array_key_first($cities);
}

$cityData = $cities[$selectedCity]; // City data identifies the slected city and fetch data for that city
$cityDataJSON = json_encode($cityData); // Turns our PHP code to JSON string so that it can be parsed (basically these '=>' turn to ':' for easier HTTP requests and JavaScript)

$mapToken = isset($_ENV['map_token']) ? $_ENV['map_token'] : null; // if token is set from ENV superglobal it will return the API token, if not it will return null
$weatherToken = isset($_ENV['weather_token']) ? $_ENV['weather_token'] : null; // same as above but for weather
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
            // Loop through each city and create a list item with a link
            foreach ($cities as $id => $city) {
                echo '<li><a href="?city=' . $id . '">' . $city['name'] . '</a></li>'; // Echo is used to output data to the screen
                // Creates the URL for each city
                // URL is generated dynamically by appending the city's ID as query parameters 
                // When the user clicks a link, the page reloads with the city ID in the URL
                // This allows the PHP to get the city data via the $_GET, which pulls the data based on the URLs city ID
            }
            ?>
        </ul>
    </nav>
</section>
</head>

<body>

<h2>Currently Viewing: <?php echo $cityData['name']; ?></h2> 
<!-- PHP is used to obtain the city name from database, where echo outputs this to the screen -->

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
        cityImage.src = "./spinner.gif";                        // Spinner GIF as image loads

        fetch(`fetch_image.php?place=${encodeURIComponent(cityName)}`) // Sends a request to fetch_image.php taking the city name so its for the correct one
        // codeURIComponent encodes special characters so it doesnt break the URL
            .then(response => response.json()) // Waits for response from fetchimage, if promise is returned it coverts it to JavaScript object for readability
            .then(data => {
                cityImage.src = data.image_url; // Then updates the image source with the image received from the PHP script
            })
            .catch(() => {
                cityImage.src = "./default.jpg"; // If it cannot connect to FlickrAPI, it will return a default image
            });
    }

    function displayPoiDetails(poi) { // Function takes our poi object as an argument
        // Finds the ID in the html and updates the page with info about the POI selected
        document.getElementById("poi-place-type").textContent = poi.place_type; // Text content means it will only get the text, ignores html tags etc..
        document.getElementById("poi-capacity").textContent = poi.capacity;
        document.getElementById("poi-year-established").textContent = poi.year_established;
        document.getElementById("poi-hours-of-operation").textContent = poi.hours_of_operation;

        fetchCityImage(poi.name); // Calls to function to get image from Flickr API that will return image based on POI name
    }

    function hidePoiDetails() { // After hover it will hide the POI details
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
        .setPopup(new mapboxgl.Popup({ offset: 25 }).setText(cityData.name)) // Offset is how far away the popup displays from the marker point
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
            // City will be city and country, and list will be the weather forecast and temp
            let weatherContent = `<h3>Weather in ${city.name}</h3><div class="forecast"><h4>5-Day Forecast:</h4>`; // String for HTML to display weather content
            const dailyForecasts = {}; // Initialises empty object to store the daily weather forecast

            list.forEach(entry => {
                const date = new Date(entry.dt * 1000).toISOString().split('T')[0]; 
                // *1000 converts to millisecs which JS date expects, ISO is the format that includes date and time, T spilts off the time and removes it
                if (!dailyForecasts[date]) { // As dailyForecasts is an object, it will add the date to be the key and value will be the temp / weather
                    dailyForecasts[date] = entry; // if statement checks if there is an entry, if not it will add it in
                }
            });

            Object.values(dailyForecasts).slice(0, 5).forEach(entry => { // Selects the first 5 weather entries to show 5 day forecast
                const date = new Date(entry.dt * 1000).toLocaleDateString('en-GB', { // Extracts the UNIX timestamp converts to JS date object and the format we want
                    weekday: 'long', month: 'short', day: 'numeric' 
                });
                const temp = (entry.main.temp - 273.15).toFixed(2); // Extracts temp and converts from kevlin to celsius, and rounds to 2 d.p.
                const description = entry.weather[0].description; // Extracts the weather description (first from array) which is basic text like 'sunny weather'
                weatherContent += `<div class="forecast-entry">  
                    <p><strong>${date}:</strong> ${temp} °C, ${description}</p>
                </div>`; // The '+=' operator appends to the existing value so it adds all 5 days 
            });

            weatherContent += '</div>';
            document.getElementById("weather-box").innerHTML = weatherContent; // Find weather-box and puts the weather forecast into the HTML element
        })
        .catch(() => document.getElementById("weather-box").innerHTML = `<p>Error fetching weather data</p>`); // Error catching if there is any problems and puts it in weather box
</script>


</body>
</html>
