@echo off
REM ===============================================
REM سكريپت التشغيل التلقائي - نظام V5 (Windows)
REM Automatic Run Script - V5 System (Windows)
REM ===============================================

echo ================================================
echo           تشغيل نظام V5 - V5 System Auto Run
echo ================================================
echo.

REM التحقق من وجود bash
where bash >nul 2>nul
if %errorlevel% neq 0 (
    echo ❌ خطأ: يحتاج لبرنامج Git Bash أو WSL
    echo    Please install Git Bash or WSL to run bash scripts
    echo.
    echo 💡 بدائل للتشغيل اليدوي:
    echo    1. تثبيت Git for Windows
    echo    2. استخدام WSL
    echo    3. تشغيل الأوامر يدوياً:
    echo.
    echo    php artisan serve --host=0.0.0.0 --port=8000
    echo    npm run dev -- --host=0.0.0.0 --port=5173
    echo.
    pause
    exit /b 1
)

echo ✅ تم العثور على bash
echo 🔄 تشغيل سكريپت start-local.sh...
echo.

REM تشغيل سكريپت bash
bash start-local.sh %*

if %errorlevel% neq 0 (
    echo.
    echo ❌ حدث خطأ في تشغيل النظام
    echo.
    echo 💡 نصائح لحل المشاكل:
    echo    1. تأكد من تثبيت PHP 8.2+
    echo    2. تأكد من تشغيل MySQL
    echo    3. تأكد من تثبيت Node.js
    echo    4. تحقق من ملف .env
    echo.
    pause
)

echo.
echo 🎉 تم الانتهاء من تشغيل النظام
echo 🌐 افتح المتصفح على: http://localhost:8000
echo.
pause