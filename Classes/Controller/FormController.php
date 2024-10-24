<?php

namespace Itx\HubspotForms\Controller;

use Exception;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Http\RequestFactory;
use Itx\HubspotForms\Service\HubspotService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use Psr\Log\LoggerInterface;

class FormController extends ActionController
{
    private string $portalID;

    private string $formID;

    private const URL = 'https://api.hubapi.com/marketing/v3/forms/';

    private string $accessToken;

    private HubspotService $hubspotService;

    public function __construct(
        private RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
        HubspotService $hubspotService,
    ) {
        $this->hubspotService = $hubspotService;
        $this->portalID = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['portalID'] ?? '';
        $this->accessToken = 'Bearer ' . $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['accessToken'] ?? '';
    }

    public function displayAction()
    {
        $this->formID = $this->settings['form'] ?? '';   // Kann nicht im Konstruktor schon geladen werden

        try {
            $form = $this->hubspotService->fetchHubspotFormData($this->accessToken, self::URL . $this->formID);
            $this->view->assign('form', $form);
        } catch (Exception $e) {
            $this->addFlashMessage(
                'Please set your Access Token in the Extension settings',
                'Warning',
                FlashMessage::ERROR,
                false
            );
            $this->logger->error('Error fetching data from HubSpot API', ['error' => $e]);
        }
        return $this->htmlResponse();
    }

    public function submitAction()
    {
        $this->formID = $this->settings['form'] ?? '';   // Kann nicht im Konstruktor schon geladen werden

        $arguments = $this->request->getArguments();
        $form = $this->hubspotService->fetchHubspotFormData($this->accessToken, self::URL . $this->formID);

        // Add fields to response
        foreach ($form['fieldGroups'] as $fieldGroup) {
            foreach ($fieldGroup['fields'] as $field) {
                if ($field['fieldType'] != 'multiple_checkboxes') {
                    $message['fields'][] = ['objectTypeId' => $field['objectTypeId'], 'name' => $field['name'], 'value' => array_key_exists($field['name'], $arguments) ? $arguments[$field['name']] : ''];
                } else {
                    foreach ($field['options'] as $option) {
                        if ($arguments[$option['value']]) {
                            $message['fields'][] = ['objectTypeId' =>  $field['objectTypeId'], 'name' => $field['name'], 'value' => $option['value']];
                        }
                    }
                }
            }
        }

        // Add legalConsentOptions to response
        switch ($form['legalConsentOptions']['type']) {
            case 'legitimate_interest':
                $message['legalConsentOptions']['legitimateInterest']['value'] = true;
                $message['legalConsentOptions']['legitimateInterest']['subscriptionTypeId'] = $form['legalConsentOptions']['subscriptionTypeIds'][0]; // Response only expects one id, so take the first, might have to change
                $message['legalConsentOptions']['legitimateInterest']['legalBasis'] = strtoupper($form['legalConsentOptions']['lawfulBasis']);
                $message['legalConsentOptions']['legitimateInterest']['text'] = $form['legalConsentOptions']['privacyText'];
                break;
            case 'implicit_consent_to_process':
                $message['legalConsentOptions']['consent']['consentToProcess'] = true;
                $message['legalConsentOptions']['consent']['text'] = $form['legalConsentOptions']['communicationConsentText'];
                foreach ($form['legalConsentOptions']['communicationsCheckboxes'] as $checkbox) {
                    $message['legalConsentOptions']['consent']['communications'][] = array('value' => $arguments['legalConsentOptions/' . $checkbox['subscriptionTypeId']] != '', 'subscriptionTypeId' => $checkbox['subscriptionTypeId'], 'text' => $checkbox['label']);
                }
                break;
            case 'explicit_consent_to_process':
                $message['legalConsentOptions']['consent']['consentToProcess'] = $arguments['consentToProcess'] != '';
                $message['legalConsentOptions']['consent']['text'] = $form['legalConsentOptions']['communicationConsentText'];
                foreach ($form['legalConsentOptions']['communicationsCheckboxes'] as $checkbox) {
                    $message['legalConsentOptions']['consent']['communications'][] = array('value' => $arguments['legalConsentOptions/' . $checkbox['subscriptionTypeId']] != '', 'subscriptionTypeId' => $checkbox['subscriptionTypeId'], 'text' => $checkbox['label']);
                }
                break;
            case 'none':
                // $message->legalConsentOptions = null;
                break;
        }

        // Add additional context params
        $message['context']['pageUri'] = $GLOBALS['TYPO3_REQUEST']->getAttribute('normalizedParams')->getRequestUrl();

        // Send to HubSpot
        $postURL = 'https://api.hsforms.com/submissions/v3/integration/submit/' . $this->portalID . '/' . $this->formID;

        try {
            $response = $this->hubspotService->sendForm($message, $postURL);
            $this->view->assignMultiple([
                'form' => $form,
                'response' => $response // In case we want to handle a failed send
            ]);
        } catch (Exception $e) {
            $this->addFlashMessage(
                'Please set your PortalID in the Extension settings',
                'Warning',
                FlashMessage::ERROR,
                false
            );
            $this->logger->error('Error fetching data from HubSpot API', ['error' => $e]);
        }
        return $this->htmlResponse();
    }
}
