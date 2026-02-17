<?php

class SsrfProtection
{
    const MAX_REDIRECTS = 3;
    const MAX_BYTES = 8388608;

    public static function isUrlAllowed($url)
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return false;
        }
        if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower($parsed['host']);
        if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $host, $m)) {
            $ip = (int)$m[1] << 24 | (int)$m[2] << 16 | (int)$m[3] << 8 | (int)$m[4];
            if (self::isPrivateOrReserved($ip)) {
                return false;
            }
        }
        $resolved = gethostbynamel($host);
        if ($resolved !== false) {
            foreach ($resolved as $ip) {
                $long = ip2long($ip);
                if ($long !== false && self::isPrivateOrReserved($long)) {
                    return false;
                }
            }
        }
        if (preg_match('/^(localhost|127\.|::1|0\.|10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|169\.254\.)/i', $host)) {
            return false;
        }
        return true;
    }

    private static function isPrivateOrReserved($ipLong)
    {
        return ($ipLong >= 0 && $ipLong <= 0x00FFFFFF)
            || ($ipLong >= 0x7F000000 && $ipLong <= 0x7FFFFFFF)
            || ($ipLong >= 0x0A000000 && $ipLong <= 0x0AFFFFFF)
            || ($ipLong >= 0xAC100000 && $ipLong <= 0xAC1FFFFF)
            || ($ipLong >= 0xC0A80000 && $ipLong <= 0xC0A8FFFF)
            || ($ipLong >= 0xA9FE0000 && $ipLong <= 0xA9FEFFFF);
    }

    public static function fetchWithLimit($url, $maxRedirects = self::MAX_REDIRECTS, $maxBytes = self::MAX_BYTES)
    {
        if (!function_exists('curl_init')) {
            return [false, null, 'MISSING_EXTENSION', 'curl extension required'];
        }
        if (!self::isUrlAllowed($url)) {
            return [false, null, 'SSRF_BLOCKED', 'URL not allowed'];
        }
        $currentUrl = $url;
        $redirects = 0;
        while ($redirects <= $maxRedirects) {
            $ch = curl_init($currentUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $totalBytes = 0;
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$totalBytes, $maxBytes) {
                $len = strlen($data);
                $totalBytes += $len;
                if ($totalBytes > $maxBytes) {
                    return -1;
                }
                return $len;
            });
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $err = curl_error($ch);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            if ($response === false || $totalBytes > $maxBytes) {
                if ($totalBytes > $maxBytes) {
                    return [false, null, 'UPLOAD_TOO_LARGE', 'Over byte limit'];
                }
                return [false, null, 'FETCH_FAILED', $err ?: 'Download failed'];
            }
            if ($code !== 200) {
                return [false, null, 'FETCH_FAILED', 'HTTP ' . $code];
            }
            $headerSize = (int) $headerSize;
            $headers = $headerSize > 0 ? substr($response, 0, $headerSize) : '';
            $body = $headerSize > 0 ? substr($response, $headerSize) : (string) $response;
            if ($code >= 300 && $code < 400 && $redirects < $maxRedirects && preg_match('/^Location:\s*(\S+)/mi', $headers, $m)) {
                $location = trim($m[1]);
                if (strpos($location, 'http') !== 0) {
                    $parsed = parse_url($currentUrl);
                    $base = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
                    $location = $base . (strpos($location, '/') === 0 ? '' : '/') . $location;
                }
                $currentUrl = $location;
                if (!self::isUrlAllowed($currentUrl)) {
                    return [false, null, 'SSRF_BLOCKED', 'Redirect target not allowed'];
                }
                $redirects++;
                continue;
            }
            if ($code !== 200) {
                return [false, null, 'FETCH_FAILED', 'HTTP ' . $code];
            }
            return [true, ['body' => $body, 'code' => $code, 'content_type' => $contentType], null, null];
        }
        return [false, null, 'FETCH_FAILED', 'Too many redirects'];
    }
}
