<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\theme;

use Besnovatyj\Contracts\snippet\Snippet;
use Besnovatyj\Contracts\snippet\SnippetGroup;
use FilesystemIterator;
use SplFileInfo;
use Yii;

/**
 * Сниппеты активной темы: заготовки разметки, которые тема отдаёт в пикер WYSIWYG-редактора.
 *
 * Предформатированные куски темы по смыслу принадлежат теме: они живут файлами рядом с её разметкой
 * и меняются вместе с ней, а не копируются руками в БД модуля сниппетов. Этот ридер — мост: модуль
 * Themes реализует {@see \Besnovatyj\Contracts\snippet\SnippetProvider} и отдаёт то, что прочитал здесь.
 *
 * Конвенция (в корне темы):
 * ```
 * snippets/
 *   {группа}/
 *     _group.php     — необязательно: ['label' => 'Подвал', 'sort' => 10]
 *     {сниппет}.php  — ['title' => '…', 'html' => '…', 'keywords' => ['…'], 'preview' => null, 'sort' => 0]
 * ```
 * Файлы с ведущим `_` служебные и сниппетами не считаются. Группа получает id `theme-{группа}`:
 * так она не сталкивается с числовыми id категорий из БД, но может слиться с одноимённой группой
 * другого провайдера, как задумано агрегатором.
 */
final class ThemeSnippetsReader
{
    /** Каталог сниппетов относительно корня темы. */
    public const string DIR = 'snippets';

    /** Служебный файл описания группы. */
    private const string GROUP_FILE = '_group.php';

    /** Префикс id групп и сниппетов темы. */
    private const string ID_PREFIX = 'theme';

    /**
     * @param ThemePathMapService $themes поставщик имени активной темы
     */
    public function __construct(
        private readonly ThemePathMapService $themes = new ThemePathMapService(),
    ) {}

    /**
     * Группы сниппетов активной темы. Нет каталога — пустой список.
     *
     * @return SnippetGroup[]
     */
    public function snippetGroups(): array
    {
        $root = Yii::getAlias('@themes/' . $this->themes->activeThemeName() . '/' . self::DIR);
        if (!is_dir($root)) {
            return [];
        }

        $groups = [];
        /** @var SplFileInfo $dir */
        foreach (new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS) as $dir) {
            if (!$dir->isDir()) {
                continue;
            }

            $group = $this->readGroup($dir);
            if ($group !== null) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * Группа из каталога; пустой каталог группой не считается.
     */
    private function readGroup(SplFileInfo $dir): ?SnippetGroup
    {
        $name = $dir->getBasename();
        $meta = $this->requireArray($dir->getPathname() . '/' . self::GROUP_FILE);

        $items = [];
        /** @var SplFileInfo $file */
        foreach (new FilesystemIterator($dir->getPathname(), FilesystemIterator::SKIP_DOTS) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php' || str_starts_with($file->getBasename(), '_')) {
                continue;
            }

            $snippet = $this->readSnippet($name, $file);
            if ($snippet !== null) {
                $items[] = $snippet;
            }
        }

        if ($items === []) {
            return null;
        }

        usort($items, static fn (array $a, array $b) => [$a['sort'], $a['dto']->title] <=> [$b['sort'], $b['dto']->title]);

        return new SnippetGroup(
            id: self::ID_PREFIX . '-' . $name,
            label: (string)($meta['label'] ?? $name),
            items: array_column($items, 'dto'),
            sort: (int)($meta['sort'] ?? 0),
        );
    }

    /**
     * Сниппет из файла вместе с его порядком внутри группы.
     *
     * @return array{sort:int, dto:Snippet}|null null — файл не описывает сниппет (нет тела)
     */
    private function readSnippet(string $group, SplFileInfo $file): ?array
    {
        $data = $this->requireArray($file->getPathname());
        $html = (string)($data['html'] ?? '');
        $key = $file->getBasename('.php');

        if ($html === '') {
            Yii::warning("Theme snippet '{$group}/{$key}' has no html", __METHOD__);
            return null;
        }

        return [
            'sort' => (int)($data['sort'] ?? 0),
            'dto' => new Snippet(
                id: self::ID_PREFIX . '/' . $group . '/' . $key,
                title: (string)($data['title'] ?? $key),
                html: $html,
                preview: isset($data['preview']) ? (string)$data['preview'] : null,
                keywords: array_map('strval', (array)($data['keywords'] ?? [])),
            ),
        ];
    }

    /**
     * Содержимое php-файла, возвращающего массив; нет файла или не массив — пустой массив.
     *
     * @return array<string, mixed>
     */
    private function requireArray(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $data = require $file;

        return is_array($data) ? $data : [];
    }
}
