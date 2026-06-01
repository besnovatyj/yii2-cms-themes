<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Themes\entities\ThemeTemplate;
use yii\helpers\StringHelper;
use yii\helpers\Url;

/** @var ThemeTemplate[] $themes */
/** @var string $viewMap */

$this->title = 'Theming';
$this->params['breadcrumbs'][] = $this->title;

?>

<div>Карта представлений</div>
<div class="btn-toolbar mb-2">
    <div class="btn-group me-2">
        <a type="button" class="btn btn-sm btn-outline-secondary"
           href="<?= Url::to(['/Themes/backend/theme/renew-path-map']) ?>">Пересоздать</a>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewMap">
            Смотреть
        </button>
    </div>
</div>

<div class="row">
    <?php foreach ($themes as $theme): ?>

        <?php if ($theme->status): ?>
            <div class="col-12 col-md-3">
                <div class="card">
                    <div class="card-header"><?= StringHelper::mb_ucfirst($theme->name) ?></div>
                    <img src="<?= $theme->screenshot ?>" class="card-img-top rounded-0"
                         alt="<?= StringHelper::mb_ucfirst($theme->name) ?>">
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <a href="<?= Url::to(['/Themes/backend/theme/activate/', 'id' => $theme->name]) ?>"
                               class="btn btn-default disabled" type="button">already active</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-12 col-md-3">
                <div class="card">
                    <div class="card-header"><?= StringHelper::mb_ucfirst($theme->name) ?></div>
                    <a href="<?= Url::to(['/Themes/backend/theme/activate/', 'id' => $theme->name]) ?>">
                        <img src="<?= $theme->screenshot ?>" class="card-img-top"
                             alt="<?= StringHelper::mb_ucfirst($theme->name) ?>">
                    </a>
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <a href="<?= Url::to(['/Themes/backend/theme/activate/', 'id' => $theme->name]) ?>"
                               class="btn btn-success" type="button">activate</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="viewMap" tabindex="-1" aria-labelledby="viewMapLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <b class="modal-title" id="viewMapLabel">Карта представлений</b>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
<pre>
<?= $viewMap; ?>
</pre>
            </div>
        </div>
    </div>
</div>
