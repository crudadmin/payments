<?php

namespace AdminPayments\Contracts\Payments;

use AdminPayments\Contracts\Payments\Exceptions\PaymentGateException;
use AdminPayments\Contracts\Payments\Exceptions\PaymentResponseException;
use AdminPayments\Contracts\Payments\PaymentHelper;
use AdminPayments\Contracts\Payments\Paypal\HasPaypalSupport;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Exception;

class PaypalPayment extends PaymentHelper
{
    use HasPaypalSupport;

    private $client;
    private $paymentResponse;

    public function __construct($options = null)
    {
        parent::__construct($options);

        $this->client = new PayPalClient(config('paypal'));
        $this->client->getAccessToken();
    }

    public function getPaymentResponse()
    {
        $data = $this->buildOrderRequest('CAPTURE');

        $response = $this->client->createOrder($data);

        //Log paypal sent request for debug purposes
        $this->logPaypalResponse('Order payment response '.$this->getOrder()->getKey(), $response);

        if ( $response['error'] ?? null ){
            throw new PaymentGateException(
                $response['error']['messsage'] ?? null, null, $response
            );
        }

        $this->setPaymentId($response['id']);

        return $response;
    }

    public function isPaid($id = null)
    {
        $this->paymentResponse = $response = $this->client->capturePaymentOrder($id);

        //Log paypal sent request for debug purposes
        $this->logPaypalResponse('Order verification response '.$this->getOrder()->getKey(), $response);

        //Order is paid yet
        if ( in_array($response['status'] ?? null, ['COMPLETED', 'ORDER_ALREADY_CAPTURED']) ){
            return true;
        }

        throw new PaymentResponseException(
            $response['error']['messsage'] ?? 'Payment is not paid yet.',
            null,
            $response
        );
    }

    public function getPaymentUrl($paymentResponse)
    {
        return collect($paymentResponse['links'])->where('rel', 'approve')->first()['href'] ?? null;
    }

    /**
     * Returns payment response for notifications
     *
     * @param  string  $paymentId
     *
     * @return  array
     */
    public function getNotificationResponse($paymentId)
    {
        return [
            'paid' => $this->getOrder()->paid_at ? true : false,
            'paypal' => $this->paymentResponse,
        ];
    }
}

?>