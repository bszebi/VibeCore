<?php
/**
 * Error handler for API endpoints
 * This file should be included at the beginning of all API endpoints
 * to ensure proper error handling and JSON responses
 */

// Start output buffering to catch any unwanted output
ob_start();

// Set error handler to catch all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the error
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    
    // Clear any output
    ob_clean();
    
    // Set JSON content type
    header('Content-Type: application/json');
    
    // Return JSON error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $errstr,
        'debug' => [
            'error' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'type' => $errno
        ]
    ]);
    
    // End output buffering and flush
    ob_end_flush();
    exit;
});

// Set exception handler to catch all exceptions
set_exception_handler(function($exception) {
    // Log the exception
    error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    // Clear any output
    ob_clean();
    
    // Set JSON content type
    header('Content-Type: application/json');
    
    // Return JSON error response
    echo json_encode([
        'success' => false,
        'message' => 'An exception occurred: ' . $exception->getMessage(),
        'debug' => [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]
    ]);
    
    // End output buffering and flush
    ob_end_flush();
    exit;
});

// Function to ensure proper JSON response
function ensureJsonResponse($data) {
    // Clear any output
    ob_clean();
    
    // Set JSON content type
    header('Content-Type: application/json');
    
    // Return JSON response
    echo json_encode($data);
    
    // End output buffering and flush
    ob_end_flush();
    exit;
}
?> 