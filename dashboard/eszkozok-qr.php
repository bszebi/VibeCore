<?php
// The session will be started in config.php instead
require_once('../includes/config.php');

// Move the login check after config.php is included
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once('../includes/layout/header.php');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Adatbázis kapcsolódási hiba történt. Kérjük próbálja újra később.");
}

// Get company ID from session
$company_id = $_SESSION['company_id'];

try {
    // Fetch all equipment for the company with joined tables to get all necessary information
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            st.name AS type_name,
            ss.name AS secondtype_name,
            sb.name AS brand_name,
            sm.name AS model_name,
            smd.year AS manufacture_year,
            sst.name AS status_name
        FROM stuffs s
        LEFT JOIN stuff_type st ON s.type_id = st.id
        LEFT JOIN stuff_secondtype ss ON s.secondtype_id = ss.id
        LEFT JOIN stuff_brand sb ON s.brand_id = sb.id
        LEFT JOIN stuff_model sm ON s.model_id = sm.id
        LEFT JOIN stuff_manufacture_date smd ON s.manufacture_date = smd.id
        LEFT JOIN stuff_status sst ON s.stuff_status_id = sst.id
        WHERE s.company_id = ?
    ");
    $stmt->execute([$company_id]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Adatbázis hiba történt. Kérjük próbálja újra később.";
}
?>

<div class="content-wrapper">
    <h2><?php echo translate('Eszköz QR kódjai nyomtatása'); ?></h2>
    <div class="table-container">
        <table class="table" id="equipmentTable">
            <thead>
                <tr>
                    <th><?php echo translate('Sorszám'); ?></th>
                    <th><?php echo translate('Típus'); ?></th>
                    <th><?php echo translate('Altípus'); ?></th>
                    <th><?php echo translate('Márka'); ?></th>
                    <th><?php echo translate('Modell'); ?></th>
                    <th><?php echo translate('Gyártási év'); ?></th>
                    <th><?php echo translate('QR Kód'); ?></th>
                    <th class="text-end"><?php echo translate('Műveletek'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($equipment) && is_array($equipment)): ?>
                    <?php foreach ($equipment as $item): ?>
                        <tr>
                            <td></td>
                            <td><?php echo translate(htmlspecialchars($item['type_name'] ?? 'N/A')); ?></td>
                            <td><?php echo translate(htmlspecialchars($item['secondtype_name'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars($item['brand_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['model_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['manufacture_year'] ?? 'N/A'); ?></td>
                            <td><?php 
                                $qr_code = preg_replace('/[^A-Za-z0-9]/', '', $item['qr_code'] ?? '');
                                // Add QR- prefix if not present
                                if (strpos($qr_code, 'QR') !== 0) {
                                    $qr_code = 'QR' . $qr_code;
                                }
                                // Split the code into parts (after QR-)
                                $code_without_prefix = substr($qr_code, 2); // Remove QR-
                                $first_part = substr($code_without_prefix, 0, 4);
                                $second_part = substr($code_without_prefix, 4, 6);
                                $third_part = substr($code_without_prefix, 10, 4);
                                
                                // Combine parts with hyphens
                                $formatted_qr = 'QR-' . $first_part . '-' . $second_part . '-' . $third_part;
                                echo htmlspecialchars($formatted_qr); 
                            ?></td>
                            <td class="text-end">
                                <div class="custom-dropdown">
                                    <button class="btn btn-sm btn-icon dropdown-toggle" type="button" id="dropdownMenu<?php echo $item['id']; ?>" onclick="toggleDropdown(this)">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="custom-dropdown-menu" id="dropdownContent<?php echo $item['id']; ?>">
                                        <a class="dropdown-item" href="#" onclick="viewQRCode('<?php echo htmlspecialchars($item['qr_code']); ?>', '<?php echo translate(htmlspecialchars($item['type_name'] ?? 'N/A')); ?>', '<?php echo htmlspecialchars($item['model_name']); ?>', '<?php echo translate(htmlspecialchars($item['secondtype_name'] ?? 'N/A')); ?>', '<?php echo htmlspecialchars($item['brand_name']); ?>', '<?php 
                                            $qr_code = preg_replace('/[^A-Za-z0-9]/', '', $item['qr_code'] ?? '');
                                            if (strpos($qr_code, 'QR') !== 0) {
                                                $qr_code = 'QR' . $qr_code;
                                            }
                                            $code_without_prefix = substr($qr_code, 2);
                                            $first_part = substr($code_without_prefix, 0, 4);
                                            $second_part = substr($code_without_prefix, 4, 6);
                                            $third_part = substr($code_without_prefix, 10, 4);
                                            echo 'QR-' . $first_part . '-' . $second_part . '-' . $third_part;
                                        ?>', '<?php echo htmlspecialchars($item['manufacture_year'] ?? 'N/A'); ?>')">
                                            <i class="fas fa-qrcode me-2"></i>
                                            QR kód
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- QR Code Modal -->
    <div class="modal" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel"><?php echo translate('QR Kód Részletei'); ?></h5>
                </div>
                <div class="modal-body">
                    <div class="qr-info-container">
                        <div id="qrCodeDisplay"></div>
                        <div id="equipmentInfo" class="ms-3"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()"><?php echo translate('Bezárás'); ?></button>
                    <button type="button" class="btn btn-primary" onclick="printQRCode()"><?php echo translate('Nyomtatás'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.content-wrapper {
    padding: 20px;
    background-color: #f8f9fa;
    min-height: calc(100vh - 60px);
}

/* QR Kód Modális Ablak Stílusok */
#qrCodeModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1050;
    overflow: auto;
}

#qrCodeModal.show {
    display: block;
}

#qrCodeModal .modal-dialog {
    margin: 1.75rem auto;
    position: relative;
    width: auto;
    pointer-events: all;
    max-width: 700px;
}

#qrCodeModal .modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    background: linear-gradient(to bottom right, #ffffff, #f8f9fa);
    overflow: hidden;
}

#qrCodeModal .modal-header {
    border-bottom: none;
    padding: 1.5rem 1.5rem 0.5rem;
    background: transparent;
}

#qrCodeModal .modal-title {
    font-weight: 600;
    color: #2c3e50;
    font-size: 1.25rem;
}

