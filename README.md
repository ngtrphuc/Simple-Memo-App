# Simple Memo App

This project stays as a normal PHP web app, and now also has a very simple Electron wrapper for running it like a local desktop app.

## How it works

- The PHP code is still the real app.
- Electron only opens a desktop window that points to `http://127.0.0.1:32123/auth.php`.
- If no PHP server is running, Electron starts one for you.
- If you already started `php -S 127.0.0.1:32123`, Electron reuses that server instead of starting a second copy.
- Web and app now run from the same folder, so they both use the same local database file at `memo.sqlite`.

## Requirements

- PHP installed and available in `PATH`
- Node.js and npm installed

Check them with:

```bash
php -v
node -v
npm -v
```

## Run as a web app

```bash
npm run web
```

Then open:

```text
http://127.0.0.1:32123/auth.php
```

You can also run the same server directly with:

```bash
php -S 127.0.0.1:32123
```

On Windows, you can also double-click `run-web.bat`.

## Run as a desktop app

Install Electron once:

```bash
npm install
```

Then start the app:

```bash
npm start
```

This project includes a small launcher script that clears `ELECTRON_RUN_AS_NODE` before opening Electron. That helps on Windows setups where that environment variable is already set in the shell.

On Windows, you can also double-click `run-app.bat`.
If you want to avoid the terminal window, use `run-app-hidden.vbs` or the `Memo App` shortcut created for you.

## Sync behavior

- If the Electron app is open, you can also open the browser at `http://127.0.0.1:32123/auth.php`.
- Both screens read and write the same `memo.sqlite`.
- There is no extra sync code because both sides use the same PHP app and the same database file.

## Notes

- `memo.sqlite` is ignored by git because it is your local data.
- The live database is `E:\Code\PHP\Simple-Memo-App\memo.sqlite`.
- Older migration backups are kept under `backup\`.
- The logout flow now clears the session more cleanly.
- New memos now store `created_at` using `Asia/Tokyo` time instead of SQLite UTC.
