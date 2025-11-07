<?php
session_start();
include 'db_connect.php';

// Optional: Add admin-only security check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

$sql = "SELECT id, name, email, role, lab_role, created_at FROM userprofiles";
$result = $conn->query($sql);

$users = array();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($users);
?>