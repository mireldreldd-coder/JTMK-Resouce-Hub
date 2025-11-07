<?php
session_start();
header('Content-Type: application/json');

require 'db_connect.php';
require 'logger.php'; // Assuming you have this for logging

$response = ['success' => false, 'message' => 'An error occurred.'];

// Admin & security check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $response['message'] = 'Access Denied: You must be an admin.';
    echo json_encode($response);
    exit;
}

try {
    // Data comes from FormData, so we use $_POST
    $item_name = $_POST['item_name'] ?? '';
    $category = $_POST['category'] ?? null;
    $unit = $_POST['unit'] ?? '';
    $current_stock = (int)($_POST['current_stock'] ?? 0);

    if (empty($item_name) || empty($unit)) {
        $response['message'] = 'Item Name and Unit are required.';
        echo json_encode($response);
        exit;
    }

    $sql = "INSERT INTO inventory_items (item_name, category, unit, current_stock) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("sssi", $item_name, $category, $unit, $current_stock);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Item added successfully!';
        logActivity($conn, $_SESSION['user_id'], 'Inventory Add', "Admin added new item: " . $item_name);
    } else {
        $response['message'] = 'Execute failed: ' . $stmt->error;
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    // You can also log this error to a file
}

echo json_encode($response);
?>