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
REM Check for key dependencies: FastAPI (web framework), Uvicorn (ASGI server), extract_msg (email parsing)
python -c "import fastapi, uvicorn, extract_msg" >nul 2>&1
if %errorlevel% neq 0 (
    echo 📦 Installing dependencies...
    python -m pip install -r requirements.txt
    if %errorlevel% neq 0 (
        echo ❌ Failed to install dependencies
        pause
        exit /b 1
    )
    echo ✅ Dependencies installed
) else (
    echo ✅ Dependencies are up to date
)

REM Start the service
echo.
echo 🚀 Starting Migration Manager Python Services...
echo    Host: 127.0.0.1
echo    Port: 5001
echo    URL: http://127.0.0.1:5001
echo    Health: http://127.0.0.1:5001/health
echo.
echo Press Ctrl+C to stop the service
echo ============================================================

python main.py --host 127.0.0.1 --port 5001

echo.
echo ⏹️  Service stopped
pause
