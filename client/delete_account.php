<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php"); exit();
}
$conn    = new mysqli("localhost","root","","skillconnect");
$user_id = (int)$_SESSION['user_id'];

// Delete all related data then the user
$conn->query("DELETE FROM notifications WHERE user_id=$user_id");
$conn->query("DELETE FROM messages WHERE sender_id=$user_id OR receiver_id=$user_id");
$conn->query("DELETE FROM connections WHERE client_id=$user_id");
$conn->query("DELETE FROM ratings WHERE client_id=$user_id");
// Delete jobs + their applications
$jobs = $conn->query("SELECT id FROM jobs WHERE client_id=$user_id");
while ($j = $jobs->fetch_assoc()) {
    $conn->query("DELETE FROM applications WHERE job_id={$j['id']}");
}
$conn->query("DELETE FROM jobs WHERE client_id=$user_id");
$conn->query("DELETE FROM users WHERE id=$user_id");
$conn->close();

session_destroy();
header("Location: ../login.php?deleted=1");
exit();
