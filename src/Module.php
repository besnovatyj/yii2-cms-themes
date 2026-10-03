<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Themes;

use Besnovatyj\Contracts\snippet\SnippetGroup;
use Besnovatyj\Contracts\snippet\SnippetProvider;
use Besnovatyj\Kernel\module\CmsModule;
use Besnovatyj\Contracts\module\DeclaresModule;
use Besnovatyj\Themes\theme\ThemeSnippetsReader;

/**
 * Модуль управления темами.
 *
 * Реализует {@see SnippetProvider}: заготовки разметки активной темы (каталог `snippets/` темы,
 * конвенция — в {@see ThemeSnippetsReader}) попадают в пикер редактора тем же `instanceof`-сканом
 * агрегатора сниппетов, что и вклад любого модуля. Нет модуля сниппетов — контракт никто не вызывает.
 */
class Module extends CmsModule implements
    DeclaresModule, SnippetProvider
{
    public const bool EDITABLE = true;
    public const string MODULE_ID = 'Themes';
    public static function moduleId(): string { return self::MODULE_ID; }
    public static function isEditable(): bool { return self::EDITABLE; }
    public static function moduleConfig(): array { return require __DIR__.'/config/config.php'; }

    /**
     * Сниппеты активной темы как вклад в общее дерево пикера.
     *
     * @return SnippetGroup[]
     */
    public function snippetGroups(): array
    {
        return (new ThemeSnippetsReader())->snippetGroups();
    }

}
