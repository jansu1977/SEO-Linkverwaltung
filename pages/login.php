<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LinkManager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .login-header { margin-bottom: 30px; }
        .login-header i { font-size: 48px; color: #667eea; margin-bottom: 16px; }
        .login-header h1 { font-size: 24px; margin-bottom: 8px; color: #2d3748; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px; }
        .form-control:focus { outline: none; border-color: #667eea; }
        .btn { width: 100%; padding: 12px 20px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #5a67d8; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; background: #fed7d7; color: #c53030; }
        .demo-info { margin-top: 24px; padding: 16px; background: #f7fafc; border-radius: 8px; text-align: left; }
        .demo-credentials { background: #edf2f7; padding: 8px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-link"></i>
            <h1>LinkManager</h1>
            <p>Anmeldung erforderlich</p>
        </div>

        <?php if (isset($loginError)): ?>
            <div class="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label class="form-label">Benutzername</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Passwort</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i>
                Anmelden
            </button>
        </form>

        <div class="demo-info">
            <h4><i class="fas fa-info-circle"></i> Standard-Zugang</h4>
            <p>Standard-Anmeldedaten:</p>
            <div class="demo-credentials">
                <strong>Benutzername:</strong> admin<br>
                <strong>Passwort:</strong> admin123
            </div>
        </div>
    </div>
</body>
</html>