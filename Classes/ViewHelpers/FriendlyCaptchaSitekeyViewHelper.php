<?php

namespace Itx\HubspotForms\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class FriendlyCaptchaSitekeyViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = true;

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('fallback', 'string', 'Fallback-Wert, falls kein Sitekey konfiguriert', false, '');
    }

    public function render(): string
    {
        $fallback = (string)($this->arguments['fallback'] ?? '');
        $siteKey = $fallback;

        $extentionSitekey = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['siteKey'] ?? '';
        if ($extentionSitekey) {
            $siteKey = $extentionSitekey;
        }

        return $siteKey !== '' ? $siteKey : $fallback;
    }
}
