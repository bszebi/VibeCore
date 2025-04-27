<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Get PDO connection
$pdo = Database::getInstance()->getConnection();

// Session ellenőrzése és inicializálása
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../includes/auth_check.php';
checkPageAccess();

// Check if user is admin (Cég tulajdonos or Manager)
$user_roles = !empty($_SESSION['user_role']) ? explode(',', $_SESSION['user_role']) : [];
$is_admin = false;
foreach ($user_roles as $role) {
    $role = trim($role);
    if ($role === 'Cég tulajdonos' || $role === 'Manager') {
        $is_admin = true;
        break;
    }
}

// Get notifications based on user role
if ($is_admin) {
    // Admin query - for viewing incoming leave requests
    $notifications_sql = "SELECT n.*, 
                                CONCAT(u.lastname, ' ', u.firstname) as sender_name,
                                lr.id as leave_request_id,
                                lr.start_date,
                                lr.end_date,
                                lr.notification_text,
                                lr.status_id as request_status_id,
                                lr.is_accepted,
                                lr.response_message,
                                lr.response_time
                         FROM notifications n
                         JOIN user u ON n.sender_user_id = u.id
                         JOIN leave_requests lr ON lr.sender_user_id = n.sender_user_id 
                              AND lr.receiver_user_id = n.receiver_user_id
                              AND lr.notification_time = n.notification_time
                         WHERE n.receiver_user_id = :user_id
                         ORDER BY n.notification_time DESC";
    $params = [':user_id' => $_SESSION['user_id']];
} else {
    // Worker query - for viewing responses to their requests
    $notifications_sql = "SELECT n.*,
                                CONCAT(u.lastname, ' ', u.firstname) as responder_name,
                                lr.id as leave_request_id,
                                lr.start_date,
                                lr.end_date,
                                lr.status_id as request_status_id,
                                lr.is_accepted,
                                lr.response_message,
                                lr.response_time,
                                lr.notification_text as request_text,
                                s.name as status_name
                         FROM leave_requests lr
                         JOIN notifications n ON n.sender_user_id = lr.receiver_user_id 
                              AND n.receiver_user_id = lr.sender_user_id
                         JOIN user u ON lr.receiver_user_id = u.id
                         LEFT JOIN status s ON lr.status_id = s.id
                         WHERE lr.sender_user_id = :user_id
                         AND lr.is_accepted IS NOT NULL
                         ORDER BY lr.response_time DESC";
    $params = [':user_id' => $_SESSION['user_id']];
}

