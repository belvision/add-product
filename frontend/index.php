<?php

// 1) Base path: SCRIPT_NAME e.g. /index.php -> base '', /myapp/index.php -> base '/myapp'.
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($base === '/' || $base === '') {
    $base = '';
}

// 2) Path relative to base: split REQUEST_URI into path and query, then strip base prefix.
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$pathOnly = parse_url($requestUri, PHP_URL_PATH);
$pathOnly = ($pathOnly !== null && $pathOnly !== '') ? $pathOnly : '/';
$pathOnly = rtrim($pathOnly, '/') ?: '/';
$queryString = parse_url($requestUri, PHP_URL_QUERY);
$queryString = ($queryString !== null && $queryString !== '') ? $queryString : '';

$path = $pathOnly;
if ($base !== '') {
    if ($pathOnly === $base) {
        $path = '/';
    } elseif (strpos($pathOnly, $base . '/') === 0) {
        $path = substr($pathOnly, strlen($base));
    }
}
if ($path === '') {
    $path = '/';
}



// Support legacy URLs that were generated with /frontend prefix (e.g. /frontend/register, /frontend/auth/*, /frontend/api/*).
// When the app is deployed at domain root, these should behave the same as without the prefix.
if ($path === '/frontend' || strpos($path, '/frontend/') === 0) {
    $path = substr($path, 8);
    if ($path === '') {
        $path = '/';
    }
}

// Proxy only by prefix: /api and /auth. Backend sees REQUEST_URI as relative path (e.g. /api/me) so it can strip /api itself; no HTML-for-JSON.
$isApi  = ($path === '/api' || strpos($path, '/api/') === 0);
$isAuth = ($path === '/auth' || strpos($path, '/auth/') === 0);
if ($isApi || $isAuth) {
    $forwardUri = $path . ($queryString !== '' ? '?' . $queryString : '');
    $savedRequestUri = $_SERVER['REQUEST_URI'];
    $_SERVER['REQUEST_URI'] = $forwardUri;
    require __DIR__ . '/../backend/api/index.php';
    $_SERVER['REQUEST_URI'] = $savedRequestUri;
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
