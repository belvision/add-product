<?php

require_once __DIR__ . '/RouteHelpers.php';
require_once __DIR__ . '/../../src/DraftRepository.php';
require_once __DIR__ . '/../../src/OzonCredentialsRepository.php';

class PipelineRoutes
{
    public static function handle($method, $path, $locale): bool
    {
        if ($method === 'POST' && preg_match('#^/drafts/([^/]+)/pipeline:start$#', $path, $m)) {
            $uid = requireVerified();
            if (OzonCredentialsRepository::getForUser($uid) === null) {
                fail('OZON_CREDENTIALS_REQUIRED', 'Ozon credentials are not set', [], 400);
            }
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
            $requiredFields = $draft['formSchema'] ? ['title', 'brand'] : [];
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

        return false;
    }
}
