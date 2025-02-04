<?php
$host = "localhost"; // Change if needed (e.g., your database server)
$username = "root"; // Your database username
$password = ""; // Your database password
$dbname = "twincities"; // Replace with your actual database name

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Connected successfully to the database!";
}

$conn->close();
?>
