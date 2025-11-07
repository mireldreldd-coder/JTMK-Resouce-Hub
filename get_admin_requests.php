<?php
session_start();
include 'db_connect.php';

// Security Check: Ensure user is logged in AND is an admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

// 1. Get all main requests (carts) and join with user info
$sql_main = "SELECT r.*, u.name as requester_name 
             FROM requests_main r
             LEFT JOIN userprofiles u ON r.requested_by_user_id = u.id 
             ORDER BY r.request_status = 'Pending' DESC, r.request_date DESC";
$result_main = $conn->query($sql_main);

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

$stmt_items->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($requests);
?>