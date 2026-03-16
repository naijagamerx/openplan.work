@echo off
title LazyMan TaskManager - Server + Scheduler

REM Change to script directory
cd /d "%~dp0"

echo ===============================================
echo     LazyMan TaskManager - Server + Scheduler
echo ===============================================
echo.

REM Check if bundled PHP exists
if not exist "php\php.exe" (
    echo ERROR: Portable PHP not found!
    echo.
    echo Looking for: %CD%\php\php.exe
    echo.
    echo Please verify:
    echo   1. You have a folder called "php" in this directory
    echo   2. The php folder contains php.exe
    echo.
    echo Current directory: %CD%
    echo.
    pause
    exit /b 1
)

echo [OK] PHP found at: %CD%\php\php.exe
echo.
echo Starting server and scheduler on port 4041...
echo.
echo Note: The server will be available at http://localhost:4041
echo.
echo Stopping any existing PHP processes...
taskkill /F /IM php.exe 2>nul
timeout /t 1 /nobreak >nul
echo.

REM Start the server and scheduler via bundled PHP
"php\php.exe" start_server.php

REM If script exits, pause to show any errors
if %errorlevel% neq 0 (
    echo.
    echo Server stopped with error code: %errorlevel%
    echo.
    pause
)
