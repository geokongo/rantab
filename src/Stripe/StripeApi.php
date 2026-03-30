<?php namespace Rantab\Stripe;

use Rantab\RantabException;

/**
 * Handles raw HTTP communication with the Stripe API.
 *
 * Responsible only for making cURL requests to Stripe endpoints.
 * All requests are authenticated using the secret key passed in
 * during instantiation. Returns raw decoded response arrays.
 * Does not contain any business logic — that lives in Stripe.php.
 *
 * @package Rantab\Stripe
 * @see Rantab\Stripe\Stripe
 */
class StripeApi
{
    /**
     * Stripe API base URL.
     *
     * All requests are made relative to this base endpoint.
     * Defined as a constant to avoid magic strings in methods.
     *
     * @var string
     */
    private const BASE = 'https://api.stripe.com/v1';

    /**
     * Stripe secret key.
     *
     * Used to authenticate every request to the Stripe API.
     * Passed in via the constructor and never exposed publicly.
     *
     * @var string
     */
    private string $key;

    /**
     * Create a new StripeApi instance.
     *
     * Accepts the Stripe secret key and stores it for use in
     * all subsequent requests made by this instance.
     *
     * @param string $key Stripe secret key
     */
    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * Make a POST request to a Stripe endpoint.
     *
     * Sends form encoded data to the given Stripe API path and
     * returns the decoded JSON response as an array. Throws a
     * RantabException if the request fails or Stripe returns an error.
     *
     * @param string $path API path e.g. '/payment_intents'
     * @param array $data Request payload
     * @return array
     * @throws RantabException
     */
    public function post(string $path, array $data): array
    {
        return $this->request('POST', $path, $data);
    }

    /**
     * Make a GET request to a Stripe endpoint.
     *
     * Retrieves data from the given Stripe API path and returns
     * the decoded JSON response as an array. Throws a
     * RantabException if the request fails or Stripe returns an error.
     *
     * @param string $path API path e.g. '/payment_intents/pi_123'
     * @return array
     * @throws RantabException
     */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * Core cURL request handler.
     *
     * Builds and executes a cURL request to the Stripe API using
     * the stored secret key for authentication. Decodes the JSON
     * response and throws RantabException on failure or Stripe error.
     *
     * @param string $method HTTP method e.g. 'POST', 'GET'
     * @param string $path API path relative to base URL
     * @param array $data Optional request payload for POST requests
     * @return array
     * @throws RantabException
     */
    private function request(string $method, string $path, array $data = []): array
    {
        $ch = curl_init();

        // Build full URL from base and path
        $url = self::BASE . $path;

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            // Authenticate with secret key via HTTP Basic Auth
            CURLOPT_USERPWD        => $this->key . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        if ($method === 'POST') {
            // Encode payload as form data for Stripe compatibility
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);

        // Catch network level failures before touching the response
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RantabException("cURL error: $err", 'stripe');
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        // Stripe returns an 'error' key on failure
        if (isset($decoded['error'])) {
            throw new RantabException($decoded['error']['message'], 'stripe');
        }

        return $decoded;
    }
}