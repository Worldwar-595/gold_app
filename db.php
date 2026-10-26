<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "gold_db";
$port = 3307; 

$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>