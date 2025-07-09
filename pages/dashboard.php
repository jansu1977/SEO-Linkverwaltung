<?php
/**
 * Dashboard Seite - KORRIGIERTE VERSION mit Admin-Unterstützung
 * pages/dashboard.php
 */

$userId = getCurrentUserId();

// Benutzer-Rolle prüfen - ADMIN-UNTERSTÜTZUNG HINZUFÜGEN
$users = loadData('users.json');
$currentUser = $users[$userId] ?? null;
$isAdmin = $currentUser && ($currentUser['role'] === 'admin');

// Daten laden
$blogs = loadData('blogs.json');
$customers = loadData('customers.json');
$links = loadData('links.json');

// KORRIGIERTE FILTERLOGIK: Admin sieht alle Daten
if ($isAdmin) {
    // Admin sieht alle Daten
    $userBlogs = $blogs;
    $userCustomers = $customers;
    $userLinks = $links;
} else {
    // Normale Benutzer sehen nur ihre eigenen Daten
    $userBlogs = array();
    foreach ($blogs as $blogId => $blog) {
        if (isset($blog['user_id']) && $blog['user_id'] === $userId) {
            $userBlogs[$blogId] = $blog;
        }
    }

    $userCustomers = array();
    foreach ($customers as $customerId => $customer) {
        if (isset($customer['user_id']) && $customer['user_id'] === $userId) {
            $userCustomers[$customerId] = $customer;
        }
    }

    $userLinks = array();
    foreach ($links as $linkId => $link) {
        if (isset($link['user_id']) && $link['user_id'] === $userId) {
            $userLinks[$linkId] = $link;
        }
    }
}

// Aktive Links zählen
$activeLinks = array();
foreach ($userLinks as $linkId => $link) {
    $status = isset($link['status']) ? $link['status'] : 'ausstehend';
    if ($status === 'aktiv') {
        $activeLinks[$linkId] = $link;
    }
}

// Umsatz berechnen
$totalRevenue = 0;
foreach ($userLinks as $link) {
    if (isset($link['price']) && is_numeric($link['price'])) {
        $totalRevenue += (float)$link['price'];
    }
}

// Neueste Links (letzte 5)
$recentLinks = array_slice(array_reverse($userLinks, true), 0, 5, true);
?>

<!-- Dashboard Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            Dashboard
            <?php if ($isAdmin): ?>
                <span class="badge badge-info" style="font-size: 12px; margin-left: 8px;">Admin-Ansicht</span>
            <?php endif; ?>
        </h1>
        <p class="page-subtitle">
            <?php if ($isAdmin): ?>
                Übersicht über alle LinkBuilder Aktivitäten im System (<?= count(array_unique(array_column($userBlogs, 'user_id'))) ?> aktive Benutzer)
            <?php else: ?>
                Überblick über Ihre LinkBuilder Aktivitäten
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

<!-- Statistik-Karten -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blogs">
            <i class="fas fa-blog"></i>
        </div>
        <div class="stat-content">
            <div class="stat-number"><?php echo count($userBlogs); ?></div>
            <div class="stat-label">
                <?= $isAdmin ? 'Blogs (gesamt)' : 'Blogs' ?>
            </div>
        </div>
        <div class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon customers">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <div class="stat-number"><?php echo count($userCustomers); ?></div>
            <div class="stat-label">
                <?= $isAdmin ? 'Kunden (gesamt)' : 'Kunden' ?>
            </div>
        </div>
        <div class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon links">
            <i class="fas fa-link"></i>
        </div>
        <div class="stat-content">
            <div class="stat-number"><?php echo count($userLinks); ?></div>
            <div class="stat-label">
                <?= $isAdmin ? 'Links (gesamt)' : 'Gesamt Links' ?>
            </div>
        </div>
        <div class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon active">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-number"><?php echo count($activeLinks); ?></div>
            <div class="stat-label">Aktive Links</div>
        </div>
        <div class="stat-change positive">
            <i class="fas fa-arrow-up"></i>
        </div>
    </div>
</div>

