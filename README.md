# Simple Memo App

Simple Memo App is a small PHP note-taking app with user login, SQLite storage, and an
optional Electron wrapper for running it like a desktop app.

## Features

- User registration and login (CSRF-protected, session regenerated on login)
- Create, edit, and delete personal memos
- Memo reminders with a flexible repeat scheduler (daily/weekly/monthly/yearly,
  custom intervals, specific weekdays, specific month dates) while the app is open
- SQLite-based local data storage
- Multi-language UI (English / 日本語 / Tiếng Việt)
- Run in a browser or as a lightweight Electron desktop app

## Stack

- PHP 8.3 (PDO + SQLite)
- Vanilla JS frontend
- Electron (optional desktop wrapper)

## Getting Started

Install PHP dependencies (now required — the app autoloads classes from `src/`):

```bash
composer install
```

Install the desktop wrapper dependencies:

```bash
npm install
```

Run in the browser:

```bash
npm run web
```

Run as a desktop app:

```bash
npm start
```

> Note: because `db.php` now loads `vendor/autoload.php`, you must run `composer install`
> before the app will start in either the browser or Electron.

## Code Quality

```bash
composer test         # PHPUnit
composer analyse      # PHPStan (level max, including root entrypoints)
composer pint:test    # code style check (PSR-12)
composer pint         # apply code style fixes
composer rector:dry   # preview automated refactors
composer rector       # apply automated refactors
composer audit        # dependency audit
composer check        # pint:test + analyse + test + audit
```

The dev toolchain stays framework-free. Unused Laravel-only debug tooling has been removed
from `require-dev`, and `_quarantine/phpstan.larastan.neon` remains unreferenced.

## Project Structure

```
.
├── auth.php              # authentication (register/login/logout), CSRF + session hardening
├── db.php                # PDO bootstrap, schema migration, autoload
├── index.php             # memo UI + thin HTTP handler
├── lang.php              # i18n strings + helpers (t, langSelect, currentLang)
├── main.js               # Electron entry
├── start-electron.js     # Electron launcher
├── src/
│   ├── Reminder.php          # pure reminder/repeat date math (tested)
│   ├── RepeatLabel.php       # repeat-pattern display labels (translator-injected)
│   ├── Csrf.php              # CSRF token + verification
│   └── MemoRepository.php    # PDO memo access, user_id scoped
├── tests/
│   ├── ReminderTest.php
│   └── CsrfTest.php
├── composer.json
├── phpstan.neon
├── rector.php
├── pint.json
├── phpunit.xml
└── docs/
    └── reference-context.md
```

See `CHANGES.md` for the full list of changes made in the refactor.
