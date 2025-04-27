<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}

// Jogosultság ellenőrzése
$user_id = $_SESSION['user_id'];
$role_check_sql = "SELECT r.role_name 
                   FROM roles r 
                   JOIN user_to_roles utr ON r.id = utr.role_id 
                   WHERE utr.user_id = ?";

$role_stmt = mysqli_prepare($conn, $role_check_sql);
mysqli_stmt_bind_param($role_stmt, "i", $user_id);
mysqli_stmt_execute($role_stmt);
$role_result = mysqli_stmt_get_result($role_stmt);

$has_access = false;
while ($role = mysqli_fetch_assoc($role_result)) {
    if ($role['role_name'] === 'Cég tulajdonos' || $role['role_name'] === 'Karbantartó') {
        $has_access = true;
        break;
    }
}

if (!$has_access) {
    header('Location: ../dashboard/index.php');
    exit();
}

require_once '../includes/layout/header.php'; 

// Módosítsuk a lekérdezést a márka és QR kód hozzáadásával
$broken_sql = "SELECT 
    s.id,
    sb.name as brand_name,
    sm.name as model_name,
    st.name as type_name,
    s.qr_code,
    ss.name as status_name
    FROM stuffs s
    LEFT JOIN stuff_type st ON s.type_id = st.id
    LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
    LEFT JOIN stuff_model sm ON s.model_id = sm.id
    LEFT JOIN stuff_status ss ON s.stuff_status_id = ss.id
    WHERE s.company_id = ? 
    AND ss.name IN ('" . translate('Hibás') . "', '" . translate('Törött') . "')
    AND s.id NOT IN (
        SELECT stuffs_id FROM maintenance 
        WHERE maintenance_status_id NOT IN (
            SELECT id FROM maintenance_status WHERE name IN ('" . translate('Befejezve') . "', '" . translate('Törölve') . "')
        )
    )
    AND s.id NOT IN (
        SELECT id FROM stuffs 
        WHERE stuff_status_id IN (
            SELECT id FROM stuff_status WHERE name = '" . translate('Kiszelektálás alatt') . "'
        )
    )
    ORDER BY s.id DESC";

$broken_stmt = mysqli_prepare($conn, $broken_sql);
mysqli_stmt_bind_param($broken_stmt, "i", $_SESSION['company_id']);
mysqli_stmt_execute($broken_stmt);
$broken_result = mysqli_stmt_get_result($broken_stmt);

// Konvertáljuk az eredményt tömbbé
$broken_items = [];
while ($row = mysqli_fetch_assoc($broken_result)) {
    $broken_items[] = $row;
}

?>

<style>
/* Base styles */
.maintenance-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 0 1rem;
}

.maintenance-header h1 {
    margin: 0;
    color: #2c3e50;
    font-size: 1.75rem;
}

.card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
    border: none;
    width: 100%;
    padding: 0.5rem;
}

.card-header {
    background: #f8f9fa;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #edf2f7;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}

.card-header h2 {
    margin: 0;
    font-size: 1.2rem;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-header h2 i {
    color: #e74c3c;
}

.card-body {
    display: none;
    transition: all 0.3s ease;
}

.card-body.show {
    display: block;
}

.card-header .toggle-icon {
    transition: transform 0.3s ease;
}

.card-header.active .toggle-icon {
    transform: rotate(180deg);
}

.card-header:hover {
    background: #f0f0f0;
}

.table-container {
    padding: 1rem;
    overflow-x: auto;
    width: 100%;
    min-width: auto;
    -webkit-overflow-scrolling: touch;
}

.table {
    width: 100%;
    margin: 0;
    white-space: nowrap;
}

.table th {
    background: #f8f9fa;
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border: none;
    font-size: 0.95rem;
}

.table td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
    background: #fff;
    border-top: 1px solid #edf2f7;
    border-bottom: 1px solid #edf2f7;
    white-space: nowrap;
}

.table tr td:first-child {
    border-left: 1px solid #edf2f7;
    border-radius: 8px 0 0 8px;
}

.table tr td:last-child {
    border-right: 1px solid #edf2f7;
    border-radius: 0 8px 8px 0;
}

.status-badge {
    padding: 0.4rem 0.8rem;
    min-width: 80px;
    text-align: center;
    font-size: 0.875rem;
    display: inline-block;
}

/* Button styles */
.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn i {
    font-size: 1rem;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 0;
    width: 90%;
    max-width: 500px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.modal-header {
    padding: 1rem;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 1rem;
}

/* Form styles */
.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2c3e50;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 1rem;
}

/* Responsive styles */
@media (max-width: 768px) {
    .maintenance-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .maintenance-header h1 {
        font-size: 1.5rem;
    }

    .card {
        margin: 1rem;
        padding: 0.25rem;
    }

    .card-header {
        padding: 0.75rem 1rem;
    }

    .card-header h2 {
        font-size: 1rem;
    }

    .table-container {
        padding: 0.5rem;
    }

    .table th,
    .table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }

    .status-badge {
        min-width: 60px;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
    }

    .modal-content {
        margin: 5% auto;
        width: 95%;
    }

    .modal-header {
        padding: 0.75rem;
    }

    .modal-body {
        padding: 0.75rem;
    }
}

@media (max-width: 576px) {
    .maintenance-header h1 {
        font-size: 1.25rem;
    }

    .card {
        margin: 0.5rem;
    }

    .card-header {
        padding: 0.5rem;
    }

    .table th,
    .table td {
        padding: 0.375rem;
        font-size: 0.75rem;
    }

    .status-badge {
        min-width: 50px;
        padding: 0.25rem;
        font-size: 0.7rem;
    }

    .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
    }

    .btn i {
        font-size: 0.875rem;
    }

    .modal-content {
        margin: 0;
        width: 100%;
        height: 100%;
        border-radius: 0;
    }
}

/* Print styles */
@media print {
    .card {
        box-shadow: none;
        border: 1px solid #dee2e6;
    }

    .btn {
        display: none;
    }

    .table-container {
        overflow: visible;
    }

    .table {
        white-space: normal;
    }
}

.status-broken {
    background: #fee2e2;
    color: #dc2626;
}

.status-faulty {
    background: #fef3c7;
    color: #d97706;
}

.status-in-progress {
    background: #e0f2fe;
    color: #0284c7;
}

.status-pending {
    background: #f3e8ff;
    color: #7e22ce;
}

.status-completed {
    background: #dcfce7;
    color: #16a34a;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-maintenance {
    background: #8b5cf6;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 130px;
}

.btn-maintenance:hover {
    background: #7c3aed;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.85rem;
}

.actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-start;
    align-items: center;
    min-width: 160px;
    padding: 0.5rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    border: none;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    white-space: nowrap;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.btn-maintenance {
    background-color: #8b5cf6;
    color: white;
}

.btn-maintenance:hover {
    background-color: #7c3aed;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
    padding: 0.25rem 0.5rem;
    min-width: 40px;
    justify-content: center;
}

.btn-info:hover {
    background-color: #138496;
}

.btn i {
    font-size: 0.875rem;
}

.table td {
    vertical-align: middle;
    padding: 0.75rem;
}

