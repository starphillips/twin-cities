<?php
if (!isset($_GET['place'])) {
    echo json_encode(["error" => "Missing place parameter"]);
    exit;
}

// Load environment variables
function loadEnv($file) {
    if (!file_exists($file)) {
        die(".env file not found!");
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, ' "');
    }
}

loadEnv(__DIR__ . '/.env');
$flickrApiKey = $_ENV['flickr_api_key'] ?? null;

$placeName = urlencode($_GET['place']);
$url = "https://api.flickr.com/services/rest/?method=flickr.photos.search&api_key={$flickrApiKey}&text={$placeName}&format=json&nojsoncallback=1&per_page=1";

$response = file_get_contents($url);
$data = json_decode($response, true);

if (isset($data['photos']['photo'][0])) {
    $photo = $data['photos']['photo'][0];
    $photoUrl = "https://farm{$photo['farm']}.staticflickr.com/{$photo['server']}/{$photo['id']}_{$photo['secret']}_b.jpg";
    echo json_encode(["image_url" => $photoUrl]);
} else {
    echo json_encode(["image_url" => "path/to/default/image.jpg"]);
}
?>