<!-- Admin-spezifische Zusatz-Statistiken -->
<?php if ($isAdmin): ?>
<div class="stats-grid" style="margin-top: 20px;">
    <div class="stat-card">
        <div class="stat-icon settings" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
            <i class="fas fa-users-cog"></i>
        </div>
        <div class="stat-content">
            <div class="stat-number"><?php echo count($users); ?></div>
            <div class="stat-label">Registrierte Benutzer</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
            <div class="stat-number">
                <?php 
                $activeUsers = count(array_unique(array_merge(
                    array_column($userBlogs, 'user_id'),
                    array_column($userCustomers, 'user_id'),
                    array_column($userLinks, 'user_id')
                )));
                echo $activeUsers;
                ?>
            </div>
            <div class="stat-label">Aktive Benutzer</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <i class="fas fa-percentage"></i>
        </div>
        <div class="stat-content">
            <div class="stat-number">
                <?php 
                $totalUsers = count($users);
                $activeUsersPercent = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0;
                echo $activeUsersPercent;
                ?>%
            </div>
            <div class="stat-label">Benutzer-Aktivität</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <div class="stat-number">
                <?php 
                $recentActivities = 0;
                $oneWeekAgo = date('Y-m-d H:i:s', strtotime('-1 week'));
                foreach ($userBlogs as $blog) {
                    if (($blog['created_at'] ?? '') > $oneWeekAgo) $recentActivities++;
                }
                foreach ($userCustomers as $customer) {
                    if (($customer['created_at'] ?? '') > $oneWeekAgo) $recentActivities++;
                }
                foreach ($userLinks as $link) {
                    if (($link['created_at'] ?? '') > $oneWeekAgo) $recentActivities++;
                }
                echo $recentActivities;
                ?>
            </div>
            <div class="stat-label">Neue Einträge (7 Tage)</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Content Grid -->
