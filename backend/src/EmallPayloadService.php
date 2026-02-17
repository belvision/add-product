<?php

/**
 * Builds eMall API product payload (batch format).
 * stock default 30, price must not be zero.
 */
class EmallPayloadService
{
    private const DEFAULT_STOCK = 30;

    /**
     * Ensure strings are valid UTF-8 so json_encode doesn't silently return false.
     * @param mixed $v
     * @return mixed
     */
    private static function sanitizeUtf8($v)
    {
        if (is_string($v)) {
            // preg_match('//u') fails when string contains invalid UTF-8 sequences
            if (!preg_match('//u', $v)) {
                if (function_exists('iconv')) {
                    $fixed = @iconv('UTF-8', 'UTF-8//IGNORE', $v);
                    if ($fixed !== false) {
                        return $fixed;
                    }
                }
                // Fallback: drop control chars
                return (string) @preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
            }
            return $v;
        }
        if (is_array($v)) {
            foreach ($v as $k => $vv) {
                $v[$k] = self::sanitizeUtf8($vv);
            }
            return $v;
        }
        return $v;
    }

    /**
     * eMall API accepts numeric barcodes.
     * In docs example barcode is 8 digits (e.g. "11885828"), so we treat 8 digits as valid.
     * We also support EAN-13 (12+check or 13 digits) if provided by the user.
     * If barcode is missing/invalid -> generate a random 8-digit numeric barcode.
     */
    private static function normalizeBarcode(string $barcode): string
    {
        $barcode = is_string($barcode) ? $barcode : '';
        $barcode = preg_replace('/\D+/', '', $barcode);

        // 8-digit barcode (as in eMall docs) is considered valid.
        if (strlen($barcode) === 8) {
            return $barcode;
        }

        // If 12 digits were provided, complete as EAN-13
        if (strlen($barcode) === 12) {
            return $barcode . self::ean13CheckDigit($barcode);
        }

        // If 13 digits were provided, keep only if checksum matches
        if (strlen($barcode) === 13) {
            $base = substr($barcode, 0, 12);
            $check = substr($barcode, 12, 1);
            $calc = self::ean13CheckDigit($base);
            return ($check === $calc) ? $barcode : ($base . $calc);
        }

        // Fallback: generate an 8-digit numeric barcode.
        // Avoid leading zeros (some validators treat them oddly).
        return (string) random_int(10000000, 99999999);
    }

    /**
     * Calculate EAN-13 check digit for 12-digit base.
     */
    private static function ean13CheckDigit(string $base12): string
    {
        $base12 = preg_replace('/\D+/', '', $base12);
        $base12 = str_pad(substr((string) $base12, 0, 12), 12, '0', STR_PAD_LEFT);

        $sumOdd = 0;
        $sumEven = 0;
        // positions are 1-based from left
        for ($i = 0; $i < 12; $i++) {
            $d = (int) $base12[$i];
            if ((($i + 1) % 2) === 1) {
                $sumOdd += $d;
            } else {
                $sumEven += $d;
            }
        }
        $sum = $sumOdd + ($sumEven * 3);
        $mod = $sum % 10;
        $check = ($mod === 0) ? 0 : (10 - $mod);
        return (string) $check;
    }

