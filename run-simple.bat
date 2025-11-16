@echo off
REM ===============================================
REM سكريبت التشغيل البسيط - نظام V5 (Windows)
REM Simple Run Script - V5 System (Windows)
REM ===============================================

echo ================================================
echo        تشغيل بسيط لنظام V5
echo        V5 System Simple Run
echo ================================================
echo.

setlocal enabledelayedexpansion

REM متغيرات
set RUN_SUCCESS=false
set PHP_CHECKED=false
set NODE_CHECKED=false

REM دالة لطباعة النجاح
:print_success
echo ✅ %~1
goto :eof

REM دالة لطباعة التحذير
:print_warning
echo ⚠️  %~1
goto :eof

REM دالة لطباعة الخطأ
:print_error
echo ❌ %~1
goto :eof

REM دالة لطباعة المعلومات
:print_info
echo ℹ️  %~1
goto :eof

REM دالة للتحقق السريع من PHP
:check_php_quick
if !PHP_CHECKED! equ false (
    where php >nul 2>nul
    if !errorlevel! neq 0 (
        call :print_error "PHP غير مثبت أو غير متوفر في PATH"
        call :print_info "  💡 تأكد من تثبيت PHP 8.2+ وإضافته إلى PATH"
        call :print_info "  💡 تحميل PHP: https://windows.php.net/download/"
        echo.
        pause
        exit /b 1
    )
    set PHP_CHECKED=true
)
goto :eof

REM دالة للتحقق السريع من Node.js
:check_node_quick
if !NODE_CHECKED! equ false (
    where node >nul 2>nul
    if !errorlevel! neq 0 (
        call :print_error "Node.js غير مثبت أو غير متوفر في PATH"
        call :print_info "  💡 تأكد من تثبيت Node.js 18+ وإضافته إلى PATH"
        call :print_info "  💡 تحميل Node.js: https://nodejs.org/"
        echo.
        pause
        exit /b 1
    )
    set NODE_CHECKED=true
)
goto :eof

REM دالة للتحقق من ملف البيئة
:check_env_file
if not exist ".env" (
    call :print_warning "ملف .env غير موجود"
    if exist ".env.example" (
        call :print_info "نسخ .env من .env.example..."
        copy ".env.example" ".env" >nul
        call :print_success "تم إنشاء ملف .env"
    ) else (
        call :print_error "لا يمكن العثور على .env أو .env.example"
        call :print_info "  💡 تأكد من وجود ملف البيئة"
        echo.
        pause
        exit /b 1
    )
)
goto :eof

REM ===============================================
REM فحص سريع للمتطلبات
REM ===============================================
call :print_info "فحص سريع للمتطلبات..."
echo.

call :check_php_quick
call :check_node_quick
call :check_env_file

REM فحص Git Bash للأوامر المعقدة
where bash >nul 2>nul
if %errorlevel% neq 0 (
    call :print_warning "Git Bash غير متوفر - سيتم استخدام أوامر Windows مباشرة"
    call :print_info "  💡 لأفضل نتائج، ثبت Git Bash من: https://git-scm.com/download/win"
    echo.
)

call :print_success "الفحص السريع مكتمل"
echo.

REM ===============================================
REM بدء التشغيل
REM ===============================================
call :print_info "بدء تشغيل النظام..."
echo.

REM محاولة استخدام start-local.sh إذا كان bash متوفر
where bash >nul 2>nul
if %errorlevel% equ 0 (
    if exist "start-local.sh" (
        call :print_info "استخدام سكريبت Bash المتقدم..."
        bash start-local.sh
        if !errorlevel! equ 0 (
            set RUN_SUCCESS=true
        ) else (
            call :print_error "فشل في تشغيل النظام عبر Bash"
            goto :simple_fallback
        )
    ) else (
        call :print_warning "سكريبت start-local.sh غير موجود"
        goto :simple_fallback
    )
) else (
    call :print_info "استخدام التشغيل البسيط (بدون Bash)..."
    goto :simple_fallback
)

