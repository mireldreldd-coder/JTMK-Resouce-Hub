<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$count = 0;
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

if ($user_role === 'admin') {
    // Admin gets the count of ALL pending requests
    // FIX: Changed 'purchase_requests' to 'requests_main'
    $sql = "SELECT COUNT(*) as pending_count FROM requests_main WHERE request_status = 'Pending'";
    $stmt = $conn->prepare($sql);
} else {
    // A regular user gets the count of ONLY THEIR pending requests
    // FIX: Changed 'purchase_requests' to 'requests_main'
    $sql = "SELECT COUNT(*) as pending_count FROM requests_main WHERE request_status = 'Pending' AND requested_by_user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $count = $result->fetch_assoc()['pending_count'];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode(['count' => $count]);
?>