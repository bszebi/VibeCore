<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => translate('Nincs bejelentkezve')]);
    exit;
}

// Get the tool ID from the request
$toolId = isset($_GET['tool_id']) ? intval($_GET['tool_id']) : 0;

if (!$toolId) {
    echo json_encode(['success' => false, 'message' => translate('Érvénytelen eszköz azonosító')]);
    exit;
}

try {
    // Get the tool's name to find alternatives
    $stmt = $pdo->prepare("
        SELECT s.name 
        FROM stuffs s 
        WHERE s.id = ?
    ");
    $stmt->execute([$toolId]);
    $tool = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tool) {
        echo json_encode(['success' => false, 'message' => translate('Az eszköz nem található')]);
        exit;
    }

    // Find alternative tools with the same name that are in "Raktáron" status
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.qr_code
        FROM stuffs s
        WHERE s.name = ?
        AND s.id != ?
        AND s.id NOT IN (
            SELECT wts.stuff_id 
            FROM work_to_stuffs wts 
            WHERE wts.is_packed = 1
        )
        ORDER BY s.qr_code
    ");
    $stmt->execute([$tool['name'], $toolId]);
    $alternatives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'alternatives' => $alternatives
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_alternative_tools.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => translate('Adatbázis hiba történt')
    ]);
} 