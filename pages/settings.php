<?php
$action = $_GET['action'] ?? 'index';
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'general') {
        $settings = loadData('settings.json');
        
        $settings['general'] = [
            'app_name' => trim($_POST['app_name'] ?? 'LinkBuilder Pro'),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'timezone' => $_POST['timezone'] ?? 'Europe/Berlin',
            'date_format' => $_POST['date_format'] ?? 'd.m.Y',
            'currency' => $_POST['currency'] ?? 'EUR',
            'items_per_page' => (int)($_POST['items_per_page'] ?? 25),
            'auto_backup' => isset($_POST['auto_backup']),
            'backup_retention_days' => (int)($_POST['backup_retention_days'] ?? 30)
        ];
        
        if (saveData('settings.json', $settings)) {
            redirectWithMessage('?page=settings', 'Allgemeine Einstellungen erfolgreich gespeichert.');
        } else {
            $error = 'Fehler beim Speichern der Einstellungen.';
        }
    } elseif ($action === 'ui') {
        $userPrefs = loadData('user_preferences.json');
        
        if (!isset($userPrefs[$userId])) {
            $userPrefs[$userId] = [];
        }
        
        $userPrefs[$userId]['ui'] = [
            'dark_mode' => isset($_POST['dark_mode']),
            'compact_view' => isset($_POST['compact_view']),
            'sidebar_collapsed' => isset($_POST['sidebar_collapsed']),
            'show_tooltips' => isset($_POST['show_tooltips']),
            'animation_enabled' => isset($_POST['animation_enabled']),
            'auto_save' => isset($_POST['auto_save'])
        ];
        
        if (saveData('user_preferences.json', $userPrefs)) {
            redirectWithMessage('?page=settings&action=ui', 'UI-Einstellungen erfolgreich gespeichert.');
        } else {
            $error = 'Fehler beim Speichern der UI-Einstellungen.';
        }
    } elseif ($action === 'topics') {
        $settings = loadData('settings.json');
        
        $topics = array_filter(array_map('trim', explode(',', $_POST['topics'] ?? '')));
        $predefinedTopics = array_filter(array_map('trim', explode(',', $_POST['predefined_topics'] ?? '')));
        
        $settings['topics'] = [
            'custom_topics' => $topics,
            'predefined_topics' => $predefinedTopics,
            'auto_suggest' => isset($_POST['auto_suggest']),
            'case_sensitive' => isset($_POST['case_sensitive'])
        ];
        
        if (saveData('settings.json', $settings)) {
            redirectWithMessage('?page=settings&action=topics', 'Themen-Einstellungen erfolgreich gespeichert.');
        } else {
            $error = 'Fehler beim Speichern der Themen-Einstellungen.';
        }
    } elseif ($action === 'notifications' && $userRole === 'admin') {
        $settings = loadData('settings.json');
        
        $settings['notifications'] = [
            'email_enabled' => isset($_POST['email_enabled']),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'smtp_host' => trim($_POST['smtp_host'] ?? ''),
            'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
            'smtp_username' => trim($_POST['smtp_username'] ?? ''),
            'smtp_password' => trim($_POST['smtp_password'] ?? ''),
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'link_expiry_warning' => isset($_POST['link_expiry_warning']),
            'expiry_warning_days' => (int)($_POST['expiry_warning_days'] ?? 7),
            'broken_link_warning' => isset($_POST['broken_link_warning']),
            'daily_summary' => isset($_POST['daily_summary']),
            'weekly_report' => isset($_POST['weekly_report'])
        ];
        
        if (saveData('settings.json', $settings)) {
            redirectWithMessage('?page=settings&action=notifications', 'Benachrichtigungseinstellungen erfolgreich gespeichert.');
        } else {
            $error = 'Fehler beim Speichern der Benachrichtigungseinstellungen.';
        }
    }
}

// Daten laden
$settings = loadData('settings.json');
$userPrefs = loadData('user_preferences.json');
$currentUserPrefs = $userPrefs[$userId] ?? [];

// Standard-Einstellungen
$generalSettings = $settings['general'] ?? [
    'app_name' => 'LinkBuilder Pro',
    'company_name' => '',
    'timezone' => 'Europe/Berlin',
    'date_format' => 'd.m.Y',
    'currency' => 'EUR',
    'items_per_page' => 25,
    'auto_backup' => false,
    'backup_retention_days' => 30
];

