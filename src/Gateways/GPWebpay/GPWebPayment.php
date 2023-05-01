<?php

namespace AdminPayments\Gateways\GPWebpay;

use AdminPayments\Gateways\GPWebpay\Api as WebpayApi;
use AdminPayments\Gateways\GPWebpay\PaymentRequest;
use AdminPayments\Gateways\GPWebpay\PaymentResponse;
use AdminPayments\Gateways\GPWebpay\PaymentResponseException as WebpayPaymentResponseException;
use AdminPayments\Gateways\GPWebpay\Signer;
use AdminPayments\Gateways\GPWebpay\FinalizePaymentRequest;
use AdminPayments\Gateways\Contracts\Exceptions\PaymentGateException;
use AdminPayments\Gateways\Contracts\Exceptions\PaymentResponseException;
use AdminPayments\Gateways\PaymentGateway;
use Exception;
use Store;
use Log;

class GPWebPayment extends PaymentGateway
{
    private $webpay;

    public function __construct($options = null)
    {
        parent::__construct(
            array_merge($options ?: [], config('webpay'))
        );

        $options = $this->getOptions();
        if ( count($options) === 0 ) {
            return abort(500, 'Webpay configuration does not exists');
        }

        $signer = new Signer(
            base_path($options['priv_path']),      // Path of private key.
            $options['password'],                  // Password for private key.
            base_path($options['pub_path'])        // Path of public key.
        );

        $this->webpay = new WebpayApi(
            $options['merchant_id'],
            $options['production'] ? 'https://3dsecure.gpwebpay.com/pgw/order.do' : 'https://test.3dsecure.gpwebpay.com/pgw/order.do',
            $signer
        );
    }

    public function getPaymentResponse()
    {
        if ( !$this->webpay ){
            return false;
        }

        try {
            $order = $this->getOrder();

            $payment = $this->getPayment();

            $currencyCode = strtoupper(Store::getCurrency()->code);

            //In case of development purposes, we need create
            //range of payments for each development environment.
            $number = ((int)$this->getOption('number_prefix', 0)) + $payment->getKey();

            $request = new PaymentRequest(
                $number,
                $payment->price,
                constant('\AdminPayments\Gateways\GPWebpay\PaymentRequest::'.$currencyCode),
                0,
                $this->getResponseUrl('status'),
                $order->getKey(),
            );

            $request->setEmail($order->email);

            return $this->webpay->createPaymentRequestUrl($request);
        } catch (Exception $e) {
            throw new PaymentGateException($e->getMessage());
        }
    }

    public function isPaid($id = null)
    {
        if ( !$this->webpay ) {
            throw new Exception('Webpay instance has not been found.');
        }

        $response = new PaymentResponse(
            request('OPERATION'),
            request('ORDERNUMBER'),
            request('MERORDERNUM') ?: request('ORDERNUMBER'),
            request('PRCODE'),
            request('SRCODE'),
            request('RESULTTEXT'),
            request('DIGEST'),
            request('DIGEST1'),
        );

        //Successfull payment
        try {
            $this->webpay->verifyPaymentResponse($response);
        }

        //Payment is not successfull
        catch (WebpayPaymentResponseException $e) {
            throw new PaymentResponseException($e->getMessage());
        }

        // Digest is not correct.
        catch (Exception $e) {
            throw new PaymentResponseException($e->getMessage());
        }
    }
}

?>