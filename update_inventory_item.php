<?php
session_start();
header('Content-Type: application/json');

require 'db_connect.php';
require 'logger.php';

$response = ['success' => false, 'message' => 'An error occurred.'];

// Admin & security check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $response['message'] = 'Access Denied: You must be an admin.';
    echo json_encode($response);
    exit;
}

try {
    // Data comes from FormData, so we use $_POST
    $id = (int)($_POST['itemId'] ?? 0);
    $item_name = $_POST['item_name'] ?? '';
    $category = $_POST['category'] ?? null;
    $unit = $_POST['unit'] ?? '';
    $current_stock = (int)($_POST['current_stock'] ?? 0);

    if (empty($id) || empty($item_name) || empty($unit)) {
        $response['message'] = 'ID, Item Name, and Unit are required.';
        echo json_encode($response);
        exit;
    }

    $sql = "UPDATE inventory_items SET item_name = ?, category = ?, unit = ?, current_stock = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("sssii", $item_name, $category, $unit, $current_stock, $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Item updated successfully!';
            logActivity($conn, $_SESSION['user_id'], 'Inventory Update', "Admin updated item ID: " . $id);
        } else {
            $response['message'] = 'No changes were made or item not found.';
            $response['success'] = true; // Still a successful operation, just no change
        }
    } else {
        $response['message'] = 'Execute failed: ' . $stmt->error;
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>