<?php

class DraftRepository
{
    private static function getStoragePath()
    {
        return __DIR__ . '/../storage/marketplace/ozon/tmp';
    }

    private static function getDraftPath($draftId)
    {
        return self::getStoragePath() . '/' . $draftId . '.json';
    }

    private static function ensureDirectory()
    {
        $path = self::getStoragePath();
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    public static function createDraft($locale)
    {
        self::ensureDirectory();
        
        // Generate draftId
        if (function_exists('random_bytes')) {
            $draftId = bin2hex(random_bytes(8));
        } else {
            $draftId = bin2hex(openssl_random_pseudo_bytes(8));
        }
        
        $formSchema = OzonFormSchema::getSchema($locale);
        
        // Build default editedJson from schema
        $editedJson = [
            'title' => '',
            'brand' => '',
            'images' => []
        ];
        
        $draft = [
            'draftId' => $draftId,
            'locale' => $locale,
            'formSchema' => $formSchema,
            'editedJson' => $editedJson,
            'images' => [],
            'version' => 1,
            'updatedAt' => gmdate('c')
        ];
        
        self::saveDraftFile($draftId, $draft);
        
        return $draft;
    }

    public static function getDraft($draftId, $locale)
    {
        $path = self::getDraftPath($draftId);
        if (!file_exists($path)) {
            return null;
        }
        
        $content = file_get_contents($path);
        $draft = json_decode($content, true);
        
        if (!$draft) {
            return null;
        }
        
        // Refresh formSchema with current locale
        $draft['formSchema'] = OzonFormSchema::getSchema($locale);
        
        return $draft;
    }

    public static function saveDraft($draftId, $editedJson)
    {
        $path = self::getDraftPath($draftId);
        if (!file_exists($path)) {
            return null;
        }
        
        $content = file_get_contents($path);
        $draft = json_decode($content, true);
        
        if (!$draft) {
            return null;
        }
        
        $draft['editedJson'] = $editedJson;
        $draft['version'] = isset($draft['version']) ? $draft['version'] + 1 : 1;
        $draft['updatedAt'] = gmdate('c');
        
        self::saveDraftFile($draftId, $draft);
        
        return $draft;
    }

    public static function addImage($draftId, $image)
    {
        $path = self::getDraftPath($draftId);
        if (!file_exists($path)) {
            return null;
        }
        
        $content = file_get_contents($path);
        $draft = json_decode($content, true);
        
        if (!$draft) {
            return null;
        }
        
        if (!isset($draft['images'])) {
            $draft['images'] = [];
        }
        
        $draft['images'][] = $image;
        $draft['updatedAt'] = gmdate('c');
        
        self::saveDraftFile($draftId, $draft);
        
        return $draft;
    }

    public static function deleteImage($draftId, $imageId)
    {
        $path = self::getDraftPath($draftId);
        if (!file_exists($path)) {
            return null;
        }
        
        $content = file_get_contents($path);
        $draft = json_decode($content, true);
        
        if (!$draft) {
            return null;
        }
        
        if (!isset($draft['images'])) {
            $draft['images'] = [];
        }
        
        $draft['images'] = array_filter($draft['images'], function($img) use ($imageId) {
            return isset($img['imageId']) && $img['imageId'] !== $imageId;
        });
        $draft['images'] = array_values($draft['images']);
        $draft['updatedAt'] = gmdate('c');
        
        // Delete physical file if exists
        $imageDir = self::getStoragePath() . '/' . $draftId . '/images';
        if (is_dir($imageDir)) {
            $files = glob($imageDir . '/' . $imageId . '.*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        self::saveDraftFile($draftId, $draft);
        
        return $draft;
    }

    private static function saveDraftFile($draftId, $draft)
    {
        self::ensureDirectory();
        $path = self::getDraftPath($draftId);
        file_put_contents($path, json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
