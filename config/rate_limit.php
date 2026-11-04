<?php
/**
 * Tiny login rate limiter (no extra table needed).
 * Counts FAILED attempts per key in small files under the system temp folder.
 *
 *   $key = 'customer:' . clientIp() . ':' . strtolower($login);
 *   if ($wait = rateLimitBlocked($key)) { ...tell user to retry in $wait seconds... }
 *   ...on failure:  rateLimitFail($key);
 *   ...on success:  rateLimitReset($key);
 *
 * Defaults: 5 failures within 15 minutes locks that key for the rest of the window.
 */

if (!function_exists('clientIp')) {
    function clientIp(): string {
        // Hostinger / Cloudflare put the real visitor IP in these headers.
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
            $ip = trim(explode(',', $_SERVER[$h] ?? '')[0]);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '0.0.0.0';
    }

    function rateLimitFile(string $key): string {
        $dir = rtrim(sys_get_temp_dir(), '/' . chr(92)) . DIRECTORY_SEPARATOR . 'rooto_rl';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }

    function rateLimitRead(string $key, int $window): array {
        $f = rateLimitFile($key);
        $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
        if (!is_array($d) || (time() - (int) ($d['start'] ?? 0)) > $window) {
            return ['start' => time(), 'fails' => 0];
        }
        return $d;
    }

    /** Seconds the caller must wait, or 0 if allowed. */
    function rateLimitBlocked(string $key, int $max = 5, int $window = 900): int {
        $d = rateLimitRead($key, $window);
        if ((int) $d['fails'] < $max) {
            return 0;
        }
        return max(1, $window - (time() - (int) $d['start']));
    }

    function rateLimitFail(string $key, int $window = 900): void {
        $d = rateLimitRead($key, $window);
        $d['fails'] = (int) $d['fails'] + 1;
        @file_put_contents(rateLimitFile($key), json_encode($d), LOCK_EX);
    }

    function rateLimitReset(string $key): void {
        @unlink(rateLimitFile($key));
    }
}
