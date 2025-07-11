<?php
/**
 * Themenverwaltung mit Farben - KORRIGIERTE VERSION
 * pages/topics.php
 */

$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$topicId = isset($_GET['id']) ? trim($_GET['id']) : null;
$userId = getCurrentUserId();

// Debug: Parameter-Validierung
if (isset($_GET['debug']) && ($action === 'edit' || $action === 'delete')) {
    error_log("Topics Debug - Action: $action, Topic ID: " . ($topicId ?? 'NULL') . ", User ID: $userId");
}

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#4dabf7');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            $error = 'Themen-Name ist erforderlich.';
        } else {
            $topics = loadData('topics.json');
            
            // Prüfen ob Thema bereits existiert
            $nameExists = false;
            foreach ($topics as $existingTopic) {
                if (isset($existingTopic['user_id']) && $existingTopic['user_id'] === $userId 
                    && isset($existingTopic['name']) && strtolower($existingTopic['name']) === strtolower($name)) {
                    $nameExists = true;
                    break;
                }
            }
            
            if ($nameExists) {
                $error = 'Ein Thema mit diesem Namen existiert bereits.';
            } else {
                $newId = generateId();
                
                $topics[$newId] = array(
                    'id' => $newId,
                    'user_id' => $userId,
                    'name' => $name,
                    'color' => $color,
                    'description' => $description,
                    'created_at' => date('Y-m-d H:i:s'),
                    'usage_count' => 0
                );
                
                if (saveData('topics.json', $topics)) {
                    redirectWithMessage('?page=topics', 'Thema "' . $name . '" erfolgreich erstellt.');
                } else {
                    $error = 'Fehler beim Speichern des Themas.';
                }
            }
        }
    } elseif ($action === 'edit' && $topicId) {
        $topics = loadData('topics.json');
        if (isset($topics[$topicId]) && isset($topics[$topicId]['user_id']) && $topics[$topicId]['user_id'] === $userId) {
            $name = trim($_POST['name'] ?? '');
            $color = trim($_POST['color'] ?? '#4dabf7');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                $error = 'Themen-Name ist erforderlich.';
            } else {
                // Prüfen ob neuer Name bereits existiert (außer bei sich selbst)
                $nameExists = false;
                foreach ($topics as $existingId => $existingTopic) {
                    if ($existingId !== $topicId 
                        && isset($existingTopic['user_id']) && $existingTopic['user_id'] === $userId 
                        && isset($existingTopic['name']) && strtolower($existingTopic['name']) === strtolower($name)) {
                        $nameExists = true;
                        break;
                    }
                }
                
                if ($nameExists) {
                    $error = 'Ein Thema mit diesem Namen existiert bereits.';
                } else {
                    $topics[$topicId]['name'] = $name;
                    $topics[$topicId]['color'] = $color;
                    $topics[$topicId]['description'] = $description;
                    $topics[$topicId]['updated_at'] = date('Y-m-d H:i:s');
                    
                    if (saveData('topics.json', $topics)) {
                        redirectWithMessage('?page=topics', 'Thema "' . $name . '" erfolgreich aktualisiert.');
                    } else {
                        $error = 'Fehler beim Aktualisieren des Themas.';
                    }
                }
            }
        } else {
            $error = 'Thema nicht gefunden oder Sie haben keine Berechtigung.';
        }
    } elseif ($action === 'delete' && $topicId) {
        $topics = loadData('topics.json');
        
        // Debug-Informationen
        if (isset($_GET['debug'])) {
            error_log("Delete Debug - Topic ID: $topicId, Topics available: " . implode(', ', array_keys($topics)));
        }
        
        if (isset($topics[$topicId]) && isset($topics[$topicId]['user_id']) && $topics[$topicId]['user_id'] === $userId) {
            $topicName = $topics[$topicId]['name'] ?? 'Unbekannt';
            
            // Thema aus Array entfernen
            unset($topics[$topicId]);
            
            if (saveData('topics.json', $topics)) {
                redirectWithMessage('?page=topics', 'Thema "' . htmlspecialchars($topicName) . '" erfolgreich gelöscht.');
            } else {
                $error = 'Fehler beim Löschen des Themas.';
            }
        } else {
            $error = 'Thema nicht gefunden oder Sie haben keine Berechtigung zum Löschen.';
            if (isset($_GET['debug'])) {
                if (!isset($topics[$topicId])) {
                    $error .= " (Topic ID $topicId existiert nicht)";
                } elseif (!isset($topics[$topicId]['user_id'])) {
                    $error .= " (Topic hat keine User ID)";
                } elseif ($topics[$topicId]['user_id'] !== $userId) {
                    $error .= " (User ID Mismatch: " . ($topics[$topicId]['user_id'] ?? 'NULL') . " vs $userId)";
                }
            }
        }
    }
}

