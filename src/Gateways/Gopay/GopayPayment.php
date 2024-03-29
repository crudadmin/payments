<?php

namespace AdminPayments\Gateways\Gopay;

use AdminPayments\Contracts\Exceptions\PaymentGateException;
use AdminPayments\Contracts\Exceptions\PaymentResponseException;
use AdminPayments\Gateways\PaymentGateway;
use Exception;
use Gopay;
use Log;

class GopayPayment extends PaymentGateway
{
    private $gopay;

    public function __construct($options = null)
    {
        parent::__construct($options);

        if ( !is_array($config = config('gopay')) ) {
            abort(500, 'Gopay configuration does not exists');

            return;
        }

        $this->gopay = GoPay\Api::payments([
            'goid' => $config['goid'],
            'clientId' => $config['clientId'],
            'clientSecret' => $config['clientSecret'],
            'isProductionMode' => $config['production'],
            'scope' => \GoPay\Definition\TokenScope::ALL,
            'language' => \GoPay\Definition\Language::SLOVAK,
            'gatewayUrl' => $config['production']
                                ? 'https://gate.gopay.cz/api'
                                : 'https://gw.sandbox.gopay.com/api',
        ]);
    }

    private function getDefaultInstrument()
    {
        $defaultPayment = $this->getOption('default_payment_instrument') ?: env('GOPAY_DEFAULT_PAYMENT', 'PAYMENT_CARD');

        //Set default payment by card, if limit is not over
        if ( $this->isPaymentInLimit($defaultPayment) ) {
            return $defaultPayment;
        }
    }

    private function getAllowedInstruments()
    {
        $allowedInstruments = $this->getOption('allowed_payment_instruments', [
            'PAYMENT_CARD',
            'BANK_ACCOUNT',
            'MPAYMENT',
            'PAYSAFECARD',
            'GOPAY',
            'PAYPAL',
            'BITCOIN',
            'MASTERPASS',
            'GPAY'
        ]);

        return array_values(array_filter($allowedInstruments, function($type){
            return $this->isPaymentInLimit($type);
        }));
    }

    public function getPaymentLimits()
    {
        return $this->getOption('payment_limits', [
            'PAYMENT_CARD' => env('GOPAY_LIMIT_PAYMENT_CARD', 2000),
            'BANK_ACCOUNT' => env('GOPAY_LIMIT_BANK_ACCOUNT', 4000),
            'PAYPAL' => env('GOPAY_LIMIT_PAYPAL', 1000),
            'BITCOIN' => env('GOPAY_LIMIT_BITCOIN', 1000),
            'PRSMS' => env('GOPAY_LIMIT_PRSMS', 20),
        ]);
    }

    public function isPaymentInLimit($key)
    {
        $payment = $this->getPayment();

        $limits = $this->getPaymentLimits();

        //If limit is not set. Allow given payment
        if ( !array_key_exists($key, $limits) ){
            return true;
        }

        //Check if limit is
        return $payment->price <= $limits[$key];
    }

    private function getItems()
    {
        return [
            [
                'name' => $this->getPaymentTitle(),
                'count' => 1,
                'amount' => round($this->getPayment()->price * 100),
            ],
        ];
    }

    private function getPayer()
    {
        $payment = $this->getPayment();
        $order = $this->getOrder();

        if ( count($this->getAllowedInstruments()) == 0 ){
            return false;
        }

        return array_filter([
            'default_payment_instrument' => $this->getDefaultInstrument(),
            'allowed_payment_instruments' => $this->getAllowedInstruments(),
            'default_swift' => $this->getOption('default_swift'),
            'contact' => array_filter([
                'first_name' => $order->firstname,
                'last_name' => $order->lastname,
                'email' => $order->email,
                'phone_number' => $order->phone,
            ]),
        ]);
    }

    public function getPaymentResponse()
    {
        if ( !$this->gopay ){
            return false;
        }

        $order = $this->getOrder();

        $payment = $this->getPayment();

        //If payer data are not available
        if ( !($payer = $this->getPayer()) ){
            return false;
        }

        $response = $this->gopay->createPayment([
            'payer' => $payer,
            'amount' => round($payment->price * 100),
            'currency' => 'EUR',
            'order_number' => $this->getPaymentNumber(),
            'order_description' => sprintf(_('Platba %s'), env('APP_NAME')),
            'items' => $this->getItems(),
            'callback' => [
                'return_url' => $this->getResponseUrl('status'),
                'notification_url' => $this->getResponseUrl('notification')
            ],
            'lang' => app()->getLocale()
        ]);

        //Ak je vykonana poziadavka v poriadku
        if ($response->hasSucceed()) {
            $this->setPaymentId($response->json['id']);

            return $response->json['gw_url'];
        } else {
            throw new PaymentGateException(
                $response->json['errors'][0]['message'] ?? null,
                $response?->statusCode ?? null,
                json_encode($response->json, JSON_PRETTY_PRINT)
            );

            return false;
        }
    }

    public function isPaid($id = null)
    {
        if ( !$this->gopay ) {
            throw new Exception('Gopay instance has not been found.');
        }

        if ( !($id = ($id ?: request('id'))) ) {
            throw new Exception('Gopay ID is missing.');
        }

        $response = $this->gopay->getStatus($id);

        if ( isset($response->json['state']) ) {
            if ( $response->json['state'] == 'PAID' ) {
                return true;
            } else {
                $errorResponse = 'Wrong GOPAY Payment status: '.(string)$response;
            }
        } else {
           $errorResponse = 'Wrong GOPAY Payment response: '.(string)$response;
        }

        throw new PaymentResponseException($errorResponse ?? '');
    }
}

?>