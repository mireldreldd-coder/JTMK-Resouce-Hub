<?php
// --- 0. ERROR HANDLING FIRST ---
ini_set('display_errors', 0); // Do not show errors to the user
ini_set('log_errors', 1); // Log errors
ini_set('error_log', __DIR__ . '/php_error.log'); // Save errors to a log file
error_reporting(E_ALL);

// --- 1. LOAD DEPENDENCIES ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// session_start(); // <-- FIX 1: REMOVED. logger.php handles this.
include 'db_connect.php';
include 'logger.php';
header('Content-Type: application/json');
ob_clean(); // Clean any stray output

// --- 2. DIAGNOSTIC CHECKS ---
$autoload_path = __DIR__ . '/vendor/autoload.php';
$config_path = __DIR__ . '/smtp_config.php';

if (!file_exists($autoload_path)) {
    echo json_encode(['success' => false, 'message' => 'DIAGNOSTIC ERROR: autoload.php does not exist at ' . $autoload_path]);
    exit;
}
if (!is_readable($autoload_path)) {
    echo json_encode(['success' => false, 'message' => 'DIAGNOSTIC ERROR: autoload.php exists but is NOT READABLE. Please fix file permissions.']);
    exit;
}
if (!file_exists($config_path)) {
    echo json_encode(['success' => false, 'message' => 'DIAGNOSTIC ERROR: smtp_config.php does not exist.']);
    exit;
}

require $autoload_path;
include $config_path;

// --- 3. GET DATA & CHECK PERMISSIONS ---
$data = json_decode(file_get_contents('php://input'), true);

$request_id = $data['request_id'] ?? null;
$new_status = $data['new_status'] ?? null;
$remark = $data['remark'] ?? '';
$admin_username = $_SESSION['username'] ?? 'Unknown Admin';

