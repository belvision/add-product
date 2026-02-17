<?php

require_once __DIR__ . '/EmallApiClient.php';
require_once __DIR__ . '/EmallPayloadService.php';

/**
 * Submits product to eMall API. On validation error, removes invalid optional properties and retries.
 */
class EmallSubmitService
{
    /**
     * Submit draft as product to eMall API.
     *
     * @param array $draft Draft from DraftRepository::getDraft
     * @param string $apiKey Bearer token
     * @param string $appBaseUrl
     * @return array{success: bool, message?: string, response?: array, attempt?: int, removedProps?: array}
     */
    public static function submit(array $draft, string $apiKey, string $appBaseUrl = ''): array
    {
        $payloadResult = EmallPayloadService::build($draft, $appBaseUrl);
        $batchPayload = $payloadResult['batchPayload'] ?? '';
        if (!is_string($batchPayload) || trim($batchPayload) === '') {
            return ['success' => false, 'message' => 'Failed to build payload'];
        }

        // Safety: ensure we really produced JSON before sending it to eMall
        if (json_decode($batchPayload, true) === null && json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Сформированный payload не является валидным JSON: ' . json_last_error_msg()];
        }
        if (!empty($payloadResult['errors'])) {
            return ['success' => false, 'message' => implode('. ', $payloadResult['errors'])];
        }

        $stepState = $draft['step_state'] ?? [];
        $stepState = is_array($stepState) ? $stepState : [];
        $category = $stepState['category'] ?? $draft['chosen_category'] ?? [];
        $category = is_array($category) ? $category : [];
        $categoryId = (int) ($category['category_id'] ?? 0);
        if ($categoryId <= 0) {
            return ['success' => false, 'message' => 'category_id required'];
        }

        $client = new EmallApiClient($apiKey);
        $propResult = $client->fetchCategoryProperties($categoryId);
        $propJsonFull = json_encode($propResult);
        $requiredIds = self::extractRequiredIds($propJsonFull);

        $resp = self::trySend($client, $batchPayload);
        if (self::isSuccess($resp['body'])) {
            return ['success' => true, 'response' => $resp['decoded'], 'attempt' => 1];
        }

        if (!self::isValidationError($resp['body'])) {
            return ['success' => false, 'message' => self::extractErrorMessage($resp['body']), 'response' => $resp['decoded']];
        }

        $badIds = self::extractBadPropertyIds($resp['body']);
        if (empty($badIds)) {
            return ['success' => false, 'message' => self::extractErrorMessage($resp['body']), 'response' => $resp['decoded']];
        }

        $badReq = [];
        $badOpt = [];
        foreach ($badIds as $id) {
            if (isset($requiredIds[$id])) {
                $badReq[] = $id;
            } else {
                $badOpt[] = $id;
            }
        }
        if (!empty($badReq)) {
            return ['success' => false, 'message' => 'Invalid required properties: ' . implode(', ', $badReq), 'response' => $resp['decoded']];
        }
        if (empty($badOpt)) {
            return ['success' => false, 'message' => self::extractErrorMessage($resp['body']), 'response' => $resp['decoded']];
        }

        $cleanedPayload = self::removeProperties($batchPayload, $badOpt);
        $resp2 = self::trySend($client, $cleanedPayload);
        if (self::isSuccess($resp2['body'])) {
            return ['success' => true, 'response' => $resp2['decoded'], 'attempt' => 2, 'removedProps' => $badOpt];
        }

        return ['success' => false, 'message' => self::extractErrorMessage($resp2['body']), 'response' => $resp2['decoded'], 'removedProps' => $badOpt];
    }

    private static function trySend(EmallApiClient $client, string $payload): array
    {
        return $client->postProducts($payload);
    }

    private static function isSuccess(string $body): bool
    {
        return (bool) preg_match('/"success"\s*:\s*true/i', $body) && !self::isValidationError($body);
    }

    private static function isValidationError(string $body): bool
    {
        return (bool) preg_match('/Ошибка\s+валидации|validation\s+error/i', $body);
    }

    private static function extractErrorMessage(string $body): string
    {
        $dec = json_decode($body, true);
        if (is_array($dec)) {
            if (isset($dec['message']) && is_string($dec['message'])) {
                return $dec['message'];
            }
            if (isset($dec['error']['message']) && is_string($dec['error']['message'])) {
                return $dec['error']['message'];
            }
        }
        return strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body;
    }

    /** @return array<string, true> id => true for required */
    private static function extractRequiredIds(string $propJsonFull): array
    {
        $required = [];
        if (preg_match_all('/\{\s*"id"\s*:\s*(\d+)[\s\S]*?"is_required"\s*:\s*true/i', $propJsonFull, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $required[$match[1]] = true;
            }
        }
        return $required;
    }

    /** @return array<string> */
    private static function extractBadPropertyIds(string $body): array
    {
        $dec = json_decode($body, true);
        if (is_array($dec) && isset($dec['properties']) && is_array($dec['properties'])) {
            return array_keys($dec['properties']);
        }
        $ids = [];
        if (preg_match('/"properties"\s*:\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/s', $body, $block)) {
            if (preg_match_all('/"(\d+)"\s*:/', $block[1], $m)) {
                foreach ($m[1] as $id) {
                    $ids[$id] = true;
                }
            }
        }
        return array_keys($ids);
    }

    /**
     * @param array<string> $idsToRemove
     */
    private static function removeProperties(string $payload, array $idsToRemove): string
    {
        foreach ($idsToRemove as $pid) {
            $payload = preg_replace(
                '/"' . preg_quote($pid, '/') . '"\s*:\s*(?:"[^"]*"|\[[^\]]*\]|\d+|true|false)\s*,?\s*/s',
                '',
                $payload,
                1
            );
        }
        $payload = preg_replace('/,\s*,/', ',', $payload);
        $payload = preg_replace('/,\s*([}\]])/', '$1', $payload);
        return $payload;
    }
}
