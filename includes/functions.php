<?php
/**
 * LinkBuilder Pro - Funktionsbibliothek
 * includes/functions.php - Vollständige Version mit Benutzer-Verwaltung
 */

/**
 * =============================================================================
 * GRUND-FUNKTIONEN
 * =============================================================================
 */

/**
 * Lädt Daten aus einer JSON-Datei
 */
function loadData($filename) {
    $filepath = __DIR__ . '/../data/' . $filename;
    
    if (!file_exists($filepath)) {
        return [];
    }
    
    $content = file_get_contents($filepath);
    if ($content === false) {
        return [];
    }
    
    $data = json_decode($content, true);
    if ($data === null) {
        return [];
    }
    
    return $data;
}

/**
 * Speichert Daten in eine JSON-Datei
 */
function saveData($filename, $data) {
    $filepath = __DIR__ . '/../data/' . $filename;
    
    // Verzeichnis erstellen falls nötig
    $dataDir = dirname($filepath);
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0755, true)) {
            return false;
        }
    }
    
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonData === false) {
        return false;
    }
    
    return file_put_contents($filepath, $jsonData, LOCK_EX) !== false;
}

/**
 * Generiert eine eindeutige ID
 */
function generateId() {
    return uniqid('', true);
}

/**
 * Validiert eine URL
 */
function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validiert eine E-Mail-Adresse
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Bereinigt HTML-Output (XSS-Schutz)
 */
function e($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * =============================================================================
 * SESSION-VERWALTUNG
 * =============================================================================
 */

/**
 * Startet die Session sicher
 */
function ensureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Gibt die aktuelle Benutzer-ID zurück
 */
function getCurrentUserId() {
    ensureSession();
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    return 'default_user';
}

/**
 * Gibt die aktuelle Benutzerrolle zurück
 */
function getCurrentUserRole() {
    ensureSession();
    if (isset($_SESSION['user_role'])) {
        return $_SESSION['user_role'];
    }
    return 'admin';
}

/**
 * Aktuellen Benutzer laden (NEU für Admin-Verwaltung)
 */
function getCurrentUser() {
    $userId = getCurrentUserId();
    if (!$userId || $userId === 'default_user') {
        return null;
    }
    
    $users = loadData('users.json');
    $user = $users[$userId] ?? null;
    
    if (!$user) {
        return null;
    }
    
    // Session-Daten aktualisieren falls nötig
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    $_SESSION['user_name'] = $user['username'] ?? 'Benutzer';
    $_SESSION['user_email'] = $user['email'] ?? '';
    
    return $user;
}

/**
 * Prüfen ob Benutzer Admin ist (NEU)
 */
function isAdmin() {
    $user = getCurrentUser();
    return $user && ($user['role'] ?? 'user') === 'admin';
}

/**
 * Admin-Zugriff erzwingen (NEU)
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('HTTP/1.0 403 Forbidden');
        echo '<div class="alert alert-danger">Zugriff verweigert. Nur Administratoren haben Zugang zu dieser Seite.</div>';
        exit;
    }
}

/**
 * =============================================================================
 * FLASH-NACHRICHTEN
 * =============================================================================
 */

/**
 * Setzt eine Flash-Nachricht
 */
function setFlashMessage($message, $type = 'success') {
    ensureSession();
    $_SESSION['flash_message'] = array(
        'message' => $message,
        'type' => $type
    );
}

/**
 * Zeigt Flash-Nachrichten an
 */
function showFlashMessage() {
    ensureSession();
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        $alertClass = 'alert-success';
        $icon = 'fa-check-circle';
        
        if ($flash['type'] === 'error' || $flash['type'] === 'danger') {
            $alertClass = 'alert-danger';
            $icon = 'fa-exclamation-triangle';
        } elseif ($flash['type'] === 'warning') {
            $alertClass = 'alert-warning';
            $icon = 'fa-exclamation-triangle';
        } elseif ($flash['type'] === 'info') {
            $alertClass = 'alert-info';
            $icon = 'fa-info-circle';
        }
        
        echo '<div class="alert ' . $alertClass . '">';
        echo '<i class="fas ' . $icon . '"></i> ';
        echo e($flash['message']);
        echo '</div>';
    }
}

/**
 * Weiterleitung mit Flash-Nachricht
 */
function redirectWithMessage($url, $message, $type = 'success') {
    setFlashMessage($message, $type);
    header('Location: ' . $url);
    exit();
}

