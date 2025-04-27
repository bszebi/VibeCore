<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/translation.php';

// Hibakezelés bekapcsolása
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Adatbázis kapcsolat létrehozása
try {
    $db = DatabaseConnection::getInstance()->getConnection();
} catch (PDOException $e) {
    die("Adatbázis kapcsolódási hiba: " . $e->getMessage());
}

// Session ellenőrzése
if (!isset($_SESSION['company_id'])) {
    die("Nincs beállítva company_id a session-ben!");
}

require_once '../includes/layout/header.php';
?>

<!-- Add SweetAlert2 CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.main-container {
    padding: 2rem;
    background-color: #f8f9fa;
    min-height: calc(100vh - 100px);
}

.card {
    border: none;
    border-radius: 15px;
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
}

.card-header {
    background: white;
    border-bottom: 1px solid #eee;
    padding: 1.5rem 2rem;
    border-radius: 15px 15px 0 0;
}

.card-body {
    padding: 2rem;
}

.section-header {
    display: flex;
    align-items: center;
    margin-bottom: 2rem;
    padding: 1rem;
    background: rgba(13, 110, 253, 0.05);
    border-radius: 10px;
}

.section-icon {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 10px;
    margin-right: 1rem;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.form-select {
    padding: 0.75rem 1rem;
    border-radius: 10px;
    border: 2px solid #eee;
    font-size: 1rem;
    margin-top: 0.5rem;
    transition: all 0.3s ease;
}

.form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    margin-right: 1rem;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.stat-label {
    color: #6c757d;
    font-size: 0.875rem;
}

.table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.table {
    margin-bottom: 0;
}

.table thead th {
    background: #f8f9fa;
    padding: 1rem 1.5rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.875rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #eee;
}

.table tbody td {
    padding: 1.2rem 1.5rem;
    vertical-align: middle;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
}

.badge {
    padding: 0.5rem 1rem;
    font-weight: 500;
    border-radius: 8px;
}

.badge i {
    margin-right: 0.5rem;
}

.empty-state {
    padding: 3rem;
    text-align: center;
}

.empty-state-icon {
    font-size: 2.5rem;
    color: #6c757d;
    margin-bottom: 1rem;
}

.spinner-container {
    padding: 3rem;
    text-align: center;
}

.spinner-border {
    width: 3rem;
    height: 3rem;
}
</style>

<div class="main-container">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-12 col-xxl-10">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="section-header mb-0">
                                <div class="section-icon">
                                    <i class="fas fa-cogs text-primary"></i>
                                </div>
                                <h4 class="mb-0"><?php echo translate('Eszközök beállítása'); ?></h4>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="section-icon">
                                    <i class="fas fa-calendar text-secondary"></i>
                                </div>
                                <span class="ms-2"><?php echo date('Y.m.d.'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6 col-lg-4">
                                <label for="workSelect" class="form-label">
                                    <div class="d-flex align-items-center">
                                        <div class="section-icon">
                                            <i class="fas fa-briefcase text-primary"></i>
                                        </div>
                                        <span class="fw-bold"><?php echo translate('Válassz munkát'); ?></span>
                                    </div>
                                </label>
                                <select class="form-select" id="workSelect">
                                    <option value=""><?php echo translate('Válassz munkát...'); ?></option>
                                    <?php
                                    $sql = "SELECT w.id, 
                                           COALESCE(p.name, 'Projekt nélküli munka') as project_name,
                                           w.work_start_date,
                                           p.project_startdate
                                           FROM work w 
                                           LEFT JOIN project p ON w.project_id = p.id 
                                           WHERE w.company_id = ? 
                                           AND w.work_start_date <= NOW() 
                                           AND (p.project_startdate IS NULL OR p.project_startdate > NOW())
                                           ORDER BY w.work_start_date DESC";
                                    
                                    $stmt = $db->prepare($sql);
                                    $stmt->execute([$_SESSION['company_id']]);
                                    
                                    while ($row = $stmt->fetch()) {
                                        $date = new DateTime($row['work_start_date']);
                                        echo '<option value="' . $row['id'] . '">' . 
                                             htmlspecialchars($row['project_name'] == 'Projekt nélküli munka' ? translate('Projekt nélküli munka') : $row['project_name']) . ' - ' . 
                                             $date->format('Y-m-d H:i') . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="stats-container">
                            <div class="stat-card">
                                <div class="stat-icon bg-success bg-opacity-10">
                                    <i class="fas fa-check-circle text-success"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value" id="packedCount">0</div>
                                    <div class="stat-label"><?php echo translate('Bepakolva'); ?></div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon bg-warning bg-opacity-10">
                                    <i class="fas fa-clock text-warning"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value" id="unpackedCount">0</div>
                                    <div class="stat-label"><?php echo translate('Bepakolásra vár'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="table-container">
                            <table class="table" id="toolsTable">
                                <thead>
                                    <tr>
                                        <th><?php echo translate('Név'); ?></th>
                                        <th><?php echo translate('QR Kód'); ?></th>
                                        <th><?php echo translate('Státusz'); ?></th>
                                        <th><?php echo translate('Bepakolás ideje'); ?></th>
                                        <th><?php echo translate('Bepakolta'); ?></th>
                                        <th><?php echo translate('Művelet'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <div class="empty-state-icon">
                                                    <i class="fas fa-info-circle"></i>
                                                </div>
                                                <p class="text-muted"><?php echo translate('Válassz munkát a eszközök megtekintéséhez'); ?></p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const workSelect = document.getElementById('workSelect');
    const toolsTable = document.getElementById('toolsTable').getElementsByTagName('tbody')[0];
    const packedCount = document.getElementById('packedCount');
    const unpackedCount = document.getElementById('unpackedCount');

    function updateCounts(workId) {
        fetch(`get_work_stuff_counts.php?work_id=${workId}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.message);
                packedCount.textContent = data.packed_count;
                unpackedCount.textContent = data.unpacked_count;
            })
            .catch(error => {
                console.error('Error:', error);
                packedCount.textContent = '0';
                unpackedCount.textContent = '0';
            });
    }

    function loadTools(workId) {
        if (!workId) {
            toolsTable.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <p class="text-muted"><?php echo translate('Válassz munkát a eszközök megtekintéséhez'); ?></p>
                        </div>
                    </td>
                </tr>`;
            return;
        }

        toolsTable.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="spinner-container">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden"><?php echo translate('Betöltés...'); ?></span>
                        </div>
                        <p class="text-muted mt-3"><?php echo translate('Eszközök betöltése...'); ?></p>
                    </div>
                </td>
            </tr>`;

        fetch(`get_work_stuffs.php?work_id=${workId}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.message);
                
                // Check if the response indicates a date validation error
                if (!data.success && data.hasOwnProperty('is_date_valid') && !data.is_date_valid) {
                    toolsTable.innerHTML = `
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-state-icon text-warning">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <p class="text-warning">${data.message}</p>
                                </div>
                            </td>
                        </tr>`;
                    // Reset the counters
                    packedCount.textContent = '0';
                    unpackedCount.textContent = '0';
                    return;
                }
                
                if (data.success && data.data) {
                    toolsTable.innerHTML = '';
                    data.data.forEach(tool => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="section-icon me-2">
                                        <i class="fas fa-tools text-secondary"></i>
                                    </div>
                                    <span>${tool.name}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-qrcode"></i>
                                    ${tool.qr_code}
                                </span>
                            </td>
                            <td>
                                <span class="badge ${tool.is_packed ? 'bg-success' : 'bg-warning'}">
                                    <i class="fas ${tool.is_packed ? 'fa-check-circle' : 'fa-clock'}"></i>
                                    ${tool.is_packed ? '<?php echo translate('Bepakolva'); ?>' : '<?php echo translate('Bepakolásra vár'); ?>'}
                                </span>
                            </td>
                            <td>
                                ${tool.packed_date ? 
                                    `<div class="d-flex align-items-center">
                                        <i class="fas fa-calendar-alt text-secondary me-2"></i>
                                        ${new Date(tool.packed_date).toLocaleString()}
                                    </div>` : '-'}
                            </td>
                            <td>
                                ${tool.packed_by_name ? 
                                    `<div class="d-flex align-items-center">
                                        <div class="section-icon me-2">
                                            <i class="fas fa-user text-primary"></i>
                                        </div>
                                        <span>${tool.packed_by_name}</span>
                                    </div>` : '-'}
                            </td>
                            <td>
                                ${!tool.is_packed ? 
                                    `<button class="btn btn-success btn-sm pack-tool" data-tool-id="${tool.id}" data-work-id="${workId}">
                                        <i class="fas fa-check"></i>
                                    </button>` : ''}
                            </td>
                        `;
                        toolsTable.appendChild(row);
                    });
                    updateCounts(workId);
                } else {
                    toolsTable.innerHTML = `
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-box-open"></i>
                                    </div>
                                    <p class="text-muted"><?php echo translate('Nem található eszköz ehhez a munkához'); ?></p>
                                </div>
                            </td>
                        </tr>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toolsTable.innerHTML = `
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-state-icon text-danger">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <p class="text-danger"><?php echo translate('Hiba történt az eszközök betöltése közben'); ?></p>
                            </div>
                        </td>
                    </tr>`;
            });
    }

    workSelect.addEventListener('change', function() {
        loadTools(this.value);
    });

    if (workSelect.value) {
        loadTools(workSelect.value);
    }

    // Bepakolás funkció hozzáadása
    function packTool(toolId, workId) {
        fetch('pack_tool.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                tool_id: toolId,
                work_id: workId
            })
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Frissítjük a táblázatot
                loadTools(workId);
            } else {
                throw new Error(data.message || 'Hiba történt a bepakolás során');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Hiba történt a bepakolás során: ' + error.message);
        });
    }

    // Bepakolás gomb eseménykezelő
    document.addEventListener('click', function(e) {
        if (e.target.closest('.pack-tool')) {
            const btn = e.target.closest('.pack-tool');
            const toolId = btn.dataset.toolId;
            const workId = btn.dataset.workId;
            
            Swal.fire({
                title: '<?php echo translate('Bepakolás megerősítése'); ?>',
                text: '<?php echo translate('Biztosan bepakolta az eszközt?'); ?>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<?php echo translate('Igen, bepakoltam'); ?>',
                cancelButtonText: '<?php echo translate('Még nem'); ?>',
                backdrop: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    packTool(toolId, workId);
                }
            });
        }
    });
});
</script>

<?php require_once '../includes/layout/footer.php'; ?>