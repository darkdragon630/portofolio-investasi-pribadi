<?php
session_start();
require_once "../config/koneksi.php";

// Regenerate session ID untuk keamanan
session_regenerate_id(true);

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

/* =========================
   CSRF TOKEN GENERATION & VALIDATION
========================= */
function generate_csrf_token() {
    return bin2hex(random_bytes(32));
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}

/* =========================
   PESAN ERROR / SUCCESS
========================= */
$error = "";
$success = "";

if (isset($_SESSION['reg_success'])) {
    $success = $_SESSION['reg_success'];
    unset($_SESSION['reg_success']);
}

if (isset($_SESSION['temp_error'])) {
    $error = $_SESSION['temp_error'];
    unset($_SESSION['temp_error']);
}

/* =========================
   FUNGSIONAL VALIDASI
========================= */
function validate_username($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validate_password($password) {
    return strlen($password) >= 8 &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[0-9]/', $password);
}

function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function log_security_event($event, $details = '') {
    $log_entry = date('Y-m-d H:i:s') . " | " . $_SERVER['REMOTE_ADDR'] . " | $event | $details" . PHP_EOL;
    error_log($log_entry, 3, "../logs/security.log");
}

/* =========================
   PROSES LOGIN
========================= */
if (isset($_POST['login'])) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            $error = "Token CSRF tidak valid.";
            log_security_event("CSRF_INVALID", "Login attempt");
            $_SESSION['csrf_token'] = generate_csrf_token();
        } else {
            $_SESSION['csrf_token'] = generate_csrf_token();

            $username = sanitize_input($_POST['username']);
            $password = $_POST['password'];

            if (empty($username) || empty($password)) {
                $error = "Username dan password tidak boleh kosong.";
            } elseif (!validate_username($username)) {
                $error = "Username hanya boleh huruf, angka, dan underscore (3-20 karakter).";
            } else {
                $stmt = $koneksi->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user) {
                    $current_time = new DateTime();

                    if ($user['locked_until'] && $current_time < new DateTime($user['locked_until'])) {
                        $error = "Akun terkunci sampai " . date('H:i:s d-m-Y', strtotime($user['locked_until'])) . ".";
                        log_security_event("LOGIN_LOCKED", "User: $username");
                    } elseif (password_verify($password, $user['password'])) {
                        $stmt = $koneksi->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);

                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['login_time'] = time();

                        log_security_event("LOGIN_SUCCESS", "User: $username");
                        header("Location: ../dashboard.php");
                        exit;
                    } else {
                        $failed_attempts = $user['failed_attempts'] + 1;
                        $locked_until = null;
                        $max_attempts = 5;

                        if ($failed_attempts >= $max_attempts) {
                            $locked_until = (new DateTime())->modify('+15 minutes')->format('Y-m-d H:i:s');
                            $error = "Akun terkunci 15 menit karena $max_attempts kali gagal login.";
                            $failed_attempts = 0;
                            log_security_event("ACCOUNT_LOCKED", "User: $username");
                        } else {
                            $remaining = $max_attempts - $failed_attempts;
                            $error = "Username atau password salah. Sisa percobaan: $remaining.";
                            log_security_event("LOGIN_FAILED", "User: $username, Attempts: $failed_attempts");
                        }

                        $stmt = $koneksi->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                        $stmt->execute([$failed_attempts, $locked_until, $user['id']]);
                    }
                } else {
                    $error = "Username atau password salah.";
                    log_security_event("LOGIN_INVALID_USER", "Username: $username");
                }
            }
        }
    } catch (Exception $e) {
        $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        log_security_event("LOGIN_ERROR", $e->getMessage());
    }
}

