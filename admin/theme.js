// Téma inicializálása
document.addEventListener('DOMContentLoaded', function() {
    // Téma beállítása a localStorage alapján
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
});

let currentTheme = localStorage.getItem('theme') || 'light';

function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    currentTheme = theme;
    
    // Frissítjük a beállítások ablakban a címkét
    const themeLabel = document.querySelector('.mode-status');
    if (themeLabel) {
        themeLabel.textContent = theme === 'dark' ? 'Sötét mód' : 'Világos mód';
    }
}

// Exportáljuk a szükséges függvényeket
window.themeManager = {
    setTheme,
    getCurrentTheme: () => currentTheme
}; 