// Daten laden und validieren
$topics = loadData('topics.json');
$blogs = loadData('blogs.json');
$customers = loadData('customers.json');

// Datenintegrität prüfen und korrigieren
$correctedTopics = array();
foreach ($topics as $topicId => $topic) {
    // Nur vollständige und gültige Themen übernehmen
    if (isset($topic['id'], $topic['user_id'], $topic['name']) && !empty($topic['name'])) {
        $correctedTopics[$topicId] = $topic;
    }
}

// Falls Korrekturen gemacht wurden, Datei aktualisieren
if (count($correctedTopics) !== count($topics)) {
    saveData('topics.json', $correctedTopics);
    $topics = $correctedTopics;
}

// Benutzer-spezifische Themen
$userTopics = array();
foreach ($topics as $topicId => $topic) {
    if (isset($topic['user_id']) && $topic['user_id'] === $userId) {
        $userTopics[$topicId] = $topic;
    }
}

// Themen nach Erstellungsdatum sortieren für konsistente Anzeige
$sortedTopics = $userTopics;
uasort($sortedTopics, function($a, $b) {
    return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
});

// Nutzungsstatistiken berechnen
foreach ($sortedTopics as $topicId => &$topic) {
    $usageCount = 0;
    $topicName = $topic['name'] ?? '';
    
    if (!empty($topicName)) {
        // In Blogs zählen
        foreach ($blogs as $blog) {
            if (isset($blog['user_id']) && $blog['user_id'] === $userId && isset($blog['topics']) && is_array($blog['topics'])) {
                if (in_array($topicName, $blog['topics'])) {
                    $usageCount++;
                }
            }
        }
        
        // In Kunden zählen
        foreach ($customers as $customer) {
            if (isset($customer['user_id']) && $customer['user_id'] === $userId && isset($customer['topics']) && is_array($customer['topics'])) {
                if (in_array($topicName, $customer['topics'])) {
                    $usageCount++;
                }
            }
        }
    }
    
    $topic['usage_count'] = $usageCount;
    
    // Auch im ursprünglichen Array aktualisieren
    if (isset($userTopics[$topicId])) {
        $userTopics[$topicId]['usage_count'] = $usageCount;
    }
}

// Vordefinierte Farbpalette
$colorPalette = array(
    '#4dabf7', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', 
    '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1',
    '#14b8a6', '#eab308', '#f43f5e', '#a855f7', '#3b82f6',
    '#22c55e', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2'
);

