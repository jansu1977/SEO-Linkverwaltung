<?php
/**
 * LinkBuilder Pro - Komplettes Authentifizierungs-System
 * includes/auth.php - Neue Datei für Authentifizierung
 */

// Session-Management Funktionen
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUserId() {
    startSession();
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $users = loadData('users.json');
    $userId = getCurrentUserId();
    return $users[$userId] ?? null;
}

function loginUser($userId) {
    startSession();
    $_SESSION['user_id'] = $userId;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
}

function logoutUser() {
    startSession();
    
    // Session-Daten komplett löschen
    session_unset();
    session_destroy();
    
    // Cookies löschen
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Alle Session-Cookies löschen
    foreach ($_COOKIE as $key => $value) {
        if (strpos($key, 'PHPSESS') === 0) {
            setcookie($key, '', time() - 3600, '/');
        }
    }
}

function generateUserId() {
    return uniqid(rand(), true);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sendPasswordResetEmail($email, $resetToken) {
    // Hier würden Sie normalerweise eine E-Mail senden
    // Für Demo-Zwecke loggen wir nur
    error_log("Password Reset Token für $email: $resetToken");
    return true; // In Realität: Mail-Versand-Status
}

/**
 * Benutzer registrieren
 */
function registerUser($username, $email, $password, $name = '') {
    $users = loadData('users.json');
    
    // Prüfen ob Username oder E-Mail bereits existiert
    foreach ($users as $user) {
        if (($user['username'] ?? '') === $username) {
            return ['success' => false, 'error' => 'Benutzername bereits vergeben.'];
        }
        if (($user['email'] ?? '') === $email) {
            return ['success' => false, 'error' => 'E-Mail-Adresse bereits registriert.'];
        }
    }
    
    // Neuen Benutzer erstellen
    $userId = generateUserId();
    $users[$userId] = [
        'id' => $userId,
        'username' => $username,
        'email' => $email,
        'password' => hashPassword($password),
        'name' => $name ?: $username,
        'role' => 'user',
        'created_at' => date('Y-m-d H:i:s'),
        'email_verified' => false
    ];
    
    if (saveData('users.json', $users)) {
        return ['success' => true, 'user_id' => $userId];
    } else {
        return ['success' => false, 'error' => 'Fehler beim Speichern der Benutzerdaten.'];
    }
}

/**
 * Benutzer authentifizieren
 */
function authenticateUser($usernameOrEmail, $password) {
    $users = loadData('users.json');
    
    // Benutzer suchen
    $foundUser = null;
    foreach ($users as $user) {
        if (($user['username'] ?? '') === $usernameOrEmail || 
            ($user['email'] ?? '') === $usernameOrEmail) {
            $foundUser = $user;
            break;
        }
    }
    
    if (!$foundUser) {
        return ['success' => false, 'error' => 'Benutzer nicht gefunden.'];
    }
    
    // Passwort prüfen
    $passwordValid = false;
    
    // Demo-User Sonderbehandlung
    if ($foundUser['id'] === 'demo-user' && $foundUser['password'] === 'demo123') {
        $passwordValid = ($password === 'demo123');
    } else {
        $passwordValid = verifyPassword($password, $foundUser['password']);
    }
    
    if ($passwordValid) {
        // Login-Zeit aktualisieren
        $users[$foundUser['id']]['last_login'] = date('Y-m-d H:i:s');
        saveData('users.json', $users);
        
        return ['success' => true, 'user' => $foundUser];
    } else {
        return ['success' => false, 'error' => 'Ungültiges Passwort.'];
    }
}

/**
 * Passwort-Reset-Token generieren
 */
function generatePasswordResetToken($email) {
    $users = loadData('users.json');
    
    // Benutzer finden
    $foundUser = null;
    foreach ($users as $userId => $user) {
        if (($user['email'] ?? '') === $email) {
            $foundUser = $user;
            $foundUserId = $userId;
            break;
        }
    }
    
    if (!$foundUser) {
        return ['success' => false, 'error' => 'E-Mail-Adresse nicht gefunden.'];
    }
    
    // Reset-Token generieren
    $resetToken = bin2hex(random_bytes(32));
    $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Token speichern
    $users[$foundUserId]['reset_token'] = $resetToken;
    $users[$foundUserId]['reset_expires'] = $resetExpires;
    
    if (saveData('users.json', $users)) {
        // E-Mail senden (hier nur simuliert)
        sendPasswordResetEmail($email, $resetToken);
        return ['success' => true, 'token' => $resetToken];
    } else {
        return ['success' => false, 'error' => 'Fehler beim Speichern des Reset-Tokens.'];
    }
}

/**
 * Passwort mit Token zurücksetzen
 */
function resetPasswordWithToken($token, $newPassword) {
    $users = loadData('users.json');
    
    // Token suchen
    $foundUser = null;
    $foundUserId = null;
    foreach ($users as $userId => $user) {
        if (($user['reset_token'] ?? '') === $token) {
            $foundUser = $user;
            $foundUserId = $userId;
            break;
        }
    }
    
    if (!$foundUser) {
        return ['success' => false, 'error' => 'Ungültiger Reset-Token.'];
    }
    
    // Token-Ablauf prüfen
    if (strtotime($foundUser['reset_expires']) < time()) {
        return ['success' => false, 'error' => 'Reset-Token ist abgelaufen.'];
    }
    
    // Neues Passwort setzen
    $users[$foundUserId]['password'] = hashPassword($newPassword);
    $users[$foundUserId]['reset_token'] = null;
    $users[$foundUserId]['reset_expires'] = null;
    $users[$foundUserId]['updated_at'] = date('Y-m-d H:i:s');
    
    if (saveData('users.json', $users)) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Fehler beim Speichern des neuen Passworts.'];
    }
}

/**
 * Auth-Middleware: Prüft ob Benutzer eingeloggt ist
 */
function requireAuth($redirectTo = '?page=login') {
    if (!isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Auth-Middleware: Prüft ob Benutzer NICHT eingeloggt ist
 */
function requireGuest($redirectTo = '?page=dashboard') {
    if (isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Session-Timeout prüfen (optional)
 */
function checkSessionTimeout($timeoutMinutes = 120) {
    startSession();
    
    if (isset($_SESSION['last_activity'])) {
        $timeout = $timeoutMinutes * 60; // In Sekunden
        if (time() - $_SESSION['last_activity'] > $timeout) {
            logoutUser();
            header('Location: ?page=login&message=session_timeout');
            exit;
        }
    }
    
    $_SESSION['last_activity'] = time();
}
?>