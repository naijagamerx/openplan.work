@echo off
title LazyMan TaskManager - Stop Server

cd /d "%~dp0"

echo.
echo Stopping LazyMan TaskManager...
echo.

REM Kill any remaining PHP processes
echo Killing PHP processes...
taskkill /F /IM php.exe 2>nul

REM Check if PID files exist and clean them up
if exist "server.pid" (
    echo Removing server.pid...
    del /f /q server.pid 2>nul
)

if exist "cron\scheduler.pid" (
    echo Removing scheduler.pid...
    del /f /q cron\scheduler.pid 2>nul
)

echo.
echo Server and scheduler stopped successfully.
echo.
pause

