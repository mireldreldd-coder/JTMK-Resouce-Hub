<?php
header('Content-Type: application/json');
include 'db_connect.php';
session_start();

// Admin-only security
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$logs = [];
$sql = "SELECT id, timestamp, username, action, details, ip_address 
        FROM activity_logs 
        ORDER BY timestamp DESC 
        LIMIT 200"; // Get the last 200 logs

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

echo json_encode($logs);
$conn->close();
?>