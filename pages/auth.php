<?php
/**
 * pages/auth.php - Zentrale Authentifizierungs-Seite
 * Behandelt Login, Registrierung, Passwort vergessen
 */

// Auth-System einbinden
require_once __DIR__ . '/../includes/auth.php';

$action = $_GET['action'] ?? 'login';
$message = $_GET['message'] ?? '';
$error = '';
$success = '';

// Bereits eingeloggte Benutzer weiterleiten
if (isLoggedIn() && $action !== 'logout') {
    header('Location: ?page=dashboard');
    exit;
}

// ===== LOGOUT =====
if ($action === 'logout') {
    logoutUser();
    header('Location: ?page=auth&action=login&message=logged_out');
    exit;
}

// ===== LOGIN =====
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($usernameOrEmail) || empty($password)) {
        $error = 'Bitte füllen Sie alle Felder aus.';
    } else {
        $result = authenticateUser($usernameOrEmail, $password);
        
        if ($result['success']) {
            loginUser($result['user']['id']);
            
            // "Angemeldet bleiben" - Session verlängern
            if ($remember) {
                ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60); // 30 Tage
                session_set_cookie_params(30 * 24 * 60 * 60);
            }
            
            header('Location: ?page=dashboard&message=welcome');
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// ===== REGISTRIERUNG =====
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $terms = isset($_POST['terms']);
    
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'Bitte füllen Sie alle Pflichtfelder aus.';
    } elseif (!validateEmail($email)) {
        $error = 'Ungültige E-Mail-Adresse.';
    } elseif (strlen($password) < 6) {
        $error = 'Das Passwort muss mindestens 6 Zeichen lang sein.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } elseif (!$terms) {
        $error = 'Bitte akzeptieren Sie die Nutzungsbedingungen.';
    } else {
        $result = registerUser($username, $email, $password, $name);
        
        if ($result['success']) {
            $success = 'Registrierung erfolgreich! Sie können sich jetzt anmelden.';
            $action = 'login'; // Zur Login-Form wechseln
        } else {
            $error = $result['error'];
        }
    }
}

// ===== PASSWORT VERGESSEN =====
if ($action === 'forgot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Bitte geben Sie Ihre E-Mail-Adresse ein.';
    } elseif (!validateEmail($email)) {
        $error = 'Ungültige E-Mail-Adresse.';
    } else {
        $result = generatePasswordResetToken($email);
        
        if ($result['success']) {
            $success = 'Falls ein Account mit dieser E-Mail existiert, wurde ein Reset-Link gesendet. (Demo: Token = ' . $result['token'] . ')';
        } else {
            // Aus Sicherheitsgründen immer Erfolg anzeigen
            $success = 'Falls ein Account mit dieser E-Mail existiert, wurde ein Reset-Link gesendet.';
        }
    }
}

// ===== PASSWORT ZURÜCKSETZEN =====
if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($token) || empty($password) || empty($confirmPassword)) {
        $error = 'Bitte füllen Sie alle Felder aus.';
    } elseif (strlen($password) < 6) {
        $error = 'Das Passwort muss mindestens 6 Zeichen lang sein.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } else {
        $result = resetPasswordWithToken($token, $password);
        
        if ($result['success']) {
            $success = 'Passwort erfolgreich zurückgesetzt! Sie können sich jetzt anmelden.';
            $action = 'login';
        } else {
            $error = $result['error'];
        }
    }
}

