<?php 
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';
require_once '../includes/language_handler.php';

// A session debug és company_id ellenőrzés/beállítás
if (!isset($_SESSION)) {
    session_start();
}

// Ellenőrizzük a bejelentkezést
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Adatbázis kapcsolat létrehozása
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Kapcsolódási hiba: " . mysqli_connect_error());
}

// Ellenőrizzük és állítsuk be a company_id-t ha nincs
if (!isset($_SESSION['company_id']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $company_query = "SELECT company_id FROM user WHERE id = ?";
    $stmt = mysqli_prepare($conn, $company_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user_data = mysqli_fetch_assoc($result)) {
        $_SESSION['company_id'] = $user_data['company_id'];
    }
}

// Jogosultság ellenőrzése
checkPageAccess();

// Projekt típusok lekérdezése
$types_sql = "SELECT * FROM project_type ORDER BY name";
$types_result = mysqli_query($conn, $types_sql);

// Országok lekérdezése a has_districts információval együtt
$countries_sql = "SELECT id, name, has_districts FROM countries ORDER BY name";
$countries_result = mysqli_query($conn, $countries_sql);

// Űrlap feldolgozása előtt ellenőrizzük a session-t
if (!isset($_SESSION['company_id'])) {
    error_log('Missing company_id. Session content: ' . print_r($_SESSION, true));
    die(translate('Error: Company ID is not set! Please log in again.'));
}

// Projekt típusok
$projectTypes = [
    'Fesztivál',
    'Konferancia',
    'Rendezvény',
    'Előadás',
    'Kiállitás',
    'Jótékonysági',
    'Ünnepség',
    'Egyéb'
];

// Projekt státuszok
$projectStatuses = [
    'Tervezés alatt',
    'Folyamatban',
    'Befejezett',
    'Elhalasztva',
    'Közelgő'
];

// Űrlap feldolgozása
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug információ
    error_log('POST data: ' . print_r($_POST, true));
    error_log('FILES data: ' . print_r($_FILES, true));

    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type_id = (int)$_POST['type_id'];
    $start_date = $_POST['start_date'] . ' ' . $_POST['start_time'];
    $end_date = $_POST['end_date'] . ' ' . $_POST['end_time'];
    $country_id = (int)$_POST['country_id'];
    $county_id = (int)$_POST['county_id'];
    $city_id = (int)$_POST['city_id'];
    $district_id = isset($_POST['district_id']) ? (int)$_POST['district_id'] : null;
    $company_id = (int)$_SESSION['company_id'];

    // Kép feldolgozása
    $picture = null;
    if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['project_image']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $file_name = uniqid() . '_' . $_FILES['project_image']['name'];
            $upload_path = '../uploads/projects/';
            
            // Ellenőrizzük/létrehozzuk a mappát
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            $target_file = $upload_path . $file_name;
            
            if (move_uploaded_file($_FILES['project_image']['tmp_name'], $target_file)) {
                $picture = 'uploads/projects/' . $file_name;
            } else {
                error_log('Hiba a fájl feltöltésekor: ' . error_get_last()['message']);
            }
        } else {
            $error = "Nem megfelelő fájltípus. Csak JPG, PNG és GIF képeket fogadunk el.";
        }
    }

    if (!isset($error)) {
        $insert_sql = "INSERT INTO project (name, type_id, project_startdate, project_enddate, 
                                          country_id, county_id, city_id, district_id, company_id, picture) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $insert_sql);
        
        mysqli_stmt_bind_param($stmt, "sissiiiiss", 
            $name,           // s - string
            $type_id,        // i - integer
            $start_date,     // s - string (dátum)
            $end_date,       // s - string (dátum)
            $country_id,     // i - integer
            $county_id,      // i - integer
            $city_id,        // i - integer
            $district_id,    // i - integer
            $company_id,     // s - string
            $picture         // s - string
        );
        
        if (mysqli_stmt_execute($stmt)) {
            header("Location: projektek.php?success=1");
            exit;
        } else {
            $error = "Hiba történt a projekt létrehozása során: " . mysqli_error($conn);
            error_log("SQL Error: " . mysqli_error($conn));
        }
    }
}

require_once '../includes/layout/header.php'; 
?>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.project-form-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

.form-header {
    margin-bottom: 2rem;
    text-align: center;
}

