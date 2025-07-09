<?php
/**
 * Themenverwaltung mit Farben
 * pages/topics.php
 */

$action = isset($_GET['action']) ? $_GET['action'] : 'index';
$topicId = isset($_GET['id']) ? $_GET['id'] : null;
$userId = getCurrentUserId();

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
                redirectWithMessage('?page=topics', 'Thema erfolgreich erstellt.');
            } else {
                $error = 'Fehler beim Speichern des Themas.';
            }
        }
    } elseif ($action === 'edit' && $topicId) {
        $topics = loadData('topics.json');
        if (isset($topics[$topicId]) && $topics[$topicId]['user_id'] === $userId) {
            $name = trim($_POST['name'] ?? '');
            $color = trim($_POST['color'] ?? '#4dabf7');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                $error = 'Themen-Name ist erforderlich.';
            } else {
                $topics[$topicId]['name'] = $name;
                $topics[$topicId]['color'] = $color;
                $topics[$topicId]['description'] = $description;
                $topics[$topicId]['updated_at'] = date('Y-m-d H:i:s');
                
                if (saveData('topics.json', $topics)) {
                    redirectWithMessage('?page=topics', 'Thema erfolgreich aktualisiert.');
                } else {
                    $error = 'Fehler beim Aktualisieren des Themas.';
                }
            }
        }
    } elseif ($action === 'delete' && $topicId) {
        $topics = loadData('topics.json');
        if (isset($topics[$topicId]) && $topics[$topicId]['user_id'] === $userId) {
            unset($topics[$topicId]);
            if (saveData('topics.json', $topics)) {
                redirectWithMessage('?page=topics', 'Thema erfolgreich gelöscht.');
            } else {
                $error = 'Fehler beim Löschen des Themas.';
            }
        }
    }
}

// Daten laden
$topics = loadData('topics.json');
$blogs = loadData('blogs.json');
$customers = loadData('customers.json');

// Benutzer-spezifische Themen
$userTopics = array();
foreach ($topics as $topicId => $topic) {
    if (isset($topic['user_id']) && $topic['user_id'] === $userId) {
        $userTopics[$topicId] = $topic;
    }
}