/* =========================
   PROSES REGISTER
========================= */
if (isset($_POST['register'])) {
    try {
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            $error = "Token CSRF tidak valid.";
            log_security_event("CSRF_INVALID", "Register attempt");
            $_SESSION['csrf_token'] = generate_csrf_token();
        } else {
            $_SESSION['csrf_token'] = generate_csrf_token();

            $username = sanitize_input($_POST['reg_username']);
            $email = sanitize_input($_POST['reg_email']);
            $password = $_POST['reg_password'];
            $confirm_password = $_POST['confirm_password'];

            if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
                $error = "Semua field harus diisi.";
            } elseif (!validate_username($username)) {
                $error = "Username hanya boleh huruf, angka, dan underscore (3-20 karakter).";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Format email tidak valid.";
            } elseif (!validate_password($password)) {
                $error = "Password minimal 8 karakter dengan huruf besar, kecil, dan angka.";
            } elseif ($password !== $confirm_password) {
                $error = "Password dan konfirmasi tidak sama.";
            } else {
                $stmt = $koneksi->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$username, $email]);
                $existing_user = $stmt->fetch();

                if ($existing_user) {
                    $error = ($existing_user['username'] === $username) ? "Username sudah digunakan." : "Email sudah terdaftar.";
                    log_security_event("REGISTER_DUPLICATE", "Username: $username, Email: $email");
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $koneksi->prepare("INSERT INTO users (username, email, password, created_at, failed_attempts, locked_until) VALUES (?, ?, ?, NOW(), 0, NULL)");
                    if ($stmt->execute([$username, $email, $hashed_password])) {
                        $_SESSION['reg_success'] = "Akun berhasil dibuat! Silakan login.";
                        log_security_event("REGISTER_SUCCESS", "User: $username");
                        header("Location: auth.php");
                        exit;
                    } else {
                        $error = "Gagal membuat akun. Coba lagi.";
                        log_security_event("REGISTER_ERROR", "Database error for user: $username");
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = "Terjadi kesalahan sistem.";
        log_security_event("REGISTER_ERROR", $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login & Register - SAAZ Investment</title>
<link rel="stylesheet" href="../css/auth.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<!-- Background Animation -->
<div class="auth-background">
    <div class="gradient-orb orb-1"></div>
    <div class="gradient-orb orb-2"></div>
    <div class="gradient-orb orb-3"></div>
</div>

<div class="auth-container">
    <!-- Back Button (optional) -->
    <!-- <div class="auth-header-top">
        <a href="../index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            <span>Kembali</span>
        </a>
    </div> -->

    <div class="auth-card">
        <!-- Logo Section -->
        <div class="auth-logo">
            <div class="logo-icon">
                💼
                <div class="logo-pulse"></div>
            </div>
            <h1>SAAZ Investment</h1>
            <p>Kelola portofolio investasi Anda dengan mudah</p>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" data-tab="login">
                <i class="fas fa-sign-in-alt"></i>
                <span>Masuk</span>
            </button>
            <button class="tab-btn" data-tab="register">
                <i class="fas fa-user-plus"></i>
                <span>Daftar</span>
            </button>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
                <button class="message-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
                <button class="message-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <div class="tab-content active" id="login">
            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        Username
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input"
                           placeholder="Masukkan username"
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" 
                           pattern="[a-zA-Z0-9_]{3,20}" 
                           required>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input"
                               placeholder="Masukkan password"
                               required>
                        <button type="button" class="toggle-password" data-target="password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" id="remember">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Ingat saya</span>
                    </label>
                    <a href="#" class="forgot-link">Lupa password?</a>
                </div>

                <button type="submit" name="login" class="submit-btn">
                    Masuk
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="form-footer">
                <p>Belum punya akun? <a href="#" onclick="switchTab('register'); return false;">Daftar sekarang</a></p>
            </div>
        </div>

        <!-- Register Form -->
        <div class="tab-content" id="register">
            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label for="reg_username">
                        <i class="fas fa-user"></i>
                        Username
                    </label>
                    <input type="text" 
                           id="reg_username" 
                           name="reg_username" 
                           class="form-input"
                           placeholder="Pilih username (3-20 karakter)"
                           value="<?= isset($_POST['reg_username']) ? htmlspecialchars($_POST['reg_username']) : '' ?>" 
                           pattern="[a-zA-Z0-9_]{3,20}" 
                           required>
                    <span class="form-hint">Hanya huruf, angka, dan underscore</span>
                </div>

                <div class="form-group">
                    <label for="reg_email">
                        <i class="fas fa-envelope"></i>
                        Email
                    </label>
                    <input type="email" 
                           id="reg_email" 
                           name="reg_email" 
                           class="form-input"
                           placeholder="alamat@email.com"
                           value="<?= isset($_POST['reg_email']) ? htmlspecialchars($_POST['reg_email']) : '' ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="reg_password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="reg_password" 
                               name="reg_password" 
                               class="form-input"
                               placeholder="Buat password yang kuat"
                               minlength="8" 
                               required>
                        <button type="button" class="toggle-password" data-target="reg_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-text" id="strengthText"></div>
                </div>

                <div class="password-requirements">
                    <p><i class="fas fa-info-circle"></i> Password harus mengandung:</p>
                    <ul id="passwordReqs">
                        <li data-req="length"><i class="fas fa-circle"></i> Minimal 8 karakter</li>
                        <li data-req="lowercase"><i class="fas fa-circle"></i> Huruf kecil (a-z)</li>
                        <li data-req="uppercase"><i class="fas fa-circle"></i> Huruf besar (A-Z)</li>
                        <li data-req="number"><i class="fas fa-circle"></i> Angka (0-9)</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-lock"></i>
                        Konfirmasi Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input"
                               placeholder="Ketik ulang password"
                               minlength="8" 
                               required>
                        <button type="button" class="toggle-password" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="register" class="submit-btn">
                    Buat Akun
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="form-footer">
                <p>Sudah punya akun? <a href="#" onclick="switchTab('login'); return false;">Masuk di sini</a></p>
            </div>
        </div>

        <!-- Footer -->
        <div class="auth-footer">
            <p>&copy; <?= date('Y') ?> SAAZ Investment Manager. All rights reserved.</p>
            <div class="footer-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Help Center</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            switchTab(tabName);
        });
    });

    // Password visibility toggle
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Password strength checker
    const regPassword = document.getElementById('reg_password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const passwordReqs = document.getElementById('passwordReqs');

    function checkPasswordStrength(password) {
        let strength = 0;
        const requirements = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
        };

        // Update requirement indicators
        Object.keys(requirements).forEach(req => {
            const li = passwordReqs.querySelector(`[data-req="${req}"]`);
            if (li) {
                if (requirements[req]) {
                    li.classList.add('met');
                    li.querySelector('i').classList.remove('fa-circle');
                    li.querySelector('i').classList.add('fa-check-circle');
                } else {
                    li.classList.remove('met');
                    li.querySelector('i').classList.remove('fa-check-circle');
                    li.querySelector('i').classList.add('fa-circle');
                }
            }
        });

        // Calculate strength
        Object.values(requirements).forEach(met => {
            if (met) strength++;
        });

        return strength;
    }

    if (regPassword) {
        regPassword.addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            
            strengthBar.className = 'strength-bar';
            strengthText.className = 'strength-text';
            
            if (this.value.length === 0) {
                strengthBar.style.width = '0%';
                strengthText.textContent = '';
            } else if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
                strengthText.classList.add('weak');
                strengthText.textContent = 'Password Lemah';
            } else if (strength === 3) {
                strengthBar.classList.add('strength-fair');
                strengthText.classList.add('fair');
                strengthText.textContent = 'Password Sedang';
            } else if (strength === 4) {
                strengthBar.classList.add('strength-good');
                strengthText.classList.add('good');
                strengthText.textContent = 'Password Bagus';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.classList.add('strong');
                strengthText.textContent = 'Password Sangat Kuat';
            }
        });
    }

    // Auto-clear messages
    setTimeout(() => {
        document.querySelectorAll('.message').forEach(msg => {
            msg.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => msg.remove(), 300);
        });
    }, 5000);
});

function switchTab(tabName) {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        if (btn.getAttribute('data-tab') === tabName) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    tabContents.forEach(content => {
        if (content.id === tabName) {
            content.classList.add('active');
        } else {
            content.classList.remove('active');
        }
    });
}
</script>
</body>
</html>