<?php

namespace App\Security;

/**
 * Checks a Cloudflare Turnstile response token against Cloudflare's
 * siteverify endpoint. Pure PHP: the HTTP call is injectable so it can be
 * unit-tested without the network.
 *
 * It fails closed — a missing/oversized token, a network error, or an
 * unreadable reply all count as "not verified", so an outage at Cloudflare
 * can't be used to skip the check.
 *
 *     $verifier = new TurnstileVerifier($secret);
 *     $result = $verifier->verify($_POST['cf-turnstile-response'], $ip);
 *     if (!$result['success']) { ... $result['errors'] ... }
 */
final class TurnstileVerifier
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Cloudflare documents tokens as at most 2048 characters. */
    private const MAX_TOKEN_LENGTH = 2048;

    /** @var string */
    private $secret;

    /** @var string */
    private $url;

    /** @var int */
    private $timeout;

    /** @var callable(string, array<string, string>, int): ?array{status: int, body: string} */
    private $http;

    /**
     * @param callable|null $http fn(string $url, array $fields, int $timeout): ?array{status:int, body:string};
     *                            null on a transport failure. Defaults to cURL (or streams if cURL is missing).
     */
    public function __construct($secret, $url = self::VERIFY_URL, $timeout = 5, $http = null)
    {
        $this->secret = (string) $secret;
        $this->url = $url ?: self::VERIFY_URL;
        $this->timeout = (int) $timeout;
        $this->http = $http ?: [self::class, 'post'];
    }

    /**
     * @return array{success: bool, errors: array<int, string>}
     */
    public function verify($token, $ip = null)
    {
        $token = (string) $token;

        if ($this->secret === '') {
            return $this->fail('missing-input-secret');
        }
        if ($token === '') {
            return $this->fail('missing-input-response');
        }
        if (strlen($token) > self::MAX_TOKEN_LENGTH) {
            return $this->fail('invalid-input-response');
        }

        $fields = ['secret' => $this->secret, 'response' => $token];
        if ($ip !== null && $ip !== '') {
            $fields['remoteip'] = (string) $ip;
        }

        $reply = ($this->http)($this->url, $fields, $this->timeout);

        if (!is_array($reply) || !isset($reply['body'])) {
            return $this->fail('connection-failed');
        }

        $data = json_decode((string) $reply['body'], true);
        if (!is_array($data) || !array_key_exists('success', $data)) {
            return $this->fail('bad-response');
        }

        if ($data['success'] === true) {
            return ['success' => true, 'errors' => []];
        }

        $errors = array_values(array_filter((array) ($data['error-codes'] ?? []), 'is_string'));

        return ['success' => false, 'errors' => $errors ?: ['verification-failed']];
    }

    /**
     * Default transport: POST form fields, return status + body, or null
     * if the request couldn't be made at all.
     *
     * @param array<string, string> $fields
     *
     * @return array{status: int, body: string}|null
     */
    public static function post($url, array $fields, $timeout)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($fields),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            return $body === false ? null : ['status' => $status, 'body' => (string) $body];
        }

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => http_build_query($fields),
            'timeout' => $timeout,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);

        return $body === false ? null : ['status' => 200, 'body' => $body];
    }

    /**
     * @return array{success: bool, errors: array<int, string>}
     */
    private function fail($code)
    {
        return ['success' => false, 'errors' => [$code]];
    }
}
