<?php

namespace AppTests\Unit;

use App\Security\Csp;
use PHPUnit\Framework\TestCase;

final class CspTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ENVIRONMENT')) {
            define('ENVIRONMENT', 'testing');
        }

        Csp::reset();
    }

    public function testNonceIsStableWithinARequest(): void
    {
        $this->assertSame(Csp::nonce(), Csp::nonce());
    }

    public function testNonceIsUnpredictableBase64OutsideDevelopment(): void
    {
        if (ENVIRONMENT === 'development') {
            $this->markTestSkipped('The nonce is intentionally empty in development.');
        }

        $first = Csp::nonce();
        Csp::reset();

        $this->assertNotSame($first, Csp::nonce());
        $this->assertSame(24, strlen($first));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+={0,2}$/', $first);
    }
}
