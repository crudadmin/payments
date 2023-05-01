<?php

namespace AdminPayments\Gateways\Paypal;

use Store;
use Log;
use Cache;
use Exception;
use Throwable;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaypalWebhookVerificator
{
    static $initialized = false;

    public function __construct()
    {
        $this->client = new PayPalClient(config('paypal'));
        $this->client->getAccessToken();
    }

    public function verify($headers)
    {
        $hasLogs = config('logging.channels.paypal_webhooks');

        $verified = false;

        try {
            $body = file_get_contents('php://input');

            // compose signature string: The third part is the ID of the webhook ITSELF(!),
            // NOT the ID of the webhook event sent. You find the ID of the webhook
            // in Paypal's developer backend where you have created the webhook
            $data = $headers->get('Paypal-Transmission-Id').'|'.
                $headers->get('Paypal-Transmission-Time').'|'.
                env('PAYPAL_WEBHOOK_ID').'|'.
                crc32($body);

            // load certificate and extract public key
            $certUrl = $headers->get('Paypal-Cert-Url');
            $certificate = Cache::rememberForever('paypal.cert.'.md5($certUrl), function() use ($certUrl) {
                return file_get_contents($certUrl);
            });

            $pubKey=openssl_pkey_get_public($certificate);
            $key=openssl_pkey_get_details($pubKey)['key'];

            // verify data against provided signature
            $result=openssl_verify(
                $data,
                base64_decode($headers->get('Paypal-Transmission-Sig')),
                $key,
                'sha256WithRSAEncryption'
            );

            $verified = $result ? true : false;
        } catch (Exception $e){
            if ( $hasLogs ) {
                Log::channel('paypal_webhooks')->error($e);
            }
        } catch (Throwable $e){
            if ( $hasLogs ) {
                Log::channel('paypal_webhooks')->error($e);
            }
        }

        if ( $hasLogs ) {
            Log::channel('paypal_webhooks')->info([
                'body' => $body,
                'headers' => json_encode($headers->all()),
                'verified' => $verified,
            ]);
        }

        return $verified;
    }
}
