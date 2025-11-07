<?php
ob_start(); // <-- ADD THIS LINE
require 'db_connect.php'; // Connect to the database
include 'logger.php'; // 1. INCLUDE THE LOGGER
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Start session to check for admin role
}

// Set header to JSON *before* any output
header('Content-Type: application/json');

// --- Security Check: Only Admins can delete users ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    $response = ['success' => false, 'message' => 'Access denied. You must be an admin to delete users.'];
    ob_end_clean(); // <-- ADD THIS LINE
    echo json_encode($response);
    exit;
}

// Check if $conn exists after include
if (!isset($conn) || !$conn) {
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Database connection failed. Check db_connect.php.'];
    ob_end_clean(); // <-- ADD THIS LINE
    echo json_encode($response);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = isset($data['id']) ? $data['id'] : null; // Safely get ID

$response = array();

if (empty($userId)) {
    http_response_code(400); // 400 Bad Request
    $response['success'] = false;
    $response['message'] = "User ID is required.";
} else {
    
    $email_of_deleted_user = 'Unknown';
    $stmt_get = $conn->prepare("SELECT email FROM userprofiles WHERE id = ?");
    $stmt_get->bind_param("i", $userId);
    if ($stmt_get->execute()) {
        $result = $stmt_get->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $email_of_deleted_user = $user_data['email'];
        }
    }
    $stmt_get->close();

    try {
        $conn->begin_transaction();

        $stmt_items = $conn->prepare("DELETE FROM request_items WHERE request_id IN (SELECT request_id FROM requests_main WHERE requested_by_user_id = ?)");
        $stmt_items->bind_param("i", $userId);
        $stmt_items->execute();
        $stmt_items->close();

        $stmt_main = $conn->prepare("DELETE FROM requests_main WHERE requested_by_user_id = ?");
        $stmt_main->bind_param("i", $userId);
        $stmt_main->execute();
        $stmt_main->close();

        $stmt_user = $conn->prepare("DELETE FROM userprofiles WHERE id = ?");
        $stmt_user->bind_param("i", $userId);
        $stmt_user->execute();

        if ($stmt_user->affected_rows > 0) {
            $conn->commit();
            
            $log_details = "Admin '{$_SESSION['username']}' deleted user: '{$email_of_deleted_user}' (ID: {$userId}) and all related request data.";
            log_activity($conn, 'USER_DELETED', $log_details);
            
            $response['success'] = true;
            $response['message'] = "User and all related data (requests, items) deleted successfully.";

        } else {
            $conn->rollback(); 
            $response['success'] = false; 
            $response['message'] = "User not found or already deleted.";
        }
        $stmt_user->close();

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $response['success'] = false;
        $response['message'] = "Database error (Transaction Failed): " . $e->getMessage();
        http_response_code(500); 
    }
}

$conn->close();

ob_end_clean(); // <-- ADD THIS LINE
echo json_encode($response);
?>