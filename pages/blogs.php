<?php
/**
 * LinkBuilder Pro - Blogs Verwaltung
 * pages/blogs.php - Vollständige Version ohne Debug
 */

// Basis-Variablen
$action = $_GET['action'] ?? 'index';
$blogId = $_GET['id'] ?? null;

// Session sicherstellen und User-ID ermitteln
ensureSession();
$userId = getCurrentUserId();

// Fallback für Tests - wenn keine echte User-ID vorhanden ist
if ($userId === 'default_user' || empty($userId)) {
    // Für Demo-Zwecke die User-ID aus den vorhandenen Blogs verwenden
    $tempBlogs = loadData('blogs.json');
    if (!empty($tempBlogs)) {
        $firstBlog = reset($tempBlogs);
        if (isset($firstBlog['user_id'])) {
            $_SESSION['user_id'] = $firstBlog['user_id'];
            $userId = $firstBlog['user_id'];
        }
    }
}

// Benutzer-Daten laden
$users = loadData('users.json');
$currentUser = $users[$userId] ?? null;

// Admin-Status prüfen
$isAdmin = $currentUser && ($currentUser['role'] ?? 'user') === 'admin';

// Wenn kein Benutzer gefunden wird, erstelle einen Standard-Benutzer
if (!$currentUser) {
    $users[$userId] = [
        'id' => $userId,
        'username' => 'admin',
        'name' => 'Administrator',
        'email' => 'admin@example.com',
        'role' => 'admin',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Zusätzlichen Test-Benutzer erstellen (für Filter-Demo)
    $testUserId = '686e3ceeb96bc9.16662141';
    $users[$testUserId] = [
        'id' => $testUserId,
        'username' => 'marcel',
        'name' => 'Marcel',
        'email' => 'marcel@example.com',
        'role' => 'user',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    saveData('users.json', $users);
    $currentUser = $users[$userId];
    $isAdmin = true;
    
    // Test-Blog für Marcel erstellen (falls noch nicht vorhanden)
    $blogs = loadData('blogs.json');
    $marcelBlogExists = false;
    foreach ($blogs as $blog) {
        if (isset($blog['user_id']) && $blog['user_id'] === $testUserId) {
            $marcelBlogExists = true;
            break;
        }
    }
    
    if (!$marcelBlogExists) {
        $newBlogId = generateId();
        $blogs[$newBlogId] = [
            'id' => $newBlogId,
            'user_id' => $testUserId,
            'name' => 'themenspeicher.com',
            'url' => 'https://www.themenspeicher.com/',
            'description' => 'Blog über verschiedene Themen und Spezialisierungen',
            'topics' => ['SEO'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        saveData('blogs.json', $blogs);
    }
}

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $name = sanitizeString($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = sanitizeString($_POST['description'] ?? '', 500);
        $topics = array_filter(array_map('trim', explode(',', $_POST['topics'] ?? '')));
        
        if (empty($name) || empty($url)) {
            $error = 'Name und URL sind Pflichtfelder.';
        } elseif (!validateUrl($url)) {
            $error = 'Ungültige URL-Adresse.';
        } else {
            $blogs = loadData('blogs.json');
            $newId = generateId();
            
            $blogs[$newId] = [
                'id' => $newId,
                'user_id' => $userId,
                'name' => $name,
                'url' => $url,
                'description' => $description,
                'topics' => $topics,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (saveData('blogs.json', $blogs)) {
                redirectWithMessage('?page=blogs', 'Blog "' . $name . '" erfolgreich erstellt.');
            } else {
                $error = 'Fehler beim Speichern des Blogs.';
            }
        }
    } elseif ($action === 'edit' && $blogId) {
        $blogs = loadData('blogs.json');
        if (isset($blogs[$blogId]) && ($isAdmin || $blogs[$blogId]['user_id'] === $userId)) {
            $name = sanitizeString($_POST['name'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $description = sanitizeString($_POST['description'] ?? '', 500);
            $topics = array_filter(array_map('trim', explode(',', $_POST['topics'] ?? '')));
            
            if (empty($name) || empty($url)) {
                $error = 'Name und URL sind Pflichtfelder.';
            } elseif (!validateUrl($url)) {
                $error = 'Ungültige URL-Adresse.';
            } else {
                $blogs[$blogId] = array_merge($blogs[$blogId], [
                    'name' => $name,
                    'url' => $url,
                    'description' => $description,
                    'topics' => $topics,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                if (saveData('blogs.json', $blogs)) {
                    redirectWithMessage("?page=blogs&action=view&id=$blogId", 'Blog "' . $name . '" erfolgreich aktualisiert.');
                } else {
                    $error = 'Fehler beim Aktualisieren des Blogs.';
                }
            }
        } else {
            $error = 'Blog nicht gefunden oder keine Berechtigung.';
        }
    } elseif ($action === 'delete' && $blogId) {
        $blogs = loadData('blogs.json');
        if (isset($blogs[$blogId]) && ($isAdmin || $blogs[$blogId]['user_id'] === $userId)) {
            $blogName = $blogs[$blogId]['name'];
            unset($blogs[$blogId]);
            
            if (saveData('blogs.json', $blogs)) {
                // Verknüpfte Links ebenfalls löschen
                $links = loadData('links.json');
                $linksDeleted = 0;
                foreach ($links as $linkId => $link) {
                    if ($link['blog_id'] === $blogId) {
                        unset($links[$linkId]);
                        $linksDeleted++;
                    }
                }
                saveData('links.json', $links);
                
                $message = 'Blog "' . $blogName . '" erfolgreich gelöscht.';
                if ($linksDeleted > 0) {
                    $message .= ' (' . $linksDeleted . ' verknüpfte Links ebenfalls gelöscht)';
                }
                redirectWithMessage('?page=blogs', $message);
            } else {
                $error = 'Fehler beim Löschen des Blogs.';
            }
        } else {
            $error = 'Blog nicht gefunden oder keine Berechtigung.';
        }
    } elseif ($action === 'import' && isset($_FILES['csv_file'])) {
        $csvFile = $_FILES['csv_file'];
        
        if ($csvFile['error'] === UPLOAD_ERR_OK && $csvFile['type'] === 'text/csv') {
            $handle = fopen($csvFile['tmp_name'], 'r');
            $blogs = loadData('blogs.json');
            $imported = 0;
            $errors = [];
            
            // Header-Zeile überspringen
            $header = fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) >= 2) {
                    $name = sanitizeString($data[0] ?? '');
                    $url = trim($data[1] ?? '');
                    $description = sanitizeString($data[2] ?? '', 500);
                    $topics = !empty($data[3]) ? array_filter(array_map('trim', explode(',', $data[3]))) : [];
                    
                    if (!empty($name) && !empty($url) && validateUrl($url)) {
                        $newId = generateId();
                        $blogs[$newId] = [
                            'id' => $newId,
                            'user_id' => $userId,
                            'name' => $name,
                            'url' => $url,
                            'description' => $description,
                            'topics' => $topics,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        $imported++;
                    } else {
                        $errors[] = "Ungültige Daten für Blog: $name";
                    }
                }
            }
            fclose($handle);
            
            if (saveData('blogs.json', $blogs)) {
                $message = "$imported Blogs erfolgreich importiert.";
                if (!empty($errors)) {
                    $message .= ' (' . count($errors) . ' Einträge übersprungen)';
                }
                redirectWithMessage('?page=blogs', $message);
            } else {
                $error = 'Fehler beim Speichern der importierten Blogs.';
            }
        } else {
            $error = 'Ungültige CSV-Datei.';
        }
    }
}

// Daten laden
$blogs = loadData('blogs.json');
$links = loadData('links.json');

// Blogs je nach Berechtigung filtern
if ($isAdmin) {
    $userBlogs = $blogs;
} else {
    $userBlogs = array_filter($blogs, function($blog) use ($userId) {
        return isset($blog['user_id']) && $blog['user_id'] === $userId;
    });
}

// Server-seitige Filterung anwenden
$activeFilters = [];

// User-Filter
if (isset($_GET['user_filter']) && !empty($_GET['user_filter'])) {
    $userFilterId = $_GET['user_filter'];
    $userBlogs = array_filter($userBlogs, function($blog) use ($userFilterId) {
        return isset($blog['user_id']) && $blog['user_id'] === $userFilterId;
    });
    $activeFilters['user'] = $userFilterId;
}

// Topic-Filter
if (isset($_GET['topic_filter']) && !empty($_GET['topic_filter'])) {
    $topicFilter = $_GET['topic_filter'];
    $userBlogs = array_filter($userBlogs, function($blog) use ($topicFilter) {
        return isset($blog['topics']) && in_array($topicFilter, $blog['topics']);
    });
    $activeFilters['topic'] = $topicFilter;
}

// Such-Filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = strtolower($_GET['search']);
    $userBlogs = array_filter($userBlogs, function($blog) use ($searchTerm) {
        $name = strtolower($blog['name'] ?? '');
        $url = strtolower($blog['url'] ?? '');
        $description = strtolower($blog['description'] ?? '');
        return strpos($name, $searchTerm) !== false || 
               strpos($url, $searchTerm) !== false || 
               strpos($description, $searchTerm) !== false;
    });
    $activeFilters['search'] = $_GET['search'];
}

// Statistiken berechnen
$topicStats = [];
foreach ($userBlogs as $blog) {
    if (isset($blog['topics']) && is_array($blog['topics'])) {
        foreach ($blog['topics'] as $topic) {
            $topicStats[$topic] = ($topicStats[$topic] ?? 0) + 1;
        }
    }
}
arsort($topicStats);

// VIEW LOGIC STARTS HERE
if ($action === 'index'): ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">
                Blogs
                <?php if ($isAdmin): ?>
                    <span class="badge badge-info" style="font-size: 12px; margin-left: 8px;">ADMIN-ANSICHT</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <?php if ($isAdmin): ?>
                    Verwalten Sie alle Blogs im System (<?= count($userBlogs) ?> Blogs von <?= count(array_unique(array_column($userBlogs, 'user_id'))) ?> Benutzern)
                <?php else: ?>
                    Verwalten Sie Ihre Blogs (<?= count($userBlogs) ?> Blogs)
                <?php endif; ?>
            </p>
        </div>
        <div class="action-buttons">
            <a href="?page=blogs&action=create" class="btn btn-primary">
                <i class="fas fa-plus"></i> Blog hinzufügen
            </a>
            <a href="?page=blogs&action=import" class="btn btn-secondary">
                <i class="fas fa-upload"></i> Blogs importieren
            </a>
        </div>
    </div>

    <?php showFlashMessage(); ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard -->
    <div class="content-grid">
        <!-- Themenverteilung -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Themenverteilung</h3>
                <p class="card-subtitle">Überblick über die Verteilung der Blog-Themen</p>
            </div>
            <div class="card-body">
                <?php if (!empty($topicStats)): ?>
                    <div class="chart-container">
                        <div class="chart-legend">
                            <?php 
                            $colors = ['#2dd4bf', '#4dabf7', '#fbbf24', '#f97316', '#f472b6', '#a78bfa'];
                            $index = 0;
                            foreach (array_slice($topicStats, 0, 6, true) as $topic => $count): 
                            ?>
                                <div class="legend-item">
                                    <div class="legend-color" style="background-color: <?= $colors[$index % count($colors)] ?>;"></div>
                                    <span><?= e($topic) ?> (<?= $count ?>)</span>
                                </div>
                            <?php 
                            $index++;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: #8b8fa3; padding: 20px;">
                        <i class="fas fa-chart-pie" style="font-size: 48px; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p>Noch keine Themen vorhanden</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Blog-Statistiken -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Blog-Statistiken</h3>
                <p class="card-subtitle">Schneller Überblick über <?= $isAdmin ? 'alle' : 'Ihre' ?> Blogs</p>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                        <div style="font-size: 28px; font-weight: bold; color: #2dd4bf; margin-bottom: 4px;">
                            <?= count($userBlogs) ?>
                        </div>
                        <div style="font-size: 12px; color: #8b8fa3;">
                            <?= $isAdmin ? 'Gesamt Blogs (alle Benutzer)' : 'Gesamt Blogs' ?>
                        </div>
                    </div>
                    <div style="text-align: center; padding: 16px; background-color: #343852; border-radius: 6px;">
                        <div style="font-size: 28px; font-weight: bold; color: #4dabf7; margin-bottom: 4px;">
                            <?= count($topicStats) ?>
                        </div>
                        <div style="font-size: 12px; color: #8b8fa3;">Verwendete Themen</div>
                    </div>
                </div>
                
                <?php if ($isAdmin && !empty($userBlogs)): ?>
                    <div style="margin-top: 16px;">
                        <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">Benutzer mit Blogs</div>
                        <div style="color: #8b8fa3;"><?= count(array_unique(array_column($userBlogs, 'user_id'))) ?> von <?= count($users) ?> Benutzern</div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($topicStats)): ?>
                    <div style="margin-top: 16px;">
                        <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">Beliebtestes Thema</div>
                        <div style="color: #8b8fa3;"><?= e(array_keys($topicStats)[0]) ?> (<?= array_values($topicStats)[0] ?> Blogs)</div>
                    </div>
                    
                    <?php if (!empty($userBlogs)): ?>
                        <div style="margin-top: 12px;">
                            <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">Neuester Eintrag</div>
                            <div style="color: #8b8fa3;">
                                <?php
                                $dates = array_column($userBlogs, 'created_at');
                                echo formatDate(max($dates));
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filter und Suche -->
    <?php if (!empty($blogs)): // Verwende $blogs statt $userBlogs für Filter-Optionen ?>
        <div class="action-bar" style="margin-top: 30px;">
            <!-- Filter-Formular -->
            <form method="GET" style="display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%;">
                <input type="hidden" name="page" value="blogs">
                
                <div class="search-bar" style="flex: 1; min-width: 280px; max-width: 400px;">
                    <div style="position: relative;">
                        <i class="fas fa-search search-icon"></i>
                        <input 
                            type="text" 
                            class="form-control search-input" 
                            placeholder="Blogs durchsuchen (Name, URL<?= $isAdmin ? ', Besitzer' : '' ?>)"
                            name="search"
                            value="<?= e($_GET['search'] ?? '') ?>"
                        >
                    </div>
                </div>
                
                <div class="filter-controls">
                    <select class="form-control filter-select" name="topic_filter" onchange="this.form.submit()">
                        <option value="">Nach Topic filtern</option>
                        <?php 
                        // Alle verfügbaren Topics aus allen Blogs sammeln
                        $allTopics = [];
                        foreach ($blogs as $blog) {
                            if (isset($blog['topics']) && is_array($blog['topics'])) {
                                foreach ($blog['topics'] as $topic) {
                                    $allTopics[$topic] = ($allTopics[$topic] ?? 0) + 1;
                                }
                            }
                        }
                        arsort($allTopics);
                        foreach (array_keys($allTopics) as $topic): 
                            $selected = (isset($_GET['topic_filter']) && $_GET['topic_filter'] === $topic) ? 'selected' : '';
                        ?>
                            <option value="<?= e($topic) ?>" <?= $selected ?>><?= e($topic) ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php if ($isAdmin && count($blogs) > 0): ?>
                        <select class="form-control filter-select" name="user_filter" onchange="this.form.submit()">
                            <option value="">Nach Benutzer filtern</option>
                            <?php 
                            $userIds = array_unique(array_column($blogs, 'user_id'));
                            foreach ($userIds as $uid): 
                                $user = $users[$uid] ?? null;
                                if ($user):
                                    $selected = (isset($_GET['user_filter']) && $_GET['user_filter'] === $uid) ? 'selected' : '';
                            ?>
                                <option value="<?= e($uid) ?>" <?= $selected ?>><?= e($user['name'] ?? $user['username'] ?? 'Unbekannt') ?></option>
                            <?php endif; endforeach; ?>
                        </select>
                    <?php endif; ?>
                    
                    <?php if (!empty($activeFilters)): ?>
                        <a href="?page=blogs" class="btn btn-secondary" style="white-space: nowrap;">
                            <i class="fas fa-times"></i> Filter zurücksetzen
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Such-Button -->
                <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
                    <i class="fas fa-search"></i> Suchen
                </button>
            </form>
        </div>
        
        <!-- Aktive Filter anzeigen -->
        <?php if (!empty($activeFilters)): ?>
            <div style="margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <span style="color: #8b8fa3; font-size: 14px; margin-right: 8px;">Aktive Filter:</span>
                <?php foreach ($activeFilters as $type => $value): ?>
                    <span class="badge badge-info" style="display: flex; align-items: center; gap: 6px;">
                        <?php if ($type === 'user'): ?>
                            <i class="fas fa-user"></i>
                            <?= e($users[$value]['name'] ?? $users[$value]['username'] ?? 'Unbekannt') ?>
                        <?php elseif ($type === 'topic'): ?>
                            <i class="fas fa-tag"></i>
                            <?= e($value) ?>
                        <?php elseif ($type === 'search'): ?>
                            <i class="fas fa-search"></i>
                            "<?= e($value) ?>"
                        <?php endif; ?>
                        <a href="?page=blogs<?= $type === 'user' ? (isset($_GET['topic_filter']) ? '&topic_filter=' . urlencode($_GET['topic_filter']) : '') . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '') : ($type === 'topic' ? (isset($_GET['user_filter']) ? '&user_filter=' . urlencode($_GET['user_filter']) : '') . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '') : (isset($_GET['user_filter']) ? '&user_filter=' . urlencode($_GET['user_filter']) : '') . (isset($_GET['topic_filter']) ? '&topic_filter=' . urlencode($_GET['topic_filter']) : '')) ?>" 
                           style="color: white; margin-left: 4px; text-decoration: none;">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($userBlogs)): ?>
        <div class="empty-state">
            <i class="fas fa-blog"></i>
            <h3>Keine Blogs vorhanden</h3>
            <p><?= $isAdmin ? 'Im System sind noch keine Blogs vorhanden.' : 'Erstellen Sie Ihren ersten Blog, um hier eine Übersicht zu sehen.' ?></p>
            <a href="?page=blogs&action=create" class="btn btn-primary" style="margin-top: 16px;">
                <i class="fas fa-plus"></i> Ersten Blog erstellen
            </a>
        </div>
    <?php else: ?>
        <!-- Blog-Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px;" id="blogGrid">
            <?php foreach ($userBlogs as $blogId => $blog): 
                // Links für diesen Blog zählen
                $blogLinks = array_filter($links, function($link) use ($blogId) {
                    return isset($link['blog_id']) && $link['blog_id'] === $blogId;
                });
                $linkCount = count($blogLinks);
                
                // Blog-Besitzer Info
                $blogOwner = $users[$blog['user_id']] ?? null;
                $ownerName = $blogOwner ? ($blogOwner['name'] ?? $blogOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
                $isOwnBlog = $blog['user_id'] === $userId;
            ?>
                <div class="card blog-card" 
                     data-name="<?= strtolower($blog['name'] ?? '') ?>" 
                     data-url="<?= strtolower($blog['url'] ?? '') ?>"
                     data-topics="<?= strtolower(implode(' ', $blog['topics'] ?? [])) ?>"
                     data-user-id="<?= $blog['user_id'] ?? '' ?>"
                     data-owner="<?= strtolower($ownerName) ?>">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div style="flex: 1;">
                                <h3 style="margin-bottom: 4px; font-size: 18px;">
                                    <a href="?page=blogs&action=view&id=<?= $blogId ?>" style="color: #4dabf7; text-decoration: none;">
                                        <?= e($blog['name'] ?? 'Unbenannt') ?>
                                    </a>
                                </h3>
                                <div style="font-size: 13px; color: #8b8fa3; margin-bottom: 8px;">
                                    <a href="<?= e($blog['url'] ?? '#') ?>" target="_blank" style="color: #4dabf7; text-decoration: none;">
                                        <?= e($blog['url'] ?? 'Keine URL') ?>
                                        <i class="fas fa-external-link-alt" style="font-size: 10px; margin-left: 4px;"></i>
                                    </a>
                                </div>
                                <?php if ($isAdmin && !$isOwnBlog): ?>
                                    <div style="font-size: 12px; color: #8b8fa3; margin-bottom: 8px;">
                                        <i class="fas fa-user" style="margin-right: 4px; color: #fbbf24;"></i>
                                        Erstellt von: <strong><?= e($ownerName) ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 4px;">
                                <a href="?page=blogs&action=view&id=<?= $blogId ?>" class="btn btn-sm btn-primary" title="Anzeigen">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($isAdmin || $isOwnBlog): ?>
                                    <a href="?page=blogs&action=edit&id=<?= $blogId ?>" class="btn btn-sm btn-secondary" title="Bearbeiten">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?page=blogs&action=delete&id=<?= $blogId ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Blog &quot;<?= e($blog['name'] ?? 'Unbenannt') ?>&quot; wirklich löschen?<?= $isAdmin && !$isOwnBlog ? '\n\nDieser Blog gehört ' . e($ownerName) . '.' : '' ?>')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($blog['description'])): ?>
                            <p style="color: #8b8fa3; font-size: 13px; margin-bottom: 12px; line-height: 1.4;">
                                <?= e(truncateText($blog['description'], 100)) ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($blog['topics'])): ?>
                            <div style="margin-bottom: 12px;">
                                <?php foreach (array_slice($blog['topics'], 0, 3) as $topic): ?>
                                    <span class="badge badge-secondary" style="margin-right: 4px; margin-bottom: 4px;">
                                        <?= e($topic) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($blog['topics']) > 3): ?>
                                    <span style="font-size: 12px; color: #8b8fa3;">+<?= count($blog['topics']) - 3 ?> weitere</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 1px solid #3a3d52; font-size: 12px; color: #8b8fa3;">
                            <div>
                                <i class="fas fa-link" style="margin-right: 4px;"></i>
                                <span><?= $linkCount ?> Link<?= $linkCount !== 1 ? 's' : '' ?></span>
                            </div>
                            <div>
                                <?php if (isset($blog['updated_at'])): ?>
                                    Aktualisiert <?= formatDate($blog['updated_at']) ?>
                                <?php else: ?>
                                    Erstellt <?= formatDate($blog['created_at'] ?? date('Y-m-d')) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'view' && $blogId): 
    $blog = $blogs[$blogId] ?? null;
    if (!$blog || (!$isAdmin && $blog['user_id'] !== $userId)) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Blog nicht gefunden oder keine Berechtigung.</div>';
        return;
    }
    
    // Links für diesen Blog
    $blogLinks = array_filter($links, function($link) use ($blogId) {
        return isset($link['blog_id']) && $link['blog_id'] === $blogId;
    });
    
    // Blog-Besitzer Info
    $blogOwner = $users[$blog['user_id']] ?? null;
    $ownerName = $blogOwner ? ($blogOwner['name'] ?? $blogOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
    $isOwnBlog = $blog['user_id'] === $userId;
?>
    <div class="breadcrumb">
        <a href="?page=blogs">Zurück zu Blogs</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= e($blog['name']) ?></span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <?= e($blog['name']) ?>
                <?php if ($isAdmin && !$isOwnBlog): ?>
                    <span class="badge badge-warning" style="font-size: 12px; margin-left: 8px;">Fremder Blog</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <a href="<?= e($blog['url']) ?>" target="_blank" style="color: #4dabf7;">
                    <?= e($blog['url']) ?>
                    <i class="fas fa-external-link-alt" style="font-size: 12px; margin-left: 4px;"></i>
                </a>
                <?php if ($isAdmin && !$isOwnBlog): ?>
                    <br>
                    <small style="color: #8b8fa3;">
                        <i class="fas fa-user" style="margin-right: 4px;"></i>
                        Erstellt von: <strong><?= e($ownerName) ?></strong>
                    </small>
                <?php endif; ?>
            </p>
        </div>
        <div class="action-buttons">
            <?php if ($isAdmin || $isOwnBlog): ?>
                <a href="?page=blogs&action=edit&id=<?= $blogId ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Bearbeiten
                </a>
                <a href="?page=blogs&action=delete&id=<?= $blogId ?>" class="btn btn-danger" onclick="return confirm('Blog &quot;<?= e($blog['name']) ?>&quot; wirklich löschen?<?= $isAdmin && !$isOwnBlog ? '\n\nDieser Blog gehört ' . e($ownerName) . '.' : '' ?>')">
                    <i class="fas fa-trash"></i> Löschen
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php showFlashMessage(); ?>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="showTab('links')">Links (<?= count($blogLinks) ?>)</button>
        <button class="tab" onclick="showTab('statistics')">Statistiken</button>
        <button class="tab" onclick="showTab('info')">Blog-Informationen</button>
    </div>

    <!-- Links Tab -->
    <div id="linksTab" class="tab-content">
        <div class="card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 class="card-title">Links auf diesem Blog</h3>
                        <p class="card-subtitle">Alle platzierten Links auf <?= e($blog['name']) ?></p>
                    </div>
                    <a href="?page=links&action=create&blog_id=<?= $blogId ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> Neuen Link hinzufügen
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($blogLinks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-link"></i>
                        <h3>Keine Links vorhanden</h3>
                        <p>Erstellen Sie den ersten Link für diesen Blog.</p>
                        <a href="?page=links&action=create&blog_id=<?= $blogId ?>" class="btn btn-success" style="margin-top: 16px;">
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
                                    <th>Kunde</th>
                                    <th>Ziel-URL</th>
                                    <th>Status</th>
                                    <?php if ($isAdmin): ?>
                                        <th>Erstellt von</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $customers = loadData('customers.json');
                                foreach ($blogLinks as $linkId => $link): 
                                    $customer = $customers[$link['customer_id']] ?? null;
                                    $linkOwner = $users[$link['user_id']] ?? null;
                                    $linkOwnerName = $linkOwner ? ($linkOwner['name'] ?? $linkOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
                                ?>
                                    <tr>
                                        <td><?= formatDate($link['published_date'] ?? $link['created_at'] ?? date('Y-m-d')) ?></td>
                                        <td>
                                            <a href="?page=links&action=view&id=<?= $linkId ?>" style="color: #4dabf7; text-decoration: none;">
                                                <?= e($link['anchor_text'] ?? 'Unbekannt') ?>
                                            </a>
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
                                            <a href="<?= e($link['target_url'] ?? '#') ?>" target="_blank" style="color: #4dabf7; text-decoration: none; font-size: 12px;">
                                                <?= e(truncateText($link['target_url'] ?? 'Keine URL', 40)) ?>
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
                                        <?php if ($isAdmin): ?>
                                            <td style="font-size: 12px; color: #8b8fa3;">
                                                <?= e($linkOwnerName) ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics Tab -->
    <div id="statisticsTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Blog-Performance</h3>
                <p class="card-subtitle">Statistiken und Metriken für <?= e($blog['name']) ?></p>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #2dd4bf; margin-bottom: 8px;">
                            <?= count($blogLinks) ?>
                        </div>
                        <div style="font-size: 14px; color: #8b8fa3;">Gesamt Links</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #10b981; margin-bottom: 8px;">
                            <?= count(array_filter($blogLinks, function($l) { return ($l['status'] ?? 'ausstehend') === 'aktiv'; })) ?>
                        </div>
                        <div style="font-size: 14px; color: #8b8fa3;">Aktive Links</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #f59e0b; margin-bottom: 8px;">
                            <?= count(array_filter($blogLinks, function($l) { return ($l['status'] ?? 'ausstehend') === 'ausstehend'; })) ?>
                        </div>
                        <div style="font-size: 14px; color: #8b8fa3;">Ausstehende Links</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background-color: #343852; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: bold; color: #4dabf7; margin-bottom: 8px;">
                            <?= count(array_unique(array_column($blogLinks, 'customer_id'))) ?>
                        </div>
                        <div style="font-size: 14px; color: #8b8fa3;">Verschiedene Kunden</div>
                    </div>
                </div>
                
                <?php if (!empty($blogLinks)): ?>
                    <div style="margin-top: 24px;">
                        <h4 style="margin-bottom: 16px;">Link-Aktivität über Zeit</h4>
                        <div style="background-color: #343852; padding: 16px; border-radius: 8px;">
                            <p style="color: #8b8fa3; margin: 0;">
                                <?php
                                $dates = array_column($blogLinks, 'published_date');
                                $dates = array_filter($dates); // Leere Werte entfernen
                                if (!empty($dates)):
                                ?>
                                    Erster Link: <?= formatDate(min($dates)) ?><br>
                                    Letzter Link: <?= formatDate(max($dates)) ?>
                                <?php else: ?>
                                    Noch keine Veröffentlichungsdaten verfügbar
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Info Tab -->
    <div id="infoTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Blog-Informationen</h3>
                <p class="card-subtitle">Detaillierte Informationen über <?= e($blog['name']) ?></p>
            </div>
            <div class="card-body">
                <div class="link-meta">
                    <div class="meta-item">
                        <div class="meta-label">Name</div>
                        <div class="meta-value"><?= e($blog['name']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">URL</div>
                        <div class="meta-value">
                            <a href="<?= e($blog['url']) ?>" target="_blank" style="color: #4dabf7;">
                                <?= e($blog['url']) ?>
                                <i class="fas fa-external-link-alt" style="font-size: 12px; margin-left: 4px;"></i>
                            </a>
                        </div>
                    </div>
                    <?php if ($isAdmin): ?>
                        <div class="meta-item">
                            <div class="meta-label">Erstellt von</div>
                            <div class="meta-value">
                                <?= e($ownerName) ?>
                                <?php if ($blogOwner && isset($blogOwner['email'])): ?>
                                    <br><small style="color: #8b8fa3;"><?= e($blogOwner['email']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="meta-item">
                        <div class="meta-label">Erstellt am</div>
                        <div class="meta-value"><?= formatDateTime($blog['created_at']) ?></div>
                    </div>
                    <?php if (!empty($blog['updated_at'])): ?>
                        <div class="meta-item">
                            <div class="meta-label">Zuletzt aktualisiert</div>
                            <div class="meta-value"><?= formatDateTime($blog['updated_at']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($blog['description'])): ?>
                    <div style="margin-top: 20px;">
                        <h4 style="margin-bottom: 12px;">Beschreibung</h4>
                        <p style="color: #8b8fa3; line-height: 1.6;"><?= e($blog['description']) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($blog['topics'])): ?>
                    <div style="margin-top: 20px;">
                        <h4 style="margin-bottom: 12px;">Themen</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php foreach ($blog['topics'] as $topic): ?>
                                <span class="badge badge-secondary"><?= e($topic) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($action === 'create' || ($action === 'edit' && $blogId)): 
    $blog = null;
    if ($action === 'edit') {
        $blog = $blogs[$blogId] ?? null;
        if (!$blog || (!$isAdmin && $blog['user_id'] !== $userId)) {
            echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Blog nicht gefunden oder keine Berechtigung.</div>';
            return;
        }
    }
    
    // Blog-Besitzer Info für Edit-Modus
    $blogOwner = null;
    $ownerName = '';
    $isOwnBlog = true;
    if ($blog) {
        $blogOwner = $users[$blog['user_id']] ?? null;
        $ownerName = $blogOwner ? ($blogOwner['name'] ?? $blogOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
        $isOwnBlog = $blog['user_id'] === $userId;
    }
?>
    <div class="breadcrumb">
        <a href="?page=blogs">Zurück zu Blogs</a>
        <i class="fas fa-chevron-right"></i>
        <span><?= $action === 'create' ? 'Blog hinzufügen' : 'Blog bearbeiten' ?></span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <?= $action === 'create' ? 'Neuen Blog hinzufügen' : 'Blog bearbeiten' ?>
                <?php if ($action === 'edit' && $isAdmin && !$isOwnBlog): ?>
                    <span class="badge badge-warning" style="font-size: 12px; margin-left: 8px;">Fremder Blog</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Füllen Sie das Formular aus, um einen <?= $action === 'create' ? 'neuen Blog zu erstellen' : 'Blog zu aktualisieren' ?>
                <?php if ($action === 'edit' && $isAdmin && !$isOwnBlog): ?>
                    <br>
                    <small style="color: #8b8fa3;">
                        <i class="fas fa-user" style="margin-right: 4px;"></i>
                        Erstellt von: <strong><?= e($ownerName) ?></strong>
                    </small>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Blog-Name *</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-control" 
                            placeholder="Name des Blogs"
                            value="<?= e($_POST['name'] ?? $blog['name'] ?? '') ?>"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="url" class="form-label">Blog-URL *</label>
                        <input 
                            type="url" 
                            id="url" 
                            name="url" 
                            class="form-control" 
                            placeholder="https://blog.example.com"
                            value="<?= e($_POST['url'] ?? $blog['url'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Beschreibung</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        rows="3"
                        placeholder="Kurze Beschreibung des Blogs"
                    ><?= e($_POST['description'] ?? $blog['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="topics" class="form-label">Themen/Topics</label>
                    <input 
                        type="text" 
                        id="topics" 
                        name="topics" 
                        class="form-control" 
                        placeholder="SEO, Marketing, Webentwicklung (durch Komma getrennt)"
                        value="<?= e($_POST['topics'] ?? (!empty($blog['topics']) ? implode(', ', $blog['topics']) : '')) ?>"
                    >
                    <small style="color: #8b8fa3; font-size: 12px;">Mehrere Themen durch Komma trennen</small>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        <?= $action === 'create' ? 'Blog erstellen' : 'Änderungen speichern' ?>
                    </button>
                    <a href="?page=blogs<?= $action === 'edit' ? "&action=view&id=$blogId" : '' ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'import'): ?>
    <div class="breadcrumb">
        <a href="?page=blogs">Zurück zu Blogs</a>
        <i class="fas fa-chevron-right"></i>
        <span>Blogs importieren</span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">Blogs importieren</h1>
            <p class="page-subtitle">Importieren Sie mehrere Blogs aus einer CSV-Datei</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">CSV-Import</h3>
            <p class="card-subtitle">Laden Sie eine CSV-Datei mit Blog-Daten hoch</p>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>CSV-Format:</strong> Die CSV-Datei sollte folgende Spalten enthalten: name, url, description, topics
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="csv_file" class="form-label">CSV-Datei auswählen</label>
                    <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i>
                        Blogs importieren
                    </button>
                    <a href="?page=blogs" class="btn btn-secondary">
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
function showTab(tabName) {
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
    }
    
    // Button aktivieren
    if (event && event.target) {
        event.target.classList.add('active');
    }
}

// Einfache Form-Übermittlung für Such-Input
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                searchInput.form.submit();
            }, 1000); // 1 Sekunde Verzögerung
        });
    }
});
</script>

<style>
/* Zusätzliche Styles für bessere UX */
.blog-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.blog-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 14px;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
    margin-right: 8px;
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

/* Filter-Dropdowns optimiert */
.filter-select {
    min-width: 180px;
    max-width: 220px;
    white-space: nowrap;
}

/* Action-Bar Layout optimiert */
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

.search-bar {
    flex: 1;
    min-width: 280px;
    max-width: 400px;
}

.filter-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

/* Responsive Design verbessert */
@media (max-width: 1200px) {
    .action-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .filter-controls {
        justify-content: flex-end;
    }
    
    .filter-select {
        min-width: 160px;
        max-width: 200px;
    }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .action-bar {
        gap: 12px;
    }
    
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

@media (max-width: 480px) {
    .action-bar {
        padding: 12px;
    }
    
    .search-input {
        font-size: 16px; /* Verhindert Zoom auf iOS */
    }
}
</style>