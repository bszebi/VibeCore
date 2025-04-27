// Tag szerkesztési funkciók
async function updateMemberField(memberId, field, value) {
    try {
        // Validáció
        if (!value.trim()) {
            throw new Error('A mező nem lehet üres!');
        }

        if (field === 'email' && !isValidEmail(value)) {
            throw new Error('Érvénytelen email cím formátum!');
        }

        console.log('Frissítési kísérlet:', { memberId, field, value });
        
        const response = await fetch('update_member.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                member_id: parseInt(memberId),
                field: field,
                value: value.trim()
            })
        });

        console.log('Szerver válasz státusz:', response.status, response.statusText);

        if (response.status === 401) {
            showNotification('error', 'Hiba', 'A munkamenet lejárt. Kérjük, jelentkezzen be újra!');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
            return null;
        }
        
        // A válasz szövegként olvasása
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

        // A válasz JSON-ként olvasása
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('JSON elemzési hiba:', e);
            console.error('Nyers válasz:', responseText);
            throw new Error('Érvénytelen JSON válasz a szervertől');
        }

        console.log('Elemzett válasz:', result);
        
        // Ellenőrizzük a válasz szerkezetét
        if (typeof result !== 'object' || result === null) {
            throw new Error('Érvénytelen válasz formátum');
        }
        
        return result;
    } catch (error) {
        console.error('Hiba a tag frissítése közben:', error);
        throw error;
    }
}

function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

// Értesítés megjelenítése
function showNotification(type, title, message) {
    console.log(`Értesítés megjelenítése: ${type} - ${title} - ${message}`);
    
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

    // 5 másodperc múlva eltűnik
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 500);
    }, 5000);
}

