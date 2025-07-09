<?php
/**
 * LinkBuilder Pro - Index.php
 * VOLLSTÄNDIGE VERSION mit Passwort vergessen Funktion + Admin-Benutzer-Verwaltung
 */

// Session starten
session_start();

// Functions.php einbinden (bestehende Funktionen)
require_once __DIR__ . '/includes/functions.php';

// E-Mail-System einbinden
require_once __DIR__ . '/includes/email-functions.php';

// VERBESSERTER LOGOUT
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Debug-Ausgabe (können Sie später entfernen)
    error_log("Logout ausgeführt für Benutzer: " . ($_SESSION['user_id'] ?? 'unbekannt'));
    
    // Session komplett zurücksetzen
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = array();
        session_unset();
        session_destroy();
    }
    
    // Alle möglichen Session-Cookies löschen
    $cookieParams = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, 
        $cookieParams['path'], 
        $cookieParams['domain'], 
        $cookieParams['secure'], 
        $cookieParams['httponly']
    );
    
    // Zusätzliche Cookie-Bereinigung
    setcookie('PHPSESSID', '', time() - 3600, '/');
    
    // Cache-Header setzen um Zurück-Button zu verhindern
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Zur Login-Seite mit eindeutiger Message
    header('Location: ?page=simple_login&message=logged_out&t=' . time());
    exit;
}

