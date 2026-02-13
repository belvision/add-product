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
                    'key' => 'main',
                    'label' => [
                        'ru' => 'Основная информация',
                        'en' => 'Main information'
                    ],
                    'fields' => [
                        [
                            'key' => 'title',
                            'type' => 'string',
                            'label' => [
                                'ru' => 'Название товара',
                                'en' => 'Product title'
                            ],
                            'required' => true,
                            'constraints' => [
                                'minLen' => 3,
                                'maxLen'  => 200
                            ]
                        ],
                        [
                            'key' => 'brand',
                            'type' => 'select',
                            'label' => [
                                'ru' => 'Бренд',
                                'en' => 'Brand'
                            ],
                            'required' => true,
                            'options' => [
                                ['value' => 'demo_brand_1', 'label' => ['ru' => 'Демо бренд 1', 'en' => 'Demo brand 1']],
                                ['value' => 'demo_brand_2', 'label' => ['ru' => 'Демо бренд 2', 'en' => 'Demo brand 2']]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
