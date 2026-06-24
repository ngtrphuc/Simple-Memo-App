param(
    [switch]$RemoveOptionalGpuFiles
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Get-DirectorySizeBytes {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    if (-not (Test-Path $Path)) {
        return 0
    }

    return (Get-ChildItem -Path $Path -Recurse -File | Measure-Object -Property Length -Sum).Sum
}

function Set-MinimalPhpIni {
    param(
        [Parameter(Mandatory = $true)]
        [string]$PhpIniPath
    )

    $keptExtensions = @(
        "php_pdo_sqlite.dll",
        "php_sqlite3.dll"
    )

    $updatedLines = foreach ($line in Get-Content $PhpIniPath) {
        if ($line -match '^\s*extension\s*=\s*(.+?)\s*$') {
            $extensionName = $Matches[1].Trim().ToLowerInvariant()
            if ($keptExtensions -contains $extensionName) {
                "extension=$extensionName"
            } else {
                ";$line"
            }
        } else {
            $line
        }
    }

    Set-Content -Path $PhpIniPath -Value $updatedLines -Encoding Ascii
}

function Slim-PhpRuntime {
    param(
        [Parameter(Mandatory = $true)]
        [string]$PhpRoot
    )

    $phpIniPath = Join-Path $PhpRoot "php.ini"
    Set-MinimalPhpIni -PhpIniPath $phpIniPath

    $keepPhpItems = @(
        "ext",
        "libsqlite3.dll",
        "php-cgi.exe",
        "php.ini",
        "php8.dll"
    )

    Get-ChildItem -Path $PhpRoot -Force | Where-Object {
        $keepPhpItems -notcontains $_.Name
    } | Remove-Item -Recurse -Force

    $extRoot = Join-Path $PhpRoot "ext"
    $keepExtensions = @(
        "php_pdo_sqlite.dll",
        "php_sqlite3.dll"
    )

    Get-ChildItem -Path $extRoot -Force | Where-Object {
        $_.PSIsContainer -or ($keepExtensions -notcontains $_.Name)
    } | Remove-Item -Recurse -Force
}

function Slim-Locales {
    param(
        [Parameter(Mandatory = $true)]
        [string]$AppRoot,
        [Parameter(Mandatory = $true)]
        [string]$SettingsPath
    )

    $localesRoot = Join-Path $AppRoot "locales"
    if (-not (Test-Path $localesRoot)) {
        return
    }

    $settings = Get-Content $SettingsPath -Raw | ConvertFrom-Json
    $lang = $settings.chrome.command_line_switches.lang

    $keepLocales = New-Object 'System.Collections.Generic.HashSet[string]' ([System.StringComparer]::OrdinalIgnoreCase)
    [void]$keepLocales.Add("en-US.pak")
    if ($lang) {
        [void]$keepLocales.Add("$lang.pak")
    }

    Get-ChildItem -Path $localesRoot -File | Where-Object {
        -not $keepLocales.Contains($_.Name)
    } | Remove-Item -Force
}

function Remove-OptionalGpuRuntimeFiles {
    param(
        [Parameter(Mandatory = $true)]
        [string]$AppRoot
    )

    $optionalFiles = @(
        "d3dcompiler_47.dll",
        "dxcompiler.dll",
        "dxil.dll",
        "vk_swiftshader.dll",
        "vk_swiftshader_icd.json",
        "vulkan-1.dll"
    )

    foreach ($fileName in $optionalFiles) {
        $filePath = Join-Path $AppRoot $fileName
        if (Test-Path $filePath) {
            Remove-Item -Force $filePath
        }
    }
}

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$distRoot = Join-Path $projectRoot "dist"
$downloadRoot = Join-Path $distRoot "downloads"
$extractRoot = Join-Path $distRoot "extract"
$appRoot = Join-Path $distRoot "MemoApp-Desktop"
$releaseApi = "https://api.github.com/repos/cztomczak/phpdesktop/releases/latest"

Write-Host "Fetching the latest PHP Desktop Windows release..."
$release = Invoke-RestMethod -Uri $releaseApi -Headers @{ "User-Agent" = "Simple-Memo-App-Builder" }
$asset = $release.assets | Where-Object { $_.name -like "phpdesktop-chrome-*-php-*.zip" } | Select-Object -First 1

if (-not $asset) {
    throw "Could not find a Windows PHP Desktop zip asset in the latest release."
}

$zipPath = Join-Path $downloadRoot $asset.name

New-Item -ItemType Directory -Force -Path $downloadRoot | Out-Null
New-Item -ItemType Directory -Force -Path $distRoot | Out-Null

if (-not (Test-Path $zipPath)) {
    Write-Host "Downloading $($asset.name)..."
    Invoke-WebRequest -Uri $asset.browser_download_url -OutFile $zipPath
} else {
    Write-Host "Using cached download: $($asset.name)"
}

if (Test-Path $extractRoot) {
    Remove-Item -Recurse -Force $extractRoot
}

if (Test-Path $appRoot) {
    Remove-Item -Recurse -Force $appRoot
}

Write-Host "Extracting runtime..."
Expand-Archive -Path $zipPath -DestinationPath $extractRoot -Force

$runtimeRoot = Get-ChildItem -Path $extractRoot -Directory | Select-Object -First 1
if (-not $runtimeRoot) {
    throw "Could not find the extracted PHP Desktop folder."
}

Write-Host "Preparing desktop app folder..."
Copy-Item -Recurse -Force $runtimeRoot.FullName $appRoot

$wwwRoot = Join-Path $appRoot "www"
if (-not (Test-Path $wwwRoot)) {
    throw "The extracted runtime does not contain a www folder."
}

Get-ChildItem -Path $wwwRoot -Force | Remove-Item -Recurse -Force

Copy-Item -Force (Join-Path $projectRoot "index.php") $wwwRoot
Copy-Item -Force (Join-Path $projectRoot "auth.php") $wwwRoot
Copy-Item -Force (Join-Path $projectRoot "db.php") $wwwRoot

$databasePath = Join-Path $projectRoot "memo.sqlite"
if (Test-Path $databasePath) {
    Copy-Item -Force $databasePath (Join-Path $wwwRoot "memo.sqlite")
    Write-Host "Copied existing memo.sqlite database."
} else {
    Write-Host "No memo.sqlite file found. A new database will be created on first launch."
}

Copy-Item -Force (Join-Path $projectRoot "settings.json") (Join-Path $appRoot "settings.json")
$settingsPath = Join-Path $appRoot "settings.json"

$defaultExe = Join-Path $appRoot "phpdesktop-chrome.exe"
$appExe = Join-Path $appRoot "MemoApp.exe"
if (Test-Path $defaultExe) {
    Rename-Item -Path $defaultExe -NewName "MemoApp.exe"
}

$phpRoot = Join-Path $appRoot "php"
$phpSizeBefore = Get-DirectorySizeBytes -Path $phpRoot
$localesSizeBefore = Get-DirectorySizeBytes -Path (Join-Path $appRoot "locales")

Write-Host "Slimming PHP runtime and locales..."
Slim-PhpRuntime -PhpRoot $phpRoot
Slim-Locales -AppRoot $appRoot -SettingsPath $settingsPath

if ($RemoveOptionalGpuFiles) {
    Write-Host "Removing optional GPU fallback files..."
    Remove-OptionalGpuRuntimeFiles -AppRoot $appRoot
}

$phpSizeAfter = Get-DirectorySizeBytes -Path $phpRoot
$localesSizeAfter = Get-DirectorySizeBytes -Path (Join-Path $appRoot "locales")

$zipOutput = Join-Path $distRoot "MemoApp-Desktop.zip"
if (Test-Path $zipOutput) {
    Remove-Item -Force $zipOutput
}

Write-Host "Creating distributable zip..."
Compress-Archive -Path $appRoot -DestinationPath $zipOutput -Force

Write-Host ""
Write-Host "Desktop app is ready."
Write-Host "Folder: $appRoot"
Write-Host "Launcher: $appExe"
Write-Host "Zip: $zipOutput"
Write-Host ("PHP runtime: {0:N1} MB -> {1:N1} MB" -f ($phpSizeBefore / 1MB), ($phpSizeAfter / 1MB))
Write-Host ("Locales: {0:N1} MB -> {1:N1} MB" -f ($localesSizeBefore / 1MB), ($localesSizeAfter / 1MB))
if (-not $RemoveOptionalGpuFiles) {
    Write-Host "Optional GPU fallback files were kept. Use -RemoveOptionalGpuFiles if you want the smallest package and accept a small rendering risk."
}
