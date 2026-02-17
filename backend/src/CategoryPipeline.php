<?php

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/ProgressReporter.php';

/**
 * Category detection pipeline for Ozon.
 *
 * Steps:
 *  0) DeepSeek builds a category tree string (optional, with graceful fallback).
 *  A) Build embedding for text used in search (stubbed, OpenAI‑compatible shape).
 *  B) Qdrant search (collection: category_ozon, top10 by vector similarity).
 *  C) PostgreSQL lookup in public.category_ozon by id_embedding.
 *  D) DeepSeek chooses 1 best category (returns JSON {"id": <db_id>}).
 */
class CategoryPipeline
{
    /** Dimension of embedding vector used in Qdrant. */
    private const EMBED_DIM = 1536;

    /**
     * Run full category pipeline and return structured result.
     *
     * @param string $description
     * @param string $locale
     * @return array{
     *   embedding: array{model:string,dim:int,status:string},
     *   category_tree: string,
     *   deepseek_tree: array{
     *     status:string,
     *     http_status:int|null,
     *     category_tree:?string,
     *     error:?string,
     *     raw_preview:?string
     *   },
     *   top10: array<int,array{
     *     db_id:int,
     *     id_embedding:int,
     *     title_cat:string,
     *     type_name:string,
     *     score:float,
     *     category_id:int|null,
     *     id_type:int|null
     *   }>,
     *   deepseek: array{
     *     status:string,
     *     http_status:int|null,
     *     selected_db_id:int,
     *     selected_category_id:int|null,
     *     selected_id_type:int|null,
     *     error:?string,
     *     raw_preview:?string
     *   },
     *   selected: array{
     *     db_id:int,
     *     id_embedding:int,
     *     title_cat:string,
     *     type_name:string,
     *     score:float,
     *     category_id:int|null,
     *     id_type:int|null
     *   }
     * }
     *
     * @param string|null $jobId If set, progress events are written to runtime/progress/<job_id>.jsonl for SSE UI.
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

        // Step 0 — optional DeepSeek category tree (with graceful fallback).
        if ($reporter) {
            $reporter->emit('embedding_prepare', 'info', 'Embedding: подготовка текста', []);
        }
        $treeMeta = self::buildCategoryTreeWithDeepseek($description, $locale);
        $categoryTreeText = isset($treeMeta['category_tree']) && is_string($treeMeta['category_tree']) && $treeMeta['category_tree'] !== ''
            ? $treeMeta['category_tree']
            : $description;

        // Step A — Embedding via OpenAI (with proxy). Reporter emits openai_embeddings steps.
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
            $reporter->emit('openai_embeddings', 'success', 'OpenAI: успешно (' . round($openaiTimeMs, 0) . ' ms, ' . ($embeddingMeta['model'] ?? '') . ')', [
                'ms' => round($openaiTimeMs, 2),
                'dim' => $embeddingMeta['dim'] ?? null,
                'model' => $embeddingMeta['model'] ?? null,
            ]);
        }

        // Step B — Qdrant search (top10 by vector). No score_threshold; return all 10 nearest.
        if ($reporter) {
            $reporter->emit('qdrant_search', 'info', 'Qdrant: поиск top-10', []);
        }
        $t1 = microtime(true);
        $rawHits = self::searchQdrantTop10($embeddingVector);
        $qdrantTimeMs = (microtime(true) - $t1) * 1000;

        if (count($rawHits) === 0) {
            self::logNoCategoriesFound($rawHits, count($embeddingVector), $embeddingMeta['model']);
            if ($reporter) {
                $reporter->emit('pipeline_error', 'error', 'Qdrant вернул 0 результатов', []);
            }
            throw new RuntimeException('NO_CATEGORIES_FOUND');
        }

        $qdrantHits = $rawHits;

        if ($reporter) {
            $scores = array_slice(array_map(static function ($h) {
                return round((float) $h['score'], 4);
            }, $qdrantHits), 0, 5);
            $reporter->emit('qdrant_search', 'success', 'Qdrant: получены top10', ['scores' => $scores]);
        }

        if (filter_var(Config::get('APP_DEBUG', ''), FILTER_VALIDATE_BOOLEAN)) {
            self::logEmbeddingDebug($categoryTreeText, $rawHits, $openaiTimeMs, $qdrantTimeMs, $embeddingMeta['model']);
        }

        // Step C — Postgres lookup of category names and ids.
        if ($reporter) {
            $reporter->emit('category_lookup', 'info', 'Выбор категории / получение свойств категории', []);
        }
        $top10 = self::lookupCategories($qdrantHits);
        if (count($top10) === 0) {
            if ($reporter) {
                $reporter->emit('pipeline_error', 'error', 'No categories found', []);
            }
            throw new RuntimeException('NO_CATEGORIES_FOUND');
        }

        $mapByDbId = [];
        foreach ($top10 as $item) {
            $mapByDbId[(int) $item['db_id']] = $item;
        }

        // Step D — DeepSeek picks 1 best category (with transparent status).
        $deepseek = self::chooseWithDeepseek($description, $top10, $locale, $mapByDbId);
        $selectedDbId = (int) $deepseek['selected_db_id'];
        $selected = $mapByDbId[$selectedDbId] ?? $top10[0];

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

    /** Backoff delays for OpenAI embedding retries (ms). */
    private const OPENAI_RETRY_DELAYS_MS = [300, 800, 1500, 3000];

