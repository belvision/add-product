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

    private static function tmpBase()
    {
        return dirname(__DIR__) . '/storage/marketplace/ozon/tmp';
    }

    public static function tmpPathForDraft($userId, $draftId)
    {
        return self::tmpBase() . '/' . (int) $userId . '/' . $draftId;
    }

    public static function imagePath($userId, $draftId, $imageId)
    {
        return self::tmpPathForDraft($userId, $draftId) . '/images/' . $imageId . '.jpg';
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
        $stmt = $pdo->prepare('SELECT id, user_id, status, description, edited_json, form_schema, updated_at, pipeline_stage, pipeline_progress_pct, pipeline_logs, qdrant_top10, chosen_category, required_fields, filled_fields, final_payload_json, publish_status FROM drafts WHERE id = ?::uuid AND user_id = ?');
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
        return [
            'draftId' => $row['id'],
            'userId' => (int) $row['user_id'],
            'status' => $row['status'],
            'description' => $row['description'] ?? '',
            'formSchema' => $schema,
            'editedJson' => json_decode($row['edited_json'], true) ?: [],
            'images' => $images,
            'updatedAt' => $row['updated_at'],
            'pipeline_stage' => $row['pipeline_stage'],
            'pipeline_progress_pct' => $row['pipeline_progress_pct'] !== null ? (int) $row['pipeline_progress_pct'] : null,
            'pipeline_logs' => $row['pipeline_logs'],
            'qdrant_top10' => $row['qdrant_top10'],
            'chosen_category' => $row['chosen_category'],
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
        $imageId = $image['imageId'];
        $path = 'marketplace/ozon/tmp/' . (int) $userId . '/' . $draftId . '/images/' . $imageId . '.jpg';
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
}
