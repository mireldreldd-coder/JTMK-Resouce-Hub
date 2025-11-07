<?php
ob_start(); // <-- ADD THIS LINE
header('Content-Type: application/json');
include 'db_connect.php';
include 'logger.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => 'An error occurred.']; // <-- Define default response

// Optional: Add admin-only security check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    $response['message'] = 'Access denied.'; // <-- Set message
    ob_end_clean(); // <-- ADD THIS LINE
    echo json_encode($response);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? '';
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$role = $data['role'] ?? '';
$lab_role = $data['lab_role'] ?? '';

// Validate data
if (empty($id) || empty($name) || empty($email) || empty($role)) {
    $response['message'] = 'All fields are required.'; // <-- Set message
    ob_end_clean(); // <-- ADD THIS LINE
    echo json_encode($response);
    exit;
}

$old_data = null;
$stmt_old = $conn->prepare("SELECT email, role, lab_role FROM userprofiles WHERE id = ?");
$stmt_old->bind_param("i", $id);
if ($stmt_old->execute()) {
    $result = $stmt_old->get_result();
    $old_data = $result->fetch_assoc();
}
$stmt_old->close();

// Prepare the SQL statement
$sql = "UPDATE userprofiles SET name = ?, email = ?, role = ?, lab_role = ? WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $response = ['success' => false, 'error' => 'db_prepare', 'message' => 'Failed to prepare statement.'];
    $conn->close();
    ob_end_clean(); // <-- ADD THIS LINE
    echo json_encode($response);
    exit;
}

$stmt->bind_param("ssssi", $name, $email, $role, $lab_role, $id);

// Execute and check for errors
try {
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0 && $old_data) {
            $details = "Admin '{$_SESSION['username']}' updated user '{$old_data['email']}'.";
            if ($old_data['role'] != $role) {
                $details .= " Changed role from '{$old_data['role']}' to '{$role}'.";
            }
            if ($old_data['lab_role'] != $lab_role) {
                $details .= " Changed lab role from '{$old_data['lab_role']}' to '{$lab_role}'.";
            }
            log_activity($conn, 'USER_UPDATED', $details);
        }
        $response = ['success' => true, 'message' => 'User updated successfully!'];
    } else {
        $response = ['success' => false, 'error' => 'db_error', 'message' => $stmt->error];
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        $response = ['success' => false, 'error' => 'duplicate', 'message' => 'This email address is already in use by another account.'];
    } else {
        $response = ['success' => false, 'error' => 'db_exception', 'message' => $e->getMessage()];
    }
}

$stmt->close();
$conn->close();

ob_end_clean(); // <-- ADD THIS LINE
echo json_encode($response);
?>