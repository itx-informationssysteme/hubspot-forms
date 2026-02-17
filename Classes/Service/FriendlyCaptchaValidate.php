<?php

namespace Itx\HubspotForms\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class FriendlyCaptchaValidate
{
    public function __invoke(): void
    {
        $isValid = false;
        $siteKey = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['siteKey'] ?? '0';
        $secret = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['secret'] ?? '0';

        $token = $_POST['token'] ?? '';

        if (!empty($siteKey) && !empty($secret)) {
            $service = GeneralUtility::makeInstance(FriendlyCaptchaService::class);
            $isValid = $service->validateToken($token, $siteKey, $secret);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => $isValid]);
        exit;
    }
}
