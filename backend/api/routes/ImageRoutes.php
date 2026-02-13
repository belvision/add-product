<?php

require_once __DIR__ . '/RouteHelpers.php';
require_once __DIR__ . '/../../src/DraftRepository.php';
require_once __DIR__ . '/../../src/ImageConverter.php';
require_once __DIR__ . '/../../src/SsrfProtection.php';

class ImageRoutes
{
    public static function handle($method, $path, $locale): bool
    {
        if ($method === 'POST' && preg_match('#^/drafts/([^/]+)/images:upload$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];
            $draft = DraftRepository::getDraft($draftId, $uid, null);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }
            if (!function_exists('finfo_open')) {
                fail('MISSING_EXTENSION', 'fileinfo extension required', ['ext' => 'fileinfo']);
            }
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                fail('VALIDATION_ERROR', 'File upload failed', []);
            }
            $file = $_FILES['file'];
            if ($file['size'] > ImageConverter::MAX_SIZE) {
                fail('UPLOAD_TOO_LARGE', 'File too large', []);
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!ImageConverter::mimeAllowed($mime)) {
                fail('UNSUPPORTED_IMAGE_FORMAT', 'Invalid image type', []);
            }
            $imageId = DraftRepository::generateUuid();
            $dir = DraftRepository::tmpPathForDraft($uid, $draftId) . '/images';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $destPath = $dir . '/' . $imageId . '.jpg';
            if (!ImageConverter::convertToJpg($file['tmp_name'], $mime, $destPath)) {
                fail('UNSUPPORTED_IMAGE_FORMAT', 'Failed to process image', []);
            }
            DraftRepository::touchTtl($uid, $draftId);
            $bytes = filesize($destPath);
            $size = @getimagesize($destPath);
            $width = $size ? $size[0] : null;
            $height = $size ? $size[1] : null;
            $image = ['imageId' => $imageId, 'url' => null, 'width' => $width, 'height' => $height, 'bytes' => $bytes];
            $draft = DraftRepository::addImage($draftId, $uid, $image);
            Response::success(['image' => $image, 'images' => $draft['images']]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/drafts/([^/]+)/images:from-url$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];
            $draft = DraftRepository::getDraft($draftId, $uid, null);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }
            if (!function_exists('curl_init')) {
                fail('MISSING_EXTENSION', 'curl extension required', ['ext' => 'curl']);
            }
            $body = jsonBody() ?: [];
            if (!isset($body['url'])) {
                fail('VALIDATION_ERROR', 'URL required', []);
            }
            $url = $body['url'];
            list($ok, $result, $errCode, $errMsg) = SsrfProtection::fetchWithLimit($url, SsrfProtection::MAX_REDIRECTS, SsrfProtection::MAX_BYTES);
            if (!$ok) {
                fail($errCode ?: 'FETCH_FAILED', $errMsg ?: 'Download failed', []);
            }
            $contentType = isset($result['content_type']) ? $result['content_type'] : '';
            $mime = strtolower(trim(explode(';', $contentType)[0]));
            if (!ImageConverter::mimeAllowed($mime)) {
                fail('UNSUPPORTED_IMAGE_FORMAT', 'Invalid image type', []);
            }
            $imageId = DraftRepository::generateUuid();
            $dir = DraftRepository::tmpPathForDraft($uid, $draftId) . '/images';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $destPath = $dir . '/' . $imageId . '.jpg';
            if (!ImageConverter::convertBlobToJpg($result['body'], $contentType, $destPath)) {
                fail('UNSUPPORTED_IMAGE_FORMAT', 'Failed to process image', []);
            }
            DraftRepository::touchTtl($uid, $draftId);
            $bytes = filesize($destPath);
            $size = @getimagesize($destPath);
            $width = $size ? $size[0] : null;
            $height = $size ? $size[1] : null;
            $image = ['imageId' => $imageId, 'url' => null, 'width' => $width, 'height' => $height, 'bytes' => $bytes];
            $draft = DraftRepository::addImage($draftId, $uid, $image);
            Response::success(['image' => $image, 'images' => $draft['images']]);
            return true;
        }

        if ($method === 'DELETE' && preg_match('#^/drafts/([^/]+)/images/([^/]+)$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];
            $imageId = $m[2];
            $draft = DraftRepository::deleteImage($draftId, $uid, $imageId);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }
            Response::success(['deleted' => true, 'images' => $draft['images']]);
            return true;
        }

        return false;
    }
}
