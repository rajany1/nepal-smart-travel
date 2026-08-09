@echo off
setlocal EnableDelayedExpansion
REM ============================================================
REM Nepal Smart Travel - AI Ready-Mode Supervisor (RESIDENT)
REM
REM One persistent process that keeps the AI pipeline ALWAYS READY:
REM   1) Queue worker  - processes AnalyzeReport / jobs INSTANTLY
REM      (report submit bhaye la 1-2 sec ma analysis hunxa)
REM   2) Scheduler     - schedule:work resident process, fires due
REM      agent tasks (ai:process-tasks etc.) at their intervals
REM
REM The supervisor only CHECKS both workers every 60 seconds and
REM restarts whichever died. It does NOT run php every minute -
REM the workers themselves stay resident and react instantly.
REM
REM Start once: double-click start_ai_workers.bat (registers itself
REM at Windows logon via Task Scheduler for auto-restart).
REM ============================================================
cd /d "%~dp0"

set "PHP=php"
if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
set "APPROOT=%CD%"
set "PIDFILE_QW=.queue_worker.pid"
set "PIDFILE_SW=.schedule_worker.pid"

if not exist "storage\logs" mkdir "storage\logs"

echo [%date% %time%] AI Ready-Mode supervisor started. >> storage\logs\watchdog.log

:check_workers
call :ensure_worker "%PIDFILE_QW%" qw
call :ensure_worker "%PIDFILE_SW%" sw
ping -n 61 127.0.0.1 >nul
goto check_workers

:ensure_worker
set "PIDFILE=%~1"
set "MODE=%~2"
if exist "%PIDFILE%" (
    set /p WPID=<"%PIDFILE%"
    tasklist /FI "PID eq !WPID!" 2>nul | findstr /r "^!WPID!" >nul
    if not errorlevel 1 exit /b 0
)
if "%MODE%"=="qw" (
    echo [%date% %time%] Starting queue worker... >> storage\logs\watchdog.log
    powershell -NoProfile -Command "$p = Start-Process -WindowStyle Minimized -FilePath '%PHP%' -ArgumentList 'artisan','queue:work','database','--tries=3','--timeout=300','--sleep=1' -WorkingDirectory '%APPROOT%' -PassThru; $p.Id | Out-File -FilePath '%APPROOT%\.queue_worker.pid' -Encoding ascii"
) else (
    echo [%date% %time%] Starting scheduler... >> storage\logs\watchdog.log
    powershell -NoProfile -Command "$p = Start-Process -WindowStyle Minimized -FilePath '%PHP%' -ArgumentList 'artisan','schedule:work' -WorkingDirectory '%APPROOT%' -PassThru; $p.Id | Out-File -FilePath '%APPROOT%\.schedule_worker.pid' -Encoding ascii"
)
exit /b 0
