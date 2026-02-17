<?php

/**
 * Proxy client for eMall.by API (brands, countries).
 * Uses Bearer token for Authorization.
 */
class EmallApiClient
{
    private const BASE = 'https://api-preprod.emall.by/open/api/v1';
    private const TIMEOUT = 30;

    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = trim($apiKey);
        if (stripos($this->apiKey, 'Bearer ') !== 0 && $this->apiKey !== '') {
            $this->apiKey = 'Bearer ' . $this->apiKey;
        }
    }

    /**
     * Fetch all brands (paginated).
     * @return array<array{id: int, name: string}>
     */
    public function fetchAllBrands(): array
    {
        $all = [];
        $page = 1;
        $perPage = 100;

        do {
            $url = self::BASE . '/configurator/brands?page=' . $page . '&perPage=' . $perPage;
            $resp = $this->request('GET', $url);
            $data = $resp['data'] ?? [];
            if (!is_array($data) || empty($data)) {
                break;
            }
            foreach ($data as $item) {
                if (isset($item['id'], $item['name'])) {
                    $all[] = ['id' => (int) $item['id'], 'name' => (string) $item['name']];
                }
            }
            $meta = $resp['meta'] ?? [];
            $totalPages = (int) ($meta['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $all;
    }

    /**
     * Fetch all countries (paginated).
     * @return array<array{id: int, name: string}>
     */
    public function fetchAllCountries(): array
    {
        $all = [];
        $page = 1;
        $perPage = 100;

        do {
            $url = self::BASE . '/catalog/countries?page=' . $page . '&per_page=' . $perPage;
            $resp = $this->request('GET', $url);
            $data = $resp['data'] ?? [];
            if (!is_array($data) || empty($data)) {
                break;
            }
            foreach ($data as $item) {
                if (isset($item['id'], $item['name'])) {
                    $all[] = ['id' => (int) $item['id'], 'name' => (string) $item['name']];
                }
            }
            $meta = $resp['meta'] ?? [];
            $totalPages = (int) ($meta['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $all;
    }

    private function request(string $method, string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('CURL_REQUIRED');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }

        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('EMALL_CURL_ERROR: ' . $err);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('EMALL_INVALID_JSON');
        }

        if ($code >= 400 || isset($decoded['status']) && $decoded['status'] === 'error' || isset($decoded['error'])) {
            $msg = $decoded['message'] ?? $decoded['error']['message'] ?? $decoded['error'] ?? 'API error';
            throw new RuntimeException('EMALL_API_ERROR: ' . (is_string($msg) ? $msg : json_encode($msg)));
        }

        return $decoded;
    }

    /**
     * Fetch category properties (paginated).
     * GET /configurator/categories/{categoryId}/properties
     *
     * @return array{data: array, meta: array}
     */
    public function fetchCategoryProperties(int $categoryId): array
    {
        $all = [];
        $page = 1;
        $perPage = 100;
        $meta = [];

        do {
            $url = self::BASE . '/configurator/categories/' . $categoryId . '/properties?page=' . $page . '&per_page=' . $perPage;
            $resp = $this->request('GET', $url);
            $data = $resp['data'] ?? [];
            $meta = $resp['meta'] ?? [];
            if (!is_array($data)) {
                break;
            }
            foreach ($data as $item) {
                $all[] = $item;
            }
            $totalPages = (int) ($meta['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return ['data' => $all, 'meta' => $meta];
    }

    /**
     * POST products - returns raw response (does not throw on 4xx, returns body for error parsing).
     * @return array{code: int, body: string, decoded: array|null}
     */
    public function postProducts(string $batchPayload): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('CURL_REQUIRED');
        }
        $url = self::BASE . '/products';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $batchPayload,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new RuntimeException('EMALL_CURL_ERROR: ' . $err);
        }
        $decoded = json_decode((string) $response, true);
        return ['code' => $code, 'body' => (string) $response, 'decoded' => is_array($decoded) ? $decoded : null];
    }
}
