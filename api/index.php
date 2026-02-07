<?php

require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/OzonFormSchema.php';
require_once __DIR__ . '/../src/DraftRepository.php';
require_once __DIR__ . '/../src/DraftValidator.php';

// Extract path from REQUEST_URI
$requestUri = $_SERVER['REQUEST_URI'];
$queryPos = strpos($requestUri, '?');
if ($queryPos !== false) {
    $requestUri = substr($requestUri, 0, $queryPos);
}

// Handle /api/index.php/... or /api/...
$path = $requestUri;
if (strpos($path, '/api/index.php') !== false) {
    $path = substr($path, strpos($path, '/api/index.php') + strlen('/api/index.php'));
} elseif (strpos($path, '/api/') !== false) {
    $path = substr($path, strpos($path, '/api/') + strlen('/api'));
}

$path = rtrim($path, '/');
if (empty($path)) {
    $path = '/';
}

$method = $_SERVER['REQUEST_METHOD'];

// Get locale from query or header
$locale = 'ru';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'])) {
    $locale = $_GET['lang'];
} elseif (isset($_SERVER['HTTP_X_LANG']) && in_array($_SERVER['HTTP_X_LANG'], ['ru', 'en'])) {
    $locale = $_SERVER['HTTP_X_LANG'];
}

// Route: POST /ozon/drafts
if ($method === 'POST' && $path === '/ozon/drafts') {
    $draft = DraftRepository::createDraft($locale);
    Response::success([
        'draftId' => $draft['draftId'],
        'formSchema' => $draft['formSchema'],
        'editedJson' => $draft['editedJson'],
        'images' => $draft['images']
    ]);
}

// Route: GET /ozon/drafts/{draftId}
if ($method === 'GET' && preg_match('#^/ozon/drafts/([^/]+)$#', $path, $matches)) {
    $draftId = $matches[1];
    $draft = DraftRepository::getDraft($draftId, $locale);
    if (!$draft) {
        Response::error('NOT_FOUND', 'Draft not found', [], 404);
    }
    Response::success([
        'draftId' => $draft['draftId'],
        'formSchema' => $draft['formSchema'],
        'editedJson' => $draft['editedJson'],
        'images' => $draft['images']
    ]);
}

// Route: PATCH /ozon/drafts/{draftId}
if ($method === 'PATCH' && preg_match('#^/ozon/drafts/([^/]+)$#', $path, $matches)) {
    $draftId = $matches[1];
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    
    if (!$data || !isset($data['editedJson'])) {
        Response::error('BAD_REQUEST', 'Invalid request body', []);
    }
    
    $draft = DraftRepository::saveDraft($draftId, $data['editedJson']);
    if (!$draft) {
        Response::error('NOT_FOUND', 'Draft not found', [], 404);
    }
    
    Response::success([
        'saved' => true,
        'version' => $draft['version']
    ]);
}

// Route: POST /ozon/drafts/{draftId}/images:upload
if ($method === 'POST' && preg_match('#^/ozon/drafts/([^/]+)/images:upload$#', $path, $matches)) {
    $draftId = $matches[1];
    
    if (!function_exists('finfo_open')) {
        Response::error('MISSING_EXTENSION', 'fileinfo extension required', ['ext' => 'fileinfo']);
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        Response::error('BAD_REQUEST', 'File upload failed', []);
    }
    
    $file = $_FILES['file'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Determine extension from mime type
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    if (!isset($extMap[$mimeType])) {
        Response::error('BAD_REQUEST', 'Invalid image type', []);
    }
    
    $ext = $extMap[$mimeType];
    $imageId = bin2hex(random_bytes(8));
    $imageDir = __DIR__ . '/../storage/marketplace/ozon/tmp/' . $draftId . '/images';
    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0755, true);
    }
    
    $targetPath = $imageDir . '/' . $imageId . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        Response::error('INTERNAL_ERROR', 'Failed to save file', []);
    }
    
    $image = [
        'imageId' => $imageId,
        'url' => null,
        'width' => null,
        'height' => null,
        'bytes' => filesize($targetPath)
    ];
    
    $draft = DraftRepository::addImage($draftId, $image);
    if (!$draft) {
        Response::error('NOT_FOUND', 'Draft not found', [], 404);
    }
    
    Response::success([
        'image' => $image,
        'images' => $draft['images']
    ]);
}

