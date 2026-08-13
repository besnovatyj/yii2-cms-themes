<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

return [
    'id' => 'Themes',
    'params' => [
        'iconClass' => 'bi bi-sliders',

        'directories' => false, // Если для работы модуля необходимы директории для статики

        // Интеграция с модулем очистки (ClearManager): удаление генерируемых артефактов темизации
        // (карты представлений `themePathMap.*.php` и вариантов `viewVariants.*.php` в @config-dyn-gen).
        // Собирается EndpointCollectorService из params модуля в рантайме — переустановка не нужна.
        'endpoints' => [
            'clear' => [
                'artifacts' => [
                    'rowTitle' => 'Артефакты темизации (карты представлений и вариантов)',
                    'getData'  => '/Themes/backend/clear/get-data',
                    'clear'    => '/Themes/backend/clear/clear-data',
                ],
            ],
        ],
    ],
];
