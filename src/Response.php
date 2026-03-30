<?php namespace Rantab;

/**
 * Represents the result of a payment operation.
 *
 * Returned by every processor method (charge, refund, status)
 * as a consistent result object. Carries all relevant data about
 * the operation: success state, processor response, error info,
 * transaction ID, and the raw response from the processor for
 * debugging purposes. Avoids passing raw arrays around the app
 * by wrapping everything in a clean object.
 *
 * @package Rantab
 * @see Rantab\Processor
 */
class Response
{
    /**
     * Whether the operation succeeded.
     *
     * Set to true if the processor returned a successful response.
     * False if the operation failed for any reason.
     *
     * @var bool
     */
    public bool $ok;

    /**
     * The transaction ID from the processor.
     *
     * Returned by the processor after a successful charge or refund.
     * Null if the operation failed before a transaction was created.
     *
     * @var string|null
     */
    public ?string $id;

    /**
     * The transaction amount.
     *
     * Stored in standard units e.g. 10.00 for $10, 100 for 100 KES.
     * Null if not applicable to the operation.
     *
     * @var float|null
     */
    public ?float $amt;

    /**
     * The currency code.
     *
     * Three letter ISO 4217 currency code e.g. 'usd', 'KES'.
     * Null if not applicable to the operation.
     *
     * @var string|null
     */
    public ?string $currency;

    /**
     * The current status of the transaction.
     *
     * A normalized string consistent across all processors.
     * Possible values: 'success', 'pending', 'failed', 'unknown'.
     *
     * @var string|null
     */
    public ?string $status;

    /**
     * Error message if the operation failed.
     *
     * Contains a human readable error message when $ok is false.
     * Null if the operation succeeded.
     *
     * @var string|null
     */
    public ?string $error;

    /**
     * The raw response from the processor.
     *
     * Contains the full unmodified response array from the processor.
     * Useful for debugging or accessing processor specific fields
     * not covered by the standard Response properties above.
     *
     * @var array|null
     */
    public ?array $raw;

    /**
     * Create a new Response instance.
     *
     * Accepts all response fields as constructor arguments with
     * sensible defaults. Only $ok is required, all others are
     * optional and default to null.
     *
     * @param bool $ok Whether the operation succeeded
     * @param string|null $id Transaction ID
     * @param float|null $amt Transaction amount
     * @param string|null $currency Currency code
     * @param string|null $status Transaction status
     * @param string|null $error Error message
     * @param array|null $raw Raw processor response
     */
    public function __construct(
        bool $ok,
        ?string $id = null,
        ?float $amt = null,
        ?string $currency = null,
        ?string $status = null,
        ?string $error = null,
        ?array $raw = null
    ) {
        $this->ok       = $ok;
        $this->id       = $id;
        $this->amt      = $amt;
        $this->currency = $currency;
        $this->status   = $status;
        $this->error    = $error;
        $this->raw      = $raw;
    }
}