$uiSettings = $currentUserPrefs['ui'] ?? [
    'dark_mode' => false,
    'compact_view' => false,
    'sidebar_collapsed' => false,
    'show_tooltips' => true,
    'animation_enabled' => true,
    'auto_save' => true
];

$topicSettings = $settings['topics'] ?? [
    'custom_topics' => [],
    'predefined_topics' => ['SEO', 'Content Marketing', 'Linkbuilding', 'Digital Marketing', 'Webentwicklung', 'E-Commerce'],
    'auto_suggest' => true,
    'case_sensitive' => false
];

$notificationSettings = $settings['notifications'] ?? [
    'email_enabled' => false,
    'admin_email' => '',
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'link_expiry_warning' => true,
    'expiry_warning_days' => 7,
    'broken_link_warning' => true,
    'daily_summary' => false,
    'weekly_report' => false
];

if ($action === 'index'): ?>
    <div class="page-header">
        <div>
            <h1 class="page-title">Einstellungen</h1>
            <p class="page-subtitle">Konfigurieren Sie Ihre Anwendung und Benutzereinstellungen</p>
        </div>
    </div>

    <?php showFlashMessage(); ?>

    <!-- Einstellungs-Navigation -->
    <div class="settings-nav">
        <a href="?page=settings&action=general" class="settings-nav-item">
            <i class="fas fa-cogs"></i>
            <div>
                <div class="nav-title">Allgemeine Einstellungen</div>
                <div class="nav-subtitle">Grundlegende Konfiguration für Ihren Arbeitsbereich</div>
            </div>
            <i class="fas fa-chevron-right"></i>
        </a>
        
        <a href="?page=settings&action=ui" class="settings-nav-item">
            <i class="fas fa-palette"></i>
            <div>
                <div class="nav-title">Benutzeroberfläche</div>
                <div class="nav-subtitle">Passen Sie das Erscheinungsbild der Anwendung an</div>
            </div>
            <i class="fas fa-chevron-right"></i>
        </a>
        
        <a href="?page=topics" class="settings-nav-item">
            <i class="fas fa-tags"></i>
            <div>
                <div class="nav-title">Themenverwaltung</div>
                <div class="nav-subtitle">Verwalten Sie farbige Themen für Blogs und Kunden</div>
            </div>
            <i class="fas fa-chevron-right"></i>
        </a>
        
        <?php if ($userRole === 'admin'): ?>
            <a href="?page=settings&action=notifications" class="settings-nav-item">
                <i class="fas fa-bell"></i>
                <div>
                    <div class="nav-title">Benachrichtigungen</div>
                    <div class="nav-subtitle">Konfigurieren Sie E-Mail-Benachrichtigungen und Warnungen</div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>

    <!-- Quick Settings -->
    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            <h3 class="card-title">Schnelleinstellungen</h3>
            <p class="card-subtitle">Häufig verwendete Einstellungen</p>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="quick-setting">
                    <div class="quick-setting-icon">
                        <i class="fas fa-moon"></i>
                    </div>
                    <div class="quick-setting-content">
                        <div class="quick-setting-title">Dunkelmodus</div>
                        <div class="quick-setting-subtitle">
                            <?= $uiSettings['dark_mode'] ? 'Aktiviert' : 'Deaktiviert' ?>
                        </div>
                    </div>
                    <div class="toggle-switch">
                        <input type="checkbox" id="quickDarkMode" <?= $uiSettings['dark_mode'] ? 'checked' : '' ?> onchange="toggleQuickSetting('dark_mode', this.checked)">
                        <label for="quickDarkMode"></label>
                    </div>
                </div>
                
                <div class="quick-setting">
                    <div class="quick-setting-icon">
                        <i class="fas fa-compress-alt"></i>
                    </div>
                    <div class="quick-setting-content">
                        <div class="quick-setting-title">Kompakte Ansicht</div>
                        <div class="quick-setting-subtitle">
                            <?= $uiSettings['compact_view'] ? 'Aktiviert' : 'Deaktiviert' ?>
                        </div>
                    </div>
                    <div class="toggle-switch">
                        <input type="checkbox" id="quickCompactView" <?= $uiSettings['compact_view'] ? 'checked' : '' ?> onchange="toggleQuickSetting('compact_view', this.checked)">
                        <label for="quickCompactView"></label>
                    </div>
                </div>
                
                <div class="quick-setting">
                    <div class="quick-setting-icon">
                        <i class="fas fa-save"></i>
                    </div>
                    <div class="quick-setting-content">
                        <div class="quick-setting-title">Automatisches Speichern</div>
                        <div class="quick-setting-subtitle">
                            <?= $uiSettings['auto_save'] ? 'Aktiviert' : 'Deaktiviert' ?>
                        </div>
                    </div>
                    <div class="toggle-switch">
                        <input type="checkbox" id="quickAutoSave" <?= $uiSettings['auto_save'] ? 'checked' : '' ?> onchange="toggleQuickSetting('auto_save', this.checked)">
                        <label for="quickAutoSave"></label>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'general'): ?>
    <div class="breadcrumb">
        <a href="?page=settings">Zurück zu Einstellungen</a>
        <i class="fas fa-chevron-right"></i>
        <span>Allgemeine Einstellungen</span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">Allgemeine Einstellungen</h1>
            <p class="page-subtitle">Grundlegende Konfiguration für Ihre Anwendung</p>
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
                <div class="form-section">
                    <h3 class="section-title">Anwendungseinstellungen</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="app_name" class="form-label">Anwendungsname</label>
                            <input 
                                type="text" 
                                id="app_name" 
                                name="app_name" 
                                class="form-control" 
                                value="<?= e($generalSettings['app_name']) ?>"
                            >
                        </div>
                        <div class="form-group">
                            <label for="company_name" class="form-label">Firmenname</label>
                            <input 
                                type="text" 
                                id="company_name" 
                                name="company_name" 
                                class="form-control" 
                                value="<?= e($generalSettings['company_name']) ?>"
                            >
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Lokalisierung</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="timezone" class="form-label">Zeitzone</label>
                            <select id="timezone" name="timezone" class="form-control">
                                <option value="Europe/Berlin" <?= $generalSettings['timezone'] === 'Europe/Berlin' ? 'selected' : '' ?>>Europa/Berlin</option>
                                <option value="Europe/Vienna" <?= $generalSettings['timezone'] === 'Europe/Vienna' ? 'selected' : '' ?>>Europa/Wien</option>
                                <option value="Europe/Zurich" <?= $generalSettings['timezone'] === 'Europe/Zurich' ? 'selected' : '' ?>>Europa/Zürich</option>
                                <option value="UTC" <?= $generalSettings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_format" class="form-label">Datumsformat</label>
                            <select id="date_format" name="date_format" class="form-control">
                                <option value="d.m.Y" <?= $generalSettings['date_format'] === 'd.m.Y' ? 'selected' : '' ?>>DD.MM.YYYY</option>
                                <option value="Y-m-d" <?= $generalSettings['date_format'] === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                                <option value="m/d/Y" <?= $generalSettings['date_format'] === 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="currency" class="form-label">Währung</label>
                            <select id="currency" name="currency" class="form-control">
                                <option value="EUR" <?= $generalSettings['currency'] === 'EUR' ? 'selected' : '' ?>>Euro (€)</option>
                                <option value="USD" <?= $generalSettings['currency'] === 'USD' ? 'selected' : '' ?>>US Dollar ($)</option>
                                <option value="CHF" <?= $generalSettings['currency'] === 'CHF' ? 'selected' : '' ?>>Schweizer Franken (CHF)</option>
                                <option value="GBP" <?= $generalSettings['currency'] === 'GBP' ? 'selected' : '' ?>>Britisches Pfund (£)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Anzeige-Einstellungen</h3>
                    
                    <div class="form-group">
                        <label for="items_per_page" class="form-label">Einträge pro Seite</label>
                        <select id="items_per_page" name="items_per_page" class="form-control" style="width: auto;">
                            <option value="10" <?= $generalSettings['items_per_page'] === 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $generalSettings['items_per_page'] === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $generalSettings['items_per_page'] === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $generalSettings['items_per_page'] === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Backup-Einstellungen</h3>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="auto_backup" <?= $generalSettings['auto_backup'] ? 'checked' : '' ?>>
                                <span class="checkmark"></span>
                                Automatische Backups aktivieren
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="backup_retention_days" class="form-label">Backup-Aufbewahrungszeit (Tage)</label>
                        <input 
                            type="number" 
                            id="backup_retention_days" 
                            name="backup_retention_days" 
                            class="form-control" 
                            style="width: auto;"
                            min="1"
                            max="365"
                            value="<?= $generalSettings['backup_retention_days'] ?>"
                        >
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Einstellungen speichern
                    </button>
                    <a href="?page=settings" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'ui'): ?>
    <div class="breadcrumb">
        <a href="?page=settings">Zurück zu Einstellungen</a>
        <i class="fas fa-chevron-right"></i>
        <span>Benutzeroberfläche</span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">Benutzeroberfläche</h1>
            <p class="page-subtitle">Passen Sie das Erscheinungsbild der Anwendung an</p>
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
                <div class="form-section">
                    <h3 class="section-title">Design & Thema</h3>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Dunkelmodus</div>
                            <div class="ui-setting-subtitle">Dunkles Thema für die Anwendungsoberfläche verwenden</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="dark_mode" name="dark_mode" <?= $uiSettings['dark_mode'] ? 'checked' : '' ?>>
                            <label for="dark_mode"></label>
                        </div>
                    </div>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Kompakte Ansicht</div>
                            <div class="ui-setting-subtitle">Reduzierte Abstände für mehr Inhalte auf dem Bildschirm</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="compact_view" name="compact_view" <?= $uiSettings['compact_view'] ? 'checked' : '' ?>>
                            <label for="compact_view"></label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Navigation</h3>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Sidebar eingeklappt</div>
                            <div class="ui-setting-subtitle">Seitenleiste standardmäßig eingeklappt anzeigen</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="sidebar_collapsed" name="sidebar_collapsed" <?= $uiSettings['sidebar_collapsed'] ? 'checked' : '' ?>>
                            <label for="sidebar_collapsed"></label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Benutzerfreundlichkeit</h3>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Tooltips anzeigen</div>
                            <div class="ui-setting-subtitle">Hilfreiche Tooltips bei Schaltflächen und Elementen anzeigen</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="show_tooltips" name="show_tooltips" <?= $uiSettings['show_tooltips'] ? 'checked' : '' ?>>
                            <label for="show_tooltips"></label>
                        </div>
                    </div>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Animationen aktiviert</div>
                            <div class="ui-setting-subtitle">Smooth Animationen und Übergänge verwenden</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="animation_enabled" name="animation_enabled" <?= $uiSettings['animation_enabled'] ? 'checked' : '' ?>>
                            <label for="animation_enabled"></label>
                        </div>
                    </div>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Automatisches Speichern</div>
                            <div class="ui-setting-subtitle">Formulardaten automatisch speichern während der Eingabe</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="auto_save" name="auto_save" <?= $uiSettings['auto_save'] ? 'checked' : '' ?>>
                            <label for="auto_save"></label>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        UI-Einstellungen speichern
                    </button>
                    <a href="?page=settings" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'topics'): ?>
    <div class="breadcrumb">
        <a href="?page=settings">Zurück zu Einstellungen</a>
        <i class="fas fa-chevron-right"></i>
        <span>Themenverwaltung</span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">Themenverwaltung</h1>
            <p class="page-subtitle">Verwalten Sie Themen für Blogs und Kunden</p>
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
                <div class="form-section">
                    <h3 class="section-title">Vordefinierte Themen</h3>
                    <p style="color: #8b8fa3; margin-bottom: 16px;">Diese Themen werden als Vorschläge in Formularen angezeigt</p>
                    
                    <div class="form-group">
                        <label for="predefined_topics" class="form-label">Standard-Themen</label>
                        <input 
                            type="text" 
                            id="predefined_topics" 
                            name="predefined_topics" 
                            class="form-control" 
                            placeholder="SEO, Content Marketing, Linkbuilding"
                            value="<?= e(implode(', ', $topicSettings['predefined_topics'])) ?>"
                        >
                        <small style="color: #8b8fa3; font-size: 12px;">Mehrere Themen durch Komma trennen</small>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Benutzerdefinierte Themen</h3>
                    <p style="color: #8b8fa3; margin-bottom: 16px;">Zusätzliche Themen, die Sie häufig verwenden</p>
                    
                    <div class="form-group">
                        <label for="topics" class="form-label">Eigene Themen</label>
                        <input 
                            type="text" 
                            id="topics" 
                            name="topics" 
                            class="form-control" 
                            placeholder="Webentwicklung, E-Commerce, Social Media"
                            value="<?= e(implode(', ', $topicSettings['custom_topics'])) ?>"
                        >
                        <small style="color: #8b8fa3; font-size: 12px;">Mehrere Themen durch Komma trennen</small>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Themen-Einstellungen</h3>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Automatische Vorschläge</div>
                            <div class="ui-setting-subtitle">Themen-Vorschläge beim Tippen anzeigen</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="auto_suggest" name="auto_suggest" <?= $topicSettings['auto_suggest'] ? 'checked' : '' ?>>
                            <label for="auto_suggest"></label>
                        </div>
                    </div>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Groß-/Kleinschreibung beachten</div>
                            <div class="ui-setting-subtitle">Bei Themen-Vorschlägen Groß- und Kleinschreibung unterscheiden</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="case_sensitive" name="case_sensitive" <?= $topicSettings['case_sensitive'] ? 'checked' : '' ?>>
                            <label for="case_sensitive"></label>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Themen-Einstellungen speichern
                    </button>
                    <a href="?page=settings" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'notifications' && $userRole === 'admin'): ?>
    <div class="breadcrumb">
        <a href="?page=settings">Zurück zu Einstellungen</a>
        <i class="fas fa-chevron-right"></i>
        <span>Benachrichtigungen</span>
    </div>

    <div class="page-header">
        <div>
            <h1 class="page-title">Benachrichtigungseinstellungen</h1>
            <p class="page-subtitle">Konfigurieren Sie E-Mail-Benachrichtigungen und Warnungen</p>
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
                <div class="form-section">
                    <h3 class="section-title">E-Mail-Konfiguration</h3>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">E-Mail-Benachrichtigungen</div>
                            <div class="ui-setting-subtitle">E-Mail-Benachrichtigungen für wichtige Ereignisse aktivieren</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="email_enabled" name="email_enabled" <?= $notificationSettings['email_enabled'] ? 'checked' : '' ?>>
                            <label for="email_enabled"></label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email" class="form-label">Admin E-Mail-Adresse</label>
                        <input 
                            type="email" 
                            id="admin_email" 
                            name="admin_email" 
                            class="form-control" 
                            placeholder="admin@example.com"
                            value="<?= e($notificationSettings['admin_email']) ?>"
                        >
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">SMTP-Einstellungen</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_host" class="form-label">SMTP-Server</label>
                            <input 
                                type="text" 
                                id="smtp_host" 
                                name="smtp_host" 
                                class="form-control" 
                                placeholder="mail.example.com"
                                value="<?= e($notificationSettings['smtp_host']) ?>"
                            >
                        </div>
                        <div class="form-group">
                            <label for="smtp_port" class="form-label">Port</label>
                            <input 
                                type="number" 
                                id="smtp_port" 
                                name="smtp_port" 
                                class="form-control" 
                                placeholder="587"
                                value="<?= $notificationSettings['smtp_port'] ?>"
                            >
                        </div>
                        <div class="form-group">
                            <label for="smtp_encryption" class="form-label">Verschlüsselung</label>
                            <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                                <option value="none" <?= $notificationSettings['smtp_encryption'] === 'none' ? 'selected' : '' ?>>Keine</option>
                                <option value="tls" <?= $notificationSettings['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= $notificationSettings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_username" class="form-label">Benutzername</label>
                            <input 
                                type="text" 
                                id="smtp_username" 
                                name="smtp_username" 
                                class="form-control" 
                                value="<?= e($notificationSettings['smtp_username']) ?>"
                            >
                        </div>
                        <div class="form-group">
                            <label for="smtp_password" class="form-label">Passwort</label>
                            <input 
                                type="password" 
                                id="smtp_password" 
                                name="smtp_password" 
                                class="form-control" 
                                value="<?= e($notificationSettings['smtp_password']) ?>"
                            >
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Benachrichtigungstypen</h3>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Link-Ablauf Warnungen</div>
                            <div class="ui-setting-subtitle">Benachrichtigt werden, wenn Links kurz vor dem Ablauf stehen</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="link_expiry_warning" name="link_expiry_warning" <?= $notificationSettings['link_expiry_warning'] ? 'checked' : '' ?>>
                            <label for="link_expiry_warning"></label>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-left: 60px;">
                        <label for="expiry_warning_days" class="form-label">Warnung X Tage vor Ablauf</label>
                        <input 
                            type="number" 
                            id="expiry_warning_days" 
                            name="expiry_warning_days" 
                            class="form-control" 
                            style="width: auto;"
                            min="1"
                            max="30"
                            value="<?= $notificationSettings['expiry_warning_days'] ?>"
                        >
                    </div>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Defekte Link Warnungen</div>
                            <div class="ui-setting-subtitle">Benachrichtigungen erhalten, wenn Links defekt werden</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="broken_link_warning" name="broken_link_warning" <?= $notificationSettings['broken_link_warning'] ? 'checked' : '' ?>>
                            <label for="broken_link_warning"></label>
                        </div>
                    </div>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Tägliche Zusammenfassung</div>
                            <div class="ui-setting-subtitle">Tägliche E-Mail mit wichtigen Ereignissen</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="daily_summary" name="daily_summary" <?= $notificationSettings['daily_summary'] ? 'checked' : '' ?>>
                            <label for="daily_summary"></label>
                        </div>
                    </div>
                    
                    <div class="ui-setting">
                        <div class="ui-setting-content">
                            <div class="ui-setting-title">Wöchentlicher Bericht</div>
                            <div class="ui-setting-subtitle">Wöchentliche E-Mail mit Statistiken und Übersicht</div>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="weekly_report" name="weekly_report" <?= $notificationSettings['weekly_report'] ? 'checked' : '' ?>>
                            <label for="weekly_report"></label>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Benachrichtigungseinstellungen speichern
                    </button>
                    <button type="button" class="btn btn-info" onclick="testEmailSettings()">
                        <i class="fas fa-paper-plane"></i>
                        Test-E-Mail senden
                    </button>
                    <a href="?page=settings" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>

<style>
.settings-nav {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.settings-nav-item {
    display: flex;
    align-items: center;
    padding: 20px;
    background-color: #2a2d42;
    border-radius: 8px;
    text-decoration: none;
    color: #e4e4e7;
    transition: all 0.3s ease;
    border: 1px solid #3a3d52;
}

.settings-nav-item:hover {
    background-color: #343852;
    border-color: #4dabf7;
    color: #4dabf7;
    text-decoration: none;
}

.settings-nav-item i:first-child {
    width: 24px;
    height: 24px;
    margin-right: 16px;
    font-size: 20px;
    color: #4dabf7;
}

.settings-nav-item > div {
    flex: 1;
}

.nav-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
}

.nav-subtitle {
    font-size: 14px;
    color: #8b8fa3;
}

.settings-nav-item i:last-child {
    color: #8b8fa3;
}

.form-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid #3a3d52;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 16px;
    color: #e4e4e7;
}

