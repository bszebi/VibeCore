// Téma inicializálása
document.addEventListener('DOMContentLoaded', function() {
    // Téma beállítása a localStorage alapján
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);

    // Header elemek inicializálása
    const headerElements = document.querySelectorAll('.header-item');
    headerElements.forEach(element => {
        element.addEventListener('click', () => {
            const theme = element.getAttribute('data-theme');
            if (theme) {
                createPopup(theme);
            }
        });
    });

    // Dock ikonok inicializálása
    document.querySelectorAll('.dock-icon').forEach(icon => {
        icon.addEventListener('click', function() {
            const theme = this.dataset.theme;
            let popup;

            switch (theme) {
                case 'companies':
                    popup = createCompaniesPopup();
                    break;
                case 'members':
                    popup = createMembersPopup();
                    break;
                case 'profile':
                    popup = createProfilePopup();
                    break;
                case 'settings':
                    popup = createSettingsPopup();
                    break;
                case 'logout':
                    showConfirmationDialog(
                        'Kijelentkezés',
                        'Biztosan ki szeretne jelentkezni?',
                        () => {
                            window.location.href = '/Vizsga_oldal/home.php';
                        }
                    );
                    return;
            }

            if (popup) {
                document.querySelector('.popups-container').appendChild(popup);
                makePopupDraggable(popup);
                addTaskbarItem(popup);
            }
        });
    });

    // Dátum és idő inicializálása
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Session kezelés
    checkSession();

    // Session ellenőrzése másodpercenként
    setInterval(checkSession, 1000);

    // Árva taskbar elemek eltávolítása
    cleanupOrphanedTaskbarItems();
});

let activePopups = [];
let minimizedPopups = [];
let nextZIndex = 100;

// Dátum és idő frissítése
function updateDateTime() {
    const now = new Date();
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric', 
        weekday: 'long',
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit'
    };
    const dateTimeString = now.toLocaleDateString('hu-HU', options);
    const dateTimeElement = document.getElementById('datetime');
    if (dateTimeElement) {
        dateTimeElement.textContent = dateTimeString;
    }
}

function createPopup(options, addTaskbar = true) {
    const popupsContainer = document.querySelector('.popups-container');
    let theme, title, content, width, height;

    // Ellenőrizzük, hogy string vagy objektum paramétert kaptunk-e
    if (typeof options === 'string') {
        theme = options;
        title = theme === 'companies' ? 'Cégek' : theme.charAt(0).toUpperCase() + theme.slice(1);
        content = '';
        width = theme === 'companies' ? 800 : 600;
        height = theme === 'companies' ? 600 : 400;
    } else {
        theme = options.theme;
        title = options.title || (theme === 'companies' ? 'Cégek' : theme.charAt(0).toUpperCase() + theme.slice(1));
        content = options.content || '';
        width = options.width || (theme === 'companies' ? 800 : 600);
        height = options.height || (theme === 'companies' ? 600 : 400);
    }

    const popupId = `popup-${Date.now()}-${theme}`;
    
    // Check if there's a minimized popup with the same theme
    const existingPopup = activePopups.find(p => 
        p.getAttribute('data-theme') === theme && 
        minimizedPopups.includes(p.getAttribute('data-id'))
    );

    if (existingPopup) {
        restorePopup(existingPopup.getAttribute('data-id'));
        return existingPopup;
    }

    // Check if there's already an open popup with the same theme
    const existingOpenPopup = activePopups.find(p => 
        p.getAttribute('data-theme') === theme && 
        !minimizedPopups.includes(p.getAttribute('data-id'))
    );

    if (existingOpenPopup) {
        existingOpenPopup.style.zIndex = ++nextZIndex;
        return existingOpenPopup;
    }

    const popup = document.createElement('div');
    popup.classList.add('popup');
    popup.setAttribute('data-id', popupId);
    popup.setAttribute('data-theme', theme);
    popup.style.zIndex = ++nextZIndex;

    // Set initial window size and position
    const windowWidth = window.innerWidth;
    const windowHeight = window.innerHeight;
    
    // Save original dimensions as data attributes
    popup.setAttribute('data-original-width', width + 'px');
    popup.setAttribute('data-original-height', height + 'px');
    
    // Calculate center position
    const left = (windowWidth - width) / 2;
    const top = (windowHeight - height) / 2;

    if (!content) {
        switch (theme) {
            case 'companies':
                content = `
                    <div class="search-container">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="company-search" placeholder="Keresés cégek között...">
                            <i class="fas fa-times clear-search" style="display: none;"></i>
                        </div>
                    </div>
                    <div class="companies-grid" id="companies-grid"></div>`;
                break;
            case 'settings':
                content = `<div class="settings-content"></div>`;
                break;
            default:
                content = `<h2>${title}</h2>`;
        }
    }

    // Create the header with or without edit button based on theme
    const headerContent = `
        <div class="popup-header">
            ${theme === 'company-details' ? '<button class="edit-company-btn"><i class="fas fa-pencil-alt"></i></button>' : ''}
            <span>${title}</span>
            <div class="traffic-lights">
                <button class="close-btn" data-symbol="×"></button>
                <button class="minimize-btn" data-symbol="−"></button>
                <button class="maximize-btn" data-symbol="□"></button>
            </div>
        </div>
        <div class="popup-content">${content}</div>
    `;

    popup.innerHTML = headerContent;

    // Apply initial position and size
    Object.assign(popup.style, {
        width: width + 'px',
        height: height + 'px',
        left: left + 'px',
        top: top + 'px'
    });

    popupsContainer.appendChild(popup);
    activePopups.push(popup);

    // Add scroll check function
    const checkScroll = () => {
        const popupContent = popup.querySelector('.popup-content');
        if (popupContent) {
            if (popupContent.scrollHeight > popupContent.clientHeight) {
                popupContent.classList.remove('no-scroll');
            } else {
                popupContent.classList.add('no-scroll');
            }
        }
    };

    // Initial scroll check
    checkScroll();

    // Add resize observer to check scroll when content changes
    const resizeObserver = new ResizeObserver(checkScroll);
    resizeObserver.observe(popup.querySelector('.popup-content'));

    const closeBtn = popup.querySelector('.close-btn');
    const minimizeBtn = popup.querySelector('.minimize-btn');
    const maximizeBtn = popup.querySelector('.maximize-btn');

    closeBtn.addEventListener('click', () => closePopup(popupId));
    minimizeBtn.addEventListener('click', () => minimizePopup(popupId));
    maximizeBtn.addEventListener('click', () => {
        maximizePopup(popupId);
        checkScroll(); // Check scroll after maximizing
    });

    makeDraggable(popup);
    makeResizable(popup);
    if (addTaskbar) {
        addTaskbarItem(popup);
    }

    // If it's the companies window, load the companies
    if (theme === 'companies') {
        loadCompanies();
    }
    // If it's the settings window, initialize settings content
    else if (theme === 'settings') {
        const settingsContent = createSettingsContent();
        popup.querySelector('.settings-content').appendChild(settingsContent);
        popup.style.width = '400px';
        popup.style.height = '300px';
    }

    popup.addEventListener('mousedown', () => {
        if (!popup.classList.contains('maximized')) {
            popup.style.zIndex = ++nextZIndex;
        }
    });

    return popup;
}

function makeDraggable(element) {
    const header = element.querySelector('.popup-header');
    let isDragging = false;
    let currentX;
    let currentY;
    let initialX;
    let initialY;

    header.addEventListener('mousedown', startDragging);

    function startDragging(e) {
        if (e.target.tagName === 'BUTTON') return;

        const rect = element.getBoundingClientRect();
        
        // Ha van transform, először konvertáljuk normál pozícióvá
        if (element.style.transform.includes('translate')) {
            element.style.transform = 'none';
            element.style.left = rect.left + 'px';
            element.style.top = rect.top + 'px';
        }

        // Frissített pozíció a transform eltávolítása után
        const updatedRect = element.getBoundingClientRect();
        initialX = e.clientX - updatedRect.left;
        initialY = e.clientY - updatedRect.top;

        if (e.target === header || header.contains(e.target)) {
            isDragging = true;
            element.style.zIndex = ++nextZIndex;
        }

        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', stopDragging);
        e.preventDefault();
    }

    function drag(e) {
        if (!isDragging) return;

        e.preventDefault();

        currentX = e.clientX - initialX;
        currentY = e.clientY - initialY;

        // Ha az ablak maximalizálva volt, visszaállítjuk normál méretre
        if (element.classList.contains('maximized')) {
            element.classList.remove('maximized');
            
            // Állítsuk vissza az eredeti méretet
            const width = element.getAttribute('data-original-width') || '500px';
            const height = element.getAttribute('data-original-height') || '600px';
            element.style.width = width;
            element.style.height = height;

            // Számítsuk ki az új pozíciót az egér alatt
            initialX = element.offsetWidth / 2;
            initialY = 20;
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
        }

        // Képernyő határok ellenőrzése
        const rect = element.getBoundingClientRect();
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;

        // Bal oldali határ
        if (currentX < 0) currentX = 0;
        // Jobb oldali határ
        if (currentX + rect.width > windowWidth) {
            currentX = windowWidth - rect.width;
        }
        // Felső határ (status bar miatt 25px)
        if (currentY < 25) currentY = 25;
        // Alsó határ (taskbar miatt -30px)
        if (currentY + rect.height > windowHeight - 30) {
            currentY = windowHeight - rect.height - 30;
        }

        // Az ablak mozgatása
        element.style.left = currentX + 'px';
        element.style.top = currentY + 'px';
    }

    function stopDragging() {
        isDragging = false;
        document.removeEventListener('mousemove', drag);
        document.removeEventListener('mouseup', stopDragging);
    }
}

function makeResizable(element) {
    const minWidth = 200;
    const minHeight = 100;

    element.style.position = 'absolute';
    element.style.resize = 'both';
    element.style.overflow = 'auto';
    element.style.minWidth = minWidth + 'px';
    element.style.minHeight = minHeight + 'px';
}

function addTaskbarItem(popup) {
    const taskbar = document.querySelector('.taskbar');
    if (!taskbar) return;

    // Ellenőrizzük, hogy létezik-e már taskbar item ehhez a popuphoz
    const existingTaskItem = document.querySelector(`.task-item[data-popup-id="${popup.getAttribute('data-id')}"]`);
    if (existingTaskItem) {
        // Ha már létezik, akkor ne hozzunk létre újat
        return;
    }

    const taskItem = document.createElement('div');
    taskItem.classList.add('task-item');
    
    // Módosítjuk a megjelenített szöveget a popup típusa alapján
    const popupTheme = popup.dataset.theme;
    let taskText = '';
    
    if (popupTheme === 'company-details') {
        const companyName = popup.querySelector('.company-name-display').textContent;
        taskText = `Cég részletek - ${companyName}`;
    } else if (popupTheme === 'company-members') {
        const companyName = popup.querySelector('.popup-header span').textContent.replace(' résztvevők', '');
        taskText = `${companyName} résztvevők`;
    } else if (popupTheme === 'member-details') {
        // A member-details esetében csak egyszer jelenítjük meg a címet
        taskText = popup.querySelector('.popup-header span').textContent;
    } else {
        taskText = popup.querySelector('.popup-header span').textContent;
    }

    taskItem.textContent = taskText;
    taskItem.dataset.popupId = popup.getAttribute('data-id');
    
    taskItem.addEventListener('click', () => {
        const targetPopup = document.querySelector(`[data-id="${popup.getAttribute('data-id')}"]`);
        if (targetPopup) {
            if (minimizedPopups.includes(targetPopup.getAttribute('data-id'))) {
                restorePopup(targetPopup.getAttribute('data-id'));
            }
            bringToFront(targetPopup);
        } else {
            // Ha nem találjuk a popupot, akkor ez egy árva taskbar item, töröljük
            console.log('Removing orphaned taskbar item:', taskItem.textContent);
            taskItem.remove();
            
            // Ellenőrizzük és töröljük az összes árva taskbar itemet
            cleanupOrphanedTaskbarItems();
        }
    });

    taskbar.appendChild(taskItem);
}

