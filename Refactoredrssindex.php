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

<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.3.1/mapbox-gl.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.3.1/mapbox-gl.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/002f6ecea1.js" crossorigin="anonymous"></script>
    <title>City Explorer</title>
</head>

<body class="text-white min-h-screen relative">
<!-- Background layer -->
<div class="absolute inset-0 bg-gradient-to-br from-blue-400 via-indigo-500 to-cyan-500 animate-gradient bg-[length:400%_400%] min-h-screen h-full z-0"></div>
<!-- Overlay patterns -->
<div class="absolute inset-0 opacity-30 min-h-screen h-full z-0">
    <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full mix-blend-overlay filter blur-3xl animate-pulse"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-yellow-300 rounded-full mix-blend-overlay filter blur-3xl animate-pulse"></div>
</div>

<!-- Header -->
<header class="relative z-10 bg-transparent text-white p-4 text-center">
    <h1 class="text-7xl font-bold">TWIN CITIES</h1>
</header>

<!-- Navigation -->
<section id="navigation" class="relative z-10 bg-transparent p-4 rounded-lg">
    <nav>
        <ul class="flex flex-wrap space-x-4 justify-center text-center">
            <?php foreach ($cities as $id => $city): ?>
                <li><a href="?city=<?= $id ?>" class="text-white-400 hover:underline text-center"><?= $city['name'] ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
</section>

