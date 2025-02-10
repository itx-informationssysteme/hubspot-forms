<?php

namespace Itx\HubspotForms\Controller;

use Exception;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Http\RequestFactory;
use Itx\HubspotForms\Service\HubspotService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Itx\HubspotForms\Event\EditFormBeforeSubmitEvent;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Messaging\FlashMessage;

class FormController extends ActionController
{
    private HubspotService $hubspotService;
    private Typo3Version $typo3Version;

    public function __construct(
        private RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
        HubspotService $hubspotService,
        Typo3Version $typo3Version,
    ) {
        $this->hubspotService = $hubspotService;
        $this->typo3Version = $typo3Version;
    }

    public function displayAction()
    {
        $formID = $this->settings['form'] ?? '';   // Kann nicht im Konstruktor schon geladen werden

        try {
            $form = $this->hubspotService->fetchHubspotFormData($formID);
            $this->view->assign('form', $form);
        } catch (Exception $e) {
            $this->addFlashMessage(
                'Please set your Access Token in the Extension settings',
                'Warning',
                $this->typo3Version->getMajorVersion() < 12 ? FlashMessage::ERROR : ContextualFeedBackSeverity::ERROR,
                false
            );
            $this->logger->error('Error fetching data from HubSpot API', ['error' => $e]);
        }

        if ((bool)$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['simulateSubmit']) {
            $this->addFlashMessage(
                'You are currently in Simulated Submit Mode',
                'Reminder',
                $this->typo3Version->getMajorVersion() < 12 ? FlashMessage::INFO : ContextualFeedBackSeverity::INFO, 
                false
            );
        }

        return $this->htmlResponse();
    }

    public function submitAction()
    {
        $formID = $this->settings['form'] ?? '';   // Kann nicht im Konstruktor schon geladen werden
 
        $arguments = $this->request->getArguments();
        $requestedFormId = $arguments['formId'] ?? null;
        if ($requestedFormId && $arguments['formId'] != $formID) {
            return $this->htmlResponse();
        }
 
        $form = $this->hubspotService->fetchHubspotFormData($formID);
        
        if($this->request->getMethod() != 'POST') {
            $this->redirect('display');
        }

        $arguments = $this->request->getArguments();
        $form = $this->hubspotService->fetchHubspotFormData($formID);

        // Add fields to response
        foreach ($form['fieldGroups'] as $fieldGroup) {
            if (array_key_exists('fields', $fieldGroup)) {
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
        }

        // Add legalConsentOptions to response
        $legalConsentType = $form['legalConsentOptions']['type'];

        switch ($legalConsentType) {
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
                $message['legalConsentOptions']['consent']['consentToProcess'] = $arguments['consentToProcess'] !== '';
                $message['legalConsentOptions']['consent']['text'] = $form['legalConsentOptions']['communicationConsentText'];
                foreach ($form['legalConsentOptions']['communicationsCheckboxes'] as $checkbox) {
                    $message['legalConsentOptions']['consent']['communications'][] = array('value' => $arguments['legalConsentOptions/' . $checkbox['subscriptionTypeId']] != '', 'subscriptionTypeId' => $checkbox['subscriptionTypeId'], 'text' => $checkbox['label']);
                }
                break;
            case 'none':
                // $message->legalConsentOptions = null;
                break;
            default:
                throw new RuntimeException("Invalid LegalConsentOption type: $legalConsentType");
                break;
        }

        // Add additional context params
        $message['context']['pageUri'] = $this->request->getHeaderLine('referer');

        // Add HUTK
        if (array_key_exists('hubspotutk', $_COOKIE)) {
            $message['context']['hutk'] = $_COOKIE['hubspotutk'];
        }

        // Change Form Data via event, if needed
        $event = $this->eventDispatcher->dispatch(
            new EditFormBeforeSubmitEvent($form, $message),
        );
        $form = $event->getForm();
        $message = $event->getMessage();

        // Send to HubSpot
        try {
            if ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['simulateSubmit'] === "0" || $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['simulateSubmit'] === '') {
                $response = $this->hubspotService->sendForm($message, $formID);
            } else {
                $response = null;
            }

            $this->view->assignMultiple([
                'form' => $form,
                'response' => $response // In case we want to handle a failed send more precisely
            ]);
        } catch (Exception $e) {
            $this->addFlashMessage(
                'Please set your PortalID in the Extension settings',
                'Warning',
                $this->typo3Version->getMajorVersion() < 12 ? FlashMessage::ERROR : ContextualFeedBackSeverity::ERROR,
                false
            );
            $this->logger->error('Error fetching data from HubSpot API', ['error' => $e]);
        }
        if ($form['configuration']['postSubmitAction']['type'] === 'redirect_url') {
            $this->redirectToUri($form['configuration']['postSubmitAction']['value']);
        }

        return $this->htmlResponse();
    }
}
