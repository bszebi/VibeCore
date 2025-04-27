<?php
if (!function_exists('translate')) {
    function translate($key, $default = null) {
        global $lang;
        
        // If language array is not loaded, try to load it
        if (!isset($lang)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Default language is Hungarian
            if (!isset($_SESSION['language'])) {
                $_SESSION['language'] = 'hu';
            }
            
            // Load language file
            $language_file = __DIR__ . '/languages/' . $_SESSION['language'] . '.php';
            if (file_exists($language_file)) {
                $lang = require_once $language_file;
            } else {
                return $default ?? $key;
            }
        }
        
        // Return translation if exists, otherwise return default or key
        return isset($lang[$key]) ? $lang[$key] : ($default ?? $key);
    }
}

// Shorthand function for translate()
if (!function_exists('t')) {
    function t($key, $default = null) {
        return translate($key, $default);
    }
} 