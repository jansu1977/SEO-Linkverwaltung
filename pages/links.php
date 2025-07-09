<?php
/**
 * LinkBuilder Pro - Link-Verwaltung (Kernfunktion)
 * pages/links.php - Vollständige Version mit Link-Validierung
 */

// Basis-Variablen
$action = $_GET['action'] ?? 'index';
$linkId = $_GET['id'] ?? null;

// Session sicherstellen und User-ID ermitteln
ensureSession();
$userId = getCurrentUserId();

// Benutzer-Daten laden
$users = loadData('users.json');
$currentUser = $users[$userId] ?? null;

// Admin-Status prüfen
$isAdmin = $currentUser && ($currentUser['role'] ?? 'user') === 'admin';

// URL-Validierung und Backlink-Checker Funktionen
function validateLinkUrl($url) {
    if (empty($url)) return false;
    
    // URL-Format prüfen
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    } elseif ($action === 'debug' && $linkId) {
        // DEBUG-SEITE: Direkter Test mit sofortiger Anzeige
        $links = loadData('links.json');
        if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
            $link = $links[$linkId];
            
            // Zeige Debug-Seite
            ?>
            <!DOCTYPE html>
            <html lang="de">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Link-Debug: <?= e($link['anchor_text']) ?></title>
                <link rel="stylesheet" href="assets/style.css">
                <style>
                    .debug-section { background: #343852; padding: 20px; margin: 20px 0; border-radius: 8px; border: 1px solid #3a3d52; }
                    .debug-step { margin: 15px 0; padding: 10px; background: #2a2d42; border-radius: 4px; }
                    .success { color: #10b981; }
                    .error { color: #ef4444; }
                    .warning { color: #f59e0b; }
                    .info { color: #4dabf7; }
                    pre { background: #1a1d2e; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
                </style>
            </head>
            <body style="background: #1a1d2e; color: #e2e8f0; font-family: monospace; padding: 20px;">
                <div style="max-width: 1200px; margin: 0 auto;">
                    <h1 style="color: #4dabf7;">🔍 Link-Debug-Analyse</h1>
                    <p><a href="?page=links&action=view&id=<?= $linkId ?>" style="color: #4dabf7;">← Zurück zum Link</a></p>
                    
                    <div class="debug-section">
                        <h2>📋 Link-Informationen</h2>
                        <p><strong>Ankertext:</strong> <?= e($link['anchor_text']) ?></p>
                        <p><strong>Ziel-URL:</strong> <?= e($link['target_url']) ?></p>
                        <p><strong>Backlink-URL:</strong> <?= e($link['backlink_url']) ?></p>
                    </div>
                    
                    <?php
                    echo '<div class="debug-section">';
                    echo '<h2>🚀 Live-Test startet...</h2>';
                    echo '<div id="liveResults">';
                    
                    // SCHRITT 1: URL-Validierung
                    echo '<div class="debug-step">';
                    echo '<h3>SCHRITT 1: URL-Validierung</h3>';
                    
                    $backlinkUrl = $link['backlink_url'];
                    $targetUrl = $link['target_url'];
                    $anchorText = $link['anchor_text'];
                    
                    if (empty($backlinkUrl) || empty($targetUrl) || empty($anchorText)) {
                        echo '<p class="error">❌ Fehler: Fehlende Parameter</p>';
                        echo '<ul>';
                        if (empty($backlinkUrl)) echo '<li class="error">Backlink-URL fehlt</li>';
                        if (empty($targetUrl)) echo '<li class="error">Ziel-URL fehlt</li>';
                        if (empty($anchorText)) echo '<li class="error">Ankertext fehlt</li>';
                        echo '</ul>';
                    } else {
                        echo '<p class="success">✅ Alle Parameter vorhanden</p>';
                        echo '<ul>';
                        echo '<li class="info">Backlink-URL: ' . e($backlinkUrl) . '</li>';
                        echo '<li class="info">Ziel-URL: ' . e($targetUrl) . '</li>';
                        echo '<li class="info">Ankertext: "' . e($anchorText) . '"</li>';
                        echo '</ul>';
                    }
                    echo '</div>';
                    
                    if (!empty($backlinkUrl) && !empty($targetUrl) && !empty($anchorText)) {
                        // SCHRITT 2: HTTP-Test
                        echo '<div class="debug-step">';
                        echo '<h3>SCHRITT 2: HTTP-Erreichbarkeit</h3>';
                        
                        $context = stream_context_create([
                            'http' => [
                                'method' => 'HEAD',
                                'header' => [
                                    'User-Agent: Mozilla/5.0 (compatible; LinkBuilder-Debug/1.0)',
                                    'Accept: text/html,application/xhtml+xml',
                                ],
                                'timeout' => 10,
                                'ignore_errors' => true
                            ]
                        ]);
                        
                        $headers = @get_headers($backlinkUrl, 1, $context);
                        
                        if ($headers === false) {
                            echo '<p class="error">❌ Fehler: Konnte keine Verbindung zur URL herstellen</p>';
                            echo '<p class="warning">Mögliche Gründe: Timeout, DNS-Fehler, Server nicht erreichbar</p>';
                        } else {
                            echo '<p class="success">✅ Verbindung erfolgreich</p>';
                            
                            $statusCode = 0;
                            if (isset($headers[0])) {
                                preg_match('/HTTP\/[\d\.]+\s+(\d+)/', $headers[0], $matches);
                                $statusCode = isset($matches[1]) ? (int)$matches[1] : 0;
                            }
                            
                            if ($statusCode >= 200 && $statusCode < 300) {
                                echo '<p class="success">✅ HTTP-Status: ' . $statusCode . ' (OK)</p>';
                            } elseif ($statusCode >= 300 && $statusCode < 400) {
                                echo '<p class="warning">⚠️ HTTP-Status: ' . $statusCode . ' (Redirect)</p>';
                            } else {
                                echo '<p class="error">❌ HTTP-Status: ' . $statusCode . ' (Fehler)</p>';
                            }
                            
                            echo '<details style="margin-top: 10px;"><summary>HTTP-Headers anzeigen</summary>';
                            echo '<pre>' . print_r($headers, true) . '</pre>';
                            echo '</details>';
                        }
                        echo '</div>';
                        
                        // SCHRITT 3: HTML-Content laden
                        if ($headers !== false && $statusCode >= 200 && $statusCode < 400) {
                            echo '<div class="debug-step">';
                            echo '<h3>SCHRITT 3: HTML-Content laden</h3>';
                            
                            $htmlContent = @file_get_contents($backlinkUrl, false, stream_context_create([
                                'http' => [
                                    'method' => 'GET',
                                    'header' => [
                                        'User-Agent: Mozilla/5.0 (compatible; LinkBuilder-Debug/1.0)',
                                        'Accept: text/html,application/xhtml+xml',
                                    ],
                                    'timeout' => 15
                                ]
                            ]));
                            
                            if ($htmlContent === false) {
                                echo '<p class="error">❌ Konnte HTML-Inhalt nicht laden</p>';
                            } else {
                                $contentLength = strlen($htmlContent);
                                echo '<p class="success">✅ HTML geladen: ' . number_format($contentLength) . ' Zeichen</p>';
                                
                                // SCHRITT 4: Link-Suche
                                echo '</div><div class="debug-step">';
                                echo '<h3>SCHRITT 4: Link-Suche</h3>';
                                
                                // Einfache Suche nach <a> Tags
                                $pattern = '/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is';
                                $matches = [];
                                $matchCount = preg_match_all($pattern, $htmlContent, $matches, PREG_SET_ORDER);
                                
                                echo '<p class="info">🔍 Gefundene &lt;a&gt; Tags: ' . $matchCount . '</p>';
                                
                                if ($matchCount > 0) {
                                    $foundTargetLink = false;
                                    $linkResults = [];
                                    
                                    foreach ($matches as $i => $match) {
                                        $href = trim($match[1]);
                                        $text = trim(strip_tags($match[2]));
                                        
                                        // URL-Normalisierung
                                        $normalizedHref = strtolower(rtrim($href, '/'));
                                        $normalizedTarget = strtolower(rtrim($targetUrl, '/'));
                                        $normalizedText = strtolower($text);
                                        $normalizedAnchor = strtolower($anchorText);
                                        
                                        // Prüfung
                                        $hrefMatch = ($normalizedHref === $normalizedTarget) || 
                                                    (strpos($normalizedHref, $normalizedTarget) !== false) ||
                                                    (strpos($normalizedTarget, $normalizedHref) !== false);
                                        
                                        $textMatch = ($normalizedText === $normalizedAnchor) || 
                                                    (strpos($normalizedText, $normalizedAnchor) !== false) ||
                                                    (strpos($normalizedAnchor, $normalizedText) !== false);
                                        
                                        $linkResults[] = [
                                            'href' => $href,
                                            'text' => $text,
                                            'hrefMatch' => $hrefMatch,
                                            'textMatch' => $textMatch,
                                            'perfectMatch' => $hrefMatch && $textMatch
                                        ];
                                        
                                        if ($hrefMatch && $textMatch) {
                                            $foundTargetLink = true;
                                        }
                                    }
                                    
                                    // Ergebnis anzeigen
                                    if ($foundTargetLink) {
                                        echo '<p class="success">🎯 ✅ LINK GEFUNDEN! Der gesuchte Link existiert.</p>';
                                    } else {
                                        echo '<p class="warning">⚠️ Gesuchter Link nicht gefunden.</p>';
                                    }
                                    
                                    // Details zu den ersten 10 Links
                                    echo '<h4>🔗 Link-Analyse (erste 10 Links):</h4>';
                                    echo '<div style="max-height: 300px; overflow-y: auto;">';
                                    foreach (array_slice($linkResults, 0, 10) as $i => $result) {
                                        $status = $result['perfectMatch'] ? 'success' : ($result['hrefMatch'] || $result['textMatch'] ? 'warning' : 'info');
                                        echo '<div style="margin: 10px 0; padding: 8px; background: #2a2d42; border-left: 3px solid ' . 
                                             ($result['perfectMatch'] ? '#10b981' : ($result['hrefMatch'] || $result['textMatch'] ? '#f59e0b' : '#4dabf7')) . ';">';
                                        echo '<strong>Link #' . ($i+1) . ':</strong><br>';
                                        echo 'URL: ' . e($result['href']) . ' ' . ($result['hrefMatch'] ? '✅' : '❌') . '<br>';
                                        echo 'Text: "' . e($result['text']) . '" ' . ($result['textMatch'] ? '✅' : '❌') . '<br>';
                                        if ($result['perfectMatch']) {
                                            echo '<span class="success">🎯 PERFEKTER MATCH!</span>';
                                        }
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                    
                                    // Zusammenfassung
                                    echo '<div style="margin-top: 20px; padding: 15px; background: #2a2d42; border-radius: 6px;">';
                                    echo '<h4>📊 Zusammenfassung:</h4>';
                                    echo '<ul>';
                                    echo '<li>Gefundene Links: ' . count($linkResults) . '</li>';
                                    echo '<li>URL-Matches: ' . count(array_filter($linkResults, fn($l) => $l['hrefMatch'])) . '</li>';
                                    echo '<li>Text-Matches: ' . count(array_filter($linkResults, fn($l) => $l['textMatch'])) . '</li>';
                                    echo '<li>Perfekte Matches: ' . count(array_filter($linkResults, fn($l) => $l['perfectMatch'])) . '</li>';
                                    echo '</ul>';
                                    
                                    if ($foundTargetLink) {
                                        echo '<p class="success"><strong>🎯 ERGEBNIS: LINK AKTIV</strong></p>';
                                    } else {
                                        echo '<p class="warning"><strong>⚠️ ERGEBNIS: LINK NICHT GEFUNDEN (Status: AUSSTEHEND/DEFEKT)</strong></p>';
                                    }
                                    echo '</div>';
                                    
                                } else {
                                    echo '<p class="error">❌ Keine &lt;a&gt; Tags auf der Seite gefunden</p>';
                                }
                            }
                        }
                    }
                    
                    echo '</div></div>';
                    ?>
                    
                    <div style="margin: 30px 0; text-align: center;">
                        <a href="?page=links&action=view&id=<?= $linkId ?>" class="btn btn-primary" style="display: inline-block; padding: 12px 24px; background: #4dabf7; color: white; text-decoration: none; border-radius: 6px;">
                            ← Zurück zum Link
                        </a>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
    
    // Basis-Header setzen
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'header' => [
                'User-Agent: Mozilla/5.0 (compatible; LinkBuilder/1.0)',
                'Accept: text/html,application/xhtml+xml',
                'Connection: close',
                'Timeout: 10'
            ],
            'timeout' => 10
        ]
    ]);
    
    $headers = @get_headers($url, 1, $context);
    
    if ($headers === false) {
        return false;
    }
    
    // Status-Code prüfen
    $statusCode = 0;
    if (isset($headers[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers[0], $matches);
        $statusCode = isset($matches[1]) ? (int)$matches[1] : 0;
    }
    
    return $statusCode >= 200 && $statusCode < 400;
}

function checkBacklinkExists($backlinkUrl, $targetUrl, $anchorText, $userAgent = 'Googlebot/2.1') {
    if (empty($backlinkUrl) || empty($targetUrl) || empty($anchorText)) {
        return [
            'isValid' => false,
            'containsTargetLink' => false,
            'httpStatus' => 0,
            'error' => 'URLs oder Ankertext nicht angegeben',
            'debug' => [],
            'foundLinks' => []
        ];
    }
    
    $debug = [];
    $debug[] = "=== LINK-PRÜFUNG GESTARTET ===";
    $debug[] = "Backlink-URL: $backlinkUrl";
    $debug[] = "Ziel-URL: $targetUrl";
    $debug[] = "Ankertext: '$anchorText'";
    
    // HTTP-Context mit besseren Einstellungen
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                "User-Agent: $userAgent",
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
                'Accept-Encoding: gzip, deflate',
                'Connection: close',
                'Cache-Control: no-cache'
            ],
            'timeout' => 20,
            'follow_location' => true,
            'max_redirects' => 5,
            'ignore_errors' => true
        ]
    ]);
    
    // SCHRITT 1: HTTP-Status prüfen
    $debug[] = "\n--- SCHRITT 1: HTTP-STATUS PRÜFEN ---";
    $headers = @get_headers($backlinkUrl, 1, $context);
    $statusCode = 0;
    
    if ($headers !== false && isset($headers[0])) {
        preg_match('/HTTP\/[\d\.]+\s+(\d+)/', $headers[0], $matches);
        $statusCode = isset($matches[1]) ? (int)$matches[1] : 0;
        $debug[] = "HTTP-Status: $statusCode";
    } else {
        $debug[] = "FEHLER: Konnte HTTP-Headers nicht abrufen";
    }
    
    $isValid = $statusCode >= 200 && $statusCode < 400;
    
    if (!$isValid) {
        $debug[] = "ABBRUCH: HTTP-Status ungültig ($statusCode)";
        return [
            'isValid' => false,
            'containsTargetLink' => false,
            'httpStatus' => $statusCode,
            'error' => "HTTP Status: $statusCode",
            'debug' => $debug,
            'foundLinks' => []
        ];
    }
    
    // SCHRITT 2: HTML-Inhalt laden
    $debug[] = "\n--- SCHRITT 2: HTML-INHALT LADEN ---";
    $htmlContent = @file_get_contents($backlinkUrl, false, $context);
    
    if ($htmlContent === false) {
        $debug[] = "FEHLER: Konnte HTML-Inhalt nicht laden";
        return [
            'isValid' => false,
            'containsTargetLink' => false,
            'httpStatus' => $statusCode,
            'error' => 'HTML-Inhalt konnte nicht geladen werden',
            'debug' => $debug,
            'foundLinks' => []
        ];
    }
    
    $debug[] = "HTML geladen: " . strlen($htmlContent) . " Zeichen";
    
    // SCHRITT 3: HTML bereinigen und normalisieren
    $debug[] = "\n--- SCHRITT 3: HTML BEREINIGEN ---";
    // Entferne Kommentare und Scripts
    $htmlContent = preg_replace('/<!--.*?-->/s', '', $htmlContent);
    $htmlContent = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $htmlContent);
    $htmlContent = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $htmlContent);
    
    $debug[] = "HTML bereinigt: " . strlen($htmlContent) . " Zeichen";
    
    // SCHRITT 4: URL-Normalisierung
    $debug[] = "\n--- SCHRITT 4: URL-NORMALISIERUNG ---";
    
    $normalizeUrl = function($url) {
        $url = trim($url);
        // Entferne Anker/Fragment
        $url = strtok($url, '#');
        // Normalisiere Slashes
        $url = rtrim($url, '/');
        return strtolower($url);
    };
    
    $normalizedTargetUrl = $normalizeUrl($targetUrl);
    $debug[] = "Normalisierte Ziel-URL: $normalizedTargetUrl";
    
    // Erstelle URL-Varianten für besseres Matching
    $targetVariants = [
        $normalizedTargetUrl,
        $normalizedTargetUrl . '/',
        str_replace('https://', 'http://', $normalizedTargetUrl),
        str_replace('http://', 'https://', $normalizedTargetUrl)
    ];
    
    // Domain-basierte Varianten
    $parsedUrl = parse_url($targetUrl);
    if (isset($parsedUrl['host'])) {
        $domain = strtolower($parsedUrl['host']);
        $targetVariants[] = $domain;
        $targetVariants[] = 'www.' . $domain;
        $targetVariants[] = str_replace('www.', '', $domain);
    }
    
    $targetVariants = array_unique($targetVariants);
    $debug[] = "URL-Varianten: " . implode(', ', array_slice($targetVariants, 0, 5)) . (count($targetVariants) > 5 ? '...' : '');
    
    // SCHRITT 5: Ankertext normalisieren
    $debug[] = "\n--- SCHRITT 5: ANKERTEXT-NORMALISIERUNG ---";
    $normalizedAnchorText = trim(strtolower($anchorText));
    
    // Erstelle Ankertext-Varianten
    $anchorVariants = [
        $normalizedAnchorText,
        strip_tags($normalizedAnchorText),
        html_entity_decode($normalizedAnchorText, ENT_QUOTES, 'UTF-8')
    ];
    
    // Wenn Ankertext eine URL ist, auch Domain-Varianten
    if (filter_var($anchorText, FILTER_VALIDATE_URL)) {
        $anchorParsed = parse_url($anchorText);
        if (isset($anchorParsed['host'])) {
            $anchorDomain = strtolower($anchorParsed['host']);
            $anchorVariants[] = $anchorDomain;
            $anchorVariants[] = str_replace('www.', '', $anchorDomain);
        }
    }
    
    $anchorVariants = array_unique($anchorVariants);
    $debug[] = "Ankertext-Varianten: " . implode(', ', $anchorVariants);
    
    // SCHRITT 6: Link-Suche mit mehreren Strategien
    $debug[] = "\n--- SCHRITT 6: LINK-SUCHE ---";
    $foundLinks = [];
    $containsTargetLink = false;
    
    // STRATEGIE 1: Einfacher Regex für <a> Tags
    $debug[] = "Strategie 1: Einfacher <a>-Tag Regex";
    $simplePattern = '/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is';
    
    if (preg_match_all($simplePattern, $htmlContent, $matches, PREG_SET_ORDER)) {
        $debug[] = "Gefunden: " . count($matches) . " <a> Tags";
        
        foreach ($matches as $i => $match) {
            $href = trim($match[1]);
            $text = trim(strip_tags($match[2]));
            
            // Normalisiere gefundene Werte
            $normalizedHref = $normalizeUrl($href);
            $normalizedText = trim(strtolower($text));
            
            $foundLinks[] = [
                'href' => $href,
                'text' => $text,
                'normalized_href' => $normalizedHref,
                'normalized_text' => $normalizedText
            ];
            
            // Prüfe URL-Match
            $hrefMatches = false;
            foreach ($targetVariants as $variant) {
                if ($normalizedHref === $variant || 
                    strpos($normalizedHref, $variant) !== false ||
                    strpos($variant, $normalizedHref) !== false) {
                    $hrefMatches = true;
                    break;
                }
            }
            
            // Prüfe Text-Match
            $textMatches = false;
            foreach ($anchorVariants as $variant) {
                if ($normalizedText === $variant || 
                    strpos($normalizedText, $variant) !== false ||
                    strpos($variant, $normalizedText) !== false) {
                    $textMatches = true;
                    break;
                }
            }
            
            $debug[] = "Link #" . ($i+1) . ": href='$href' text='$text' | URL:" . ($hrefMatches ? "✓" : "✗") . " Text:" . ($textMatches ? "✓" : "✗");
            
            if ($hrefMatches && $textMatches) {
                $containsTargetLink = true;
                $debug[] = "*** PERFEKTER MATCH GEFUNDEN! ***";
                break;
            }
        }
    } else {
        $debug[] = "Keine <a> Tags mit einfachem Regex gefunden";
    }
    
    // STRATEGIE 2: Fallback - href und Text separat suchen
    if (!$containsTargetLink) {
        $debug[] = "\nStrategie 2: Fallback - separate Suche";
        
        $hrefFound = false;
        $textFound = false;
        
        // Suche nach href-Attributen
        foreach ($targetVariants as $variant) {
            if (stripos($htmlContent, 'href') !== false && stripos($htmlContent, $variant) !== false) {
                $hrefFound = true;
                $debug[] = "URL-Variante '$variant' im HTML gefunden";
                break;
            }
        }
        
        // Suche nach Ankertext
        foreach ($anchorVariants as $variant) {
            if (stripos($htmlContent, $variant) !== false) {
                $textFound = true;
                $debug[] = "Ankertext-Variante '$variant' im HTML gefunden";
                break;
            }
        }
        
        $debug[] = "Fallback-Ergebnis: href=" . ($hrefFound ? "✓" : "✗") . " text=" . ($textFound ? "✓" : "✗");
        
        // Nur als Backup - nicht als definitiver Match
        if ($hrefFound && $textFound) {
            $debug[] = "HINWEIS: Beide Elemente vorhanden, aber nicht als korrekter Link verknüpft";
        }
    }
    
    // STRATEGIE 3: DOMDocument falls verfügbar
    if (!$containsTargetLink && class_exists('DOMDocument')) {
        $debug[] = "\nStrategie 3: DOMDocument-Parser";
        
        $dom = new DOMDocument();
        // Fehler unterdrücken bei fehlerhaftem HTML
        libxml_use_internal_errors(true);
        
        if (@$dom->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            $links = $dom->getElementsByTagName('a');
            $debug[] = "DOMDocument: " . $links->length . " <a> Tags gefunden";
            
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);
                
                if (empty($href)) continue;
                
                $normalizedHref = $normalizeUrl($href);
                $normalizedText = trim(strtolower($text));
                
                // URL-Match prüfen
                $hrefMatches = false;
                foreach ($targetVariants as $variant) {
                    if ($normalizedHref === $variant || strpos($normalizedHref, $variant) !== false) {
                        $hrefMatches = true;
                        break;
                    }
                }
                
                // Text-Match prüfen
                $textMatches = false;
                foreach ($anchorVariants as $variant) {
                    if ($normalizedText === $variant || strpos($normalizedText, $variant) !== false) {
                        $textMatches = true;
                        break;
                    }
                }
                
                if ($hrefMatches && $textMatches) {
                    $containsTargetLink = true;
                    $debug[] = "*** DOMDocument MATCH: href='$href' text='$text' ***";
                    break;
                }
            }
        } else {
            $debug[] = "DOMDocument konnte HTML nicht parsen";
        }
        
        libxml_clear_errors();
    }
    
    $debug[] = "\n=== PRÜFUNG ABGESCHLOSSEN ===";
    $debug[] = "Endergebnis: " . ($containsTargetLink ? "LINK GEFUNDEN" : "LINK NICHT GEFUNDEN");
    
    return [
        'isValid' => $isValid,
        'containsTargetLink' => $containsTargetLink,
        'httpStatus' => $statusCode,
        'error' => null,
        'debug' => $debug,
        'foundLinks' => array_slice($foundLinks, 0, 10), // Limitiere auf erste 10
        'searchedFor' => [
            'targetUrl' => $targetUrl,
            'anchorText' => $anchorText,
            'normalizedUrl' => $normalizedTargetUrl,
            'normalizedText' => $normalizedAnchorText,
            'urlVariants' => $targetVariants,
            'anchorVariants' => $anchorVariants
        ]
    ];
}

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $customerId = $_POST['customer_id'] ?? '';
        $customerWebsiteUrl = $_POST['customer_website_url'] ?? '';
        $blogId = $_POST['blog_id'] ?? '';
        $backlinkUrl = trim($_POST['backlink_url'] ?? '');
        $anchorText = trim($_POST['anchor_text'] ?? '');
        $targetUrl = trim($_POST['target_url'] ?? '');
        $publishedDate = $_POST['published_date'] ?? date('Y-m-d');
        $expiryDate = $_POST['expiry_date'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'ausstehend';
        
        $errors = [];
        
        // Validierung
        if (empty($customerId)) {
            $errors[] = 'Kunde ist ein Pflichtfeld.';
        }
        if (empty($blogId)) {
            $errors[] = 'Blog ist ein Pflichtfeld.';
        }
        if (empty($backlinkUrl)) {
            $errors[] = 'Backlink-URL ist ein Pflichtfeld.';
        } elseif (!validateUrl($backlinkUrl)) {
            $errors[] = 'Ungültige Backlink-URL.';
        }
        if (empty($anchorText)) {
            $errors[] = 'Ankertext ist ein Pflichtfeld.';
        }
        if (empty($targetUrl)) {
            $errors[] = 'Ziel-URL ist ein Pflichtfeld.';
        } elseif (!validateUrl($targetUrl)) {
            $errors[] = 'Ungültige Ziel-URL.';
        }
        
        // Kunde und Blog existieren prüfen
        $customers = loadData('customers.json');
        $blogs = loadData('blogs.json');
        
        if (!isset($customers[$customerId])) {
            $errors[] = 'Gewählter Kunde existiert nicht.';
        }
        if (!isset($blogs[$blogId])) {
            $errors[] = 'Gewählter Blog existiert nicht.';
        }
        
        // Berechtigung prüfen
        if (!$isAdmin) {
            if (isset($customers[$customerId]) && $customers[$customerId]['user_id'] !== $userId) {
                $errors[] = 'Keine Berechtigung für diesen Kunden.';
            }
            if (isset($blogs[$blogId]) && $blogs[$blogId]['user_id'] !== $userId) {
                $errors[] = 'Keine Berechtigung für diesen Blog.';
            }
        }
        
        if (empty($errors)) {
            $links = loadData('links.json');
            $newId = generateId();
            
            $links[$newId] = [
                'id' => $newId,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'customer_website_url' => $customerWebsiteUrl,
                'blog_id' => $blogId,
                'backlink_url' => $backlinkUrl,
                'anchor_text' => $anchorText,
                'target_url' => $targetUrl,
                'published_date' => $publishedDate,
                'expiry_date' => $expiryDate,
                'description' => $description,
                'status' => $status,
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
    } elseif ($action === 'edit' && $linkId) {
        $links = loadData('links.json');
        if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
            $customerId = $_POST['customer_id'] ?? '';
            $customerWebsiteUrl = $_POST['customer_website_url'] ?? '';
            $blogId = $_POST['blog_id'] ?? '';
            $backlinkUrl = trim($_POST['backlink_url'] ?? '');
            $anchorText = trim($_POST['anchor_text'] ?? '');
            $targetUrl = trim($_POST['target_url'] ?? '');
            $publishedDate = $_POST['published_date'] ?? date('Y-m-d');
            $expiryDate = $_POST['expiry_date'] ?? '';
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'ausstehend';
            
            $errors = [];
            
            // Validierung (gleich wie bei create)
            if (empty($customerId)) {
                $errors[] = 'Kunde ist ein Pflichtfeld.';
            }
            if (empty($blogId)) {
                $errors[] = 'Blog ist ein Pflichtfeld.';
            }
            if (empty($backlinkUrl)) {
                $errors[] = 'Backlink-URL ist ein Pflichtfeld.';
            } elseif (!validateUrl($backlinkUrl)) {
                $errors[] = 'Ungültige Backlink-URL.';
            }
            if (empty($anchorText)) {
                $errors[] = 'Ankertext ist ein Pflichtfeld.';
            }
            if (empty($targetUrl)) {
                $errors[] = 'Ziel-URL ist ein Pflichtfeld.';
            } elseif (!validateUrl($targetUrl)) {
                $errors[] = 'Ungültige Ziel-URL.';
            }
            
            if (empty($errors)) {
                $links[$linkId] = array_merge($links[$linkId], [
                    'customer_id' => $customerId,
                    'customer_website_url' => $customerWebsiteUrl,
                    'blog_id' => $blogId,
                    'backlink_url' => $backlinkUrl,
                    'anchor_text' => $anchorText,
                    'target_url' => $targetUrl,
                    'published_date' => $publishedDate,
                    'expiry_date' => $expiryDate,
                    'description' => $description,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                if (saveData('links.json', $links)) {
                    redirectWithMessage("?page=links&action=view&id=$linkId", 'Link "' . $anchorText . '" erfolgreich aktualisiert.');
                } else {
                    $errors[] = 'Fehler beim Aktualisieren des Links.';
                }
            }
        } else {
            $errors[] = 'Link nicht gefunden oder keine Berechtigung.';
        }
    } elseif ($action === 'delete' && $linkId) {
        $links = loadData('links.json');
        if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
            $linkAnchor = $links[$linkId]['anchor_text'];
            unset($links[$linkId]);
            
            if (saveData('links.json', $links)) {
                redirectWithMessage('?page=links', 'Link "' . $linkAnchor . '" erfolgreich gelöscht.');
            } else {
                setFlashMessage('Fehler beim Löschen des Links.', 'error');
            }
        } else {
            setFlashMessage('Link nicht gefunden oder keine Berechtigung.', 'error');
        }
    } elseif ($action === 'check' && $linkId) {
        // Einzelnen Link prüfen
        $links = loadData('links.json');
        if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
            $link = $links[$linkId];
            $result = checkBacklinkExists($link['backlink_url'], $link['target_url'], $link['anchor_text']);
            
            // Status basierend auf Prüfung setzen
            $newStatus = 'defekt';
            if ($result['isValid'] && $result['containsTargetLink']) {
                $newStatus = 'aktiv';
            } elseif ($result['isValid']) {
                $newStatus = 'ausstehend'; // URL erreichbar, aber Link nicht gefunden
            }
            
            $links[$linkId]['status'] = $newStatus;
            $links[$linkId]['last_checked'] = date('Y-m-d H:i:s');
            $links[$linkId]['check_result'] = $result;
            
            if (saveData('links.json', $links)) {
                $message = 'Link-Prüfung abgeschlossen. Status: ' . ucfirst($newStatus);
                if (!empty($result['error'])) {
                    $message .= ' (Fehler: ' . $result['error'] . ')';
                }
                redirectWithMessage("?page=links&action=view&id=$linkId", $message);
            } else {
                setFlashMessage('Fehler beim Speichern der Prüfungsergebnisse.', 'error');
            }
        }
    } elseif ($action === 'bulk_check') {
        // Alle Links prüfen
        $links = loadData('links.json');
        $userLinks = [];
        
        if ($isAdmin) {
            $userLinks = $links;
        } else {
            foreach ($links as $id => $link) {
                if ($link['user_id'] === $userId) {
                    $userLinks[$id] = $link;
                }
            }
        }
        
        $checkedCount = 0;
        $statusChanges = 0;
        
        foreach ($userLinks as $id => $link) {
            $result = checkBacklinkExists($link['backlink_url'], $link['target_url'], $link['anchor_text']);
            
            $oldStatus = $link['status'];
            $newStatus = 'defekt';
            if ($result['isValid'] && $result['containsTargetLink']) {
                $newStatus = 'aktiv';
            } elseif ($result['isValid']) {
                $newStatus = 'ausstehend';
            }
            
            $links[$id]['status'] = $newStatus;
            $links[$id]['last_checked'] = date('Y-m-d H:i:s');
            $links[$id]['check_result'] = $result;
            
            if ($oldStatus !== $newStatus) {
                $statusChanges++;
            }
            
            $checkedCount++;
            
            // Kurze Pause zwischen Requests
            usleep(500000); // 0.5 Sekunden
        }
        
        if (saveData('links.json', $links)) {
            redirectWithMessage('?page=links', "Massenprüfung abgeschlossen: $checkedCount Links geprüft, $statusChanges Statusänderungen.");
        } else {
            setFlashMessage('Fehler beim Speichern der Prüfungsergebnisse.', 'error');
        }
    }
}