.form-header h1 {
    color: #2c3e50;
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.form-header p {
    color: #6b7280;
    font-size: 1.1rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #4a5568;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.date-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.btn-container {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-secondary {
    background: #e2e8f0;
    color: #4a5568;
}

.btn-secondary:hover {
    background: #cbd5e0;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.alert-danger {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.image-preview {
    margin-top: 1rem;
    max-width: 300px;
    max-height: 200px;
    overflow: hidden;
    border-radius: 8px;
    display: none;
}

.image-preview img {
    width: 100%;
    height: auto;
    object-fit: cover;
}

.image-upload-container {
    position: relative;
    width: 200px;
    height: 200px;
    margin: 0 auto;
    border-radius: 10px;
    overflow: hidden;
    background: #f8fafc;
    border: 3px dashed #e2e8f0;
    cursor: pointer;
    transition: all 0.3s ease;
}

.image-upload-container:hover {
    border-color: #3498db;
    background: #f1f5f9;
}

.image-upload-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.upload-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 3rem;
    color: #94a3b8;
    z-index: 0;
}

.image-upload-text {
    position: absolute;
    width: 100%;
    text-align: center;
    bottom: 20px;
    color: #64748b;
    font-size: 0.9rem;
    z-index: 0;
}

.image-upload-container input[type="file"] {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}

#image_preview {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

#image_preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.datetime-input {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.datetime-input input[type="time"] {
    padding: 0.75rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.datetime-input input[type="time"]:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translate3d(0, -20%, 0);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

.animated {
    animation-duration: 0.3s;
    animation-fill-mode: both;
}

.fadeInDown {
    animation-name: fadeInDown;
}
</style>

