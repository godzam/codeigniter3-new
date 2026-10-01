<?php

namespace AppTests\Unit;

use App\Security\TurnstileVerifier;
use PHPUnit\Framework\TestCase;

final class TurnstileVerifierTest extends TestCase
{
    /** @var array<int, array{url: string, fields: array<string, string>, timeout: int}> */
    private $calls = [];

    /**
     * A verifier whose HTTP layer returns $reply (or null = transport failure).
     *
     * @param array{status: int, body: string}|null $reply
     */
    private function verifier($reply, string $secret = 'secret-key'): TurnstileVerifier
    {
        $this->calls = [];

        return new TurnstileVerifier($secret, 'https://example.test/verify', 3, function ($url, $fields, $timeout) use ($reply) {
            $this->calls[] = ['url' => $url, 'fields' => $fields, 'timeout' => $timeout];

            return $reply;
        });
    }

    private function json(array $data): array
    {
        return ['status' => 200, 'body' => json_encode($data)];
    }

    public function testAcceptsAValidToken(): void
    {
        $result = $this->verifier($this->json(['success' => true]))->verify('good-token', '203.0.113.9');

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['errors']);
    }

    public function testSendsSecretTokenAndIpToTheConfiguredUrl(): void
    {
        $this->verifier($this->json(['success' => true]))->verify('good-token', '203.0.113.9');
        $call = $this->calls[0];

        $this->assertSame('https://example.test/verify', $call['url']);
        $this->assertSame(['secret' => 'secret-key', 'response' => 'good-token', 'remoteip' => '203.0.113.9'], $call['fields']);
        $this->assertSame(3, $call['timeout']);
    }

    public function testOmitsTheIpWhenUnknown(): void
    {
        $this->verifier($this->json(['success' => true]))->verify('t', null);

        $this->assertArrayNotHasKey('remoteip', $this->calls[0]['fields']);
    }

    public function testRejectsAndReportsCloudflaresErrorCodes(): void
    {
        $result = $this->verifier($this->json(['success' => false, 'error-codes' => ['timeout-or-duplicate']]))->verify('used-token');

        $this->assertFalse($result['success']);
        $this->assertSame(['timeout-or-duplicate'], $result['errors']);
    }

    public function testRejectsWithAGenericCodeWhenCloudflareGivesNone(): void
    {
        $result = $this->verifier($this->json(['success' => false]))->verify('t');

        $this->assertFalse($result['success']);
        $this->assertSame(['verification-failed'], $result['errors']);
    }

    public function testMissingTokenIsRejectedWithoutCallingCloudflare(): void
    {
        foreach (['', null] as $token) {
            $result = $this->verifier($this->json(['success' => true]))->verify($token);

            $this->assertFalse($result['success']);
            $this->assertSame(['missing-input-response'], $result['errors']);
            $this->assertSame([], $this->calls);
        }
    }

    public function testOversizedTokenIsRejectedWithoutCallingCloudflare(): void
    {
        $result = $this->verifier($this->json(['success' => true]))->verify(str_repeat('a', 2049));

        $this->assertFalse($result['success']);
        $this->assertSame(['invalid-input-response'], $result['errors']);
        $this->assertSame([], $this->calls);
    }

    public function testMissingSecretIsRejected(): void
    {
        $result = $this->verifier($this->json(['success' => true]), '')->verify('t');

        $this->assertFalse($result['success']);
        $this->assertSame(['missing-input-secret'], $result['errors']);
        $this->assertSame([], $this->calls);
    }

    public function testFailsClosedWhenCloudflareCannotBeReached(): void
    {
        $result = $this->verifier(null)->verify('t');

        $this->assertFalse($result['success']);
        $this->assertSame(['connection-failed'], $result['errors']);
    }

    public function testFailsClosedOnAnUnreadableReply(): void
    {
        foreach (['<html>502 Bad Gateway</html>', '', '{"hello":"world"}', 'null'] as $body) {
            $result = $this->verifier(['status' => 502, 'body' => $body])->verify('t');

            $this->assertFalse($result['success'], $body);
            $this->assertSame(['bad-response'], $result['errors'], $body);
        }
    }

    public function testOnlyAnExplicitTrueCountsAsSuccess(): void
    {
        foreach (['true', 1, 'yes', null] as $truthy) {
            $result = $this->verifier($this->json(['success' => $truthy]))->verify('t');

            $this->assertFalse($result['success'], var_export($truthy, true));
        }
    }
}
