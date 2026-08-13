<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\theme;

use Besnovatyj\Contracts\theme\ViewSourcesManifest;
use Besnovatyj\Contracts\theme\ViewVariantsManifest;
use Besnovatyj\Helpers\ArrayExportHelper;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Yii;

/**
 * Производитель артефакта вариантов представления `viewVariants.{theme}.php`.
 *
 * Сканирует оверлеи активной темы и строит мапу `slot => [ключ => метка]` по конвенции
 * {@see ViewVariantsManifest}. Работает ТОЛЬКО на генерацию (recompile/`Themes/cache/flush`),
 * никогда на обычный запрос — чтение делает лёгкий {@see ViewVariantsReader} без обхода ФС.
 *
 * Симметричен {@see ThemePathMapService}: тот композитит `pathMap` (ось модулей × тема), этот —
 * плоский индекс выбираемых шаблонов той же темы. Оба тема-зависимы и кэшируются per-theme.
 */
final class ViewVariantsService
{
    /**
     * Базовый путь артефакта (алиас). Реальные файлы — тема-зависимые `viewVariants.{theme}.php`
     * рядом. Единый источник истины пути для производителя и {@see ViewVariantsReader}: артефакт
     * никем вне пакета тем не читается, поэтому путь локален, а не в глобальных params приложения.
     */
    public const string ARTIFACT_BASE = '@config-dyn-gen/viewVariants.php';

    public function __construct(
        private readonly ArrayExportHelper $exporter = new ArrayExportHelper(),
    ) {}

    /**
     * Пересобрать артефакты всех тем, присутствующих в `@themes/*`.
     */
    public function rebuildAll(): void
    {
        $themesDir = (string)Yii::getAlias('@themes');
        foreach (glob($themesDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $this->rebuild(basename($dir));
        }
    }

    /**
     * Пересобрать и сохранить артефакт одной темы.
     *
     * @return array<string, array<string,string>> собранная мапа `slot => [ключ => метка]`
     */
    public function rebuild(string $themeName): array
    {
        $map = $this->scan($themeName);
        $file = $this->artifactFile($themeName);

        if ($map === []) {
            // Пустую тему не материализуем: ридер трактует отсутствие файла как «вариантов нет».
            $this->deleteFile($file);
            return $map;
        }

        $this->exporter->saveToFile($map, $file);
        $this->invalidateOpcache($file);

        return $map;
    }

    /**
     * Удалить артефакты всех тем (`viewVariants.*.php`).
     */
    public function invalidateAll(): void
    {
        $dir = dirname($this->artifactFile('_'));
        foreach (glob($dir . '/viewVariants.*.php') ?: [] as $file) {
            $this->deleteFile($file);
        }
    }

    // -------------------------------------------------------------------------------------------

    /**
     * Обойти оверлеи темы и собрать все каталоги `*.variants`.
     *
     * @return array<string, array<string,string>>
     */
    private function scan(string $themeName): array
    {
        $themeBase = (string)Yii::getAlias('@themes') . '/' . $themeName;
        $map = [];

        // Ось модулей: @themes/{theme}/modules/{ModuleId}/views/**
        $modulesDir = $themeBase . '/modules';
        foreach (glob($modulesDir . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
            $this->collectFrom($moduleDir . '/views', basename($moduleDir), $map);
        }

        // Ось приложения: @themes/{theme}/views/**
        $this->collectFrom($themeBase . '/views', ViewSourcesManifest::APP_VIEWS_KEY, $map);

        ksort($map);
        return $map;
    }

    /**
     * Рекурсивно найти под `$viewsRoot` каталоги `*.variants` и добавить их слоты в `$map`.
     *
     * @param string                                    $viewsRoot корень `views/` (модуля или приложения)
     * @param string                                    $moduleId  сегмент модуля для слота
     * @param array<string, array<string,string>>       $map       аккумулятор (по ссылке)
     */
    private function collectFrom(string $viewsRoot, string $moduleId, array &$map): void
    {
        if (!is_dir($viewsRoot)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        $suffix = ViewVariantsManifest::VARIANTS_DIR_SUFFIX;
        foreach ($iterator as $item) {
            if (!$item->isDir() || !str_ends_with($item->getFilename(), $suffix)) {
                continue;
            }

            $variantsDir = $item->getPathname();
            // Путь базового представления относительно views/: снимаем суффикс `.variants` с хвоста.
            $relative = ltrim(str_replace('\\', '/', substr($variantsDir, strlen($viewsRoot))), '/');
            $viewPath = substr($relative, 0, -strlen($suffix));

            $variants = $this->readVariants($variantsDir);
            if ($variants !== []) {
                $map[ViewVariantsManifest::slot($moduleId, $viewPath)] = $variants;
            }
        }
    }

    /**
     * Прочитать один каталог вариантов: файлы `*.php` (кроме меток) → `ключ => метка`.
     *
     * @return array<string,string>
     */
    private function readVariants(string $variantsDir): array
    {
        $labels = $this->readLabels($variantsDir);
        $variants = [];

        foreach (glob($variantsDir . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            if ($key === basename(ViewVariantsManifest::LABELS_FILE, '.php')) {
                continue;
            }
            $variants[$key] = $labels[$key] ?? $key;
        }

        ksort($variants);
        return $variants;
    }

    /**
     * Метки вариантов из `_labels.php`, если он есть.
     *
     * @return array<string,string>
     */
    private function readLabels(string $variantsDir): array
    {
        $file = $variantsDir . '/' . ViewVariantsManifest::LABELS_FILE;
        if (!is_file($file)) {
            return [];
        }

        $labels = require $file;
        return is_array($labels) ? $labels : [];
    }

    private function artifactFile(string $themeName): string
    {
        $base = (string)Yii::getAlias(self::ARTIFACT_BASE);
        return ViewVariantsManifest::themedFile($base, $themeName);
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