$stmt = $pdo->prepare($notifications_sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/layout/header.php';
?>

<style>
.notifications-container {
    max-width: 100%;
    margin: 0;
    padding: 0;
    display: flex;
    height: calc(100vh - 60px);
}

.sidebar {
    width: 250px;
    background: #fff;
    border-right: 1px solid #e0e0e0;
    padding: 20px 0;
    display: flex;
    flex-direction: column;
}

.sidebar-item {
    display: flex;
    align-items: center;
    padding: 12px 24px;
    color: #5f6368;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
    border-radius: 0 20px 20px 0;
    margin-right: 10px;
}

.sidebar-item:hover {
    background: #f1f3f4;
    color: #1a73e8;
}

.sidebar-item.active {
    background: #e8f0fe;
    color: #1a73e8;
}

.sidebar-item i {
    margin-right: 12px;
    width: 20px;
    text-align: center;
}

.sidebar-item .count {
    margin-left: auto;
    background: #1a73e8;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
}

.main-content {
    flex: 1;
    background: #fff;
    display: flex;
    flex-direction: column;
}

.toolbar {
    padding: 12px 24px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    align-items: center;
    gap: 16px;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.toolbar-button {
    background: none;
    border: none;
    color: #5f6368;
    padding: 8px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
}

.toolbar-button:hover {
    background: #f1f3f4;
    color: #1a73e8;
}

.search-box {
    flex: 1;
    max-width: 720px;
    position: relative;
}

.search-input {
    width: 100%;
    padding: 12px 16px 12px 48px;
    border: none;
    border-radius: 8px;
    background: #f1f3f4;
    font-size: 1rem;
    color: #3c4043;
    transition: all 0.2s;
    text-indent: 20px;
}

.search-input::placeholder {
    color: #5f6368;
    padding-left: 8px;
}

.search-input:focus {
    background: #fff;
    box-shadow: 0 1px 1px 0 rgba(65,69,73,0.3), 0 1px 3px 1px rgba(65,69,73,0.15);
    outline: none;
}

.search-icon {
    position: absolute;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #5f6368;
    pointer-events: none;
    z-index: 1;
    font-size: 14px;
    width: 14px;
    height: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.content {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
}

.notification-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.notification-item {
    display: flex;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #e0e0e0;
    transition: all 0.2s;
    cursor: pointer;
}

.notification-item:hover {
    background: #f8f9fa;
    box-shadow: 0 1px 2px rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
}

.notification-checkbox {
    margin-right: 16px;
}

.notification-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #1a73e8;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
}

.notification-icon.vacation {
    background: #2ecc71;
}

.notification-icon.sick {
    background: #e74c3c;
}

.notification-content {
    flex: 1;
}

.notification-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.notification-sender {
    font-weight: 600;
    color: #202124;
}

.notification-time {
    color: #5f6368;
    font-size: 0.875rem;
}

.notification-subject {
    margin-top: 8px;
    font-weight: 500;
    color: #5f6368;
}

.notification-preview {
    margin-top: 4px;
    color: #5f6368;
    font-size: 0.9rem;
}

.notification-actions {
    display: none;
    align-items: center;
    gap: 8px;
}

.notification-item:hover .notification-actions {
    display: flex;
}

.action-button {
    background: none;
    border: none;
    color: #5f6368;
    padding: 8px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
}

.action-button:hover {
    background: #f1f3f4;
    color: #1a73e8;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 500;
    margin-left: 8px;
}

.status-badge.accepted {
    background-color: #e6f4ea;
    color: #1e8e3e;
}

.status-badge.rejected {
    background-color: #fce8e6;
    color: #d93025;
}

.status-badge.pending {
    background-color: #e8f0fe;
    color: #1a73e8;
}

/* Reszponzív design */
@media (max-width: 768px) {
    .sidebar {
        width: 60px;
    }

    .sidebar-item span {
        display: none;
    }

    .sidebar-item .count {
        display: none;
    }

    .search-box {
        display: none;
    }
}

.response-box {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 600px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
    z-index: 1000;
    max-height: 90vh;
    overflow-y: auto;
}

.response-box.show {
    display: block;
}

.response-box-header {
    padding: 16px 24px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.response-box-header.accept {
    background-color: #e8f5e9;
}

.response-box-header.reject {
    background-color: #ffebee;
}

.response-box-header.details {
    background-color: #e3f2fd;
}

.response-box-title {
    margin: 0;
    font-size: 1.25rem;
    color: #202124;
    display: flex;
    align-items: center;
    gap: 8px;
}

.response-box-close {
    background: none;
    border: none;
    color: #5f6368;
    padding: 8px;
    cursor: pointer;
    border-radius: 50%;
    transition: all 0.2s;
}

.response-box-close:hover {
    background: #f1f3f4;
    color: #1a73e8;
}

.response-box-body {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.request-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.request-details .info-item {
    margin-bottom: 0;
}

.request-details .info-item.full-width {
    grid-column: 1 / -1;
}

.info-label {
    font-weight: 500;
    color: #5f6368;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-value {
    color: #202124;
    line-height: 1.5;
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #e0e0e0;
}

.collapsible-section {
    margin-top: 0;
    grid-column: 1 / -1;
}

.collapsible-button {
    width: 100%;
    padding: 12px;
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    color: #5f6368;
}

.collapsible-content {
    display: none;
    padding: 20px;
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    margin-top: 10px;
}

.response-form {
    margin-top: 0;
    grid-column: 1 / -1;
}

.form-group {
    margin-bottom: 0;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-size: 1rem;
    color: #202124;
    transition: all 0.2s;
    background: #f8f9fa;
    resize: vertical;
    min-height: 100px;
}

.response-box-footer {
    padding: 16px 24px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.info-item {
    margin-bottom: 16px;
}

.info-label {
    font-weight: 500;
    color: #5f6368;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-value {
    color: #202124;
    line-height: 1.5;
}

.response-form {
    margin-top: 24px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: #5f6368;
    font-weight: 500;
}

.form-control:focus {
    border-color: #1a73e8;
    box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
    outline: none;
}

/* Modal overlay */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}

.modal-overlay.show {
    display: block;
}

/* Confirmation modal */
.confirmation-modal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 500px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
    z-index: 1000;
}

.confirmation-modal.show {
    display: block;
}

.confirmation-content {
    padding: 24px;
}

.confirmation-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.confirmation-header i {
    color: #f44336;
    font-size: 24px;
}

.confirmation-header h5 {
    margin: 0;
    font-size: 1.25rem;
    color: #202124;
}

.confirmation-body {
    margin-bottom: 24px;
}

.confirmation-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn-cancel,
.btn-confirm-delete {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-cancel {
    background: #f1f3f4;
    color: #5f6368;
}

.btn-confirm-delete {
    background: #f44336;
    color: white;
}

/* Success notification */
.success-notification {
    display: none;
    position: fixed;
    top: 24px;
    right: 24px;
    width: 100%;
    max-width: 400px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
    z-index: 1000;
}

.success-notification.show {
    display: block;
}

.success-notification-header {
    padding: 16px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.success-notification-title {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #202124;
}

.success-notification-title i {
    color: #34a853;
}

.success-notification-close {
    background: none;
    border: none;
    color: #5f6368;
    cursor: pointer;
    padding: 4px;
    border-radius: 50%;
}

.success-notification-body {
    padding: 16px;
}

.success-notification-message {
    margin: 0;
    color: #5f6368;
}

.collapsible-section {
    margin-top: 20px;
    border-top: 1px solid #e0e0e0;
    padding-top: 20px;
}

.collapsible-button {
    width: 100%;
    padding: 10px;
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.collapsible-button:hover {
    background: #e9ecef;
}

.collapsible-button i {
    transition: transform 0.3s ease;
}

.collapsible-button.active i {
    transform: rotate(180deg);
}

.collapsible-content {
    display: none;
    padding: 20px 0 0;
}

.collapsible-content.show {
    display: block;
}

.notification-details {
    margin-top: 8px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
}

.notification-details p {
    margin: 4px 0;
    color: #5f6368;
    font-size: 0.9rem;
}

.text-success {
    color: #2ecc71;
}

.text-danger {
    color: #e74c3c;
}

.notification-title {
    font-size: 1rem;
    color: #202124;
}

.notification-time {
    color: #5f6368;
    font-size: 0.85rem;
}
</style>

<div class="notifications-container">
    <div class="sidebar">
        <a href="#all" class="sidebar-item active" data-category="all">
            <i class="fas fa-inbox"></i>
            <span><?php echo translate('Összes'); ?></span>
            <span class="count">0</span>
        </a>
        <a href="#leave" class="sidebar-item" data-category="leave">
            <i class="fas fa-umbrella-beach"></i>
            <span><?php echo translate('Szabadság kérelmek'); ?></span>
            <span class="count">0</span>
        </a>
        <a href="#sick" class="sidebar-item" data-category="sick">
            <i class="fas fa-procedures"></i>
            <span><?php echo translate('Betegállomány'); ?></span>
            <span class="count">0</span>
        </a>
        <?php if ($is_admin): ?>
        <a href="#pending" class="sidebar-item" data-category="pending">
            <i class="fas fa-clock"></i>
            <span><?php echo translate('Függőben'); ?></span>
            <span class="count">0</span>
        </a>
        <?php endif; ?>
        <a href="#accepted" class="sidebar-item" data-category="accepted">
            <i class="fas fa-check-circle"></i>
            <span><?php echo translate('Elfogadott'); ?></span>
            <span class="count">0</span>
        </a>
        <a href="#rejected" class="sidebar-item" data-category="rejected">
            <i class="fas fa-times-circle"></i>
            <span><?php echo translate('Elutasított'); ?></span>
            <span class="count">0</span>
        </a>
    </div>

    <div class="main-content">
        <div class="toolbar">
            <div class="toolbar-actions">
                <button class="toolbar-button" id="selectAll" title="<?php echo translate('Összes kijelölése'); ?>">
                    <i class="far fa-square"></i>
                </button>
                <button class="toolbar-button" id="refresh" title="<?php echo translate('Frissítés'); ?>">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button class="toolbar-button" id="delete" title="<?php echo translate('Törlés'); ?>" disabled>
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="<?php echo translate('Keresés az értesítések között...'); ?>">
            </div>
        </div>

        <div class="content">
            <ul class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                <li class="notification-item" 
                    data-category="<?php echo $notification['request_status_id'] == 4 ? 'leave' : 'sick'; ?>"
                    data-status="<?php echo $notification['is_accepted'] === null ? 'pending' : ($notification['is_accepted'] ? 'accepted' : 'rejected'); ?>">
                    <input type="checkbox" class="notification-checkbox custom-checkbox" data-notification-id="<?php echo $notification['id']; ?>">
                    
                    <?php
                    // Determine the icon class based on request type
                    $icon_class = '';
                    $icon_type = '';
                    if (strpos(strtolower($notification['notification_text']), 'szabadság') !== false || $notification['request_status_id'] == 4) {
                        $icon_class = 'vacation';
                        $icon_type = 'fa-umbrella-beach';
                    } else {
                        $icon_class = 'sick';
                        $icon_type = 'fa-procedures';
                    }
                    ?>
                    
                    <div class="notification-icon <?php echo $icon_class; ?>">
                        <i class="fas <?php echo $icon_type; ?>"></i>
                    </div>
                    
                    <div class="notification-content">
                        <div class="notification-header">
                            <?php if ($is_admin): ?>
                                <span class="notification-sender"><?php echo htmlspecialchars($notification['sender_name']); ?></span>
                                <span class="notification-time"><?php echo date('Y.m.d H:i', strtotime($notification['notification_time'])); ?></span>
                                <?php if ($notification['is_accepted'] !== null): ?>
                                    <span class="status-badge <?php echo $notification['is_accepted'] ? 'accepted' : 'rejected'; ?>">
                                        <?php echo $notification['is_accepted'] ? 'Elfogadva' : 'Elutasítva'; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge pending"><?php echo translate('Függőben'); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="notification-title">
                                    <?php if (isset($notification['is_accepted'])): ?>
                                        <?php
                                        $status_text = $notification['is_accepted'] ? 'elfogadta' : 'elutasította';
                                        $status_class = $notification['is_accepted'] ? 'text-success' : 'text-danger';
                                        $request_type = $notification['request_status_id'] == 4 ? 'szabadság' : 'betegállomány';
                                        ?>
                                        <strong><?php echo htmlspecialchars($notification['responder_name'] ?? ''); ?></strong> 
                                        <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span> 
                                        az Ön <?php echo $request_type; ?> kérelmét
                                        <span class="status-badge <?php echo $notification['is_accepted'] ? 'accepted' : 'rejected'; ?>">
                                            <?php echo $notification['is_accepted'] ? 'Elfogadva' : 'Elutasítva'; ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($notification['notification_text'] ?? ''); ?>
                                        <span class="status-badge pending"><?php echo translate('Függőben'); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="notification-time">
                                    <?php echo date('Y.m.d H:i', strtotime($notification['notification_time'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($is_admin): ?>
                            <div class="notification-subject">
                                <?php echo $notification['request_status_id'] == 4 ? 'Szabadság kérelem' : 'Betegállomány kérelem'; ?>
                                <?php if (!empty($notification['start_date']) && !empty($notification['end_date'])): ?>
                                    <?php 
                                    $start_date = new DateTime($notification['start_date']);
                                    $end_date = new DateTime($notification['end_date']);
                                    if ($start_date == $end_date) {
                                        echo ' (' . $start_date->format('Y.m.d') . ')';
                                    } else {
                                        echo ' (' . $start_date->format('Y.m.d') . ' - ' . $end_date->format('Y.m.d') . ')';
                                    }
                                    ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notification-preview">
                                <?php 
                                if (!empty($notification['start_date']) && !empty($notification['end_date'])) {
                                    $start_date = new DateTime($notification['start_date']);
                                    $end_date = new DateTime($notification['end_date']);
                                    $date_text = $start_date == $end_date ? 
                                        $start_date->format('Y.m.d') : 
                                        $start_date->format('Y.m.d') . ' - ' . $end_date->format('Y.m.d');
                                    echo "Időszak: " . $date_text . " | ";
                                }
                                echo htmlspecialchars($notification['notification_text']); 
                                ?>
                            </div>
                        <?php else: ?>
                            <?php if ($notification['leave_request_id'] && isset($notification['is_accepted'])): ?>
                                <div class="notification-details">
                                    <p>Időszak: <?php echo date('Y.m.d', strtotime($notification['start_date'])); ?> - <?php echo date('Y.m.d', strtotime($notification['end_date'])); ?></p>
                                    <?php if (!empty($notification['response_message'])): ?>
                                        <p>Válasz üzenet: <?php echo htmlspecialchars($notification['response_message']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($notification['response_time'])): ?>
                                        <p>Válasz időpontja: <?php echo date('Y.m.d H:i', strtotime($notification['response_time'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($notification['request_text'])): ?>
                                        <p>Eredeti kérelem: <?php echo htmlspecialchars($notification['request_text']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-actions">
                        <?php if ($is_admin && $notification['is_accepted'] === null): ?>
                            <button class="action-button accept-request" data-request-id="<?php echo $notification['leave_request_id']; ?>"
                                    data-status="<?php echo $notification['request_status_id']; ?>" title="<?php echo translate('Elfogadás'); ?>">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="action-button reject-request" data-request-id="<?php echo $notification['leave_request_id']; ?>"
                                    data-status="<?php echo $notification['request_status_id']; ?>" title="<?php echo translate('Elutasítás'); ?>">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                        <button class="action-button delete-btn" data-notification-id="<?php echo $notification['id']; ?>" title="<?php echo translate('Törlés'); ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="action-button info-icon" onclick="showInfo(this)" 
                                data-request-id="<?php echo $notification['leave_request_id']; ?>" 
                                title="<?php echo translate('Részletek'); ?>">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="response-box" id="responseBox">
    <div class="response-box-header">
        <h5 class="response-box-title">
            <i class="fas fa-reply"></i>
            <span id="responseBoxTitle"><?php echo translate('Válasz megadása'); ?></span>
        </h5>
        <button type="button" class="response-box-close" onclick="closeResponseBox()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="response-box-body">
        <div class="request-details">
            <div class="info-item">
                <div class="info-label">
                    <i class="far fa-user"></i>
                    <?php echo translate('Munkavállaló'); ?>
                </div>
                <div class="info-value" id="requestEmployee"></div>
            </div>
            <div class="info-item">
                <div class="info-label">
                    <i class="fas fa-tag"></i>
                    <?php echo translate('Típus'); ?>
                </div>
                <div class="info-value" id="requestType"></div>
            </div>
            <div class="info-item">
                <div class="info-label">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo translate('Időszak'); ?>
                </div>
                <div class="info-value" id="requestPeriod"></div>
            </div>
            <div class="info-item full-width">
                <div class="info-label">
                    <i class="fas fa-comment"></i>
                    <?php echo translate('Kérelem indoklása'); ?>
                </div>
                <div class="info-value" id="requestMessage"></div>
            </div>

            <div class="info-item full-width" id="responseMessageContainer" style="display: none;">
                <div class="info-label">
                    <i class="fas fa-reply"></i>
                    <?php echo translate('Válasz üzenet'); ?>
                </div>
                <div class="info-value" id="responseMessageText"></div>
            </div>

            <div id="additionalInfoSection" style="display: none;" class="full-width">
                <div class="collapsible-section">
                    <button class="collapsible-button" onclick="toggleDetails(this)">
                        <i class="fas fa-chevron-down"></i>
                        <?php echo translate('További információk'); ?>
                    </button>
                    <div class="collapsible-content">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-briefcase"></i>
                                <?php echo translate('Munkakör'); ?>
                            </div>
                            <div class="info-value" id="requestRole"></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-envelope"></i>
                                <?php echo translate('Email'); ?>
                            </div>
                            <div class="info-value" id="requestEmail"></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-phone"></i>
                                <?php echo translate('Telefonszám'); ?>
                            </div>
                            <div class="info-value" id="requestPhone"></div>
                        </div>
                    </div>
                </div>
            </div>

            <form id="responseForm" class="response-form" style="display: none;">
                <input type="hidden" id="requestId" name="request_id">
                <input type="hidden" id="action" name="action">
                <div class="form-group">
                    <label for="responseMessage" class="info-label">
                        <i class="fas fa-comment"></i>
                        <?php echo translate('Válasz üzenete:'); ?>
                    </label>
                    <textarea class="form-control" id="responseMessage" name="response_message" rows="3" required></textarea>
                </div>
            </form>
        </div>
    </div>
    <div class="response-box-footer" id="responseBoxFooter">
        <button type="button" class="btn btn-secondary" onclick="closeResponseBox()">
            <i class="fas fa-times"></i>
            <?php echo translate('Mégse'); ?>
        </button>
        <button type="button" class="btn btn-primary" id="submitResponse">
            <i class="fas fa-paper-plane"></i>
            <?php echo translate('Küldés'); ?>
        </button>
    </div>
</div>

<!-- Add the confirmation modal -->
<div class="confirmation-modal" id="deleteConfirmationModal">
    <div class="confirmation-content">
        <div class="confirmation-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h5><?php echo translate('Értesítések törlése'); ?></h5>
        </div>
        <div class="confirmation-body">
            <p id="confirmationMessage"><?php echo translate('Biztosan törölni szeretné a kiválasztott értesítéseket?'); ?></p>
            </div>
        <div class="confirmation-footer">
            <button type="button" class="btn-cancel" onclick="closeConfirmationModal()">
                <i class="fas fa-times me-2"></i>
                <?php echo translate('Mégse'); ?>
            </button>
            <button type="button" class="btn-confirm-delete" id="confirmDelete">
                <i class="fas fa-trash-alt me-2"></i>
                <?php echo translate('Törlés'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Add the success notification template -->
<div class="success-notification" id="successNotification">
    <div class="success-notification-header">
        <h5 class="success-notification-title">
            <i class="fas fa-check-circle"></i>
            <span id="successNotificationTitle"><?php echo translate('Sikeres művelet'); ?></span>
        </h5>
        <button type="button" class="success-notification-close" onclick="closeSuccessNotification()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="success-notification-body">
        <p class="success-notification-message" id="successNotificationMessage"></p>
    </div>
</div>

<!-- Add modal overlay -->
<div class="modal-overlay" id="modalOverlay"></div>

<!-- Font Awesome betöltése -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Bootstrap JS és jQuery betöltése -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar navigation
    const sidebarItems = document.querySelectorAll('.sidebar-item');
    const notificationItems = document.querySelectorAll('.notification-item');
    
    function updateCounts() {
        const counts = {
            all: notificationItems.length,
            leave: Array.from(notificationItems).filter(item => item.dataset.category === 'leave').length,
            sick: Array.from(notificationItems).filter(item => item.dataset.category === 'sick').length,
            pending: Array.from(notificationItems).filter(item => item.dataset.status === 'pending').length,
            accepted: Array.from(notificationItems).filter(item => item.dataset.status === 'accepted').length,
            rejected: Array.from(notificationItems).filter(item => item.dataset.status === 'rejected').length
        };
        
        sidebarItems.forEach(item => {
            const category = item.dataset.category;
            const countElement = item.querySelector('.count');
            if (countElement) {
                countElement.textContent = counts[category] || 0;
            }
        });
    }
    
    function filterNotifications(category) {
        notificationItems.forEach(item => {
            if (category === 'all') {
                item.style.display = '';
            } else if (category === 'pending') {
                item.style.display = item.dataset.status === 'pending' ? '' : 'none';
            } else if (category === 'accepted' || category === 'rejected') {
                item.style.display = item.dataset.status === category ? '' : 'none';
            } else {
                item.style.display = item.dataset.category === category ? '' : 'none';
            }
        });
    }
    
    sidebarItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            sidebarItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            filterNotifications(item.dataset.category);
        });
    });
    
    // Továbbfejlesztett keresési funkció
    const searchInput = document.querySelector('.search-input');
    searchInput?.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase().trim();
        
        notificationItems.forEach(item => {
            // Keresendő elemek összegyűjtése
            const sender = item.querySelector('.notification-sender')?.textContent.toLowerCase() || '';
            const subject = item.querySelector('.notification-subject')?.textContent.toLowerCase() || '';
            const preview = item.querySelector('.notification-preview')?.textContent.toLowerCase() || '';
            const status = item.querySelector('.status-badge')?.textContent.toLowerCase() || '';
            
            // Speciális keresési kulcsszavak kezelése
            const isLeaveRequest = subject.includes('szabadság');
            const isSickLeaveRequest = subject.includes('betegállomány');
            
            // Keresési feltételek
            const matchesSearch = 
                sender.includes(searchTerm) || 
                subject.includes(searchTerm) || 
                preview.includes(searchTerm) ||
                status.includes(searchTerm) ||
                // Speciális keresési kulcsszavak
                (searchTerm === 'szabadság' && isLeaveRequest) ||
                (searchTerm === 'beteg' && isSickLeaveRequest) ||
                (searchTerm === 'betegállomány' && isSickLeaveRequest) ||
                // Státusz alapján keresés
                (searchTerm === 'elfogadva' && status.includes('elfogadva')) ||
                (searchTerm === 'elutasítva' && status.includes('elutasítva')) ||
                (searchTerm === 'függőben' && status.includes('függőben'));

            // Elem megjelenítése vagy elrejtése
            item.style.display = matchesSearch ? '' : 'none';
        });
    });
    
    // Select all functionality
    const selectAllButton = document.getElementById('selectAll');
    const deleteButton = document.getElementById('delete');
    const checkboxes = document.querySelectorAll('.notification-checkbox');
    const selectAllIcon = selectAllButton.querySelector('i');

    // Összes kijelölés gomb kezelése
    selectAllButton.addEventListener('click', function() {
        const isAllSelected = Array.from(checkboxes).every(checkbox => checkbox.checked);
        
        // Toggle minden checkbox állapotát
        checkboxes.forEach(checkbox => {
            checkbox.checked = !isAllSelected;
        });

        // Frissítjük az ikont
        selectAllIcon.className = !isAllSelected ? 'fas fa-check-square' : 'far fa-square';
        
        // Frissítjük a törlés gomb állapotát
        updateDeleteButtonState();
    });

    // Egyedi checkbox kezelése
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Frissítjük az "Összes kijelölés" gomb ikonját
            const isAllSelected = Array.from(checkboxes).every(cb => cb.checked);
            selectAllIcon.className = isAllSelected ? 'fas fa-check-square' : 'far fa-square';
            
            // Frissítjük a törlés gomb állapotát
            updateDeleteButtonState();
        });
    });

    // Törlés gomb állapotának frissítése
    function updateDeleteButtonState() {
        const hasSelected = Array.from(checkboxes).some(checkbox => checkbox.checked);
        deleteButton.disabled = !hasSelected;
    }

    // Törlés gomb kezelése
    deleteButton.addEventListener('click', function() {
        const selectedIds = Array.from(checkboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.dataset.notificationId);

        if (selectedIds.length === 0) {
            alert('Válasszon ki legalább egy értesítést a törléshez!');
            return;
        }

        if (confirm('Biztosan törölni szeretné a kiválasztott értesítéseket?')) {
            const formData = new FormData();
            formData.append('notification_ids', JSON.stringify(selectedIds));

            fetch('delete_notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Töröljük a kiválasztott értesítéseket a DOM-ból
                    selectedIds.forEach(id => {
                        const notification = document.querySelector(`[data-notification-id="${id}"]`).closest('.notification-item');
                        if (notification) {
                            notification.remove();
                        }
                    });

                    // Frissítjük a törlés gomb és az összes kijelölés gomb állapotát
                    deleteButton.disabled = true;
                    selectAllIcon.className = 'far fa-square';

                    // Ha nincs több értesítés, frissítjük a felületet
                    if (document.querySelectorAll('.notification-item').length === 0) {
                        location.reload();
                    }
                } else {
                    alert(data.message || 'Hiba történt a törlés során!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Hiba történt a törlés során!');
            });
        }
    });
    
    // Refresh functionality
    document.getElementById('refresh')?.addEventListener('click', () => {
        location.reload();
    });
    
    // Initialize
    updateCounts();
    updateDeleteButtonState();
    
    // Existing functionality remains unchanged
});

