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

// Validate incoming data
if (empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'No items in the request.']);
    exit;
}

$items = $data['items'];
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username']; 
$request_type = 'Inventory'; 

// --- FIX 1: Set status to Pending ---
$status = 'Pending'; 

$total_cost = 0; // Inventory items are free to request
$item_count = count($items); // For the log

// Start the database transaction
$conn->begin_transaction();

try {
    // 1. Insert one row into the main 'requests_main' table
    $sql_main = "INSERT INTO requests_main (requested_by_user_id, request_type, request_status, total_cost) 
                 VALUES (?, ?, ?, ?)";
    $stmt_main = $conn->prepare($sql_main);
    $stmt_main->bind_param("issd", $user_id, $request_type, $status, $total_cost);
    $stmt_main->execute();
    
    // Get the new auto-generated request_id
    $request_id = $conn->insert_id;
    
    // 2. Insert each item into the 'request_items' table
    $sql_items = "INSERT INTO request_items (request_id, item_name, quantity, price_per_unit) 
                  VALUES (?, ?, ?, ?)";
    $stmt_items = $conn->prepare($sql_items);
    
    // --- FIX 2: REMOVED all stock checking and updating logic ---
    // This logic should be moved to the admin's approval script (update_request_status.php)
    
    $item_names_for_log = []; // For a cleaner log message

    foreach ($items as $item) {
        $item_price = 0.00; // Price is 0 for inventory items
        $stmt_items->bind_param(
            "isid",
            $request_id,
            $item['name'],
            $item['quantity'],
            $item_price
        );
        $stmt_items->execute();
        $item_names_for_log[] = $item['name'] . " (x" . $item['quantity'] . ")";
    }

    // If we get here, all inserts were successful
    $conn->commit();
    
    // 3. ADD THE ACTIVITY LOG
    $item_list_string = implode(', ', $item_names_for_log); // Create the item list
    $log_details = "User '{$username}' submitted a new '{$request_type}' request (ID: {$request_id}) for {$item_count} items: {$item_list_string}.";
    log_activity($conn, 'REQUEST_SUBMITTED', $log_details);
    
    echo json_encode(['success' => true, 'request_id' => $request_id, 'message' => 'Request submitted successfully.']);

} catch (mysqli_sql_exception $e) {
    // 12. Catch any database exception and roll back
    $conn->rollback();
    
    // --- ADDED LOG (FAILURE) ---
    $log_details = "Database error for '{$username}' submitting '{$request_type}' request. Error: " . $e->getMessage();
    log_activity($conn, 'DB_ERROR', $log_details);
    // --- END LOG ---
    
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// 13. Close statements and connection
if (isset($stmt_main)) {
    $stmt_main->close();
}
if (isset($stmt_items)) {
    $stmt_items->close();
}
$conn->close();
?>