<?php

namespace Itx\HubspotForms\Service;

use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FriendlyCaptchaService
{
    public function validateToken(string $token, string $siteKey, string $secret): bool
    {
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);

        $url = 'https://api.friendlycaptcha.com/api/v1/siteverify';
        $payload = json_encode([
            'solution' => $token,
            'secret' => $secret,
            'sitekey' => $siteKey,
        ]);

        try {
            $response = $requestFactory->request(
                $url,
                'POST',
                [
                    'body' => $payload,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 5,
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            return !empty($data['success']) && $data['success'] === true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
