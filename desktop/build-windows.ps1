<#
    Builds the SchoolHub for Windows installer (NativePHP) from this repository.

    The online server never installs NativePHP: the build happens in a separate
    copy of the project (default ..\schoolhub-windows), with the files in this
    desktop\ folder added:

        NativeAppServiceProvider.php  -> app\Providers\  (window, PHP settings)
        nativephp.php                 -> config\         (app details, cleanup)
        icon.png, icon.ico            -> public\         (app and installer icon)
        ..\.env.desktop.example       -> .env            (APP_EDITION=desktop)

    Usage, from the repository root:
        powershell -ExecutionPolicy Bypass -File desktop\build-windows.ps1 -Version 1.0.1

    -PhpBin points at an unpacked copy of nativephp/php-bin when Composer
    cannot download it itself (it is a 350 MB package); leave it out otherwise.
    The installer ends up in <BuildDir>\dist.
#>
param(
    [string] $Version = '1.0.0',
    [string] $BuildDir = (Join-Path (Split-Path $PSScriptRoot -Parent) '..\schoolhub-windows'),
    [string] $PhpBin = ''
)

$ErrorActionPreference = 'Stop'
$repo = Split-Path $PSScriptRoot -Parent
$BuildDir = [IO.Path]::GetFullPath($BuildDir)

function Step($text) { Write-Host "`n== $text" -ForegroundColor Cyan }

Step "Copying the project to $BuildDir"
$skipDirs = @('.git', 'vendor', 'node_modules', 'dist', 'storage\logs', 'storage\framework\cache',
    'storage\framework\sessions', 'storage\framework\views', 'storage\framework\testing',
    'storage\app\backups', 'storage\app\imports', 'storage\app\private', 'storage\app\public', '.idea', '.vscode') |
    ForEach-Object { Join-Path $repo $_ }
robocopy $repo $BuildDir /MIR /XD @skipDirs /XF '.env' '*.sqlite' '*.sqlite-*' /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { throw "Copying failed (robocopy $LASTEXITCODE)." }

# Never ship server settings: every .env file except the two samples goes.
Get-ChildItem -Force -LiteralPath $BuildDir -Filter '.env*' |
    Where-Object { $_.Name -notin '.env.example', '.env.desktop.example' } |
    ForEach-Object { Remove-Item -LiteralPath $_.FullName -Force }

foreach ($dir in 'storage\framework\views', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\logs', 'storage\app\backups') {
    New-Item -ItemType Directory -Force -Path (Join-Path $BuildDir $dir) | Out-Null
}

Step 'Adding the Windows app files'
Copy-Item "$PSScriptRoot\NativeAppServiceProvider.php" "$BuildDir\app\Providers\NativeAppServiceProvider.php" -Force
Copy-Item "$PSScriptRoot\nativephp.php" "$BuildDir\config\nativephp.php" -Force
Copy-Item "$PSScriptRoot\icon.png" "$BuildDir\public\icon.png" -Force
Copy-Item "$PSScriptRoot\icon.ico" "$BuildDir\public\icon.ico" -Force

$envText = (Get-Content "$BuildDir\.env.desktop.example" -Raw) -replace '(?m)^NATIVEPHP_APP_VERSION=.*\r?\n', ''
[IO.File]::WriteAllText("$BuildDir\.env", $envText.TrimEnd() + "`nNATIVEPHP_APP_VERSION=$Version`n", (New-Object Text.UTF8Encoding $false))

Push-Location $BuildDir
try {
    Step 'Adding NativePHP (build copy only)'
    $composer = Get-Content composer.json -Raw | ConvertFrom-Json
    $composer.require | Add-Member -NotePropertyName 'nativephp/desktop' -NotePropertyValue '^2.3' -Force
    if ($PhpBin) {
        $path = [ordered]@{ type = 'path'; url = ($PhpBin -replace '\\', '/'); options = [ordered]@{ symlink = $false; versions = @{ 'nativephp/php-bin' = '1.99.0' } } }
        $composer | Add-Member -NotePropertyName 'repositories' -NotePropertyValue @($path) -Force
    }
    [IO.File]::WriteAllText("$BuildDir\composer.json", ($composer | ConvertTo-Json -Depth 20), (New-Object Text.UTF8Encoding $false))
    composer update nativephp/desktop nativephp/php-bin --with-dependencies --no-interaction --no-scripts
    if ($LASTEXITCODE -ne 0) { throw 'Composer could not install NativePHP.' }
    # Without the scripts (they call dev-only commands like boost:update), so discover the packages here.
    php artisan package:discover --ansi
    if ($LASTEXITCODE -ne 0) { throw 'Laravel could not discover the packages.' }

    php artisan key:generate --force
    php artisan native:install --no-interaction

    # native:install writes its own provider and settings: put SchoolHub's back.
    Copy-Item "$PSScriptRoot\NativeAppServiceProvider.php" "$BuildDir\app\Providers\NativeAppServiceProvider.php" -Force
    Copy-Item "$PSScriptRoot\nativephp.php" "$BuildDir\config\nativephp.php" -Force

    Step 'Building the styles'
    npm ci --no-audit --no-fund
    npm run build
    if ($LASTEXITCODE -ne 0) { throw 'npm run build failed.' }

    Step "Building the Windows installer (version $Version)"
    php -d memory_limit=1G artisan native:build win x64 --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'native:build failed.' }

    Step 'Done'
    Get-ChildItem "$BuildDir\nativephp\electron\dist" -Filter "*$Version-setup.exe" | ForEach-Object { Write-Host "$($_.FullName)  ($([math]::Round($_.Length / 1MB)) MB)" }
}
finally {
    Pop-Location
}
