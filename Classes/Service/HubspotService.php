<?php

namespace Itx\HubspotForms\Service;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

class HubspotService
{

    public function __construct(
        private RequestFactory $requestFactory,
    ) {}

    public function fetchHubspotFormData(string $AccessToken, string $URL): array
    {
        $additionalOptions = [
            'headers' => ['authorization' => $AccessToken],
        ];

        // Get a PSR-7-compliant response object
        $response = $this->requestFactory->request(
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
        $result = json_decode($content, true);

        return $result;
    }

    public function sendForm(array $message, $URL): ResponseInterface
    {
        return $this->requestFactory->request($URL, 'POST', ['body' => json_encode($message), 'headers' => ['Content-Type' => 'application/json']]);
    }
}
