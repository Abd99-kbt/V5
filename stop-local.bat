@echo off
REM ===============================================
REM سكريپت إيقاف النظام - نظام V5 (Windows)
REM Stop System Script - V5 System (Windows)
REM ===============================================

echo ================================================
echo           إيقاف نظام V5 - V5 System Stop
echo ================================================
echo.

REM التحقق من وجود bash
where bash >nul 2>nul
if %errorlevel% neq 0 (
    echo ❌ خطأ: يحتاج لبرنامج Git Bash أو WSL
    echo    Please install Git Bash or WSL to run bash scripts
    echo.
    echo 💡 بدائل للإيقاف اليدوي:
    echo    1. اضغط Ctrl+C في terminal الخوادم
    echo    2. استخدم Task Manager لإيقاف PHP/Node processes
    echo    3. أو تشغيل الأوامر:
    echo.
    echo    taskkill /f /im php.exe
    echo    taskkill /f /im node.exe
    echo.
    pause
    exit /b 1
)

echo ✅ تم العثور على bash
echo 🔄 تشغيل سكريپت stop-local.sh...
echo.

REM تشغيل سكريپت bash
bash stop-local.sh %*

if %errorlevel% neq 0 (
    echo.
    echo ⚠️  تم تشغيل سكريپت الإيقاف مع بعض التحذيرات
    echo    This is normal in some cases
) else (
    echo.
    echo ✅ تم إيقاف النظام بنجاح
    echo    System stopped successfully
)

echo.
echo 💡 لتشغيل النظام مرة أخرى:
echo    Use: start-local.bat
echo.
pause