// Új függvény az árva taskbar elemek eltávolítására
function cleanupOrphanedTaskbarItems() {
    console.log('Cleaning up orphaned taskbar items...');
    const taskItems = document.querySelectorAll('.task-item');
    taskItems.forEach(item => {
        const popupId = item.getAttribute('data-popup-id');
        const popup = document.querySelector(`[data-id="${popupId}"]`);
        if (!popup) {
            console.log('Found orphaned taskbar item:', item.textContent);
            item.remove();
        }
    });
}

// Módosítsuk a bringToFront függvényt is
function bringToFront(popup) {
    if (popup && document.body.contains(popup)) {
        popup.style.zIndex = ++nextZIndex;
        const taskItem = document.querySelector(`.task-item[data-popup-id="${popup.getAttribute('data-id')}"]`);
        if (taskItem) {
            taskItem.classList.add('active');
        }
    }
}

function minimizePopup(popupId) {
    const popup = document.querySelector(`[data-id="${popupId}"]`);
    if (popup) {
        popup.style.display = 'none';
        minimizedPopups.push(popupId);
        const taskItem = document.querySelector(`.task-item[data-popup-id="${popupId}"]`);
        if (taskItem) {
            taskItem.classList.add('minimized');
        }
    }
}

function restorePopup(popupId) {
    const popup = document.querySelector(`[data-id="${popupId}"]`);
    if (popup) {
        popup.style.display = 'flex';
        popup.style.zIndex = ++nextZIndex;
        minimizedPopups = minimizedPopups.filter(id => id !== popupId);
        const taskItem = document.querySelector(`.task-item[data-popup-id="${popupId}"]`);
        if (taskItem) {
            taskItem.classList.remove('minimized');
        }
    }
}

function closePopup(popupId) {
    const popup = document.querySelector(`[data-id="${popupId}"]`);
    if (popup) {
        console.log('Closing popup with ID:', popupId);
        console.log('Popup theme:', popup.getAttribute('data-theme'));
        
        // Remove from activePopups array
        activePopups = activePopups.filter(p => p.getAttribute('data-id') !== popupId);
        console.log('Removed from activePopups');
        
        // Remove from minimizedPopups array
        minimizedPopups = minimizedPopups.filter(id => id !== popupId);
        console.log('Removed from minimizedPopups');
        
        // Ha ez egy tag ablaka, akkor töröljük az összes kapcsolódó taskbar elemet
        if (popup.getAttribute('data-theme') === 'member-details') {
            console.log('This is a member details window');
            const memberId = popup.getAttribute('data-member-id');
            console.log('Member ID:', memberId);
            
            if (memberId) {
                // Töröljük az összes olyan taskbar elemet, ami ehhez a taghoz tartozik
                const memberTaskItems = document.querySelectorAll(`.task-item[data-popup-id*="member-details-${memberId}"]`);
                console.log('Found member taskbar items:', memberTaskItems.length);
                memberTaskItems.forEach(item => {
                    console.log('Removing taskbar item:', item.textContent);
                    item.remove();
                });
            }
        } else {
            // Ha nem tag ablaka, akkor csak a saját taskbar elemét töröljük
            const taskItem = document.querySelector(`.task-item[data-popup-id="${popupId}"]`);
            if (taskItem) {
                console.log('Removing regular taskbar item:', taskItem.textContent);
                taskItem.remove();
            }
        }
        
        // Remove the popup itself
        popup.remove();
        console.log('Popup removed from DOM');
    } else {
        console.log('No popup found with ID:', popupId);
        // Ha nem találtuk meg a popupot, de van taskbar item, azt is töröljük
        const taskItem = document.querySelector(`.task-item[data-popup-id="${popupId}"]`);
        if (taskItem) {
            console.log('Removing orphaned taskbar item:', taskItem.textContent);
            taskItem.remove();
        }
        
        // Ellenőrizzük, hogy van-e olyan taskbar item, amihez már nincs popup
        const allTaskItems = document.querySelectorAll('.task-item');
        allTaskItems.forEach(item => {
            const relatedPopupId = item.getAttribute('data-popup-id');
            const relatedPopup = document.querySelector(`[data-id="${relatedPopupId}"]`);
            if (!relatedPopup) {
                console.log('Removing taskbar item without popup:', item.textContent);
                item.remove();
            }
        });
    }
}

function maximizePopup(popupId) {
    const popup = document.querySelector(`[data-id="${popupId}"]`);
    if (popup) {
        popup.classList.toggle('maximized');
        if (popup.classList.contains('maximized')) {
            popup.style.transform = 'none';
            popup.style.top = '0'; // Start from the very top of the screen
            popup.style.left = '0';
            popup.style.width = '100%';
            popup.style.height = 'calc(100vh - 30px)'; // Only subtract taskbar height (30px)
        } else {
            // Restore to original size and position
            popup.style.width = '600px';
            popup.style.height = '400px';
            popup.style.top = '50%';
            popup.style.left = '50%';
            popup.style.transform = 'translate(-50%, -50%)';
        }
    }
}

// Módosított hibakezelés az első helyen
async function loadCompanies() {
    try {
        const response = await fetch('companies.php');
        if (!response.ok) throw new Error('Network response was not ok');
        const companies = await response.json();
        displayCompanies(companies);
    } catch (error) {
        console.error('Error loading companies:', error);
        showError('Hiba', 'Nem sikerült betölteni a cégeket.');
    }
}

function displayCompanies(companies) {
    const grid = document.getElementById('companies-grid');
    if (!grid) return;

    // Mentsük el a cégek listáját globálisan
    window.allCompanies = companies;

    // Keresőmező eseménykezelő beállítása
    const searchInput = document.getElementById('company-search');
    const clearSearch = document.querySelector('.clear-search');
    
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            filterCompanies(searchTerm);
            
            // Törlés ikon megjelenítése/elrejtése
            clearSearch.style.display = searchTerm ? 'block' : 'none';
        });
    }

    if (clearSearch) {
        clearSearch.addEventListener('click', () => {
            searchInput.value = '';
            filterCompanies('');
            clearSearch.style.display = 'none';
        });
    }

    // Kezdeti megjelenítés
    filterCompanies('');
}

function filterCompanies(searchTerm) {
    const grid = document.getElementById('companies-grid');
    if (!grid || !window.allCompanies) return;

    grid.innerHTML = '';
    
    const filteredCompanies = window.allCompanies.filter(company => 
        company.company_name.toLowerCase().includes(searchTerm)
    );

    if (filteredCompanies.length === 0) {
        grid.innerHTML = `
            <div class="no-results">
                <i class="fas fa-search"></i>
                <p>Nincs találat a keresésre</p>
            </div>`;
        return;
    }

    filteredCompanies.forEach(company => {
        const card = createCompanyCard(company);
        grid.appendChild(card);
    });
}

function createCompanyCard(company) {
    const card = document.createElement('div');
    card.classList.add('company-card');
    
    const logo = document.createElement('img');
    // Check if the profile_picture is already a full path
    if (company.profile_picture.startsWith('../')) {
        logo.src = company.profile_picture;
    } else {
        logo.src = `../uploads/company_logos/${company.profile_picture}`;
    }
    logo.onerror = () => logo.src = '../admin/VIBECORE.png';
    logo.classList.add('company-logo');
    logo.alt = company.company_name;
    
    const name = document.createElement('div');
    name.classList.add('company-name');
    name.textContent = company.company_name;
    
    card.appendChild(logo);
    card.appendChild(name);
    
    card.addEventListener('click', () => showCompanyDetails(company));
    
    return card;
}

