<?php

require_once __DIR__ . '/DeepSeekClient.php';

/**
 * Extracts brand and country of manufacture from product title/description via DeepSeek.
 */
class EmallBrandCountryService
{
    /**
     * Predict brand (without series/modification) and country of manufacture.
     *
     * @param string $title Product title
     * @param string $description Product description
     * @param string $locale 'ru'|'en'
     * @return array{brand: string, country: string}
     * @throws RuntimeException
     */
    public static function predict(string $title, string $description, string $locale): array
    {
        $title = trim($title);
        $description = trim($description);
        if ($title === '' && $description === '') {
            throw new RuntimeException('TITLE_OR_DESCRIPTION_REQUIRED');
        }

        $client = new DeepSeekClient();
        if (!$client->isConfigured()) {
            throw new RuntimeException('DEEPSEEK_NOT_CONFIGURED');
        }

        $userContent = $locale === 'ru'
            ? "Укажи название бренда товара без упоминания серии или модификации, а также страну производства. "
            . "Верни результат только через функцию get_brand_country. Без лишнего текста.\n\n"
            . "Название: {$title}\nОписание: {$description}"
            : "Specify the product brand name (without series or modification) and country of manufacture. "
            . "Return result only via get_brand_country function. No extra text.\n\n"
            . "Title: {$title}\nDescription: {$description}";

        $payload = [
            'model' => 'deepseek-chat',
            'temperature' => 0.1,
            'messages' => [['role' => 'user', 'content' => $userContent]],
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'get_brand_country',
                    'description' => 'Returns brand name and country of manufacture.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'brand' => ['type' => 'string', 'description' => 'Brand name only, no series'],
                            'country' => ['type' => 'string', 'description' => 'Country of manufacture in Russian or English'],
                        ],
                        'required' => ['brand', 'country'],
                    ],
                ],
            ]],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'get_brand_country']],
        ];

        $response = $client->chat($payload);
        $parsed = self::parseResponse($response);

        $brand = trim(preg_replace('/^.*?:\s*/iu', '', (string) ($parsed['brand'] ?? '')));
        $brand = str_replace('*', '', $brand);
        $brand = trim($brand, " .\t\n\r");

        $country = trim((string) ($parsed['country'] ?? ''));
        $country = str_replace('*', '', $country);
        $country = trim($country, " .\t\n\r");

        return ['brand' => $brand ?: 'Unknown', 'country' => $country ?: 'Unknown'];
    }

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
                return is_array($decoded) ? $decoded : [];
            }
            return [];
        }

        $parsed = json_decode($argsJson, true);
        return is_array($parsed) ? $parsed : [];
    }
}