<div class="project-form-container">
    <div class="form-header">
        <h1><?php echo translate('Új projekt létrehozása'); ?></h1>
        <p><?php echo translate('Töltse ki az alábbi űrlapot az új projekt létrehozásához'); ?></p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-grid">
            <div class="form-group">
                <label for="name"><?php echo translate('Projekt neve'); ?>*</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="type_id"><?php echo translate('Projekt típusa'); ?>*</label>
                <select id="type_id" name="type_id" class="form-control" required>
                    <option value=""><?php echo translate('Válasszon típust...'); ?></option>
                    <?php while ($type = mysqli_fetch_assoc($types_result)): ?>
                        <option value="<?php echo $type['id']; ?>">
                            <?php echo translate(htmlspecialchars($type['name'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="start_date"><?php echo translate('Kezdés dátuma és ideje'); ?>*</label>
                <div class="datetime-input">
                    <input type="date" 
                           id="start_date" 
                           name="start_date" 
                           class="form-control" 
                           required>
                    <input type="time" id="start_time" name="start_time" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label for="end_date"><?php echo translate('Befejezés dátuma és ideje'); ?>*</label>
                <div class="datetime-input">
                    <input type="date" id="end_date" name="end_date" class="form-control" required>
                    <input type="time" id="end_time" name="end_time" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label for="country_id"><?php echo translate('Ország'); ?>*</label>
                <select id="country_id" name="country_id" class="form-control" required>
                    <option value=""><?php echo translate('Válasszon országot...'); ?></option>
                    <?php while ($country = mysqli_fetch_assoc($countries_result)): ?>
                        <option value="<?php echo $country['id']; ?>">
                            <?php echo translate(htmlspecialchars($country['name'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="county_id"><?php echo translate('Megye'); ?>*</label>
                <select id="county_id" name="county_id" class="form-control" required disabled>
                    <option value=""><?php echo translate('Válasszon megyét...'); ?></option>
                </select>
            </div>

            <div class="form-group" id="district_group" style="display: none;">
                <label for="district_id"><?php echo translate('Kerület'); ?>*</label>
                <select id="district_id" name="district_id" class="form-control" required disabled>
                    <option value=""><?php echo translate('Válasszon kerületet...'); ?></option>
                </select>
            </div>

            <div class="form-group">
                <label for="city_id"><?php echo translate('Város'); ?>*</label>
                <select id="city_id" name="city_id" class="form-control" required disabled>
                    <option value=""><?php echo translate('Válasszon várost...'); ?></option>
                </select>
            </div>

            <div class="form-group">
                <label><?php echo translate('Projekt képe'); ?></label>
                <div class="image-upload-container">
                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                    <div class="image-upload-text"><?php echo translate('Kattintson vagy húzza ide a képet'); ?></div>
                    <div id="image_preview"></div>
                    <input type="file" 
                           id="project_image" 
                           name="project_image" 
                           accept="image/*"
                           onchange="previewImage(this);">
                </div>
            </div>

            <div class="btn-container">
                <a href="projektek.php" class="btn btn-secondary"><?php echo translate('Mégse'); ?></a>
                <button type="submit" class="btn btn-primary"><?php echo translate('Projekt létrehozása'); ?></button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const form = document.querySelector('form');

    // Aktuális dátum és idő beállítása
    const now = new Date();
    
    // Dátum formázása YYYY-MM-DD formátumra
    const currentDate = now.toISOString().split('T')[0];
    
    // Idő formázása HH:mm formátumra a kezdéshez
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const currentTime = `${hours}:${minutes}`;

    // Befejezés ideje (egy órával később)
    const endHours = String(now.getHours() + 1 > 23 ? 0 : now.getHours() + 1).padStart(2, '0');
    const endTime = `${endHours}:${minutes}`;

    // Befejezés dátuma (ha az idő átfordul másnap 0 órára, akkor a dátumot is növeljük)
    const endDate = now.getHours() + 1 > 23 ? 
        new Date(now.setDate(now.getDate() + 1)).toISOString().split('T')[0] : 
        currentDate;

    // Alapértelmezett értékek beállítása
    startDateInput.value = currentDate;
    startTimeInput.value = currentTime;
    endDateInput.value = endDate;
    endTimeInput.value = endTime;

    // Start dátum változásakor frissítsük a befejezés dátumot és időt
    startDateInput.addEventListener('input', updateEndDateTime);
    startDateInput.addEventListener('change', updateEndDateTime);
    
    // Start idő változásakor frissítsük a befejezés időt
    startTimeInput.addEventListener('input', updateEndDateTime);
    startTimeInput.addEventListener('change', updateEndDateTime);
    
    // Befejezés idő változásakor ellenőrizzük, hogy szükséges-e a dátumot módosítani
    endTimeInput.addEventListener('input', checkAndUpdateEndDate);
    endTimeInput.addEventListener('change', checkAndUpdateEndDate);

    function updateEndDateTime() {
        // Beállítjuk ugyanazt a dátumot a befejezéshez
        endDateInput.value = startDateInput.value;
        
        // Beállítjuk az időt egy órával későbbre
        const [hours, minutes] = startTimeInput.value.split(':');
        const endHours = String(parseInt(hours) + 1 > 23 ? 0 : parseInt(hours) + 1).padStart(2, '0');
        endTimeInput.value = `${endHours}:${minutes}`;
        
        // Ha éjfél után átfordul, növeljük a dátumot
        if (parseInt(hours) + 1 > 23) {
            const nextDay = new Date(startDateInput.value);
            nextDay.setDate(nextDay.getDate() + 1);
            endDateInput.value = nextDay.toISOString().split('T')[0];
        }
    }

    function checkAndUpdateEndDate() {
        const startDate = new Date(startDateInput.value + 'T' + startTimeInput.value);
        const endDate = new Date(endDateInput.value + 'T' + endTimeInput.value);
        
        // Ha a befejezés időpontja korábbi mint a kezdés időpontja ugyanazon a napon
        if (endDateInput.value === startDateInput.value && endTimeInput.value < startTimeInput.value) {
            // Következő napra állítjuk a dátumot
            const nextDay = new Date(startDate);
            nextDay.setDate(nextDay.getDate() + 1);
            endDateInput.value = nextDay.toISOString().split('T')[0];
        }
        // Ha különböző napokon vagyunk és a befejezés dátuma korábbi
        else if (endDate < startDate) {
            const nextDay = new Date(startDate);
            nextDay.setDate(nextDay.getDate() + 1);
            endDateInput.value = nextDay.toISOString().split('T')[0];
        }
    }

    // Dátum validáció függvény - CSAK amikor a felhasználó befejezte a bevitelt
    function validateDate(inputElement) {
        const value = inputElement.value;
        if (!value) return;

        const date = new Date(value);
        const year = date.getFullYear();
        const currentYear = new Date().getFullYear();

        if (year > 2099) {
            Swal.fire({
                title: 'Figyelmeztetés!',
                text: 'A megadott év nem lehet nagyobb mint 2099!',
                icon: 'warning',
                timer: 3000,
                showConfirmButton: false
            });
            inputElement.value = '2099' + value.substring(4);
        } else if (year < currentYear) {
            Swal.fire({
                title: 'Figyelmeztetés!',
                text: 'A megadott év nem lehet kisebb mint a jelenlegi év!',
                icon: 'warning',
                timer: 3000,
                showConfirmButton: false
            });
            inputElement.value = currentYear + value.substring(4);
        }
    }

    // CSAK blur eseményre (amikor a felhasználó kilép a mezőből) validálunk
    startDateInput.addEventListener('blur', function() {
        validateDate(this);
    });

    endDateInput.addEventListener('blur', function() {
        validateDate(this);
    });

    // Múltbeli dátum ellenőrzése
    async function checkPastDateTime() {
        const selectedDate = new Date(startDateInput.value + 'T' + startTimeInput.value);
        const currentDateTime = new Date();

        if (selectedDate < currentDateTime) {
            // Friss időpontok lekérése
            const now = new Date();
            const currentDate = now.toISOString().split('T')[0];
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const currentTime = `${hours}:${minutes}`;

            let timerInterval;
            try {
                await Swal.fire({
                    title: 'Figyelmeztetés!',
                    html: 'A kezdés időpontja nem lehet korábbi az aktuális időpontnál!<br><br><span id="countdown"></span>',
                    icon: 'warning',
                    timer: 5000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        timerInterval = setInterval(() => {
                            const timeLeft = Math.ceil(Swal.getTimerLeft() / 1000);
                            document.getElementById('countdown').textContent = 
                                `(${timeLeft} másodperc múlva bezárul)`;
                        }, 100);
                    },
                    willClose: () => {
                        clearInterval(timerInterval);
                    }
                });
            } finally {
                // Értékek frissítése a legfrissebb időpontra
                startDateInput.value = currentDate;
                startTimeInput.value = currentTime;
                endDateInput.value = currentDate;
                endTimeInput.value = currentTime;
            }
            
            return false;
        }
        return true;
    }

    // Start dátum és idő események módosítása aszinkronná
    startDateInput.addEventListener('blur', async function() {
        if (this.value) {
            const isValid = await checkPastDateTime();
            if (!isValid) return;
            
            if (validateDateTime(startDateInput, startTimeInput, true)) {
                endDateInput.min = this.value;
                if (endDateInput.value < this.value || !endDateInput.value) {
                    endDateInput.value = this.value;
                    endTimeInput.value = startTimeInput.value;
                }
            }
        }
    });

    startTimeInput.addEventListener('blur', async function() {
        const isValid = await checkPastDateTime();
        if (!isValid) return;
        
        if (validateDateTime(startDateInput, startTimeInput, true)) {
            if (startDateInput.value === endDateInput.value) {
                if (endTimeInput.value < this.value) {
                    endTimeInput.value = this.value;
                }
            }
        }
    });

    // Form elküldés előtti végső ellenőrzés módosítása
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const startDateTime = new Date(startDateInput.value + 'T' + startTimeInput.value);
        const endDateTime = new Date(endDateInput.value + 'T' + endTimeInput.value);
        
        if (endDateTime < startDateTime) {
            let timerInterval;
            try {
                await Swal.fire({
                    title: 'Figyelmeztetés!',
                    html: 'A befejezés időpontja nem lehet korábbi a kezdés időpontjánál!<br><br><span id="countdown"></span>',
                    icon: 'warning',
                    timer: 5000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        timerInterval = setInterval(() => {
                            const timeLeft = Math.ceil(Swal.getTimerLeft() / 1000);
                            document.getElementById('countdown').textContent = 
                                `(${timeLeft} másodperc múlva bezárul)`;
                        }, 100);
                    },
                    willClose: () => {
                        clearInterval(timerInterval);
                    }
                });
            } finally {
                clearInterval(timerInterval);
            }
            return false;
        }
        
        // Ha minden rendben, küldjük el a formot
        this.submit();
    });

    // Függő legördülő menük kezelése
    const countrySelect = document.getElementById('country_id');
    const countySelect = document.getElementById('county_id');
    const districtGroup = document.getElementById('district_group');
    const districtSelect = document.getElementById('district_id');
    const citySelect = document.getElementById('city_id');

    countrySelect.addEventListener('change', function() {
        const countryId = this.value;
        countySelect.disabled = !countryId;
        citySelect.disabled = true;
        
        // Listák törlése
        countySelect.innerHTML = '<option value="">Válasszon megyét...</option>';
        districtSelect.innerHTML = '<option value="">Válasszon kerületet...</option>';
        citySelect.innerHTML = '<option value="">Válasszon várost...</option>';

        if (countryId) {
            // Ország adatainak lekérése
            fetch(`get_country_info.php?country_id=${countryId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Ország adatok:', data); // Debug log
                    
                    if (data.error) {
                        console.error('Hiba:', data.error);
                        return;
                    }
                    
                    // Kerület megjelenítése/elrejtése az ország beállításai alapján
                    const hasDistricts = parseInt(data.has_districts) === 1;
                    console.log('Has districts:', hasDistricts); // Debug log
                    
                    if (hasDistricts) {
                        districtGroup.style.display = 'block';
                        districtSelect.required = true;
                    } else {
                        districtGroup.style.display = 'none';
                        districtSelect.required = false;
                        districtSelect.value = '';
                        districtSelect.disabled = true;
                    }
                })
                .catch(error => {
                    console.error('Hiba az ország adatok lekérésénél:', error);
                });

            // Megyék lekérése
            fetch(`get_counties.php?country_id=${countryId}`)
                .then(response => response.json())
                .then(counties => {
                    counties.forEach(county => {
                        const option = document.createElement('option');
                        option.value = county.id;
                        option.textContent = county.name;
                        countySelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Hiba:', error));
        }
    });

    countySelect.addEventListener('change', function() {
        const countyId = this.value;
        const countryId = countrySelect.value;
        
        if (!countyId) {
            districtSelect.disabled = true;
            citySelect.disabled = true;
            return;
        }

        // Ellenőrizzük, hogy az aktuális országban van-e kerület
        fetch(`get_country_info.php?country_id=${countryId}`)
            .then(response => response.json())
            .then(data => {
                console.log('Megye változáskor ország adatok:', data); // Debug log
                const hasDistricts = parseInt(data.has_districts) === 1;
                
                if (hasDistricts) {
                    // Ha van kerület, akkor először azt kell kiválasztani
                    districtSelect.disabled = false;
                    citySelect.disabled = true;
                    
                    // Kerületek lekérése
                    fetch(`get_districts.php?county_id=${countyId}`)
                        .then(response => response.json())
                        .then(districts => {
                            console.log('Kerületek:', districts); // Debug log
                            districtSelect.innerHTML = '<option value="">Válasszon kerületet...</option>';
                            districts.forEach(district => {
                                const option = document.createElement('option');
                                option.value = district.id;
                                option.textContent = district.name;
                                districtSelect.appendChild(option);
                            });
                            // Megjelenítjük a kerület csoportot
                            districtGroup.style.display = 'block';
                        })
                        .catch(error => console.error('Hiba a kerületek lekérésénél:', error));
                } else {
                    // Ha nincs kerület, közvetlenül a városokat kérjük le
                    districtGroup.style.display = 'none';
                    districtSelect.value = '';
                    districtSelect.disabled = true;
                    citySelect.disabled = false;
                    
                    fetch(`get_cities.php?county_id=${countyId}`)
                        .then(response => response.json())
                        .then(cities => {
                            citySelect.innerHTML = '<option value="">Válasszon várost...</option>';
                            cities.forEach(city => {
                                const option = document.createElement('option');
                                option.value = city.id;
                                option.textContent = city.name;
                                citySelect.appendChild(option);
                            });
                        })
                        .catch(error => console.error('Hiba a városok lekérésénél:', error));
                }
            })
            .catch(error => console.error('Hiba az ország adatok lekérésénél:', error));
    });

    districtSelect.addEventListener('change', function() {
        const districtId = this.value;
        citySelect.disabled = !districtId;
        
        if (districtId) {
            // Városok lekérése kerület alapján
            fetch(`get_cities.php?district_id=${districtId}`)
                .then(response => response.json())
                .then(cities => {
                    citySelect.innerHTML = '<option value="">Válasszon várost...</option>';
                    cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.id;
                        option.textContent = city.name;
                        citySelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Hiba a városok lekérésénél:', error));
        }
    });

    // Kép feltöltés kezelése
    const imageUploadContainer = document.querySelector('.image-upload-container');
    const fileInput = document.getElementById('project_image');
    
    // Konténer kattintás kezelése
    imageUploadContainer.addEventListener('click', function(e) {
        // Csak akkor nyissuk meg a fájl választót, ha nem magára az input elemre kattintottunk
        if (e.target !== fileInput) {
            fileInput.click();
        }
    });
    
    // Drag and drop események
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        imageUploadContainer.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        imageUploadContainer.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        imageUploadContainer.addEventListener(eventName, unhighlight, false);
    });

    function highlight(e) {
        imageUploadContainer.style.borderColor = '#3498db';
        imageUploadContainer.style.backgroundColor = '#f1f5f9';
    }

    function unhighlight(e) {
        imageUploadContainer.style.borderColor = '#e2e8f0';
        imageUploadContainer.style.backgroundColor = '#f8fafc';
    }

    imageUploadContainer.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        previewImage(fileInput);
    }
});

// A previewImage függvény módosítása
function previewImage(input) {
    const container = input.closest('.image-upload-container');
    const preview = document.getElementById('image_preview');
    const uploadIcon = container.querySelector('.upload-icon');
    const uploadText = container.querySelector('.image-upload-text');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            uploadIcon.style.opacity = '0';
            uploadText.style.opacity = '0';
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.innerHTML = '';
        uploadIcon.style.opacity = '1';
        uploadText.style.opacity = '1';
    }
}
</script>

<?php require_once '../includes/layout/footer.php'; ?> 