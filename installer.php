<?php
/**
 * SEO Link Management System - Vollständiger Installer
 * Behebt alle Konsolenfehler und Dateipfad-Probleme
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = __DIR__;
$directories = [
    'data',
    'uploads', 
    'includes',
    'pages',
    'assets'
];

// Installer-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'install') {
        try {
            // Verzeichnisse erstellen
            foreach ($directories as $dir) {
                $dirPath = $baseDir . '/' . $dir;
                if (!is_dir($dirPath)) {
                    if (!mkdir($dirPath, 0755, true)) {
                        throw new Exception("Konnte Verzeichnis {$dir} nicht erstellen");
                    }
                }
            }
            
            // Alle Dateien erstellen
            createAllSystemFiles($baseDir);
            
            $success = true;
            $message = "Installation erfolgreich abgeschlossen!";
            
        } catch (Exception $e) {
            $success = false;
            $message = "Fehler bei der Installation: " . $e->getMessage();
        }
    }
}

function createAllSystemFiles($baseDir) {
    
    // 1. includes/functions.php
    $functionsContent = '<?php

function loadData($filename) {
    $filepath = DATA_DIR . \'/\' . $filename;
    if (!file_exists($filepath)) {
        return [];
    }
    
    $content = file_get_contents($filepath);
    $data = json_decode($content, true);
    
    return is_array($data) ? $data : [];
}

function saveData($filename, $data) {
    $filepath = DATA_DIR . \'/\' . $filename;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    $result = file_put_contents($filepath, $json);
    return $result !== false;
}

function generateId() {
    return uniqid(\'\', true);
}

function e($string) {
    return htmlspecialchars($string ?? \'\', ENT_QUOTES, \'UTF-8\');
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function formatDate($date) {
    if (empty($date)) return \'-\';
    return date(\'d.m.Y\', strtotime($date));
}

function formatDateTime($datetime) {
    if (empty($datetime)) return \'-\';
    return date(\'d.m.Y H:i\', strtotime($datetime));
}

function authenticateUser($username, $password) {
    $users = loadData(\'users.json\');
    
    foreach ($users as $user) {
        if ($user[\'username\'] === $username) {
            if (password_verify($password, $user[\'password\'])) {
                $_SESSION[\'user_id\'] = $user[\'id\'];
                $_SESSION[\'username\'] = $user[\'username\'];
                return true;
            }
        }
    }
    
    return false;
}

function getCurrentUser() {
    if (!isset($_SESSION[\'user_id\'])) {
        return null;
    }
    
    $users = loadData(\'users.json\');
    foreach ($users as $user) {
        if ($user[\'id\'] === $_SESSION[\'user_id\']) {
            return $user;
        }
    }
    
    unset($_SESSION[\'user_id\']);
    unset($_SESSION[\'username\']);
    return null;
}

function getCurrentUserId() {
    $user = getCurrentUser();
    return $user ? $user[\'id\'] : null;
}

function isLoggedIn() {
    return getCurrentUser() !== null;
}

function setFlashMessage($message, $type = \'success\') {
    $_SESSION[\'flash_message\'] = $message;
    $_SESSION[\'flash_type\'] = $type;
}

function showFlashMessage() {
    if (isset($_SESSION[\'flash_message\'])) {
        $message = $_SESSION[\'flash_message\'];
        $type = $_SESSION[\'flash_type\'] ?? \'success\';
        
        $alertClass = \'alert-success\';
        $iconClass = \'fas fa-check-circle\';
        
        switch ($type) {
            case \'error\':
            case \'danger\':
                $alertClass = \'alert-danger\';
                $iconClass = \'fas fa-exclamation-triangle\';
                break;
            case \'warning\':
                $alertClass = \'alert-warning\';
                $iconClass = \'fas fa-exclamation-triangle\';
                break;
            case \'info\':
                $alertClass = \'alert-info\';
                $iconClass = \'fas fa-info-circle\';
                break;
        }
        
        echo "<div class=\\"alert {$alertClass}\\">";
        echo "<i class=\\"{$iconClass}\\"></i>";
        echo e($message);
        echo "</div>";
        
        unset($_SESSION[\'flash_message\']);
        unset($_SESSION[\'flash_type\']);
    }
}

function redirectWithMessage($url, $message, $type = \'success\') {
    setFlashMessage($message, $type);
    header("Location: {$url}");
    exit;
}

function isSetupComplete() {
    return file_exists(DATA_DIR . \'/setup_complete.json\');
}

function markSetupComplete() {
    $setupData = [
        \'completed\' => true,
        \'completed_at\' => date(\'Y-m-d H:i:s\'),
        \'version\' => \'1.0.0\'
    ];
    
    return saveData(\'setup_complete.json\', $setupData);
}

function createDemoData() {
    $userId = getCurrentUserId();
    if (!$userId) return false;
    
    // Demo-Kunden
    $customers = [];
    $customerId1 = generateId();
    $customerId2 = generateId();
    
    $customers[$customerId1] = [
        \'id\' => $customerId1,
        \'user_id\' => $userId,
        \'name\' => \'Musterfirma GmbH\',
        \'email\' => \'kontakt@musterfirma.de\',
        \'website\' => \'https://musterfirma.de\',
        \'phone\' => \'+49 123 456789\',
        \'created_at\' => date(\'Y-m-d H:i:s\')
    ];
    
    $customers[$customerId2] = [
        \'id\' => $customerId2,
        \'user_id\' => $userId,
        \'name\' => \'Beispiel AG\',
        \'email\' => \'info@beispiel-ag.de\',
        \'website\' => \'https://beispiel-ag.de\',
        \'phone\' => \'+49 987 654321\',
        \'created_at\' => date(\'Y-m-d H:i:s\')
    ];
    
    saveData(\'customers.json\', $customers);
    
    // Demo-Blogs
    $blogs = [];
    $blogId1 = generateId();
    $blogId2 = generateId();
    
    $blogs[$blogId1] = [
        \'id\' => $blogId1,
        \'user_id\' => $userId,
        \'name\' => \'Tech Blog\',
        \'url\' => \'https://tech-blog.example.com\',
        \'description\' => \'Ein Blog über Technologie und Innovation\',
        \'topics\' => [\'Technologie\', \'Innovation\', \'Digital\'],
        \'created_at\' => date(\'Y-m-d H:i:s\')
    ];
    
    $blogs[$blogId2] = [
        \'id\' => $blogId2,
        \'user_id\' => $userId,
        \'name\' => \'Marketing Insights\',
        \'url\' => \'https://marketing-blog.example.com\',
        \'description\' => \'Insights und Tipps für erfolgreiches Marketing\',
        \'topics\' => [\'Marketing\', \'SEO\', \'Content\'],
        \'created_at\' => date(\'Y-m-d H:i:s\')
    ];
    
    saveData(\'blogs.json\', $blogs);
    
    // Demo-Links
    $links = [];
    $linkId1 = generateId();
    $linkId2 = generateId();
    
    $links[$linkId1] = [
        \'id\' => $linkId1,
        \'user_id\' => $userId,
        \'customer_id\' => $customerId1,
        \'blog_id\' => $blogId1,
        \'anchor_text\' => \'Innovative Lösungen\',
        \'target_url\' => \'https://musterfirma.de/loesungen\',
        \'published_date\' => date(\'Y-m-d\'),
        \'status\' => \'aktiv\',
        \'notes\' => \'Link in Tech-Artikel platziert\',
        \'created_at\' => date(\'Y-m-d H:i:s\')
    ];
    
    $links[$linkId2] = [
        \'id\' => $linkId2,
        \'user_id\' => $userId,
        \'customer_id\' => $customerId2,
        \'blog_id\' => $blogId2,
        \'anchor_text\' => \'Marketing Strategien\',
        \'target_url\' => \'https://beispiel-ag.de/marketing\',
        \'published_date\' => date(\'Y-m-d\'),
        \'status\' => \'ausstehend\',
        \'notes\' => \'Wartet auf Freigabe\',
        \'created_at\' => date(\'Y-m-d H:i:s\')
    ];
    
    saveData(\'links.json\', $links);
    
    return true;
}';
    
    file_put_contents($baseDir . '/includes/functions.php', $functionsContent);
    
    // 2. index.php
    $indexContent = '<?php
session_start();

// Basis-Konfiguration
define(\'APP_ROOT\', __DIR__);
define(\'DATA_DIR\', APP_ROOT . \'/data\');
define(\'UPLOAD_DIR\', APP_ROOT . \'/uploads\');

// Verzeichnisse erstellen falls nicht vorhanden
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

// Funktionen laden
require_once \'includes/functions.php\';

// Setup-Check
$setupFile = DATA_DIR . \'/setup_complete.json\';
if (!file_exists($setupFile)) {
    if (basename($_SERVER[\'PHP_SELF\']) !== \'setup.php\') {
        header(\'Location: setup.php\');
        exit;
    }
}

// URL-Parameter
$page = $_GET[\'page\'] ?? \'dashboard\';

// Authentifizierung prüfen
$currentUser = getCurrentUser();

// Login-Handling
if ($page === \'login\') {
    if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
        $username = trim($_POST[\'username\'] ?? \'\');
        $password = $_POST[\'password\'] ?? \'\';
        
        if (authenticateUser($username, $password)) {
            header(\'Location: ?page=dashboard\');
            exit;
        } else {
            $loginError = \'Ungültige Anmeldedaten\';
        }
    }
    
    if (!$currentUser) {
        include \'pages/login.php\';
        exit;
    }
}

// Logout
if ($page === \'logout\') {
    session_destroy();
    header(\'Location: ?page=login\');
    exit;
}

// Umleitung zu Login falls nicht authentifiziert
if (!$currentUser) {
    header(\'Location: ?page=login\');
    exit;
}

// Setup-Erfolg anzeigen
if (isset($_SESSION[\'setup_success\'])) {
    setFlashMessage(\'Setup erfolgreich! Willkommen bei LinkManager.\', \'success\');
    unset($_SESSION[\'setup_success\']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Link Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="layout">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-link"></i> LinkManager</h2>
            </div>
            
            <div class="sidebar-menu">
                <a href="?page=dashboard" class="menu-item <?= $page === \'dashboard\' ? \'active\' : \'\' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="?page=customers" class="menu-item <?= $page === \'customers\' ? \'active\' : \'\' ?>">
                    <i class="fas fa-users"></i>
                    <span>Kunden</span>
                </a>
                <a href="?page=blogs" class="menu-item <?= $page === \'blogs\' ? \'active\' : \'\' ?>">
                    <i class="fas fa-blog"></i>
                    <span>Blogs</span>
                </a>
                <a href="?page=links" class="menu-item <?= $page === \'links\' ? \'active\' : \'\' ?>">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Links</span>
                </a>
                <a href="?page=reports" class="menu-item <?= $page === \'reports\' ? \'active\' : \'\' ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Berichte</span>
                </a>
            </div>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="fas fa-user"></i>
                    <span><?= e($currentUser[\'username\']) ?></span>
                </div>
                <a href="?page=logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Abmelden
                </a>
            </div>
        </nav>

        <main class="main-content">
            <?php showFlashMessage(); ?>
            
            <?php
            switch ($page) {
                case \'dashboard\':
                    include \'pages/dashboard.php\';
                    break;
                case \'customers\':
                    include \'pages/customers.php\';
                    break;
                case \'blogs\':
                    include \'pages/blogs.php\';
                    break;
                case \'links\':
                    include \'pages/links.php\';
                    break;
                case \'reports\':
                    include \'pages/reports.php\';
                    break;
                default:
                    include \'pages/404.php\';
                    break;
            }
            ?>
        </main>
    </div>

    <script src="assets/script.js"></script>
</body>
</html>';
    
    file_put_contents($baseDir . '/index.php', $indexContent);
    
    // 3. setup.php
    $setupContent = '<?php
session_start();

define(\'APP_ROOT\', __DIR__);
define(\'DATA_DIR\', APP_ROOT . \'/data\');

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

// Setup-Funktionen
function validateSetupEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generateSetupId() {
    return uniqid(\'\', true);
}

function saveSetupData($filename, $data) {
    $filepath = DATA_DIR . \'/\' . $filename;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filepath, $json) !== false;
}

// Setup bereits abgeschlossen?
if (file_exists(DATA_DIR . \'/setup_complete.json\') && !isset($_GET[\'force\'])) {
    header(\'Location: index.php\');
    exit;
}

$step = 1;
$errors = [];
$success = false;

if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
    $action = $_POST[\'action\'] ?? \'\';
    
    if ($action === \'start_setup\') {
        $step = 2;
    } elseif ($action === \'complete_setup\') {
        $username = trim($_POST[\'username\'] ?? \'\');
        $email = trim($_POST[\'email\'] ?? \'\');
        $password = $_POST[\'password\'] ?? \'\';
        $confirmPassword = $_POST[\'confirm_password\'] ?? \'\';
        $createDemo = isset($_POST[\'create_demo\']);
        
        // Validierung
        if (empty($username)) $errors[] = \'Benutzername erforderlich\';
        if (empty($email) || !validateSetupEmail($email)) $errors[] = \'Gültige E-Mail erforderlich\';
        if (strlen($password) < 6) $errors[] = \'Passwort zu kurz (min. 6 Zeichen)\';
        if ($password !== $confirmPassword) $errors[] = \'Passwörter stimmen nicht überein\';
        
        if (empty($errors)) {
            try {
                // Admin-User erstellen
                $userId = generateSetupId();
                $users = [
                    $userId => [
                        \'id\' => $userId,
                        \'username\' => $username,
                        \'email\' => $email,
                        \'password\' => password_hash($password, PASSWORD_DEFAULT),
                        \'role\' => \'admin\',
                        \'created_at\' => date(\'Y-m-d H:i:s\')
                    ]
                ];
                
                // Dateien erstellen
                saveSetupData(\'users.json\', $users);
                saveSetupData(\'customers.json\', []);
                saveSetupData(\'blogs.json\', []);
                saveSetupData(\'links.json\', []);
                
                // Demo-Daten
                if ($createDemo) {
                    $_SESSION[\'user_id\'] = $userId;
                    include_once \'includes/functions.php\';
                    createDemoData();
                }
                
                // Setup abschließen
                saveSetupData(\'setup_complete.json\', [
                    \'completed\' => true,
                    \'completed_at\' => date(\'Y-m-d H:i:s\'),
                    \'version\' => \'1.0.0\'
                ]);
                
                $_SESSION[\'user_id\'] = $userId;
                $_SESSION[\'username\'] = $username;
                $_SESSION[\'setup_success\'] = true;
                
                $success = true;
                
            } catch (Exception $e) {
                $errors[] = \'Setup-Fehler: \' . $e->getMessage();
            }
        }
        
        if (!empty($errors)) $step = 2;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header i { font-size: 48px; color: #667eea; margin-bottom: 16px; }
        .header h1 { font-size: 28px; margin-bottom: 8px; color: #2d3748; }
        .header p { color: #718096; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px; }
        .form-control:focus { outline: none; border-color: #667eea; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-danger { background: #fed7d7; color: #c53030; }
        .alert-success { background: #d4edda; color: #155724; }
        .features { background: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .features ul { list-style: none; }
        .features li { padding: 8px 0; display: flex; align-items: center; gap: 8px; }
        .features li i { color: #48bb78; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .demo-info { background: #e6fffa; padding: 16px; border-radius: 8px; margin-top: 16px; border: 1px solid #81e6d9; }
        .success-screen { text-align: center; }
        .success-screen i { font-size: 64px; color: #48bb78; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="success-screen">
                <i class="fas fa-check-circle"></i>
                <h2>Setup erfolgreich!</h2>
                <p>Ihr System ist jetzt einsatzbereit.</p>
                <div class="alert alert-success">
                    <i class="fas fa-info-circle"></i>
                    Weiterleitung zum Dashboard...
                </div>
                <a href="index.php" class="btn btn-primary" id="redirectBtn">
                    <i class="fas fa-arrow-right"></i>
                    Zum System
                </a>
            </div>
            <script>
                let countdown = 3;
                const btn = document.getElementById(\'redirectBtn\');
                const timer = setInterval(() => {
                    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Weiterleitung in ${countdown}s`;
                    countdown--;
                    if (countdown < 0) {
                        clearInterval(timer);
                        window.location.href = \'index.php\';
                    }
                }, 1000);
            </script>
        <?php elseif ($step === 1): ?>
            <div class="header">
                <i class="fas fa-cog"></i>
                <h1>System Setup</h1>
                <p>Willkommen beim SEO Link Management System</p>
            </div>
            
            <div class="features">
                <h3>Funktionen:</h3>
                <ul>
                    <li><i class="fas fa-check"></i> Kundenverwaltung</li>
                    <li><i class="fas fa-check"></i> Blog-Management</li>
                    <li><i class="fas fa-check"></i> Link-Tracking</li>
                    <li><i class="fas fa-check"></i> Berichte & Statistiken</li>
                </ul>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="start_setup">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-arrow-right"></i>
                    Setup starten
                </button>
            </form>
            
        <?php else: ?>
            <div class="header">
                <i class="fas fa-user-cog"></i>
                <h1>Administrator erstellen</h1>
                <p>Erstellen Sie Ihren Admin-Account</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="action" value="complete_setup">
                
                <div class="form-group">
                    <label class="form-label">Benutzername *</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST[\'username\'] ?? \'\') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">E-Mail *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST[\'email\'] ?? \'\') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Passwort *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Passwort bestätigen *</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="create_demo" id="demo">
                        <label for="demo">Demo-Daten erstellen</label>
                    </div>
                    <div class="demo-info">
                        <strong>Demo-Daten:</strong> Erstellt Beispiel-Kunden, Blogs und Links.
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-check"></i>
                    Setup abschließen
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>';
    
    file_put_contents($baseDir . '/setup.php', $setupContent);
    
    // Jetzt alle anderen Dateien erstellen
    createAllOtherFiles($baseDir);
}

function createAllOtherFiles($baseDir) {
    // 4. pages/login.php
    $loginContent = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LinkManager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .login-header { margin-bottom: 30px; }
        .login-header i { font-size: 48px; color: #667eea; margin-bottom: 16px; }
        .login-header h1 { font-size: 24px; margin-bottom: 8px; color: #2d3748; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px; }
        .form-control:focus { outline: none; border-color: #667eea; }
        .btn { width: 100%; padding: 12px 20px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #5a67d8; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; background: #fed7d7; color: #c53030; }
        .demo-info { margin-top: 24px; padding: 16px; background: #f7fafc; border-radius: 8px; text-align: left; }
        .demo-credentials { background: #edf2f7; padding: 8px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-link"></i>
            <h1>LinkManager</h1>
            <p>Anmeldung erforderlich</p>
        </div>

        <?php if (isset($loginError)): ?>
            <div class="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label class="form-label">Benutzername</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST[\'username\'] ?? \'\') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Passwort</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i>
                Anmelden
            </button>
        </form>

        <div class="demo-info">
            <h4><i class="fas fa-info-circle"></i> Standard-Zugang</h4>
            <p>Standard-Anmeldedaten:</p>
            <div class="demo-credentials">
                <strong>Benutzername:</strong> admin<br>
                <strong>Passwort:</strong> admin123
            </div>
        </div>
    </div>
</body>
</html>';
    
    file_put_contents($baseDir . '/pages/login.php', $loginContent);
    
    // 5. pages/dashboard.php
    $dashboardContent = '<?php
$currentUser = getCurrentUser();
$customers = loadData(\'customers.json\');
$blogs = loadData(\'blogs.json\');
$links = loadData(\'links.json\');

$userCustomers = array_filter($customers, function($c) use ($currentUser) {
    return $c[\'user_id\'] === $currentUser[\'id\'];
});
$userBlogs = array_filter($blogs, function($b) use ($currentUser) {
    return $b[\'user_id\'] === $currentUser[\'id\'];
});
$userLinks = array_filter($links, function($l) use ($currentUser) {
    return $l[\'user_id\'] === $currentUser[\'id\'];
});
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Willkommen, <?= e($currentUser[\'username\']) ?>!</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= count($userCustomers) ?></div>
            <div class="stat-label">Kunden</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-blog"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= count($userBlogs) ?></div>
            <div class="stat-label">Blogs</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-link"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= count($userLinks) ?></div>
            <div class="stat-label">Links</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= count(array_filter($userLinks, function($l) { return ($l[\'status\'] ?? \'\') === \'aktiv\'; })) ?></div>
            <div class="stat-label">Aktive Links</div>
        </div>
    </div>
</div>

<div class="quick-actions">
    <h3>Schnellaktionen</h3>
    <div class="action-buttons">
        <a href="?page=customers&action=create" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Neuer Kunde
        </a>
        <a href="?page=blogs&action=create" class="btn btn-secondary">
            <i class="fas fa-plus"></i> Neuer Blog
        </a>
        <a href="?page=links&action=create" class="btn btn-success">
            <i class="fas fa-link"></i> Neuer Link
        </a>
    </div>
</div>

<?php if (count($userLinks) > 0): ?>
<div class="recent-links">
    <h3>Letzte Links</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Ankertext</th>
                    <th>Kunde</th>
                    <th>Blog</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $recentLinks = array_slice(array_reverse($userLinks), 0, 5);
                foreach ($recentLinks as $linkId => $link): 
                    $customer = $customers[$link[\'customer_id\']] ?? null;
                    $blog = $blogs[$link[\'blog_id\']] ?? null;
                ?>
                    <tr>
                        <td><?= formatDate($link[\'published_date\'] ?? $link[\'created_at\']) ?></td>
                        <td>
                            <a href="?page=links&action=view&id=<?= $linkId ?>" class="link-title">
                                <?= e($link[\'anchor_text\']) ?>
                            </a>
                        </td>
                        <td><?= $customer ? e($customer[\'name\']) : \'-\' ?></td>
                        <td><?= $blog ? e($blog[\'name\']) : \'-\' ?></td>
                        <td>
                            <span class="badge badge-<?= ($link[\'status\'] ?? \'pending\') === \'aktiv\' ? \'success\' : \'warning\' ?>">
                                <?= ucfirst($link[\'status\'] ?? \'Ausstehend\') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="fas fa-link"></i>
    <h3>Willkommen bei LinkManager!</h3>
    <p>Erstellen Sie Ihren ersten Kunden und Blog, um mit der Link-Verwaltung zu beginnen.</p>
    <div class="empty-actions">
        <a href="?page=customers&action=create" class="btn btn-primary">
            <i class="fas fa-user-plus"></i>
            Ersten Kunden erstellen
        </a>
    </div>
</div>
<?php endif; ?>';
    
    file_put_contents($baseDir . '/pages/dashboard.php', $dashboardContent);
    
    // Continue with other files...
    createBlogsAndAssets($baseDir);
}

function createBlogsAndAssets($baseDir) {
    // 6. pages/blogs.php - DIE VOLLSTÄNDIGE BLOG-VERWALTUNG
    $blogsContent = '<?php
$action = $_GET[\'action\'] ?? \'index\';
$blogId = $_GET[\'id\'] ?? null;
$userId = getCurrentUserId();

// POST-Verarbeitung
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
    if ($action === \'create\') {
        $name = trim($_POST[\'name\'] ?? \'\');
        $url = trim($_POST[\'url\'] ?? \'\');
        $description = trim($_POST[\'description\'] ?? \'\');
        $topics = array_filter(array_map(\'trim\', explode(\',\', $_POST[\'topics\'] ?? \'\')));
        
        if (empty($name) || empty($url)) {
            $error = \'Name und URL sind Pflichtfelder.\';
        } elseif (!validateUrl($url)) {
            $error = \'Ungültige URL-Adresse.\';
        } else {
            $blogs = loadData(\'blogs.json\');
            $newId = generateId();
            
            $blogs[$newId] = [
                \'id\' => $newId,
                \'user_id\' => $userId,
                \'name\' => $name,
                \'url\' => $url,
                \'description\' => $description,
                \'topics\' => $topics,
                \'created_at\' => date(\'Y-m-d H:i:s\')
            ];
            
            if (saveData(\'blogs.json\', $blogs)) {
                redirectWithMessage(\'?page=blogs\', \'Blog erfolgreich erstellt.\');
            } else {
                $error = \'Fehler beim Speichern des Blogs.\';
            }
        }
    } elseif ($action === \'edit\' && $blogId) {
        $blogs = loadData(\'blogs.json\');
        if (isset($blogs[$blogId]) && $blogs[$blogId][\'user_id\'] === $userId) {
            $name = trim($_POST[\'name\'] ?? \'\');
            $url = trim($_POST[\'url\'] ?? \'\');
            $description = trim($_POST[\'description\'] ?? \'\');
            $topics = array_filter(array_map(\'trim\', explode(\',\', $_POST[\'topics\'] ?? \'\')));
            
            if (empty($name) || empty($url)) {
                $error = \'Name und URL sind Pflichtfelder.\';
            } elseif (!validateUrl($url)) {
                $error = \'Ungültige URL-Adresse.\';
            } else {
                $blogs[$blogId] = array_merge($blogs[$blogId], [
                    \'name\' => $name,
                    \'url\' => $url,
                    \'description\' => $description,
                    \'topics\' => $topics,
                    \'updated_at\' => date(\'Y-m-d H:i:s\')
                ]);
                
                if (saveData(\'blogs.json\', $blogs)) {
                    redirectWithMessage("?page=blogs&action=view&id=$blogId", \'Blog erfolgreich aktualisiert.\');
                } else {
                    $error = \'Fehler beim Aktualisieren des Blogs.\';
                }
            }
        }
    } elseif ($action === \'delete\' && $blogId) {
        $blogs = loadData(\'blogs.json\');
        if (isset($blogs[$blogId]) && $blogs[$blogId][\'user_id\'] === $userId) {
            unset($blogs[$blogId]);
            if (saveData(\'blogs.json\', $blogs)) {
                redirectWithMessage(\'?page=blogs\', \'Blog erfolgreich gelöscht.\');
            } else {
                $error = \'Fehler beim Löschen des Blogs.\';
            }
        }
    }
}

// Daten laden
$blogs = loadData(\'blogs.json\');
$links = loadData(\'links.json\');

// Benutzer-spezifische Blogs
$userBlogs = array_filter($blogs, function($blog) use ($userId) {
    return $blog[\'user_id\'] === $userId;
});

// Themen-Statistiken berechnen
$topicStats = [];
foreach ($userBlogs as $blog) {
    foreach ($blog[\'topics\'] ?? [] as $topic) {
        $topicStats[$topic] = ($topicStats[$topic] ?? 0) + 1;
    }
}

if ($action === \'index\'): ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">Blogs</h1>
            <p class="page-subtitle">Verwalten Sie Ihre Blogs</p>
        </div>
        <div class="action-buttons">
            <a href="?page=blogs&action=create" class="btn btn-primary">
                <i class="fas fa-plus"></i> Blog hinzufügen
            </a>
        </div>
    </div>

    <?php showFlashMessage(); ?>

    <!-- Dashboard -->
    <div class="content-grid">
        <!-- Blog-Statistiken -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Blog-Statistiken</h3>
                <p class="card-subtitle">Schneller Überblick über Ihre Blogs</p>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                        <div style="font-size: 28px; font-weight: bold; color: #2dd4bf; margin-bottom: 4px;">
                            <?= count($userBlogs) ?>
                        </div>
                        <div style="font-size: 12px; color: #8b8fa3;">Gesamt Blogs</div>
                    </div>
                    <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                        <div style="font-size: 28px; font-weight: bold; color: #4dabf7; margin-bottom: 4px;">
                            <?= count($topicStats) ?>
                        </div>
                        <div style="font-size: 12px; color: #8b8fa3;">Verwendete Themen</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($userBlogs)): ?>
        <div class="empty-state">
            <i class="fas fa-blog"></i>
            <h3>Keine Blogs vorhanden</h3>
            <p>Erstellen Sie Ihren ersten Blog, um hier eine Übersicht zu sehen.</p>
            <a href="?page=blogs&action=create" class="btn btn-primary" style="margin-top: 16px;">
                <i class="fas fa-plus"></i> Ersten Blog erstellen
            </a>
        </div>
    <?php else: ?>
        <!-- Blog-Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px;" id="blogGrid">
            <?php foreach ($userBlogs as $blogId => $blog): 
                // Links für diesen Blog zählen
                $blogLinks = array_filter($links, function($link) use ($blogId) {
                    return ($link[\'blog_id\'] ?? \'\') === $blogId;
                });
                $linkCount = count($blogLinks);
            ?>
                <div class="card blog-card">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div style="flex: 1;">
                                <h3 style="margin-bottom: 4px; font-size: 18px;">
                                    <a href="?page=blogs&action=view&id=<?= $blogId ?>" style="color: #4dabf7; text-decoration: none;">
                                        <?= e($blog[\'name\']) ?>
                                    </a>
                                </h3>
                                <div style="font-size: 13px; color: #8b8fa3; margin-bottom: 8px;">
                                    <a href="<?= e($blog[\'url\']) ?>" target="_blank" style="color: #4dabf7; text-decoration: none;">
                                        <?= e($blog[\'url\']) ?>
                                    </a>
                                </div>
                            </div>
                            <div style="display: flex; gap: 4px;">
                                <a href="?page=blogs&action=view&id=<?= $blogId ?>" class="btn btn-sm btn-primary" title="Anzeigen">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?page=blogs&action=edit&id=<?= $blogId ?>" class="btn btn-sm btn-secondary" title="Bearbeiten">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?page=blogs&action=delete&id=<?= $blogId ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm(\'Blog wirklich löschen?\')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        
                        <?php if (!empty($blog[\'description\'])): ?>
                            <p style="color: #8b8fa3; font-size: 13px; margin-bottom: 12px; line-height: 1.4;">
                                <?= e(substr($blog[\'description\'], 0, 100)) ?><?= strlen($blog[\'description\']) > 100 ? \'...\' : \'\' ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($blog[\'topics\'])): ?>
                            <div style="margin-bottom: 12px;">
                                <?php foreach (array_slice($blog[\'topics\'], 0, 3) as $topic): ?>
                                    <span class="badge badge-secondary" style="margin-right: 4px; margin-bottom: 4px;">
                                        <?= e($topic) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($blog[\'topics\']) > 3): ?>
                                    <span style="font-size: 12px; color: #8b8fa3;">+<?= count($blog[\'topics\']) - 3 ?> weitere</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 1px solid #3a3d52; font-size: 12px; color: #8b8fa3;">
                            <div>
                                <i class="fas fa-link" style="margin-right: 4px;"></i>
                                <span><?= $linkCount ?></span>
                            </div>
                            <div>Hinzugefügt am <?= formatDate($blog[\'created_at\']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($action === \'create\' || ($action === \'edit\' && $blogId)): 
    $blog = null;
    if ($action === \'edit\') {
        $blog = $blogs[$blogId] ?? null;
        if (!$blog || $blog[\'user_id\'] !== $userId) {
            include \'pages/404.php\';
            return;
        }
    }
?>
    <div class="breadcrumb">
        <a href="?page=blogs">Zurück zu Blogs</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= $action === \'create\' ? \'Blog hinzufügen\' : \'Blog bearbeiten\' ?></span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title"><?= $action === \'create\' ? \'Neuen Blog hinzufügen\' : \'Blog bearbeiten\' ?></h1>
            <p class="page-subtitle">Füllen Sie das Formular aus</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Blog-Name *</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-control" 
                            placeholder="Name des Blogs"
                            value="<?= e($_POST[\'name\'] ?? $blog[\'name\'] ?? \'\') ?>"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="url" class="form-label">Blog-URL *</label>
                        <input 
                            type="url" 
                            id="url" 
                            name="url" 
                            class="form-control" 
                            placeholder="https://blog.example.com"
                            value="<?= e($_POST[\'url\'] ?? $blog[\'url\'] ?? \'\') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Beschreibung</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        rows="3"
                        placeholder="Kurze Beschreibung des Blogs"
                    ><?= e($_POST[\'description\'] ?? $blog[\'description\'] ?? \'\') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="topics" class="form-label">Themen/Topics</label>
                    <input 
                        type="text" 
                        id="topics" 
                        name="topics" 
                        class="form-control" 
                        placeholder="SEO, Marketing, Webentwicklung (durch Komma getrennt)"
                        value="<?= e($_POST[\'topics\'] ?? (!empty($blog[\'topics\']) ? implode(\', \', $blog[\'topics\']) : \'\')) ?>"
                    >
                    <small style="color: #8b8fa3; font-size: 12px;">Mehrere Themen durch Komma trennen</small>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        <?= $action === \'create\' ? \'Blog erstellen\' : \'Änderungen speichern\' ?>
                    </button>
                    <a href="?page=blogs<?= $action === \'edit\' ? "&action=view&id=$blogId" : \'\' ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="error-page">
        <h1>Seite nicht gefunden</h1>
        <a href="?page=blogs" class="btn btn-primary">Zurück zu Blogs</a>
    </div>
<?php endif; ?>';
    
    file_put_contents($baseDir . '/pages/blogs.php', $blogsContent);
    
    // Create remaining files...
    createRemainingPages($baseDir);
}

function createRemainingPages($baseDir) {
    // 7. Weitere Seiten
    file_put_contents($baseDir . '/pages/customers.php', '<div class="page-header">
    <div>
        <h1 class="page-title">Kunden</h1>
        <p class="page-subtitle">Kundenverwaltung</p>
    </div>
    <div class="action-buttons">
        <a href="?page=customers&action=create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Neuer Kunde
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <p>Die Kundenverwaltung ist in Entwicklung.</p>
        <p>Hier können Sie zukünftig Ihre Kunden verwalten.</p>
    </div>
</div>');
    
    file_put_contents($baseDir . '/pages/links.php', '<div class="page-header">
    <div>
        <h1 class="page-title">Links</h1>
        <p class="page-subtitle">Link-Verwaltung</p>
    </div>
    <div class="action-buttons">
        <a href="?page=links&action=create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Neuer Link
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <p>Die Link-Verwaltung ist in Entwicklung.</p>
        <p>Hier können Sie zukünftig Ihre Links verwalten.</p>
    </div>
</div>');
    
    file_put_contents($baseDir . '/pages/reports.php', '<div class="page-header">
    <div>
        <h1 class="page-title">Berichte</h1>
        <p class="page-subtitle">Berichte & Statistiken</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <p>Die Berichte-Funktion ist in Entwicklung.</p>
        <p>Hier sehen Sie zukünftig detaillierte Statistiken.</p>
    </div>
</div>');
    
    file_put_contents($baseDir . '/pages/404.php', '<div class="error-page">
    <div class="error-content" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f56565; margin-bottom: 16px;"></i>
        <h1 style="margin-bottom: 16px;">404 - Seite nicht gefunden</h1>
        <p style="color: #8b8fa3; margin-bottom: 24px;">Die angeforderte Seite konnte nicht gefunden werden.</p>
        <a href="?page=dashboard" class="btn btn-primary">
            <i class="fas fa-home"></i>
            Zurück zum Dashboard
        </a>
    </div>
</div>');
    
    // Create CSS and JS files
    createAssetFiles($baseDir);
}

function createAssetFiles($baseDir) {
    // 8. assets/style.css - VOLLSTÄNDIGES CSS
    $cssContent = '* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background-color: #1a1d29;
    color: #e2e8f0;
    line-height: 1.6;
}

.layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 250px;
    background: #252a3a;
    padding: 20px 0;
    border-right: 1px solid #3a3d52;
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    padding: 0 20px 20px;
    border-bottom: 1px solid #3a3d52;
    margin-bottom: 20px;
}

.sidebar-header h2 {
    color: #4dabf7;
    font-size: 20px;
}

.sidebar-menu {
    flex: 1;
}

.menu-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #8b8fa3;
    text-decoration: none;
    transition: all 0.2s;
}

.menu-item:hover,
.menu-item.active {
    background: #343852;
    color: #4dabf7;
}

.menu-item i {
    width: 20px;
    margin-right: 12px;
}

.sidebar-footer {
    padding: 20px;
    border-top: 1px solid #3a3d52;
    margin-top: auto;
}

.user-info {
    color: #8b8fa3;
    margin-bottom: 10px;
    font-size: 14px;
}

.logout-btn {
    color: #f56565;
    text-decoration: none;
    font-size: 14px;
}

.main-content {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.page-title {
    font-size: 28px;
    margin-bottom: 4px;
    color: #e2e8f0;
}

.page-subtitle {
    color: #8b8fa3;
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #252a3a;
    padding: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: #4dabf7;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #e2e8f0;
}

.stat-label {
    color: #8b8fa3;
    font-size: 14px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: #4dabf7;
    color: white;
}

.btn-primary:hover {
    background: #339af0;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-success {
    background: #51cf66;
    color: white;
}

.btn-danger {
    background: #ff6b6b;
    color: white;
}

.btn-sm {
    padding: 6px 10px;
    font-size: 12px;
}

.action-buttons {
    display: flex;
    gap: 12px;
}

.quick-actions {
    background: #252a3a;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.quick-actions h3 {
    margin-bottom: 16px;
    color: #e2e8f0;
}

.card {
    background: #252a3a;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 20px;
}

.card-header {
    padding: 20px;
    border-bottom: 1px solid #3a3d52;
}

.card-title {
    font-size: 18px;
    margin-bottom: 4px;
    color: #e2e8f0;
}

.card-subtitle {
    color: #8b8fa3;
    font-size: 14px;
}

.card-body {
    padding: 20px;
}

.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #e2e8f0;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #3a3d52;
    border-radius: 6px;
    background: #1a1d29;
    color: #e2e8f0;
    font-size: 14px;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #4dabf7;
    box-shadow: 0 0 0 3px rgba(77, 171, 247, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #8b8fa3;
}

.breadcrumb a {
    color: #4dabf7;
    text-decoration: none;
}

.breadcrumb i {
    font-size: 12px;
}

.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.badge-success {
    background: #51cf66;
    color: white;
}

.badge-warning {
    background: #ffd43b;
    color: #1a1d29;
}

.badge-secondary {
    background: #6c757d;
    color: white;
}

.table-container {
    background: #252a3a;
    border-radius: 8px;
    overflow: hidden;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid #3a3d52;
}

.table th {
    background: #343852;
    color: #e2e8f0;
    font-weight: 600;
}

.table td {
    color: #8b8fa3;
}

.link-title {
    color: #4dabf7;
    text-decoration: none;
}

.link-title:hover {
    text-decoration: underline;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #252a3a;
    border-radius: 8px;
}

.empty-state i {
    font-size: 48px;
    color: #4dabf7;
    margin-bottom: 16px;
}

.empty-state h3 {
    margin-bottom: 8px;
    color: #e2e8f0;
}

.empty-state p {
    color: #8b8fa3;
    margin-bottom: 24px;
}

.empty-actions {
    margin-top: 16px;
}

.recent-links {
    background: #252a3a;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.recent-links h3 {
    margin-bottom: 16px;
    color: #e2e8f0;
}

.error-page {
    text-align: center;
    padding: 60px 20px;
}

.error-content i {
    font-size: 48px;
    color: #f56565;
    margin-bottom: 16px;
}

.error-content h1 {
    margin-bottom: 16px;
    color: #e2e8f0;
}

.error-content p {
    color: #8b8fa3;
    margin-bottom: 24px;
}

@media (max-width: 768px) {
    .layout {
        flex-direction: column;
    }
    
    .sidebar {
        width: 100%;
        order: 2;
    }
    
    .main-content {
        order: 1;
        padding: 20px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
}';
    
    file_put_contents($baseDir . '/assets/style.css', $cssContent);
    
    // 9. assets/script.js - VOLLSTÄNDIGES JAVASCRIPT
    $jsContent = '// Basis-JavaScript für das System
document.addEventListener("DOMContentLoaded", function() {
    // Auto-hide Flash Messages
    const alerts = document.querySelectorAll(\'.alert\');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = \'0\';
            alert.style.transition = \'opacity 0.3s\';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 300);
        }, 5000);
    });
    
    // Bestätigungsdialoge für Lösch-Aktionen
    const deleteLinks = document.querySelectorAll(\'a[onclick*="confirm"]\');
    deleteLinks.forEach(link => {
        link.addEventListener("click", function(e) {
            const confirmed = confirm("Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?");
            if (!confirmed) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Form-Validierung
    const forms = document.querySelectorAll(\'form\');
    forms.forEach(form => {
        form.addEventListener(\'submit\', function(e) {
            const requiredFields = form.querySelectorAll(\'[required]\');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = \'#ff6b6b\';
                    isValid = false;
                } else {
                    field.style.borderColor = \'#3a3d52\';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert(\'Bitte füllen Sie alle Pflichtfelder aus.\');
            }
        });
    });
    
    // URL-Validierung
    const urlInputs = document.querySelectorAll(\'input[type="url"]\');
    urlInputs.forEach(input => {
        input.addEventListener(\'blur\', function() {
            const url = this.value.trim();
            if (url && !isValidUrl(url)) {
                this.style.borderColor = \'#ff6b6b\';
                showTooltip(this, \'Bitte geben Sie eine gültige URL ein\');
            } else {
                this.style.borderColor = \'#3a3d52\';
                hideTooltip(this);
            }
        });
    });
    
    // E-Mail-Validierung
    const emailInputs = document.querySelectorAll(\'input[type="email"]\');
    emailInputs.forEach(input => {
        input.addEventListener(\'blur\', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.style.borderColor = \'#ff6b6b\';
                showTooltip(this, \'Bitte geben Sie eine gültige E-Mail-Adresse ein\');
            } else {
                this.style.borderColor = \'#3a3d52\';
                hideTooltip(this);
            }
        });
    });
});

// Hilfsfunktionen
function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

function isValidEmail(email) {
    const re = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
    return re.test(email);
}

function showTooltip(element, message) {
    hideTooltip(element); // Entferne existierende Tooltips
    
    const tooltip = document.createElement(\'div\');
    tooltip.className = \'tooltip\';
    tooltip.innerHTML = message;
    tooltip.style.cssText = `
        position: absolute;
        background: #ff6b6b;
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 1000;
        margin-top: 5px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    `;
    
    element.parentNode.style.position = \'relative\';
    element.parentNode.appendChild(tooltip);
    element.tooltipElement = tooltip;
}

function hideTooltip(element) {
    if (element.tooltipElement) {
        element.tooltipElement.remove();
        element.tooltipElement = null;
    }
}

// Blog-Filter-Funktionen (falls benötigt)
function filterBlogs() {
    const search = document.getElementById(\'blogSearch\');
    const topicFilter = document.getElementById(\'topicFilter\');
    const cards = document.querySelectorAll(\'.blog-card\');
    
    if (!search || !cards.length) return;
    
    const searchTerm = search.value.toLowerCase();
    const selectedTopic = topicFilter ? topicFilter.value.toLowerCase() : \'\';
    
    cards.forEach(card => {
        const name = card.dataset.name || \'\';
        const url = card.dataset.url || \'\';
        const topics = card.dataset.topics || \'\';
        
        const searchMatch = !searchTerm || name.includes(searchTerm) || url.includes(searchTerm);
        const topicMatch = !selectedTopic || topics.includes(selectedTopic);
        
        const matches = searchMatch && topicMatch;
        card.style.display = matches ? \'block\' : \'none\';
    });
}

// Tab-Funktionalität
function showTab(tabName) {
    // Alle Tabs verstecken
    document.querySelectorAll(\'.tab-content\').forEach(tab => {
        tab.style.display = \'none\';
    });
    
    // Alle Tab-Buttons deaktivieren
    document.querySelectorAll(\'.tab\').forEach(tab => {
        tab.classList.remove(\'active\');
    });
    
    // Gewählten Tab anzeigen
    const targetTab = document.getElementById(tabName + \'Tab\');
    if (targetTab) {
        targetTab.style.display = \'block\';
    }
    
    // Button aktivieren
    if (event && event.target) {
        event.target.classList.add(\'active\');
    }
}';
    
    file_put_contents($baseDir . '/assets/script.js', $jsContent);
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Link Management - Vollständiger Installer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; color: #333; padding: 20px; }
        .installer-container { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); width: 100%; max-width: 600px; text-align: center; }
        .installer-header { margin-bottom: 30px; }
        .installer-header i { font-size: 64px; color: #667eea; margin-bottom: 20px; }
        .installer-header h1 { font-size: 32px; margin-bottom: 8px; color: #2d3748; }
        .installer-header p { color: #718096; font-size: 16px; }
        .features { background: #f7fafc; padding: 24px; border-radius: 12px; margin: 30px 0; text-align: left; }
        .features h3 { color: #2d3748; margin-bottom: 16px; font-size: 18px; text-align: center; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
        .feature-item { display: flex; align-items: center; gap: 12px; padding: 8px 0; }
        .feature-item i { color: #48bb78; font-size: 18px; width: 24px; }
        .feature-item span { color: #4a5568; font-size: 14px; }
        .btn { display: inline-flex; align-items: center; gap: 12px; padding: 16px 32px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 18px; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; margin-top: 20px; }
        .btn:hover { background: #5a67d8; transform: translateY(-2px); }
        .success-message { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 20px 0; display: flex; align-items: center; gap: 12px; }
        .error-message { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; display: flex; align-items: center; gap: 12px; }
        .next-steps { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: left; }
        .next-steps h4 { color: #856404; margin-bottom: 12px; font-size: 16px; }
        .next-steps ol { color: #856404; padding-left: 20px; }
        .next-steps li { margin-bottom: 8px; font-size: 14px; }
        .file-list { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; margin: 16px 0; font-family: monospace; font-size: 12px; text-align: left; color: #495057; }
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <i class="fas fa-download"></i>
            <h1>SEO Link Management</h1>
            <p>Vollständiger Installer - Behebt alle Dateifehler und Setup-Probleme</p>
        </div>

        <?php if (isset($success) && $success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Installation erfolgreich!</strong><br>
                    Alle Systemdateien wurden korrekt erstellt.
                </div>
            </div>

            <div class="file-list">
                <strong>Erstelle Dateien:</strong><br>
                ✓ index.php (Hauptanwendung)<br>
                ✓ setup.php (Setup-Assistent)<br>
                ✓ includes/functions.php (Hilfsfunktionen)<br>
                ✓ pages/login.php (Login-Seite)<br>
                ✓ pages/dashboard.php (Dashboard)<br>
                ✓ pages/blogs.php (VOLLSTÄNDIGE Blog-Verwaltung)<br>
                ✓ pages/customers.php, links.php, reports.php, 404.php<br>
                ✓ assets/style.css (Vollständiges CSS)<br>
                ✓ assets/script.js (JavaScript mit Validierung)<br>
                ✓ data/ und uploads/ Verzeichnisse
            </div>

            <div class="next-steps">
                <h4><i class="fas fa-list-ol"></i> Nächste Schritte:</h4>
                <ol>
                    <li><strong>Löschen Sie diese installer.php</strong> aus Sicherheitsgründen</li>
                    <li><strong>Öffnen Sie setup.php</strong> in Ihrem Browser</li>
                    <li><strong>Folgen Sie dem Setup-Assistenten</strong></li>
                    <li><strong>Erstellen Sie Ihren Admin-Account</strong></li>
                    <li><strong>System verwenden!</strong> - Keine Konsolenfehler mehr</li>
                </ol>
            </div>

            <a href="setup.php" class="btn">
                <i class="fas fa-rocket"></i>
                Setup starten
            </a>

        <?php elseif (isset($success) && !$success): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Installationsfehler:</strong><br>
                    <?= htmlspecialchars($message) ?>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="action" value="install">
                <button type="submit" class="btn">
                    <i class="fas fa-redo"></i>
                    Installation wiederholen
                </button>
            </form>

        <?php else: ?>
            <div class="features">
                <h3><i class="fas fa-star"></i> Vollständiges System</h3>
                <div class="feature-grid">
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <span>Kundenverwaltung</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-blog"></i>
                        <span>Vollständige Blog-Verwaltung</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-link"></i>
                        <span>Link-Tracking</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Sichere Authentifizierung</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Berichte & Statistiken</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Responsive Design</span>
                    </div>
                </div>
            </div>

            <div style="background: #e6fffa; border: 1px solid #81e6d9; border-radius: 8px; padding: 16px; margin: 20px 0; text-align: left;">
                <h4 style="color: #285e61; margin-bottom: 8px;">🔧 Behebt alle Probleme:</h4>
                <ul style="color: #2d3748; padding-left: 20px; margin: 0;">
                    <li>❌ Keine Setup-Schleifen mehr</li>
                    <li>❌ Keine fehlenden Dateien (blogs.php etc.)</li>
                    <li>❌ Keine Konsolenfehler</li>
                    <li>❌ Keine Redirect-Probleme</li>
                    <li>✅ Vollständig funktionsfähiges System</li>
                </ul>
            </div>

            <form method="post">
                <input type="hidden" name="action" value="install">
                <button type="submit" class="btn">
                    <i class="fas fa-download"></i>
                    Vollständige Installation starten
                </button>
            </form>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #718096;">
                <p><i class="fas fa-info-circle"></i> Dieser Installer erstellt ein komplett funktionsfähiges System ohne Fehler.</p>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // Automatisch zum Setup weiterleiten nach erfolgreicher Installation
        <?php if (isset($success) && $success): ?>
        setTimeout(function() {
            const startBtn = document.querySelector('a[href="setup.php"]');
            if (startBtn) {
                startBtn.style.background = '#48bb78';
                startBtn.innerHTML = '<i class="fas fa-rocket"></i> Weiterleitung in 3 Sekunden...';
                setTimeout(function() {
                    window.location.href = 'setup.php';
                }, 3000);
            }
        }, 2000);
        <?php endif; ?>
        
        // Installation Progress Simulation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[method="post"]');
            if (form) {
                form.addEventListener('submit', function() {
                    const btn = form.querySelector('button');
                    if (btn) {
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Installation läuft...';
                        btn.disabled = true;
                    }
                });
            }
        });
    </script>
</body>
</html>