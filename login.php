<?php
session_start(); // Start a session to remember the user
include 'logger.php';
include 'db_connect.php';

// Set header *before* any output
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'];
$password_attempt = $data['password'];

// Find the user by their email
$stmt = $conn->prepare("SELECT * FROM userprofiles WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$response = array();

if ($result->num_rows === 1) {
    // User found, now check the password
    $user = $result->fetch_assoc();
    
    if (password_verify($password_attempt, $user['password_hash'])) {
        // Password is correct!
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['username'] = $user['email']; // For logger consistency

        $response['success'] = true;
        $response['name'] = $user['name'];
        $response['email'] = $user['email'];
        $response['role'] = $user['role']; // This is what localStorage will save

        // --- Log the successful login ---
        $log_details = "User '{$user['email']}' logged in successfully.";
        log_activity($conn, 'USER_LOGIN_SUCCESS', $log_details);
        
    } else {
        // Wrong password
        $response['success'] = false;
        $response['message'] = "Invalid email or password.";
        
        // --- Log the failed login ---
        $log_details = "Failed login attempt for username: '{$email}'.";
        log_activity($conn, 'USER_LOGIN_FAILED', $log_details);
    }
} else {
    // No user found with that email
    $response['success'] = false;
    $response['message'] = "Invalid email or password.";
    
    // --- Log the failed login ---
    $log_details = "Failed login attempt for non-existent username: '{$email}'.";
    log_activity($conn, 'USER_LOGIN_FAILED', $log_details);
}

// Send the one, final JSON response
echo json_encode($response);

$stmt->close();
$conn->close();
?>