// Response box functionality
function showResponseBox(title = 'Válasz megadása') {
    document.getElementById('responseBoxTitle').textContent = title;
    const header = document.querySelector('.response-box-header');
    
    // Reset classes
    header.classList.remove('accept', 'reject', 'details');
    
    // Add appropriate class based on title
    if (title === 'Kérelem elfogadása') {
        header.classList.add('accept');
    } else if (title === 'Kérelem elutasítása') {
        header.classList.add('reject');
    } else if (title === 'Kérelem részletei') {
        header.classList.add('details');
    }
    
    // Show/hide elements based on the type of box
    const isDetails = title === 'Kérelem részletei';
    document.getElementById('responseForm').style.display = isDetails ? 'none' : 'block';
    document.getElementById('responseBoxFooter').style.display = isDetails ? 'none' : 'flex';
    document.getElementById('additionalInfoSection').style.display = isDetails ? 'block' : 'none';
    
    document.getElementById('responseBox').classList.add('show');
    document.getElementById('modalOverlay').classList.add('show');
}

function closeResponseBox() {
    document.getElementById('responseBox').classList.remove('show');
    document.getElementById('modalOverlay').classList.remove('show');
    // Clear form
    document.getElementById('responseForm').reset();
    document.getElementById('requestId').value = '';
    document.getElementById('action').value = '';
}