function showCompanyDetails(company) {
    const popup = createPopup({
        theme: 'company-details',
        title: `Cég részletek - ${company.company_name}`,
        width: 450,
        height: 700,
        content: `
            <div class="company-details">
                <div class="company-image" data-editing="false">
                    <img src="${company.profile_picture.startsWith('../') ? company.profile_picture : `../uploads/company_logos/${company.profile_picture}`}" 
                         onerror="this.src='../admin/VIBECORE.png'" 
                         alt="${company.company_name}" 
                         class="company-details-logo">
                </div>
                <h2 class="company-name-display editable" data-field="company_name">${company.company_name}</h2>
                
                <div class="company-details-info">
                    <strong>Tulajdonos(ok):</strong>
                    <p>${company.owners || 'Nincs megadva'}</p>

                    <strong>Cím:</strong>
                    <p class="editable" data-field="company_address">${company.company_address || 'Nincs megadva'}</p>
                    
                    <strong>Email:</strong>
                    <p class="editable" data-field="company_email">${company.company_email || 'Nincs megadva'}</p>
                    
                    <strong>Telefon:</strong>
                    <p class="editable" data-field="company_telephone">${company.company_telephone || 'Nincs megadva'}</p>
                    
                    <strong>Létrehozva:</strong>
                    <p>${new Date(company.created_date).toLocaleDateString('hu-HU')}</p>
                </div>

                <button class="company-details-button subscription-button" style="background-color: #0056b3;">Csomag</button>
                <button class="company-details-button" style="background-color: #0056b3;">Cég résztvevők</button>
            </div>
        `
    });

    // Add event listeners
    popup.querySelector('.close-btn').addEventListener('click', () => closePopup(popup.getAttribute('data-id')));
    popup.querySelector('.minimize-btn').addEventListener('click', () => minimizePopup(popup.getAttribute('data-id')));
    popup.querySelector('.maximize-btn').addEventListener('click', () => maximizePopup(popup.getAttribute('data-id')));
    popup.querySelector('.subscription-button').addEventListener('click', () => showSubscriptionDetails(company.id, company.company_name));
    popup.querySelector('.company-details-button:not(.subscription-button)').addEventListener('click', () => showCompanyMembers(company.id));
    
    // Add edit functionality
    const editBtn = popup.querySelector('.edit-company-btn');
    const editableFields = popup.querySelectorAll('.editable');
    let isEditing = false;

    // Add click event listener for company logo
    const companyImage = popup.querySelector('.company-image');
    let isUploading = false; // Flag to prevent multiple uploads

    companyImage.addEventListener('click', () => {
        if (isEditing && !isUploading) {  // Only allow editing when in edit mode and not currently uploading
            isUploading = true; // Set the flag
            const field = companyImage;
            const currentValue = company.profile_picture || '';
            
            field.innerHTML = `
                <div class="image-upload-container">
                    <div class="image-preview" style="margin-bottom: 10px;">
                        <img src="${currentValue.startsWith('../') ? currentValue : `../uploads/company_logos/${currentValue}`}" 
                             onerror="this.src='../admin/VIBECORE.png'" 
                             alt="Preview"
                             style="max-width: 200px; max-height: 200px; object-fit: contain;">
                    </div>
                    <input type="file" class="edit-input" accept="image/*" data-original="${currentValue}" style="display: none;">
                </div>
            `;
            
            const fileInput = field.querySelector('.edit-input');
            const preview = field.querySelector('.image-preview img');
            
            const handleFileChange = async (e) => {
                const file = e.target.files[0];
                if (file) {
                    // Először mutassuk az előnézetet
                    const reader = new FileReader();
                    reader.onload = async (e) => {
                        preview.src = e.target.result;
                        
                        // Azonnal kezdjük meg a feltöltést
                        const formData = new FormData();
                        formData.append('logo', file);
                        formData.append('company_id', company.id);

                        try {
                            const response = await fetch('upload_logo.php', {
                                method: 'POST',
                                body: formData
                            });
                            
                            if (response.status === 401) {
                                window.location.href = 'login.php';
                                return;
                            }
                            
                            const result = await response.json();
                            
                            if (result.success) {
                                // Update the company object with the new image
                                company.profile_picture = result.filename;
                                
                                // Update all instances of the company logo in the UI
                                // 1. Update in the company card list
                                const companyCards = document.querySelectorAll('.company-card');
                                companyCards.forEach(card => {
                                    const cardImage = card.querySelector('.company-logo');
                                    if (cardImage && card.querySelector('.company-name').textContent === company.company_name) {
                                        cardImage.src = `${result.filename.startsWith('../') ? result.filename : `../uploads/company_logos/${result.filename}`}?t=${Date.now()}`;
                                    }
                                });

                                // 2. Update in the company details popup
                                const companyDetailsPopups = document.querySelectorAll('.popup[data-theme="company-details"]');
                                companyDetailsPopups.forEach(popup => {
                                    if (popup.querySelector('.company-name-display').textContent === company.company_name) {
                                        const detailsImage = popup.querySelector('.company-details-logo');
                                        if (detailsImage) {
                                            detailsImage.src = `${result.filename.startsWith('../') ? result.filename : `../uploads/company_logos/${result.filename}`}?t=${Date.now()}`;
                                        }
                                    }
                                });

                                // 3. Update in the current field
                                field.innerHTML = `
                                    <img src="${result.filename.startsWith('../') ? result.filename : `../uploads/company_logos/${result.filename}`}?t=${Date.now()}" 
                                         onerror="this.src='../admin/VIBECORE.png'" 
                                         alt="${company.company_name}" 
                                         class="company-details-logo">
                                `;
                                
                                showSuccess('Siker', 'A vállalati logó sikeresen frissítve!');
                            } else {
                                showError('Hiba', result.error || 'Nem sikerült frissíteni a vállalati logót.');
                                // Hiba esetén állítsuk vissza az eredeti képet
                                field.innerHTML = `
                                    <img src="${currentValue.startsWith('../') ? currentValue : `../uploads/company_logos/${currentValue}`}" 
                                         onerror="this.src='../admin/VIBECORE.png'" 
                                         alt="${company.company_name}" 
                                         class="company-details-logo">
                                `;
                            }
                        } catch (error) {
                            console.error('Error updating company:', error);
                            showError('Hiba', 'Nem sikerült frissíteni a vállalati logót. Kérjük, próbálja újra!');
                            // Hiba esetén állítsuk vissza az eredeti képet
                            field.innerHTML = `
                                <img src="${currentValue.startsWith('../') ? currentValue : `../uploads/company_logos/${currentValue}`}" 
                                     onerror="this.src='../admin/VIBECORE.png'" 
                                     alt="${company.company_name}" 
                                     class="company-details-logo">
                            `;
                        } finally {
                            isUploading = false; // Reset the flag when upload is complete
                            // Remove the event listener
                            fileInput.removeEventListener('change', handleFileChange);
                        }
                    };
                    reader.readAsDataURL(file);
                }
            };
            
            // Add the event listener
            fileInput.addEventListener('change', handleFileChange);
            
            // Automatically open file picker
            fileInput.click();
        }
    });

    editBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        isEditing = !isEditing;
        editBtn.classList.toggle('active');
        
        // Update cursor style and data-editing attribute based on edit mode
        companyImage.style.cursor = isEditing ? 'pointer' : 'default';
        companyImage.setAttribute('data-editing', isEditing);
        
        if (isEditing) {
            // Calculate new size for edit mode
            const editModeContent = popup.querySelector('.popup-content');
            const editWidth = Math.min(editModeContent.scrollWidth + 60, window.innerWidth * 0.9);
            const editHeight = Math.min(editModeContent.scrollHeight + 60, window.innerHeight * 0.9);
            
            requestAnimationFrame(() => {
                const rect = popup.getBoundingClientRect();
                const leftPos = rect.left;
                const topPos = rect.top;
                
                popup.style.width = `${editWidth}px`;
                popup.style.height = `${editHeight}px`;
                popup.style.left = `${leftPos}px`;
                popup.style.top = `${topPos}px`;
                popup.style.transform = 'none';
            });
        } else {
            requestAnimationFrame(() => {
                const rect = popup.getBoundingClientRect();
                const leftPos = rect.left;
                const topPos = rect.top;
                
                popup.style.width = popup.getAttribute('data-original-width');
                popup.style.height = popup.getAttribute('data-original-height');
                popup.style.left = `${leftPos}px`;
                popup.style.top = `${topPos}px`;
                popup.style.transform = 'none';
            });
        }
        
        editableFields.forEach(field => {
            if (isEditing) {
                const currentValue = field.textContent.trim();
                if (field.dataset.field === 'profile_picture') {
                    field.innerHTML = `
                        <div class="image-upload-container">
                            <div class="image-preview" style="margin-bottom: 10px;">
                                <img src="${currentValue.startsWith('../') ? currentValue : `../uploads/company_logos/${currentValue}`}" 
                                     onerror="this.src='../admin/VIBECORE.png'" 
                                     alt="Preview"
                                     style="max-width: 200px; max-height: 200px; object-fit: contain;">
                            </div>
                            <input type="file" class="edit-input" accept="image/*" data-original="${currentValue}" style="display: none;">
                        </div>
                    `;
                    
                    // Add preview functionality
                    const fileInput = field.querySelector('.edit-input');
                    const preview = field.querySelector('.image-preview img');
                    
                    fileInput.addEventListener('change', async (e) => {
                        const file = e.target.files[0];
                        if (file) {
                            // Először mutassuk az előnézetet
                            const reader = new FileReader();
                            reader.onload = async (e) => {
                                preview.src = e.target.result;
                                
                                // Azonnal kezdjük meg a feltöltést
                                const formData = new FormData();
                                formData.append('logo', file);
                                formData.append('company_id', company.id);

                                try {
                                    const response = await fetch('upload_logo.php', {
                                        method: 'POST',
                                        body: formData
                                    });
                                    
                                    if (response.status === 401) {
                                        window.location.href = 'login.php';
                                        return;
                                    }
                                    
                                    const result = await response.json();
                                    
                                    if (result.success) {
                                        // Update the company object with the new image
                                        company.profile_picture = result.filename;
                                        
                                        // Update all instances of the company logo in the UI
                                        // 1. Update in the company card list
                                        const companyCards = document.querySelectorAll('.company-card');
                                        companyCards.forEach(card => {
                                            const cardImage = card.querySelector('.company-logo');
                                            if (cardImage && card.querySelector('.company-name').textContent === company.company_name) {
                                                cardImage.src = `${result.filename.startsWith('../') ? result.filename : `../uploads/company_logos/${result.filename}`}?t=${Date.now()}`;
                                            }
                                        });

                                        // 2. Update in the company details popup
                                        const companyDetailsPopups = document.querySelectorAll('.popup[data-theme="company-details"]');
                                        companyDetailsPopups.forEach(popup => {
                                            if (popup.querySelector('.company-name-display').textContent === company.company_name) {
                                                const detailsImage = popup.querySelector('.company-details-logo');
                                                if (detailsImage) {
                                                    detailsImage.src = `${result.filename.startsWith('../') ? result.filename : `../uploads/company_logos/${result.filename}`}?t=${Date.now()}`;
                                                }
                                            }
                                        });

                                        // 3. Update in the current field
                                        field.innerHTML = `
                                            <img src="${result.filename.startsWith('../') ? result.filename : `../uploads/company_logos/${result.filename}`}?t=${Date.now()}" 
                                                 onerror="this.src='../admin/VIBECORE.png'" 
                                                 alt="${company.company_name}" 
                                                 class="company-details-logo">
                                        `;
                                        
                                        showSuccess('Siker', 'A vállalati logó sikeresen frissítve!');
                                    } else {
                                        showError('Hiba', result.error || 'Nem sikerült frissíteni a vállalati logót.');
                                        // Hiba esetén állítsuk vissza az eredeti képet
                                        field.innerHTML = `
                                            <img src="${currentValue.startsWith('../') ? currentValue : `../uploads/company_logos/${currentValue}`}" 
                                                 onerror="this.src='../admin/VIBECORE.png'" 
                                                 alt="${company.company_name}" 
                                                 class="company-details-logo">
                                        `;
                                    }
                                } catch (error) {
                                    console.error('Error updating company:', error);
                                    showError('Hiba', 'Nem sikerült frissíteni a vállalati logót. Kérjük, próbálja újra!');
                                    // Hiba esetén állítsuk vissza az eredeti képet
                                    field.innerHTML = `
                                        <img src="${currentValue.startsWith('../') ? currentValue : `../uploads/company_logos/${currentValue}`}" 
                                             onerror="this.src='../admin/VIBECORE.png'" 
                                             alt="${company.company_name}" 
                                             class="company-details-logo">
                                    `;
                                } finally {
                                    isUploading = false; // Reset the flag when upload is complete
                                    // Remove the event listener
                                    fileInput.removeEventListener('change', handleFileChange);
                                }
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                } else {
                    field.innerHTML = `
                        <input type="text" class="edit-input" value="${currentValue}" data-original="${currentValue}">
                        <div class="edit-buttons">
                            <button class="save-edit" title="Mentés">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="cancel-edit" title="Mégse">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                }
                
                const saveBtn = field.querySelector('.save-edit');
                const cancelBtn = field.querySelector('.cancel-edit');
                const input = field.querySelector('.edit-input');
                
                saveBtn.addEventListener('click', async () => {
                    const newValue = input.value.trim();
                    
                    if (!newValue) {
                        showNotification('error', 'Hiba', 'A mező nem lehet üres!');
                        return;
                    }
                    
                    // Megjelenítünk egy töltő ikont
                    field.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mentés...';
                    console.log('Mentés indítása:', field.dataset.field, newValue);

                    try {
                        // Meghívjuk a frissítő függvényt
                        const result = await updateCompanyField(company.id, field.dataset.field, newValue);
                        
                        if (!result) {
                            throw new Error('Nem érkezett válasz a szervertől');
                        }
                        
                        console.log('Frissítési eredmény:', result);
                        
                        // Feldolgozzuk a választ
                        if (result.success) {
                            // Frissítjük a nézetet az új értékkel
                            field.textContent = newValue;
                            
                            // Frissítjük a lokális objektumot
                            if (field.dataset.field === 'company_name') {
                                company.company_name = newValue;
                                
                                // Frissítjük a fejlécet is
                                const headerSpan = popup.querySelector('.popup-header span');
                                if (headerSpan) {
                                    headerSpan.textContent = `Cég részletek - ${newValue}`;
                                }
                                
                                // Frissítünk minden más helyet is, ahol a név megjelenik
                                document.querySelectorAll(`.company-card[data-id="${company.id}"] .company-name`).forEach(el => {
                                    el.textContent = newValue;
                                });
                            } else {
                                company[field.dataset.field] = newValue;
                            }
                            
                            // Sikeres üzenet
                            showNotification('success', 'Sikeres módosítás', result.message || 'A cég adatai sikeresen frissítve.');
                            
                            // Debug infó
                            console.log('Frissítve a cég mező:', field.dataset.field, 'érték:', newValue);
                            if (result.affected_rows !== undefined) {
                                console.log('Érintett sorok száma:', result.affected_rows);
                            }
                        } else {
                            // Hiba történt, visszaállítjuk az eredeti értéket
                            console.error('Szerveroldali hiba:', result.error);
                            field.textContent = currentValue;
                            showNotification('error', 'Hiba', result.error || 'Hiba történt a mentés során.');
                        }
                    } catch (error) {
                        // Kivétel esetén visszaállítjuk az eredeti értéket
                        console.error('Kivétel a cég frissítése során:', error);
                        field.textContent = currentValue;
                        showNotification('error', 'Hiba', 'Nem sikerült frissíteni a cég adatait: ' + error.message);
                    } finally {
                        // Mindenképpen kilépünk a szerkesztő módból
                        isEditing = false;
                        editBtn.classList.remove('active');
                        
                        // Recalculate size after saving
                        requestAnimationFrame(() => {
                            const content = popup.querySelector('.popup-content');
                            const width = Math.min(content.scrollWidth + 40, window.innerWidth * 0.9);
                            const height = Math.min(content.scrollHeight + 40, window.innerHeight * 0.9);
                            
                            const rect = popup.getBoundingClientRect();
                            popup.style.width = `${width}px`;
                            popup.style.height = `${height}px`;
                            popup.style.left = `${rect.left}px`;
                            popup.style.top = `${rect.top}px`;
                            popup.style.transform = 'none';
                            
                            popup.setAttribute('data-original-width', `${width}px`);
                            popup.setAttribute('data-original-height', `${height}px`);
                        });
                    }
                });
                
                cancelBtn.addEventListener('click', () => {
                    field.textContent = currentValue;
                    isEditing = false;
                    editBtn.classList.remove('active');
                    
                    requestAnimationFrame(() => {
                        const rect = popup.getBoundingClientRect();
                        popup.style.width = popup.getAttribute('data-original-width');
                        popup.style.height = popup.getAttribute('data-original-height');
                        popup.style.left = `${rect.left}px`;
                        popup.style.top = `${rect.top}px`;
                        popup.style.transform = 'none';
                    });
                });
            } else {
                const input = field.querySelector('.edit-input');
                if (input) {
                    field.textContent = input.value;
                }
            }
        });
    });

    makeDraggable(popup);
    makeResizable(popup);
    if (addTaskbar) {
        addTaskbarItem(popup);
    }
}

async function showCompanyMembers(companyId) {
    try {
        const response = await fetch(`company_members.php?company_id=${companyId}`);
        
        if (!response.ok) {
            throw new Error(`Hálózati hiba: ${response.status} ${response.statusText}`);
        }
        
        let members = [];
        try {
            members = await response.json();
        } catch (jsonError) {
            console.error('JSON parse error:', jsonError);
            throw new Error('A szervertől érkező válasz nem érvényes JSON formátumú');
        }
        
        // Find the company name from the active popups
        const companyPopup = activePopups.find(popup => 
            popup.getAttribute('data-theme') === 'company-details'
        );
        const companyName = companyPopup ? 
            companyPopup.querySelector('h2.company-name-display').textContent.trim() : 
            'Cég';

        const popupId = `popup-${Date.now()}-company-members`;
        const popup = document.createElement('div');
        popup.classList.add('popup');
        popup.setAttribute('data-id', popupId);
        popup.setAttribute('data-theme', 'company-members');
        popup.style.zIndex = ++nextZIndex;

        popup.innerHTML = `
            <div class="popup-header">
                <span>${companyName} résztvevők</span>
                <div class="traffic-lights">
                    <button class="close-btn" data-symbol="×"></button>
                    <button class="minimize-btn" data-symbol="−"></button>
                    <button class="maximize-btn" data-symbol="□"></button>
                </div>
            </div>
            <div class="popup-content">
                <div class="search-container">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="member-search" placeholder="Keresés résztvevők között...">
                        <i class="fas fa-times clear-search" style="display: none;"></i>
                    </div>
                </div>
                <div class="members-grid"></div>
            </div>
        `;

        // Set position and size
        Object.assign(popup.style, {
            width: '800px',
            height: '600px',
            left: '50%',
            top: '50%',
            transform: 'translate(-50%, -50%)'
        });

        document.querySelector('.popups-container').appendChild(popup);
        activePopups.push(popup);

        // Add event listeners
        popup.querySelector('.close-btn').addEventListener('click', () => closePopup(popupId));
        popup.querySelector('.minimize-btn').addEventListener('click', () => minimizePopup(popupId));
        popup.querySelector('.maximize-btn').addEventListener('click', () => maximizePopup(popupId));

        // Add search functionality
        const searchInput = popup.querySelector('#member-search');
        const clearSearch = popup.querySelector('.clear-search');
        
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                filterMembers(members, searchTerm, popup.querySelector('.members-grid'));
                clearSearch.style.display = searchTerm ? 'block' : 'none';
            });
        }

        if (clearSearch) {
            clearSearch.addEventListener('click', () => {
                searchInput.value = '';
                filterMembers(members, '', popup.querySelector('.members-grid'));
                clearSearch.style.display = 'none';
            });
        }

        makeDraggable(popup);
        makeResizable(popup);
        addTaskbarItem(popup);
        
        // Initial display of all members
        filterMembers(members, '', popup.querySelector('.members-grid'));
        
    } catch (error) {
        console.error('Error loading company members:', error);
        showNotification('error', 'Hiba', 'Nem sikerült betölteni a cég tagjait: ' + error.message);
    }
}

function filterMembers(members, searchTerm, container) {
    if (!members || !container) return;

    container.innerHTML = '';
    
    const filteredMembers = members.filter(member => {
        const fullName = `${member.lastname} ${member.firstname}`.toLowerCase();
        const role = (member.role_names || '').toLowerCase();
        return fullName.includes(searchTerm) || role.includes(searchTerm);
    });

    if (filteredMembers.length === 0) {
        container.innerHTML = `
            <div class="no-results">
                <i class="fas fa-search"></i>
                <p>Nincs találat a keresésre</p>
            </div>`;
        return;
    }

    filteredMembers.forEach(member => {
        const card = createMemberCard(member);
        container.appendChild(card);
    });
}

function createMemberCard(member) {
    const card = document.createElement('div');
    card.classList.add('member-card');
    
    const profilePicContainer = document.createElement('div');
    profilePicContainer.classList.add('member-profile-pic-container');
    
    const profilePic = document.createElement('img');
    profilePic.src = member.profile_picture === 'user.png' 
        ? '../assets/img/user.png' 
        : `../uploads/profiles/${member.profile_picture}`;
    profilePic.onerror = () => profilePic.src = '../assets/img/user.png';
    profilePic.classList.add('member-profile-pic');
    profilePic.alt = `${member.lastname} ${member.firstname}`;
    
    const overlay = document.createElement('div');
    overlay.classList.add('member-profile-pic-overlay');
    overlay.innerHTML = `
        <i class="fas fa-camera"></i>
        <span>Profilkép módosítása</span>
    `;
    
    profilePicContainer.appendChild(profilePic);
    profilePicContainer.appendChild(overlay);
    
    const info = document.createElement('div');
    info.classList.add('member-info');
    
    const name = document.createElement('h3');
    name.classList.add('member-name');
    name.textContent = `${member.lastname} ${member.firstname}`;
    
    const role = document.createElement('p');
    role.classList.add('member-role');
    role.textContent = member.role_names || 'Nincs szerepkör';
    
    info.appendChild(name);
    info.appendChild(role);
    
    card.appendChild(profilePicContainer);
    card.appendChild(info);
    
    // Add click event to show member details
    card.addEventListener('click', () => {
        const companyName = document.querySelector('[data-theme="company-members"] .popup-header span').textContent.split(' résztvevők')[0];
        showMemberDetails(member, companyName);
    });
    
    return card;
}

function showMemberDetails(member, companyName) {
    const popupId = `member-details-${member.id}-${Date.now()}`;
    
    // Először ellenőrizzük és bezárjuk az összes nyitott tag ablakot
    const openMemberWindows = document.querySelectorAll('[data-theme="member-details"]');
    openMemberWindows.forEach(window => {
        // Remove the window
        const windowId = window.getAttribute('data-id');
        
        // Remove from arrays
        activePopups = activePopups.filter(p => p !== window);
        minimizedPopups = minimizedPopups.filter(id => id !== windowId);
        
        // Remove taskbar item
        const taskItem = document.querySelector(`.task-item[data-popup-id="${windowId}"]`);
        if (taskItem) {
            taskItem.remove();
        }
        
        // Remove the window
        window.remove();
    });
    
    // Most hozzuk létre az új ablakot
    const popup = createPopup({
        theme: 'member-details',
        title: `${member.lastname} ${member.firstname} - ${companyName}`,
        width: 600,
        height: 580,
        content: `
            <div class="member-details" data-member-id="${member.id}">
                <div class="member-image-container">
                    <img src="${member.profile_picture === 'user.png' ? '../assets/img/user.png' : `../uploads/profiles/${member.profile_picture}`}" 
                         onerror="this.src='../assets/img/user.png'" 
                         alt="${member.lastname} ${member.firstname}" 
                         class="member-details-pic">
                    <div class="profile-pic-overlay">
                        <i class="fas fa-camera"></i>
                        <span>Profilkép módosítása</span>
                    </div>
                </div>
                
                <div class="member-details-info">
                    <strong>Vezetéknév:</strong>
                    <p class="editable member-lastname" data-field="lastname">${member.lastname}</p>
                    
                    <strong>Keresztnév:</strong>
                    <p class="editable member-firstname" data-field="firstname">${member.firstname}</p>
                    
                    <strong>Email:</strong>
                    <p class="editable member-email" data-field="email">${member.email || 'Nincs megadva'}</p>
                    
                    <strong>Telefon:</strong>
                    <p class="editable member-telephone" data-field="telephone">${member.telephone || 'Nincs megadva'}</p>
                    
                    <strong>Szerepkör:</strong>
                    <p class="role-display">Betöltés...</p>
                    
                    <strong>Csatlakozás dátuma:</strong>
                    <p>${member.connect_date ? new Date(member.connect_date).toLocaleDateString('hu-HU') : 'Nincs megadva'}</p>
                </div>
            </div>
        `
    }, false);

    // Beállítjuk az azonosítókat
    popup.setAttribute('data-id', popupId);
    popup.setAttribute('data-member-id', member.id);
    popup.setAttribute('data-theme', 'member-details');

    // Pozicionáljuk az ablakot
    const offset = 30;
    popup.style.left = `${50 + offset}px`;
    popup.style.top = `${50 + offset}px`;
    popup.style.transform = 'none';

    // Hozzáadjuk a szerkesztés gombot a headerhez, hasonlóan a cég szerkesztéshez
    // Most bal oldalra tesszük a gombot
    const header = popup.querySelector('.popup-header');
    const title = header.querySelector('.popup-title');
    const editBtn = document.createElement('button');
    editBtn.className = 'edit-member-btn';
    editBtn.innerHTML = '<i class="fas fa-pencil-alt"></i>';
    
    // Inline stílusok helyett csak a CSS osztályokat használjuk
    header.insertBefore(editBtn, title);

    // Hozzáadjuk az ablakot a DOM-hoz
    document.querySelector('.popups-container').appendChild(popup);
    activePopups.push(popup);

    // Eseménykezelők hozzáadása
    const closeBtn = popup.querySelector('.close-btn');
    const minimizeBtn = popup.querySelector('.minimize-btn');
    const maximizeBtn = popup.querySelector('.maximize-btn');

    closeBtn.addEventListener('click', () => {
        // Remove from arrays
        activePopups = activePopups.filter(p => p !== popup);
        minimizedPopups = minimizedPopups.filter(id => id !== popupId);
        
        // Remove taskbar item
        const taskItem = document.querySelector(`.task-item[data-popup-id="${popupId}"]`);
        if (taskItem) {
            taskItem.remove();
        }
        
        // Remove the window
        popup.remove();
    });

    minimizeBtn.addEventListener('click', () => minimizePopup(popupId));
    maximizeBtn.addEventListener('click', () => maximizePopup(popupId));

    // Load the roles from the server and set up the role select
    loadRolesForDisplay(popup, member);

    // Add edit button functionality
    let isEditing = false;
    editBtn.addEventListener('click', () => {
        isEditing = !isEditing;
        editBtn.classList.toggle('active');
        
        const roleDisplay = popup.querySelector('.role-display');
        
        if (isEditing) {
            // Szerkesztés mód bekapcsolása
            // Szerepkör szerkesztéséhez select mezőt adunk hozzá
            const roleDisplay = popup.querySelector('.role-display');
            const currentRole = roleDisplay.textContent;
            
            // Select létrehozása szerepkörhöz
            createRoleSelect(popup, member, roleDisplay);
            
            // Többi mező szerkeszthetővé tétele
            const editableFields = popup.querySelectorAll('.editable');
            editableFields.forEach(field => {
                field.classList.add('editing');
            });
            
            // Profilkép szerkeszthetővé tétele
            const profilePic = popup.querySelector('.member-image-container');
            profilePic.classList.add('editing');
        } else {
            // Szerkesztés mód kikapcsolása
            // Szerepkör megjelenítés visszaállítása
            const roleSelectContainer = popup.querySelector('.role-select-container');
            if (roleSelectContainer) {
                const roleValue = roleSelectContainer.querySelector('select').value;
                const roleText = roleSelectContainer.querySelector('select option:checked').textContent;
                roleDisplay.textContent = roleText;
                roleSelectContainer.remove();
            }
            
            // Többi mező szerkeszthetőségének megszüntetése
            const editableFields = popup.querySelectorAll('.editable');
            editableFields.forEach(field => {
                field.classList.remove('editing');
                const input = field.querySelector('.edit-input');
                if (input) {
                    field.textContent = input.value;
                }
            });
            
            // Profilkép szerkesztés kikapcsolása
            const profilePic = popup.querySelector('.member-image-container');
            profilePic.classList.remove('editing');
        }
    });

    // Initialize member editing
    initializeMemberEditing(popup, member, companyName);

    // Add profile picture upload functionality
    if (typeof setupProfilePictureUpload === 'function') {
        setupProfilePictureUpload(popup, member);
    }
}

// Új függvény a szerepkörök betöltésére és megjelenítésére
async function loadRolesForDisplay(popup, member) {
    try {
        console.log('Szerepkörök betöltése...', member);
        const roleDisplay = popup.querySelector('.role-display');
        
        // Fetch available roles
        const response = await fetch('get_roles.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Nem sikerült betölteni a szerepköröket');
        }
        
        // Roles stored globally for later use
        window.availableRoles = data.roles;
        
        // Now get the user's current role
        try {
            console.log('Lekérjük a felhasználó szerepkörét...');
            const memberRoleResponse = await fetch(`get_member_role.php?member_id=${member.id}`);
            if (!memberRoleResponse.ok) {
                console.error('Hiba a szerepkör lekérésekor:', memberRoleResponse.status);
                roleDisplay.textContent = 'Nem sikerült betölteni';
                return;
            }
            
            const memberRoleData = await memberRoleResponse.json();
            if (memberRoleData.success && memberRoleData.role) {
                roleDisplay.textContent = memberRoleData.role.role_name;
                member.role_id = memberRoleData.role.id;
            } else {
                roleDisplay.textContent = 'Nincs beállítva';
            }
        } catch (error) {
            console.error('Hiba a felhasználó szerepkörének lekérésekor:', error);
            roleDisplay.textContent = 'Hiba történt';
        }
        
    } catch (error) {
        console.error('Hiba a szerepkörök betöltésekor:', error);
        const roleDisplay = popup.querySelector('.role-display');
        roleDisplay.textContent = 'Hiba a betöltés során';
    }
}

// Új függvény a szerepkör select létrehozásához
function createRoleSelect(popup, member, roleDisplay) {
    // Ellenőrizzük, hogy betöltődtek-e a szerepkörök
    if (!window.availableRoles || !Array.isArray(window.availableRoles)) {
        console.error('Szerepkörök nem érhetők el!');
        return;
    }
    
    const currentRole = roleDisplay.textContent.trim();
    
    // Létrehozzuk a select container-t
    const container = document.createElement('div');
    container.className = 'role-select-container';
    
    // Létrehozzuk a select elemet
    const select = document.createElement('select');
    select.className = 'role-select';
    select.setAttribute('data-member-id', member.id);
    
    // Opciók hozzáadása
    window.availableRoles.forEach(role => {
        const option = document.createElement('option');
        option.value = role.id;
        option.textContent = role.role_name;
        if (role.role_name === currentRole || (member.role_id && member.role_id == role.id)) {
            option.selected = true;
        }
        select.appendChild(option);
    });
    
    // Gombok konténere
    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'edit-buttons';
    
    // Mentés gomb
    const saveBtn = document.createElement('button');
    saveBtn.className = 'save-edit';
    saveBtn.innerHTML = '<i class="fas fa-check"></i>';
    saveBtn.title = 'Mentés';
    
    // Mégse gomb
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'cancel-edit';
    cancelBtn.innerHTML = '<i class="fas fa-times"></i>';
    cancelBtn.title = 'Mégse';
    
    // Gombok hozzáadása
    buttonContainer.appendChild(saveBtn);
    buttonContainer.appendChild(cancelBtn);
    
    // Minden elem hozzáadása a konténerhez
    container.appendChild(select);
    container.appendChild(buttonContainer);
    
    // Eredeti tartalom cseréje
    roleDisplay.innerHTML = '';
    roleDisplay.appendChild(container);
    
    // Események kezelése
    saveBtn.addEventListener('click', async () => {
        const selectedValue = select.value;
        const selectedText = select.options[select.selectedIndex].textContent;
        
        if (!selectedValue) {
            showNotification('error', 'Hiba', 'Válassz szerepkört!');
            return;
        }
        
        try {
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            saveBtn.disabled = true;
            
            const response = await fetch('update_member_role.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    member_id: member.id,
                    role_id: selectedValue
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                member.role_id = selectedValue;
                roleDisplay.textContent = selectedText;
                showNotification('success', 'Sikeres mentés', 'A szerepkör sikeresen frissítve.');
            } else {
                throw new Error(result.error || 'Sikertelen frissítés');
            }
            
        } catch (error) {
            console.error('Hiba a szerepkör mentésekor:', error);
            showNotification('error', 'Hiba', error.message || 'Nem sikerült frissíteni a szerepkört.');
            
            // Visszaállítjuk az eredeti kijelzőt
            roleDisplay.textContent = currentRole;
        }
    });
    
    cancelBtn.addEventListener('click', () => {
        roleDisplay.textContent = currentRole;
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}. ${month}. ${day}.`;
}

function showError(title, message) {
    showNotification('error', title, message);
}

function showSuccess(title, message) {
    showNotification('success', title, message);
}

function createSettingsContent() {
    const container = document.createElement('div');
    container.className = 'settings-content';

    const themeSection = document.createElement('div');
    themeSection.className = 'settings-section';

    const header = document.createElement('div');
    header.className = 'settings-section-header';
    const title = document.createElement('h3');
    title.textContent = 'Megjelenés';
    header.appendChild(title);

    const themeToggleContainer = document.createElement('div');
    themeToggleContainer.className = 'theme-toggle-container';

    const themeLabel = document.createElement('span');
    themeLabel.className = 'settings-section-title';
    themeLabel.textContent = 'Sötét mód';

    const toggleLabel = document.createElement('label');
    toggleLabel.id = 'theme-toggle-button';

    const toggleInput = document.createElement('input');
    toggleInput.type = 'checkbox';
    toggleInput.id = 'toggle';
    toggleInput.checked = document.documentElement.getAttribute('data-theme') === 'dark';

    const toggleSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    toggleSvg.setAttribute('viewBox', '0 0 69.667 44');
    toggleSvg.innerHTML = `
        <defs>
            <filter id="container" x="-0.8" y="-0.8" width="63.667" height="38" filterUnits="userSpaceOnUse">
                <feOffset input="SourceAlpha"/>
                <feGaussianBlur stdDeviation="3" result="blur"/>
                <feFlood flood-color="#000" flood-opacity="0.161"/>
                <feComposite operator="in" in2="blur"/>
                <feComposite in="SourceGraphic"/>
            </filter>
            <filter id="sun-outer" x="-3" y="-3" width="39.333" height="39.333" filterUnits="userSpaceOnUse">
                <feOffset input="SourceAlpha"/>
                <feGaussianBlur stdDeviation="3" result="blur-2"/>
                <feFlood flood-color="#000" flood-opacity="0.161"/>
                <feComposite operator="in" in2="blur-2"/>
                <feComposite in="SourceGraphic"/>
            </filter>
            <filter id="sun" x="0.333" y="0.333" width="32.667" height="32.667" filterUnits="userSpaceOnUse">
                <feOffset input="SourceAlpha"/>
                <feGaussianBlur stdDeviation="3" result="blur-3"/>
                <feFlood flood-color="#000" flood-opacity="0.161"/>
                <feComposite operator="in" in2="blur-3"/>
                <feComposite in="SourceGraphic"/>
            </filter>
            <filter id="moon" x="22.667" y="-3" width="39.333" height="39.333" filterUnits="userSpaceOnUse">
                <feOffset input="SourceAlpha"/>
                <feGaussianBlur stdDeviation="3" result="blur-4"/>
                <feFlood flood-color="#000" flood-opacity="0.161"/>
                <feComposite operator="in" in2="blur-4"/>
                <feComposite in="SourceGraphic"/>
            </filter>
            <filter id="cloud" x="-9.434" y="-3" width="69.2" height="48.3" filterUnits="userSpaceOnUse">
                <feOffset input="SourceAlpha"/>
                <feGaussianBlur stdDeviation="3" result="blur-5"/>
                <feFlood flood-color="#000" flood-opacity="0.161"/>
                <feComposite operator="in" in2="blur-5"/>
                <feComposite in="SourceGraphic"/>
            </filter>
        </defs>
        <g transform="translate(3.5 3.5)" data-name="Component 15 – 1" id="Component_15_1">
            <g filter="url(#container)" transform="matrix(1, 0, 0, 1, -3.5, -3.5)">
                <rect fill="#83cbd8" transform="translate(3.5 3.5)" rx="17.5" height="35" width="60.667" data-name="container" id="container"></rect>
            </g>
            <g transform="translate(2.333 2.333)" id="button">
                <g data-name="sun" id="sun">
                    <g filter="url(#sun-outer)" transform="matrix(1, 0, 0, 1, -5.83, -5.83)">
                        <circle fill="#f8e664" transform="translate(5.83 5.83)" r="15.167" cy="15.167" cx="15.167" data-name="sun-outer" id="sun-outer-2"></circle>
                    </g>
                    <g filter="url(#sun)" transform="matrix(1, 0, 0, 1, -5.83, -5.83)">
                        <path fill="rgba(246,254,247,0.29)" transform="translate(9.33 9.33)" d="M11.667,0A11.667,11.667,0,1,1,0,11.667,11.667,11.667,0,0,1,11.667,0Z" data-name="sun" id="sun-3"></path>
                    </g>
                    <circle fill="#fcf4b9" transform="translate(8.167 8.167)" r="7" cy="7" cx="7" id="sun-inner"></circle>
                </g>
                <g data-name="moon" id="moon">
                    <g filter="url(#moon)" transform="matrix(1, 0, 0, 1, -31.5, -5.83)">
                        <circle fill="#cce6ee" transform="translate(31.5 5.83)" r="15.167" cy="15.167" cx="15.167" data-name="moon" id="moon-3"></circle>
                    </g>
                    <g fill="#a6cad0" transform="translate(-24.415 -1.009)" id="patches">
                        <circle transform="translate(43.009 4.496)" r="2" cy="2" cx="2"></circle>
                        <circle transform="translate(39.366 17.952)" r="2" cy="2" cx="2" data-name="patch"></circle>
                        <circle transform="translate(33.016 8.044)" r="1" cy="1" cx="1" data-name="patch"></circle>
                        <circle transform="translate(51.081 18.888)" r="1" cy="1" cx="1" data-name="patch"></circle>
                        <circle transform="translate(33.016 22.503)" r="1" cy="1" cx="1" data-name="patch"></circle>
                        <circle transform="translate(50.081 10.53)" r="1.5" cy="1.5" cx="1.5" data-name="patch"></circle>
                    </g>
                </g>
            </g>
            <g filter="url(#cloud)" transform="matrix(1, 0, 0, 1, -3.5, -3.5)">
                <path fill="#fff" transform="translate(-3466.47 -160.94)" d="M3512.81,173.815a4.463,4.463,0,0,1,2.243.62.95.95,0,0,1,.72-1.281,4.852,4.852,0,0,1,2.623.519c.034.02-.5-1.968.281-2.716a2.117,2.117,0,0,1,2.829-.274,1.821,1.821,0,0,1,.854,1.858c.063.037,2.594-.049,3.285,1.273s-.865,2.544-.807,2.626a12.192,12.192,0,0,1,2.278.892c.553.448,1.106,1.992-1.62,2.927a7.742,7.742,0,0,1-3.762-.3c-1.28-.49-1.181-2.65-1.137-2.624s-1.417,2.2-2.623,2.2a4.172,4.172,0,0,1-2.394-1.206,3.825,3.825,0,0,1-2.771.774c-3.429-.46-2.333-3.267-2.2-3.55A3.721,3.721,0,0,1,3512.81,173.815Z" data-name="cloud" id="cloud"></path>
            </g>
            <g fill="#def8ff" transform="translate(3.585 1.325)" id="stars">
                <path transform="matrix(-1, 0.017, -0.017, -1, 24.231, 3.055)" d="M.774,0,.566.559,0,.539.458.933.25,1.492l.485-.361.458.394L1.024.953,1.509.592.943.572Z"></path>
                <path transform="matrix(-0.777, 0.629, -0.629, -0.777, 23.185, 12.358)" d="M1.341.529.836.472.736,0,.505.46,0,.4.4.729l-.231.46L.605.932l.4.326L.9.786Z" data-name="star"></path>
                <path transform="matrix(0.438, 0.899, -0.899, 0.438, 23.177, 29.735)" d="M.015,1.065.475.9l.285.365L.766.772l.46-.164L.745.494.751,0,.481.407,0,.293.285.658Z" data-name="star"></path>
                <path transform="translate(12.677 0.388) rotate(104)" d="M1.161,1.6,1.059,1,1.574.722.962.607.86,0,.613.572,0,.457.446.881.2,1.454l.516-.274Z" data-name="star"></path>
                <path transform="matrix(-0.07, 0.998, -0.998, -0.07, 11.066, 15.457)" d="M.873,1.648l.114-.62L1.579.945,1.03.62,1.144,0,.706.464.157.139.438.7,0,1.167l.592-.083Z" data-name="star"></path>
                <path transform="translate(8.326 28.061) rotate(11)" d="M.593,0,.638.724,0,.982l.7.211.045.724.36-.64.7.211L1.342.935,1.7.294,1.063.552Z" data-name="star"></path>
                <path transform="translate(5.012 5.962) rotate(172)" d="M.816,0,.5.455,0,.311.323.767l-.312.455.516-.215.323.456L.827.911,1.343.7.839.552Z" data-name="star"></path>
                <path transform="translate(2.218 14.616) rotate(169)" d="M1.261,0,.774.571.114.3.487.967,0,1.538.728,1.32l.372.662.047-.749.728-.218L1.215.749Z" data-name="star"></path>
            </g>
        </g>
    `;

    toggleLabel.appendChild(toggleInput);
    toggleLabel.appendChild(toggleSvg);

    toggleInput.addEventListener('change', function() {
        const theme = this.checked ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
    });

    themeToggleContainer.appendChild(themeLabel);
    themeToggleContainer.appendChild(toggleLabel);

    themeSection.appendChild(header);
    themeSection.appendChild(themeToggleContainer);
    container.appendChild(themeSection);

    return container;
}

// Add confirmation dialog function
function showConfirmationDialog(title, message, onConfirm) {
    const overlay = document.createElement('div');
    overlay.classList.add('confirmation-dialog-overlay');
    
    const dialog = document.createElement('div');
    dialog.classList.add('confirmation-dialog');
    dialog.innerHTML = `
        <h3>${title}</h3>
        <p>${message}</p>
        <div class="confirmation-dialog-buttons">
            <button class="cancel-btn">Mégse</button>
            <button class="confirm-btn">Igen</button>
        </div>
    `;
    
    document.body.appendChild(overlay);
    document.body.appendChild(dialog);
    
    const cancelBtn = dialog.querySelector('.cancel-btn');
    const confirmBtn = dialog.querySelector('.confirm-btn');
    
    const closeDialog = () => {
        overlay.remove();
        dialog.remove();
    };
    
    cancelBtn.addEventListener('click', closeDialog);
    confirmBtn.addEventListener('click', () => {
        closeDialog();
        onConfirm();
    });
    
    overlay.addEventListener('click', closeDialog);
}

function createProfilePopup() {
    // Először lekérjük az admin adatait
    fetch('get_admin_profile.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Server response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(result => {
            if (result.success) {
                const adminData = result.data;
                
                // Létrehozzuk a popup ablakot az admin adataival
                const popup = createPopup({
                    theme: 'profile',
                    title: 'Profil',
                    width: 400,
                    height: 580,
                    content: `
                        <div class="profile-container">
                            <div class="profile-header">
                                <div class="profile-icon">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <h2 class="admin-name">Admin Profil</h2>
                            </div>
                            
                            <div class="profile-info">
                                <div class="info-group">
                                    <label>Felhasználónév</label>
                                    <p class="editable" data-field="username">${adminData.username}</p>
                                </div>
                                <div class="info-group">
                                    <label>Email cím</label>
                                    <p class="editable" data-field="email">${adminData.email}</p>
                                </div>
                                <button class="change-password-btn">
                                    <i class="fas fa-key"></i>
                                    Jelszó módosítása
                                </button>
                            </div>
                        </div>
                    `
                });

                // Hozzáadjuk a szerkesztés gombot
                const header = popup.querySelector('.popup-header');
                const editBtn = document.createElement('button');
                editBtn.className = 'edit-company-btn';
                editBtn.innerHTML = '<i class="fas fa-pencil-alt"></i>';
                header.insertBefore(editBtn, header.firstChild);

                // Szerkesztés funkció hozzáadása
                const editableFields = popup.querySelectorAll('.editable');
                let isEditing = false;

                editBtn.addEventListener('click', () => {
                    isEditing = !isEditing;
                    editBtn.classList.toggle('active');

                    editableFields.forEach(field => {
                        const currentValue = field.textContent;
                        if (isEditing) {
                            field.innerHTML = `
                                <div class="edit-field">
                                    <input type="text" class="edit-input" value="${currentValue}">
                                    <div class="edit-buttons">
                                        <button class="save-edit" title="Mentés">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="cancel-edit" title="Mégse">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            `;

                            const input = field.querySelector('.edit-input');
                            const saveBtn = field.querySelector('.save-edit');
                            const cancelBtn = field.querySelector('.cancel-edit');

                            saveBtn.addEventListener('click', async () => {
                                const newValue = input.value.trim();
                                if (newValue !== currentValue) {
                                    try {
                                        const response = await fetch('update_admin_profile.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                            },
                                            body: JSON.stringify({
                                                field: field.dataset.field,
                                                value: newValue
                                            })
                                        });

                                        const result = await response.json();
                                        if (result.success) {
                                            field.textContent = newValue;
                                            showSuccess('Sikeres módosítás', 'Az adat sikeresen frissítve!');
                                        } else {
                                            showError('Hiba', result.message || 'Nem sikerült frissíteni az adatot!');
                                            field.textContent = currentValue;
                                        }
                                    } catch (error) {
                                        showError('Hiba', 'Nem sikerült frissíteni az adatot!');
                                        field.textContent = currentValue;
                                    }
                                } else {
                                    field.textContent = currentValue;
                                }
                            });

                            cancelBtn.addEventListener('click', () => {
                                field.textContent = currentValue;
                            });
                        } else {
                            field.textContent = field.querySelector('.edit-input')?.value || currentValue;
                        }
                    });
                });
            } else {
                showError('Hiba', result.error || 'Nem sikerült betölteni a profil adatokat!');
            }
        })
        .catch(error => {
            showError('Hiba', 'Nem sikerült betölteni a profil adatokat!');
            console.error('Error:', error);
        });
}

function createCompaniesPopup() {
    const popup = createPopup({
        theme: 'companies',
        title: 'Cégek',
        width: 800,
        height: 600
    });

    // Cégek betöltése
    loadCompanies().then(companies => {
        if (companies) {
            displayCompanies(companies);
        }
    });

    return popup;
}

function createSettingsPopup() {
    const popup = createPopup({
        theme: 'settings',
        title: 'Beállítások',
        width: 400,
        height: 300
    });

    // Használjuk a themeManager-t a beállítások tartalom létrehozásához
    const settingsContent = window.themeManager.createSettingsContent();
    popup.querySelector('.popup-content').appendChild(settingsContent);

    return popup;
}

// Rename makeDraggable to makePopupDraggable for consistency
function makePopupDraggable(element) {
    let isDragging = false;
    let currentX;
    let currentY;
    let initialX;
    let initialY;
    let xOffset = 0;
    let yOffset = 0;

    const dragStart = (e) => {
        if (e.type === "touchstart") {
            initialX = e.touches[0].clientX - xOffset;
            initialY = e.touches[0].clientY - yOffset;
        } else {
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;
        }

        if (e.target.classList.contains('popup-header')) {
            isDragging = true;
        }
    };

    const drag = (e) => {
        if (isDragging) {
            e.preventDefault();

            if (e.type === "touchmove") {
                currentX = e.touches[0].clientX - initialX;
                currentY = e.touches[0].clientY - initialY;
            } else {
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;
            }

            xOffset = currentX;
            yOffset = currentY;

            setTranslate(currentX, currentY, element);
        }
    };

    const dragEnd = () => {
        initialX = currentX;
        initialY = currentY;
        isDragging = false;
    };

    const setTranslate = (xPos, yPos, el) => {
        el.style.transform = `translate3d(${xPos}px, ${yPos}px, 0)`;
    };

    element.addEventListener('touchstart', dragStart, false);
    element.addEventListener('touchend', dragEnd, false);
    element.addEventListener('touchmove', drag, false);
    element.addEventListener('mousedown', dragStart, false);
    element.addEventListener('mouseup', dragEnd, false);
    element.addEventListener('mousemove', drag, false);
}

// Szerepkörök betöltése
async function loadRoles(popup, member) {
    try {
        console.log('Szerepkörök betöltése...', member);
        const roleSelect = popup.querySelector('.role-select');
        
        // Fetch available roles
        const response = await fetch('get_roles.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Nem sikerült betölteni a szerepköröket');
        }
        
        console.log('Elérhető szerepkörök:', data.roles);
        
        // Clear existing options except the loading one
        while (roleSelect.options.length > 0) {
            roleSelect.remove(0);
        }
        
        // Add options from server
        data.roles.forEach(role => {
            const option = document.createElement('option');
            option.value = role.id;
            option.textContent = role.role_name;
            roleSelect.appendChild(option);
        });
        
        // Now get the user's current role
        try {
            console.log('Lekérjük a felhasználó szerepkörét...');
            const memberRoleResponse = await fetch(`get_member_role.php?member_id=${member.id}`);
            if (!memberRoleResponse.ok) {
                console.error('Hiba a szerepkör lekérésekor:', memberRoleResponse.status);
                
                // Check if the role_id is available directly in the member object
                if (member.role_id) {
                    console.log('Szerepkör a member objektumban:', member.role_id);
                    roleSelect.value = member.role_id;
                }
                return;
            }
            
            const memberRoleData = await memberRoleResponse.json();
            console.log('Felhasználó szerepköre:', memberRoleData);
            
            if (memberRoleData.success && memberRoleData.role_id) {
                roleSelect.value = memberRoleData.role_id;
            } else if (member.role_id) {
                roleSelect.value = member.role_id;
            }
        } catch (roleError) {
            console.error('Hiba a szerepkör lekérésekor:', roleError);
            
            // Fallback to the role_id in member data if available
            if (member.role_id) {
                roleSelect.value = member.role_id;
            }
        }
        
        // Add change event listener - only triggered when edit mode is active since the select is disabled by default
        roleSelect.addEventListener('change', function() {
            if (this.value && member.id) {
                updateMemberRole(member.id, this.value);
            }
        });
        
    } catch (error) {
        console.error('Hiba a szerepkörök betöltésekor:', error);
        const roleSelect = popup.querySelector('.role-select');
        roleSelect.innerHTML = '<option value="">Hiba a betöltés során</option>';
    }
}

// Szerepkör módosítása
async function updateMemberRole(memberId, roleId) {
    try {
        const response = await fetch('update_member_role.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                member_id: memberId,
                role_id: roleId
            })
        });
        
        const result = await response.json();
        if (result.success) {
            showSuccessMessage('Szerepkör sikeresen frissítve');
        } else {
            showErrorMessage('Hiba történt a szerepkör frissítésekor');
        }
    } catch (error) {
        console.error('Hiba a szerepkör módosításakor:', error);
        showErrorMessage('Hiba történt a szerepkör frissítésekor');
    }
}

// Módosítsuk az initializeMemberEditing függvényt is
function initializeMemberEditing(popup, member, companyName) {
    const editBtn = popup.querySelector('.edit-member-btn');
    const editableFields = popup.querySelectorAll('.editable');
    let isEditing = false;

    editBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        isEditing = !isEditing;
        editBtn.classList.toggle('active');
        
        editableFields.forEach(field => {
            if (isEditing) {
                const currentValue = field.textContent.trim();
                if (field.dataset.field === 'role') {
                    // Szerepkör mező szerkesztése
                    fetch('get_roles.php')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.error || 'Nem sikerült betölteni a szerepköröket');
                            }
                            
                            const roles = data.roles;
                            const currentRole = field.textContent.trim();
                            const container = document.createElement('div');
                            container.className = 'role-edit-container';
                            
                            const select = document.createElement('select');
                            select.className = 'role-select';
                            
                            // Opciók hozzáadása
                            roles.forEach(role => {
                                const option = document.createElement('option');
                                option.value = role.id;
                                option.textContent = role.role_name;
                                if (role.role_name === currentRole) {
                                    option.selected = true;
                                }
                                select.appendChild(option);
                            });
                            
                            // Gombok konténere
                            const buttonContainer = document.createElement('div');
                            buttonContainer.className = 'edit-buttons';
                            
                            // Mentés gomb
                            const saveBtn = document.createElement('button');
                            saveBtn.className = 'save-edit';
                            saveBtn.innerHTML = '<i class="fas fa-check"></i>';
                            saveBtn.title = 'Mentés';
                            
                            // Mégse gomb
                            const cancelBtn = document.createElement('button');
                            cancelBtn.className = 'cancel-edit';
                            cancelBtn.innerHTML = '<i class="fas fa-times"></i>';
                            cancelBtn.title = 'Mégse';
                            
                            // Gombok hozzáadása
                            buttonContainer.appendChild(saveBtn);
                            buttonContainer.appendChild(cancelBtn);
                            
                            // Minden elem hozzáadása a konténerhez
                            container.appendChild(select);
                            container.appendChild(buttonContainer);
                            
                            // Eredeti tartalom cseréje
                            field.innerHTML = '';
                            field.appendChild(container);

                            // Események kezelése
                            saveBtn.addEventListener('click', () => {
                                const selectedRole = select.value;
                                const memberId = field.dataset.memberId;

                                // Ellenőrizzük, hogy van-e memberId
                                if (!memberId) {
                                    showNotification('error', 'Hiányzó felhasználó azonosító');
                                    return;
                                }

                                // Ellenőrizzük, hogy van-e kiválasztott szerepkör
                                if (!selectedRole) {
                                    showNotification('error', 'Válasszon szerepkört');
                                    return;
                                }

                                fetch('update_member_role.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        memberId: memberId,
                                        roleId: selectedRole
                                    })
                                })
                                .then(response => {
                                    if (!response.ok) {
                                        return response.json().then(data => {
                                            throw new Error(data.error || `HTTP error! status: ${response.status}`);
                                        });
                                    }
                                    return response.json();
                                })
                                .then(data => {
                                    if (!data.success) {
                                        throw new Error(data.error || 'Failed to update role');
                                    }
                                    
                                    // Frissítjük a megjelenített szerepkört
                                    const selectedOption = select.options[select.selectedIndex];
                                    field.innerHTML = selectedOption.textContent;
                                    
                                    // Sikeres frissítés üzenet
                                    showNotification('success', 'A szerepkör sikeresen frissítve');
                                })
                                .catch(error => {
                                    console.error('Error updating role:', error);
                                    showNotification('error', error.message || 'Hiba történt a szerepkör frissítésekor');
                                    // Visszaállítjuk az eredeti szerepkört
                                    field.innerHTML = currentRole;
                                });
                            });

                            cancelBtn.addEventListener('click', () => {
                                field.textContent = currentRole;
                            });
                        })
                        .catch(error => {
                            console.error('Error loading roles:', error);
                            showError('Hiba', 'Nem sikerült betölteni a szerepköröket.');
                            field.textContent = currentValue;
                        });
                } else {
                    // Egyéb mezők szerkesztése
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'edit-input';
                    input.value = currentValue;
                    
                    const buttonContainer = document.createElement('div');
                    buttonContainer.className = 'edit-buttons';
                    
                    const saveBtn = document.createElement('button');
                    saveBtn.className = 'save-edit';
                    saveBtn.innerHTML = '<i class="fas fa-check"></i>';
                    saveBtn.title = 'Mentés';
                    
                    const cancelBtn = document.createElement('button');
                    cancelBtn.className = 'cancel-edit';
                    cancelBtn.innerHTML = '<i class="fas fa-times"></i>';
                    cancelBtn.title = 'Mégse';
                    
                    buttonContainer.appendChild(saveBtn);
                    buttonContainer.appendChild(cancelBtn);
                    
                    field.innerHTML = '';
                    field.appendChild(input);
                    field.appendChild(buttonContainer);
                    
                    input.focus();
                    
                    saveBtn.addEventListener('click', () => {
                        const newValue = input.value.trim();
                        if (newValue !== currentValue) {
                            // Itt küldheted el a módosítást a szervernek
                            field.textContent = newValue;
                        } else {
                            field.textContent = currentValue;
                        }
                    });
                    
                    cancelBtn.addEventListener('click', () => {
                        field.textContent = currentValue;
                    });
                }
            } else {
                // Ha kikapcsoljuk a szerkesztést, visszaállítjuk az eredeti megjelenítést
                const input = field.querySelector('.edit-input');
                if (input) {
                    field.textContent = input.value;
                }
            }
        });
    });
}

