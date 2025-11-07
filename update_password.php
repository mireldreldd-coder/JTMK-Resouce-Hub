<?php
// Set the content type to return JSON responses
header('Content-Type: application/json');

// Include necessary files
include 'db_connect.php';
include 'logger.php'; // This file should also handle session_start()

// Get the posted JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Get token and password from the data
$token = $data['token'] ?? null;
$password = $data['password'] ?? null;

// Validate the input
if (empty($token) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Token and new password are required.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit;
}

// Check if the token is valid and not expired
$sql_check = "SELECT id, email FROM userprofiles WHERE reset_token = ? AND token_expiry > NOW()";
$stmt_check = $conn->prepare($sql_check);

if (!$stmt_check) {
    // **FIXED**: Call logActivity with 4 arguments. Use user_id 0 (system) since we don't know the user yet.
    logActivity($conn, 0, 'DB_ERROR', "Password reset: Failed to prepare statement (token check). Error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    $conn->close();
    exit;
}

$stmt_check->bind_param("s", $token);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows == 0) {
    // Token is invalid or expired
    // **FIXED**: Call logActivity with 4 arguments. Use user_id 0 (system).
    logActivity($conn, 0, 'AUTH_FAILURE', "An invalid or expired password reset token was used. Token: {$token}");
    
    echo json_encode(['success' => false, 'message' => 'This link is invalid or has expired. Please request a new one.']);
    
    $stmt_check->close();
    $conn->close();
    exit;
}

// Token is valid, fetch the user's details
$user = $result->fetch_assoc();
$user_id = $user['id'];
$user_email = $user['email'];
$stmt_check->close();

// Hash the new password securely
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Update the user's password and nullify the token fields
$sql_update = "UPDATE userprofiles SET password_hash = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?";
$stmt_update = $conn->prepare($sql_update);

if (!$stmt_update) {
    // **FIXED**: Call logActivity with 4 arguments. We HAVE $user_id here.
    logActivity($conn, $user_id, 'DB_ERROR', "Password reset: Failed to prepare statement (password update). Error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    $conn->close();
    exit;
}

$stmt_update->bind_param("si", $password_hash, $user_id);

if ($stmt_update->execute()) {
    // Password was updated successfully
    $log_details = "User '{$user_email}' (ID: {$user_id}) successfully reset their password.";
    // **FIXED**: Call logActivity with 4 arguments.
    logActivity($conn, $user_id, 'PASSWORD_RESET_SUCCESS', $log_details);
    
    echo json_encode(['success' => true, 'message' => 'Password has been updated successfully. You can now log in.']);
} else {
    // Failed to update the database
    $log_details = "Failed to update password for user '{$user_email}' (ID: {$user_id}). DB Error: " . $stmt_update->error;
    // **FIXED**: Call logActivity with 4 arguments.
    logActivity($conn, $user_id, 'DB_ERROR', $log_details);
    
    echo json_encode(['success' => false, 'message' => 'Error updating password. Please try again.']);
}

// Close all connections
$stmt_update->close();
$conn->close();
?>