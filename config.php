<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "iworld";

// Set PHP timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set MySQL timezone to Malaysia
$conn->query("SET time_zone = '+08:00'");
?>
