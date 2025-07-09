<?php
/**
 * Dashboard Seite - Kompatible Version
 * pages/dashboard.php
 */

$userId = getCurrentUserId();

// Daten laden
$blogs = loadData('blogs.json');
$customers = loadData('customers.json');
$links = loadData('links.json');

// Benutzer-spezifische Daten filtern (ohne Closures)
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
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Überblick über Ihre LinkBuilder Aktivitäten</p>
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
            <div class="stat-label">Blogs</div>
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
            <div class="stat-label">Kunden</div>
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
            <div class="stat-label">Gesamt Links</div>
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

<!-- Content Grid -->
<div class="content-grid">
    <!-- Neueste Links -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Neueste Links</h3>
            <p class="card-subtitle">Ihre zuletzt erstellten Links</p>
            <a href="?page=links" class="card-action">Alle anzeigen</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentLinks)): ?>
                <div class="empty-state">
                    <i class="fas fa-link"></i>
                    <h4>Keine Links vorhanden</h4>
                    <p>Erstellen Sie Ihren ersten Link</p>
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
                                <th>Status</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLinks as $linkId => $link): 
                                $blog = isset($blogs[$link['blog_id']]) ? $blogs[$link['blog_id']] : null;
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

                <a href="?page=settings" class="quick-action">
                    <div class="quick-action-icon settings">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Einstellungen</div>
                        <div class="quick-action-subtitle">System konfigurieren</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Top Blogs -->
<?php if (!empty($userBlogs)): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Ihre Blogs</h3>
        <p class="card-subtitle">Überblick über Ihre registrierten Blogs</p>
        <a href="?page=blogs" class="card-action">Alle anzeigen</a>
    </div>
    <div class="card-body">
        <div class="blog-grid">
            <?php 
            $blogCount = 0;
            foreach ($userBlogs as $blogId => $blog): 
                if ($blogCount >= 3) break; // Nur die ersten 3 anzeigen
                
                // Links für diesen Blog zählen
                $blogLinkCount = 0;
                foreach ($userLinks as $link) {
                    if ($link['blog_id'] === $blogId) {
                        $blogLinkCount++;
                    }
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