    /**
     * Step A — build embedding via OpenAI embeddings API.
     *
     * Uses proxy (PROXY_*) when configured. Retries on network errors, 429, 5xx.
     * Never retries on 401/403/400.
     *
     * @param ProgressReporterInterface|null $reporter Optional progress reporter for UI (no secrets in meta).
     * @return array{0:array<int,float>,1:array{model:string,dim:int,status:string}}
     */
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
            $reporter->emit('openai_embeddings', 'info', 'OpenAI: отправка запроса embeddings', [
                'model' => $model,
                'proxy' => 'enabled',
            ]);
        }

        $url = 'https://api.openai.com/v1/embeddings';
        $body = json_encode([
            'input' => $text,
            'model' => $model,
        ]);

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
                        $reporter->emit('openai_embeddings', 'error', 'Ошибка OpenAI embeddings', [
                            'curl' => substr($err, 0, 120),
                            'http' => null,
                            'type' => 'OPENAI_CURL_ERROR',
                        ]);
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
                    $reporter->emit('openai_embeddings', 'error', 'Ошибка OpenAI embeddings', [
                        'http' => $code,
                        'curl' => null,
                        'type' => $msg,
                    ]);
                }
                throw new RuntimeException($msg);
            }

            if ($code === 429 || ($code >= 500 && $code < 600)) {
                if ($attempt < $attempts - 1) {
                    usleep(self::OPENAI_RETRY_DELAYS_MS[$attempt] * 1000);
                } else {
                    if ($reporter) {
                        $reporter->emit('openai_embeddings', 'error', 'Ошибка OpenAI embeddings', [
                            'http' => $code,
                            'curl' => null,
                            'type' => 'OPENAI_HTTP_' . $code,
                        ]);
                    }
                    throw new RuntimeException('OPENAI_HTTP_' . $code . ': ' . self::truncatePreview((string) $response));
                }
                continue;
            }

            if ($code >= 200 && $code < 300) {
                break;
            }

            if ($reporter) {
                $reporter->emit('openai_embeddings', 'error', 'Ошибка OpenAI embeddings', [
                    'http' => $code,
                    'curl' => null,
                    'type' => 'OPENAI_HTTP_' . $code,
                ]);
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

        $meta = [
            'model' => $model,
            'dim' => $dim,
            'status' => 'ok',
        ];

        return [$vector, $meta];
    }

    /**
     * Optional Step 0 — DeepSeek builds a category tree string for embedding.
     *
     * Returns structured metadata and never throws; callers must handle fallback.
     *
     * @return array{
     *   status:string,
     *   http_status:int|null,
     *   category_tree:?string,
     *   error:?string,
     *   raw_preview:?string
     * }
     */
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
            // DeepSeek not configured — mark as skipped, pipeline will fallback to description.
            $meta['status'] = 'skipped';
            $meta['error'] = 'DEEPSEEK_NOT_CONFIGURED';
            return $meta;
        }

        if (!function_exists('curl_init')) {
            // Cannot call DeepSeek — mark as failed, pipeline will fallback to description.
            $meta['status'] = 'failed';
            $meta['error'] = 'MISSING_EXTENSION_CURL';
            return $meta;
        }

        $model = 'deepseek-chat';

        $languageHint = ($locale === 'ru') ? 'на русском языке' : 'in English';

        $promptLines = [];
        if ($locale === 'ru') {
            $promptLines[] = 'Тебе дано описание товара. Твоя задача — построить дерево категорий Ozon для этого товара.';
            $promptLines[] = 'Верни результат только через инструмент build_category_tree.';
            $promptLines[] = 'Правила:';
            $promptLines[] = '- Значение category_tree — строка из 2–6 уровней категорий.';
            $promptLines[] = '- Уровни разделяются точкой с запятой ;, без других разделителей.';
            $promptLines[] = '- Без лишнего текста, без markdown, без пояснений.';
            $promptLines[] = '- Всё дерево должно быть ' . $languageHint . '.';
            $promptLines[] = '';
            $promptLines[] = 'Примеры формата:';
            $promptLines[] = '- "Электроника; Смарт-часы; Спортивные; GPS"';
            $promptLines[] = '- "Одежда; Женская одежда; Платья"';
            $promptLines[] = '';
            $promptLines[] = 'Описание товара:';
            $promptLines[] = '"' . $description . '"';
        } else {
            $promptLines[] = 'You are given a product description. Your task is to build a category tree for this product.';
            $promptLines[] = 'Return the result only via the build_category_tree tool.';
            $promptLines[] = 'Rules:';
            $promptLines[] = '- The category_tree value must be a string of 2–6 category levels.';
            $promptLines[] = '- Levels are separated by semicolons ; and no other delimiters.';
            $promptLines[] = '- No extra text, no markdown, no explanations.';
            $promptLines[] = '- The whole tree must be ' . $languageHint . '.';
            $promptLines[] = '';
            $promptLines[] = 'Examples of valid format:';
            $promptLines[] = '- "Electronics; Smart watches; Sport; GPS"';
            $promptLines[] = '- "Clothing; Women\'s clothing; Dresses"';
            $promptLines[] = '';
            $promptLines[] = 'Product description:';
            $promptLines[] = '"' . $description . '"';
        }

        $prompt = implode("\n", $promptLines);

        $payload = [
            'model' => $model,
            'temperature' => 0.0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an assistant that must build a category_tree string for a product and return it strictly via the build_category_tree tool.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'tools' => [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'build_category_tree',
                        'description' => 'Построй дерево категорий для товара и верни его в поле category_tree.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'category_tree' => [
                                    'type' => 'string',
                                    'description' => 'Строка дерева категорий вида "Основная; Подкатегория1; Подкатегория2", 2–6 уровней, разделитель ;.',
                                ],
                            ],
                            'required' => ['category_tree'],
                        ],
                    ],
                ],
            ],
            'tool_choice' => [
                'type' => 'function',
                'function' => [
                    'name' => 'build_category_tree',
                ],
            ],
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        // DeepSeek: no proxy (proxy is used only for OpenAI in buildEmbedding).
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, '');

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        $meta['http_status'] = $code ?: null;
        curl_close($ch);

        if ($response === false || $code < 200 || $code >= 300) {
            // Do not fail the whole pipeline, just fallback to description.
            $meta['status'] = 'failed';
            $meta['error'] = $response === false
                ? ('CURL_ERROR' . ($err !== '' ? (': ' . substr($err, 0, 120)) : ''))
                : ('HTTP_STATUS_' . (int) $code);
            $meta['raw_preview'] = self::truncatePreview((string) $response);
            return $meta;
        }

        $raw = (string) $response;
        $meta['raw_preview'] = self::truncatePreview($raw);

        $data = json_decode($raw, true);
        if (!is_array($data)
            || !isset($data['choices'][0]['message']['tool_calls'][0]['function']['arguments'])
        ) {
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

        $tree = (string) $parsedArgs['category_tree'];
        $tree = self::normalizeCategoryTreeString($tree);
        if ($tree === '') {
            $meta['status'] = 'failed';
            $meta['error'] = 'EMPTY_CATEGORY_TREE';
            return $meta;
        }

        $meta['status'] = 'called';
        $meta['category_tree'] = $tree;

        return $meta;
    }

    /**
     * Normalize category_tree string:
     * - trim and collapse whitespace
     * - replace newlines with spaces
     * - use semicolon as a strict separator
     * - limit length to ~300 characters
     */
    private static function normalizeCategoryTreeString(string $tree): string
    {
        $tree = trim($tree);
        if ($tree === '') {
            return '';
        }

        // Replace newlines with spaces.
        $tree = str_replace(["\r\n", "\r", "\n"], ' ', $tree);
        // Collapse excessive whitespace.
        $tree = preg_replace('/\s+/', ' ', $tree);

        // Replace commas with semicolons, then normalize separators.
        $tree = str_replace(',', ';', $tree);

        $parts = array_filter(array_map('trim', explode(';', $tree)), 'strlen');
        if (empty($parts)) {
            return '';
        }

        $tree = implode('; ', $parts);

        // Limit length to 300 characters.
        if (function_exists('mb_substr')) {
            $tree = mb_substr($tree, 0, 300, 'UTF-8');
        } else {
            $tree = substr($tree, 0, 300);
        }

        return trim($tree);
    }

    /**
     * Step B — Qdrant search in collection "category_ozon".
     *
     * @param array<int,float> $vector
     * @return array<int,array{id_embedding:int,score:float}>
     */
    private static function searchQdrantTop10(array $vector): array
    {
        $baseUrl = rtrim((string) Config::get('QDRANT_URL', ''), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('QDRANT_URL_NOT_CONFIGURED');
        }
        $url = $baseUrl . '/collections/category_ozon/points/search';

        if (!function_exists('curl_init')) {
            throw new RuntimeException('MISSING_EXTENSION_CURL');
        }

        $payload = [
            'vector' => array_values($vector),
            'limit' => 10,
            'with_payload' => false,
        ];

        // Qdrant must never use proxy (proxy is only for OpenAI). Explicitly disable.
        if (filter_var(Config::get('APP_DEBUG', ''), FILTER_VALIDATE_BOOLEAN)) {
            $proxyAddr = trim((string) Config::get('PROXY_ADDR', ''));
            if ($proxyAddr !== '') {
                error_log('[CategoryPipeline] Qdrant request must not use proxy; proxy is configured for OpenAI only. This request uses no proxy.');
            }
        }

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
            if (!isset($item['id']) || !isset($item['score'])) {
                continue;
            }
            $hits[] = [
                'id_embedding' => (int) $item['id'],
                'score' => (float) $item['score'],
            ];
        }

        return $hits;
    }

    /**
     * Step C — lookup human‑readable category names in Postgres.
     *
     * @param array<int,array{id_embedding:int,score:float}> $hits
     * @return array<int,array{
     *   db_id:int,
     *   id_embedding:int,
     *   title_cat:string,
     *   type_name:string,
     *   score:float,
     *   category_id:int|null,
     *   id_type:int|null
     * }>
     */
    private static function lookupCategories(array $hits): array
    {
        if (count($hits) === 0) {
            return [];
        }
        $ids = [];
        foreach ($hits as $h) {
            $ids[] = (int) $h['id_embedding'];
        }
        $ids = array_values(array_unique($ids));

        $pdo = Db::get();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT id, title_cat, type_name, category_id, id_type, id_embedding
                FROM public.category_ozon
                WHERE id_embedding IN (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);

        $byIdEmbedding = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = (string) ((int) $row['id_embedding']);
            $byIdEmbedding[$key] = [
                'db_id' => (int) $row['id'],
                'id_embedding' => (int) $row['id_embedding'],
                'title_cat' => (string) $row['title_cat'],
                'type_name' => (string) $row['type_name'],
                'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
                'id_type' => isset($row['id_type']) ? (int) $row['id_type'] : null,
            ];
        }

        $result = [];
        foreach ($hits as $h) {
            $key = (string) ((int) $h['id_embedding']);
            if (!isset($byIdEmbedding[$key])) {
                continue;
            }
            $base = $byIdEmbedding[$key];
            $base['score'] = (float) $h['score'];
            $result[] = $base;
        }

        return $result;
    }

    /**
     * Step D — DeepSeek chooses the best category.
     *
     * @param string $description
     * @param array<int,array{
     *   db_id:int,
     *   id_embedding:int,
     *   title_cat:string,
     *   type_name:string,
     *   score:float,
     *   category_id:int|null,
     *   id_type:int|null
     * }> $top10
     * @param string $locale
     * @param array<int,array{
     *   db_id:int,
     *   id_embedding:int,
     *   title_cat:string,
     *   type_name:string,
     *   score:float,
     *   category_id:int|null,
     *   id_type:int|null
     * }> $mapByDbId keyed by db_id
     * @return array{
     *   status:string,
     *   http_status:int|null,
     *   selected_db_id:int,
     *   selected_category_id:int|null,
     *   selected_id_type:int|null,
     *   error:?string,
     *   raw_preview:?string
     * }
     */
    private static function chooseWithDeepseek(string $description, array $top10, string $locale, array $mapByDbId): array
    {
        // Initialize metadata structure.
        $meta = [
            'status' => 'skipped',
            'http_status' => null,
            'selected_db_id' => 0,
            'selected_category_id' => null,
            'selected_id_type' => null,
            'error' => null,
            'raw_preview' => null,
        ];

        if (count($top10) === 0) {
            throw new RuntimeException('NO_CATEGORIES_FOR_DEEPSEEK');
        }

        // Always have a deterministic fallback: top1 by score.
        $fallback = $top10[0];

        $apiUrl = trim((string) Config::get('DEEPSEEK_API_URL', ''));
        $apiKey = trim((string) Config::get('DEEPSEEK_API_KEY', ''));

        if ($apiUrl === '' || $apiKey === '') {
            // DeepSeek not configured — mark as skipped, fallback to top1.
            $meta['status'] = 'skipped';
            $meta['error'] = 'DEEPSEEK_NOT_CONFIGURED';
            $meta['selected_db_id'] = (int) $fallback['db_id'];
            $meta['selected_category_id'] = isset($fallback['category_id']) ? (int) $fallback['category_id'] : null;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        if (!function_exists('curl_init')) {
            // Cannot call DeepSeek — mark as failed and fallback to top1.
            $meta['status'] = 'failed';
            $meta['error'] = 'MISSING_EXTENSION_CURL';
            $meta['selected_db_id'] = (int) $fallback['db_id'];
            $meta['selected_category_id'] = isset($fallback['category_id']) ? (int) $fallback['category_id'] : null;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        $model = 'deepseek-chat';

        $prompt = self::buildDeepseekPrompt($description, $top10, $locale);

        $payload = [
            'model' => $model,
            'temperature' => 0.0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an assistant that must choose exactly one db_id from the provided list of categories using the choose_category tool. '
                        . 'Do not invent ids. If the description does not match anything, still pick the closest one.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'tools' => [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'choose_category',
                        'description' => 'Выбери одну категорию (db_id) из списка top10 категорий Ozon.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'db_id' => [
                                    'type' => 'integer',
                                    'description' => 'db_id выбранной строки public.category_ozon.id из предложенного списка top10.',
                                ],
                            ],
                            'required' => ['db_id'],
                        ],
                    ],
                ],
            ],
            'tool_choice' => [
                'type' => 'function',
                'function' => [
                    'name' => 'choose_category',
                ],
            ],
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        // DeepSeek: no proxy (proxy is used only for OpenAI in buildEmbedding).
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, '');

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        $meta['http_status'] = $code ?: null;
        curl_close($ch);

        if ($response === false || $code < 200 || $code >= 300) {
            // Do not fail the whole pipeline, just fallback to top1.
            $meta['status'] = 'failed';
            $meta['error'] = $response === false
                ? ('CURL_ERROR' . ($err !== '' ? (': ' . substr($err, 0, 120)) : ''))
                : ('HTTP_STATUS_' . (int) $code);
            $meta['selected_db_id'] = (int) $fallback['db_id'];
            $meta['selected_category_id'] = isset($fallback['category_id']) ? (int) $fallback['category_id'] : null;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            $meta['raw_preview'] = self::truncatePreview((string) $response);
            return $meta;
        }

        $raw = (string) $response;
        $meta['raw_preview'] = self::truncatePreview($raw);

        $data = json_decode($raw, true);
        if (!is_array($data)
            || !isset($data['choices'][0]['message']['tool_calls'][0]['function']['arguments'])
        ) {
            $meta['status'] = 'failed';
            $meta['error'] = 'MISSING_TOOL_CALLS';
            $meta['selected_db_id'] = (int) $fallback['db_id'];
            $meta['selected_category_id'] = isset($fallback['category_id']) ? (int) $fallback['category_id'] : null;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        $argsJson = (string) $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'];
        $parsedArgs = json_decode($argsJson, true);
        if (!is_array($parsedArgs) || !isset($parsedArgs['db_id'])) {
            $meta['status'] = 'failed';
            $meta['error'] = 'INVALID_TOOL_ARGUMENTS';
            $meta['selected_db_id'] = (int) $fallback['db_id'];
            $meta['selected_category_id'] = isset($fallback['category_id']) ? (int) $fallback['category_id'] : null;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        $id = (int) $parsedArgs['db_id'];
        if ($id <= 0 || !isset($mapByDbId[$id])) {
            $meta['status'] = 'failed';
            $meta['error'] = 'DB_ID_NOT_IN_TOP10';
            $meta['selected_db_id'] = (int) $fallback['db_id'];
            $meta['selected_category_id'] = isset($fallback['category_id']) ? (int) $fallback['category_id'] : null;
            $meta['selected_id_type'] = isset($fallback['id_type']) ? (int) $fallback['id_type'] : null;
            return $meta;
        }

        $chosen = $mapByDbId[$id];
        $meta['status'] = 'called';
        $meta['selected_db_id'] = (int) $chosen['db_id'];
        $meta['selected_category_id'] = isset($chosen['category_id']) ? (int) $chosen['category_id'] : null;
        $meta['selected_id_type'] = isset($chosen['id_type']) ? (int) $chosen['id_type'] : null;

        return $meta;
    }

    /**
     * Build user prompt for DeepSeek with description + list of 10 candidates.
     *
     * @param string $description
     * @param array<int,array{
     *   db_id:int,
     *   id_embedding:int,
     *   title_cat:string,
     *   type_name:string,
     *   score:float,
     *   category_id:int|null,
     *   id_type:int|null
     * }> $top10
     */
    private static function buildDeepseekPrompt(string $description, array $top10, string $locale): string
    {
        $lines = [];
        $lines[] = 'У тебя есть описание товара и список из 10 категорий Ozon.';
        $lines[] = '';
        $lines[] = 'Описание товара:';
        $lines[] = '"' . $description . '"';
        $lines[] = '';
        $lines[] = 'Список категорий (db_id, category_id, id_type, title_cat, type_name, score):';
        foreach ($top10 as $item) {
            $lines[] = sprintf(
                '- db_id=%d, category_id=%s, id_type=%s, title_cat="%s", type_name="%s", score=%.5f',
                (int) $item['db_id'],
                isset($item['category_id']) && $item['category_id'] !== null ? (string) (int) $item['category_id'] : 'null',
                isset($item['id_type']) && $item['id_type'] !== null ? (string) (int) $item['id_type'] : 'null',
                (string) $item['title_cat'],
                (string) $item['type_name'],
                isset($item['score']) ? (float) $item['score'] : 0.0
            );
        }
        $lines[] = '';
        $lines[] = 'Задача: выбери ОДНУ категорию, которая лучше всего подходит этому товару.';
        $lines[] = 'Ты обязан выбрать ровно один db_id из списка выше. Не придумывай новые значения.';
        $lines[] = 'Используй инструмент choose_category, чтобы вернуть выбранный db_id.';

        return implode("\n", $lines);
    }

    /**
     * Log when Qdrant returned 0 results (no secrets).
     *
     * @param array<int,array{id_embedding:int,score:float}> $rawHits
     */
    private static function logNoCategoriesFound(array $rawHits, int $vectorLen, string $model): void
    {
        $scores = [];
        foreach (array_slice($rawHits, 0, 10) as $h) {
            $scores[] = ['id_embedding' => (int) $h['id_embedding'], 'score' => (float) $h['score']];
        }
        error_log('[CategoryPipeline] NO_CATEGORIES_FOUND: vector_len=' . $vectorLen
            . ', model=' . $model
            . ', top_scores=' . json_encode($scores));
    }

    /**
     * Debug log for embedding step (APP_DEBUG only). No secrets.
     *
     * @param array<int,array{id_embedding:int,score:float}> $rawHits
     */
    private static function logEmbeddingDebug(string $text, array $rawHits, float $openaiTimeMs, float $qdrantTimeMs, string $model): void
    {
        $snippet = $text;
        if (function_exists('mb_strlen') && mb_strlen($snippet) > 120) {
            $snippet = mb_substr($snippet, 0, 120, 'UTF-8') . '…';
        } elseif (strlen($snippet) > 120) {
            $snippet = substr($snippet, 0, 120) . '…';
        }
        $topScores = [];
        foreach (array_slice($rawHits, 0, 5) as $h) {
            $topScores[] = ['id_embedding' => (int) $h['id_embedding'], 'score' => (float) $h['score']];
        }
        error_log('[CategoryPipeline] DEBUG embedding: text_snippet=' . json_encode($snippet)
            . ', top_5_scores=' . json_encode($topScores)
            . ', openai_ms=' . round($openaiTimeMs, 2)
            . ', qdrant_ms=' . round($qdrantTimeMs, 2)
            . ', model=' . $model);
    }

    /**
     * Truncate raw model response for safe preview (no secrets, max ~1500 chars).
     */
    private static function truncatePreview(string $raw): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($raw, 0, 1500, 'UTF-8');
        }
        return substr($raw, 0, 1500);
    }
}

