<?php

require_once __DIR__ . '/DeepSeekClient.php';

/**
 * Predicts product dimensions and weight via DeepSeek from title + description.
 * Used for eMall price calculation (logistics) and product creation.
 */
class EmallDimensionsService
{
    /**
     * Call DeepSeek to estimate dimensions (mm) and weight (g).
     *
     * @param string $title Product title
     * @param string $description Product description (from step 1/2)
     * @param string $locale 'ru'|'en'
     * @return array{width_mm: int, length_mm: int, height_mm: int, weight_g: int}
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
            ? "Верни только JSON-вызов функции get_dimensions с параметрами (width_mm, length_mm, height_mm, weight_g). "
            . "Сумма трёх сторон (Д+Ш+В) не должна превышать 2500 мм. "
            . "Задай себе вопрос: помещается ли товар в руке человека — для правильных приблизительных габаритов. "
            . "Оцени приблизительный вес товара в граммах.\n\n"
            . "Название: {$title}\nОписание: {$description}"
            : "Return only a JSON function call get_dimensions with (width_mm, length_mm, height_mm, weight_g). "
            . "Sum of three sides (L+W+H) must not exceed 2500 mm. "
            . "Consider: does the product fit in a human hand — for correct approximate dimensions. "
            . "Estimate approximate weight in grams.\n\n"
            . "Title: {$title}\nDescription: {$description}";

        $payload = [
            'model' => 'deepseek-chat',
            'temperature' => 0.1,
            'messages' => [['role' => 'user', 'content' => $userContent]],
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'get_dimensions',
                    'description' => 'Returns product dimensions in mm and weight in grams.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'width_mm' => ['type' => 'integer', 'description' => 'Width in mm'],
                            'length_mm' => ['type' => 'integer', 'description' => 'Length in mm'],
                            'height_mm' => ['type' => 'integer', 'description' => 'Height in mm'],
                            'weight_g' => ['type' => 'integer', 'description' => 'Weight in grams'],
                        ],
                        'required' => ['width_mm', 'length_mm', 'height_mm', 'weight_g'],
                    ],
                ],
            ]],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'get_dimensions']],
        ];

        $response = $client->chat($payload);

        $parsed = self::parseResponse($response);
        return self::validateAndNormalize($parsed);
    }

    /**
     * @return array{width_mm?: int, length_mm?: int, height_mm?: int, weight_g?: int}
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
     * @param array{width_mm?: mixed, length_mm?: mixed, height_mm?: mixed, weight_g?: mixed} $data
     * @return array{width_mm: int, length_mm: int, height_mm: int, weight_g: int}
     */
    private static function validateAndNormalize(array $data): array
    {
        $width = max(1, min(2000, (int) ($data['width_mm'] ?? 100)));
        $length = max(1, min(2000, (int) ($data['length_mm'] ?? 100)));
        $height = max(1, min(2000, (int) ($data['height_mm'] ?? 100)));
        $weight = max(10, min(50000, (int) ($data['weight_g'] ?? 500)));

        // Sum L+W+H <= 2500
        $sum = $width + $length + $height;
        if ($sum > 2500) {
            $scale = 2500 / $sum;
            $width = (int) round($width * $scale);
            $length = (int) round($length * $scale);
            $height = (int) round($height * $scale);
        }

        return [
            'width_mm' => $width,
            'length_mm' => $length,
            'height_mm' => $height,
            'weight_g' => $weight,
        ];
    }
}
