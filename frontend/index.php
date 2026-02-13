<?php

$uri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
$uri = $uri ?: '/';
$uri = rtrim($uri, '/') ?: '/';

// Base path when app runs in subdirectory (e.g. /myapp). Used for strict prefix routing.
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if (substr($base, -7) === '/public') {
    $base = substr($base, 0, -7);
}
$path = $uri;
if ($base !== '' && $base !== '/') {
    if ($uri === $base) {
        $path = '/';
    } elseif (strpos($uri, $base . '/') === 0) {
        $path = substr($uri, strlen($base));
    }
}

// Proxy /api and /auth only by strict prefix (avoid HTML instead of JSON on wrong paths).
$isApi  = ($path === '/api' || strpos($path, '/api/') === 0);
$isAuth = ($path === '/auth' || strpos($path, '/auth/') === 0);
if ($isApi || $isAuth) {
    $query = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) : '';
    $_SERVER['REQUEST_URI'] = $path . ($query !== null && $query !== '' ? '?' . $query : '');
    require __DIR__ . '/../backend/api/index.php';
    return;
}

require_once __DIR__ . '/../backend/src/Config.php';
Config::loadEnv();
require_once __DIR__ . '/../backend/src/Db.php';
require_once __DIR__ . '/../backend/src/Auth.php';
Auth::initSession();

$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en']) ? $_GET['lang'] : 'ru';

if ($path === '/login') {
    require __DIR__ . '/public/login.php';
    return;
}
if ($path === '/register') {
    require __DIR__ . '/public/register.php';
    return;
}
if ($path === '/verify-email') {
    require __DIR__ . '/public/verify-email.php';
    return;
}
if ($path === '/cabinet') {
    require __DIR__ . '/public/cabinet.php';
    return;
}
if ($path === '/add') {
    require __DIR__ . '/public/add.php';
    return;
}
if (preg_match('#^/draft/([^/]+)$#', $path, $m)) {
    $_GET['draftId'] = $m[1];
    require __DIR__ . '/public/draft.php';
    return;
}

require __DIR__ . '/public/ozon-wizard.php';
