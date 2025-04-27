document.addEventListener('DOMContentLoaded', function() {
    const popup = document.querySelector('.popup');
    const checkbox = popup.querySelector('input[type="checkbox"]');

    // Kattintás figyelése a dokumentumon
    document.addEventListener('click', function(event) {
        // Ha a kattintás nem a popup-on belül történt és a menü nyitva van
        if (!popup.contains(event.target) && checkbox.checked) {
            checkbox.checked = false;
        }
    });

    // Megakadályozzuk, hogy a popup-on belüli kattintások bezárják a menüt
    popup.addEventListener('click', function(event) {
        // Ha menüpontra kattintottak, akkor zárjuk be a menüt
        if (event.target.closest('button')) {
            checkbox.checked = false;
        }
    });
}); 