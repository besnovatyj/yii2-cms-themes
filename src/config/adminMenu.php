<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Contracts\adminMenu\AdminMenuLocation;
use Besnovatyj\Contracts\adminMenu\AdminMenuPlacement;

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
                new AdminMenuPlacement(
                    location: AdminMenuLocation::RightSidebar,
                    group: 'Service',
                    groupIcon: 'bi bi-sliders',
                    groupPriority: 100,
                    priority: 100,
                ),
            ],
        ],
    ],
];