<div class="content-grid">
    <!-- Neueste Links -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?= $isAdmin ? 'Neueste Links (systemweit)' : 'Neueste Links' ?>
            </h3>
            <p class="card-subtitle">
                <?= $isAdmin ? 'Die zuletzt erstellten Links aller Benutzer' : 'Ihre zuletzt erstellten Links' ?>
            </p>
            <a href="?page=links" class="card-action">Alle anzeigen</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentLinks)): ?>
                <div class="empty-state">
                    <i class="fas fa-link"></i>
                    <h4>Keine Links vorhanden</h4>
                    <p><?= $isAdmin ? 'Es wurden noch keine Links im System erstellt' : 'Erstellen Sie Ihren ersten Link' ?></p>
                    <a href="?page=links&action=create" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Link erstellen
                    </a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ankertext</th>
                                <th>Blog</th>
                                <?php if ($isAdmin): ?>
                                    <th>Erstellt von</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLinks as $linkId => $link): 
                                $blog = isset($blogs[$link['blog_id']]) ? $blogs[$link['blog_id']] : null;
                                
                                // Link-Besitzer Info (nur für Admin)
                                if ($isAdmin) {
                                    $linkOwner = $users[$link['user_id']] ?? null;
                                    $linkOwnerName = $linkOwner ? ($linkOwner['name'] ?? $linkOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
                                    $isOwnLink = $link['user_id'] === $userId;
                                }
                            ?>
                                <tr>
                                    <td>
                                        <a href="?page=links&action=view&id=<?php echo $linkId; ?>" class="link-title">
                                            <?php echo e($link['anchor_text']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($blog): ?>
                                            <span class="blog-name"><?php echo e($blog['name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
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
                                    <td>
                                        <?php
                                        $status = isset($link['status']) ? $link['status'] : 'ausstehend';
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
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <?php echo formatDate(isset($link['created_at']) ? $link['created_at'] : ''); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Schnellaktionen -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Schnellaktionen</h3>
            <p class="card-subtitle">Häufig verwendete Aktionen</p>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="?page=blogs&action=create" class="quick-action">
                    <div class="quick-action-icon blogs">
                        <i class="fas fa-blog"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Blog hinzufügen</div>
                        <div class="quick-action-subtitle">Neuen Blog registrieren</div>
                    </div>
                </a>

                <a href="?page=customers&action=create" class="quick-action">
                    <div class="quick-action-icon customers">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Kunde hinzufügen</div>
                        <div class="quick-action-subtitle">Neuen Kunden anlegen</div>
                    </div>
                </a>

                <a href="?page=links&action=create" class="quick-action">
                    <div class="quick-action-icon links">
                        <i class="fas fa-link"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Link erstellen</div>
                        <div class="quick-action-subtitle">Neuen Link platzieren</div>
                    </div>
                </a>

                <?php if ($isAdmin): ?>
                    <a href="?page=admin_users" class="quick-action">
                        <div class="quick-action-icon settings">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="quick-action-content">
                            <div class="quick-action-title">Benutzer-Verwaltung</div>
                            <div class="quick-action-subtitle">Benutzer verwalten</div>
                        </div>
                    </a>
                <?php else: ?>
                    <a href="?page=settings" class="quick-action">
                        <div class="quick-action-icon settings">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="quick-action-content">
                            <div class="quick-action-title">Einstellungen</div>
                            <div class="quick-action-subtitle">System konfigurieren</div>
                        </div>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Blogs -->
<?php if (!empty($userBlogs)): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <?= $isAdmin ? 'Blogs im System' : 'Ihre Blogs' ?>
        </h3>
        <p class="card-subtitle">
            <?= $isAdmin ? 'Überblick über alle registrierten Blogs' : 'Überblick über Ihre registrierten Blogs' ?>
        </p>
        <a href="?page=blogs" class="card-action">Alle anzeigen</a>
    </div>
    <div class="card-body">
        <div class="blog-grid">
            <?php 
            $blogCount = 0;
            foreach ($userBlogs as $blogId => $blog): 
                if ($blogCount >= 6) break; // Admin sieht mehr Blogs
                
                // Links für diesen Blog zählen
                $blogLinkCount = 0;
                foreach ($userLinks as $link) {
                    if ($link['blog_id'] === $blogId) {
                        $blogLinkCount++;
                    }
                }
                
                // Blog-Besitzer Info (nur für Admin)
                if ($isAdmin) {
                    $blogOwner = $users[$blog['user_id']] ?? null;
                    $blogOwnerName = $blogOwner ? ($blogOwner['name'] ?? $blogOwner['username'] ?? 'Unbekannt') : 'Unbekannt';
                    $isOwnBlog = $blog['user_id'] === $userId;
                }
                
                $blogCount++;
            ?>
                <div class="blog-item">
                    <div class="blog-header">
                        <h4 class="blog-title">
                            <a href="?page=blogs&action=view&id=<?php echo $blogId; ?>">
                                <?php echo e($blog['name']); ?>
                            </a>
                        </h4>
                        <span class="blog-links-count"><?php echo $blogLinkCount; ?> Links</span>
                    </div>
                    <div class="blog-url">
                        <a href="<?php echo e($blog['url']); ?>" target="_blank" class="text-muted">
                            <?php echo e($blog['url']); ?>
                        </a>
                    </div>
                    <?php if ($isAdmin): ?>
                        <div style="margin-bottom: 8px; font-size: 12px;">
                            <?php if ($isOwnBlog): ?>
                                <span style="color: #10b981;">
                                    <i class="fas fa-crown" style="margin-right: 4px;"></i>
                                    Ihr Blog
                                </span>
                            <?php else: ?>
                                <span style="color: #fbbf24;">
                                    <i class="fas fa-user" style="margin-right: 4px;"></i>
                                    Von: <?= e($blogOwnerName) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($blog['topics'])): ?>
                        <div class="blog-topics">
                            <?php 
                            $topicCount = 0;
                            foreach ($blog['topics'] as $topic): 
                                if ($topicCount >= 2) break; // Nur die ersten 2 anzeigen
                                $topicCount++;
                            ?>
                                <span class="badge badge-secondary"><?php echo e($topic); ?></span>
                            <?php endforeach; ?>
                            <?php if (count($blog['topics']) > 2): ?>
                                <span class="text-muted">+<?php echo count($blog['topics']) - 2; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Admin-spezifische Benutzer-Aktivität -->
<?php if ($isAdmin && count($users) > 1): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Benutzer-Aktivität</h3>
        <p class="card-subtitle">Übersicht über die Aktivitäten der Benutzer</p>
        <a href="?page=admin_users" class="card-action">Alle Benutzer anzeigen</a>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Benutzer</th>
                        <th>Blogs</th>
                        <th>Kunden</th>
                        <th>Links</th>
                        <th>Letzter Login</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Top 5 aktivste Benutzer
                    $userActivity = [];
                    foreach ($users as $uid => $user) {
                        $userBlogs = array_filter($blogs, function($b) use ($uid) { return ($b['user_id'] ?? '') === $uid; });
                        $userCustomers = array_filter($customers, function($c) use ($uid) { return ($c['user_id'] ?? '') === $uid; });
                        $userLinks = array_filter($links, function($l) use ($uid) { return ($l['user_id'] ?? '') === $uid; });
                        
                        $totalActivity = count($userBlogs) + count($userCustomers) + count($userLinks);
                        
                        if ($totalActivity > 0) {
                            $userActivity[] = [
                                'user' => $user,
                                'blogs' => count($userBlogs),
                                'customers' => count($userCustomers),
                                'links' => count($userLinks),
                                'total' => $totalActivity
                            ];
                        }
                    }
                    
                    // Nach Aktivität sortieren
                    usort($userActivity, function($a, $b) {
                        return $b['total'] - $a['total'];
                    });
                    
                    foreach (array_slice($userActivity, 0, 5) as $activity):
                        $user = $activity['user'];
                        $isCurrentUser = $user['id'] === $userId;
                    ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php if ($isCurrentUser): ?>
                                        <i class="fas fa-crown" style="color: #fbbf24;"></i>
                                    <?php endif; ?>
                                    <span style="font-weight: 600; color: <?= $isCurrentUser ? '#10b981' : '#e2e8f0' ?>;">
                                        <?= e($user['name'] ?? $user['username'] ?? 'Unbekannt') ?>
                                    </span>
                                </div>
                            </td>
                            <td><span class="badge badge-secondary"><?= $activity['blogs'] ?></span></td>
                            <td><span class="badge badge-secondary"><?= $activity['customers'] ?></span></td>
                            <td><span class="badge badge-secondary"><?= $activity['links'] ?></span></td>
                            <td style="color: #8b8fa3; font-size: 12px;">
                                <?= !empty($user['last_login']) ? formatDateTime($user['last_login']) : 'Noch nie' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>