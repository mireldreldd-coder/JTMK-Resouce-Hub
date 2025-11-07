<?php
ob_start();
session_start();
header('Content-Type: application/json');

require 'db_connect.php';
require 'logger.php';

$response = ['success' => false, 'message' => 'Import failed.', 'imported' => 0, 'failed' => 0];

// Admin & security check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $response['message'] = 'Access Denied: You must be an admin.';
    echo json_encode($response);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] != UPLOAD_ERR_OK) {
    $response['message'] = 'No file uploaded or an upload error occurred.';
    echo json_encode($response);
    exit;
}

$csvFile = $_FILES['csvFile']['tmp_name'];
$importedCount = 0;
$failedCount = 0;
$failedLines = [];

try {
    // Prepare the SQL statement once
    $sql = "INSERT INTO inventory_items (item_name, category, unit, current_stock) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Database prepare statement failed: ' . $conn->error);
    }

    // Open the CSV file
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        // Get headers (and skip them)
        $headers = fgetcsv($handle, 1000, ",");
        if ($headers === false) {
            throw new Exception('Could not read CSV header.');
        }

        // Validate headers
        $expectedHeaders = ['item_name', 'category', 'unit', 'current_stock'];
        if ($headers !== $expectedHeaders) {
             $response['message'] = 'Invalid CSV headers. Expected: item_name,category,unit,current_stock';
             echo json_encode($response);
             fclose($handle);
             exit;
        }

        $line = 2; // Start from line 2 (after header)
        
        // Loop through each row in the CSV
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) == 4) {
                // Assign data from CSV
                $item_name = $data[0];
                $category = $data[1];
                $unit = $data[2];
                $current_stock = (int)$data[3];

                // Basic validation
                if (!empty($item_name) && !empty($unit)) {
                    // Bind parameters and execute
                    $stmt->bind_param("sssi", $item_name, $category, $unit, $current_stock);
                    if ($stmt->execute()) {
                        $importedCount++;
                    } else {
                        $failedCount++;
                        $failedLines[] = $line;
                    }
                } else {
                    $failedCount++;
                    $failedLines[] = $line; // Missing required data
                }
            } else {
                $failedCount++;
                $failedLines[] = $line; // Incorrect column count
            }
            $line++;
        }
        fclose($handle);
    } else {
        throw new Exception('Could not open the uploaded CSV file.');
    }

    $stmt->close();

    $response['success'] = true;
    $response['imported'] = $importedCount;
    $response['failed'] = $failedCount;
    $response['message'] = "Import complete: $importedCount items added, $failedCount items failed.";
    if ($failedCount > 0) {
        $response['message'] .= " Failed lines: " . implode(', ', $failedLines);
    }
    
    logActivity($conn, $_SESSION['user_id'], 'Inventory CSV Import', $response['message']);

} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

$conn->close();
ob_end_clean();
echo json_encode($response);
?>