// CSS hozzáadása a szerepkör szerkesztéshez
const style = document.createElement('style');
style.textContent = `
    .role-edit-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .role-select {
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid #ccc;
        background-color: #fff;
        color: #333;
    }
    
    .edit-buttons {
        display: flex;
        gap: 5px;
    }
    
    .edit-buttons button {
        width: 24px;
        height: 24px;
        padding: 4px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .save-edit {
        background-color: #4CAF50;
        color: white;
    }
    
    .cancel-edit {
        background-color: #f44336;
        color: white;
    }
`;
document.head.appendChild(style);

// Értesítések megjelenítése
function showNotification(type, title, message) {
    // Eltávolítjuk a korábbi értesítéseket
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());

    // Új értesítés létrehozása
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <h4>${title}</h4>
        <p>${message}</p>
    `;
    document.body.appendChild(notification);
    
    console.log(`Értesítés megjelenítése: ${type} - ${title} - ${message}`);

    // 5 másodperc múlva eltűnik
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 500);
    }, 5000);
}

// Session kezelés
let sessionCheckInterval;
let lastActivityTime = Date.now();
let countdownInterval = null;

// Aktivitás figyelése
function resetActivityTimer() {
    // Eltávolítjuk az aktivitás kezelését, csak a figyelmeztetést zárjuk be
    const warningDialog = document.querySelector('.session-warning-dialog');
    if (warningDialog) {
        warningDialog.remove();
    }
}

// Header időzítő létrehozása
function createHeaderTimer() {
    const header = document.querySelector('.status-bar');
    if (!header) return;

    // Ellenőrizzük, hogy létezik-e már az időzítő
    let existingTimer = header.querySelector('.session-timer');
    if (!existingTimer) {
        const timerContainer = document.createElement('div');
        timerContainer.className = 'session-timer';
        timerContainer.innerHTML = `
            <i class="fas fa-clock"></i>
            <span class="timer-text">Munkamenet: <span class="countdown-value"></span></span>
        `;
        // Beszúrjuk az első elem elé
        if (header.firstChild) {
            header.insertBefore(timerContainer, header.firstChild);
        } else {
            header.appendChild(timerContainer);
        }
    }
}

// Visszaszámláló frissítése
function updateCountdown(seconds) {
    const countdownElement = document.querySelector('.countdown-value');
    if (!countdownElement) return;

    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    
    // Formázás: perc:másodperc
    countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
}

// Utolsó perces figyelmeztetés
function showLastMinuteWarning(seconds) {
    let warningDialog = document.querySelector('.session-warning-dialog');
    
    if (!warningDialog) {
        warningDialog = document.createElement('div');
        warningDialog.className = 'session-warning-dialog';
        
        const content = document.createElement('div');
        content.className = 'session-warning-content';
        
        content.innerHTML = `
            <h3 style="color: #000000 !important;">Figyelmeztetés</h3>
            <p style="color: #000000 !important;">A munkamenet hamarosan lejár!</p>
            <div class="countdown" style="margin: 20px 0;">
                <span class="countdown-display" style="color: #000000 !important; font-size: 2.5em; font-weight: bold;">${seconds}</span>
            </div>
            <div class="warning-buttons">
                <button class="logout-btn">Kijelentkezés</button>
                <button class="stay-active-btn">Munkamenet meghosszabbítása</button>
            </div>
        `;
        
        warningDialog.appendChild(content);
        document.body.appendChild(warningDialog);
        
        const stayActiveBtn = content.querySelector('.stay-active-btn');
        const logoutBtn = content.querySelector('.logout-btn');
        
        stayActiveBtn.addEventListener('click', async () => {
            try {
                const response = await fetch('reset_session.php');
                const data = await response.json();
                
                if (data.success) {
                    warningDialog.remove();
                    resetActivityTimer();
                }
            } catch (error) {
                console.error('Hiba történt a munkamenet meghosszabbítása során:', error);
            }
        });
        
        logoutBtn.addEventListener('click', () => {
            window.location.href = 'logout.php';
        });
    } else {
        const countdownDisplay = warningDialog.querySelector('.countdown-display');
        if (countdownDisplay) {
            countdownDisplay.textContent = seconds;
            countdownDisplay.style.color = '#000000';
        }
    }
}

// Session ellenőrzése
function checkSession(updateActivity = false) {
    const url = updateActivity ? 'check_session.php?update_activity=true' : 'check_session.php';
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'expired' || data.status === 'invalid') {
                window.location.href = '../home.php';
            } else if (data.status === 'active') {
                // Frissítjük a fejlécben lévő időzítőt
                updateCountdown(data.time_remaining);
                
                // Ha 60 másodperc vagy kevesebb van hátra, megjelenítjük a figyelmeztető ablakot
                if (data.time_remaining <= 60) {
                    showLastMinuteWarning(data.time_remaining);
                }
            }
        })
        .catch(error => {
            console.error('Session check failed:', error);
        });
}

// Felhasználói interakció figyelése
document.addEventListener('click', () => {
    checkSession(true);
});

document.addEventListener('keypress', () => {
    checkSession(true);
});

// Session ellenőrzése 1 másodpercenként és időzítő létrehozása
document.addEventListener('DOMContentLoaded', () => {
    createHeaderTimer();
    // Azonnal elindítjuk az első ellenőrzést
    checkSession();
    // Majd beállítjuk az időzítőt
    setInterval(() => checkSession(false), 1000);
});

// Stílus hozzáadása
const sessionStyles = document.createElement('style');
sessionStyles.textContent = `
    .session-timer {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 15px;
        color: #fff;
        font-size: 14px;
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 1000;
    }

    .session-timer i {
        color: #fff;
    }

    .session-timer .timer-text {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #fff;
    }

    .countdown-value {
        font-weight: bold;
        color: #fff;
    }

    .session-warning-dialog {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000;
    }

    .session-warning-content {
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
        min-width: 300px;
    }

    .warning-buttons {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 15px;
    }

    .warning-buttons button {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        transition: opacity 0.2s;
    }

    .stay-active-btn {
        background-color: #2ecc71;
        color: white;
    }

    .logout-btn {
        background-color: #e74c3c;
        color: white;
    }

    .warning-buttons button:hover {
        opacity: 0.9;
    }
