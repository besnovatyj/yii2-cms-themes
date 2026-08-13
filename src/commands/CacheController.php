<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\commands;

use Besnovatyj\Themes\theme\ThemePathMapService;
use Besnovatyj\Themes\theme\ViewVariantsService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Консольное управление кэшем темизации.
 *
 * Живёт в пакете (namespace `commands` модуля `CmsModule`), а не в app-скелете: команда — это
 * поведение пакета тем, поэтому она уезжает вместе с ним (см. ANALYSIS_MODULES_INTEGRATION.MD, П8).
 * Вызов идёт по конвенции модульных команд, без `controllerMap`:
 *
 *  - `php yii Themes/cache/flush` — сбросить кэш `pathMap` всех тем.
 *
 * Ровно тот же способ адресации, что и у `Modman/modules/recompile`. Обычно отдельно звать не нужно:
 * `Modman/modules/recompile` уже инвалидирует `pathMap`, а {@see ThemePathMapService::isFresh()}
 * протухает кэш по mtime манифеста. Команда — точечный ручной сброс.
 */
final class CacheController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly ThemePathMapService $pathMaps,
        private readonly ViewVariantsService $viewVariants,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Сбросить кэш карты представлений (`themePathMap.*.php`) всех тем и пересобрать артефакт
     * выбираемых вариантов (`viewVariants.*.php`).
     *
     * pathMap самовосстанавливается по mtime манифеста, поэтому его достаточно инвалидировать. Артефакт
     * вариантов зависит от файлов самой темы (изменение набора модулей его не трогает), а ридер его не
     * генерирует — поэтому здесь пересобираем сразу, чтобы добавленный/удалённый `*.variants` подхватился.
     */
    public function actionFlush(): int
    {
        $this->pathMaps->invalidateAll();
        $this->stdout("Theme pathMap cache cleared\n");

        $this->viewVariants->invalidateAll();
        $this->viewVariants->rebuildAll();
        $this->stdout("View variants artifact rebuilt\n");

        return ExitCode::OK;
    }
}
