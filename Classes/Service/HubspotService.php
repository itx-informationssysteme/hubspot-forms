<?php

namespace Itx\HubspotForms\Service;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

class HubspotService
{
    private string $portalID;

    private string $accessToken;

    private string $formID;

    public function __construct(
        private RequestFactory $requestFactory,
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
        $this->portalID = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['portalID'] ?? '';
        $this->accessToken = 'Bearer ' . $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['accessToken'] ?? '';
    }

    public function fetchHubspotFormData(string $formID): array
    {
        $this->formID = $formID;

        return $this->getCachedValue(md5("hubspot_form_cache/$formID/{$this->accessToken}"), ['hubspot_form'], null);
    }

    public function sendForm(array $message, string $formID): ResponseInterface
    {
        $URL = 'https://api.hsforms.com/submissions/v3/integration/submit/' . $this->portalID . '/' . $formID;

        return $this->requestFactory->request($URL, 'POST', ['body' => json_encode($message), 'headers' => ['Content-Type' => 'application/json']]);
    }

    private function getCachedValue(string $cacheIdentifier, array $tags, int|null $lifetime): array
    {
        // If value is false, it has not been cached
        $value = $this->cache->get($cacheIdentifier);
        if ($value === false) {
            // Store the data in cache
            $value = $this->getFormData();
            $this->cache->set($cacheIdentifier, $value, $tags, $lifetime);
        }

        return $value;
    }

    private function getFormData(): array
    {
        $URL = 'https://api.hubapi.com/marketing/v3/forms/' . $this->formID;

        $additionalOptions = [
            'headers' => ['authorization' => $this->accessToken],
        ];

        // Get a PSR-7-compliant response object
        $response = $this->requestFactory->request(
            $URL,
            'GET',
            $additionalOptions,
        );

        if ($response->getStatusCode() !== 200) {
            $this->logger->error("Cannot reach HubSpot API endpoint: {$response->getStatusCode()}");
            throw new \RuntimeException(
                'Returned status code is ' . $response->getStatusCode(),
            );
        }

        if ($response->getHeaderLine('Content-Type') !== 'application/json;charset=utf-8') {
            $this->logger->error('Cannot load form data: The request did not return JSON data');
            throw new \RuntimeException(
                'The request did not return JSON data',
            );
        }
        $content = $response->getBody()->getContents();
        $result = json_decode($content, true);

        return $result;
    }
}
