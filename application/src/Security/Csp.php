<?php

namespace App\Security;

/**
 * Holds the Content-Security-Policy nonce for the current request.
 *
 * The SecurityHeaders hook puts it in the CSP header; views put the same
 * value on their inline <script>/<style> tags (csp_nonce_attr() in
 * theme_helper.php). It is generated lazily, once per request, and is
 * empty in development, where the policy allows 'unsafe-inline' instead
 * (DevelBar needs that, and a nonce would make browsers ignore it).
 */
final class Csp
{
    /** @var string|null */
    private static $nonce;

    public static function nonce()
    {
        if (self::$nonce === null) {
            self::$nonce = (defined('ENVIRONMENT') && ENVIRONMENT === 'development')
                ? ''
                : base64_encode(random_bytes(16));
        }

        return self::$nonce;
    }

    /**
     * Forgets the nonce so the next request-like call generates a new one
     * (only useful in tests, since PHP normally runs one request per process).
     */
    public static function reset()
    {
        self::$nonce = null;
    }
}
