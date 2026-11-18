<?php
/**
 * Images: the website's own pictures (assets/images, read-only) and pictures
 * uploaded from the dashboard (uploads/YYYY/MM/…, deletable when unused).
 */

const MEDIA_MAX_BYTES = 8 * 1024 * 1024;
const MEDIA_MAX_WIDTH = 2000;
const MEDIA_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
const MEDIA_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

function uploads_dir(): string
{
    return (string) (config('uploads_dir') ?: ROOT_DIR . '/uploads');
}

function uploads_url(): string
{
    return '/' . trim((string) (config('uploads_url') ?: 'uploads'), '/');
}

/** Make sure the uploads folder exists and cannot run scripts. */
function uploads_prepare(): void
{
    ensure_dir(uploads_dir());
    $ht = uploads_dir() . '/.htaccess';
    if (!is_file($ht)) {
        file_put_contents($ht, "# Uploaded pictures only: never run scripts here.\nOptions -Indexes -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pl .py .cgi\nRemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar\n<FilesMatch \"\\.(?i:php\\d*|phtml|phar|pl|py|cgi|sh|html?|svg)$\">\n  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n  <IfModule !mod_authz_core.c>\n    Deny from all\n  </IfModule>\n</FilesMatch>\n");
    }
}

/**
 * All images: [['url', 'file', 'source' => 'site'|'upload', 'folder', 'name', 'bytes', 'width', 'height'], …]
 */
function media_list(): array
{
    $out = [];
    $sources = [
        'site'   => [ROOT_DIR . '/assets/images', '/assets/images'],
        'upload' => [uploads_dir(), uploads_url()],
    ];
    foreach ($sources as $source => [$dir, $url]) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $ext = strtolower($file->getExtension());
            if (!$file->isFile() || !in_array($ext, MEDIA_EXTENSIONS, true)) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            $size = $ext !== 'svg' ? @getimagesize($file->getPathname()) : false;
            $out[] = [
                'url'    => $url . '/' . $rel,
                'file'   => $file->getPathname(),
                'source' => $source,
                'folder' => trim(dirname($rel), '.') ?: '(top)',
                'name'   => $file->getFilename(),
                'bytes'  => $file->getSize(),
                'width'  => $size ? $size[0] : null,
                'height' => $size ? $size[1] : null,
            ];
        }
    }
    usort($out, function ($a, $b) {
        return [$a['source'] === 'upload' ? 0 : 1, $a['url']] <=> [$b['source'] === 'upload' ? 0 : 1, $b['url']];
    });
    return $out;
}

/** Every image address used anywhere in the content (pages, settings) and site CSS. */
function media_used_urls(): array
{
    $blob = '';
    foreach (array_merge([content_path('site.json')], glob(content_path('pages', '*.json')) ?: []) as $f) {
        $blob .= is_file($f) ? stripslashes((string) file_get_contents($f)) : '';
    }
    foreach (glob(ROOT_DIR . '/assets/css/*.css') ?: [] as $f) {
        $blob .= (string) file_get_contents($f);
    }
    return ['blob' => $blob];
}

function media_is_used(string $url, ?array $used = null): bool
{
    $used = $used ?? media_used_urls();
    return strpos($used['blob'], $url) !== false;
}

/**
 * Save an uploaded file (one entry of $_FILES). Returns the public URL.
 * Throws ContentError with a plain-language message.
 */
function media_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = [
            UPLOAD_ERR_INI_SIZE => 'The picture is too large for this server.',
            UPLOAD_ERR_FORM_SIZE => 'The picture is too large.',
            UPLOAD_ERR_NO_FILE => 'Please choose a picture first.',
        ][$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'The upload did not finish. Please try again.';
        throw new ContentError([$msg]);
    }
    $tmp = (string) $file['tmp_name'];
    if (!is_uploaded_file($tmp) && PHP_SAPI !== 'cli') {
        throw new ContentError(['The upload did not finish. Please try again.']);
    }
    if (filesize($tmp) > MEDIA_MAX_BYTES) {
        throw new ContentError(['The picture is larger than 8 MB. Please make it smaller first.']);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset(MEDIA_TYPES[$mime]) || !@getimagesize($tmp)) {
        throw new ContentError(['Only JPG, PNG, WebP or GIF pictures can be uploaded.']);
    }
    $ext = MEDIA_TYPES[$mime];
    $base = slugify(pathinfo((string) ($file['name'] ?? 'image'), PATHINFO_FILENAME)) ?: 'image';
    $base = substr($base, 0, 60);
    uploads_prepare();
    $sub = date('Y/m');
    ensure_dir(uploads_dir() . '/' . $sub);
    $name = $base . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    $dest = uploads_dir() . '/' . $sub . '/' . $name;
    if (!media_resize($tmp, $dest, $mime)) {
        if (!(PHP_SAPI === 'cli' ? copy($tmp, $dest) : move_uploaded_file($tmp, $dest))) {
            throw new ContentError(['Could not save the picture.']);
        }
    }
    @chmod($dest, 0644);
    return uploads_url() . '/' . $sub . '/' . $name;
}

/** Shrink very wide pictures with GD. Returns false when nothing was written (caller copies). */
function media_resize(string $src, string $dest, string $mime): bool
{
    if (!function_exists('imagecreatetruecolor') || $mime === 'image/gif') {
        return false;
    }
    $size = @getimagesize($src);
    if (!$size || $size[0] <= MEDIA_MAX_WIDTH) {
        return false;
    }
    $load = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'][$mime] ?? null;
    if (!$load || !function_exists($load)) {
        return false;
    }
    $img = @$load($src);
    if (!$img) {
        return false;
    }
    $w = MEDIA_MAX_WIDTH;
    $h = (int) round($size[1] * $w / $size[0]);
    $out = imagecreatetruecolor($w, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, $size[0], $size[1]);
    $ok = $mime === 'image/png' ? imagepng($out, $dest, 6) : ($mime === 'image/webp' ? imagewebp($out, $dest, 82) : imagejpeg($out, $dest, 82));
    unset($img, $out); // GD images are freed automatically (imagedestroy is deprecated in PHP 8.5)
    return (bool) $ok;
}

/** Delete an uploaded picture that is not used anywhere. Site pictures can't be deleted. */
function media_delete(string $url): void
{
    $prefix = uploads_url() . '/';
    if (strpos($url, $prefix) !== 0 || strpos($url, '..') !== false) {
        throw new ContentError(['Only uploaded pictures can be deleted.']);
    }
    $file = uploads_dir() . '/' . substr($url, strlen($prefix));
    $real = realpath($file);
    $root = realpath(uploads_dir());
    if (!$real || !$root || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) {
        throw new ContentError(['That picture no longer exists.']);
    }
    if (media_is_used($url)) {
        throw new ContentError(['This picture is still used on the website. Remove it from those pages first.']);
    }
    unlink($real);
}
