@echo off
REM ====================================================================
REM  Menyiapkan lingkungan Python dan menjalankan layanan model KNN.
REM  Cukup klik dua kali berkas ini. Tidak perlu conda, tidak perlu
REM  mengaktifkan venv, dan tidak terpengaruh ExecutionPolicy PowerShell
REM  karena python.exe di dalam venv dipanggil langsung.
REM ====================================================================
cd /d "%~dp0"
title Layanan Model KNN - SiDeteksi Stunting

if not exist "venv\Scripts\python.exe" (
    echo [1/3] Membuat lingkungan Python baru di folder ml-service\venv ...
    python -m venv venv
    if errorlevel 1 (
        echo.
        echo GAGAL membuat venv. Pastikan Python sudah terpasang dan dapat
        echo dipanggil dengan perintah: python --version
        pause
        exit /b 1
    )

    echo [2/3] Memasang pustaka yang dibutuhkan ...
    venv\Scripts\python.exe -m pip install --upgrade pip
    venv\Scripts\python.exe -m pip install -r requirements.txt
    if errorlevel 1 (
        echo.
        echo GAGAL memasang pustaka. Periksa koneksi internet lalu ulangi.
        pause
        exit /b 1
    )
) else (
    echo [1/3] Lingkungan Python sudah ada, melewati pemasangan.
    echo [2/3] Untuk memasang ulang dari awal, hapus folder ml-service\venv
    echo       lalu jalankan berkas ini lagi.
)

echo [3/3] Menjalankan layanan model pada http://127.0.0.1:5000
echo.
echo Biarkan jendela ini tetap terbuka selama aplikasi web dipakai.
echo Tekan Ctrl+C untuk menghentikan layanan.
echo.
venv\Scripts\python.exe app.py
pause
