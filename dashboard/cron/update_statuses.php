<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log start of execution
error_log("Starting status update cron job at " . date('Y-m-d H:i:s'));

try {
    // Include the status update script
    require_once '../update_status_from_calendar.php';
    
    // Log successful completion
    error_log("Status update cron job completed successfully at " . date('Y-m-d H:i:s'));
} catch (Exception $e) {
    // Log any errors
    error_log("Error in status update cron job: " . $e->getMessage());
} 