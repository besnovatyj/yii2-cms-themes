<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\theme;

use Besnovatyj\Contracts\theme\ViewSourcesManifest;
use Besnovatyj\Helpers\ArrayExportHelper;
use Yii;

/**
 * Строит и кэширует карту представлений (`pathMap`) активной темы.
 *
 * Инкапсулирует всю работу с двумя осями темизации:
 *  - **ось модулей** — тема-НЕзависимый манифест `moduleViewSources.php` (генерит modman при
 *    recompile): плоская мапа `moduleId => sourceAlias`;
 *  - **ось темы** — активная тема, поверх которой накладывается оверлей по единой конвенции
 *    {@see ViewSourcesManifest::overlaySubPath()} (`modules/{moduleId}/views`).
 *
 * Кэш — тема-зависимый файл `themePathMap.{theme}.php`. Благодаря этому переключение темы просто
 * берёт другой файл (нет устаревания, как в старом `Theme` с одним общим кэшем). Изменение набора
 * модулей ловится сравнением mtime кэша и манифеста — без прямого вызова из modman.
 *
 * Никакой рефлексии и парсинга `composer.json`: путь к `views/` уже вычислен генератором в виде
 * алиаса, а Yii резолвит алиасы в ключах/значениях `pathMap` сам.
 */
final class ThemePathMapService
{
    public function __construct(
        private readonly ArrayExportHelper $exporter = new ArrayExportHelper(),
    ) {}

    /**
     * Имя активной темы из конфигурации фронта (fallback — 'basic').
     */
    public function activeThemeName(): string
    {
        $configPath = Yii::getAlias(Yii::$app->params['frontThemeConfigFile']);
        $config = is_file($configPath) ? require $configPath : null;

        return is_array($config) && !empty($config['themeName'])
            ? (string)$config['themeName']
            : 'basic';
    }

    /**
     * Карта представлений для темы с ленивой генерацией и mtime-инвалидацией против манифеста.
     *
     * @return array<string, string[]> совместимо с `yii\base\Theme::$pathMap`
     */
    public function pathMapFor(string $themeName): array
    {
        $cacheFile = $this->cacheFile($themeName);

        if ($this->isFresh($cacheFile)) {
            $map = require $cacheFile;
            if (is_array($map)) {
                return $map;
            }
        }

        $map = $this->build($themeName);
        $this->exporter->saveToFile($map, $cacheFile);
        $this->invalidateOpcache($cacheFile);

        return $map;
    }

    /**
     * Сбросить кэш одной темы.
     */
    public function invalidate(string $themeName): void
    {
        $this->deleteFile($this->cacheFile($themeName));
    }

    /**
     * Сбросить кэш всех тем (`themePathMap.*.php`).
     */
    public function invalidateAll(): void
    {
        $dir = dirname($this->cacheFile('_'));
        foreach (glob($dir . '/themePathMap.*.php') ?: [] as $file) {
            $this->deleteFile($file);
        }
    }

    // -------------------------------------------------------------------------------------------

    /**
     * Композиция манифеста источников и активной темы в `pathMap`.
     *
     * @return array<string, string[]>
     */
    private function build(string $themeName): array
    {
        $map = [];

        foreach ($this->readManifest() as $key => $sourceAlias) {
            if ($key === ViewSourcesManifest::APP_VIEWS_KEY) {
                // Корневые представления приложения: сначала {theme}/views, потом исходные @app/views.
                $map[ViewSourcesManifest::APP_VIEWS_KEY] = [
                    "@themes/{$themeName}/views",
                    ViewSourcesManifest::APP_VIEWS_KEY,
                ];
                continue;
            }

            $overlay = "@themes/{$themeName}/" . ViewSourcesManifest::overlaySubPath($key);
            // В карту попадает только реально существующий в теме оверлей; иначе Yii и так возьмёт
            // исходную директорию модуля по умолчанию — лишняя запись не нужна.
            if (is_dir((string)Yii::getAlias($overlay))) {
                $map[$sourceAlias] = [$overlay, $sourceAlias];
            }
        }

        return $map;
    }

    /**
     * Тема-независимый манифест источников представлений. Если ещё не сгенерирован modman'ом —
     * минимально возвращаем корневой ключ приложения.
     *
     * @return array<string, string>
     */
    private function readManifest(): array
    {
        $file = Yii::getAlias(Yii::$app->params['moduleViewSourcesFile']);
        if (is_file($file)) {
            $data = require $file;
            if (is_array($data)) {
                return $data;
            }
        }

        return [ViewSourcesManifest::APP_VIEWS_KEY => ''];
    }

    /**
     * Тема-зависимый путь кэша: `<dir>/themePathMap.{theme}.php`, где `<dir>` — каталог базового
     * параметра `themePathMap`.
     */
    private function cacheFile(string $themeName): string
    {
        $base = Yii::getAlias(Yii::$app->params['themePathMap']);

        return dirname($base) . '/themePathMap.' . $themeName . '.php';
    }

    /**
     * Кэш свеж, если существует и не старее манифеста источников.
     */
    private function isFresh(string $cacheFile): bool
    {
        if (!is_file($cacheFile)) {
            return false;
        }

        $manifest = Yii::getAlias(Yii::$app->params['moduleViewSourcesFile']);
        if (!is_file($manifest)) {
            return true; // нечему инвалидировать — доверяем кэшу
        }

        return filemtime($cacheFile) >= filemtime($manifest);
    }

    private function deleteFile(string $file): void
    {
        if (is_file($file)) {
            @unlink($file);
            $this->invalidateOpcache($file);
        }
    }

    private function invalidateOpcache(string $file): void
    {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }
}
