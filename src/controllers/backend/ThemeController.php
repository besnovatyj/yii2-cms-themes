<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Themes\controllers\backend;

use Besnovatyj\Themes\repositories\ThemesRepository;
use Besnovatyj\Themes\theme\ThemePathMapService;
use Besnovatyj\Kernel\controller\ControllerTrait;
use DomainException;
use Exception;
use Yii;
use yii\helpers\VarDumper;
use yii\web\Controller;
use yii\web\Response;

class ThemeController extends Controller
{
    use ControllerTrait;

    private ThemesRepository $themes;
    private ThemePathMapService $pathMaps;

    public function __construct(
        $id,
        $module,
        ThemesRepository $themes,
        ThemePathMapService $pathMaps,
        $config = []
    )
    {
        parent::__construct($id, $module, $config);
        $this->themes = $themes;
        $this->pathMaps = $pathMaps;
    }

    public function actionIndex(): string
    {
        try {
            $pathMap = $this->pathMaps->pathMapFor($this->pathMaps->activeThemeName());
            $viewMap = VarDumper::export($pathMap);
            $themes = $this->themes->getThemes();
            return $this->render('index', [
                'themes' => $themes,
                'viewMap' => $viewMap,
            ]);
        } catch (Exception $e) {
            $this->handleDomainException($e);
        }
        return 'Error';
    }

    public function actionActivate($id): Response
    {
        try {
            $this->themes->activate($id);
        } catch (DomainException $e) {
            $this->handleDomainException($e);
        }
        return $this->goReferer();
    }

    public function actionRenewPathMap(): Response
    {
        try {
            // Сбрасываем кэш всех тем и сразу пересобираем активную для немедленной обратной связи.
            $this->pathMaps->invalidateAll();
            $this->pathMaps->pathMapFor($this->pathMaps->activeThemeName());
            Yii::$app->session->setFlash('success', 'Карта путей сгенерирована!');
        } catch (DomainException $e) {
            $this->handleDomainException($e);
        }
        return $this->goReferer();
    }
}
