<?php

declare(strict_types=1);

namespace Itx\HubspotForms\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class CaptchaEnabledViewHelper extends AbstractViewHelper
{
    public function render(): int
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['enableGlobally'] ?? 0;
    }
}