<!-- Currently Viewing Sign -->
    <main class="container mx-auto p-4">
        <h2 class="text-4xl font-semibold mb-4 text-center animate-bounce ">Currently Viewing: <?= $cityData['name'] ?></h2>

        <!-- Map -->
        <div class="relative group h-96">
            <div class="absolute -inset-0.5 bg-white rounded-lg blur"></div>
            <div id="map" class="relative px-10 py-8 rounded-lg leading-none h-full w-full  "></div>

            <!-- Setup for grid -->
        </div>
        <section class="p-4 py-20">

            <!-- Weather -->
            <div class="w-full  mx-auto "> ">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="absolute -inset-0.5 bg-gradient-to-r from-transparent via-indigo-700 to-transparent  border-2 rounded-lg blur  relative group h-64">
                        <div class="absolute rounded-lg blur opacity-75 "></div>
                        <section class="relative px-10 py-8  rounded-lg  h-full flex flex-col justify-center ">
                            <svg
                                    class="h-10 w-10 absolute text-white fill-current top-4 right-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 640 512">
                                    <!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                                    <path d="M294.2 1.2c5.1 2.1 8.7 6.7 9.6 12.1l14.1 84.7 84.7 14.1c5.4 .9 10 4.5 12.1 9.6s1.5 10.9-1.6 15.4l-38.5 55c-2.2-.1-4.4-.2-6.7-.2c-23.3 0-45.1 6.2-64 17.1l0-1.1c0-53-43-96-96-96s-96 43-96 96s43 96 96 96c8.1 0 15.9-1 23.4-2.9c-36.6 18.1-63.3 53.1-69.8 94.9l-24.4 17c-4.5 3.2-10.3 3.8-15.4 1.6s-8.7-6.7-9.6-12.1L98.1 317.9 13.4 303.8c-5.4-.9-10-4.5-12.1-9.6s-1.5-10.9 1.6-15.4L52.5 208 2.9 137.2c-3.2-4.5-3.8-10.3-1.6-15.4s6.7-8.7 12.1-9.6L98.1 98.1l14.1-84.7c.9-5.4 4.5-10 9.6-12.1s10.9-1.5 15.4 1.6L208 52.5 278.8 2.9c4.5-3.2 10.3-3.8 15.4-1.6zM144 208a64 64 0 1 1 128 0 64 64 0 1 1 -128 0zM639.9 431.9c0 44.2-35.8 80-80 80l-271.9 0c-53 0-96-43-96-96c0-47.6 34.6-87 80-94.6l0-1.3c0-53 43-96 96-96c34.9 0 65.4 18.6 82.2 46.4c13-9.1 28.8-14.4 45.8-14.4c44.2 0 80 35.8 80 80c0 5.9-.6 11.7-1.9 17.2c37.4 6.7 65.8 39.4 65.8 78.7z"/></svg>
                            <section class="relative   rounded-lg  h-full flex flex-col  ">
                            <h3 class="text-xl font-semibold mb-2">Weather Forecast</h3>
                            <div id="weather-box" class="flex-grow  whitespace-nowrap text-sm">Loading weather...</div>
                        </section>
                    </div>

                    <!-- Images -->
                    <div class=" absolute -inset-0.5 bg-gradient-to-r from-transparent via-indigo-700 to-transparent border-2 rounded-lg blur  relative group h-64">
                        <section class="relative px-10 py-8  rounded-lg  h-full flex flex-col justify-center">
                            <svg
                                    class="h-10 w-10 absolute text-white fill-current top-4 right-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 640 512">
                                <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. -->
                                <path d="M220.6 121.2L271.1 96 448 96v96H333.2c-21.9-15.1-48.5-24-77.2-24s-55.2 8.9-77.2 24H64V128H192c9.9 0 19.7-2.3 28.6-6.8zM0 128V416c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H271.1c-9.9 0-19.7 2.3-28.6 6.8L192 64H160V48c0-8.8-7.2-16-16-16H80c-8.8 0-16 7.2-16 16l0 16C28.7 64 0 92.7 0 128zM168 304a88 88 0 1 1 176 0 88 88 0 1 1 -176 0z"></path>
                            <div id="poi-image-box" class=" h-full w-full">
                                <img id="poi-image" src="path/to/placeholder-image.jpg" alt="Place of interest image" class="w-full h-full object-contain rounded-lg" />
                            </div>
                        </section>
                    </div>

                    <!-- City Details -->
                    <div class=" absolute -inset-0.5 bg-gradient-to-r from-transparent via-indigo-700 to-transparent border-2 rounded-lg blur  relative group h-64">
                        <div class=" rounded-lg blur opacity-75 "></div>
                        <section class="relative px-10 py-8 rounded-lg  h-full flex flex-col justify-center">
                            <svg
                                    class="h-10 w-10 absolute text-white fill-current top-4 right-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 640 512">
                                <!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                                <path d="M48 0C21.5 0 0 21.5 0 48V464c0 26.5 21.5 48 48 48h96V432c0-26.5 21.5-48 48-48s48 21.5 48 48v80h96c15.1 0 28.5-6.9 37.3-17.8C340.4 462.2 320 417.5 320 368c0-54.7 24.9-103.5 64-135.8V48c0-26.5-21.5-48-48-48H48zM64 240c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V240zm112-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V240c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V240zM80 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V112zM272 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16zM496 512a144 144 0 1 0 0-288 144 144 0 1 0 0 288zm0-96a24 24 0 1 1 0 48 24 24 0 1 1 0-48zm0-144c8.8 0 16 7.2 16 16v80c0 8.8-7.2 16-16 16s-16-7.2-16-16V288c0-8.8 7.2-16 16-16z"></path>
                            <h3 class="text-xl font-semibold mb-2">City Details:</h3>
                            <table class="table-auto w-full">
                                <tbody>
                                <tr>
                                    <td class="font-bold">Population:</td>
                                    <td><?= $cityData['population'] ?></td>
                                </tr>
                                <tr>
                                    <td class="font-bold">Currency:</td>
                                    <td><?= $cityData['currency'] ?></td>
                                </tr>
                                <tr>
                                    <td class="font-bold">Fun Fact:</td>
                                    <td><?= $cityData['funFact'] ?></td>
                                </tr>
                                </tbody>
                            </table>
                        </section>
                    </div>

                    <!-- Place of interest Details -->
                    <div class=" absolute -inset-0.5 bg-gradient-to-r from-transparent via-indigo-700 to-transparent border-2 rounded-lg blur   relative group h-64">
                        <div class="absolute rounded-lg blur opacity-75"></div>
                        <section class="relative px-10 py-8 rounded-lg leading-none h-full flex flex-col justify-center">
                            <svg
                                    class="h-10 w-10 absolute text-white fill-current top-4 right-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 640 512">
                                <!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. -->
                                <path d="M243.4 2.6l-224 96c-14 6-21.8 21-18.7 35.8S16.8 160 32 160v8c0 13.3 10.7 24 24 24H456c13.3 0 24-10.7 24-24v-8c15.2 0 28.3-10.7 31.3-25.6s-4.8-29.9-18.7-35.8l-224-96c-8-3.4-17.2-3.4-25.2 0zM128 224H64V420.3c-.6 .3-1.2 .7-1.8 1.1l-48 32c-11.7 7.8-17 22.4-12.9 35.9S17.9 512 32 512H480c14.1 0 26.5-9.2 30.6-22.7s-1.1-28.1-12.9-35.9l-48-32c-.6-.4-1.2-.7-1.8-1.1V224H384V416H344V224H280V416H232V224H168V416H128V224zM256 64a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"></path>
                            <h3 class="text-xl font-semibold mb-2">Place of Interest Details:</h3>
                            <table class="table-auto w-full">
                                <tbody>
                                <tr>
                                    <td class="font-bold">Place Type:</td>
                                    <td><span id="poi-place-type"></span></td>
                                </tr>
                                <tr>
                                    <td class="font-bold">Capacity:</td>
                                    <td><span id="poi-capacity"></span></td>
                                </tr>
                                <tr>
                                    <td class="font-bold">Year Established:</td>
                                    <td><span id="poi-year-established"></span></td>
                                </tr>
                                <tr>
                                    <td class="font-bold">Hours of Operation:</td>
                                    <td><span id="poi-hours-of-operation"></span></td>
                                </tr>
                                </tbody>
                            </table>
                        </section>
                    </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script>
    const cityData = <?php echo $cityDataJSON; ?>;
    const weatherToken = '<?php echo $weatherToken; ?>';

    function fetchCityImage(cityName) {
        const cityImage = document.getElementById("poi-image");
        cityImage.src = "./spinner.gif";

        fetch(`fetch_image.php?place=${encodeURIComponent(cityName)}`)
            .then(response => response.json())
            .then(data => {
                cityImage.src = data.image_url;
            })
            .catch(() => {
                cityImage.src = "./default.jpg";
            });
    }

    function displayPoiDetails(poi) {
        document.getElementById("poi-place-type").textContent = poi.place_type;
        document.getElementById("poi-capacity").textContent = poi.capacity;
        document.getElementById("poi-year-established").textContent = poi.year_established;
        document.getElementById("poi-hours-of-operation").textContent = poi.hours_of_operation;

        fetchCityImage(poi.name);
    }

    function hidePoiDetails() {
        document.getElementById("poi-place-type").textContent = '';
        document.getElementById("poi-capacity").textContent = '';
        document.getElementById("poi-year-established").textContent = '';
        document.getElementById("poi-hours-of-operation").textContent = '';
        fetchCityImage(cityData.name);
    }

    fetchCityImage(cityData.name);

    mapboxgl.accessToken = '<?php echo $mapToken; ?>';
    const map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/streets-v11',
        center: [cityData.lon, cityData.lat],
        zoom: 12
    });

    new mapboxgl.Marker()
        .setLngLat([cityData.lon, cityData.lat])
        .setPopup(new mapboxgl.Popup({ offset: 25 }).setText(cityData.name))
        .addTo(map);

    cityData.points_of_interest.forEach(poi => {
        const marker = new mapboxgl.Marker({ color: 'red' })
            .setLngLat([poi.lon, poi.lat])
            .setPopup(new mapboxgl.Popup({ offset: 25 }).setHTML(`<h4>${poi.name}</h4>`))
            .addTo(map);

        marker.getElement().addEventListener('mouseenter', () => {
            displayPoiDetails(poi);
        });

        marker.getElement().addEventListener('mouseleave', () => {
            hidePoiDetails();
        });

        marker.getElement().addEventListener('click', () => {
            if (poi.poi_url && poi.poi_url.trim() !== "") {
                window.open(poi.poi_url, '_blank');
            } else {
                alert("No URL available for this place.");
            }
        });
    });

    const weatherAPI = `https://api.openweathermap.org/data/2.5/forecast?lat=${cityData.lat}&lon=${cityData.lon}&appid=${weatherToken}`;
    fetch(weatherAPI)
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
            document.getElementById("weather-box").innerHTML = weatherContent;
        })
        .catch(() => document.getElementById("weather-box").innerHTML = `<p>Error fetching weather data</p>`);
</script>
</body>

<!-- Footer -->
<footer class="relative z-10 bg-transparent ">
    <div class="w-full mx-auto max-w-screen-xl p-4 md:flex md:items-center md:justify-between">
    <span class="text-sm text-white sm:text-center dark:text-gray-400">
      <a href="https://github.com/starphillips/twin-cities" class="hover:underline">
            <img src="footerimgs/github-mark.png" alt="GitHub Logo" class="inline-block w-6 h-6 ">
          Github™</a>  Group Project

    </span>
        <span class="flex flex-wrap items-center mt-3 text-sm font-medium text-white dark:text-gray-400 sm:mt-0">
      <span class="inline-block mt-2 sm:mt-0 mr-2">Eryk Szymanski | Star Phillips | Nick LeMasonry | Harrison Hamilton</span>
            <img src="footerimgs/logo.png" alt="UWE Logo" class="inline-block w-6 h-6 ">
    </span>
    </div>
</footer>