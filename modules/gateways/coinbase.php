<?php
require_once __DIR__ . '/Coinbase/init.php';
require_once __DIR__ . '/Coinbase/const.php';

use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


function coinbase_MetaData()
{
    return array(
        'DisplayName' => 'Coinbase Commerce',
        'APIVersion' => '1.1',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false
    );
}

function coinbase_config()
{
    // Global variable required
    global $customadminpath;

    // Build callback URL.
    $url_s = ($_SERVER['HTTPS'] == "on") ? "https://" : "http://";
    $url1 = $url_s . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    $url2 = strpos($url1, $customadminpath);
    $url3 = substr($url1, 0, $url2);
    $callbackUrl = $url3 . "modules/gateways/callback/coinbase.php";

    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Coinbase Commerce'
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Description' => 'API Key from Coinbase Commerce.',
            'Type' => 'text'
        ),
        'secretKey' => array(
            'FriendlyName' => 'Shared Secret',
            'Description' => 'Shared Secret Key from Coinbase Commerce Webhook subscriptions.',
            'Type' => 'text'
        ),
        'webhookUrl' => array(
            'FriendlyName' => 'Webhook subscription url',
            'Type' => '',
            'Size' => '',
            'Default' => '',
            'Description' => "Please copy/paste <b>$callbackUrl</b> url  to Settings/Webhook subscriptions <b>https://commerce.coinbase.com/dashboard/settings</b>"
        ),
        'readme' => array(
            'FriendlyName' => '',
            'Type' => '',
            'Size' => '',
            'Default' => '',
            'Description' => 'Read the readme.txt file for instructions on how to use this module'
        )
    );
}

function coinbase_link($params)
{
    if (false === isset($params) || true === empty($params)) {
        die('Missing or invalid $params data.');
    }

    $description = '';

    try {
        $description = Capsule::table('tblinvoiceitems')
            ->where("invoiceid", "=", $params['invoiceid'])
            ->value('description');
    } catch (Exception $e) {
    }

    $chargeData = array(
        'local_price' => array(
            'amount' => $params['amount'],
            'currency' => $params['currency']
        ),
        'pricing_type' => 'fixed_price',
        'name' => $params['description'],
        'description' => empty($description) ? $params['description'] : $description,
        'metadata' => [
            METADATA_SOURCE_PARAM => METADATA_SOURCE_VALUE,
            METADATA_INVOICE_PARAM => $params['invoiceid'],
            METADATA_CLIENT_PARAM => $params['clientdetails']['userid'],
            'firstName' => isset($params['clientdetails']['firstname']) ? $params['clientdetails']['firstname'] : null,
            'lastName' => isset($params['clientdetails']['lastname']) ? $params['clientdetails']['lastname'] : null,
            'email' => isset($params['clientdetails']['email']) ? $params['clientdetails']['email'] : null
        ],
        'redirect_url' => $params['returnurl']
    );

    \Coinbase\ApiClient::init($params['apiKey']);
    $chargeObj = \Coinbase\Resources\Charge::create($chargeData);

    $form = '<form action="' . $chargeObj->hosted_url . '" method="GET">';
    $form .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
    $form .= '</form>';

    return $form;
}
