<?php
// Local database configuration template
$host     = "localhost";
$username = "root";
$password = "";
$dbname   = "skillconnect";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
