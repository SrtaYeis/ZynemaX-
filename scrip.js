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

function updateSede() {
    const funcionSelect = document.getElementById('funcion_id');
    const sedeSelect = document.getElementById('sede_id');
    const salaSelect = document.getElementById('sala_id');
    const butacaSelect = document.getElementById('butaca_id');

    // Limpiar los selects dependientes
    sedeSelect.innerHTML = '<option value="">Seleccione una sede</option>';
    salaSelect.innerHTML = '<option value="">Seleccione una sala</option>';
    butacaSelect.innerHTML = '<option value="">Seleccione una butaca</option>';

    if (funcionSelect.value) {
        const selectedOption = funcionSelect.options[funcionSelect.selectedIndex];
        const salaId = selectedOption.getAttribute('data-sala-id');

        fetch(`/get_sede_from_sala.php?sala_id=${salaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error al obtener la sede:', data.error);
                    return;
                }
                // Seleccionar automáticamente la sede correspondiente
                Array.from(sedeSelect.options).forEach(option => {
                    if (option.value == data.id_sede) {
                        option.selected = true;
                    }
                });
                updateSalas(); // Actualizar las salas
            })
            .catch(error => console.error('Error al obtener la sede:', error));
    }
}

function updateSalas() {
    const sedeId = document.getElementById('sede_id').value;
    const salaSelect = document.getElementById('sala_id');
    const butacaSelect = document.getElementById('butaca_id');
    salaSelect.innerHTML = '<option value="">Seleccione una sala</option>';
    butacaSelect.innerHTML = '<option value="">Seleccione una butaca</option>';

    if (sedeId) {
        fetch(`/get_salas.php?sede_id=${sedeId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error al obtener las salas:', data.error);
                    return;
                }
                data.forEach(sala => {
                    const option = document.createElement('option');
                    option.value = sala.id_sala;
                    option.textContent = sala.nombre_sala;
                    salaSelect.appendChild(option);
                });

                // Seleccionar automáticamente la sala de la función
                const funcionSelect = document.getElementById('funcion_id');
                const selectedOption = funcionSelect.options[funcionSelect.selectedIndex];
                const salaId = selectedOption.getAttribute('data-sala-id');
                Array.from(salaSelect.options).forEach(option => {
                    if (option.value == salaId) {
                        option.selected = true;
                    }
                });
                updateButacas(); // Actualizar las butacas
            })
            .catch(error => console.error('Error al obtener las salas:', error));
    }
}

function updateButacas() {
    const salaId = document.getElementById('sala_id').value;
    const butacaSelect = document.getElementById('butaca_id');
    butacaSelect.innerHTML = '<option value="">Seleccione una butaca</option>';

    if (salaId) {
        fetch(`/get_butacas.php?sala_id=${salaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error al obtener las butacas:', data.error);
                    return;
                }
                data.forEach(butaca => {
                    const option = document.createElement('option');
                    option.value = butaca.id_butaca;
                    option.textContent = `Fila ${butaca.fila}, Número ${butaca.numero_butaca}`;
                    butacaSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error al obtener las butacas:', error));
    }
}

// Llamar a updateSede al cargar la página si ya hay una función seleccionada
document.addEventListener('DOMContentLoaded', () => {
    const funcionSelect = document.getElementById('funcion_id');
    if (funcionSelect && funcionSelect.value) {
        updateSede();
    }
});
