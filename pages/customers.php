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

// GET-Verarbeitung für DELETE (separate Behandlung)
if ($action === 'delete' && $customerId && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $customers = loadData('customers.json');
    
    // Kunden-Validierung
    if (isset($customers[$customerId]) && ($isAdmin || getArrayValue($customers[$customerId], 'user_id') === $userId)) {
        unset($customers[$customerId]);
        if (saveData('customers.json', $customers)) {
            redirectWithMessage('?page=customers', 'Kunde erfolgreich gelöscht.');
        } else {
            setFlashMessage('Fehler beim Löschen des Kunden.', 'error');
            header('Location: ?page=customers');
            exit;
        }
    } else {
        setFlashMessage('Kunde nicht gefunden oder keine Berechtigung.', 'error');
        header('Location: ?page=customers');
        exit;
    }
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

// CREATE & EDIT ACTION - Formulare
if ($action === 'create' || ($action === 'edit' && $customerId)):
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
        <h1 class="page-title">
            <?= $action === 'create' ? 'Neuen Kunden erstellen' : 'Kunde bearbeiten' ?>
        </h1>
        <p class="page-subtitle">
            <?= $action === 'create' 
                ? 'Fügen Sie einen neuen Kunden zu Ihrem System hinzu' 
                : 'Bearbeiten Sie die Informationen für ' . e($customer['name'] ?? '') ?>
        </p>
    </div>
</div>

<?php showFlashMessage(); ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Kundendaten</h3>
        <p class="card-subtitle">Alle mit * markierten Felder sind Pflichtfelder</p>
    </div>
    <div class="card-body">
        <form method="post" action="?page=customers&action=<?= $action ?><?= $customerId ? '&id=' . urlencode($customerId) : '' ?>" class="form">
            <!-- Grundinformationen -->
            <div class="form-section">
                <div class="section-header">
                    <h4 class="section-title">
                        <i class="fas fa-user section-icon"></i>
                        Grundinformationen
                    </h4>
                    <p class="section-subtitle">Basis-Kundendaten und Kontaktinformationen</p>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="name" class="form-label required">
                                <i class="fas fa-user-tag"></i>
                                Name
                            </label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?= e($customer['name'] ?? $_POST['name'] ?? '') ?>" 
                                   required placeholder="Vollständiger Name">
                            <div class="field-hint">Der vollständige Name des Kunden</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">
                                <i class="fas fa-phone"></i>
                                Telefon
                            </label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?= e($customer['phone'] ?? $_POST['phone'] ?? '') ?>"
                                   placeholder="+49 123 456789">
                            <div class="field-hint">Telefonnummer für direkten Kontakt</div>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i>
                                E-Mail
                            </label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= e($customer['email'] ?? $_POST['email'] ?? '') ?>"
                                   placeholder="kunde@beispiel.de">
                            <div class="field-hint">Haupt-E-Mail-Adresse des Kunden</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="status" class="form-label">
                                <i class="fas fa-toggle-on"></i>
                                Status
                            </label>
                            <select id="status" name="status" class="form-control">
                                <option value="aktiv" <?= (($customer['status'] ?? $_POST['status'] ?? 'aktiv') === 'aktiv') ? 'selected' : '' ?>>
                                    🟢 Aktiv
                                </option>
                                <option value="inaktiv" <?= (($customer['status'] ?? $_POST['status'] ?? '') === 'inaktiv') ? 'selected' : '' ?>>
                                    🔴 Inaktiv
                                </option>
                            </select>
                            <div class="field-hint">Aktueller Status des Kunden</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Firmeninformationen -->
            <div class="form-section">
                <div class="section-header">
                    <h4 class="section-title">
                        <i class="fas fa-building section-icon"></i>
                        Firmeninformationen
                    </h4>
                    <p class="section-subtitle">Unternehmens- und Geschäftsdaten</p>
                </div>
                <div class="form-row">
                    <div class="form-col-full">
                        <div class="form-group">
                            <label for="company" class="form-label">
                                <i class="fas fa-briefcase"></i>
                                Unternehmen
                            </label>
                            <input type="text" id="company" name="company" class="form-control" 
                                   value="<?= e($customer['company'] ?? $_POST['company'] ?? '') ?>"
                                   placeholder="Name des Unternehmens">
                            <div class="field-hint">Offizieller Firmenname oder Organisation</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Adressinformationen -->
            <div class="form-section">
                <div class="section-header">
                    <h4 class="section-title">
                        <i class="fas fa-map-marker-alt section-icon"></i>
                        Adressinformationen
                    </h4>
                    <p class="section-subtitle">Vollständige Postanschrift</p>
                </div>
                <div class="form-row">
                    <div class="form-col-full">
                        <div class="form-group">
                            <label for="address" class="form-label">
                                <i class="fas fa-home"></i>
                                Straße und Hausnummer
                            </label>
                            <input type="text" id="address" name="address" class="form-control" 
                                   value="<?= e($customer['address'] ?? $_POST['address'] ?? '') ?>"
                                   placeholder="Musterstraße 123">
                            <div class="field-hint">Vollständige Straßenanschrift mit Hausnummer</div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="postal_code" class="form-label">
                                <i class="fas fa-mail-bulk"></i>
                                PLZ
                            </label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control" 
                                   value="<?= e($customer['postal_code'] ?? $_POST['postal_code'] ?? '') ?>"
                                   placeholder="12345">
                            <div class="field-hint">Postleitzahl</div>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="city" class="form-label">
                                <i class="fas fa-city"></i>
                                Stadt
                            </label>
                            <input type="text" id="city" name="city" class="form-control" 
                                   value="<?= e($customer['city'] ?? $_POST['city'] ?? '') ?>"
                                   placeholder="Musterstadt">
                            <div class="field-hint">Ortsname</div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col-full">
                        <div class="form-group">
                            <label for="country" class="form-label">
                                <i class="fas fa-globe"></i>
                                Land
                            </label>
                            <input type="text" id="country" name="country" class="form-control" 
                                   value="<?= e($customer['country'] ?? $_POST['country'] ?? '') ?>" 
                                   placeholder="Deutschland">
                            <div class="field-hint">Land der Hauptgeschäftstätigkeit</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Websites -->
            <div class="form-section">
                <div class="section-header">
                    <h4 class="section-title">
                        <i class="fas fa-globe-americas section-icon"></i>
                        Websites
                    </h4>
                    <p class="section-subtitle">Online-Präsenzen und Webseiten des Kunden</p>
                </div>
                <div id="websitesContainer">
                    <?php 
                    $websites = $customer['websites'] ?? $_POST['websites'] ?? [['url' => '', 'title' => '', 'description' => '']];
                    if (empty($websites)) {
                        $websites = [['url' => '', 'title' => '', 'description' => '']];
                    }
                    foreach ($websites as $index => $website): 
                    ?>
                        <div class="website-group" data-index="<?= $index ?>">
                            <div class="website-card">
                                <div class="website-header">
                                    <h5 class="website-title">
                                        <i class="fas fa-link"></i>
                                        Website <?= $index + 1 ?>
                                    </h5>
                                    <?php if ($index > 0): ?>
                                        <button type="button" class="btn btn-sm btn-danger remove-website">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-link"></i>
                                                Website-URL
                                            </label>
                                            <input type="url" name="websites[<?= $index ?>][url]" class="form-control" 
                                                   value="<?= e($website['url'] ?? '') ?>" 
                                                   placeholder="https://beispiel.de">
                                            <div class="field-hint">Vollständige URL der Website</div>
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-tag"></i>
                                                Titel
                                            </label>
                                            <input type="text" name="websites[<?= $index ?>][title]" class="form-control" 
                                                   value="<?= e($website['title'] ?? '') ?>" 
                                                   placeholder="Wird automatisch erkannt">
                                            <div class="field-hint">Name oder Titel der Website</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-col-full">
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-comment"></i>
                                                Beschreibung
                                            </label>
                                            <input type="text" name="websites[<?= $index ?>][description]" class="form-control" 
                                                   value="<?= e($website['description'] ?? '') ?>" 
                                                   placeholder="Kurze Beschreibung der Website (optional)">
                                            <div class="field-hint">Zusätzliche Informationen zur Website</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="addWebsite" class="btn btn-secondary add-website-btn">
                    <i class="fas fa-plus"></i> Weitere Website hinzufügen
                </button>
            </div>

            <!-- Notizen -->
            <div class="form-section">
                <div class="section-header">
                    <h4 class="section-title">
                        <i class="fas fa-sticky-note section-icon"></i>
                        Zusätzliche Informationen
                    </h4>
                    <p class="section-subtitle">Interne Notizen und wichtige Hinweise</p>
                </div>
                <div class="form-row">
                    <div class="form-col-full">
                        <div class="form-group">
                            <label for="notes" class="form-label">
                                <i class="fas fa-edit"></i>
                                Notizen
                            </label>
                            <textarea id="notes" name="notes" class="form-control notes-textarea" rows="5" 
                                      placeholder="Zusätzliche Notizen zum Kunden, wichtige Informationen, Besonderheiten..."><?= e($customer['notes'] ?? $_POST['notes'] ?? '') ?></textarea>
                            <div class="field-hint">Interne Notizen, die nur für Sie sichtbar sind</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form-Buttons -->
            <div class="form-actions">
                <div class="action-buttons-container">
                    <a href="?page=customers<?= $action === 'edit' && $customerId ? '&action=view&id=' . urlencode($customerId) : '' ?>" class="btn btn-secondary btn-cancel">
                        <i class="fas fa-times"></i> 
                        Abbrechen
                    </a>
                    <button type="submit" class="btn btn-primary btn-save">
                        <i class="fas fa-save"></i> 
                        <?= $action === 'create' ? 'Kunde erstellen' : 'Änderungen speichern' ?>
                    </button>
                </div>
                <div class="form-footer-info">
                    <i class="fas fa-info-circle"></i>
                    <?= $action === 'create' 
                        ? 'Nach dem Erstellen können Sie sofort Links für diesen Kunden anlegen.' 
                        : 'Ihre Änderungen werden sofort gespeichert und sind direkt verfügbar.' ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- CSS für das moderne Filter-Design -->
<style>
/* Verbessertes Formular-Styling */
.form-section {
    background: #2a2d42;
    border-radius: 12px;
    margin-bottom: 24px;
    border: 1px solid #3a3d52;
    overflow: hidden;
}

.section-header {
    background: linear-gradient(135deg, #3a3d52, #2a2d42);
    padding: 20px 24px;
    border-bottom: 1px solid #3a3d52;
}

.section-title {
    margin: 0;
    color: #e2e8f0;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-icon {
    color: #4dabf7;
    font-size: 16px;
}

.section-subtitle {
    margin: 6px 0 0 0;
    color: #8b8fa3;
    font-size: 14px;
    font-weight: normal;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    padding: 24px;
}

.form-col-full {
    grid-column: 1 / -1;
}

.form-col {
    display: flex;
    flex-direction: column;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #e2e8f0;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-label.required::after {
    content: " *";
    color: #f56565;
    font-weight: bold;
}

.form-label i {
    color: #4dabf7;
    font-size: 12px;
    width: 14px;
}

.form-control {
    background: #1a1d29;
    border: 2px solid #3a3d52;
    color: #e2e8f0;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #4dabf7;
    background: #1f2235;
    box-shadow: 0 0 0 3px rgba(77, 171, 247, 0.1);
    outline: none;
}

.form-control::placeholder {
    color: #6b7280;
}

.field-hint {
    font-size: 12px;
    color: #8b8fa3;
    margin-top: 4px;
    line-height: 1.3;
}

.notes-textarea {
    resize: vertical;
    min-height: 120px;
}

/* Website-spezifische Styles */
.website-card {
    background: #1a1d29;
    border: 1px solid #3a3d52;
    border-radius: 8px;
    margin-bottom: 16px;
    overflow: hidden;
}

.website-header {
    background: #2a2d42;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #3a3d52;
}

.website-title {
    margin: 0;
    color: #e2e8f0;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.website-title i {
    color: #4dabf7;
}

.website-card .form-row {
    padding: 16px;
}

.add-website-btn {
    margin-top: 12px;
    background: #2a2d42;
    border: 2px dashed #3a3d52;
    color: #8b8fa3;
    padding: 12px 20px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.add-website-btn:hover {
    border-color: #4dabf7;
    color: #4dabf7;
    background: #1f2235;
}

/* Form Actions */
.form-actions {
    background: #2a2d42;
    border-radius: 12px;
    padding: 24px;
    border: 1px solid #3a3d52;
    margin-top: 24px;
}

.action-buttons-container {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-bottom: 16px;
}

.btn-cancel {
    background: #374151;
    border-color: #4b5563;
    color: #e2e8f0;
    padding: 12px 24px;
    font-weight: 500;
}

.btn-cancel:hover {
    background: #4b5563;
    border-color: #6b7280;
    color: #f3f4f6;
}

.btn-save {
    background: linear-gradient(135deg, #4dabf7, #339af0);
    border-color: #4dabf7;
    color: #ffffff;
    padding: 12px 24px;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(77, 171, 247, 0.2);
}

.btn-save:hover {
    background: linear-gradient(135deg, #339af0, #228be6);
    border-color: #339af0;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(77, 171, 247, 0.3);
}

.form-footer-info {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #8b8fa3;
    font-size: 13px;
    padding: 12px 16px;
    background: #1a1d29;
    border-radius: 6px;
    border-left: 3px solid #4dabf7;
}

.form-footer-info i {
    color: #4dabf7;
    font-size: 14px;
}

.btn {
    transition: all 0.3s ease;
    border-radius: 6px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    border: none;
    cursor: pointer;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Responsive Design */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 20px;
    }
    
    .section-header {
        padding: 16px 20px;
    }
    
    .section-title {
        font-size: 16px;
    }
    
    .form-control {
        padding: 10px 14px;
    }
    
    .action-buttons-container {
        flex-direction: column-reverse;
        gap: 8px;
    }
    
    .btn-cancel,
    .btn-save {
        width: 100%;
        text-align: center;
    }
    
    .form-footer-info {
        text-align: center;
        flex-direction: column;
        gap: 4px;
    }
}

.form-control:hover {
    border-color: #4a5568;
}

.website-card:hover {
    border-color: #4a5568;
}

select.form-control option {
    background: #1a1d29;
    color: #e2e8f0;
    padding: 8px;
}

.btn-save:active {
    transform: translateY(0);
    transition: transform 0.1s ease;
}

.btn:focus,
.form-control:focus {
    outline: 2px solid #4dabf7;
    outline-offset: 2px;
}

.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.card-header {
    background: linear-gradient(135deg, #2a2d42, #1f2235);
    border-bottom: 2px solid #3a3d52;
}

.card-title {
    color: #e2e8f0;
    font-size: 20px;
    font-weight: 600;
}

.card-subtitle {
    color: #8b8fa3;
    font-size: 14px;
    margin-top: 4px;
}
</style>

<script>
// Website-Management JavaScript
let websiteIndex = <?= count($websites) ?>;

document.getElementById('addWebsite').addEventListener('click', function() {
    const container = document.getElementById('websitesContainer');
    const newWebsite = document.createElement('div');
    newWebsite.className = 'website-group';
    newWebsite.setAttribute('data-index', websiteIndex);
    
    newWebsite.innerHTML = `
        <div class="website-card">
            <div class="website-header">
                <h5 class="website-title">
                    <i class="fas fa-link"></i>
                    Website ${websiteIndex + 1}
                </h5>
                <button type="button" class="btn btn-sm btn-danger remove-website">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-link"></i>
                            Website-URL
                        </label>
                        <input type="url" name="websites[${websiteIndex}][url]" class="form-control" 
                               placeholder="https://beispiel.de">
                        <div class="field-hint">Vollständige URL der Website</div>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag"></i>
                            Titel
                        </label>
                        <input type="text" name="websites[${websiteIndex}][title]" class="form-control" 
                               placeholder="Wird automatisch erkannt">
                        <div class="field-hint">Name oder Titel der Website</div>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-col-full">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-comment"></i>
                            Beschreibung
                        </label>
                        <input type="text" name="websites[${websiteIndex}][description]" class="form-control" 
                               placeholder="Kurze Beschreibung der Website (optional)">
                        <div class="field-hint">Zusätzliche Informationen zur Website</div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(newWebsite);
    websiteIndex++;
});

// Website entfernen
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-website') || e.target.closest('.remove-website')) {
        const websiteGroup = e.target.closest('.website-group');
        if (websiteGroup) {
            websiteGroup.remove();
            
            // Website-Nummern neu durchnummerieren
            const remainingWebsites = document.querySelectorAll('.website-group .website-title');
            remainingWebsites.forEach((title, index) => {
                title.innerHTML = `<i class="fas fa-link"></i> Website ${index + 1}`;
            });
        }
    }
});

// Form-Enhancement: Auto-URL-Formatting
document.addEventListener('input', function(e) {
    if (e.target.type === 'url' && e.target.name && e.target.name.includes('[url]')) {
        const value = e.target.value.trim();
        if (value && !value.startsWith('http://') && !value.startsWith('https://')) {
            // Nur wenn der Benutzer aufhört zu tippen (kurze Verzögerung)
            clearTimeout(e.target.urlTimer);
            e.target.urlTimer = setTimeout(() => {
                if (e.target.value === value && value.length > 3) {
                    e.target.value = 'https://' + value;
                }
            }, 1000);
        }
    }
});
</script>

<?php 
    return; // Beende hier für Create/Edit-Action
endif;

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

// INDEX ACTION - Kundenliste
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

    <!-- Filter und Suche - MODERNER STIL -->
    <div class="modern-filter-section">
        <div class="modern-filter-container">
            <div class="modern-search-container">
                <div class="modern-search-wrapper">
                    <i class="fas fa-search modern-search-icon"></i>
                    <input 
                        type="text" 
                        class="modern-search-input" 
                        placeholder="Kunden durchsuchen (Name, Unternehmen, E-Mail)..."
                        id="customerSearch"
                        onkeyup="filterCustomers()"
                    >
                </div>
            </div>
            <div class="modern-filter-controls">
                <div class="modern-filter-group">
                    <select class="modern-filter-select" id="statusFilter" onchange="filterCustomers()">
                        <option value="">Nach Status filtern</option>
                        <option value="aktiv">🟢 Aktive Kunden</option>
                        <option value="inaktiv">🔴 Inaktive Kunden</option>
                    </select>
                </div>
                <div class="modern-filter-group">
                    <select class="modern-filter-select" id="countryFilter" onchange="filterCustomers()">
                        <option value="">Nach Land filtern</option>
                        <?php foreach (array_keys($countryStats) as $country): ?>
                            <option value="<?= e($country) ?>">🌍 <?= e($country) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="modern-filter-reset-btn" onclick="resetFilters()" title="Filter zurücksetzen">
                    <i class="fas fa-times"></i>
                </button>
            </div>
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
                                <th>Websites</th>
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
                                        <?php 
                                        // Websites anzeigen - sowohl alte 'website' als auch neue 'websites' unterstützen
                                        $websites = [];
                                        
                                        // Alte 'website' Feld unterstützen (für Rückwärtskompatibilität)
                                        if (!empty($customer['website'])) {
                                            $websites[] = [
                                                'url' => $customer['website'],
                                                'title' => parse_url($customer['website'], PHP_URL_HOST) ?: $customer['website']
                                            ];
                                        }
                                        
                                        // Neue 'websites' Array hinzufügen
                                        if (!empty($customer['websites']) && is_array($customer['websites'])) {
                                            foreach ($customer['websites'] as $website) {
                                                if (!empty($website['url'])) {
                                                    $websites[] = [
                                                        'url' => $website['url'],
                                                        'title' => $website['title'] ?: parse_url($website['url'], PHP_URL_HOST) ?: $website['url']
                                                    ];
                                                }
                                            }
                                        }
                                        
                                        if (!empty($websites)): ?>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <?php foreach ($websites as $index => $website): ?>
                                                    <div style="display: flex; align-items: center; gap: 6px;">
                                                        <i class="fas fa-globe" style="color: #4dabf7; font-size: 10px; margin-right: 2px;"></i>
                                                        <a href="<?= e($website['url']) ?>" target="_blank" 
                                                           style="color: #4dabf7; text-decoration: none; font-size: 12px; line-height: 1.3;"
                                                           title="<?= e($website['url']) ?>">
                                                            <?= e(strlen($website['title']) > 20 ? substr($website['title'], 0, 20) . '...' : $website['title']) ?>
                                                            <i class="fas fa-external-link-alt" style="margin-left: 3px; font-size: 9px; opacity: 0.7;"></i>
                                                        </a>
                                                    </div>
                                                    <?php if ($index < count($websites) - 1): ?>
                                                        <div style="height: 2px;"></div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #6b7280; font-style: italic; font-size: 12px;">Keine Websites</span>
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
                                            <a href="?page=customers&action=view&id=<?= urlencode($customerKey) ?>" class="btn btn-sm btn-primary" title="Anzeigen">
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

<!-- MODERNES FILTER CSS -->
<style>
/* MODERN FILTER SECTION - KOMPLETT NEU */
.modern-filter-section {
    margin: 24px 0;
    padding: 20px;
    background: linear-gradient(135deg, #2a2d42, #1f2235);
    border-radius: 16px;
    border: 1px solid #3a3d52;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.modern-filter-container {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.modern-search-container {
    flex: 1;
    min-width: 300px;
    max-width: 500px;
}

.modern-search-wrapper {
    position: relative;
    width: 100%;
}

.modern-search-input {
    width: 100%;
    background: #1a1d29;
    border: 2px solid #3a3d52;
    border-radius: 12px;
    padding: 16px 20px 16px 52px;
    color: #e2e8f0;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.modern-search-input:focus {
    outline: none;
    border-color: #4dabf7;
    background: #141720;
    box-shadow: 0 0 0 4px rgba(77, 171, 247, 0.15), 0 4px 16px rgba(0, 0, 0, 0.2);
    transform: translateY(-1px);
}

.modern-search-input::placeholder {
    color: #8b8fa3;
    font-weight: 400;
}

.modern-search-icon {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #8b8fa3;
    font-size: 16px;
    pointer-events: none;
    z-index: 1;
    transition: color 0.3s ease;
}

.modern-search-wrapper:focus-within .modern-search-icon {
    color: #4dabf7;
}

.modern-filter-controls {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.modern-filter-group {
    position: relative;
}

.modern-filter-select {
    background: #1a1d29;
    border: 2px solid #3a3d52;
    border-radius: 12px;
    padding: 16px 44px 16px 20px;
    color: #e2e8f0;
    font-size: 15px;
    font-weight: 500;
    min-width: 200px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2394A3B8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 16px center;
    background-repeat: no-repeat;
    background-size: 20px;
}

.modern-filter-select:focus {
    outline: none;
    border-color: #4dabf7;
    background-color: #141720;
    box-shadow: 0 0 0 4px rgba(77, 171, 247, 0.15), 0 4px 16px rgba(0, 0, 0, 0.2);
    transform: translateY(-1px);
}

.modern-filter-select:hover {
    border-color: #4a5568;
    background-color: #141720;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.modern-filter-select option {
    background: #1a1d29;
    color: #e2e8f0;
    padding: 12px 16px;
    border: none;
}

.modern-filter-reset-btn {
    background: linear-gradient(135deg, #374151, #4b5563);
    border: 2px solid #4b5563;
    border-radius: 12px;
    padding: 16px 20px;
    color: #e2e8f0;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 56px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    font-size: 16px;
    font-weight: 600;
}

.modern-filter-reset-btn:hover {
    background: linear-gradient(135deg, #4b5563, #6b7280);
    border-color: #6b7280;
    color: #f3f4f6;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
}

.modern-filter-reset-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.modern-filter-reset-btn i {
    font-size: 16px;
}

/* Responsive Design für moderne Filter */
@media (max-width: 768px) {
    .modern-filter-container {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }
    
    .modern-search-container {
        min-width: unset;
        max-width: unset;
        width: 100%;
    }
    
    .modern-filter-controls {
        width: 100%;
        justify-content: space-between;
        gap: 12px;
    }
    
    .modern-filter-select {
        min-width: 140px;
        flex: 1;
        padding: 14px 40px 14px 16px;
        font-size: 14px;
    }
    
    .modern-filter-reset-btn {
        flex-shrink: 0;
        padding: 14px 16px;
        min-width: 48px;
    }
}

@media (max-width: 480px) {
    .modern-filter-section {
        margin: 16px 0;
        padding: 16px;
        border-radius: 12px;
    }
    
    .modern-filter-controls {
        flex-direction: column;
        gap: 12px;
    }
    
    .modern-filter-select,
    .modern-filter-reset-btn {
        width: 100%;
    }
    
    .modern-search-input {
        padding: 14px 16px 14px 48px;
        font-size: 14px;
    }
    
    .modern-search-icon {
        left: 16px;
        font-size: 14px;
    }
}

/* Hover-Effekte und Animationen */
.modern-search-input:hover {
    border-color: #4a5568;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

/* Spezielle Anpassungen für bessere Performance */
.modern-filter-section * {
    box-sizing: border-box;
}

/* Smooth Transitions */
.modern-filter-section *,
.modern-filter-section *::before,
.modern-filter-section *::after {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>

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
    
    // Zeige/verstecke Reset-Button
    const resetBtn = document.querySelector('.modern-filter-reset-btn');
    const hasActiveFilters = searchValue || statusValue || countryValue;
    if (resetBtn) {
        resetBtn.style.display = hasActiveFilters ? 'flex' : 'none';
    }
}

function resetFilters() {
    // Alle Filter zurücksetzen
    document.getElementById('customerSearch').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('countryFilter').value = '';
    
    // Filter anwenden (zeigt alle Zeilen)
    filterCustomers();
}

// Filter-Reset-Button initial verstecken
document.addEventListener('DOMContentLoaded', function() {
    const resetBtn = document.querySelector('.modern-filter-reset-btn');
    if (resetBtn) {
        resetBtn.style.display = 'none';
    }
});
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