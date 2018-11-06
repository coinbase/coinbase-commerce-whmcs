<?php
require_once __DIR__ . '/../Coinbase/init.php';
require_once __DIR__ . '/../Coinbase/const.php';
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

class Webhook
{
    /**
     * @var string
     */
    private $gatewayModuleName;

    /**
     * @var array
     */
    private $gatewayParams;

    public function __construct()
    {
        $this->init();
    }

    private function init()
    {
        $this->gatewayModuleName = basename(__FILE__, '.php');
        $this->gatewayParams = getGatewayVariables($this->gatewayModuleName);
        $this->checkIsModuleActivated();
    }

    private function getModuleParam($paramName)
    {
        return array_key_exists($paramName, $this->gatewayParams) ? $this->gatewayParams[$paramName] : null;
    }

    private function checkIsModuleActivated()
    {
        // Die if module is not active.
        if (!$this->getModuleParam('type')) {
            $this->failProcess('Coinbase Commerce module not activated');
        }
    }

    private function failProcess($errorMessage)
    {
        $this->log($errorMessage);
        http_response_code(500);
        die();
    }

    public function process()
    {
        $event = $this->getEvent();
        $charge = $this->getCharge($event->data['id']);

        if (($orderId = $charge->metadata[METADATA_INVOICE_PARAM]) === null
            || ($userId = $charge->metadata[METADATA_CLIENT_PARAM]) === null) {
            $this->failProcess('Invoice ID or client ID was not found in charge');
        }

        $order = $this->getOrder($orderId, $userId);
        $lastTimeLine = end($charge->timeline);

        switch ($lastTimeLine['status']) {
            case 'RESOLVED':
            case 'COMPLETED':
                $this->handlePaid($orderId, $charge);
                return;
            case 'PENDING':
                $this->log(sprintf('Charge %s was pending. Charge has been detected but has not been confirmed yet.', $charge['id']));
                return;
            case 'NEW':
                $this->log(sprintf('Charge %s was created. Awaiting payment.', $charge['id']));
                return;
            case 'UNRESOLVED':
                // mark order as paid on overpaid
                if ($lastTimeLine['context'] === 'OVERPAID') {
                    $this->handlePaid($orderId, $charge);
                } else {
                    $this->log(sprintf('Charge %s was unresolved.', $charge['id']));
                }
                return;
            case 'CANCELED':
                $this->log(sprintf('Charge %s was canceled.', $charge['id']));
                return;
            case 'EXPIRED':
                $this->log(sprintf('Charge %s was expired.', $charge['id']));
                return;
        }
    }

    private function handlePaid($orderId, $charge)
    {
        $transactionId = null;
        $fee = 0;

        foreach ($charge->payments as $payment) {
            if (strtolower($payment['status']) === 'confirmed') {
                $transactionId = $payment['transaction_id'];
                $amount = $payment['value']['local']['amount'];
            }
        }

        if ($transactionId) {
            checkCbTransID($transactionId);
            addInvoicePayment($orderId, $transactionId, $amount, $fee, $this->gatewayModuleName);
            $this->log(sprintf('Charge %s is confirmed.', $charge['id']));
        } else {
            $this->failProcess(sprintf('Invalid charge %s. No transaction found.', $charge['id']));
        }
    }

    private function log($message)
    {
        logTransaction($this->gatewayModuleName, $_POST, $message);
    }

    private function getEvent()
    {
        $secretKey = $this->getModuleParam('secretKey');
        $headers = array_change_key_case(getallheaders());
        $signatureHeader = isset($headers[SIGNATURE_HEADER]) ? $headers[SIGNATURE_HEADER] : null;
        $payload = trim(file_get_contents('php://input'));

        try {
            $event = \Coinbase\Webhook::buildEvent($payload, $signatureHeader, $secretKey);
        } catch (\Exception $exception) {
            $this->failProcess($exception->getMessage());
        }

        return $event;
    }

    private function getCharge($chargeId)
    {
        $apiKey = $this->getModuleParam('apiKey');
        \Coinbase\ApiClient::init($apiKey);

        try {
            $charge = \Coinbase\Resources\Charge::retrieve($chargeId);
        } catch (\Exception $exception) {
            $this->failProcess($exception->getMessage());
        }

        if (!$charge) {
            $this->failProcess('Charge was not found in Coinbase Commerce.');
        }

        if ($charge->metadata[METADATA_SOURCE_PARAM] != METADATA_SOURCE_VALUE) {
            $this->failProcess( 'Not ' . METADATA_SOURCE_VALUE . ' charge');
        }

        return $charge;
    }

    private function getOrder($id, $userId)
    {
        $orderData = \Illuminate\Database\Capsule\Manager::table('tblinvoices')
            ->where('id', $id)
            ->where('userid', $userId)
            ->get();

        if (!$orderData || !isset($orderData[0]->id)) {
            $this->failProcess(sprintf('Order with ID "%s" is not exists', $id));
        }

        checkCbInvoiceID($id, $this->gatewayModuleName);

        return reset($orderData);
    }
}

$webhook = new Webhook();
$webhook->process();
