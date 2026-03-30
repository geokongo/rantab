<?php namespace Rantab\Mpesa;

/**
 * Handles raw HTTP communication with the Mpesa Daraja API.
 *
 * Responsible only for making cURL requests to Mpesa endpoints.
 * Manages OAuth token generation and caches it for the duration
 * of the request lifecycle to avoid unnecessary token fetches.
 * Returns raw decoded response arrays. Business logic lives in Mpesa.php.
 *
 * @package Rantab\Mpesa
 * @see Rantab\Mpesa\Mpesa
 */

use Rantab\RantabException;

class MpesaApi
{
    /**
     * Mpesa Daraja API base URL.
     *
     * All requests are made relative to this base endpoint.
     * Swap this for the sandbox URL during development and testing.
     *
     * @var string
     */
    private const BASE = 'https://api.safaricom.co.ke';

    /**
     * Mpesa consumer key.
     *
     * Used together with the consumer secret to generate an
     * OAuth access token for authenticating API requests.
     *
     * @var string
     */
    private string $key;

    /**
     * Mpesa consumer secret.
     *
     * Used together with the consumer key to generate an
     * OAuth access token for authenticating API requests.
     *
     * @var string
     */
    private string $secret;

    /**
     * Cached OAuth access token.
     *
     * Fetched once per instance and reused for all subsequent
     * requests to avoid hitting the token endpoint repeatedly.
     *
     * @var string|null
     */
    private ?string $token = null;

    /**
     * Create a new MpesaApi instance.
     *
     * Accepts the consumer key and secret and stores them for
     * use when generating the OAuth token for API requests.
     *
     * @param string $key Mpesa consumer key
     * @param string $secret Mpesa consumer secret
     */
    public function __construct(string $key, string $secret)
    {
        $this->key    = $key;
        $this->secret = $secret;
    }

    /**
     * Make a POST request to an Mpesa endpoint.
     *
     * Fetches a fresh OAuth token if not already cached, then
     * sends a JSON encoded payload to the given Mpesa API path.
     * Returns the decoded JSON response as an array.
     *
     * @param string $path API path e.g. '/mpesa/stkpush/v1/processrequest'
     * @param array $data Request payload
     * @return array
     * @throws RantabException
     */
    public function post(string $path, array $data): array
    {
        // Ensure we have a valid token before making the request
        $token = $this->token();

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => self::BASE . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            // Mpesa expects JSON encoded body
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                // Bearer token authentication for Mpesa
                "Authorization: Bearer {$token}",
            ],
        ]);

        $response = curl_exec($ch);

        // Catch network level failures before touching the response
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RantabException("cURL error: $err", 'mpesa');
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        // Mpesa returns errorCode on failure
        if (isset($decoded['errorCode'])) {
            throw new RantabException($decoded['errorMessage'], 'mpesa');
        }

        return $decoded;
    }

    /**
     * Fetch or return a cached OAuth access token.
     *
     * Generates a Base64 encoded credential string from the consumer
     * key and secret, then requests a token from the Mpesa OAuth
     * endpoint. Caches the token for the lifetime of this instance.
     *
     * @return string
     * @throws RantabException
     */
    private function token(): string
    {
        // Return cached token if already fetched this instance
        if ($this->token !== null) {
            return $this->token;
        }

        $ch = curl_init();

        // Base64 encode key:secret for Basic Auth as Mpesa requires
        $credentials = base64_encode("{$this->key}:{$this->secret}");

        curl_setopt_array($ch, [
            CURLOPT_URL            => self::BASE . '/oauth/v1/generate?grant_type=client_credentials',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Basic {$credentials}",
            ],
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RantabException("Token fetch failed: $err", 'mpesa');
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        // Mpesa returns access_token on success
        if (empty($decoded['access_token'])) {
            throw new RantabException('Failed to obtain Mpesa access token', 'mpesa');
        }

        // Cache the token for subsequent requests
        $this->token = $decoded['access_token'];

        return $this->token;
    }
}