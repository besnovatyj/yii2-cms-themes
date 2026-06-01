<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Themes\entities;

class ThemeTemplate
{
    public string|null $screenshot = null;
    public string|null $name = null;
    public bool|null $status = null;

    public function __construct($screenshot, $name, $status)
    {
        $this->screenshot = $screenshot;
        $this->name = $name;
        $this->status = $status;
    }

}
