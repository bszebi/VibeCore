<?php
// First start the session
session_start();

// Now include database
require_once '../includes/database.php';

// Check if the session is valid
$valid_session = false;
if (isset($_SESSION['admin_id']) && isset($_SESSION['last_activity'])) {
    $current_time = time();
    $last_activity = $_SESSION['last_activity'];
    $session_timeout = 3599;
    
    if (($current_time - $last_activity) <= $session_timeout) {
        // Session is valid
        $_SESSION['last_activity'] = time(); // Update last activity time
        $valid_session = true;
    }
}

if (!$valid_session) {
    // If called via AJAX, return JSON error
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Session expired or invalid'
    ]);
    exit;
}

// Clear any previous output and set proper headers
ob_clean();
header('Content-Type: application/json');

// Get database connection
$db = DatabaseConnection::getInstance();
$conn = $db->getConnection();

// Időszak szűrő függvény
function getDateRangeFilter($timeRange) {
    $now = new DateTime();
    switch ($timeRange) {
        case 'last30days':
            return ['interval' => 'P30D', 'format' => '%Y-%m-%d'];
        case 'last3months':
            return ['interval' => 'P3M', 'format' => '%Y-%m'];
        case 'last6months':
            return ['interval' => 'P6M', 'format' => '%Y-%m'];
        case 'lastyear':
            return ['interval' => 'P1Y', 'format' => '%Y-%m'];
        default:
            return ['interval' => 'P1Y', 'format' => '%Y-%m'];
    }
}

// CSV export függvény
function exportToCsv($data, $filename) {
    // Beállítjuk a header-eket
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Megnyitjuk az output streamet
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM hozzáadása az ékezetes karakterek helyes megjelenítéséhez
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Ha van adat, akkor dolgozzuk fel
    if (!empty($data) && is_array($data) && count($data) > 0) {
        // Fejléc
        fputcsv($output, array_keys(reset($data)));
        
        // Adatok
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    } else {
        // Ha nincs adat, akkor is írjunk ki egy fejlécet minimum
        fputcsv($output, ['Nincs elérhető adat']);
    }
    
    // Lezárjuk a streamet
    fclose($output);
    exit;
}

