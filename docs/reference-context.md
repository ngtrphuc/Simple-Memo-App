# Reference Context For Simple Memo App

This note captures the reusable context imported from:

- `E:\Code\PHP\Laravel-Lab`
- `E:\Code\Skills\Skill-Factory`

It is the working reference for future changes in this memo app.

## Current Memo App Shape

- Lightweight PHP app, not Laravel.
- Entry files: `auth.php`, `db.php`, `index.php`, `lang.php`.
- Domain logic now lives in `src/` (PSR-4 `App\`): `Reminder`, `RepeatLabel`, `Csrf`,
  `MemoRepository`.
- Persistence uses `PDO` with local `SQLite`, behind `MemoRepository`.
- Authentication uses PHP sessions with CSRF protection and session-id regeneration on
  login, and per-user memo ownership checks enforced in SQL.

## What We Reused From Laravel-Lab

### Architecture
- Separation between request handling, domain logic, persistence, and rendering — applied
  by moving reminder math to `Reminder`, labels to `RepeatLabel`, and SQL to
  `MemoRepository`, leaving `index.php` as a thin handler.
- Validate input before mutating data (username regex, repeat-pattern normalization).
- Keep user ownership checks server-side.

### Tooling
- `strict_types=1` everywhere.
- `PHPStan` (generic, level max), `Pint` (PSR-12), `Rector` (PHP sets, no Laravel) as the
  quality toolchain. **Larastan / rector-laravel deliberately NOT used** — this is plain PHP.
- Tests added before/with the refactor, around reminder scheduling and CSRF.
- `SQLite` for low-friction local dev and tests.

## What We Reused From Skill-Factory

### Backend
- Thin handlers, business rules in classes, no persistence/domain logic in view templates.
- Small focused modules over one procedural file.
- No user-data caching introduced (no clear invalidation need yet).

### Security
- No frontend-only ownership checks.
- No logging of passwords or session identifiers.
- Session id regenerated on successful login (DONE).
- CSRF protection added across all write actions, including the reschedule AJAX (DONE).

### Testing
- Lightest layer that proves behavior: pure reminder/repeat logic unit-tested first (DONE).
- DB-backed integration tests for auth/CRUD remain a possible follow-up.

### Dependencies
- Stack docs kept truthful to the real repo.
- No framework-specific tooling carried in.

## Migration Roadmap Status

Original extraction path and where it now stands:

1. Create `src/` with PSR-4 autoloading — DONE
2. Extract reminder calculations into a dedicated service — DONE (`Reminder` + `RepeatLabel`)
3. Extract memo CRUD into a repository around `PDO` — DONE (`MemoRepository`)
4. Extract auth/session operations into an auth service — PARTIAL (CSRF + session hardening
   added in `auth.php`; not yet a standalone `AuthService` class)
5. Move repeated HTML into partials/templates — NOT DONE (HTML still inline in `index.php`
   and `auth.php`)
6. Add tests around extracted services — DONE for reminder + CSRF; auth/CRUD integration
   tests still open

## Working Standard Going Forward

- Keep the app lightweight; no Laravel unless there is a strong reason.
- Borrow Laravel-style discipline without forcing the framework in.
- Prefer incremental extraction and test coverage over big-bang rewrites.
- New domain logic goes in `src/` with a matching test; keep `index.php`/`auth.php` thin.
