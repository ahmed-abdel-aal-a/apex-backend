@echo off
title ApexStore Backend Server
color 0A
echo ============================================
echo    ApexStore Backend Server - Port 8000
echo ============================================
echo.
echo Starting Laravel server...
echo.
cd /d "D:\important projects\apex-store-v2\backend"
php -S 127.0.0.1:8000 server.php
pause
