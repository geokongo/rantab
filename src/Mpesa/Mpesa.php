<?php namespace Rantab\Mpesa;

/**
 * Mpesa payment processor implementation for Rantab.
 *
 * Implements the Processor interface using the Mpesa Daraja API.
 * Delegates all raw HTTP communication to MpesaApi and focuses
 * only on business logic: building STK push requests, handling
 * callbacks, and returning normalized Response objects to Rantab core.
 * Supports charge via STK push, refund, and transaction status check.
 *
 * @package Rantab\Mpesa
 * @see Rantab\Processor
 * @see Rantab\Mpesa\MpesaApi
 */

use Rantab\Processor;
use Rantab\Response;
use Rantab\RantabException;

class Mpesa implements Processor
{
    /**
     * The MpesaApi HTTP client instance.
     *
     * Used to make all raw API calls to Mpesa Daraja endpoints.
     * Instantiated in the constructor using provided credentials.
     *
     * @var MpesaApi
     */
    private MpesaApi $api;

    /**
     * Mpesa business shortcode.
     *
     * The till or paybill number registered on the Mpesa platform.
     * Used in STK push requests as the business identifier.
     *
     * @var string
     */
    private string $shortcode;

    /**
     * Mpesa Lipa Na Mpesa passkey.
     *
     * Used together with the shortcode and timestamp to generate
     * the Base64 encoded password for STK push requests.
     *
     * @var string
     */
    private string $passkey;

    /**
     * STK push callback URL.
     *
     * Mpesa will POST the payment result to this URL after the
     * customer completes or cancels the STK push prompt.
     *
     * @var string
     */
    private string $callback;

    /**
     * Create a new Mpesa processor instance.
     *
     * Accepts all required Mpesa credentials and config, stores
     * them, and instantiates the MpesaApi client for HTTP calls.
     *
     * @param string $key Mpesa consumer key
     * @param string $secret Mpesa consumer secret
     * @param string $shortcode Business shortcode or till number
     * @param string $passkey Lipa Na Mpesa passkey
     * @param string $callback URL to receive payment notifications
     */
    public function __construct(
        string $key,
        string $secret,
        string $shortcode,
        string $passkey,
        string $callback
    ) {
        $this->api       = new MpesaApi($key, $secret);
        $this->shortcode = $shortcode;
        $this->passkey   = $passkey;
        $this->callback  = $callback;
    }

    /**
     * Initiate an STK push charge request via Mpesa.
     *
     * Sends a Lipa Na Mpesa STK push prompt to the customer's phone.
     * The customer approves the payment on their handset. Result is
     * delivered asynchronously to the configured callback URL.
     *
     * @param array $data Must include: amount (int), phone (string
     *                    in 2547XXXXXXXX format), ref (string),
     *                    description (string)
     * @return Response
     * @throws RantabException
     */
    public function charge(array $data): Response
    {
        $timestamp = date('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $data['amount'],
            'PartyA'            => $data['phone'],
            'PartyB'            => $this->shortcode,
            'PhoneNumber'       => $data['phone'],
            'CallBackURL'       => $this->callback,
            'AccountReference'  => $data['ref'],
            'TransactionDesc'   => $data['description'],
        ];

        $res = $this->api->post('/mpesa/stkpush/v1/processrequest', $payload);

        return new Response(
            $res['ResponseCode'] === '0',
            $res['CheckoutRequestID'],
            $data['amount'],
            'KES',
            $res['ResponseCode'] === '0' ? 'pending' : 'failed',
            null,
            $res
        );
    }

    /**
     * Refund a transaction via Mpesa.
     *
     * Initiates a B2C reversal for a previously completed transaction.
     * Mpesa reversals are asynchronous and result is sent to callback.
     *
     * @param string $id Mpesa transaction ID to reverse
     * @param float $amt Amount to reverse in KES
     * @return Response
     * @throws RantabException
     */
    public function refund(string $id, float $amt): Response
    {
        $payload = [
            'Initiator'       => $this->shortcode,
            'TransactionID'   => $id,
            'Amount'          => $amt,
            'ReceiverParty'   => $this->shortcode,
            'ResultURL'       => $this->callback,
            'QueueTimeOutURL' => $this->callback,
            'Remarks'         => 'Rantab refund',
            'Occasion'        => 'Refund',
        ];

        $res = $this->api->post('/mpesa/reversal/v1/request', $payload);

        return new Response(
            $res['ResponseCode'] === '0',
            $id,
            $amt,
            'KES',
            $res['ResponseCode'] === '0' ? 'pending' : 'failed',
            null,
            $res
        );
    }
    /**
     * Query the status of an Mpesa transaction.
     *
     * Uses the Mpesa Transaction Status API to check the current
     * state of a transaction. Returns a normalized status string
     * consistent with other Rantab processors.
     *
     * @param string $id Mpesa CheckoutRequestID or TransactionID
     * @return string
     * @throws RantabException
     */
    public function status(string $id): string
    {
        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'CheckoutRequestID' => $id,
            'Password'          => base64_encode(
                $this->shortcode . $this->passkey . date('YmdHis')
            ),
            'Timestamp'         => date('YmdHis'),
        ];

        $res = $this->api->post('/mpesa/stkpushquery/v1/query', $payload);

        // ResultCode 0 means success, anything else is pending or failed
        return match($res['ResultCode']) {
            '0'     => 'success',
            '1032'  => 'cancelled',
            '1'     => 'failed',
            default => 'pending'
        };
    }
}