goto :end

REM ===============================================
REM التشغيل البسيط كبديل
REM ===============================================
:simple_fallback
call :print_info "تشغيل مبسط للنظام..."
echo.

REM إعداد قاعدة البيانات إذا أمكن
if exist "artisan" (
    call :print_info "إعداد قاعدة البيانات..."
    php artisan key:generate --force >nul 2>&1
    php artisan migrate --force >nul 2>&1
    if !errorlevel! equ 0 (
        call :print_success "تم إعداد قاعدة البيانات"
    ) else (
        call :print_warning "تحذير: مشكلة في قاعدة البيانات - قد تحتاج إلى إعدادها يدوياً"
    )
)

REM تشغيل الخادم في الخلفية
call :print_info "تشغيل خادم Laravel..."
start "Laravel Server" cmd /c "php artisan serve --host=0.0.0.0 --port=8000"

REM انتظار قليل لبدء الخادم
timeout /t 3 /nobreak >nul

REM فحص إذا كان الخادم يعمل
netstat -an | findstr ":8000" >nul
if %errorlevel% equ 0 (
    call :print_success "خادم Laravel يعمل على: http://localhost:8000"
) else (
    call :print_error "فشل في تشغيل خادم Laravel"
    goto :troubleshooting
)

REM تشغيل Vite في الخلفية
if exist "package.json" (
    call :print_info "تشغيل خادم Vite..."
    start "Vite Dev Server" cmd /c "npm run dev -- --host=0.0.0.0 --port=5173"

    REM انتظار قليل لبدء Vite
    timeout /t 5 /nobreak >nul

    REM فحص إذا كان Vite يعمل
    netstat -an | findstr ":5173" >nul
    if %errorlevel% equ 0 (
        call :print_success "خادم Vite يعمل على: http://localhost:5173"
    ) else (
        call :print_warning "تحذير: فشل في تشغيل خادم Vite - قد يعمل Laravel فقط"
    )
)

set RUN_SUCCESS=true
goto :end

REM ===============================================
REM استكشاف الأخطاء
REM ===============================================
:troubleshooting
echo.
call :print_error "حدثت مشاكل في التشغيل"
echo.
call :print_info "🔧 حلول محتملة:"
echo.
echo "1. 📋 فحص المتطلبات:"
echo "   شغّل: check-requirements.bat"
echo.
echo "2. 🔧 إصلاح المشاكل التلقائي:"
echo "   شغّل: fix-issues.bat"
echo.
echo "3. 🔍 تشخيص شامل:"
echo "   شغّل: diagnose.bat"
echo.
echo "4. 📖 دليل الاستكشاف:"
echo "   اقرأ: TROUBLESHOOTING.md"
echo.
echo "5. 🚀 تشغيل يدوي:"
echo "   php artisan serve --host=0.0.0.0 --port=8000"
echo "   npm run dev -- --host=0.0.0.0 --port=5173"
echo.
pause
exit /b 1

REM ===============================================
REM نهاية التشغيل
REM ===============================================
:end
if !RUN_SUCCESS! equ true (
    echo.
    call :print_success "تم تشغيل النظام بنجاح! 🎉"
    echo.
    echo "🌐 الروابط المتاحة:"
    echo "   📱 النظام الرئيسي: http://localhost:8000"
    echo "   ⚡ خادم التطوير:  http://localhost:5173"
    echo "   📧 البريد التجريبي: http://localhost:1025"
    echo.
    call :print_info "للإيقاف: اضغط Ctrl+C في كل نافذة أو استخدم stop-local.bat"
    echo.
    call :print_info "نصيحة: اترك هذه النافذة مفتوحة أثناء التطوير"
    echo.
) else (
    call :print_error "فشل في تشغيل النظام"
    goto :troubleshooting
)

REM إبقاء النافذة مفتوحة
echo اضغط أي مفتاح للإغلاق...
pause >nul