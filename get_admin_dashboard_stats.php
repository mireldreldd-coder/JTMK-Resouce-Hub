<?php
session_start();
include 'db_connect.php';

// Security Check: Ensure user is logged in AND is an admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

$stats = [
    'total_users' => 0,
    'total_pending' => 0,
    'approved_monthly' => 0
];

// 1. Get Total Users (excluding admin)
$sql_users = "SELECT COUNT(*) as count FROM userprofiles WHERE role = 'user'";
$stmt_users = $conn->prepare($sql_users);
if ($stmt_users->execute()) {
    $result = $stmt_users->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['total_users'] = $row['count'];
    }
}
$stmt_users->close();

// 2. Get Total Pending (All Users)
$sql_pending = "SELECT COUNT(*) as count FROM requests_main WHERE request_status = 'Pending'";
$stmt_pending = $conn->prepare($sql_pending);
if ($stmt_pending->execute()) {
    $result = $stmt_pending->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['total_pending'] = $row['count'];
    }
}
$stmt_pending->close();

// 3. Get Approved This Month (All Users)
$sql_approved = "SELECT COUNT(*) as count FROM requests_main 
                 WHERE request_status = 'Approved'
                 AND MONTH(last_updated) = MONTH(CURRENT_DATE())
                 AND YEAR(last_updated) = YEAR(CURRENT_DATE())";
$stmt_approved = $conn->prepare($sql_approved);
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