try {
    $action = $_GET['action'] ?? '';
    $timeRange = $_GET['timeRange'] ?? 'last30days';
    $format = $_GET['format'] ?? 'json';
    $company_id = $_GET['company_id'] ?? null;
    
    // Ha van company_id, akkor előfizetési adatokat kérünk
    if ($company_id) {
        $query = "SELECT 
                    CASE 
                        WHEN sp.name = 'free-trial' THEN 'Ingyenes próba időszak'
                        ELSE sp.name
                    END as plan_name,
                    sp.price as plan_price,
                    sp.description as plan_description,
                    bi.name as billing_interval,
                    ss.name as subscription_status,
                    s.start_date,
                    CASE 
                        WHEN sp.name = 'free-trial' THEN DATE_ADD(s.start_date, INTERVAL 14 DAY)
                        ELSE s.next_billing_date
                    END as next_billing_date,
                    s.auto_renewal
                 FROM subscriptions s
                 JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
                 JOIN billing_intervals bi ON sp.billing_interval_id = bi.id
                 JOIN subscription_statuses ss ON s.subscription_status_id = ss.id
                 WHERE s.company_id = :company_id
                 AND s.subscription_status_id = 1
                 ORDER BY s.start_date DESC
                 LIMIT 1";
                 
        $stmt = $conn->prepare($query);
        $stmt->execute(['company_id' => $company_id]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Lekérjük a módosításokat
        $modQuery = "SELECT 
                        sp1.name as original_plan,
                        sp2.name as modified_plan,
                        sm.price_difference,
                        sm.modification_reason,
                        sm.modification_date
                     FROM subscription_modifications sm
                     JOIN subscription_plans sp1 ON sm.original_plan_id = sp1.id
                     JOIN subscription_plans sp2 ON sm.modified_plan_id = sp2.id
                     WHERE sm.subscription_id IN (
                        SELECT id FROM subscriptions WHERE company_id = :company_id
                     )
                     ORDER BY sm.modification_date DESC";
                     
        $stmt = $conn->prepare($modQuery);
        $stmt->execute(['company_id' => $company_id]);
        $modifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Lekérjük a fizetési előzményeket
        $payQuery = "SELECT 
                        ph.amount,
                        ph.payment_date,
                        ph.transaction_id,
                        ps.name as payment_status
                     FROM payment_history ph
                     JOIN payment_statuses ps ON ph.payment_status_id = ps.id
                     WHERE ph.subscription_id IN (
                        SELECT id FROM subscriptions WHERE company_id = :company_id
                     )
                     ORDER BY ph.payment_date DESC";
                     
        $stmt = $conn->prepare($payQuery);
        $stmt->execute(['company_id' => $company_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [
            'subscription' => $subscription ?: null,
            'modifications' => $modifications ?: [],
            'payments' => $payments ?: []
        ];
        
        echo json_encode($data);
        exit;
    }
    
    $dateRange = getDateRangeFilter($timeRange);
    $startDate = (new DateTime())->sub(new DateInterval($dateRange['interval']))->format('Y-m-d');
    
    switch ($action) {
        // Alap csomag eloszlás
        case 'getStats':
            $query = "SELECT 
                     CASE sp.name
                        WHEN 'free-trial' THEN 'Ingyenes próba verzió'
                        WHEN 'alap' THEN 'Alap - Havi'
                        WHEN 'alap_eves' THEN 'Alap - Éves'
                        WHEN 'kozepes' THEN 'Közepes - Havi'
                        WHEN 'kozepes_eves' THEN 'Közepes - Éves'
                        WHEN 'uzleti' THEN 'Üzleti - Havi'
                        WHEN 'uzleti_eves' THEN 'Üzleti - Éves'
                        ELSE sp.name
                     END as package_name,
                     COUNT(s.id) as count 
                     FROM subscriptions s 
                     JOIN subscription_plans sp ON s.subscription_plan_id = sp.id 
                     WHERE s.subscription_status_id = 1
                     GROUP BY sp.id, sp.name 
                     ORDER BY count DESC";
                     
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Adatok átstrukturálása Chart.js formátumra
            $labels = array_column($results, 'package_name');
            $counts = array_column($results, 'count');
            
            // Színek generálása
            $colors = [
                'rgba(75, 192, 192, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(255, 99, 132, 0.8)',
                'rgba(255, 206, 86, 0.8)'
            ];
            
            $data = [
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'datasets' => [[
                        'data' => $counts,
                        'backgroundColor' => array_slice($colors, 0, count($counts)),
                        'borderColor' => array_map(function($color) {
                            return str_replace('0.8', '1', $color);
                        }, array_slice($colors, 0, count($counts))),
                        'borderWidth' => 1
                    ]]
                ]
            ];
            break;
            
        // Előfizetés típus preferencia (havi vs. éves)
        case 'getSubscriptionTypeStats':
            $query = "SELECT 
                        DATE_FORMAT(s.start_date, '" . $dateRange['format'] . "') as period,
                        bi.name as billing_type,
                        COUNT(*) as count
                     FROM subscriptions s
                     JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
                     JOIN billing_intervals bi ON sp.billing_interval_id = bi.id
                     WHERE s.start_date >= '$startDate'
                     AND s.subscription_status_id = 1
                     GROUP BY period, bi.id, bi.name
                     ORDER BY period, bi.name";
                     
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Adatok átstrukturálása Chart.js formátumra
            $periods = array_unique(array_column($results, 'period'));
            $billingTypes = array_unique(array_column($results, 'billing_type'));
            
            $datasets = [];
            foreach ($billingTypes as $type) {
                $data = array_fill_keys($periods, 0);
                foreach ($results as $row) {
                    if ($row['billing_type'] === $type) {
                        $data[$row['period']] = (int)$row['count'];
                    }
                }
                $datasets[] = [
                    'label' => $type,
                    'data' => array_values($data),
                    'backgroundColor' => $type === 'Havi' ? 'rgba(75, 192, 192, 0.8)' : 'rgba(153, 102, 255, 0.8)',
                    'borderColor' => $type === 'Havi' ? 'rgba(75, 192, 192, 1)' : 'rgba(153, 102, 255, 1)',
                    'borderWidth' => 1
                ];
            }
            
            $data = [
                'labels' => array_values($periods),
                'datasets' => $datasets
            ];
            break;
            
        // Időbeli eloszlás
        case 'getTimeDistribution':
            $query = "SELECT 
                        DATE_FORMAT(s.start_date, '" . $dateRange['format'] . "') as period,
                        COUNT(*) as count
                     FROM subscriptions s
                     WHERE s.start_date >= '$startDate'
                     GROUP BY period
                     ORDER BY period";
                     
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $data = [
                'labels' => array_column($results, 'period'),
                'datasets' => [[
                    'label' => 'Új előfizetések',
                    'data' => array_column($results, 'count')
                ]]
            ];
            break;
            
        // Bővítmény használat - ideiglenesen kikapcsolva
        case 'getAddonStats':
            $query = "SELECT 
                        'Nincs adat' as addon_name,
                        0 as usage_count,
                        0 as usage_percentage";
            break;
            
        // Lemorzsolódási statisztikák
        case 'getChurnStats':
            $query = "SELECT 
                        sp_from.name as from_package,
                        sp_to.name as to_package,
                        COUNT(*) as change_count
                     FROM subscription_modifications sm
                     JOIN subscription_plans sp_from ON sm.original_plan_id = sp_from.id
                     JOIN subscription_plans sp_to ON sm.modified_plan_id = sp_to.id
                     WHERE sm.modification_date >= '$startDate'
                     GROUP BY sp_from.id, sp_to.id
                     ORDER BY change_count DESC";
            break;
            
        // Növekedési trendek
        case 'getGrowthStats':
            $query = "SELECT 
                        DATE_FORMAT(date_data.date, '" . $dateRange['format'] . "') as period,
                        COUNT(DISTINCT s_new.id) as new_subscriptions,
                        COUNT(DISTINCT s_cancelled.id) as cancelled_subscriptions,
                        COUNT(DISTINCT sm.id) as modifications
                     FROM (
                        SELECT DISTINCT DATE(start_date) as date 
                        FROM subscriptions 
                        WHERE start_date >= '$startDate'
                     ) date_data
                     LEFT JOIN subscriptions s_new 
                        ON DATE(s_new.start_date) = date_data.date
                     LEFT JOIN subscriptions s_cancelled 
                        ON DATE(s_cancelled.end_date) = date_data.date
                     LEFT JOIN subscription_modifications sm 
                        ON DATE(sm.modification_date) = date_data.date
                     GROUP BY period
                     ORDER BY period";
                     
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $data = [
                'labels' => array_column($results, 'period'),
                'datasets' => [
                    [
                        'label' => 'Új előfizetések',
                        'data' => array_column($results, 'new_subscriptions')
                    ],
                    [
                        'label' => 'Lemondások',
                        'data' => array_column($results, 'cancelled_subscriptions')
                    ],
                    [
                        'label' => 'Módosítások',
                        'data' => array_column($results, 'modifications')
                    ]
                ]
            ];
            break;
            
        // Pénzügyi metrikák
        case 'getFinancialStats':
            // First query - Monthly breakdown of revenues
            $query = "SELECT 
                        DATE_FORMAT(ph.payment_date, '" . $dateRange['format'] . "') as period,
                        SUM(ph.amount) as total_revenue,
                        COUNT(*) as total_payments,
                        SUM(CASE WHEN bi.name = 'Havi' THEN ph.amount ELSE 0 END) as monthly_revenue,
                        SUM(CASE WHEN bi.name = 'Éves' THEN ph.amount ELSE 0 END) as yearly_revenue,
                        COUNT(CASE WHEN bi.name = 'Havi' THEN 1 ELSE NULL END) as monthly_payments,
                        COUNT(CASE WHEN bi.name = 'Éves' THEN 1 ELSE NULL END) as yearly_payments
                     FROM payment_history ph
                     JOIN subscriptions s ON ph.subscription_id = s.id
                     JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
                     JOIN billing_intervals bi ON sp.billing_interval_id = bi.id
                     WHERE ph.payment_date >= '$startDate'
                     AND ph.payment_status_id = 1 -- Successful payments only
                     GROUP BY period
                     ORDER BY period";
                     
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $monthly_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Second query - Direct calculation of totals
            $totals_query = "SELECT 
                        SUM(ph.amount) as grand_total,
                        SUM(CASE WHEN bi.name = 'Havi' THEN ph.amount ELSE 0 END) as monthly_total,
                        SUM(CASE WHEN bi.name = 'Éves' THEN ph.amount ELSE 0 END) as yearly_total,
                        COUNT(*) as total_payments,
                        COUNT(CASE WHEN bi.name = 'Havi' THEN 1 ELSE NULL END) as monthly_payments,
                        COUNT(CASE WHEN bi.name = 'Éves' THEN 1 ELSE NULL END) as yearly_payments
                    FROM payment_history ph
                    JOIN subscriptions s ON ph.subscription_id = s.id
                    JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
                    JOIN billing_intervals bi ON sp.billing_interval_id = bi.id
                    WHERE ph.payment_date >= '$startDate'
                    AND ph.payment_status_id = 1";
                    
            $stmt = $conn->prepare($totals_query);
            $stmt->execute();
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log the totals for debugging
            error_log("Financial Totals: " . json_encode($totals));
            
            // If any of the totals are null, set them to 0
            $totals['grand_total'] = $totals['grand_total'] ?? 0;
            $totals['monthly_total'] = $totals['monthly_total'] ?? 0;
            $totals['yearly_total'] = $totals['yearly_total'] ?? 0;
            
            // Third query - Totals by plan type
            $query_plans = "SELECT 
                        CASE sp.name
                            WHEN 'free-trial' THEN 'Ingyenes próba verzió'
                            WHEN 'alap' THEN 'Alap - Havi'
                            WHEN 'alap_eves' THEN 'Alap - Éves'
                            WHEN 'kozepes' THEN 'Közepes - Havi'
                            WHEN 'kozepes_eves' THEN 'Közepes - Éves'
                            WHEN 'uzleti' THEN 'Üzleti - Havi'
                            WHEN 'uzleti_eves' THEN 'Üzleti - Éves'
                            ELSE sp.name
                        END as plan_name,
                        SUM(ph.amount) as total_revenue,
                        COUNT(*) as number_of_payments,
                        bi.name as billing_type
                     FROM payment_history ph
                     JOIN subscriptions s ON ph.subscription_id = s.id
                     JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
                     JOIN billing_intervals bi ON sp.billing_interval_id = bi.id
                     WHERE ph.payment_date >= '$startDate'
                     AND ph.payment_status_id = 1
                     GROUP BY sp.id, sp.name, bi.name
                     ORDER BY total_revenue DESC";
                     
            $stmt = $conn->prepare($query_plans);
            $stmt->execute();
            $plan_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Setup data for Chart.js
            $data = [
                'labels' => array_column($monthly_results, 'period'),
                'datasets' => [
                    [
                        'label' => 'Havi előfizetések bevétele',
                        'data' => array_map('floatval', array_column($monthly_results, 'monthly_revenue')),
                        'backgroundColor' => 'rgba(75, 192, 192, 0.8)',
                        'borderColor' => 'rgba(75, 192, 192, 1)',
                        'type' => 'bar'
                    ],
                    [
                        'label' => 'Éves előfizetések bevétele',
                        'data' => array_map('floatval', array_column($monthly_results, 'yearly_revenue')),
                        'backgroundColor' => 'rgba(54, 162, 235, 0.8)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'type' => 'bar'
                    ],
                    [
                        'label' => 'Összes bevétel',
                        'data' => array_map('floatval', array_column($monthly_results, 'total_revenue')),
                        'backgroundColor' => 'rgba(255, 99, 132, 0.8)',
                        'borderColor' => 'rgba(255, 99, 132, 1)',
                        'type' => 'line',
                        'borderWidth' => 2,
                        'fill' => false
                    ]
                ],
                'summary' => [
                    'grand_total' => number_format($totals['grand_total'], 0, ',', ' ') . ' Ft',
                    'monthly_total' => number_format($totals['monthly_total'], 0, ',', ' ') . ' Ft',
                    'yearly_total' => number_format($totals['yearly_total'], 0, ',', ' ') . ' Ft',
                    'plan_breakdown' => $plan_totals
                ]
            ];
            break;
            
        // Felhasználói viselkedés
        case 'getUserBehaviorStats':
            $query = "SELECT 
                        sp.name as package_name,
                        AVG(DATEDIFF(COALESCE(sa.addon_date, CURRENT_DATE), s.start_date)) as avg_days_to_addon,
                        COUNT(DISTINCT sa.addon_id) as avg_addons_per_subscription
                     FROM subscriptions s
                     JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
                     LEFT JOIN subscription_addons sa ON s.id = sa.subscription_id
                     WHERE s.start_date >= '$startDate'
                     GROUP BY sp.id";
            break;

        // Visszajelzések statisztikái
        case 'getFeedbackStats':
            $query = "SELECT 
                        sf.rating,
                        sf.feedback_text,
                        sf.created_at,
                        c.company_name,
                        sp.name as package_name,
                        CASE sp.name
                            WHEN 'free-trial' THEN 'Ingyenes próba verzió'
                            WHEN 'alap' THEN 'Alap - Havi'
                            WHEN 'alap_eves' THEN 'Alap - Éves'
                            WHEN 'kozepes' THEN 'Közepes - Havi'
                            WHEN 'kozepes_eves' THEN 'Közepes - Éves'
                            WHEN 'uzleti' THEN 'Üzleti - Havi'
                            WHEN 'uzleti_eves' THEN 'Üzleti - Éves'
                            ELSE sp.name
                        END as formatted_package_name,
                        sf.status as feedback_status,
                        sf.admin_response,
                        sf.response_date
                     FROM subscription_feedback sf
                     JOIN subscriptions s ON sf.subscription_id = s.id
                     JOIN companies c ON s.company_id = c.id
                     JOIN subscription_plans sp ON s.subscription_plan_id = sp.id
                     WHERE sf.created_at >= '$startDate'
                     ORDER BY sf.created_at DESC";
            break;
            
        // Módosítások típusai
        case 'getModificationTypes':
            try {
                // Lekérjük az összes módosítást és a módosítások típusait
                $query = "SELECT 
                    COUNT(*) as total_modifications,
                    SUM(CASE 
                        WHEN sm.modification_reason LIKE '%felhasználó%' AND sm.modification_reason NOT LIKE '%eszköz%' THEN 1
                        ELSE 0 
                    END) as user_limit_only,
                    SUM(CASE 
                        WHEN sm.modification_reason NOT LIKE '%felhasználó%' AND sm.modification_reason LIKE '%eszköz%' THEN 1
                        ELSE 0 
                    END) as device_limit_only,
                    SUM(CASE 
                        WHEN sm.modification_reason LIKE '%felhasználó%' AND sm.modification_reason LIKE '%eszköz%' THEN 1
                        ELSE 0 
                    END) as both_modified,
                    SUM(CASE 
                        WHEN sm.modification_reason IS NULL OR sm.modification_reason = '' THEN 1
                        ELSE 0 
                    END) as no_modifications
                FROM subscription_modifications sm";
                
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $modifications = $stmt->fetch(PDO::FETCH_ASSOC);

                // Lekérjük a módosításokat csomagonként
                $query = "SELECT 
                    CASE sp.name
                        WHEN 'free-trial' THEN 'Ingyenes próba verzió'
                        WHEN 'alap' THEN 'Alap csomag'
                        WHEN 'kozepes' THEN 'Közepes csomag'
                        WHEN 'uzleti' THEN 'Üzleti csomag'
                        WHEN 'alap_eves' THEN 'Alap csomag (éves)'
                        WHEN 'kozepes_eves' THEN 'Közepes csomag (éves)'
                        WHEN 'uzleti_eves' THEN 'Üzleti csomag (éves)'
                        ELSE sp.name
                    END as package_name,
                    COUNT(*) as count
                FROM subscription_modifications sm
                JOIN subscription_plans sp ON sm.modified_plan_id = sp.id
                GROUP BY sp.id, sp.name
                ORDER BY count DESC";
                
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $package_modifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Összeállítjuk a válasz objektumot
                $response = [
                    'success' => true,
                    'data' => [
                        'total' => (int)$modifications['total_modifications'],
                        'no_modifications' => (int)$modifications['no_modifications'],
                        'user_limit_only' => (int)$modifications['user_limit_only'],
                        'device_limit_only' => (int)$modifications['device_limit_only'],
                        'both_modified' => (int)$modifications['both_modified'],
                        'package_modifications' => $package_modifications,
                        'total_package_modifications' => array_sum(array_column($package_modifications, 'count'))
                    ]
                ];

                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Hiba történt a módosítások lekérdezése során: ' . $e->getMessage()
                ]);
                exit;
            }
            break;
            
        case 'getModificationTypeStats':
            $query = "SELECT 
                        sm.modification_reason,
                        sp.description as plan_description,
                        COUNT(*) as count
                     FROM subscription_modifications sm
                     JOIN subscription_plans sp ON sm.modified_plan_id = sp.id
                     GROUP BY sm.id";
                     
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Kategorizáljuk a módosításokat
            $stats = [
                'no_modification' => 0,
                'only_users' => 0,
                'only_devices' => 0,
                'both_modified' => 0
            ];
            
            foreach ($results as $row) {
                // Eredeti csomag adatainak kinyerése a leírásból
                preg_match('/(\d+)\s+felhasználó,\s+(\d+)\s+eszköz/', $row['plan_description'], $plan_matches);
                $plan_users = isset($plan_matches[1]) ? intval($plan_matches[1]) : 0;
                $plan_devices = isset($plan_matches[2]) ? intval($plan_matches[2]) : 0;
                
                // Módosított adatok kinyerése a modification_reason-ből
                preg_match('/(\d+)\s+felhasználó,\s+(\d+)\s+eszköz/', $row['modification_reason'], $mod_matches);
                $mod_users = isset($mod_matches[1]) ? intval($mod_matches[1]) : 0;
                $mod_devices = isset($mod_matches[2]) ? intval($mod_matches[2]) : 0;
                
                // Módosítások kategorizálása
                if ($mod_users == $plan_users && $mod_devices == $plan_devices) {
                    $stats['no_modification']++;
                } elseif ($mod_users != $plan_users && $mod_devices == $plan_devices) {
                    $stats['only_users']++;
                } elseif ($mod_users == $plan_users && $mod_devices != $plan_devices) {
                    $stats['only_devices']++;
                } else {
                    $stats['both_modified']++;
                }
            }
            
            $data = [
                'labels' => ['Nem módosított', 'Csak felhasználószám', 'Csak eszközszám', 'Mindkettő módosítva'],
                'datasets' => [[
                    'data' => array_values($stats),
                    'backgroundColor' => [
                        'rgba(75, 192, 75, 0.8)',   // zöld
                        'rgba(54, 162, 235, 0.8)',  // kék
                        'rgba(255, 159, 64, 0.8)',  // narancssárga
                        'rgba(153, 102, 255, 0.8)'  // lila
                    ]
                ]]
            ];
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
    // Lekérdezés végrehajtása
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // CSV export kezelése
    if ($format === 'csv') {
        // Ha van data, akkor azt használjuk, különben a $results-ot
        $exportData = $data['data'] ?? $results;
        
        // Ha a data egy összetett Chart.js objektum, próbáljuk kiszedni a hasznosítható adatokat
        if (isset($exportData['datasets'])) {
            $exportRows = [];
            $labels = $exportData['labels'] ?? [];
            
            foreach ($labels as $i => $label) {
                $row = ['period' => $label];
                
                // Kinyerjük az adatokat minden adathalmazból
                foreach ($exportData['datasets'] as $j => $dataset) {
                    $datasetLabel = $dataset['label'] ?? 'Dataset ' . ($j + 1);
                    $row[$datasetLabel] = $dataset['data'][$i] ?? 0;
                }
                
                $exportRows[] = $row;
            }
            
            exportToCsv($exportRows, "statistics_{$action}_{$timeRange}.csv");
        } 
        // Ha plan_breakdown létezik, azt exportáljuk
        elseif (isset($data['summary']['plan_breakdown'])) {
            exportToCsv($data['summary']['plan_breakdown'], "statistics_{$action}_{$timeRange}.csv");
        }
        // Egyszerű adatszerkezet esetén
        else {
            exportToCsv($exportData, "statistics_{$action}_{$timeRange}.csv");
        }
    }
    
    // JSON válasz
    echo json_encode([
        'success' => true,
        'data' => $data ?? $results
    ]);
    
} catch (Exception $e) {
    error_log('Statistics error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Nem sikerült lekérni a statisztikákat: ' . $e->getMessage()
    ]);
}
