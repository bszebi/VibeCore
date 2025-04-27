document.addEventListener('DOMContentLoaded', function() {
    // Dark mode inicializálása
    initDarkMode();
    
    // Dark mode toggle kezelése a beállítások oldalon
    const darkModeToggle = document.getElementById('darkMode');
    const modeStatus = document.getElementById('modeStatus');
    
    if (darkModeToggle) {
        // Beállítjuk a toggle állapotát
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        darkModeToggle.checked = isDarkMode;
        
        // Frissítjük a státusz szöveget
        if (modeStatus) {
            updateModeStatus(modeStatus, isDarkMode);
        }
        
        // Toggle eseménykezelő
        darkModeToggle.addEventListener('change', function() {
            const isDark = this.checked;
            toggleDarkMode(isDark);
            
            // Broadcast üzenet küldése minden nyitott fülnek
            broadcastDarkMode(isDark);
            
            if (modeStatus) {
                updateModeStatus(modeStatus, isDark);
            }
        });
    }

    // Broadcast üzenetek figyelése
    listenForDarkModeChanges();
});

function initDarkMode() {
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    toggleDarkMode(isDarkMode);
}

function toggleDarkMode(isDark) {
    if (isDark) {
        document.body.classList.add('dark-mode');
    } else {
        document.body.classList.remove('dark-mode');
    }
    localStorage.setItem('darkMode', isDark);
}

function updateModeStatus(element, isDark) {
    const darkText = document.documentElement.getAttribute('data-translations-dark_mode') || 'Dark Mode';
    const lightText = document.documentElement.getAttribute('data-translations-light_mode') || 'Light Mode';
    element.textContent = isDark ? darkText : lightText;
}

// Broadcast Channel API használata a fülek közötti szinkronizációhoz
function broadcastDarkMode(isDark) {
    const channel = new BroadcastChannel('darkMode');
    channel.postMessage({ isDarkMode: isDark });
}

function listenForDarkModeChanges() {
    const channel = new BroadcastChannel('darkMode');
    channel.onmessage = (event) => {
        const { isDarkMode } = event.data;
        toggleDarkMode(isDarkMode);
        
        // Ha van toggle gomb az oldalon, azt is frissítjük
        const darkModeToggle = document.getElementById('darkMode');
        if (darkModeToggle) {
            darkModeToggle.checked = isDarkMode;
        }
        
        // Ha van státusz szöveg, azt is frissítjük
        const modeStatus = document.getElementById('modeStatus');
        if (modeStatus) {
            updateModeStatus(modeStatus, isDarkMode);
        }
    };
} 