if ($action === 'index'): ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">Themenverwaltung</h1>
            <p class="page-subtitle">Verwalten Sie Themen für Blogs und Kunden</p>
        </div>
        <div class="action-buttons">
            <a href="?page=topics&action=create" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thema hinzufügen
            </a>
        </div>
    </div>

    <?php showFlashMessage(); ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Debug für URL-Parameter -->
    <?php if (isset($_GET['debug']) && $action === 'index'): ?>
        <div class="alert alert-warning" style="margin-bottom: 20px;">
            <h4>URL-Parameter Debug</h4>
            <div style="font-family: monospace; font-size: 12px;">
                <div><strong>$_GET:</strong> <?= htmlspecialchars(json_encode($_GET)) ?></div>
                <div><strong>Action:</strong> <?= htmlspecialchars($action) ?></div>
                <div><strong>Topic ID:</strong> <?= htmlspecialchars($topicId ?? 'NULL') ?></div>
                <div><strong>Current User ID:</strong> <?= htmlspecialchars($userId) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Debug Information (nur bei Entwicklung anzeigen) -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
        <div class="alert alert-info" style="margin-bottom: 20px;">
            <h4>Debug-Informationen</h4>
            <p><strong>Anzahl geladener Themen:</strong> <?= count($topics) ?></p>
            <p><strong>Anzahl Benutzer-Themen:</strong> <?= count($userTopics) ?></p>
            <p><strong>User ID:</strong> <?= htmlspecialchars($userId) ?></p>
            
            <h5 style="margin-top: 16px;">Themen-Vergleich:</h5>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 10px;">
                <div>
                    <strong>Alle geladenen Themen:</strong>
                    <pre style="background: #f4f4f4; padding: 10px; margin: 10px 0; border-radius: 4px; overflow: auto; max-height: 200px; font-size: 11px;"><?= htmlspecialchars(json_encode($topics, JSON_PRETTY_PRINT)) ?></pre>
                </div>
                <div>
                    <strong>Gefilterte Benutzer-Themen:</strong>
                    <pre style="background: #f4f4f4; padding: 10px; margin: 10px 0; border-radius: 4px; overflow: auto; max-height: 200px; font-size: 11px;"><?= htmlspecialchars(json_encode($userTopics, JSON_PRETTY_PRINT)) ?></pre>
                </div>
            </div>
            
            <h5 style="margin-top: 16px;">Einzelprüfung der problematischen ID:</h5>
            <?php 
            $problemId = '68718cf14384b1.99354540';
            if (isset($topics[$problemId])): 
                $problemTopic = $topics[$problemId];
            ?>
                <div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0;">
                    <strong>Rohe Daten aus topics.json für ID <?= $problemId ?>:</strong><br>
                    Name: "<?= htmlspecialchars($problemTopic['name'] ?? 'NULL') ?>"<br>
                    Farbe: "<?= htmlspecialchars($problemTopic['color'] ?? 'NULL') ?>"<br>
                    User ID: "<?= htmlspecialchars($problemTopic['user_id'] ?? 'NULL') ?>"<br>
                    Vollständige Daten: <?= htmlspecialchars(json_encode($problemTopic)) ?>
                </div>
            <?php else: ?>
                <div style="background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0;">
                    <strong>FEHLER:</strong> Thema mit ID <?= $problemId ?> nicht in topics.json gefunden!
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Statistiken -->
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4dabf7 0%, #3b9ae1 100%);">
                <i class="fas fa-tags"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo count($userTopics); ?></div>
                <div class="stat-label">Gesamt Themen</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">
                    <?php 
                    $totalUsage = 0;
                    foreach ($userTopics as $topic) {
                        $totalUsage += intval($topic['usage_count'] ?? 0);
                    }
                    echo $totalUsage;
                    ?>
                </div>
                <div class="stat-label">Verwendungen</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <i class="fas fa-palette"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">
                    <?php 
                    $uniqueColors = array_unique(array_column($userTopics, 'color'));
                    echo count($uniqueColors);
                    ?>
                </div>
                <div class="stat-label">Verschiedene Farben</div>
            </div>
        </div>
    </div>

    <?php if (empty($userTopics)): ?>
        <div class="empty-state">
            <i class="fas fa-tags"></i>
            <h3>Keine Themen vorhanden</h3>
            <p>Erstellen Sie Ihr erstes Thema, um hier eine Übersicht zu sehen.</p>
            <a href="?page=topics&action=create" class="btn btn-primary" style="margin-top: 16px;">
                <i class="fas fa-plus"></i> Erstes Thema erstellen
            </a>
        </div>
    <?php else: ?>
        <!-- Themen-Grid -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ihre Themen</h3>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <p class="card-subtitle">Alle erstellten Themen mit Farbzuordnung</p>
                    <?php if (isset($_GET['debug'])): ?>
                        <a href="?page=topics" class="btn btn-sm btn-secondary">Debug ausblenden</a>
                    <?php else: ?>
                        <a href="?page=topics&debug=1" class="btn btn-sm btn-info">Debug anzeigen</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="topics-grid">
                    <?php 
                    // Themen nach Erstellungsdatum sortieren (neueste zuerst)
                    $sortedTopics = $userTopics;
                    uasort($sortedTopics, function($a, $b) {
                        return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
                    });
                    
                    foreach ($sortedTopics as $currentTopicId => $currentTopic): 
                        // Debug: Aktuelle Werte direkt aus dem Array extrahieren
                        $displayName = isset($currentTopic['name']) ? $currentTopic['name'] : 'Unbenannt';
                        $displayColor = isset($currentTopic['color']) ? $currentTopic['color'] : '#4dabf7';
                        $displayDescription = isset($currentTopic['description']) ? $currentTopic['description'] : '';
                        $displayUsageCount = isset($currentTopic['usage_count']) ? intval($currentTopic['usage_count']) : 0;
                        $displayCreatedAt = isset($currentTopic['created_at']) ? $currentTopic['created_at'] : date('Y-m-d H:i:s');
                    ?>
                        <div class="topic-item" data-topic-id="<?= htmlspecialchars($currentTopicId) ?>">
                            <div class="topic-color" style="background-color: <?= htmlspecialchars($displayColor) ?>;">
                                <div class="topic-color-overlay">
                                    <div class="topic-actions">
                                        <a href="?page=topics&action=edit&id=<?= urlencode($currentTopicId) ?>" class="topic-action-btn" title="Bearbeiten">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?page=topics&action=delete&id=<?= urlencode($currentTopicId) ?>" class="topic-action-btn topic-delete-btn" title="Löschen" 
                                           onclick="return confirm('Thema &quot;<?= htmlspecialchars($displayName) ?>&quot; wirklich löschen?\n\nID: <?= htmlspecialchars($currentTopicId) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="topic-content">
                                <h4 class="topic-name"><?= htmlspecialchars($displayName) ?></h4>
                                <?php if (!empty($displayDescription)): ?>
                                    <p class="topic-description"><?= htmlspecialchars($displayDescription) ?></p>
                                <?php endif; ?>
                                
                                <div class="topic-meta">
                                    <span class="usage-count">
                                        <i class="fas fa-chart-bar"></i>
                                        <?= $displayUsageCount ?> Verwendungen
                                    </span>
                                    <span class="created-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= formatDate($displayCreatedAt) ?>
                                    </span>
                                </div>
                                
                                <?php if (isset($_GET['debug'])): ?>
                                    <div style="margin-top: 8px; padding: 8px; background: #2a2d3e; border-radius: 4px; font-size: 11px; font-family: monospace; color: #e4e4e7;">
                                        <div><strong>Array-Key:</strong> <?= htmlspecialchars($currentTopicId) ?></div>
                                        <div><strong>Topic-ID:</strong> <?= htmlspecialchars($currentTopic['id'] ?? 'NULL') ?></div>
                                        <div><strong>Display-Name:</strong> <?= htmlspecialchars($displayName) ?></div>
                                        <div><strong>Display-Farbe:</strong> <?= htmlspecialchars($displayColor) ?></div>
                                        <div><strong>User ID:</strong> <?= htmlspecialchars($currentTopic['user_id'] ?? 'NULL') ?></div>
                                        <div><strong>JSON-Data:</strong> <?= htmlspecialchars(json_encode($currentTopic)) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Themen-Vorschau -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Themen-Vorschau</h3>
                <p class="card-subtitle">So werden Ihre Themen in der gesamten Anwendung angezeigt</p>
            </div>
            <div class="card-body">
                <div class="topic-preview">
                    <h4 style="margin-bottom: 16px;">Als Badges:</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px;">
                        <?php foreach ($sortedTopics as $currentTopicId => $currentTopic): 
                            $displayName = isset($currentTopic['name']) ? $currentTopic['name'] : 'Unbenannt';
                            $displayColor = isset($currentTopic['color']) ? $currentTopic['color'] : '#4dabf7';
                        ?>
                            <span class="topic-badge" style="background-color: <?= htmlspecialchars($displayColor) ?>;">
                                <?= htmlspecialchars($displayName) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    
                    <h4 style="margin-bottom: 16px;">Als Tags:</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($sortedTopics as $currentTopicId => $currentTopic): 
                            $displayName = isset($currentTopic['name']) ? $currentTopic['name'] : 'Unbenannt';
                            $displayColor = isset($currentTopic['color']) ? $currentTopic['color'] : '#4dabf7';
                        ?>
                            <span class="topic-tag" style="border-color: <?= htmlspecialchars($displayColor) ?>; color: <?= htmlspecialchars($displayColor) ?>;">
                                #<?= htmlspecialchars($displayName) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'create' || ($action === 'edit' && $topicId)): 
    $topic = null;
    $debugInfo = '';
    
    if ($action === 'edit') {
        // Debug: Zeige alle verfügbaren IDs
        $debugInfo .= "Gesuchte Topic-ID: " . htmlspecialchars($topicId) . "\n";
        $debugInfo .= "Verfügbare Topic-IDs: " . implode(', ', array_keys($topics)) . "\n";
        
        // Prüfe explizit, ob die ID existiert
        if (isset($topics[$topicId])) {
            $topic = $topics[$topicId];
            $debugInfo .= "Thema gefunden: " . json_encode($topic) . "\n";
            
            // Prüfe Berechtigung
            if (!isset($topic['user_id']) || $topic['user_id'] !== $userId) {
                $debugInfo .= "Berechtigung verweigert. Topic User ID: " . ($topic['user_id'] ?? 'NULL') . ", Current User ID: " . $userId . "\n";
                header('HTTP/1.0 403 Forbidden');
                echo '<div class="alert alert-danger">Sie haben keine Berechtigung, dieses Thema zu bearbeiten.</div>';
                return;
            }
        } else {
            $debugInfo .= "Thema mit ID nicht gefunden!\n";
            header('HTTP/1.0 404 Not Found');
            echo '<div class="alert alert-danger">Thema nicht gefunden.</div>';
            return;
        }
    }
