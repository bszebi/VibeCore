<?php
require_once '../config.php';
require_once '../auth_check.php';

header('Content-Type: application/json');

try {
    if (!isset($_FILES['image'])) {
        throw new Exception('Nem található feltöltött kép');
    }

    $file = $_FILES['image'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];

    // Ellenőrizzük a fájl típusát
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($fileTmpName);
    
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Nem támogatott fájltípus. Csak JPG, PNG, GIF és WEBP formátumok engedélyezettek.');
    }

    // Ellenőrizzük a fájl méretét (max 5MB)
    if ($fileSize > 5 * 1024 * 1024) {
        throw new Exception('A fájl túl nagy. Maximum 5MB engedélyezett.');
    }

    // Generálunk egy egyedi fájlnevet
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = uniqid('img_') . '.' . $extension;

    // Létrehozzuk a feltöltési mappát, ha nem létezik
    $uploadDir = '../../uploads/images/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Feltöltjük a fájlt
    $destination = $uploadDir . $newFileName;
    if (!move_uploaded_file($fileTmpName, $destination)) {
        throw new Exception('Hiba történt a fájl feltöltése során');
    }

    // Relatív URL visszaadása a projekt alapkönyvtárával
    $imageUrl = '/Vizsga_oldal/uploads/images/' . $newFileName;

    echo json_encode([
        'success' => true,
        'url' => $imageUrl
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 