function initializeMemberEditing(popup, member, companyName) {
    console.log('Initializing member editing for:', member);
    
    const editBtn = popup.querySelector('.edit-member-btn');
    if (!editBtn) {
        console.error('Edit button not found!');
        return;
    }

    const editableFields = popup.querySelectorAll('.editable');
    if (editableFields.length === 0) {
        console.error('No editable fields found!');
        return;
    }

    console.log('Found editable fields:', editableFields.length);
    
    let isEditing = false;

    function editBtnHandler(e) {
        e.preventDefault();
        e.stopPropagation();
        
        isEditing = !isEditing;
        editBtn.classList.toggle('active');
        
        console.log('Edit mode:', isEditing);
        
        editableFields.forEach(field => {
            if (!field.dataset.field) {
                if (field.classList.contains('member-firstname')) field.dataset.field = 'firstname';
                if (field.classList.contains('member-lastname')) field.dataset.field = 'lastname';
                if (field.classList.contains('member-email')) field.dataset.field = 'email';
                if (field.classList.contains('member-telephone')) field.dataset.field = 'telephone';
            }

            console.log('Processing field:', field.dataset.field);

            if (isEditing) {
                const currentValue = field.textContent.trim();
                const fieldType = field.dataset.field === 'email' ? 'email' : 'text';
                
                field.innerHTML = `
                    <div class="edit-field-container">
                        <input type="${fieldType}" class="edit-input" value="${currentValue}" data-original="${currentValue}">
                        <div class="edit-buttons">
                            <button class="save-edit" title="Mentés" id="save-${field.dataset.field}">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="cancel-edit" title="Mégse" id="cancel-${field.dataset.field}">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;

                const input = field.querySelector('.edit-input');
                const saveBtn = field.querySelector('.save-edit');
                const cancelBtn = field.querySelector('.cancel-edit');

                // Mentés gomb eseménykezelő
                saveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log(`Mentés gomb kattintás: ${field.dataset.field}`);
                    
                    // Közvetlen visszajelzés alert formájában
                    alert("Működik a zöld pipa gomb!");
                    
                    // Vizuális visszajelzés hogy működik a kattintás
                    this.classList.add('loading');
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    
                    // "Működik" üzenet megjelenítése
                    showNotification('success', 'Feldolgozás', 'Adatok mentése folyamatban...');
                    
                    // Mentés indítása
                    handleSave(field, input, currentValue, member);
                });

                // Mégse gomb eseménykezelő
                cancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log(`Mégse gomb kattintás: ${field.dataset.field}`);
                    
                    // Közvetlen visszajelzés alert formájában
                    alert("A piros X gomb működik!");
                    
                    field.textContent = currentValue;
                    
                    // Jelezzük hogy a mégse is működik
                    showNotification('error', 'Megszakítva', 'Módosítások elvetve.');
                });

                // Enter gomb kezelése
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        console.log(`Enter billentyű: ${field.dataset.field}`);
                        
                        // Közvetlen visszajelzés alert formájában (hogy Enter is megjelenik)
                        alert("Mentés az Enter billentyűvel!");
                        
                        // Vizuális visszajelzés
                        const saveBtn = field.querySelector('.save-edit');
                        if (saveBtn) {
                            saveBtn.classList.add('loading');
                            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        }
                        // Mentés indítása
                        handleSave(field, input, currentValue, member);
                    }
                });
            } else {
                const input = field.querySelector('.edit-input');
                if (input) {
                    field.textContent = input.value;
                }
            }
        });
    }

    editBtn.removeEventListener('click', editBtnHandler);
    editBtn.addEventListener('click', editBtnHandler);
}

async function handleSave(field, input, currentValue, member) {
    try {
        console.log('Mentés indítása...');
        const newValue = input.value.trim();
        
        // Validáció
        if (newValue === '') {
            showNotification('error', 'Hiba', 'A mező nem lehet üres!');
            field.textContent = currentValue;
            return;
        }

        if (field.dataset.field === 'email' && !isValidEmail(newValue)) {
            showNotification('error', 'Hiba', 'Érvénytelen email cím formátum!');
            field.textContent = currentValue;
            return;
        }

        if (newValue === currentValue) {
            console.log('Nincs változás, visszaállítás az eredeti értékre');
            field.textContent = currentValue;
            showNotification('info', 'Információ', 'Nem történt változtatás, az adatok megegyeznek.');
            return;
        }

        const memberDetails = field.closest('.member-details');
        const memberId = memberDetails ? memberDetails.getAttribute('data-member-id') : member.id;
        
        console.log('Mentési kísérlet:', {
            field: field.dataset.field,
            value: newValue,
            memberId: memberId,
            currentValue: currentValue
        });

        if (!memberId) {
            console.error('Hiányzó tag azonosító!');
            showNotification('error', 'Hiba', 'Nem található a tag azonosítója!');
            field.textContent = currentValue;
            return;
        }

        // Várakozás jelzése a mezőben
        field.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            console.log('updateMemberField hívása:', memberId, field.dataset.field, newValue);
            const result = await updateMemberField(memberId, field.dataset.field, newValue);
            console.log('Frissítési eredmény:', result);
            
            if (result && result.success) {
                // Frissítjük a nézetet az új értékkel
                field.textContent = newValue;
                
                // Frissítjük a lokális objektumot is
                if (member) {
                    member[field.dataset.field] = newValue;
                }
                
                // Frissítjük a listaelemeket is
                if (typeof updateMemberCardInList === 'function') {
                    try {
                        updateMemberCardInList(member);
                    } catch (listUpdateError) {
                        console.warn('Listafrissítési hiba:', listUpdateError);
                    }
                }
                
                // Ha név mezőt változtattunk, frissítsük a fejlécet is
                if (field.dataset.field === 'firstname' || field.dataset.field === 'lastname') {
                    const popup = field.closest('[data-theme="member-details"]');
                    if (popup) {
                        const titleElement = popup.querySelector('.popup-title');
                        if (titleElement) {
                            const companyName = titleElement.textContent.split(' - ')[1] || '';
                            const newFullName = `${member.lastname || ''} ${member.firstname || ''}`.trim();
                            if (companyName) {
                                titleElement.textContent = `${newFullName} - ${companyName}`;
                            } else {
                                titleElement.textContent = newFullName;
                            }
                        }
                    }
                }
                
                // Sikeres üzenet
                showNotification('success', 'Sikeres mentés', result.message || 'A módosítások sikeresen mentésre kerültek.');
                
                // Debug infó
                console.log('Frissítve a tag mező:', field.dataset.field, 'érték:', newValue);
                if (result.affected_rows !== undefined) {
                    console.log('Érintett sorok száma:', result.affected_rows);
                }
            } else {
                // Hiba történt, visszaállítjuk az eredeti értéket
                field.textContent = currentValue;
                
                // Hibaüzenet
                showNotification('error', 'Hiba történt', result && result.error ? result.error : 'Nem sikerült frissíteni az adatokat.');
            }
        } catch (error) {
            console.error('Hiba a frissítés során:', error);
            
            // Visszaállítjuk az eredeti értéket
            field.textContent = currentValue;
            
            // Hibaüzenet
            showNotification('error', 'Hiba történt', error.message || 'Nem sikerült frissíteni az adatokat a szerveren.');
        }
    } catch (error) {
        console.error('Váratlan hiba:', error);
        field.textContent = currentValue;
        showNotification('error', 'Rendszerhiba', 'Váratlan hiba történt a művelet során.');
    }
}

function updateMemberCardInList(member) {
    console.log('Updating member card in list:', member);
    const memberCards = document.querySelectorAll('.member-card');
    memberCards.forEach(card => {
        if (card.getAttribute('data-member-id') === member.id.toString()) {
            const nameElement = card.querySelector('.member-name');
            if (nameElement) {
                nameElement.textContent = `${member.lastname} ${member.firstname}`;
            }
            const roleElement = card.querySelector('.member-role');
            if (roleElement) {
                roleElement.textContent = member.role_names || 'Nincs szerepkör';
            }
        }
    });
} 

// Profilkép feltöltés beállítása
function setupProfilePictureUpload(popup, member) {
    const imageContainer = popup.querySelector('.member-image-container');
    if (!imageContainer) return;

    // Töröljük a korábbi inputot, ha van
    const oldInput = imageContainer.querySelector('input[type="file"]');
    if (oldInput) oldInput.remove();

    let isUploading = false;

    imageContainer.addEventListener('click', () => {
        const editBtn = popup.querySelector('.edit-member-btn');
        if (editBtn && editBtn.classList.contains('active') && !isUploading) {
            isUploading = true;
            const currentValue = member.profile_picture || 'user.png';
            imageContainer.innerHTML = `
                <div class="image-upload-container">
                    <div class="image-preview" style="margin-bottom: 10px;">
                        <img src="${currentValue === 'user.png' ? '../assets/img/user.png' : `../uploads/profiles/${currentValue}`}" 
                             onerror="this.src='../assets/img/user.png'" 
                             alt="Előnézet"
                             style="max-width: 200px; max-height: 200px; object-fit: contain; border-radius: 50%;">
                    </div>
                    <input type="file" class="edit-input" accept="image/*" data-original="${currentValue}" style="display: none;">
                </div>
            `;
            const fileInput = imageContainer.querySelector('.edit-input');
            const preview = imageContainer.querySelector('.image-preview img');

            fileInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = async (ev) => {
                        preview.src = ev.target.result;
                        // Feltöltés
                        const formData = new FormData();
                        formData.append('profile_picture', file);
                        formData.append('member_id', member.id);
                        try {
                            const response = await fetch('update_member_profile.php', {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            if (result.success) {
                                member.profile_picture = result.filename;
                                // Frissítjük a popupban a képet
                                imageContainer.innerHTML = `
                                    <img src="../uploads/profiles/${result.filename}?t=${Date.now()}" 
                                         onerror="this.src='../assets/img/user.png'" 
                                         alt="${member.lastname} ${member.firstname}" 
                                         class="member-details-pic">
                                    <div class="profile-pic-overlay">
                                        <i class="fas fa-camera"></i>
                                        <span>Profilkép módosítása</span>
                                    </div>
                                `;
                                updateMemberCardInList(member);
                                showNotification('success', 'Sikeres módosítás', 'A profilkép sikeresen frissítve.');
                            } else {
                                showNotification('error', 'Hiba', result.message || 'Nem sikerült frissíteni a profilképet.');
                                // Visszaállítjuk az eredeti képet
                                imageContainer.innerHTML = `
                                    <img src="${currentValue === 'user.png' ? '../assets/img/user.png' : `../uploads/profiles/${currentValue}`}" 
                                         onerror="this.src='../assets/img/user.png'" 
                                         alt="${member.lastname} ${member.firstname}" 
                                         class="member-details-pic">
                                    <div class="profile-pic-overlay">
                                        <i class="fas fa-camera"></i>
                                        <span>Profilkép módosítása</span>
                                    </div>
                                `;
                            }
                        } catch (error) {
                            showNotification('error', 'Hiba', 'Nem sikerült frissíteni a profilképet.');
                            imageContainer.innerHTML = `
                                <img src="${currentValue === 'user.png' ? '../assets/img/user.png' : `../uploads/profiles/${currentValue}`}" 
                                     onerror="this.src='../assets/img/user.png'" 
                                     alt="${member.lastname} ${member.firstname}" 
                                     class="member-details-pic">
                                <div class="profile-pic-overlay">
                                    <i class="fas fa-camera"></i>
                                    <span>Profilkép módosítása</span>
                                </div>
                            `;
                        } finally {
                            isUploading = false;
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    isUploading = false;
                }
            });
            // Automatikusan megnyitjuk a file választót
            fileInput.click();
        }
    });
} 