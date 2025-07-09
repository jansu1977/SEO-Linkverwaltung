<?php
$action = $_GET['action'] ?? 'index';
$blogId = $_GET['id'] ?? null;
$userId = getCurrentUserId();

// Benutzer-Rolle prüfen
$users = loadData('users.json');
$currentUser = $users[$userId] ?? null;
$isAdmin = $currentUser && ($currentUser['role'] === 'admin');

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
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
                redirectWithMessage('?page=blogs', 'Blog erfolgreich erstellt.');
            } else {
                $error = 'Fehler beim Speichern des Blogs.';
            }
        }
    } elseif ($action === 'edit' && $blogId) {
        $blogs = loadData('blogs.json');
        // Admin kann alle Blogs bearbeiten, normale Benutzer nur ihre eigenen
        if (isset($blogs[$blogId]) && ($isAdmin || $blogs[$blogId]['user_id'] === $userId)) {
            $name = trim($_POST['name'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $description = trim($_POST['description'] ?? '');
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
                    redirectWithMessage("?page=blogs&action=view&id=$blogId", 'Blog erfolgreich aktualisiert.');
                } else {
                    $error = 'Fehler beim Aktualisieren des Blogs.';
                }
            }
        }
    } elseif ($action === 'delete' && $blogId) {
        $blogs = loadData('blogs.json');
        // Admin kann alle Blogs löschen, normale Benutzer nur ihre eigenen
        if (isset($blogs[$blogId]) && ($isAdmin || $blogs[$blogId]['user_id'] === $userId)) {
            unset($blogs[$blogId]);
            if (saveData('blogs.json', $blogs)) {
                redirectWithMessage('?page=blogs', 'Blog erfolgreich gelöscht.');
            } else {
                $error = 'Fehler beim Löschen des Blogs.';
            }
        }
    }
}

// Daten laden
$blogs = loadData('blogs.json');
$links = loadData('links.json');

// Blogs je nach Rolle filtern
if ($isAdmin) {
    // Admin sieht alle Blogs
    $userBlogs = $blogs;
} else {
    // Normale Benutzer sehen nur ihre eigenen Blogs
    $userBlogs = array_filter($blogs, function($blog) use ($userId) {
        return $blog['user_id'] === $userId;
    });
}

// Themen-Statistiken berechnen
$topicStats = [];
foreach ($userBlogs as $blog) {
    foreach ($blog['topics'] as $topic) {
        $topicStats[$topic] = ($topicStats[$topic] ?? 0) + 1;
    }
}
arsort($topicStats);

