<?php

require_once __DIR__ . '/RouteHelpers.php';
require_once __DIR__ . '/../../src/DraftRepository.php';
require_once __DIR__ . '/../../src/EmallTextGenService.php';
require_once __DIR__ . '/../../src/EmallDimensionsService.php';
require_once __DIR__ . '/../../src/EmallBrandCountryService.php';
require_once __DIR__ . '/../../src/EmallPropertiesService.php';
require_once __DIR__ . '/../../src/EmallPayloadService.php';
require_once __DIR__ . '/../../src/EmallSubmitService.php';
require_once __DIR__ . '/../../src/EmallApiClient.php';
require_once __DIR__ . '/../../src/EmallCredentialsRepository.php';

class EmallDraftRoutes
{
    public static function handle($method, $path, $locale): bool
    {
        // POST /emall/drafts/{draftId}/generate-text
        if ($method === 'POST' && preg_match('#^/emall/drafts/([^/]+)/generate-text$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];

            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }

            $marketplace = DraftRepository::getMarketplaceForDraft($uid, $draftId);
            if ($marketplace !== 'emall') {
                fail('MARKETPLACE_MISMATCH', 'Draft is not eMall', [], 400);
            }

            $sourceText = (string) ($draft['editedJson']['origText'] ?? $draft['editedJson']['description'] ?? $draft['description'] ?? '');
            $sourceText = trim($sourceText);
            if ($sourceText === '') {
                fail('SOURCE_TEXT_EMPTY', 'Enter product description on step 1 first', []);
            }

            try {
                $result = EmallTextGenService::generate($sourceText, $locale);
            } catch (RuntimeException $e) {
                $code = $e->getMessage();
                if (strpos($code, 'DEEPSEEK') !== false || strpos($code, 'invalid lengths') !== false) {
                    fail('GENERATE_FAILED', $e->getMessage(), [], 400);
                }
                fail('GENERATE_FAILED', 'Text generation failed', ['reason' => $e->getMessage()], 400);
            }

            $model = 'deepseek-chat';
            DraftRepository::updateEmallGeneratedText($draftId, $uid, $result, $model, $sourceText);

            Response::success([
                'title' => $result['title'],
                'description' => $result['description'],
                'benefits' => $result['benefits'],
                'usage' => $result['usage'],
            ]);
            return true;
        }

        // PUT or POST /emall/drafts/{draftId}/text (POST avoids nginx 406 on PUT)
        if (($method === 'PUT' || $method === 'POST') && preg_match('#^/emall/drafts/([^/]+)/text$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];

            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }

            $marketplace = DraftRepository::getMarketplaceForDraft($uid, $draftId);
            if ($marketplace !== 'emall') {
                fail('MARKETPLACE_MISMATCH', 'Draft is not eMall', [], 400);
            }

            $body = jsonBody() ?: [];
            $text = [
                'title' => $body['title'] ?? '',
                'description' => $body['description'] ?? '',
                'benefits' => $body['benefits'] ?? '',
                'usage' => $body['usage'] ?? '',
            ];

            try {
                $validated = EmallTextGenService::validateUserText($text);
            } catch (RuntimeException $e) {
                fail('VALIDATION_ERROR', $e->getMessage(), [], 400);
            }

            DraftRepository::updateEmallEditedText($draftId, $uid, $validated);

            Response::success(['ok' => true]);
            return true;
        }

        // POST /emall/drafts/{draftId}/dimensions — predict dimensions and weight via DeepSeek
        if ($method === 'POST' && preg_match('#^/emall/drafts/([^/]+)/dimensions$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];

            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }

            $marketplace = DraftRepository::getMarketplaceForDraft($uid, $draftId);
            if ($marketplace !== 'emall') {
                fail('MARKETPLACE_MISMATCH', 'Draft is not eMall', [], 400);
            }

            $title = '';
            $description = trim((string) ($draft['editedJson']['description'] ?? $draft['description'] ?? ''));
            $filledRaw = $draft['filled_fields'] ?? null;
            $filled = is_array($filledRaw) ? $filledRaw : (is_string($filledRaw) ? (json_decode($filledRaw, true) ?: []) : []);
            $stepState = $draft['step_state'] ?? [];
            $stepState = is_array($stepState) ? $stepState : [];

            $editedText = $filled['edited_text'] ?? $filled['generated_text'] ?? [];
            if (is_array($editedText)) {
                $title = trim((string) ($editedText['title'] ?? ''));
                if ($description === '' && (isset($editedText['description']) || isset($editedText['benefits']) || isset($editedText['usage']))) {
                    $parts = array_filter([
                        (string) ($editedText['description'] ?? ''),
                        (string) ($editedText['benefits'] ?? ''),
                        (string) ($editedText['usage'] ?? ''),
                    ]);
                    $description = implode("\n\n", $parts);
                }
            }
            $genText = $filled['generated_text'] ?? $stepState['generated_text'] ?? [];
            if (is_array($genText)) {
                if ($title === '') {
                    $title = trim((string) ($genText['title'] ?? ''));
                }
                if ($description === '' && (isset($genText['description']) || isset($genText['benefits']) || isset($genText['usage']))) {
                    $parts = array_filter([
                        (string) ($genText['description'] ?? ''),
                        (string) ($genText['benefits'] ?? ''),
                        (string) ($genText['usage'] ?? ''),
                    ]);
                    $description = implode("\n\n", $parts);
                }
            }
            if ($description === '') {
                $description = trim((string) ($draft['editedJson']['description'] ?? $draft['description'] ?? ''));
            }

            if ($title === '' && $description === '') {
                fail('SOURCE_EMPTY', 'Enter product description and complete step 2 first', []);
            }

            try {
                $result = EmallDimensionsService::predict($title, $description, $locale);
            } catch (RuntimeException $e) {
                $code = $e->getMessage();
                if (strpos($code, 'DEEPSEEK') !== false || strpos($code, 'TITLE_OR_DESCRIPTION') !== false) {
                    fail('DIMENSIONS_FAILED', $e->getMessage(), [], 400);
                }
                fail('DIMENSIONS_FAILED', 'Could not predict dimensions', ['reason' => $e->getMessage()], 400);
            }

            DraftRepository::mergeStepStateForDraft($draftId, $uid, ['dimensions' => $result]);

            Response::success($result);
            return true;
        }

        // POST /emall/drafts/{draftId}/brand-country — predict brand and country via DeepSeek
        if ($method === 'POST' && preg_match('#^/emall/drafts/([^/]+)/brand-country$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];

            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }

            $marketplace = DraftRepository::getMarketplaceForDraft($uid, $draftId);
            if ($marketplace !== 'emall') {
                fail('MARKETPLACE_MISMATCH', 'Draft is not eMall', [], 400);
            }

            $title = '';
            $description = '';
            $filledRaw = $draft['filled_fields'] ?? null;
            $filled = is_array($filledRaw) ? $filledRaw : (is_string($filledRaw) ? (json_decode($filledRaw, true) ?: []) : []);
            $stepState = is_array($draft['step_state'] ?? []) ? $draft['step_state'] : [];

            $genText = $filled['generated_text'] ?? $filled['edited_text'] ?? $stepState['generated_text'] ?? [];
            if (is_array($genText)) {
                $title = trim((string) ($genText['title'] ?? ''));
                $parts = array_filter([
                    (string) ($genText['description'] ?? ''),
                    (string) ($genText['benefits'] ?? ''),
                    (string) ($genText['usage'] ?? ''),
                ]);
                $description = implode("\n\n", $parts);
            }
            if ($description === '') {
                $description = trim((string) ($draft['editedJson']['description'] ?? $draft['description'] ?? ''));
            }
            if ($title === '' && $description === '') {
                fail('SOURCE_EMPTY', 'Complete step 2 first', []);
            }

            try {
                $result = EmallBrandCountryService::predict($title, $description, $locale);
                // Resolve predicted brand/country against eMall directories (IDs) so UI can auto-select.
                $apiKey = self::getEmallApiKey($uid, $draftId);
                $resolvedBrand = null;
                $resolvedCountry = null;
                if ($apiKey) {
                    try {
                        $client = new EmallApiClient($apiKey);

                        // --- Countries (small directory)
                        $countriesAll = $client->fetchAllCountries();
                        $predCountry = isset($result['country']) ? trim((string)$result['country']) : '';
                        if ($predCountry !== '' && is_array($countriesAll)) {
                            $qLower = mb_strtolower($predCountry);
                            foreach ($countriesAll as $c) {
                                $name = isset($c['name']) ? (string)$c['name'] : '';
                                if ($name !== '' && mb_strtolower($name) === $qLower) { $resolvedCountry = $c; break; }
                            }
                            if (!$resolvedCountry) {
                                foreach ($countriesAll as $c) {
                                    $name = isset($c['name']) ? (string)$c['name'] : '';
                                    if ($name !== '' && mb_strpos(mb_strtolower($name), $qLower) !== false) { $resolvedCountry = $c; break; }
                                }
                            }
                        }

                        // --- Brands (huge directory) use the same cache strategy as /emall/brands/search
                        $cacheKey = 'emall_brands_' . sha1(preg_replace('/^Bearer\s+/i','', $apiKey)) . '.json';
                        $cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cacheKey;
                        $ttlSeconds = 1800;

                        $brandsAll = null;
                        if (file_exists($cachePath)) {
                            $mtime = @filemtime($cachePath);
                            if ($mtime && (time() - $mtime) < $ttlSeconds) {
                                $raw = @file_get_contents($cachePath);
                                $decoded = json_decode((string)$raw, true);
                                if (is_array($decoded)) $brandsAll = $decoded;
                            }
                        }
                        if (!is_array($brandsAll)) {
                            $brandsAll = $client->fetchAllBrands();
                            if (is_array($brandsAll)) {
                                @file_put_contents($cachePath, json_encode($brandsAll, JSON_UNESCAPED_UNICODE));
                            }
                        }

                        $predBrand = isset($result['brand']) ? trim((string)$result['brand']) : '';
                        if ($predBrand !== '' && is_array($brandsAll)) {
                            $qLower = mb_strtolower($predBrand);
                            foreach ($brandsAll as $b) {
                                $name = isset($b['name']) ? (string)$b['name'] : '';
                                if ($name !== '' && mb_strtolower($name) === $qLower) { $resolvedBrand = $b; break; }
                            }
                            if (!$resolvedBrand) {
                                foreach ($brandsAll as $b) {
                                    $name = isset($b['name']) ? (string)$b['name'] : '';
                                    if ($name !== '' && mb_strpos(mb_strtolower($name), $qLower) !== false) { $resolvedBrand = $b; break; }
                                }
                            }
                        }
                    } catch (RuntimeException $e) {
                        // ignore directory resolution errors, fallback to raw prediction
                    }
                }

                $result['brand_resolved'] = $resolvedBrand;
                $result['country_resolved'] = $resolvedCountry;

            } catch (RuntimeException $e) {
                fail('BRAND_COUNTRY_FAILED', $e->getMessage(), [], 400);
            }

            Response::success($result);
            return true;
        }

        // GET /emall/brands — proxy to eMall API (uses user's API key)
        if ($method === 'GET' && preg_match('#^/emall/brands(?:\?(.*))?$#', $path, $pathM)) {
            $uid = requireAuth();
            $draftId = isset($_GET['draftId']) ? trim((string) $_GET['draftId']) : null;
            $apiKey = self::getEmallApiKey($uid, $draftId);
            if (!$apiKey) {
                fail('EMALL_KEY_REQUIRED', 'Save eMall API key in cabinet first', []);
            }
            try {
                $client = new EmallApiClient($apiKey);
                $brands = $client->fetchAllBrands();
                Response::success(['brands' => $brands]);
            } catch (RuntimeException $e) {
                fail('EMALL_BRANDS_FAILED', $e->getMessage(), [], 400);
            }
            return true;
        }

        // GET /emall/brands/search?q=...&limit=... — server-side search (avoids loading huge directory in UI)
        // Uses a short-lived file cache to prevent repeated heavy requests to eMall.
        if ($method === 'GET' && preg_match('#^/emall/brands/search(?:\?(.*))?$#', $path, $pathM)) {
            $uid = requireAuth();
            $draftId = isset($_GET['draftId']) ? trim((string) $_GET['draftId']) : null;
            $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
            if ($limit < 1) $limit = 50;
            if ($limit > 200) $limit = 200;
            $refresh = isset($_GET['refresh']) && ((string) $_GET['refresh'] === '1' || (string) $_GET['refresh'] === 'true');

            $apiKey = self::getEmallApiKey($uid, $draftId);
            if (!$apiKey) {
                fail('EMALL_KEY_REQUIRED', 'Save eMall API key in cabinet first', []);
            }

            $cacheKey = 'emall_brands_' . sha1($apiKey) . '.json';
            $cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cacheKey;
            $ttlSeconds = 1800; // 30 minutes

            try {
                $brandsAll = null;
                if (!$refresh && file_exists($cachePath)) {
                    $mtime = @filemtime($cachePath);
                    if ($mtime && (time() - $mtime) < $ttlSeconds) {
                        $raw = @file_get_contents($cachePath);
                        $decoded = json_decode((string) $raw, true);
                        if (is_array($decoded)) {
                            $brandsAll = $decoded;
                        }
                    }
                }

                if (!is_array($brandsAll)) {
                    $client = new EmallApiClient($apiKey);
                    $brandsAll = $client->fetchAllBrands();
                    if (is_array($brandsAll)) {
                        @file_put_contents($cachePath, json_encode($brandsAll, JSON_UNESCAPED_UNICODE));
                    }
                }

                $qLower = mb_strtolower($q);
                $out = [];
                if ($qLower === '') {
                    // If no query provided, return first N items to keep UI responsive.
                    foreach ($brandsAll as $b) {
                        $out[] = $b;
                        if (count($out) >= $limit) break;
                    }
                } else {
                    foreach ($brandsAll as $b) {
                        $name = isset($b['name']) ? (string) $b['name'] : '';
                        if ($name === '') continue;
                        if (mb_strpos(mb_strtolower($name), $qLower) !== false) {
                            $out[] = $b;
                            if (count($out) >= $limit) break;
                        }
                    }
                }

                Response::success(['brands' => $out]);
            } catch (RuntimeException $e) {
                fail('EMALL_BRANDS_SEARCH_FAILED', $e->getMessage(), [], 400);
            }
            return true;
        }

        // GET /emall/properties — category properties (requires categoryId, draftId for API key)
        if ($method === 'GET' && preg_match('#^/emall/properties(?:\?(.*))?$#', $path, $pathM)) {
            $uid = requireAuth();
            $categoryId = isset($_GET['categoryId']) ? trim((string) $_GET['categoryId']) : null;
            $draftId = isset($_GET['draftId']) ? trim((string) $_GET['draftId']) : null;
            if (!$categoryId || !ctype_digit($categoryId)) {
                fail('CATEGORY_ID_REQUIRED', 'categoryId is required', []);
            }
            $apiKey = self::getEmallApiKey($uid, $draftId);
            if (!$apiKey) {
                fail('EMALL_KEY_REQUIRED', 'Save eMall API key in cabinet first', []);
            }
            try {
                $client = new EmallApiClient($apiKey);
                $result = $client->fetchCategoryProperties((int) $categoryId);
                // Cache full properties schema in draft.step_state so that fill-properties
                // can work without sending large JSON in the request body (avoids WAF 406).
                if ($draftId) {
                    // Prefer explicit 'properties' key, fallback to 'data' (current client shape).
                    $schema = [];
                    if (isset($result['properties']) && is_array($result['properties'])) {
                        $schema = $result['properties'];
                    } elseif (isset($result['data']) && is_array($result['data'])) {
                        $schema = $result['data'];
                    }
                    if (!empty($schema)) {
                        DraftRepository::mergeStepStateForDraft($draftId, $uid, ['category_properties_schema' => $schema]);
                    }
                }
                Response::success($result);
            } catch (RuntimeException $e) {
                fail('EMALL_PROPERTIES_FAILED', $e->getMessage(), [], 400);
            }
            return true;
        }

        // POST /emall/drafts/{draftId}/fill-properties — DeepSeek fills properties from description
        if ($method === 'POST' && preg_match('#^/emall/drafts/([^/]+)/fill-properties$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];

            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }

            $marketplace = DraftRepository::getMarketplaceForDraft($uid, $draftId);
            if ($marketplace !== 'emall') {
                fail('MARKETPLACE_MISMATCH', 'Draft is not eMall', [], 400);
            }

            $title = '';
            $description = '';
            $filledRaw = $draft['filled_fields'] ?? null;
            $filled = is_array($filledRaw) ? $filledRaw : (is_string($filledRaw) ? (json_decode($filledRaw, true) ?: []) : []);
            $stepState = is_array($draft['step_state'] ?? []) ? $draft['step_state'] : [];

            $genText = $filled['generated_text'] ?? $filled['edited_text'] ?? $stepState['generated_text'] ?? [];
            if (is_array($genText)) {
                $title = trim((string) ($genText['title'] ?? ''));
                $parts = array_filter([
                    (string) ($genText['description'] ?? ''),
                    (string) ($genText['benefits'] ?? ''),
                    (string) ($genText['usage'] ?? ''),
                ]);
                $description = implode("\n\n", $parts);
            }
            if ($description === '') {
                $description = trim((string) ($draft['editedJson']['description'] ?? $draft['description'] ?? ''));
            }
            if ($title === '' && $description === '') {
                fail('SOURCE_EMPTY', 'Complete step 2 first', []);
            }

            // Take properties schema from step_state, not from request body, to avoid
            // sending large JSON with words like "select" through WAF/ModSecurity".
            $propertiesSchema = $stepState['category_properties_schema'] ?? [];
            if (!is_array($propertiesSchema) || empty($propertiesSchema)) {
                // Fallback: if schema is not yet cached (e.g. properties were fetched before
                // this fix was deployed), fetch schema from eMall API here using category_id.
                $cat = $stepState['category'] ?? ($draft['editedJson']['selected_category'] ?? null);
                $categoryId = is_array($cat) && isset($cat['category_id']) ? (int) $cat['category_id'] : null;
                if ($categoryId !== null && $categoryId > 0) {
                    $apiKey = self::getEmallApiKey($uid, $draftId);
                    if (!$apiKey) {
                        fail('EMALL_KEY_REQUIRED', 'Save eMall API key in cabinet first', []);
                    }
                    $client = new EmallApiClient($apiKey);
                    $propsResp = $client->fetchCategoryProperties($categoryId);
                    if (isset($propsResp['data']) && is_array($propsResp['data']) && !empty($propsResp['data'])) {
                        $propertiesSchema = $propsResp['data'];
                        DraftRepository::mergeStepStateForDraft($draftId, $uid, ['category_properties_schema' => $propertiesSchema]);
                    }
                }
                if (!is_array($propertiesSchema) || empty($propertiesSchema)) {
                    fail('PROPERTIES_SCHEMA_REQUIRED', 'Category properties schema not loaded. Open step 5 and fetch properties first.', [], 400);
                }
            }

            try {
                $result = EmallPropertiesService::fillFromDescription($title, $description, $propertiesSchema, $locale);
            } catch (RuntimeException $e) {
                $code = $e->getMessage();
                if (strpos($code, 'DEEPSEEK') !== false) {
                    fail('FILL_PROPERTIES_FAILED', $e->getMessage(), [], 400);
                }
                fail('FILL_PROPERTIES_FAILED', 'Could not fill properties', ['reason' => $e->getMessage()], 400);
            }

            DraftRepository::mergeStepStateForDraft($draftId, $uid, ['category_properties' => $result['properties']]);

            Response::success($result);
            return true;
        }

        // POST /emall/drafts/{draftId}/submit-product — send payload to eMall API
        if ($method === 'POST' && preg_match('#^/emall/drafts/([^/]+)/submit-product$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];

            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }

            $marketplace = DraftRepository::getMarketplaceForDraft($uid, $draftId);
            if ($marketplace !== 'emall') {
                fail('MARKETPLACE_MISMATCH', 'Draft is not eMall', [], 400);
            }

            $apiKey = self::getEmallApiKey($uid, $draftId);
            if (!$apiKey) {
                fail('EMALL_KEY_REQUIRED', 'Save eMall API key in cabinet first', []);
            }

            $baseUrl = trim((string) Config::get('APP_BASE_URL', ''));
            try {
                $result = EmallSubmitService::submit($draft, $apiKey, $baseUrl);
            } catch (RuntimeException $e) {
                fail('SUBMIT_FAILED', $e->getMessage(), [], 400);
            }

            Response::success($result);
            return true;
        }

        // GET /emall/drafts/{draftId}/build-payload — build product payload for API
        if ($method === 'GET' && preg_match('#^/emall/drafts/([^/]+)/build-payload$#', $path, $m)) {
            $uid = requireAuth();
            $draftId = $m[1];

            $draft = DraftRepository::getDraft($draftId, $uid, $locale);
            if (!$draft) {
                fail('DRAFT_NOT_FOUND', 'Draft not found', [], 404);
            }

            $marketplace = DraftRepository::getMarketplaceForDraft($uid, $draftId);
            if ($marketplace !== 'emall') {
                fail('MARKETPLACE_MISMATCH', 'Draft is not eMall', [], 400);
            }

            $baseUrl = trim((string) Config::get('APP_BASE_URL', ''));
            $result = EmallPayloadService::build($draft, $baseUrl);
            Response::success($result);
            return true;
        }

        // GET /emall/countries — proxy to eMall API
        if ($method === 'GET' && preg_match('#^/emall/countries(?:\?(.*))?$#', $path, $pathM2)) {
            $uid = requireAuth();
            $draftId = isset($_GET['draftId']) ? trim((string) $_GET['draftId']) : null;
            $apiKey = self::getEmallApiKey($uid, $draftId);
            if (!$apiKey) {
                fail('EMALL_KEY_REQUIRED', 'Save eMall API key in cabinet first', []);
            }
            try {
                $client = new EmallApiClient($apiKey);
                $countries = $client->fetchAllCountries();
                Response::success(['countries' => $countries]);
            } catch (RuntimeException $e) {
                fail('EMALL_COUNTRIES_FAILED', $e->getMessage(), [], 400);
            }
            return true;
        }

        return false;
    }

    private static function getEmallApiKey($userId, $draftId = null)
    {
        // API key is managed only in the user's cabinet (no override from draft step 1)
        $creds = EmallCredentialsRepository::getForUser($userId);
        return $creds['api_key'] ?? null;
    }
}
