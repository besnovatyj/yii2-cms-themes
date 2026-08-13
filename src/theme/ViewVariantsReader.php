<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\theme;

use Besnovatyj\Contracts\theme\ViewVariantCatalog;
use Besnovatyj\Contracts\theme\ViewVariantsManifest;
use Yii;

/**
 * Ридер каталога вариантов: реализация {@see ViewVariantCatalog} поверх артефакта
 * `viewVariants.{theme}.php`.
 *
 * Намеренно лёгкий: на happy-path единственная работа на запрос — `require` готового файла активной
 * темы и выборка по ключу-слоту (memo на запрос). Обхода ФС и знания о модулях-потребителях нет —
 * их даёт генерация ({@see ViewVariantsService}).
 *
 * Self-heal по образцу {@see ThemePathMapService::pathMapFor()}: если артефакта нет (например, его
 * удалил модуль очистки), ридер генерирует его один раз — так после сброса кэша первый же рендер
 * страницы или открытие формы в админке всё «чинит» само, без ручного flush/renew. Генерация всегда
 * оставляет файл (даже пустой), поэтому на теме без вариантов пересканирования на каждом запросе нет.
 *
 * Связывается с интерфейсом через DI в `config/common.php` (секция `container.singletons`),
 * поэтому доступен и на фронте, и в бэкенде без инициализации модуля Themes.
 */
final class ViewVariantsReader implements ViewVariantCatalog
{
    /** @var array<string, array<string,string>>|null плоская мапа `slot => [ключ => метка]` */
    private ?array $map = null;

    /**
     * @param string              $artifactBase базовый путь артефакта (алиас); дефолт — единый
     *                                          источник истины {@see ViewVariantsService::ARTIFACT_BASE}
     * @param ThemePathMapService $themes       поставщик имени активной темы
     * @param ViewVariantsService $variants     генератор для ленивого self-heal при отсутствии файла
     */
    public function __construct(
        private readonly string $artifactBase = ViewVariantsService::ARTIFACT_BASE,
        private readonly ThemePathMapService $themes = new ThemePathMapService(),
        private readonly ViewVariantsService $variants = new ViewVariantsService(),
    ) {}

    public function getVariants(string $slot): array
    {
        return $this->map()[$slot] ?? [];
    }

    public function hasVariant(string $slot, string $variant): bool
    {
        return $variant !== '' && isset($this->map()[$slot][$variant]);
    }

    /**
     * Лениво загрузить и запомнить артефакт активной темы; при отсутствии — сгенерировать (self-heal).
     *
     * @return array<string, array<string,string>>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $theme = $this->themes->activeThemeName();
        $base = (string)Yii::getAlias($this->artifactBase);
        $file = ViewVariantsManifest::themedFile($base, $theme);

        if (is_file($file)) {
            $data = require $file;
            return $this->map = is_array($data) ? $data : [];
        }

        // Артефакта нет (свежая установка / после очистки) — генерируем один раз, как pathMapFor.
        return $this->map = $this->variants->rebuild($theme);
    }
}
