<?php
// Nur für Admins zugänglich
$currentUser = getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo '<div class="alert alert-danger">Zugriff verweigert. Nur Administratoren haben Zugang zu dieser Seite.</div>';
    return;
}

$action = $_GET['action'] ?? 'index';
$userId = $_GET['id'] ?? null;

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $status = $_POST['status'] ?? 'active';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        
        if (empty($username) || empty($email) || empty($password)) {
            $error = 'Benutzername, E-Mail und Passwort sind Pflichtfelder.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse.';
        } elseif (strlen($password) < 6) {
            $error = 'Passwort muss mindestens 6 Zeichen lang sein.';
        } else {
            $users = loadData('users.json');
            
            // Prüfen ob Benutzername oder E-Mail bereits existiert
            $userExists = false;
            foreach ($users as $existingUser) {
                if (strtolower($existingUser['username']) === strtolower($username) || 
                    strtolower($existingUser['email']) === strtolower($email)) {
                    $userExists = true;
                    break;
                }
            }
            
            if ($userExists) {
                $error = 'Benutzername oder E-Mail bereits vergeben.';
            } else {
                $newId = generateId();
                $users[$newId] = [
                    'id' => $newId,
                    'username' => $username,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'status' => $status,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'created_at' => date('Y-m-d H:i:s'),
                    'last_login' => null,
                    'avatar' => null
                ];
                
                if (saveData('users.json', $users)) {
                    redirectWithMessage('?page=admin_users', 'Benutzer erfolgreich erstellt.');
                } else {
                    $error = 'Fehler beim Speichern des Benutzers.';
                }
            }
        }
    } elseif ($action === 'edit' && $userId) {
        $users = loadData('users.json');
        if (isset($users[$userId])) {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'user';
            $status = $_POST['status'] ?? 'active';
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $newPassword = $_POST['password'] ?? '';
            
            if (empty($username) || empty($email)) {
                $error = 'Benutzername und E-Mail sind Pflichtfelder.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Ungültige E-Mail-Adresse.';
            } else {
                // Prüfen ob Benutzername oder E-Mail bereits von anderem User verwendet wird
                $conflict = false;
                foreach ($users as $id => $user) {
                    if ($id !== $userId && (
                        strtolower($user['username']) === strtolower($username) || 
                        strtolower($user['email']) === strtolower($email)
                    )) {
                        $conflict = true;
                        break;
                    }
                }
                
                if ($conflict) {
                    $error = 'Benutzername oder E-Mail bereits von anderem Benutzer verwendet.';
                } else {
                    $users[$userId] = array_merge($users[$userId], [
                        'username' => $username,
                        'email' => $email,
                        'role' => $role,
                        'status' => $status,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Passwort nur ändern wenn eines eingegeben wurde
                    if (!empty($newPassword)) {
                        if (strlen($newPassword) < 6) {
                            $error = 'Passwort muss mindestens 6 Zeichen lang sein.';
                        } else {
                            $users[$userId]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                        }
                    }
                    
                    if (!isset($error) && saveData('users.json', $users)) {
                        redirectWithMessage("?page=admin_users&action=view&id=$userId", 'Benutzer erfolgreich aktualisiert.');
                    } elseif (!isset($error)) {
                        $error = 'Fehler beim Aktualisieren des Benutzers.';
                    }
                }
            }
        }
    } elseif ($action === 'delete' && $userId) {
        $users = loadData('users.json');
        if (isset($users[$userId]) && $userId !== getCurrentUserId()) {
            unset($users[$userId]);
            if (saveData('users.json', $users)) {
                redirectWithMessage('?page=admin_users', 'Benutzer erfolgreich gelöscht.');
            } else {
                $error = 'Fehler beim Löschen des Benutzers.';
            }
        } else {
            $error = 'Benutzer kann nicht gelöscht werden.';
        }
    } elseif ($action === 'toggle_status' && $userId) {
        $users = loadData('users.json');
        if (isset($users[$userId]) && $userId !== getCurrentUserId()) {
            $users[$userId]['status'] = $users[$userId]['status'] === 'active' ? 'inactive' : 'active';
            $users[$userId]['updated_at'] = date('Y-m-d H:i:s');
            
            if (saveData('users.json', $users)) {
                $statusText = $users[$userId]['status'] === 'active' ? 'aktiviert' : 'deaktiviert';
                redirectWithMessage('?page=admin_users', "Benutzer erfolgreich $statusText.");
            } else {
                $error = 'Fehler beim Ändern des Benutzerstatus.';
            }
        }
    }
}

// Daten laden
$users = loadData('users.json');

// Statistiken berechnen
$totalUsers = count($users);
$activeUsers = count(array_filter($users, function($u) { return $u['status'] === 'active'; }));
$adminUsers = count(array_filter($users, function($u) { return $u['role'] === 'admin'; }));
$inactiveUsers = $totalUsers - $activeUsers;

if ($action === 'index'): ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">Benutzer-Verwaltung</h1>
            <p class="page-subtitle">Verwalten Sie alle Systembenutzer</p>
        </div>
        <div class="action-buttons">
            <a href="?page=admin_users&action=create" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Benutzer hinzufügen
            </a>
        </div>
    </div>

    <?php showFlashMessage(); ?>

    <!-- Statistiken Dashboard -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #2dd4bf, #14b8a6);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $totalUsers ?></div>
                <div class="stat-label">Gesamt Benutzer</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $activeUsers ?></div>
                <div class="stat-label">Aktive Benutzer</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $adminUsers ?></div>
                <div class="stat-label">Administratoren</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $inactiveUsers ?></div>
                <div class="stat-label">Inaktive Benutzer</div>
            </div>
        </div>
    </div>

    <!-- Filter und Suche -->
    <div class="action-bar">
        <div class="search-bar">
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input 
                    type="text" 
                    class="form-control search-input" 
                    placeholder="Benutzer durchsuchen"
                    id="userSearch"
                    onkeyup="filterUsers()"
                >
            </div>
        </div>
        <div class="filters">
            <select class="form-control filter-select" id="roleFilter" onchange="filterUsers()">
                <option value="">Alle Rollen</option>
                <option value="admin">Administrator</option>
                <option value="user">Benutzer</option>
            </select>
            <select class="form-control filter-select" id="statusFilter" onchange="filterUsers()">
                <option value="">Alle Status</option>
                <option value="active">Aktiv</option>
                <option value="inactive">Inaktiv</option>
            </select>
        </div>
    </div>

    <!-- Benutzer-Tabelle -->
    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table class="table" id="usersTable">
                    <thead>
                        <tr>
                            <th>Benutzer</th>
                            <th>E-Mail</th>
                            <th>Rolle</th>
                            <th>Status</th>
                            <th>Letzter Login</th>
                            <th>Erstellt</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $id => $user): ?>
                            <tr class="user-row" 
                                data-username="<?= strtolower($user['username']) ?>"
                                data-email="<?= strtolower($user['email']) ?>"
                                data-role="<?= $user['role'] ?>"
                                data-status="<?= $user['status'] ?>">
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name">
                                                <a href="?page=admin_users&action=view&id=<?= $id ?>">
                                                    <?= e($user['username']) ?>
                                                </a>
                                            </div>
                                            <?php if (!empty($user['first_name']) || !empty($user['last_name'])): ?>
                                                <div class="user-fullname">
                                                    <?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="mailto:<?= e($user['email']) ?>" class="email-link">
                                        <?= e($user['email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    $roleClass = $user['role'] === 'admin' ? 'badge-warning' : 'badge-secondary';
                                    $roleText = $user['role'] === 'admin' ? 'Administrator' : 'Benutzer';
                                    ?>
                                    <span class="badge <?= $roleClass ?>"><?= $roleText ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = $user['status'] === 'active' ? 'badge-success' : 'badge-danger';
                                    $statusText = $user['status'] === 'active' ? 'Aktiv' : 'Inaktiv';
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <span class="text-muted">
                                        <?= $user['last_login'] ? formatDateTime($user['last_login']) : 'Noch nie' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted">
                                        <?= formatDate($user['created_at']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?page=admin_users&action=view&id=<?= $id ?>" class="btn btn-sm btn-primary" title="Anzeigen">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?page=admin_users&action=edit&id=<?= $id ?>" class="btn btn-sm btn-secondary" title="Bearbeiten">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($id !== getCurrentUserId()): ?>
                                            <a href="?page=admin_users&action=toggle_status&id=<?= $id ?>" 
                                               class="btn btn-sm <?= $user['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>" 
                                               title="<?= $user['status'] === 'active' ? 'Deaktivieren' : 'Aktivieren' ?>"
                                               onclick="return confirm('Status wirklich ändern?')">
                                                <i class="fas fa-<?= $user['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                            </a>
                                            <a href="?page=admin_users&action=delete&id=<?= $id ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Benutzer wirklich löschen?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<style>
/* Zusätzliche CSS-Styles für die Benutzer-Verwaltung */
.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4dabf7, #339af0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 16px;
}

.user-details {
    flex: 1;
}

.user-name a {
    color: #4dabf7;
    text-decoration: none;
    font-weight: 600;
}

.user-name a:hover {
    text-decoration: underline;
}

.user-fullname {
    font-size: 12px;
    color: #8b8fa3;
    margin-top: 2px;
}

.email-link {
    color: #4dabf7;
    text-decoration: none;
}

.email-link:hover {
    text-decoration: underline;
}

.action-buttons {
    display: flex;
    gap: 4px;
}

.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 30px 0 20px 0;
    gap: 20px;
}

.search-bar {
    flex: 1;
    max-width: 400px;
}

.search-container {
    position: relative;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #8b8fa3;
    font-size: 14px;
}

.search-input {
    padding-left: 40px;
}

.filters {
    display: flex;
    gap: 12px;
}

.filter-select {
    min-width: 150px;
}

/* Stats Grid für bessere Darstellung */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    display: flex;
    align-items: center;
    background: #2a2d3e;
    border: 1px solid #3a3d52;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
    color: white;
    font-size: 24px;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #fff;
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 14px;
    color: #8b8fa3;
    font-weight: 500;
}
</style>

