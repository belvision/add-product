<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../src/Config.php';
Config::loadEnv();
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/OzonFormSchema.php';
require_once __DIR__ . '/../src/DraftRepository.php';
require_once __DIR__ . '/../src/DraftValidator.php';
require_once __DIR__ . '/../src/ImageConverter.php';
require_once __DIR__ . '/../src/SsrfProtection.php';

require_once __DIR__ . '/routes/RouteHelpers.php';
require_once __DIR__ . '/routes/AuthRoutes.php';
require_once __DIR__ . '/routes/ProfileRoutes.php';
require_once __DIR__ . '/routes/DraftRoutes.php';
require_once __DIR__ . '/routes/ImageRoutes.php';
require_once __DIR__ . '/routes/ProgressRoutes.php';
require_once __DIR__ . '/routes/PipelineRoutes.php';
require_once __DIR__ . '/routes/EmallDraftRoutes.php';

set_error_handler(function ($severity, $message, $file, $line) {
    if (ob_get_level()) {
        ob_clean();
    }
    \Response::error('INTERNAL_ERROR', 'An error occurred', ['_debug' => $message], 500);
    exit;
});

set_exception_handler(function (Throwable $e) {
    if (ob_get_level()) {
        ob_clean();
    }
    \Response::error('INTERNAL_ERROR', 'An error occurred', ['_debug' => $e->getMessage()], 500);
    exit;
});

Auth::initSession();

$requestUri = $_SERVER['REQUEST_URI'];
$queryPos = strpos($requestUri, '?');
if ($queryPos !== false) {
    $requestUri = substr($requestUri, 0, $queryPos);
}
// Some shared-hosting / nginx setups reject PATH_INFO (e.g. /api/index.php/drafts/..)
// with 406 Not Acceptable. To avoid relying on PATH_INFO, frontend can call:
//   /api/index.php?r=/drafts/<id>
// and we will route based on the `r` query parameter.
$path = $requestUri;
if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
    $path = $_GET['r'];
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
}

// Normalize possible /frontend/api* prefixes produced by clean external URLs.
// Examples we want to support (all should become the same logical path):
//   /frontend/api/drafts
//   /frontend/api/index.php?r=/drafts
if (strncmp($path, '/frontend/api/index.php', 22) === 0) {
    $path = substr($path, 22);
} elseif (strncmp($path, '/frontend/api', 12) === 0) {
    $path = substr($path, 12);
}

// Historical prefixes: /api/index.php, /api.
if (strncmp($path, '/api/index.php', 14) === 0) {
    $path = substr($path, 14);
} elseif (strncmp($path, '/api', 4) === 0) {
    $path = substr($path, 4);
}
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}
$method = $_SERVER['REQUEST_METHOD'];
$locale = Config::get('APP_LOCALE_DEFAULT', 'ru');
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'], true)) {
    $locale = $_GET['lang'];
}
if (isset($_SERVER['HTTP_X_LANG']) && in_array($_SERVER['HTTP_X_LANG'], ['ru', 'en'], true)) {
    $locale = $_SERVER['HTTP_X_LANG'];
}

if (AuthRoutes::handle($method, $path, $locale)) {
    return;
}
if (ProfileRoutes::handle($method, $path, $locale)) {
    return;
}
if (DraftRoutes::handle($method, $path, $locale)) {
    return;
}
if (ImageRoutes::handle($method, $path, $locale)) {
    return;
}
if (ProgressRoutes::handle($method, $path, $locale)) {
    return;
}
if (PipelineRoutes::handle($method, $path, $locale)) {
    return;
}
if (EmallDraftRoutes::handle($method, $path, $locale)) {
    return;
}

fail('ENDPOINT_NOT_FOUND', 'Endpoint not found', [], 404);