if ($action === 'index'): ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">
                Blogs
                <?php if ($isAdmin): ?>
                    <span class="badge badge-info" style="font-size: 12px; margin-left: 8px;">Admin-Ansicht</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <?php if ($isAdmin): ?>
                    Verwalten Sie alle Blogs im System (<?= count($userBlogs) ?> Blogs von <?= count(array_unique(array_column($userBlogs, 'user_id'))) ?> Benutzern)
                <?php else: ?>
                    Verwalten Sie Ihre Blogs
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

    <!-- Dashboard -->
    <div class="content-grid">
        <!-- Themenverteilung -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Themenverteilung</h3>
                <p class="card-subtitle">Überblick über die Verteilung der Blog-Themen</p>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <div class="chart-placeholder" id="topicChart"></div>
                    <?php if (!empty($topicStats)): ?>
                        <div class="chart-legend">
                            <?php $colors = ['#2dd4bf', '#4dabf7', '#fbbf24', '#f97316', '#f472b6', '#a78bfa']; ?>
                            <?php foreach (array_slice($topicStats, 0, 6) as $index => $topic): ?>
                                <div class="legend-item">
                                    <div class="legend-color" style="background-color: <?= $colors[$index % count($colors)] ?>;"></div>
                                    <span><?= e(array_keys($topicStats)[$index]) ?> (<?= $topic ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
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
                    
                    <div style="margin-top: 12px;">
                        <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">Neuester Eintrag</div>
                        <div style="color: #8b8fa3;"><?= formatDate(max(array_column($userBlogs, 'created_at'))) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filter und Suche -->
    <div class="action-bar" style="margin-top: 30px;">
        <div class="search-bar" style="flex: 1; max-width: 400px;">
            <div style="position: relative;">
                <i class="fas fa-search search-icon"></i>
                <input 
                    type="text" 
                    class="form-control search-input" 
                    placeholder="Blogs durchsuchen (Name, URL<?= $isAdmin ? ', Besitzer' : '' ?>)"
                    id="blogSearch"
                    onkeyup="filterBlogs()"
                >
            </div>
        </div>
        <div style="display: flex; gap: 12px;">
            <select class="form-control" id="topicFilter" onchange="filterBlogs()" style="width: auto;">
                <option value="">Nach Topic filtern</option>
                <?php foreach (array_keys($topicStats) as $topic): ?>
                    <option value="<?= e($topic) ?>"><?= e($topic) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isAdmin && count($userBlogs) > 0): ?>
                <select class="form-control" id="userFilter" onchange="filterBlogs()" style="width: auto;">
                    <option value="">Nach Benutzer filtern</option>
                    <?php 
                    $userIds = array_unique(array_column($userBlogs, 'user_id'));
                    foreach ($userIds as $uid): 
                        $user = $users[$uid] ?? null;
                        if ($user):
                    ?>
                        <option value="<?= e($uid) ?>"><?= e($user['name'] ?? $user['username'] ?? 'Unbekannt') ?></option>
                    <?php endif; endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </div>

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
                    return $link['blog_id'] === $blogId;
                });
                $linkCount = count($blogLinks);
                
                // Blog-Besitzer Info
                $blogOwner = $users[$blog['user_id']] ?? null;
                $ownerName = $blogOwner ? ($blogOwner['name'] ?? $blogOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
                $isOwnBlog = $blog['user_id'] === $userId;
            ?>
                <div class="card blog-card" 
                     data-name="<?= strtolower($blog['name']) ?>" 
                     data-url="<?= strtolower($blog['url']) ?>"
                     data-topics="<?= strtolower(implode(' ', $blog['topics'])) ?>"
                     data-user-id="<?= $blog['user_id'] ?>"
                     data-owner="<?= strtolower($ownerName) ?>">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div style="flex: 1;">
                                <h3 style="margin-bottom: 4px; font-size: 18px;">
                                    <a href="?page=blogs&action=view&id=<?= $blogId ?>" style="color: #4dabf7; text-decoration: none;">
                                        <?= e($blog['name']) ?>
                                    </a>
                                </h3>
                                <div style="font-size: 13px; color: #8b8fa3; margin-bottom: 8px;">
                                    <a href="<?= e($blog['url']) ?>" target="_blank" style="color: #4dabf7; text-decoration: none;">
                                        <?= e($blog['url']) ?>
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
                                    <a href="?page=blogs&action=delete&id=<?= $blogId ?>" class="btn btn-sm btn-danger" title="Löschen" onclick="return confirm('Blog &quot;<?= e($blog['name']) ?>&quot; wirklich löschen?<?= $isAdmin && !$isOwnBlog ? '\n\nDieser Blog gehört ' . e($ownerName) . '.' : '' ?>')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($blog['description'])): ?>
                            <p style="color: #8b8fa3; font-size: 13px; margin-bottom: 12px; line-height: 1.4;">
                                <?= e(substr($blog['description'], 0, 100)) ?><?= strlen($blog['description']) > 100 ? '...' : '' ?>
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
                                    Aktualisiert am <?= formatDate($blog['updated_at']) ?>
                                <?php else: ?>
                                    Hinzugefügt am <?= formatDate($blog['created_at']) ?>
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
        header('HTTP/1.0 404 Not Found');
        include 'pages/404.php';
        return;
    }
    
    // Links für diesen Blog
    $blogLinks = array_filter($links, function($link) use ($blogId) {
        return $link['blog_id'] === $blogId;
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
                                        <td><?= formatDate($link['published_date']) ?></td>
                                        <td>
                                            <a href="?page=links&action=view&id=<?= $linkId ?>" style="color: #4dabf7; text-decoration: none;">
                                                <?= e($link['anchor_text']) ?>
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
                                            <a href="<?= e($link['target_url']) ?>" target="_blank" style="color: #4dabf7; text-decoration: none; font-size: 12px;">
                                                <?= e(strlen($link['target_url']) > 40 ? substr($link['target_url'], 0, 40) . '...' : $link['target_url']) ?>
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
                                Erster Link: <?= formatDate(min(array_column($blogLinks, 'published_date'))) ?><br>
                                Letzter Link: <?= formatDate(max(array_column($blogLinks, 'published_date'))) ?>
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
            header('HTTP/1.0 404 Not Found');
            include 'pages/404.php';
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

<?php endif; ?>

<script>
function filterBlogs() {
    const search = document.getElementById('blogSearch').value.toLowerCase();
    const topicFilter = document.getElementById('topicFilter') ? document.getElementById('topicFilter').value.toLowerCase() : '';
    const userFilter = document.getElementById('userFilter') ? document.getElementById('userFilter').value : '';
    const cards = document.querySelectorAll('.blog-card');
    
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const url = card.dataset.url || '';
        const topics = card.dataset.topics || '';
        const userId = card.dataset.userId || '';
        const owner = card.dataset.owner || '';
        
        const searchMatch = !search || name.includes(search) || url.includes(search) || owner.includes(search);
        const topicMatch = !topicFilter || topics.includes(topicFilter);
        const userMatch = !userFilter || userId === userFilter;
        
        const matches = searchMatch && topicMatch && userMatch;
        card.style.display = matches ? 'block' : 'none';
    });
}

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
    document.getElementById(tabName + 'Tab').style.display = 'block';
    event.target.classList.add('active');
}
</script>