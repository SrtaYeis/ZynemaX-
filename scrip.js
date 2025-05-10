function showForm(formType) {
    console.log("showForm called with:", formType);
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const profileForm = document.getElementById('profile-form');

    // Ocultar todos los formularios primero
    if (loginForm) loginForm.style.display = 'none';
    if (registerForm) registerForm.style.display = 'none';
    if (profileForm) profileForm.style.display = 'none';

    // Mostrar el formulario correspondiente
    if (formType === 'login' && loginForm) {
        loginForm.style.display = 'block';
    } else if (formType === 'register' && registerForm) {
        registerForm.style.display = 'block';
    } else if (formType === 'profile' && profileForm) {
        profileForm.style.display = 'block';
    }
}

function updateSedeAndButacas(funcionId) {
    const sedeSelect = document.getElementById('sede_id');
    const butacaSelect = document.getElementById('butaca_id');
    const funcionSelect = document.getElementById('funcion_id');

    // Limpiar los selects dependientes
    sedeSelect.innerHTML = '<option value="">Seleccione una sede</option>';
    butacaSelect.innerHTML = '<option value="">Seleccione una butaca</option>';

    if (funcionId) {
        const selectedOption = funcionSelect.options[funcionSelect.selectedIndex];
        const salaId = selectedOption.getAttribute('data-sala-id');

        // Obtener la sede basada en la sala
        fetch(`?funcion_id=${funcionId}&sala_id=${salaId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la solicitud: ' + response.statusText);
                }
                return response.text(); // Obtener la página completa para procesar
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newSedeSelect = doc.getElementById('sede_id');
                const newButacaSelect = doc.getElementById('butaca_id');

                if (newSedeSelect && newButacaSelect) {
                    sedeSelect.innerHTML = newSedeSelect.innerHTML;
                    butacaSelect.innerHTML = newButacaSelect.innerHTML;

                    // Seleccionar la sede automáticamente si hay una pre-seleccionada
                    const selectedSede = newSedeSelect.value;
                    if (selectedSede) {
                        Array.from(sedeSelect.options).forEach(option => {
                            if (option.value == selectedSede) {
                                option.selected = true;
                            }
                        });
                    }
                } else {
                    console.error('No se encontraron los elementos sede o butaca en la respuesta.');
                }
            })
            .catch(error => console.error('Error al actualizar sede y butacas:', error));
    }
}

// Llamar a updateSedeAndButacas al cargar la página si ya hay una función seleccionada
document.addEventListener('DOMContentLoaded', () => {
    const funcionSelect = document.getElementById('funcion_id');
    if (funcionSelect && funcionSelect.value) {
        updateSedeAndButacas(funcionSelect.value);
    }
});
