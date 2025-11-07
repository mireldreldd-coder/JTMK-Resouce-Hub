<?php
// mark_request_seen.php
session_start();
// --- FIX 1: Use db_connect.php, not config.php
include 'db_connect.php'; 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get the request ID from the URL
$requestId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$userId = $_SESSION['user_id'];

if ($requestId > 0) {
    // --- FIX 2: Update 'requests_main' table
    // --- FIX 3: Check against 'requested_by_user_id' column
    $sql = "UPDATE requests_main 
            SET is_seen_by_user = 1 
            WHERE request_id = ? AND requested_by_user_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $requestId, $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
}
$conn->close();
?>