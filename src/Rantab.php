<?php namespace Rantab;

/**
 * Main entry point for the Rantab payment library.
 *
 * Bootstraps the library with config, resolves the correct processor,
 * and exposes a clean unified API for charging, refunding, and checking
 * transaction status. The calling application interacts only with this
 * class and never directly with individual processor implementations.
 * Supports Stripe and Mpesa out of the box with room for more processors.
 *
 * @package Rantab
 * @see Rantab\Processor
 * @see Rantab\Response
 */

use Rantab\Stripe\Stripe;
use Rantab\Mpesa\Mpesa;

class Rantab
{
    /**
     * The resolved payment processor instance.
     *
     * Set during instantiation based on the processor key in config.
     * All charge, refund, and status calls are delegated to this.
     *
     * @var Processor
     */
    private Processor $processor;

    /**
     * Create a new Rantab instance.
     *
     * Accepts a config array and resolves the correct processor based
     * on the 'processor' key. Throws RantabException if the processor
     * is not recognized or required config keys are missing.
     *
     * Example config for Stripe:
     * [
     *   'processor' => 'stripe',
     *   'key'       => 'sk_live_xxx'
     * ]
     *
     * Example config for Mpesa:
     * [
     *   'processor' => 'mpesa',
     *   'key'       => 'consumer_key',
     *   'secret'    => 'consumer_secret',
     *   'shortcode' => '174379',
     *   'passkey'   => 'mpesa_passkey',
     *   'callback'  => 'https://example.com/mpesa/callback'
     * ]
     *
     * @param array $config Processor configuration
     * @throws RantabException
     */
    public function __construct(array $config)
    {
        $this->processor = $this->resolve($config);
    }

    /**
     * Charge a customer using the configured processor.
     *
     * Delegates directly to the active processor's charge method.
     * Returns a normalized Response object regardless of processor.
     *
     * @param array $data Payment details (amount, currency, etc.)
     * @return Response
     * @throws RantabException
     */
    public function charge(array $data): Response
    {
        return $this->processor->charge($data);
    }

    /**
     * Refund a transaction using the configured processor.
     *
     * Delegates directly to the active processor's refund method.
     * Returns a normalized Response object regardless of processor.
     *
     * @param string $id Transaction ID to refund
     * @param float $amt Amount to refund
     * @return Response
     * @throws RantabException
     */
    public function refund(string $id, float $amt): Response
    {
        return $this->processor->refund($id, $amt);
    }

    /**
     * Get the status of a transaction via the configured processor.
     *
     * Delegates directly to the active processor's status method.
     * Returns a normalized status string e.g. 'success', 'pending'.
     *
     * @param string $id Transaction ID to query
     * @return string
     * @throws RantabException
     */
    public function status(string $id): string
    {
        return $this->processor->status($id);
    }

    /**
     * Resolve and instantiate the correct processor from config.
     *
     * Reads the 'processor' key from the config array and instantiates
     * the matching processor class with the remaining config values.
     * Throws RantabException if the processor key is unknown or config
     * values required by the processor are missing.
     *
     * @param array $config Full configuration array
     * @return Processor
     * @throws RantabException
     */
    private function resolve(array $config): Processor
    {
        if (empty($config['processor'])) {
            throw new RantabException('No processor specified in config.');
        }

        // Resolve processor by name
        switch ($config['processor']) {
            case 'stripe':
                return $this->resolveStripe($config);
            case 'mpesa':
                return $this->resolveMpesa($config);
            default:
                throw new RantabException(
                    "Unknown processor: {$config['processor']}"
                );
        }
    }

    /**
     * Instantiate and return a Stripe processor.
     *
     * Validates that the required 'key' config value is present
     * then instantiates and returns a configured Stripe instance.
     *
     * @param array $config Must include: key
     * @return Stripe
     * @throws RantabException
     */
    private function resolveStripe(array $config): Stripe
    {
        // Secret key is the only requirement for Stripe
        if (empty($config['key'])) {

            throw new RantabException('Stripe requires a secret key.', 'stripe');
        }

        return new Stripe($config['key']);
    }

    /**
     * Instantiate and return an Mpesa processor.
     *
     * Validates that all required Mpesa config values are present
     * then instantiates and returns a configured Mpesa instance.
     *
     * @param array $config Must include: key, secret, shortcode,
     *                      passkey, callback
     * @return Mpesa
     * @throws RantabException
     */
    private function resolveMpesa(array $config): Mpesa
    {
        // All five values are required for Mpesa to function
        $required = ['key', 'secret', 'shortcode', 'passkey', 'callback'];

        foreach ($required as $field) {

            if (empty($config[$field])) {
                
                throw new RantabException(
                    "Mpesa requires '{$field}' in config.", 'mpesa'
                );
            }
        }

        return new Mpesa(
            $config['key'],
            $config['secret'],
            $config['shortcode'],
            $config['passkey'],
            $config['callback']
        );
    }
}