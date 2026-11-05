@echo off
REM ==========================================================================
REM  CardioPulse - Entwicklungsserver stoppen (Port 8700)
REM ==========================================================================
setlocal
rem Vite-Dev-Server (falls "npm run dev" laeuft): fester Port, siehe vite.config.js
set "VITE_PORT=5950"
for /f "tokens=5" %%v in ('netstat -ano ^| findstr /R /C:":%VITE_PORT%  *0\.0\.0\.0:0 " /C:":%VITE_PORT%  *\[::\]:0 "') do taskkill /PID %%v /T /F >nul 2>&1
if exist "%~dp0public\hot" del /q "%~dp0public\hot" >nul 2>&1
set PORT=8700
set FOUND=0

for /f "tokens=5" %%p in ('netstat -ano ^| findstr /R /C:":%PORT%  *0\.0\.0\.0:0 " /C:":%PORT%  *\[::\]:0 "') do (
    taskkill /F /PID %%p >nul 2>&1
    set FOUND=1
)

taskkill /F /FI "WINDOWTITLE eq CardioPulse Server*" >nul 2>&1

if "%FOUND%"=="1" (
    echo CardioPulse-Server auf Port %PORT% gestoppt.
) else (
    echo Kein Server auf Port %PORT% gefunden.
)
endlocal
