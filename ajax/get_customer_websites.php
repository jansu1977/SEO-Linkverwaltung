<?php
/**
 * AJAX-Endpoint für Kunden-Websites
 * Datei: ajax/get_customer_websites.php
 */

// Alle vorherige Ausgabe stoppen
while (ob_get_level()) {
    ob_end_clean();
}

// JSON-Header setzen
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    // Session und Funktionen laden
    session_start();
    require_once __DIR__ . '/../includes/functions.php';
    
    // Parameter prüfen
    if (!isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
        throw new Exception('customer_id parameter missing or empty');
    }
    
    // Benutzer-ID ermitteln
    $userId = getCurrentUserId();
    if (!$userId || $userId === 'default_user') {
        throw new Exception('Not authenticated - please login');
    }
    
    // Benutzer-Daten laden
    $users = loadData('users.json');
    $currentUser = $users[$userId] ?? null;
    $isAdmin = $currentUser && ($currentUser['role'] ?? 'user') === 'admin';
    
    $customerId = trim($_GET['customer_id']);
    $customers = loadData('customers.json');
    
    if (!isset($customers[$customerId])) {
        throw new Exception('Customer not found with ID: ' . $customerId);
    }
    
    $customer = $customers[$customerId];
    
    // Prüfen ob Benutzer Berechtigung hat
    if (!$isAdmin && (!isset($customer['user_id']) || $customer['user_id'] !== $userId)) {
        throw new Exception('No permission to access customer data');
    }
    
    $websites = [];
    
    // Websites des Kunden laden
    // Neue Struktur mit websites-Array (bevorzugt)
    if (!empty($customer['websites']) && is_array($customer['websites'])) {
        foreach ($customer['websites'] as $index => $website) {
            if (!empty($website['url'])) {
                // URL normalisieren
                $url = $website['url'];
                if (!preg_match('/^https?:\/\//', $url)) {
                    $url = 'https://' . $url;
                }
                
                $websites[] = [
                    'id' => $index,
                    'title' => $website['title'] ?? parse_url($url, PHP_URL_HOST),
                    'url' => $url,
                    'description' => $website['description'] ?? '',
                    'added_at' => $website['added_at'] ?? 'Unknown'
                ];
            }
        }
    }
    // Backward compatibility: Alte einzelne Website
    elseif (!empty($customer['website'])) {
        $url = $customer['website'];
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }
        
        $websites[] = [
            'id' => 0,
            'title' => parse_url($url, PHP_URL_HOST),
            'url' => $url,
            'description' => 'Legacy single website',
            'added_at' => $customer['created_at'] ?? 'Unknown'
        ];
    }
    
    // Erfolgreiche Response
    echo json_encode([
        'success' => true,
        'websites' => $websites,
        'count' => count($websites),
        'customer' => [
            'id' => $customerId,
            'name' => $customer['name'] ?? 'Unknown'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    // Fehler-Response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'websites' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;
?>