<?php
// includes/logout.php
// Diese Datei sollte in Ihrer Hauptindex.php oder einem zentralen Handler eingebunden werden

function handleLogout() {
    // Session starten falls noch nicht gestartet
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Session-Daten löschen
    $_SESSION = array();
    
    // Session-Cookie löschen
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Session zerstören
    session_destroy();
    
    // Zur Login-Seite weiterleiten mit Erfolgsmeldung
    header('Location: ?page=login&message=logged_out');
    exit;
}

// Logout-Handler in Ihrer Haupt-Routing-Logik
// Dies sollte ganz oben in Ihrer index.php stehen, vor allem anderen
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    handleLogout();
}
?>