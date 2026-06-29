<?php

declare(strict_types=1);

namespace App;

/**
 * Minimal session-backed CSRF protection.
 *
 * One token per session is generated lazily and reused for every form. Verification
 * uses hash_equals to avoid timing leaks. This guards all state-changing POST
 * requests (add / edit / delete memo, reschedule reminder, login, register).
 *
 * Requires an active session (db.php calls session_start()).
 */
final class Csrf
{
    private const string SESSION_KEY = '_csrf_token';

    /**
     * Return the current session CSRF token, creating one if needed.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY]) || ! is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Constant-time comparison of a submitted token against the session token.
     */
    public static function verify(?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        if (! is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /**
     * Verify the token from $_POST and abort with HTTP 419 if invalid.
     * Centralizes the reject path so handlers stay a single line.
     */
    public static function check(): void
    {
        $token = $_POST['csrf_token'] ?? null;

        if (! self::verify(is_string($token) ? $token : null)) {
            http_response_code(419);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Invalid or missing CSRF token.';
            exit;
        }
    }

    /**
     * Ready-to-embed hidden input for HTML forms.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(self::token(), ENT_QUOTES).'">';
    }
}
