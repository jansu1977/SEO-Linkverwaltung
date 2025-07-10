<?php
/**
 * LinkBuilder Pro - Link-Verwaltung (Vereinfachte Version)
 * pages/links.php - Funktioniert garantiert!
 */

// Error Reporting aktivieren für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
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

    // =============================================================================
    // DEBUG-ACTION: Muss VOR allen anderen Aktionen stehen!
    // =============================================================================
    if ($action === 'debug' && $linkId) {
        $links = loadData('links.json');
        if (isset($links[$linkId]) && ($isAdmin || $links[$linkId]['user_id'] === $userId)) {
            $link = $links[$linkId];
            
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
                    body { background: #1a1d2e; color: #e2e8f0; font-family: monospace; padding: 20px; }
                    .debug-section { background: #343852; padding: 20px; margin: 20px 0; border-radius: 8px; border: 1px solid #3a3d52; }
                    .debug-step { margin: 15px 0; padding: 10px; background: #2a2d42; border-radius: 4px; }
                    .success { color: #10b981; }
                    .error { color: #ef4444; }
                    .warning { color: #f59e0b; }
                    .info { color: #4dabf7; }
                    pre { background: #1a1d2e; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
                </style>
            </head>
            <body>
                <div style="max-width: 1200px; margin: 0 auto;">
                    <h1 style="color: #4dabf7;">🔍 Link-Debug-Analyse</h1>
                    <p><a href="?page=links&action=view&id=<?= $linkId ?>" style="color: #4dabf7;">← Zurück zum Link</a></p>
                    
                    <div class="debug-section">
                        <h2>📋 Link-Informationen</h2>
                        <p><strong>Ankertext:</strong> <?= htmlspecialchars($link['anchor_text']) ?></p>
                        <p><strong>Ziel-URL:</strong> <?= htmlspecialchars($link['target_url']) ?></p>
                        <p><strong>Backlink-URL:</strong> <?= htmlspecialchars($link['backlink_url']) ?></p>
                    </div>
                    
                    <div class="debug-section">
                        <h2>🚀 Live-Test</h2>
                        <?php
                        $backlinkUrl = $link['backlink_url'];
                        $targetUrl = $link['target_url'];
                        $anchorText = $link['anchor_text'];
                        
                        if (empty($backlinkUrl) || empty($targetUrl) || empty($anchorText)) {
                            echo '<p class="error">❌ Fehler: Fehlende Parameter</p>';
                        } else {
                            echo '<p class="success">✅ Alle Parameter vorhanden</p>';
                            
                            // HTTP-Test
                            echo '<div class="debug-step">';
                            echo '<h3>HTTP-Erreichbarkeit</h3>';
                            
                            // SCHRITT 1: HTTP-Erreichbarkeit prüfen
                            $headers = @get_headers($backlinkUrl, 1);
                            
                            if ($headers === false) {
                                echo '<p class="error">❌ URL nicht erreichbar</p>';
                            } else {
                                echo '<p class="success">✅ URL erreichbar</p>';
                                
                                // Status-Code extrahieren
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
                                    echo '<p class="warning">⚠️ HTTP-Status: ' . $statusCode . '</p>';
                                }
                                
                                // SCHRITT 2: HTML-Content laden (wenn Status OK oder Redirect)
                                if ($statusCode >= 200 && $statusCode < 400) {
                                    echo '</div><div class="debug-step">';
                                    echo '<h3>SCHRITT 2: HTML-Content laden</h3>';
                                    
                                    // Realistischer Browser User-Agent für bessere Kompatibilität
                                    $userAgents = [
                                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                                        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0'
                                    ];
                                    
                                    $selectedUserAgent = $userAgents[array_rand($userAgents)];
                                    
                                    echo '<p class="info">🤖 User-Agent: ' . htmlspecialchars($selectedUserAgent) . '</p>';
                                    
                                    // Vollständige Browser-Headers für maximale Kompatibilität
                                    $context = stream_context_create([
                                        'http' => [
                                            'method' => 'GET',
                                            'header' => [
                                                'User-Agent: ' . $selectedUserAgent,
                                                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                                                'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
                                                'Accept-Encoding: identity', // Keine Kompression für einfacheres Debugging
                                                'DNT: 1',
                                                'Connection: keep-alive',
                                                'Upgrade-Insecure-Requests: 1',
                                                'Sec-Fetch-Dest: document',
                                                'Sec-Fetch-Mode: navigate',
                                                'Sec-Fetch-Site: none',
                                                'Cache-Control: no-cache, no-store, must-revalidate',
                                                'Pragma: no-cache',
                                                'Expires: 0'
                                            ],
                                            'timeout' => 20,
                                            'follow_location' => true,
                                            'max_redirects' => 5,
                                            'ignore_errors' => false
                                        ]
                                    ]);
                                    
                                    echo '<p class="info">⏳ Lade HTML-Content...</p>';
                                    
                                    $htmlContent = @file_get_contents($backlinkUrl, false, $context);
                                    
                                    if ($htmlContent === false) {
                                        echo '<p class="error">❌ Konnte HTML-Inhalt nicht laden</p>';
                                        
                                        // Fallback: Nochmal mit Googlebot versuchen
                                        echo '<p class="info">🔄 Fallback: Versuche mit Googlebot User-Agent...</p>';
                                        
                                        $fallbackContext = stream_context_create([
                                            'http' => [
                                                'method' => 'GET',
                                                'header' => [
                                                    'User-Agent: Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                                                    'Accept: text/html,application/xhtml+xml',
                                                    'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
                                                    'Cache-Control: no-cache'
                                                ],
                                                'timeout' => 15,
                                                'follow_location' => true,
                                                'max_redirects' => 3,
                                                'ignore_errors' => true
                                            ]
                                        ]);
                                        
                                        $htmlContent = @file_get_contents($backlinkUrl, false, $fallbackContext);
                                        
                                        if ($htmlContent === false) {
                                            echo '<p class="error">❌ Auch Fallback-Request fehlgeschlagen</p>';
                                            echo '<div style="margin: 12px 0; padding: 12px; background: #2a2d42; border-radius: 4px;">';
                                            echo '<p class="warning"><strong>Mögliche Ursachen:</strong></p>';
                                            echo '<ul style="margin: 8px 0; padding-left: 20px; color: #8b8fa3; font-size: 13px;">';
                                            echo '<li>Server blockt automatisierte Anfragen (Bot-Detection)</li>';
                                            echo '<li>Rate-Limiting oder IP-basierte Beschränkungen</li>';
                                            echo '<li>Cloudflare oder ähnlicher Schutz aktiv</li>';
                                            echo '<li>Website erfordert JavaScript für Content-Laden</li>';
                                            echo '<li>Temporäre Server-Probleme</li>';
                                            echo '</ul>';
                                            echo '<p class="info" style="margin: 8px 0 0 0; font-size: 13px;">💡 <strong>Tipp:</strong> Versuchen Sie es später erneut oder prüfen Sie die URL manuell im Browser.</p>';
                                            echo '</div>';
                                        } else {
                                            echo '<p class="success">✅ Fallback erfolgreich!</p>';
                                        }
                                    } else {
                                        echo '<p class="success">✅ HTML-Content erfolgreich geladen</p>';
                                    }
                                    
                                    if ($htmlContent !== false) {
                                        $contentLength = strlen($htmlContent);
                                        echo '<p class="success">📄 HTML geladen: ' . number_format($contentLength) . ' Zeichen</p>';
                                        
                                        // Quick Content-Check
                                        $isEmptyContent = $contentLength < 100;
                                        $containsError = (stripos($htmlContent, 'error') !== false || 
                                                         stripos($htmlContent, '404') !== false || 
                                                         stripos($htmlContent, 'not found') !== false);
                                        
                                        if ($isEmptyContent) {
                                            echo '<p class="warning">⚠️ Warnung: Sehr wenig Content geladen (< 100 Zeichen)</p>';
                                        }
                                        
                                        if ($containsError) {
                                            echo '<p class="warning">⚠️ Warnung: Content enthält möglicherweise Fehlermeldungen</p>';
                                        }
                                        
                                        // SCHRITT 3: Link-Analyse
                                        echo '</div><div class="debug-step">';
                                        echo '<h3>SCHRITT 3: Link-Analyse</h3>';
                                        
                                        // URL-Normalisierung für besseres Matching
                                        $normalizeUrl = function($url) {
                                            $url = trim($url);
                                            $url = rtrim($url, '/'); // Trailing Slash entfernen
                                            return strtolower($url);
                                        };
                                        
                                        $normalizedTargetUrl = $normalizeUrl($targetUrl);
                                        $normalizedAnchorText = trim(strtolower($anchorText));
                                        
                                        echo '<p class="info">🎯 Suche nach:</p>';
                                        echo '<ul>';
                                        echo '<li><strong>URL:</strong> ' . htmlspecialchars($normalizedTargetUrl) . '</li>';
                                        echo '<li><strong>Ankertext:</strong> "' . htmlspecialchars($normalizedAnchorText) . '"</li>';
                                        echo '</ul>';
                                        
                                        // Erweiterte URL-Varianten für besseres Matching
                                        $urlVariants = [
                                            $normalizedTargetUrl,
                                            $normalizedTargetUrl . '/',
                                            str_replace('https://', 'http://', $normalizedTargetUrl),
                                            str_replace('http://', 'https://', $normalizedTargetUrl),
                                            str_replace('www.', '', $normalizedTargetUrl),
                                            'www.' . str_replace('www.', '', $normalizedTargetUrl)
                                        ];
                                        $urlVariants = array_unique($urlVariants);
                                        
                                        echo '<p class="info">🔍 URL-Varianten (' . count($urlVariants) . '): ' . htmlspecialchars(implode(', ', array_slice($urlVariants, 0, 3))) . '...</p>';
                                        
                                        // Regex für <a> Tags (robuster)
                                        $patterns = [
                                            '/<a\s[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',  // Standard
                                            '/<a\s[^>]*href\s*=\s*([^\s>]+)[^>]*>(.*?)<\/a>/is'            // Ohne Anführungszeichen
                                        ];
                                        
                                        $allMatches = [];
                                        foreach ($patterns as $pattern) {
                                            $matches = [];
                                            if (preg_match_all($pattern, $htmlContent, $matches, PREG_SET_ORDER)) {
                                                $allMatches = array_merge($allMatches, $matches);
                                            }
                                        }
                                        
                                        // Duplikate entfernen
                                        $uniqueMatches = [];
                                        $seen = [];
                                        foreach ($allMatches as $match) {
                                            $key = trim($match[1]) . '|' . trim(strip_tags($match[2]));
                                            if (!isset($seen[$key])) {
                                                $uniqueMatches[] = $match;
                                                $seen[$key] = true;
                                            }
                                        }
                                        
                                        $matchCount = count($uniqueMatches);
                                        echo '<p class="info">🔗 Gefundene &lt;a&gt; Tags: ' . $matchCount . '</p>';
                                        
                                        if ($matchCount > 0) {
                                            $foundTargetLink = false;
                                            $linkResults = [];
                                            $perfectMatches = 0;
                                            $urlMatches = 0;
                                            $textMatches = 0;
                                            
                                            foreach ($uniqueMatches as $i => $match) {
                                                $href = trim($match[1]);
                                                $text = trim(strip_tags($match[2]));
                                                
                                                // URL-Normalisierung
                                                $normalizedHref = $normalizeUrl($href);
                                                $normalizedText = trim(strtolower($text));
                                                
                                                // Matching-Logik
                                                $hrefMatch = false;
                                                $textMatch = false;
                                                
                                                // URL-Matching (gegen alle Varianten prüfen)
                                                foreach ($urlVariants as $variant) {
                                                    if ($normalizedHref === $variant || 
                                                        strpos($normalizedHref, $variant) !== false ||
                                                        strpos($variant, $normalizedHref) !== false) {
                                                        $hrefMatch = true;
                                                        break;
                                                    }
                                                }
                                                
                                                // Text-Matching (flexibler)
                                                if ($normalizedText === $normalizedAnchorText || 
                                                    strpos($normalizedText, $normalizedAnchorText) !== false ||
                                                    strpos($normalizedAnchorText, $normalizedText) !== false ||
                                                    // Zusätzlich: URL als Ankertext
                                                    strpos($normalizedText, $normalizedTargetUrl) !== false) {
                                                    $textMatch = true;
                                                }
                                                
                                                $isPerfectMatch = $hrefMatch && $textMatch;
                                                
                                                $linkResults[] = [
                                                    'href' => $href,
                                                    'text' => $text,
                                                    'hrefMatch' => $hrefMatch,
                                                    'textMatch' => $textMatch,
                                                    'perfectMatch' => $isPerfectMatch
                                                ];
                                                
                                                if ($isPerfectMatch) {
                                                    $foundTargetLink = true;
                                                    $perfectMatches++;
                                                }
                                                if ($hrefMatch) $urlMatches++;
                                                if ($textMatch) $textMatches++;
                                            }
                                            
                                            // ERGEBNIS anzeigen
                                            echo '<div style="margin: 20px 0; padding: 16px; background: #2a2d42; border-radius: 6px;">';
                                            if ($foundTargetLink) {
                                                echo '<p class="success" style="font-size: 18px; margin: 0;"><strong>🎯 ✅ LINK GEFUNDEN!</strong></p>';
                                                echo '<p class="success">Der gesuchte Link ist auf der Seite vorhanden und korrekt verlinkt.</p>';
                                                echo '<p class="info">Perfekte Matches: ' . $perfectMatches . '</p>';
                                            } else {
                                                echo '<p class="warning" style="font-size: 18px; margin: 0;"><strong>⚠️ LINK NICHT GEFUNDEN</strong></p>';
                                                echo '<p class="warning">Der gesuchte Link wurde auf der Seite nicht gefunden.</p>';
                                                if ($urlMatches > 0 || $textMatches > 0) {
                                                    echo '<p class="info">Teilweise Matches gefunden: ' . $urlMatches . ' URL-Matches, ' . $textMatches . ' Text-Matches</p>';
                                                }
                                            }
                                            echo '</div>';
                                            
                                            // Details zu gefundenen Links (erste 15)
                                            echo '<h4>🔗 Link-Details (erste 15 Links):</h4>';
                                            echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #3a3d52; border-radius: 4px;">';
                                            
                                            foreach (array_slice($linkResults, 0, 15) as $i => $result) {
                                                $borderColor = $result['perfectMatch'] ? '#10b981' : 
                                                             ($result['hrefMatch'] || $result['textMatch'] ? '#f59e0b' : '#6b7280');
                                                
                                                echo '<div style="margin: 0; padding: 12px; border-bottom: 1px solid #3a3d52; border-left: 4px solid ' . $borderColor . ';">';
                                                echo '<div style="font-size: 13px; margin-bottom: 4px;">';
                                                echo '<strong>Link #' . ($i+1) . ':</strong>';
                                                if ($result['perfectMatch']) {
                                                    echo ' <span class="success">🎯 PERFEKTER MATCH!</span>';
                                                } elseif ($result['hrefMatch']) {
                                                    echo ' <span class="info">🔗 URL-Match</span>';
                                                } elseif ($result['textMatch']) {
                                                    echo ' <span class="info">📝 Text-Match</span>';
                                                }
                                                echo '</div>';
                                                
                                                echo '<div style="font-size: 12px; font-family: monospace; margin-bottom: 4px;">';
                                                echo '<strong>URL:</strong> ' . htmlspecialchars(strlen($result['href']) > 60 ? substr($result['href'], 0, 60) . '...' : $result['href']) . ' ';
                                                echo $result['hrefMatch'] ? '<span class="success">✅</span>' : '<span class="error">❌</span>';
                                                echo '</div>';
                                                
                                                echo '<div style="font-size: 12px; font-family: monospace;">';
                                                echo '<strong>Text:</strong> "' . htmlspecialchars(strlen($result['text']) > 60 ? substr($result['text'], 0, 60) . '...' : $result['text']) . '" ';
                                                echo $result['textMatch'] ? '<span class="success">✅</span>' : '<span class="error">❌</span>';
                                                echo '</div>';
                                                echo '</div>';
                                            }
                                            echo '</div>';
                                            
                                            // Zusammenfassung
                                            echo '<div style="margin-top: 20px; padding: 15px; background: #343852; border-radius: 6px;">';
                                            echo '<h4>📊 Zusammenfassung:</h4>';
                                            echo '<ul style="margin: 0; padding-left: 20px;">';
                                            echo '<li>Gefundene Links: ' . count($linkResults) . '</li>';
                                            echo '<li>URL-Matches: ' . $urlMatches . '</li>';
                                            echo '<li>Text-Matches: ' . $textMatches . '</li>';
                                            echo '<li>Perfekte Matches: ' . $perfectMatches . '</li>';
                                            echo '</ul>';
                                            
                                            echo '<div style="margin-top: 12px; padding: 12px; background: #2a2d42; border-radius: 4px;">';
                                            if ($foundTargetLink) {
                                                echo '<p class="success" style="margin: 0;"><strong>🎯 ERGEBNIS: LINK AKTIV</strong></p>';
                                                echo '<p style="margin: 4px 0 0 0; font-size: 12px; color: #8b8fa3;">Der Link wurde gefunden und ist korrekt verlinkt.</p>';
                                            } else {
                                                echo '<p class="warning" style="margin: 0;"><strong>⚠️ ERGEBNIS: LINK DEFEKT/AUSSTEHEND</strong></p>';
                                                echo '<p style="margin: 4px 0 0 0; font-size: 12px; color: #8b8fa3;">Der Link wurde nicht gefunden oder ist nicht korrekt verlinkt.</p>';
                                            }
                                            echo '</div>';
                                            echo '</div>';
                                            
                                        } else {
                                            echo '<p class="error">❌ Keine &lt;a&gt; Tags auf der Seite gefunden</p>';
                                            echo '<div style="margin: 12px 0; padding: 12px; background: #2a2d42; border-radius: 4px;">';
                                            echo '<p class="info"><strong>Mögliche Ursachen:</strong></p>';
                                            echo '<ul style="margin: 8px 0; padding-left: 20px; color: #8b8fa3; font-size: 13px;">';
                                            echo '<li>Die Seite verwendet JavaScript für Link-Generierung</li>';
                                            echo '<li>Links sind in iFrames oder externen Widgets</li>';
                                            echo '<li>Ungewöhnliche HTML-Struktur oder CSS-Links</li>';
                                            echo '<li>Content ist hinter Login/Paywall versteckt</li>';
                                            echo '</ul>';
                                            echo '</div>';
                                        }
                                    }
                                }
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <div style="margin: 30px 0; text-align: center;">
                        <a href="?page=links&action=view&id=<?= $linkId ?>" class="btn btn-primary" style="display: inline-block; padding: 12px 24px; background: #4dabf7; color: white; text-decoration: none; border-radius: 6px;">
                            ← Zurück zum Link
                        </a>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit; // WICHTIG: Hier beenden wir die Ausführung komplett!
        } else {
            // Falls Link nicht gefunden oder keine Berechtigung
            echo '<div class="alert alert-danger">Link nicht gefunden oder keine Berechtigung.</div>';
            echo '<a href="?page=links" class="btn btn-primary">← Zurück zu Links</a>';
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
                                    <th>Erstellt</th>
                                    <th style="width: 140px;">Aktionen</th>
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
                                            <?= isset($link['created_at']) ? date('d.m.Y', strtotime($link['created_at'])) : '-' ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 4px;">
                                                <a href="?page=links&action=view&id=<?= $linkId ?>" class="btn btn-sm btn-primary" title="Anzeigen">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?page=links&action=debug&id=<?= $linkId ?>" class="btn btn-sm btn-warning" title="Debug">
                                                    <i class="fas fa-bug"></i>
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
                <a href="?page=links&action=debug&id=<?= $linkId ?>" class="btn btn-warning">
                    <i class="fas fa-bug"></i> Debug-Analyse
                </a>
                <a href="?page=links&action=edit&id=<?= $linkId ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Bearbeiten
                </a>
            </div>
        </div>

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
                            <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">KUNDE</div>
                            <div style="color: #e2e8f0;">
                                <?php if ($customer): ?>
                                    <a href="?page=customers&action=view&id=<?= $link['customer_id'] ?>" style="color: #4dabf7;">
                                        <?= htmlspecialchars($customer['name']) ?>
                                    </a>
                                    <?php if (!empty($customer['company'])): ?>
                                        <br><small style="color: #8b8fa3;"><?= htmlspecialchars($customer['company']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #ef4444;">Kunde nicht gefunden</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <div style="font-weight: 600; color: #8b8fa3; font-size: 12px; margin-bottom: 4px;">BLOG</div>
                            <div style="color: #e2e8f0;">
                                <?php if ($blog): ?>
                                    <a href="?page=blogs&action=view&id=<?= $link['blog_id'] ?>" style="color: #4dabf7;">
                                        <?= htmlspecialchars($blog['name']) ?>
                                    </a>
                                    <br><small style="color: #8b8fa3;"><?= htmlspecialchars($blog['url']) ?></small>
                                <?php else: ?>
                                    <span style="color: #ef4444;">Blog nicht gefunden</span>
                                <?php endif; ?>
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

<?php

} catch (Exception $e) {
    // Fehlerbehandlung
    echo '<div class="alert alert-danger">';
    echo '<h3>Ein Fehler ist aufgetreten:</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>Datei:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Zeile:</strong> ' . $e->getLine() . '</p>';
    echo '</div>';
    echo '<a href="?page=dashboard" class="btn btn-primary">← Zurück zum Dashboard</a>';
}

?>