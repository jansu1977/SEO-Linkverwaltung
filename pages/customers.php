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

// Kostenfunktionen definieren
function calculateMonthlyCost($amount, $type) {
    if (empty($amount) || $amount <= 0) return 0;
    
    switch ($type) {
        case 'monthly':
            return $amount;
        case 'yearly':
            return $amount / 12;
        case 'onetime':
            return 0; // Einmalige Kosten nicht in monatliche Berechnung
        default:
            return 0;
    }
}

function formatCostDisplay($amount, $type = null, $currency = 'EUR') {
    if (empty($amount) || $amount <= 0) return '-';
    
    $formatted = number_format($amount, 2, ',', '.') . ' ' . $currency;
    
    if ($type) {
        switch ($type) {
            case 'monthly':
                return $formatted . '/Monat';
            case 'yearly':
                return $formatted . '/Jahr';
            case 'onetime':
                return $formatted . ' (einmalig)';
            default:
                return $formatted;
        }
    }
    
    return $formatted;
}

function calculateCustomerCosts($customerId, $links) {
    $costs = [
        'total_monthly' => 0,
        'total_yearly' => 0,
        'total_onetime' => 0,
        'links_with_costs' => 0,
        'total_links' => 0,
        'by_website' => []
    ];
    
    foreach ($links as $link) {
        if (($link['customer_id'] ?? '') === $customerId) {
            $costs['total_links']++;
            
            if (!empty($link['cost_amount']) && $link['cost_amount'] > 0) {
                $costs['links_with_costs']++;
                $amount = (float)$link['cost_amount'];
                $type = $link['cost_type'] ?? 'monthly';
                $websiteId = $link['customer_website_id'] ?? 'default';
                
                // Gesamtkosten
                switch ($type) {
                    case 'monthly':
                        $costs['total_monthly'] += $amount;
                        break;
                    case 'yearly':
                        $costs['total_yearly'] += $amount;
                        break;
                    case 'onetime':
                        $costs['total_onetime'] += $amount;
                        break;
                }
                
                // Pro Website
                if (!isset($costs['by_website'][$websiteId])) {
                    $costs['by_website'][$websiteId] = [
                        'monthly' => 0,
                        'yearly' => 0,
                        'onetime' => 0,
                        'link_count' => 0
                    ];
                }
                
                $costs['by_website'][$websiteId][$type] += $amount;
                $costs['by_website'][$websiteId]['link_count']++;
            }
        }
    }
    
    // Gesamte monatliche Kosten berechnen
    $costs['estimated_monthly'] = $costs['total_monthly'] + ($costs['total_yearly'] / 12);
    
    return $costs;
}

// Daten laden
$customers = loadData('customers.json');
$links = loadData('links.json');
$users = loadData('users.json');

// Admin-Logik: Admin sieht alle Kunden, normale Benutzer nur ihre eigenen
$currentUser = $users[$userId] ?? null;
$isAdmin = $currentUser && ($currentUser['role'] === 'admin');

// Debug-Information für Problemanalyse
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo '<div style="background: #ff0000; color: white; padding: 10px; margin: 10px 0; position: fixed; top: 0; left: 0; right: 0; z-index: 9999;">';
    echo '<h3>DEBUG INFO - CUSTOMERS MAIN:</h3>';
    echo '<p><strong>Action:</strong> ' . htmlspecialchars($action) . '</p>';
    echo '<p><strong>Customer ID:</strong> ' . htmlspecialchars($customerId ?? 'KEINE') . '</p>';
    echo '<p><strong>Current User ID:</strong> ' . $userId . '</p>';
    echo '<p><strong>Ist Admin:</strong> ' . ($isAdmin ? 'JA' : 'NEIN') . '</p>';
    echo '<p><strong>Anzahl geladene Kunden:</strong> ' . count($customers) . '</p>';
    if ($customerId && isset($customers[$customerId])) {
        echo '<p><strong>Kunde gefunden:</strong> ' . htmlspecialchars($customers[$customerId]['name']) . '</p>';
    } elseif ($customerId) {
        echo '<p><strong>Kunde NICHT gefunden mit ID:</strong> ' . htmlspecialchars($customerId) . '</p>';
    }
    echo '<button onclick="this.parentElement.style.display=\'none\'" style="position: absolute; top: 5px; right: 10px; background: white; color: red; border: none; padding: 2px 6px;">X</button>';
    echo '</div>';
    echo '<div style="margin-top: 100px;"></div>'; // Platz für Debug-Box
}

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
            $customers = loadData('customers.json'); // Refresh data
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
        $customers = loadData('customers.json'); // Refresh data
        
        // Kunden-Validierung mit verbesserter Suche
        $customer = null;
        if (isset($customers[$customerId])) {
            $customer = $customers[$customerId];
        }
        
        if ($customer && ($isAdmin || getArrayValue($customer, 'user_id') === $userId)) {
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
        } else {
            $errors[] = 'Kunde nicht gefunden oder keine Berechtigung.';
        }
    } elseif ($action === 'delete' && $customerId) {
        $customers = loadData('customers.json'); // Refresh data
        
        // Verbesserte Kunden-Validierung
        if (isset($customers[$customerId]) && ($isAdmin || getArrayValue($customers[$customerId], 'user_id') === $userId)) {
            unset($customers[$customerId]);
            if (saveData('customers.json', $customers)) {
                redirectWithMessage('?page=customers', 'Kunde erfolgreich gelöscht.');
            } else {
                setFlashMessage('Fehler beim Löschen des Kunden.', 'error');
            }
        } else {
            setFlashMessage('Kunde nicht gefunden oder keine Berechtigung.', 'error');
        }
    }
}

