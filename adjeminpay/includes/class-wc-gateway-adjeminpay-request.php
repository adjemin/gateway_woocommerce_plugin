<?php
/**
 * Class WC_Gateway_Adjeminpay_Request file.
 *
 * @package WooCommerce\Gateways
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates requests to send to AdjeminPay.
 */
class WC_Gateway_Adjeminpay_Request {

    /**
     * API's URL
     */
    const API_BASE_URL = "https://api.adjem.in/v3";

    /**
     * Stores line items to send to Adjeminpay.
     *
     * @var array
     */
    protected $line_items = array();

    /**
     * Pointer to gateway making the request.
     *
     * @var WC_Gateway_Adjeminpay
     */
    protected $gateway;

    /**
     * Endpoint for requests from AdjeminPay.
     *
     * @var string
     */
    protected $notify_url;

    /**
     * Constructor.
     *
     * @param WC_Gateway_Adjeminpay $gateway Adjeminpay gateway object.
     */
    public function __construct( $gateway ) {
        $this->gateway    = $gateway;
        $this->notify_url = WC()->api_request_url( 'WC_Gateway_Adjeminpay' );
    }

    /**
     * Get the AdjeminPay request URL for an order.
     *
     * @param WC_Order $order Order object.
     * @return string
     * @throws Exception
     */
    public function get_request_url( $order ){

        $clientId = $this->gateway->get_option('client_id');
        $clientSecret = $this->gateway->get_option('client_secret');
        $sellerUsername = $this->gateway->get_option('seller_username');

        $transaction_id = "woocommerce_order_" . $order->get_id() . "_" . time();

        $webhook_url = $this->notify_url;
        $return_url  = esc_url_raw( add_query_arg( 'utm_nooverride', '1', $this->gateway->get_return_url( $order ) ) );
        $cancel_url = esc_url_raw( $order->get_cancel_order_url_raw() );

        $payment_args = array(
            'amount' => intval($order->get_total()),
/*            'custom' => wp_json_encode(
                array(
                    'order_id'  => $order->get_id(),
                    'order_key' => $order->get_order_key(),
                )
            ),*/
            'customer_recipient_number' =>  $order->get_billing_phone()??'',
            'customer_email' => $order->get_billing_email()??'',
            'customer_firstname' => $order->get_billing_first_name()??'',
            'customer_lastname' => $order->get_billing_last_name()??'',
            'designation' => "Paiement de facture",
            'currency_code' => "XOF",
            'merchant_trans_id' => $transaction_id,
            'seller_username' => $sellerUsername,
            'payment_type' => 'gateway',
            'webhook_url' => $webhook_url,
            'return_url' => $return_url,
            "cancel_url" => $cancel_url,
        );


        $payment_url = $this->get_payment_url($clientId, $clientSecret, $payment_args);

        WC_Gateway_Paypal::log( 'AdjeminPay Request Args for order ' . $order->get_order_number(). '| URL: '.$payment_url);

        return $payment_url;
    }

/*    private function get_payment_signature($transaction_id, $designation, $amount){

        $adp_amount = urlencode($amount);
        $adp_designation = urlencode($designation);
        $adp_transaction_id = urlencode($transaction_id);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_BASE_URL . '/v2/getSignature',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "amount=$adp_amount&designation=$adp_designation&transaction_id=$adp_transaction_id",
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $json = (array)json_decode($response, true);

        if (array_key_exists('signature', $json) && !empty($json['signature'])) {
            return $json['signature'];
        } else {
            throw new Exception("We got error on getting signature");
        }


    }*/

    private function get_access_token($clientId, $clientSecret)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_BASE_URL . '/oauth/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "grant_type=client_credentials&client_id=$clientId&client_secret=$clientSecret",
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $json = (array)json_decode($response, true);

        if (array_key_exists('access_token', $json) && !empty($json['access_token'])) {
            return $json['access_token'];
        } else {
            if (array_key_exists('message', $json) && !empty($json['message'])) {
                $message = $json['message'];
            } else {
                $message = "Client authentication failed";
            }
            throw new Exception($message);
        }
    }

    public function get_payment_status($clientId, $clientSecret, $merchantTransactionId)
    {
        try {
            $token = $this->get_access_token($clientId, $clientSecret);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => self::API_BASE_URL."/gateway/merchants/payment/".$merchantTransactionId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Accept: application/json',
                    "Authorization: Bearer $token"
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);

            $json = (array)json_decode($response, true);

            if (array_key_exists('data', $json) && !empty($json['data'])) {
                return $json['data']['status'];
            } else {
                return "FAILED";
            }
        } catch (Exception $exception) {
            return "FAILED";
        }
    }

    private function get_payment_url($clientId, $clientSecret, $params)
    {
        $token = $this->get_access_token($clientId, $clientSecret);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_BASE_URL . '/gateway/merchants/create_payment',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer '.$token
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $json = (array)json_decode($response, true);

        echo "<!-- DEBUG\n" . print_r($json, true) . "\n-->";

        if (array_key_exists('data', $json) && !empty($json['data'])) {
            return $json['data']['gateway_payment_url'];
        } else {
            if (array_key_exists('message', $json) && !empty($json['message'])) {
                $message = $json['message'];
            } else {
                $message = "Error when getting payment URL";
            }
            throw new Exception($message);
        }
    }
}
