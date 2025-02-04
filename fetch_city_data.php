<?php
// Database connection variables
$host = "localhost";  
$username = "root";   
$password = "";       
$database = "twincities";  // Your database name

$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

// Query the 'city' table
$sql = "SELECT cityName, latitude, longitude FROM city";  // Use the correct 'city' table
$result = $conn->query($sql);

$cities = [];
while ($row = $result->fetch_assoc()) {
    $cities[] = $row;
}

$conn->close();

echo json_encode($cities);  // Output the data as JSON
?>
