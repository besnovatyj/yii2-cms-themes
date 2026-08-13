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
 * Намеренно лёгкий: единственная работа на запрос — `require` готового файла активной темы и
 * выборка по ключу-слоту. Никакого обхода ФС и знания о конкретных модулях-потребителях —
 * их дала генерация ({@see ViewVariantsService}). Файл читается один раз за запрос (memo).
 * Отсутствие артефакта (тема без вариантов / ещё не сгенерирован) = пустой каталог, потребитель
 * откатывается к базовому представлению.
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
     */
    public function __construct(
        private readonly string $artifactBase = ViewVariantsService::ARTIFACT_BASE,
        private readonly ThemePathMapService $themes = new ThemePathMapService(),
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
     * Лениво загрузить и запомнить артефакт активной темы.
     *
     * @return array<string, array<string,string>>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $base = (string)Yii::getAlias($this->artifactBase);
        $file = ViewVariantsManifest::themedFile($base, $this->themes->activeThemeName());

        $data = is_file($file) ? require $file : null;

        return $this->map = is_array($data) ? $data : [];
    }
}
