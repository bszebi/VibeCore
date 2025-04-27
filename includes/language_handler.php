<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default language
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'hu';
}

// Handle language change
if (isset($_GET['lang'])) {
    $allowed_languages = ['hu', 'en'];
    if (in_array($_GET['lang'], $allowed_languages)) {
        $_SESSION['language'] = $_GET['lang'];
    }
}

// Create languages directory if it doesn't exist
$languages_dir = __DIR__ . '/languages';
if (!file_exists($languages_dir)) {
    mkdir($languages_dir, 0777, true);
}

// Load language file
$language_file = $languages_dir . '/' . $_SESSION['language'] . '.php';
if (!file_exists($language_file)) {
    // Create default Hungarian language file if it doesn't exist
    if ($_SESSION['language'] === 'hu') {
        file_put_contents($language_file, '<?php
$lang = [
    "welcome" => "Üdvözöljük",
    "menu" => "Menü",
    // Add more translations as needed
];
?>');
    }
}

if (file_exists($language_file)) {
    require_once $language_file;
} else {
    die('Language file not found!');
}
?> 