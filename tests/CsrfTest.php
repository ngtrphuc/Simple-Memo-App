<?php

declare(strict_types=1);

namespace Tests;

use App\Csrf;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Csrf uses $_SESSION directly; start from a clean slate each test.
        $_SESSION = [];
    }

    #[Test]
    public function token_is_generated_and_stable_within_session(): void
    {
        $first = Csrf::token();
        $second = Csrf::token();

        $this->assertNotSame('', $first);
        $this->assertSame($first, $second, 'Token must be reused across calls in one session');
        $this->assertSame(64, strlen($first), '32 random bytes -> 64 hex chars');
    }

    #[Test]
    public function verify_accepts_the_current_token(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::verify($token));
    }

    #[Test]
    public function verify_rejects_wrong_token(): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::verify('deadbeef'));
    }

    #[Test]
    public function verify_rejects_empty_and_null(): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::verify(''));
        $this->assertFalse(Csrf::verify(null));
    }

    #[Test]
    public function verify_fails_when_no_session_token_exists(): void
    {
        // No token() call -> nothing stored in session.
        $this->assertFalse(Csrf::verify('anything'));
    }

    #[Test]
    public function field_embeds_the_token_as_hidden_input(): void
    {
        $token = Csrf::token();
        $field = Csrf::field();

        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString($token, $field);
    }
}
