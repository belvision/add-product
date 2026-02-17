<?php

require_once __DIR__ . '/Config.php';

/**
 * cURL wrapper for DeepSeek chat completions API.
 * Used for function calling (generate_marketplace_text etc).
 */
class DeepSeekClient
{
    private const TIMEOUT_SEC = 60;
    private const RETRY_ON_NETWORK = 1;

    private string $apiUrl;
    private string $apiKey;
    private ?string $proxyAddr;
    private ?string $proxyAuth;

    public function __construct()
    {
        $this->apiUrl = trim((string) Config::get('DEEPSEEK_API_URL', ''));
        $this->apiKey = trim((string) Config::get('DEEPSEEK_API_KEY', ''));
        $proxyAddr = trim((string) Config::get('PROXY_ADDR', ''));
        $this->proxyAddr = $proxyAddr !== '' ? $proxyAddr : null;
        $proxyUser = trim((string) Config::get('PROXY_USER', ''));
        $proxyPass = (string) Config::get('PROXY_PASS', '');
        $this->proxyAuth = ($proxyUser !== '' && $proxyPass !== '') ? ($proxyUser . ':' . $proxyPass) : null;
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl !== '' && $this->apiKey !== '';
    }

    /**
     * Send chat completion request.
     *
     * @param array{model:string,messages:array,temperature?:float,tools?:array,tool_choice?:array} $payload
     * @return array Raw API response (decoded JSON)
     * @throws RuntimeException
     */
    public function chat(array $payload): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('DEEPSEEK_NOT_CONFIGURED');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('MISSING_EXTENSION_CURL');
        }

        $body = json_encode($payload);
        $attempts = 1 + self::RETRY_ON_NETWORK;
        $lastErr = null;
        $lastResponse = null;
        $lastCode = null;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $ch = curl_init($this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT_SEC);
            curl_setopt($ch, CURLOPT_PROXY, $this->proxyAddr ?? '');
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxyAuth ?? '');
            if ($this->proxyAddr !== null && $this->proxyAuth !== null) {
                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }

            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            $lastCode = $code;
            $lastResponse = $response;
            $lastErr = $err;

            if ($response === false) {
                if ($attempt < $attempts - 1) {
                    usleep(500000); // 500ms retry
                    continue;
                }
                throw new RuntimeException('DEEPSEEK_CURL_ERROR' . ($err !== '' ? ': ' . substr($err, 0, 150) : ''));
            }

            if ($code >= 200 && $code < 300) {
                $decoded = json_decode((string) $response, true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('DEEPSEEK_INVALID_JSON');
                }
                return $decoded;
            }

            if ($code === 401 || $code === 403 || $code === 400) {
                $dec = json_decode((string) $response, true);
                $msg = is_array($dec) && isset($dec['error']['message']) ? substr((string) $dec['error']['message'], 0, 200) : 'HTTP_' . $code;
                throw new RuntimeException('DEEPSEEK_API_ERROR: ' . $msg);
            }

            if (($code === 429 || $code >= 500) && $attempt < $attempts - 1) {
                usleep(1000000); // 1s retry
                continue;
            }

            throw new RuntimeException('DEEPSEEK_HTTP_' . $code . ': ' . substr((string) $response, 0, 200));
        }

        throw new RuntimeException('DEEPSEEK_REQUEST_FAILED');
    }
}
