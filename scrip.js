function showForm(formType) {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');

    if (formType === 'register') {
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
    } else {
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
    }
}

// Ensure form submission works
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('register-form-submit');
    if (registerForm) {
        registerForm.addEventListener('submit', function(event) {
            // Prevent default if you want to add client-side validation later
            // event.preventDefault();
            // Add your validation here if needed
            this.submit(); // Ensure form submits
        });
    }
});
