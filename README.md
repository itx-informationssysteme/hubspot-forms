# Hubspot Forms Extension for Typo3
This extension will help displaying Hubspot forms in our pages by dynamically loading in any given Form using your FormID, PortalID and Access Token using the HubSpot API

## Installation (Composer)
To install the extension using composer, run `composer req itx/hubspot-forms`

## Configuration
* To start using the extension, you will first have to set your **Access Token** and **PortalID** in the TYPO3 Backend `Settings > Extension Configuration > hubspot_forms`
* Alternatively you can configure these paths inside your `AdditionalConfiguration.php` file located under `public/typo3conf/AdditionalConfiguration.php`
e.g.
```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['accessToken'] = 'Your Access Token';
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['hubspot_forms']['portalID'] = 'Your PortalID';
```
* Next, you can add a General Plugin content element where you want your form to be on your website
* After that, go to the Plugin Tab in the settings of your new content element. Here, you can select which form you want to load from your given HubSpot Portal in Form of a select list
* After selecting your form, switch over to the frontend to see it all loaded in

## Styling 
* Every field type of the forms is rendered by a partial, so if you want to style your forms we recommend overwriting our basic styling in the partial files, located under `Resources/Private/Partials` in the extension directory

## TODO
* Mark mandatory fields
* Switchpartial
* Move URL variables to HubSpotService 