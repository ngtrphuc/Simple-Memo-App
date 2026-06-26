# Simple Memo App

Simple Memo App is a small PHP note-taking app with user login, SQLite storage, and an optional Electron wrapper for running it like a desktop app.

## Features

- User registration and login
- Create, edit, and delete personal memos
- Optional memo reminders while the app is open
- SQLite-based local data storage
- Run in a browser or as a lightweight Electron desktop app

## Stack

- PHP
- SQLite
- Electron

## Getting Started

Install dependencies for the desktop wrapper:

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

## Code Quality

Run Rector in dry-run mode:

```bash
composer rector:dry
```

Apply Rector changes:

```bash
composer rector
```

## Project Structure

- `auth.php` handles authentication
- `index.php` renders the memo interface
- `db.php` initializes the SQLite database
- `main.js` starts the Electron window
