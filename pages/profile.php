<?php
// pages/profile.php - Funktionale Benutzerprofil-Verwaltung

// User-ID aus Session holen
if (!isset($_SESSION['user_id'])) {
    header('Location: ?page=simple_login');
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'index';

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($name) || empty($email)) {
            $error = 'Name und E-Mail sind Pflichtfelder.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse.';
        } else {
            $users = loadData('users.json');
            
            // Prüfen ob E-Mail bereits von anderem Benutzer verwendet wird
            $existingUser = array_filter($users, function($user) use ($email, $userId) {
                return $user['email'] === $email && $user['id'] !== $userId;
            });
            
            if (!empty($existingUser)) {
                $error = 'Diese E-Mail-Adresse wird bereits verwendet.';
            } else {
                if (isset($users[$userId])) {
                    // Aktualisiere Benutzerdaten
                    $users[$userId] = array_merge($users[$userId], [
                        'name' => $name,
                        'email' => $email,
                        'company' => $company,
                        'phone' => $phone,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    if (saveData('users.json', $users)) {
                        // Session-Daten auch aktualisieren
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        
                        $success = 'Profil erfolgreich aktualisiert.';
                        // Benutzer-Array auch lokal aktualisieren für sofortige Anzeige
                        $user = $users[$userId];
                    } else {
                        $error = 'Fehler beim Speichern der Änderungen.';
                    }
                }
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Alle Passwort-Felder sind erforderlich.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Die neuen Passwörter stimmen nicht überein.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Das neue Passwort muss mindestens 6 Zeichen lang sein.';
        } else {
            $users = loadData('users.json');
            $user = $users[$userId] ?? null;
            
            if (!$user) {
                $error = 'Benutzer nicht gefunden.';
            } else {
                // Aktuelles Passwort prüfen
                if (!password_verify($currentPassword, $user['password'])) {
                    $error = 'Das aktuelle Passwort ist falsch.';
                } else {
                    // Neues Passwort hashen und speichern
                    $users[$userId]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    $users[$userId]['updated_at'] = date('Y-m-d H:i:s');
                    
                    if (saveData('users.json', $users)) {
                        $success = 'Passwort erfolgreich geändert.';
                    } else {
                        $error = 'Fehler beim Ändern des Passworts.';
                    }
                }
            }
        }
    } elseif ($action === 'delete_account') {
        $password = $_POST['password'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';
        
        if ($confirmation !== 'ACCOUNT LÖSCHEN') {
            $error = 'Bitte geben Sie die exakte Bestätigung ein.';
        } elseif (empty($password)) {
            $error = 'Passwort ist erforderlich.';
        } else {
            $users = loadData('users.json');
            $user = $users[$userId] ?? null;
            
            if (!$user) {
                $error = 'Benutzer nicht gefunden.';
            } else {
                // Passwort prüfen
                if (!password_verify($password, $user['password'])) {
                    $error = 'Falsches Passwort.';
                } else {
                    // Alle Benutzerdaten löschen
                    $blogs = loadData('blogs.json');
                    $links = loadData('links.json');
                    $customers = loadData('customers.json');
                    
                    // Blogs des Benutzers löschen
                    $userBlogs = array_filter($blogs, function($blog) use ($userId) {
                        return ($blog['user_id'] ?? '') === $userId;
                    });
                    foreach (array_keys($userBlogs) as $blogId) {
                        unset($blogs[$blogId]);
                    }
                    
                    // Links des Benutzers löschen
                    $userLinks = array_filter($links, function($link) use ($userId) {
                        return ($link['user_id'] ?? '') === $userId;
                    });
                    foreach (array_keys($userLinks) as $linkId) {
                        unset($links[$linkId]);
                    }
                    
                    // Kunden des Benutzers löschen
                    $userCustomers = array_filter($customers, function($customer) use ($userId) {
                        return ($customer['user_id'] ?? '') === $userId;
                    });
                    foreach (array_keys($userCustomers) as $customerId) {
                        unset($customers[$customerId]);
                    }
                    
                    // Benutzer löschen
                    unset($users[$userId]);
                    
                    // Alles speichern
                    saveData('users.json', $users);
                    saveData('blogs.json', $blogs);
                    saveData('links.json', $links);
                    saveData('customers.json', $customers);
                    
                    // Session beenden und weiterleiten
                    session_unset();
                    session_destroy();
                    
                    // Erfolgsmeldung anzeigen
                    ?>
                    <!DOCTYPE html>
                    <html lang="de">
                    <head>
                        <meta charset="UTF-8">
                        <title>Account gelöscht - LinkBuilder Pro</title>
                        <link rel="stylesheet" href="assets/style.css">
                        <style>
                            .delete-success {
                                max-width: 600px; margin: 100px auto; padding: 40px;
                                text-align: center; background: white; border-radius: 10px;
                                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                            }
                            .delete-success h1 { color: #10b981; margin-bottom: 20px; }
                            .delete-success p { color: #666; margin-bottom: 30px; }
                            .delete-success a {
                                background: #4dabf7; color: white; padding: 12px 24px;
                                border-radius: 6px; text-decoration: none;
                            }
                        </style>
                    </head>
                    <body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
                        <div class="delete-success">
                            <h1>✅ Account erfolgreich gelöscht</h1>
                            <p>Ihr Account und alle zugehörigen Daten wurden permanent entfernt.</p>
                            <a href="?page=simple_login">Zur Startseite</a>
                        </div>
                    </body>
                    </html>
                    <?php
                    exit;
                }
            }
        }
    }
}

// Benutzerdaten laden
$users = loadData('users.json');
$user = $users[$userId] ?? null;

if (!$user) {
    header('Location: ?page=simple_login');
    exit;
}

// Name für Anzeige setzen (Fallback-Logik)
if (empty($user['name'])) {
    $user['name'] = $user['username'] ?? 'Benutzer';
}

// Statistiken berechnen
$blogs = loadData('blogs.json');
$links = loadData('links.json');
$customers = loadData('customers.json');

$userBlogs = array_filter($blogs, function($blog) use ($userId) {
    return ($blog['user_id'] ?? '') === $userId;
});

$userLinks = array_filter($links, function($link) use ($userId) {
    return ($link['user_id'] ?? '') === $userId;
});

$userCustomers = array_filter($customers, function($customer) use ($userId) {
    return ($customer['user_id'] ?? '') === $userId;
});

if ($action === 'index'): ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">Mein Profil</h1>
            <p class="page-subtitle">Verwalten Sie Ihre Kontoeinstellungen</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" style="display: flex; align-items: center; gap: 8px; margin: 20px; padding: 15px; background: #ffe8e8; color: #d63031; border: 1px solid #fab1a0; border-radius: 8px;">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success" style="display: flex; align-items: center; gap: 8px; margin: 20px; padding: 15px; background: #e8f5e8; color: #00b894; border: 1px solid #81ecec; border-radius: 8px;">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success) ?>
        </div>
        <script>
            // Erfolgsmeldung nach 5 Sekunden ausblenden
            setTimeout(function() {
                const successAlert = document.querySelector('.alert-success');
                if (successAlert) {
                    successAlert.style.opacity = '0';
                    successAlert.style.transition = 'opacity 0.5s';
                    setTimeout(() => successAlert.remove(), 500);
                }
            }, 5000);
        </script>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="showTab('profile')">Profil</button>
        <button class="tab" onclick="showTab('password')">Passwort</button>
        <button class="tab" onclick="showTab('statistics')">Statistiken</button>
        <button class="tab" onclick="showTab('danger')">Gefahrenzone</button>
    </div>

    <!-- Profil Tab -->
    <div id="profileTab" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Persönliche Informationen</h3>
                <p class="card-subtitle">Aktualisieren Sie Ihre Profildaten</p>
            </div>
            <div class="card-body">
                <form method="post" action="?page=profile&action=update_profile">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name" class="form-label">Name *</label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                class="form-control" 
                                value="<?= htmlspecialchars($user['name'] ?? $user['username'] ?? '') ?>"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">E-Mail *</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-control" 
                                value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="company" class="form-label">Unternehmen</label>
                            <input 
                                type="text" 
                                id="company" 
                                name="company" 
                                class="form-control" 
                                value="<?= htmlspecialchars($user['company'] ?? '') ?>"
                            >
                        </div>
                        <div class="form-group">
                            <label for="phone" class="form-label">Telefon</label>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                class="form-control" 
                                value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                            >
                        </div>
                    </div>

                    <div style="margin-top: 24px;">
                        <button type="submit" class="btn btn-success" id="profileSaveBtn">
                            <i class="fas fa-save"></i>
                            Profil aktualisieren
                        </button>
                        <div id="profileSaveStatus" style="display: none; margin-top: 10px; padding: 8px; border-radius: 4px;"></div>
                    </div>
                </form>

                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #3a3d52;">
                    <div class="link-meta">
                        <div class="meta-item">
                            <div class="meta-label">Registriert seit</div>
                            <div class="meta-value"><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></div>
                        </div>
                        <?php if (!empty($user['updated_at'])): ?>
                            <div class="meta-item">
                                <div class="meta-label">Zuletzt aktualisiert</div>
                                <div class="meta-value"><?= date('d.m.Y H:i', strtotime($user['updated_at'])) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Passwort Tab -->
    <div id="passwordTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Passwort ändern</h3>
                <p class="card-subtitle">Aktualisieren Sie Ihr Passwort für mehr Sicherheit</p>
            </div>
            <div class="card-body">
                <form method="post" action="?page=profile&action=change_password">
                    <div class="form-group">
                        <label for="current_password" class="form-label">Aktuelles Passwort *</label>
                        <input 
                            type="password" 
                            id="current_password" 
                            name="current_password" 
                            class="form-control" 
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password" class="form-label">Neues Passwort *</label>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                class="form-control" 
                                minlength="6"
                                required
                            >
                            <small style="color: #8b8fa3; font-size: 12px;">Mindestens 6 Zeichen</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Passwort bestätigen *</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-control" 
                                minlength="6"
                                required
                            >
                        </div>
                    </div>

                    <div style="margin-top: 24px;">
                        <button type="submit" class="btn btn-success" id="passwordSaveBtn">
                            <i class="fas fa-key"></i>
                            Passwort ändern
                        </button>
                        <div id="passwordSaveStatus" style="display: none; margin-top: 10px; padding: 8px; border-radius: 4px;"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Statistiken Tab -->
    <div id="statisticsTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Meine Statistiken</h3>
                <p class="card-subtitle">Überblick über Ihre LinkBuilder-Aktivitäten</p>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #2dd4bf; margin-bottom: 8px;">
                            <?= count($userBlogs) ?>
                        </div>
                        <div style="font-size: 14px; color: #8b8fa3;">Registrierte Blogs</div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #4dabf7; margin-bottom: 8px;">
                            <?= count($userLinks) ?>
                        </div>
                        <div style="font-size: 14px; color: #8b8fa3;">Erstellte Links</div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #fbbf24; margin-bottom: 8px;">
                            <?= count($userCustomers) ?>
                        </div>
                        <div style="font-size: 14px; color: #8b8fa3;">Verwaltete Kunden</div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #10b981; margin-bottom: 8px;">
                            <?= count(array_filter($userLinks, function($l) { return ($l['status'] ?? '') === 'aktiv'; })) ?>
                        </div>
                        <div style="font-size: 14px; color: #8b8fa3;">Aktive Links</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gefahrenzone Tab -->
    <div id="dangerTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title" style="color: #ef4444;">Gefahrenzone</h3>
                <p class="card-subtitle">Irreversible Aktionen - Vorsicht geboten!</p>
            </div>
            <div class="card-body">
                <div style="padding: 20px; background-color: #472f2f; border: 1px solid #ef4444; border-radius: 8px;">
                    <h4 style="color: #ef4444; margin-bottom: 12px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Account dauerhaft löschen
                    </h4>
                    <p style="color: #8b8fa3; margin-bottom: 16px;">
                        Diese Aktion löscht Ihren Account und alle zugehörigen Daten permanent. 
                        Dazu gehören alle Ihre Blogs, Links und Kunden. Diese Aktion kann nicht rückgängig gemacht werden.
                    </p>
                    
                    <button type="button" class="btn btn-danger" onclick="showDeleteForm()">
                        <i class="fas fa-trash"></i>
                        Account löschen
                    </button>

                    <div id="deleteForm" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ef4444;">
                        <form method="post" action="?page=profile&action=delete_account" onsubmit="return confirmDelete()">
                            <div class="form-group">
                                <label for="confirmation" class="form-label">
                                    Geben Sie <strong style="color: #ef4444;">"ACCOUNT LÖSCHEN"</strong> ein, um zu bestätigen:
                                </label>
                                <input 
                                    type="text" 
                                    id="confirmation" 
                                    name="confirmation" 
                                    class="form-control" 
                                    placeholder="ACCOUNT LÖSCHEN"
                                    required
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="delete_password" class="form-label">Ihr aktuelles Passwort:</label>
                                <input 
                                    type="password" 
                                    id="delete_password" 
                                    name="password" 
                                    class="form-control" 
                                    required
                                >
                            </div>

                            <div style="display: flex; gap: 12px; margin-top: 20px;">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i>
                                    Endgültig löschen
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="hideDeleteForm()">
                                    Abbrechen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<script>
function showTab(tabName) {
    // Alle Tabs verstecken
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Alle Tab-Buttons deaktivieren
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Gewählten Tab anzeigen
    document.getElementById(tabName + 'Tab').style.display = 'block';
    event.target.classList.add('active');
}

function showDeleteForm() {
    document.getElementById('deleteForm').style.display = 'block';
}

function hideDeleteForm() {
    document.getElementById('deleteForm').style.display = 'none';
}

function confirmDelete() {
    return confirm('WARNUNG: Diese Aktion löscht Ihren Account und alle Daten permanent. Sind Sie sich absolut sicher?');
}

// Passwort bestätigen
document.getElementById('new_password')?.addEventListener('input', function() {
    const confirmField = document.getElementById('confirm_password');
    if (confirmField && confirmField.value) {
        if (this.value !== confirmField.value) {
            confirmField.setCustomValidity('Passwörter stimmen nicht überein');
        } else {
            confirmField.setCustomValidity('');
        }
    }
});

document.getElementById('confirm_password')?.addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    if (this.value !== newPassword) {
        this.setCustomValidity('Passwörter stimmen nicht überein');
    } else {
        this.setCustomValidity('');
    }
});

// Form-Feedback für bessere UX
document.addEventListener('DOMContentLoaded', function() {
    // Profil-Form
    const profileForm = document.querySelector('form[action*="update_profile"]');
    if (profileForm) {
        profileForm.addEventListener('submit', function() {
            const btn = document.getElementById('profileSaveBtn');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Speichern...';
                btn.disabled = true;
            }
        });
    }

    // Passwort-Form
    const passwordForm = document.querySelector('form[action*="change_password"]');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function() {
            const btn = document.getElementById('passwordSaveBtn');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ändern...';
                btn.disabled = true;
            }
        });
        
        // Passwort-Felder zurücksetzen nach erfolgreichem Speichern
        <?php if (isset($success) && $action === 'change_password'): ?>
        document.getElementById('current_password').value = '';
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
        <?php endif; ?>
    }

    // Änderungen verfolgen für bessere UX
    const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"]');
    inputs.forEach(input => {
        const originalValue = input.value;
        input.addEventListener('input', function() {
            if (this.value !== originalValue) {
                this.style.borderColor = '#fbbf24';
                this.style.backgroundColor = '#fffbeb';
            } else {
                this.style.borderColor = '';
                this.style.backgroundColor = '';
            }
        });
    });
});
</script>