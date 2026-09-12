@echo off
setlocal
title Estate Pharmacy Management System - Offline Installer
cd /d "%~dp0"

echo Estate Pharmacy Management System - Offline Installer
echo Developed by WFd DeepTech Labs Private Limited
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0INSTALL_OFFLINE_WINDOWS.ps1"
set "RC=%ERRORLEVEL%"

echo.
if not "%RC%"=="0" (
    echo INSTALLATION FAILED.
    echo Please take a photo of this screen.
    pause
    exit /b %RC%
)

echo INSTALLATION COMPLETED SUCCESSFULLY.
pause
exit /b 0
