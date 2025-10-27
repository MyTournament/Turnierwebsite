<?php
// Simple image-selection CAPTCHA for Blankensteinpark
// Renders a grid of images and asks users to select all from Blankensteinpark.
// Reads images dynamically from:
//   images/captcha_blanki/Blankensteinpark
//   images/captcha_blanki/Andere Parks
// Stores challenge metadata in PHP session and validates server-side.

class CaptchaBlanki {
    private const DIR_BLANKI = 'images/captcha_blanki/Blankensteinpark';
    private const DIR_OTHER  = 'images/captcha_blanki/Andere Parks';
    private const SESSION_KEY = 'captcha_blanki';
    private const TTL_SECONDS = 600; // 10 minutes

    private static function ensureSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    private static function listImages(string $dir): array {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $files = [];
        $path = rtrim($dir, '/');
        foreach ($allowed as $ext) {
            foreach (glob($path . '/*.' . $ext) as $f) {
                if (is_file($f)) { $files[] = $f; }
            }
            foreach (glob($path . '/*.' . strtoupper($ext)) as $f) {
                if (is_file($f)) { $files[] = $f; }
            }
        }
        return $files;
    }

    private static function randomToken(int $bytes = 16): string {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function render(string $formKey = 'default', int $targetTotal = 8): void {
        self::ensureSession();

        // Always require a fresh verification on new render
        $passKey = self::SESSION_KEY . '_pass';
        if (isset($_SESSION[$passKey][$formKey])) {
            unset($_SESSION[$passKey][$formKey]);
        }

        $blanki = self::listImages(self::DIR_BLANKI);
        $other  = self::listImages(self::DIR_OTHER);

        // Fallback if not enough images; still render gracefully
        shuffle($blanki);
        shuffle($other);

        $minFromEach = 2; // ensure at least 2 positives if possible
        $takeBlanki = min(max($minFromEach, intdiv($targetTotal, 2)), count($blanki));
        $takeOther  = min($targetTotal - $takeBlanki, max(0, count($other)));
        // If still not enough total, fill from whatever available
        if ($takeBlanki + $takeOther < $targetTotal) {
            $extra = $targetTotal - ($takeBlanki + $takeOther);
            $moreB = min($extra, max(0, count($blanki) - $takeBlanki));
            $takeBlanki += $moreB;
            $extra -= $moreB;
            $takeOther += min($extra, max(0, count($other) - $takeOther));
        }

        $choices = [];
        foreach (array_slice($blanki, 0, $takeBlanki) as $f) {
            $choices[] = ['file' => $f, 'is_blank' => true];
        }
        foreach (array_slice($other, 0, $takeOther) as $f) {
            $choices[] = ['file' => $f, 'is_blank' => false];
        }
        shuffle($choices);

        $alreadyPassed = self::passed($formKey);

        $token = self::randomToken(18);
        $now = time();
        $map = [];
        foreach ($choices as $ch) {
            $id = self::randomToken(12);
            $map[$id] = [
                'is_blank' => $ch['is_blank'] ? 1 : 0,
                // Store a short fingerprint instead of full path
                'fp' => substr(hash('sha256', $ch['file']), 0, 16)
            ];
        }
        $_SESSION[self::SESSION_KEY][$token] = [
            'form' => $formKey,
            'created' => $now,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'map' => $map,
            'required' => array_keys(array_filter($map, fn($v) => $v['is_blank'] === 1)),
            'attempts' => 0,
        ];

        // Render HTML (compact block + interactive tiles)
        $containerId = 'captcha-blanki-' . $token;
        $statusMsg = $alreadyPassed ? 'Captcha bestätigt. Du kannst jetzt absenden.' : '';
        $statusColor = $alreadyPassed ? '#2ecc71' : '#c0392b';
        $flashKey = 'flash_error_' . $formKey;
        if (isset($_SESSION[$flashKey]) && $_SESSION[$flashKey]) {
            $statusMsg = (string)$_SESSION[$flashKey];
            $statusColor = (stripos($statusMsg, 'best') !== false) ? '#2ecc71' : '#c0392b';
            unset($_SESSION[$flashKey]);
        }
        $initialAttempts = 3;
        $attemptsUsedInitial = 0;
        $containerClasses = 'captcha-blanki';
        if ($alreadyPassed) { $containerClasses .= ' captcha-blanki--success'; }
        if ($statusMsg !== '' && $statusColor !== '#2ecc71') { $containerClasses .= ' captcha-blanki--error'; }
        echo '<div class="'. $containerClasses .'" id="'. htmlspecialchars($containerId) .'" data-initial-attempts="'. $initialAttempts .'" data-attempts-used="'. $attemptsUsedInitial .'" data-passed="'. ($alreadyPassed ? '1' : '0') .'" style="margin:10px auto;padding:14px;border:1px solid #888;border-radius:10px;max-width:560px;transition:background 0.2s ease,border-color 0.2s ease,box-shadow 0.2s ease;">';
        // Scoped styles
        echo '<style> 
            #'. htmlspecialchars($containerId) .'{background:#050505;color:#f5f5f5;}
            #'. htmlspecialchars($containerId) .' .cb-title{margin:0 0 10px 0;text-align:center;}
            #'. htmlspecialchars($containerId) .' .cb-title strong{color:#ffffff;letter-spacing:0.2px;}
            #'. htmlspecialchars($containerId) .' .cb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;}
            @media (max-width:520px){ #'. htmlspecialchars($containerId) .' .cb-grid{grid-template-columns:repeat(auto-fit,minmax(160px,1fr));} }
            #'. htmlspecialchars($containerId) .' label.cb-item{position:relative;display:block;border:1px solid #666;border-radius:6px;overflow:hidden;cursor:pointer;user-select:none;}
            #'. htmlspecialchars($containerId) .' .cb-native{position:absolute;left:0;top:0;width:100%;height:100%;opacity:0;cursor:pointer;}
            #'. htmlspecialchars($containerId) .' .cb-tile{position:relative;aspect-ratio:1;overflow:hidden;}
            #'. htmlspecialchars($containerId) .' .cb-img{display:block;width:100%;height:100%;object-fit:cover;}
            #'. htmlspecialchars($containerId) .' .cb-badge{position:absolute;left:6px;top:6px;background:rgba(0,0,0,0.55);padding:3px 6px;border-radius:3px;color:#fff;font-size:12px;}
            #'. htmlspecialchars($containerId) .' .cb-check{position:absolute;right:6px;top:6px;background:rgba(3,150,70,0.0);border-radius:50%;color:#fff;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:14px;transition:background 0.15s ease;border:1px solid rgba(255,255,255,0.6);} 
            #'. htmlspecialchars($containerId) .' .cb-native:checked + .cb-tile{outline:2px solid #2ecc71;border-color:#2ecc71;}
            #'. htmlspecialchars($containerId) .' .cb-native:checked + .cb-tile .cb-check{background:rgba(3,150,70,0.9);} 
            #'. htmlspecialchars($containerId) .' .cb-check-btn[onclick]{display:none !important;}
            #'. htmlspecialchars($containerId) .' .cb-status{margin:8px 0 14px 0;font-size:1.05em;line-height:1.35;min-height:24px;font-weight:600;padding:0;border:none;background:transparent;color:#f5f5f5;transition:all 0.2s ease;}
            #'. htmlspecialchars($containerId) .' .cb-status.cb-status--error{background:#310000;border-left:5px solid #ff5c5c;color:#ffbdbd;padding:14px 16px;border-radius:6px;}
            #'. htmlspecialchars($containerId) .' .cb-status.cb-status--success{background:#062913;border-left:5px solid #2ecc71;color:#b1ffd3;padding:12px 16px;border-radius:6px;}
            #'. htmlspecialchars($containerId) .'.captcha-blanki--error{background:#2c0000;border-color:#f04e4e;box-shadow:0 0 0 3px rgba(240,78,78,0.25);}
            #'. htmlspecialchars($containerId) .'.captcha-blanki--success{background:#082c16;border-color:#29a357;box-shadow:0 0 0 3px rgba(41,163,87,0.18);}
            #'. htmlspecialchars($containerId) .' .cb-attempts{color:#e0e0e0;}
        </style>';
        echo '<p class="cb-title"><strong>Bitte w&auml;hle die 4 Bilder aus, die im Blankensteinpark aufgenommen wurden.</strong></p>';
        $statusClass = 'cb-status';
        if ($statusMsg !== '') {
            $statusClass .= ($statusColor === '#2ecc71') ? ' cb-status--success' : ' cb-status--error';
        }
        echo '<div class="'. $statusClass .'" aria-live="polite">'. htmlspecialchars($statusMsg) .'</div>';
        $alreadyPassed = self::passed($formKey);
        echo '<div class="cb-grid">';
        $i = 0;
        foreach ($map as $id => $meta) {
            $file = null;
            foreach ($choices as $c) {
                if (substr(hash('sha256', $c['file']), 0, 16) === $meta['fp']) { $file = $c['file']; break; }
            }
            if (!$file) { continue; }
            $i++;
            $imgSrc = '/' . ltrim(str_replace('\\', '/', $file), '/');
            $safeId = htmlspecialchars($id);
            echo '<label class="cb-item" data-id="'. $safeId .'">';
            echo '<input class="cb-native" type="checkbox" name="cbsel[]" value="' . $safeId . '" aria-label="Bild '. $i .' auswählen" />';
            echo '<div class="cb-tile">';
            echo '<img class="cb-img" src="' . htmlspecialchars($imgSrc) . '" alt="Captcha Bild ' . $i . '" loading="lazy"/>';
            echo '<div class="cb-badge">Auswählen</div>';
            echo '<div class="cb-check">✓</div>';
            echo '</div>';
            echo '</label>';
        }
        echo '</div>';
        echo '<input type="hidden" name="cb_token" value="' . htmlspecialchars($token) . '"/>';
        echo '<input type="hidden" name="cb_formkey" value="' . htmlspecialchars($formKey) . '"/>';
        echo '<input type="hidden" name="cb_pass" value="'. ($alreadyPassed ? '1' : '0') .'"/>';
        // honeypot + render timestamp
        echo '<div style="position:absolute;left:-9999px;top:-9999px;"><input type="text" name="website" value="" tabindex="-1" autocomplete="off"></div>';
        echo '<input type="hidden" name="cb_rendered_at" value="' . (int)$now . '">';
        $attemptLabel = ($initialAttempts === 1) ? 'Versuch' : 'Versuche';
        $attemptText = $initialAttempts . ' ' . $attemptLabel . ' übrig';
        $checkDisabledAttr = $alreadyPassed ? ' disabled' : '';
        echo '<div class="cb-actions" style="margin-top:10px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">';
        echo '<span class="cb-attempts" style="font-size:0.95em;color:#999;">'. htmlspecialchars($attemptText) .'</span>';
        echo '<button type="submit" name="cb_action" value="check" formnovalidate class="button cb-check-btn" style="background:#444;color:#fff;"'. $checkDisabledAttr .'>Captcha überprüfen</button>';
        echo '</div>';
        echo '</div>';
    }

    public static function validate(array $post): bool {
        self::ensureSession();
        $token = isset($post['cb_token']) ? (string)$post['cb_token'] : '';
        if ($token === '' || !isset($_SESSION[self::SESSION_KEY][$token])) { return false; }
        $entry = $_SESSION[self::SESSION_KEY][$token];
        // Track attempts to limit brute force
        $_SESSION[self::SESSION_KEY][$token]['attempts'] = ($entry['attempts'] ?? 0) + 1;
        $attempts = (int)$_SESSION[self::SESSION_KEY][$token]['attempts'];
        $_SESSION[self::SESSION_KEY][$token]['remaining'] = max(0, 3 - $attempts);
        if ($attempts > 3) { unset($_SESSION[self::SESSION_KEY][$token]); return false; }

        $created = (int)($entry['created'] ?? 0);
        if (time() - $created > self::TTL_SECONDS) { unset($_SESSION[self::SESSION_KEY][$token]); return false; }

        // basic honeypot
        if (!empty($post['website'])) { unset($_SESSION[self::SESSION_KEY][$token]); return false; }
        // basic min render time (0.5s)
        $renderedAt = isset($post['cb_rendered_at']) ? (int)$post['cb_rendered_at'] : 0;
        if ($renderedAt > 0 && (time() - $renderedAt) < 1) { /* allow small margin */ usleep(350000); }

        $selected = isset($post['cbsel']) ? (array)$post['cbsel'] : [];
        $selected = array_values(array_unique(array_map('strval', $selected)));
        $map = $entry['map'] ?? [];
        $required = $entry['required'] ?? [];
        // Must select at least one and only the required ones
        sort($selected);
        $req = $required; sort($req);
        $ok = ($selected === $req) && count($req) > 0;
        if ($ok) {
            unset($_SESSION[self::SESSION_KEY][$token]);
            return true;
        }
        // Wrong selection: allow retry with same token until attempts limit
        if ($attempts >= 3) { unset($_SESSION[self::SESSION_KEY][$token]); }
        return false;
    }

    public static function preverify(array $post): array {
        // Returns ['ok'=>bool, 'remaining'=>int, 'reload'=>bool]
        self::ensureSession();
        $ok = self::validate($post); // uses and updates attempts, unsets on success
        $token = isset($post['cb_token']) ? (string)$post['cb_token'] : '';
        $formKey = isset($post['cb_formkey']) ? (string)$post['cb_formkey'] : 'default';
        $remaining = 3;
        $attempts = 0;
        $reload = false;
        if ($token !== '' && isset($_SESSION[self::SESSION_KEY][$token])) {
            $entry = $_SESSION[self::SESSION_KEY][$token];
            $attempts = (int)($entry['attempts'] ?? 0);
            $remaining = isset($entry['remaining']) ? (int)$entry['remaining'] : max(0, 3 - $attempts);
            if ($remaining <= 0 && !$ok) { $reload = true; }
        } else if ($ok) {
            $remaining = 3; // after success token is removed, treat as full
            $attempts = 0;
        } else {
            $remaining = 0; // token might have been removed due to limit
            $attempts = 3;
            $reload = true;
        }
        $displayRemaining = $remaining;
        if (!$ok && $remaining <= 0) {
            $displayRemaining = 3;
        }
        if ($ok) {
            if (!isset($_SESSION[self::SESSION_KEY.'_pass'])) { $_SESSION[self::SESSION_KEY.'_pass'] = []; }
            $_SESSION[self::SESSION_KEY.'_pass'][$formKey] = time();
        } else {
            if ($remaining <= 0) { $reload = true; }
        }
        $_SESSION['captcha_remaining_' . $formKey] = $displayRemaining;
        return ['ok'=>$ok, 'remaining'=>$displayRemaining, 'reload'=>$reload, 'attempts'=>$attempts];
    }

    public static function passed(string $formKey, int $ttl = 600): bool {
        self::ensureSession();
        $arr = $_SESSION[self::SESSION_KEY.'_pass'] ?? [];
        if (!isset($arr[$formKey])) return false;
        if ((time() - (int)$arr[$formKey]) > $ttl) { unset($_SESSION[self::SESSION_KEY.'_pass'][$formKey]); return false; }
        return true;
    }

}

?>
