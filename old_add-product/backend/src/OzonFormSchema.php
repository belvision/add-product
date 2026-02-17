<?php

class OzonFormSchema
{
    public static function getSchema($locale)
    {
        $locale = in_array($locale, ['ru', 'en']) ? $locale : 'ru';
        
        return [
            'schemaVersion' => 1,
            'locale' => $locale,
            'steps' => [
                [
                    'key' => 'description_images',
                    'label' => [
                        'ru' => 'Описание и изображения',
                        'en' => 'Description and images'
                    ],
                    'fields' => [
                        [
                            'key' => 'description',
                            'type' => 'string',
                            'label' => [
                                'ru' => 'Описание',
                                'en' => 'Description'
                            ],
                            'required' => false,
                            'constraints' => ['maxLen' => 5000],
                            'textarea' => true
                        ],
                        [
                            'key' => 'image_list',
                            'type' => 'image_list',
                            'label' => [
                                'ru' => 'Изображения товара',
                                'en' => 'Product images'
                            ],
                            'required' => true,
                            'constraints' => [
                                'minItems' => 1,
                                'maxItems' => 10
                            ]
                        ]
                    ]
                ],
                [
                    'key' => 'category',
                    'label' => [
                        'ru' => 'Категория',
                        'en' => 'Category'
                    ],
                    'fields' => []
                ]
            ]
        ];
    }
}
