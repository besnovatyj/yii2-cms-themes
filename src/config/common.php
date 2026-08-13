<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Contracts\theme\ViewVariantCatalog;
use Besnovatyj\Themes\Module;
use Besnovatyj\Themes\theme\Theme;
use Besnovatyj\Themes\theme\ViewVariantsReader;

/**
 * Yii2-конфиг модуля для движка yiisoft/config (группа `common` — общий для всех приложений).
 *
 * Объявляется через `extra.config-plugin`, собирается modman в merge-plan и мёржится в рантайме.
 * Содержит регистрацию модуля. Меню (adminMenu) и миграции остаются вкладами modman. Значения берутся
 * из статических методов {@see Module} — единый источник, без дублирования.
 *
 * Дополнительно вкладывает `mailer.view.theme`: письма Yii рендерятся через СОБСТВЕННЫЙ `View`
 * mailer'а (не `Yii::$app->view`), у которого своя `theme`. Присваивая ему тот же {@see Theme}
 * (а значит и тот же `pathMap` из `ThemePathMapService`), мы включаем темизацию писем по единой
 * конвенции: письмо модуля под `src/views/mail/…` перекрывается темой в
 * `@themes/{theme}/modules/{ModuleId}/views/mail/…` — так же, как любое представление
 * (см. ANALYSIS_MODULES_INTEGRATION.MD, §6 и П8). Модули-отправители об этом не знают.
 *
 * Связка — здесь, в `common` (mailer живёт в common-слое), а не в каждом модуле-отправителе и не
 * хардкодом в app-скелете. Базовый компонент `mailer` (class/transport) задаёт окружение
 * (`environments/{dev|prod}/common/config/main-local.php`); merge лишь дополняет его ключом `view.theme`.
 *
 * Дополнительно вкладывает DI-биндинг {@see ViewVariantCatalog} → {@see ViewVariantsReader} в
 * секцию `container.singletons`. Это глобальная (обе аппликации), но ЛЕНИВАЯ регистрация: singleton
 * лишь объявлен в app-конфиге и инстанциируется только когда модуль-потребитель реально спрашивает
 * каталог выбираемых шаблонов. Модуль Themes при этом НЕ инициализируется и его Bootstrap не нужен —
 * чтение артефакта `viewVariants.{theme}.php` не требует ничего от модуля как модуля. Нет пакета тем —
 * нет биндинга: потребитель мягко откатывается к базовому представлению.
 */
return [
    'modules' => [
        Module::moduleId() => array_merge(
            ['class' => Module::class],
            Module::moduleConfig(),
            ['version' => Module::moduleVersion()],
        ),
    ],
    'container' => [
        'singletons' => [
            ViewVariantCatalog::class => ViewVariantsReader::class,
        ],
    ],
    'components' => [
        'mailer' => [
            'view' => [
                'theme' => [
                    'class' => Theme::class,
                ],
            ],
        ],
    ],
];
