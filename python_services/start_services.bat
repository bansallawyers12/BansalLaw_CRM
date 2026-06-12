@echo off
REM ============================================================
REM Migration Manager Python Services - Windows Startup Script
REM ============================================================
REM This script starts the unified Python services on Windows
REM Services included:
REM   - PDF Processing Service (merge, split, watermark, OCR)
REM   - Email Service (Outlook integration, .msg parsing)
REM   - AI Service (OpenAI integration for document analysis)
REM ============================================================

echo ============================================================
echo Migration Manager Python Services - Windows Startup
echo ============================================================

REM Check if Python is available
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Error: Python is not installed or not in PATH
    echo    Please install Python 3.7+ from https://python.org
    pause
    exit /b 1
)

REM Get the directory where this script is located
set SCRIPT_DIR=%~dp0
cd /d "%SCRIPT_DIR%"

REM Check if main.py exists
if not exist "main.py" (
    echo ❌ Error: main.py not found in %SCRIPT_DIR%
    echo    Please run this script from the python_services directory
    pause
    exit /b 1
)

REM Check if requirements.txt exists
if not exist "requirements.txt" (
    echo ❌ Error: requirements.txt not found
    echo    Please ensure all files are present
    pause
    exit /b 1
)

REM Install dependencies if needed (FastAPI, Uvicorn, PDF libraries, etc.)
echo 📦 Checking dependencies...
python -m pip install -r requirements.txt >nul 2>&1

REM Configure GTK/Cairo for WeasyPrint on Windows (email PDF preview only)
echo 🔍 Checking WeasyPrint (email PDF preview)...
python check_weasyprint.py
if %errorlevel% neq 0 (
    echo.
    echo ⚠️  WeasyPrint is not ready — email PDF preview will be unavailable.
    echo    Other services (PDF merge, .msg parsing, DOCX convert) will still start.
    echo    To fix: powershell -ExecutionPolicy Bypass -File setup_weasyprint_windows.ps1
    echo.
) else (
    echo ✅ WeasyPrint is ready
)

REM Start the service
echo.
echo 🚀 Starting Migration Manager Python Services...
echo    Host: 127.0.0.1
echo    Port: 5002
echo    URL: http://127.0.0.1:5002
echo    Health: http://127.0.0.1:5002/health
echo.
echo Press Ctrl+C to stop the service
echo ============================================================

python main.py --host 127.0.0.1 --port 5002

echo.
echo ⏹️  Service stopped
pause
