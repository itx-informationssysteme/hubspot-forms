<?php

defined('TYPO3') || die('Access denied.');

use Itx\HubspotForms\Hook\DataHandlerHooks;

call_user_func(
    function () {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['hubspot_forms'] = DataHandlerHooks::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['hubspot_forms'] = DataHandlerHooks::class;
    }
);