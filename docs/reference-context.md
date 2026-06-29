# Reference Context For Simple Memo App

This note captures the reusable context imported from:

- `E:\Code\PHP\Laravel-Lab`
- `E:\Code\Skills\Skill-Factory`

It is the working reference for future changes in this memo app.

## Current Memo App Shape

- Lightweight PHP app, not Laravel.
- Main entry files are `auth.php`, `db.php`, `index.php`, and `lang.php`.
- Persistence uses `PDO` with local `SQLite`.
- Authentication uses PHP sessions and per-user memo ownership checks in SQL queries.
- UI, request handling, domain logic, and rendering are still mostly co-located in single files.

## What We Reuse From Laravel-Lab

### Architecture

- Favor a clear separation between request handling, domain logic, persistence, and rendering.
- Keep routes/controllers thin. In this repo that means moving business logic out of page files over time.
- Validate input before mutating data.
- Keep user ownership checks on the server side, never only in the UI.

### Tooling

- Keep `strict_types=1`.
- Use `PHPStan`, `Pint`, and `Rector` as the default quality toolchain.
- Add tests before large refactors, especially around auth and reminder scheduling.
- Prefer `SQLite` for low-friction local development and test fixtures.

### Practical Migration Direction

When evolving this app, prefer this extraction path instead of a full rewrite:

1. Create `src/` with PSR-4 autoloading in `composer.json`.
2. Extract reminder calculations into a dedicated service.
3. Extract memo CRUD into a repository class around `PDO`.
4. Extract auth/session operations into an auth service.
5. Move repeated HTML into partials/templates.
6. Add tests around the extracted services before deeper structural changes.

## What We Reuse From Skill-Factory

### Backend Rules

- Keep handlers thin and push business rules into services.
- Do not put persistence decisions and domain logic directly in view templates.
- Prefer small, focused modules over one growing procedural file.
- Avoid introducing user-data caching unless there is a clear invalidation strategy.

### Security Rules

- Never trust frontend-only checks for memo ownership.
- Do not log passwords, raw session identifiers, or other secrets.
- Regenerate session identifiers on successful login in future auth hardening work.
- Add CSRF protection before expanding write actions further.

### Testing Rules

- Use the lightest test layer that proves behavior.
- Unit test pure reminder/repeat logic first.
- Add integration tests for auth and SQLite-backed memo CRUD flows.
- Do not depend on ad hoc manual seed state for tests; create minimal fixtures per test.

### Dependency Rules

- Keep the documented stack truthful to the actual repo.
- Do not carry framework-specific tooling/config that the app does not really use.
- If the app stays plain PHP, prefer generic `PHPStan` config over Laravel-only analysis paths.

## Immediate Implications For This Repo

- `index.php` currently mixes HTTP handling, reminder domain rules, HTML, CSS, and JavaScript.
- `auth.php` handles login/register/logout correctly at a basic level, but should later add session ID regeneration and CSRF protection.
- `db.php` is acting as both bootstrap and migration layer; that is acceptable for now, but schema evolution should become more explicit once the app grows.
- `phpstan.larastan.neon` is reference material from Laravel-oriented tooling and should not be treated as the primary analysis config unless this repo becomes Laravel-based.

## Working Standard Going Forward

For future work on this memo app:

- Keep the app lightweight unless there is a strong reason to migrate to Laravel.
- Borrow Laravel-style structure and discipline without forcing Laravel itself into the project.
- Prefer incremental extraction and test coverage over big-bang rewrites.
- Use the two reference repos as guidance for architecture, tooling, security, and maintainability decisions.