.qr-code {
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
    letter-spacing: 0.75px;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-broken {
    background-color: #fee2e2;
    color: #dc2626;
}

.status-faulty {
    background-color: #fef3c7;
    color: #d97706;
}

.table th:nth-child(1), 
.table td:nth-child(1) { width: 10%; }
.table th:nth-child(2), 
.table td:nth-child(2) { width: 10%; }
.table th:nth-child(3), 
.table td:nth-child(3) { width: 12%; }
.table th:nth-child(4), 
.table td:nth-child(4) { width: 20%; }
.table th:nth-child(5), 
.table td:nth-child(5) { width: 10%; }
.table th:nth-child(6), 
.table td:nth-child(6) { 
    width: 15%;
    min-width: 180px;
}

/* Animációk */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.table tr {
    animation: slideIn 0.3s ease-out forwards;
}

.table tr:nth-child(n) {
    animation-delay: calc(n * 0.05s);
}

/* Dropdown menü hover viselkedés */
.nav-item.dropdown:hover .dropdown-menu,
.dropdown-menu:hover {
    display: block;
    opacity: 1;
    visibility: visible;
    transition: opacity 0.3s, visibility 0.3s;
}

.dropdown-menu {
    display: block;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
    margin-top: 0;
}

.nav-item.dropdown .dropdown-menu {
    transition-delay: 0.1s;
}

.dropdown-menu:hover {
    transition-delay: 0s;
}

.nav-item.dropdown {
    padding-bottom: 5px;
}

.dropdown-menu {
    margin-top: -5px;
}

/* Modal stílusok */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 0;
    width: 90%;
    max-width: 500px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.modal-header {
    padding: 1rem;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #2c3e50;
}

.close {
    font-size: 1.5rem;
    font-weight: bold;
    color: #6c757d;
    cursor: pointer;
}

.close:hover {
    color: #343a40;
}

#maintenanceForm {
    padding: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2c3e50;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 1rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

/* A form-group osztályokat módosítsuk a kötelező mezőknél */
.form-group.required label::after {
    content: "*";
    color: #dc3545;
    margin-left: 4px;
}

.form-group input:required:invalid {
    border-color: #dc3545;
}

.form-group input:required:valid {
    border-color: #28a745;
}

/* Tooltip a kötelező mezőkhöz */
.form-info {
    background-color: #e2e8f0;
    padding: 0.75rem;
    border-radius: 4px;
    margin: 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #4a5568;
}

.form-info i {
    color: #3182ce;
}

/* Adjuk hozzá a meglévő stílusokhoz */
.btn-success {
    background-color: #28a745;
    color: white;
}

.btn-success:hover {
    background-color: #218838;
}

.actions {
    display: flex;
    gap: 0.3rem;
    justify-content: center;
}

.actions .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.form-control {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 1rem;
    background-color: #fff;
}

.form-control:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

/* Notification stílusok */
.notification {
    position: fixed;
    top: 80px;
    right: 20px;
    background-color: #28a745;
    color: white;
    padding: 15px 25px;
    border-radius: 4px;
    display: none;
    z-index: 1000;
    animation: slideIn 0.5s ease-out;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
}

.notification i {
    font-size: 20px;
    color: white;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.notification.success {
    background-color: #28a745;
}

.notification.error {
    background-color: #dc3545;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
}

.description-box {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 1rem;
    margin-top: 0.5rem;
    white-space: pre-wrap;
    max-height: 200px;
    overflow-y: auto;
}

.info-content {
    font-size: 0.95rem;
}

.info-content p {
    margin-bottom: 0.75rem;
}

/* Konténer módosítások */
.container {
    max-width: 98% !important;
    margin: 0 auto;
    padding: 25px;
    width: 100%;
}

/* Táblázat konténer módosítások */
.table-container {
    padding: 1rem;
    overflow-x: auto;
    width: 100%;
    min-width: auto;
}

/* Táblázat oszlopok szélességének módosítása */
.table th:nth-child(1), 
.table td:nth-child(1) { width: 10%; }

.table th:nth-child(2), 
.table td:nth-child(2) { width: 10%; }

.table th:nth-child(3), 
.table td:nth-child(3) { width: 12%; }

.table th:nth-child(4), 
.table td:nth-child(4) { width: 20%; }

.table th:nth-child(5), 
.table td:nth-child(5) { width: 10%; }

.table th:nth-child(6), 
.table td:nth-child(6) { 
    width: 15%;
    min-width: 180px;
}

/* Műveletek oszlop gombjai */
.actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-start;
    align-items: center;
    min-width: 160px;
}

/* Card stílusok módosítása */
.card {
    margin: 0 auto 2rem;
    width: 100%;
    padding: 0.5rem;
}

/* Táblázat celláinak padding módosítása */
.table td, .table th {
    padding: 0.75rem 1rem;
    white-space: nowrap;
}

/* QR kód stílus módosítása */
.qr-code {
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
    letter-spacing: 0.75px;
}

/* Státusz badge módosítása */
.status-badge {
    padding: 0.4rem 0.8rem;
    min-width: 80px;
    text-align: center;
}

/* Gombok méretének módosítása */
.btn-maintenance {
    min-width: 130px;
}

.btn-info {
    min-width: 40px;
    justify-content: center;
}

/* Tooltip gombok alapstílusa */
.tooltip-btn {
    position: relative;
    overflow: hidden;
    min-width: 36px;
    width: 36px;
    height: 36px;
    padding: 0;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}

/* Az ikon konténer */
.tooltip-btn .icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: auto;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

/* A szöveg konténer */
.tooltip-btn .text {
    position: absolute;
    left: 100%;
    white-space: nowrap;
    opacity: 0;
    transition: all 0.3s ease;
    pointer-events: none;
    transform: translateX(-20px);
}

/* Hover effektus */
.tooltip-btn:hover {
    width: auto;
    min-width: 140px;
    padding: 0 1rem;
    justify-content: flex-start;
}

.tooltip-btn:hover .icon {
    margin-right: 8px;
}

.tooltip-btn:hover .text {
    position: static;
    opacity: 1;
    transform: translateX(0);
    pointer-events: auto;
}

/* Gomb specifikus stílusok */
.btn-maintenance.tooltip-btn {
    background-color: #8b5cf6;
}

.btn-maintenance.tooltip-btn:hover {
    background-color: #7c3aed;
}

.btn-info.tooltip-btn {
    background-color: #17a2b8;
}

.btn-info.tooltip-btn:hover {
    background-color: #138496;
}

/* Actions konténer módosítása */
.actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-start;
    align-items: center;
    min-width: 200px;
    padding: 0.5rem;
}

/* Ikonok méretezése */
.tooltip-btn .icon i {
    font-size: 1rem;
    width: 16px;
    text-align: center;
}

/* Táblázat sorok közötti távolság */
.table tr {
    margin-bottom: 0.75rem;
}

/* Counter badge módosítása */
.header-right .counter {
    font-size: 0.95rem;
    padding: 0.4rem 0.8rem;
}

/* Dátum modal stílusok */
#maintenanceDateModal .modal-content {
    max-width: 500px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