/**
 * =============================================================================
 * DATUM & ZEIT
 * =============================================================================
 */

/**
 * Formatiert ein Datum
 */
function formatDate($date, $format = 'd.m.Y') {
    if (empty($date)) {
        return '-';
    }
    
    if (is_numeric($date)) {
        $timestamp = $date;
    } else {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '-';
        }
    }
    
    return date($format, $timestamp);
}

/**
 * Formatiert Datum und Zeit
 */
function formatDateTime($datetime, $format = 'd.m.Y H:i') {
    if (empty($datetime)) {
        return '-';
    }
    
    if (is_numeric($datetime)) {
        $timestamp = $datetime;
    } else {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '-';
        }
    }
    
    return date($format, $timestamp);
}

/**
 * =============================================================================
 * EINSTELLUNGEN-VERWALTUNG
 * =============================================================================
 */

/**
 * Erstellt Standard-Einstellungen
 */
function ensureDefaultSettings() {
    $settingsFile = __DIR__ . '/../data/settings.json';
    $userPrefsFile = __DIR__ . '/../data/user_preferences.json';
    
    // Verzeichnis erstellen
    $dataDir = dirname($settingsFile);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    // settings.json erstellen
    if (!file_exists($settingsFile)) {
        $defaultSettings = array(
            'general' => array(
                'app_name' => 'LinkBuilder Pro',
                'company_name' => '',
                'timezone' => 'Europe/Berlin',
                'date_format' => 'd.m.Y',
                'currency' => 'EUR',
                'items_per_page' => 25,
                'auto_backup' => false,
                'backup_retention_days' => 30
            ),
            'topics' => array(
                'custom_topics' => array(),
                'predefined_topics' => array(
                    'SEO', 
                    'Content Marketing', 
                    'Linkbuilding', 
                    'Digital Marketing', 
                    'Webentwicklung', 
                    'E-Commerce'
                ),
                'auto_suggest' => true,
                'case_sensitive' => false
            ),
            'notifications' => array(
                'email_enabled' => false,
                'admin_email' => '',
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_encryption' => 'tls',
                'link_expiry_warning' => true,
                'expiry_warning_days' => 7,
                'broken_link_warning' => true,
                'daily_summary' => false,
                'weekly_report' => false
            )
        );
        
        saveData('settings.json', $defaultSettings);
    }
    
    // user_preferences.json erstellen
    if (!file_exists($userPrefsFile)) {
        saveData('user_preferences.json', array());
    }
}

/**
 * Lädt globale Einstellungen
 */
function getSettings() {
    return loadData('settings.json');
}

/**
 * Lädt Benutzereinstellungen
 */
function getUserPreferences($userId) {
    $userPrefs = loadData('user_preferences.json');
    
    $defaultPrefs = array(
        'ui' => array(
            'dark_mode' => false,
            'compact_view' => false,
            'sidebar_collapsed' => false,
            'show_tooltips' => true,
            'animation_enabled' => true,
            'auto_save' => true
        )
    );
    
    if (isset($userPrefs[$userId])) {
        return $userPrefs[$userId];
    }
    
    return $defaultPrefs;
}

/**
 * Speichert Benutzereinstellungen
 */
function saveUserPreferencesForUser($userId, $preferences) {
    $userPrefs = loadData('user_preferences.json');
    $userPrefs[$userId] = $preferences;
    return saveData('user_preferences.json', $userPrefs);
}

/**
 * =============================================================================
 * HILFSFUNKTIONEN
 * =============================================================================
 */

/**
 * Bereinigt einen String
 */
function sanitizeString($string, $maxLength = null) {
    if ($string === null) {
        $string = '';
    }
    
    $string = trim($string);
    
    if ($maxLength && strlen($string) > $maxLength) {
        $string = substr($string, 0, $maxLength);
    }
    
    return $string;
}

/**
 * Formatiert eine Währung
 */
function formatCurrency($amount, $currency = 'EUR') {
    $symbols = array(
        'EUR' => '€',
        'USD' => '$',
        'CHF' => 'CHF',
        'GBP' => '£'
    );
    
    if (isset($symbols[$currency])) {
        $symbol = $symbols[$currency];
    } else {
        $symbol = $currency;
    }
    
    $formatted = number_format((float)$amount, 2, ',', '.');
    
    return $formatted . ' ' . $symbol;
}

