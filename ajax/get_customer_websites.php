<?php
/**
 * AJAX-Endpoint für Kunden-Websites
 * Datei: ajax/get_customer_websites.php
 * 
 * Diese Datei lädt die verfügbaren Websites eines Kunden
 * und gibt sie als JSON zurück für die Website-Auswahl.
 */

// Alle vorherige Ausgabe stoppen
while (ob_get_level()) {
    ob_end_clean();
}

// JSON-Header SOFORT setzen
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
    
    $websites = [];
    $debugInfo = [
        'customer_id' => $customerId,
        'user_id' => $userId,
        'is_admin' => $isAdmin,
        'customer_exists' => isset($customers[$customerId]),
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'method' => 'separate_ajax_file'
    ];
    
    if (!isset($customers[$customerId])) {
        throw new Exception('Customer not found with ID: ' . $customerId);
    }
    
    $customer = $customers[$customerId];
    $debugInfo['customer_name'] = $customer['name'] ?? 'Unknown';
    $debugInfo['customer_user_id'] = $customer['user_id'] ?? 'None';
    $debugInfo['has_permission'] = $isAdmin || (isset($customer['user_id']) && $customer['user_id'] === $userId);
    
    // Prüfen ob Benutzer Berechtigung hat
    if (!$isAdmin && (!isset($customer['user_id']) || $customer['user_id'] !== $userId)) {
        throw new Exception('No permission to access customer data');
    }
    
    // Websites des Kunden laden
    // Neue Struktur mit websites-Array (bevorzugt)
    if (!empty($customer['websites']) && is_array($customer['websites'])) {
        $debugInfo['website_structure'] = 'new_array';
        $debugInfo['website_count'] = count($customer['websites']);
        
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
        $debugInfo['website_structure'] = 'old_single';
        
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
    } else {
        $debugInfo['website_structure'] = 'none';
        $debugInfo['note'] = 'Customer has no websites configured';
    }
    
    // Erfolgreiche Response
    $response = [
        'success' => true,
        'websites' => $websites,
        'count' => count($websites),
        'customer' => [
            'id' => $customerId,
            'name' => $customer['name'] ?? 'Unknown'
        ],
        'debug' => $debugInfo
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Fehler-Response
    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage(),
        'websites' => [],
        'debug' => [
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'error_trace' => array_slice($e->getTrace(), 0, 3), // Nur die ersten 3 Stack-Frames
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'method' => 'separate_ajax_file',
            'get_params' => $_GET,
            'session_id' => session_id()
        ]
    ];
    
    // HTTP Status Code für Fehler setzen
    http_response_code(400);
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

// Script beenden
exit;
?>