<?php
$host = "localhost";
$user = "root"; // default in XAMPP
$pass = "";     // default is empty
$db   = "viotrack";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
