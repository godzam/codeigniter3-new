<?php

namespace AppTests\Unit;

use App\Auth\Tokens;
use PHPUnit\Framework\TestCase;

final class AuthTokensTest extends TestCase
{
    public function testATokenIs64HexCharactersAndOnlyItsHashIsMeantForStorage(): void
    {
        $t = Tokens::generate();

        $this->assertSame(1, preg_match('/^[a-f0-9]{64}$/', $t['plain']));
        $this->assertSame(64, strlen($t['hash']));
        $this->assertNotSame($t['plain'], $t['hash']);
        $this->assertSame(hash('sha256', $t['plain']), $t['hash']);
        $this->assertSame($t['hash'], Tokens::hash($t['plain']));
    }

    public function testTokensDoNotRepeat(): void
    {
        $seen = [];
        for ($i = 0; $i < 500; ++$i) {
            $seen[Tokens::generate()['plain']] = true;
        }

        $this->assertCount(500, $seen);
    }

    public function testWellFormedAcceptsOnlyLowercaseHexOfTheRightLength(): void
    {
        $this->assertTrue(Tokens::wellFormed(str_repeat('a', 64)));
        $this->assertTrue(Tokens::wellFormed(Tokens::generate()['plain']));

        $this->assertFalse(Tokens::wellFormed(str_repeat('a', 63)));
        $this->assertFalse(Tokens::wellFormed(str_repeat('a', 65)));
        $this->assertFalse(Tokens::wellFormed(str_repeat('A', 64)));
        $this->assertFalse(Tokens::wellFormed(str_repeat('g', 64)));
        $this->assertFalse(Tokens::wellFormed(str_repeat('a', 63)."\n"));
        $this->assertFalse(Tokens::wellFormed(''));
        $this->assertFalse(Tokens::wellFormed(null));
        $this->assertFalse(Tokens::wellFormed(['a']));
    }

    public function testLifetimes(): void
    {
        $this->assertSame(3600, Tokens::ttl(Tokens::RESET_PASSWORD), 'a reset link lives one hour');
        $this->assertSame(259200, Tokens::ttl(Tokens::VERIFY_EMAIL), 'a verification link lives three days');
        $this->assertTrue(Tokens::isType('verify_email'));
        $this->assertFalse(Tokens::isType('anything_else'));
    }
}
