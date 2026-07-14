<?php

declare(strict_types=1);

namespace Itx\HubspotForms\Controller;

use Itx\HubspotForms\Event\EditFormBeforeSubmitEvent;
use Itx\HubspotForms\Service\FriendlyCaptchaService;
use Itx\HubspotForms\Service\HubspotService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class FormController extends ActionController
{
    private HubspotService $hubspotService;
    private FriendlyCaptchaService $friendlyCaptchaService;
    private Typo3Version $typo3Version;

    public function __construct(
        private RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
        HubspotService $hubspotService,
        FriendlyCaptchaService $friendlyCaptchaService,
        Typo3Version $typo3Version,
    ) {
        $this->hubspotService = $hubspotService;
        $this->friendlyCaptchaService = $friendlyCaptchaService;
        $this->typo3Version = $typo3Version;
    }

    public function displayAction()
    {
        $formID = $this->settings['form'] ?? '';   // Kann nicht im Konstruktor schon geladen werden

        try {
            $form = $this->hubspotService->fetchHubspotFormData($formID);
            $this->view->assign('form', $form);
        } catch (\Exception $e) {
            $this->addFlashMessage(
                'Please set your Access Token and Portal ID in the Extension settings',
                'Error',
                $this->typo3Version->getMajorVersion() < 12 ? FlashMessage::ERROR : ContextualFeedBackSeverity::ERROR,
                false
            );
            $this->logger->error('Error fetching data from HubSpot API: Access Token or Portal ID not set');
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

        $siteKey = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['siteKey'] ?? '';
        $secret = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['secret'] ?? '';
        $enableCaptcha = $this->settings['enableCaptcha'] ?? false;
        $enableGlobally = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['enableGlobally'] ?? false;

        $captchaFieldName = 'frc-captcha-solution-' . $formID;

        if (($enableCaptcha || $enableGlobally) && $siteKey != '' && $secret != '') {
            $captchaToken = trim((string)($_POST[$captchaFieldName] ?? ''));

            if ($captchaToken !== '') {
                $isValid = $this->friendlyCaptchaService->validateToken($captchaToken, $siteKey, $secret);
                if (!$isValid) {
                    $this->addFlashMessage(
                        'Friendly Captcha Token was not valid',
                        'Warning',
                        $this->typo3Version->getMajorVersion() < 12 ? FlashMessage::WARNING : ContextualFeedBackSeverity::WARNING,
                        false
                    );
                    $this->logger->error('Friendly Captcha Token was not valid for form ' . $formID);
                    return $this->redirect('display');
                }
            } else {
                $this->addFlashMessage(
                    'Friendly Captcha Token was not valid',
                    'Warning',
                    $this->typo3Version->getMajorVersion() < 12 ? FlashMessage::WARNING : ContextualFeedBackSeverity::WARNING,
                    false
                );
                $this->logger->error('Captcha is enabled but no captcha solution string is present in form ' . $formID);
                return $this->redirect('display');
            }
        }

        if ($this->request->getMethod() != 'POST') {
            return $this->redirect('display');
        }

        $arguments = $this->request->getArguments();
        $form = $this->hubspotService->fetchHubspotFormData($formID);

        if ($this->isSpamSubmission($arguments)) {
            $this->logger->error('Submission blocked for form ' .$formID .'. Content was analyzed and spam was detected.');
            return $this->redirect('display');
        }

        // Add fields to response
        foreach ($form['fieldGroups'] as $fieldGroup) {
            if (array_key_exists('fields', $fieldGroup)) {
                foreach ($fieldGroup['fields'] as $field) {
                    if ($field['fieldType'] != 'multiple_checkboxes') {
                        $message['fields'][] = ['objectTypeId' => $field['objectTypeId'], 'name' => $field['name'], 'value' => array_key_exists($field['name'], $arguments) ? $arguments[$field['name']] : ''];
                    } else {
                        foreach ($field['options'] as $option) {
                            if ($arguments[$field['name'] . '/' . $option['value']]) {
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
                    $message['legalConsentOptions']['consent']['communications'][] = ['value' => $arguments['legalConsentOptions/' . $checkbox['subscriptionTypeId']] != '', 'subscriptionTypeId' => $checkbox['subscriptionTypeId'], 'text' => $checkbox['label']];
                }
                break;
            case 'explicit_consent_to_process':
                $message['legalConsentOptions']['consent']['consentToProcess'] = $arguments['consentToProcess'] !== '';
                $message['legalConsentOptions']['consent']['text'] = $form['legalConsentOptions']['communicationConsentText'];
                foreach ($form['legalConsentOptions']['communicationsCheckboxes'] as $checkbox) {
                    $message['legalConsentOptions']['consent']['communications'][] = ['value' => $arguments['legalConsentOptions/' . $checkbox['subscriptionTypeId']] != '', 'subscriptionTypeId' => $checkbox['subscriptionTypeId'], 'text' => $checkbox['label']];
                }
                break;
            case 'none':
                // $message->legalConsentOptions = null;
                break;
            default:
                $this->logger->error("Invalid LegalConsentOption type: $legalConsentType");
                throw new \RuntimeException("Invalid LegalConsentOption type: $legalConsentType");
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
            if ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['simulateSubmit'] === '0' || $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['simulateSubmit'] === '') {
                $response = $this->hubspotService->sendForm($message, $formID);
            } else {
                $response = null;
            }

            $this->view->assignMultiple([
                'form' => $form,
                'response' => $response, // In case we want to handle a failed send more precisely
            ]);
        } catch (\Exception $e) {
            $this->addFlashMessage(
                'Sending form to Hubspot failed',
                'Warning',
                $this->typo3Version->getMajorVersion() < 12 ? FlashMessage::ERROR : ContextualFeedBackSeverity::ERROR,
                false
            );
            $this->logger->error('Error sending data to HubSpot API', ['error' => $e]);
        }

        // Send contents of Form Submission per mail (if configured)
        $enableMailing = $this->settings['enableMailing'] ?? '';

        if ($enableMailing) {
            $recipient = $this->settings['mailRecipient'] ?? '';
            $sender = $this->settings['mailSender'] ?? '';
            $subject = $this->settings['mailSubject'] ?? '';

            $mailer = GeneralUtility::makeInstance(Mailer::class);
            $email = GeneralUtility::makeInstance(FluidEmail::class);

            try {
                $email
                    ->from(new Address($sender))
                    ->to(new Address($recipient))
                    ->format(FluidEmail::FORMAT_HTML)
                    ->setTemplate('SubmissionEmail')
                    ->assignMultiple([
                        'subject' => $subject,
                        'fields' => $message['fields'],
                    ])
                    ->replyTo($sender);

                $mailer->send($email);
            } catch (\Exception $e) {
                $this->logger->error('Error sending email', ['error' => $e]);
            }
        }

        // Redirect to success page if configured
        if ($form['configuration']['postSubmitAction']['type'] === 'redirect_url') {
            return $this->redirectToUri($form['configuration']['postSubmitAction']['value']);
        }

        return $this->htmlResponse();
    }

    public function isSpamSubmission(array $arguments): bool
    {
        $firstname = trim((string)($arguments['firstname'] ?? ''));
        $lastname  = trim((string)($arguments['lastname'] ?? ''));

        return $this->isSuspiciousName($firstname) || $this->isSuspiciousName($lastname);
    }

    /**
     * Determines whether a name appears to be
     * automatically generated or otherwise suspicious.
     *
     * Returns {@see true} if the name is considered suspicious and should
     * be treated as spam; otherwise {@see false}.
     *
     * @param string $name The submitted first name or last name.
     * @return bool True if the name is classified as suspicious, otherwise false.
     */
    public function isSuspiciousName(string $name): bool
    {
        $name = preg_replace('/\s+/u', ' ', trim(strip_tags($name)));
        if (empty($name)) {
            return false;
        }

        if (
            preg_match('/https?:\/\/|www\.|@/iu', $name) ||
            preg_match("/[^\p{L}\p{M}\s'\-]/u", $name)
        ) {
            $this->logger->error('Spam detected. In your name ' .$name .', we found a number, web or mail-address or unusual special characters.');
            return true;
        }

        $compactName = preg_replace("/[\s'\-]/u", '', $name);
        if ($compactName === null) {
            $this->logger->error('Spam detected. Your name ' .$name .', looks empty, excluding spaces, hyphens, and apostrophes.');
            return true;
        }

        $length = mb_strlen($compactName, 'UTF-8');
        if ($length > 40) {
            $this->logger->error('Spam detected. Your name ' .$name .', is an unrealistically long single name.');
            return true;
        }
        else if ($length < 6) {
            return false;
        }

        $lowerName = mb_strtolower($compactName, 'UTF-8');

        if (preg_match('/[bcdfghjklmnpqrstvwxz]{5,}/iu', $lowerName)) {
            $this->logger->error('Spam detected. Your name ' .$name .', has a very long consonant cluster.');
            return true;
        }

        $matchCount = preg_match_all('/[aeiouyäöüéèêáàâíìîóòôúùû]/iu', $lowerName, $vowelMatches);
        if ($matchCount === false) {
            return false;
        }

        $vowelRatio = $matchCount / max($length, 1);

        if ($length >= 8 && $vowelRatio < 0.15) {
            $this->logger->error('Spam detected. The name "' . $name . '" contains no or very few vowels.');
            return true;
        }

        $caseTransitions = $this->countCaseTransitions($compactName);

        if ($length >= 10 && $caseTransitions >= 5) {
            $this->logger->error('Spam detected. Your name ' .$name .' consist of random capitalization.');
            return true;
        }

        $uppercaseCount = preg_match_all('/\p{Lu}/u', $compactName);
        if ($uppercaseCount === false) {
            return false;
        }

        if ($length >= 10 && $uppercaseCount >= 5) {
            $this->logger->error('Spam detected. The name "' . $name . '" has an excessive number of capital letters within a single word.');
            return true;
        }

        $characters = preg_split('//u', $lowerName, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters !== false) {
            $uniqueRatio = count(array_unique($characters)) / max(count($characters), 1);

            if (
                $length >= 12 &&
                $uniqueRatio > 0.80 &&
                ($caseTransitions >= 3 || $vowelRatio < 0.25)
            ) {
                $this->logger->error('Spam detected. Your name ' .$name .', looks like random generated text.');
                return true;
            }
        }

        return false;
    }

    /**
     * Counts the number of transitions between uppercase and lowercase letters.
     *
     * @param string $value The string to analyse.
     * @return int Number of uppercase/lowercase transitions.
     */
    public function countCaseTransitions(string $value): int
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return 0;
        }

        $transitions = 0;
        $previousCase = null;

        foreach ($characters as $character) {
            if (!preg_match('/\p{L}/u', $character)) {
                continue;
            }

            $currentCase = preg_match('/\p{Lu}/u', $character) ? 'upper' : 'lower';
            if ($previousCase !== null && $currentCase !== $previousCase) {
                $transitions++;
            }

            $previousCase = $currentCase;
        }

        return $transitions;
    }
}