<?php elseif ($action === 'view' && $userId): 
    $user = $users[$userId] ?? null;
    if (!$user) {
        header('HTTP/1.0 404 Not Found');
        include 'pages/404.php';
        return;
    }
    
    // Benutzer-Statistiken laden
    $blogs = loadData('blogs.json');
    $links = loadData('links.json');
    $customers = loadData('customers.json');
    
    $userBlogs = array_filter($blogs, function($blog) use ($userId) {
        return $blog['user_id'] === $userId;
    });
    
    $userLinks = array_filter($links, function($link) use ($userId) {
        return $link['user_id'] === $userId;
    });
    
    $userCustomers = array_filter($customers, function($customer) use ($userId) {
        return $customer['user_id'] === $userId;
    });
?>
    <div class="breadcrumb">
        <a href="?page=admin_users">Zurück zur Benutzer-Verwaltung</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= e($user['username']) ?></span>
    </div>

    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #4dabf7, #339af0); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px;">
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
            </div>
            <div>
                <h1 class="page-title"><?= e($user['username']) ?></h1>
                <p class="page-subtitle"><?= e($user['email']) ?></p>
            </div>
        </div>
        <div class="action-buttons">
            <a href="?page=admin_users&action=edit&id=<?= $userId ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Bearbeiten
            </a>
            <?php if ($userId !== getCurrentUserId()): ?>
                <a href="?page=admin_users&action=delete&id=<?= $userId ?>" class="btn btn-danger" onclick="return confirm('Benutzer wirklich löschen?')">
                    <i class="fas fa-trash"></i> Löschen
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php showFlashMessage(); ?>

    <!-- Benutzer-Informationen -->
    <div class="content-grid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Benutzer-Informationen</h3>
            </div>
            <div class="card-body">
                <div class="link-meta">
                    <div class="meta-item">
                        <div class="meta-label">Vollständiger Name</div>
                        <div class="meta-value">
                            <?= !empty($user['first_name']) || !empty($user['last_name']) ? 
                                e(trim($user['first_name'] . ' ' . $user['last_name'])) : 
                                '<span style="color: #8b8fa3;">Nicht angegeben</span>' ?>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Rolle</div>
                        <div class="meta-value">
                            <span class="badge <?= $user['role'] === 'admin' ? 'badge-warning' : 'badge-secondary' ?>">
                                <?= $user['role'] === 'admin' ? 'Administrator' : 'Benutzer' ?>
                            </span>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Status</div>
                        <div class="meta-value">
                            <span class="badge <?= $user['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                <?= $user['status'] === 'active' ? 'Aktiv' : 'Inaktiv' ?>
                            </span>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Erstellt am</div>
                        <div class="meta-value"><?= formatDateTime($user['created_at']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Letzter Login</div>
                        <div class="meta-value">
                            <?= $user['last_login'] ? formatDateTime($user['last_login']) : '<span style="color: #8b8fa3;">Noch nie</span>' ?>
                        </div>
                    </div>
                    <?php if (!empty($user['updated_at'])): ?>
                        <div class="meta-item">
                            <div class="meta-label">Zuletzt aktualisiert</div>
                            <div class="meta-value"><?= formatDateTime($user['updated_at']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Aktivitäts-Statistiken -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Aktivitäts-Statistiken</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                        <div style="font-size: 28px; font-weight: bold; color: #2dd4bf; margin-bottom: 4px;">
                            <?= count($userBlogs) ?>
                        </div>
                        <div style="font-size: 12px; color: #8b8fa3;">Blogs</div>
                    </div>
                    <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                        <div style="font-size: 28px; font-weight: bold; color: #4dabf7; margin-bottom: 4px;">
                            <?= count($userLinks) ?>
                        </div>
                        <div style="font-size: 12px; color: #8b8fa3;">Links</div>
                    </div>
                </div>
                
                <div style="margin-top: 16px; text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                    <div style="font-size: 28px; font-weight: bold; color: #f59e0b; margin-bottom: 4px;">
                        <?= count($userCustomers) ?>
                    </div>
                    <div style="font-size: 12px; color: #8b8fa3;">Kunden</div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'create' || ($action === 'edit' && $userId)): 
    $user = null;
    if ($action === 'edit') {
        $user = $users[$userId] ?? null;
        if (!$user) {
            header('HTTP/1.0 404 Not Found');
            include 'pages/404.php';
            return;
        }
    }
?>
    <div class="breadcrumb">
        <a href="?page=admin_users">Zurück zur Benutzer-Verwaltung</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= $action === 'create' ? 'Benutzer hinzufügen' : 'Benutzer bearbeiten' ?></span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title"><?= $action === 'create' ? 'Neuen Benutzer hinzufügen' : 'Benutzer bearbeiten' ?></h1>
            <p class="page-subtitle">Füllen Sie das Formular aus, um einen <?= $action === 'create' ? 'neuen Benutzer zu erstellen' : 'Benutzer zu aktualisieren' ?></p>
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
                        <label for="username" class="form-label">Benutzername *</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            placeholder="Benutzername"
                            value="<?= e($_POST['username'] ?? $user['username'] ?? '') ?>"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">E-Mail-Adresse *</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="benutzer@example.com"
                            value="<?= e($_POST['email'] ?? $user['email'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name" class="form-label">Vorname</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="form-control" 
                            placeholder="Vorname"
                            value="<?= e($_POST['first_name'] ?? $user['first_name'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="last_name" class="form-label">Nachname</label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            class="form-control" 
                            placeholder="Nachname"
                            value="<?= e($_POST['last_name'] ?? $user['last_name'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="role" class="form-label">Rolle *</label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="user" <?= ($_POST['role'] ?? $user['role'] ?? '') === 'user' ? 'selected' : '' ?>>Benutzer</option>
                            <option value="admin" <?= ($_POST['role'] ?? $user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status" class="form-label">Status *</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" <?= ($_POST['status'] ?? $user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktiv</option>
                            <option value="inactive" <?= ($_POST['status'] ?? $user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inaktiv</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        Passwort <?= $action === 'create' ? '*' : '(leer lassen für keine Änderung)' ?>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Mindestens 6 Zeichen"
                        <?= $action === 'create' ? 'required' : '' ?>
                    >
                    <?php if ($action === 'edit'): ?>
                        <small style="color: #8b8fa3; font-size: 12px;">Lassen Sie das Feld leer, um das aktuelle Passwort beizubehalten</small>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        <?= $action === 'create' ? 'Benutzer erstellen' : 'Änderungen speichern' ?>
                    </button>
                    <a href="?page=admin_users<?= $action === 'edit' ? "&action=view&id=$userId" : '' ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>

<script>
function filterUsers() {
    const search = document.getElementById('userSearch').value.toLowerCase();
    const roleFilter = document.getElementById('roleFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('.user-row');
    
    rows.forEach(row => {
        const username = row.dataset.username || '';
        const email = row.dataset.email || '';
        const role = row.dataset.role || '';
        const status = row.dataset.status || '';
        
        const searchMatch = !search || username.includes(search) || email.includes(search);
        const roleMatch = !roleFilter || role === roleFilter;
        const statusMatch = !statusFilter || status === statusFilter;
        
        const matches = searchMatch && roleMatch && statusMatch;
        row.style.display = matches ? '' : 'none';
    });
}
</script>