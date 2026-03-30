<?php namespace Rantab;

/**
 * Contract for all payment processors in Rantab.
 *
 * Defines the three core methods every processor must implement: charge, refund, and status.
 * Implementing this interface guarantees a consistent API across Stripe, Mpesa, and any future processor.
 * Any processor that does not implement all three methods will throw a PHP fatal error at runtime.
 *
 * @package Rantab
 * @see Rantab\Stripe\Stripe
 * @see Rantab\Mpesa\Mpesa
 */
interface Processor
{
    /**
     * Charge a customer.
     *
     * Initiates a payment request against the configured processor.
     * Returns a Response object indicating success or failure.
     *
     * @param array $data Payment details (amount, currency, token, etc.)
     * @return Response
     * @throws RantabException
     */
    public function charge(array $data): Response;

    /**
     * Refund a transaction.
     *
     * Reverses a previously successful charge either fully or partially.
     * Returns a Response object with the refund result.
     *
     * @param string $id Transaction ID to refund
     * @param float $amt Amount to refund
     * @return Response
     * @throws RantabException
     */
    public function refund(string $id, float $amt): Response;

    /**
     * Get the status of a transaction.
     *
     * Queries the processor for the current state of a transaction.
     * Returns a plain status string e.g. 'success', 'pending', 'failed'.
     *
     * @param string $id Transaction ID
     * @return string
     * @throws RantabException
     */
    public function status(string $id): string;
}