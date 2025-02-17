<?php
// Load environment variables
function loadEnv($file) {
    if (!file_exists($file)) die(".env file not found!");
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, ' "');
    }
}
loadEnv(__DIR__ . '/.env');

// Database connection
$config = parse_ini_file('.env');
$conn = new mysqli($config['db_host'], $config['db_username'], $config['db_password'], $config['db_database']);

if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

// Handle Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cityID = $_POST['cityID'];
    $username = $_POST['username'];
    $comment = $_POST['comment'];

    $stmt = $conn->prepare("INSERT INTO comments (cityID, username, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $cityID, $username, $comment);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["status" => "success"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cityID = $_GET['cityID'];
    $search = $_GET['search'] ?? '';

    $query = "SELECT * FROM comments WHERE cityID = ?";
    if (!empty($search)) {
        $query .= " AND (comment LIKE ? OR username LIKE ?)";
    }

    $stmt = $conn->prepare($query);
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bind_param("iss", $cityID, $searchParam, $searchParam);
    } else {
        $stmt->bind_param("i", $cityID);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }

    echo json_encode($comments);
    $stmt->close();
    exit;
}
?>
