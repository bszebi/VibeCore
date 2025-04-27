<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1); // Enable error display for testing

// Set JSON content type
header('Content-Type: application/json');

// Direct database connection - avoid any complex includes or dependencies
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "vizsgaremek";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'error' => 'Adatbázis kapcsolódási hiba: ' . $conn->connect_error
    ]));
}

// Validate session
session_start();
if (!isset($_SESSION['admin_id'])) {
    die(json_encode([
        'success' => false,
        'error' => 'Nincs bejelentkezve'
    ]));
}

// Get JSON data
$input = file_get_contents('php://input');
if (empty($input)) {
    die(json_encode([
        'success' => false,
        'error' => 'Nincs adat elküldve'
    ]));
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die(json_encode([
        'success' => false,
        'error' => 'Érvénytelen JSON adat: ' . json_last_error_msg()
    ]));
}

// Validate required fields
if (!isset($data['company_id']) || !isset($data['field']) || !isset($data['value'])) {
    die(json_encode([
        'success' => false,
        'error' => 'Hiányzó kötelező mezők'
    ]));
}

// Sanitize inputs
$company_id = intval($data['company_id']);
$field = $data['field'];
$value = trim($data['value']);

// Log for debugging
error_log("Update Request - ID: $company_id, Field: $field, Value: $value");

// List of allowed fields to update
$allowed_fields = ['company_name', 'company_address', 'company_email', 'company_telephone'];

// Check if the field is allowed to be updated
if (!in_array($field, $allowed_fields)) {
    die(json_encode([
        'success' => false,
        'error' => 'Érvénytelen mező: ' . $field
    ]));
}

// Get old values for logging
$old_value_query = $conn->query("SELECT `$field` FROM company WHERE id = $company_id");
if ($old_value_query->num_rows === 0) {
    die(json_encode([
        'success' => false,
        'error' => 'A cég nem található ezzel az azonosítóval: ' . $company_id
    ]));
}

$old_value_row = $old_value_query->fetch_assoc();
$old_value = $old_value_row[$field];

// Escape values for direct query
$escaped_value = $conn->real_escape_string($value);

// Perform direct update with simple query
$update_query = "UPDATE company SET `$field` = '$escaped_value' WHERE id = $company_id";
$update_result = $conn->query($update_query);

if ($update_result === false) {
    error_log("SQL Update Error: " . $conn->error . " for query: $update_query");
    die(json_encode([
        'success' => false,
        'error' => 'Adatbázis frissítési hiba: ' . $conn->error
    ]));
}

// Verify the update was successful
$verify_query = $conn->query("SELECT `$field` FROM company WHERE id = $company_id");
$verify_row = $verify_query->fetch_assoc();
$new_value = $verify_row[$field];