.ui-setting {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 0;
    border-bottom: 1px solid #3a3d52;
}

.ui-setting:last-child {
    border-bottom: none;
}

.ui-setting-content {
    flex: 1;
}

.ui-setting-title {
    font-size: 15px;
    font-weight: 500;
    color: #e4e4e7;
    margin-bottom: 4px;
}

.ui-setting-subtitle {
    font-size: 13px;
    color: #8b8fa3;
}

.toggle-switch {
    position: relative;
    width: 48px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-switch label {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #3a3d52;
    transition: 0.3s;
    border-radius: 24px;
}

.toggle-switch label:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: #8b8fa3;
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-switch input:checked + label {
    background-color: #4dabf7;
}

.toggle-switch input:checked + label:before {
    transform: translateX(24px);
    background-color: white;
}

.quick-setting {
    display: flex;
    align-items: center;
    padding: 16px;
    background-color: #343852;
    border-radius: 8px;
    border: 1px solid #3a3d52;
}

.quick-setting-icon {
    width: 40px;
    height: 40px;
    background-color: #4dabf7;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    color: white;
    font-size: 18px;
}

.quick-setting-content {
    flex: 1;
}

.quick-setting-title {
    font-size: 14px;
    font-weight: 600;
    color: #e4e4e7;
    margin-bottom: 2px;
}

.quick-setting-subtitle {
    font-size: 12px;
    color: #8b8fa3;
}
</style>

<script>
function toggleQuickSetting(setting, value) {
    // AJAX-Call für schnelle Einstellungsänderung
    fetch('?page=settings&action=quick_update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `setting=${setting}&value=${value ? '1' : '0'}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // UI sofort aktualisieren
            updateQuickSettingDisplay(setting, value);
        }
    });
}

function updateQuickSettingDisplay(setting, value) {
    const settingElement = document.querySelector(`#quick${setting.charAt(0).toUpperCase() + setting.slice(1).replace('_', '')}`);
    if (settingElement) {
        const subtitle = settingElement.closest('.quick-setting').querySelector('.quick-setting-subtitle');
        if (subtitle) {
            subtitle.textContent = value ? 'Aktiviert' : 'Deaktiviert';
        }
    }
}

function testEmailSettings() {
    const button = event.target;
    const originalText = button.innerHTML;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sende Test-E-Mail...';
    button.disabled = true;
    
    fetch('?page=settings&action=test_email', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Test-E-Mail erfolgreich gesendet!');
        } else {
            alert('Fehler beim Senden der Test-E-Mail: ' + data.error);
        }
    })
    .catch(error => {
        alert('Fehler beim Senden der Test-E-Mail: ' + error.message);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}
</script>