// Show info functionality
function showInfo(button) {
    const requestId = button.getAttribute('data-request-id');
    console.log('Request ID:', requestId);
    
    // Reset collapsible state
    const collapsibleButton = document.querySelector('.collapsible-button');
    const collapsibleContent = document.querySelector('.collapsible-content');
    if (collapsibleButton) {
        collapsibleButton.classList.remove('active');
    }
    if (collapsibleContent) {
        collapsibleContent.style.display = 'none';
    }
    
    // AJAX call to get request details
    $.ajax({
        url: 'process_request.php',
        method: 'POST',
        data: {
            action: 'get_info',
            request_id: requestId
        },
        success: function(response) {
            console.log('Raw response:', response);
            let data;
            
            try {
                // Ha a response már objektum, használjuk azt, különben parse-oljuk
                data = typeof response === 'object' ? response : JSON.parse(response);
                console.log('Parsed data:', data);
                
                if (data.success) {
                    document.getElementById('requestEmployee').textContent = data.data.employee || 'Nincs megadva';
                    document.getElementById('requestRole').textContent = data.data.role || 'Nincs megadva';
                    document.getElementById('requestEmail').textContent = data.data.email || 'Nincs megadva';
                    document.getElementById('requestPhone').textContent = data.data.telephone || 'Nincs megadva';
                    document.getElementById('requestType').textContent = data.data.type || 'Nincs megadva';
                    
                    // Dátumok formázása
                    const startDate = data.data.start_date ? new Date(data.data.start_date).toLocaleDateString('hu-HU') : 'Nincs megadva';
                    const endDate = data.data.end_date ? new Date(data.data.end_date).toLocaleDateString('hu-HU') : 'Nincs megadva';
                    document.getElementById('requestPeriod').textContent = `${startDate} - ${endDate}`;
                    
                    document.getElementById('requestMessage').textContent = data.data.message || 'Nincs üzenet megadva';
                    
                    // Show response message if exists
                    const responseContainer = document.getElementById('responseMessageContainer');
                    const responseText = document.getElementById('responseMessageText');
                    if (data.data.response_message) {
                        responseText.textContent = data.data.response_message;
                        responseContainer.style.display = 'block';
                    } else {
                        responseContainer.style.display = 'none';
                    }
                    
                    showResponseBox('Kérelem részletei');
                } else {
                    showSuccessNotification(data.message || 'Hiba történt az adatok lekérése során!', 'Hiba');
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Raw response:', response);
                showSuccessNotification('Hiba történt az adatok feldolgozása során!', 'Hiba');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            console.error('XHR:', xhr.responseText);
            showSuccessNotification('Hiba történt a szerver kommunikáció során!', 'Hiba');
        }
    });
}

// Handle accept/reject buttons
document.addEventListener('DOMContentLoaded', function() {
    // Accept request
    document.querySelectorAll('.accept-request').forEach(button => {
        button.addEventListener('click', function() {
            const requestId = this.getAttribute('data-request-id');
            document.getElementById('requestId').value = requestId;
            document.getElementById('action').value = 'accept';
            
            // Fetch request details first
            $.ajax({
                url: 'process_request.php',
                method: 'POST',
                data: {
                    action: 'get_info',
                    request_id: requestId
                },
                success: function(response) {
                    try {
                        // Ha a response már objektum, használjuk azt, különben parse-oljuk
                        const data = typeof response === 'object' ? response : JSON.parse(response);
                        if (data.success) {
                            // Fill in the request details
                            document.getElementById('requestEmployee').textContent = data.data.employee || 'Nincs megadva';
                            document.getElementById('requestType').textContent = data.data.type || 'Nincs megadva';
                            
                            // Dátumok formázása
                            const startDate = data.data.start_date ? new Date(data.data.start_date).toLocaleDateString('hu-HU') : 'Nincs megadva';
                            const endDate = data.data.end_date ? new Date(data.data.end_date).toLocaleDateString('hu-HU') : 'Nincs megadva';
                            document.getElementById('requestPeriod').textContent = `${startDate} - ${endDate}`;
                            
                            document.getElementById('requestMessage').textContent = data.data.message || 'Nincs üzenet megadva';
                            
                            // Show the response box
                            showResponseBox('Kérelem elfogadása');
                            
                            // Show the response form
                            document.getElementById('responseForm').style.display = 'block';
                            document.getElementById('responseBoxFooter').style.display = 'flex';
                        } else {
                            showSuccessNotification(data.message || 'Hiba történt az adatok lekérése során!', 'Hiba');
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Raw response:', response);
                        showSuccessNotification('Hiba történt az adatok feldolgozása során!', 'Hiba');
                    }
                },
                error: function() {
                    showSuccessNotification('Hiba történt a szerver kommunikáció során!', 'Hiba');
                }
            });
        });
    });

    // Reject request
    document.querySelectorAll('.reject-request').forEach(button => {
        button.addEventListener('click', function() {
            const requestId = this.getAttribute('data-request-id');
            document.getElementById('requestId').value = requestId;
            document.getElementById('action').value = 'reject';
            
            // Fetch request details first
            $.ajax({
                url: 'process_request.php',
                method: 'POST',
                data: {
                    action: 'get_info',
                    request_id: requestId
                },
                success: function(response) {
                    try {
                        // Ha a response már objektum, használjuk azt, különben parse-oljuk
                        const data = typeof response === 'object' ? response : JSON.parse(response);
                        if (data.success) {
                            // Fill in the request details
                            document.getElementById('requestEmployee').textContent = data.data.employee || 'Nincs megadva';
                            document.getElementById('requestType').textContent = data.data.type || 'Nincs megadva';
                            
                            // Dátumok formázása
                            const startDate = data.data.start_date ? new Date(data.data.start_date).toLocaleDateString('hu-HU') : 'Nincs megadva';
                            const endDate = data.data.end_date ? new Date(data.data.end_date).toLocaleDateString('hu-HU') : 'Nincs megadva';
                            document.getElementById('requestPeriod').textContent = `${startDate} - ${endDate}`;
                            
                            document.getElementById('requestMessage').textContent = data.data.message || 'Nincs üzenet megadva';
                            
                            // Show the response box
                            showResponseBox('Kérelem elutasítása');
                            
                            // Show the response form
                            document.getElementById('responseForm').style.display = 'block';
                            document.getElementById('responseBoxFooter').style.display = 'flex';
                        } else {
                            showSuccessNotification(data.message || 'Hiba történt az adatok lekérése során!', 'Hiba');
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Raw response:', response);
                        showSuccessNotification('Hiba történt az adatok feldolgozása során!', 'Hiba');
                    }
                },
                error: function() {
                    showSuccessNotification('Hiba történt a szerver kommunikáció során!', 'Hiba');
                }
            });
        });
    });

    // Submit response
    document.getElementById('submitResponse').addEventListener('click', function() {
        const requestId = document.getElementById('requestId').value;
        const action = document.getElementById('action').value;
        const responseMessage = document.getElementById('responseMessage').value;

        if (!responseMessage.trim()) {
            showSuccessNotification('Kérem adjon meg egy válaszüzenetet!', 'Hiba');
            return;
        }

        $.ajax({
            url: 'process_request.php',
            method: 'POST',
            data: {
                action: action,
                request_id: requestId,
                response_message: responseMessage
            },
            success: function(response) {
                const data = JSON.parse(response);
                showSuccessNotification(data.message, data.success ? 'Sikeres művelet' : 'Hiba');
                if (data.success) {
                    closeResponseBox();
                    location.reload(); // Refresh the page to show updated status
                }
            },
            error: function() {
                showSuccessNotification('Hiba történt a művelet során!', 'Hiba');
            }
        });
    });

    // Delete single notification
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const requestId = this.getAttribute('data-notification-id');
            document.getElementById('confirmDelete').setAttribute('data-request-id', requestId);
            showConfirmationModal();
        });
    });

    // Confirm delete
    document.getElementById('confirmDelete').addEventListener('click', function() {
        const requestId = this.getAttribute('data-request-id');
        
        $.ajax({
            url: 'process_request.php',
            method: 'POST',
            data: {
                action: 'delete',
                request_id: requestId
            },
            success: function(response) {
                const data = JSON.parse(response);
                showSuccessNotification(data.message, data.success ? 'Sikeres művelet' : 'Hiba');
            if (data.success) {
                    closeConfirmationModal();
                    location.reload(); // Refresh the page to show updated list
                }
            },
            error: function() {
                showSuccessNotification('Hiba történt a törlés során!', 'Hiba');
            }
        });
    });
});