/**
 * Kürzt einen Text
 */
function truncateText($text, $maxLength = 100, $suffix = '...') {
    if (strlen($text) <= $maxLength) {
        return $text;
    }
    
    return substr($text, 0, $maxLength - strlen($suffix)) . $suffix;
}

/**
 * =============================================================================
 * STATISTIKEN
 * =============================================================================
 */

/**
 * Berechnet Dashboard-Statistiken
 */
function getDashboardStats() {
    $userId = getCurrentUserId();
    
    $blogs = loadData('blogs.json');
    $customers = loadData('customers.json');
    $links = loadData('links.json');
    
    // Benutzer-spezifische Daten filtern
    $userBlogs = array();
    foreach ($blogs as $blog) {
        if (isset($blog['user_id']) && $blog['user_id'] === $userId) {
            $userBlogs[] = $blog;
        }
    }
    
    $userCustomers = array();
    foreach ($customers as $customer) {
        if (isset($customer['user_id']) && $customer['user_id'] === $userId) {
            $userCustomers[] = $customer;
        }
    }
    
    $userLinks = array();
    foreach ($links as $link) {
        if (isset($link['user_id']) && $link['user_id'] === $userId) {
            $userLinks[] = $link;
        }
    }
    
    // Aktive Links zählen
    $activeLinks = array();
    foreach ($userLinks as $link) {
        $status = isset($link['status']) ? $link['status'] : 'ausstehend';
        if ($status === 'aktiv') {
            $activeLinks[] = $link;
        }
    }
    
    // Umsatz berechnen
    $totalRevenue = 0;
    foreach ($userLinks as $link) {
        if (isset($link['price']) && is_numeric($link['price'])) {
            $totalRevenue += (float)$link['price'];
        }
    }
    
    return array(
        'total_blogs' => count($userBlogs),
        'total_customers' => count($userCustomers),
        'total_links' => count($userLinks),
        'active_links' => count($activeLinks),
        'total_revenue' => $totalRevenue
    );
}

/**
 * =============================================================================
 * ZUSÄTZLICHE HILFSFUNKTIONEN FÜR BENUTZER-VERWALTUNG
 * =============================================================================
 */

/**
 * String kürzen mit Ellipsis
 */
function truncate($string, $length = 100, $append = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    
    return substr($string, 0, $length) . $append;
}

/**
 * Sichere Array-Zugriff
 */
function getArrayValue($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * Passwort-Stärke prüfen
 */
function checkPasswordStrength($password) {
    $score = 0;
    $feedback = [];
    
    // Länge prüfen
    if (strlen($password) >= 8) {
        $score += 1;
    } else {
        $feedback[] = 'Mindestens 8 Zeichen';
    }
    
    // Großbuchstaben
    if (preg_match('/[A-Z]/', $password)) {
        $score += 1;
    } else {
        $feedback[] = 'Mindestens ein Großbuchstabe';
    }
    
    // Kleinbuchstaben
    if (preg_match('/[a-z]/', $password)) {
        $score += 1;
    } else {
        $feedback[] = 'Mindestens ein Kleinbuchstabe';
    }
    
    // Zahlen
    if (preg_match('/[0-9]/', $password)) {
        $score += 1;
    } else {
        $feedback[] = 'Mindestens eine Zahl';
    }
    
    // Sonderzeichen
    if (preg_match('/[^A-Za-z0-9]/', $password)) {
        $score += 1;
    } else {
        $feedback[] = 'Mindestens ein Sonderzeichen';
    }
    
    return [
        'score' => $score,
        'max_score' => 5,
        'strength' => $score < 3 ? 'schwach' : ($score < 4 ? 'mittel' : 'stark'),
        'feedback' => $feedback
    ];
}

/**
 * Debug-Ausgabe (nur in Entwicklungsumgebung)
 */
function debug($data, $label = 'DEBUG') {
    if (defined('DEBUG') && DEBUG === true) {
        echo '<pre style="background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; margin: 10px 0;">';
        echo '<strong>' . htmlspecialchars($label) . ':</strong><br>';
        print_r($data);
        echo '</pre>';
    }
}

/**
 * =============================================================================
 * INITIALISIERUNG
 * =============================================================================
 */

// Session starten
ensureSession();

// Standard-Einstellungen erstellen (nur wenn nötig)
if (!file_exists(__DIR__ . '/../data/settings.json')) {
    ensureDefaultSettings();
}

?>