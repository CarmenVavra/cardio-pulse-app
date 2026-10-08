@echo off
REM ==========================================================================
REM  CardioPulse - Entwicklungsserver stoppen (Port 8700)
REM ==========================================================================
setlocal
set PORT=8700
set FOUND=0

for /f "tokens=5" %%p in ('netstat -ano ^| findstr /R /C:":%PORT% .*LISTENING"') do (
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
