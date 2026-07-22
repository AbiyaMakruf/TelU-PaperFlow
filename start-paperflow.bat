@echo off
if "%~1"=="RUNNING_IN_PERSISTENT_CMD" goto :MAIN
cmd /k ""%~f0" RUNNING_IN_PERSISTENT_CMD"
exit /b

:MAIN
title Paperflow Development Server (Local)
cls

echo ========================================================
echo             PAPERFLOW DEVELOPMENT LAUNCHER (LOCAL)
echo ========================================================
echo.
echo  [1/2] Memeriksa instalasi PHP dan Node.js...

:: Auto-detect PHP
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 call :detect_php

:: Auto-detect Node
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 call :detect_node

where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ========================================================
    echo [ERROR] PHP (php.exe) tidak ditemukan di komputer ini!
    echo ========================================================
    echo Lokasi pencarian: Laragon (C:\, D:\), XAMPP, C:\php, D:\php, PATH.
    echo Pastikan PHP sudah terinstall atau tambahkan folder php.exe
    echo ke Environment Variables Windows (System PATH).
    echo.
    goto :END
)

where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ========================================================
    echo [ERROR] Node.js (node.exe) tidak ditemukan di komputer ini!
    echo ========================================================
    echo Mohon install Node.js dari https://nodejs.org/
    echo.
    goto :END
)

echo  PHP    : OK
echo  Node.js: OK
echo.

echo  [2/2] Menjalankan layanan Paperflow lokal:
echo   [SERVE] http://127.0.0.1:8000
echo   [QUEUE] php artisan queue:work --tries=3
echo   [VITE]  Vite Dev Server
echo.
echo  Membuka browser ke http://127.0.0.1:8000 dalam 3 detik...
echo  Tekan Ctrl + C di jendela ini untuk menghentikan semua service.
echo ========================================================
echo.

start "" powershell -Command "Start-Sleep -Seconds 3; Start-Process 'http://127.0.0.1:8000'"

call npm run dev

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Terjadi kesalahan saat menjalankan service.
)

:END
echo.
echo ========================================================
echo Sesi selesai. Jendela ini sengaja tetap terbuka agar Anda
echo dapat membaca log / pesan error jika ada.
echo ========================================================
pause
exit /b 0

:detect_php
if exist "C:\laragon\bin\php" (
    for /d %%D in ("C:\laragon\bin\php\php-*") do if exist "%%D\php.exe" set "PATH=%%D;%PATH%"
)
if exist "D:\laragon\bin\php" (
    for /d %%D in ("D:\laragon\bin\php\php-*") do if exist "%%D\php.exe" set "PATH=%%D;%PATH%"
)
if exist "C:\xampp\php\php.exe" set "PATH=C:\xampp\php;%PATH%"
if exist "D:\xampp\php\php.exe" set "PATH=D:\xampp\php;%PATH%"
if exist "C:\php\php.exe" set "PATH=C:\php;%PATH%"
if exist "D:\php\php.exe" set "PATH=D:\php;%PATH%"
goto :eof

:detect_node
if exist "C:\laragon\bin\nodejs" (
    for /d %%D in ("C:\laragon\bin\nodejs\node-*") do if exist "%%D\node.exe" set "PATH=%%D;%PATH%"
)
if exist "D:\laragon\bin\nodejs" (
    for /d %%D in ("D:\laragon\bin\nodejs\node-*") do if exist "%%D\node.exe" set "PATH=%%D;%PATH%"
)
if exist "C:\Program Files\nodejs\node.exe" set "PATH=C:\Program Files\nodejs;%PATH%"
if exist "D:\Program Files\nodejs\node.exe" set "PATH=D:\Program Files\nodejs;%PATH%"
goto :eof
