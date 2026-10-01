<?php

namespace App\Exceptions;

/**
 * 404 Not Found — mirrors CI4's CodeIgniter\Exceptions\PageNotFoundException.
 *
 *     throw PageNotFoundException::forPageNotFound('Post not found.');
 */
class PageNotFoundException extends HttpException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct($message = '', array $headers = [], $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, $headers, $code, $previous);
    }

    public static function forPageNotFound($message = '')
    {
        return new self($message);
    }
}
