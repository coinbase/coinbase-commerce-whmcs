<?php
require_once __DIR__ . '/../Coinbase/init.php';
require_once __DIR__ . '/../Coinbase/const.php';
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

function errorDebug($error) {
    global $gatewayModuleName;
    logTransaction($gatewayModuleName, $_POST, $error);
    http_response_code(500);
    die('[ERROR] ' . $error);
}

// Die if module is not active.
if (!$gatewayParams['type']) {
    errorDebug('Coinbase Commerce module not activated');
}

$secretKey = $gatewayParams['secretKey'];
$apiKey = $gatewayParams['apiKey'];
$headers = array_change_key_case(getallheaders());
$signatureHeader = isset($headers[SIGNATURE_HEADER]) ? $headers[SIGNATURE_HEADER] : null;
$payload = trim(file_get_contents('php://input'));

try {
    $event = \Coinbase\Webhook::buildEvent($payload, $signatureHeader, $secretKey);
} catch (\Exception $exception) {
    errorDebug($exception->getMessage());
}

\Coinbase\ApiClient::init($apiKey);
$charge = \Coinbase\Resources\Charge::retrieve($event->data['id']);

if (!$charge) {
    errorDebug('Charge was not found in Coinbase Commerce.');
}

if ($charge->metadata[METADATA_SOURCE_PARAM] != METADATA_SOURCE_VALUE) {
    die('[Error] not whmcs charge');
}

if (($invoiceId = $charge->metadata[METADATA_INVOICE_PARAM]) === null
    || ($userId = $charge->metadata[METADATA_CLIENT_PARAM]) === null) {
    errorDebug('Invoice ID or client ID was not found in response');
}

$orderData = \Illuminate\Database\Capsule\Manager::table('tblinvoices')
    ->where('id', $invoiceId)
    ->where('userid', $userId)
    ->get();

if (!$orderData || !isset($orderData[0]->id)) {
    errorDebug(sprintf('Invoice ID "%s" is not exists', $invoiceId));
}

checkCbInvoiceID($invoiceId, $gatewayModuleName);

switch ($event->type) {
    case 'charge:confirmed':
        $transactionId = '';
        $fee = 0;

        foreach ($charge->payments as $payment) {
            if (strtolower($payment['status']) === 'confirmed') {
                $transactionId = $payment['transaction_id'];
                $amount = isset($payment['value']['local']['amount']) ? $payment['value']['local']['amount'] : $orderData[0]->total;
            }
        }
        if ($transactionId && isset($charge['confirmed_at'])) {
            checkCbTransID($transactionId);
            addInvoicePayment($invoiceId, $transactionId, $amount, $fee, $gatewayModuleName);
            logTransaction($gatewayModuleName, $payload, 'Charge is confirmed.');
        } else {
            errorDebug(sprintf('Invalid charge %s. No transaction found.', $charge['id']));
        }

        break;
    case 'charge:pending':
        logTransaction($gatewayModuleName, $payload, sprintf('Charge %s was pending. Charge has been detected but has not been confirmed yet.', $charge['id']));
        break;
    case 'charge:created':
        logTransaction($gatewayModuleName, $payload, sprintf('Charge %s was created. Awaiting payment.', $charge['id']));
        break;
    case 'charge:delayed':
        logTransaction($gatewayModuleName, $payload, sprintf('Charge %s was delayed.', $charge['id']));
        break;
    case 'charge:failed':
        logTransaction($gatewayModuleName, $payload, sprintf('Charge %s was failed.', $charge['id']));
        break;
}
