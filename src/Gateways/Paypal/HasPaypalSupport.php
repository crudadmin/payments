<?php

namespace AdminPayments\Gateways\Paypal;

use App\Models\Order\OrdersItem;
use Log;
use Propaganistas\LaravelPhone\PhoneNumber;
use Store;

trait HasPaypalSupport
{
    protected function buildOrderRequest($intent = 'CAPTURE')
    {
        $order = $this->getOrder();

        $data = [
            'intent' => $intent,
            'id' => $this->getPayment()->getKey(),
            'application_context' => [
                'brand_name' => 'Gon Market',
                'return_url' => $this->getResponseUrl('status'),
                'cancel_url' => $this->getResponseUrl('status'),
                'landing_page' => 'BILLING',
                'user_action' => 'PAY_NOW',
            ],
            'payer' => [
                'email_address' => $order->email,
                'name' => [
                  'given_name' => $order->firstname,
                  'surname' => $order->lastname
                ],
                'address' => $this->buildOrderAddress(),
            ],
            'purchase_units' => [
                [
                    'reference_id' => $order->number,
                    'amount' => [
                        'currency_code' => strtoupper(Store::getCurrency()->code),
                        'value' => Store::roundNumber($order->price_vat),
                    ],
                ]
            ],
        ];

        //We does not want to fill phone number, because there may be an error from PP validation
        if ( $order->phone ){
            $data['payer']['phone'] = [
                'phone_type' => 'MOBILE',
                'phone_number' => [
                    'national_number' => $this->toPhoneNumberFormat($order->phone)
                ]
            ];
        }

        return $data;
    }

    private function buildOrderAddress()
    {
        $order = $this->getOrder();

        return [
            'address_line_1' => $order->street,
            'admin_area_2' => $order->city,
            // 'admin_area_1' => $order->country->name,
            'postal_code' => $order->zipcode,
            'country_code' => strtoupper($order->country->iso3166 ?: $order->country->code),
        ];
    }

    public function toPhoneNumberFormat($phone)
    {
        $phone = PhoneNumber::make($phone, $this->getOrder()->country->code);
        $phone = $phone->formatNational();
        $phone = str_replace(' ', '', $phone);

        return $phone;
    }

    public function logPaypalResponse($message, $response)
    {
        Log::channel('store')->info($message, $response);
    }
}
