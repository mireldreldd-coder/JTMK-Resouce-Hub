<?php
ob_start(); // <-- ADD THIS LINE
// Set header to JSON *before* any output
header('Content-Type: application/json');
include 'logger.php';
include 'db_connect.php';

// session_start() is already in logger.php, but it's safe to call again.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => 'An error occurred.'];

// Get the data from the JavaScript fetch() call
$data = json_decode(file_get_contents('php://input'), true);

$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$role = $data['role'] ?? 'user';
$lab_role = $data['lab_role'] ?? '';

if (empty($name) || empty($email) || empty($password) || empty($role)) {
    $response['message'] = "All fields are required.";
    ob_end_clean(); // <-- ADD THIS LINE
    echo json_encode($response);
    exit;
}

// --- IMPORTANT: Hash the password ---
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Prepare the SQL statement to prevent SQL injection
$sql = "INSERT INTO userprofiles (name, email, password_hash, role, lab_role) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $response = ['success' => false, 'error' => 'db_prepare', 'message' => 'Failed to prepare statement.'];
    $conn->close();
    ob_end_clean(); // <-- ADD THIS LINE
    echo json_encode($response);
    exit;
}

$stmt->bind_param("sssss", $name, $email, $password_hash, $role, $lab_role);

// Execute and check for errors
try {
    if ($stmt->execute()) {
        $log_details = "Admin '{$_SESSION['username']}' created new user: '{$name}' ({$email}) with role '{$role}'.";
        log_activity($conn, 'USER_CREATED', $log_details);
        $response = ['success' => true, 'message' => 'User added successfully!'];
    } else {
        $response = ['success' => false, 'error' => 'db_error', 'message' => $stmt->error];
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        $response = ['success' => false, 'error' => 'duplicate', 'message' => 'This email address is already in use.'];
    } else {
        $response = ['success' => false, 'error' => 'db_exception', 'message' => $e->getMessage()];
    }
}

$stmt->close();
$conn->close();

ob_end_clean(); // <-- ADD THIS LINE
echo json_encode($response);
?>