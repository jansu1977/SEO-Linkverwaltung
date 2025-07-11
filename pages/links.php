<?php
/**
 * LinkBuilder Pro - Link-Verwaltung mit Kostenverwaltung
 * pages/links.php
 */

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

/**
 * Backlink-Checker mit Anti-Bot-Detection
 */
class RobustBacklinkChecker {
    private $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15'
    ];
    
    private $rateLimitStorage = [];
    private $circuitBreakerStorage = [];
    
    /**
     * Backlink-Prüfung
     */
    public function checkBacklinkAdvanced($backlinkUrl, $targetUrl, $anchorText, $options = []) {
        $startTime = microtime(true);
        
        // Input-Validierung
        if (empty($backlinkUrl) || empty($targetUrl) || empty($anchorText)) {
            return $this->createErrorResponse('Fehlende Parameter (URL oder Ankertext)', 0);
        }
        
        // Domain für Rate-Limiting extrahieren
        $domain = parse_url($backlinkUrl, PHP_URL_HOST);
        
        // Rate-Limiting prüfen
        if (!$this->checkRateLimit($domain)) {
            return $this->createErrorResponse('Rate-Limit erreicht, bitte später versuchen', 429);
        }
        
        // Circuit-Breaker prüfen
        if (!$this->checkCircuitBreaker($domain)) {
            return $this->createErrorResponse('Domain temporär nicht verfügbar', 503);
        }
        
        try {
            // HTTP-Erreichbarkeit prüfen
            $statusResult = $this->checkHttpStatus($backlinkUrl);
            
            if (!$statusResult['isValid']) {
                $this->recordCircuitBreakerFailure($domain);
                return $this->createErrorResponse($statusResult['error'], $statusResult['httpStatus']);
            }
            
            // HTML-Content laden
            $contentResult = $this->loadHtmlContent($backlinkUrl);
            
            if (!$contentResult['success']) {
                $this->recordCircuitBreakerFailure($domain);
                return $this->createErrorResponse($contentResult['error'], $contentResult['httpStatus']);
            }
            
            $htmlContent = $contentResult['content'];
            $responseSize = strlen($htmlContent);
            
            // Link-Analyse
            $linkResult = $this->analyzeLinks($htmlContent, $targetUrl, $anchorText);
            
            // Circuit-Breaker-Success registrieren
            $this->recordCircuitBreakerSuccess($domain);
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'isValid' => $statusResult['isValid'],
                'containsTargetLink' => $linkResult['containsTargetLink'],
                'httpStatus' => $statusResult['httpStatus'],
                'error' => null,
                'foundLinks' => $linkResult['foundLinks'],
                'responseTime' => $responseTime,
                'responseSize' => $responseSize,
                'searchedFor' => [
                    'targetUrl' => $targetUrl,
                    'anchorText' => $anchorText,
                    'normalizedUrl' => $linkResult['normalizedTargetUrl'],
                    'normalizedText' => $linkResult['normalizedAnchorText']
                ],
                'statistics' => [
                    'totalLinks' => count($linkResult['foundLinks']),
                    'urlMatches' => $linkResult['urlMatches'],
                    'textMatches' => $linkResult['textMatches'],
                    'perfectMatches' => $linkResult['perfectMatches']
                ]
            ];
            
        } catch (Exception $e) {
            $this->recordCircuitBreakerFailure($domain);
            return $this->createErrorResponse('Unerwarteter Fehler: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * HTTP-Status prüfen
     */
    private function checkHttpStatus($url) {
        $userAgent = $this->getRandomUserAgent();
        
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'header' => $this->getBrowserHeaders($userAgent),
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);
        
        $headers = @get_headers($url, 1, $context);
        
        if ($headers === false) {
            return ['isValid' => false, 'httpStatus' => 0, 'error' => 'URL nicht erreichbar'];
        }
        
        $statusCode = $this->extractStatusCode($headers);
        
        if ($statusCode >= 200 && $statusCode < 400) {
            return ['isValid' => true, 'httpStatus' => $statusCode];
        } else {
            return ['isValid' => false, 'httpStatus' => $statusCode, 'error' => "HTTP-Status: $statusCode"];
        }
    }
    
    /**
     * HTML-Content laden
     */
    private function loadHtmlContent($url) {
        $strategies = [
            'browser' => 'Standard Browser Headers',
            'googlebot' => 'Googlebot User-Agent'
        ];
        
        foreach ($strategies as $strategy => $description) {
            $context = $this->createContextForStrategy($strategy);
            $content = @file_get_contents($url, false, $context);
            
            if ($content !== false && strlen($content) > 100) {
                return ['success' => true, 'content' => $content, 'strategy' => $strategy];
            }
        }
        
        return ['success' => false, 'error' => 'Konnte HTML-Content nicht laden', 'httpStatus' => 0];
    }
    
    /**
     * Stream-Context für verschiedene Strategien erstellen
     */
    private function createContextForStrategy($strategy) {
        $baseOptions = [
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'follow_location' => true,
                'max_redirects' => 3,
                'ignore_errors' => true
            ]
        ];
        
        switch ($strategy) {
            case 'browser':
                $baseOptions['http']['header'] = $this->getBrowserHeaders($this->getRandomUserAgent());
                break;
                
            case 'googlebot':
                $baseOptions['http']['header'] = [
                    'User-Agent: Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.8',
                    'Accept-Encoding: identity',
                    'Cache-Control: no-cache'
                ];
                break;
        }
        
        return stream_context_create($baseOptions);
    }
    
    /**
     * Link-Analyse
     */
    private function analyzeLinks($htmlContent, $targetUrl, $anchorText) {
        // URL-Normalisierung
        $normalizeUrl = function($url) {
            $url = trim($url);
            $url = strtok($url, '#');
            $url = rtrim($url, '/');
            return strtolower($url);
        };
        
        $normalizedTargetUrl = $normalizeUrl($targetUrl);
        $normalizedAnchorText = trim(strtolower($anchorText));
        
        // URL-Varianten generieren
        $urlVariants = $this->generateUrlVariants($targetUrl, $normalizedTargetUrl);
        $anchorVariants = $this->generateAnchorVariants($anchorText, $normalizedAnchorText);
        
        // Links extrahieren
        $foundLinks = $this->extractLinks($htmlContent);
        
        // Link-Matching
        $matchResults = $this->matchLinks($foundLinks, $urlVariants, $anchorVariants);
        
        return [
            'containsTargetLink' => $matchResults['containsTargetLink'],
            'foundLinks' => array_slice($foundLinks, 0, 20),
            'normalizedTargetUrl' => $normalizedTargetUrl,
            'normalizedAnchorText' => $normalizedAnchorText,
            'urlMatches' => $matchResults['urlMatches'],
            'textMatches' => $matchResults['textMatches'],
            'perfectMatches' => $matchResults['perfectMatches']
        ];
    }
    
    /**
     * Links aus HTML extrahieren
     */
    private function extractLinks($htmlContent) {
        $patterns = [
            '/<a\s[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',
            '/<a\s[^>]*href\s*=\s*([^\s>]+)[^>]*>(.*?)<\/a>/is'
        ];
        
        $allMatches = [];
        foreach ($patterns as $pattern) {
            $matches = [];
            if (preg_match_all($pattern, $htmlContent, $matches, PREG_SET_ORDER)) {
                $allMatches = array_merge($allMatches, $matches);
            }
        }
        
        // Duplikate entfernen und normalisieren
        $uniqueLinks = [];
        $seen = [];
        
        foreach ($allMatches as $match) {
            $href = trim($match[1]);
            $text = trim(strip_tags($match[2]));
            
            $key = strtolower($href . '|' . $text);
            if (!isset($seen[$key]) && !empty($href) && !empty($text)) {
                $uniqueLinks[] = [
                    'href' => $href,
                    'text' => $text,
                    'normalizedHref' => strtolower(rtrim($href, '/')),
                    'normalizedText' => strtolower($text)
                ];
                $seen[$key] = true;
            }
        }
        
        return $uniqueLinks;
    }
    
    /**
     * URL-Varianten generieren
     */
    private function generateUrlVariants($originalUrl, $normalizedUrl) {
        $variants = [$normalizedUrl];
        
        $variants[] = $normalizedUrl . '/';
        $variants[] = rtrim($normalizedUrl, '/');
        
        // HTTP/HTTPS Varianten
        $variants[] = str_replace('https://', 'http://', $normalizedUrl);
        $variants[] = str_replace('http://', 'https://', $normalizedUrl);
        
        // WWW Varianten
        $variants[] = str_replace('://', '://www.', $normalizedUrl);
        $variants[] = str_replace('://www.', '://', $normalizedUrl);
        
        return array_unique($variants);
    }
    
    /**
     * Ankertext-Varianten generieren
     */
    private function generateAnchorVariants($originalAnchor, $normalizedAnchor) {
        $variants = [$normalizedAnchor];
        
        $variants[] = html_entity_decode($normalizedAnchor, ENT_QUOTES, 'UTF-8');
        $variants[] = preg_replace('/\s+/', ' ', $normalizedAnchor);
        
        // Wenn Ankertext eine URL ist
        if (filter_var($originalAnchor, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($originalAnchor);
            if (isset($parsed['host'])) {
                $variants[] = strtolower($parsed['host']);
            }
        }
        
        return array_unique($variants);
    }
    
    /**
     * Link-Matching durchführen
     */
    private function matchLinks($foundLinks, $urlVariants, $anchorVariants) {
        $containsTargetLink = false;
        $urlMatches = 0;
        $textMatches = 0;
        $perfectMatches = 0;
        
        foreach ($foundLinks as &$link) {
            $hrefMatch = false;
            $textMatch = false;
            
            // URL-Matching
            foreach ($urlVariants as $variant) {
                if ($link['normalizedHref'] === $variant || 
                    strpos($link['normalizedHref'], $variant) !== false ||
                    strpos($variant, $link['normalizedHref']) !== false) {
                    $hrefMatch = true;
                    break;
                }
            }
            
            // Text-Matching
            foreach ($anchorVariants as $variant) {
                if ($link['normalizedText'] === $variant || 
                    strpos($link['normalizedText'], $variant) !== false ||
                    strpos($variant, $link['normalizedText']) !== false) {
                    $textMatch = true;
                    break;
                }
            }
            
            $link['hrefMatch'] = $hrefMatch;
            $link['textMatch'] = $textMatch;
            $link['perfectMatch'] = $hrefMatch && $textMatch;
            
            if ($hrefMatch) $urlMatches++;
            if ($textMatch) $textMatches++;
            if ($hrefMatch && $textMatch) {
                $perfectMatches++;
                $containsTargetLink = true;
            }
        }
        
        return [
            'containsTargetLink' => $containsTargetLink,
            'urlMatches' => $urlMatches,
            'textMatches' => $textMatches,
            'perfectMatches' => $perfectMatches
        ];
    }
    
    /**
     * Browser-Headers generieren
     */
    private function getBrowserHeaders($userAgent) {
        return [
            "User-Agent: $userAgent",
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: identity',
            'DNT: 1',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Cache-Control: no-cache'
        ];
    }
    
    /**
     * Zufälligen User-Agent auswählen
     */
    private function getRandomUserAgent() {
        return $this->userAgents[array_rand($this->userAgents)];
    }
    
    /**
     * Status-Code aus Headers extrahieren
     */
    private function extractStatusCode($headers) {
        if (isset($headers[0])) {
            preg_match('/HTTP\/[\d\.]+\s+(\d+)/', $headers[0], $matches);
            return isset($matches[1]) ? (int)$matches[1] : 0;
        }
        return 0;
    }
    
    /**
     * Rate-Limiting prüfen
     */
    private function checkRateLimit($domain) {
        $key = "rate_limit_$domain";
        $now = time();
        $windowSize = 60;
        $maxRequests = 10;
        
        if (!isset($this->rateLimitStorage[$key])) {
            $this->rateLimitStorage[$key] = [];
        }
        
        $this->rateLimitStorage[$key] = array_filter(
            $this->rateLimitStorage[$key],
            function($timestamp) use ($now, $windowSize) {
                return $timestamp > ($now - $windowSize);
            }
        );
        
        if (count($this->rateLimitStorage[$key]) >= $maxRequests) {
            return false;
        }
        
        $this->rateLimitStorage[$key][] = $now;
        return true;
    }
    
    /**
     * Circuit-Breaker prüfen
     */
    private function checkCircuitBreaker($domain) {
        $key = "circuit_breaker_$domain";
        $now = time();
        
        if (!isset($this->circuitBreakerStorage[$key])) {
            $this->circuitBreakerStorage[$key] = [
                'failures' => 0,
                'lastFailure' => 0,
                'state' => 'closed'
            ];
        }
        
        $cb = &$this->circuitBreakerStorage[$key];
        
        if ($cb['state'] === 'open') {
            $timeout = 300;
            if ($now - $cb['lastFailure'] > $timeout) {
                $cb['state'] = 'half-open';
                return true;
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Circuit-Breaker Fehler registrieren
     */
    private function recordCircuitBreakerFailure($domain) {
        $key = "circuit_breaker_$domain";
        $now = time();
        
        if (!isset($this->circuitBreakerStorage[$key])) {
            $this->circuitBreakerStorage[$key] = [
                'failures' => 0,
                'lastFailure' => 0,
                'state' => 'closed'
            ];
        }
        
        $cb = &$this->circuitBreakerStorage[$key];
        $cb['failures']++;
        $cb['lastFailure'] = $now;
        
        if ($cb['failures'] >= 5) {
            $cb['state'] = 'open';
        }
    }
    
    /**
     * Circuit-Breaker Erfolg registrieren
     */
    private function recordCircuitBreakerSuccess($domain) {
        $key = "circuit_breaker_$domain";
        
        if (isset($this->circuitBreakerStorage[$key])) {
            $this->circuitBreakerStorage[$key]['failures'] = 0;
            $this->circuitBreakerStorage[$key]['state'] = 'closed';
        }
    }
    
    /**
     * Fehler-Response erstellen
     */
    private function createErrorResponse($error, $httpStatus) {
        return [
            'isValid' => false,
            'containsTargetLink' => false,
            'httpStatus' => $httpStatus,
            'error' => $error,
            'foundLinks' => [],
            'responseTime' => 0,
            'responseSize' => 0
        ];
    }
}

/**
 * Kostenfunktionen
 */
function validateCostAmount($amount) {
    if (empty($amount)) return null;
    $amount = str_replace(',', '.', trim($amount));
    return is_numeric($amount) && $amount >= 0 ? (float)$amount : false;
}

function formatCostDisplay($amount, $type, $currency = 'EUR') {
    if (empty($amount) || $amount <= 0) return '-';
    
    $formatted = number_format($amount, 2, ',', '.') . ' ' . $currency;
    
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

try {
    // Basis-Variablen
    $action = $_POST['action'] ?? $_GET['action'] ?? 'index';
    $linkId = $_POST['id'] ?? $_GET['id'] ?? null;

    // Session sicherstellen und User-ID ermitteln
    ensureSession();
    $userId = getCurrentUserId();

    // Benutzer-Daten laden
    $users = loadData('users.json');
    $currentUser = $users[$userId] ?? null;

    // Admin-Status prüfen
    $isAdmin = $currentUser && ($currentUser['role'] ?? 'user') === 'admin';

    // Backlink-Checker initialisieren
    $backlinkChecker = new RobustBacklinkChecker();

    // POST-Verarbeitung
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create') {
            $customerId = $_POST['customer_id'] ?? '';
            $customerWebsiteId = $_POST['customer_website_id'] ?? '';
            $blogId = $_POST['blog_id'] ?? '';
            $backlinkUrl = trim($_POST['backlink_url'] ?? '');
            $anchorText = trim($_POST['anchor_text'] ?? '');
            $targetUrl = trim($_POST['target_url'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            // Kostenfelder
            $costAmount = $_POST['cost_amount'] ?? '';
            $costType = $_POST['cost_type'] ?? 'monthly';
            $costCurrency = $_POST['cost_currency'] ?? 'EUR';
            
            $errors = [];
            
            // Validierung
            if (empty($customerId)) $errors[] = 'Kunde ist ein Pflichtfeld.';
            if (empty($blogId)) $errors[] = 'Blog ist ein Pflichtfeld.';
            if (empty($backlinkUrl)) $errors[] = 'Backlink-URL ist ein Pflichtfeld.';
            if (empty($anchorText)) $errors[] = 'Ankertext ist ein Pflichtfeld.';
            if (empty($targetUrl)) $errors[] = 'Ziel-URL ist ein Pflichtfeld.';
            
            if (!empty($backlinkUrl) && !filter_var($backlinkUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Ungültige Backlink-URL.';
            }
            if (!empty($targetUrl) && !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Ungültige Ziel-URL.';
            }
            
            // Kosten-Validierung
            $validatedCostAmount = null;
            if (!empty($costAmount)) {
                $validatedCostAmount = validateCostAmount($costAmount);
                if ($validatedCostAmount === false) {
                    $errors[] = 'Ungültiger Kostenbetrag. Bitte geben Sie eine gültige Zahl ein.';
                }
            }
            
            if (empty($errors)) {
                $links = loadData('links.json');
                $newId = generateId();
                
                $links[$newId] = [
                    'id' => $newId,
                    'user_id' => $userId,
                    'customer_id' => $customerId,
                    'customer_website_id' => $customerWebsiteId,
                    'blog_id' => $blogId,
                    'backlink_url' => $backlinkUrl,
                    'anchor_text' => $anchorText,
                    'target_url' => $targetUrl,
                    'description' => $description,
                    'cost_amount' => $validatedCostAmount,
                    'cost_type' => $costType,
                    'cost_currency' => $costCurrency,
                    'status' => 'ausstehend',
                    'created_at' => date('Y-m-d H:i:s'),
                    'last_checked' => null,
                    'check_result' => null
                ];
                
                if (saveData('links.json', $links)) {
                    redirectWithMessage('?page=links', 'Link "' . $anchorText . '" erfolgreich erstellt.');
                } else {
                    $errors[] = 'Fehler beim Speichern des Links.';
                }
            }
        } elseif ($action === 'update' && $linkId) {
            $customerId = $_POST['customer_id'] ?? '';
            $customerWebsiteId = $_POST['customer_website_id'] ?? '';
            $blogId = $_POST['blog_id'] ?? '';
            $backlinkUrl = trim($_POST['backlink_url'] ?? '');
            $anchorText = trim($_POST['anchor_text'] ?? '');
            $targetUrl = trim($_POST['target_url'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            // Kostenfelder
            $costAmount = $_POST['cost_amount'] ?? '';
            $costType = $_POST['cost_type'] ?? 'monthly';
            $costCurrency = $_POST['cost_currency'] ?? 'EUR';
            
            $errors = [];
            
            // Validierung
            if (empty($customerId)) $errors[] = 'Kunde ist ein Pflichtfeld.';
            if (empty($blogId)) $errors[] = 'Blog ist ein Pflichtfeld.';
            if (empty($backlinkUrl)) $errors[] = 'Backlink-URL ist ein Pflichtfeld.';
            if (empty($anchorText)) $errors[] = 'Ankertext ist ein Pflichtfeld.';
            if (empty($targetUrl)) $errors[] = 'Ziel-URL ist ein Pflichtfeld.';
            
            if (!empty($backlinkUrl) && !filter_var($backlinkUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Ungültige Backlink-URL.';
            }
            if (!empty($targetUrl) && !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
                $errors[] = 'Ungültige Ziel-URL.';
            }
            
            // Kosten-Validierung
            $validatedCostAmount = null;
            if (!empty($costAmount)) {
                $validatedCostAmount = validateCostAmount($costAmount);
                if ($validatedCostAmount === false) {
                    $errors[] = 'Ungültiger Kostenbetrag. Bitte geben Sie eine gültige Zahl ein.';
                }
            }
            
            if (empty($errors)) {
                $links = loadData('links.json');
                if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
                    $links[$linkId]['customer_id'] = $customerId;
                    $links[$linkId]['customer_website_id'] = $customerWebsiteId;
                    $links[$linkId]['blog_id'] = $blogId;
                    $links[$linkId]['backlink_url'] = $backlinkUrl;
                    $links[$linkId]['anchor_text'] = $anchorText;
                    $links[$linkId]['target_url'] = $targetUrl;
                    $links[$linkId]['description'] = $description;
                    $links[$linkId]['cost_amount'] = $validatedCostAmount;
                    $links[$linkId]['cost_type'] = $costType;
                    $links[$linkId]['cost_currency'] = $costCurrency;
                    $links[$linkId]['updated_at'] = date('Y-m-d H:i:s');
                    
                    if (saveData('links.json', $links)) {
                        redirectWithMessage("?page=links&action=view&id=$linkId", 'Link "' . $anchorText . '" erfolgreich aktualisiert.');
                    } else {
                        $errors[] = 'Fehler beim Speichern des Links.';
                    }
                } else {
                    $errors[] = 'Link nicht gefunden oder keine Berechtigung.';
                }
            }
        } elseif ($action === 'delete' && $linkId) {
            $links = loadData('links.json');
            if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
                $link = $links[$linkId];
                $anchorText = $link['anchor_text'] ?? 'Unbekannter Link';
                
                unset($links[$linkId]);
                
                if (saveData('links.json', $links)) {
                    redirectWithMessage('?page=links', 'Link "' . $anchorText . '" erfolgreich gelöscht.');
                } else {
                    setFlashMessage('Fehler beim Löschen des Links.', 'error');
                    redirectWithMessage("?page=links&action=view&id=$linkId", 'Fehler beim Löschen des Links.');
                }
            } else {
                setFlashMessage('Link nicht gefunden oder keine Berechtigung zum Löschen.', 'error');
                redirectWithMessage('?page=links', 'Link nicht gefunden oder keine Berechtigung.');
            }
        } elseif ($action === 'check' && $linkId) {
            $links = loadData('links.json');
            if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
                $link = $links[$linkId];
                
                $result = $backlinkChecker->checkBacklinkAdvanced(
                    $link['backlink_url'],
                    $link['target_url'],
                    $link['anchor_text']
                );
                
                // Status-Bestimmung
                $newStatus = 'defekt';
                
                if ($result['isValid'] && $result['containsTargetLink']) {
                    $newStatus = 'aktiv';
                } elseif ($result['isValid'] && !$result['containsTargetLink']) {
                    $newStatus = 'ausstehend';
                }
                
                // Debug-Informationen
                $debugInfo = [
                    'http_status' => $result['httpStatus'] ?? 0,
                    'url_erreichbar' => $result['isValid'] ? 'Ja' : 'Nein',
                    'link_gefunden' => $result['containsTargetLink'] ? 'Ja' : 'Nein',
                    'perfect_matches' => $result['statistics']['perfectMatches'] ?? 0,
                    'url_matches' => $result['statistics']['urlMatches'] ?? 0,
                    'text_matches' => $result['statistics']['textMatches'] ?? 0,
                    'total_links_found' => $result['statistics']['totalLinks'] ?? 0,
                    'response_time' => $result['responseTime'] ?? 0,
                    'response_size' => $result['responseSize'] ?? 0
                ];
                
                $links[$linkId]['status'] = $newStatus;
                $links[$linkId]['last_checked'] = date('Y-m-d H:i:s');
                $links[$linkId]['check_result'] = $result;
                $links[$linkId]['debug_info'] = $debugInfo;
                
                if (saveData('links.json', $links)) {
                    $message = "Link-Prüfung abgeschlossen: Status = <strong>" . strtoupper($newStatus) . "</strong>";
                    $message .= "<br>→ HTTP: {$debugInfo['http_status']} | Erreichbar: {$debugInfo['url_erreichbar']} | Link gefunden: {$debugInfo['link_gefunden']}";
                    $message .= "<br>→ Perfekte Matches: {$debugInfo['perfect_matches']} | Gefundene Links: {$debugInfo['total_links_found']}";
                    
                    if (!empty($result['error'])) {
                        $message .= "<br>⚠️ Hinweis: " . htmlspecialchars($result['error']);
                    }
                    
                    redirectWithMessage("?page=links&action=view&id=$linkId", $message);
                } else {
                    setFlashMessage('Fehler beim Speichern der Prüfungsergebnisse.', 'error');
                }
            }
        }
    }

    // Debug-Seite für Link-Analyse
    if ($action === 'debug' && $linkId) {
        $links = loadData('links.json');
        if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
            $link = $links[$linkId];
            
            $result = $backlinkChecker->checkBacklinkAdvanced(
                $link['backlink_url'],
                $link['target_url'],
                $link['anchor_text']
            );
            
            // Vereinfachte Debug-Seite
            ?>
            <!DOCTYPE html>
            <html lang="de">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Link-Debug: <?= htmlspecialchars($link['anchor_text']) ?></title>
                <link rel="stylesheet" href="assets/style.css">
                <style>
                    body { background: #1a1d2e; color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; line-height: 1.6; }
                    .debug-container { max-width: 1200px; margin: 0 auto; }
                    .debug-section { background: #343852; padding: 24px; margin: 20px 0; border-radius: 12px; border: 1px solid #3a3d52; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
                    .success { color: #10b981; }
                    .error { color: #ef4444; }
                    .info { color: #4dabf7; }
                    .metric { display: inline-block; margin: 8px 16px 8px 0; padding: 8px 12px; background: #3a3d52; border-radius: 6px; font-size: 14px; }
                    .btn { display: inline-block; padding: 12px 24px; background: #4dabf7; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s; }
                    .btn:hover { background: #3b9ae1; transform: translateY(-1px); }
                    .status-badge { padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 12px; }
                    .status-success { background: #10b981; color: white; }
                    .status-warning { background: #f59e0b; color: white; }
                    .status-error { background: #ef4444; color: white; }
                </style>
            </head>
            <body>
                <div class="debug-container">
                    <div class="header" style="text-align: center; margin-bottom: 30px;">
                        <h1 style="color: #4dabf7; margin-bottom: 8px;">🔍 Link-Debug-Analyse</h1>
                        <p style="color: #8b8fa3; margin: 0;">Erweiterte Analyse der Backlink-Validierung</p>
                    </div>
                    
                    <div class="debug-section">
                        <h2 style="color: #e2e8f0; margin-bottom: 16px;">📋 Link-Informationen</h2>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
                            <div>
                                <strong>Ankertext:</strong><br>
                                <span style="color: #4dabf7;"><?= htmlspecialchars($link['anchor_text']) ?></span>
                            </div>
                            <div>
                                <strong>Ziel-URL:</strong><br>
                                <span style="color: #10b981; word-break: break-all;"><?= htmlspecialchars($link['target_url']) ?></span>
                            </div>
                            <div>
                                <strong>Backlink-URL:</strong><br>
                                <span style="color: #f59e0b; word-break: break-all;"><?= htmlspecialchars($link['backlink_url']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="debug-section">
                        <h2 style="color: #e2e8f0; margin-bottom: 16px;">📊 Ergebnis-Übersicht</h2>
                        
                        <div style="display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap;">
                            <div class="metric">
                                <strong>HTTP-Status:</strong> 
                                <span class="<?= $result['httpStatus'] >= 200 && $result['httpStatus'] < 300 ? 'success' : 'error' ?>">
                                    <?= $result['httpStatus'] ?>
                                </span>
                            </div>
                            <div class="metric">
                                <strong>URL erreichbar:</strong> 
                                <span class="<?= $result['isValid'] ? 'success' : 'error' ?>">
                                    <?= $result['isValid'] ? 'Ja' : 'Nein' ?>
                                </span>
                            </div>
                            <div class="metric">
                                <strong>Link gefunden:</strong> 
                                <span class="<?= $result['containsTargetLink'] ? 'success' : 'error' ?>">
                                    <?= $result['containsTargetLink'] ? 'Ja' : 'Nein' ?>
                                </span>
                            </div>
                            <div class="metric">
                                <strong>Response-Zeit:</strong> 
                                <span class="info"><?= $result['responseTime'] ?? 0 ?>ms</span>
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: #2a2d42; border-radius: 8px;">
                            <?php if ($result['containsTargetLink']): ?>
                                <div class="status-badge status-success">
                                    🎯 LINK AKTIV - Perfekter Match gefunden!
                                </div>
                            <?php elseif ($result['isValid']): ?>
                                <div class="status-badge status-warning">
                                    ⚠️ LINK AUSSTEHEND - URL erreichbar, aber Link nicht gefunden
                                </div>
                            <?php else: ?>
                                <div class="status-badge status-error">
                                    ❌ LINK DEFEKT - URL nicht erreichbar oder blockiert
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (isset($result['statistics'])): ?>
                    <div class="debug-section">
                        <h2 style="color: #e2e8f0; margin-bottom: 16px;">📈 Statistiken</h2>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                            <div style="text-align: center; padding: 16px; background: #2a2d42; border-radius: 8px;">
                                <div style="font-size: 32px; font-weight: bold; color: #4dabf7;">
                                    <?= $result['statistics']['totalLinks'] ?>
                                </div>
                                <div style="font-size: 12px; color: #8b8fa3;">Gefundene Links</div>
                            </div>
                            <div style="text-align: center; padding: 16px; background: #2a2d42; border-radius: 8px;">
                                <div style="font-size: 32px; font-weight: bold; color: #10b981;">
                                    <?= $result['statistics']['perfectMatches'] ?>
                                </div>
                                <div style="font-size: 12px; color: #8b8fa3;">Perfekte Matches</div>
                            </div>
                            <div style="text-align: center; padding: 16px; background: #2a2d42; border-radius: 8px;">
                                <div style="font-size: 32px; font-weight: bold; color: #f59e0b;">
                                    <?= $result['statistics']['urlMatches'] ?>
                                </div>
                                <div style="font-size: 12px; color: #8b8fa3;">URL-Matches</div>
                            </div>
                            <div style="text-align: center; padding: 16px; background: #2a2d42; border-radius: 8px;">
                                <div style="font-size: 32px; font-weight: bold; color: #06b6d4;">
                                    <?= $result['statistics']['textMatches'] ?>
                                </div>
                                <div style="font-size: 12px; color: #8b8fa3;">Text-Matches</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="?page=links&action=view&id=<?= $linkId ?>" class="btn">
                            ← Zurück zum Link
                        </a>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        } else {
            echo '<div class="alert alert-danger">Link nicht gefunden oder keine Berechtigung.</div>';
            return;
        }
    }

    // Daten laden
    $links = loadData('links.json');
    $customers = loadData('customers.json');
    $blogs = loadData('blogs.json');

    if (!is_array($links)) $links = [];
    if (!is_array($customers)) $customers = [];
    if (!is_array($blogs)) $blogs = [];

    // Links je nach Berechtigung filtern
    if ($isAdmin) {
        $userLinks = $links;
    } else {
        $userLinks = array();
        if (is_array($links)) {
            foreach ($links as $id => $link) {
                if (isset($link['user_id']) && $link['user_id'] === $userId) {
                    $userLinks[$id] = $link;
                }
            }
        }
    }

    // Statistiken berechnen
    $statusStats = array();
    $totalMonthlyCosts = 0;
    if (is_array($userLinks)) {
        foreach ($userLinks as $link) {
            $status = $link['status'] ?? 'ausstehend';
            $statusStats[$status] = ($statusStats[$status] ?? 0) + 1;
            
            // Monatliche Kosten summieren
            if (!empty($link['cost_amount']) && !empty($link['cost_type'])) {
                $totalMonthlyCosts += calculateMonthlyCost($link['cost_amount'], $link['cost_type']);
            }
        }
    }

    // Hilfsfunktion: Website-Info aus Link laden
    function getCustomerWebsiteInfo($link, $customers) {
        $customerId = $link['customer_id'] ?? '';
        $websiteId = $link['customer_website_id'] ?? '';
        
        if (!isset($customers[$customerId])) {
            return null;
        }
        
        $customer = $customers[$customerId];
        
        // Neue Struktur mit websites-Array
        if (!empty($customer['websites']) && is_array($customer['websites'])) {
            if (isset($customer['websites'][$websiteId])) {
                return $customer['websites'][$websiteId];
            }
        }
        // Backward compatibility: Alte einzelne Website
        elseif (!empty($customer['website'])) {
            return [
                'url' => $customer['website'],
                'title' => parse_url($customer['website'], PHP_URL_HOST),
                'description' => ''
            ];
        }
        
        return null;
    }

    // View Logic starts here
    if ($action === 'index'): ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    Link-Verwaltung
                    <?php if ($isAdmin): ?>
                        <span class="badge badge-info" style="font-size: 12px; margin-left: 8px;">ADMIN</span>
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    Verwalten Sie Ihre platzierten Links (<?= count($userLinks) ?> Links)
                    <?php if ($totalMonthlyCosts > 0): ?>
                        • Monatliche Kosten: <strong><?= formatCostDisplay($totalMonthlyCosts, 'monthly') ?></strong>
                    <?php endif; ?>
                </p>
            </div>
            <div class="action-buttons">
                <a href="?page=links&action=create" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Neuer Link
                </a>
            </div>
        </div>

        <?php showFlashMessage(); ?>

        <!-- Dashboard -->
        <div class="content-grid">
            <!-- Link-Statistiken -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Link-Status Übersicht</h3>
                    <p class="card-subtitle">Aktuelle Verteilung der Link-Status</p>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 16px;">
                        <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                            <div style="font-size: 28px; font-weight: bold; color: #2dd4bf; margin-bottom: 4px;">
                                <?= count($userLinks) ?>
                            </div>
                            <div style="font-size: 12px; color: #8b8fa3;">Gesamt</div>
                        </div>
                        <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                            <div style="font-size: 28px; font-weight: bold; color: #10b981; margin-bottom: 4px;">
                                <?= $statusStats['aktiv'] ?? 0 ?>
                            </div>
                            <div style="font-size: 12px; color: #8b8fa3;">Aktiv</div>
                        </div>
                        <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                            <div style="font-size: 28px; font-weight: bold; color: #f59e0b; margin-bottom: 4px;">
                                <?= $statusStats['ausstehend'] ?? 0 ?>
                            </div>
                            <div style="font-size: 12px; color: #8b8fa3;">Ausstehend</div>
                        </div>
                        <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                            <div style="font-size: 28px; font-weight: bold; color: #ef4444; margin-bottom: 4px;">
                                <?= $statusStats['defekt'] ?? 0 ?>
                            </div>
                            <div style="font-size: 12px; color: #8b8fa3;">Defekt</div>
                        </div>
                    </div>
                    
                    <!-- Kosten-Übersicht -->
                    <?php if ($totalMonthlyCosts > 0): ?>
                        <div style="margin-top: 20px; padding: 16px; background: linear-gradient(135deg, #343852, #2a2d42); border-radius: 8px; border: 1px solid #4dabf7;">
                            <div style="text-align: center;">
                                <div style="font-size: 12px; color: #8b8fa3; margin-bottom: 4px;">GESCHÄTZTE MONATLICHE KOSTEN</div>
                                <div style="font-size: 24px; font-weight: bold; color: #4dabf7;">
                                    <?= formatCostDisplay($totalMonthlyCosts, 'monthly') ?>
                                </div>
                                <div style="font-size: 11px; color: #8b8fa3; margin-top: 4px;">
                                    Basierend auf <?= count(array_filter($userLinks, function($link) { return !empty($link['cost_amount']); })) ?> Links mit Kostendaten
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Schnellaktionen -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Schnellaktionen</h3>
                    <p class="card-subtitle">Häufig verwendete Link-Funktionen</p>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="?page=links&action=create" class="quick-action">
                            <div class="quick-action-icon" style="background: linear-gradient(135deg, #4dabf7, #3b9ae1);">
                                <i class="fas fa-link"></i>
                            </div>
                            <div class="quick-action-content">
                                <div class="quick-action-title">Neuer Link</div>
                                <div class="quick-action-subtitle">Link zu einem Blog hinzufügen</div>
                            </div>
                        </a>

                        <a href="?page=customers" class="quick-action">
                            <div class="quick-action-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="quick-action-content">
                                <div class="quick-action-title">Kunden verwalten</div>
                                <div class="quick-action-subtitle">Kunden hinzufügen oder bearbeiten</div>
                            </div>
                        </a>

                        <a href="?page=blogs" class="quick-action">
                            <div class="quick-action-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                                <i class="fas fa-blog"></i>
                            </div>
                            <div class="quick-action-content">
                                <div class="quick-action-title">Blogs verwalten</div>
                                <div class="quick-action-subtitle">Blog-Netzwerk erweitern</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($userLinks)): ?>
            <div class="empty-state">
                <i class="fas fa-link"></i>
                <h3>Keine Links vorhanden</h3>
                <p>Erstellen Sie Ihren ersten Link, um hier eine Übersicht zu sehen.</p>
                <a href="?page=links&action=create" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="fas fa-plus"></i> Ersten Link erstellen
                </a>
            </div>
        <?php else: ?>
            <!-- Links-Tabelle -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3 class="card-title">Ihre Links</h3>
                    <p class="card-subtitle"><?= count($userLinks) ?> Links insgesamt</p>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ankertext</th>
                                    <th>Kunde / Website</th>
                                    <th>Blog</th>
                                    <th>Status</th>
                                    <th>Kosten</th>
                                    <th>Letzte Prüfung</th>
                                    <th>Erstellt</th>
                                    <th style="width: 180px;">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userLinks as $linkId => $link): 
                                    $customer = $customers[$link['customer_id']] ?? null;
                                    $blog = $blogs[$link['blog_id']] ?? null;
                                    $websiteInfo = getCustomerWebsiteInfo($link, $customers);
                                ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: #e2e8f0; margin-bottom: 4px;">
                                                <a href="?page=links&action=view&id=<?= $linkId ?>" style="color: #4dabf7; text-decoration: none;">
                                                    <?= htmlspecialchars($link['anchor_text'] ?? 'Unbekannt') ?>
                                                </a>
                                            </div>
                                            <div style="font-size: 11px; color: #8b8fa3;">
                                                <?= htmlspecialchars(substr($link['backlink_url'] ?? '', 0, 50)) ?>...
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($customer): ?>
                                                <div style="margin-bottom: 2px;">
                                                    <a href="?page=customers&action=view&id=<?= $link['customer_id'] ?>" style="color: #4dabf7; text-decoration: none; font-weight: 600;">
                                                        <?= htmlspecialchars($customer['name']) ?>
                                                    </a>
                                                </div>
                                                <?php if ($websiteInfo): ?>
                                                    <div style="font-size: 11px; color: #8b8fa3;">
                                                        🌐 <?= htmlspecialchars($websiteInfo['title'] ?? $websiteInfo['url']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #8b8fa3;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($blog): ?>
                                                <a href="?page=blogs&action=view&id=<?= $link['blog_id'] ?>" style="color: #4dabf7; text-decoration: none;">
                                                    <?= htmlspecialchars($blog['name']) ?>
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
                                        <td style="color: #8b8fa3; font-size: 12px;">
                                            <?php if (!empty($link['cost_amount']) && !empty($link['cost_type'])): ?>
                                                <div style="color: #4dabf7; font-weight: 600;">
                                                    <?= formatCostDisplay($link['cost_amount'], $link['cost_type'], $link['cost_currency'] ?? 'EUR') ?>
                                                </div>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #8b8fa3; font-size: 12px;">
                                            <?php if (!empty($link['last_checked'])): ?>
                                                <?= date('d.m.Y H:i', strtotime($link['last_checked'])) ?>
                                            <?php else: ?>
                                                Noch nie
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #8b8fa3; font-size: 12px;">
                                            <?= isset($link['created_at']) ? date('d.m.Y', strtotime($link['created_at'])) : '-' ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 4px;">
                                                <a href="?page=links&action=view&id=<?= $linkId ?>" class="btn btn-sm btn-primary" title="Anzeigen">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <form method="post" style="display: inline;" id="linkCheckForm<?= $linkId ?>">
                                                    <input type="hidden" name="action" value="check">
                                                    <input type="hidden" name="id" value="<?= $linkId ?>">
                                                    <button type="submit" class="btn btn-sm btn-info link-check-btn" title="Link prüfen" data-link-id="<?= $linkId ?>">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                </form>
                                                <a href="?page=links&action=debug&id=<?= $linkId ?>" class="btn btn-sm btn-warning" title="Debug-Analyse">
                                                    <i class="fas fa-bug"></i>
                                                </a>
                                                <a href="?page=links&action=edit&id=<?= $linkId ?>" class="btn btn-sm btn-secondary" title="Bearbeiten">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Link löschen?\\n\\n<?= htmlspecialchars($link['anchor_text']) ?>\\n\\nDiese Aktion kann nicht rückgängig gemacht werden!');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $linkId ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Löschen">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
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

    <?php elseif ($action === 'view' && $linkId): 
        $link = $userLinks[$linkId] ?? null;
        if (!$link) {
            echo '<div class="alert alert-danger">Link nicht gefunden oder keine Berechtigung.</div>';
            echo '<a href="?page=links" class="btn btn-primary">← Zurück zu Links</a>';
            return;
        }
        
        $customer = $customers[$link['customer_id']] ?? null;
        $blog = $blogs[$link['blog_id']] ?? null;
        $websiteInfo = getCustomerWebsiteInfo($link, $customers);
    ?>
        <div class="breadcrumb">
            <a href="?page=links">Zurück zu Links</a>
            <i class="fas fa-chevron-right"></i>
            <span><?= htmlspecialchars($link['anchor_text']) ?></span>
        </div>

        <div class="page-header">
            <div>
                <h1 class="page-title"><?= htmlspecialchars($link['anchor_text']) ?></h1>
                <p class="page-subtitle">
                    Von <strong><?= $customer ? htmlspecialchars($customer['name']) : 'Unbekannter Kunde' ?></strong> 
                    zu <strong><?= $blog ? htmlspecialchars($blog['name']) : 'Unbekannter Blog' ?></strong>
                    <?php if ($websiteInfo): ?>
                        <br><small style="color: #8b8fa3;">
                            🌐 Für Website: <?= htmlspecialchars($websiteInfo['title'] ?? $websiteInfo['url']) ?>
                        </small>
                    <?php endif; ?>
                    <?php if (!empty($link['cost_amount']) && !empty($link['cost_type'])): ?>
                        <br><small style="color: #4dabf7; font-weight: 600;">
                            💰 Kosten: <?= formatCostDisplay($link['cost_amount'], $link['cost_type'], $link['cost_currency'] ?? 'EUR') ?>
                        </small>
                    <?php endif; ?>
                </p>
            </div>
            <div class="action-buttons">
                <form method="post" style="display: inline;" id="linkCheckFormDetail">
                    <input type="hidden" name="action" value="check">
                    <input type="hidden" name="id" value="<?= $linkId ?>">
                    <button type="submit" class="btn btn-info link-check-btn-detail" data-link-id="<?= $linkId ?>">
                        <i class="fas fa-sync-alt"></i> Link prüfen
                    </button>
                </form>
                <a href="?page=links&action=debug&id=<?= $linkId ?>" class="btn btn-warning">
                    <i class="fas fa-bug"></i> Debug-Analyse
                </a>
                <a href="?page=links&action=edit&id=<?= $linkId ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Bearbeiten
                </a>
                <form method="post" style="display: inline;" onsubmit="return confirm('Sind Sie sicher, dass Sie diesen Link löschen möchten?\\n\\nLink: <?= htmlspecialchars($link['anchor_text']) ?>\\n\\nDiese Aktion kann nicht rückgängig gemacht werden!');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $linkId ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Löschen
                    </button>
                </form>
            </div>
        </div>

        <?php showFlashMessage(); ?>

        <!-- Link-Details Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Link-Informationen</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <div style="margin-bottom: 16px;">
                            <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">ANKERTEXT</div>
                            <div style="color: #e2e8f0;"><?= htmlspecialchars($link['anchor_text']) ?></div>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">ZIEL-URL</div>
                            <div style="color: #e2e8f0;">
                                <a href="<?= htmlspecialchars($link['target_url']) ?>" target="_blank" style="color: #4dabf7; word-break: break-all;">
                                    <?= htmlspecialchars($link['target_url']) ?>
                                    <i class="fas fa-external-link-alt" style="margin-left: 4px; font-size: 12px;"></i>
                                </a>
                            </div>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">BACKLINK-URL</div>
                            <div style="color: #e2e8f0;">
                                <a href="<?= htmlspecialchars($link['backlink_url']) ?>" target="_blank" style="color: #4dabf7; word-break: break-all;">
                                    <?= htmlspecialchars($link['backlink_url']) ?>
                                    <i class="fas fa-external-link-alt" style="margin-left: 4px; font-size: 12px;"></i>
                                </a>
                            </div>
                        </div>
                        <?php if ($websiteInfo): ?>
                        <div style="margin-bottom: 16px;">
                            <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">KUNDEN-WEBSITE</div>
                            <div style="color: #e2e8f0;">
                                <a href="<?= htmlspecialchars($websiteInfo['url']) ?>" target="_blank" style="color: #4dabf7; word-break: break-all;">
                                    🌐 <?= htmlspecialchars($websiteInfo['title'] ?? $websiteInfo['url']) ?>
                                    <i class="fas fa-external-link-alt" style="margin-left: 4px; font-size: 12px;"></i>
                                </a>
                                <?php if (!empty($websiteInfo['description'])): ?>
                                    <div style="font-size: 12px; color: #8b8fa3; margin-top: 4px;">
                                        <?= htmlspecialchars($websiteInfo['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="margin-bottom: 16px;">
                            <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">STATUS</div>
                            <div style="color: #e2e8f0;">
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
                            </div>
                        </div>
                        
                        <!-- Kosteninformationen -->
                        <?php if (!empty($link['cost_amount']) && !empty($link['cost_type'])): ?>
                        <div style="margin-bottom: 16px;">
                            <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">KOSTEN</div>
                            <div style="color: #4dabf7; font-weight: 600; font-size: 16px;">
                                💰 <?= formatCostDisplay($link['cost_amount'], $link['cost_type'], $link['cost_currency'] ?? 'EUR') ?>
                            </div>
                            <?php if ($link['cost_type'] !== 'monthly'): ?>
                                <div style="font-size: 12px; color: #8b8fa3; margin-top: 4px;">
                                    Monatlich: <?= formatCostDisplay(calculateMonthlyCost($link['cost_amount'], $link['cost_type']), 'monthly', $link['cost_currency'] ?? 'EUR') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div style="margin-bottom: 16px;">
                            <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">LETZTE PRÜFUNG</div>
                            <div style="color: #e2e8f0;">
                                <?php if (!empty($link['last_checked'])): ?>
                                    <?= date('d.m.Y H:i:s', strtotime($link['last_checked'])) ?>
                                <?php else: ?>
                                    Noch nie geprüft
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">ERSTELLT AM</div>
                            <div style="color: #e2e8f0;">
                                <?= isset($link['created_at']) ? date('d.m.Y H:i:s', strtotime($link['created_at'])) : '-' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($link['description'])): ?>
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #3a3d52;">
                        <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 8px;">BESCHREIBUNG</div>
                        <p style="color: #e2e8f0; line-height: 1.6; white-space: pre-line; margin: 0;"><?= htmlspecialchars($link['description']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($link['check_result'])): ?>
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3 class="card-title">Letzte Prüfungsergebnisse</h3>
                    <p class="card-subtitle">Detaillierte Analyse der Link-Validierung</p>
                </div>
                <div class="card-body">
                    <?php $result = $link['check_result']; ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; margin-bottom: 8px;">
                                <i class="fas fa-globe" style="color: <?= $result['isValid'] ? '#10b981' : '#ef4444' ?>;"></i>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">URL Erreichbar</div>
                            <div style="font-size: 16px; font-weight: bold; color: <?= $result['isValid'] ? '#10b981' : '#ef4444' ?>;">
                                <?= $result['isValid'] ? 'Ja' : 'Nein' ?>
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; margin-bottom: 8px;">
                                <i class="fas fa-link" style="color: <?= $result['containsTargetLink'] ? '#10b981' : '#ef4444' ?>;"></i>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">Korrekter Link gefunden</div>
                            <div style="font-size: 16px; font-weight: bold; color: <?= $result['containsTargetLink'] ? '#10b981' : '#ef4444' ?>;">
                                <?= $result['containsTargetLink'] ? 'Ja' : 'Nein' ?>
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; margin-bottom: 8px;">
                                <i class="fas fa-chart-line" style="color: #4dabf7;"></i>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">HTTP Status</div>
                            <div style="font-size: 16px; font-weight: bold; color: #4dabf7;">
                                <?= $result['httpStatus'] ?>
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                            <div style="font-size: 28px; margin-bottom: 8px;">
                                <i class="fas fa-clock" style="color: #06b6d4;"></i>
                            </div>
                            <div style="font-size: 14px; color: #8b8fa3;">Response-Zeit</div>
                            <div style="font-size: 16px; font-weight: bold; color: #06b6d4;">
                                <?= $result['responseTime'] ?? 0 ?>ms
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($result['error'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Prüfungshinweis:</strong> <?= htmlspecialchars($result['error']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($action === 'edit' && $linkId): 
        $link = $userLinks[$linkId] ?? null;
        if (!$link) {
            echo '<div class="alert alert-danger">Link nicht gefunden oder keine Berechtigung.</div>';
            echo '<a href="?page=links" class="btn btn-primary">← Zurück zu Links</a>';
            return;
        }
        
        $customer = $customers[$link['customer_id']] ?? null;
        $blog = $blogs[$link['blog_id']] ?? null;
    ?>
        <div class="breadcrumb">
            <a href="?page=links">Zurück zu Links</a>
            <i class="fas fa-chevron-right"></i>
            <a href="?page=links&action=view&id=<?= $linkId ?>"><?= htmlspecialchars($link['anchor_text']) ?></a>
            <i class="fas fa-chevron-right"></i>
            <span>Bearbeiten</span>
        </div>

        <div class="page-header">
            <div>
                <h1 class="page-title">Link bearbeiten</h1>
                <p class="page-subtitle">
                    Link "<?= htmlspecialchars($link['anchor_text']) ?>" bearbeiten
                </p>
            </div>
        </div>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Link-Formular</h3>
            </div>
            <div class="card-body">
                <form method="post" id="linkForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($linkId) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div>
                            <label class="form-label">Kunde *</label>
                            <select name="customer_id" class="form-control" required id="customerSelect" onchange="loadCustomerWebsites()">
                                <option value="">Kunde auswählen</option>
                                <?php foreach ($customers as $customerId => $customer): ?>
                                    <?php if ($isAdmin || $customer['user_id'] === $userId): ?>
                                        <option value="<?= htmlspecialchars($customerId) ?>" 
                                                <?= $customerId === $link['customer_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($customer['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Blog *</label>
                            <select name="blog_id" class="form-control" required>
                                <option value="">Blog auswählen</option>
                                <?php foreach ($blogs as $blogId => $blog): ?>
                                    <?php if ($isAdmin || $blog['user_id'] === $userId): ?>
                                        <option value="<?= htmlspecialchars($blogId) ?>" 
                                                <?= $blogId === $link['blog_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($blog['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Website-Auswahl für Kunde -->
                    <div id="websiteSelectionContainer" style="margin-bottom: 20px; display: none;">
                        <label class="form-label">Kunden-Website (Optional)</label>
                        <select name="customer_website_id" class="form-control" id="customerWebsiteSelect">
                            <option value="">Keine spezifische Website</option>
                        </select>
                        <small style="color: #8b8fa3; font-size: 12px; display: block; margin-top: 4px;">
                            Wählen Sie die spezifische Website des Kunden für die dieser Link erstellt wird
                        </small>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label">Ankertext *</label>
                        <input type="text" name="anchor_text" class="form-control" 
                               value="<?= htmlspecialchars($link['anchor_text']) ?>" 
                               placeholder="Der sichtbare Text des Links" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div>
                            <label class="form-label">Ziel-URL *</label>
                            <input type="url" name="target_url" class="form-control" 
                                   value="<?= htmlspecialchars($link['target_url']) ?>" 
                                   placeholder="https://example.com/seite" required>
                        </div>
                        <div>
                            <label class="form-label">Backlink-URL *</label>
                            <input type="url" name="backlink_url" class="form-control" 
                                   value="<?= htmlspecialchars($link['backlink_url']) ?>" 
                                   placeholder="https://blog.example.com/artikel" required>
                        </div>
                    </div>

                    <!-- Kosten-Sektion -->
                    <div class="cost-section" style="background: #2a2d42; border: 1px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="color: #f59e0b; margin: 0 0 16px 0; font-size: 16px;">
                            <i class="fas fa-euro-sign" style="margin-right: 8px;"></i>
                            Kostenverwaltung
                        </h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 16px; align-items: end;">
                            <div>
                                <label class="form-label">Kostenbetrag</label>
                                <input type="text" name="cost_amount" class="form-control" 
                                       value="<?= !empty($link['cost_amount']) ? number_format($link['cost_amount'], 2, ',', '') : '' ?>"
                                       placeholder="0,00" pattern="[0-9]+([.,][0-9]{1,2})?"
                                       title="Bitte geben Sie einen gültigen Betrag ein (z.B. 12,50)">
                            </div>
                            <div style="min-width: 120px;">
                                <label class="form-label">Kostentyp</label>
                                <select name="cost_type" class="form-control">
                                    <option value="monthly" <?= ($link['cost_type'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>Monatlich</option>
                                    <option value="yearly" <?= ($link['cost_type'] ?? '') === 'yearly' ? 'selected' : '' ?>>Jährlich</option>
                                    <option value="onetime" <?= ($link['cost_type'] ?? '') === 'onetime' ? 'selected' : '' ?>>Einmalig</option>
                                </select>
                            </div>
                            <div style="min-width: 80px;">
                                <label class="form-label">Währung</label>
                                <select name="cost_currency" class="form-control">
                                    <option value="EUR" <?= ($link['cost_currency'] ?? 'EUR') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                                    <option value="USD" <?= ($link['cost_currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="GBP" <?= ($link['cost_currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP</option>
                                </select>
                            </div>
                        </div>
                        
                        <small style="color: #8b8fa3; font-size: 12px; display: block; margin-top: 8px;">
                            💡 Tipp: Lassen Sie das Feld leer, wenn für diesen Link keine Kosten anfallen
                        </small>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label">Beschreibung</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Zusätzliche Notizen zu diesem Link"><?= htmlspecialchars($link['description'] ?? '') ?></textarea>
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Änderungen speichern
                        </button>
                        <a href="?page=links&action=view&id=<?= $linkId ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Abbrechen
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Website-Auswahl beim Edit-Mode laden
        document.addEventListener('DOMContentLoaded', function() {
            const currentWebsiteId = '<?= htmlspecialchars($link['customer_website_id'] ?? '') ?>';
            if (document.getElementById('customerSelect').value) {
                loadCustomerWebsites(currentWebsiteId);
            }
        });
        </script>

    <?php elseif ($action === 'create'): ?>
        <div class="breadcrumb">
            <a href="?page=links">Zurück zu Links</a>
            <i class="fas fa-chevron-right"></i>
            <span>Link hinzufügen</span>
        </div>

        <div class="page-header">
            <div>
                <h1 class="page-title">Neuen Link hinzufügen</h1>
                <p class="page-subtitle">Erstellen Sie einen neuen Link zwischen Kunde und Blog</p>
            </div>
        </div>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Link-Formular</h3>
            </div>
            <div class="card-body">
                <form method="post" id="linkForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div>
                            <label class="form-label">Kunde *</label>
                            <select name="customer_id" class="form-control" required id="customerSelect" onchange="loadCustomerWebsites()">
                                <option value="">Kunde auswählen</option>
                                <?php foreach ($customers as $customerId => $customer): ?>
                                    <?php if ($isAdmin || $customer['user_id'] === $userId): ?>
                                        <option value="<?= htmlspecialchars($customerId) ?>" 
                                                <?= (isset($_POST['customer_id']) && $_POST['customer_id'] === $customerId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($customer['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Blog *</label>
                            <select name="blog_id" class="form-control" required>
                                <option value="">Blog auswählen</option>
                                <?php foreach ($blogs as $blogId => $blog): ?>
                                    <?php if ($isAdmin || $blog['user_id'] === $userId): ?>
                                        <option value="<?= htmlspecialchars($blogId) ?>" 
                                                <?= (isset($_POST['blog_id']) && $_POST['blog_id'] === $blogId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($blog['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Website-Auswahl für Kunde -->
                    <div id="websiteSelectionContainer" style="margin-bottom: 20px; display: none;">
                        <label class="form-label">
                            <i class="fas fa-globe" style="margin-right: 6px; color: #4dabf7;"></i>
                            Kunden-Website (Optional)
                        </label>
                        <select name="customer_website_id" class="form-control" id="customerWebsiteSelect">
                            <option value="">Keine spezifische Website</option>
                        </select>
                        <small style="color: #8b8fa3; font-size: 12px; display: block; margin-top: 4px;">
                            📌 Wählen Sie die spezifische Website des Kunden für die dieser Link erstellt wird
                        </small>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label">Ankertext *</label>
                        <input type="text" name="anchor_text" class="form-control" 
                               value="<?= htmlspecialchars($_POST['anchor_text'] ?? '') ?>"
                               placeholder="Der sichtbare Text des Links" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div>
                            <label class="form-label">Ziel-URL *</label>
                            <input type="url" name="target_url" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['target_url'] ?? '') ?>"
                                   placeholder="https://example.com/seite" required>
                        </div>
                        <div>
                            <label class="form-label">Backlink-URL *</label>
                            <input type="url" name="backlink_url" class="form-control" 
                                   value="<?= htmlspecialchars($_POST['backlink_url'] ?? '') ?>"
                                   placeholder="https://blog.example.com/artikel" required>
                        </div>
                    </div>

                    <!-- Kosten-Sektion -->
                    <div class="cost-section" style="background: #2a2d42; border: 1px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <h4 style="color: #f59e0b; margin: 0 0 16px 0; font-size: 16px;">
                            <i class="fas fa-euro-sign" style="margin-right: 8px;"></i>
                            Kostenverwaltung
                        </h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 16px; align-items: end;">
                            <div>
                                <label class="form-label">Kostenbetrag</label>
                                <input type="text" name="cost_amount" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['cost_amount'] ?? '') ?>"
                                       placeholder="0,00" pattern="[0-9]+([.,][0-9]{1,2})?"
                                       title="Bitte geben Sie einen gültigen Betrag ein (z.B. 12,50)">
                            </div>
                            <div style="min-width: 120px;">
                                <label class="form-label">Kostentyp</label>
                                <select name="cost_type" class="form-control">
                                    <option value="monthly" <?= ($_POST['cost_type'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>Monatlich</option>
                                    <option value="yearly" <?= ($_POST['cost_type'] ?? '') === 'yearly' ? 'selected' : '' ?>>Jährlich</option>
                                    <option value="onetime" <?= ($_POST['cost_type'] ?? '') === 'onetime' ? 'selected' : '' ?>>Einmalig</option>
                                </select>
                            </div>
                            <div style="min-width: 80px;">
                                <label class="form-label">Währung</label>
                                <select name="cost_currency" class="form-control">
                                    <option value="EUR" <?= ($_POST['cost_currency'] ?? 'EUR') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                                    <option value="USD" <?= ($_POST['cost_currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="GBP" <?= ($_POST['cost_currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP</option>
                                </select>
                            </div>
                        </div>
                        
                        <small style="color: #8b8fa3; font-size: 12px; display: block; margin-top: 8px;">
                            💡 Tipp: Lassen Sie das Feld leer, wenn für diesen Link keine Kosten anfallen
                        </small>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label">Beschreibung</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Zusätzliche Notizen zu diesem Link"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Link erstellen
                        </button>
                        <a href="?page=links" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Abbrechen
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Website-Auswahl beim Create-Mode laden falls Kunde bereits ausgewählt
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('customerSelect').value) {
                loadCustomerWebsites();
            }
        });
        </script>

    <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            Ungültige Aktion oder fehlende Parameter.
        </div>
        <a href="?page=links" class="btn btn-primary">← Zurück zu Links</a>

    <?php endif; ?>

    <style>
    /* Link-spezifische Styles */
    .form-label {
        font-weight: 600;
        color: #8b8fa3;
        font-size: 12px;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: block;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        background-color: #343852;
        border: 1px solid #3a3d52;
        border-radius: 6px;
        color: #e2e8f0;
        font-size: 14px;
        transition: border-color 0.2s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: #4dabf7;
        box-shadow: 0 0 0 2px rgba(77, 171, 247, 0.2);
    }

    .form-control::placeholder {
        color: #6b7280;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .quick-action {
        display: flex;
        align-items: center;
        padding: 16px;
        background-color: #343852;
        border: 1px solid #3a3d52;
        border-radius: 8px;
        text-decoration: none;
        color: #e4e4e7;
        transition: all 0.3s ease;
        gap: 12px;
        cursor: pointer;
    }

    .quick-action:hover {
        background-color: #3a3d52;
        border-color: #4dabf7;
        transform: translateY(-1px);
        color: #e4e4e7;
        text-decoration: none;
    }

    .quick-action-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: white;
    }

    .quick-action-content {
        flex: 1;
    }

    .quick-action-title {
        font-weight: 600;
        color: #e4e4e7;
        margin-bottom: 2px;
    }

    .quick-action-subtitle {
        font-size: 12px;
        color: #8b8fa3;
    }

    .btn-danger {
        background-color: #ef4444;
        border-color: #ef4444;
        color: white;
    }

    .btn-danger:hover {
        background-color: #dc2626;
        border-color: #dc2626;
        color: white;
    }

    .btn-sm.btn-danger {
        background-color: #ef4444;
        border-color: #ef4444;
    }

    .btn-sm.btn-danger:hover {
        background-color: #dc2626;
        border-color: #dc2626;
    }

    .btn.checking {
        position: relative;
        color: transparent;
        pointer-events: none;
    }

    .btn.checking::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 16px;
        height: 16px;
        border: 2px solid #ffffff;
        border-top: 2px solid transparent;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: translate(-50%, -50%) rotate(0deg); }
        100% { transform: translate(-50%, -50%) rotate(360deg); }
    }

    /* Website Selection Styling */
    #websiteSelectionContainer {
        background: #2a2d42;
        border: 1px solid #4dabf7;
        border-radius: 8px;
        padding: 16px;
        position: relative;
        overflow: hidden;
    }

    #websiteSelectionContainer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #4dabf7, #06b6d4);
    }

    #websiteSelectionContainer.visible {
        animation: slideDown 0.3s ease-out;
    }

    #websiteSelectionContainer .form-label {
        color: #4dabf7 !important;
        font-weight: 600;
        margin-bottom: 8px;
    }

    #customerWebsiteSelect {
        background-color: #343852 !important;
        border: 1px solid #4dabf7 !important;
    }

    #customerWebsiteSelect:focus {
        border-color: #06b6d4 !important;
        box-shadow: 0 0 0 2px rgba(77, 171, 247, 0.3) !important;
    }

    /* Kosten-Sektion */
    .cost-section {
        position: relative;
        overflow: hidden;
    }

    .cost-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #f59e0b, #d97706);
    }

    .cost-section .form-control {
        background-color: #343852 !important;
        border-color: #f59e0b !important;
    }

    .cost-section .form-control:focus {
        border-color: #d97706 !important;
        box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.3) !important;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
            max-height: 0;
        }
        to {
            opacity: 1;
            transform: translateY(0);
            max-height: 200px;
        }
    }

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            gap: 16px;
        }
        
        .action-buttons {
            width: 100%;
            justify-content: stretch;
        }
        
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .cost-section > div {
            grid-template-columns: 1fr !important;
        }
    }
    </style>

    <script>
    // Website-Auswahl Funktion
    async function loadCustomerWebsites(preselectedWebsiteId = null) {
        const customerSelect = document.getElementById('customerSelect');
        const websiteContainer = document.getElementById('websiteSelectionContainer');
        const websiteSelect = document.getElementById('customerWebsiteSelect');
        
        if (!customerSelect || !websiteContainer || !websiteSelect) {
            return;
        }
        
        const customerId = customerSelect.value;
        
        if (!customerId) {
            websiteContainer.style.display = 'none';
            return;
        }
        
        try {
            websiteSelect.innerHTML = '<option value="">🔄 Lade Websites...</option>';
            websiteSelect.disabled = true;
            
            const url = `ajax/get_customer_websites.php?customer_id=${encodeURIComponent(customerId)}&t=${Date.now()}`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Unknown server error');
            }
            
            websiteSelect.innerHTML = '<option value="">Keine spezifische Website</option>';
            
            if (data.websites && data.websites.length > 0) {
                data.websites.forEach((website, index) => {
                    const option = document.createElement('option');
                    option.value = website.id;
                    option.textContent = `🌐 ${website.title}`;
                    if (website.description) {
                        option.textContent += ` - ${website.description}`;
                    }
                    
                    if (preselectedWebsiteId !== null && website.id == preselectedWebsiteId) {
                        option.selected = true;
                    }
                    
                    websiteSelect.appendChild(option);
                });
                
                websiteContainer.style.display = 'block';
                websiteContainer.classList.add('visible');
                
                const label = websiteContainer.querySelector('.form-label');
                if (label) {
                    label.innerHTML = `<i class="fas fa-globe" style="margin-right: 6px; color: #4dabf7;"></i>Kunden-Website (${data.websites.length} verfügbar)`;
                }
            } else {
                websiteContainer.style.display = 'none';
            }
            
            websiteSelect.disabled = false;
            
        } catch (error) {
            websiteSelect.innerHTML = '<option value="">❌ Fehler beim Laden</option>';
            websiteSelect.disabled = false;
            websiteContainer.style.display = 'block';
        }
    }

    // Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Handler für Link-Prüfung Buttons
        document.querySelectorAll('.link-check-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                startLinkCheck(form, this, 'small');
            });
        });

        document.querySelectorAll('.link-check-btn-detail').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                startLinkCheck(form, this, 'detail');
            });
        });

        // Website-Auswahl laden falls Kunde bereits ausgewählt
        const customerSelect = document.getElementById('customerSelect');
        if (customerSelect && customerSelect.value) {
            loadCustomerWebsites();
        }

        function startLinkCheck(form, button, type) {
            button.classList.add('checking');
            button.disabled = true;

            if (type === 'detail') {
                setTimeout(() => {
                    form.submit();
                }, 1500);
            } else {
                setTimeout(() => {
                    form.submit();
                }, 1000);
            }
        }
    });
    </script>

<?php

} catch (Exception $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    echo '<div class="alert alert-danger">';
    echo '<h3>Ein Fehler ist aufgetreten:</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>Datei:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Zeile:</strong> ' . $e->getLine() . '</p>';
    echo '</div>';
    echo '<a href="?page=dashboard" class="btn btn-primary">← Zurück zum Dashboard</a>';
}

if (ob_get_level()) {
    ob_end_flush();
}

?>