if ($new_value === $value || ($old_value === $value)) {
    // Log the successful update if different
    if ($old_value !== $value) {
        try {
            // Debug information
            error_log("Starting admin_logs logging for company #$company_id, field: $field, old: $old_value, new: $value");
            
            // Ellenőrizzük az admin_logs tábla szerkezetét
            $check_table = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'action_type'");
            
            if ($check_table && $check_table->num_rows > 0) {
                // Az admin_logs tábla megfelel az adatbázisban megadott struktúrának
                error_log("Admin_logs table has action_type column, using new structure");
                
                $admin_id = (int)$_SESSION['admin_id'];
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $action_type = 'UPDATE';
                $table_name = 'company';
                $record_id = $company_id;
                $current_date = date('Y-m-d H:i:s');
                
                // Formázott JSON adatok a könnyebb olvashatóság érdekében
                $old_values = json_encode([$field => $old_value], JSON_UNESCAPED_UNICODE);
                $new_values = json_encode([$field => $value], JSON_UNESCAPED_UNICODE);
                
                // Prepared statement használata SQL injection elleni védelem miatt
                $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, table_name, record_id, old_values, new_values, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                if (!$log_stmt) {
                    error_log("Prepare statement error: " . $conn->error);
                } else {
                    $bind_result = $log_stmt->bind_param("isssssss", $admin_id, $action_type, $table_name, $record_id, $old_values, $new_values, $ip_address, $current_date);
                    
                    if (!$bind_result) {
                        error_log("Bind param error: " . $log_stmt->error);
                    } else {
                        $execute_result = $log_stmt->execute();
                        
                        if (!$execute_result) {
                            error_log("Execute error: " . $log_stmt->error);
                        } else {
                            error_log("Admin log successfully inserted using new structure");
                        }
                    }
                    
                    $log_stmt->close();
                }
            } else {
                error_log("Admin_logs table does not have action_type column, checking for admin_id");
                
                // Régebbi admin_logs szerkezet (kompatibilitási mód)
                $check_table = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'admin_id'");
                
                if ($check_table && $check_table->num_rows > 0) {
                    // Az admin_id oszlop létezik
                    error_log("Admin_logs table has admin_id column, using legacy structure");
                    
                    $admin_id = (int)$_SESSION['admin_id'];
                    $current_date = date('Y-m-d H:i:s');
                    $details = "Admin #$admin_id, cég név változtatás, $old_value, $value, $current_date";
                    $escaped_details = $conn->real_escape_string($details);
                    
                    $log_query = "INSERT INTO admin_logs (admin_id, action, details) VALUES ($admin_id, 'company_update_$field', '$escaped_details')";
                    $log_result = $conn->query($log_query);
                    
                    if (!$log_result) {
                        error_log("Query error with admin_id: " . $conn->error . " for query: $log_query");
                    } else {
                        error_log("Admin log successfully inserted using admin_id structure");
                    }
                } else {
                    // Ellenőrizzük, hogy van-e user_id oszlop helyette
                    error_log("Admin_logs table does not have admin_id column, checking for user_id");
                    
                    $check_user_id = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'user_id'");
                    
                    if ($check_user_id && $check_user_id->num_rows > 0) {
                        // A user_id oszlop létezik
                        error_log("Admin_logs table has user_id column, using fallback structure");
                        
                        $admin_id = (int)$_SESSION['admin_id'];
                        $current_date = date('Y-m-d H:i:s');
                        $details = "Admin #$admin_id, cég $field változtatás, $old_value, $value, $current_date";
                        $escaped_details = $conn->real_escape_string($details);
                        
                        // Próbáljuk az admin_id-t használni, ha létezik, ha nem, akkor a user_id-t
                        $check_admin_id = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'admin_id'");
                        if ($check_admin_id && $check_admin_id->num_rows > 0) {
                            $log_query = "INSERT INTO admin_logs (admin_id, action, details) VALUES ($admin_id, 'company_update_$field', '$escaped_details')";
                        } else {
                            $log_query = "INSERT INTO admin_logs (user_id, action, details) VALUES ($admin_id, 'company_update_$field', '$escaped_details')";
                        }
                        $log_result = $conn->query($log_query);
                        
                        if (!$log_result) {
                            error_log("Query error with user_id: " . $conn->error . " for query: $log_query");
                        } else {
                            error_log("Admin log successfully inserted using user_id structure");
                        }
                    } else {
                        // Megnézzük, létezik-e az admin_logs tábla egyáltalán
                        $table_check = $conn->query("SHOW TABLES LIKE 'admin_logs'");
                        if (!$table_check || $table_check->num_rows === 0) {
                            error_log("admin_logs table does not exist in the database");
                        } else {
                            error_log("admin_logs table exists but has no compatible columns (action_type, admin_id, or user_id)");
                            
                            // Megnézzük, milyen oszlopai vannak az admin_logs táblának
                            $col_result = $conn->query("SHOW COLUMNS FROM admin_logs");
                            $columns = [];
                            while ($col = $col_result->fetch_assoc()) {
                                $columns[] = $col['Field'];
                            }
                            error_log("Available columns in admin_logs: " . implode(', ', $columns));
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Naplózási hiba - ezt csak naplózzuk, de a frissítést sikeresnek tekintjük
            error_log("Naplózási hiba: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        
        error_log("Company update successful. Old value: $old_value, New value: $value");
    } else {
        error_log("Company update skipped, values are the same: $value");
    }
    
    // Successful update
    echo json_encode([
        'success' => true,
        'value' => $value,
        'message' => 'A cég adatai sikeresen frissítve.',
        'affected_rows' => $conn->affected_rows
    ]);
} else {
    error_log("Update verification failed. Expected: $value, Got: $new_value");
    echo json_encode([
        'success' => false,
        'error' => 'A frissítés nem sikerült. Az érték nem változott az adatbázisban. Várható: '.$value.', Kapott: '.$new_value,
        'affected_rows' => $conn->affected_rows
    ]);
}

// Close the connection
$conn->close();
?> 