    /**
     * Build product + batch payload from draft step_state and related data.
     *
     * @param array $draft Draft from DraftRepository::getDraft
     * @param string $appBaseUrl Base URL for image paths (e.g. https://add.logistgo.pro)
     * @return array{product: array, batchPayload: string, errors: string[]}
     */
    public static function build(array $draft, string $appBaseUrl = ''): array
    {
        $errors = [];
        $stepState = $draft['step_state'] ?? [];
        $stepState = is_array($stepState) ? $stepState : [];
        $editedJson = $draft['editedJson'] ?? [];
        $editedJson = is_array($editedJson) ? $editedJson : [];
        if (isset($stepState['attributes']) && is_array($stepState['attributes'])) {
            $editedJson = array_merge($editedJson, $stepState['attributes']);
        }

        $filled = $draft['filled_fields'] ?? null;
        $filled = is_array($filled) ? $filled : (is_string($filled) ? (json_decode($filled, true) ?: []) : []);
        $genText = $filled['generated_text'] ?? $filled['edited_text'] ?? $stepState['generated_text'] ?? [];
        $genText = is_array($genText) ? $genText : [];

        $name = trim((string) ($genText['title'] ?? ''));
        $descParts = array_filter([
            (string) ($genText['description'] ?? ''),
            (string) ($genText['benefits'] ?? ''),
            (string) ($genText['usage'] ?? ''),
        ]);
        $description = implode("\n\n", $descParts);
        if ($description === '') {
            $description = trim((string) ($editedJson['description'] ?? $draft['description'] ?? ''));
        }

        $innerArticle = trim((string) ($editedJson['inner_article'] ?? $editedJson['artikul'] ?? ''));
        if ($innerArticle === '') {
            $innerArticle = str_replace('-', '', $draft['draftId'] ?? $draft['id'] ?? '') ?: 'draft';
        }
        $barcode = self::normalizeBarcode((string) ($editedJson['barcode'] ?? ''));

        $priceData = $stepState['price'] ?? [];
        $price = isset($priceData['price']) ? (float) $priceData['price'] : 0.0;
        $oldPrice = isset($priceData['old_price']) ? (float) $priceData['old_price'] : 0.0;
        if ($price <= 0) {
            $errors[] = 'Цена должна быть больше нуля';
        }
        $stock = isset($editedJson['stock']) ? (int) $editedJson['stock'] : self::DEFAULT_STOCK;
        if ($stock <= 0) {
            $stock = self::DEFAULT_STOCK;
        }

        $priceApi = (int) round($price * 100);
        $oldPriceApi = (int) round($oldPrice * 100);
        if ($priceApi <= 0) {
            $priceApi = 100;
        }
        if ($oldPriceApi <= 0) {
            $oldPriceApi = (int) round($priceApi * 1.2);
        }

        $dim = $stepState['dimensions'] ?? [];
        $length = (float) ($dim['length_mm'] ?? 100);
        $width = (float) ($dim['width_mm'] ?? 100);
        $height = (float) ($dim['height_mm'] ?? 100);
        $weight = (float) ($dim['weight_g'] ?? 100);

        $brand = $stepState['brand'] ?? [];
        $brandId = isset($brand['id']) ? (int) $brand['id'] : 0;
        $brandName = trim((string) ($brand['name'] ?? ''));
        $importer = trim((string) ($editedJson['importer'] ?? ''));
        if ($importer === '') {
            $importer = $brandName ?: 'ИП Гаев';
        }

        $country = $stepState['country'] ?? [];
        $countryId = isset($country['id']) ? (int) $country['id'] : 0;

        $category = $stepState['category'] ?? $draft['chosen_category'] ?? [];
        $category = is_array($category) ? $category : [];
        $categoryId = (int) ($category['category_id'] ?? 0);

        $images = self::resolveImageUrls($draft, $stepState, $appBaseUrl);
        if (empty($images)) {
            $errors[] = 'Добавьте хотя бы одно изображение (шаг 1)';
        }

        $properties = self::buildProperties($stepState['category_properties'] ?? [], $errors);

        $product = [
            'name' => $name,
            'inner_article' => $innerArticle,
            'barcode' => $barcode,
            'description' => $description,
            'dimensions' => [
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'weight' => $weight,
            ],
            'images' => $images,
            'prices' => [
                'price' => $priceApi,
                'old_price' => $oldPriceApi,
            ],
            'stock' => $stock,
            'brand_id' => $brandId,
            'brand' => $brandName,
            'country_id' => $countryId,
            'category_id' => $categoryId,
            'importer' => $importer,
            'importer_name' => $importer,
            'manufacturer_name' => $brandName,
            'warranty_unit' => 'none',
            'is_adult' => false,
            'vat' => 0,
            'properties' => $properties,
        ];

        $batch = [
            'items' => [
                ['is_bunched' => false, 'products' => [$product]],
            ],
        ];

        // Guard against invalid UTF-8 in DB/user input (otherwise json_encode returns false => empty body => eMall: “not JSON”).
        $batch = self::sanitizeUtf8($batch);

        $batchPayload = json_encode(
            $batch,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($batchPayload === false) {
            $errors[] = 'Ошибка формирования JSON: ' . json_last_error_msg();
            // Return an explicit non-empty JSON (so upstream won't say “not JSON”).
            $batchPayload = '{"items":[]}';
        }

        return [
            'product' => $product,
            'batchPayload' => $batchPayload,
            'errors' => $errors,
        ];
    }

    /**
     * Resolve image URLs for product payload.
     * Uses explicit url from step_state, or builds URL from appBaseUrl + draft images.
     *
     * @param array $draft
     * @param array $stepState
     * @param string $appBaseUrl
     * @return array<string>
     */
    private static function resolveImageUrls(array $draft, array $stepState, string $appBaseUrl): array
    {
        $images = [];
        // draft.images = canonical source from draft_images table (actual uploads)
        // step_state.images can be overwritten with [] when saving, so prefer draft.images
        $imgList = !empty($draft['images']) ? $draft['images'] : ($stepState['images'] ?? []);
        if (!is_array($imgList)) {
            return [];
        }
        $draftId = $draft['draftId'] ?? $draft['id'] ?? '';
        $base = rtrim($appBaseUrl, '/');
        foreach ($imgList as $img) {
            $url = $img['url'] ?? null;
            if ($url && is_string($url) && $url !== '') {
                $images[] = $url;
                continue;
            }
            $imageId = $img['imageId'] ?? null;
            if ($imageId && $base !== '' && $draftId !== '') {
                // eMall API requires image URLs to have extension (png, jpg, jpeg, webp)
                $images[] = $base . '/api/drafts/' . $draftId . '/images/' . $imageId . '.jpg';
            }
        }
        return $images;
    }

    /**
     * @param array $categoryProperties [['id'=>int,'value'=>mixed},...]
     * @param array $errors
     * @return array<int|string, mixed> Property id => value for API
     */
    private static function buildProperties(array $categoryProperties, array &$errors): array
    {
        $out = [];
        foreach ($categoryProperties as $p) {
            $pid = $p['id'] ?? null;
            if ($pid === null) {
                continue;
            }
            $val = $p['value'] ?? null;
            if ($val === null || $val === '') {
                continue;
            }
            if (is_array($val)) {
                $filtered = array_values(array_filter($val, function ($v) {
                    return $v !== null && $v !== '';
                }));
                if (!empty($filtered)) {
                    $out[(string) $pid] = $filtered;
                }
            } elseif (is_bool($val)) {
                $out[(string) $pid] = $val;
            } elseif (is_numeric($val)) {
                $out[(string) $pid] = $val;
            } else {
                $out[(string) $pid] = (string) $val;
            }
        }
        return $out;
    }
}
