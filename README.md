## Coinbase Commerce module for WHMCS, version 1.2

### About Coinbase Commerce:
- Coinbase Commerce is a new service that enables merchants to accept multiple cryptocurrencies directly into a user-controlled wallet.
This module allows you to integrate Coinbase Commerce easily on your platform.
Additional information can be found at:
https://commerce.coinbase.com/

### Requirements:
- Working WHMCS installation (tested up to version 7.4.2).
- Coinbase Commerce account, you can register for free at https://commerce.coinbase.com/signup

### Installation:
- Clone current repository and run `composer install` or download build from [releases page](https://github.com/coinbase/coinbase-commerce-whmcs/releases) and unzip
- Copy modules folder to the root folder of your WHMCS installation.
- Activate the Coinbase Commerce module in your WHMCS admin panel (Setup -> Payments -> Payment Gateways -> All Payment Gateways).
- Look for "Coinbase Commerce" button and click on.
- Log into your Coinbase Commerce Dashboard and go to "Settings" section, copy the Api Key and Webhook Shared Secret from your account and paste them into the corresponding fields at the module's setup page on your WHMCS site.
- Copy the "Webhook subscription url" from your Coinbase Commerce's module setup and paste it into the "Webhook Url" field at the "Notifications" section of your Coinbase Commerce dashboard, then save the changes.
- Click on "Save Changes" in your WHMCS site.

### Integrate with other e-commerce platforms
[Coinbase Commerce Integrations](https://commerce.coinbase.com/integrate)