<?php

namespace AppTests\Unit;

use App\Auth\GoogleOAuth;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthTest extends TestCase
{
    private const CLIENT = '1234.apps.googleusercontent.com';
    private const NOW = 1800000000;

    private function oauth(?callable $http = null): GoogleOAuth
    {
        return new GoogleOAuth(self::CLIENT, 'secret', 'https://app.test/google/callback', null, null, $http);
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(array $override = []): array
    {
        return $override + [
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT,
            'exp' => self::NOW + 600,
            'nonce' => 'n-1',
            'sub' => '1100223344',
            'email' => 'ann@example.com',
            'email_verified' => true,
            'name' => 'Ann',
        ];
    }

    private function jwt(array $claims): string
    {
        $b = static function (string $s): string {
            return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        };

        return $b('{"alg":"none"}').'.'.$b((string) json_encode($claims)).'.sig';
    }

    // ---- PKCE ----------------------------------------------------------

    public function testPkceChallengeMatchesTheRfc7636Example(): void
    {
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', GoogleOAuth::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));
    }

    public function testVerifierAndRandomTokensAreLongAndUrlSafe(): void
    {
        $v = GoogleOAuth::newVerifier();
        $this->assertSame(1, preg_match('/^[A-Za-z0-9_-]{43}$/', $v));
        $this->assertNotSame($v, GoogleOAuth::newVerifier());
        $this->assertSame(1, preg_match('/^[a-f0-9]{48}$/', GoogleOAuth::randomToken()));
    }

    // ---- The URL to Google -----------------------------------------------

    public function testAuthorizationUrlCarriesStateNonceAndPkce(): void
    {
        $url = $this->oauth()->authorizationUrl('STATE', 'NONCE', 'VERIFIER');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertSame(self::CLIENT, $q['client_id']);
        $this->assertSame('https://app.test/google/callback', $q['redirect_uri']);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame('openid email profile', $q['scope']);
        $this->assertSame('STATE', $q['state'], 'the state must really be sent');
        $this->assertSame('NONCE', $q['nonce']);
        $this->assertSame(GoogleOAuth::challenge('VERIFIER'), $q['code_challenge']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertArrayNotHasKey('access_type', $q, 'no refresh token is asked for');
        $this->assertStringNotContainsString('VERIFIER', $url, 'the verifier itself never leaves the server');
    }

    // ---- Claims --------------------------------------------------------------

    public function testAcceptsGoodClaims(): void
    {
        $this->assertSame('', $this->oauth()->validate($this->claims(), 'n-1', self::NOW));
        $this->assertSame('', $this->oauth()->validate($this->claims(['iss' => 'accounts.google.com']), 'n-1', self::NOW));
        $this->assertSame('', $this->oauth()->validate($this->claims(['email_verified' => 'true']), 'n-1', self::NOW));
        $this->assertSame('', $this->oauth()->validate($this->claims(['aud' => [self::CLIENT], 'azp' => self::CLIENT]), 'n-1', self::NOW));
    }

    public function testRejectsEverythingThatIsNotRight(): void
    {
        $o = $this->oauth();

        $this->assertSame('wrong issuer', $o->validate($this->claims(['iss' => 'https://evil.example']), 'n-1', self::NOW));
        $this->assertSame('wrong issuer', $o->validate(array_diff_key($this->claims(), ['iss' => 1]), 'n-1', self::NOW));
        $this->assertSame('token is for another client', $o->validate($this->claims(['aud' => 'someone-else']), 'n-1', self::NOW));
        $this->assertSame('authorized party mismatch', $o->validate($this->claims(['aud' => [self::CLIENT, 'x'], 'azp' => 'x']), 'n-1', self::NOW));
        $this->assertSame('token expired', $o->validate($this->claims(['exp' => self::NOW]), 'n-1', self::NOW));
        $this->assertSame('token expired', $o->validate(array_diff_key($this->claims(), ['exp' => 1]), 'n-1', self::NOW));
        $this->assertSame('nonce mismatch', $o->validate($this->claims(), 'another', self::NOW));
        $this->assertSame('nonce mismatch', $o->validate(array_diff_key($this->claims(), ['nonce' => 1]), 'n-1', self::NOW));
        $this->assertSame('no subject', $o->validate($this->claims(['sub' => '']), 'n-1', self::NOW));
        $this->assertSame('no usable e-mail', $o->validate($this->claims(['email' => 'not-an-email']), 'n-1', self::NOW));
        $this->assertSame('no usable e-mail', $o->validate($this->claims(['email' => ['a@b.c']]), 'n-1', self::NOW));
        $this->assertSame('e-mail not verified by Google', $o->validate($this->claims(['email_verified' => false]), 'n-1', self::NOW));
        $this->assertSame('e-mail not verified by Google', $o->validate($this->claims(['email_verified' => 'yes']), 'n-1', self::NOW));
        $this->assertSame('e-mail not verified by Google', $o->validate(array_diff_key($this->claims(), ['email_verified' => 1]), 'n-1', self::NOW));
    }

    // ---- Exchanging the code ------------------------------------------------------

    public function testExchangeSendsTheCodeAndVerifierAndReadsTheIdToken(): void
    {
        $seen = null;
        $o = $this->oauth(function ($url, $form, $timeout) use (&$seen) {
            $seen = [$url, $form];

            return ['status' => 200, 'body' => (string) json_encode(['id_token' => $this->jwt($this->claims()), 'access_token' => 'x'])];
        });

        $r = $o->exchange('CODE', 'VERIFIER');

        $this->assertSame('', $r['error']);
        $this->assertSame('ann@example.com', $r['claims']['email']);
        $this->assertSame('https://oauth2.googleapis.com/token', $seen[0]);
        $this->assertSame('CODE', $seen[1]['code']);
        $this->assertSame('VERIFIER', $seen[1]['code_verifier']);
        $this->assertSame('authorization_code', $seen[1]['grant_type']);
        $this->assertSame('secret', $seen[1]['client_secret']);
    }

    public function testExchangeFailuresAreReportedNotThrown(): void
    {
        $down = $this->oauth(function () {
            return null;
        });
        $this->assertSame('transport', $down->exchange('c', 'v')['error']);

        $bad = $this->oauth(function () {
            return ['status' => 400, 'body' => '{"error":"invalid_grant"}'];
        });
        $this->assertSame('token endpoint: invalid_grant', $bad->exchange('c', 'v')['error']);

        $noToken = $this->oauth(function () {
            return ['status' => 200, 'body' => '{"access_token":"x"}'];
        });
        $this->assertSame('token endpoint: http 200', $noToken->exchange('c', 'v')['error']);

        $junk = $this->oauth(function () {
            return ['status' => 200, 'body' => '{"id_token":"not.a-jwt"}'];
        });
        $this->assertSame('unreadable id_token', $junk->exchange('c', 'v')['error']);
    }

    public function testCustomIssuersForTests(): void
    {
        $o = new GoogleOAuth(self::CLIENT, 's', 'https://app.test/cb', 'http://127.0.0.1:9/auth', 'http://127.0.0.1:9/token', null, ['http://127.0.0.1:9']);

        $this->assertSame('', $o->validate($this->claims(['iss' => 'http://127.0.0.1:9']), 'n-1', self::NOW));
        $this->assertSame('wrong issuer', $o->validate($this->claims(), 'n-1', self::NOW));
        $this->assertStringStartsWith('http://127.0.0.1:9/auth?', $o->authorizationUrl('s', 'n', 'v'));
    }
}
