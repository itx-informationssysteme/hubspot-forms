<?php

namespace Itx\HubspotForms\Service;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

class HubspotService
{

    private string $URL = 'https://api.hubapi.com/marketing/v3/forms/';

    private string $portalID;

    private string $accessToken;

    public function __construct(
        private RequestFactory $requestFactory,
    ) {
        $this->portalID = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['portalID'] ?? '';
        $this->accessToken = 'Bearer ' . $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['accessToken'] ?? '';
    }

    public function fetchHubspotFormData(string $formID): array
    {
        $this->URL = $this->URL . $formID;

        $additionalOptions = [
            'headers' => ['authorization' => $this->accessToken],
        ];

        // Get a PSR-7-compliant response object
        $response = $this->requestFactory->request(
            $this->URL,
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
        $result = json_decode($content, true);

        return $result;
    }

    public function sendForm(array $message, $formID): ResponseInterface
    {
        $URL = 'https://api.hsforms.com/submissions/v3/integration/submit/' . $this->portalID . '/' . $formID;

        return $this->requestFactory->request($URL, 'POST', ['body' => json_encode($message), 'headers' => ['Content-Type' => 'application/json']]);
    }
}
