# Refactor Changes

This refactor extracts domain logic into a tested `src/` layer, adds CSRF protection
and session hardening, and wires up a generic quality toolchain (PHPUnit, PHPStan at
max level, Pint, Rector). No framework was introduced; the app stays plain PHP + PDO + SQLite.

## 2026-06-30 Maintenance pass

- Removed unused `barryvdh/laravel-debugbar` from `require-dev`, which also dropped the
  leftover Laravel/Symfony transitive tree from `composer.lock`.
- Expanded `phpstan.neon` coverage to include `auth.php`, `db.php`, `index.php`, and
  `lang.php` instead of only `src/` and `tests/`.
- Tightened the plain-PHP bootstrap and entrypoints so the new PHPStan coverage passes
  without baselines or suppressions:
  - `db.php` now exposes a typed `memoAppDatabase()` bootstrap, guards directory creation,
    and handles schema introspection safely.
  - `auth.php` and `index.php` now normalize request/session data before use.
  - `src/MemoRepository.php` now hydrates memo rows to a stable typed shape.
  - `lang.php` now normalizes translation access for predictable string returns.
- Removed remaining user-facing English fallback literals from `memo-ui.js`; runtime UI
  copy now comes from `window.memoAppConfig.text`.

## 2026-06-30 Latest-version bump

- Raised the runtime constraint from PHP `^8.3` to `^8.5`.
- Updated direct dev tools to the latest stable versions verified for this pass:
  - `phpunit/phpunit` `11.5.55` -> `13.2.1`
  - `phpstan/phpstan` `2.2.2` -> kept at latest stable
  - `rector/rector` `2.5.2` -> kept at latest stable
  - `laravel/pint` `1.29.3` -> kept at latest stable
  - `electron` `42.5.0` -> `42.5.1`
- Added `failOnDeprecation="true"` to `phpunit.xml` so deprecations break CI early on
  the newer toolchain.
- Applied the only PHP-8.5-driven Rector change in app code: dropped unnecessary
  parentheses from a local `new DateTimeImmutable(...)` call in `src/MemoRepository.php`.
- Verified the updated stack with PHPUnit 13, PHPStan 2.2, Pint 1.29, Rector 2.5,
  `composer audit`, `npm audit`, the live web smoke test, and an Electron startup check.

## New files

### `src/Reminder.php`
Pure reminder/repeat date math, extracted verbatim (behavior-preserving) from the
top of the old `index.php`. No I/O, no session, no `t()`. Fully unit-testable.
Contains: date parsing/formatting, repeat-pattern normalization, config encode/decode,
and the next-occurrence calculators including the missed-cycle `do...while` and the
`intdiv` fast-jump for second-based units.

### `src/RepeatLabel.php`
Display labels for repeat patterns (dropdown options, hints, weekday names, the
"Every 2 hours" summary). Split from `Reminder` because labels depend on language.
Instead of calling global `t()`, every method takes a translator callable, so the
class is decoupled from the i18n bootstrap and testable with a stub.

### `src/Csrf.php`
Session-backed CSRF protection. One token per session, `hash_equals` verification,
`Csrf::check()` aborts with HTTP 419 on a bad/missing token, `Csrf::field()` emits the
hidden input for forms.

### `src/MemoRepository.php`
All memo DB access behind a class, with `user_id` scoping on every query (ownership
enforced in SQL, never trusted from the UI). `readonly` class taking a `PDO` in the
constructor.

### `tests/ReminderTest.php`
26-assertion coverage of the repeat scheduler: missed-cycle skip, interval fast-jump
vs calendar loop, weekly/monthly selection, boundary and malformed-input edge cases.

### `tests/CsrfTest.php`
Token generation stability, verify accept/reject, empty/null handling, field output.

### Toolchain configs
`composer.json` (PSR-4 `App\` -> `src/`, dev tools), `phpstan.neon` (level max),
`rector.php` (PHP sets + quality, no Laravel), `pint.json` (PSR-12),
`phpunit.xml`, `.gitignore`.

## Modified files

### `db.php`
One line added: `require __DIR__.'/vendor/autoload.php';` so the `App\` classes load.
**Consequence:** `composer install` is now required before the app (browser or Electron)
will boot. Everything else unchanged.

### `auth.php`
- `declare(strict_types=1);` and `use App\Csrf;`
- `Csrf::check();` at the start of the POST branch
- `Csrf::field();` inside the login/register form
- New username validation: `^[a-zA-Z0-9_]{3,32}$` (error key `err_invalid_username`)
- `session_regenerate_id(true);` immediately before setting `$_SESSION['user_id']` in
  both the register path and the login-success path (session-fixation fix)
- Input cast to string for strict types

### `index.php`
- Removed ~20 helper functions (moved to `src/Reminder.php` and `src/RepeatLabel.php`)
- Removed all inline SQL (moved to `src/MemoRepository.php`)
- HTTP handler now calls `Reminder::`, `RepeatLabel::`, `MemoRepository`, and `Csrf::`
- `Csrf::check();` at the start of the POST branch; `Csrf::field();` in the add/edit and
  delete forms
- A `csrfToken` JS variable is emitted and sent in the reschedule `fetch()` body, so the
  AJAX reschedule passes CSRF verification like every other POST
- Translator passed to label helpers via `t(...)` first-class callable
- All output cast to string for strict types
- HTML/CSS/JS behavior unchanged

### `lang.php`
- `declare(strict_types=1);`
- One new key in all three languages: `err_invalid_username`
- Everything else unchanged

## Verification performed

- `composer test` — 26 tests, 47 assertions, all pass
- `composer analyse` (PHPStan level max) — no errors
- `composer pint:test` (PSR-12) — 11 files pass
- `composer rector:dry` — clean (modernizations already applied)
- End-to-end against `php -S`:
  - register without CSRF token -> 419; with token -> 302 to index
  - add memo with a past daily reminder -> persisted
  - reschedule without token -> 419; with token -> 200, reminder advanced to the next
    future occurrence (missed-cycle logic confirmed against Asia/Tokyo "now")

## Notes / follow-ups not done

- Reschedule is now CSRF-protected (option a, as requested). If a session expires while a
  tab stays open, the next reschedule will 419 silently (JS only logs to console). A future
  improvement could surface a "session expired, please reload" message.
- No integration test for the full HTTP/auth/CRUD path was added; only the live-server
  smoke test above. Pure logic is unit-tested; the DB layer is thin and could get
  SQLite-backed integration tests later if desired.
- `src/MemoRepository.php` is the piece that most changes the original "hand-written"
  feel. It was included because the production direction was chosen for this pass.
