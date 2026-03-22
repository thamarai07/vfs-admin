<?php
/**
 * Lightweight .env loader (no Composer/dotenv needed)
 * Parses the root .env file and loads values into $_ENV.
 * Call once at application entry point (included by db.php automatically).
 */

if (!function_exists('env')) {
    /**
     * Get an environment variable with optional default.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function env(string $key, $default = null) {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

if (!function_exists('loadEnv')) {
    /**
     * Load a .env file into $_ENV.
     *
     * @param string $path Full path to the .env file
     */
    function loadEnv(string $path): void {
        if (!file_exists($path)) {
            return; // No .env file – rely on actual environment variables
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip surrounding quotes if present
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Auto-load the .env from the project root (two levels up from this file)
loadEnv(dirname(__DIR__) . '/.env');

// Set default IMAGE_BASE_URL if not defined (avoid localhost in production)
if (!env('IMAGE_BASE_URL')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'vfs-admin.up.railway.app';
    $fallbackUrl = "$protocol://$host/assets/images/uploads/";
    putenv("IMAGE_BASE_URL=$fallbackUrl");
    $_ENV['IMAGE_BASE_URL'] = $fallbackUrl;
}
