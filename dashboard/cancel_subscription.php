<?php
ob_clean();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);
session_start();
require_once '../includes/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Nincs bejelentkezve'
    ]);
    exit;
}

try {
    $db = DatabaseConnection::getInstance();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Get the user's company ID
    $user_id = $_SESSION['user_id'];
    $company_id = $_SESSION['company_id'];

    // Get the current subscription
    $subscriptionQuery = "SELECT s.id, s.subscription_plan_id, sp.name as plan_name FROM subscriptions s
                         JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
                         WHERE s.company_id = :company_id AND s.subscription_status_id = 1";
    $stmt = $conn->prepare($subscriptionQuery);
    $stmt->execute(['company_id' => $company_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        throw new Exception('Aktív előfizetés nem található');
    }
    
    // Check if the subscription is a free trial
    if ($subscription['plan_name'] === 'free-trial') {
        throw new Exception('Az ingyenes próbaidőszak előfizetése nem mondható le.');
    }

    // Update subscription status to cancelled
    $updateQuery = "UPDATE subscriptions 
                   SET subscription_status_id = 2, 
                       cancelled_at = NOW(),
                       cancellation_reason = 'Felhasználó által lemondva'
                   WHERE id = :subscription_id";
    $stmt = $conn->prepare($updateQuery);
    $stmt->execute(['subscription_id' => $subscription['id']]);

    // Log the cancellation in admin_logs
    $logQuery = "INSERT INTO admin_logs 
        (admin_id, action_type, table_name, record_id, old_values, new_values, ip_address)
        VALUES (:admin_id, 'CANCEL_SUBSCRIPTION', 'subscriptions', :record_id, 
                :old_values, :new_values, :ip_address)";

    $stmt = $conn->prepare($logQuery);
    $stmt->execute([
        'admin_id' => $user_id,
        'record_id' => $subscription['id'],
        'old_values' => json_encode(['status' => 'active']),
        'new_values' => json_encode([
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ]),
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    // Update subscription analytics
    $analyticsQuery = "UPDATE subscription_analytics 
                      SET cancelled_subscriptions = cancelled_subscriptions + 1,
                          active_subscriptions = active_subscriptions - 1,
                          last_updated = NOW()
                      WHERE subscription_plan_id = :plan_id";
    
    $stmt = $conn->prepare($analyticsQuery);
    $stmt->execute(['plan_id' => $subscription['subscription_plan_id']]);

    // Commit the transaction
    $conn->commit();

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Előfizetés sikeresen lemondva'
    ]);

} catch (Exception $e) {
    // Rollback the transaction in case of error
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Hiba történt a lemondás során: ' . $e->getMessage()
    ]);
}
?> 