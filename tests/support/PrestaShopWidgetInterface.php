<?php

namespace PrestaShop\PrestaShop\Core\Module;

interface WidgetInterface
{
    public function renderWidget($hookName, array $configuration);

    public function getWidgetVariables($hookName, array $configuration);
}
