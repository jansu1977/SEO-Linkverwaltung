<?php
/**
 * includes/email-functions.php
 * E-Mail-System für Passwort-Reset (PHP mail() Version)
 */

/**
 * E-Mail-Konfiguration (nur für PHP mail())
 */
function getEmailConfig() {
    return [
        'method' => 'php_mail',
        'from_email' => 'noreply@' . $_SERVER['HTTP_HOST'],
        'from_name' => 'LinkBuilder Pro',
        'reply_to' => 'support@' . $_SERVER['HTTP_HOST']
    ];
}

/**
 * Sendet eine Passwort-Reset-E-Mail
 */
function sendPasswordResetEmail($email, $resetToken, $userName = '') {
    $config = getEmailConfig();
    
    // Reset-URL generieren
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $resetUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/?page=simple_login&action=reset&token=" . $resetToken;
    
    // E-Mail-Inhalt erstellen
    $subject = "Passwort zurücksetzen - LinkBuilder Pro";
    $htmlMessage = createPasswordResetEmailHTML($resetUrl, $userName);
    
    // E-Mail mit PHP mail() senden
    return sendEmailPHP($email, $subject, $htmlMessage, $config);
}

/**
 * E-Mail mit PHP mail() Funktion senden
 */
function sendEmailPHP($to, $subject, $htmlMessage, $config) {
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
        'Reply-To: ' . $config['reply_to'],
        'X-Mailer: LinkBuilder Pro',
        'X-Priority: 3'
    ];
    
    $success = mail($to, $subject, $htmlMessage, implode("\r\n", $headers));
    
    if ($success) {
        error_log("Password reset email sent successfully to: $to");
    } else {
        error_log("Failed to send password reset email to: $to");
    }
    
    return $success;
}

/**
 * HTML-E-Mail-Template erstellen
 */
function createPasswordResetEmailHTML($resetUrl, $userName = '') {
    $currentYear = date('Y');
    $siteName = $_SERVER['HTTP_HOST'];
    
    return "
    <!DOCTYPE html>
    <html lang='de'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Passwort zurücksetzen - LinkBuilder Pro</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; 
            }
            .container { 
                max-width: 600px; margin: 20px auto; background: white; 
                border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #4dabf7, #339af0); 
                color: white; padding: 30px 20px; text-align: center; 
            }
            .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
            .header p { margin: 5px 0 0 0; opacity: 0.9; }
            .content { padding: 40px 30px; }
            .button { 
                display: inline-block; background: linear-gradient(135deg, #4dabf7, #339af0); 
                color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 8px; 
                margin: 20px 0; font-weight: 600; font-size: 16px;
                box-shadow: 0 3px 10px rgba(77, 171, 247, 0.3);
            }
            .footer { 
                margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;
                font-size: 12px; color: #666; text-align: center;
            }
            .warning { 
                background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; 
                border-radius: 8px; margin: 25px 0; border-left: 4px solid #fbbf24;
            }
            .warning h3 { margin-top: 0; color: #d97706; }
            .url-box {
                background: #f8f9fa; padding: 15px; border-radius: 6px; 
                word-break: break-all; font-family: monospace; font-size: 13px;
                border: 1px solid #e9ecef; margin: 15px 0;
            }
            .logo { font-size: 24px; margin-right: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1><span class='logo'>🔗</span> LinkBuilder Pro</h1>
                <p>Passwort zurücksetzen</p>
            </div>
            <div class='content'>
                <h2>Hallo" . ($userName ? " $userName" : "") . ",</h2>
                
                <p>Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts für Ihr LinkBuilder Pro Account gestellt.</p>
                
                <p>Klicken Sie auf den folgenden Button, um ein neues Passwort zu erstellen:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetUrl' class='button'>Passwort jetzt zurücksetzen</a>
                </div>
                
                <p>Falls der Button nicht funktioniert, können Sie den folgenden Link in Ihren Browser kopieren:</p>
                <div class='url-box'>$resetUrl</div>
                
                <div class='warning'>
                    <h3>⚠️ Wichtige Sicherheitshinweise:</h3>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li><strong>Gültigkeitsdauer:</strong> Dieser Link ist nur 1 Stunde gültig</li>
                        <li><strong>Einmalige Verwendung:</strong> Der Link kann nur einmal verwendet werden</li>
                        <li><strong>Nicht angefordert?</strong> Falls Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail</li>
                        <li><strong>Verdächtige Aktivität?</strong> Kontaktieren Sie sofort unseren Support</li>
                    </ul>
                </div>
                
                <p>Bei Fragen oder Problemen wenden Sie sich an unseren Support unter: <a href='mailto:support@$siteName'>support@$siteName</a></p>
                
                <p>Viele Grüße<br><strong>Ihr LinkBuilder Pro Team</strong></p>
                
                <div class='footer'>
                    <p>Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht direkt auf diese E-Mail.</p>
                    <p>© $currentYear LinkBuilder Pro ($siteName). Alle Rechte vorbehalten.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>