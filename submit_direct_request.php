<?php
header('Content-Type: application/json');
include 'db_connect.php';
include 'logger.php'; // This file already starts the session
// session_start(); // <-- FIX 1: Removed redundant call

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

// Define upload directory and initialize variables
$upload_dir = 'uploads/';
$attachment_path = null; // FIX 2: Renamed variable to match DB
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Handle the file upload (if one exists)
// FIX 3: Changed 'itemFile' to 'attachment' to match HTML form
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['attachment']['tmp_name'];
    $file_info = pathinfo($_FILES['attachment']['name']);
    $file_ext = $file_info['extension'];
    $unique_filename = 'req_' . uniqid() . '.' . $file_ext;
    $attachment_path = $upload_dir . $unique_filename; // Save to the correct variable

    if (!move_uploaded_file($tmp_name, $attachment_path)) {
        // Log file upload failure
        log_activity($conn, 'FILE_UPLOAD_FAILURE', "Failed to move uploaded file for user '{$_SESSION['username']}'.");
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
        exit;
    }
}

// Get data from the POST request
// FIX 4: Changed keys to match HTML form (e.g., itemType, estimatedPrice)
$item_name = $_POST['itemName'];
$category = $_POST['itemType'];
$quantity = $_POST['quantity'];
$price = $_POST['estimatedPrice'];
$product_link = $_POST['productLink']; // Get the product link

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$request_type = 'Direct';
$status = 'Pending';
$total_cost = $quantity * $price;
$item_count = 1; // Direct purchase is one item

// Use a transaction
$conn->begin_transaction();

try {
    // Insert into the main 'requests_main' table
    // FIX 5: Corrected table and column names, removed request_id (it's auto-increment) and item_count
    $sql_main = "INSERT INTO requests_main (requested_by_user_id, request_type, request_status, total_cost) 
                 VALUES (?, ?, ?, ?)";
    $stmt_main = $conn->prepare($sql_main);
    // FIX 6: Updated bind_param to match new query
    $stmt_main->bind_param("issd", $user_id, $request_type, $status, $total_cost);
    $stmt_main->execute();
    
    // FIX 7: Get the new auto-generated request_id
    $request_id = $conn->insert_id;

    // Insert the single item into the 'request_items' table
    // FIX 8: Corrected column names (price_per_unit, item_category, attachment_path, product_link), removed 'reason'
    $sql_item = "INSERT INTO request_items (request_id, item_name, quantity, price_per_unit, item_category, product_link, attachment_path) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_item = $conn->prepare($sql_item);
    // FIX 9: Updated bind_param to match new query (isidsss)
    $stmt_item->bind_param(
        "isidsss",
        $request_id,
        $item_name,
        $quantity,
        $price,
        $category,
        $product_link,
        $attachment_path // This will be NULL if no file was uploaded
    );
    $stmt_item->execute();

    // If both inserts were successful, commit
    $conn->commit();
    
    // LOG THE ACTIVITY
    $log_details = "User '{$username}' submitted a new '{$request_type}' request (ID: {$request_id}) for '{$item_name}' (x{$quantity}).";
    log_activity($conn, 'REQUEST_SUBMITTED', $log_details);
    
    $response = ['success' => true, 'message' => 'Direct request submitted successfully.'];

} catch (mysqli_sql_exception $e) {
    // If anything fails, roll back
    $conn->rollback();
    
    // Log the database failure
    $log_details = "Database error for '{$username}' submitting '{$request_type}' request. Error: " . $e->getMessage();
    log_activity($conn, 'DB_ERROR', $log_details);
    
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];

    // If DB failed, delete the orphaned file
    if ($attachment_path && file_exists($attachment_path)) {
        unlink($attachment_path);
    }
}

// Close statements and connection
if (isset($stmt_main)) {
    $stmt_main->close();
}
if (isset($stmt_item)) {
    $stmt_item->close();
}
$conn->close();

// Send final response
echo json_encode($response);
?>