#qrCodeModal .modal-body {
    padding: 1.5rem;
}

#qrCodeModal .qr-info-container {
    display: flex;
    align-items: flex-start;
    gap: 20px;
}

#qrCodeModal #qrCodeDisplay {
    flex: 0 0 300px;
    background: white;
    padding: 0;
    border-radius: 12px;
    margin: 0;
    line-height: 0;
}

#qrCodeModal #qrCodeDisplay img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    display: block;
    margin: 0;
    line-height: 0;
}

#qrCodeModal #equipmentInfo {
    flex: 1;
    margin: 0;
}

/* Eszköz Információk Stílusok */
#qrCodeModal .equipment-details {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

#qrCodeModal .equipment-details p {
    margin-bottom: 0.8rem;
    color: #2c3e50;
    font-size: 1.1rem;
}

#qrCodeModal .equipment-details strong {
    color: #495057;
    min-width: 80px;
    display: inline-block;
}

#qrCodeModal .modal-footer {
    border-top: none;
    padding: 1.5rem;
    justify-content: center;
    gap: 1rem;
    margin-top: 0;
}

#qrCodeModal .btn {
    padding: 0.6rem 1.5rem;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.2s ease;
}

#qrCodeModal .btn-secondary {
    background-color: #f8f9fa;
    border: none;
    color: #6c757d;
}

#qrCodeModal .btn-secondary:hover {
    background-color: #e9ecef;
    color: #2c3e50;
}

#qrCodeModal .btn-primary {
    background: linear-gradient(45deg, #0088ee, #0099ff);
    border: none;
    box-shadow: 0 4px 15px rgba(0, 153, 255, 0.2);
}

#qrCodeModal .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0, 153, 255, 0.25);
}

.table-container {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.table {
    margin-bottom: 0;
}

.table thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    padding: 15px;
    border-bottom: 2px solid #dee2e6;
}

.table tbody td {
    padding: 15px;
    vertical-align: middle;
}

.btn-icon {
    background: transparent;
    border: none;
    color: #6c757d;
    padding: 6px 8px;
    border-radius: 50%;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-icon:hover {
    background-color: rgba(108, 117, 125, 0.1);
    color: #333;
}

.btn-icon:focus {
    outline: none;
    box-shadow: none;
}

/* Custom Dropdown Styles */
.custom-dropdown {
    position: relative;
    display: inline-block;
}

.custom-dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    min-width: 180px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.15);
    border: none;
    padding: 8px 0;
    border-radius: 6px;
    margin-top: 5px;
    background-color: white;
    z-index: 1000;
    transform: translateX(20px);
    opacity: 0;
    transition: transform 0.3s ease, opacity 0.3s ease;
    transform-origin: top right;
}

.custom-dropdown-menu.show {
    display: block;
    transform: translateX(0);
    opacity: 1;
}

.dropdown-item {
    padding: 8px 16px;
    color: #333;
    font-size: 14px;
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: background-color 0.2s;
}

.dropdown-item:hover {
    background-color: #f0f7ff;
    text-decoration: none;
}

.dropdown-item i {
    width: 20px;
    margin-right: 8px;
}

.dropdown-item.text-danger {
    color: #dc3545 !important;
}

.dropdown-item.text-danger:hover {
    background-color: #fff5f5;
}

/* Táblázat Testreszabás */
.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 6px 12px;
}

.dataTables_wrapper .dataTables_length {
    margin-bottom: 15px;
}

