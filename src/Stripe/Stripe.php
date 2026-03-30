<?php namespace Rantab\Stripe;

/**
 * Stripe payment processor implementation for Rantab.
 *
 * Implements the Processor interface using the Stripe API.
 * Delegates all raw HTTP communication to StripeApi and focuses
 * only on business logic: building requests, handling responses,
 * and returning normalized Response objects back to Rantab core.
 * Supports charge, refund, and transaction status retrieval.
 *
 * @package Rantab\Stripe
 * @see Rantab\Processor
 * @see Rantab\Stripe\StripeApi
 */

use Rantab\Processor;
use Rantab\Response;
use Rantab\RantabException;

class Stripe implements Processor
{
    /**
     * The StripeApi HTTP client instance.
     *
     * Used to make all raw API calls to Stripe endpoints.
     * Injected via the constructor for easy testing and flexibility.
     *
     * @var StripeApi
     */
    private StripeApi $api;

    /**
     * Create a new Stripe processor instance.
     *
     * Accepts the Stripe secret key and instantiates the StripeApi
     * client internally. All subsequent API calls use this client.
     *
     * @param string $key Stripe secret key
     */
    public function __construct(string $key)
    {
        $this->api = new StripeApi($key);
    }

    /**
     * Charge a customer via Stripe.
     *
     * Creates a PaymentIntent on Stripe with the provided amount,
     * currency, and payment method. Returns a normalized Response
     * object with the transaction ID, amount, status, and raw data.
     *
     * @param array $data Must include: amount (int, smallest unit),
     *                    currency (string), payment_method (string)
     * @return Response
     * @throws RantabException
     */
    public function charge(array $data): Response
    {
        // Stripe requires amount in smallest currency unit e.g. cents
        $payload = [
            'amount'         => $data['amount'],
            'currency'       => $data['currency'],
            'payment_method' => $data['payment_method'],
            'confirm'        => 'true',
            'return_url'     => $data['return_url'] ?? '',
        ];

        $res = $this->api->post('/payment_intents', $payload);

        // Map Stripe's response to a normalized Response object
        return new Response(
            $res['status'] === 'succeeded',
            $res['id'],
            $res['amount'] / 100, // convert back from cents
            $res['currency'],
            $res['status'],
            null,
            $res
        );
    }

    /**
     * Refund a Stripe transaction.
     *
     * Issues a full or partial refund against a previously successful
     * PaymentIntent. Returns a normalized Response with refund status.
     *
     * @param string $id Stripe PaymentIntent ID e.g. 'pi_123'
     * @param float $amt Amount to refund in standard units e.g. 10.00
     * @return Response
     * @throws RantabException
     */
    public function refund(string $id, float $amt): Response
    {
        // Stripe refunds are created against a PaymentIntent
        $res = $this->api->post('/refunds', [
            'payment_intent' => $id,
            // Convert to cents as Stripe expects smallest currency unit
            'amount'         => (int) ($amt * 100),
        ]);

        return new Response(
            $res['status'] === 'succeeded',
            $res['id'],
            $res['amount'] / 100,
            null,
            $res['status'],
            null,
            $res
        );
    }

    /**
     * Get the status of a Stripe transaction.
     *
     * Retrieves the current status of a PaymentIntent from Stripe.
     * Returns a plain normalized status string for consistency across
     * processors e.g. 'success', 'pending', 'failed'.
     *
     * @param string $id Stripe PaymentIntent ID e.g. 'pi_123'
     * @return string
     * @throws RantabException
     */
    public function status(string $id): string
    {
        $res = $this->api->get("/payment_intents/{$id}");

        // Normalize Stripe statuses to Rantab standard strings
        switch ($res['status']) {
            case 'succeeded':
                return 'success';
            case 'processing':
            case 'requires_payment_method':
            case 'requires_confirmation':
            case 'requires_action':
                return 'pending';
            case 'canceled':
                return 'failed';
            default:
                return 'unknown';
        }
    }
}