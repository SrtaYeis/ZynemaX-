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

