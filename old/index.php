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

$mapToken = $_ENV['map_token'] ?? null;
$weatherToken = $_ENV['weather_token'] ?? null;

// Define cities with coordinates and points of interest
$cities = [
    "plymouth_uk" => [
        "name" => "Plymouth, UK",
        "lat" => 50.3755,
        "lon" => -4.1427,
        "points_of_interest" => [
            ["name" => "The Hoe", "lat" => 50.365, "lon" => -4.139],
            ["name" => "Royal William Yard", "lat" => 50.375, "lon" => -4.142],
            ["name" => "National Marine Aquarium", "lat" => 50.366, "lon" => -4.138]
        ]
    ],
    "brest_france" => [
        "name" => "Brest, France",
        "lat" => 48.3904,
        "lon" => -4.4861,
        "points_of_interest" => [
            ["name" => "Oceanopolis", "lat" => 48.408, "lon" => -4.459],
            ["name" => "Brest Castle", "lat" => 48.388, "lon" => -4.497],
            ["name" => "Tanguy Tower", "lat" => 48.388, "lon" => -4.489]
        ]
    ],
    "gdynia_poland" => [
        "name" => "Gdynia, Poland",
        "lat" => 54.5189,
        "lon" => 18.5305,
        "points_of_interest" => [
            ["name" => "ORP Błyskawica", "lat" => 54.523, "lon" => 18.532],
            ["name" => "Gdynia Aquarium", "lat" => 54.522, "lon" => 18.533],
            ["name" => "Kosciuszko Square", "lat" => 54.522, "lon" => 18.530]
        ]
    ],
    "plymouth_usa" => [
        "name" => "Plymouth, USA",
        "lat" => 41.9584,
        "lon" => -70.6673,
        "points_of_interest" => [
            ["name" => "Plymouth Rock", "lat" => 41.974, "lon" => -70.665],
            ["name" => "Mayflower II", "lat" => 41.975, "lon" => -70.667],
            ["name" => "Plimoth Patuxet Museums", "lat" => 41.966, "lon" => -70.631]
        ]
    ]
];

$selectedCity = $_GET['city'] ?? "brest_france";
if (!array_key_exists($selectedCity, $cities)) {
    $selectedCity = "brest_france";
}

$cityData = $cities[$selectedCity];
$cityDataJSON = json_encode($cityData);
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
                <li><a href="?city=plymouth_uk">Plymouth, UK</a></li>
                <li><a href="?city=plymouth_usa">Plymouth, USA</a></li>
                <li><a href="?city=brest_france">Brest, France</a></li>
                <li><a href="?city=gdynia_poland">Gdynia, Poland</a></li>
            </ul>
        </nav>
    </section>
</head>

<body>

<h2>Currently Viewing: <?php echo $cityData['name']; ?></h2>

<div id="map"></div>
<div id="weather-box">Loading weather...</div>
<div id="poi-box"><h3>Points of Interest</h3><ul id="poi-list"></ul></div>

<script>
    const cityData = <?php echo $cityDataJSON; ?>;
    const weatherToken = '<?php echo $weatherToken; ?>';

    mapboxgl.accessToken = '<?php echo $mapToken; ?>';
    const map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/streets-v11',
        center: [cityData.lon, cityData.lat],
        zoom: 10
    });

    // Add the city marker
    new mapboxgl.Marker()
        .setLngLat([cityData.lon, cityData.lat])
        .setPopup(new mapboxgl.Popup({ offset: 25 }).setText(cityData.name))
        .addTo(map);

    // Add markers for points of interest
    cityData.points_of_interest.forEach(poi => {
        new mapboxgl.Marker({ color: 'red' })
            .setLngLat([poi.lon, poi.lat])
            .setPopup(new mapboxgl.Popup({ offset: 25 }).setText(poi.name))
            .addTo(map);

        const poiList = document.getElementById("poi-list");
        const li = document.createElement("li");
        li.textContent = poi.name;
        poiList.appendChild(li);
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