.dataTables_wrapper .dataTables_filter {
    margin-bottom: 15px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 5px 10px;
    margin: 0 2px;
    border-radius: 4px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #0099ff;
    border-color: #0099ff;
    color: white !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #0088ee;
    border-color: #0088ee;
    color: white !important;
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>

<script>
// Global variable
let currentItemId = null;
let currentModal = null;

// Custom dropdown function
function toggleDropdown(button) {
    // Close all other dropdowns first
    const allDropdowns = document.querySelectorAll('.custom-dropdown-menu');
    allDropdowns.forEach(dropdown => {
        if (dropdown.id !== button.nextElementSibling.id) {
            dropdown.classList.remove('show');
        }
    });
    
    // Toggle the clicked dropdown
    const dropdownContent = button.nextElementSibling;
    
    // If the dropdown is already showing, just hide it
    if (dropdownContent.classList.contains('show')) {
        dropdownContent.classList.remove('show');
        return;
    }
    
    // Otherwise, show it with animation
    dropdownContent.classList.add('show');
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function closeDropdown(e) {
        if (!button.contains(e.target) && !dropdownContent.contains(e.target)) {
            dropdownContent.classList.remove('show');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

// QR Code functions
function viewQRCode(qrCode, typeName, modelName, secondTypeName, brandName, formattedQrCode, manufactureYear) {
    if (!qrCode || qrCode === 'N/A') {
        alert('<?php echo translate('Nincs elérhető QR kód ehhez az eszközhöz.'); ?>');
        return;
    }
    
    const qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(qrCode);
    $('#qrCodeDisplay').html('<img src="' + qrImageUrl + '" alt="QR Kód" class="img-fluid">');
    $('#equipmentInfo').html(`
        <div class="equipment-details">
            <p class="mb-1"><strong><?php echo translate('QR kód'); ?>:</strong> ${formattedQrCode}</p>
            <p class="mb-1"><strong><?php echo translate('Típus'); ?>:</strong> ${typeName}</p>
            <p class="mb-1"><strong><?php echo translate('Altípus'); ?>:</strong> ${secondTypeName}</p>
            <p class="mb-1"><strong><?php echo translate('Márka'); ?>:</strong> ${brandName}</p>
            <p class="mb-1"><strong><?php echo translate('Modell'); ?>:</strong> ${modelName}</p>
            <p class="mb-1"><strong><?php echo translate('Gyártási év'); ?>:</strong> ${manufactureYear}</p>
        </div>
    `);
    
    const qrModal = document.getElementById('qrCodeModal');
    qrModal.classList.add('show');
    currentModal = qrModal;
}

function closeModal() {
    if (currentModal) {
        currentModal.classList.remove('show');
    }
}

function printQRCode() {
    const qrImage = $('#qrCodeDisplay img').attr('src');
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>QR Kód Nyomtatása</title>
            <style>
                body { 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    height: 100vh; 
                    margin: 0; 
                }
                .print-container { 
                    text-align: center; 
                    padding: 20px; 
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 20px;
                }
                .qr-image { 
                    max-width: 300px; 
                    margin-bottom: 20px;
                }
                .logo {
                    max-width: 200px;
                    height: auto;
                }
                @media print { 
                    @page { margin: 0; } 
                    body { margin: 1.6cm; } 
                }
            </style>
        </head>
        <body>
            <div class="print-container">
                <img src="${qrImage}" alt="QR Kód" class="qr-image">
                <img src="../assets/img/VIBECORE.png" alt="Logo" class="logo">
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    window.onafterprint = function() { window.close(); };
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Initialize dropdowns
$(document).ready(function() {
    $('#equipmentTable').DataTable({
        "language": {
            "processing": "<?php echo translate('Feldolgozás...'); ?>",
            "search": "<?php echo translate('Keresés'); ?>:",
            "lengthMenu": "<?php echo translate('Megjelenítés _MENU_ elem oldalanként'); ?>",
            "info": "<?php echo translate('Megjelenítve: _START_ - _END_ / _TOTAL_ elem'); ?>",
            "infoEmpty": "<?php echo translate('Nincs megjeleníthető adat'); ?>",
            "infoFiltered": "<?php echo translate('(Szűrve _MAX_ elemből)'); ?>",
            "loadingRecords": "<?php echo translate('Betöltés...'); ?>",
            "zeroRecords": "<?php echo translate('Nincs találat'); ?>",
            "emptyTable": "<?php echo translate('Nincs megjeleníthető adat'); ?>",
            "paginate": {
                "first": "<?php echo translate('Első'); ?>",
                "previous": "<?php echo translate('Előző'); ?>",
                "next": "<?php echo translate('Következő'); ?>",
                "last": "<?php echo translate('Utolsó'); ?>"
            },
            "aria": {
                "sortAscending": ": aktiválja a növekvő rendezéshez",
                "sortDescending": ": aktiválja a csökkenő rendezéshez"
            },
            "searchBuilder": {
                "conditions": {
                    "string": {
                        "contains": "Tartalmazza",
                        "empty": "Üres",
                        "endsWith": "Végződik",
                        "equals": "Egyenlő",
                        "startsWith": "Kezdődik",
                        "not": "Nem"
                    }
                }
            }
        },
        "pageLength": 10,
        "order": [[1, "asc"]],
        "responsive": true,
        "columnDefs": [{
            "targets": 0,
            "searchable": false,
            "orderable": false
        }],
        "rowCallback": function(row, data, index) {
            let info = this.api().page.info();
            let rowNum = info.start + index + 1;
            $('td:eq(0)', row).html(rowNum);
        }
    });
});
</script>

<?php require_once('../includes/layout/footer.php'); ?>