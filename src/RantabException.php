<?php namespace Rantab;

/**
 * Base exception class for all Rantab errors.
 *
 * Thrown by processors and the Rantab core when an operation fails.
 * Extends PHP's base Exception class so it can be caught either
 * specifically as a RantabException or broadly as an Exception.
 * All processor specific errors should throw this exception.
 *
 * @package Rantab
 * @see Rantab\Processor
 */
class RantabException extends \Exception
{
    /**
     * The processor that threw the exception.
     *
     * Identifies which processor (e.g. 'stripe', 'mpesa') was
     * active when the error occurred. Useful for debugging and
     * logging processor specific failures.
     *
     * @var string|null
     */
    public ?string $processor;

    /**
     * Create a new RantabException.
     *
     * Accepts a message, optional processor name, and an optional
     * code. Passes message and code up to the parent Exception
     * class for standard PHP exception handling compatibility.
     *
     * @param string $message Error message
     * @param string|null $processor Processor name e.g. 'stripe'
     * @param int $code Optional error code
     * @param \Exception|null $prev Previous exception if chained
     */
    public function __construct(
        string $message,
        ?string $processor = null,
        int $code = 0,
        ?\Exception $prev = null
    ) {
        // Store the processor name for debugging
        $this->processor = $processor;

        parent::__construct($message, $code, $prev);
    }
}