// Daten laden
$links = loadData('links.json');
$customers = loadData('customers.json');
$blogs = loadData('blogs.json');

// Links je nach Berechtigung filtern
if ($isAdmin) {
    $userLinks = $links;
} else {
    $userLinks = array_filter($links, function($link) use ($userId) {
        return isset($link['user_id']) && $link['user_id'] === $userId;
    });
}

// Server-seitige Filterung anwenden
$activeFilters = [];

// Status-Filter
if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
    $statusFilter = $_GET['status_filter'];
    $userLinks = array_filter($userLinks, function($link) use ($statusFilter) {
        return ($link['status'] ?? 'ausstehend') === $statusFilter;
    });
    $activeFilters['status'] = $statusFilter;
}

// Blog-Filter
if (isset($_GET['blog_filter']) && !empty($_GET['blog_filter'])) {
    $blogFilter = $_GET['blog_filter'];
    $userLinks = array_filter($userLinks, function($link) use ($blogFilter) {
        return isset($link['blog_id']) && $link['blog_id'] === $blogFilter;
    });
    $activeFilters['blog'] = $blogFilter;
}

// Kunden-Filter
if (isset($_GET['customer_filter']) && !empty($_GET['customer_filter'])) {
    $customerFilter = $_GET['customer_filter'];
    $userLinks = array_filter($userLinks, function($link) use ($customerFilter) {
        return isset($link['customer_id']) && $link['customer_id'] === $customerFilter;
    });
    $activeFilters['customer'] = $customerFilter;
}

