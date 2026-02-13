<?php

require_once __DIR__ . '/RouteHelpers.php';
require_once __DIR__ . '/../../src/DraftRepository.php';
require_once __DIR__ . '/../../src/DraftValidator.php';
require_once __DIR__ . '/../../src/OzonCredentialsRepository.php';

class DraftRoutes
{
    public static function handle($method, $path, $locale): bool
    {
        if ($method === 'POST' && $path === '/drafts') {
            $uid = requireAuth();
            $body = jsonBody() ?: [];
            $marketplace = (isset($body['marketplace']) && $body['marketplace'] !== '') ? $body['marketplace'] : 'ozon';
            $draft = DraftRepository::createDraft($uid, $locale, $marketplace);
            Response::success([
                'draftId' => $draft['draftId'],
                'formSchema' => $draft['formSchema'],
                'editedJson' => $draft['editedJson'],
                'images' => $draft['images'],
                'status' => $draft['status']
            ]);
            return true;
        }

        if ($method === 'GET' && preg_match('#^/drafts/([^/]+)$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];
            $draft = DraftRepository::getDraftForApi($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }
            Response::success($draft);
            return true;
        }

        if ($method === 'PATCH' && preg_match('#^/drafts/([^/]+)$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];
            $body = jsonBody() ?: [];
            $editedJson = isset($body['editedJson']) ? $body['editedJson'] : null;
            $description = isset($body['description']) ? $body['description'] : null;
            if ($editedJson === null && $description === null) {
                fail('VALIDATION_ERROR', 'description or editedJson required', []);
            }
            $draft = DraftRepository::getDraft($draftId, $uid, null);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }
            $draft = DraftRepository::saveDraft($draftId, $uid, $editedJson !== null ? $editedJson : $draft['editedJson'], $description);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }
            Response::success(['saved' => true]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/drafts/([^/]+)/validate$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];
            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }
            $result = DraftValidator::validate($draft['formSchema'], $draft['editedJson'], $draft['images']);
            Response::success($result);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/drafts/([^/]+)/publish$#', $path, $m)) {
            $uid = requireVerified();
            if (OzonCredentialsRepository::getForUser($uid) === null) {
                fail('OZON_CREDENTIALS_REQUIRED', 'Ozon credentials are not set', [], 400);
            }
            $draftId = $m[1];
            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }
            if (!in_array($draft['status'], ['draft', 'ready', 'payload_ready'], true)) {
                fail('VALIDATION_ERROR', 'Draft cannot be published', [], 400);
            }
            $validation = DraftValidator::validate($draft['formSchema'], $draft['editedJson'], $draft['images']);
            if (!$validation['valid']) {
                fail('PUBLISH_VALIDATION_FAILED', 'Validation failed', isset($validation['errors']) ? ['fieldErrors' => $validation['errors']] : []);
            }
            $productId = 'demo_' . $draftId;
            DraftRepository::setStatus($draftId, $uid, 'published');
            if (!DraftRepository::deleteTmpDir($uid, $draftId)) {
                fail('PUBLISH_CLEANUP_FAILED', 'Publish succeeded but cleanup failed', ['productId' => $productId], 500);
            }
            Response::success(['published' => true, 'productId' => $productId]);
            return true;
        }

        return false;
    }
}
