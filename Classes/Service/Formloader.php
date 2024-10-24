<?php

namespace Itx\HubspotForms\Service;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Formloader
{

    public function loadForms(array &$config)
    {
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);

        $accessToken = 'Bearer ' . $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['accessToken'] ?? '';
        $URL = 'https://api.hubapi.com/marketing/v3/forms/?limit=1000';

        // Same code as in HubspotService class
        $additionalOptions = [
            'headers' => ['authorization' => $accessToken],
        ];

        // Get a PSR-7-compliant response object
        $response = $requestFactory->request(
            $URL,
            'GET',
            $additionalOptions,
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                'Returned status code is ' . $response->getStatusCode(),
            );
        }

        if ($response->getHeaderLine('Content-Type') !== 'application/json;charset=utf-8') {
            throw new \RuntimeException(
                'The request did not return JSON data',
            );
        }
        $content = $response->getBody()->getContents();
        $forms = json_decode($content, true);

        foreach ($forms['results'] as $form) {
            $config['items'][] = [$form['name'], $form['id']];
        }
    }
}
