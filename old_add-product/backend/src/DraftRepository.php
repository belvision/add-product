<?php

require_once __DIR__ . '/OzonFormSchema.php';

class DraftRepository
{
    /** UUID v4 for entity ids (draftId, imageId). Do not use Response::traceId() for entity ids. */
    public static function generateUuid()
    {
        return self::uuid4();
    }

    private static function uuid4()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Get marketplace for a draft (used for tmp path resolution).
     * @return string|null 'ozon'|'emall'|'wb' or null if not found
     */
    public static function getMarketplaceForDraft($userId, $draftId)
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('SELECT marketplace FROM drafts WHERE id = ?::uuid AND user_id = ?');
        $stmt->execute([$draftId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? trim((string) $row['marketplace']) : null;
    }

    /**
     * Base tmp directory for a marketplace (no user/draft).
     * @param string $marketplace e.g. 'ozon', 'wb', 'emall'
     */
    private static function tmpBase($marketplace)
    {
        $marketplace = $marketplace === '' ? 'ozon' : trim((string) $marketplace);
        return dirname(__DIR__) . '/storage/marketplace/' . $marketplace . '/tmp';
    }

    /**
     * Full tmp path for a draft. Resolves marketplace from DB.
     * backend/storage/marketplace/<marketplace>/tmp/<userId>/<draftId>
     */
    public static function tmpPathForDraft($userId, $draftId)
    {
        $marketplace = self::getMarketplaceForDraft($userId, $draftId);
        if ($marketplace === null || $marketplace === '') {
            $marketplace = 'ozon';
        }
        return self::tmpBase($marketplace) . '/' . (int) $userId . '/' . $draftId;
    }

    public static function imagePath($userId, $draftId, $imageId)
    {
        return self::tmpPathForDraft($userId, $draftId) . '/images/' . $imageId . '.jpg';
    }

    /**
     * Get filesystem path for a draft image. Returns null if image not found.
     * Used by GET /drafts/{draftId}/images/{imageId} to serve image files.
     */
    public static function getImageFilePath($draftId, $imageId)
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('SELECT di.user_id FROM draft_images di WHERE di.draft_id = ?::uuid AND di.id = ?::uuid');
        $stmt->execute([$draftId, $imageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return self::imagePath((int) $row['user_id'], $draftId, $imageId);
    }

    public static function createDraft($userId, $locale, $marketplace = 'ozon')
    {
        $draftId = self::uuid4();
        $formSchema = OzonFormSchema::getSchema($locale);
        $editedJson = ['title' => '', 'brand' => '', 'description' => ''];
        $pdo = \Db::get();
        $stmt = $pdo->prepare('INSERT INTO drafts (id, user_id, marketplace, description, edited_json, form_schema, status) VALUES (?::uuid, ?, ?, ?, ?::jsonb, ?::jsonb, ?)');
        $stmt->execute([$draftId, $userId, $marketplace, '', json_encode($editedJson), json_encode($formSchema), 'draft']);
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_dir($dir . '/images')) {
            mkdir($dir . '/images', 0755, true);
        }
        touch($dir . '/.touch');
        return self::getDraft($draftId, $userId, $locale);
    }

    public static function getDraft($draftId, $userId, $locale)
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('SELECT id, user_id, status, description, edited_json, form_schema, updated_at, pipeline_stage, pipeline_progress_pct, pipeline_logs, qdrant_top10, chosen_category, required_fields, filled_fields, final_payload_json, publish_status, COALESCE(current_step, 1) AS current_step, COALESCE(step_state, \'{}\') AS step_state FROM drafts WHERE id = ?::uuid AND user_id = ?');
        $stmt->execute([$draftId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $schema = json_decode($row['form_schema'], true);
        if (empty($schema)) {
            $schema = OzonFormSchema::getSchema($locale);
        }
        $stmt2 = $pdo->prepare('SELECT id, path, width, height, bytes FROM draft_images WHERE draft_id = ?::uuid ORDER BY created_at');
        $stmt2->execute([$draftId]);
        $images = [];
        while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $images[] = ['imageId' => $r['id'], 'url' => null, 'width' => $r['width'] !== null ? (int) $r['width'] : null, 'height' => $r['height'] !== null ? (int) $r['height'] : null, 'bytes' => (int) $r['bytes']];
        }
        $stepState = $row['step_state'] ?? '{}';
        if (is_string($stepState)) {
            $stepState = json_decode($stepState, true) ?: [];
        }
        $chosenCategory = $row['chosen_category'];
        if (is_string($chosenCategory)) {
            $chosenCategory = json_decode($chosenCategory, true) ?: null;
        }
        return [
            'draftId' => $row['id'],
            'userId' => (int) $row['user_id'],
            'status' => $row['status'],
            'description' => $row['description'] ?? '',
            'formSchema' => $schema,
            'editedJson' => json_decode($row['edited_json'], true) ?: [],
            'images' => $images,
            'updatedAt' => $row['updated_at'],
            'current_step' => isset($row['current_step']) ? (int) $row['current_step'] : 1,
            'step_state' => $stepState,
            'pipeline_stage' => $row['pipeline_stage'],
            'pipeline_progress_pct' => $row['pipeline_progress_pct'] !== null ? (int) $row['pipeline_progress_pct'] : null,
            'pipeline_logs' => $row['pipeline_logs'],
            'qdrant_top10' => $row['qdrant_top10'],
            'chosen_category' => $chosenCategory,
            'required_fields' => $row['required_fields'],
            'filled_fields' => $row['filled_fields'],
            'final_payload_json' => $row['final_payload_json'],
            'publish_status' => $row['publish_status'],
        ];
    }

    public static function getDraftForApi($draftId, $userId, $locale)
    {
        $d = self::getDraft($draftId, $userId, $locale);
        if (!$d) {
            return null;
        }
        unset($d['userId']);
        unset($d['pipeline_stage']);
        unset($d['pipeline_progress_pct']);
        unset($d['pipeline_logs']);
        unset($d['qdrant_top10']);
        unset($d['chosen_category']);
        unset($d['required_fields']);
        unset($d['filled_fields']);
        unset($d['final_payload_json']);
        unset($d['publish_status']);
        return $d;
    }

    public static function saveDraft($draftId, $userId, $editedJson, $description = null)
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('SELECT id FROM drafts WHERE id = ?::uuid AND user_id = ?');
        $stmt->execute([$draftId, $userId]);
        if (!$stmt->fetch()) {
            return null;
        }
        if ($description !== null) {
            $stmt = $pdo->prepare('UPDATE drafts SET description = ?, edited_json = ?::jsonb, updated_at = NOW() WHERE id = ?::uuid AND user_id = ?');
            $stmt->execute([$description, json_encode($editedJson), $draftId, $userId]);
        } else {
            $stmt = $pdo->prepare('UPDATE drafts SET edited_json = ?::jsonb, updated_at = NOW() WHERE id = ?::uuid AND user_id = ?');
            $stmt->execute([json_encode($editedJson), $draftId, $userId]);
        }
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (is_dir($dir)) {
            touch($dir . '/.touch');
        }
        return self::getDraft($draftId, $userId, null);
    }

