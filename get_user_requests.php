<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Get all main requests (carts) for this user
$sql_main = "SELECT * FROM requests_main 
             WHERE requested_by_user_id = ? 
             ORDER BY request_date DESC";
$stmt_main = $conn->prepare($sql_main);
$stmt_main->bind_param("i", $user_id);
$stmt_main->execute();
$result_main = $stmt_main->get_result();

$requests = array();

// 2. For each cart, get its items
$sql_items = "SELECT * FROM request_items WHERE request_id = ?";
$stmt_items = $conn->prepare($sql_items);

while ($row = $result_main->fetch_assoc()) {
    $request_id = $row['request_id'];
    
    // Fetch all items for this request_id
    $stmt_items->bind_param("i", $request_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    
    $items_array = array();
    while ($item_row = $result_items->fetch_assoc()) {
        $items_array[] = $item_row;
    }
    
    // Add the items array into the main request object
    $row['items'] = $items_array;
    $requests[] = $row;
}

$stmt_main->close();
$stmt_items->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($requests);
?>