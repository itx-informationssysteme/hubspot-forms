<?php

namespace Itx\HubspotForms\Service;

use ITX\Jobapplications\Utility\Typo3VersionUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Formloader
{
    public function loadForms(array &$config)
    {
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

        $accessToken = $extensionConfiguration->get('hubspot_forms', 'accessToken');
        if ($accessToken === '' || $accessToken === null) {
            if (Typo3VersionUtility::getMajorVersion() < 13) {
                throw new \RuntimeException(
                    'Please configure a HubSpot access token in the extension configuration.',
                );
            }

            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                'Please configure a HubSpot access token in the extension configuration.',
                'Missing HubSpot access token',
                ContextualFeedbackSeverity::WARNING,
                true
            );

            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->addMessage($message);
            return;
        }

        $URL = 'https://api.hubapi.com/marketing/v3/forms/?limit=1000';

        // Same code as in HubspotService class
        $additionalOptions = [
            'headers' => ['authorization' => "Bearer {$accessToken}"],
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

        $config['items'] = [
            ['', ''],
        ];

        foreach ($forms['results'] as $form) {
            $config['items'][] = [$form['name'], $form['id']];
        }
    }
}
