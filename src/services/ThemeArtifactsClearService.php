<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\services;

use yii\helpers\FileHelper;

/**
 * Сервис получения размера и удаления генерируемых артефактов темизации.
 *
 * Артефакты — тема-зависимые файлы в `@config-dyn-gen`: карты представлений `themePathMap.*.php`
 * (композиция {@see \Besnovatyj\Themes\theme\ThemePathMapService}) и выбираемых вариантов
 * `viewVariants.*.php` (сканер {@see \Besnovatyj\Themes\theme\ViewVariantsService}). Здесь ТОЛЬКО
 * удаление — без перегенерации: pathMap самовосстановится лениво на следующем запросе по mtime,
 * артефакт вариантов пересоберётся при `Themes/cache/flush` или из админки.
 * Это интеграция с модулем очистки (ClearManager).
 *
 * Каталог и fnmatch-шаблоны имён приходят извне (см. config/container.php) — класс не завязан на
 * `Yii::getAlias` и остаётся чистым для DI и тестов.
 */
final class ThemeArtifactsClearService
{
    /**
     * @param string   $artifactsDir абсолютный путь каталога артефактов (`@config-dyn-gen`)
     * @param string[] $only         fnmatch-шаблоны имён файлов-артефактов (для `FileHelper::findFiles`)
     */
    public function __construct(
        private readonly string $artifactsDir,
        private readonly array $only,
    ) {
    }

    /**
     * Суммарный отформатированный размер артефактов темизации.
     */
    public function getData(): string
    {
        $bytes = 0;
        foreach ($this->files() as $file) {
            $bytes += filesize($file);
        }

        return $this->formatBytes($bytes);
    }

    /**
     * Удалить все файлы-артефакты темизации (без перегенерации).
     *
     * @return bool false — если хотя бы один существующий файл удалить не удалось
     */
    public function clearData(): bool
    {
        $ok = true;
        foreach ($this->files() as $file) {
            if (!FileHelper::unlink($file)) {
                $ok = false;
                continue;
            }
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
        }

        return $ok;
    }

    /**
     * Существующие файлы-артефакты каталога, отобранные по fnmatch-шаблонам имён.
     *
     * @return string[]
     */
    private function files(): array
    {
        if (!is_dir($this->artifactsDir)) {
            return [];
        }

        return FileHelper::findFiles($this->artifactsDir, [
            'only'      => $this->only,
            'recursive' => false,
        ]);
    }

    /**
     * Форматирует размер в байтах в читаемый вид.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $size = (float)$bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $size, $units[$unitIndex]);
    }
}
