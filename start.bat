@echo off
setlocal
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
    echo PHP was not found. Install PHP and add php.exe to PATH.
    pause
    exit /b 1
)

start "e-HadirGo PHP server" /D "%~dp0" php -S 127.0.0.1:8000 -t public
timeout /t 2 /nobreak >nul
start "" "http://127.0.0.1:8000/"
