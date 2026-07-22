@echo off
setlocal enabledelayedexpansion
title Paperflow Ngrok Server (Mobile dan HP Compatible)
cls

:: Auto-detect PHP dan Node.js
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 call :detect_php

where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 call :detect_node

where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ========================================================
    echo [ERROR] PHP (php.exe) tidak ditemukan!
    echo ========================================================
    echo Sistem tidak menemukan php.exe di Laragon, XAMPP, atau PATH.
    echo Pastikan Laragon/XAMPP sudah berjalan atau tambahkan folder PHP ke PATH.
    echo.
    pause
    exit /b 1
)

where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo ========================================================
    echo [ERROR] Node.js (node.exe) tidak ditemukan!
    echo ========================================================
    echo Sistem tidak menemukan node.exe di Laragon atau Program Files.
    echo.
    pause
    exit /b 1
)

echo ========================================================
echo        PAPERFLOW NGROK LAUNCHER (UNTUK HP DAN LAPTOP LAIN)
echo ========================================================
echo.
echo  [1/2] Membersihkan cache hot-reload dan kompilasi CSS/JS...
echo.

if exist "public\hot" del "public\hot"
call npm run build

echo.
echo  [2/2] Menjalankan layanan Paperflow dan Ngrok:
echo   [SERVE] http://127.0.0.1:8000
echo   [QUEUE] php artisan queue:work --tries=3
echo   [NGROK] https://hormonal-shari-noncommodiously.ngrok-free.dev
echo.
echo  Membuka browser ke https://hormonal-shari-noncommodiously.ngrok-free.dev dalam 3 detik...
echo  Akses dari HP dan laptop lain kini 100%% memuat CSS tanpa popup!
echo  Tekan Ctrl + C di jendela ini untuk menghentikan semua service.
echo ========================================================
echo.

start "" powershell -Command "Start-Sleep -Seconds 3; Start-Process 'https://hormonal-shari-noncommodiously.ngrok-free.dev'"

call npm run dev:ngrok

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Terjadi kesalahan saat menjalankan service.
    pause
)

pause
exit /b 0

:detect_php
for /d %%D in ("C:\laragon\bin\php\php-*") do (
    if exist "%%D\php.exe" (
        set "PATH=%%D;!PATH!"
        goto :eof
    )
)
if exist "C:\xampp\php\php.exe" set "PATH=C:\xampp\php;!PATH!"
if exist "C:\php\php.exe" set "PATH=C:\php;!PATH!"
goto :eof

:detect_node
for /d %%D in ("C:\laragon\bin\nodejs\node-*") do (
    if exist "%%D\node.exe" (
        set "PATH=%%D;!PATH!"
        goto :eof
    )
)
if exist "C:\Program Files\nodejs\node.exe" set "PATH=C:\Program Files\nodejs;!PATH!"
goto :eof
