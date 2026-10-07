document.addEventListener('DOMContentLoaded', () => {
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    if (!email || !password) {
        return;
    }
    document.querySelectorAll('[data-fill-email]').forEach((button) => {
        button.addEventListener('click', () => {
            email.value = button.getAttribute('data-fill-email') || '';
            password.value = button.getAttribute('data-fill-password') || '';
            email.dispatchEvent(new Event('input', { bubbles: true }));
            password.dispatchEvent(new Event('input', { bubbles: true }));
            password.focus();
        });
    });
});
