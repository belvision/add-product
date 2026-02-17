<?php

require_once __DIR__ . '/DeepSeekClient.php';

/**
 * Fills category properties from product title/description via DeepSeek.
 * Returns JSON: {"properties":[{"id":int,"value":mixed},...]}
 */
class EmallPropertiesService
{
    /**
     * @param string $title Product title
     * @param string $description Product description
     * @param array $propertiesSchema Full JSON from eMall API (data array)
     * @param string $locale 'ru'|'en'
     * @return array{properties: array<array{id: int, value: mixed}>}
     */
    public static function fillFromDescription(string $title, string $description, array $propertiesSchema, string $locale): array
    {
        $client = new DeepSeekClient();
        if (!$client->isConfigured()) {
            throw new RuntimeException('DEEPSEEK_NOT_CONFIGURED');
        }

        $propJson = json_encode($propertiesSchema, JSON_UNESCAPED_UNICODE);
        if ($propJson === false || $propJson === '[]') {
            return ['properties' => []];
        }

        $userContent = $locale === 'ru'
            ? "Проанализируй описание товара и JSON со свойствами категории. "
            . "Верни строго JSON вида {\"properties\":[{\"id\":...,\"value\":...},...]} без markdown, без комментариев. "
            . "Соблюдай типы:\n"
            . "- list → число (id значения);\n"
            . "- multiselect_list → массив чисел [id1, id2];\n"
            . "- number → число без кавычек;\n"
            . "- string → текст в кавычках;\n"
            . "- boolean → true или false.\n"
            . "Используй только id и value из списка свойств. Не придумывай значения.\n\n"
            . "Название: {$title}\nОписание: {$description}\n\nСписок свойств категории: {$propJson}"
            : "Analyze the product description and JSON with category properties. "
            . "Return strictly JSON: {\"properties\":[{\"id\":...,\"value\":...},...]} without markdown or comments. "
            . "Respect types:\n"
            . "- list → number (value id);\n"
            . "- multiselect_list → array of numbers [id1, id2];\n"
            . "- number → number without quotes;\n"
            . "- string → text in quotes;\n"
            . "- boolean → true or false.\n"
            . "Use only id and value from the property list. Do not invent values.\n\n"
            . "Title: {$title}\nDescription: {$description}\n\nCategory properties: {$propJson}";

        $payload = [
            'model' => 'deepseek-chat',
            'temperature' => 0.1,
            'messages' => [['role' => 'user', 'content' => $userContent]],
        ];

        $response = $client->chat($payload);
        $content = self::extractContent($response);
        $parsed = self::parseAndFix($content);
        return ['properties' => $parsed];
    }

    private static function extractContent(array $response): string
    {
        $msg = $response['choices'][0]['message'] ?? null;
        if (!$msg || !is_array($msg)) {
            return '';
        }
        return trim((string) ($msg['content'] ?? ''));
    }

    /**
     * Parse JSON, strip markdown, fix common issues.
     * @return array<array{id: int, value: mixed}>
     */
    private static function parseAndFix(string $content): array
    {
        $content = preg_replace('/^```json\s*|\s*```$/i', '', trim($content));
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', $content);
        $content = preg_replace('/,\s*([}\]])/', '$1', $content);

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['properties']) || !is_array($decoded['properties'])) {
            return [];
        }

        $out = [];
        foreach ($decoded['properties'] as $p) {
            if (!isset($p['id']) || $p['id'] === null || $p['id'] === '') {
                continue;
            }
            $id = (int) $p['id'];
            $value = $p['value'] ?? null;
            $out[] = ['id' => $id, 'value' => $value];
        }
        return $out;
    }
}
