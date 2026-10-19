<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\theme;

use Besnovatyj\Contracts\block\BlockType;
use Besnovatyj\Contracts\theme\ThemeBlockTypeCatalog;
use FilesystemIterator;
use SplFileInfo;
use Yii;

/**
 * Ридер типов блоков активной темы: реализация {@see ThemeBlockTypeCatalog} поверх файлов темы.
 *
 * Конвенция (в корне темы):
 * ```
 * blockTypes/
 *   {type}/
 *     schema.php  — ['label' => '…', 'hint' => '…', 'fields' => [...]]
 *     view.php    — шаблон вывода блока
 * ```
 * Имя каталога — id типа: латиница в нижнем регистре, цифры и дефис. Каталог без `schema.php` или
 * без `view.php` типом не считается (в лог уходит предупреждение).
 *
 * Артефакта и кэша нет намеренно. Вывод блока знает id и читает один файл ({@see blockType()}):
 * `is_file` + `require`, который при OPcache не парсит файл заново. Обход каталога ({@see blockTypes()})
 * нужен только админке. Memo на запрос в обоих случаях.
 *
 * Связывается с интерфейсом через DI в `config/common.php` (секция `container.singletons`), без
 * инициализации модуля Themes.
 */
final class ThemeBlockTypesReader implements ThemeBlockTypeCatalog
{
    /** Каталог типов блоков относительно корня темы. */
    public const string DIR = 'blockTypes';

    /** Файл схемы типа. */
    public const string SCHEMA_FILE = 'schema.php';

    /** Файл шаблона типа. */
    public const string VIEW_FILE = 'view.php';

    /** Допустимый id типа темы (он же имя каталога). */
    private const string ID_PATTERN = '/^[a-z0-9][a-z0-9-]*$/';

    /** @var array<string, BlockType|null> memo по id; null — тема тип не объявляет */
    private array $types = [];

    /** Весь каталог уже прочитан в {@see $types}. */
    private bool $complete = false;

    /**
     * @param ThemePathMapService $themes поставщик имени активной темы
     */
    public function __construct(
        private readonly ThemePathMapService $themes = new ThemePathMapService(),
    ) {}

    public function blockTypes(): array
    {
        if (!$this->complete) {
            $root = $this->root();
            if (is_dir($root)) {
                /** @var SplFileInfo $dir */
                foreach (new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS) as $dir) {
                    if ($dir->isDir()) {
                        $id = $dir->getBasename();
                        $this->types[$id] ??= $this->read($id);
                    }
                }
            }
            $this->complete = true;
        }

        $types = array_filter($this->types);
        uasort($types, static fn (BlockType $a, BlockType $b) => $a->label <=> $b->label);

        return $types;
    }

    public function blockType(string $id): ?BlockType
    {
        if (!array_key_exists($id, $this->types)) {
            $this->types[$id] = $this->complete ? null : $this->read($id);
        }

        return $this->types[$id];
    }

    /**
     * Тип из каталога темы; null — каталога нет или он не описывает тип.
     */
    private function read(string $id): ?BlockType
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            return null;
        }

        $dir = $this->root() . '/' . $id;
        $schemaFile = $dir . '/' . self::SCHEMA_FILE;
        $viewFile = $dir . '/' . self::VIEW_FILE;

        if (!is_file($schemaFile)) {
            return null;
        }
        if (!is_file($viewFile)) {
            Yii::warning("Theme block type '{$id}' has no " . self::VIEW_FILE, __METHOD__);
            return null;
        }

        $schema = require $schemaFile;
        if (!is_array($schema)) {
            Yii::warning("Theme block type '{$id}': " . self::SCHEMA_FILE . ' must return an array', __METHOD__);
            return null;
        }

        return new BlockType(
            id: $id,
            label: (string)($schema['label'] ?? $id),
            view: $viewFile,
            fields: is_array($schema['fields'] ?? null) ? $schema['fields'] : [],
            hint: (string)($schema['hint'] ?? ''),
        );
    }

    /**
     * Абсолютный путь к каталогу типов активной темы.
     */
    private function root(): string
    {
        return Yii::getAlias('@themes/' . $this->themes->activeThemeName() . '/' . self::DIR);
    }
}