#maintenanceDateModal .modal-header {
    background: #f8f9fa;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #edf2f7;
    border-radius: 12px 12px 0 0;
}

#maintenanceDateModal .modal-header h3 {
    font-size: 1.25rem;
    color: #2c3e50;
    margin: 0;
}

#maintenanceDateModal .form-group {
    margin-bottom: 1.5rem;
    padding: 0 1.5rem;
}

#maintenanceDateModal .form-group:first-of-type {
    margin-top: 1.5rem;
}

#maintenanceDateModal .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

#maintenanceDateModal .form-group input[type="date"] {
    width: 100%;
    padding: 0.625rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.2s;
}

#maintenanceDateModal .form-group input[type="date"]:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

#maintenanceDateModal .form-group.required label:after {
    content: "*";
    color: #ef4444;
    margin-left: 4px;
}

#maintenanceDateModal .form-actions {
    padding: 1.25rem 1.5rem;
    background: #f8f9fa;
    border-top: 1px solid #edf2f7;
    border-radius: 0 0 12px 12px;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

#maintenanceDateModal .btn {
    padding: 0.625rem 1.25rem;
    font-weight: 500;
    border-radius: 6px;
    transition: all 0.2s;
}

#maintenanceDateModal .btn-primary {
    background: #3b82f6;
    color: white;
}

#maintenanceDateModal .btn-primary:hover {
    background: #2563eb;
}

#maintenanceDateModal .btn-secondary {
    background: #e5e7eb;
    color: #4b5563;
}

#maintenanceDateModal .btn-secondary:hover {
    background: #d1d5db;
}

/* Close button stílus */
#maintenanceDateModal .close {
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    transition: color 0.2s;
}

#maintenanceDateModal .close:hover {
    color: #374151;
}

/* Figyelmeztető üzenet stílusok */
#maintenanceDateModal .warning-message {
    margin: 0 1.5rem 1.5rem;
    padding: 0.75rem 1rem;
    background-color: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    color: #dc2626;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

#maintenanceDateModal .warning-message i {
    font-size: 1.1rem;
}

/* Modal tartalom módosítása a warning miatt */
#maintenanceDateModal .form-group:last-of-type {
    margin-bottom: 1rem;
}

/* Form actions módosítása */
#maintenanceDateModal .form-actions {
    margin-top: 0;
}

/* Modal stílusok */
#maintenanceModal .modal-content {
    max-width: 500px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

#maintenanceModal .modal-header {
    background: #f8f9fa;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #edf2f7;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#maintenanceModal .modal-header h3 {
    font-size: 1.25rem;
    color: #2c3e50;
    margin: 0;
}

#maintenanceModal .close {
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    transition: color 0.2s;
}

#maintenanceModal .close:hover {
    color: #374151;
}

#maintenanceModal .form-group {
    margin-bottom: 1.5rem;
    padding: 1.5rem 1.5rem 0;
}

#maintenanceModal .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

#maintenanceModal .form-control {
    width: 100%;
    padding: 0.625rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.2s;
}

#maintenanceModal .form-control:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

#maintenanceModal .form-actions {
    padding: 1.25rem 1.5rem;
    background: #f8f9fa;
    border-top: 1px solid #edf2f7;
    border-radius: 0 0 12px 12px;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

#maintenanceModal .btn {
    padding: 0.625rem 1.25rem;
    font-weight: 500;
    border-radius: 6px;
    transition: all 0.2s;
}

#maintenanceModal .btn-primary {
    background: #3b82f6;
    color: white;
}

#maintenanceModal .btn-primary:hover {
    background: #2563eb;
}

#maintenanceModal .btn-secondary {
    background: #e5e7eb;
    color: #4b5563;
}

#maintenanceModal .btn-secondary:hover {
    background: #d1d5db;
}

/* Befejezés Modal stílusok */
#completeModal .modal-content {
    max-width: 600px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

#completeModal .modal-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid #edf2f7;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#completeModal .modal-header h3 {
    font-size: 1.25rem;
    color: #2c3e50;
    margin: 0;
    font-weight: 600;
}

#completeModal .form-group {
    margin: 1.5rem;
}

#completeModal .form-group label {
    display: block;
    margin-bottom: 0.75rem;
    color: #374151;
    font-weight: 500;
}

#completeModal textarea.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.95rem;
    min-height: 120px;
    resize: vertical;
    transition: all 0.2s;
}

#completeModal textarea.form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

#completeModal .warning-message {
    margin: 0 1.5rem 1.5rem;
    padding: 1rem;
    background-color: #fff5f5;
    border: 1px solid #feb2b2;
    border-radius: 8px;
    color: #e53e3e;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#completeModal .warning-message i {
    font-size: 1.25rem;
}

#completeModal .form-actions {
    padding: 1.5rem;
    background: #f8f9fa;
    border-top: 1px solid #edf2f7;
    border-radius: 0 0 12px 12px;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

#completeModal .btn {
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s;
}

#completeModal .btn-success {
    background: #10b981;
    color: white;
}

#completeModal .btn-success:hover {
    background: #059669;
}

#completeModal .btn-secondary {
    background: #e5e7eb;
    color: #4b5563;
}

#completeModal .btn-secondary:hover {
    background: #d1d5db;
}

#completeModal .close {
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    transition: color 0.2s;
    line-height: 1;
}

#completeModal .close:hover {
    color: #374151;
}

/* Placeholder stílus */
#completeModal textarea::placeholder {
    color: #9ca3af;
}

/* Törlés megerősítő Modal stílusok */
#deleteConfirmModal .modal-content {
    max-width: 500px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

#deleteConfirmModal .modal-header {
    background: #f8f9fa;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #edf2f7;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#deleteConfirmModal .modal-header h3 {
    font-size: 1.25rem;
    color: #2c3e50;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#deleteConfirmModal .warning-message {
    margin: 0;
    padding: 1rem;
    background-color: #fff5f5;
    border: 1px solid #feb2b2;
    border-radius: 8px;
    color: #e53e3e;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#deleteConfirmModal .warning-message i {
    font-size: 1.25rem;
    color: #dc3545;
}

#deleteConfirmModal .modal-body {
    padding: 1.5rem;
}

#deleteConfirmModal .modal-body p {
    margin-top: 1.5rem;
    text-align: center;
    font-size: 1.1rem;
    color: #4a5568;
}

#deleteConfirmModal .form-actions {
    padding: 1.5rem;
    background: #f8f9fa;
    border-top: 1px solid #edf2f7;
    display: flex;
    justify-content: center;
    gap: 1rem;
}

#deleteConfirmModal .btn {
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

#deleteConfirmModal .btn i {
    font-size: 1rem;
}

#deleteConfirmModal .btn-secondary {
    background: #e5e7eb;
    color: #4b5563;
}

#deleteConfirmModal .btn-secondary:hover {
    background: #d1d5db;
}

#deleteConfirmModal .btn-danger {
    background: #dc3545;
    color: white;
}

#deleteConfirmModal .btn-danger:hover {
    background: #c82333;
}

#deleteConfirmModal .close {
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    transition: color 0.2s;
}

#deleteConfirmModal .close:hover {
    color: #374151;
}

