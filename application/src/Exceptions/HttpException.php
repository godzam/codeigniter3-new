<?php

namespace App\Exceptions;

/**
 * An exception that maps directly to an HTTP response — the CI3
 * equivalent of Laravel's Symfony HttpException / CI4's HTTPException.
 *
 * Throw it (or call abort()) from anywhere — a controller, a model, a
 * library — and MY_Exceptions renders the matching error page
 * (application/views/errors/html/{status}.php) or a JSON body for API
 * requests, with the right status code and headers. Unlike a plain
 * exception, its message is considered safe to show to the user, and it
 * isn't written to the error log for 4xx statuses (see
 * application/config/errors.php).
 */
class HttpException extends \RuntimeException
{
    /** @var int */
    protected $statusCode;

    /** @var array<string, string> */
    protected $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct($statusCode, $message = '', array $headers = [], $code = 0, ?\Throwable $previous = null)
    {
        $this->statusCode = (int) $statusCode;
        $this->headers = $headers;

        parent::__construct((string) $message, (int) $code, $previous);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders()
    {
        return $this->headers;
    }
}
