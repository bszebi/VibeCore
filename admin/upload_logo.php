<?php
session_start();
header('Content-Type: application/json');

// Include database connection
require_once('../config/database.php');

try {
    // Check if user is logged in and is an admin
    if (!isset($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit();
    }

    // Check if file was uploaded
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    // Check if company_id is provided
    if (!isset($_POST['company_id'])) {
        throw new Exception('Company ID is required');
    }

    $company_id = intval($_POST['company_id']);
    $file = $_FILES['logo'];

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_size) {
        throw new Exception('File is too large. Maximum size is 5MB.');
    }

    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/company_logos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('company_' . $company_id . '_') . '.' . $extension;
    $target_path = $upload_dir . $filename;

    // Get old logo filename
    $stmt = $conn->prepare("SELECT profile_picture FROM company WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_logo = $result->fetch_assoc()['profile_picture'];
    $stmt->close();

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Update company profile picture in database
    $stmt = $conn->prepare("UPDATE company SET profile_picture = ? WHERE id = ?");
    $stmt->bind_param("si", $filename, $company_id);
    
    if (!$stmt->execute()) {
        // If database update fails, delete the uploaded file
        unlink($target_path);
        throw new Exception("Failed to update database");
    }
    $stmt->close();

    // Delete old logo if it exists and is not the default
    if (!empty($old_logo) && $old_logo !== 'default-company.png' && $old_logo !== $filename) {
        $old_logo_path = $upload_dir . $old_logo;
        if (file_exists($old_logo_path)) {
            unlink($old_logo_path);
        }
    }

    // Log the change
    $log_sql = "INSERT INTO admin_logs (admin_id, action_type, table_name, record_id, old_values, new_values, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($log_sql);
    $old_values = json_encode(['profile_picture' => $old_logo]);
    $new_values = json_encode(['profile_picture' => $filename]);
    $action_type = 'UPDATE';
    $table_name = 'company';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt->bind_param("ississs", 
        $_SESSION['admin_id'],
        $action_type,
        $table_name,
        $company_id,
        $old_values,
        $new_values,
        $ip_address
    );
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Logo uploaded successfully',
        'filename' => $filename
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 