?>
    <div class="breadcrumb">
        <a href="?page=topics">Zurück zu Themen</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= $action === 'create' ? 'Thema hinzufügen' : 'Thema bearbeiten' ?></span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title"><?= $action === 'create' ? 'Neues Thema hinzufügen' : 'Thema bearbeiten' ?></h1>
            <p class="page-subtitle">
                <?= $action === 'create' ? 'Erstellen Sie ein Thema mit individueller Farbzuordnung' : 'Bearbeiten Sie das Thema "' . htmlspecialchars($topic['name'] ?? 'Unbekannt') . '"' ?>
            </p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Debug-Informationen für Edit-Modus -->
    <?php if ($action === 'edit' && isset($_GET['debug'])): ?>
        <div class="alert alert-info">
            <h4>Debug-Informationen (Edit-Modus)</h4>
            <pre style="background: #f4f4f4; padding: 10px; border-radius: 4px; font-size: 11px;"><?= htmlspecialchars($debugInfo) ?></pre>
            <div style="margin-top: 10px;">
                <strong>URL Topic ID:</strong> <?= htmlspecialchars($_GET['id'] ?? 'NULL') ?><br>
                <strong>Verarbeitete Topic ID:</strong> <?= htmlspecialchars($topicId ?? 'NULL') ?><br>
                <strong>Gefundenes Thema:</strong> <?= $topic ? 'JA' : 'NEIN' ?><br>
                <?php if ($topic): ?>
                    <strong>Thema-Name:</strong> <?= htmlspecialchars($topic['name'] ?? 'NULL') ?><br>
                    <strong>Thema-Farbe:</strong> <?= htmlspecialchars($topic['color'] ?? 'NULL') ?><br>
                    <strong>Thema-User-ID:</strong> <?= htmlspecialchars($topic['user_id'] ?? 'NULL') ?><br>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" id="topicForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Themen-Name *</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-control" 
                            placeholder="z.B. SEO, Marketing, Webentwicklung"
                            value="<?= htmlspecialchars($_POST['name'] ?? ($topic['name'] ?? '')) ?>"
                            required
                            autocomplete="off"
                        >
                        <?php if ($action === 'edit' && isset($_GET['debug'])): ?>
                            <small style="color: #666; font-size: 11px;">
                                Debug: Wert aus Array: "<?= htmlspecialchars($topic['name'] ?? 'NULL') ?>"
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="color" class="form-label">Farbe</label>
                        <div class="color-input-group">
                            <input 
                                type="color" 
                                id="color" 
                                name="color" 
                                class="form-control color-picker" 
                                value="<?= htmlspecialchars($_POST['color'] ?? ($topic['color'] ?? '#4dabf7')) ?>"
                            >
                            <input 
                                type="text" 
                                id="color-text" 
                                class="form-control color-text" 
                                value="<?= htmlspecialchars($_POST['color'] ?? ($topic['color'] ?? '#4dabf7')) ?>"
                                readonly
                            >
                        </div>
                        <?php if ($action === 'edit' && isset($_GET['debug'])): ?>
                            <small style="color: #666; font-size: 11px;">
                                Debug: Farbe aus Array: "<?= htmlspecialchars($topic['color'] ?? 'NULL') ?>"
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Beschreibung</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        rows="3"
                        placeholder="Optionale Beschreibung des Themas"
                    ><?= htmlspecialchars($_POST['description'] ?? ($topic['description'] ?? '')) ?></textarea>
                </div>

                <!-- Farbpalette -->
                <div class="form-group">
                    <label class="form-label">Farbvorschläge</label>
                    <div class="color-palette">
                        <?php foreach ($colorPalette as $paletteColor): ?>
                            <div class="color-option" 
                                 style="background-color: <?= htmlspecialchars($paletteColor) ?>;" 
                                 onclick="selectColor('<?= htmlspecialchars($paletteColor) ?>')"
                                 title="<?= htmlspecialchars($paletteColor) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Live-Vorschau -->
                <div class="form-group">
                    <label class="form-label">Vorschau</label>
                    <div class="topic-preview-container">
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <span class="topic-badge preview-badge" id="previewBadge">Themen-Name</span>
                            <span class="topic-tag preview-tag" id="previewTag">#Themen-Name</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        <?= $action === 'create' ? 'Thema erstellen' : 'Änderungen speichern' ?>
                    </button>
                    <a href="?page=topics" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                    <?php if ($action === 'edit'): ?>
                        <a href="?page=topics&action=delete&id=<?= urlencode($topicId) ?>" 
                           class="btn btn-danger" 
                           onclick="return confirm('Thema &quot;<?= htmlspecialchars($topic['name'] ?? 'Unbekannt') ?>&quot; wirklich löschen?')"
                           style="margin-left: auto;">
                            <i class="fas fa-trash"></i>
                            Thema löschen
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($action === 'edit'): ?>
                    <div style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                        <strong>Debug-Links:</strong>
                        <a href="?page=topics&action=edit&id=<?= urlencode($topicId) ?>&debug=1" style="margin-left: 10px;">Mit Debug anzeigen</a> |
                        <a href="?page=topics&debug=1" style="margin-left: 10px;">Zurück zur Übersicht mit Debug</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

