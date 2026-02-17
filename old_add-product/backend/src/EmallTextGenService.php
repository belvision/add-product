<?php

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/DeepSeekClient.php';

/**
 * Generates marketplace card text (title, description, benefits, usage) via DeepSeek.
 */
class EmallTextGenService
{
    private const DESC_MIN = 400;
    private const DESC_MAX = 900;
    private const USAGE_MIN = 300;
    private const USAGE_MAX = 700;
    private const TITLE_MIN = 10;
    private const TITLE_MAX = 200;
    private const BENEFITS_MAX = 500;

    /**
     * @return array{title: string, description: string, benefits: string, usage: string}
     * @throws RuntimeException
     */
    public static function generate(string $sourceText, string $locale): array
    {
        $sourceText = trim($sourceText);
        if ($sourceText === '') {
            throw new RuntimeException('SOURCE_TEXT_EMPTY');
        }

        $client = new DeepSeekClient();
        if (!$client->isConfigured()) {
            throw new RuntimeException('DEEPSEEK_NOT_CONFIGURED');
        }

        $systemContent = $locale === 'ru'
            ? 'Ты генерируешь текст для карточек маркетплейса. Верни данные ТОЛЬКО через function calling (generate_marketplace_text). Никакого markdown, объяснений или текста в content. Поля: title (название товара), description (400–900 символов, без HTML), benefits (одна строка, пункты через ; ), usage (300–700 символов).'
            : 'You generate text for marketplace product cards. Return data ONLY via function calling (generate_marketplace_text). No markdown, explanations or content. Fields: title, description (400–900 chars, no HTML), benefits (one line, items separated by ;), usage (300–700 chars).';

        $userContent = $locale === 'ru'
            ? 'Название (если есть), исходное описание/текст: ' . $sourceText . "\n\nСформируй: title, description, benefits, usage."
            : 'Title (if any), original description: ' . $sourceText . "\n\nGenerate: title, description, benefits, usage.";

        $payload = [
            'model' => 'deepseek-chat',
            'temperature' => 0.1,
            'messages' => [
                ['role' => 'system', 'content' => $systemContent],
                ['role' => 'user', 'content' => $userContent],
            ],
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'generate_marketplace_text',
                    'description' => 'Returns generated product card text.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Product title'],
                            'description' => ['type' => 'string', 'description' => 'Description 400-900 chars'],
                            'benefits' => ['type' => 'string', 'description' => 'Benefits, semicolon-separated'],
                            'usage' => ['type' => 'string', 'description' => 'Usage 300-700 chars'],
                        ],
                        'required' => ['title', 'description', 'benefits', 'usage'],
                    ],
                ],
            ]],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'generate_marketplace_text']],
        ];

        if (Config::get('APP_DEBUG') === 'true') {
            error_log('[EmallTextGen] Request payload: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        $response = $client->chat($payload);

        if (Config::get('APP_DEBUG') === 'true') {
            error_log('[EmallTextGen] Response (no key): ' . json_encode(array_diff_key($response, ['key' => 1]), JSON_UNESCAPED_UNICODE));
        }

        $parsed = self::parseResponse($response);
        return self::validateAndNormalize($parsed);
    }

    /**
     * Parse tool_calls or function_call from DeepSeek response.
     *
     * @return array{title?: string, description?: string, benefits?: string, usage?: string}
     */
    private static function parseResponse(array $response): array
    {
        $message = $response['choices'][0]['message'] ?? null;
        if (!$message || !is_array($message)) {
            return [];
        }

        $argsJson = null;

        if (!empty($message['tool_calls'][0]['function']['arguments'])) {
            $argsJson = (string) $message['tool_calls'][0]['function']['arguments'];
        } elseif (!empty($message['function_call']['arguments'])) {
            $argsJson = (string) $message['function_call']['arguments'];
        }

        if ($argsJson === null) {
            $content = trim((string) ($message['content'] ?? ''));
            if ($content !== '') {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            return [];
        }

        $parsed = json_decode($argsJson, true);
        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Validate lengths and normalize (remove control chars, smart quotes).
     *
     * @return array{title: string, description: string, benefits: string, usage: string}
     */
    private static function validateAndNormalize(array $data): array
    {
        $title = self::normalizeString((string) ($data['title'] ?? ''));
        $description = self::normalizeString((string) ($data['description'] ?? ''));
        $benefits = self::normalizeString((string) ($data['benefits'] ?? ''));
        $usage = self::normalizeString((string) ($data['usage'] ?? ''));

        if ($title === '' || $description === '' || $benefits === '' || $usage === '') {
            throw new RuntimeException('DeepSeek returned incomplete data: all fields required');
        }

        return [
            'title' => $title,
            'description' => $description,
            'benefits' => $benefits,
            'usage' => $usage,
        ];
    }

    private static function normalizeString(string $s): string
    {
        $s = trim($s);
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? $s;
        $s = str_replace(
            ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}", "\u{2013}", "\u{2014}"],
            ['"', '"', "'", "'", '-', '-'],
            $s
        );
        $s = preg_replace('/，/u', ',', $s) ?? $s;
        return trim($s);
    }

    /**
     * Validate user-edited text before save.
     *
     * @return array{title: string, description: string, benefits: string, usage: string}
     */
    public static function validateUserText(array $data): array
    {
        $title = self::sanitizeUserField((string) ($data['title'] ?? ''));
        $description = self::sanitizeUserField((string) ($data['description'] ?? ''));
        $benefits = self::sanitizeUserField((string) ($data['benefits'] ?? ''));
        $usage = self::sanitizeUserField((string) ($data['usage'] ?? ''));

        $lenTitle = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);

        if ($lenTitle < self::TITLE_MIN || $lenTitle > self::TITLE_MAX) {
            throw new RuntimeException('title must be 10–200 characters');
        }
        if ($description === '') {
            throw new RuntimeException('description is required');
        }

        return [
            'title' => $title,
            'description' => $description,
            'benefits' => $benefits,
            'usage' => $usage,
        ];
    }

    private static function sanitizeUserField(string $s): string
    {
        $s = trim($s);
        $s = strip_tags($s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? $s;
        return trim($s);
    }
}
