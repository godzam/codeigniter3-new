<?php

namespace App\Auth;

/**
 * One-time tokens for e-mailed links (verify an address, reset a password).
 *
 * The token in the link is 32 random bytes from the operating system's
 * secure generator, written as 64 hex characters. Only its SHA-256 hash is
 * stored, so a copy of the database cannot be turned into working links.
 * (A hash of a high-entropy random value needs no salt or slow hashing.)
 */
final class Tokens
{
    public const VERIFY_EMAIL = 'verify_email';
    public const RESET_PASSWORD = 'reset_password';

    /** Seconds a link keeps working. */
    private const TTL = [
        self::VERIFY_EMAIL => 259200, // 3 days
        self::RESET_PASSWORD => 3600, // 1 hour
    ];

    /**
     * @return array{plain: string, hash: string} "plain" goes into the e-mail, "hash" into the database
     */
    public static function generate()
    {
        $plain = bin2hex(random_bytes(32));

        return ['plain' => $plain, 'hash' => self::hash($plain)];
    }

    public static function hash($plain)
    {
        return hash('sha256', (string) $plain);
    }

    /**
     * Whether a string has the shape of a token, checked before the database is asked.
     */
    public static function wellFormed($plain)
    {
        return is_string($plain) && preg_match('/^[a-f0-9]{64}$/', $plain) === 1;
    }

    public static function isType($type)
    {
        return isset(self::TTL[$type]);
    }

    /**
     * @return int seconds
     */
    public static function ttl($type)
    {
        return self::TTL[$type] ?? 3600;
    }
}