// Daten nach POST-Verarbeitung neu laden
$customers = loadData('customers.json');
$links = loadData('links.json');

// Benutzer-spezifische Kunden filtern
if ($isAdmin) {
    // Admin sieht alle Kunden mit ihren originalen IDs
    $userCustomers = $customers;
} else {
    // Benutzer-spezifische Kunden - WICHTIG: Schlüssel beibehalten!
    $userCustomers = [];
    foreach ($customers as $customerKey => $customer) {
        if (getArrayValue($customer, 'user_id') === $userId) {
            $userCustomers[$customerKey] = $customer;
        }
    }
}

// VIEW ACTION - Integriert (nicht mehr als separate Datei)
if ($action === 'view' && $customerId):
    // Kunden suchen und validieren
    $customer = null;
    if (isset($customers[$customerId])) {
        $customer = $customers[$customerId];
    } else {
        // Fallback: Suche nach ID in den Werten (falls Array-Key-Problem)
        foreach ($customers as $key => $customerData) {
            if (($customerData['id'] ?? '') === $customerId) {
                $customer = $customerData;
                $customerId = $key; // Korrigiere die ID
                break;
            }
        }
    }

    // Fehlerbehandlung: Kunde nicht gefunden
    if (!$customer) {
        echo '<div class="error-page"><div class="error-content" style="text-align: center; padding: 60px 20px;">';
        echo '<i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f56565; margin-bottom: 16px;"></i>';
        echo '<h1 style="margin-bottom: 16px;">Kunde nicht gefunden</h1>';
        echo '<p style="color: #8b8fa3; margin-bottom: 24px;">Der Kunde mit ID "' . htmlspecialchars($customerId) . '" existiert nicht.</p>';
        echo '<a href="?page=customers" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Zurück zur Kundenverwaltung</a>';
        echo '</div></div>';
        return;
    }

    // Berechtigungsprüfung
    if (!$isAdmin && getArrayValue($customer, 'user_id') !== $userId) {
        echo '<div class="error-page"><div class="error-content" style="text-align: center; padding: 60px 20px;">';
        echo '<i class="fas fa-ban" style="font-size: 48px; color: #f56565; margin-bottom: 16px;"></i>';
        echo '<h1 style="margin-bottom: 16px;">Keine Berechtigung</h1>';
        echo '<p style="color: #8b8fa3; margin-bottom: 24px;">Sie haben keine Berechtigung, diesen Kunden anzuzeigen.</p>';
        echo '<a href="?page=customers" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Zurück zur Kundenverwaltung</a>';
        echo '</div></div>';
        return;
    }

    // Links für diesen Kunden laden
    $customerLinks = array_filter($links, function($link) use ($customerId) {
        return getArrayValue($link, 'customer_id') === $customerId;
    });

    // Kosten für diesen Kunden berechnen
    $customerCosts = calculateCustomerCosts($customerId, $links);

    // Debug Info für aktuelle Kundendaten
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        echo '<div style="background: #0066cc; color: white; padding: 10px; margin: 10px 0;">';
        echo '<h3>KUNDE ERFOLGREICH GELADEN:</h3>';
        echo '<p><strong>Geladene Kunden-ID:</strong> ' . htmlspecialchars($customerId) . '</p>';
        echo '<p><strong>Kundenname:</strong> ' . htmlspecialchars($customer['name']) . '</p>';
        echo '<p><strong>Kunden User-ID:</strong> ' . htmlspecialchars($customer['user_id'] ?? 'KEINE') . '</p>';
        echo '<p><strong>Links gefunden:</strong> ' . count($customerLinks) . '</p>';
        echo '<p><strong>Geschätzte monatliche Kosten:</strong> ' . formatCostDisplay($customerCosts['estimated_monthly'], null, 'EUR') . '</p>';
        echo '</div>';
    }
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
        <?php if ($customerCosts['estimated_monthly'] > 0): ?>
            <div style="margin-top: 8px;">
                <span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                    💰 Geschätzte monatliche Kosten: <?= formatCostDisplay($customerCosts['estimated_monthly'], null, 'EUR') ?>/Monat
                </span>
            </div>
        <?php endif; ?>
    </div>
    <div class="action-buttons">
        <?php if ($isAdmin || getArrayValue($customer, 'user_id') === $userId): ?>
            <a href="?page=customers&action=edit&id=<?= urlencode($customerId) ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Bearbeiten
            </a>
            <a href="?page=links&action=create&customer_id=<?= urlencode($customerId) ?>" class="btn btn-success">
                <i class="fas fa-link"></i> Neuer Link
            </a>
            <a href="?page=customers&action=delete&id=<?= urlencode($customerId) ?>" class="btn btn-danger" onclick="return confirm('Kunde wirklich löschen?')">
                <i class="fas fa-trash"></i> Löschen
            </a>
        <?php else: ?>
            <a href="?page=links&action=create&customer_id=<?= urlencode($customerId) ?>" class="btn btn-success">
                <i class="fas fa-link"></i> Neuer Link
            </a>
        <?php endif; ?>
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
    <?php if ($customerCosts['links_with_costs'] > 0): ?>
        <button class="tab" onclick="showTab('costs')" style="padding: 12px 24px; background: none; border: none; color: #8b8fa3; border-bottom: 2px solid transparent; font-weight: 600; cursor: pointer;">
            💰 Kosten
        </button>
    <?php endif; ?>
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
                <a href="?page=links&action=create&customer_id=<?= urlencode($customerId) ?>" class="btn btn-success">
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
                    <a href="?page=links&action=create&customer_id=<?= urlencode($customerId) ?>" class="btn btn-success" style="margin-top: 16px;">
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
                                <th>Kosten</th>
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
                                            case 'aktiv': $badgeClass = 'badge-success'; break;
                                            case 'ausstehend': $badgeClass = 'badge-warning'; break;
                                            case 'defekt': $badgeClass = 'badge-danger'; break;
                                        }
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span>
                                    </td>
                                    <td style="color: #8b8fa3; font-size: 12px;">
                                        <?php if (!empty($link['cost_amount']) && !empty($link['cost_type'])): ?>
                                            <div style="color: #f59e0b; font-weight: 600;">
                                                💰 <?= formatCostDisplay($link['cost_amount'], $link['cost_type'], $link['cost_currency'] ?? 'EUR') ?>
                                            </div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
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

