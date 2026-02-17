<?php

require_once __DIR__ . '/RouteHelpers.php';
require_once __DIR__ . '/../../src/DraftRepository.php';
require_once __DIR__ . '/../../src/DraftValidator.php';
require_once __DIR__ . '/../../src/OzonCredentialsRepository.php';

class DraftRoutes
{
    public static function handle($method, $path, $locale): bool
    {
        // GET /draft?marketplace=... — при отсутствии черновика обязательно 404 + error.code NO_DRAFT для фронта (init: 404 → POST /draft/init).
        if ($method === 'GET' && $path === '/draft') {
            $uid = requireAuth();
            $marketplace = isset($_GET['marketplace']) ? trim((string) $_GET['marketplace']) : 'ozon';
            if ($marketplace === '') {
                $marketplace = 'ozon';
            }
            $row = DraftRepository::getCurrentDraftRow($uid, $marketplace);
            if (!$row) {
                fail('NO_DRAFT', 'No draft found', [], 404);
            }
            $draft = DraftRepository::getDraft($row['id'], $uid, $locale);
            $out = DraftRepository::getDraftForApi($row['id'], $uid, $locale);
            $out['id'] = $draft['draftId'];
            $out['current_step'] = $draft['current_step'];
            $out['step_state'] = $draft['step_state'];
            if ($marketplace === 'emall' && isset($draft['filled_fields'])) {
                $out['filled_fields'] = DraftRepository::resolveEmallFilledFieldsForApi($draft['filled_fields']);
            }
            $out['chosen_category'] = ($marketplace === 'ozon') ? DraftRepository::enrichCategoryWithName($draft['chosen_category']) : $draft['chosen_category'];
            if (!empty($out['step_state']['category'])) {
                $out['step_state']['category'] = ($marketplace === 'ozon') ? DraftRepository::enrichCategoryWithName($out['step_state']['category']) : $out['step_state']['category'];
            }
            $out['updated_at'] = $draft['updatedAt'];
            Response::success($out);
            return true;
        }

        if ($method === 'POST' && $path === '/draft/init') {
            $uid = requireAuth();
            $body = jsonBody() ?: [];
            $marketplace = isset($body['marketplace']) && $body['marketplace'] !== '' ? trim((string) $body['marketplace']) : 'ozon';
            $draft = DraftRepository::createOrGetDraft($uid, $marketplace, $locale);
            $out = DraftRepository::getDraftForApi($draft['draftId'], $uid, $locale);
            $out['current_step'] = $draft['current_step'];
            $out['step_state'] = $draft['step_state'];
            Response::success($out);
            return true;
        }

        // PATCH или POST /draft — сохранение шага (marketplace + patch). POST — обход 406 в nginx, где PATCH не проксируется.
        if (($method === 'PATCH' || $method === 'POST') && $path === '/draft') {
            $uid = requireAuth();
            $body = jsonBody() ?: [];
            $marketplace = isset($body['marketplace']) && $body['marketplace'] !== '' ? trim((string) $body['marketplace']) : 'ozon';
            $patch = isset($body['patch']) && is_array($body['patch']) ? $body['patch'] : [];

            // Decode base64url description fields (sent to bypass ModSecurity false-positives on rich text).
            $base64url_decode_utf8 = function($b64u) {
                if (!is_string($b64u) || $b64u === '') return null;
                $b64 = strtr($b64u, '-_', '+/');
                $pad = strlen($b64) % 4;
                if ($pad) $b64 .= str_repeat('=', 4 - $pad);
                $raw = base64_decode($b64, true);
                if ($raw === false) return null;
                // Ensure string is valid UTF-8 (best-effort)
                if (!mb_check_encoding($raw, 'UTF-8')) {
                    $raw = @mb_convert_encoding($raw, 'UTF-8', 'UTF-8,Windows-1251,ISO-8859-1');
                }
                return $raw;
            };

            if (isset($patch['description_b64']) && !isset($patch['description'])) {
                $decoded = $base64url_decode_utf8($patch['description_b64']);
                if ($decoded !== null) $patch['description'] = $decoded;
                unset($patch['description_b64']);
            }
            if (isset($patch['step_state']) && is_array($patch['step_state'])
                && isset($patch['step_state']['attributes']) && is_array($patch['step_state']['attributes'])
                && isset($patch['step_state']['attributes']['description_b64'])
                && !isset($patch['step_state']['attributes']['description'])) {
                $decoded = $base64url_decode_utf8($patch['step_state']['attributes']['description_b64']);
                if ($decoded !== null) $patch['step_state']['attributes']['description'] = $decoded;
                unset($patch['step_state']['attributes']['description_b64']);
            }

            $row = DraftRepository::getCurrentDraftRow($uid, $marketplace);
            if (!$row) {
                fail('NO_DRAFT', 'No draft found', [], 404);
            }
            DraftRepository::updateDraftPatch($uid, $marketplace, $patch);
            Response::success(['saved' => true]);
            return true;
        }

        if ($method === 'POST' && $path === '/draft/reset') {
            $uid = requireAuth();
            $body = jsonBody() ?: [];
            $marketplace = isset($body['marketplace']) && $body['marketplace'] !== '' ? trim((string) $body['marketplace']) : 'ozon';
            $draft = DraftRepository::resetDraftForUserMarketplace($uid, $marketplace, $locale);
            $out = DraftRepository::getDraftForApi($draft['draftId'], $uid, $locale);
            $out['id'] = $draft['draftId'];
            $out['current_step'] = $draft['current_step'];
            $out['step_state'] = $draft['step_state'];
            $out['editedJson'] = $draft['editedJson'];
            $out['images'] = $draft['images'];
            Response::success($out);
            return true;
        }

        if ($method === 'GET' && $path === '/drafts') {
            $uid = requireAuth();
            $list = DraftRepository::listDraftsForUser($uid, $locale);
            Response::success(['drafts' => $list]);
            return true;
        }

        if ($method === 'POST' && $path === '/drafts') {
            $uid = requireAuth();
            $body = jsonBody() ?: [];
            $marketplace = (isset($body['marketplace']) && $body['marketplace'] !== '') ? $body['marketplace'] : 'ozon';
            $draft = DraftRepository::createOrGetDraft($uid, $marketplace, $locale);
            Response::success([
                'draftId' => $draft['draftId'],
                'id' => $draft['draftId'],
                'formSchema' => $draft['formSchema'],
                'editedJson' => $draft['editedJson'],
                'images' => $draft['images'],
                'status' => $draft['status'],
                'current_step' => $draft['current_step'],
                'step_state' => $draft['step_state']
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

        $op = isset($_GET['op']) ? $_GET['op'] : (isset($_GET['action']) ? $_GET['action'] : '');
        if ((($method === 'PATCH') || ($method === 'POST' && $op === 'patch')) && preg_match('#^/drafts/([^/]+)$#', $path, $m)) {
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
