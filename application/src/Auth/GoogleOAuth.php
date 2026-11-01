<?php

namespace App\Auth;

/**
 * "Sign in with Google": the OpenID Connect authorization-code flow with PKCE.
 *
 *   1. authorizationUrl() sends the visitor to Google with a one-time `state`
 *      (CSRF protection), a `nonce` (replay protection) and a PKCE challenge
 *      (so a stolen authorization code is useless without our secret verifier).
 *   2. Google sends the visitor back with `code` and the same `state`.
 *   3. exchange() trades the code for an ID token at Google's token endpoint over
 *      TLS, and validate() checks the claims in it.
 *
 * The ID token arrives straight from Google's token endpoint over TLS, which
 * OpenID Connect Core (section 3.1.3.7) accepts in place of checking its
 * signature, so no JWT library is needed. Never feed this class a token that
 * came from the browser.
 *
 * Pure PHP except the default HTTP call, which can be replaced for tests.
 */
final class GoogleOAuth
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** @var string */
    private $clientId;

    /** @var string */
    private $clientSecret;

    /** @var string */
    private $redirectUri;

    /** @var string */
    private $authUrl;

    /** @var string */
    private $tokenUrl;

    /** @var callable(string, array<string, string>, int): ?array{status: int, body: string} */
    private $http;

    /** @var array<int, string> */
    private $issuers;

    /**
     * @param callable|null $http    fn(string $url, array $form, int $timeout): ?array{status:int, body:string};
     *                               null on a transport failure. Defaults to cURL.
     * @param array|null    $issuers the `iss` values accepted; Google's two by default (tests use their own)
     */
    public function __construct($clientId, $clientSecret, $redirectUri, $authUrl = null, $tokenUrl = null, $http = null, $issuers = null)
    {
        $this->issuers = $issuers ?: ['https://accounts.google.com', 'accounts.google.com'];
        $this->clientId = (string) $clientId;
        $this->clientSecret = (string) $clientSecret;
        $this->redirectUri = (string) $redirectUri;
        $this->authUrl = $authUrl ?: self::AUTH_URL;
        $this->tokenUrl = $tokenUrl ?: self::TOKEN_URL;
        $this->http = $http ?: [self::class, 'post'];
    }

    /**
     * @return string a random value for `state` or `nonce`
     */
    public static function randomToken()
    {
        return bin2hex(random_bytes(24));
    }

    /**
     * @return string the PKCE code verifier: 43 URL-safe characters (RFC 7636)
     */
    public static function newVerifier()
    {
        return self::base64url(random_bytes(32));
    }

    /**
     * @return string the S256 PKCE challenge for a verifier (RFC 7636, section 4.2)
     */
    public static function challenge($verifier)
    {
        return self::base64url(hash('sha256', (string) $verifier, true));
    }

    /**
     * @return string
     */
    public function authorizationUrl($state, $nonce, $verifier)
    {
        return $this->authUrl.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::challenge($verifier),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Trades the authorization code for the visitor's claims.
     *
     * @return array{claims: array<string, mixed>|null, error: string}
     */
    public function exchange($code, $verifier)
    {
        $response = ($this->http)($this->tokenUrl, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => (string) $code,
            'code_verifier' => (string) $verifier,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ], 8);

        if ($response === null) {
            return ['claims' => null, 'error' => 'transport'];
        }

        $body = json_decode($response['body'], true);
        if ($response['status'] !== 200 || !is_array($body) || empty($body['id_token']) || !is_string($body['id_token'])) {
            $reason = is_array($body) && isset($body['error']) && is_string($body['error']) ? substr($body['error'], 0, 60) : 'http '.$response['status'];

            return ['claims' => null, 'error' => 'token endpoint: '.$reason];
        }

        $claims = self::claims($body['id_token']);

        return $claims === null ? ['claims' => null, 'error' => 'unreadable id_token'] : ['claims' => $claims, 'error' => ''];
    }

    /**
     * The claims of a JWT's payload. The signature is NOT checked, see the class comment.
     *
     * @return array<string, mixed>|null
     */
    public static function claims($jwt)
    {
        $parts = explode('.', (string) $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = $json === false ? null : json_decode($json, true);

        return is_array($claims) ? $claims : null;
    }

    /**
     * Whether the claims describe a sign-in we may accept.
     *
     * @param array<string, mixed> $claims
     *
     * @return string an empty string when fine, otherwise the reason (for the log, not for the visitor)
     */
    public function validate(array $claims, $nonce, $now)
    {
        if (!in_array($claims['iss'] ?? null, $this->issuers, true)) {
            return 'wrong issuer';
        }

        $aud = $claims['aud'] ?? null;
        if (!(is_string($aud) && hash_equals($this->clientId, $aud)) && !(is_array($aud) && in_array($this->clientId, $aud, true))) {
            return 'token is for another client';
        }
        if (is_array($aud) && ($claims['azp'] ?? null) !== $this->clientId) {
            return 'authorized party mismatch';
        }

        if (!isset($claims['exp']) || !is_numeric($claims['exp']) || (int) $claims['exp'] <= (int) $now) {
            return 'token expired';
        }

        if (!isset($claims['nonce']) || !is_string($claims['nonce']) || !hash_equals((string) $nonce, $claims['nonce'])) {
            return 'nonce mismatch';
        }

        if (empty($claims['sub']) || !is_string($claims['sub']) || strlen($claims['sub']) > 64) {
            return 'no subject';
        }

        if (empty($claims['email']) || !is_string($claims['email']) || strlen($claims['email']) > 190 || filter_var($claims['email'], FILTER_VALIDATE_EMAIL) === false) {
            return 'no usable e-mail';
        }

        // Google also sends the string "true" in some older tokens.
        if (($claims['email_verified'] ?? false) !== true && ($claims['email_verified'] ?? null) !== 'true') {
            return 'e-mail not verified by Google';
        }

        return '';
    }

    private static function base64url($raw)
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @param array<string, string> $form
     *
     * @return array{status: int, body: string}|null
     */
    public static function post($url, array $form, $timeout)
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($form),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return is_string($body) ? ['status' => $status, 'body' => $body] : null;
    }
}
