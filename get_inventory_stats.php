<?php
// get_inventory_stats.php
header('Content-Type: application/json');
include 'db_connect.php'; // Provides $conn

$stats = [
    'totalItems' => 0,
    'outOfStock' => 0
];

// Get total items count
$resultTotal = $conn->query("SELECT COUNT(*) as totalItems FROM inventory_items");
if ($resultTotal) {
    $stats['totalItems'] = $resultTotal->fetch_assoc()['totalItems'];
}

// Get out of stock count (where stock is 0)
$resultOutOfStock = $conn->query("SELECT COUNT(*) as outOfStock FROM inventory_items WHERE current_stock = 0");
if ($resultOutOfStock) {
    $stats['outOfStock'] = $resultOutOfStock->fetch_assoc()['outOfStock'];
}

echo json_encode($stats);

$conn->close();
?>