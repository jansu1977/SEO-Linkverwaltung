<?php
/**
 * LinkBuilder Pro - Link-Verwaltung (Robuste Version)
 * pages/links.php - Implementiert moderne Best Practices für Website-Scraping
 */

// Output Buffering starten um Header-Probleme zu vermeiden
ob_start();

// Error Reporting für Produktion deaktivieren (um Header-Probleme zu vermeiden)
error_reporting(0);
ini_set('display_errors', 0);

/**
 * Robuster Backlink-Checker mit Anti-Bot-Detection
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
     * Erweiterte Backlink-Prüfung mit umfassendem Debugging
     */
    public function checkBacklinkAdvanced($backlinkUrl, $targetUrl, $anchorText, $options = []) {
        $debug = [];
        $debug[] = "=== ERWEITERTE BACKLINK-PRÜFUNG GESTARTET ===";
        $debug[] = "Backlink-URL: $backlinkUrl";
        $debug[] = "Ziel-URL: $targetUrl";
        $debug[] = "Ankertext: '$anchorText'";
        $debug[] = "Timestamp: " . date('Y-m-d H:i:s');
        
        $startTime = microtime(true);
        
        // Input-Validierung
        if (empty($backlinkUrl) || empty($targetUrl) || empty($anchorText)) {
            return $this->createErrorResponse('Fehlende Parameter (URL oder Ankertext)', $debug, 0);
        }
        
        // Domain für Rate-Limiting extrahieren
        $domain = parse_url($backlinkUrl, PHP_URL_HOST);
        $debug[] = "Domain: $domain";
        
        // Rate-Limiting prüfen
        if (!$this->checkRateLimit($domain)) {
            $debug[] = "FEHLER: Rate-Limit für Domain $domain erreicht";
            return $this->createErrorResponse('Rate-Limit erreicht, bitte später versuchen', $debug, 429);
        }
        
        // Circuit-Breaker prüfen
        if (!$this->checkCircuitBreaker($domain)) {
            $debug[] = "FEHLER: Circuit-Breaker für Domain $domain geöffnet";
            return $this->createErrorResponse('Domain temporär nicht verfügbar', $debug, 503);
        }
        
        try {
            // SCHRITT 1: HTTP-Erreichbarkeit prüfen
            $debug[] = "\n--- SCHRITT 1: HTTP-ERREICHBARKEIT PRÜFEN ---";
            $statusResult = $this->checkHttpStatus($backlinkUrl, $debug);
            
            if (!$statusResult['isValid']) {
                $this->recordCircuitBreakerFailure($domain);
                return $this->createErrorResponse($statusResult['error'], $debug, $statusResult['httpStatus']);
            }
            
            // SCHRITT 2: HTML-Content laden mit mehreren Fallbacks
            $debug[] = "\n--- SCHRITT 2: HTML-CONTENT LADEN ---";
            $contentResult = $this->loadHtmlContent($backlinkUrl, $debug);
            
            if (!$contentResult['success']) {
                $this->recordCircuitBreakerFailure($domain);
                return $this->createErrorResponse($contentResult['error'], $debug, $contentResult['httpStatus']);
            }
            
            $htmlContent = $contentResult['content'];
            $responseSize = strlen($htmlContent);
            $debug[] = "HTML erfolgreich geladen: " . number_format($responseSize) . " Zeichen";
            
            // SCHRITT 3: Link-Analyse
            $debug[] = "\n--- SCHRITT 3: LINK-ANALYSE ---";
            $linkResult = $this->analyzeLinks($htmlContent, $targetUrl, $anchorText, $debug);
            
            // Circuit-Breaker-Success registrieren
            $this->recordCircuitBreakerSuccess($domain);
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $debug[] = "\n=== PRÜFUNG ABGESCHLOSSEN ===";
            $debug[] = "Verarbeitungszeit: {$responseTime}ms";
            $debug[] = "Response-Größe: " . number_format($responseSize) . " Zeichen";
            $debug[] = "Endergebnis: " . ($linkResult['containsTargetLink'] ? "LINK GEFUNDEN" : "LINK NICHT GEFUNDEN");
            
            return [
                'isValid' => $statusResult['isValid'],
                'containsTargetLink' => $linkResult['containsTargetLink'],
                'httpStatus' => $statusResult['httpStatus'],
                'error' => null,
                'debug' => $debug,
                'foundLinks' => $linkResult['foundLinks'],
                'responseTime' => $responseTime,
                'responseSize' => $responseSize,
                'searchedFor' => [
                    'targetUrl' => $targetUrl,
                    'anchorText' => $anchorText,
                    'normalizedUrl' => $linkResult['normalizedTargetUrl'],
                    'normalizedText' => $linkResult['normalizedAnchorText'],
                    'urlVariants' => $linkResult['urlVariants'],
                    'anchorVariants' => $linkResult['anchorVariants']
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
            $debug[] = "AUSNAHME: " . $e->getMessage();
            return $this->createErrorResponse('Unerwarteter Fehler: ' . $e->getMessage(), $debug, 500);
        }
    }
    
    /**
     * HTTP-Status prüfen mit erweiterten Headern (SCHNELLE VERSION)
     */
    private function checkHttpStatus($url, &$debug) {
        $debug[] = "Prüfe HTTP-Status für: $url";
        
        $userAgent = $this->getRandomUserAgent();
        $debug[] = "User-Agent: $userAgent";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'header' => $this->getBrowserHeaders($userAgent),
                'timeout' => 5,  // Reduziert: 15s → 5s
                'ignore_errors' => true
            ]
        ]);
        
        $headers = @get_headers($url, 1, $context);
        
        if ($headers === false) {
            $debug[] = "FEHLER: Konnte keine HTTP-Headers abrufen";
            return ['isValid' => false, 'httpStatus' => 0, 'error' => 'URL nicht erreichbar'];
        }
        
        $statusCode = $this->extractStatusCode($headers);
        $debug[] = "HTTP-Status: $statusCode";
        
        if ($statusCode >= 200 && $statusCode < 400) {
            $debug[] = "✅ HTTP-Status OK";
            return ['isValid' => true, 'httpStatus' => $statusCode];
        } else {
            $debug[] = "❌ HTTP-Status nicht OK: $statusCode";
            return ['isValid' => false, 'httpStatus' => $statusCode, 'error' => "HTTP-Status: $statusCode"];
        }
    }
    
    /**
     * HTML-Content laden mit Fallback-Strategien (SCHNELLE VERSION)
     */
    private function loadHtmlContent($url, &$debug) {
        // Nur 2 Strategien statt 4 für Geschwindigkeit
        $strategies = [
            'browser' => 'Standard Browser Headers',
            'googlebot' => 'Googlebot User-Agent'
        ];
        
        foreach ($strategies as $strategy => $description) {
            $debug[] = "Versuche Strategie: $description";
            
            $context = $this->createContextForStrategy($strategy);
            $content = @file_get_contents($url, false, $context);
            
            if ($content !== false && strlen($content) > 100) {
                $debug[] = "✅ Strategie '$description' erfolgreich";
                $debug[] = "Content-Länge: " . number_format(strlen($content)) . " Zeichen";
                
                // Vereinfachte Content-Validierung für Geschwindigkeit
                if (strlen($content) > 100) {
                    return ['success' => true, 'content' => $content, 'strategy' => $strategy];
                }
            } else {
                $debug[] = "❌ Strategie '$description' fehlgeschlagen";
            }
            
            // KEINE Pause zwischen Versuchen für Geschwindigkeit
            // usleep(500000); // ENTFERNT
        }
        
        return ['success' => false, 'error' => 'Konnte HTML-Content nicht laden', 'httpStatus' => 0];
    }
    
    /**
     * Stream-Context für verschiedene Strategien erstellen (SCHNELLE VERSION)
     */
    private function createContextForStrategy($strategy) {
        $baseOptions = [
            'http' => [
                'method' => 'GET',
                'timeout' => 8,  // Reduziert: 20s → 8s
                'follow_location' => true,
                'max_redirects' => 3,  // Reduziert: 5 → 3
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
     * Link-Analyse mit erweiterten Matching-Algorithmen
     */
    private function analyzeLinks($htmlContent, $targetUrl, $anchorText, &$debug) {
        // URL-Normalisierung
        $normalizeUrl = function($url) {
            $url = trim($url);
            $url = strtok($url, '#'); // Fragment entfernen
            $url = rtrim($url, '/');
            return strtolower($url);
        };
        
        $normalizedTargetUrl = $normalizeUrl($targetUrl);
        $normalizedAnchorText = trim(strtolower($anchorText));
        
        $debug[] = "Normalisierte Ziel-URL: $normalizedTargetUrl";
        $debug[] = "Normalisierter Ankertext: '$normalizedAnchorText'";
        
        // URL-Varianten generieren
        $urlVariants = $this->generateUrlVariants($targetUrl, $normalizedTargetUrl);
        $anchorVariants = $this->generateAnchorVariants($anchorText, $normalizedAnchorText);
        
        $debug[] = "URL-Varianten (" . count($urlVariants) . "): " . implode(', ', array_slice($urlVariants, 0, 3)) . '...';
        $debug[] = "Ankertext-Varianten (" . count($anchorVariants) . "): " . implode(', ', array_slice($anchorVariants, 0, 3)) . '...';
        
        // Links extrahieren
        $foundLinks = $this->extractLinks($htmlContent, $debug);
        $debug[] = "Gefundene Links: " . count($foundLinks);
        
        // Link-Matching
        $matchResults = $this->matchLinks($foundLinks, $urlVariants, $anchorVariants, $debug);
        
        return [
            'containsTargetLink' => $matchResults['containsTargetLink'],
            'foundLinks' => array_slice($foundLinks, 0, 20), // Limitiere auf 20 für Performance
            'normalizedTargetUrl' => $normalizedTargetUrl,
            'normalizedAnchorText' => $normalizedAnchorText,
            'urlVariants' => $urlVariants,
            'anchorVariants' => $anchorVariants,
            'urlMatches' => $matchResults['urlMatches'],
            'textMatches' => $matchResults['textMatches'],
            'perfectMatches' => $matchResults['perfectMatches']
        ];
    }
    
    /**
     * Links aus HTML extrahieren
     */
    private function extractLinks($htmlContent, &$debug) {
        $patterns = [
            '/<a\s[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',
            '/<a\s[^>]*href\s*=\s*([^\s>]+)[^>]*>(.*?)<\/a>/is'
        ];
        
        $allMatches = [];
        foreach ($patterns as $i => $pattern) {
            $matches = [];
            if (preg_match_all($pattern, $htmlContent, $matches, PREG_SET_ORDER)) {
                $debug[] = "Pattern " . ($i + 1) . ": " . count($matches) . " Matches";
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
     * URL-Varianten generieren (SCHNELLE VERSION)
     */
    private function generateUrlVariants($originalUrl, $normalizedUrl) {
        $variants = [$normalizedUrl];
        
        // Nur die wichtigsten Varianten für Geschwindigkeit
        $variants[] = $normalizedUrl . '/';
        $variants[] = rtrim($normalizedUrl, '/');
        
        // HTTP/HTTPS Varianten
        $variants[] = str_replace('https://', 'http://', $normalizedUrl);
        $variants[] = str_replace('http://', 'https://', $normalizedUrl);
        
        // WWW Varianten (nur die häufigsten)
        $variants[] = str_replace('://', '://www.', $normalizedUrl);
        $variants[] = str_replace('://www.', '://', $normalizedUrl);
        
        return array_unique($variants);
    }
    
    /**
     * Ankertext-Varianten generieren (SCHNELLE VERSION)
     */
    private function generateAnchorVariants($originalAnchor, $normalizedAnchor) {
        $variants = [$normalizedAnchor];
        
        // Nur die wichtigsten Varianten
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
    private function matchLinks($foundLinks, $urlVariants, $anchorVariants, &$debug) {
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
        
        $debug[] = "Matching-Ergebnisse: URL-Matches=$urlMatches, Text-Matches=$textMatches, Perfekte-Matches=$perfectMatches";
        
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
            'Accept-Encoding: identity', // Keine Kompression für einfacheres Debugging
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
     * HTML-Content validieren (SCHNELLE VERSION - vereinfacht)
     */
    private function isValidHtmlContent($content, &$debug) {
        // Vereinfachte Validierung für Geschwindigkeit
        
        // Zu kurzer Content
        if (strlen($content) < 100) {
            $debug[] = "⚠️ Content zu kurz (< 100 Zeichen)";
            return false;
        }
        
        // Schnelle HTML-Check (nur die wichtigsten Tags)
        if (!preg_match('/<(html|body|div|p|a)/i', $content)) {
            $debug[] = "⚠️ Content enthält keine HTML-Tags";
            return false;
        }
        
        return true;
    }
    
    /**
     * Rate-Limiting prüfen
     */
    private function checkRateLimit($domain) {
        $key = "rate_limit_$domain";
        $now = time();
        $windowSize = 60; // 1 Minute
        $maxRequests = 10; // 10 Requests pro Minute
        
        if (!isset($this->rateLimitStorage[$key])) {
            $this->rateLimitStorage[$key] = [];
        }
        
        // Alte Einträge entfernen
        $this->rateLimitStorage[$key] = array_filter(
            $this->rateLimitStorage[$key],
            function($timestamp) use ($now, $windowSize) {
                return $timestamp > ($now - $windowSize);
            }
        );
        
        // Prüfen ob Limit erreicht
        if (count($this->rateLimitStorage[$key]) >= $maxRequests) {
            return false;
        }
        
        // Request registrieren
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
                'state' => 'closed' // closed, open, half-open
            ];
        }
        
        $cb = &$this->circuitBreakerStorage[$key];
        
        // Circuit-Breaker-Logik
        if ($cb['state'] === 'open') {
            $timeout = 300; // 5 Minuten
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
    private function createErrorResponse($error, $debug, $httpStatus) {
        return [
            'isValid' => false,
            'containsTargetLink' => false,
            'httpStatus' => $httpStatus,
            'error' => $error,
            'debug' => $debug,
            'foundLinks' => [],
            'responseTime' => 0,
            'responseSize' => 0
        ];
    }
}

try {
    // Basis-Variablen - Action kann aus GET oder POST kommen
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

    // =============================================================================
    // POST-Verarbeitung
    // =============================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create') {
            $customerId = $_POST['customer_id'] ?? '';
            $blogId = $_POST['blog_id'] ?? '';
            $backlinkUrl = trim($_POST['backlink_url'] ?? '');
            $anchorText = trim($_POST['anchor_text'] ?? '');
            $targetUrl = trim($_POST['target_url'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
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
            
            if (empty($errors)) {
                $links = loadData('links.json');
                $newId = generateId();
                
                $links[$newId] = [
                    'id' => $newId,
                    'user_id' => $userId,
                    'customer_id' => $customerId,
                    'blog_id' => $blogId,
                    'backlink_url' => $backlinkUrl,
                    'anchor_text' => $anchorText,
                    'target_url' => $targetUrl,
                    'description' => $description,
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
            // Link aktualisieren
            $customerId = $_POST['customer_id'] ?? '';
            $blogId = $_POST['blog_id'] ?? '';
            $backlinkUrl = trim($_POST['backlink_url'] ?? '');
            $anchorText = trim($_POST['anchor_text'] ?? '');
            $targetUrl = trim($_POST['target_url'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
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
            
            if (empty($errors)) {
                $links = loadData('links.json');
                if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
                    // Link aktualisieren (ID und Erstellungszeit beibehalten)
                    $links[$linkId]['customer_id'] = $customerId;
                    $links[$linkId]['blog_id'] = $blogId;
                    $links[$linkId]['backlink_url'] = $backlinkUrl;
                    $links[$linkId]['anchor_text'] = $anchorText;
                    $links[$linkId]['target_url'] = $targetUrl;
                    $links[$linkId]['description'] = $description;
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
            // Link löschen
            $links = loadData('links.json');
            if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
                $link = $links[$linkId];
                $anchorText = $link['anchor_text'] ?? 'Unbekannter Link';
                
                // Link aus Array entfernen
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
            // Einzelnen Link prüfen - GLEICHE LOGIK WIE DEBUG-ANALYSE
            $links = loadData('links.json');
            if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
                $link = $links[$linkId];
                
                // Exakt gleiche Prüfung wie in der Debug-Analyse
                $result = $backlinkChecker->checkBacklinkAdvanced(
                    $link['backlink_url'],
                    $link['target_url'],
                    $link['anchor_text']
                );
                
                // Status-Bestimmung - EXAKT WIE IN DEBUG-ANALYSE
                $newStatus = 'defekt'; // Standard: defekt
                
                // Wenn URL erreichbar UND Link gefunden = AKTIV
                if ($result['isValid'] && $result['containsTargetLink']) {
                    $newStatus = 'aktiv';
                } 
                // Wenn URL erreichbar aber Link NICHT gefunden = AUSSTEHEND  
                elseif ($result['isValid'] && !$result['containsTargetLink']) {
                    $newStatus = 'ausstehend';
                }
                // Wenn URL NICHT erreichbar = DEFEKT (bereits als Standard gesetzt)
                
                // Debug-Informationen für Transparenz
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
                
                // Link-Daten aktualisieren
                $links[$linkId]['status'] = $newStatus;
                $links[$linkId]['last_checked'] = date('Y-m-d H:i:s');
                $links[$linkId]['check_result'] = $result;
                $links[$linkId]['debug_info'] = $debugInfo;
                
                if (saveData('links.json', $links)) {
                    // Detaillierte Erfolgsmeldung mit Debug-Infos
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

    // =============================================================================
    // DEBUG-ACTION: Vollständige Debug-Analyse
    // =============================================================================
    if ($action === 'debug' && $linkId) {
        $links = loadData('links.json');
        if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
            $link = $links[$linkId];
            
            // Robuste Debug-Analyse durchführen
            $result = $backlinkChecker->checkBacklinkAdvanced(
                $link['backlink_url'],
                $link['target_url'],
                $link['anchor_text']
            );
            
            // Vollständige Debug-Seite ausgeben
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
                    .debug-step { margin: 20px 0; padding: 16px; background: #2a2d42; border-radius: 8px; border-left: 4px solid #4dabf7; }
                    .success { color: #10b981; }
                    .error { color: #ef4444; }
                    .warning { color: #f59e0b; }
                    .info { color: #4dabf7; }
                    .metric { display: inline-block; margin: 8px 16px 8px 0; padding: 8px 12px; background: #3a3d52; border-radius: 6px; font-size: 14px; }
                    .link-item { margin: 8px 0; padding: 12px; background: #2a2d42; border-radius: 6px; border-left: 4px solid #6b7280; font-size: 13px; }
                    .link-item.perfect { border-left-color: #10b981; }
                    .link-item.partial { border-left-color: #f59e0b; }
                    pre { background: #1a1d2e; padding: 16px; border-radius: 8px; overflow-x: auto; font-size: 12px; font-family: 'Monaco', 'Menlo', monospace; }
                    .btn { display: inline-block; padding: 12px 24px; background: #4dabf7; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s; }
                    .btn:hover { background: #3b9ae1; transform: translateY(-1px); }
                    .header { text-align: center; margin-bottom: 30px; }
                    .status-badge { padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 12px; }
                    .status-success { background: #10b981; color: white; }
                    .status-warning { background: #f59e0b; color: white; }
                    .status-error { background: #ef4444; color: white; }
                </style>
            </head>
            <body>
                <div class="debug-container">
                    <div class="header">
                        <h1 style="color: #4dabf7; margin-bottom: 8px;">🔍 Erweiterte Link-Debug-Analyse</h1>
                        <p style="color: #8b8fa3; margin: 0;">
                            Vollständige Analyse mit modernen Anti-Bot-Detection Techniken
                        </p>
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
                            <div class="metric">
                                <strong>Response-Größe:</strong> 
                                <span class="info"><?= number_format($result['responseSize'] ?? 0) ?> Zeichen</span>
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
                    
                    <?php if (!empty($result['foundLinks'])): ?>
                    <div class="debug-section">
                        <h2 style="color: #e2e8f0; margin-bottom: 16px;">🔗 Gefundene Links (Top 10)</h2>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach (array_slice($result['foundLinks'], 0, 10) as $i => $foundLink): ?>
                                <div class="link-item <?= $foundLink['perfectMatch'] ? 'perfect' : ($foundLink['hrefMatch'] || $foundLink['textMatch'] ? 'partial' : '') ?>">
                                    <div style="font-weight: 600; margin-bottom: 4px;">
                                        Link #<?= $i + 1 ?>
                                        <?php if ($foundLink['perfectMatch']): ?>
                                            <span class="success">🎯 PERFEKTER MATCH!</span>
                                        <?php elseif ($foundLink['hrefMatch']): ?>
                                            <span class="info">🔗 URL-Match</span>
                                        <?php elseif ($foundLink['textMatch']): ?>
                                            <span class="info">📝 Text-Match</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-bottom: 2px;">
                                        <strong>URL:</strong> <?= htmlspecialchars(strlen($foundLink['href']) > 80 ? substr($foundLink['href'], 0, 80) . '...' : $foundLink['href']) ?>
                                        <?= $foundLink['hrefMatch'] ? '<span class="success">✅</span>' : '<span class="error">❌</span>' ?>
                                    </div>
                                    <div>
                                        <strong>Text:</strong> "<?= htmlspecialchars(strlen($foundLink['text']) > 80 ? substr($foundLink['text'], 0, 80) . '...' : $foundLink['text']) ?>"
                                        <?= $foundLink['textMatch'] ? '<span class="success">✅</span>' : '<span class="error">❌</span>' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($result['debug'])): ?>
                    <div class="debug-section">
                        <h2 style="color: #e2e8f0; margin-bottom: 16px;">🐛 Debug-Log</h2>
                        <details>
                            <summary style="cursor: pointer; color: #4dabf7; font-weight: 600;">Debug-Informationen anzeigen</summary>
                            <pre style="margin-top: 16px;"><?= htmlspecialchars(implode("\n", $result['debug'])) ?></pre>
                        </details>
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

    // =============================================================================
    // DATEN LADEN
    // =============================================================================
    $links = loadData('links.json');
    $customers = loadData('customers.json');
    $blogs = loadData('blogs.json');

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
    if (is_array($userLinks)) {
        foreach ($userLinks as $link) {
            $status = $link['status'] ?? 'ausstehend';
            $statusStats[$status] = ($statusStats[$status] ?? 0) + 1;
        }
    }

    // =============================================================================
    // VIEW LOGIC STARTS HERE
    // =============================================================================

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
                                    <th>Kunde</th>
                                    <th>Blog</th>
                                    <th>Status</th>
                                    <th>Letzte Prüfung</th>
                                    <th>Erstellt</th>
                                    <th style="width: 180px;">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userLinks as $linkId => $link): 
                                    $customer = $customers[$link['customer_id']] ?? null;
                                    $blog = $blogs[$link['blog_id']] ?? null;
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
                                                <a href="?page=customers&action=view&id=<?= $link['customer_id'] ?>" style="color: #4dabf7; text-decoration: none;">
                                                    <?= htmlspecialchars($customer['name']) ?>
                                                </a>
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
                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($linkId) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div>
                            <label class="form-label">Kunde *</label>
                            <select name="customer_id" class="form-control" required>
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
                <form method="post">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div>
                            <label class="form-label">Kunde *</label>
                            <select name="customer_id" class="form-control" required>
                                <option value="">Kunde auswählen</option>
                                <?php foreach ($customers as $customerId => $customer): ?>
                                    <?php if ($isAdmin || $customer['user_id'] === $userId): ?>
                                        <option value="<?= htmlspecialchars($customerId) ?>">
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
                                        <option value="<?= htmlspecialchars($blogId) ?>">
                                            <?= htmlspecialchars($blog['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label">Ankertext *</label>
                        <input type="text" name="anchor_text" class="form-control" placeholder="Der sichtbare Text des Links" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div>
                            <label class="form-label">Ziel-URL *</label>
                            <input type="url" name="target_url" class="form-control" placeholder="https://example.com/seite" required>
                        </div>
                        <div>
                            <label class="form-label">Backlink-URL *</label>
                            <input type="url" name="backlink_url" class="form-control" placeholder="https://blog.example.com/artikel" required>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label">Beschreibung</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Zusätzliche Notizen zu diesem Link"></textarea>
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

    /* Progress Bar Styles */
    .progress-container {
        display: none;
        width: 100%;
        background-color: #343852;
        border-radius: 4px;
        overflow: hidden;
        margin: 8px 0;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .progress-bar {
        height: 24px;
        background: linear-gradient(90deg, #4dabf7, #06b6d4);
        width: 0%;
        transition: width 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 11px;
        font-weight: 600;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    }

    .progress-text {
        font-size: 12px;
        color: #8b8fa3;
        text-align: center;
        margin-top: 4px;
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
    }
    </style>

    <script>
    // Progress Bar für Link-Prüfung
    document.addEventListener('DOMContentLoaded', function() {
        // Handler für kleine Buttons in der Tabelle
        document.querySelectorAll('.link-check-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const linkId = this.dataset.linkId;
                const form = this.closest('form');
                startLinkCheck(form, this, 'small');
            });
        });

        // Handler für große Buttons in der Detailansicht
        document.querySelectorAll('.link-check-btn-detail').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const linkId = this.dataset.linkId;
                const form = this.closest('form');
                startLinkCheck(form, this, 'detail');
            });
        });

        function startLinkCheck(form, button, type) {
            // Button deaktivieren und Spinner zeigen
            button.classList.add('checking');
            button.disabled = true;

            let progressContainer = null;
            let progressBar = null;
            let progressText = null;

            // Progress Bar nur für Detail-Ansicht
            if (type === 'detail') {
                // Progress Container erstellen
                progressContainer = document.createElement('div');
                progressContainer.className = 'progress-container';
                progressContainer.innerHTML = `
                    <div class="progress-bar">0%</div>
                    <div class="progress-text">Prüfung wird vorbereitet...</div>
                `;
                
                // Progress Container nach dem Button einfügen
                button.parentNode.insertBefore(progressContainer, button.nextSibling);
                progressContainer.style.display = 'block';
                
                progressBar = progressContainer.querySelector('.progress-bar');
                progressText = progressContainer.querySelector('.progress-text');
            }

            // Simuliere Progress Steps
            const steps = [
                { progress: 15, text: '📡 Verbinde zu Server...', delay: 300 },
                { progress: 30, text: '🔍 Prüfe HTTP-Status...', delay: 800 },
                { progress: 55, text: '📄 Lade HTML-Content...', delay: 1500 },
                { progress: 75, text: '🔗 Analysiere Links...', delay: 1000 },
                { progress: 90, text: '✅ Verarbeite Ergebnisse...', delay: 500 },
                { progress: 100, text: '🎯 Prüfung abgeschlossen!', delay: 300 }
            ];

            let currentStep = 0;
            
            function updateProgress() {
                if (currentStep < steps.length && progressBar && progressText) {
                    const step = steps[currentStep];
                    progressBar.style.width = step.progress + '%';
                    progressBar.textContent = step.progress + '%';
                    progressText.textContent = step.text;
                    currentStep++;
                    
                    setTimeout(updateProgress, step.delay);
                } else if (currentStep >= steps.length) {
                    // Nach kurzer Pause Form absenden
                    setTimeout(() => {
                        form.submit();
                    }, 500);
                }
            }

            // Progress starten
            if (type === 'detail') {
                setTimeout(updateProgress, 100);
            } else {
                // Für kleine Buttons: Nach kurzer Simulation direkt absenden
                setTimeout(() => {
                    form.submit();
                }, 1000);
            }
        }
    });
    </script>

<?php

} catch (Exception $e) {
    // Output Buffer leeren bei Fehlern
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Fehlerbehandlung
    echo '<div class="alert alert-danger">';
    echo '<h3>Ein Fehler ist aufgetreten:</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>Datei:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Zeile:</strong> ' . $e->getLine() . '</p>';
    echo '</div>';
    echo '<a href="?page=dashboard" class="btn btn-primary">← Zurück zum Dashboard</a>';
}

// Output Buffer leeren und senden
if (ob_get_level()) {
    ob_end_flush();
}

?>