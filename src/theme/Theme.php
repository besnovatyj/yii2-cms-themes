<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\theme;

use Besnovatyj\Contracts\theme\LayoutPathProvider;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Theme as BaseTheme;

/**
 * Тема приложения: тонкая обёртка над `yii\base\Theme`, получающая `pathMap` и имя активной темы
 * из {@see ThemePathMapService}.
 *
 * В отличие от прежней реализации, здесь НЕТ обхода модулей рефлексией, парсинга `composer.json`
 * и записи файлов в `init()` — вся эта логика вынесена (генерация манифеста → modman; композиция и
 * кэш → сервис). Компонент лишь подставляет готовую карту.
 *
 * Реализует {@see LayoutPathProvider}, поэтому тонкое ядро модулей (`CmsModule`) берёт каталог
 * layout'ов темы через абстракцию, не завися от этого класса напрямую.
 */
class Theme extends BaseTheme implements LayoutPathProvider
{
    /** Имя активной темы (каталог в `@themes`). */
    public string $name = '';

    private ?ThemePathMapService $mapService = null;

    public function init(): void
    {
        parent::init();

        $service = $this->mapService();
        $this->name = $service->activeThemeName();
        $this->basePath = Yii::getAlias('@themes') . '/' . $this->name;
        $this->pathMap = $service->pathMapFor($this->name);
    }

    /**
     * Каталог layout'ов активной темы (для темизации layout'ов модулей через `CmsModule`).
     *
     * @throws InvalidConfigException
     */
    public function getLayoutsPath(): string
    {
        return $this->getPath('layouts');
    }

    private function mapService(): ThemePathMapService
    {
        return $this->mapService ??= Yii::createObject(ThemePathMapService::class);
    }
}
