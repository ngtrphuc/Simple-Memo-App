@echo off
setlocal
cd /d "%~dp0"

echo ============================================
echo   Memo App cleanup
echo ============================================
echo.
echo This will keep only the files required for the
echo PHP + SQLite runtime used by this app.
echo.
echo Keep:
echo   php\php-cgi.exe
echo   php\php8.dll
echo   php\php.ini
echo   php\libsqlite3.dll
echo   php\ext\php_pdo_sqlite.dll
echo   php\ext\php_sqlite3.dll
echo   locales\en-US.pak and the language selected in settings.json
echo.
echo Before cleanup, back up the www\ folder if you want an extra copy
echo of your data. The SQLite database is stored in www\memo.sqlite.
echo.

if not exist "php\php-cgi.exe" (
    echo [X] php\php-cgi.exe was not found.
    echo     Put this file next to MemoApp.exe and run it again.
    pause
    exit /b 1
)

choice /c YN /m "Continue with cleanup"
if errorlevel 2 goto end

echo.
echo [1/3] Minimizing php.ini...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$p='php\php.ini';" ^
  "$keep=@('php_pdo_sqlite.dll','php_sqlite3.dll');" ^
  "$lines=Get-Content $p | ForEach-Object {" ^
  "  if ($_ -match '^\s*extension\s*=\s*(.+?)\s*$') {" ^
  "    $ext=$Matches[1].Trim().ToLowerInvariant();" ^
  "    if ($keep -contains $ext) { 'extension=' + $ext } else { ';' + $_ }" ^
  "  } else { $_ }" ^
  "};" ^
  "Set-Content -Path $p -Value $lines -Encoding Ascii"
if errorlevel 1 goto failed

echo [2/3] Removing unused PHP runtime files...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$phpRoot=Resolve-Path 'php';" ^
  "$keep=@('ext','libsqlite3.dll','php-cgi.exe','php.ini','php8.dll');" ^
  "Get-ChildItem $phpRoot -Force | Where-Object { $keep -notcontains $_.Name } | Remove-Item -Recurse -Force;" ^
  "$extRoot=Join-Path $phpRoot 'ext';" ^
  "$keepExt=@('php_pdo_sqlite.dll','php_sqlite3.dll');" ^
  "Get-ChildItem $extRoot -Force | Where-Object { $_.PSIsContainer -or ($keepExt -notcontains $_.Name) } | Remove-Item -Recurse -Force"
if errorlevel 1 goto failed

echo [3/3] Removing unused locale files...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$settings=Get-Content 'settings.json' -Raw | ConvertFrom-Json;" ^
  "$lang=$settings.chrome.command_line_switches.lang;" ^
  "$keep=@('en-US.pak');" ^
  "if ($lang) { $keep += ($lang + '.pak') };" ^
  "if (Test-Path 'locales') {" ^
  "  Get-ChildItem 'locales' -File | Where-Object { $keep -notcontains $_.Name } | Remove-Item -Force" ^
  "}"
if errorlevel 1 goto failed

echo.
echo Cleanup finished.
echo.
echo Optional GPU files are still kept because removing them can cause
echo a blank window on some PCs.
echo.
choice /c YN /m "Remove optional GPU fallback files too"
if errorlevel 2 goto end

del /q d3dcompiler_47.dll dxcompiler.dll dxil.dll vulkan-1.dll 2>nul
del /q vk_swiftshader.dll vk_swiftshader_icd.json 2>nul
echo GPU fallback files removed. Open MemoApp.exe and test immediately.
goto end

:failed
echo.
echo Cleanup failed. No more files will be removed.

:end
echo.
pause
endlocal
