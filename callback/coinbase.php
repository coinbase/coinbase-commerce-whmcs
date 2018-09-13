<?php
require_once __DIR__ . '/../Coinbase/init.php';
require_once __DIR__ . '/../Coinbase/const.php';
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    logTransaction($gatewayModuleName, $_POST, 'Not activated');
    die('[ERROR] Coinbase Commerce module not activated.');
}

$headers = array_change_key_case(getallheaders());
$signatureHeader = isset($headers[SIGNATURE_HEADER]) ? $headers[SIGNATURE_HEADER] : null;
$payload = trim(file_get_contents('php://input'));

try {
    $event = \Coinbase\Webhook::buildEvent($payload, $signatureHeader, $gatewayParams['secretKey']);
} catch (\Exception $exception) {
    logTransaction($gatewayModuleName, $_POST, $exception->getMessage());
    die('[ERROR] ' . $exception->getMessage());
}

$charge = $event->data;

if ($charge->metadata[METADATA_SOURCE_PARAM] != METADATA_SOURCE_VALUE) {
    die('[Error] not whmcs charge');
}

if (($invoiceId = $charge->metadata['invoiceid']) === null
    || ($userId = $charge->metadata['clientid']) === null) {
    die('[Error] invoice id or client id not found in response');
}

$orderData = \Illuminate\Database\Capsule\Manager::table('tblinvoices')
    ->where('id', $invoiceId)
    ->where('userid', $userId)
    ->get();

if (!$orderData || !isset($orderData[0]->id)) {
    logTransaction($gatewayModuleName, $_POST, '[Error] invoiceid is not exists');
    die('[Error] invoiceid is not exists');
}

$invoiceId = checkCbInvoiceID($invoiceId, $gatewayModuleName);
$invoiceTotal = $orderData[0]->total;

switch ($event->type) {
    case 'charge:confirmed':
        $transactionId = '';
        $fee = 0;

        foreach ($charge->payments as $payment) {
            if (strtolower($payment['status']) === 'confirmed') {
                $transactionId = $payment['transaction_id'];
                $amount = isset($payment['value']['local']['amount']) ? $payment['value']['local']['amount'] : $invoiceTotal;
            }
        }

        checkCbTransID($transactionId);
        addInvoicePayment($invoiceId, $transactionId, $amount, $fee, $gatewayModuleName);
        logTransaction($gatewayModuleName, $payload, 'Charge is confirmed.');
        break;
    case 'charge:created':
        logTransaction($gatewayModuleName, $payload, 'Charge was created. Awaiting payment.');
        break;
    case 'charge:delayed':
        logTransaction($gatewayModuleName, $payload, 'Charge was delayed.');
        break;
    case 'charge:failed':
        logTransaction($gatewayModuleName, $payload, 'Charge was failed.');
        break;
}
