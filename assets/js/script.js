document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    handleUrlAlerts();
    initPasswordToggles();
    initPasswordStrengthMeter();
    initLoginValidation();
    initRegisterValidation();
});

function initThemeToggle() {
    const savedTheme = localStorage.getItem('author_theme') || 'light';
    applyTheme(savedTheme);

    const toggleBtn = document.getElementById('theme-toggle');
    if (!toggleBtn) return;

    toggleBtn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = current === 'light' ? 'dark' : 'light';
        localStorage.setItem('author_theme', next);
        applyTheme(next);
    });
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    const toggleBtn = document.getElementById('theme-toggle');
    if (toggleBtn) {
        toggleBtn.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    }
}

function handleUrlAlerts() {
    const alertBox = document.getElementById('alert-box');
    if (!alertBox) return;

    const params = new URLSearchParams(window.location.search);
    const error = params.get('error');
    const status = params.get('status');

    const errorMessages = {
        invalid: 'The email address or password you entered is incorrect.',
        empty_fields: 'Please enter a valid email address and password.',
        unauthorized: 'Please sign in first to access the author dashboard.',
        account_locked: 'Account temporarily locked for 15 minutes due to multiple failed sign-in attempts.',
        invalid_name: 'Full name must be between 3 and 100 characters.',
        invalid_pen_name: 'Pen name must not exceed 100 characters.',
        invalid_email: 'Please provide a valid email address.',
        bio_too_long: 'Biography must not exceed 400 characters.',
        short_password: 'Password must be at least 8 characters long.',
        password_mismatch: 'Password confirmation does not match.',
        email_exists: 'This email address is already registered. Please sign in or use another email.',
        csrf_invalid: 'Invalid security token. Please refresh the page and try again.',
        article_validation: 'Please enter a title (min. 4 chars), summary (min. 10 chars), and content (min. 20 chars).',
        article_not_found: 'Article not found or you do not have permission to modify it.',
        profile_validation: 'Please check your full name (3-100 chars) and biography (max. 400 chars).',
        server_error: 'An internal server error occurred. Please try again later.'
    };

    const statusMessages = {
        registered: 'Registration successful! Please sign in with your new account.',
        logged_out: 'You have been signed out successfully.',
        welcome: 'Welcome back to Author Studio.',
        article_saved: 'Article saved successfully.',
        status_updated: 'Article publication status updated.',
        article_deleted: 'Article deleted permanently.',
        profile_updated: 'Author profile updated successfully.'
    };

    if (error && errorMessages[error]) {
        alertBox.textContent = errorMessages[error];
        alertBox.className = 'alert alert-error';
        alertBox.hidden = false;
    } else if (status && statusMessages[status]) {
        alertBox.textContent = statusMessages[status];
        alertBox.className = 'alert alert-success';
        alertBox.hidden = false;
    }
}

function initPasswordToggles() {
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.textContent = isPassword ? 'Hide' : 'Show';
        });
    });
}

function initPasswordStrengthMeter() {
    const passwordInput = document.getElementById('password');
    const bar = document.getElementById('strength-bar');
    const label = document.getElementById('strength-label');
    if (!passwordInput || !bar || !label) return;

    passwordInput.addEventListener('input', () => {
        const val = passwordInput.value;
        if (!val) {
            bar.style.width = '0%';
            bar.style.backgroundColor = 'transparent';
            label.textContent = 'Password strength: Not entered';
            return;
        }

        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { width: '25%', color: '#dc2626', text: 'Weak' },
            { width: '50%', color: '#f59e0b', text: 'Fair' },
            { width: '75%', color: '#2563eb', text: 'Strong' },
            { width: '100%', color: '#16a34a', text: 'Very Strong' }
        ];

        const idx = Math.max(0, Math.min(levels.length - 1, score - 1));
        bar.style.width = levels[idx].width;
        bar.style.backgroundColor = levels[idx].color;
        label.textContent = `Password strength: ${levels[idx].text}`;
    });
}

function initLoginValidation() {
    const form = document.getElementById('login-form');
    if (!form) return;

    form.addEventListener('submit', (event) => {
        let isValid = true;
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        clearFieldError(emailInput, 'email-error');
        clearFieldError(passwordInput, 'password-error');

        if (!isValidEmail(emailInput.value.trim())) {
            setFieldError(emailInput, 'email-error', 'Please enter a valid email address.');
            isValid = false;
        }

        if (passwordInput.value.trim() === '') {
            setFieldError(passwordInput, 'password-error', 'Password is required.');
            isValid = false;
        }

        if (!isValid) {
            event.preventDefault();
        }
    });
}

function initRegisterValidation() {
    const form = document.getElementById('register-form');
    if (!form) return;

    form.addEventListener('submit', (event) => {
        let isValid = true;
        const nameInput = document.getElementById('full_name');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');

        clearFieldError(nameInput, 'full_name-error');
        clearFieldError(emailInput, 'email-error');
        clearFieldError(passwordInput, 'password-error');
        clearFieldError(confirmInput, 'confirm_password-error');

        const nameVal = nameInput.value.trim();
        if (nameVal.length < 3 || nameVal.length > 100) {
            setFieldError(nameInput, 'full_name-error', 'Full name must be between 3 and 100 characters.');
            isValid = false;
        }

        if (!isValidEmail(emailInput.value.trim())) {
            setFieldError(emailInput, 'email-error', 'Please enter a valid email address.');
            isValid = false;
        }

        if (passwordInput.value.length < 8) {
            setFieldError(passwordInput, 'password-error', 'Password must be at least 8 characters long.');
            isValid = false;
        }

        if (confirmInput.value !== passwordInput.value || confirmInput.value === '') {
            setFieldError(confirmInput, 'confirm_password-error', 'Password confirmation does not match.');
            isValid = false;
        }

        if (!isValid) {
            event.preventDefault();
        }
    });
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function setFieldError(inputEl, errorElId, message) {
    const errorEl = document.getElementById(errorElId);
    if (inputEl) inputEl.classList.add('input-invalid');
    if (errorEl) errorEl.textContent = message;
}

function clearFieldError(inputEl, errorElId) {
    const errorEl = document.getElementById(errorElId);
    if (inputEl) inputEl.classList.remove('input-invalid');
    if (errorEl) errorEl.textContent = '';
}