// Nachrichten aus URL-Parametern
switch ($message) {
    case 'logged_out':
        $success = 'Sie wurden erfolgreich abgemeldet.';
        break;
    case 'session_timeout':
        $error = 'Ihre Session ist abgelaufen. Bitte melden Sie sich erneut an.';
        break;
    case 'access_denied':
        $error = 'Zugriff verweigert. Bitte melden Sie sich an.';
        break;
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($action) ?> - LinkBuilder Pro</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }
        .auth-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        .auth-header {
            margin-bottom: 30px;
        }
        .auth-header h1 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }
        .auth-header p {
            color: #666;
            margin: 0;
            font-size: 16px;
        }
        .auth-tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
        }
        .auth-tab {
            flex: 1;
            padding: 15px;
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        .auth-tab.active {
            color: #4dabf7;
            border-bottom-color: #4dabf7;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
            background: #fafbfc;
        }
        .form-control:focus {
            outline: none;
            border-color: #4dabf7;
            box-shadow: 0 0 0 3px rgba(77, 171, 247, 0.1);
            background: white;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #4dabf7, #339af0);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn:hover {
            background: linear-gradient(135deg, #339af0, #228be6);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(77, 171, 247, 0.4);
        }
        .btn:active {
            transform: translateY(0);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .alert-danger {
            background: #ffe8e8;
            color: #d63031;
            border: 1px solid #fab1a0;
        }
        .alert-success {
            background: #e8f5e8;
            color: #00b894;
            border: 1px solid #81ecec;
        }
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #4dabf7;
        }
        .form-check label {
            font-size: 14px;
            color: #666;
            margin: 0;
        }
        .auth-links {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }
        .auth-link {
            color: #4dabf7;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        .auth-link:hover {
            color: #339af0;
            text-decoration: underline;
        }
        .demo-info {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 14px;
            color: #495057;
            border-left: 4px solid #4dabf7;
        }
        .hidden {
            display: none;
        }
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1><i class="fas fa-link"></i> LinkBuilder Pro</h1>
            <p id="auth-subtitle">
                <?php if ($action === 'login'): ?>
                    Melden Sie sich an, um fortzufahren
                <?php elseif ($action === 'register'): ?>
                    Erstellen Sie Ihr kostenloses Konto
                <?php elseif ($action === 'forgot'): ?>
                    Passwort zurücksetzen
                <?php elseif ($action === 'reset'): ?>
                    Neues Passwort festlegen
                <?php endif; ?>
            </p>
        </div>

        <!-- Tab Navigation -->
        <?php if ($action !== 'reset'): ?>
        <div class="auth-tabs">
            <button class="auth-tab <?= $action === 'login' ? 'active' : '' ?>" onclick="switchTab('login')">
                Anmelden
            </button>
            <button class="auth-tab <?= $action === 'register' ? 'active' : '' ?>" onclick="switchTab('register')">
                Registrieren
            </button>
        </div>
        <?php endif; ?>

        <!-- Nachrichten -->
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- LOGIN FORM -->
        <form id="loginForm" method="post" action="?page=auth&action=login" class="<?= $action !== 'login' ? 'hidden' : '' ?>">
            <div class="form-group">
                <label for="username" class="form-label">Benutzername oder E-Mail</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control" 
                    placeholder="admin oder info@seogoal.de"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Passwort</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Ihr Passwort"
                    required
                >
            </div>

            <div class="form-check">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Angemeldet bleiben</label>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i>
                Anmelden
            </button>

            <div class="auth-links">
                <a href="#" onclick="switchTab('forgot')" class="auth-link">Passwort vergessen?</a>
            </div>
        </form>

        <!-- REGISTER FORM -->
        <form id="registerForm" method="post" action="?page=auth&action=register" class="<?= $action !== 'register' ? 'hidden' : '' ?>">
            <div class="form-group">
                <label for="reg_name" class="form-label">Vollständiger Name</label>
                <input 
                    type="text" 
                    id="reg_name" 
                    name="name" 
                    class="form-control" 
                    placeholder="Max Mustermann"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="reg_username" class="form-label">Benutzername *</label>
                <input 
                    type="text" 
                    id="reg_username" 
                    name="username" 
                    class="form-control" 
                    placeholder="benutzername"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="reg_email" class="form-label">E-Mail-Adresse *</label>
                <input 
                    type="email" 
                    id="reg_email" 
                    name="email" 
                    class="form-control" 
                    placeholder="ihre@email.de"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="reg_password" class="form-label">Passwort *</label>
                <input 
                    type="password" 
                    id="reg_password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Mindestens 6 Zeichen"
                    minlength="6"
                    required
                    oninput="checkPasswordStrength(this.value)"
                >
                <div id="password-strength" class="password-strength"></div>
            </div>
            
            <div class="form-group">
                <label for="reg_confirm_password" class="form-label">Passwort bestätigen *</label>
                <input 
                    type="password" 
                    id="reg_confirm_password" 
                    name="confirm_password" 
                    class="form-control" 
                    placeholder="Passwort wiederholen"
                    required
                >
            </div>

            <div class="form-check">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">Ich akzeptiere die Nutzungsbedingungen</label>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-user-plus"></i>
                Konto erstellen
            </button>
        </form>

        <!-- FORGOT PASSWORD FORM -->
        <form id="forgotForm" method="post" action="?page=auth&action=forgot" class="<?= $action !== 'forgot' ? 'hidden' : '' ?>">
            <div class="form-group">
                <label for="forgot_email" class="form-label">E-Mail-Adresse</label>
                <input 
                    type="email" 
                    id="forgot_email" 
                    name="email" 
                    class="form-control" 
                    placeholder="ihre@email.de"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-paper-plane"></i>
                Reset-Link senden
            </button>

            <div class="auth-links">
                <a href="#" onclick="switchTab('login')" class="auth-link">Zurück zur Anmeldung</a>
            </div>
        </form>

        <!-- RESET PASSWORD FORM -->
        <?php if ($action === 'reset'): ?>
        <form method="post" action="?page=auth&action=reset">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
            
            <div class="form-group">
                <label for="reset_password" class="form-label">Neues Passwort</label>
                <input 
                    type="password" 
                    id="reset_password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Neues Passwort"
                    minlength="6"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="reset_confirm_password" class="form-label">Passwort bestätigen</label>
                <input 
                    type="password" 
                    id="reset_confirm_password" 
                    name="confirm_password" 
                    class="form-control" 
                    placeholder="Passwort wiederholen"
                    required
                >
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-key"></i>
                Passwort zurücksetzen
            </button>
        </form>
        <?php endif; ?>

        <!-- Demo-Informationen -->
        <div class="demo-info">
            <strong>Demo-Zugänge:</strong><br>
            <strong>Admin:</strong> <code>admin</code> (Ihr Admin-Passwort)<br>
            <strong>Demo:</strong> <code>demo-user</code> / <code>demo123</code><br><br>
            <strong>Passwort vergessen?</strong> Verwenden Sie <code>info@seogoal.de</code> oder <code>demo@linkbuilder.pro</code>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Alle Formulare verstecken
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('registerForm').classList.add('hidden');
            document.getElementById('forgotForm').classList.add('hidden');
            
            // Alle Tabs deaktivieren
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            
            // Gewähltes Formular anzeigen
            if (tab === 'login') {
                document.getElementById('loginForm').classList.remove('hidden');
                document.querySelector('.auth-tab').classList.add('active');
                document.getElementById('auth-subtitle').textContent = 'Melden Sie sich an, um fortzufahren';
                window.history.replaceState({}, '', '?page=auth&action=login');
            } else if (tab === 'register') {
                document.getElementById('registerForm').classList.remove('hidden');
                document.querySelectorAll('.auth-tab')[1].classList.add('active');
                document.getElementById('auth-subtitle').textContent = 'Erstellen Sie Ihr kostenloses Konto';
                window.history.replaceState({}, '', '?page=auth&action=register');
            } else if (tab === 'forgot') {
                document.getElementById('forgotForm').classList.remove('hidden');
                document.getElementById('auth-subtitle').textContent = 'Passwort zurücksetzen';
                window.history.replaceState({}, '', '?page=auth&action=forgot');
            }
        }

        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('password-strength');
            let strength = 0;
            let text = '';
            let className = '';

            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            switch (strength) {
                case 0:
                case 1:
                    text = 'Sehr schwach';
                    className = 'strength-weak';
                    break;
                case 2:
                case 3:
                    text = 'Mittel';
                    className = 'strength-medium';
                    break;
                case 4:
                case 5:
                    text = 'Stark';
                    className = 'strength-strong';
                    break;
            }

            strengthDiv.textContent = text;
            strengthDiv.className = 'password-strength ' + className;
        }

        // Passwort-Bestätigung prüfen
        document.getElementById('reg_confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('reg_password').value;
            if (this.value && this.value !== password) {
                this.setCustomValidity('Passwörter stimmen nicht überein');
            } else {
                this.setCustomValidity('');
            }
        });

        document.getElementById('reset_confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('reset_password').value;
            if (this.value && this.value !== password) {
                this.setCustomValidity('Passwörter stimmen nicht überein');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>