// ERWEITERTE LOGIN-SEITE MIT PASSWORT VERGESSEN
if (isset($_GET['page']) && $_GET['page'] === 'simple_login') {
    $action = $_GET['action'] ?? 'login';
    
    // === PASSWORT VERGESSEN VERARBEITUNG ===
    if ($action === 'forgot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Bitte geben Sie Ihre E-Mail-Adresse ein.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse.';
        } else {
            $users = loadData('users.json');
            $foundUser = null;
            
            // Benutzer mit dieser E-Mail suchen
            foreach ($users as $userId => $user) {
                if (($user['email'] ?? '') === $email) {
                    $foundUser = $user;
                    $foundUserId = $userId;
                    break;
                }
            }
            
            if ($foundUser) {
                // Reset-Token generieren
                $resetToken = bin2hex(random_bytes(32));
                $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Token in Benutzerdaten speichern
                $users[$foundUserId]['reset_token'] = $resetToken;
                $users[$foundUserId]['reset_expires'] = $resetExpires;
                
                if (saveData('users.json', $users)) {
                    $success = 'Falls ein Account mit dieser E-Mail existiert, wurde ein Reset-Link gesendet.';
                    
                    // E-Mail senden
                    $emailSent = sendPasswordResetEmail($email, $resetToken, $foundUser['name'] ?? '');
                    
                    if (!$emailSent) {
                        error_log("Failed to send password reset email to: $email");
                        // Für den Benutzer trotzdem Erfolg anzeigen (Sicherheit)
                    }
                } else {
                    $error = 'Fehler beim Generieren des Reset-Links.';
                }
            } else {
                // Aus Sicherheitsgründen immer Erfolg anzeigen
                $success = 'Falls ein Account mit dieser E-Mail existiert, wurde ein Reset-Link gesendet.';
            }
        }
    }
    
    // === PASSWORT ZURÜCKSETZEN VERARBEITUNG ===
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
            $users = loadData('users.json');
            $foundUser = null;
            $foundUserId = null;
            
            // Token suchen
            foreach ($users as $userId => $user) {
                if (($user['reset_token'] ?? '') === $token) {
                    $foundUser = $user;
                    $foundUserId = $userId;
                    break;
                }
            }
            
            if (!$foundUser) {
                $error = 'Ungültiger oder abgelaufener Reset-Link.';
            } elseif (strtotime($foundUser['reset_expires']) < time()) {
                $error = 'Reset-Link ist abgelaufen. Bitte fordern Sie einen neuen an.';
            } else {
                // Neues Passwort setzen
                $users[$foundUserId]['password'] = password_hash($password, PASSWORD_DEFAULT);
                $users[$foundUserId]['reset_token'] = null;
                $users[$foundUserId]['reset_expires'] = null;
                $users[$foundUserId]['updated_at'] = date('Y-m-d H:i:s');
                
                if (saveData('users.json', $users)) {
                    $success = 'Passwort erfolgreich zurückgesetzt! Sie können sich jetzt anmelden.';
                    $action = 'login'; // Zur Login-Form wechseln
                } else {
                    $error = 'Fehler beim Speichern des neuen Passworts.';
                }
            }
        }
    }
    
    // === LOGIN VERARBEITUNG ===
    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!empty($username) && !empty($password)) {
            $users = loadData('users.json');
            $foundUser = null;
            $foundUserId = null;
            
            // Benutzer suchen
            foreach ($users as $userId => $user) {
                if (($user['username'] ?? '') === $username || ($user['email'] ?? '') === $username) {
                    $foundUser = $user;
                    $foundUserId = $userId;
                    break;
                }
            }
            
            if ($foundUser) {
                // Passwort prüfen (nur noch gehashte Passwörter)
                $passwordValid = password_verify($password, $foundUser['password']);
                
                if ($passwordValid && ($foundUser['status'] ?? 'active') === 'active') {
                    // Last Login aktualisieren
                    $users[$foundUserId]['last_login'] = date('Y-m-d H:i:s');
                    saveData('users.json', $users);
                    
                    // Login erfolgreich
                    $_SESSION['user_id'] = $foundUser['id'];
                    $_SESSION['user_name'] = $foundUser['username'] ?? 'Benutzer';
                    $_SESSION['user_email'] = $foundUser['email'] ?? '';
                    $_SESSION['user_role'] = $foundUser['role'] ?? 'user';
                    header('Location: ?page=dashboard&message=welcome');
                    exit;
                } elseif ($foundUser && ($foundUser['status'] ?? 'active') === 'inactive') {
                    $loginError = 'Ihr Account wurde deaktiviert.';
                } else {
                    $loginError = 'Ungültiger Benutzername oder Passwort.';
                }
            } else {
                $loginError = 'Ungültiger Benutzername oder Passwort.';
            }
        } else {
            $loginError = 'Bitte füllen Sie alle Felder aus.';
        }
    }
    
    // ERWEITERTE LOGIN-SEITE MIT ALLEN FUNKTIONEN
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= ucfirst($action) ?> - LinkBuilder Pro</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body {
                margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh; display: flex; align-items: center; justify-content: center;
                padding: 20px; box-sizing: border-box;
            }
            .auth-container {
                background: white; padding: 40px; border-radius: 15px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 450px; text-align: center;
            }
            .auth-header h1 { color: #333; margin: 0 0 10px 0; font-size: 28px; font-weight: 700; }
            .auth-header p { color: #666; margin: 0 0 30px 0; font-size: 16px; }
            .form-group { margin-bottom: 20px; text-align: left; }
            .form-label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; font-size: 14px; }
            .form-control {
                width: 100%; padding: 15px; border: 2px solid #e1e5e9; border-radius: 8px;
                font-size: 16px; transition: border-color 0.3s, box-shadow 0.3s; box-sizing: border-box; background: #fafbfc;
            }
            .form-control:focus {
                outline: none; border-color: #4dabf7; box-shadow: 0 0 0 3px rgba(77, 171, 247, 0.1); background: white;
            }
            .btn {
                width: 100%; padding: 15px; background: linear-gradient(135deg, #4dabf7, #339af0);
                color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600;
                cursor: pointer; transition: all 0.3s; margin-top: 10px;
            }
            .btn:hover {
                background: linear-gradient(135deg, #339af0, #228be6);
                transform: translateY(-2px); box-shadow: 0 5px 15px rgba(77, 171, 247, 0.4);
            }
            .alert {
                padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.5;
            }
            .alert-danger { background: #ffe8e8; color: #d63031; border: 1px solid #fab1a0; }
            .alert-success { background: #e8f5e8; color: #00b894; border: 1px solid #81ecec; }
            .auth-links { margin-top: 25px; padding-top: 25px; border-top: 1px solid #eee; }
            .auth-link { color: #4dabf7; text-decoration: none; font-size: 14px; transition: color 0.3s; }
            .auth-link:hover { color: #339af0; text-decoration: underline; }
            .back-link { margin-bottom: 20px; }
            .back-link a { color: #4dabf7; text-decoration: none; font-size: 14px; }
            .back-link a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="auth-container">
            <div class="auth-header">
                <h1><i class="fas fa-link"></i> LinkBuilder Pro</h1>
                <p>
                    <?php if ($action === 'login'): ?>
                        Melden Sie sich an, um fortzufahren
                    <?php elseif ($action === 'forgot'): ?>
                        Passwort zurücksetzen
                    <?php elseif ($action === 'reset'): ?>
                        Neues Passwort festlegen
                    <?php endif; ?>
                </p>
            </div>

            <!-- Zurück-Link für Passwort-Aktionen -->
            <?php if ($action === 'forgot'): ?>
                <div class="back-link">
                    <a href="?page=simple_login&action=login">← Zurück zur Anmeldung</a>
                </div>
            <?php endif; ?>

            <!-- Nachrichten -->
            <?php if (isset($_GET['message']) && $_GET['message'] === 'logged_out'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Sie wurden erfolgreich abgemeldet.
                </div>
            <?php endif; ?>
            
            <?php if (isset($loginError)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($loginError) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <?php if ($action === 'login'): ?>
                <form method="post" action="?page=simple_login&action=login">
                    <div class="form-group">
                        <label for="username" class="form-label">Benutzername oder E-Mail</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Passwort</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Anmelden
                    </button>

                    <div class="auth-links">
                        <a href="?page=simple_login&action=forgot" class="auth-link">Passwort vergessen?</a>
                    </div>
                </form>
            <?php endif; ?>

            <!-- PASSWORT VERGESSEN FORM -->
            <?php if ($action === 'forgot'): ?>
                <form method="post" action="?page=simple_login&action=forgot">
                    <div class="form-group">
                        <label for="email" class="form-label">E-Mail-Adresse</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="ihre@email.de"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Reset-Link senden
                    </button>
                </form>
            <?php endif; ?>

            <!-- PASSWORT ZURÜCKSETZEN FORM -->
            <?php if ($action === 'reset'): ?>
                <form method="post" action="?page=simple_login&action=reset">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Neues Passwort</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Neues Passwort" minlength="6" required>
                        <small style="color: #666; font-size: 12px;">Mindestens 6 Zeichen</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Passwort bestätigen</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               placeholder="Passwort wiederholen" required>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-key"></i> Passwort zurücksetzen
                    </button>
                </form>
            <?php endif; ?>

        </div>

        <script>
            // Passwort-Bestätigung prüfen
            document.getElementById('confirm_password')?.addEventListener('input', function() {
                const password = document.getElementById('password').value;
                if (this.value && this.value !== password) {
                    this.setCustomValidity('Passwörter stimmen nicht überein');
                } else {
                    this.setCustomValidity('');
                }
            });

            document.getElementById('password')?.addEventListener('input', function() {
                const confirmPassword = document.getElementById('confirm_password');
                if (confirmPassword && confirmPassword.value) {
                    if (this.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwörter stimmen nicht überein');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// LOGIN-PRÜFUNG (einfach)
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ?page=simple_login');
    exit;
}

// Aktuellen Benutzer laden
$currentUserId = $_SESSION['user_id'];
$users = loadData('users.json');
$currentUser = $users[$currentUserId] ?? null;

if (!$currentUser) {
    // Benutzer existiert nicht mehr - Session löschen
    session_destroy();
    header('Location: ?page=simple_login');
    exit;
}

// Benutzer-Status prüfen
if (($currentUser['status'] ?? 'active') === 'inactive') {
    session_destroy();
    header('Location: ?page=simple_login&message=account_disabled');
    exit;
}

// Ab hier: Benutzer ist eingeloggt
$currentUser = [
    'id' => $_SESSION['user_id'],
    'name' => $_SESSION['user_name'] ?? 'Benutzer',
    'email' => $_SESSION['user_email'] ?? '',
    'role' => $_SESSION['user_role'] ?? 'user'
];

// Aktuelle Seite ermitteln (Ihre bestehende Logik)
$page = 'dashboard';
if (isset($_GET['page']) && !empty($_GET['page'])) {
    $requestedPage = $_GET['page'];
    $cleanPage = preg_replace('/[^a-zA-Z0-9_-]/', '', $requestedPage);
    if (!empty($cleanPage)) {
        $page = $cleanPage;
    }
}

// ERWEITERTE Erlaubte Seiten (mit Admin-Verwaltung)
$allowedPages = array('dashboard', 'blogs', 'customers', 'links', 'topics', 'settings', 'profile', 'admin_users');

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

// Admin-Seiten nur für Admins
if ($page === 'admin_users' && $currentUser['role'] !== 'admin') {
    $page = 'dashboard';
}

$pageFile = __DIR__ . '/pages/' . $page . '.php';

if (!file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = __DIR__ . '/pages/dashboard.php';
}

$showEmergencyPage = !file_exists($pageFile);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page === 'dashboard' ? 'Dashboard' : ucfirst($page); ?> - LinkBuilder Pro</title>
    
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Emergency Page */
        .emergency-page {
            max-width: 800px; margin: 50px auto; padding: 20px;
            font-family: Arial, sans-serif; color: #333;
        }
        .emergency-page h1 { color: #333; margin-bottom: 20px; }
        .emergency-page .status { 
            background: #e8f5e8; padding: 15px; border-radius: 8px; 
            margin: 15px 0; border-left: 4px solid #10b981;
        }
        .emergency-page .error { 
            background: #ffe8e8; padding: 15px; border-radius: 8px; 
            margin: 15px 0; border-left: 4px solid #ef4444;
        }

        /* User Dropdown */
        .user-dropdown { position: relative; display: inline-block; }
        .user-button {
            background: none; border: none; color: #8b8fa3; cursor: pointer;
            padding: 8px 12px; border-radius: 6px; display: flex; align-items: center;
            gap: 8px; transition: all 0.2s ease; font-size: 14px;
        }
        .user-button:hover { background-color: #343852; color: #fff; }
        .user-dropdown-menu {
            position: absolute; top: calc(100% + 8px); right: 0;
            background-color: #2a2d3e; border: 1px solid #3a3d52; border-radius: 8px;
            min-width: 220px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            z-index: 1000; display: none; opacity: 0; transform: translateY(-10px);
            transition: all 0.2s ease;
        }
        .dropdown-item {
            display: flex; align-items: center; gap: 12px; padding: 10px 16px;
            color: #8b8fa3; text-decoration: none; transition: all 0.2s ease; font-size: 14px;
        }
        .dropdown-item:hover { background-color: #343852; color: #fff; }
        .dropdown-item.logout:hover { background-color: #472f2f; color: #ff6b6b; }
        .sidebar-header {
            display: flex; justify-content: space-between; align-items: center; padding: 20px;
        }
        .alert-success {
            background: #e8f5e8; color: #00b894; border: 1px solid #81ecec;
            padding: 12px; border-radius: 5px; margin: 20px;
        }
    </style>
</head>
<body>
    <?php if ($showEmergencyPage): ?>
        <div class="emergency-page">
            <h1>🔧 LinkBuilder Pro - Setup benötigt</h1>
            
            <div class="status">
                <h3>✅ System Status</h3>
                <p>✅ PHP funktioniert</p>
                <p>✅ functions.php geladen</p>
                <p>✅ Erweiteres Login-System mit Passwort-Reset aktiv</p>
                <p>✅ Benutzer eingeloggt: <?= htmlspecialchars($currentUser['name']) ?></p>
                <p>✅ Rolle: <?= htmlspecialchars($currentUser['role']) ?></p>
            </div>
            
            <div class="error">
                <h3>❌ Fehlende Dateien</h3>
                <p>Die Seitendateien fehlen. Erstellen Sie: pages/dashboard.php</p>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="?action=logout" style="background: #ef4444; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">
                    🔓 Abmelden (Test)
                </a>
            </div>
        </div>
        
    <?php else: ?>
        <?php if (isset($_GET['message']) && $_GET['message'] === 'welcome'): ?>
            <div class="alert-success">
                ✅ Willkommen zurück, <?= htmlspecialchars($currentUser['name']) ?>!
            </div>
        <?php endif; ?>
        
        <div class="app-container">
            <nav class="sidebar">
                <div class="sidebar-header">
                    <h2>LinkBuilder Pro</h2>
                    
                    <div class="user-dropdown">
                        <button class="user-button" onclick="toggleUserDropdown()">
                            <i class="fas fa-user-circle" style="font-size: 20px;"></i>
                            <span><?= htmlspecialchars($currentUser['name']) ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 12px;" id="dropdownChevron"></i>
                        </button>
                        
                        <div id="userDropdown" class="user-dropdown-menu">
                            <div style="padding: 16px; border-bottom: 1px solid #3a3d52; background-color: #343852; border-radius: 8px 8px 0 0;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #4dabf7, #2dd4bf); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                        <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: #fff; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($currentUser['name']) ?>
                                        </div>
                                        <div style="font-size: 12px; color: #8b8fa3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($currentUser['email']) ?>
                                        </div>
                                        <div style="font-size: 11px; color: #4dabf7; margin-top: 2px;">
                                            <?= $currentUser['role'] === 'admin' ? '👑 Administrator' : '👤 Benutzer' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="padding: 8px 0;">
                                <a href="?page=profile" class="dropdown-item">
                                    <i class="fas fa-user" style="width: 16px; text-align: center;"></i>
                                    <span>Mein Profil</span>
                                </a>
                                <a href="?page=dashboard" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt" style="width: 16px; text-align: center;"></i>
                                    <span>Dashboard</span>
                                </a>
                                <a href="?page=settings" class="dropdown-item">
                                    <i class="fas fa-cog" style="width: 16px; text-align: center;"></i>
                                    <span>Einstellungen</span>
                                </a>
                                <div style="height: 1px; background-color: #3a3d52; margin: 8px 16px;"></div>
                                <a href="?action=logout" class="dropdown-item logout">
                                    <i class="fas fa-sign-out-alt" style="width: 16px; text-align: center;"></i>
                                    <span>Abmelden</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <ul class="sidebar-menu">
                    <li<?php echo $page === 'dashboard' ? ' class="active"' : ''; ?>>
                        <a href="?page=dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    </li>
                    <li<?php echo $page === 'blogs' ? ' class="active"' : ''; ?>>
                        <a href="?page=blogs"><i class="fas fa-blog"></i> Blogs</a>
                    </li>
                    <li<?php echo $page === 'customers' ? ' class="active"' : ''; ?>>
                        <a href="?page=customers"><i class="fas fa-users"></i> Kunden</a>
                    </li>
                    <li<?php echo $page === 'links' ? ' class="active"' : ''; ?>>
                        <a href="?page=links"><i class="fas fa-link"></i> Links</a>
                    </li>
                    
                    <!-- ADMIN-BEREICH (nur für Admins sichtbar) -->
                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <li style="margin-top: 20px;">
                            <div style="padding: 8px 20px; color: #8b8fa3; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                Administration
                            </div>
                        </li>
                        <li<?php echo $page === 'admin_users' ? ' class="active"' : ''; ?>>
                            <a href="?page=admin_users"><i class="fas fa-users-cog"></i> Benutzer-Verwaltung</a>
                        </li>
                    <?php endif; ?>
                    
                    <li style="margin-top: 20px;<?php echo $page === 'settings' ? ' class="active"' : ''; ?>">
                        <a href="?page=settings"><i class="fas fa-cog"></i> Einstellungen</a>
                    </li>
                </ul>
            </nav>

            <main class="main-content">
                <div class="content-wrapper">
                    <?php
                    if (file_exists($pageFile)) {
                        ob_start();
                        try {
                            include $pageFile;
                        } catch (Exception $e) {
                            ob_end_clean();
                            echo '<div style="padding: 20px; background: #ffe8e8; border-radius: 5px; margin: 20px;">';
                            echo '<h2>❌ Fehler beim Laden der Seite</h2>';
                            echo '<p><strong>Seite:</strong> ' . htmlspecialchars($page) . '</p>';
                            echo '<p><strong>Fehler:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                            echo '<p><a href="?page=dashboard">Zurück zum Dashboard</a></p>';
                            echo '</div>';
                        }
                        if (ob_get_level() > 0) {
                            ob_end_flush();
                        }
                    } else {
                        echo '<div style="padding: 20px; background: #ffe8e8; border-radius: 5px; margin: 20px;">';
                        echo '<h2>❌ Seite nicht gefunden</h2>';
                        echo '<p><a href="?page=dashboard">Zurück zum Dashboard</a></p>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </main>
        </div>
    <?php endif; ?>

    <script>
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            const chevron = document.getElementById('dropdownChevron');
            
            if (!dropdown) return;
            
            if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                dropdown.style.display = 'block';
                setTimeout(() => {
                    dropdown.style.opacity = '1';
                    dropdown.style.transform = 'translateY(0)';
                }, 10);
                if (chevron) chevron.style.transform = 'rotate(180deg)';
            } else {
                dropdown.style.opacity = '0';
                dropdown.style.transform = 'translateY(-10px)';
                if (chevron) chevron.style.transform = 'rotate(0deg)';
                setTimeout(() => { dropdown.style.display = 'none'; }, 200);
            }
        }

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const userDropdownContainer = event.target.closest('.user-dropdown');
            
            if (!userDropdownContainer && dropdown && dropdown.style.display === 'block') {
                dropdown.style.opacity = '0';
                dropdown.style.transform = 'translateY(-10px)';
                const chevron = document.getElementById('dropdownChevron');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
                setTimeout(() => { dropdown.style.display = 'none'; }, 200);
            }
        });

        // Logout-Funktionalität mit eigener Bestätigung
        document.addEventListener('DOMContentLoaded', function() {
            const logoutLink = document.querySelector('a[href*="action=logout"]');
            if (logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    if (confirm('Möchten Sie sich wirklich abmelden?')) {
                        // Sofortiger Logout ohne weitere Dialoge
                        window.location.href = this.href;
                    }
                });
            }
        });
    </script>
    
    <?php if (file_exists(__DIR__ . '/assets/script.js')): ?>
        <script src="assets/script.js"></script>
    <?php endif; ?>
</body>
</html>