<!-- Kosten Tab -->
<?php if ($customerCosts['links_with_costs'] > 0): ?>
<div id="costsTab" class="tab-content" style="display: none;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">💰 Kosten-Übersicht für <?= e($customer['name']) ?></h3>
            <p class="card-subtitle">Detaillierte Aufschlüsselung aller Kosten</p>
        </div>
        <div class="card-body">
            <!-- Gesamtkosten-Übersicht -->
            <div style="background: linear-gradient(135deg, #2a2d42, #1f2235); border: 2px solid #f59e0b; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 16px; color: #f59e0b; font-weight: 600; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">
                        GESCHÄTZTE MONATLICHE KOSTEN
                    </div>
                    <div style="font-size: 36px; font-weight: bold; color: #f59e0b; margin-bottom: 8px;">
                        <?= formatCostDisplay($customerCosts['estimated_monthly'], null, 'EUR') ?>
                    </div>
                    <div style="font-size: 14px; color: #8b8fa3;">
                        Basierend auf <?= $customerCosts['links_with_costs'] ?> von <?= $customerCosts['total_links'] ?> Links
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px;">
                    <?php if ($customerCosts['total_monthly'] > 0): ?>
                        <div style="text-align: center; padding: 16px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3);">
                            <div style="font-size: 20px; font-weight: bold; color: #f59e0b;">
                                <?= formatCostDisplay($customerCosts['total_monthly'], null, 'EUR') ?>
                            </div>
                            <div style="font-size: 12px; color: #8b8fa3;">Monatlich</div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($customerCosts['total_yearly'] > 0): ?>
                        <div style="text-align: center; padding: 16px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3);">
                            <div style="font-size: 20px; font-weight: bold; color: #f59e0b;">
                                <?= formatCostDisplay($customerCosts['total_yearly'], null, 'EUR') ?>
                            </div>
                            <div style="font-size: 12px; color: #8b8fa3;">Jährlich (<?= formatCostDisplay($customerCosts['total_yearly'] / 12, null, 'EUR') ?>/Monat)</div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($customerCosts['total_onetime'] > 0): ?>
                        <div style="text-align: center; padding: 16px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3);">
                            <div style="font-size: 20px; font-weight: bold; color: #f59e0b;">
                                <?= formatCostDisplay($customerCosts['total_onetime'], null, 'EUR') ?>
                            </div>
                            <div style="font-size: 12px; color: #8b8fa3;">Einmalig</div>
                        </div>
                    <?php endif; ?>
                    
                    <div style="text-align: center; padding: 16px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3);">
                        <div style="font-size: 20px; font-weight: bold; color: #f59e0b;">
                            <?= $customerCosts['links_with_costs'] ?>
                        </div>
                        <div style="font-size: 12px; color: #8b8fa3;">Links mit Kosten</div>
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
                    <?php if ($customerCosts['links_with_costs'] > 0): ?>
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; font-weight: bold; color: #f59e0b; margin-bottom: 8px;">
                                <?= $customerCosts['links_with_costs'] ?>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">Links mit Kosten</div>
                        </div>
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; font-weight: bold; color: #f59e0b; margin-bottom: 8px;">
                                <?= formatCostDisplay($customerCosts['estimated_monthly'], null, 'EUR') ?>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">Kosten/Monat</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