// Route: POST /ozon/drafts/{draftId}/images:from-url
if ($method === 'POST' && preg_match('#^/ozon/drafts/([^/]+)/images:from-url$#', $path, $matches)) {
    $draftId = $matches[1];
    
    if (!function_exists('curl_init')) {
        Response::error('MISSING_EXTENSION', 'curl extension required', ['ext' => 'curl']);
    }
    
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    
    if (!$data || !isset($data['url'])) {
        Response::error('BAD_REQUEST', 'URL required', []);
    }
    
    $url = $data['url'];
    $maxSize = 8 * 1024 * 1024; // 8MB
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXFILESIZE, $maxSize);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use ($maxSize) {
        $len = strlen($header);
        static $totalLen = 0;
        $totalLen += $len;
        if ($totalLen > $maxSize) {
            return -1;
        }
        return $len;
    });
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($httpCode !== 200 || $imageData === false || strlen($imageData) > $maxSize) {
        Response::error('FILE_TOO_LARGE', 'File too large or download failed', []);
    }
    
    // Determine extension
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    $ext = 'jpg'; // default
    foreach ($extMap as $mime => $e) {
        if (strpos($contentType, $mime) !== false) {
            $ext = $e;
            break;
        }
    }
    
    $imageId = bin2hex(random_bytes(8));
    $imageDir = __DIR__ . '/../storage/marketplace/ozon/tmp/' . $draftId . '/images';
    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0755, true);
    }
    
    $targetPath = $imageDir . '/' . $imageId . '.' . $ext;
    file_put_contents($targetPath, $imageData);
    
    $image = [
        'imageId' => $imageId,
        'url' => null,
        'width' => null,
        'height' => null,
        'bytes' => strlen($imageData)
    ];
    
    $draft = DraftRepository::addImage($draftId, $image);
    if (!$draft) {
        Response::error('NOT_FOUND', 'Draft not found', [], 404);
    }
    
    Response::success([
        'image' => $image,
        'images' => $draft['images']
    ]);
}

// Route: DELETE /ozon/drafts/{draftId}/images/{imageId}
if ($method === 'DELETE' && preg_match('#^/ozon/drafts/([^/]+)/images/([^/]+)$#', $path, $matches)) {
    $draftId = $matches[1];
    $imageId = $matches[2];
    
    $draft = DraftRepository::deleteImage($draftId, $imageId);
    if (!$draft) {
        Response::error('NOT_FOUND', 'Draft not found', [], 404);
    }
    
    Response::success([
        'deleted' => true,
        'images' => $draft['images']
    ]);
}

// Route: POST /ozon/drafts/{draftId}/validate
if ($method === 'POST' && preg_match('#^/ozon/drafts/([^/]+)/validate$#', $path, $matches)) {
    $draftId = $matches[1];
    $draft = DraftRepository::getDraft($draftId, $locale);
    if (!$draft) {
        Response::error('NOT_FOUND', 'Draft not found', [], 404);
    }
    
    $result = DraftValidator::validate($draft['formSchema'], $draft['editedJson'], $draft['images']);
    
    Response::success($result);
}

// Route: POST /ozon/drafts/{draftId}/publish
if ($method === 'POST' && preg_match('#^/ozon/drafts/([^/]+)/publish$#', $path, $matches)) {
    $draftId = $matches[1];
    $draft = DraftRepository::getDraft($draftId, $locale);
    if (!$draft) {
        Response::error('NOT_FOUND', 'Draft not found', [], 404);
    }
    
    // Validate first
    $validation = DraftValidator::validate($draft['formSchema'], $draft['editedJson'], $draft['images']);
    
    if (!$validation['valid']) {
        Response::error('VALIDATION_FAILED', 'Validation failed', $validation['errors']);
    }
    
    // Publish
    $productId = 'demo_' . $draftId;
    
    Response::success([
        'published' => true,
        'productId' => $productId
    ]);
}

// 404
Response::error('NOT_FOUND', 'Endpoint not found', [], 404);
