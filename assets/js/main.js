function togglePassword(inputId, toggleElement) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        toggleElement.classList.add('view');    // Nyitott szem ikon
    } else {
        input.type = 'password';
        toggleElement.classList.remove('view'); // Vissza a csukott szem ikonra
    }
}

// Form validáció
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const password = form.querySelector('input[name="jelszo"]');
            const passwordConfirm = form.querySelector('input[name="jelszo_megerosites"]');
            
            if (password && passwordConfirm) {
                if (password.value.length < 8) {
                    e.preventDefault();
                    alert('A jelszónak legalább 8 karakter hosszúnak kell lennie!');
                    return;
                }
                
                if (password.value !== passwordConfirm.value) {
                    e.preventDefault();
                    alert('A jelszavak nem egyeznek!');
                    return;
                }
            }
        });
    });
}); 