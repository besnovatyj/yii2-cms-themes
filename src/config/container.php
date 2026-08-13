<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Themes\services\ThemeArtifactsClearService;
use Besnovatyj\Themes\theme\ViewVariantsService;

/**
 * DI-проводка модуля темизации (способ A: грузится лениво при инициализации модуля Themes —
 * достаточно для бэкенд-контроллёра интеграции с очисткой {@see \Besnovatyj\Themes\controllers\backend\ClearController}).
 *
 * Каталог и fnmatch-шаблоны имён выводятся из авторитетных источников путей артефактов (а не
 * хардкодом), чтобы {@see ThemeArtifactsClearService} оставался чистым (без `Yii::getAlias` внутри),
 * как `BlogCacheClearService`. Оба артефакта живут в одном каталоге `@config-dyn-gen`.
 */
return function (\yii\di\Container $container): void {
    $container->setSingleton(ThemeArtifactsClearService::class, static function (): ThemeArtifactsClearService {
        $pathMapBase = (string)Yii::getAlias(Yii::$app->params['themePathMap']);
        $variantsBase = (string)Yii::getAlias(ViewVariantsService::ARTIFACT_BASE);

        return new ThemeArtifactsClearService(
            dirname($pathMapBase),
            [
                basename($pathMapBase, '.php') . '.*.php',   // themePathMap.*.php
                basename($variantsBase, '.php') . '.*.php',  // viewVariants.*.php
            ],
        );
    });
};
