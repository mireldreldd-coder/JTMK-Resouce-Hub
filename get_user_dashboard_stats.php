<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$stats = [
    'total_requests' => 0,
    'pending_requests' => 0,
    'approved_monthly' => 0
];

// 1. Get My Total Requests (All time)
$sql_total = "SELECT COUNT(*) as count FROM requests_main WHERE requested_by_user_id = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("i", $user_id);
if ($stmt_total->execute()) {
    $result = $stmt_total->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['total_requests'] = $row['count'];
    }
}
$stmt_total->close();

// 2. Get Pending Review (Current)
$sql_pending = "SELECT COUNT(*) as count FROM requests_main WHERE requested_by_user_id = ? AND request_status = 'Pending'";
$stmt_pending = $conn->prepare($sql_pending);
$stmt_pending->bind_param("i", $user_id);
if ($stmt_pending->execute()) {
    $result = $stmt_pending->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['pending_requests'] = $row['count'];
    }
}
$stmt_pending->close();

// 3. Get Approved This Month
$sql_approved = "SELECT COUNT(*) as count FROM requests_main 
                 WHERE requested_by_user_id = ? 
                 AND request_status = 'Approved'
                 AND MONTH(last_updated) = MONTH(CURRENT_DATE())
                 AND YEAR(last_updated) = YEAR(CURRENT_DATE())";
$stmt_approved = $conn->prepare($sql_approved);
$stmt_approved->bind_param("i", $user_id);
if ($stmt_approved->execute()) {
    $result = $stmt_approved->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['approved_monthly'] = $row['count'];
    }
}
$stmt_approved->close();

$conn->close();

header('Content-Type: application/json');
echo json_encode($stats);
?>