</script>

<?php 
    return; // Beende hier für View-Action
endif;

// Verschiedene Actions verarbeiten
if ($action === 'index'): 
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

// Gesamtkosten aller Kunden berechnen
$totalCustomerCosts = [
    'monthly' => 0,
    'yearly' => 0,
    'onetime' => 0,
    'estimated_monthly' => 0,
    'customers_with_costs' => 0
];

foreach ($userCustomers as $customerKey => $customer) {
    $customerCosts = calculateCustomerCosts($customerKey, $links);
    if ($customerCosts['links_with_costs'] > 0) {
        $totalCustomerCosts['customers_with_costs']++;
        $totalCustomerCosts['monthly'] += $customerCosts['total_monthly'];
        $totalCustomerCosts['yearly'] += $customerCosts['total_yearly'];
        $totalCustomerCosts['onetime'] += $customerCosts['total_onetime'];
        $totalCustomerCosts['estimated_monthly'] += $customerCosts['estimated_monthly'];
    }
}
?>
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
                <?php if ($totalCustomerCosts['estimated_monthly'] > 0): ?>
                    • Geschätzte monatliche Kosten: <strong style="color: #f59e0b;"><?= formatCostDisplay($totalCustomerCosts['estimated_monthly'], null, 'EUR') ?>/Monat</strong>
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
                
                <!-- Kosten-Übersicht -->
                <?php if ($totalCustomerCosts['estimated_monthly'] > 0): ?>
                    <div style="margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #2a2d42, #1f2235); border: 2px solid #f59e0b; border-radius: 12px;">
                        <div style="text-align: center; margin-bottom: 16px;">
                            <div style="font-size: 14px; color: #f59e0b; font-weight: 600; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">
                                💰 GESAMTE KUNDENKOSTEN
                            </div>
                            <div style="font-size: 32px; font-weight: bold; color: #f59e0b; margin-bottom: 8px;">
                                <?= formatCostDisplay($totalCustomerCosts['estimated_monthly'], null, 'EUR') ?>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">Geschätzte monatliche Kosten</div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px;">
                            <?php if ($totalCustomerCosts['monthly'] > 0): ?>
                                <div style="text-align: center; padding: 12px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3);">
                                    <div style="font-size: 16px; font-weight: bold; color: #f59e0b;">
                                        <?= formatCostDisplay($totalCustomerCosts['monthly'], null, 'EUR') ?>
                                    </div>
                                    <div style="font-size: 11px; color: #8b8fa3;">Monatlich</div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($totalCustomerCosts['yearly'] > 0): ?>
                                <div style="text-align: center; padding: 12px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3);">
                                    <div style="font-size: 16px; font-weight: bold; color: #f59e0b;">
                                        <?= formatCostDisplay($totalCustomerCosts['yearly'], null, 'EUR') ?>
                                    </div>
                                    <div style="font-size: 11px; color: #8b8fa3;">Jährlich</div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($totalCustomerCosts['onetime'] > 0): ?>
                                <div style="text-align: center; padding: 12px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3);">
                                    <div style="font-size: 16px; font-weight: bold; color: #f59e0b;">
                                        <?= formatCostDisplay($totalCustomerCosts['onetime'], null, 'EUR') ?>
                                    </div>
                                    <div style="font-size: 11px; color: #8b8fa3;">Einmalig</div>
                                </div>
                            <?php endif; ?>
                            
                            <div style="text-align: center; padding: 12px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3);">
                                <div style="font-size: 16px; font-weight: bold; color: #f59e0b;">
                                    <?= $totalCustomerCosts['customers_with_costs'] ?>
                                </div>
                                <div style="font-size: 11px; color: #8b8fa3;">Kunden mit Kosten</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
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
                    <?php foreach ($recentCustomers as $customerKey => $customer): 
                        $customerCosts = calculateCustomerCosts($customerKey, $links);
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #3a3d52;">
                            <div>
                                <div style="font-weight: 600; color: #e2e8f0; font-size: 14px;">
                                    <a href="?page=customers&action=view&id=<?= htmlspecialchars($customerKey) ?>" style="color: #4dabf7; text-decoration: none;">
                                        <?= e($customer['name']) ?>
                                    </a>
                                </div>
                                <div style="font-size: 12px; color: #8b8fa3;">
                                    <?= e(getArrayValue($customer, 'company', 'Kein Unternehmen')) ?>
                                    <?php if ($customerCosts['estimated_monthly'] > 0): ?>
                                        • <span style="color: #f59e0b;"><?= formatCostDisplay($customerCosts['estimated_monthly'], null, 'EUR') ?>/Monat</span>
                                    <?php endif; ?>
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
                                <th>Kosten/Monat</th>
                                <?php if ($isAdmin): ?>
                                    <th>Erstellt von</th>
                                <?php endif; ?>
                                <th>Erstellt</th>
                                <th style="width: 120px;">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody">
                            <?php foreach ($userCustomers as $customerKey => $customer): 
                                // Links für diesen Kunden zählen
                                $customerLinks = array_filter($links, function($link) use ($customerKey) {
                                    return getArrayValue($link, 'customer_id') === $customerKey;
                                });
                                $linkCount = count($customerLinks);
                                
                                // Kosten für diesen Kunden berechnen
                                $customerCosts = calculateCustomerCosts($customerKey, $links);
                                
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
                                                <a href="?page=customers&action=view&id=<?= urlencode($customerKey) ?>" style="color: #4dabf7; text-decoration: none;">
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
                                            <a href="?page=links&customer_id=<?= urlencode($customerKey) ?>" style="color: #4dabf7; text-decoration: none;">
                                                <?= $linkCount ?> Link<?= $linkCount !== 1 ? 's' : '' ?>
                                            </a>
                                        <?php else: ?>
                                            0 Links
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 12px;">
                                        <?php if ($customerCosts['estimated_monthly'] > 0): ?>
                                            <div style="color: #f59e0b; font-weight: 600;">
                                                💰 <?= formatCostDisplay($customerCosts['estimated_monthly'], null, 'EUR') ?>
                                            </div>
                                            <?php if ($customerCosts['links_with_costs'] < $customerCosts['total_links']): ?>
                                                <div style="color: #8b8fa3; font-size: 10px;">
                                                    (<?= $customerCosts['links_with_costs'] ?>/<?= $customerCosts['total_links'] ?> Links)
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #8b8fa3;">-</span>
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
                                            <a href="?page=customers&action=view&id=<?= urlencode($customerKey) ?>" class="btn btn-sm btn-primary" title="Anzeigen (ID: <?= htmlspecialchars($customerKey) ?>)">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($isAdmin || getArrayValue($customer, 'user_id') === $userId): ?>
                                                <a href="?page=customers&action=edit&id=<?= urlencode($customerKey) ?>" class="btn btn-sm btn-secondary" title="Bearbeiten">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?page=customers&action=delete&id=<?= urlencode($customerKey) ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Kunde wirklich löschen?')">
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

<script>
// JavaScript-Code für Filter-Funktionalität
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
}
</script>

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