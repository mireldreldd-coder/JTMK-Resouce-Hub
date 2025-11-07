<?php
header('Content-Type: application/json');
include 'db_connect.php';
include 'logger.php'; // This file already starts the session

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['items']) || !isset($data['totalCost'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data. No items or total cost received.']);
    exit;
}

$items = $data['items'];
$total_cost = $data['totalCost'];
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username']; 
$request_type = 'Central'; 
$status = 'Pending';
$item_count = count($items); // We still need this for the log

// Use a transaction to ensure all or nothing is inserted
$conn->begin_transaction();

try {
    // 1. Insert into the main 'requests_main' table
    $sql_main = "INSERT INTO requests_main (requested_by_user_id, request_type, request_status, total_cost) 
                 VALUES (?, ?, ?, ?)";
    $stmt_main = $conn->prepare($sql_main);
    
    $stmt_main->bind_param("issd", $user_id, $request_type, $status, $total_cost);
    $stmt_main->execute();
    
    // Get the new 'request_id' that the database just created
    $request_id = $conn->insert_id;

    // 2. Insert each item into the 'request_items' table
    // FIX: Removed 'category' AND 'unit' from the query
    $sql_items = "INSERT INTO request_items (request_id, item_name, quantity, price_per_unit) 
                  VALUES (?, ?, ?, ?)";
    $stmt_items = $conn->prepare($sql_items);
    
    foreach ($items as $item) {
        // FIX: Updated bind_param to match 4 columns (isid) and removed $item['category'] and $item['unit']
        $stmt_items->bind_param(
            "isid",
            $request_id,
            $item['name'],
            $item['quantity'],
            $item['price']
        );
        $stmt_items->execute();
    }

    // If all inserts were successful, commit the transaction
    $conn->commit();
    
    // 3. ADD THE ACTIVITY LOG
    $log_details = "User '{$username}' submitted a new '{$request_type}' request (ID: {$request_id}) with {$item_count} items, totaling RM{$total_cost}.";
    log_activity($conn, 'REQUEST_SUBMITTED', $log_details);
    
    // Send success response back to the frontend
    echo json_encode(['success' => true, 'request_id' => $request_id]);

} catch (mysqli_sql_exception $e) {
    // If anything fails, roll back the entire transaction
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// Close statements and connection
if (isset($stmt_main)) {
    $stmt_main->close();
}
if (isset($stmt_items)) {
    $stmt_items->close();
}
$conn->close();
?>