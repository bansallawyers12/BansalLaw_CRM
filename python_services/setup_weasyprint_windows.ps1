# One-time WeasyPrint setup for Windows local development (email PDF preview).
# Requires: Python 3, pip, admin rights for GTK runtime install.

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ScriptDir

Write-Host '=== WeasyPrint setup for BansalLaw CRM (Windows) ===' -ForegroundColor Cyan

Write-Host 'Installing Python dependencies...'
python -m pip install -r requirements.txt

$localGtkRoot = Join-Path $ScriptDir 'vendor\GTK3-Runtime'
$gtkBin = Join-Path $localGtkRoot 'bin'
if (-not (Test-Path "$gtkBin\libcairo-2.dll")) {
    Write-Host 'GTK3 runtime not found. Downloading installer...' -ForegroundColor Yellow
    $installer = Join-Path $env:TEMP 'gtk3-runtime-3.24.31-2022-01-04-ts-win64.exe'
    $url = 'https://github.com/tschoonj/GTK-for-Windows-Runtime-Environment-Installer/releases/download/2022-01-04/gtk3-runtime-3.24.31-2022-01-04-ts-win64.exe'
    Invoke-WebRequest -Uri $url -OutFile $installer
    Write-Host 'Installing GTK3 runtime into python_services\vendor (no admin required)...'
    New-Item -ItemType Directory -Force -Path $localGtkRoot | Out-Null
    Start-Process -FilePath $installer -ArgumentList '/S', "/D=$localGtkRoot" -Wait
    $gtkBin = Join-Path $localGtkRoot 'bin'
}

$env:WEASYPRINT_DLL_DIRECTORIES = $gtkBin
if (Test-Path $gtkBin) {
    $env:PATH = "$gtkBin;$env:PATH"
}

Write-Host 'Testing WeasyPrint...'
python check_weasyprint.py
if ($LASTEXITCODE -ne 0) {
    Write-Host 'WeasyPrint setup failed. See messages above.' -ForegroundColor Red
    exit 1
}

Write-Host 'Setup complete. Start the service with start_services.bat' -ForegroundColor Green
