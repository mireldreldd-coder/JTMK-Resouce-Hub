<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

$sql = "SELECT id, item_name, quantity_stock, unit, category, price FROM price_table";
$result = $conn->query($sql);

$items = array();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($items);
?>