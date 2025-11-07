<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); // Start output buffering
header('Content-Type: application/json');
include 'db_connect.php';
include 'logger.php'; 
// session_start() is already in logger.php

// Admin security check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['id'] ?? null;
$admin_id = $_SESSION['user_id'] ?? 0;
$admin_username = $_SESSION['username'] ?? 'Unknown Admin';

if (empty($user_id)) {
    echo json_encode(['success' => false, 'message' => 'User ID is required.']);
    exit;
}

// Get user's email for logging
$stmt_email = $conn->prepare("SELECT email FROM userprofiles WHERE id = ?");
$stmt_email->bind_param("i", $user_id);
$stmt_email->execute();
$result_email = $stmt_email->get_result();
$user_email = 'Unknown User';
if ($result_email->num_rows > 0) {
    $user_email = $result_email->fetch_assoc()['email'];
}
$stmt_email->close();

try {
    // Generate a unique token
    $token = bin2hex(random_bytes(32));
    date_default_timezone_set('UTC'); // Set timezone to UTC
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token is valid for 1 hour

    // Store the token and expiry in the database for the user
    $stmt = $conn->prepare("UPDATE userprofiles SET reset_token = ?, token_expiry = ? WHERE id = ?");
    $stmt->bind_param("ssi", $token, $expiry, $user_id);

    if (!$stmt->execute()) {
        throw new Exception('Error storing reset token: ' . $stmt->error);
    }
    $stmt->close();

    // --- This is the key change ---
    // Determine the base URL dynamically
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
    $host = $_SERVER['HTTP_HOST']; // e.g., localhost:8001
    
    // Get the directory path of the current script
    $path = dirname($_SERVER['PHP_SELF']); // e.g., /jtmkreshub
    
    // Build the final link
    $reset_link = "{$protocol}://{$host}{$path}/reset_password.html?token={$token}"; 

    // Log the successful action
    logActivity($conn, $admin_id, 'PASSWORD_RESET_GENERATED', "Admin '{$admin_username}' generated a reset link for user '{$user_email}'.");
    
    // Return the link in the JSON response
    $response = [
        'success' => true,
        'message' => 'Reset link generated successfully.',
        'link' => $reset_link // This is what the JavaScript expects
    ];

} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

$conn->close();

ob_end_clean(); // Clean any stray errors
echo json_encode($response);
?>