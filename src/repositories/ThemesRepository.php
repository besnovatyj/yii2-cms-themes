<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Themes\repositories;

use Besnovatyj\Helpers\ArrayExportHelper;
use Besnovatyj\Themes\entities\ThemeTemplate;
use DomainException;
use FilesystemIterator;
use Yii;
use yii\base\InvalidConfigException;

class ThemesRepository
{
    private string|false $themesDirPath;
    private string|false $themeConfigFile;
    private ArrayExportHelper $exporter;

    public function __construct(ArrayExportHelper $exporter)
    {
        $this->themesDirPath = Yii::getAlias('@themes');
        if (!is_dir($this->themesDirPath)) {
            throw new DomainException('Themes directory does not exist');
        }
        $this->themeConfigFile = Yii::getAlias(Yii::$app->params['frontThemeConfigFile']);
        // Файл активной темы — генерируемое состояние в var/config (не в git).
        // Отсутствие НЕ ошибка: фронт (ThemePathMapService) трактует его как тему 'basic'.
        // Держим тот же контракт — путь резолвим, существование не требуем.
//        if (!is_file($this->themeConfigFile)) {
//            throw new DomainException('Theme config file does not exist');
//        }
        $this->exporter = $exporter;
    }

    public function activate($id): bool
    {
        $res = false;
        $list = [];
        $iterator = new FilesystemIterator($this->themesDirPath);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $list[] = $item->getBasename();
            }
        }
        if (in_array($id, $list, false)) {
            $res = $this->exporter->saveToFile(['themeName' => $id], $this->themeConfigFile);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($this->themeConfigFile, true);
            }
        }
        return $res;
    }

    /**
     * Возвращает массив названий существующих тем
     *
     * @return array
     * @throws InvalidConfigException
     */
    public function getThemes(): array
    {
        $list = [];
        $directoryIterator = new FilesystemIterator($this->themesDirPath);
        foreach ($directoryIterator as $item) {
            if ($item->isDir()) {
                $url = "";
                if (file_exists($screenshot = $item->getPathname() . DIRECTORY_SEPARATOR . 'screenshot.jpg')) {
                    list (, $url) = Yii::$app->getAssetManager()->publish($screenshot);
                }
                $list[] = new ThemeTemplate(
                    $url,
                    $item->getBasename(),
                    $this->activeThemeName() === $item->getBasename()
                );
            }
        }
        return $list;
    }

    private function activeThemeName(): string
    {
        $config = is_file($this->themeConfigFile) ? require $this->themeConfigFile : null;
        return is_array($config) && !empty($config['themeName'])
            ? (string)$config['themeName']
            : 'basic';
    }

}
