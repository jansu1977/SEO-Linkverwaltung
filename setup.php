<?php
session_start();

define('APP_ROOT', __DIR__);
define('DATA_DIR', APP_ROOT . '/data');

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

// Setup-Funktionen
function validateSetupEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generateSetupId() {
    return uniqid('', true);
}

function saveSetupData($filename, $data) {
    $filepath = DATA_DIR . '/' . $filename;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filepath, $json) !== false;
}

// Setup bereits abgeschlossen?
if (file_exists(DATA_DIR . '/setup_complete.json') && !isset($_GET['force'])) {
    header('Location: index.php');
    exit;
}

$step = 1;
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'start_setup') {
        $step = 2;
    } elseif ($action === 'complete_setup') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $createDemo = isset($_POST['create_demo']);
        
        // Validierung
        if (empty($username)) $errors[] = 'Benutzername erforderlich';
        if (empty($email) || !validateSetupEmail($email)) $errors[] = 'Gültige E-Mail erforderlich';
        if (strlen($password) < 6) $errors[] = 'Passwort zu kurz (min. 6 Zeichen)';
        if ($password !== $confirmPassword) $errors[] = 'Passwörter stimmen nicht überein';
        
        if (empty($errors)) {
            try {
                // Admin-User erstellen
                $userId = generateSetupId();
                $users = [
                    $userId => [
                        'id' => $userId,
                        'username' => $username,
                        'email' => $email,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => 'admin',
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ];
                
                // Dateien erstellen
                saveSetupData('users.json', $users);
                saveSetupData('customers.json', []);
                saveSetupData('blogs.json', []);
                saveSetupData('links.json', []);
                
                // Demo-Daten
                if ($createDemo) {
                    $_SESSION['user_id'] = $userId;
                    include_once 'includes/functions.php';
                    createDemoData();
                }
                
                // Setup abschließen
                saveSetupData('setup_complete.json', [
                    'completed' => true,
                    'completed_at' => date('Y-m-d H:i:s'),
                    'version' => '1.0.0'
                ]);
                
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['setup_success'] = true;
                
                $success = true;
                
            } catch (Exception $e) {
                $errors[] = 'Setup-Fehler: ' . $e->getMessage();
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
                const btn = document.getElementById('redirectBtn');
                const timer = setInterval(() => {
                    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Weiterleitung in ${countdown}s`;
                    countdown--;
                    if (countdown < 0) {
                        clearInterval(timer);
                        window.location.href = 'index.php';
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
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">E-Mail *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
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
</html>