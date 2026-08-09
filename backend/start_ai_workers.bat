@echo off
setlocal EnableDelayedExpansion
REM ============================================================
REM Nepal Smart Travel - AI Ready-Mode launcher (RUN THIS ONCE)
REM Starts the resident supervisor that keeps the queue worker and
REM scheduler ALWAYS READY, and registers itself to auto-start at
REM Windows logon (so after reboot it comes back by itself).
REM ============================================================
cd /d "%~dp0"

if not exist ".env" (
    echo .env not found. Run from the backend folder.
    pause
    exit /b 1
)

REM Register at Windows logon (idempotent - overwrites existing task)
schtasks /Create /F /TN "NepalAIWorkers" /TR "%~f0" /SC ONLOGON >nul 2>&1
if not errorlevel 1 (
    echo [OK] Registered "NepalAIWorkers" to auto-start at Windows logon.
) else (
    echo [WARN] Could not register auto-start ^(run as admin^). Workers will start manually.
)

REM Check if supervisor already running
if exist ".supervisor.pid" (
    set /p SPID=<".supervisor.pid"
    tasklist /FI "PID eq !SPID!" 2>nul | findstr /r "^!SPID!" >nul
    if not errorlevel 1 (
        echo Supervisor already running ^(PID !SPID!^).
        exit /b 0
    )
)

REM Start supervisor in a minimized window
echo Starting AI Ready-Mode supervisor...
powershell -NoProfile -Command "$p = Start-Process -WindowStyle Minimized -FilePath 'cmd.exe' -ArgumentList '/c','ai_watchdog.bat' -WorkingDirectory '%CD%' -PassThru; $p.Id | Out-File -FilePath '.supervisor.pid' -Encoding ascii"

ping -n 4 127.0.0.1 >nul
echo Done. AI is now in READY mode - workers run instantly in the background.
echo Status: check storage\logs\watchdog.log
