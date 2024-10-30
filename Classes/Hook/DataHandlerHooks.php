<?php

namespace Itx\HubspotForms\Hook;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DataHandlerHooks
{
    public function processDatamap_afterDatabaseOperations($status, $table, $id, array $fieldArray, \TYPO3\CMS\Core\DataHandling\DataHandler &$pObj)
    {
        if (array_key_exists('list_type', $pObj->datamap['tt_content'][$id] ?? [])  && ($pObj->datamap['tt_content'][$id]['list_type'] === 'hubspotforms_showhubspotforms')) {
            $container = GeneralUtility::getContainer();
            /** @var FrontendInterface $cache */
            $cache = $container->get('cache.hubspot_form_cache');

            $cache->flushByTag('hubspot_form');
        }
    }

    public function postProcessClearCache()
    {
        $container = GeneralUtility::getContainer();
        /** @var FrontendInterface $cache */
        $cache = $container->get('cache.hubspot_form_cache');

        $cache->flushByTag('hubspot_form');
    }
}
