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
    // Data is sent as JSON, so we read from php://input
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);

    if (empty($id)) {
        $response['message'] = 'Invalid Item ID.';
        echo json_encode($response);
        exit;
    }

    $sql = "DELETE FROM inventory_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Item deleted successfully!';
        logActivity($conn, $_SESSION['user_id'], 'Inventory Delete', "Admin deleted item ID: " . $id);
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