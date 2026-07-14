<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Themes\theme\Theme;

/**
 * Пер-аппликационный вклад модуля тем в приложение app-frontend (движок yiisoft/config).
 *
 * Подключает компонент темизации `view.theme` как ВКЛАД ПАКЕТА, а не хардкодом в app-скелете
 * (`frontend/config/main.php`). Смысл — «инверсия владения» (см. ANALYSIS_MODULES_INTEGRATION.MD,
 * П8): темизация не входит в минимальные зависимости ядра (kernel/contracts/modman/yiisoft-config),
 * поэтому ядро обязано подниматься и БЕЗ этого пакета. При отсутствии пакета вклад просто не
 * применяется — фронт рендерит `@app/views` напрямую (мягкая деградация), а не падает
 * `class not found`.
 *
 * {@see Theme::init()} сам берёт активную тему, `basePath` и `pathMap` из `ThemePathMapService`.
 */
return [
    'components' => [
        'view' => [
            'theme' => [
                'class' => Theme::class,
            ],
        ],
    ],
];