`;
document.head.appendChild(sessionStyles);

// Add subscription details script
const subscriptionScript = document.createElement('script');
subscriptionScript.src = 'subscription_details.js';
document.head.appendChild(subscriptionScript);

async function showSubscriptionStats() {
    try {
        const response = await fetch('subscription_details.php?action=getStats');
        const stats = await response.json();

        if (!stats.success) {
            showError('Hiba', 'Nem sikerült betölteni a statisztikákat.');
            return;
        }

        const popup = createPopup({
            theme: 'subscription-stats',
            title: 'Előfizetési statisztikák',
            width: 800,
            height: 600,
            content: `
                <div class="stats-container">
                    <div class="chart-container">
                        <canvas id="subscriptionChart"></canvas>
                    </div>
                    <div class="stats-details">
                        <h3>Részletes statisztikák</h3>
                        <div class="stats-grid"></div>
                    </div>
                </div>
            `
        });

        const ctx = popup.querySelector('#subscriptionChart').getContext('2d');
        
        // Színek a különböző csomagokhoz
        const colors = [
            'rgba(75, 192, 192, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)'
        ];

        // Chart.js diagram létrehozása
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: stats.data.map(item => item.package_name),
                datasets: [{
                    data: stats.data.map(item => item.count),
                    backgroundColor: colors.slice(0, stats.data.length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: getComputedStyle(document.documentElement).getPropertyValue('--text-color')
                        }
                    },
                    title: {
                        display: true,
                        text: 'Előfizetési csomagok megoszlása',
                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-color'),
                        font: {
                            size: 16
                        }
                    }
                }
            }
        });

        // Részletes statisztikák megjelenítése
        const statsGrid = popup.querySelector('.stats-grid');
        let totalSubscriptions = stats.data.reduce((acc, curr) => acc + curr.count, 0);
        
        stats.data.forEach((item, index) => {
            const percentage = ((item.count / totalSubscriptions) * 100).toFixed(1);
            const statItem = document.createElement('div');
            statItem.className = 'stat-item';
            statItem.innerHTML = `
                <div class="stat-color" style="background-color: ${colors[index]}"></div>
                <div class="stat-info">
                    <h4>${item.package_name}</h4>
                    <p>${item.count} cég (${percentage}%)</p>
                </div>
            `;
            statsGrid.appendChild(statItem);
        });

        // Összesítés hozzáadása
        const totalItem = document.createElement('div');
        totalItem.className = 'stat-item total';
        totalItem.innerHTML = `
            <div class="stat-info">
                <h4>Összes előfizetés</h4>
                <p>${totalSubscriptions} cég</p>
            </div>
        `;
        statsGrid.appendChild(totalItem);
    } catch (error) {
        console.error('Error loading subscription stats:', error);
        showError('Hiba', 'Nem sikerült betölteni a statisztikákat.');
    }
}

// Módosítom a cég adatok frissítésének kezelését, hogy jobban működjön és több információt adjon a hibákról
async function updateCompanyField(companyId, field, value) {
    try {
        console.log('Frissítési kísérlet:', { companyId, field, value });
        
        // Direkt debug információ
        console.log('AJAX kérés indítása: update_company.php');
        
        const response = await fetch('update_company.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                company_id: companyId,
                field: field,
                value: value
            })
        });
        
        console.log('Szerver válaszstátusz:', response.status, response.statusText);
        
        if (response.status === 401) {
            // Session expired or not authenticated
            showNotification('error', 'Munkamenet lejárt', 'Kérjük, jelentkezzen be újra!');
            setTimeout(() => window.location.href = 'login.php', 2000);
            return null;
        }
        
        if (!response.ok) {
            throw new Error(`Szerver hiba: ${response.status} ${response.statusText}`);
        }
        
        const responseText = await response.text();
        console.log('Szerver válasz szöveg:', responseText);
        
        // Ellenőrizzük, hogy a válasz üres-e
        if (!responseText.trim()) {
            throw new Error('Üres válasz a szervertől');
        }
        
        // Ellenőrizzük, hogy a válasz HTML-e (PHP hiba)
        if (responseText.trim().startsWith('<!DOCTYPE') || 
            responseText.trim().startsWith('<html') || 
            responseText.trim().startsWith('<?xml') || 
            responseText.trim().startsWith('<')) {
            
            console.error('HTML válasz (valószínűleg PHP hiba):', responseText);
            
            // Próbáljuk meg kiszedni a hibaüzenetet, ha lehetséges
            let errorMatch = responseText.match(/<b>Fatal error<\/b>:(.*?)in/i);
            if (errorMatch && errorMatch[1]) {
                throw new Error('PHP hiba: ' + errorMatch[1].trim());
            }
            
            throw new Error('A szerver hibaüzenetet küldött vissza (HTML). Kérjük, ellenőrizze a szerver naplókat.');
        }
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('JSON elemzési hiba:', e);
            console.error('Nyers válasz:', responseText);
            throw new Error('Érvénytelen JSON válasz a szervertől');
        }
        
        // Részletes naplózás
        console.log('Elemzett válasz objektum:', result);
        
        // Ellenőrizzük a válasz szerkezetét
        if (typeof result !== 'object' || result === null) {
            throw new Error('Érvénytelen válasz formátum');
        }
        
        // Ha van hibaüzenet, azt jelezzük akkor is, ha success=true
        if (result.error) {
            console.warn('Szerver figyelmeztető üzenet:', result.error);
        }
        
        return result;
    } catch (error) {
        console.error('Hiba a cég frissítése során:', error);
        throw error;
    }
}