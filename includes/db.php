<?php
// ── DATABASE CONNECTION ──
$conn = new mysqli("localhost", "root", "", "skillconnect");
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