<?php endif; ?>

<style>
/* Themen-spezifische Styles */
.topics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.topic-item {
    background-color: #343852;
    border: 1px solid #3a3d52;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.topic-item:hover {
    transform: translateY(-2px);
    border-color: #4dabf7;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.topic-color {
    height: 80px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.topic-color-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.topic-item:hover .topic-color-overlay {
    opacity: 1;
}

.topic-actions {
    display: flex;
    gap: 8px;
}

.topic-action-btn {
    width: 32px;
    height: 32px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #333;
    text-decoration: none;
    transition: all 0.2s ease;
}

.topic-action-btn:hover {
    background: white;
    transform: scale(1.1);
}

.topic-delete-btn:hover {
    background: #ef4444 !important;
    color: white !important;
}

.topic-content {
    padding: 16px;
}

.topic-name {
    font-size: 16px;
    font-weight: 600;
    color: #e4e4e7;
    margin-bottom: 8px;
    word-break: break-word;
}

.topic-description {
    font-size: 13px;
    color: #8b8fa3;
    margin-bottom: 12px;
    line-height: 1.4;
    word-break: break-word;
}

.topic-meta {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #6c757d;
    flex-wrap: wrap;
    gap: 8px;
}

.usage-count, .created-date {
    display: flex;
    align-items: center;
    gap: 4px;
}

.topic-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: white;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.topic-tag {
    display: inline-block;
    padding: 4px 8px;
    border: 2px solid;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    background: transparent;
}

.color-input-group {
    display: flex;
    gap: 8px;
}

.color-picker {
    width: 60px;
    height: 40px;
    padding: 4px;
    border-radius: 6px;
    cursor: pointer;
}

.color-text {
    flex: 1;
    font-family: monospace;
    text-transform: uppercase;
}

.color-palette {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 8px;
    margin-top: 8px;
}

.color-option {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.2s ease;
}

.color-option:hover {
    transform: scale(1.1);
    border-color: #4dabf7;
}

.topic-preview-container {
    padding: 16px;
    background-color: #343852;
    border-radius: 8px;
    border: 1px solid #3a3d52;
}

.alert-info {
    background: #e8f4ff;
    color: #0066cc;
    border: 1px solid #b3d9ff;
}

@media (max-width: 768px) {
    .topics-grid {
        grid-template-columns: 1fr;
    }
    
    .color-palette {
        grid-template-columns: repeat(5, 1fr);
    }
    
    .topic-meta {
        flex-direction: column;
        gap: 4px;
    }
}
</style>

<script>
// Farb-Picker Funktionalität
document.addEventListener('DOMContentLoaded', function() {
    const colorPicker = document.getElementById('color');
    const colorText = document.getElementById('color-text');
    const nameInput = document.getElementById('name');
    const previewBadge = document.getElementById('previewBadge');
    const previewTag = document.getElementById('previewTag');
    
    if (colorPicker && colorText) {
        colorPicker.addEventListener('input', function() {
            colorText.value = this.value.toUpperCase();
            updatePreview();
        });
    }
    
    if (nameInput) {
        nameInput.addEventListener('input', updatePreview);
    }
    
    function updatePreview() {
        const name = nameInput ? (nameInput.value.trim() || 'Themen-Name') : 'Themen-Name';
        const color = colorPicker ? colorPicker.value : '#4dabf7';
        
        if (previewBadge) {
            previewBadge.textContent = name;
            previewBadge.style.backgroundColor = color;
        }
        
        if (previewTag) {
            previewTag.textContent = '#' + name;
            previewTag.style.borderColor = color;
            previewTag.style.color = color;
        }
    }
    
    // Initial preview update
    updatePreview();
    
    // Form validation
    const form = document.getElementById('topicForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const nameValue = nameInput ? nameInput.value.trim() : '';
            if (!nameValue) {
                e.preventDefault();
                alert('Bitte geben Sie einen Themen-Namen ein.');
                if (nameInput) nameInput.focus();
                return false;
            }
        });
    }
});

