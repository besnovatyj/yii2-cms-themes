<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Themes\controllers\backend;

use Besnovatyj\Themes\repositories\ThemesRepository;
use common\components\controller\ControllerTrait;
use common\components\theme\Theme;
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

    public function __construct(
        $id,
        $module,
        ThemesRepository $themes,
        $config = []
    )
    {
        parent::__construct($id, $module, $config);
        $this->themes = $themes;
    }

    public function actionIndex(): string
    {
        try {
            $pathMap = new Theme()->getPathMap();
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
            new Theme()->renewPathMap();
            Yii::$app->session->setFlash('success', 'Карта путей сгенерирована!');
        } catch (DomainException $e) {
            $this->handleDomainException($e);
        }
        return $this->goReferer();
    }
}
