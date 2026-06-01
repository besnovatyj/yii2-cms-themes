<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Themes\repositories;

use Besnovatyj\Helpers\ArrayExportHelper;
use DomainException;
use FilesystemIterator;
use Besnovatyj\Themes\entities\ThemeTemplate;
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
        if (!is_file($this->themeConfigFile)) {
            throw new DomainException('Theme config file does not exist');
        }
        $this->exporter = $exporter;
    }

    public function activate($id): bool
    {
        $list = [];
        $iterator = new FilesystemIterator($this->themesDirPath);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $list[] = $item->getBasename();
            }
        }
        if (in_array($id, $list, false)) {
            return $this->exporter->saveToFile(['themeName' => $id], $this->themeConfigFile);
        }
        return false;
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
                    (require $this->themeConfigFile)['themeName'] == $item->getBasename()
                );
            }
        }
        return $list;
    }

}
