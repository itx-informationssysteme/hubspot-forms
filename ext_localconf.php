<?php

declare(strict_types=1);

use Itx\HubspotForms\Controller\FormController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;

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

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['hubspot_form_cache']
    ??= [];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
    mod {
        wizards {
            newContentElement {
                wizardItems {
                    plugins {
                        elements {
                            hubspot_forms {
                                iconIdentifier = hubspot-forms-logo-png
                                title = Hubspot Forms
                                description = Add a HubSpot Form to your site - seamlessly
                                tt_content_defValues {
                                    CType = list
                                    list_type = hubspotforms_showhubspotforms
                                }
                            }
                        }
                    }
                }
            }
        }
    }
');

$iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
$iconRegistry->registerIcon('hubspot-forms-logo-png', BitmapIconProvider::class, [
    'source' => 'EXT:hubspot_forms/Resources/Public/Icons/Extension.png'
]);
