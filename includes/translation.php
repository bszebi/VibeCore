<?php
if (!function_exists('translate')) {
    function translate($key) {
        // Get the current language from session or default to Hungarian
        $current_lang = $_SESSION['language'] ?? 'hu';
        
        // Define the languages directory path
        $languages_dir = __DIR__ . '/languages';
        
        // Load language file if it exists
        $language_file = $languages_dir . '/' . $current_lang . '.php';
        static $translations = [];
        
        // Only load translations once per language
        if (!isset($translations[$current_lang])) {
            if (file_exists($language_file)) {
                // Load translations from file
                $translations[$current_lang] = require $language_file;
            } else {
                // If language file doesn't exist, create empty array
                $translations[$current_lang] = [];
                error_log("Language file not found: " . $language_file);
            }
        }
        
        // Return translation if it exists, otherwise return the key
        return isset($translations[$current_lang][$key]) ? $translations[$current_lang][$key] : $key;
    }
}

function getTranslationFromSource($text, $language) {
    // This is where you would implement the actual translation lookup
    // For now, return null to fall back to original text
    return null;
}

// Keep these functions here
function setLanguage($lang) {
    $_SESSION['language'] = $lang;
    // Clear the static translations cache to force reload
    global $translations;
    $translations = [];
}

function getCurrentLanguage() {
    return $_SESSION['language'] ?? 'hu'; // Default to Hungarian
} 