<?php
// get_inventory_items.php
header('Content-Type: application/json');
include 'db_connect.php'; // This file provides the $conn variable

$sql = "SELECT id, item_name, category, unit, current_stock FROM inventory_items ORDER BY item_name ASC";
$result = $conn->query($sql);

if ($result) {
    // Fetch all items as an associative array
    $items = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($items);
} else {
    // Send back a JSON error if the query fails
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}

// Close the connection
$conn->close();
?>