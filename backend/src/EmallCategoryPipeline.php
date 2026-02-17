<?php

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/ProgressReporter.php';

/**
 * Category detection pipeline for eMall.
 *
 * Steps:
 *  1) DeepSeek builds a category tree string.
 *  2) GPT embedding via proxy.
 *  3) Qdrant search (collection: categoryEmall, top10, with_payload for display).
 *  4) PostgreSQL lookup in public.category_emall if table exists; else top10 from Qdrant payload.
 *  5) DeepSeek chooses 1 best category.
 */
class EmallCategoryPipeline
{
    private const EMBED_DIM = 1536;
    private const QDRANT_COLLECTION = 'categoryEmall';
    private const OPENAI_RETRY_DELAYS_MS = [300, 800, 1500, 3000];

    /**
     * Run full category pipeline for eMall.
     *
     * @param string $description
     * @param string $locale
     * @param string|null $jobId
     * @return array{embedding: array, category_tree: string, deepseek_tree: array, top10: array, deepseek: array, selected: array}
     */
    public static function run(string $description, string $locale, ?string $jobId = null): array
    {
        $reporter = $jobId !== null && $jobId !== '' ? new FileProgressReporter($jobId) : null;

        $description = trim($description);
        if ($description === '') {
            if ($reporter) {
                $reporter->emit('pipeline_error', 'error', 'Description required', []);
            }
            throw new RuntimeException('DESCRIPTION_REQUIRED');
        }

        if ($reporter) {
            $reporter->emit('deepseek_tree', 'info', '1/5 DeepSeek строит дерево категорий eMall...', []);
        }
        $treeMeta = self::buildCategoryTreeWithDeepseek($description, $locale);
        $categoryTreeText = isset($treeMeta['category_tree']) && is_string($treeMeta['category_tree']) && $treeMeta['category_tree'] !== ''
            ? $treeMeta['category_tree']
            : $description;

        if ($reporter) {
            $reporter->emit('embedding_prepare', 'info', '2/5 Получаем embedding...', []);
        }
        $t0 = microtime(true);
        try {
            [$embeddingVector, $embeddingMeta] = self::buildEmbedding($categoryTreeText, $reporter);
        } catch (Throwable $e) {
            if ($reporter) {
                $reporter->emit('openai_embeddings', 'error', 'Ошибка OpenAI embeddings', [
                    'type' => $e->getMessage(),
                    'http' => null,
                    'curl' => null,
                ]);
                $reporter->emit('pipeline_error', 'error', 'Pipeline failed', ['reason' => $e->getMessage()]);
            }
            throw $e;
        }
        $openaiTimeMs = (microtime(true) - $t0) * 1000;
        if ($reporter) {
            $reporter->emit('openai_embeddings', 'success', 'OpenAI: успешно (' . round($openaiTimeMs, 0) . ' ms)', [
                'ms' => round($openaiTimeMs, 2),
                'dim' => $embeddingMeta['dim'] ?? null,
                'model' => $embeddingMeta['model'] ?? null,
            ]);
        }

        if ($reporter) {
            $reporter->emit('qdrant_search', 'info', 'Qdrant: поиск top-10 (eMall)', []);
        }
        $t1 = microtime(true);
        $rawHits = self::searchQdrantTop10($embeddingVector);
        $qdrantTimeMs = (microtime(true) - $t1) * 1000;

        if (count($rawHits) === 0) {
            if ($reporter) {
                $reporter->emit('pipeline_error', 'error', 'Qdrant вернул 0 результатов', []);
            }
            throw new RuntimeException('NO_CATEGORIES_FOUND');
        }

        if ($reporter) {
            $scores = array_slice(array_map(static function ($h) {
                return round((float) $h['score'], 4);
            }, $rawHits), 0, 5);
            $reporter->emit('qdrant_search', 'success', 'Qdrant: получены top10', ['scores' => $scores]);
        }

        if ($reporter) {
            $reporter->emit('category_lookup', 'info', 'Выбор категории eMall', []);
        }
        $top10 = self::lookupTop10($rawHits);

        if (count($top10) === 0) {
            if ($reporter) {
                $reporter->emit('pipeline_error', 'error', 'No categories found', []);
            }
            throw new RuntimeException('NO_CATEGORIES_FOUND');
        }

        $mapByCategoryId = [];
        foreach ($top10 as $item) {
            $cid = isset($item['category_id']) ? (int) $item['category_id'] : null;
            if ($cid !== null && $cid > 0) {
                $mapByCategoryId[$cid] = $item;
            }
        }
        $fallbackFirst = $top10[0];

        $deepseek = self::chooseWithDeepseek($description, $top10, $locale, $mapByCategoryId);
        $selectedCategoryId = isset($deepseek['selected_category_id']) ? (int) $deepseek['selected_category_id'] : null;
        $selectedRaw = ($selectedCategoryId !== null && isset($mapByCategoryId[$selectedCategoryId]))
            ? $mapByCategoryId[$selectedCategoryId]
            : $fallbackFirst;
        $selected = [
            'category_id' => (int) ($selectedRaw['category_id'] ?? 0),
            'title_cat' => (string) ($selectedRaw['title_cat'] ?? ''),
            'path' => isset($selectedRaw['path']) ? (string) $selectedRaw['path'] : null,
        ];

        if ($reporter) {
            $reporter->emit('pipeline_done', 'success', 'Успешно', []);
        }

        return [
            'embedding' => $embeddingMeta,
            'category_tree' => $categoryTreeText,
            'deepseek_tree' => $treeMeta,
            'top10' => $top10,
            'deepseek' => $deepseek,
            'selected' => $selected,
        ];
    }

