<?php
/**
 * Configuration.
 *
 * Order (later wins): built-in defaults → environment variables → app/config.local.php.
 * config.local.php is per-server and is NOT in git. Copy config.local.example.php to start.
 */

function config_defaults(): array
{
    return [
        // Feature switch. While false, the old static .html files keep serving the site.
        'enabled'         => false,

        // Where pages, settings, history and cache live. Best: a folder OUTSIDE the
        // public web folder (see GO-LIVE.md). Default: the snapshot inside the project.
        'content_dir'     => ROOT_DIR . '/content',

        // Public address of the site, no trailing slash.
        'base_url'        => 'https://www.jaibabananakinterlinings.com',

        // Official address of the home page: '/index.html' (as the static site has it)
        // or '/' (then /index.html sends a 301 to /).
        'home_path'       => '/index.html',

        // Store rendered pages as files for speed. Cleared on every save.
        'cache'           => true,

        // While 'enabled' is false: serve the matching static .html file if the web
        // server passed the request to index.php (e.g. "/" when index.php is the
        // DirectoryIndex). Keeps the static site working before go-live.
        'static_fallback' => true,

        // Show PHP errors in the browser. Never true on the live site.
        'debug'           => false,

        'timezone'        => 'Asia/Kolkata',

        // Pictures uploaded in the dashboard. Must be inside the public web folder.
        'uploads_dir'     => ROOT_DIR . '/uploads',
        'uploads_url'     => 'uploads',
    ];
}

function config_env(): array
{
    $map = [
        'JAIBABA_ENABLED'     => 'enabled',
        'JAIBABA_CONTENT_DIR' => 'content_dir',
        'JAIBABA_BASE_URL'    => 'base_url',
        'JAIBABA_HOME_PATH'   => 'home_path',
        'JAIBABA_CACHE'       => 'cache',
        'JAIBABA_DEBUG'       => 'debug',
        'JAIBABA_UPLOADS_DIR' => 'uploads_dir',
    ];
    $bools = ['enabled', 'cache', 'debug'];
    $out = [];
    foreach ($map as $env => $key) {
        $v = getenv($env);
        if ($v === false || $v === '') {
            continue;
        }
        $out[$key] = in_array($key, $bools, true)
            ? in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true)
            : $v;
    }
    return $out;
}

/**
 * Read a config value. config() with no argument returns the whole array.
 */
function config(?string $key = null)
{
    $cfg = config_all();
    if ($key === null) {
        return $cfg;
    }
    return $cfg[$key] ?? null;
}

function config_all(?array $override = null, bool $reset = false): array
{
    static $cfg = null;
    if ($reset) {
        $cfg = null;
    }
    if ($cfg === null) {
        $cfg = array_merge(config_defaults(), config_env());
        $local = APP_DIR . '/config.local.php';
        if (is_file($local)) {
            $extra = require $local;
            if (is_array($extra)) {
                $cfg = array_merge($cfg, $extra);
            }
        }
        $cfg['content_dir'] = rtrim((string) $cfg['content_dir'], '/\\');
        $cfg['base_url'] = rtrim((string) $cfg['base_url'], '/');
        if (!in_array($cfg['home_path'], ['/', '/index.html'], true)) {
            $cfg['home_path'] = '/index.html';
        }
    }
    if ($override !== null) {
        $cfg = array_merge($cfg, $override);
        $cfg['content_dir'] = rtrim((string) $cfg['content_dir'], '/\\');
    }
    return $cfg;
}

/** Change config at runtime (tests and CLI only). */
function config_set(array $override): void
{
    config_all($override);
}
