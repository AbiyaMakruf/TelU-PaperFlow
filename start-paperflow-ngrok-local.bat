@echo off
if "%~1"=="RUNNING_IN_PERSISTENT_CMD" goto :MAIN
cmd /k ""%~f0" RUNNING_IN_PERSISTENT_CMD"
exit /b

:MAIN
title Paperflow Ngrok Local (Ngrok + Docker Local DB)
cls

echo ========================================================
echo   PAPERFLOW LAUNCHER 3: NGROK + DOCKER LOCAL DB
echo ========================================================
echo.
echo  [1/3] Memeriksa instalasi PHP dan Node.js...

:: 1. Auto-detect & prioritize Laragon/XAMPP PHP over barebones C:\php
if exist "C:\laragon\bin\php" for /d %%F in ("C:\laragon\bin\php\*") do if exist "%%F\php.exe" set "PHP_BIN=%%F"
if exist "D:\laragon\bin\php" for /d %%F in ("D:\laragon\bin\php\*") do if exist "%%F\php.exe" set "PHP_BIN=%%F"
if exist "E:\laragon\bin\php" for /d %%F in ("E:\laragon\bin\php\*") do if exist "%%F\php.exe" set "PHP_BIN=%%F"
if exist "F:\laragon\bin\php" for /d %%F in ("F:\laragon\bin\php\*") do if exist "%%F\php.exe" set "PHP_BIN=%%F"
if exist "C:\xampp\php\php.exe" set "PHP_BIN=C:\xampp\php"
if exist "D:\xampp\php\php.exe" set "PHP_BIN=D:\xampp\php"
if exist "E:\xampp\php\php.exe" set "PHP_BIN=E:\xampp\php"
if exist "F:\xampp\php\php.exe" set "PHP_BIN=F:\xampp\php"

if defined PHP_BIN set "PATH=%PHP_BIN%;%PATH%"

where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\php\php.exe" set "PATH=C:\php;%PATH%"
    if exist "D:\php\php.exe" set "PATH=D:\php;%PATH%"
)

:: 2. Auto-detect Node.js
if exist "C:\laragon\bin\nodejs" for /d %%F in ("C:\laragon\bin\nodejs\*") do if exist "%%F\node.exe" set "NODE_BIN=%%F"
if exist "D:\laragon\bin\nodejs" for /d %%F in ("D:\laragon\bin\nodejs\*") do if exist "%%F\node.exe" set "NODE_BIN=%%F"
if exist "E:\laragon\bin\nodejs" for /d %%F in ("E:\laragon\bin\nodejs\*") do if exist "%%F\node.exe" set "NODE_BIN=%%F"
if exist "F:\laragon\bin\nodejs" for /d %%F in ("F:\laragon\bin\nodejs\*") do if exist "%%F\node.exe" set "NODE_BIN=%%F"
if exist "C:\Program Files\nodejs\node.exe" set "NODE_BIN=C:\Program Files\nodejs"
if exist "D:\Program Files\nodejs\node.exe" set "NODE_BIN=D:\Program Files\nodejs"

if defined NODE_BIN set "PATH=%NODE_BIN%;%PATH%"

:: Verification
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ========================================================
    echo [ERROR] PHP tidak ditemukan di Laragon / XAMPP / PATH.
    echo ========================================================
    echo Mohon pastikan Laragon / XAMPP sudah terinstall.
    echo.
    pause
    exit /b 1
)

where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ========================================================
    echo [ERROR] Node.js tidak ditemukan di komputer ini.
    echo ========================================================
    echo Mohon install Node.js dari https://nodejs.org/
    echo.
    pause
    exit /b 1
)

php bootstrap/enable-pgsql.php

:: 3. Ensure Docker PostgreSQL container is up & switch DB to Local
where docker >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    echo  [DOCKER] Memastikan container PostgreSQL local [port 54322] berjalan...
    docker compose up -d >nul 2>nul
)

echo  [DATABASE] Menghubungkan ke Docker Local PostgreSQL (127.0.0.1:54322)...
php artisan paperflow:switch-supabase local >nul 2>nul

:: Free port 8000 & kill orphaned ngrok process
taskkill /F /IM ngrok.exe >nul 2>nul
for /f "tokens=5" %%a in ('netstat -aon 2^>nul ^| findstr ":8000" ^| findstr "LISTENING"') do (
    echo  [FIX] Membebaskan port 8000 yang terpakai oleh PID %%a...
    taskkill /F /PID %%a >nul 2>nul
)

echo  PHP      : OK
echo  Node.js  : OK
echo  Database : Docker PostgreSQL Local (127.0.0.1:54322)
echo.

echo  [2/3] Membersihkan cache hot-reload dan kompilasi CSS/JS...
echo.

if exist "public\hot" del "public\hot"
call npm run build

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Gagal melakukan kompilasi aset npm run build.
    pause
    exit /b 1
)

echo.
echo  [3/3] Menjalankan layanan Paperflow dan Ngrok (Local DB):
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

if defined PHP_BIN (
    set "PHP_EXEC=%PHP_BIN:\=/%/php.exe"
) else (
    set "PHP_EXEC=php"
)

call npx concurrently -k -n "SERVE,QUEUE,NGROK" -c "blue,magenta,cyan" "\"%PHP_EXEC%\" artisan serve" "\"%PHP_EXEC%\" artisan queue:work --tries=3" "ngrok http --url=hormonal-shari-noncommodiously.ngrok-free.dev 8000"

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Terjadi kesalahan saat menjalankan service.
)

pause
exit /b 0
