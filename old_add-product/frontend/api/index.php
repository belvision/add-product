<?php

/**
 * Front controller for clean API URLs under /frontend/api/*.
 *
 * Expected nginx config (external):
 *
 *   # /frontend/api/<route> -> /frontend/api/index.php?r=/<route>
 *   location ^~ /frontend/api/ {
 *       rewrite ^/frontend/api/(.*)$ /frontend/api/index.php?r=/$1 last;
 *       proxy_pass http://127.0.0.1:81;
 *       proxy_redirect http://127.0.0.1:81/ /;
 *       include /etc/nginx/proxy_params;
 *   }
 *
 * Browser sees only "clean" paths like:
 *   /frontend/api/me
 *   /frontend/api/drafts/{id}
 *   /frontend/api/pipeline/category:detect
 *
 * Nginx rewrites them to /frontend/api/index.php?r=/..., and this file
 * forwards the request into the main API router (backend/api/index.php),
 * emulating a request to /api/<route>.
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$pathOnly = parse_url($requestUri, PHP_URL_PATH);
$pathOnly = ($pathOnly !== null && $pathOnly !== '') ? $pathOnly : '/';
$queryString = parse_url($requestUri, PHP_URL_QUERY);
$queryString = ($queryString !== null && $queryString !== '') ? $queryString : '';

// Parse query string to extract r=/... (if present).
$query = [];
if ($queryString !== '') {
    parse_str($queryString, $query);
}

$r = isset($query['r']) ? (string) $query['r'] : '';

// If nginx somehow didn't add r=, derive it from the path /frontend/api/<route>.
if ($r === '') {
    if (strpos($pathOnly, '/frontend/api/') === 0) {
        $r = substr($pathOnly, strlen('/frontend/api'));
    } else {
        $r = '/';
    }
}

if ($r === '' || $r[0] !== '/') {
    $r = '/' . ltrim($r, '/');
}

// Preserve all other query params (e.g. token, pagination) except r.
unset($query['r']);
$restQuery = http_build_query($query);

// Final URI for the backend API router.
$forwardUri = '/api' . $r;
if ($restQuery !== '') {
    $forwardUri .= '?' . $restQuery;
}

$savedRequestUri = $_SERVER['REQUEST_URI'] ?? null;
$_SERVER['REQUEST_URI'] = $forwardUri;

require __DIR__ . '/../../backend/api/index.php';

if ($savedRequestUri !== null) {
    $_SERVER['REQUEST_URI'] = $savedRequestUri;
}