    public static function addImage($draftId, $userId, $image)
    {
        $d = self::getDraft($draftId, $userId, null);
        if (!$d) {
            return null;
        }
        $marketplace = self::getMarketplaceForDraft($userId, $draftId);
        if ($marketplace === null || $marketplace === '') {
            $marketplace = 'ozon';
        }
        $imageId = $image['imageId'];
        $path = 'marketplace/' . $marketplace . '/tmp/' . (int) $userId . '/' . $draftId . '/images/' . $imageId . '.jpg';
        $pdo = \Db::get();
        $stmt = $pdo->prepare('INSERT INTO draft_images (id, draft_id, user_id, path, width, height, bytes) VALUES (?::uuid, ?::uuid, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $imageId,
            $draftId,
            $userId,
            $path,
            isset($image['width']) ? $image['width'] : null,
            isset($image['height']) ? $image['height'] : null,
            isset($image['bytes']) ? $image['bytes'] : null
        ]);
        return self::getDraft($draftId, $userId, null);
    }

    public static function deleteImage($draftId, $userId, $imageId)
    {
        $d = self::getDraft($draftId, $userId, null);
        if (!$d) {
            return null;
        }
        $pdo = \Db::get();
        $pdo->prepare('DELETE FROM draft_images WHERE draft_id = ?::uuid AND id = ?::uuid')->execute([$draftId, $imageId]);
        $path = self::imagePath($userId, $draftId, $imageId);
        if (is_file($path)) {
            unlink($path);
        }
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (is_dir($dir)) {
            touch($dir . '/.touch');
        }
        return self::getDraft($draftId, $userId, null);
    }

    public static function touchTtl($userId, $draftId)
    {
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (is_dir($dir)) {
            touch($dir . '/.touch');
        }
    }

    public static function setPipelineStatus($draftId, $userId, $stage, $progressPct, $logs, $qdrantTop10, $chosenCategory, $requiredFields, $filledFields, $editedJson, $finalPayloadJson, $publishStatus)
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('UPDATE drafts SET pipeline_stage = ?, pipeline_progress_pct = ?, pipeline_logs = ?::jsonb, qdrant_top10 = ?::jsonb, chosen_category = ?::jsonb, required_fields = ?::jsonb, filled_fields = ?::jsonb, edited_json = COALESCE(?::jsonb, edited_json), final_payload_json = ?::jsonb, publish_status = ?, status = ?, updated_at = NOW() WHERE id = ?::uuid AND user_id = ?');
        $status = ($stage === 'ready' || $stage === 'payload_ready' || $stage === 'failed') ? ($stage === 'failed' ? 'failed' : 'ready') : 'processing';
        $stmt->execute([
            $stage,
            $progressPct,
            $logs === null ? null : json_encode($logs),
            $qdrantTop10 === null ? null : json_encode($qdrantTop10),
            $chosenCategory === null ? null : json_encode($chosenCategory),
            $requiredFields === null ? null : json_encode($requiredFields),
            $filledFields === null ? null : json_encode($filledFields),
            $editedJson === null ? null : json_encode($editedJson),
            $finalPayloadJson === null ? null : json_encode($finalPayloadJson),
            $publishStatus,
            $status,
            $draftId,
            $userId
        ]);
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (is_dir($dir)) {
            touch($dir . '/.touch');
        }
    }

    public static function setStatus($draftId, $userId, $status)
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('UPDATE drafts SET status = ?, updated_at = NOW() WHERE id = ?::uuid AND user_id = ?');
        $stmt->execute([$status, $draftId, $userId]);
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (is_dir($dir)) {
            touch($dir . '/.touch');
        }
    }

    public static function deleteTmpDir($userId, $draftId)
    {
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (!is_dir($dir)) {
            return true;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $f) {
            if ($f->isDir()) {
                rmdir($f->getRealPath());
            } else {
                unlink($f->getRealPath());
            }
        }
        return @rmdir($dir);
    }

    public static function listDraftsForUser($userId, $locale)
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('SELECT id FROM drafts WHERE user_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$userId]);
        $list = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $d = self::getDraftForApi($row['id'], $userId, $locale);
            if ($d) {
                $list[] = $d;
            }
        }
        return $list;
    }

    /**
     * Get current draft row (status in draft/processing) for user + marketplace.
     * @return array|null ['id'=>..., 'description'=>..., 'current_step'=>..., 'step_state'=>...] or null
     */
    public static function getCurrentDraftRow($userId, $marketplace)
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('SELECT id, description, current_step, step_state FROM drafts WHERE user_id = ? AND marketplace = ? AND status IN (?, ?) ORDER BY updated_at DESC LIMIT 1');
        $stmt->execute([$userId, $marketplace, 'draft', 'processing']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $stepState = $row['step_state'] ?? '{}';
        if (is_string($stepState)) {
            $stepState = json_decode($stepState, true) ?: [];
        }
        $row['current_step'] = isset($row['current_step']) ? (int) $row['current_step'] : 1;
        $row['step_state'] = $stepState;
        return $row;
    }

    /**
     * Get or create the single draft (status='draft') for user + marketplace.
     * On unique constraint violation (race), re-select and return existing.
     * @return array full draft (getDraft shape) with current_step, step_state
     */
    public static function createOrGetDraft($userId, $marketplace, $locale)
    {
        $row = self::getCurrentDraftRow($userId, $marketplace);
        if ($row) {
            return self::getDraft($row['id'], $userId, $locale);
        }

        $draftId = self::uuid4();
        $formSchema = OzonFormSchema::getSchema($locale);
        $editedJson = ['title' => '', 'brand' => '', 'description' => ''];
        $pdo = \Db::get();
        $stmt = $pdo->prepare('INSERT INTO drafts (id, user_id, marketplace, description, current_step, step_state, edited_json, form_schema, status) VALUES (?::uuid, ?, ?, ?, 1, ?::jsonb, ?::jsonb, ?::jsonb, ?)');
        try {
            $stmt->execute([$draftId, $userId, $marketplace, '', json_encode([]), json_encode($editedJson), json_encode($formSchema), 'draft']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505' || strpos($e->getMessage(), 'uq_drafts_one_draft_per_market') !== false || strpos($e->getMessage(), 'unique') !== false) {
                $row = self::getCurrentDraftRow($userId, $marketplace);
                if ($row) {
                    return self::getDraft($row['id'], $userId, $locale);
                }
            }
            throw $e;
        }
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_dir($dir . '/images')) {
            mkdir($dir . '/images', 0755, true);
        }
        touch($dir . '/.touch');
        return self::getDraft($draftId, $userId, $locale);
    }

    /**
     * Update current draft (status='draft') for user + marketplace.
     * patch: description?, current_step?, step_state? (step_state merged at top level).
     * @return bool true if updated, false if no draft found
     */
    public static function updateDraftPatch($userId, $marketplace, array $patch)
    {
        $pdo = \Db::get();
        $row = self::getCurrentDraftRow($userId, $marketplace);
        if (!$row) {
            return false;
        }
        $updates = [];
        $params = [];
        if (array_key_exists('description', $patch)) {
            $updates[] = 'description = ?';
            $params[] = $patch['description'];
        }
        if (array_key_exists('current_step', $patch)) {
            $updates[] = 'current_step = ?';
            $params[] = (int) $patch['current_step'];
        }
        if (array_key_exists('step_state', $patch) && is_array($patch['step_state'])) {
            $newState = $patch['step_state'];
            $existing = $row['step_state'] ?? '{}';
            if (is_string($existing)) {
                $decoded = json_decode($existing, true);
                $existing = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($existing)) {
                $existing = [];
            }
            $merged = array_merge($existing, $newState);
            $updates[] = 'step_state = ?::jsonb';
            $params[] = json_encode($merged);
        }
        if (array_key_exists('chosen_category', $patch)) {
            $updates[] = 'chosen_category = ?::jsonb';
            $params[] = $patch['chosen_category'] === null ? null : json_encode($patch['chosen_category']);
        }
        if (empty($updates)) {
            return true;
        }
        $updates[] = 'updated_at = NOW()';
        $sql = 'UPDATE drafts SET ' . implode(', ', $updates) . ' WHERE id = ?::uuid AND user_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$row['id'], $userId]));
        if (array_key_exists('description', $patch)) {
            $stmt2 = $pdo->prepare('SELECT edited_json FROM drafts WHERE id = ?::uuid AND user_id = ?');
            $stmt2->execute([$row['id'], $userId]);
            $ej = $stmt2->fetchColumn();
            $editedJson = is_string($ej) ? (json_decode($ej, true) ?: []) : [];
            $editedJson['description'] = $patch['description'];
            $stmt3 = $pdo->prepare('UPDATE drafts SET edited_json = ?::jsonb WHERE id = ?::uuid AND user_id = ?');
            $stmt3->execute([json_encode($editedJson), $row['id'], $userId]);
        }
        return true;
    }

    /**
     * Merge step_state for a specific draft by ID.
     *
     * @param string $draftId UUID
     * @param int $userId
     * @param array $stepStatePatch Keys to merge into step_state (e.g. ['dimensions' => [...]])
     * @return bool true if updated
     */
    public static function mergeStepStateForDraft($draftId, $userId, array $stepStatePatch)
    {
        if (empty($stepStatePatch)) {
            return true;
        }
        $pdo = \Db::get();
        $stmt = $pdo->prepare('UPDATE drafts SET step_state = COALESCE(step_state, \'{}\')::jsonb || ?::jsonb, updated_at = NOW() WHERE id = ?::uuid AND user_id = ?');
        $stmt->execute([json_encode($stepStatePatch), $draftId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Set current draft (status in draft/processing) to ready for user + marketplace.
     * @return bool true if updated
     */
    public static function setDraftReady($userId, $marketplace)
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('UPDATE drafts SET status = ?, updated_at = NOW() WHERE user_id = ? AND marketplace = ? AND status IN (?, ?)');
        $stmt->execute(['ready', $userId, $marketplace, 'draft', 'processing']);
        return $stmt->rowCount() > 0;
    }

    /**
     * Fully reset draft for user + marketplace: clear step_state, edited_json, delete images, tmp dir.
     * Reuses the same draft row. Returns the cleared draft.
     *
     * @return array|null Full draft (getDraft shape) or null if no draft found
     */
    public static function resetDraftForUserMarketplace($userId, $marketplace, $locale)
    {
        $row = self::getCurrentDraftRow($userId, $marketplace);
        if (!$row || empty($row['id'])) {
            return self::createOrGetDraft($userId, $marketplace, $locale);
        }
        $draftId = $row['id'];
        $pdo = \Db::get();
        $pdo->prepare('DELETE FROM draft_images WHERE draft_id = ?::uuid')->execute([$draftId]);
        self::deleteTmpDir($userId, $draftId);
        $emptyJson = json_encode(['title' => '', 'brand' => '', 'description' => '']);
        // Полный сброс черновика: очищаем step_state, edited_json и текстовое описание.
        $stmt = $pdo->prepare('UPDATE drafts SET step_state = ?::jsonb, edited_json = ?::jsonb, description = ?, current_step = 1, chosen_category = NULL, qdrant_top10 = NULL, required_fields = NULL, filled_fields = NULL, pipeline_stage = NULL, pipeline_progress_pct = NULL, pipeline_logs = NULL, final_payload_json = NULL, publish_status = NULL, updated_at = NOW() WHERE id = ?::uuid AND user_id = ?');
        $stmt->execute([json_encode([]), $emptyJson, '', $draftId, $userId]);
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_dir($dir . '/images')) {
            mkdir($dir . '/images', 0755, true);
        }
        touch($dir . '/.touch');
        return self::getDraft($draftId, $userId, $locale);
    }

    /**
     * Resolve category title_cat and type_name from public.category_ozon by primary key id (db_id).
     * @param int $dbId category_ozon.id (db_id from pipeline)
     * @return array|null ['title_cat' => string, 'type_name' => string] or null
     */
    public static function getCategoryOzonNameByDbId($dbId)
    {
        if ($dbId === null || $dbId === '') {
            return null;
        }
        $pdo = \Db::get();
        $stmt = $pdo->prepare('SELECT title_cat, type_name FROM public.category_ozon WHERE id = ?');
        $stmt->execute([(int) $dbId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'title_cat' => (string) ($row['title_cat'] ?? ''),
            'type_name' => (string) ($row['type_name'] ?? ''),
        ];
    }

    /**
     * Update eMall generated text in filled_fields.
     *
     * @param string $draftId
     * @param int $userId
     * @param array{title: string, description: string, benefits: string, usage: string} $text
     * @param string $model
     * @param string $source
     * @return bool
     */
    public static function updateEmallGeneratedText($draftId, $userId, array $text, string $model = '', string $source = '')
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('SELECT filled_fields FROM drafts WHERE id = ?::uuid AND user_id = ?');
        $stmt->execute([$draftId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $filled = $row['filled_fields'];
        $filled = is_string($filled) ? (json_decode($filled, true) ?: []) : (is_array($filled) ? $filled : []);
        $filled['generated_text'] = [
            'title' => (string) ($text['title'] ?? ''),
            'description' => (string) ($text['description'] ?? ''),
            'benefits' => (string) ($text['benefits'] ?? ''),
            'usage' => (string) ($text['usage'] ?? ''),
            'model' => $model,
            'created_at' => gmdate('c'),
            'source' => $source,
        ];
        $filled['title'] = isset($filled['edited_text']['title']) ? (string) $filled['edited_text']['title'] : (string) ($text['title'] ?? '');
        $stmt = $pdo->prepare('UPDATE drafts SET filled_fields = ?::jsonb, updated_at = NOW() WHERE id = ?::uuid AND user_id = ?');
        $stmt->execute([json_encode($filled), $draftId, $userId]);
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (is_dir($dir)) {
            touch($dir . '/.touch');
        }
        return true;
    }

    /**
     * Update eMall user-edited text in filled_fields.
     *
     * @param string $draftId
     * @param int $userId
     * @param array{title: string, description: string, benefits: string, usage: string} $text
     * @return bool
     */
    public static function updateEmallEditedText($draftId, $userId, array $text): bool
    {
        $pdo = \Db::get();
        $stmt = $pdo->prepare('SELECT filled_fields FROM drafts WHERE id = ?::uuid AND user_id = ?');
        $stmt->execute([$draftId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $filled = $row['filled_fields'];
        $filled = is_string($filled) ? (json_decode($filled, true) ?: []) : (is_array($filled) ? $filled : []);
        $filled['edited_text'] = [
            'title' => (string) ($text['title'] ?? ''),
            'description' => (string) ($text['description'] ?? ''),
            'benefits' => (string) ($text['benefits'] ?? ''),
            'usage' => (string) ($text['usage'] ?? ''),
            'saved_at' => gmdate('c'),
        ];
        $filled['title'] = (string) ($text['title'] ?? '');
        $stmt = $pdo->prepare('UPDATE drafts SET filled_fields = ?::jsonb, updated_at = NOW() WHERE id = ?::uuid AND user_id = ?');
        $stmt->execute([json_encode($filled), $draftId, $userId]);
        $dir = self::tmpPathForDraft($userId, $draftId);
        if (is_dir($dir)) {
            touch($dir . '/.touch');
        }
        return true;
    }

    /**
     * Resolve eMall filled_fields for API (edited_text priority over generated_text).
     *
     * @param array|string|null $rawFilledFields
     * @return array{title: string, description: string, benefits: string, usage: string, generated_text?: array}
     */
    public static function resolveEmallFilledFieldsForApi($rawFilledFields): array
    {
        $ff = is_string($rawFilledFields) ? json_decode($rawFilledFields, true) : ($rawFilledFields ?? []);
        $ff = is_array($ff) ? $ff : [];
        $edited = $ff['edited_text'] ?? null;
        $generated = $ff['generated_text'] ?? null;
        $source = is_array($edited) && (($edited['title'] ?? '') !== '' || ($edited['description'] ?? '') !== '') ? $edited : (is_array($generated) ? $generated : []);
        $title = (string) ($source['title'] ?? $ff['title'] ?? '');
        $description = (string) ($source['description'] ?? '');
        $benefits = (string) ($source['benefits'] ?? '');
        $usage = (string) ($source['usage'] ?? '');
        return [
            'title' => $title,
            'description' => $description,
            'benefits' => $benefits,
            'usage' => $usage,
            'generated_text' => ['title' => $title, 'description' => $description, 'benefits' => $benefits, 'usage' => $usage],
        ];
    }

    /**
     * Enrich category array with title_cat and type_name from category_ozon if missing.
     * @param array|null $cat chosen_category or step_state.category (assoc array)
     * @return array|null same structure with title_cat/type_name filled if they were missing
     */
    public static function enrichCategoryWithName($cat)
    {
        if (!$cat || !is_array($cat)) {
            return $cat;
        }
        $hasName = (!empty($cat['title_cat']) || !empty($cat['type_name']));
        if ($hasName) {
            return $cat;
        }
        $dbId = isset($cat['db_id']) ? $cat['db_id'] : (isset($cat['id']) ? $cat['id'] : null);
        if ($dbId === null) {
            return $cat;
        }
        $resolved = self::getCategoryOzonNameByDbId($dbId);
        if ($resolved) {
            $cat['title_cat'] = $resolved['title_cat'];
            $cat['type_name'] = $resolved['type_name'];
        }
        return $cat;
    }
}
