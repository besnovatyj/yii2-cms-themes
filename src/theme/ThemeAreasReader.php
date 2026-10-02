<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\theme;

use Besnovatyj\Contracts\theme\ThemeArea;
use Besnovatyj\Contracts\theme\ThemeAreaCatalog;
use Yii;

/**
 * Ридер мест активной темы: реализация {@see ThemeAreaCatalog} поверх декларации темы.
 *
 * Конвенция: в корне темы лежит `areas.php`, который возвращает
 * `['id-места' => ['label' => '…', 'hint' => '…', 'sort' => 10], …]`. Нет файла — у темы нет
 * управляемых мест (пустой каталог, не ошибка).
 *
 * Артефакта и кэша нет намеренно: список нужен только админке модуля блоков, а фронт выводит блоки
 * по идентификатору места, не спрашивая каталог. Работа на запрос — один `require` маленького файла
 * (memo на запрос).
 *
 * Связывается с интерфейсом через DI в `config/common.php` (секция `container.singletons`), без
 * инициализации модуля Themes.
 */
final class ThemeAreasReader implements ThemeAreaCatalog
{
    /** Имя файла декларации мест относительно корня темы. */
    public const string FILE = 'areas.php';

    /** @var array<string, ThemeArea>|null */
    private ?array $areas = null;

    /**
     * @param ThemePathMapService $themes поставщик имени активной темы
     */
    public function __construct(
        private readonly ThemePathMapService $themes = new ThemePathMapService(),
    ) {}

    public function areas(): array
    {
        if ($this->areas !== null) {
            return $this->areas;
        }

        $file = Yii::getAlias('@themes/' . $this->themes->activeThemeName() . '/' . self::FILE);
        $data = is_file($file) ? require $file : [];

        $areas = [];
        foreach (is_array($data) ? $data : [] as $id => $definition) {
            if (!is_array($definition)) {
                Yii::warning("Theme area '{$id}' must be declared as an array", __METHOD__);
                continue;
            }

            $id = (string)$id;
            $areas[$id] = new ThemeArea(
                id: $id,
                label: (string)($definition['label'] ?? $id),
                hint: (string)($definition['hint'] ?? ''),
                sort: (int)($definition['sort'] ?? 0),
            );
        }

        uasort($areas, static fn (ThemeArea $a, ThemeArea $b) => [$a->sort, $a->label] <=> [$b->sort, $b->label]);

        return $this->areas = $areas;
    }
}
