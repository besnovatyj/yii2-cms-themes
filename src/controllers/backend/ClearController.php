<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Themes\controllers\backend;

use Besnovatyj\Themes\services\ThemeArtifactsClearService;
use Exception;
use Yii;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Контроллер интеграции модуля темизации с модулем очистки (ClearManager).
 *
 * Работает с генерируемыми артефактами темизации (`themePathMap.*.php`, `viewVariants.*.php` в
 * `@config-dyn-gen`): отдаёт их суммарный размер и удаляет (без перегенерации). Формат ответа и
 * обработка ошибок повторяют {@see \Besnovatyj\ClearManager\controllers\backend\DataController}
 * и {@see \Besnovatyj\Blog\controllers\backend\ClearController}:
 *  - все экшены — только POST и только AJAX, ответ в JSON;
 *  - ожидаемые сбои бросаются {@see ServerErrorHttpException} (нативный конверт Yii ErrorHandler),
 *    непредвиденные исключения сервиса не ловятся и всплывают к ErrorHandler.
 */
class ClearController extends Controller
{
    private ThemeArtifactsClearService $service;

    public function __construct($id, $module, ThemeArtifactsClearService $service, array $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->service = $service;
    }

    /**
     * @inheritDoc
     */
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    '*' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Все экшены отдают JSON и принимают только AJAX-запросы.
     *
     * @throws BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        // Формат ставим до parent::beforeAction — чтобы ошибки фильтров (verb) тоже ушли как JSON.
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!parent::beforeAction($action)) {
            return false;
        }
        if (!Yii::$app->getRequest()->getIsAjax()) {
            throw new BadRequestHttpException('Ожидается AJAX-запрос.');
        }
        return true;
    }

    /**
     * Возвращает суммарный размер артефактов темизации.
     *
     * @throws Exception
     */
    public function actionGetData(): array
    {
        return ['status' => 'success', 'data' => $this->service->getData()];
    }

    /**
     * Удаляет артефакты темизации.
     *
     * @throws ServerErrorHttpException
     * @throws Exception
     */
    public function actionClearData(): array
    {
        if (!$this->service->clearData()) {
            throw new ServerErrorHttpException('Не удалось удалить артефакты темизации.');
        }
        return ['status' => 'success', 'message' => 'Артефакты темизации удалены'];
    }
}