// Nutzungsstatistiken berechnen
foreach ($userTopics as $topicId => &$topic) {
    $usageCount = 0;
    
    // In Blogs zählen
    foreach ($blogs as $blog) {
        if (isset($blog['user_id']) && $blog['user_id'] === $userId && isset($blog['topics'])) {
            if (in_array($topic['name'], $blog['topics'])) {
                $usageCount++;
            }
        }
    }
    
    // In Kunden zählen
    foreach ($customers as $customer) {
        if (isset($customer['user_id']) && $customer['user_id'] === $userId && isset($customer['topics'])) {
            if (in_array($topic['name'], $customer['topics'])) {
                $usageCount++;
            }
        }
    }
    
    $topic['usage_count'] = $usageCount;
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
                        $totalUsage += $topic['usage_count'];
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
                <p class="card-subtitle">Alle erstellten Themen mit Farbzuordnung</p>
            </div>
            <div class="card-body">
                <div class="topics-grid">
                    <?php foreach ($userTopics as $topicId => $topic): ?>
                        <div class="topic-item" data-topic-id="<?php echo $topicId; ?>">
                            <div class="topic-color" style="background-color: <?php echo e($topic['color']); ?>;">
                                <div class="topic-color-overlay">
                                    <div class="topic-actions">
                                        <a href="?page=topics&action=edit&id=<?php echo $topicId; ?>" class="topic-action-btn" title="Bearbeiten">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?page=topics&action=delete&id=<?php echo $topicId; ?>" class="topic-action-btn" title="Löschen" onclick="return confirm('Thema wirklich löschen?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="topic-content">
                                <h4 class="topic-name"><?php echo e($topic['name']); ?></h4>
                                <?php if (!empty($topic['description'])): ?>
                                    <p class="topic-description"><?php echo e($topic['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="topic-meta">
                                    <span class="usage-count">
                                        <i class="fas fa-chart-bar"></i>
                                        <?php echo $topic['usage_count']; ?> Verwendungen
                                    </span>
                                    <span class="created-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo formatDate($topic['created_at']); ?>
                                    </span>
                                </div>
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
                        <?php foreach ($userTopics as $topic): ?>
                            <span class="topic-badge" style="background-color: <?php echo e($topic['color']); ?>;">
                                <?php echo e($topic['name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    
                    <h4 style="margin-bottom: 16px;">Als Tags:</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($userTopics as $topic): ?>
                            <span class="topic-tag" style="border-color: <?php echo e($topic['color']); ?>; color: <?php echo e($topic['color']); ?>;">
                                #<?php echo e($topic['name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'create' || ($action === 'edit' && $topicId)): 
    $topic = null;
    if ($action === 'edit') {
        $topic = isset($topics[$topicId]) ? $topics[$topicId] : null;
        if (!$topic || $topic['user_id'] !== $userId) {
            header('HTTP/1.0 404 Not Found');
            echo '<div class="alert alert-danger">Thema nicht gefunden.</div>';
            return;
        }
    }
?>
    <div class="breadcrumb">
        <a href="?page=topics">Zurück zu Themen</a>
        <i class="fas fa-chevron-right"></i>
        <span><?php echo $action === 'create' ? 'Thema hinzufügen' : 'Thema bearbeiten'; ?></span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title"><?php echo $action === 'create' ? 'Neues Thema hinzufügen' : 'Thema bearbeiten'; ?></h1>
            <p class="page-subtitle">Erstellen Sie ein Thema mit individueller Farbzuordnung</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Themen-Name *</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-control" 
                            placeholder="z.B. SEO, Marketing, Webentwicklung"
                            value="<?php echo e($_POST['name'] ?? ($topic['name'] ?? '')); ?>"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="color" class="form-label">Farbe</label>
                        <div class="color-input-group">
                            <input 
                                type="color" 
                                id="color" 
                                name="color" 
                                class="form-control color-picker" 
                                value="<?php echo e($_POST['color'] ?? ($topic['color'] ?? '#4dabf7')); ?>"
                            >
                            <input 
                                type="text" 
                                id="color-text" 
                                class="form-control color-text" 
                                value="<?php echo e($_POST['color'] ?? ($topic['color'] ?? '#4dabf7')); ?>"
                                readonly
                            >
                        </div>
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
                    ><?php echo e($_POST['description'] ?? ($topic['description'] ?? '')); ?></textarea>
                </div>

                <!-- Farbpalette -->
                <div class="form-group">
                    <label class="form-label">Farbvorschläge</label>
                    <div class="color-palette">
                        <?php foreach ($colorPalette as $paletteColor): ?>
                            <div class="color-option" 
                                 style="background-color: <?php echo $paletteColor; ?>;" 
                                 onclick="selectColor('<?php echo $paletteColor; ?>')"
                                 title="<?php echo $paletteColor; ?>">
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
                        <?php echo $action === 'create' ? 'Thema erstellen' : 'Änderungen speichern'; ?>
                    </button>
                    <a href="?page=topics" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                </div>
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

.topic-content {
    padding: 16px;
}

.topic-name {
    font-size: 16px;
    font-weight: 600;
    color: #e4e4e7;
    margin-bottom: 8px;
}

.topic-description {
    font-size: 13px;
    color: #8b8fa3;
    margin-bottom: 12px;
    line-height: 1.4;
}

.topic-meta {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #6c757d;
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

@media (max-width: 768px) {
    .topics-grid {
        grid-template-columns: 1fr;
    }
    
    .color-palette {
        grid-template-columns: repeat(5, 1fr);
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
            colorText.value = this.value;
            updatePreview();
        });
    }
    
    if (nameInput) {
        nameInput.addEventListener('input', updatePreview);
    }
    
    function updatePreview() {
        const name = nameInput ? nameInput.value || 'Themen-Name' : 'Themen-Name';
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
});

function selectColor(color) {
    const colorPicker = document.getElementById('color');
    const colorText = document.getElementById('color-text');
    
    if (colorPicker) {
        colorPicker.value = color;
        colorText.value = color;
        
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
</script>