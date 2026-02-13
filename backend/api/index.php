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
require_once __DIR__ . '/routes/PipelineRoutes.php';

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
$path = $requestUri;
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
if (PipelineRoutes::handle($method, $path, $locale)) {
    return;
}

fail('ENDPOINT_NOT_FOUND', 'Endpoint not found', [], 404);
