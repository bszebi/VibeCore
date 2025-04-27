// Jelszó módosítás kezelése
function handlePasswordChange() {
    const currentPassword = document.getElementById('current-password').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;

    if (newPassword !== confirmPassword) {
        showError('Hiba', 'Az új jelszó és a megerősítés nem egyezik!');
        return;
    }

    if (newPassword.length < 8) {
        showError('Hiba', 'Az új jelszónak legalább 8 karakter hosszúnak kell lennie!');
        return;
    }

    fetch('update_admin_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            currentPassword: currentPassword,
            newPassword: newPassword
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                console.error('Server response:', text);
                throw new Error('Network response was not ok');
            });
        }
        return response.json();
    })
    .then(result => {
        console.log('Szerver válasz:', result);
        if (result.success) {
            showSuccess('Sikeres', 'A jelszó sikeresen módosítva!');
            resetPasswordForm();
        } else {
            showError('Hiba', result.message || 'Nem sikerült módosítani a jelszót!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Hiba', 'Nem sikerült módosítani a jelszót! Kérjük, próbáld újra később.');
    });
}

// Form visszaállítása eredeti állapotra
function resetPasswordForm() {
    const passwordForm = document.querySelector('.password-form');
    if (passwordForm) {
        passwordForm.remove();
    }
    
    const profileInfo = document.querySelector('.profile-info');
    if (profileInfo) {
        profileInfo.style.display = 'block';
    }
    
    // Töröljük az input mezők tartalmát
    const inputs = ['current-password', 'new-password', 'confirm-password'];
    inputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.value = '';
        }
    });
}

// Jelszó módosítás form létrehozása
function createPasswordChangeForm() {
    // Először ellenőrizzük, hogy van-e már létező form
    const existingForm = document.querySelector('.password-form');
    if (existingForm) {
        existingForm.remove();
    }

    const form = document.createElement('div');
    form.className = 'password-form';
    form.innerHTML = `
        <div style="margin-top: -50px; margin-bottom: 50px; margin-left: 85px; font-size: 20px; font-weight: bold;">Jelszó módosítása</div>
        <div class="form-group">
            <label for="current-password">Jelenlegi jelszó:</label>
            <input type="password" id="current-password" required>
        </div>
        <div class="form-group">
            <label for="new-password">Új jelszó:</label>
            <input type="password" id="new-password" required>
        </div>
        <div class="form-group">
            <label for="confirm-password">Új jelszó megerősítése:</label>
            <input type="password" id="confirm-password" required>
        </div>
        <div class="form-actions">
            <button type="button" class="cancel-btn">Mégse</button>
            <button type="button" class="save-btn">Mentés</button>
        </div>
    `;

    // Eseménykezelők hozzáadása
    form.querySelector('.cancel-btn').addEventListener('click', () => {
        resetPasswordForm();
    });

    form.querySelector('.save-btn').addEventListener('click', handlePasswordChange);

    return form;
}

// Jelszó módosítás gomb eseménykezelő
function initializePasswordChangeButton() {
    // Figyeljük a DOM változásait
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.addedNodes.length) {
                const changePasswordBtn = document.querySelector('.change-password-btn');
                if (changePasswordBtn && !changePasswordBtn.hasListener) {
                    changePasswordBtn.hasListener = true;
                    changePasswordBtn.addEventListener('click', () => {
                        const profileInfo = document.querySelector('.profile-info');
                        if (profileInfo) {
                            profileInfo.style.display = 'none';
                        }
                        const passwordForm = createPasswordChangeForm();
                        const popupContent = document.querySelector('.popup-content');
                        if (popupContent) {
                            popupContent.appendChild(passwordForm);
                        }
                    });
                }
            }
        });
    });

    // Figyeljük a body változásait
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

// Inicializálás amikor a dokumentum betöltődik
document.addEventListener('DOMContentLoaded', initializePasswordChangeButton); 