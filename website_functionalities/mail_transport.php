<?php
if (!function_exists('send_contact_message')) {
    /**
     * Versendet eine Kontakt-Nachricht über den konfigurierten Transport.
     * @return array [success => bool, error => string|null]
     */
    function send_contact_message(array $payload): array
    {
        $cfg = include __DIR__ . '/mail_config.php';
        $transport = strtolower(trim($cfg['transport'] ?? ''));

        if (!$transport) {
            return ['success' => false, 'error' => 'Kein Mail-Transport konfiguriert.'];
        }

        switch ($transport) {
            case 'sendgrid':
                return send_via_sendgrid($cfg, $payload);
            case 'mailgun':
                return send_via_mailgun($cfg, $payload);
            case 'smtp':
                return send_via_smtp($cfg, $payload);
            case 'mail':
            default:
                return send_via_mail_fallback($cfg, $payload);
        }
    }

    function send_via_sendgrid(array $cfg, array $payload): array
    {
        $apiKey = $cfg['sendgrid_api_key'] ?? '';
        if (!$apiKey) {
            return ['success' => false, 'error' => 'SendGrid API-Key fehlt.'];
        }

        $body = [
            'personalizations' => [[
                'to' => [['email' => $cfg['to_email']]],
                'subject' => $payload['subject'],
            ]],
            'from' => ['email' => $cfg['from_email'], 'name' => $cfg['from_name']],
            'reply_to' => ['email' => $payload['reply_to'], 'name' => $payload['reply_name']],
            'content' => [[
                'type' => 'text/plain',
                'value' => $payload['body_text'],
            ]],
        ];

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'error' => 'SendGrid CURL-Error: ' . $curlErr];
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'error' => null];
        }
        return ['success' => false, 'error' => 'SendGrid HTTP ' . $httpCode . ' Antwort: ' . $response];
    }

    function send_via_mailgun(array $cfg, array $payload): array
    {
        $apiKey = $cfg['mailgun_api_key'] ?? '';
        $domain = $cfg['mailgun_domain'] ?? '';
        if (!$apiKey || !$domain) {
            return ['success' => false, 'error' => 'Mailgun Domain oder API-Key fehlt.'];
        }

        $url = "https://api.mailgun.net/v3/{$domain}/messages";
        $postData = [
            'from' => "{$cfg['from_name']} <{$cfg['from_email']}>",
            'to' => $cfg['to_email'],
            'subject' => $payload['subject'],
            'text' => $payload['body_text'],
            'h:Reply-To' => "{$payload['reply_name']} <{$payload['reply_to']}>",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'error' => 'Mailgun CURL-Error: ' . $curlErr];
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'error' => null];
        }
        return ['success' => false, 'error' => 'Mailgun HTTP ' . $httpCode . ' Antwort: ' . $response];
    }

    function send_via_smtp(array $cfg, array $payload): array
    {
        // Einfacher SMTP-Client ohne externe Lib; für TLS/SSL ist OpenSSL erforderlich.
        $host = $cfg['smtp_host'] ?? '';
        $port = (int)($cfg['smtp_port'] ?? 0);
        $user = $cfg['smtp_username'] ?? '';
        $pass = $cfg['smtp_password'] ?? '';
        $enc  = strtolower($cfg['smtp_encryption'] ?? 'tls');

        if (!$host || !$port || !$user || !$pass) {
            return ['success' => false, 'error' => 'SMTP Zugangsdaten unvollständig.'];
        }

        // Fallback: nutze PHPs mail() mit konfiguriertem From, falls kein direkter SMTP-Send implementiert.
        // Hinweis: Dieser Weg benötigt ggf. local sendmail/ssmtp.
        return send_via_mail_fallback($cfg, $payload, 'SMTP nicht direkt implementiert, mail()-Fallback genutzt.');
    }

    function send_via_mail_fallback(array $cfg, array $payload, string $note = ''): array
    {
        $headers = [];
        $headers[] = 'From: ' . (!empty($cfg['from_name']) ? "{$cfg['from_name']} <{$cfg['from_email']}>" : $cfg['from_email']);
        $headers[] = 'Reply-To: ' . "{$payload['reply_name']} <{$payload['reply_to']}>";
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        if ($note) {
            $headers[] = 'X-Debug-Note: ' . $note;
        }

        $ok = mail($cfg['to_email'], $payload['subject'], $payload['body_text'], implode("\r\n", $headers));
        if ($ok) {
            return ['success' => true, 'error' => null];
        }
        return ['success' => false, 'error' => 'mail()-Send fehlgeschlagen. Prüfe lokales Mail-Setup oder konfiguriere SendGrid/Mailgun.'];
    }
}
