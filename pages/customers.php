<?php
session_start();
require_once 'includes/functions.php';

// Authentifizierung prüfen
if (!getCurrentUserId()) {
    header('Location: ?page=login');
    exit;
}

$action = $_GET['action'] ?? 'index';
$customerId = $_GET['id'] ?? null;
$userId = getCurrentUserId();

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $name = sanitizeString($_POST['name'] ?? '');
        $email = sanitizeString($_POST['email'] ?? '');
        $phone = sanitizeString($_POST['phone'] ?? '');
        $company = sanitizeString($_POST['company'] ?? '');
        $address = sanitizeString($_POST['address'] ?? '');
        $city = sanitizeString($_POST['city'] ?? '');
        $postal_code = sanitizeString($_POST['postal_code'] ?? '');
        $country = sanitizeString($_POST['country'] ?? '');
        $notes = sanitizeString($_POST['notes'] ?? '');
        $status = $_POST['status'] ?? 'aktiv';
        
        // Websites verarbeiten
        $websites = [];
        if (!empty($_POST['websites'])) {
            foreach ($_POST['websites'] as $websiteData) {
                $url = trim($websiteData['url'] ?? '');
                $title = trim($websiteData['title'] ?? '');
                $description = trim($websiteData['description'] ?? '');
                
                if (!empty($url)) {
                    // Auto-format URL
                    if (!preg_match('/^https?:\/\//', $url)) {
                        $url = 'https://' . $url;
                    }
                    
                    $websites[] = [
                        'url' => $url,
                        'title' => $title ?: parse_url($url, PHP_URL_HOST),
                        'description' => $description,
                        'added_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }
        
        $errors = [];
        
        // Validierung
        if (empty($name)) {
            $errors[] = 'Name ist ein Pflichtfeld.';
        }
        if (!empty($email) && !validateEmail($email)) {
            $errors[] = 'Ungültige E-Mail-Adresse.';
        }
        
        // Website-URLs validieren
        foreach ($websites as $website) {
            if (!validateUrl($website['url'])) {
                $errors[] = 'Ungültige Website-URL: ' . $website['url'];
            }
        }
        
        if (empty($errors)) {
            $customers = loadData('customers.json');
            $newId = generateId();
            
            $customers[$newId] = [
                'id' => $newId,
                'user_id' => $userId,
                'name' => $name,
                'email' => $email,
                'websites' => $websites,
                'phone' => $phone,
                'company' => $company,
                'address' => $address,
                'city' => $city,
                'postal_code' => $postal_code,
                'country' => $country,
                'notes' => $notes,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (saveData('customers.json', $customers)) {
                redirectWithMessage('?page=customers', 'Kunde erfolgreich erstellt.');
            } else {
                $errors[] = 'Fehler beim Speichern des Kunden.';
            }
        }
    } elseif ($action === 'edit' && $customerId) {
        $customers = loadData('customers.json');
        if (isset($customers[$customerId]) && ($isAdmin || $customers[$customerId]['user_id'] === $userId)) {
            $name = sanitizeString($_POST['name'] ?? '');
            $email = sanitizeString($_POST['email'] ?? '');
            $phone = sanitizeString($_POST['phone'] ?? '');
            $company = sanitizeString($_POST['company'] ?? '');
            $address = sanitizeString($_POST['address'] ?? '');
            $city = sanitizeString($_POST['city'] ?? '');
            $postal_code = sanitizeString($_POST['postal_code'] ?? '');
            $country = sanitizeString($_POST['country'] ?? '');
            $notes = sanitizeString($_POST['notes'] ?? '');
            $status = $_POST['status'] ?? 'aktiv';
            
            // Websites verarbeiten
            $websites = [];
            if (!empty($_POST['websites'])) {
                foreach ($_POST['websites'] as $websiteData) {
                    $url = trim($websiteData['url'] ?? '');
                    $title = trim($websiteData['title'] ?? '');
                    $description = trim($websiteData['description'] ?? '');
                    
                    if (!empty($url)) {
                        // Auto-format URL
                        if (!preg_match('/^https?:\/\//', $url)) {
                            $url = 'https://' . $url;
                        }
                        
                        $websites[] = [
                            'url' => $url,
                            'title' => $title ?: parse_url($url, PHP_URL_HOST),
                            'description' => $description,
                            'added_at' => date('Y-m-d H:i:s')
                        ];
                    }
                }
            }
            
            $errors = [];
            
            // Validierung
            if (empty($name)) {
                $errors[] = 'Name ist ein Pflichtfeld.';
            }
            if (!empty($email) && !validateEmail($email)) {
                $errors[] = 'Ungültige E-Mail-Adresse.';
            }
            
            // Website-URLs validieren
            foreach ($websites as $website) {
                if (!validateUrl($website['url'])) {
                    $errors[] = 'Ungültige Website-URL: ' . $website['url'];
                }
            }
            
            if (empty($errors)) {
                $customers[$customerId] = array_merge($customers[$customerId], [
                    'name' => $name,
                    'email' => $email,
                    'websites' => $websites,
                    'phone' => $phone,
                    'company' => $company,
                    'address' => $address,
                    'city' => $city,
                    'postal_code' => $postal_code,
                    'country' => $country,
                    'notes' => $notes,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                if (saveData('customers.json', $customers)) {
                    redirectWithMessage("?page=customers&action=view&id=$customerId", 'Kunde erfolgreich aktualisiert.');
                } else {
                    $errors[] = 'Fehler beim Aktualisieren des Kunden.';
                }
            }
        }
    } elseif ($action === 'delete' && $customerId) {
        $customers = loadData('customers.json');
        if (isset($customers[$customerId]) && ($isAdmin || $customers[$customerId]['user_id'] === $userId)) {
            unset($customers[$customerId]);
            if (saveData('customers.json', $customers)) {
                redirectWithMessage('?page=customers', 'Kunde erfolgreich gelöscht.');
            } else {
                setFlashMessage('Fehler beim Löschen des Kunden.', 'error');
            }
        }
    } elseif ($action === 'import_process') {
        // CSV Import verarbeiten
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
            $csvFile = $_FILES['csv_file']['tmp_name'];
            $hasHeader = isset($_POST['has_header']);
            
            $customers = loadData('customers.json');
            $imported = 0;
            $errors = [];
            
            if (($handle = fopen($csvFile, 'r')) !== FALSE) {
                $row = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $row++;
                    
                    // Header-Zeile überspringen
                    if ($hasHeader && $row === 1) {
                        continue;
                    }
                    
                    // Daten zuweisen (erwartete Reihenfolge: Name, E-Mail, Telefon, Unternehmen, Adresse, Stadt, PLZ, Land, Notizen)
                    $name = sanitizeString($data[0] ?? '');
                    $email = sanitizeString($data[1] ?? '');
                    $phone = sanitizeString($data[2] ?? '');
                    $company = sanitizeString($data[3] ?? '');
                    $address = sanitizeString($data[4] ?? '');
                    $city = sanitizeString($data[5] ?? '');
                    $postal_code = sanitizeString($data[6] ?? '');
                    $country = sanitizeString($data[7] ?? '');
                    $notes = sanitizeString($data[8] ?? '');
                    
                    // Validierung
                    if (empty($name)) {
                        $errors[] = "Zeile $row: Name ist erforderlich";
                        continue;
                    }
                    
                    if (!empty($email) && !validateEmail($email)) {
                        $errors[] = "Zeile $row: Ungültige E-Mail-Adresse";
                        continue;
                    }
                    
                    // Kunde erstellen
                    $newId = generateId();
                    $customers[$newId] = [
                        'id' => $newId,
                        'user_id' => $userId,
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'company' => $company,
                        'address' => $address,
                        'city' => $city,
                        'postal_code' => $postal_code,
                        'country' => $country,
                        'notes' => $notes,
                        'status' => 'aktiv',
                        'websites' => [],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $imported++;
                }
                fclose($handle);
                
                if (saveData('customers.json', $customers)) {
                    $message = "Import erfolgreich: $imported Kunden importiert.";
                    if (!empty($errors)) {
                        $message .= " Fehler: " . implode(', ', array_slice($errors, 0, 3));
                        if (count($errors) > 3) {
                            $message .= ' und ' . (count($errors) - 3) . ' weitere...';
                        }
                    }
                    redirectWithMessage('?page=customers', $message);
                } else {
                    $errors[] = 'Fehler beim Speichern der importierten Daten.';
                }
            } else {
                $errors[] = 'CSV-Datei konnte nicht gelesen werden.';
            }
        } else {
            $errors[] = 'Bitte wählen Sie eine gültige CSV-Datei aus.';
        }
    }
}

// Daten laden
$customers = loadData('customers.json');
$links = loadData('links.json');

// Admin-Logik: Admin sieht alle Kunden, normale Benutzer nur ihre eigenen
$users = loadData('users.json');
$currentUser = $users[$userId] ?? null;
$isAdmin = $currentUser && ($currentUser['role'] === 'admin');

if ($isAdmin) {
    // Admin sieht alle Kunden
    $userCustomers = $customers;
} else {
    // Benutzer-spezifische Kunden
    $userCustomers = array_filter($customers, function($customer) use ($userId) {
        return getArrayValue($customer, 'user_id') === $userId;
    });
}

// Statistiken berechnen
$activeCustomers = array_filter($userCustomers, function($customer) {
    return getArrayValue($customer, 'status', 'aktiv') === 'aktiv';
});

$inactiveCustomers = array_filter($userCustomers, function($customer) {
    return getArrayValue($customer, 'status', 'aktiv') === 'inaktiv';
});

// Länder-Statistiken
$countryStats = [];
foreach ($userCustomers as $customer) {
    $country = getArrayValue($customer, 'country', 'Unbekannt');
    $countryStats[$country] = ($countryStats[$country] ?? 0) + 1;
}

if ($action === 'index'): ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">
                Kundenverwaltung
                <?php if ($isAdmin): ?>
                    <span class="badge badge-info" style="font-size: 12px; margin-left: 8px;">Admin-Ansicht</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <?php if ($isAdmin): ?>
                    Verwalten Sie alle Kunden im System
                <?php else: ?>
                    Verwalten Sie Ihre Kunden und Kontakte
                <?php endif; ?>
            </p>
        </div>
        <div class="action-buttons">
            <a href="?page=customers&action=create" class="btn btn-primary">
                <i class="fas fa-plus"></i> Neuer Kunde
            </a>
            <a href="?page=customers&action=import" class="btn btn-secondary">
                <i class="fas fa-upload"></i> Import
            </a>
            <a href="?page=customers&action=export" class="btn btn-secondary">
                <i class="fas fa-download"></i> Export
            </a>
        </div>
    </div>

    <?php showFlashMessage(); ?>

    <!-- Statistik-Dashboard -->
    <div class="content-grid">
        <!-- Kunden-Statistiken -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Kunden-Übersicht</h3>
                <p class="card-subtitle">
                    <?php if ($isAdmin): ?>
                        Schneller Überblick über alle Kunden im System
                    <?php else: ?>
                        Schneller Überblick über Ihre Kunden
                    <?php endif; ?>
                </p>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                    <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                        <div style="font-size: 28px; font-weight: bold; color: #2dd4bf; margin-bottom: 4px;">
                            <?= count($userCustomers) ?>
                        </div>
                        <div style="font-size: 12px; color: #8b8fa3;">Gesamt Kunden</div>
                    </div>
                    <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                        <div style="font-size: 28px; font-weight: bold; color: #4dabf7; margin-bottom: 4px;">
                            <?= count($activeCustomers) ?>
                        </div>
                        <div style="font-size: 12px; color: #8b8fa3;">Aktive Kunden</div>
                    </div>
                    <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                        <div style="font-size: 28px; font-weight: bold; color: #f97316; margin-bottom: 4px;">
                            <?= count($inactiveCustomers) ?>
                        </div>
                        <div style="font-size: 12px; color: #8b8fa3;">Inaktive Kunden</div>
                    </div>
                </div>
                
                <?php if (!empty($countryStats)): ?>
                    <div style="margin-top: 16px;">
                        <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">Top Länder</div>
                        <?php 
                        arsort($countryStats);
                        $topCountries = array_slice($countryStats, 0, 3, true);
                        foreach ($topCountries as $country => $count): 
                        ?>
                            <div style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; color: #8b8fa3;">
                                <span><?= e($country) ?></span>
                                <span><?= $count ?> Kunde<?= $count !== 1 ? 'n' : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Letzte Aktivitäten -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Letzte Aktivitäten</h3>
                <p class="card-subtitle">Kürzlich hinzugefügte oder bearbeitete Kunden</p>
            </div>
            <div class="card-body">
                <?php 
                $recentCustomers = array_slice(array_reverse($userCustomers), 0, 5);
                if (!empty($recentCustomers)): 
                ?>
                    <?php foreach ($recentCustomers as $customer): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #3a3d52;">
                            <div>
                                <div style="font-weight: 600; color: #e2e8f0; font-size: 14px;">
                                    <a href="?page=customers&action=view&id=<?= $customer['id'] ?>" style="color: #4dabf7; text-decoration: none;">
                                        <?= e($customer['name']) ?>
                                    </a>
                                </div>
                                <div style="font-size: 12px; color: #8b8fa3;">
                                    <?= e(getArrayValue($customer, 'company', 'Kein Unternehmen')) ?>
                                </div>
                            </div>
                            <div style="font-size: 11px; color: #8b8fa3;">
                                <?= formatDate($customer['created_at']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #8b8fa3; text-align: center; padding: 20px;">Keine Kunden vorhanden</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filter und Suche -->
    <div class="action-bar" style="margin-top: 30px;">
        <div class="search-bar" style="flex: 1; max-width: 400px;">
            <div style="position: relative;">
                <i class="fas fa-search search-icon" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #8b8fa3;"></i>
                <input 
                    type="text" 
                    class="form-control search-input" 
                    placeholder="Kunden durchsuchen..."
                    id="customerSearch"
                    onkeyup="filterCustomers()"
                    style="padding-left: 40px;"
                >
            </div>
        </div>
        <div style="display: flex; gap: 12px;">
            <select class="filter-select" id="statusFilter" onchange="filterCustomers()">
                <option value="">Alle Status</option>
                <option value="aktiv">Aktive Kunden</option>
                <option value="inaktiv">Inaktive Kunden</option>
            </select>
            <select class="filter-select" id="countryFilter" onchange="filterCustomers()">
                <option value="">Alle Länder</option>
                <?php foreach (array_keys($countryStats) as $country): ?>
                    <option value="<?= e($country) ?>"><?= e($country) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (empty($userCustomers)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>Keine Kunden vorhanden</h3>
            <p>Erstellen Sie Ihren ersten Kunden, um hier eine Übersicht zu sehen.</p>
            <a href="?page=customers&action=create" class="btn btn-primary" style="margin-top: 16px;">
                <i class="fas fa-plus"></i> Ersten Kunden erstellen
            </a>
        </div>
    <?php else: ?>
        <!-- Kunden-Tabelle -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Unternehmen</th>
                                <th>E-Mail</th>
                                <th>Telefon</th>
                                <th>Land</th>
                                <th>Status</th>
                                <th>Links</th>
                                <?php if ($isAdmin): ?>
                                    <th>Erstellt von</th>
                                <?php endif; ?>
                                <th>Erstellt</th>
                                <th style="width: 120px;">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody">
                            <?php foreach ($userCustomers as $customerId => $customer): 
                                // Links für diesen Kunden zählen
                                $customerLinks = array_filter($links, function($link) use ($customerId) {
                                    return getArrayValue($link, 'customer_id') === $customerId;
                                });
                                $linkCount = count($customerLinks);
                                
                                // Kunden-Besitzer Info (nur für Admin)
                                if ($isAdmin) {
                                    $customerOwner = $users[getArrayValue($customer, 'user_id', '')] ?? null;
                                    $customerOwnerName = $customerOwner ? ($customerOwner['name'] ?? $customerOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
                                    $isOwnCustomer = getArrayValue($customer, 'user_id') === $userId;
                                }
                            ?>
                                <tr class="customer-row" 
                                    data-name="<?= strtolower($customer['name']) ?>" 
                                    data-company="<?= strtolower(getArrayValue($customer, 'company', '')) ?>"
                                    data-email="<?= strtolower(getArrayValue($customer, 'email', '')) ?>"
                                    data-status="<?= strtolower(getArrayValue($customer, 'status', 'aktiv')) ?>"
                                    data-country="<?= strtolower(getArrayValue($customer, 'country', '')) ?>">
                                    <td>
                                        <div>
                                            <div style="font-weight: 600; color: #e2e8f0;">
                                                <a href="?page=customers&action=view&id=<?= $customerId ?>" style="color: #4dabf7; text-decoration: none;">
                                                    <?= e($customer['name']) ?>
                                                </a>
                                            </div>
                                            <?php if (!empty($customer['email'])): ?>
                                                <div style="font-size: 12px; color: #8b8fa3;">
                                                    <a href="mailto:<?= e($customer['email']) ?>" style="color: #8b8fa3; text-decoration: none;">
                                                        <?= e($customer['email']) ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="color: #8b8fa3;">
                                        <?= e(getArrayValue($customer, 'company', '-')) ?>
                                    </td>
                                    <td style="color: #8b8fa3;">
                                        <?php if (!empty($customer['email'])): ?>
                                            <a href="mailto:<?= e($customer['email']) ?>" style="color: #4dabf7; text-decoration: none;">
                                                <?= e($customer['email']) ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: #8b8fa3;">
                                        <?php if (!empty($customer['phone'])): ?>
                                            <a href="tel:<?= e($customer['phone']) ?>" style="color: #4dabf7; text-decoration: none;">
                                                <?= e($customer['phone']) ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: #8b8fa3;">
                                        <?= e(getArrayValue($customer, 'country', '-')) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status = getArrayValue($customer, 'status', 'aktiv');
                                        $badgeClass = $status === 'aktiv' ? 'badge-success' : 'badge-warning';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td style="color: #8b8fa3;">
                                        <?php if ($linkCount > 0): ?>
                                            <a href="?page=links&customer_id=<?= $customerId ?>" style="color: #4dabf7; text-decoration: none;">
                                                <?= $linkCount ?> Link<?= $linkCount !== 1 ? 's' : '' ?>
                                            </a>
                                        <?php else: ?>
                                            0 Links
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($isAdmin): ?>
                                        <td style="font-size: 12px; color: #8b8fa3;">
                                            <?php if ($isOwnCustomer): ?>
                                                <span style="color: #10b981;">
                                                    <i class="fas fa-crown" style="margin-right: 4px;"></i>
                                                    Sie
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #fbbf24;">
                                                    <i class="fas fa-user" style="margin-right: 4px;"></i>
                                                    <?= e($customerOwnerName) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td style="color: #8b8fa3; font-size: 12px;">
                                        <?= formatDate($customer['created_at']) ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 4px;">
                                            <a href="?page=customers&action=view&id=<?= $customerId ?>" class="btn btn-sm btn-primary" title="Anzeigen">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($isAdmin || getArrayValue($customer, 'user_id') === $userId): ?>
                                                <a href="?page=customers&action=edit&id=<?= $customerId ?>" class="btn btn-sm btn-secondary" title="Bearbeiten">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?page=customers&action=delete&id=<?= $customerId ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Kunde wirklich löschen?')">
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
    <?php endif; ?>

<?php elseif ($action === 'view' && $customerId): 
    $customer = $customers[$customerId] ?? null;
    if (!$customer || (!$isAdmin && getArrayValue($customer, 'user_id') !== $userId)) {
        echo '<div class="error-page"><div class="error-content" style="text-align: center; padding: 60px 20px;">';
        echo '<i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f56565; margin-bottom: 16px;"></i>';
        echo '<h1 style="margin-bottom: 16px;">Kunde nicht gefunden</h1>';
        echo '<p style="color: #8b8fa3; margin-bottom: 24px;">Der angeforderte Kunde existiert nicht oder Sie haben keine Berechtigung.</p>';
        echo '<a href="?page=customers" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Zurück zur Kundenverwaltung</a>';
        echo '</div></div>';
        return;
    }
    
    // Links für diesen Kunden
    $customerLinks = array_filter($links, function($link) use ($customerId) {
        return getArrayValue($link, 'customer_id') === $customerId;
    });
?>
    <div class="breadcrumb">
        <a href="?page=customers">Zurück zu Kunden</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= e($customer['name']) ?></span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title"><?= e($customer['name']) ?></h1>
            <?php if (!empty($customer['company'])): ?>
                <p class="page-subtitle"><?= e($customer['company']) ?></p>
            <?php endif; ?>
        </div>
        <div class="action-buttons">
            <a href="?page=customers&action=edit&id=<?= $customerId ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Bearbeiten
            </a>
            <a href="?page=links&action=create&customer_id=<?= $customerId ?>" class="btn btn-success">
                <i class="fas fa-link"></i> Neuer Link
            </a>
            <a href="?page=customers&action=delete&id=<?= $customerId ?>" class="btn btn-danger" onclick="return confirm('Kunde wirklich löschen?')">
                <i class="fas fa-trash"></i> Löschen
            </a>
        </div>
    </div>

    <?php showFlashMessage(); ?>

    <!-- Tabs -->
    <div class="tabs" style="display: flex; border-bottom: 2px solid #3a3d52; margin-bottom: 20px;">
        <button class="tab active" onclick="showTab('info')" style="padding: 12px 24px; background: none; border: none; color: #4dabf7; border-bottom: 2px solid #4dabf7; font-weight: 600; cursor: pointer;">
            Kundeninformationen
        </button>
        <button class="tab" onclick="showTab('links')" style="padding: 12px 24px; background: none; border: none; color: #8b8fa3; border-bottom: 2px solid transparent; font-weight: 600; cursor: pointer;">
            Links (<?= count($customerLinks) ?>)
        </button>
        <button class="tab" onclick="showTab('statistics')" style="padding: 12px 24px; background: none; border: none; color: #8b8fa3; border-bottom: 2px solid transparent; font-weight: 600; cursor: pointer;">
            Statistiken
        </button>
        <?php if (!empty($customer['websites']) && is_array($customer['websites']) && count($customer['websites']) > 1): ?>
            <button class="tab" onclick="showTab('websites')" style="padding: 12px 24px; background: none; border: none; color: #8b8fa3; border-bottom: 2px solid transparent; font-weight: 600; cursor: pointer;">
                Websites (<?= count($customer['websites']) ?>)
            </button>
        <?php endif; ?>
    </div>

    <!-- Kundeninformationen Tab -->
    <div id="infoTab" class="tab-content">
        <div class="content-grid">
            <!-- Kontaktinformationen -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Kontaktinformationen</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid" style="display: grid; gap: 16px;">
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">NAME</div>
                            <div class="info-value" style="color: #e2e8f0;"><?= e($customer['name']) ?></div>
                        </div>
                        
                        <?php if (!empty($customer['email'])): ?>
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">E-MAIL</div>
                            <div class="info-value">
                                <a href="mailto:<?= e($customer['email']) ?>" style="color: #4dabf7; text-decoration: none;">
                                    <?= e($customer['email']) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($customer['phone'])): ?>
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">TELEFON</div>
                            <div class="info-value">
                                <a href="tel:<?= e($customer['phone']) ?>" style="color: #4dabf7; text-decoration: none;">
                                    <?= e($customer['phone']) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($customer['websites']) && is_array($customer['websites'])): ?>
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">WEBSITES</div>
                            <div class="info-value">
                                <?php foreach ($customer['websites'] as $index => $website): ?>
                                    <div style="margin-bottom: 8px; padding: 8px; background: #343852; border-radius: 4px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <strong style="color: #4dabf7;">
                                                <a href="<?= e($website['url']) ?>" target="_blank" style="color: #4dabf7; text-decoration: none;">
                                                    <?= e($website['title']) ?>
                                                    <i class="fas fa-external-link-alt" style="margin-left: 4px; font-size: 10px;"></i>
                                                </a>
                                            </strong>
                                            <span style="font-size: 11px; color: #8b8fa3;">
                                                <?= formatDate($website['added_at']) ?>
                                            </span>
                                        </div>
                                        <div style="font-size: 12px; color: #8b8fa3; margin-bottom: 4px;">
                                            <?= e($website['url']) ?>
                                        </div>
                                        <?php if (!empty($website['description'])): ?>
                                            <div style="font-size: 12px; color: #8b8fa3;">
                                                <?= e($website['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php elseif (!empty($customer['website'])): ?>
                        <!-- Backward compatibility für alte einzelne Website -->
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">WEBSITE</div>
                            <div class="info-value">
                                <a href="<?= e($customer['website']) ?>" target="_blank" style="color: #4dabf7; text-decoration: none;">
                                    <?= e($customer['website']) ?>
                                    <i class="fas fa-external-link-alt" style="margin-left: 4px; font-size: 10px;"></i>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">STATUS</div>
                            <div class="info-value">
                                <span class="badge <?= getArrayValue($customer, 'status', 'aktiv') === 'aktiv' ? 'badge-success' : 'badge-warning' ?>">
                                    <?= ucfirst(getArrayValue($customer, 'status', 'aktiv')) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Firmeninformationen -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Firmeninformationen</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid" style="display: grid; gap: 16px;">
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">UNTERNEHMEN</div>
                            <div class="info-value" style="color: #e2e8f0;"><?= e(getArrayValue($customer, 'company', '-')) ?></div>
                        </div>
                        
                        <?php if (!empty($customer['address']) || !empty($customer['city']) || !empty($customer['postal_code'])): ?>
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">ADRESSE</div>
                            <div class="info-value" style="color: #e2e8f0;">
                                <?php if (!empty($customer['address'])): ?>
                                    <?= e($customer['address']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($customer['postal_code']) || !empty($customer['city'])): ?>
                                    <?= e(getArrayValue($customer, 'postal_code', '')) ?> <?= e(getArrayValue($customer, 'city', '')) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($customer['country'])): ?>
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">LAND</div>
                            <div class="info-value" style="color: #e2e8f0;"><?= e($customer['country']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">ERSTELLT AM</div>
                            <div class="info-value" style="color: #e2e8f0;"><?= formatDateTime($customer['created_at']) ?></div>
                        </div>
                        
                        <?php if (!empty($customer['updated_at'])): ?>
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">ZULETZT AKTUALISIERT</div>
                            <div class="info-value" style="color: #e2e8f0;"><?= formatDateTime($customer['updated_at']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($customer['notes'])): ?>
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3 class="card-title">Notizen</h3>
            </div>
            <div class="card-body">
                <p style="color: #e2e8f0; line-height: 1.6; white-space: pre-line;"><?= e($customer['notes']) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Links Tab -->
    <div id="linksTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 class="card-title">Links für <?= e($customer['name']) ?></h3>
                        <p class="card-subtitle">Alle Links die für diesen Kunden erstellt wurden</p>
                    </div>
                    <a href="?page=links&action=create&customer_id=<?= $customerId ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> Neuer Link
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($customerLinks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-link"></i>
                        <h3>Keine Links vorhanden</h3>
                        <p>Erstellen Sie den ersten Link für diesen Kunden.</p>
                        <a href="?page=links&action=create&customer_id=<?= $customerId ?>" class="btn btn-success" style="margin-top: 16px;">
                            <i class="fas fa-plus"></i> Ersten Link erstellen
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Ankertext</th>
                                    <th>Ziel-URL</th>
                                    <th>Blog</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $blogs = loadData('blogs.json');
                                foreach ($customerLinks as $linkId => $link): 
                                    $blog = $blogs[getArrayValue($link, 'blog_id', '')] ?? null;
                                ?>
                                    <tr>
                                        <td><?= formatDate(getArrayValue($link, 'published_date', $link['created_at'])) ?></td>
                                        <td>
                                            <a href="?page=links&action=view&id=<?= $linkId ?>" style="color: #4dabf7; text-decoration: none;">
                                                <?= e(getArrayValue($link, 'anchor_text', '')) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="<?= e(getArrayValue($link, 'target_url', '')) ?>" target="_blank" style="color: #4dabf7; text-decoration: none; font-size: 12px;">
                                                <?= e(truncate(getArrayValue($link, 'target_url', ''), 40)) ?>
                                                <i class="fas fa-external-link-alt" style="margin-left: 4px; font-size: 10px;"></i>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($blog): ?>
                                                <a href="?page=blogs&action=view&id=<?= getArrayValue($link, 'blog_id') ?>" style="color: #4dabf7; text-decoration: none;">
                                                    <?= e(getArrayValue($blog, 'name', '')) ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #8b8fa3;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = getArrayValue($link, 'status', 'ausstehend');
                                            $badgeClass = 'badge-secondary';
                                            switch ($status) {
                                                case 'aktiv':
                                                    $badgeClass = 'badge-success';
                                                    break;
                                                case 'ausstehend':
                                                    $badgeClass = 'badge-warning';
                                                    break;
                                                case 'defekt':
                                                    $badgeClass = 'badge-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 4px;">
                                                <a href="?page=links&action=view&id=<?= $linkId ?>" class="btn btn-sm btn-primary" title="Anzeigen">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?page=links&action=edit&id=<?= $linkId ?>" class="btn btn-sm btn-secondary" title="Bearbeiten">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Websites Tab (falls mehrere Websites vorhanden) -->
    <?php if (!empty($customer['websites']) && is_array($customer['websites']) && count($customer['websites']) > 1): ?>
    <div id="websitesTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Alle Websites von <?= e($customer['name']) ?></h3>
                <p class="card-subtitle">Übersicht über alle registrierten Websites</p>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($customer['websites'] as $index => $website): ?>
                        <div class="website-card" style="background: #343852; padding: 20px; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                <div style="flex: 1;">
                                    <h4 style="color: #4dabf7; margin-bottom: 8px; font-size: 16px;">
                                        <a href="<?= e($website['url']) ?>" target="_blank" style="color: #4dabf7; text-decoration: none;">
                                            <?= e($website['title']) ?>
                                            <i class="fas fa-external-link-alt" style="margin-left: 6px; font-size: 12px;"></i>
                                        </a>
                                    </h4>
                                    <div style="font-size: 12px; color: #8b8fa3; margin-bottom: 8px; word-break: break-all;">
                                        <?= e($website['url']) ?>
                                    </div>
                                    <?php if (!empty($website['description'])): ?>
                                        <p style="color: #e2e8f0; font-size: 13px; line-height: 1.4; margin-bottom: 8px;">
                                            <?= e($website['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid #3a3d52; font-size: 11px; color: #8b8fa3;">
                                <span>Hinzugefügt: <?= formatDate($website['added_at']) ?></span>
                                <div style="display: flex; gap: 4px;">
                                    <a href="<?= e($website['url']) ?>" target="_blank" class="btn btn-sm btn-primary" title="Website besuchen">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <a href="?page=links&action=create&customer_id=<?= $customerId ?>&website_url=<?= urlencode($website['url']) ?>" class="btn btn-sm btn-success" title="Link für diese Website erstellen">
                                        <i class="fas fa-link"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Website-Statistiken -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #3a3d52;">
                    <h4 style="color: #e2e8f0; margin-bottom: 16px;">Website-Statistiken</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                        <div style="text-align: center; padding: 16px; background-color: #3a3d52; border-radius: 6px;">
                            <div style="font-size: 24px; font-weight: bold; color: #4dabf7; margin-bottom: 4px;">
                                <?= count($customer['websites']) ?>
                            </div>
                            <div style="font-size: 12px; color: #8b8fa3;">Websites gesamt</div>
                        </div>
                        <div style="text-align: center; padding: 16px; background-color: #3a3d52; border-radius: 6px;">
                            <div style="font-size: 24px; font-weight: bold; color: #2dd4bf; margin-bottom: 4px;">
                                <?php 
                                $domains = array_unique(array_map(function($w) { 
                                    return parse_url($w['url'], PHP_URL_HOST); 
                                }, $customer['websites']));
                                echo count($domains);
                                ?>
                            </div>
                            <div style="font-size: 12px; color: #8b8fa3;">Verschiedene Domains</div>
                        </div>
                        <div style="text-align: center; padding: 16px; background-color: #3a3d52; border-radius: 6px;">
                            <div style="font-size: 24px; font-weight: bold; color: #fbbf24; margin-bottom: 4px;">
                                <?= count(array_filter($customer['websites'], function($w) { return !empty($w['description']); })) ?>
                            </div>
                            <div style="font-size: 12px; color: #8b8fa3;">Mit Beschreibung</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistiken Tab -->
    <div id="statisticsTab" class="tab-content" style="display: none;">
        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Link-Statistiken</h3>
                    <p class="card-subtitle">Übersicht über die Links für <?= e($customer['name']) ?></p>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; font-weight: bold; color: #2dd4bf; margin-bottom: 8px;">
                                <?= count($customerLinks) ?>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">Gesamt Links</div>
                        </div>
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; font-weight: bold; color: #4dabf7; margin-bottom: 8px;">
                                <?= count(array_filter($customerLinks, function($l) { return getArrayValue($l, 'status', '') === 'aktiv'; })) ?>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">Aktive Links</div>
                        </div>
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; font-weight: bold; color: #fbbf24; margin-bottom: 8px;">
                                <?= count(array_filter($customerLinks, function($l) { return getArrayValue($l, 'status', '') === 'ausstehend'; })) ?>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">Ausstehend</div>
                        </div>
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; font-weight: bold; color: #f97316; margin-bottom: 8px;">
                                <?= count(array_unique(array_column($customerLinks, 'blog_id'))) ?>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">Verschiedene Blogs</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'create' || ($action === 'edit' && $customerId)): 
    $customer = null;
    if ($action === 'edit') {
        $customer = $customers[$customerId] ?? null;
        if (!$customer || (!$isAdmin && getArrayValue($customer, 'user_id') !== $userId)) {
            echo '<div class="error-page"><div class="error-content" style="text-align: center; padding: 60px 20px;">';
            echo '<i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f56565; margin-bottom: 16px;"></i>';
            echo '<h1 style="margin-bottom: 16px;">Kunde nicht gefunden</h1>';
            echo '<p style="color: #8b8fa3; margin-bottom: 24px;">Der angeforderte Kunde existiert nicht oder Sie haben keine Berechtigung.</p>';
            echo '<a href="?page=customers" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Zurück zur Kundenverwaltung</a>';
            echo '</div></div>';
            return;
        }
    }
?>
    <div class="breadcrumb">
        <a href="?page=customers">Zurück zu Kunden</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= $action === 'create' ? 'Neuer Kunde' : 'Kunde bearbeiten' ?></span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title"><?= $action === 'create' ? 'Neuen Kunden hinzufügen' : 'Kunde bearbeiten' ?></h1>
            <p class="page-subtitle">Füllen Sie das Formular aus, um einen Kunden zu <?= $action === 'create' ? 'erstellen' : 'aktualisieren' ?></p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <!-- Grundinformationen -->
                <h3 style="margin-bottom: 20px; color: #e2e8f0; border-bottom: 1px solid #3a3d52; padding-bottom: 10px;">
                    <i class="fas fa-user"></i> Grundinformationen
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Name *</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-control" 
                            placeholder="Vor- und Nachname"
                            value="<?= e(getArrayValue($_POST, 'name', getArrayValue($customer, 'name', ''))) ?>"
                            required
                            autofocus
                        >
                    </div>
                    <div class="form-group">
                        <label for="company" class="form-label">Unternehmen</label>
                        <input 
                            type="text" 
                            id="company" 
                            name="company" 
                            class="form-control" 
                            placeholder="Firmenname"
                            value="<?= e(getArrayValue($_POST, 'company', getArrayValue($customer, 'company', ''))) ?>"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email" class="form-label">E-Mail-Adresse</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="kunde@example.com"
                            value="<?= e(getArrayValue($_POST, 'email', getArrayValue($customer, 'email', ''))) ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">Telefon</label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            class="form-control" 
                            placeholder="+49 123 456789"
                            value="<?= e(getArrayValue($_POST, 'phone', getArrayValue($customer, 'phone', ''))) ?>"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="aktiv" <?= getArrayValue($_POST, 'status', getArrayValue($customer, 'status', 'aktiv')) === 'aktiv' ? 'selected' : '' ?>>Aktiv</option>
                            <option value="inaktiv" <?= getArrayValue($_POST, 'status', getArrayValue($customer, 'status', 'aktiv')) === 'inaktiv' ? 'selected' : '' ?>>Inaktiv</option>
                        </select>
                    </div>
                </div>

                <!-- Websites-Bereich -->
                <h3 style="margin: 30px 0 20px; color: #e2e8f0; border-bottom: 1px solid #3a3d52; padding-bottom: 10px;">
                    <i class="fas fa-globe"></i> Websites
                </h3>

                <div id="websitesContainer">
                    <?php 
                    $websites = [];
                    if (!empty($_POST['websites'])) {
                        $websites = $_POST['websites'];
                    } elseif (!empty($customer['websites']) && is_array($customer['websites'])) {
                        $websites = $customer['websites'];
                    } elseif (!empty($customer['website'])) {
                        // Backward compatibility für alte einzelne Website
                        $websites = [[
                            'url' => $customer['website'],
                            'title' => parse_url($customer['website'], PHP_URL_HOST),
                            'description' => ''
                        ]];
                    }
                    
                    if (empty($websites)) {
                        $websites = [['url' => '', 'title' => '', 'description' => '']];
                    }
                    
                    foreach ($websites as $index => $website): 
                    ?>
                        <div class="website-item" style="background: #343852; padding: 16px; border-radius: 6px; margin-bottom: 16px; position: relative;">
                            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 12px;">
                                <h4 style="color: #e2e8f0; font-size: 14px; margin: 0;">
                                    <i class="fas fa-globe" style="margin-right: 8px; color: #4dabf7;"></i>
                                    Website <?= $index + 1 ?>
                                </h4>
                                <?php if ($index > 0): ?>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeWebsite(this)" style="position: absolute; top: 8px; right: 8px; padding: 4px 8px;" title="Website entfernen">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">URL *</label>
                                    <input 
                                        type="url" 
                                        name="websites[<?= $index ?>][url]" 
                                        class="form-control website-url" 
                                        placeholder="https://example.com"
                                        value="<?= e(getArrayValue($website, 'url', '')) ?>"
                                        onchange="updateWebsiteTitle(this)"
                                    >
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Titel</label>
                                    <input 
                                        type="text" 
                                        name="websites[<?= $index ?>][title]" 
                                        class="form-control website-title" 
                                        placeholder="Wird automatisch gefüllt"
                                        value="<?= e(getArrayValue($website, 'title', '')) ?>"
                                    >
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Beschreibung</label>
                                <input 
                                    type="text" 
                                    name="websites[<?= $index ?>][description]" 
                                    class="form-control" 
                                    placeholder="Kurze Beschreibung der Website (optional)"
                                    value="<?= e(getArrayValue($website, 'description', '')) ?>"
                                >
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-bottom: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="addWebsite()">
                        <i class="fas fa-plus"></i> Weitere Website hinzufügen
                    </button>
                </div>

                <!-- Adressinformationen -->
                <h3 style="margin: 30px 0 20px; color: #e2e8f0; border-bottom: 1px solid #3a3d52; padding-bottom: 10px;">
                    <i class="fas fa-map-marker-alt"></i> Adressinformationen
                </h3>

                <div class="form-group">
                    <label for="address" class="form-label">Straße und Hausnummer</label>
                    <input 
                        type="text" 
                        id="address" 
                        name="address" 
                        class="form-control" 
                        placeholder="Musterstraße 123"
                        value="<?= e(getArrayValue($_POST, 'address', getArrayValue($customer, 'address', ''))) ?>"
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="postal_code" class="form-label">Postleitzahl</label>
                        <input 
                            type="text" 
                            id="postal_code" 
                            name="postal_code" 
                            class="form-control" 
                            placeholder="12345"
                            value="<?= e(getArrayValue($_POST, 'postal_code', getArrayValue($customer, 'postal_code', ''))) ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="city" class="form-label">Stadt</label>
                        <input 
                            type="text" 
                            id="city" 
                            name="city" 
                            class="form-control" 
                            placeholder="Musterstadt"
                            value="<?= e(getArrayValue($_POST, 'city', getArrayValue($customer, 'city', ''))) ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="country" class="form-label">Land</label>
                    <select id="country" name="country" class="form-control">
                        <option value="">Land auswählen</option>
                        <?php 
                        $countries = ['Deutschland', 'Österreich', 'Schweiz', 'Niederlande', 'Belgien', 'Frankreich', 'Italien', 'Spanien', 'Polen', 'Tschechien', 'Ungarn', 'Slowakei'];
                        $selectedCountry = getArrayValue($_POST, 'country', getArrayValue($customer, 'country', ''));
                        foreach ($countries as $country): 
                        ?>
                            <option value="<?= e($country) ?>" <?= $selectedCountry === $country ? 'selected' : '' ?>><?= e($country) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Notizen -->
                <h3 style="margin: 30px 0 20px; color: #e2e8f0; border-bottom: 1px solid #3a3d52; padding-bottom: 10px;">
                    <i class="fas fa-sticky-note"></i> Zusätzliche Informationen
                </h3>

                <div class="form-group">
                    <label for="notes" class="form-label">Notizen</label>
                    <textarea 
                        id="notes" 
                        name="notes" 
                        class="form-control" 
                        rows="4"
                        placeholder="Interne Notizen, Besonderheiten, etc."
                    ><?= e(getArrayValue($_POST, 'notes', getArrayValue($customer, 'notes', ''))) ?></textarea>
                    <small style="color: #8b8fa3; font-size: 12px;">Diese Notizen sind nur für interne Zwecke sichtbar</small>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #3a3d52;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        <?= $action === 'create' ? 'Kunde erstellen' : 'Änderungen speichern' ?>
                    </button>
                    <a href="?page=customers<?= $action === 'edit' ? "&action=view&id=$customerId" : '' ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'import'): ?>
    <div class="breadcrumb">
        <a href="?page=customers">Zurück zu Kunden</a>
        <i class="fas fa-chevron-right"></i>
        <span>Import</span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">Kunden importieren</h1>
            <p class="page-subtitle">Importieren Sie Kunden aus einer CSV-Datei</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>CSV-Format:</strong> Die erste Zeile sollte die Spaltennamen enthalten: Name, E-Mail, Telefon, Unternehmen, Adresse, Stadt, PLZ, Land, Notizen
            </div>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_process">
                
                <div class="form-group">
                    <label for="csv_file" class="form-label">CSV-Datei auswählen</label>
                    <input 
                        type="file" 
                        id="csv_file" 
                        name="csv_file" 
                        class="form-control" 
                        accept=".csv"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="has_header" value="1" checked>
                        Erste Zeile enthält Spaltennamen
                    </label>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i>
                        Kunden importieren
                    </button>
                    <a href="?page=customers" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                </div>
            </form>

            <div style="margin-top: 30px;">
                <h4>CSV-Vorlage herunterladen</h4>
                <p style="color: #8b8fa3;">Laden Sie eine Beispiel-CSV-Datei herunter, um das richtige Format zu sehen:</p>
                <button type="button" class="btn btn-secondary" onclick="downloadTemplate()">
                    <i class="fas fa-download"></i>
                    CSV-Vorlage herunterladen
                </button>
            </div>
        </div>
    </div>

<?php elseif ($action === 'export'): ?>
    <?php
    // CSV-Export der Kunden
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kunden_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM für UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // CSV-Header
    fputcsv($output, [
        'Name',
        'E-Mail',
        'Telefon',
        'Unternehmen',
        'Adresse',
        'Stadt',
        'PLZ',
        'Land',
        'Status',
        'Notizen',
        'Erstellt am'
    ], ';');
    
    // Kundendaten
    foreach ($userCustomers as $customer) {
        fputcsv($output, [
            $customer['name'],
            getArrayValue($customer, 'email', ''),
            getArrayValue($customer, 'phone', ''),
            getArrayValue($customer, 'company', ''),
            getArrayValue($customer, 'address', ''),
            getArrayValue($customer, 'city', ''),
            getArrayValue($customer, 'postal_code', ''),
            getArrayValue($customer, 'country', ''),
            getArrayValue($customer, 'status', 'aktiv'),
            getArrayValue($customer, 'notes', ''),
            $customer['created_at']
        ], ';');
    }
    
    fclose($output);
    exit;
    ?>

<?php else: ?>
    <div class="error-page">
        <div class="error-content" style="text-align: center; padding: 60px 20px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f56565; margin-bottom: 16px;"></i>
            <h1 style="margin-bottom: 16px;">Seite nicht gefunden</h1>
            <p style="color: #8b8fa3; margin-bottom: 24px;">Die angeforderte Seite konnte nicht gefunden werden.</p>
            <a href="?page=customers" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i>
                Zurück zur Kundenverwaltung
            </a>
        </div>
    </div>
<?php endif; ?>

<script>
// CSS für Filter-Dropdowns
const filterCSS = `
.filter-select {
    background-color: #343852;
    border: 1px solid #3a3d52;
    border-radius: 6px;
    color: #e2e8f0;
    padding: 8px 12px;
    font-size: 14px;
    min-width: 120px;
    outline: none;
    cursor: pointer;
    transition: border-color 0.2s ease;
}

.filter-select:hover {
    border-color: #4dabf7;
}

.filter-select:focus {
    border-color: #4dabf7;
    box-shadow: 0 0 0 2px rgba(77, 171, 247, 0.1);
}

.filter-select option {
    background-color: #343852;
    color: #e2e8f0;
    padding: 8px;
}

.search-input {
    background-color: #343852 !important;
    border: 1px solid #3a3d52 !important;
    border-radius: 6px !important;
    color: #e2e8f0 !important;
    padding: 8px 12px !important;
    font-size: 14px !important;
    width: 100% !important;
    outline: none !important;
    transition: border-color 0.2s ease !important;
}

.search-input:hover {
    border-color: #4dabf7 !important;
}

.search-input:focus {
    border-color: #4dabf7 !important;
    box-shadow: 0 0 0 2px rgba(77, 171, 247, 0.1) !important;
}

.search-input::placeholder {
    color: #8b8fa3 !important;
}

.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-bar {
        width: 100% !important;
        max-width: none !important;
    }
    
    .filter-select {
        min-width: 100px;
    }
}
`;

// CSS in den Head einfügen
const style = document.createElement('style');
style.textContent = filterCSS;
document.head.appendChild(style);

// Websites-Management JavaScript
let websiteCounter = <?= count($websites ?? []) ?>;

function addWebsite() {
    const container = document.getElementById('websitesContainer');
    if (!container) return;
    
    const websiteItem = document.createElement('div');
    websiteItem.className = 'website-item';
    websiteItem.style.cssText = 'background: #343852; padding: 16px; border-radius: 6px; margin-bottom: 16px; position: relative;';
    
    websiteItem.innerHTML = `
        <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 12px;">
            <h4 style="color: #e2e8f0; font-size: 14px; margin: 0;">
                <i class="fas fa-globe" style="margin-right: 8px; color: #4dabf7;"></i>
                Website ${websiteCounter + 1}
            </h4>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeWebsite(this)" style="position: absolute; top: 8px; right: 8px; padding: 4px 8px;" title="Website entfernen">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">URL *</label>
                <input 
                    type="url" 
                    name="websites[${websiteCounter}][url]" 
                    class="form-control website-url" 
                    placeholder="https://example.com"
                    onchange="updateWebsiteTitle(this)"
                >
            </div>
            <div class="form-group">
                <label class="form-label">Titel</label>
                <input 
                    type="text" 
                    name="websites[${websiteCounter}][title]" 
                    class="form-control website-title" 
                    placeholder="Wird automatisch gefüllt"
                >
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Beschreibung</label>
            <input 
                type="text" 
                name="websites[${websiteCounter}][description]" 
                class="form-control" 
                placeholder="Kurze Beschreibung der Website (optional)"
            >
        </div>
    `;
    
    container.appendChild(websiteItem);
    websiteCounter++;
    
    // Focus auf die neue URL-Eingabe
    const urlInput = websiteItem.querySelector('.website-url');
    if (urlInput) urlInput.focus();
}

function removeWebsite(button) {
    if (confirm('Website wirklich entfernen?')) {
        const websiteItem = button.closest('.website-item');
        if (websiteItem) {
            websiteItem.remove();
            updateWebsiteNumbers();
        }
    }
}

function updateWebsiteNumbers() {
    const websiteItems = document.querySelectorAll('.website-item');
    websiteItems.forEach((item, index) => {
        const title = item.querySelector('h4');
        if (title) {
            title.innerHTML = `<i class="fas fa-globe" style="margin-right: 8px; color: #4dabf7;"></i>Website ${index + 1}`;
        }
        
        // Update input names
        const inputs = item.querySelectorAll('input');
        inputs.forEach(input => {
            if (input.name.includes('[url]')) {
                input.name = `websites[${index}][url]`;
            } else if (input.name.includes('[title]')) {
                input.name = `websites[${index}][title]`;
            } else if (input.name.includes('[description]')) {
                input.name = `websites[${index}][description]`;
            }
        });
    });
}

function updateWebsiteTitle(urlInput) {
    const websiteItem = urlInput.closest('.website-item');
    if (!websiteItem) return;
    
    const titleInput = websiteItem.querySelector('.website-title');
    if (!titleInput) return;
    
    if (urlInput.value && !titleInput.value) {
        try {
            const url = new URL(urlInput.value.includes('://') ? urlInput.value : 'https://' + urlInput.value);
            titleInput.value = url.hostname.replace('www.', '');
        } catch (e) {
            // Invalid URL, do nothing
        }
    }
}

function filterCustomers() {
    const search = document.getElementById('customerSearch');
    const statusFilter = document.getElementById('statusFilter');
    const countryFilter = document.getElementById('countryFilter');
    
    if (!search || !statusFilter || !countryFilter) return;
    
    const searchValue = search.value.toLowerCase();
    const statusValue = statusFilter.value.toLowerCase();
    const countryValue = countryFilter.value.toLowerCase();
    const rows = document.querySelectorAll('.customer-row');
    
    rows.forEach(row => {
        const name = row.dataset.name || '';
        const company = row.dataset.company || '';
        const email = row.dataset.email || '';
        const status = row.dataset.status || '';
        const country = row.dataset.country || '';
        
        const searchMatch = !searchValue || 
            name.includes(searchValue) || 
            company.includes(searchValue) || 
            email.includes(searchValue);
        
        const statusMatch = !statusValue || status === statusValue;
        const countryMatch = !countryValue || country === countryValue;
        
        const matches = searchMatch && statusMatch && countryMatch;
        row.style.display = matches ? 'table-row' : 'none';
    });
    
    // Zeige "Keine Ergebnisse" Nachricht falls keine Zeilen sichtbar
    const visibleRows = document.querySelectorAll('.customer-row[style="table-row"], .customer-row:not([style*="none"])');
    const tableBody = document.getElementById('customerTableBody');
    
    if (!tableBody) return;
    
    // Entferne existierende "Keine Ergebnisse" Zeile
    const noResultsRow = tableBody.querySelector('.no-results-row');
    if (noResultsRow) {
        noResultsRow.remove();
    }
    
            if (visibleRows.length === 0) {
        const noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-results-row';
        noResultsRow.innerHTML = `
            <td colspan="${document.querySelector('.table thead tr').children.length}" style="text-align: center; padding: 40px; color: #8b8fa3;">
                <i class="fas fa-search" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
                Keine Kunden gefunden, die den Filterkriterien entsprechen.
            </td>
        `;
        tableBody.appendChild(noResultsRow);
    }
}

// Tab-Funktionalität für Kundendetails
function showTab(tabName) {
    // Alle Tabs verstecken
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Alle Tab-Buttons zurücksetzen
    document.querySelectorAll('.tab').forEach(tab => {
        tab.style.color = '#8b8fa3';
        tab.style.borderBottomColor = 'transparent';
    });
    
    // Gewählten Tab anzeigen
    const targetTab = document.getElementById(tabName + 'Tab');
    if (targetTab) {
        targetTab.style.display = 'block';
    }
    
    // Aktiven Tab-Button markieren
    if (event && event.target) {
        event.target.style.color = '#4dabf7';
        event.target.style.borderBottomColor = '#4dabf7';
    }
}

// CSV-Template Download
function downloadTemplate() {
    const csvContent = "data:text/csv;charset=utf-8," 
        + "Name,E-Mail,Telefon,Unternehmen,Adresse,Stadt,PLZ,Land,Notizen\n"
        + "Max Mustermann,max@example.com,+49 123 456789,Musterfirma GmbH,Musterstraße 1,Berlin,10115,Deutschland,Beispielnotiz";
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "kunden_vorlage.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Form-Validierung
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[method="post"]');
    if (form) {
        // Website-URL automatisch formatieren
        const websiteInputs = document.querySelectorAll('.website-url');
        websiteInputs.forEach(input => {
            input.addEventListener('blur', function() {
                let url = this.value.trim();
                if (url && !url.startsWith('http://') && !url.startsWith('https://')) {
                    this.value = 'https://' + url;
                }
                updateWebsiteTitle(this);
            });
        });
        
        // Telefonnummer formatieren
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                let phone = this.value.replace(/[^\d\+\-\s\(\)]/g, '');
                this.value = phone;
            });
        }
        
        // Postleitzahl nur Zahlen erlauben
        const postalInput = document.getElementById('postal_code');
        if (postalInput) {
            postalInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^\d]/g, '');
            });
        }
    }
    
    // Initialisiere Websiteanzahl korrekt
    const websiteItems = document.querySelectorAll('.website-item');
    websiteCounter = websiteItems.length;
});

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + N für neuen Kunden
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        if (window.location.search.includes('page=customers')) {
            window.location.href = '?page=customers&action=create';
        }
    }
    
    // ESC zum Abbrechen
    if (e.key === 'Escape') {
        const currentUrl = window.location.search;
        if (currentUrl.includes('action=create') || currentUrl.includes('action=edit')) {
            if (confirm('Möchten Sie wirklich abbrechen? Nicht gespeicherte Änderungen gehen verloren.')) {
                window.location.href = '?page=customers';
            }
        }
    }
});
</script>