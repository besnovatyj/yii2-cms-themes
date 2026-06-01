<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

return [
    // Themes
    [
        'label' => 'Themes',
        'iconClass' => 'bi bi-palette me-1',
        'url' => ['/Themes/backend/theme/index'],
        'active' => static function () {
            return str_contains(\Yii::$app->request->url, 'Themes/backend/theme');
        },
        '_meta' => [
            'placements' => [
                [
                    'location' => 'right-sidebar',
                    'group' => 'Service',
                    'groupIcon' => 'bi bi-sliders',
                    'priority' => 100,
                    'groupPriority' => 100,
                ],
            ],
        ],
    ],
];
