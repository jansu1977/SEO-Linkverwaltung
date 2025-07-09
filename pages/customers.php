// Websites Tab hinzufügen (falls Kunde mehrere Websites hat)
<?php if (!empty($customer['websites']) && is_array($customer['websites']) && count($customer['websites']) > 1): ?>
        <button class="tab" onclick="showTab('websites')" style="padding: 12px 24px; background: none; border: none; color: #8b8fa3; border-bottom: 2px solid transparent; font-weight: 600; cursor: pointer;">
            Websites (<?= count($customer['websites']) ?>)
        </button>
<?php endif; ?><?php
$action = $_GET['action'] ?? 'index';
$customerId = $_GET['id'] ?? null;
$userId = getCurrentUserId();

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
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
        if (isset($customers[$customerId]) && $customers[$customerId]['user_id'] === $userId) {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $company = trim($_POST['company'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');
            $country = trim($_POST['country'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
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
        if (isset($customers[$customerId]) && $customers[$customerId]['user_id'] === $userId) {
            unset($customers[$customerId]);
            if (saveData('customers.json', $customers)) {
                redirectWithMessage('?page=customers', 'Kunde erfolgreich gelöscht.');
            } else {
                $error = 'Fehler beim Löschen des Kunden.';
            }
        }
    }
}

// Daten laden
$customers = loadData('customers.json');
$links = loadData('links.json');

// Benutzer-spezifische Kunden
$userCustomers = array_filter($customers, function($customer) use ($userId) {
    return $customer['user_id'] === $userId;
});

// Statistiken berechnen
$activeCustomers = array_filter($userCustomers, function($customer) {
    return ($customer['status'] ?? 'aktiv') === 'aktiv';
});

$inactiveCustomers = array_filter($userCustomers, function($customer) {
    return ($customer['status'] ?? 'aktiv') === 'inaktiv';
});

// Länder-Statistiken
$countryStats = [];
foreach ($userCustomers as $customer) {
    $country = $customer['country'] ?? 'Unbekannt';
    $countryStats[$country] = ($countryStats[$country] ?? 0) + 1;
}

if ($action === 'index'): ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">Kundenverwaltung</h1>
            <p class="page-subtitle">Verwalten Sie Ihre Kunden und Kontakte</p>
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
                <p class="card-subtitle">Schneller Überblick über Ihre Kunden</p>
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
                                    <?= e($customer['company'] ?? 'Kein Unternehmen') ?>
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
            <select class="form-control" id="statusFilter" onchange="filterCustomers()" style="width: auto;">
                <option value="">Alle Status</option>
                <option value="aktiv">Aktive Kunden</option>
                <option value="inaktiv">Inaktive Kunden</option>
            </select>
            <select class="form-control" id="countryFilter" onchange="filterCustomers()" style="width: auto;">
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
                                <th>Erstellt</th>
                                <th style="width: 120px;">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody">
                            <?php foreach ($userCustomers as $customerId => $customer): 
                                // Links für diesen Kunden zählen
                                $customerLinks = array_filter($links, function($link) use ($customerId) {
                                    return ($link['customer_id'] ?? '') === $customerId;
                                });
                                $linkCount = count($customerLinks);
                            ?>
                                <tr class="customer-row" 
                                    data-name="<?= strtolower($customer['name']) ?>" 
                                    data-company="<?= strtolower($customer['company'] ?? '') ?>"
                                    data-email="<?= strtolower($customer['email'] ?? '') ?>"
                                    data-status="<?= strtolower($customer['status'] ?? 'aktiv') ?>"
                                    data-country="<?= strtolower($customer['country'] ?? '') ?>">
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
                                        <?= e($customer['company'] ?? '-') ?>
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
                                        <?= e($customer['country'] ?? '-') ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $customer['status'] ?? 'aktiv';
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
                                    <td style="color: #8b8fa3; font-size: 12px;">
                                        <?= formatDate($customer['created_at']) ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 4px;">
                                            <a href="?page=customers&action=view&id=<?= $customerId ?>" class="btn btn-sm btn-primary" title="Anzeigen">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?page=customers&action=edit&id=<?= $customerId ?>" class="btn btn-sm btn-secondary" title="Bearbeiten">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?page=customers&action=delete&id=<?= $customerId ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Kunde wirklich löschen?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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
    if (!$customer || $customer['user_id'] !== $userId) {
        header('HTTP/1.0 404 Not Found');
        include 'pages/404.php';
        return;
    }
    
    // Links für diesen Kunden
    $customerLinks = array_filter($links, function($link) use ($customerId) {
        return ($link['customer_id'] ?? '') === $customerId;
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
                                <span class="badge <?= ($customer['status'] ?? 'aktiv') === 'aktiv' ? 'badge-success' : 'badge-warning' ?>">
                                    <?= ucfirst($customer['status'] ?? 'aktiv') ?>
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
                            <div class="info-value" style="color: #e2e8f0;"><?= e($customer['company'] ?? '-') ?></div>
                        </div>
                        
                        <?php if (!empty($customer['address']) || !empty($customer['city']) || !empty($customer['postal_code'])): ?>
                        <div class="info-item">
                            <div class="info-label" style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">ADRESSE</div>
                            <div class="info-value" style="color: #e2e8f0;">
                                <?php if (!empty($customer['address'])): ?>
                                    <?= e($customer['address']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($customer['postal_code']) || !empty($customer['city'])): ?>
                                    <?= e($customer['postal_code']) ?> <?= e($customer['city']) ?>
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
                                    $blog = $blogs[$link['blog_id']] ?? null;
                                ?>
                                    <tr>
                                        <td><?= formatDate($link['published_date'] ?? $link['created_at']) ?></td>
                                        <td>
                                            <a href="?page=links&action=view&id=<?= $linkId ?>" style="color: #4dabf7; text-decoration: none;">
                                                <?= e($link['anchor_text']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="<?= e($link['target_url']) ?>" target="_blank" style="color: #4dabf7; text-decoration: none; font-size: 12px;">
                                                <?= e(strlen($link['target_url']) > 40 ? substr($link['target_url'], 0, 40) . '...' : $link['target_url']) ?>
                                                <i class="fas fa-external-link-alt" style="margin-left: 4px; font-size: 10px;"></i>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($blog): ?>
                                                <a href="?page=blogs&action=view&id=<?= $link['blog_id'] ?>" style="color: #4dabf7; text-decoration: none;">
                                                    <?= e($blog['name']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #8b8fa3;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $link['status'] ?? 'ausstehend';
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
                                <?= count(array_filter($customerLinks, function($l) { return ($l['status'] ?? '') === 'aktiv'; })) ?>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">Aktive Links</div>
                        </div>
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; font-weight: bold; color: #fbbf24; margin-bottom: 8px;">
                                <?= count(array_filter($customerLinks, function($l) { return ($l['status'] ?? '') === 'ausstehend'; })) ?>
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
        if (!$customer || $customer['user_id'] !== $userId) {
            header('HTTP/1.0 404 Not Found');
            include 'pages/404.php';
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
                            value="<?= e($_POST['name'] ?? $customer['name'] ?? '') ?>"
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
                            value="<?= e($_POST['company'] ?? $customer['company'] ?? '') ?>"
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
                            value="<?= e($_POST['email'] ?? $customer['email'] ?? '') ?>"
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
                            value="<?= e($_POST['phone'] ?? $customer['phone'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone" class="form-label">Telefon</label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            class="form-control" 
                            placeholder="+49 123 456789"
                            value="<?= e($_POST['phone'] ?? $customer['phone'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="aktiv" <?= ($_POST['status'] ?? $customer['status'] ?? 'aktiv') === 'aktiv' ? 'selected' : '' ?>>Aktiv</option>
                            <option value="inaktiv" <?= ($_POST['status'] ?? $customer['status'] ?? 'aktiv') === 'inaktiv' ? 'selected' : '' ?>>Inaktiv</option>
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
                                        value="<?= e($website['url'] ?? '') ?>"
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
                                        value="<?= e($website['title'] ?? '') ?>"
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
                                    value="<?= e($website['description'] ?? '') ?>"
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
                        value="<?= e($_POST['address'] ?? $customer['address'] ?? '') ?>"
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
                            value="<?= e($_POST['postal_code'] ?? $customer['postal_code'] ?? '') ?>"
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
                            value="<?= e($_POST['city'] ?? $customer['city'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="country" class="form-label">Land</label>
                    <select id="country" name="country" class="form-control">
                        <option value="">Land auswählen</option>
                        <option value="Deutschland" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Deutschland' ? 'selected' : '' ?>>Deutschland</option>
                        <option value="Österreich" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Österreich' ? 'selected' : '' ?>>Österreich</option>
                        <option value="Schweiz" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Schweiz' ? 'selected' : '' ?>>Schweiz</option>
                        <option value="Niederlande" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Niederlande' ? 'selected' : '' ?>>Niederlande</option>
                        <option value="Belgien" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Belgien' ? 'selected' : '' ?>>Belgien</option>
                        <option value="Frankreich" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Frankreich' ? 'selected' : '' ?>>Frankreich</option>
                        <option value="Italien" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Italien' ? 'selected' : '' ?>>Italien</option>
                        <option value="Spanien" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Spanien' ? 'selected' : '' ?>>Spanien</option>
                        <option value="Polen" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Polen' ? 'selected' : '' ?>>Polen</option>
                        <option value="Tschechien" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Tschechien' ? 'selected' : '' ?>>Tschechien</option>
                        <option value="Ungarn" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Ungarn' ? 'selected' : '' ?>>Ungarn</option>
                        <option value="Slowakei" <?= ($_POST['country'] ?? $customer['country'] ?? '') === 'Slowakei' ? 'selected' : '' ?>>Slowakei</option>
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
                    ><?= e($_POST['notes'] ?? $customer['notes'] ?? '') ?></textarea>
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
// Websites-Management JavaScript
let websiteCounter = <?= count($websites) ?>;

function addWebsite() {
    const container = document.getElementById('websitesContainer');
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
    websiteItem.querySelector('.website-url').focus();
}

function removeWebsite(button) {
    if (confirm('Website wirklich entfernen?')) {
        const websiteItem = button.closest('.website-item');
        websiteItem.remove();
        updateWebsiteNumbers();
    }
}

function updateWebsiteNumbers() {
    const websiteItems = document.querySelectorAll('.website-item');
    websiteItems.forEach((item, index) => {
        const title = item.querySelector('h4');
        title.innerHTML = `<i class="fas fa-globe" style="margin-right: 8px; color: #4dabf7;"></i>Website ${index + 1}`;
        
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
    const titleInput = websiteItem.querySelector('.website-title');
    
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
    const search = document.getElementById('customerSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    const countryFilter = document.getElementById('countryFilter').value.toLowerCase();
    const rows = document.querySelectorAll('.customer-row');
    
    rows.forEach(row => {
        const name = row.dataset.name || '';
        const company = row.dataset.company || '';
        const email = row.dataset.email || '';
        const status = row.dataset.status || '';
        const country = row.dataset.country || '';
        
        const searchMatch = !search || 
            name.includes(search) || 
            company.includes(search) || 
            email.includes(search);
        
        const statusMatch = !statusFilter || status === statusFilter;
        const countryMatch = !countryFilter || country === countryFilter;
        
        const matches = searchMatch && statusMatch && countryMatch;
        row.style.display = matches ? 'table-row' : 'none';
    });
    
    // Zeige "Keine Ergebnisse" Nachricht falls keine Zeilen sichtbar
    const visibleRows = document.querySelectorAll('.customer-row[style="table-row"], .customer-row:not([style*="none"])');
    const tableBody = document.getElementById('customerTableBody');
    
    // Entferne existierende "Keine Ergebnisse" Zeile
    const noResultsRow = tableBody.querySelector('.no-results-row');
    if (noResultsRow) {
        noResultsRow.remove();
    }
    
    if (visibleRows.length === 0 && tableBody) {
        const noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-results-row';
        noResultsRow.innerHTML = `
            <td colspan="9" style="text-align: center; padding: 40px; color: #8b8fa3;">
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
    document.getElementById(tabName + 'Tab').style.display = 'block';
    
    // Aktiven Tab-Button markieren
    event.target.style.color = '#4dabf7';
    event.target.style.borderBottomColor = '#4dabf7';
}

// Form-Validierung verbessern
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
});
</script>