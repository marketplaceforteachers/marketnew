<?php
// PHP's built-in dev server (php -S) is only ever used locally; every other SAPI is a real
// deployment, where PHP errors must never be shown to visitors (stack traces can leak file
// paths, query structure, etc.) — log them instead.
if (php_sapi_name() !== 'cli-server') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';

// When an upload is bigger than the server's post_max_size, PHP silently throws away the whole
// form ($_POST and $_FILES both arrive empty), so the page just reloads with no explanation.
// Catch that here, before any page logic runs, and send the person back with a clear message.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && empty($_POST) && empty($_FILES)
    && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
) {
    flash('error', 'That photo is too large to upload. Please choose an image under '
        . format_upload_limit() . ' (tip: a screenshot or "Medium" size photo works well).');
    redirect(safe_local_redirect_path($_SERVER['REQUEST_URI'] ?? '/index.php'));
}
