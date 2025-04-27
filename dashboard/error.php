<?php
require_once '../includes/config.php';
require_once '../includes/layout/header.php';

$error_message = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'unauthorized':
            $error_message = 'Nincs jogosultsága az oldal megtekintéséhez!';
            break;
        default:
            $error_message = 'Ismeretlen hiba történt!';
    }
}
?>

<div class="error-container">
    <h1>Hiba!</h1>
    <p><?php echo htmlspecialchars($error_message); ?></p>
    <a href="/vizsga_oldal/dashboard/home.php" class="back-button">Vissza a főoldalra</a>
</div>

<style>
    .error-container {
        max-width: 600px;
        margin: 100px auto;
        text-align: center;
        padding: 2rem;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    }

    .error-container h1 {
        color: #e74c3c;
        margin-bottom: 1rem;
    }

    .back-button {
        display: inline-block;
        margin-top: 1rem;
        padding: 10px 20px;
        background: #3498db;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        transition: background 0.3s;
    }

    .back-button:hover {
        background: #2980b9;
    }
</style> 