    private static function buildEmbedding(string $text, ?ProgressReporterInterface $reporter = null): array
    {
        $apiKey = trim((string) Config::get('OPENAI_API_KEY', ''));
        $model = trim((string) Config::get('GPT_EMBEDDING_MODEL', 'text-embedding-3-small'));
        if ($model === '') {
            $model = 'text-embedding-3-small';
        }

        $proxyAddr = trim((string) Config::get('PROXY_ADDR', ''));
        if ($proxyAddr === '') {
            throw new RuntimeException('OPENAI_REQUIRES_PROXY');
        }
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY_NOT_SET');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('MISSING_EXTENSION_CURL');
        }

        if ($reporter) {
            $reporter->emit('openai_embeddings', 'info', 'OpenAI: отправка запроса embeddings', ['model' => $model, 'proxy' => 'enabled']);
        }

        $url = 'https://api.openai.com/v1/embeddings';
        $body = json_encode(['input' => $text, 'model' => $model]);

        $lastError = null;
        $lastCode = null;
        $lastResponse = null;
        $attempts = 1 + count(self::OPENAI_RETRY_DELAYS_MS);

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_PROXY, $proxyAddr);
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
            $proxyUser = trim((string) Config::get('PROXY_USER', ''));
            $proxyPass = (string) Config::get('PROXY_PASS', '');
            if ($proxyUser !== '') {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyUser . ':' . $proxyPass);
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }

            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            $lastCode = $code;
            $lastResponse = $response;
            $lastError = $err;

            if ($response === false) {
                if ($attempt < $attempts - 1) {
                    usleep(self::OPENAI_RETRY_DELAYS_MS[$attempt] * 1000);
                } else {
                    if ($reporter) {
                        $reporter->emit('openai_embeddings', 'error', 'Ошибка OpenAI embeddings', ['curl' => substr($err, 0, 120), 'http' => null, 'type' => 'OPENAI_CURL_ERROR']);
                    }
                    throw new RuntimeException('OPENAI_CURL_ERROR' . ($err !== '' ? ': ' . substr($err, 0, 120) : ''));
                }
                continue;
            }

            if ($code === 401 || $code === 403 || $code === 400) {
                $msg = 'HTTP_' . $code;
                $dec = json_decode((string) $response, true);
                if (is_array($dec) && isset($dec['error']['message'])) {
                    $msg .= ': ' . substr((string) $dec['error']['message'], 0, 200);
                }
                if ($reporter) {
                    $reporter->emit('openai_embeddings', 'error', 'Ошибка OpenAI embeddings', ['http' => $code, 'curl' => null, 'type' => $msg]);
                }
                throw new RuntimeException($msg);
            }

            if ($code === 429 || ($code >= 500 && $code < 600)) {
                if ($attempt < $attempts - 1) {
                    usleep(self::OPENAI_RETRY_DELAYS_MS[$attempt] * 1000);
                } else {
                    if ($reporter) {
                        $reporter->emit('openai_embeddings', 'error', 'Ошибка OpenAI embeddings', ['http' => $code, 'curl' => null, 'type' => 'OPENAI_HTTP_' . $code]);
                    }
                    throw new RuntimeException('OPENAI_HTTP_' . $code . ': ' . self::truncatePreview((string) $response));
                }
                continue;
            }

            if ($code >= 200 && $code < 300) {
                break;
            }

            if ($reporter) {
                $reporter->emit('openai_embeddings', 'error', 'Ошибка OpenAI embeddings', ['http' => $code, 'curl' => null, 'type' => 'OPENAI_HTTP_' . $code]);
            }
            throw new RuntimeException('OPENAI_HTTP_' . $code);
        }

        $data = json_decode((string) $lastResponse, true);
        if (!is_array($data) || !isset($data['data'][0]['embedding']) || !is_array($data['data'][0]['embedding'])) {
            throw new RuntimeException('OPENAI_EMBEDDING_RESPONSE_INVALID');
        }

        $embedding = $data['data'][0]['embedding'];
        $vector = [];
        foreach ($embedding as $v) {
            $vector[] = (float) $v;
        }
        $dim = count($vector);
        if ($dim !== self::EMBED_DIM) {
            throw new RuntimeException('EMBEDDING_DIM_MISMATCH: expected ' . self::EMBED_DIM . ', got ' . $dim);
        }

        $meta = ['model' => $model, 'dim' => $dim, 'status' => 'ok'];
        return [$vector, $meta];
    }

    private static function buildCategoryTreeWithDeepseek(string $description, string $locale): array
    {
        $meta = [
            'status' => 'skipped',
            'http_status' => null,
            'category_tree' => null,
            'error' => null,
            'raw_preview' => null,
        ];

        $description = trim($description);
        if ($description === '') {
            return $meta;
        }

        $apiUrl = trim((string) Config::get('DEEPSEEK_API_URL', ''));
        $apiKey = trim((string) Config::get('DEEPSEEK_API_KEY', ''));

        if ($apiUrl === '' || $apiKey === '') {
            $meta['status'] = 'skipped';
            $meta['error'] = 'DEEPSEEK_NOT_CONFIGURED';
            return $meta;
        }

        if (!function_exists('curl_init')) {
            $meta['status'] = 'failed';
            $meta['error'] = 'MISSING_EXTENSION_CURL';
            return $meta;
        }

        $languageHint = ($locale === 'ru') ? 'на русском языке' : 'in English';
        $promptLines = [];
        if ($locale === 'ru') {
            $promptLines[] = 'Тебе дано описание товара. Твоя задача — построить дерево категорий eMall для этого товара.';
            $promptLines[] = 'Верни результат только через инструмент build_category_tree.';
            $promptLines[] = '- Значение category_tree — строка из 2–6 уровней категорий, уровни разделяются точкой с запятой ;.';
            $promptLines[] = '- Всё дерево должно быть ' . $languageHint . '.';
            $promptLines[] = '';
            $promptLines[] = 'Описание товара:';
            $promptLines[] = '"' . $description . '"';
        } else {
            $promptLines[] = 'You are given a product description. Build a category tree for eMall. Return only via build_category_tree tool.';
            $promptLines[] = '- category_tree: 2–6 levels separated by semicolons ;.';
            $promptLines[] = '- The whole tree must be ' . $languageHint . '.';
            $promptLines[] = '';
            $promptLines[] = 'Product description:';
            $promptLines[] = '"' . $description . '"';
        }
        $prompt = implode("\n", $promptLines);

        $payload = [
            'model' => 'deepseek-chat',
            'temperature' => 0.0,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an assistant that must build a category_tree string for a product and return it strictly via the build_category_tree tool.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'build_category_tree',
                    'description' => 'Построй дерево категорий eMall.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['category_tree' => ['type' => 'string', 'description' => 'Строка дерева категорий, 2–6 уровней, разделитель ;.']],
                        'required' => ['category_tree'],
                    ],
                ],
            ]],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'build_category_tree']],
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, '');

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        $meta['http_status'] = $code ?: null;
        curl_close($ch);

        if ($response === false || $code < 200 || $code >= 300) {
            $meta['status'] = 'failed';
            $meta['error'] = $response === false ? ('CURL_ERROR' . ($err !== '' ? (': ' . substr($err, 0, 120)) : '')) : ('HTTP_STATUS_' . (int) $code);
            $meta['raw_preview'] = self::truncatePreview((string) $response);
            return $meta;
        }

        $raw = (string) $response;
        $meta['raw_preview'] = self::truncatePreview($raw);
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
            $meta['status'] = 'failed';
            $meta['error'] = 'MISSING_TOOL_CALLS';
            return $meta;
        }

        $argsJson = (string) $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'];
        $parsedArgs = json_decode($argsJson, true);
        if (!is_array($parsedArgs) || !isset($parsedArgs['category_tree'])) {
            $meta['status'] = 'failed';
            $meta['error'] = 'INVALID_TOOL_ARGUMENTS';
            return $meta;
        }

        $tree = self::normalizeCategoryTreeString((string) $parsedArgs['category_tree']);
        if ($tree === '') {
            $meta['status'] = 'failed';
            $meta['error'] = 'EMPTY_CATEGORY_TREE';
            return $meta;
        }

        $meta['status'] = 'called';
        $meta['category_tree'] = $tree;
        return $meta;
    }

    private static function normalizeCategoryTreeString(string $tree): string
    {
        $tree = trim($tree);
        if ($tree === '') return '';
        $tree = str_replace(["\r\n", "\r", "\n"], ' ', $tree);
        $tree = preg_replace('/\s+/', ' ', $tree);
        $tree = str_replace(',', ';', $tree);
        $parts = array_filter(array_map('trim', explode(';', $tree)), 'strlen');
        if (empty($parts)) return '';
        $tree = implode('; ', $parts);
        if (function_exists('mb_substr')) {
            $tree = mb_substr($tree, 0, 300, 'UTF-8');
        } else {
            $tree = substr($tree, 0, 300);
        }
        return trim($tree);
    }

    /**
     * Qdrant search in collection categoryEmall; with_payload true for display.
     *
     * @param array<int,float> $vector
     * @return array<int,array{id_embedding:int,score:float,payload?:array}>
     */
    private static function searchQdrantTop10(array $vector): array
    {
        $baseUrl = rtrim((string) Config::get('QDRANT_URL', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('QDRANT_URL_NOT_CONFIGURED');
        }
        $url = $baseUrl . '/collections/' . self::QDRANT_COLLECTION . '/points/search';

        if (!function_exists('curl_init')) {
            throw new RuntimeException('MISSING_EXTENSION_CURL');
        }

        $payload = [
            'vector' => array_values($vector),
            'limit' => 10,
            'with_payload' => true,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, '');

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('QDRANT_HTTP_ERROR: ' . ($err ?: 'unknown'));
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('QDRANT_HTTP_STATUS_' . $code);
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['result']) || !is_array($data['result'])) {
            throw new RuntimeException('QDRANT_INVALID_RESPONSE');
        }

        $hits = [];
        foreach ($data['result'] as $item) {
            if (!isset($item['id']) || !isset($item['score'])) continue;
            $hit = [
                'id_embedding' => (int) $item['id'],
                'score' => (float) $item['score'],
            ];
            if (isset($item['payload']) && is_array($item['payload'])) {
                $hit['payload'] = $item['payload'];
            }
            $hits[] = $hit;
        }
        return $hits;
    }

    /**
     * Build top10 for UI/DeepSeek: link Qdrant hits to Postgres by payload.emall_id = category_emall.category_id.
     * Qdrant point id is NOT emall_id; payload contains name, path, emall_id.
     *
     * @param array<int,array{id_embedding:int,score:float,payload?:array}> $hits
     * @return array<int,array{category_id:int,title_cat:string,path:string|null,score:float,...}>
     */
    private static function lookupTop10(array $hits): array
    {
        if (count($hits) === 0) return [];

        $fallbackFromPayload = static function (array $h): array {
            $payload = $h['payload'] ?? [];
            $name = (string) ($payload['name'] ?? $payload['title_cat'] ?? $payload['title'] ?? '');
            $path = (string) ($payload['path'] ?? '');
            $emallId = isset($payload['emall_id']) ? (int) $payload['emall_id'] : null;
            return [
                'category_id' => $emallId,
                'title_cat' => $name,
                'path' => $path !== '' ? $path : null,
                'score' => (float) $h['score'],
                'id_type' => isset($payload['id_type']) ? (int) $payload['id_type'] : null,
                'type_name' => (string) ($payload['type_name'] ?? ''),
            ];
        };

        $emallIds = [];
        foreach ($hits as $h) {
            $payload = $h['payload'] ?? [];
            if (isset($payload['emall_id'])) {
                $emallIds[(int) $payload['emall_id']] = true;
            }
        }
        $emallIds = array_keys($emallIds);

        $byCategoryId = [];
        if (count($emallIds) > 0) {
            $pdo = Db::get();
            $tableExists = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'category_emall'")->fetch();
            if ($tableExists) {
                $placeholders = implode(',', array_fill(0, count($emallIds), '?'));
                $sql = 'SELECT category_id, title_cat, path, parent_id, id_type, type_name, disabled, statuse FROM public.category_emall WHERE category_id IN (' . $placeholders . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($emallIds));
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cid = (int) $row['category_id'];
                    $byCategoryId[$cid] = [
                        'category_id' => $cid,
                        'title_cat' => (string) ($row['title_cat'] ?? ''),
                        'path' => isset($row['path']) && $row['path'] !== '' ? (string) $row['path'] : null,
                        'parent_id' => isset($row['parent_id']) ? (int) $row['parent_id'] : null,
                        'id_type' => isset($row['id_type']) ? (int) $row['id_type'] : null,
                        'type_name' => (string) ($row['type_name'] ?? ''),
                        'disabled' => isset($row['disabled']) ? $row['disabled'] : null,
                        'statuse' => isset($row['statuse']) ? (int) $row['statuse'] : null,
                    ];
                }
            }
        }

        $result = [];
        foreach ($hits as $h) {
            $payload = $h['payload'] ?? [];
            $emallId = isset($payload['emall_id']) ? (int) $payload['emall_id'] : null;
            $score = (float) $h['score'];

            if ($emallId !== null && $emallId > 0 && isset($byCategoryId[$emallId])) {
                $row = $byCategoryId[$emallId];
                $result[] = [
                    'category_id' => $emallId,
                    'title_cat' => $row['title_cat'],
                    'path' => $row['path'],
                    'score' => $score,
                    'id_type' => $row['id_type'] ?? null,
                    'type_name' => $row['type_name'] ?? '',
                ];
            } else {
                $fallback = $fallbackFromPayload($h);
                $result[] = [
                    'category_id' => $fallback['category_id'],
                    'title_cat' => $fallback['title_cat'],
                    'path' => $fallback['path'],
                    'score' => $fallback['score'],
                    'id_type' => $fallback['id_type'],
                    'type_name' => $fallback['type_name'],
                ];
            }
        }
        return $result;
    }

    /**
     * DeepSeek chooses best category from top10; returns category_id (emall_id).
     * Only candidates with category_id > 0 are passed to DeepSeek; mapByCategoryId is keyed by category_id.
     */
    private static function chooseWithDeepseek(string $description, array $top10, string $locale, array $mapByCategoryId): array
    {
        $meta = [
            'status' => 'skipped',
            'http_status' => null,
            'selected_category_id' => null,
            'selected_id_type' => null,
            'error' => null,
            'raw_preview' => null,
        ];

        if (count($top10) === 0) {
            throw new RuntimeException('NO_CATEGORIES_FOR_DEEPSEEK');
        }

        $fallback = $top10[0];
        $fallbackCategoryId = isset($fallback['category_id']) ? (int) $fallback['category_id'] : null;

        $apiUrl = trim((string) Config::get('DEEPSEEK_API_URL', ''));
        $apiKey = trim((string) Config::get('DEEPSEEK_API_KEY', ''));

        if ($apiUrl === '' || $apiKey === '') {
            $meta['status'] = 'skipped';
            $meta['error'] = 'DEEPSEEK_NOT_CONFIGURED';
            $meta['selected_category_id'] = $fallbackCategoryId;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        if (!function_exists('curl_init')) {
            $meta['status'] = 'failed';
            $meta['error'] = 'MISSING_EXTENSION_CURL';
            $meta['selected_category_id'] = $fallbackCategoryId;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        $candidatesWithId = [];
        foreach ($top10 as $item) {
            $cid = isset($item['category_id']) ? (int) $item['category_id'] : null;
            if ($cid !== null && $cid > 0) {
                $candidatesWithId[] = $item;
            }
        }
        if (count($candidatesWithId) === 0) {
            $meta['status'] = 'skipped';
            $meta['error'] = 'NO_CATEGORY_IDS_IN_TOP10';
            $meta['selected_category_id'] = $fallbackCategoryId;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        $lines = [];
        $lines[] = $locale === 'ru'
            ? 'У тебя есть описание товара и список категорий eMall. Выбери ОДНУ категорию (category_id = emall_id) из списка.'
            : 'You have a product description and a list of eMall categories. Choose exactly ONE category (category_id = emall_id) from the list.';
        $lines[] = '';
        $lines[] = $locale === 'ru' ? 'Описание товара:' : 'Product description:';
        $lines[] = '"' . $description . '"';
        $lines[] = '';
        $lines[] = $locale === 'ru' ? 'Список категорий (category_id, title_cat, path, score):' : 'Categories (category_id, title_cat, path, score):';
        foreach ($candidatesWithId as $item) {
            $lines[] = sprintf(
                '- category_id=%d, title_cat="%s", path="%s", score=%.5f',
                (int) $item['category_id'],
                (string) ($item['title_cat'] ?? ''),
                (string) ($item['path'] ?? ''),
                (float) ($item['score'] ?? 0)
            );
        }
        $lines[] = '';
        $lines[] = $locale === 'ru'
            ? 'Верни выбранный category_id (emall_id) через инструмент choose_category.'
            : 'Return the chosen category_id (emall_id) via the choose_category tool.';
        $prompt = implode("\n", $lines);

        $payload = [
            'model' => 'deepseek-chat',
            'temperature' => 0.0,
            'messages' => [
                ['role' => 'system', 'content' => 'You must choose exactly one category_id (emall_id) from the provided list using the choose_category tool. Do not invent ids.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'choose_category',
                    'description' => 'Выбери одну категорию eMall (category_id = emall_id).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['category_id' => ['type' => 'integer', 'description' => 'category_id (emall_id) из списка top10.']],
                        'required' => ['category_id'],
                    ],
                ],
            ]],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'choose_category']],
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, '');

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        $meta['http_status'] = $code ?: null;
        curl_close($ch);

        if ($response === false || $code < 200 || $code >= 300) {
            $meta['status'] = 'failed';
            $meta['error'] = $response === false ? ('CURL_ERROR' . ($err !== '' ? (': ' . substr($err, 0, 120)) : '')) : ('HTTP_STATUS_' . (int) $code);
            $meta['selected_category_id'] = $fallbackCategoryId;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            $meta['raw_preview'] = self::truncatePreview((string) $response);
            return $meta;
        }

        $raw = (string) $response;
        $meta['raw_preview'] = self::truncatePreview($raw);
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
            $meta['status'] = 'failed';
            $meta['error'] = 'MISSING_TOOL_CALLS';
            $meta['selected_category_id'] = $fallbackCategoryId;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        $argsJson = (string) $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'];
        $parsedArgs = json_decode($argsJson, true);
        if (!is_array($parsedArgs) || !isset($parsedArgs['category_id'])) {
            $meta['status'] = 'failed';
            $meta['error'] = 'INVALID_TOOL_ARGUMENTS';
            $meta['selected_category_id'] = $fallbackCategoryId;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        $id = (int) $parsedArgs['category_id'];
        if ($id <= 0 || !isset($mapByCategoryId[$id])) {
            $meta['status'] = 'failed';
            $meta['error'] = 'CATEGORY_ID_NOT_IN_TOP10';
            $meta['selected_category_id'] = $fallbackCategoryId;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        $chosen = $mapByCategoryId[$id];
        $meta['status'] = 'called';
        $meta['selected_category_id'] = (int) $chosen['category_id'];
        $meta['selected_id_type'] = isset($chosen['id_type']) ? (int) $chosen['id_type'] : null;
        return $meta;
    }

    private static function truncatePreview(string $raw): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($raw, 0, 1500, 'UTF-8');
        }
        return substr($raw, 0, 1500);
    }
}
