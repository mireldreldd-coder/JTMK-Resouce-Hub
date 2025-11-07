<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('logActivity')) {
    /**
     * Logs an activity to the database.
     *
     * @param mysqli $conn The active database connection.
     * @param int $user_id The ID of the user performing the action.
     * @param string $action The type of action (e.g., 'Login', 'Inventory Update').
     * @param string $details A description of the action.
     * @return bool True on success, false on failure.
     */
    function logActivity($conn, $user_id, $action, $details) {
        if (!$conn || $conn->connect_error) {
            error_log('Logger Error: Invalid DB connection passed to logActivity.');
            return false;
        }

        try {
            // Get username from session if available, otherwise just use the ID
            $username = $_SESSION['username'] ?? 'UserID: ' . $user_id;
            $log_details = "User '{$username}' | {$details}";

            $sql = "INSERT INTO activity_logs (user_id, username, action, details) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                error_log('Logger Error: Prepare failed: ' . $conn->error);
                return false;
            }

            $stmt->bind_param("isss", $user_id, $username, $action, $log_details);
            
            if (!$stmt->execute()) {
                error_log('Logger Error: Execute failed: ' . $stmt->error);
                $stmt->close();
                return false;
            }

            $stmt->close();
            return true;

        } catch (Exception $e) {
            error_log('Logger Exception: ' . $e->getMessage());
            return false;
        }
    }
}

// --- THIS IS THE FIX ---
// Create an alias `log_activity` that points to `logActivity`
// This makes your old code work without having to edit every file.
if (!function_exists('log_activity')) {
    function log_activity($conn, $action, $details) {
        // Get the user ID from the session
        $user_id = $_SESSION['user_id'] ?? 0; // 0 for 'system' or 'unknown'
        
        // Call the main function with the correct parameters
        return logActivity($conn, $user_id, $action, $details);
    }
}
?>