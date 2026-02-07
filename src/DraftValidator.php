<?php

class DraftValidator
{
    public static function validate($formSchema, $editedJson, $images)
    {
        $errors = [];
        $locale = isset($formSchema['locale']) ? $formSchema['locale'] : 'ru';
        
        foreach ($formSchema['steps'] as $step) {
            foreach ($step['fields'] as $field) {
                $key = $field['key'];
                $value = isset($editedJson[$key]) ? $editedJson[$key] : null;
                
                // Required check
                if (isset($field['required']) && $field['required']) {
                    if ($field['type'] === 'image_list') {
                        if (empty($images) || count($images) === 0) {
                            $errors[$key] = $locale === 'ru' 
                                ? 'Поле обязательно для заполнения' 
                                : 'Field is required';
                        }
                    } else {
                        if ($value === null || $value === '') {
                            $errors[$key] = $locale === 'ru' 
                                ? 'Поле обязательно для заполнения' 
                                : 'Field is required';
                        }
                    }
                }
                
                // Skip further validation if already has error
                if (isset($errors[$key])) {
                    continue;
                }
                
                // String length validation
                if ($field['type'] === 'string' && $value !== null && $value !== '') {
                    if (isset($field['constraints']['minLen'])) {
                        if (mb_strlen($value) < $field['constraints']['minLen']) {
                            $errors[$key] = $locale === 'ru'
                                ? 'Минимальная длина: ' . $field['constraints']['minLen']
                                : 'Minimum length: ' . $field['constraints']['minLen'];
                        }
                    }
                    if (isset($field['constraints']['maxLen'])) {
                        if (mb_strlen($value) > $field['constraints']['maxLen']) {
                            $errors[$key] = $locale === 'ru'
                                ? 'Максимальная длина: ' . $field['constraints']['maxLen']
                                : 'Maximum length: ' . $field['constraints']['maxLen'];
                        }
                    }
                }
                
                // Image list items count
                if ($field['type'] === 'image_list') {
                    $imageCount = count($images);
                    if (isset($field['constraints']['minItems'])) {
                        if ($imageCount < $field['constraints']['minItems']) {
                            $errors[$key] = $locale === 'ru'
                                ? 'Минимум изображений: ' . $field['constraints']['minItems']
                                : 'Minimum images: ' . $field['constraints']['minItems'];
                        }
                    }
                    if (isset($field['constraints']['maxItems'])) {
                        if ($imageCount > $field['constraints']['maxItems']) {
                            $errors[$key] = $locale === 'ru'
                                ? 'Максимум изображений: ' . $field['constraints']['maxItems']
                                : 'Maximum images: ' . $field['constraints']['maxItems'];
                        }
                    }
                }
            }
        }
        
        if (empty($errors)) {
            return [
                'valid' => true,
                'errors' => []
            ];
        }
        
        return [
            'valid' => false,
            'errors' => [
                'fieldErrors' => $errors
            ]
        ];
    }
}