// Such-Filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = strtolower($_GET['search']);
    $userLinks = array_filter($userLinks, function($link) use ($searchTerm) {
        $anchor = strtolower($link['anchor_text'] ?? '');
        $target = strtolower($link['target_url'] ?? '');
        $backlink = strtolower($link['backlink_url'] ?? '');
        return strpos($anchor, $searchTerm) !== false || 
               strpos($target, $searchTerm) !== false || 
               strpos($backlink, $searchTerm) !== false;
    });
    $activeFilters['search'] = $_GET['search'];
}

// Statistiken berechnen
$statusStats = [];
foreach ($userLinks as $link) {
    $status = $link['status'] ?? 'ausstehend';
    $statusStats[$status] = ($statusStats[$status] ?? 0) + 1;
}

// VIEW LOGIC STARTS HERE
if ($action === 'debug' && $linkId) {
    // DEBUG-SEITE: Direkter Test mit sofortiger Anzeige
    $links = loadData('links.json');
    if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
        $link = $links[$linkId];
        
        // Zeige Debug-Seite
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Link-Debug: <?= e($link['anchor_text']) ?></title>
            <link rel="stylesheet" href="assets/style.css">
            <style>
                .debug-section { background: #343852; padding: 20px; margin: 20px 0; border-radius: 8px; border: 1px solid #3a3d52; }
                .debug-step { margin: 15px 0; padding: 10px; background: #2a2d42; border-radius: 4px; }
                .success { color: #10b981; }
                .error { color: #ef4444; }
                .warning { color: #f59e0b; }
                .info { color: #4dabf7; }
                pre { background: #1a1d2e; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
            </style>
        </head>
        <body style="background: #1a1d2e; color: #e2e8f0; font-family: monospace; padding: 20px;">
            <div style="max-width: 1200px; margin: 0 auto;">
                <h1 style="color: #4dabf7;">🔍 Link-Debug-Analyse</h1>
                <p><a href="?page=links&action=view&id=<?= $linkId ?>" style="color: #4dabf7;">← Zurück zum Link</a></p>
                
                <div class="debug-section">
                    <h2>📋 Link-Informationen</h2>
                    <p><strong>Ankertext:</strong> <?= e($link['anchor_text']) ?></p>
                    <p><strong>Ziel-URL:</strong> <?= e($link['target_url']) ?></p>
                    <p><strong>Backlink-URL:</strong> <?= e($link['backlink_url']) ?></p>
                </div>
                
                <?php
                echo '<div class="debug-section">';
                echo '<h2>🚀 Live-Test startet...</h2>';
                echo '<div id="liveResults">';
                
                // SCHRITT 1: URL-Validierung
                echo '<div class="debug-step">';
                echo '<h3>SCHRITT 1: URL-Validierung</h3>';
                
                $backlinkUrl = $link['backlink_url'];
                $targetUrl = $link['target_url'];
                $anchorText = $link['anchor_text'];
                
                if (empty($backlinkUrl) || empty($targetUrl) || empty($anchorText)) {
                    echo '<p class="error">❌ Fehler: Fehlende Parameter</p>';
                    echo '<ul>';
                    if (empty($backlinkUrl)) echo '<li class="error">Backlink-URL fehlt</li>';
                    if (empty($targetUrl)) echo '<li class="error">Ziel-URL fehlt</li>';
                    if (empty($anchorText)) echo '<li class="error">Ankertext fehlt</li>';
                    echo '</ul>';
                } else {
                    echo '<p class="success">✅ Alle Parameter vorhanden</p>';
                    echo '<ul>';
                    echo '<li class="info">Backlink-URL: ' . e($backlinkUrl) . '</li>';
                    echo '<li class="info">Ziel-URL: ' . e($targetUrl) . '</li>';
                    echo '<li class="info">Ankertext: "' . e($anchorText) . '"</li>';
                    echo '</ul>';
                }
                echo '</div>';
                
                if (!empty($backlinkUrl) && !empty($targetUrl) && !empty($anchorText)) {
                    // SCHRITT 2: HTTP-Test
                    echo '<div class="debug-step">';
                    echo '<h3>SCHRITT 2: HTTP-Erreichbarkeit</h3>';
                    
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'HEAD',
                            'header' => [
                                'User-Agent: Mozilla/5.0 (compatible; LinkBuilder-Debug/1.0)',
                                'Accept: text/html,application/xhtml+xml',
                            ],
                            'timeout' => 10,
                            'ignore_errors' => true
                        ]
                    ]);
                    
                    $headers = @get_headers($backlinkUrl, 1, $context);
                    
                    if ($headers === false) {
                        echo '<p class="error">❌ Fehler: Konnte keine Verbindung zur URL herstellen</p>';
                        echo '<p class="warning">Mögliche Gründe: Timeout, DNS-Fehler, Server nicht erreichbar</p>';
                    } else {
                        echo '<p class="success">✅ Verbindung erfolgreich</p>';
                        
                        $statusCode = 0;
                        if (isset($headers[0])) {
                            preg_match('/HTTP\/[\d\.]+\s+(\d+)/', $headers[0], $matches);
                            $statusCode = isset($matches[1]) ? (int)$matches[1] : 0;
                        }
                        
                        if ($statusCode >= 200 && $statusCode < 300) {
                            echo '<p class="success">✅ HTTP-Status: ' . $statusCode . ' (OK)</p>';
                        } elseif ($statusCode >= 300 && $statusCode < 400) {
                            echo '<p class="warning">⚠️ HTTP-Status: ' . $statusCode . ' (Redirect)</p>';
                        } else {
                            echo '<p class="error">❌ HTTP-Status: ' . $statusCode . ' (Fehler)</p>';
                        }
                        
                        echo '<details style="margin-top: 10px;"><summary>HTTP-Headers anzeigen</summary>';
                        echo '<pre>' . print_r($headers, true) . '</pre>';
                        echo '</details>';
                    }
                    echo '</div>';
                    
                    // SCHRITT 3: HTML-Content laden
                    if ($headers !== false && $statusCode >= 200 && $statusCode < 400) {
                        echo '<div class="debug-step">';
                        echo '<h3>SCHRITT 3: HTML-Content laden</h3>';
                        
                        $htmlContent = @file_get_contents($backlinkUrl, false, stream_context_create([
                            'http' => [
                                'method' => 'GET',
                                'header' => [
                                    'User-Agent: Mozilla/5.0 (compatible; LinkBuilder-Debug/1.0)',
                                    'Accept: text/html,application/xhtml+xml',
                                ],
                                'timeout' => 15
                            ]
                        ]));
                        
                        if ($htmlContent === false) {
                            echo '<p class="error">❌ Konnte HTML-Inhalt nicht laden</p>';
                        } else {
                            $contentLength = strlen($htmlContent);
                            echo '<p class="success">✅ HTML geladen: ' . number_format($contentLength) . ' Zeichen</p>';
                            
                            // SCHRITT 4: Link-Suche
                            echo '</div><div class="debug-step">';
                            echo '<h3>SCHRITT 4: Link-Suche</h3>';
                            
                            // Einfache Suche nach <a> Tags
                            $pattern = '/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is';
                            $matches = [];
                            $matchCount = preg_match_all($pattern, $htmlContent, $matches, PREG_SET_ORDER);
                            
                            echo '<p class="info">🔍 Gefundene &lt;a&gt; Tags: ' . $matchCount . '</p>';
                            
                            if ($matchCount > 0) {
                                $foundTargetLink = false;
                                $linkResults = [];
                                
                                foreach ($matches as $i => $match) {
                                    $href = trim($match[1]);
                                    $text = trim(strip_tags($match[2]));
                                    
                                    // URL-Normalisierung
                                    $normalizedHref = strtolower(rtrim($href, '/'));
                                    $normalizedTarget = strtolower(rtrim($targetUrl, '/'));
                                    $normalizedText = strtolower($text);
                                    $normalizedAnchor = strtolower($anchorText);
                                    
                                    // Prüfung
                                    $hrefMatch = ($normalizedHref === $normalizedTarget) || 
                                                (strpos($normalizedHref, $normalizedTarget) !== false) ||
                                                (strpos($normalizedTarget, $normalizedHref) !== false);
                                    
                                    $textMatch = ($normalizedText === $normalizedAnchor) || 
                                                (strpos($normalizedText, $normalizedAnchor) !== false) ||
                                                (strpos($normalizedAnchor, $normalizedText) !== false);
                                    
                                    $linkResults[] = [
                                        'href' => $href,
                                        'text' => $text,
                                        'hrefMatch' => $hrefMatch,
                                        'textMatch' => $textMatch,
                                        'perfectMatch' => $hrefMatch && $textMatch
                                    ];
                                    
                                    if ($hrefMatch && $textMatch) {
                                        $foundTargetLink = true;
                                    }
                                }
                                
                                // Ergebnis anzeigen
                                if ($foundTargetLink) {
                                    echo '<p class="success">🎯 ✅ LINK GEFUNDEN! Der gesuchte Link existiert.</p>';
                                } else {
                                    echo '<p class="warning">⚠️ Gesuchter Link nicht gefunden.</p>';
                                }
                                
                                // Details zu den ersten 10 Links
                                echo '<h4>🔗 Link-Analyse (erste 10 Links):</h4>';
                                echo '<div style="max-height: 300px; overflow-y: auto;">';
                                foreach (array_slice($linkResults, 0, 10) as $i => $result) {
                                    $status = $result['perfectMatch'] ? 'success' : ($result['hrefMatch'] || $result['textMatch'] ? 'warning' : 'info');
                                    echo '<div style="margin: 10px 0; padding: 8px; background: #2a2d42; border-left: 3px solid ' . 
                                         ($result['perfectMatch'] ? '#10b981' : ($result['hrefMatch'] || $result['textMatch'] ? '#f59e0b' : '#4dabf7')) . ';">';
                                    echo '<strong>Link #' . ($i+1) . ':</strong><br>';
                                    echo 'URL: ' . e($result['href']) . ' ' . ($result['hrefMatch'] ? '✅' : '❌') . '<br>';
                                    echo 'Text: "' . e($result['text']) . '" ' . ($result['textMatch'] ? '✅' : '❌') . '<br>';
                                    if ($result['perfectMatch']) {
                                        echo '<span class="success">🎯 PERFEKTER MATCH!</span>';
                                    }
                                    echo '</div>';
                                }
                                echo '</div>';
                                
                                // Zusammenfassung
                                echo '<div style="margin-top: 20px; padding: 15px; background: #2a2d42; border-radius: 6px;">';
                                echo '<h4>📊 Zusammenfassung:</h4>';
                                echo '<ul>';
                                echo '<li>Gefundene Links: ' . count($linkResults) . '</li>';
                                echo '<li>URL-Matches: ' . count(array_filter($linkResults, fn($l) => $l['hrefMatch'])) . '</li>';
                                echo '<li>Text-Matches: ' . count(array_filter($linkResults, fn($l) => $l['textMatch'])) . '</li>';
                                echo '<li>Perfekte Matches: ' . count(array_filter($linkResults, fn($l) => $l['perfectMatch'])) . '</li>';
                                echo '</ul>';
                                
                                if ($foundTargetLink) {
                                    echo '<p class="success"><strong>🎯 ERGEBNIS: LINK AKTIV</strong></p>';
                                } else {
                                    echo '<p class="warning"><strong>⚠️ ERGEBNIS: LINK NICHT GEFUNDEN (Status: AUSSTEHEND/DEFEKT)</strong></p>';
                                }
                                echo '</div>';
                                
                            } else {
                                echo '<p class="error">❌ Keine &lt;a&gt; Tags auf der Seite gefunden</p>';
                            }
                        }
                    }
                }
                
                echo '</div></div>';
                ?>
                
                <div style="margin: 30px 0; text-align: center;">
                    <a href="?page=links&action=view&id=<?= $linkId ?>" class="btn btn-primary" style="display: inline-block; padding: 12px 24px; background: #4dabf7; color: white; text-decoration: none; border-radius: 6px;">
                        ← Zurück zum Link
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Link nicht gefunden oder keine Berechtigung.</div>';
        return;
    }
} elseif ($action === 'index'): ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">
                Link-Verwaltung
                <?php if ($isAdmin): ?>
                    <span class="badge badge-info" style="font-size: 12px; margin-left: 8px;">ADMIN-ANSICHT</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <?php if ($isAdmin): ?>
                    Verwalten Sie alle Links im System (<?= count($userLinks) ?> Links von <?= count(array_unique(array_column($userLinks, 'user_id'))) ?> Benutzern)
                <?php else: ?>
                    Verwalten Sie Ihre platzierten Links (<?= count($userLinks) ?> Links)
                <?php endif; ?>
            </p>
        </div>
        <div class="action-buttons">
            <a href="?page=links&action=create" class="btn btn-primary">
                <i class="fas fa-plus"></i> Neuer Link
            </a>
            <button onclick="startBulkCheck()" class="btn btn-info" id="bulkCheckBtn">
                <i class="fas fa-sync-alt"></i> Alle Links prüfen
            </button>
        </div>
    </div>

    <?php showFlashMessage(); ?>
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

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
                
                <?php if (!empty($userLinks)): ?>
                    <div style="margin-top: 16px;">
                        <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">Link-Performance</div>
                        <?php 
                        $total = count($userLinks);
                        $active = $statusStats['aktiv'] ?? 0;
                        $successRate = $total > 0 ? round(($active / $total) * 100) : 0;
                        ?>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #8b8fa3;">
                            <span>Erfolgsrate:</span>
                            <span style="color: <?= $successRate >= 80 ? '#10b981' : ($successRate >= 60 ? '#f59e0b' : '#ef4444') ?>;">
                                <?= $successRate ?>%
                            </span>
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

                    <button onclick="startBulkCheck()" class="quick-action" style="background: none; border: none; width: 100%;">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <div class="quick-action-content">
                            <div class="quick-action-title">Links prüfen</div>
                            <div class="quick-action-subtitle">Alle Links auf Status prüfen</div>
                        </div>
                    </button>

                    <a href="?page=links&status_filter=defekt" class="quick-action">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="quick-action-content">
                            <div class="quick-action-title">Defekte Links</div>
                            <div class="quick-action-subtitle"><?= $statusStats['defekt'] ?? 0 ?> defekte Links anzeigen</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter und Suche -->
    <?php if (!empty($links)): ?>
        <div class="action-bar" style="margin-top: 30px;">
            <form method="GET" style="display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%; flex-wrap: wrap;">
                <input type="hidden" name="page" value="links">
                
                <div class="search-bar" style="flex: 1; min-width: 280px; max-width: 400px;">
                    <div style="position: relative;">
                        <i class="fas fa-search search-icon"></i>
                        <input 
                            type="text" 
                            class="form-control search-input" 
                            placeholder="Links durchsuchen (Ankertext, URLs)"
                            name="search"
                            value="<?= e($_GET['search'] ?? '') ?>"
                        >
                    </div>
                </div>
                
                <div class="filter-controls">
                    <select class="form-control filter-select" name="status_filter" onchange="this.form.submit()">
                        <option value="">Alle Status</option>
                        <option value="aktiv" <?= ($_GET['status_filter'] ?? '') === 'aktiv' ? 'selected' : '' ?>>Aktiv</option>
                        <option value="ausstehend" <?= ($_GET['status_filter'] ?? '') === 'ausstehend' ? 'selected' : '' ?>>Ausstehend</option>
                        <option value="defekt" <?= ($_GET['status_filter'] ?? '') === 'defekt' ? 'selected' : '' ?>>Defekt</option>
                    </select>
                    
                    <select class="form-control filter-select" name="blog_filter" onchange="this.form.submit()">
                        <option value="">Alle Blogs</option>
                        <?php 
                        $availableBlogs = [];
                        foreach ($userLinks as $link) {
                            if (isset($blogs[$link['blog_id']])) {
                                $availableBlogs[$link['blog_id']] = $blogs[$link['blog_id']];
                            }
                        }
                        foreach ($availableBlogs as $blogId => $blog): 
                        ?>
                            <option value="<?= e($blogId) ?>" <?= ($_GET['blog_filter'] ?? '') === $blogId ? 'selected' : '' ?>>
                                <?= e($blog['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select class="form-control filter-select" name="customer_filter" onchange="this.form.submit()">
                        <option value="">Alle Kunden</option>
                        <?php 
                        $availableCustomers = [];
                        foreach ($userLinks as $link) {
                            if (isset($customers[$link['customer_id']])) {
                                $availableCustomers[$link['customer_id']] = $customers[$link['customer_id']];
                            }
                        }
                        foreach ($availableCustomers as $customerId => $customer): 
                        ?>
                            <option value="<?= e($customerId) ?>" <?= ($_GET['customer_filter'] ?? '') === $customerId ? 'selected' : '' ?>>
                                <?= e($customer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php if (!empty($activeFilters)): ?>
                        <a href="?page=links" class="btn btn-secondary" style="white-space: nowrap;">
                            <i class="fas fa-times"></i> Filter zurücksetzen
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Aktive Filter anzeigen -->
        <?php if (!empty($activeFilters)): ?>
            <div style="margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <span style="color: #8b8fa3; font-size: 14px; margin-right: 8px;">Aktive Filter:</span>
                <?php foreach ($activeFilters as $type => $value): ?>
                    <span class="badge badge-info" style="display: flex; align-items: center; gap: 6px;">
                        <?php if ($type === 'status'): ?>
                            <i class="fas fa-flag"></i>
                            Status: <?= e(ucfirst($value)) ?>
                        <?php elseif ($type === 'blog'): ?>
                            <i class="fas fa-blog"></i>
                            Blog: <?= e($blogs[$value]['name'] ?? 'Unbekannt') ?>
                        <?php elseif ($type === 'customer'): ?>
                            <i class="fas fa-user"></i>
                            Kunde: <?= e($customers[$value]['name'] ?? 'Unbekannt') ?>
                        <?php elseif ($type === 'search'): ?>
                            <i class="fas fa-search"></i>
                            "<?= e($value) ?>"
                        <?php endif; ?>
                        <?php
                        $removeFilters = $_GET;
                        unset($removeFilters[$type . '_filter']);
                        if ($type === 'search') unset($removeFilters['search']);
                        $removeUrl = '?page=links';
                        if (!empty($removeFilters)) {
                            unset($removeFilters['page']);
                            if (!empty($removeFilters)) {
                                $removeUrl .= '&' . http_build_query($removeFilters);
                            }
                        }
                        ?>
                        <a href="<?= $removeUrl ?>" style="color: white; margin-left: 4px; text-decoration: none;">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($userLinks)): ?>
        <div class="empty-state">
            <i class="fas fa-link"></i>
            <h3>Keine Links vorhanden</h3>
            <p><?= $isAdmin ? 'Im System sind noch keine Links vorhanden.' : 'Erstellen Sie Ihren ersten Link, um hier eine Übersicht zu sehen.' ?></p>
            <a href="?page=links&action=create" class="btn btn-primary" style="margin-top: 16px;">
                <i class="fas fa-plus"></i> Ersten Link erstellen
            </a>
        </div>
    <?php else: ?>
        <!-- Links-Tabelle -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ankertext</th>
                                <th>Kunde</th>
                                <th>Blog</th>
                                <th>Ziel-URL</th>
                                <th>Status</th>
                                <th>Letzte Prüfung</th>
                                <?php if ($isAdmin): ?>
                                    <th>Erstellt von</th>
                                <?php endif; ?>
                                <th>Veröffentlicht</th>
                                <th style="width: 140px;">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userLinks as $linkId => $link): 
                                $customer = $customers[$link['customer_id']] ?? null;
                                $blog = $blogs[$link['blog_id']] ?? null;
                                
                                // Link-Besitzer Info (nur für Admin)
                                if ($isAdmin) {
                                    $linkOwner = $users[$link['user_id']] ?? null;
                                    $linkOwnerName = $linkOwner ? ($linkOwner['name'] ?? $linkOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
                                    $isOwnLink = $link['user_id'] === $userId;
                                }
                            ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div style="font-weight: 600; color: #e2e8f0; margin-bottom: 4px;">
                                                <a href="?page=links&action=view&id=<?= $linkId ?>" style="color: #4dabf7; text-decoration: none;">
                                                    <?= e($link['anchor_text']) ?>
                                                </a>
                                            </div>
                                            <div style="font-size: 11px; color: #8b8fa3;">
                                                <a href="<?= e($link['backlink_url']) ?>" target="_blank" style="color: #8b8fa3; text-decoration: none;">
                                                    <?= e(truncateText($link['backlink_url'], 40)) ?>
                                                    <i class="fas fa-external-link-alt" style="margin-left: 2px; font-size: 9px;"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($customer): ?>
                                            <a href="?page=customers&action=view&id=<?= $link['customer_id'] ?>" style="color: #4dabf7; text-decoration: none;">
                                                <?= e($customer['name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #8b8fa3;">-</span>
                                        <?php endif; ?>
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
                                        <a href="<?= e($link['target_url']) ?>" target="_blank" style="color: #4dabf7; text-decoration: none; font-size: 12px;">
                                            <?= e(truncateText($link['target_url'], 30)) ?>
                                            <i class="fas fa-external-link-alt" style="margin-left: 2px; font-size: 9px;"></i>
                                        </a>
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
                                            <?= formatDateTime($link['last_checked']) ?>
                                        <?php else: ?>
                                            Noch nie
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($isAdmin): ?>
                                        <td style="font-size: 12px; color: #8b8fa3;">
                                            <?php if ($isOwnLink): ?>
                                                <span style="color: #10b981;">
                                                    <i class="fas fa-crown" style="margin-right: 4px;"></i>
                                                    Sie
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #fbbf24;">
                                                    <i class="fas fa-user" style="margin-right: 4px;"></i>
                                                    <?= e($linkOwnerName) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td style="color: #8b8fa3; font-size: 12px;">
                                        <?= formatDate($link['published_date'] ?? $link['created_at']) ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 4px;">
                                            <a href="?page=links&action=view&id=<?= $linkId ?>" class="btn btn-sm btn-primary" title="Anzeigen">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="check">
                                                <input type="hidden" name="id" value="<?= $linkId ?>">
                                                <button type="submit" class="btn btn-sm btn-info" title="Link prüfen">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </form>
                                            <?php if ($isAdmin || $link['user_id'] === $userId): ?>
                                                <a href="?page=links&action=edit&id=<?= $linkId ?>" class="btn btn-sm btn-secondary" title="Bearbeiten">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?page=links&action=delete&id=<?= $linkId ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Link &quot;<?= e($link['anchor_text']) ?>&quot; wirklich löschen?')">
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

<?php elseif ($action === 'view' && $linkId): 
    $link = $links[$linkId] ?? null;
    if (!$link || (!$isAdmin && $link['user_id'] !== $userId)) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Link nicht gefunden oder keine Berechtigung.</div>';
        return;
    }
    
    $customer = $customers[$link['customer_id']] ?? null;
    $blog = $blogs[$link['blog_id']] ?? null;
    
    // Link-Besitzer Info
    $linkOwner = $users[$link['user_id']] ?? null;
    $ownerName = $linkOwner ? ($linkOwner['name'] ?? $linkOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
    $isOwnLink = $link['user_id'] === $userId;
?>
    <div class="breadcrumb">
        <a href="?page=links">Zurück zu Links</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= e($link['anchor_text']) ?></span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <?= e($link['anchor_text']) ?>
                <?php if ($isAdmin && !$isOwnLink): ?>
                    <span class="badge badge-warning" style="font-size: 12px; margin-left: 8px;">Fremder Link</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Von <strong><?= $customer ? e($customer['name']) : 'Unbekannter Kunde' ?></strong> 
                zu <strong><?= $blog ? e($blog['name']) : 'Unbekannter Blog' ?></strong>
                <?php if ($isAdmin && !$isOwnLink): ?>
                    <br>
                    <small style="color: #8b8fa3;">
                        <i class="fas fa-user" style="margin-right: 4px;"></i>
                        Erstellt von: <strong><?= e($ownerName) ?></strong>
                    </small>
                <?php endif; ?>
            </p>
        </div>
        <div class="action-buttons">
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="check">
                <input type="hidden" name="id" value="<?= $linkId ?>">
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-sync-alt"></i> Link prüfen
                </button>
            </form>
            <?php if ($isAdmin || $isOwnLink): ?>
                <a href="?page=links&action=edit&id=<?= $linkId ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Bearbeiten
                </a>
                <a href="?page=links&action=delete&id=<?= $linkId ?>" class="btn btn-danger" onclick="return confirm('Link &quot;<?= e($link['anchor_text']) ?>&quot; wirklich löschen?')">
                    <i class="fas fa-trash"></i> Löschen
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php showFlashMessage(); ?>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="showTab('details')">Link-Details</button>
        <button class="tab" onclick="showTab('validation')">Validierung</button>
        <button class="tab" onclick="showTab('history')">Prüfungshistorie</button>
    </div>

    <!-- Details Tab -->
    <div id="detailsTab" class="tab-content">
        <div class="content-grid">
            <!-- Link-Informationen -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Link-Informationen</h3>
                </div>
                <div class="card-body">
                    <div class="link-meta">
                        <div class="meta-item">
                            <div class="meta-label">Ankertext</div>
                            <div class="meta-value"><?= e($link['anchor_text']) ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Ziel-URL</div>
                            <div class="meta-value">
                                <a href="<?= e($link['target_url']) ?>" target="_blank" style="color: #4dabf7; word-break: break-all;">
                                    <?= e($link['target_url']) ?>
                                    <i class="fas fa-external-link-alt" style="margin-left: 4px; font-size: 12px;"></i>
                                </a>
                            </div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Backlink-URL</div>
                            <div class="meta-value">
                                <a href="<?= e($link['backlink_url']) ?>" target="_blank" style="color: #4dabf7; word-break: break-all;">
                                    <?= e($link['backlink_url']) ?>
                                    <i class="fas fa-external-link-alt" style="margin-left: 4px; font-size: 12px;"></i>
                                </a>
                            </div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Status</div>
                            <div class="meta-value">
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
                        <div class="meta-item">
                            <div class="meta-label">Veröffentlicht am</div>
                            <div class="meta-value"><?= formatDate($link['published_date']) ?></div>
                        </div>
                        <?php if (!empty($link['expiry_date'])): ?>
                            <div class="meta-item">
                                <div class="meta-label">Ablaufdatum</div>
                                <div class="meta-value"><?= formatDate($link['expiry_date']) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($link['customer_website_url'])): ?>
                            <div class="meta-item">
                                <div class="meta-label">Kunden-Website</div>
                                <div class="meta-value">
                                    <a href="<?= e($link['customer_website_url']) ?>" target="_blank" style="color: #4dabf7;">
                                        <?= e($link['customer_website_url']) ?>
                                        <i class="fas fa-external-link-alt" style="margin-left: 4px; font-size: 12px;"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Zugehörigkeit -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Zugehörigkeit</h3>
                </div>
                <div class="card-body">
                    <div class="link-meta">
                        <div class="meta-item">
                            <div class="meta-label">Kunde</div>
                            <div class="meta-value">
                                <?php if ($customer): ?>
                                    <a href="?page=customers&action=view&id=<?= $link['customer_id'] ?>" style="color: #4dabf7;">
                                        <?= e($customer['name']) ?>
                                    </a>
                                    <?php if (!empty($customer['company'])): ?>
                                        <br><small style="color: #8b8fa3;"><?= e($customer['company']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #ef4444;">Kunde nicht gefunden</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Blog</div>
                            <div class="meta-value">
                                <?php if ($blog): ?>
                                    <a href="?page=blogs&action=view&id=<?= $link['blog_id'] ?>" style="color: #4dabf7;">
                                        <?= e($blog['name']) ?>
                                    </a>
                                    <br><small style="color: #8b8fa3;"><?= e($blog['url']) ?></small>
                                <?php else: ?>
                                    <span style="color: #ef4444;">Blog nicht gefunden</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($isAdmin): ?>
                            <div class="meta-item">
                                <div class="meta-label">Erstellt von</div>
                                <div class="meta-value">
                                    <?= e($ownerName) ?>
                                    <?php if ($linkOwner && isset($linkOwner['email'])): ?>
                                        <br><small style="color: #8b8fa3;"><?= e($linkOwner['email']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="meta-item">
                            <div class="meta-label">Erstellt am</div>
                            <div class="meta-value"><?= formatDateTime($link['created_at']) ?></div>
                        </div>
                        <?php if (!empty($link['updated_at'])): ?>
                            <div class="meta-item">
                                <div class="meta-label">Zuletzt aktualisiert</div>
                                <div class="meta-value"><?= formatDateTime($link['updated_at']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($link['description'])): ?>
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3 class="card-title">Beschreibung</h3>
                </div>
                <div class="card-body">
                    <p style="color: #e2e8f0; line-height: 1.6; white-space: pre-line;"><?= e($link['description']) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Validierung Tab -->
    <div id="validationTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Link-Validierung</h3>
                <p class="card-subtitle">Aktuelle Prüfungsergebnisse und Status</p>
            </div>
            <div class="card-body">
                <?php if (!empty($link['check_result'])): ?>
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
                    </div>
                    
                    <!-- Gesuchte Kriterien anzeigen -->
                    <?php if (!empty($result['searchedFor'])): ?>
                        <div style="background-color: #343852; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin-bottom: 12px; color: #e2e8f0;">Prüfungskriterien</h4>
                            <div style="color: #8b8fa3; font-size: 14px;">
                                <div><strong>Gesuchter Ankertext:</strong> "<?= e($result['searchedFor']['anchorText']) ?>"</div>
                                <div><strong>Gesuchte Ziel-URL:</strong> <?= e($result['searchedFor']['targetUrl']) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($result['error'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Prüfungshinweis:</strong> <?= e($result['error']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Gefundene Links anzeigen -->
                    <?php if (!empty($result['foundLinks'])): ?>
                        <div style="background-color: #343852; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin-bottom: 12px; color: #e2e8f0;">Gefundene Links auf der Seite (<?= count($result['foundLinks']) ?>)</h4>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($result['foundLinks'] as $foundLink): ?>
                                    <div style="border-bottom: 1px solid #3a3d52; padding: 8px 0; font-size: 13px;">
                                        <div style="color: #4dabf7; margin-bottom: 2px;">
                                            <strong>Ankertext:</strong> "<?= e($foundLink['text']) ?>"
                                        </div>
                                        <div style="color: #8b8fa3;">
                                            <strong>URL:</strong> <?= e($foundLink['href']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Debug-Informationen (nur für Admins oder eigene Links) -->
                    <?php if (($isAdmin || $isOwnLink) && !empty($result['debug'])): ?>
                        <details style="background-color: #343852; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                            <summary style="color: #e2e8f0; cursor: pointer; margin-bottom: 8px;">
                                🔍 Debug-Informationen anzeigen
                            </summary>
                            <div style="font-family: monospace; font-size: 12px; color: #8b8fa3;">
                                <?php foreach ($result['debug'] as $debugLine): ?>
                                    <div><?= e($debugLine) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                    
                    <div style="background-color: #343852; padding: 16px; border-radius: 8px;">
                        <h4 style="margin-bottom: 12px; color: #e2e8f0;">Letzte Prüfung</h4>
                        <p style="color: #8b8fa3; margin: 0;">
                            <?= formatDateTime($link['last_checked']) ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>Link-Prüfung erforderlich</h3>
                        <p>Dieser Link wurde noch nicht geprüft. Klicken Sie auf "Jetzt prüfen", um eine detaillierte Analyse zu starten.</p>
                        
                        <div style="background-color: #343852; padding: 16px; border-radius: 8px; margin: 20px 0; text-align: left;">
                            <h4 style="color: #4dabf7; margin-bottom: 12px;">
                                <i class="fas fa-info-circle"></i> Was wird geprüft?
                            </h4>
                            <ul style="color: #8b8fa3; margin: 0; padding-left: 20px;">
                                <li>Ist die Backlink-URL erreichbar?</li>
                                <li>Existiert ein Link mit dem Ankertext "<strong><?= e($link['anchor_text']) ?></strong>"?</li>
                                <li>Zeigt dieser Link auf die Ziel-URL "<strong><?= e($link['target_url']) ?></strong>"?</li>
                                <li>Sind beide Bedingungen erfüllt? → Status "AKTIV"</li>
                            </ul>
                        </div>
                        
                        <form method="post" style="margin-top: 16px;">
                            <input type="hidden" name="action" value="check">
                            <input type="hidden" name="id" value="<?= $linkId ?>">
                            <button type="submit" class="btn btn-primary" style="font-size: 16px; padding: 12px 24px;">
                                <i class="fas fa-sync-alt"></i> Jetzt prüfen und Debug-Infos anzeigen
                            </button>
                        </form>
                        
                        <!-- DIREKT-TEST BUTTON -->
                        <a href="?page=links&action=debug&id=<?= $linkId ?>" class="btn btn-info" style="font-size: 16px; padding: 12px 24px; margin-top: 12px; text-decoration: none; display: inline-block;">
                            <i class="fas fa-bug"></i> 🔍 Live-Debug-Analyse (neue Seite)
                        </a>
                        
                        <div style="margin-top: 20px; padding: 12px; background-color: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 6px;">
                            <div style="color: #f59e0b; font-size: 14px;">
                                <i class="fas fa-lightbulb"></i> 
                                <strong>Hinweis:</strong> Nach der Prüfung sehen Sie hier detaillierte Debug-Informationen, 
                                alle gefundenen Links und den Grund für das Prüfungsergebnis.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- History Tab -->
    <div id="historyTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Prüfungshistorie</h3>
                <p class="card-subtitle">Verlauf der automatischen Link-Prüfungen</p>
            </div>
            <div class="card-body">
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>Historie in Entwicklung</h3>
                    <p>Die detaillierte Prüfungshistorie wird in einer zukünftigen Version verfügbar sein.</p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'create' || ($action === 'edit' && $linkId)): 
    $link = null;
    if ($action === 'edit') {
        $link = $links[$linkId] ?? null;
        if (!$link || (!$isAdmin && $link['user_id'] !== $userId)) {
            echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Link nicht gefunden oder keine Berechtigung.</div>';
            return;
        }
    }
    
    // Pre-select values from URL parameters (for quick creation)
    $preSelectedCustomer = $_GET['customer_id'] ?? ($link['customer_id'] ?? '');
    $preSelectedBlog = $_GET['blog_id'] ?? ($link['blog_id'] ?? '');
    $preSelectedWebsiteUrl = $_GET['website_url'] ?? ($link['customer_website_url'] ?? '');
    
    // Filter customers and blogs based on user permissions
    $availableCustomers = [];
    $availableBlogs = [];
    
    if ($isAdmin) {
        $availableCustomers = $customers;
        $availableBlogs = $blogs;
    } else {
        foreach ($customers as $id => $customer) {
            if ($customer['user_id'] === $userId) {
                $availableCustomers[$id] = $customer;
            }
        }
        foreach ($blogs as $id => $blog) {
            if ($blog['user_id'] === $userId) {
                $availableBlogs[$id] = $blog;
            }
        }
    }
    
    // Link-Besitzer Info für Edit-Modus
    $linkOwner = null;
    $ownerName = '';
    $isOwnLink = true;
    if ($link) {
        $linkOwner = $users[$link['user_id']] ?? null;
        $ownerName = $linkOwner ? ($linkOwner['name'] ?? $linkOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
        $isOwnLink = $link['user_id'] === $userId;
    }
?>
    <div class="breadcrumb">
        <a href="?page=links">Zurück zu Links</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= $action === 'create' ? 'Link hinzufügen' : 'Link bearbeiten' ?></span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <?= $action === 'create' ? 'Neuen Link hinzufügen' : 'Link bearbeiten' ?>
                <?php if ($action === 'edit' && $isAdmin && !$isOwnLink): ?>
                    <span class="badge badge-warning" style="font-size: 12px; margin-left: 8px;">Fremder Link</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Füllen Sie das Formular aus, um einen <?= $action === 'create' ? 'neuen Link zu erstellen' : 'Link zu aktualisieren' ?>
                <?php if ($action === 'edit' && $isAdmin && !$isOwnLink): ?>
                    <br>
                    <small style="color: #8b8fa3;">
                        <i class="fas fa-user" style="margin-right: 4px;"></i>
                        Erstellt von: <strong><?= e($ownerName) ?></strong>
                    </small>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (isset($errors) && !empty($errors)): ?>
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
            <form method="post" id="linkForm">
                <!-- Grund-Zuordnung -->
                <h3 style="margin-bottom: 20px; color: #e2e8f0; border-bottom: 1px solid #3a3d52; padding-bottom: 10px;">
                    <i class="fas fa-users"></i> Zuordnung
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_id" class="form-label">Kunde *</label>
                        <select id="customer_id" name="customer_id" class="form-control" required onchange="updateCustomerWebsites()">
                            <option value="">Kunde auswählen</option>
                            <?php foreach ($availableCustomers as $customerId => $customer): ?>
                                <option value="<?= e($customerId) ?>" 
                                        data-websites='<?= json_encode($customer['websites'] ?? []) ?>'
                                        <?= $preSelectedCustomer === $customerId ? 'selected' : '' ?>>
                                    <?= e($customer['name']) ?>
                                    <?php if (!empty($customer['company'])): ?>
                                        (<?= e($customer['company']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($availableCustomers)): ?>
                            <small style="color: #ef4444; font-size: 12px;">
                                Keine Kunden verfügbar. 
                                <a href="?page=customers&action=create" style="color: #4dabf7;">Ersten Kunden erstellen</a>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="blog_id" class="form-label">Blog *</label>
                        <select id="blog_id" name="blog_id" class="form-control" required>
                            <option value="">Blog auswählen</option>
                            <?php foreach ($availableBlogs as $blogId => $blog): ?>
                                <option value="<?= e($blogId) ?>" <?= $preSelectedBlog === $blogId ? 'selected' : '' ?>>
                                    <?= e($blog['name']) ?>
                                    <span style="color: #8b8fa3;">(<?= e($blog['url']) ?>)</span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($availableBlogs)): ?>
                            <small style="color: #ef4444; font-size: 12px;">
                                Keine Blogs verfügbar. 
                                <a href="?page=blogs&action=create" style="color: #4dabf7;">Ersten Blog erstellen</a>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="customer_website_url" class="form-label">Kunden-Website (Optional)</label>
                    <select id="customer_website_url" name="customer_website_url" class="form-control">
                        <option value="">Automatisch auswählen oder leer lassen</option>
                    </select>
                    <small style="color: #8b8fa3; font-size: 12px;">
                        Wählen Sie eine spezifische Website des Kunden, falls mehrere verfügbar sind
                    </small>
                </div>

                <!-- Link-Details -->
                <h3 style="margin: 30px 0 20px; color: #e2e8f0; border-bottom: 1px solid #3a3d52; padding-bottom: 10px;">
                    <i class="fas fa-link"></i> Link-Details
                </h3>

                <div class="form-group">
                    <label for="anchor_text" class="form-label">Ankertext *</label>
                    <input 
                        type="text" 
                        id="anchor_text" 
                        name="anchor_text" 
                        class="form-control" 
                        placeholder="Der sichtbare Text des Links"
                        value="<?= e($_POST['anchor_text'] ?? $link['anchor_text'] ?? '') ?>"
                        required
                    >
                    <small style="color: #8b8fa3; font-size: 12px;">
                        Der Text, der als anklickbarer Link erscheint
                    </small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="target_url" class="form-label">Ziel-URL *</label>
                        <input 
                            type="url" 
                            id="target_url" 
                            name="target_url" 
                            class="form-control" 
                            placeholder="https://example.com/seite"
                            value="<?= e($_POST['target_url'] ?? $link['target_url'] ?? $preSelectedWebsiteUrl) ?>"
                            required
                        >
                        <small style="color: #8b8fa3; font-size: 12px;">
                            Die URL, auf die der Link zeigen soll
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="backlink_url" class="form-label">Backlink-URL *</label>
                        <input 
                            type="url" 
                            id="backlink_url" 
                            name="backlink_url" 
                            class="form-control" 
                            placeholder="https://blog.example.com/artikel"
                            value="<?= e($_POST['backlink_url'] ?? $link['backlink_url'] ?? '') ?>"
                            required
                        >
                        <small style="color: #8b8fa3; font-size: 12px;">
                            Die URL der Seite, auf der der Link platziert wird
                        </small>
                    </div>
                </div>

                <!-- Timing und Status -->
                <h3 style="margin: 30px 0 20px; color: #e2e8f0; border-bottom: 1px solid #3a3d52; padding-bottom: 10px;">
                    <i class="fas fa-calendar"></i> Timing und Status
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="published_date" class="form-label">Veröffentlichungsdatum</label>
                        <input 
                            type="date" 
                            id="published_date" 
                            name="published_date" 
                            class="form-control" 
                            value="<?= $_POST['published_date'] ?? $link['published_date'] ?? date('Y-m-d') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="expiry_date" class="form-label">Ablaufdatum (Optional)</label>
                        <input 
                            type="date" 
                            id="expiry_date" 
                            name="expiry_date" 
                            class="form-control" 
                            value="<?= $_POST['expiry_date'] ?? $link['expiry_date'] ?? '' ?>"
                        >
                        <small style="color: #8b8fa3; font-size: 12px;">
                            Wann soll der Link entfernt werden? (Optional)
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="ausstehend" <?= ($_POST['status'] ?? $link['status'] ?? 'ausstehend') === 'ausstehend' ? 'selected' : '' ?>>
                                Ausstehend
                            </option>
                            <option value="aktiv" <?= ($_POST['status'] ?? $link['status'] ?? 'ausstehend') === 'aktiv' ? 'selected' : '' ?>>
                                Aktiv
                            </option>
                            <option value="defekt" <?= ($_POST['status'] ?? $link['status'] ?? 'ausstehend') === 'defekt' ? 'selected' : '' ?>>
                                Defekt
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Zusätzliche Informationen -->
                <h3 style="margin: 30px 0 20px; color: #e2e8f0; border-bottom: 1px solid #3a3d52; padding-bottom: 10px;">
                    <i class="fas fa-sticky-note"></i> Zusätzliche Informationen
                </h3>

                <div class="form-group">
                    <label for="description" class="form-label">Beschreibung</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        rows="4"
                        placeholder="Zusätzliche Notizen, Kontext oder Anweisungen für diesen Link"
                    ><?= e($_POST['description'] ?? $link['description'] ?? '') ?></textarea>
                    <small style="color: #8b8fa3; font-size: 12px;">
                        Interne Notizen, die nur für Sie sichtbar sind
                    </small>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #3a3d52;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        <?= $action === 'create' ? 'Link erstellen' : 'Änderungen speichern' ?>
                    </button>
                    <?php if ($action === 'create'): ?>
                        <button type="button" class="btn btn-info" onclick="validateBeforeSave()">
                            <i class="fas fa-check"></i>
                            Erstellen und sofort prüfen
                        </button>
                    <?php endif; ?>
                    <a href="?page=links<?= $action === 'edit' ? "&action=view&id=$linkId" : '' ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
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

<?php endif; ?>

<script>
// Tab-Funktionalität
function showTab(tabName) {
    console.log('Wechsle zu Tab:', tabName); // Debug-Ausgabe
    
    // Alle Tabs verstecken
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Alle Tab-Buttons deaktivieren
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Gewählten Tab anzeigen
    const targetTab = document.getElementById(tabName + 'Tab');
    if (targetTab) {
        targetTab.style.display = 'block';
        console.log('Tab angezeigt:', tabName + 'Tab'); // Debug-Ausgabe
    } else {
        console.error('Tab nicht gefunden:', tabName + 'Tab'); // Fehler-Ausgabe
    }
    
    // Button aktivieren
    if (event && event.target) {
        event.target.classList.add('active');
    }
}

// Link-Prüfung mit visueller Rückmeldung
function checkLinkWithFeedback(button) {
    const originalText = button.innerHTML;
    const form = button.closest('form');
    
    // Button-Status ändern
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Prüfe Link...';
    button.disabled = true;
    
    // Nach dem Submit wieder aktivieren (falls Fehler)
    setTimeout(() => {
        if (button.disabled) {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }, 10000); // 10 Sekunden Timeout
    
    // Form normal absenden
    return true;
}

// Bulk-Check mit Progress
function startBulkCheck() {
    const button = document.getElementById('bulkCheckBtn');
    if (!button) return;
    
    if (!confirm('Möchten Sie alle Links prüfen? Dies kann einige Zeit dauern.')) {
        return;
    }
    
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Prüfe Links...';
    button.disabled = true;
    
    // Progress-Anzeige erstellen
    const progressDiv = document.createElement('div');
    progressDiv.id = 'bulkProgress';
    progressDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #2a2d42;
        border: 1px solid #4dabf7;
        border-radius: 8px;
        padding: 16px;
        color: #e2e8f0;
        z-index: 1000;
        min-width: 250px;
    `;
    progressDiv.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-spinner fa-spin" style="color: #4dabf7;"></i>
            <span>Starte Link-Prüfung...</span>
        </div>
    `;
    document.body.appendChild(progressDiv);
    
    // Form für Bulk-Check erstellen und absenden
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'action';
    input.value = 'bulk_check';
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// Kunden-Websites dynamisch aktualisieren
function updateCustomerWebsites() {
    const customerSelect = document.getElementById('customer_id');
    const websiteSelect = document.getElementById('customer_website_url');
    const targetUrlInput = document.getElementById('target_url');
    
    if (!customerSelect || !websiteSelect) return;
    
    const selectedOption = customerSelect.options[customerSelect.selectedIndex];
    const websites = selectedOption ? JSON.parse(selectedOption.dataset.websites || '[]') : [];
    
    // Website-Select zurücksetzen
    websiteSelect.innerHTML = '<option value="">Automatisch auswählen oder leer lassen</option>';
    
    // Websites hinzufügen
    websites.forEach(website => {
        const option = document.createElement('option');
        option.value = website.url;
        option.textContent = website.title || website.url;
        websiteSelect.appendChild(option);
    });
    
    // Automatisch erste Website als Ziel-URL vorschlagen (falls leer)
    if (websites.length > 0 && !targetUrlInput.value) {
        targetUrlInput.value = websites[0].url;
    }
}

// Link-Validierung vor dem Speichern
function validateBeforeSave() {
    const form = document.getElementById('linkForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Hidden input für sofortige Validierung hinzufügen
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'validate_immediately';
    input.value = '1';
    form.appendChild(input);
    
    form.submit();
}

// Debug-Funktionen
function toggleDebugInfo() {
    const debugSection = document.getElementById('debugInfo');
    const toggleButton = document.getElementById('debugToggle');
    
    if (debugSection && toggleButton) {
        if (debugSection.style.display === 'none') {
            debugSection.style.display = 'block';
            toggleButton.innerHTML = '<i class="fas fa-eye-slash"></i> Debug-Infos verbergen';
        } else {
            debugSection.style.display = 'none';
            toggleButton.innerHTML = '<i class="fas fa-eye"></i> Debug-Infos anzeigen';
        }
    }
}

// Such-Formular verzögert absenden
document.addEventListener('DOMContentLoaded', function() {
    console.log('LinkBuilder: Links-Seite geladen'); // Debug-Ausgabe
    
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                console.log('Suche wird ausgeführt:', searchInput.value);
                searchInput.form.submit();
            }, 1000);
        });
    }
    
    // Initiale Kunden-Website-Update
    updateCustomerWebsites();
    
    // URL-Auto-Format
    const urlInputs = document.querySelectorAll('input[type="url"]');
    urlInputs.forEach(input => {
        input.addEventListener('blur', function() {
            let url = this.value.trim();
            if (url && !url.startsWith('http://') && !url.startsWith('https://')) {
                this.value = 'https://' + url;
                console.log('URL automatisch formatiert:', this.value);
            }
        });
    });
    
    // Link-Prüfung Buttons erweitern
    const checkButtons = document.querySelectorAll('button[type="submit"]:contains("prüfen")');
    checkButtons.forEach(button => {
        button.addEventListener('click', function() {
            return checkLinkWithFeedback(this);
        });
    });
    
    // Prüfe ob wir auf der Link-Detail-Seite sind
    if (window.location.search.includes('action=view')) {
        console.log('Link-Detail-Seite erkannt');
        
        // Zeige Hinweis wenn noch nicht geprüft
        const validationTab = document.getElementById('validationTab');
        if (validationTab && validationTab.innerHTML.includes('noch nicht geprüft')) {
            console.log('Link wurde noch nicht geprüft - Hinweis wird angezeigt');
        }
    }
});
</script>

<style>
/* Link-spezifische Styles */
.link-meta {
    display: grid;
    gap: 16px;
}

.meta-item {
    padding-bottom: 12px;
    border-bottom: 1px solid #3a3d52;
}

.meta-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.meta-label {
    font-weight: 600;
    color: #8b8fa3;
    font-size: 12px;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.meta-value {
    color: #e2e8f0;
    font-size: 14px;
    line-height: 1.4;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #8b8fa3;
    z-index: 1;
}

.search-input {
    padding-left: 40px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

/* Filter-Controls responsiv */
.filter-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.filter-select {
    min-width: 160px;
    max-width: 200px;
    white-space: nowrap;
}

.action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    background-color: #2a2d42;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #3a3d52;
}

@media (max-width: 1200px) {
    .action-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .filter-controls {
        justify-content: flex-end;
        flex-wrap: wrap;
    }
}

@media (max-width: 768px) {
    .filter-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    
    .filter-select {
        width: 100%;
        max-width: none;
    }
    
    .search-bar {
        min-width: auto;
        max-width: none;
        width: 100%;
    }
}

/* Tabs Styling */
.tabs {
    display: flex;
    border-bottom: 2px solid #3a3d52;
    margin-bottom: 20px;
}

.tab {
    padding: 12px 24px;
    background: none;
    border: none;
    color: #8b8fa3;
    border-bottom: 2px solid transparent;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.tab:hover {
    color: #4dabf7;
}

.tab.active {
    color: #4dabf7;
    border-bottom-color: #4dabf7;
}

.tab-content {
    display: block;
}

/* Quick Actions */
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
</style>