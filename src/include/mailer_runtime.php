<?php
require_once dirname(__DIR__) . '/config/mailer.php';

/**
 * Build and send email according to settings stored in DB.
 * Providers:
 * - dev_log (default): logs to logs/mail.log
 * - php_smtp: uses php.ini overrides on Windows (SMTP, smtp_port, sendmail_from)
 * - sendgrid: HTTPS API with Bearer key
 * - mailgun: HTTPS API with domain + key
 */

function get_setting($pdo, $key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
        $cache = [];
        foreach ($stmt->fetchAll() as $r) { $cache[$r['setting_key']] = $r['setting_value']; }
    }
    return $cache[$key] ?? $default;
}

function send_mail_runtime($pdo, $to, $subject, $htmlBody) {
    $provider = get_setting($pdo, 'smtp_provider', 'dev_log');
    $enabled = (int)get_setting($pdo, 'smtp_enabled', '0') === 1;
    $fromEmail = get_setting($pdo, 'smtp_from_email', Mailer::$MAIL_FROM);
    $fromName = get_setting($pdo, 'smtp_from_name', Mailer::$MAIL_FROM_NAME);

    // configure base headers
    Mailer::$MAIL_FROM = $fromEmail ?: Mailer::$MAIL_FROM;
    Mailer::$MAIL_FROM_NAME = $fromName ?: Mailer::$MAIL_FROM_NAME;

    if (!$enabled || $provider === 'dev_log') {
        Mailer::$MAIL_DEV_MODE = true;
        return Mailer::send($to, $subject, $htmlBody);
    }

    if ($provider === 'php_smtp') {
        // Works mainly on Windows XAMPP; relies on mail() after ini override
        $host = get_setting($pdo, 'smtp_host', 'localhost');
        $port = (int)get_setting($pdo, 'smtp_port', '25');
        ini_set('SMTP', $host);
        ini_set('smtp_port', (string)$port);
        ini_set('sendmail_from', $fromEmail);
        Mailer::$MAIL_DEV_MODE = false;
        return Mailer::send($to, $subject, $htmlBody);
    }

    if ($provider === 'sendgrid') {
        $apiKey = get_setting($pdo, 'smtp_api_key', '');
        if (empty($apiKey)) { return false; }
        $payload = [
            'personalizations' => [[ 'to' => [[ 'email' => $to ]] ]],
            'from' => [ 'email' => $fromEmail, 'name' => $fromName ],
            'subject' => $subject,
            'content' => [[ 'type' => 'text/html', 'value' => $htmlBody ]]
        ];
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($res === false) { error_log('SendGrid error: ' . curl_error($ch)); }
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    if ($provider === 'mailgun') {
        $apiKey = get_setting($pdo, 'smtp_api_key', '');
        $domain = get_setting($pdo, 'smtp_api_domain', '');
        if (empty($apiKey) || empty($domain)) { return false; }
        $url = 'https://api.mailgun.net/v3/' . $domain . '/messages';
        $post = [
            'from' => $fromName . ' <' . $fromEmail . '>',
            'to' => $to,
            'subject' => $subject,
            'html' => $htmlBody
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $apiKey);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($res === false) { error_log('Mailgun error: ' . curl_error($ch)); }
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    // fallback
    Mailer::$MAIL_DEV_MODE = true;
    return Mailer::send($to, $subject, $htmlBody);
}
?>