// Confirmation modal functionality
function showConfirmationModal() {
    document.getElementById('deleteConfirmationModal').classList.add('show');
    document.getElementById('modalOverlay').classList.add('show');
}

function closeConfirmationModal() {
    document.getElementById('deleteConfirmationModal').classList.remove('show');
    document.getElementById('modalOverlay').classList.remove('show');
}

// Success notification functionality
function showSuccessNotification(message, title = 'Sikeres művelet') {
    const notification = document.getElementById('successNotification');
    document.getElementById('successNotificationTitle').textContent = title;
    document.getElementById('successNotificationMessage').textContent = message;
    notification.classList.add('show');
    
    // Automatically hide after 3 seconds
    setTimeout(() => {
        closeSuccessNotification();
    }, 3000);
}

function closeSuccessNotification() {
    document.getElementById('successNotification').classList.remove('show');
}

// Close response box when clicking outside
document.getElementById('modalOverlay').addEventListener('click', closeResponseBox);

// Prevent closing when clicking inside the response box
document.getElementById('responseBox').addEventListener('click', function(e) {
    e.stopPropagation();
});

// Close modals when clicking outside
document.getElementById('modalOverlay').addEventListener('click', () => {
    closeConfirmationModal();
    closeResponseBox();
    closeSuccessNotification();
});

// Prevent closing when clicking inside modals
document.getElementById('deleteConfirmationModal')?.addEventListener('click', (e) => {
    e.stopPropagation();
});

document.getElementById('successNotification')?.addEventListener('click', (e) => {
    e.stopPropagation();
});

function toggleDetails(button) {
    button.classList.toggle('active');
    const content = button.nextElementSibling;
    if (content.style.display === 'block') {
        content.style.display = 'none';
    } else {
        content.style.display = 'block';
    }
}
</script>

<?php require_once '../includes/layout/footer.php'; ?> 