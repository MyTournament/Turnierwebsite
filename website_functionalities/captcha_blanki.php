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
        echo '<div class="captcha-blanki" id="'. htmlspecialchars($containerId) .'" style="margin:10px auto;padding:12px;border:1px solid #888;border-radius:8px;max-width:560px;">';
        // Scoped styles
        echo '<style> 
            #'. htmlspecialchars($containerId) .' .cb-title{margin:0 0 10px 0;text-align:center;}
            #'. htmlspecialchars($containerId) .' .cb-grid{display:grid;grid-template-columns:repeat(4,minmax(70px,1fr));gap:10px;}
            @media (max-width:520px){ #'. htmlspecialchars($containerId) .' .cb-grid{grid-template-columns:repeat(3,1fr);} }
            #'. htmlspecialchars($containerId) .' .cb-item{position:relative;border:1px solid #666;border-radius:6px;overflow:hidden;cursor:pointer;user-select:none;}
            #'. htmlspecialchars($containerId) .' .cb-item .cb-img{display:block;width:100%;height:100px;object-fit:cover;}
            #'. htmlspecialchars($containerId) .' .cb-item .cb-badge{position:absolute;left:6px;top:6px;background:rgba(0,0,0,0.55);padding:3px 6px;border-radius:3px;color:#fff;font-size:12px;}
            #'. htmlspecialchars($containerId) .' .cb-item .cb-check{position:absolute;right:6px;top:6px;background:rgba(3,150,70,0.0);border-radius:50%;color:#fff;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:14px;transition:background 0.15s ease;border:1px solid rgba(255,255,255,0.6);} 
            #'. htmlspecialchars($containerId) .' .cb-item.selected{outline:2px solid #2ecc71;border-color:#2ecc71;}
            #'. htmlspecialchars($containerId) .' .cb-item.selected .cb-check{background:rgba(3,150,70,0.9);} 
            #'. htmlspecialchars($containerId) .' .cb-hidden{position:absolute !important;left:-9999px !important;}
        </style>';
        echo '<p class="cb-title"><strong>Erkenne welche der folgenden Bilder im Blankensteinpark gemacht wurden.</strong></p>';
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
            echo '<div class="cb-item" data-id="'. $safeId .'">';
            echo '<img class="cb-img" src="' . htmlspecialchars($imgSrc) . '" alt="Captcha Bild ' . $i . '" loading="lazy"/>';
            echo '<div class="cb-badge">Auswählen</div>';
            echo '<div class="cb-check">✓</div>';
            echo '<input class="cb-hidden" type="checkbox" name="cbsel[]" value="' . $safeId . '" aria-label="Bild '. $i .' auswählen" />';
            echo '</div>';
        }
        echo '</div>';
        echo '<input type="hidden" name="cb_token" value="' . htmlspecialchars($token) . '"/>';
        // honeypot + render timestamp
        echo '<div style="position:absolute;left:-9999px;top:-9999px;"><input type="text" name="website" value="" tabindex="-1" autocomplete="off"></div>';
        echo '<input type="hidden" name="cb_rendered_at" value="' . (int)$now . '">';
        // Interaction script (scoped to this container)
        echo '<script>(function(){
                var root = document.getElementById('. json_encode($containerId) .');
                if (!root) return;
                function sync(el){
                    var cb = el.querySelector("input[type=checkbox]");
                    if (!cb) return;
                    el.classList.toggle("selected", !!cb.checked);
                }
                root.querySelectorAll(".cb-item").forEach(function(item){
                    var cb = item.querySelector("input[type=checkbox]");
                    item.addEventListener("click", function(e){
                        if (e.target && e.target.tagName && e.target.tagName.toLowerCase() === "input") return;
                        if (cb){ cb.checked = !cb.checked; }
                        sync(item);
                    });
                    if (cb){ cb.addEventListener("change", function(){ sync(item); }); }
                });
            })();</script>';
        echo '</div>';
    }

    public static function validate(array $post): bool {
        self::ensureSession();
        $token = isset($post['cb_token']) ? (string)$post['cb_token'] : '';
        if ($token === '' || !isset($_SESSION[self::SESSION_KEY][$token])) { return false; }
        $entry = $_SESSION[self::SESSION_KEY][$token];
        // Track attempts to limit brute force
        $_SESSION[self::SESSION_KEY][$token]['attempts'] = ($entry['attempts'] ?? 0) + 1;
        $attempts = $_SESSION[self::SESSION_KEY][$token]['attempts'];
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
}

?>
