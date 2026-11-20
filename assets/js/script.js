document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    handleUrlAlerts();
    initPasswordToggles();
    initPasswordStrengthMeter();
    initLoginValidation();
    initRegisterValidation();
});

/**
 * Mengelola tema Terang/Gelap menggunakan localStorage
 */
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
        toggleBtn.textContent = theme === 'dark' ? 'Mode Terang' : 'Mode Gelap';
    }
}

/**
 * Menampilkan notifikasi berdasarkan parameter URL (?error=... atau ?status=...)
 */
function handleUrlAlerts() {
    const alertBox = document.getElementById('alert-box');
    if (!alertBox) return;

    const params = new URLSearchParams(window.location.search);
    const error = params.get('error');
    const status = params.get('status');

    const errorMessages = {
        invalid: 'Email atau kata sandi yang Anda masukkan salah.',
        empty_fields: 'Harap isi email dan kata sandi dengan benar.',
        unauthorized: 'Silakan masuk terlebih dahulu untuk mengakses dashboard.',
        account_locked: 'Akun terkunci sementara selama 15 menit karena terlalu banyak percobaan login gagal.',
        invalid_name: 'Nama lengkap harus terdiri dari 3 hingga 100 karakter.',
        invalid_pen_name: 'Nama pena maksimal 100 karakter.',
        invalid_email: 'Format alamat email tidak valid.',
        bio_too_long: 'Biografi maksimal 400 karakter.',
        short_password: 'Kata sandi minimal harus 8 karakter.',
        password_mismatch: 'Konfirmasi kata sandi tidak cocok.',
        email_exists: 'Alamat email ini sudah terdaftar. Silakan gunakan email lain atau masuk.',
        csrf_invalid: 'Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.',
        article_validation: 'Lengkapi judul (min. 4 karakter), ringkasan (min. 10 karakter), dan isi artikel (min. 20 karakter).',
        article_not_found: 'Artikel tidak ditemukan atau bukan milik akun Anda.',
        profile_validation: 'Periksa kembali nama lengkap (3-100 karakter) dan biografi (maks. 400 karakter).',
        server_error: 'Terjadi kesalahan pada server. Silakan coba lagi nanti.'
    };

    const statusMessages = {
        registered: 'Registrasi berhasil! Silakan masuk menggunakan akun baru Anda.',
        logged_out: 'Anda telah berhasil keluar dari sesi.',
        welcome: 'Selamat datang kembali di Author Studio.',
        article_saved: 'Artikel berhasil disimpan.',
        status_updated: 'Status publikasi artikel berhasil diperbarui.',
        article_deleted: 'Artikel berhasil dihapus.',
        profile_updated: 'Profil penulis berhasil diperbarui.'
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
            button.textContent = isPassword ? 'Sembunyikan' : 'Tampilkan';
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
            label.textContent = 'Kekuatan kata sandi: Belum diisi';
            return;
        }

        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { width: '25%', color: '#dc2626', text: 'Lemah' },
            { width: '50%', color: '#f59e0b', text: 'Cukup' },
            { width: '75%', color: '#2563eb', text: 'Kuat' },
            { width: '100%', color: '#16a34a', text: 'Sangat Kuat' }
        ];

        const idx = Math.max(0, Math.min(levels.length - 1, score - 1));
        bar.style.width = levels[idx].width;
        bar.style.backgroundColor = levels[idx].color;
        label.textContent = `Kekuatan kata sandi: ${levels[idx].text}`;
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
            setFieldError(emailInput, 'email-error', 'Masukkan alamat email yang valid.');
            isValid = false;
        }

        if (passwordInput.value.trim() === '') {
            setFieldError(passwordInput, 'password-error', 'Kata sandi wajib diisi.');
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
            setFieldError(nameInput, 'full_name-error', 'Nama lengkap harus antara 3 hingga 100 karakter.');
            isValid = false;
        }

        if (!isValidEmail(emailInput.value.trim())) {
            setFieldError(emailInput, 'email-error', 'Masukkan alamat email yang valid.');
            isValid = false;
        }

        if (passwordInput.value.length < 8) {
            setFieldError(passwordInput, 'password-error', 'Kata sandi minimal harus 8 karakter.');
            isValid = false;
        }

        if (confirmInput.value !== passwordInput.value || confirmInput.value === '') {
            setFieldError(confirmInput, 'confirm_password-error', 'Konfirmasi kata sandi tidak cocok.');
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
