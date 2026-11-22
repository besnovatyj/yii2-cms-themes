<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\theme;

use Besnovatyj\Contracts\theme\EditorStylesProvider;
use Closure;
use yii\base\InvalidConfigException;

/** Читает декларацию CSS редактора и возвращает публичные URL в порядке подключения. */
final class ThemeEditorStyles implements EditorStylesProvider
{
    /** @var list<string>|null */
    private ?array $styles = null;

    /**
     * @param Closure(): string $manifestPath путь к декларации активной темы
     * @param Closure(string): string $resolveSourcePath разрешение пути к каталогу ресурсов
     * @param Closure(string): string $publishDirectory публикация каталога с возвратом абсолютного URL
     * @param Closure(string): void $warn запись ошибки конфигурации или публикации
     */
    public function __construct(
        private readonly Closure $manifestPath,
        private readonly Closure $resolveSourcePath,
        private readonly Closure $publishDirectory,
        private readonly Closure $warn,
    ) {
    }

    /** @inheritdoc */
    public function styles(): array
    {
        return $this->styles ??= $this->loadStyles(($this->manifestPath)());
    }

    /**
     * Загружает декларацию CSS. Отсутствие файла означает стандартное оформление редактора.
     *
     * @return list<string>
     */
    private function loadStyles(string $manifestPath): array
    {
        if (!is_file($manifestPath)) {
            return [];
        }

        try {
            $groups = require $manifestPath;
            if (!is_array($groups) || !array_is_list($groups)) {
                throw new InvalidConfigException('В editor/config.php ожидается список групп CSS.');
            }

            return array_merge(...array_map($this->publishGroup(...), $groups));
        } catch (\Exception | \ParseError $exception) {
            ($this->warn)("Не удалось подключить стили редактора из {$manifestPath}. {$exception->getMessage()}");

            return [];
        }
    }

    /**
     * Публикует каталог при первом локальном CSS и сохраняет порядок локальных и внешних ссылок.
     *
     * @return list<string>
     */
    private function publishGroup(mixed $group): array
    {
        if (
            !is_array($group) || !is_string($group['sourcePath'] ?? null)
            || !is_array($group['css'] ?? null) || !array_is_list($group['css'])
        ) {
            throw new InvalidConfigException('Каждая группа CSS должна объявлять sourcePath и список css.');
        }

        $sourcePath = ($this->resolveSourcePath)($group['sourcePath']);
        $baseUrl = null;
        $styles = [];
        foreach ($group['css'] as $file) {
            if (!is_string($file) || $file === '') {
                throw new InvalidConfigException('Путь к таблице стилей должен быть непустой строкой.');
            }
            if (preg_match('~^https?://~i', $file)) {
                $styles[] = $file;
                continue;
            }
            if (!is_file($sourcePath . '/' . $file)) {
                throw new InvalidConfigException("Таблица стилей {$file} отсутствует в {$sourcePath}.");
            }

            $baseUrl ??= rtrim(($this->publishDirectory)($sourcePath), '/');
            $styles[] = $baseUrl . '/' . $file;
        }

        return $styles;
    }
}
