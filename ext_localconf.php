<?php

declare(strict_types=1);

use Itx\HubspotForms\Controller\FormController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

ExtensionUtility::configurePlugin(
    // extension name, matching the PHP namespaces (but without the vendor)
    'HubspotForms',
    // arbitrary, but unique plugin name (not visible in the backend)
    'ShowHubspotForms',
    // all actions
    [FormController::class => 'display, submit'],
    // non-cacheable actions
    [FormController::class => 'display, submit'],
);
