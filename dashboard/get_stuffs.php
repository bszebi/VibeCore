<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Only start a session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nincs bejelentkezve!']);
    exit;
}

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    // Lekérjük a felhasználó company_id-ját
    $stmt = $db->prepare("SELECT company_id FROM user WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company_id = $stmt->fetchColumn();
    
    // Lekérjük az eszközöket
    $stmt = $db->prepare("
        SELECT 
            s.*,
            st.name as type_name,
            sst.name as secondtype_name,
            sb.name as brand_name,
            sm.name as model_name,
            ss.name as status_name,
            smd.year as manufacture_year
        FROM stuffs s
        LEFT JOIN stuff_type st ON s.type_id = st.id
        LEFT JOIN stuff_secondtype sst ON s.secondtype_id = sst.id
        LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
        LEFT JOIN stuff_model sm ON s.model_id = sm.id
        LEFT JOIN stuff_status ss ON s.stuff_status_id = ss.id
        LEFT JOIN stuff_manufacture_date smd ON s.manufacture_date = smd.id
        WHERE s.company_id = ?
        ORDER BY s.id DESC
    ");
    $stmt->execute([$company_id]);
    $stuffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // HTML generálása
    $html = '';
    foreach ($stuffs as $stuff) {
        $html .= sprintf('
            <tr id="row-%d">
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>
                    <button class="btn btn-sm btn-primary edit-btn" data-id="%d">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-btn" data-id="%d">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>',
            $stuff['id'],
            htmlspecialchars($stuff['type_name']),
            htmlspecialchars($stuff['secondtype_name']),
            htmlspecialchars($stuff['brand_name']),
            htmlspecialchars($stuff['model_name']),
            htmlspecialchars($stuff['manufacture_year']),
            htmlspecialchars($stuff['status_name']),
            htmlspecialchars($stuff['qr_code']),
            $stuff['id'],
            $stuff['id']
        );
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    error_log('Hiba a get_stuffs.php-ban: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Hiba történt az adatok lekérésekor!'
    ]);
} 