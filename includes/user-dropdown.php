<?php
// includes/user-dropdown.php
// Diese Datei wird in den Header eingebunden

$users = loadData('users.json');
$userId = getCurrentUserId();
$currentUser = $users[$userId] ?? null;

if (!$currentUser) {
    // Fallback falls Benutzer nicht gefunden wird
    $currentUser = ['name' => 'Unbekannt', 'email' => ''];
}
?>

<div class="user-dropdown" style="position: relative; display: inline-block;">
    <button class="user-button" onclick="toggleUserDropdown()" style="
        background: none;
        border: none;
        color: #8b8fa3;
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        font-size: 14px;
    " onmouseover="this.style.backgroundColor='#343852'; this.style.color='#fff';" 
       onmouseout="this.style.backgroundColor='transparent'; this.style.color='#8b8fa3';">
        <i class="fas fa-user-circle" style="font-size: 20px;"></i>
        <span><?= e($currentUser['name']) ?></span>
        <i class="fas fa-chevron-down" style="font-size: 12px; transition: transform 0.2s;" id="dropdownChevron"></i>
    </button>
    
    <div id="userDropdown" class="user-dropdown-menu" style="
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        background-color: #2a2d3e;
        border: 1px solid #3a3d52;
        border-radius: 8px;
        min-width: 220px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        z-index: 1000;
        display: none;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.2s ease;
    ">
        <!-- Benutzer-Info Header -->
        <div style="padding: 16px; border-bottom: 1px solid #3a3d52; background-color: #343852; border-radius: 8px 8px 0 0;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="
                    width: 40px;
                    height: 40px;
                    background: linear-gradient(135deg, #4dabf7, #2dd4bf);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: bold;
                    font-size: 16px;
                ">
                    <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; color: #fff; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= e($currentUser['name']) ?>
                    </div>
                    <div style="font-size: 12px; color: #8b8fa3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= e($currentUser['email']) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Navigation Links -->
        <div style="padding: 8px 0;">
            <a href="?page=profile" class="dropdown-item" style="
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 16px;
                color: #8b8fa3;
                text-decoration: none;
                transition: all 0.2s ease;
                font-size: 14px;
            " onmouseover="this.style.backgroundColor='#343852'; this.style.color='#fff';" 
               onmouseout="this.style.backgroundColor='transparent'; this.style.color='#8b8fa3';">
                <i class="fas fa-user" style="width: 16px; text-align: center;"></i>
                <span>Mein Profil</span>
            </a>
            
            <a href="?page=dashboard" class="dropdown-item" style="
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 16px;
                color: #8b8fa3;
                text-decoration: none;
                transition: all 0.2s ease;
                font-size: 14px;
            " onmouseover="this.style.backgroundColor='#343852'; this.style.color='#fff';" 
               onmouseout="this.style.backgroundColor='transparent'; this.style.color='#8b8fa3';">
                <i class="fas fa-tachometer-alt" style="width: 16px; text-align: center;"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="?page=settings" class="dropdown-item" style="
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 16px;
                color: #8b8fa3;
                text-decoration: none;
                transition: all 0.2s ease;
                font-size: 14px;
            " onmouseover="this.style.backgroundColor='#343852'; this.style.color='#fff';" 
               onmouseout="this.style.backgroundColor='transparent'; this.style.color='#8b8fa3';">
                <i class="fas fa-cog" style="width: 16px; text-align: center;"></i>
                <span>Einstellungen</span>
            </a>
            
            <!-- Trennlinie -->
            <div style="height: 1px; background-color: #3a3d52; margin: 8px 16px;"></div>
            
            <!-- Logout -->
            <a href="?action=logout" class="dropdown-item" style="
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 16px;
                color: #ef4444;
                text-decoration: none;
                transition: all 0.2s ease;
                font-size: 14px;
            " onmouseover="this.style.backgroundColor='#472f2f'; this.style.color='#ff6b6b';" 
               onmouseout="this.style.backgroundColor='transparent'; this.style.color='#ef4444';"
               onclick="return confirm('Möchten Sie sich wirklich abmelden?')">
                <i class="fas fa-sign-out-alt" style="width: 16px; text-align: center;"></i>
                <span>Abmelden</span>
            </a>
        </div>
    </div>
</div>

<script>
// User Dropdown Funktionalität
function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    const chevron = document.getElementById('dropdownChevron');
    
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        // Dropdown öffnen
        dropdown.style.display = 'block';
        setTimeout(() => {
            dropdown.style.opacity = '1';
            dropdown.style.transform = 'translateY(0)';
        }, 10);
        chevron.style.transform = 'rotate(180deg)';
    } else {
        // Dropdown schließen
        dropdown.style.opacity = '0';
        dropdown.style.transform = 'translateY(-10px)';
        chevron.style.transform = 'rotate(0deg)';
        setTimeout(() => {
            dropdown.style.display = 'none';
        }, 200);
    }
}

// Dropdown schließen wenn außerhalb geklickt wird
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('userDropdown');
    const userDropdownContainer = event.target.closest('.user-dropdown');
    
    if (!userDropdownContainer && dropdown && dropdown.style.display === 'block') {
        dropdown.style.opacity = '0';
        dropdown.style.transform = 'translateY(-10px)';
        document.getElementById('dropdownChevron').style.transform = 'rotate(0deg)';
        setTimeout(() => {
            dropdown.style.display = 'none';
        }, 200);
    }
});

// Escape-Taste zum Schließen
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown && dropdown.style.display === 'block') {
            dropdown.style.opacity = '0';
            dropdown.style.transform = 'translateY(-10px)';
            document.getElementById('dropdownChevron').style.transform = 'rotate(0deg)';
            setTimeout(() => {
                dropdown.style.display = 'none';
            }, 200);
        }
    }
});
</script>