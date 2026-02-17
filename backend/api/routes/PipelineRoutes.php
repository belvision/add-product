<?php

require_once __DIR__ . '/RouteHelpers.php';
require_once __DIR__ . '/../../src/DraftRepository.php';
require_once __DIR__ . '/../../src/OzonCredentialsRepository.php';
require_once __DIR__ . '/../../src/CategoryPipeline.php';
require_once __DIR__ . '/../../src/EmallCategoryPipeline.php';

class PipelineRoutes
{
    public static function handle($method, $path, $locale): bool
    {
        if ($method === 'POST' && preg_match('#^/drafts/([^/]+)/pipeline:start$#', $path, $m)) {
            // Category selection pipeline should work as soon as the user is authenticated.
            // Ozon API credentials are only required at the final publish step.
            $uid = requireAuth();
            $draftId = $m[1];
            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }
            if ($draft['status'] === 'processing') {
                fail('PIPELINE_ALREADY_RUNNING', 'Pipeline already running', [], 400);
            }
            if (!in_array($draft['status'], ['draft', 'failed'], true)) {
                Response::success(['started' => true, 'stage' => $draft['pipeline_stage'] ?? 'processing']);
                return true;
            }
            DraftRepository::setStatus($draftId, $uid, 'processing');
            $logs = [['ts' => gmdate('c'), 'message' => 'Pipeline started (stub)']];
            $qdrantTop10 = [];
            $chosenCategory = null;
            // In this MVP, the pipeline fills category candidates and chosen category.
            $requiredFields = [];
            $filledFields = array_keys(array_filter($draft['editedJson']));
            DraftRepository::setPipelineStatus($draftId, $uid, 'processing', 50, $logs, $qdrantTop10, $chosenCategory, $requiredFields, $filledFields, null, null, null);
            $logs[] = ['ts' => gmdate('c'), 'message' => 'Processing complete (stub)'];
            DraftRepository::setPipelineStatus($draftId, $uid, 'payload_ready', 100, $logs, $qdrantTop10, $chosenCategory, $requiredFields, $filledFields, $draft['editedJson'], $draft['editedJson'], null);
            Response::success(['started' => true, 'stage' => 'processing']);
            return true;
        }

        if ($method === 'GET' && preg_match('#^/drafts/([^/]+)/pipeline:status$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];
            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }
            $logs = $draft['pipeline_logs'];
            if (is_string($logs)) {
                $logs = json_decode($logs, true) ?: [];
            }
            if (is_array($logs) && count($logs) > 200) {
                $logs = array_slice($logs, -200);
            }
            Response::success([
                'stage' => $draft['pipeline_stage'] ?? 'idle',
                'progressPct' => $draft['pipeline_progress_pct'] ?? 0,
                'logs' => $logs,
                'qdrantTop10' => is_string($draft['qdrant_top10']) ? json_decode($draft['qdrant_top10'], true) : ($draft['qdrant_top10'] ?? []),
                'chosenCategory' => is_string($draft['chosen_category']) ? json_decode($draft['chosen_category'], true) : ($draft['chosen_category'] ?? null),
                'requiredFields' => is_string($draft['required_fields']) ? json_decode($draft['required_fields'], true) : ($draft['required_fields'] ?? []),
                'filledFields' => is_string($draft['filled_fields']) ? json_decode($draft['filled_fields'], true) : ($draft['filled_fields'] ?? []),
                'editedJson' => $draft['editedJson'],
                'finalPayloadJson' => is_string($draft['final_payload_json']) ? json_decode($draft['final_payload_json'], true) : ($draft['final_payload_json'] ?? null),
                'publishStatus' => $draft['publish_status'] ?? null,
            ]);
            return true;
        }

        // New category detection pipeline: embedding -> Qdrant -> Postgres -> DeepSeek.
        // Optional job_id: when provided, progress events are written for SSE stream (/api/progress/stream?job_id=...).
        // Body marketplace: 'ozon' (default) -> CategoryPipeline, 'emall' -> EmallCategoryPipeline.
        // NOTE: Some WAF/ModSecurity setups may block ':' in the URL path.
// Keep backward-compatible alias routes without ':' to avoid 406.
        if ($method === 'POST' && in_array($path, ['/pipeline/category:detect', '/pipeline/category-detect', '/pipeline/category/detect'], true)) {
            $uid = requireAuth();
            // IMPORTANT: this endpoint can be long-running; release PHP session lock to avoid blocking
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            $body = jsonBody() ?: [];
            $draftId = isset($body['draftId']) ? (string) $body['draftId'] : '';

            // ModSecurity (OWASP CRS 941120) may block long rich text description as XSS.
            // Support base64url-encoded description to avoid false positives.
            $description = '';
            if (isset($body['description_b64']) && is_string($body['description_b64']) && $body['description_b64'] !== '') {
                $b64 = strtr($body['description_b64'], '-_', '+/');
                $pad = strlen($b64) % 4;
                if ($pad) {
                    $b64 .= str_repeat('=', 4 - $pad);
                }
                $decoded = base64_decode($b64, true);
                if ($decoded !== false) {
                    $description = (string) $decoded;
                }
            }
            if ($description === '' && isset($body['description'])) {
                $description = (string) $body['description'];
            }
            $marketplace = isset($body['marketplace']) ? trim((string) $body['marketplace']) : 'ozon';
            if ($marketplace === '') {
                $marketplace = 'ozon';
            }
            $jobId = isset($body['job_id']) ? trim((string) $body['job_id']) : null;
            if ($jobId !== null && $jobId === '') {
                $jobId = null;
            }
            if ($draftId === '' || $description === '') {
                fail('VALIDATION_ERROR', 'draftId and description are required', []);
            }

            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }

            @set_time_limit(300);

            try {
                if ($marketplace === 'emall') {
                    $result = EmallCategoryPipeline::run($description, $locale, $jobId);
                } else {
                    $result = CategoryPipeline::run($description, $locale, $jobId);
                }
            } catch (RuntimeException $e) {
                fail('PIPELINE_ERROR', 'Category detection failed', ['reason' => $e->getMessage()]);
            }

            $editedJson = $draft['editedJson'];
            if (!is_array($editedJson)) {
                $editedJson = [];
            }
            $editedJson['description'] = $description;
            $editedJson['embedding'] = $result['embedding'];
            $editedJson['category_tree'] = isset($result['category_tree']) ? $result['category_tree'] : null;
            $editedJson['deepseek_tree'] = isset($result['deepseek_tree']) ? $result['deepseek_tree'] : null;
            $editedJson['qdrant_top10'] = $result['top10'];
            $editedJson['selected_category'] = $result['selected'];
            $editedJson['deepseek'] = $result['deepseek'];

            DraftRepository::saveDraft($draftId, $uid, $editedJson, $description);

            Response::success($result);
            return true;
        }

        return false;
    }
}

