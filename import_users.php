<?php
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

// Get the JSON payload sent from the JavaScript
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data) || !is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'No user data received.']);
    exit;
}

$conn->begin_transaction(); 
$imported = 0;
$failed = 0;
$failed_emails = [];

try {
    $sql = "INSERT INTO userprofiles (name, email, password_hash, role, lab_role) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    foreach ($data as $user) {
        $name = $user['name'] ?? '';
        $email = $user['email'] ?? '';
        $password = $user['password'] ?? '';
        $role = $user['role'] ?? 'user';
        $lab_role = $user['lab_role'] ?? '';

        // Validate essential data
        if (empty($name) || empty($email) || empty($password)) {
            $failed++;
            $failed_emails[] = $email . " (missing data)";
            continue;
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt->bind_param("sssss", $name, $email, $password_hash, $role, $lab_role);
        
        if ($stmt->execute()) {
            $imported++;
        } else {
            $failed++;
            $failed_emails[] = $email . " (db error)";
        }
    }

    $conn->commit();
    
    $log_details = "Admin '{$_SESSION['username']}' imported users via CSV. Success: {$imported}, Failed: {$failed}.";
    if ($failed > 0) {
        $log_details .= " Failed emails: " . implode(', ', $failed_emails);
    }
    logActivity($conn, $_SESSION['user_id'], 'USER_IMPORT', $log_details); // Use logActivity with user ID

    $response = [
        'success' => true, 
        'message' => "Import complete. Added: {$imported}. Failed: {$failed}.",
        'imported' => $imported,
        'failed' => $failed
    ];

} catch (Exception $e) {
    $conn->rollback();
    $response = ['success' => false, 'message' => $e->getMessage()];
}

$stmt->close();
$conn->close();

ob_end_clean(); // Clean any stray errors
echo json_encode($response);
?>