function selectColor(color) {
    const colorPicker = document.getElementById('color');
    const colorText = document.getElementById('color-text');
    
    if (colorPicker && colorText) {
        colorPicker.value = color;
        colorText.value = color.toUpperCase();
        
        // Update preview
        const previewBadge = document.getElementById('previewBadge');
        const previewTag = document.getElementById('previewTag');
        
        if (previewBadge) {
            previewBadge.style.backgroundColor = color;
        }
        
        if (previewTag) {
            previewTag.style.borderColor = color;
            previewTag.style.color = color;
        }
    }
}

// Debug-Funktionen
function refreshTopicsData() {
    if (confirm('Themen-Daten neu laden? (Aktuelle Eingaben gehen verloren)')) {
        window.location.reload();
    }
}

// URL-Parameter Debug
function showUrlDebug() {
    const urlParams = new URLSearchParams(window.location.search);
    let debugInfo = 'URL-Parameter:\n';
    for (const [key, value] of urlParams) {
        debugInfo += `${key}: ${value}\n`;
    }
    alert(debugInfo);
}

// Erweiterte Lösch-Bestätigung
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.topic-delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const url = this.href;
            const topicName = this.closest('.topic-item').querySelector('.topic-name').textContent;
            const topicId = this.closest('.topic-item').dataset.topicId;
            
            if (confirm(`Thema "${topicName}" wirklich löschen?\n\nID: ${topicId}\n\nDieser Vorgang kann nicht rückgängig gemacht werden.`)) {
                window.location.href = url;
            }
        });
    });
});
</script>