document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const confirmMessage = form.getAttribute('data-confirm');
            if (confirmMessage && !confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    });

    // Ejemplo de validación para formularios (si aplica)
    const loginForm = document.querySelector('#login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            const dni = document.querySelector('#dni').value;
            const contrasena = document.querySelector('#contrasena').value;

            if (!dni || !contrasena) {
                e.preventDefault();
                alert('Por favor, complete todos los campos.');
            }
        });
    }

    // Ejemplo de funcionalidad para botones de selección
    const buttons = document.querySelectorAll('button[name="select_movie"], button[name="select_sede"], button[name="select_sala"], button[name="select_butaca"]');
    buttons.forEach(button => {
        button.addEventListener('click', () => {
            button.style.backgroundColor = '#28a745';
            button.textContent = 'Seleccionado';
            button.disabled = true;
        });
    });

    // Mostrar mensaje de bienvenida (si aplica)
    const welcomeMessage = document.querySelector('.welcome-message');
    if (welcomeMessage) {
        setTimeout(() => {
            welcomeMessage.style.opacity = '1';
        }, 500);
    }
});