if (!$request_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Request ID and new status are required.']);
    exit;
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// --- 4. START TRANSACTION ---
$conn->begin_transaction();
$request_type = ''; // Initialize

try {
    // --- 5. UPDATE REQUEST STATUS ---
    
    // *** THIS IS THE CHANGED LINE ***
    $sql = "UPDATE requests_main SET request_status = ?, last_updated = NOW(), admin_remark = ?, is_seen_by_user = 0 WHERE request_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $new_status, $remark, $request_id);
    $stmt->execute();
    $stmt->close();

    // --- 6. HANDLE INVENTORY DEDUCTION (if approved and is an Inventory request) ---
    if ($new_status === 'Approved') {
        // First, find out what type of request this is
        $stmt_type = $conn->prepare("SELECT request_type FROM requests_main WHERE request_id = ?");
        $stmt_type->bind_param("i", $request_id);
        $stmt_type->execute();
        $result_type = $stmt_type->get_result();
        if ($result_type->num_rows > 0) {
            $request_type = $result_type->fetch_assoc()['request_type'];
        }
        $stmt_type->close();

        if ($request_type === 'Inventory') {
            // This is an inventory request, so we must deduct stock.
            
            // Get all items for this request
            $sql_get_items = "SELECT item_name, quantity FROM request_items WHERE request_id = ?";
            $stmt_get_items = $conn->prepare($sql_get_items);
            $stmt_get_items->bind_param("i", $request_id);
            $stmt_get_items->execute();
            $items_result = $stmt_get_items->get_result();
            
            $items_to_update = [];
            while ($item = $items_result->fetch_assoc()) {
                $items_to_update[] = $item;
            }
            $stmt_get_items->close();

            // Prepare the update statement
            // Note: This relies on 'item_name' being unique in 'inventory_items'
            $sql_update_stock = "UPDATE inventory_items SET current_stock = current_stock - ? WHERE item_name = ?";
            $stmt_update_stock = $conn->prepare($sql_update_stock);

            foreach ($items_to_update as $item) {
                // We must also check for sufficient stock here to be safe
                $stock_check_sql = "SELECT current_stock FROM inventory_items WHERE item_name = ?";
                $stmt_check = $conn->prepare($stock_check_sql);
                $stmt_check->bind_param("s", $item['item_name']);
                $stmt_check->execute();
                $stock_res = $stmt_check->get_result();
                $stock_data = $stock_res->fetch_assoc();
                $stmt_check->close();

                if (!$stock_data || $stock_data['current_stock'] < $item['quantity']) {
                    // Not enough stock! Roll back and throw an error.
                    throw new Exception("Not enough stock to approve '{$item['item_name']}'. Request aborted.");
                }
                
                // Stock is sufficient, update it
                $stmt_update_stock->bind_param("is", $item['quantity'], $item['item_name']);
                $stmt_update_stock->execute();
            }
            $stmt_update_stock->close();
        }
    }
    
    // --- 7. COMMIT & SEND EMAIL (if all DB operations succeeded) ---
    $conn->commit();
    
} catch (Exception $e) {
    $conn->rollback();
    // Send a specific error message if stock deduction failed
    $log_details = "DB Error updating request {$request_id}: " . $e->getMessage();
    log_activity($conn, 'DB_ERROR', $log_details);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    $conn->close();
    exit;
}

// --- 8. GET USER/REQUEST INFO FOR EMAIL & LOG ---
$email_sent = false;
$email_error = '';

// FIX 3: Changed 'purchase_requests' to 'requests_main' and 'r.user_id' to 'r.requested_by_user_id'
$sql_user = "SELECT u.email, u.name, r.request_type, r.total_cost 
             FROM requests_main r 
             JOIN userprofiles u ON r.requested_by_user_id = u.id 
             WHERE r.request_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $request_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows > 0) {
    $user_data = $result_user->fetch_assoc();
    $user_email = $user_data['email'];
    $user_name = $user_data['name'];
    $request_type = $user_data['request_type']; // Get request type again (safe)
    $total_cost = $user_data['total_cost'];
    
    $stmt_user->close();
    
    // --- 9. LOG THE ACTION (Now that we have all info) ---
    $log_details = "Admin '{$admin_username}' set Request ID {$request_id} ({$request_type} by {$user_email}) to '{$new_status}'.";
    if ($new_status === 'Approved' && $request_type === 'Inventory') {
        $log_details .= " Inventory stock was deducted.";
    }
    log_activity($conn, 'REQUEST_' . strtoupper($new_status), $log_details);

    // --- 10. GET ITEMS FOR EMAIL ---
    // FIX 4: Changed 'price' to 'price_per_unit'
    $sql_items = "SELECT item_name, quantity, price_per_unit FROM request_items WHERE request_id = ?";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $request_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();

    $items_html = "<table border='1' cellpadding='5' cellspacing='0'><tr><th>Item</th><th>Qty</th><th>Price</th></tr>";
    while ($item = $items_result->fetch_assoc()) {
        $items_html .= "<tr><td>" . htmlspecialchars($item['item_name']) . "</td><td>" . $item['quantity'] . "</td><td>RM " . number_format($item['price_per_unit'], 2) . "</td></tr>";
    }
    $items_html .= "</table>";
    $stmt_items->close();

    // --- 11. SEND EMAIL NOTIFICATION ---
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($user_email, $user_name);
        $mail->isHTML(true);

        $email_subject = '';
        $email_body = '';

        if ($new_status == 'Approved') {
            $email_subject = "Your Request (ID: $request_id) has been Approved";
            $email_body = "Hi $user_name,<br><br>Your procurement request (ID: $request_id - $request_type) has been **APPROVED**.<br><br><b>Total Cost:</b> RM " . number_format($total_cost, 2) . "<br><b>Items:</b><br>$items_html<br><br>The request will now be processed.<br><br>Thank you,<br>JTMK Resource Hub System";
        } elseif ($new_status == 'Rejected') {
            $email_subject = "Your Request (ID: $request_id) has been Rejected";
            $email_body = "Hi $user_name,<br><br>We regret to inform you that your procurement request (ID: $request_id - $request_type) has been **REJECTED**.<br><br>";
            if (!empty($remark)) {
                $email_body .= "<b>Admin Remark:</b> " . htmlspecialchars($remark) . "<br><br>";
            }
            $email_body .= "<b>Items in request:</b><br>$items_html<br><br>";
            $email_body .= "Please log in to the JTMK Resource Hub for more details or contact the admin if you have questions.<br><br>Thank you,<br>JTMK Resource Hub System";
        }

        
        if ($email_subject) {
             $mail->Subject = $email_subject;
             $mail->Body = $email_body;
             $mail->SMTPDebug  = 0; // 0 = off, 2 = verbose if debugging
                $mail->Debugoutput = function($str, $level) {
                    file_put_contents(__DIR__ . '/email_debug.log', date('[Y-m-d H:i:s] ') . $str . PHP_EOL, FILE_APPEND);
                };
            $mail->DKIM_domain = 'gmail.com';
            $mail->DKIM_selector = 'google';
            $mail->DKIM_identity = $mail->From;
            $mail->send();
            $email_sent = true;
        }

    } catch (Exception $e) {
        $email_error = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
} else {
    $email_error = 'User not found or has no email, so no notification was sent.';
}

echo json_encode([
    'success' => true, 
    'message' => "Status updated to $new_status.",
    'email_status' => $email_sent,
    'email_error' => $email_error
]);

$conn->close();
?>