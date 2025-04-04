<?php
header("Content-Type: application/json; charset=UTF-8");

$host = 'saturn.capconnect.com';
$dbname = 'beluxest_app';
$username = 'beluxest_ayyoub_echarkaouy';
$password = 'Kal7h0:02nw0ke';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}
?>