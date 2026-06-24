# Simple Memo App

This project can still run as a normal PHP app, but it now also includes everything needed to build a real Windows desktop app with PHP Desktop.

## What changed

- The `Delete` button no longer uses `confirm()`, which is unreliable in PHP Desktop.
- `settings.json` now uses the correct schema for PHP Desktop Chrome `130.1`.
- The project includes a reproducible build script that downloads the official PHP Desktop runtime and assembles the desktop app automatically.
- All project text has been converted to English.

## Run as a local PHP app

Open a terminal in the project folder and run:

```bash
php -S 127.0.0.1:8000
```

Then open:

```text
http://127.0.0.1:8000/auth.php
```

## Build the Windows desktop app

Run:

```powershell
.\build-desktop.ps1
```

Or, if you want the absolute smallest package and are willing to risk a blank window on some PCs, run:

```powershell
.\build-desktop.ps1 -RemoveOptionalGpuFiles
```

The script will:

- Download the latest official Windows PHP Desktop release if it is missing
- Extract the runtime
- Copy this app into the runtime `www` folder
- Copy the current `memo.sqlite` database if it exists
- Rename the launcher to `MemoApp.exe`
- Slim the PHP runtime down to only the SQLite-related files this app actually uses
- Keep only `en-US.pak` plus the locale selected in `settings.json`
- Create a distributable zip file

## Build output

After the script finishes, the generated files will be here:

- `dist\MemoApp-Desktop\`
- `dist\MemoApp-Desktop.zip`

Run the desktop app by opening:

```text
dist\MemoApp-Desktop\MemoApp.exe
```

If you already have a built app folder and want to slim it in place, put [cleanup.bat](/E:/Code/PHP/Simple-Memo-App/cleanup.bat) next to `MemoApp.exe` and run it there.

## Notes

- The database file is stored inside the packaged app at `www\memo.sqlite`.
- Keep the app in a normal writable folder such as `D:\MemoApp` or your Desktop.
- If Windows reports missing runtime DLLs, install Microsoft Visual C++ Redistributable 2015-2022 x64.
- The `php\` folder only needs these files for this project:

```text
php\
|-- php-cgi.exe
|-- php8.dll
|-- php.ini
|-- libsqlite3.dll
\-- ext\
    |-- php_pdo_sqlite.dll
    \-- php_sqlite3.dll
```

- Do not aggressively delete the top-level Chromium / CEF files such as `libcef.dll`, `resources.pak`, `icudtl.dat`, `libEGL.dll`, `libGLESv2.dll`, `snapshot_blob.bin`, `v8_context_snapshot.bin`, or `chrome_*.pak`. Those are part of the browser engine and are required for the desktop window to open.
