# Hubspot Forms Extension for TYPO3
This extension will help displaying Hubspot forms in our pages by **dynamically** loading in any given form using the HubSpot API

## Installation (Composer)
To install the extension using composer, run `composer req itx/hubspot-forms`

## Configuration
* Create a HubSpot access token with the following scope: `forms`
* To start using the extension, you will first have to set your **Access Token** and **PortalID** in the TYPO3 Backend `Settings > Extension Configuration > hubspot_forms`
* Alternatively you can configure these settings inside your `additional.php` file located under `public/typo3conf/AdditionalConfiguration.php`
e.g.
```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['accessToken'] = 'Your Access Token';
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['portalID'] = 'Your PortalID';
```
* FriendlyCaptcha support has been added. To use it, just add your sitekey and secret from FriendlyCaptcha to the according setting fields and then toggle the captcha on in the plugin settings

## Usage
* First, add a General Plugin content element where you want your form to be on your website
<img width="1044" height="845" alt="{917CD7C6-1080-46A7-B40E-BB8031E1FD3D}" src="https://github.com/user-attachments/assets/f455f443-f576-4b9f-ab3f-ea90d21a01f7" />

* After that, go to the `Plugin` Tab in the settings of your new content element. Here, you can select which form you want to load from your given HubSpot Portal in Form of a select list
<img width="2008" height="702" alt="{FA418E9B-F47B-4A14-9252-C2F07EA94320}" src="https://github.com/user-attachments/assets/a68af43f-1853-4867-8770-a31ac981f625" />

* After selecting your form, switch over to the frontend to see it all loaded in
<img width="730" height="283" alt="{D810CF8E-ABC6-4FF2-96B9-01D9656FFDDC}" src="https://github.com/user-attachments/assets/9f6b182a-aaa4-4c21-b38a-237c101e4284" />

* There is an option to only simulate form submissions in the `Extension Settings`. This toggle disables the line of code responsible for the POST request to HubSpot after submitting a form
<img width="981" height="731" alt="{0F270FED-AAF6-4222-A654-8309983E74A7}" src="https://github.com/user-attachments/assets/f49dfb3b-af27-4242-91a3-3f37ec8f9161" />

### Emails
* You can send emails with the contents of the form submissions if you configure it in the `Plugin` tab of the content element

### IP Tracking
* Tracking the IP of the submitter is now also possible, but since getting legal consent for that is not simple and left up to the site owner, the setting is not visible in the Extension Settings and off by default
* To forward the submitters IP-Address to Hubspot, set `$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['trackIp']` to `true` in your additional.php file

## Styling
* Every field type of the forms is rendered by a partial, if you want to individually style your form fields, overwrite the partials and add your own styling
* The same goes for the template of the optional mail

## Experimental Features
* Conditional fields from Hubspot now get rendered when the condition is fulfilled and sent to Hubspot if filled. Currently not all combinations of field types and conditions is tested, so if you find a non-working one please open an [issue](https://github.com/itx-informationssysteme/hubspot-forms/issues) 

## Known Issues
### Multiple Multiple-Checkboxes
* When a form contains more than one multiple checkbox field, if any checkbox of any of the fields is checked, the browser won't prompt you to check at least one of the checkboxes in each field
* Submitting the form while there isn't at least one checkbox checked in the required fields causes TYPO3 to throw a Bad Request Error
* The error gets caught, so the site continues to operate, but the form will not be sent to HubSpot