/* Meglévő stílusok megtartása */

/* Dropdown Menu Styles */
.profile-dropdown {
    position: relative;
    z-index: 9999;
}

.profile-trigger {
    cursor: pointer;
}

#profileDropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    min-width: 200px;
    z-index: 9999;
    margin-top: 0.5rem;
    padding: 0.5rem 0;
}

#profileDropdown.show {
    display: block !important;
}

#profileDropdown a {
    color: #333;
    padding: 10px 20px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.95rem;
}

#profileDropdown a:hover {
    background-color: #f5f6fa;
}

#profileDropdown hr {
    margin: 8px 0;
    border: none;
    border-top: 1px solid #edf2f7;
}

/* Ensure dropdown is above other elements */
.user-menu {
    position: relative;
    z-index: 9999;
}
</style>

<!-- Értesítés sáv -->
<div id="notification" class="notification">
    <div class="notification-content">
        <i class="fas fa-check-circle"></i>
        <span id="notification-message"></span>
    </div>
</div>

<div class="maintenance-header">
    <h1 class="page-title"><?php echo translate('Karbantartások'); ?></h1>
</div>

<!-- Hibás eszközök megjelenítése -->
<div class="card mb-4 broken-list">
    <div class="card-header" onclick="togglePanel(this)">
        <div class="header-left">
            <h2><i class="fas fa-exclamation-triangle"></i> <?php echo translate('Hibás eszközök'); ?></h2>
        </div>
        <div class="header-right">
            <span class="counter"><?php echo count($broken_items); ?> <?php echo translate('eszköz'); ?></span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
    </div>
    <div class="card-body show">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo translate('Márka'); ?></th>
                        <th><?php echo translate('Modell'); ?></th>
                        <th><?php echo translate('Típus'); ?></th>
                        <th><?php echo translate('QR kód'); ?></th>
                        <th><?php echo translate('Státusz'); ?></th>
                        <th><?php echo translate('Műveletek'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($broken_items)): ?>
                        <?php foreach ($broken_items as $item): ?>
                            <tr data-stuff-id="<?php echo $item['id']; ?>">
                                <td><?php echo htmlspecialchars($item['brand_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['model_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['type_name']); ?></td>
                                <td><code class="qr-code"><?php echo htmlspecialchars($item['qr_code']); ?></code></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($item['status_name']) === 'törött' ? 'status-broken' : 'status-faulty'; ?>">
                                        <?php echo htmlspecialchars($item['status_name']); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <button class="btn btn-maintenance btn-sm tooltip-btn" onclick="addMaintenance(<?php echo $item['id']; ?>)" data-tooltip="Karbantartás">
                                        <span class="icon"><i class="fas fa-tools"></i></span>
                                        <span class="text">Karbantartás</span>
                                    </button>
                                    <button class="btn btn-info btn-sm tooltip-btn" onclick="showInfo(<?php echo $item['id']; ?>)" data-tooltip="Információ">
                                        <span class="icon"><i class="fas fa-info-circle"></i></span>
                                        <span class="text">Információ</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center"><?php echo translate('Nincs hibás eszköz'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Karbantartások listája -->
<div class="card maintenance-list">
    <div class="card-header" onclick="togglePanel(this)">
        <h2><i class="fas fa-clipboard-list"></i> <?php echo translate('Aktív karbantartások'); ?></h2>
        <div class="header-right">
            <?php
            // Aktív karbantartások lekérdezése
            $maintenance_sql = "SELECT 
                m.id,
                m.stuffs_id,
                m.servis_startdate,
                m.servis_planenddate,
                m.servis_currectenddate,
                m.maintenance_status_id,
                sb.name as brand_name,
                sm.name as model_name,
                st.name as type_name,
                ms.name as maintenance_status,
                u.firstname,
                u.lastname
                FROM maintenance m
                LEFT JOIN stuffs s ON m.stuffs_id = s.id
                LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
                LEFT JOIN stuff_model sm ON s.model_id = sm.id
                LEFT JOIN stuff_type st ON s.type_id = st.id
                LEFT JOIN maintenance_status ms ON m.maintenance_status_id = ms.id
                LEFT JOIN user u ON m.user_id = u.id
                WHERE m.company_id = ? 
                AND m.maintenance_status_id NOT IN (
                    SELECT id FROM maintenance_status WHERE name IN ('" . translate('Befejezve') . "', '" . translate('Törölve') . "')
                )
                ORDER BY m.servis_startdate DESC";

            $stmt = mysqli_prepare($conn, $maintenance_sql);
            mysqli_stmt_bind_param($stmt, "i", $_SESSION['company_id']);
            mysqli_stmt_execute($stmt);
            $maintenance_result = mysqli_stmt_get_result($stmt);
            ?>
            <span class="counter"><?php echo mysqli_num_rows($maintenance_result); ?> <?php echo translate('karbantartás'); ?></span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo translate('Márka'); ?></th>
                        <th><?php echo translate('Modell'); ?></th>
                        <th><?php echo translate('Típus'); ?></th>
                        <th><?php echo translate('Tervezett kezdés'); ?></th>
                        <th><?php echo translate('Tervezett befejezés'); ?></th>
                        <th><?php echo translate('Csúszás esetén'); ?></th>
                        <th><?php echo translate('Státusz'); ?></th>
                        <th><?php echo translate('Felelős'); ?></th>
                        <th><?php echo translate('Műveletek'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($maintenance_result) > 0) {
                        while ($row = mysqli_fetch_assoc($maintenance_result)) {
                            // Státusz szín meghatározása
                            $statusClass = '';
                            $maintenance_status = $row['maintenance_status'] ?? '';
                            switch(strtolower($maintenance_status)) {
                                case 'javítás alatt':
                                    $statusClass = 'status-in-progress';
                                    break;
                                case 'várakozik':
                                    $statusClass = 'status-pending';
                                    break;
                                case 'befejezve':
                                    $statusClass = 'status-completed';
                                    break;
                                case 'törölve':
                                    $statusClass = 'status-cancelled';
                                    break;
                                default:
                                    $statusClass = 'status-pending';
                            }

                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['brand_name'] ?? '') . "</td>";
                            echo "<td>" . htmlspecialchars($row['model_name'] ?? '') . "</td>";
                            echo "<td>" . htmlspecialchars($row['type_name'] ?? '') . "</td>";
                            echo "<td>" . date('Y-m-d', strtotime($row['servis_startdate'])) . "</td>";
                            echo "<td>" . date('Y-m-d', strtotime($row['servis_planenddate'])) . "</td>";
                            echo "<td>" . ($row['servis_currectenddate'] ? date('Y-m-d', strtotime($row['servis_currectenddate'])) : '-') . "</td>";
                            echo "<td><span class='status-badge {$statusClass}'>" . htmlspecialchars($maintenance_status) . "</span></td>";
                            echo "<td>" . htmlspecialchars(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')) . "</td>";
                            echo "<td class='actions'>";
                            echo "<button class='btn btn-success btn-sm tooltip-btn' onclick='completeMaintenance({$row['id']}, {$row['stuffs_id']})' title='Befejezés' data-maintenance-id='{$row['id']}' data-stuffs-id='{$row['stuffs_id']}'>";
                            echo "<span class='icon'><i class='fas fa-check-circle'></i></span>";
                            echo "<span class='text'>Befejezés</span>";
                            echo "</button>";
                            
                            // Dátumok formázása
                            $start_date = date('Y-m-d', strtotime($row['servis_startdate']));
                            $plan_end_date = date('Y-m-d', strtotime($row['servis_planenddate']));
                            $current_end_date = !empty($row['servis_currectenddate']) ? date('Y-m-d', strtotime($row['servis_currectenddate'])) : '';
                            $status_id = intval($row['maintenance_status_id']);
                            
                            echo "<button class='btn btn-primary btn-sm tooltip-btn' onclick='editMaintenance({$row['id']}, \"{$start_date}\", \"{$plan_end_date}\", \"{$current_end_date}\", {$status_id})' title='" . translate('Szerkesztés') . "'>";
                            echo "<span class='icon'><i class='fas fa-pencil-alt'></i></span>";
                            echo "<span class='text'>" . translate('Szerkesztés') . "</span>";
                            echo "</button>";
                            
                            echo "<button class='btn btn-danger btn-sm tooltip-btn' onclick='deleteMaintenance({$row['id']}, {$row['stuffs_id']})' title='" . translate('Törlés') . "'>";
                            echo "<span class='icon'><i class='fas fa-trash-alt'></i></span>";
                            echo "<span class='text'>" . translate('Törlés') . "</span>";
                            echo "</button>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' class='text-center'>" . translate('Nincs aktív karbantartás') . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Karbantartás Modal -->
<div class="modal" id="maintenanceModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><?php echo translate('Karbantartás szerkesztése'); ?></h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form id="maintenanceForm" method="post">
            <input type="hidden" id="maintenance_id" name="maintenance_id">
            <div class="form-group">
                <label for="maintenance_status"><?php echo translate('Státusz'); ?></label>
                <select id="maintenance_status" name="maintenance_status_id" class="form-control" required>
                    <?php
                    $status_sql = "SELECT id, name FROM maintenance_status WHERE name != '" . translate('Befejezve') . "'";
                    $status_result = mysqli_query($conn, $status_sql);
                    while ($status = mysqli_fetch_assoc($status_result)) {
                        echo "<option value='" . $status['id'] . "'>" . ucfirst($status['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="delay_end_date"><?php echo translate('Csúszás esetén végdátum'); ?></label>
                <input type="date" id="delay_end_date" name="delay_end_date" class="form-control">
                <small class="form-text text-muted"><?php echo translate('Csak akkor töltse ki, ha a karbantartás a tervezett időn túl csúszik'); ?></small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo translate('Mentés'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Info Modal -->
<div class="modal" id="infoModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo translate('Bejelentés információk'); ?></h3>
            <span class="close" onclick="closeInfoModal()">&times;</span>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <div class="info-content">
                <p><strong><?php echo translate('Bejelentő'); ?>:</strong> <span id="reporter-name"></span></p>
                <p><strong><?php echo translate('Bejelentés dátuma'); ?>:</strong> <span id="report-date"></span></p>
                <p><strong><?php echo translate('Leírás'); ?>:</strong></p>
                <div id="report-description" class="description-box"></div>
            </div>
        </div>
    </div>
</div>

<!-- Új Karbantartás Dátum Modal -->
<div class="modal" id="maintenanceDateModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo translate('Karbantartás időpontjai'); ?></h3>
            <span class="close" onclick="closeDateModal()">&times;</span>
        </div>
        <form id="maintenanceDateForm" method="post">
            <input type="hidden" id="date_stuff_id" name="stuff_id">
            <div class="form-group required">
                <label for="maintenance_start_date"><?php echo translate('Kezdés dátuma'); ?></label>
                <input type="date" id="maintenance_start_date" name="start_date" required class="form-control">
            </div>
            <div class="form-group required">
                <label for="maintenance_end_date"><?php echo translate('Tervezett befejezés'); ?></label>
                <input type="date" id="maintenance_end_date" name="end_date" required class="form-control">
            </div>
            <div class="warning-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo translate('Figyelem! A dátumok mentése után nem módosíthatók!'); ?>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDateModal()"><?php echo translate('Mégse'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo translate('Mentés'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Befejezés Modal -->
<div class="modal" id="completeModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-check-circle"></i>
                <?php echo translate('Karbantartás befejezése'); ?>
            </h3>
            <span class="close" onclick="closeCompleteModal()">&times;</span>
        </div>
        <form id="completeForm" method="post">
            <input type="hidden" id="complete_maintenance_id" name="maintenance_id">
            <input type="hidden" id="complete_stuff_id" name="stuff_id">
            <div class="form-group">
                <label for="complete_description">
                    <i class="fas fa-clipboard-list"></i>
                    <?php echo translate('Elvégzett munka leírása'); ?>
                </label>
                <textarea 
                    id="complete_description" 
                    name="description" 
                    class="form-control" 
                    required 
                    rows="5"
                    placeholder="<?php echo translate('Részletezze az elvégzett karbantartási munkálatokat...'); ?>"
                ></textarea>
            </div>
            <div class="warning-message">
                <i class="fas fa-exclamation-triangle"></i>
                <span>
                    <strong><?php echo translate('Figyelem'); ?>!</strong>
                    <?php echo translate('A befejezés után a karbantartás és az eszköz állapota nem módosítható. Az eszköz automatikusan működőképes állapotba kerül.'); ?>
                </span>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeCompleteModal()"><?php echo translate('Mégse'); ?></button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i>
                    <?php echo translate('Befejezés'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Törlés megerősítő Modal -->
<div class="modal" id="deleteConfirmModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
                <?php echo translate('Karbantartás törlése'); ?>
            </h3>
            <span class="close" onclick="closeDeleteConfirmModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="warning-message">
                <i class="fas fa-exclamation-circle"></i>
                <span>
                    <strong><?php echo translate('Figyelem'); ?>!</strong>
                    <?php echo translate('Az eszköz státusza "Kiszelektálás alatt" állapotba kerül.'); ?>
                </span>
            </div>
            <p>
                <?php echo translate('Biztosan törölni szeretné ezt a karbantartást?'); ?>
            </p>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteConfirmModal()">
                <i class="fas fa-times"></i>
                <?php echo translate('Mégse'); ?>
            </button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                <i class="fas fa-trash-alt"></i>
                <?php echo translate('Törlés'); ?>
            </button>
        </div>
    </div>
</div>

<script>
// Add these two utility functions at the beginning of the script section
function getStatusClass(status) {
    switch(status) {
        case 'javítás alatt':
            return 'status-in-progress';
        case 'várakozik':
            return 'status-pending';
        case 'befejezve':
            return 'status-completed';
        case 'törölve':
            return 'status-cancelled';
        default:
            return 'status-pending';
    }
}

function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) {
        return '';
    }
    return unsafe
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Add the togglePanel function
function togglePanel(header) {
    // Toggle active class on the header
    header.classList.toggle('active');
    
    // Find the card body that follows this header
    const cardBody = header.nextElementSibling;
    
    // Toggle the show class on the card body
    if (cardBody && cardBody.classList.contains('card-body')) {
        cardBody.classList.toggle('show');
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    
    // Ha Date objektum, akkor konvertáljuk
    if (dateStr instanceof Date) {
        const year = dateStr.getFullYear();
        const month = String(dateStr.getMonth() + 1).padStart(2, '0');
        const day = String(dateStr.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    // Ha string, akkor ellenőrizzük a formátumot
    if (typeof dateStr === 'string') {
        // Ha már YYYY-MM-DD formátumú, akkor visszaadjuk
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
            return dateStr;
        }
        
        // Egyéb esetben megpróbáljuk átalakítani
        const date = new Date(dateStr);
        if (!isNaN(date.getTime())) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
    }
    
    return '';
}

function addMaintenance(id) {
    if (!id) {
        showNotification('Érvénytelen eszköz azonosító!', 'error');
        return;
    }
    
    document.getElementById('date_stuff_id').value = id;
    
    // Mai dátum beállítása alapértelmezettként
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    document.getElementById('maintenance_start_date').value = formatDate(today);
    document.getElementById('maintenance_end_date').value = formatDate(tomorrow);
    
    // Modal megjelenítése
    document.getElementById('maintenanceDateModal').style.display = 'block';
}

function closeDateModal() {
    document.getElementById('maintenanceDateModal').style.display = 'none';
    document.getElementById('maintenanceDateForm').reset();
}

// Dátum form kezelése
document.getElementById('maintenanceDateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const startDate = document.getElementById('maintenance_start_date').value;
    const endDate = document.getElementById('maintenance_end_date').value;
    const stuffId = document.getElementById('date_stuff_id').value;
    
    // Dátumok ellenőrzése
    if (new Date(startDate) >= new Date(endDate)) {
        showNotification('A befejezés dátuma nem lehet korábbi, mint a kezdés dátuma!', 'error');
        return;
    }
    
    // Adatok küldése a szervernek
    const formData = new FormData();
    formData.append('stuff_id', stuffId);
    formData.append('start_date', startDate);
    formData.append('planned_end_date', endDate);
    formData.append('maintenance_status_id', '1'); // Alapértelmezett "Várakozik" státusz
    
    fetch('add_maintenance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Karbantartás sikeresen létrehozva!', 'success');
            closeDateModal();
            
            // Az eszköz eltávolítása a hibás eszközök listájából
            removeItemFromBrokenList(stuffId);
            
            // Karbantartások lista frissítése
            refreshMaintenanceList();
            
            // Számláló frissítése
            updateCounters();
        } else {
            throw new Error(data.message || 'Hiba történt a mentés során!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(error.message || 'Hiba történt a mentés során!', 'error');
    });
});

// Eszköz eltávolítása a hibás eszközök listájából
function removeItemFromBrokenList(stuffId) {
    const row = document.querySelector(`.broken-list tr[data-stuff-id="${stuffId}"]`);
    if (row) {
        // Animáció hozzáadása az eltávolításhoz
        row.style.transition = 'all 0.3s ease';
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        
        // Kis késleltetés után eltávolítjuk a sort
        setTimeout(() => {
            row.remove();
            
            // Ha nincs több sor, megjelenítjük az üres üzenetet
            const tbody = document.querySelector('.broken-list .table tbody');
            if (tbody && (!tbody.children.length || tbody.children.length === 0)) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nincs hibás eszköz</td></tr>';
            }
        }, 300);
    }
}

// Karbantartások lista frissítése
function refreshMaintenanceList() {
    fetch('get_maintenance_data.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.querySelector('.maintenance-list .table tbody');
            if (!tbody) return;

            if (data.length > 0) {
                tbody.innerHTML = '';
                data.forEach(row => {
                    const statusClass = getStatusClass(row.maintenance_status?.toLowerCase() || '');
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-maintenance-id', row.id);
                    
                    tr.innerHTML = `
                        <td>${escapeHtml(row.brand_name || '')}</td>
                        <td>${escapeHtml(row.model_name || '')}</td>
                        <td>${escapeHtml(row.type_name || '')}</td>
                        <td>${formatDate(row.servis_startdate)}</td>
                        <td>${formatDate(row.servis_planenddate)}</td>
                        <td>${row.servis_currectenddate ? formatDate(row.servis_currectenddate) : '-'}</td>
                        <td><span class="status-badge ${statusClass}">${escapeHtml(row.maintenance_status || '')}</span></td>
                        <td>${escapeHtml(row.firstname || '')} ${escapeHtml(row.lastname || '')}</td>
                        <td class="actions">
                            <button class="btn btn-success btn-sm tooltip-btn" onclick="completeMaintenance(${row.id}, ${row.stuffs_id})" title="Befejezés">
                                <span class="icon"><i class="fas fa-check-circle"></i></span>
                                <span class="text">Befejezés</span>
                            </button>
                            <button class="btn btn-primary btn-sm tooltip-btn" onclick="editMaintenance(${row.id})" title="Szerkesztés">
                                <span class="icon"><i class="fas fa-pencil-alt"></i></span>
                                <span class="text">Szerkesztés</span>
                            </button>
                            <button class="btn btn-danger btn-sm tooltip-btn" onclick="deleteMaintenance(${row.id}, ${row.stuffs_id})" title="Törlés">
                                <span class="icon"><i class="fas fa-trash-alt"></i></span>
                                <span class="text">Törlés</span>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                // Update the counter directly here with the actual data length
                const counter = document.querySelector('.maintenance-list .header-right .counter');
                if (counter) {
                    counter.textContent = `${data.length} karbantartás`;
                }
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">Nincs aktív karbantartás</td></tr>';
                // Set counter to 0 when no maintenance items
                const counter = document.querySelector('.maintenance-list .header-right .counter');
                if (counter) {
                    counter.textContent = '0 karbantartás';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Hiba történt a karbantartási lista frissítésekor!', 'error');
        });
}

// Számlálók frissítése
function updateCounters() {
    // Hibás eszközök számláló
    const brokenCounter = document.querySelector('.broken-list .header-right .counter');
    if (brokenCounter) {
        const brokenCount = document.querySelectorAll('.broken-list .table tbody tr:not(.text-center)').length;
        brokenCounter.textContent = `${brokenCount} eszköz`;
    }
    
    // Karbantartások számláló
    const maintenanceCounter = document.querySelector('.maintenance-list .header-right .counter');
    if (maintenanceCounter) {
        // Get the maintenance table body
        const maintenanceTbody = document.querySelector('.maintenance-list .table tbody');
        
        // Check if there's a "no active maintenance" message
        const noMaintenanceRow = maintenanceTbody.querySelector('tr td[colspan="9"]');
        
        if (noMaintenanceRow) {
            // If there's a "no active maintenance" message, set count to 0
            maintenanceCounter.textContent = '0 karbantartás';
        } else {
            // Count actual maintenance rows
            const maintenanceRows = maintenanceTbody.querySelectorAll('tr');
            maintenanceCounter.textContent = `${maintenanceRows.length} karbantartás`;
            
            // Debug log
            console.log('Maintenance rows count:', maintenanceRows.length);
            console.log('Maintenance rows:', maintenanceRows);
        }
    }
}

// Modal bezárása függvény
function closeModal() {
    const modal = document.getElementById('maintenanceModal');
    modal.style.display = 'none';
    document.getElementById('maintenanceForm').reset();
    document.getElementById('maintenance_id').value = '';
}

// Modal bezárás kiegészítése
window.onclick = function(event) {
    const maintenanceModal = document.getElementById('maintenanceModal');
    const infoModal = document.getElementById('infoModal');
    const dateModal = document.getElementById('maintenanceDateModal');
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    
    if (event.target == maintenanceModal) {
        closeModal();
    }
    if (event.target == infoModal) {
        closeInfoModal();
    }
    if (event.target == dateModal) {
        closeDateModal();
    }
    if (event.target == deleteConfirmModal) {
        closeDeleteConfirmModal();
    }
}

// Értesítés megjelenítése függvény
function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    const messageElement = document.getElementById('notification-message');
    
    // Notification stílus beállítása
    notification.className = 'notification';
    notification.classList.add(type === 'success' ? 'success' : 'error');
    
    messageElement.textContent = message;
    notification.style.display = 'block';
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.5s ease-out';
        setTimeout(() => {
            notification.style.display = 'none';
            notification.style.animation = 'slideIn 0.5s ease-out';
        }, 500);
    }, 3000);
}

// Form beküldés kezelése
document.getElementById('maintenanceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const maintenanceId = document.getElementById('maintenance_id').value;
    
    fetch('edit_maintenance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Karbantartás sikeresen módosítva!', 'success');
            closeModal();
            
            // Frissítjük mindkét táblázatot
            refreshMaintenanceList();
            updateBrokenList();
            
            // Frissítjük a számlálókat
            updateCounters();
        } else {
            throw new Error(data.message || 'Hiba történt a mentés során!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(error.message || 'Hiba történt a mentés során!', 'error');
    });
});

// Hibás eszközök lista frissítése
function updateBrokenList() {
    fetch('get_broken_stuff.php')
        .then(response => response.json())
        .then(response => {
            if (response.success && Array.isArray(response.data)) {
                const tbody = document.querySelector('.broken-list .table tbody');
                if (!tbody) return;

                if (response.data.length > 0) {
                    tbody.innerHTML = '';
                    response.data.forEach(item => {
                        const row = document.createElement('tr');
                        row.setAttribute('data-stuff-id', item.id);
                        row.innerHTML = `
                            <td>${escapeHtml(item.brand_name || '')}</td>
                            <td>${escapeHtml(item.model_name || '')}</td>
                            <td>${escapeHtml(item.type_name || '')}</td>
                            <td><code class="qr-code">${escapeHtml(item.qr_code || '')}</code></td>
                            <td><span class="status-badge ${item.status_name.toLowerCase() === 'törött' ? 'status-broken' : 'status-faulty'}">${escapeHtml(item.status_name || '')}</span></td>
                            <td class="actions">
                                <button class="btn btn-maintenance btn-sm tooltip-btn" onclick="addMaintenance(${item.id})" data-tooltip="Karbantartás">
                                    <span class="icon"><i class="fas fa-tools"></i></span>
                                    <span class="text">Karbantartás</span>
                                </button>
                                <button class="btn btn-info btn-sm tooltip-btn" onclick="showInfo(${item.id})" data-tooltip="Információ">
                                    <span class="icon"><i class="fas fa-info-circle"></i></span>
                                    <span class="text">Információ</span>
                                </button>
                            </td>
                        `;
                        
                        // Animáció az új sorhoz
                        row.style.opacity = '0';
                        row.style.transform = 'translateY(20px)';
                        tbody.appendChild(row);
                        
                        setTimeout(() => {
                            row.style.transition = 'all 0.3s ease';
                            row.style.opacity = '1';
                            row.style.transform = 'translateY(0)';
                        }, 50);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nincs hibás eszköz</td></tr>';
                }
                
                // Számláló frissítése
                const counter = document.querySelector('.broken-list .header-right .counter');
                if (counter) {
                    counter.textContent = `${response.data.length} eszköz`;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Hiba történt a hibás eszközök lista frissítésekor!', 'error');
        });
}

// Módosított törlés funkció
function deleteMaintenance(maintenanceId, stuffsId) {
    // Beállítjuk a törlendő elemek azonosítóit
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    confirmDeleteBtn.onclick = function() {
        const formData = new FormData();
        formData.append('maintenance_id', maintenanceId);
        formData.append('stuffs_id', stuffsId);
        
        fetch('delete_maintenance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('<?php echo translate("Karbantartás sikeresen törölve! Az eszköz kiszelektálásra került."); ?>', 'success');
                closeDeleteConfirmModal();
                
                // Frissítjük mindkét táblázatot
                refreshMaintenanceList();
                updateBrokenList();
                
                // Frissítjük a számlálókat
                updateCounters();
            } else {
                throw new Error(data.message || '<?php echo translate("Hiba történt a törlés során!"); ?>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification(error.message || '<?php echo translate("Hiba történt a törlés során!"); ?>', 'error');
        });
    };
    
    // Megjelenítjük a modális ablakot
    document.getElementById('deleteConfirmModal').style.display = 'block';
}

function closeDeleteConfirmModal() {
    document.getElementById('deleteConfirmModal').style.display = 'none';
}

// Az oldal betöltésekor is frissítsük a táblázatokat
document.addEventListener('DOMContentLoaded', function() {
    refreshMaintenanceList();
    updateBrokenList();
    updateCounters();
});

function editMaintenance(id, startDate, plannedEndDate, delayEndDate, statusId) {
    // Modal cím módosítása
    document.getElementById('modalTitle').textContent = 'Karbantartás szerkesztése';
    
    // Form mezők feltöltése
    document.getElementById('maintenance_id').value = id;
    
    // Státusz beállítása
    const statusSelect = document.getElementById('maintenance_status');
    if (statusSelect) {
        statusSelect.value = statusId;
    }
    
    // Modal megnyitása
    document.getElementById('maintenanceModal').style.display = 'block';
}

function showInfo(stuffId) {
    fetch('../api/get_stuff_report.php?stuff_id=' + stuffId)  // Módosított útvonal
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                document.getElementById('reporter-name').textContent = data.reporter_name || 'N/A';
                document.getElementById('report-date').textContent = formatDate(data.report_date) || 'N/A';
                document.getElementById('report-description').textContent = data.description || 'Nincs leírás megadva';
                document.getElementById('infoModal').style.display = 'block';
            } else {
                showNotification(data.message || 'Nem sikerült betölteni az információkat!', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Hiba történt az adatok betöltésekor!', 'error');
        });
}

function closeInfoModal() {
    document.getElementById('infoModal').style.display = 'none';
}

function closeCompleteModal() {
    document.getElementById('completeModal').style.display = 'none';
    document.getElementById('completeForm').reset();
}

function completeMaintenance(maintenanceId, stuffsId) {
    // Debug log
    console.log('Raw values:', { maintenanceId, stuffsId });
    
    // Explicit konvertálás számokká
    maintenanceId = parseInt(maintenanceId, 10);
    stuffsId = parseInt(stuffsId, 10); // Közvetlenül használjuk a paraméterként kapott stuffsId-t
    
    // Ellenőrizzük az értékeket
    if (!maintenanceId || isNaN(maintenanceId)) {
        showNotification('Érvénytelen karbantartás azonosító!', 'error');
        return;
    }
    
    if (!stuffsId || isNaN(stuffsId)) {
        showNotification('Érvénytelen eszköz azonosító!', 'error');
        return;
    }
    
    // Az értékek beállítása a form hidden mezőiben
    document.getElementById('complete_maintenance_id').value = maintenanceId;
    document.getElementById('complete_stuff_id').value = stuffsId;
    
    // Debug log a form értékek beállítása után
    console.log('Form values set:', {
        maintenance_id: document.getElementById('complete_maintenance_id').value,
        stuff_id: document.getElementById('complete_stuff_id').value
    });
    
    // Modal megjelenítése
    document.getElementById('completeModal').style.display = 'block';
}

// Befejezés form kezelése
document.getElementById('completeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Adatok összegyűjtése
    const maintenanceId = parseInt(document.getElementById('complete_maintenance_id').value, 10);
    const stuffsId = parseInt(document.getElementById('complete_stuff_id').value, 10);
    const description = document.getElementById('complete_description').value;
    
    console.log('Form submission data:', { maintenanceId, stuffsId, description });
    
    // Validáció
    if (!maintenanceId || isNaN(maintenanceId)) {
        showNotification('Érvénytelen karbantartás azonosító!', 'error');
        return;
    }
    
    if (!stuffsId || isNaN(stuffsId)) {
        showNotification('Érvénytelen eszköz azonosító!', 'error');
        return;
    }
    
    if (!description.trim()) {
        showNotification('Kérem, írja le az elvégzett munkát!', 'error');
        return;
    }
    
    // FormData objektum létrehozása
    const formData = new FormData();
    formData.append('maintenance_id', maintenanceId);
    formData.append('stuffs_id', stuffsId);
    formData.append('description', description);
    
    // AJAX kérés küldése
    fetch('complete_maintenance.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        console.log('Complete maintenance response status:', response.status);
        console.log('Complete maintenance response headers:', Object.fromEntries(response.headers.entries()));
        
        const rawText = await response.text();
        console.log('Complete maintenance raw response:', rawText);
        
        if (!rawText || rawText.trim() === '') {
            throw new Error('Üres válasz érkezett a szervertől');
        }
        
        try {
            return JSON.parse(rawText);
        } catch (e) {
            console.error('Complete maintenance JSON parse error:', e);
            console.error('Raw text that failed to parse:', rawText);
            throw new Error('Érvénytelen válasz a szervertől: ' + e.message);
        }
    })
    .then(data => {
        console.log('Complete maintenance parsed response:', data);
        
        if (data.success) {
            showNotification('Karbantartás sikeresen befejezve!', 'success');
            closeCompleteModal();
            
            // Eltávolítjuk a befejezett karbantartást a karbantartások listájából
            const maintenanceRow = document.querySelector(`tr[data-maintenance-id="${maintenanceId}"]`);
            if (maintenanceRow) {
                maintenanceRow.remove();
                
                // Ellenőrizzük, hogy van-e még aktív karbantartás
                const maintenanceTbody = document.querySelector('.maintenance-list .table tbody');
                if (maintenanceTbody && maintenanceTbody.children.length === 0) {
                    maintenanceTbody.innerHTML = '<tr><td colspan="9" class="text-center">Nincs aktív karbantartás</td></tr>';
                }
                
                // Frissítjük a karbantartások számlálóját
                const maintenanceCounter = document.querySelector('.maintenance-list .header-right .counter');
                if (maintenanceCounter) {
                    const currentCount = parseInt(maintenanceCounter.textContent) - 1;
                    maintenanceCounter.textContent = `${currentCount} karbantartás`;
                }
            }
            
            // Eltávolítjuk az eszközt a hibás eszközök listájából
            const brokenRow = document.querySelector(`.broken-list tr[data-stuff-id="${stuffsId}"]`);
            if (brokenRow) {
                brokenRow.remove();
                
                // Ellenőrizzük, hogy van-e még hibás eszköz
                const brokenTbody = document.querySelector('.broken-list .table tbody');
                if (brokenTbody && brokenTbody.children.length === 0) {
                    brokenTbody.innerHTML = '<tr><td colspan="6" class="text-center">Nincs hibás eszköz</td></tr>';
                }
                
                // Frissítjük a hibás eszközök számlálóját
                const brokenCounter = document.querySelector('.broken-list .header-right .counter');
                if (brokenCounter) {
                    const currentCount = parseInt(brokenCounter.textContent) - 1;
                    brokenCounter.textContent = `${currentCount} eszköz`;
                }
            }
            
            // Form reset
            document.getElementById('completeForm').reset();
            
        } else {
            throw new Error(data.message || 'Hiba történt a mentés során!');
        }
    })
    .catch(error => {
        console.error('Complete maintenance error:', error);
        console.error('Error stack:', error.stack);
        showNotification(error.message || 'Hiba történt a mentés során!', 'error');
    });
});

// Profil dropdown menü kezelése
document.addEventListener('DOMContentLoaded', function() {
    // Profil dropdown menü kezelése
    const profileTrigger = document.querySelector('.profile-trigger');
    const profileDropdown = document.querySelector('.dropdown-menu');
    
    if (profileTrigger && profileDropdown) {
        profileTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        // Kattintás figyelése a dokumentumon a menü bezárásához
        document.addEventListener('click', function(e) {
            if (!profileTrigger.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    // Debug: Keressük meg az elemeket
    const profileTrigger = document.querySelector('.profile-trigger');
    const dropdownMenu = document.querySelector('.profile-dropdown .dropdown-menu');
    
    console.log('Profile Trigger element:', profileTrigger);
    console.log('Dropdown Menu element:', dropdownMenu);
    
    if (profileTrigger && dropdownMenu) {
        console.log('Both elements found, setting up click handler');
        
        profileTrigger.addEventListener('click', function(e) {
            console.log('Profile clicked');
            e.preventDefault();
            e.stopPropagation();
            console.log('Current dropdown state:', dropdownMenu.classList.contains('show'));
            dropdownMenu.classList.toggle('show');
            console.log('New dropdown state:', dropdownMenu.classList.contains('show'));
        });

        // Kattintás figyelése a dokumentumon
        document.addEventListener('click', function(e) {
            if (!profileTrigger.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        });
    } else {
        console.error('Missing elements:');
        console.error('Profile Trigger found:', !!profileTrigger);
        console.error('Dropdown Menu found:', !!dropdownMenu);
        // Debug: Keressük meg az összes lehetséges elemet
        console.log('All .dropdown-menu elements:', document.querySelectorAll('.dropdown-menu'));
        console.log('All .profile-trigger elements:', document.querySelectorAll('.profile-trigger'));
    }
});
</script>

<?php require_once '../includes/layout/footer.php'; ?> 