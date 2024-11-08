<?php

defined('TYPO3') or die();

(static function (): void {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        // extension name, matching the PHP namespaces (but without the vendor)
        'HubspotForms',
        // arbitrary, but unique plugin name (not visible in the backend)
        'ShowHubspotForms',
        // plugin title, as visible in the drop-down in the backend, use "LLL:" for localization
        'LLL:EXT:hubspot_forms/Resources/Private/Language/locallang.xlf:plugin.title',
        // icon
        'hubspot-forms-logo-png',
        // group
        'plugins',
        // description
        'LLL:EXT:hubspot_forms/Resources/Private/Language/locallang.xlf:plugin.description',
    );

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['hubspotforms_showhubspotforms'] = 'layout,select_key,recursive,pages';

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['hubspotforms_showhubspotforms'] = 'pi_flexform';

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
        // 'list_type' does not apply here
        'hubspotforms_showhubspotforms',
        // FlexForm configuration schema file
        'FILE:EXT:hubspot_forms/Configuration/FlexForms/Forms.xml',
        // ctype
        'list'
    );
})();
