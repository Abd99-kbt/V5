@echo off
REM ===============================================
REM سكريبت إصلاح المشاكل التلقائي - نظام V5 (Windows)
REM Automatic Issue Fix Script - V5 System (Windows)
REM ===============================================

echo ================================================
echo      إصلاح تلقائي للمشاكل - نظام V5
echo      V5 System Automatic Issue Fix
echo ================================================
echo.

setlocal enabledelayedexpansion

REM متغيرات للنتائج
set FIXES_APPLIED=false
set FIXES_FAILED=false
set BACKUP_CREATED=false

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

REM دالة لطباعة العناوين
:print_header
echo.
echo ================================================
echo %~1
echo ================================================
goto :eof

REM دالة لإنشاء نسخة احتياطية
:create_backup
if !BACKUP_CREATED! equ false (
    call :print_header "💾 إنشاء نسخة احتياطية / Creating Backup"

    set BACKUP_DIR=backup_%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%
    set BACKUP_DIR=!BACKUP_DIR: =0!

    mkdir "!BACKUP_DIR!" 2>nul
    if exist ".env" (
        copy ".env" "!BACKUP_DIR!\.env.backup" >nul
        call :print_success "تم حفظ نسخة احتياطية من .env"
    )
    if exist "composer.lock" (
        copy "composer.lock" "!BACKUP_DIR!\composer.lock.backup" >nul
        call :print_success "تم حفظ نسخة احتياطية من composer.lock"
    )
    if exist "package-lock.json" (
        copy "package-lock.json" "!BACKUP_DIR!\package-lock.json.backup" >nul
        call :print_success "تم حفظ نسخة احتياطية من package-lock.json"
    )

    set BACKUP_CREATED=true
    call :print_info "تم إنشاء مجلد النسخة الاحتياطية: !BACKUP_DIR!"
)
goto :eof

REM ===============================================
REM إصلاح ملف البيئة
REM ===============================================
call :print_header "🔧 إصلاح ملف البيئة / Environment File Fix"

if not exist ".env" (
    if exist ".env.example" (
        call :print_info "نسخ ملف البيئة من النموذج..."
        copy ".env.example" ".env" >nul
        if !errorlevel! equ 0 (
            call :print_success "تم إنشاء ملف .env بنجاح"
            set FIXES_APPLIED=true
        ) else (
            call :print_error "فشل في إنشاء ملف .env"
            set FIXES_FAILED=true
        )
    ) else (
        call :print_warning "ملف .env.example غير موجود - لا يمكن إنشاء .env"
    )
) else (
    call :print_info "ملف .env موجود بالفعل"
)

REM تحديث إعدادات .env للتطوير
if exist ".env" (
    call :print_info "تحديث إعدادات التطوير في .env..."

    REM إنشاء نسخة احتياطية أولاً
    call :create_backup

    REM تحديث الإعدادات باستخدام PowerShell للدقة
    powershell -Command "& { (Get-Content .env) -replace 'APP_ENV=.*', 'APP_ENV=local' | Set-Content .env }" 2>nul
    powershell -Command "& { (Get-Content .env) -replace 'APP_DEBUG=.*', 'APP_DEBUG=true' | Set-Content .env }" 2>nul
    powershell -Command "& { (Get-Content .env) -replace 'APP_URL=.*', 'APP_URL=http://localhost:8000' | Set-Content .env }" 2>nul
    powershell -Command "& { (Get-Content .env) -replace 'DB_DATABASE=.*', 'DB_DATABASE=v5_development' | Set-Content .env }" 2>nul
    powershell -Command "& { (Get-Content .env) -replace 'CACHE_STORE=.*', 'CACHE_STORE=file' | Set-Content .env }" 2>nul
    powershell -Command "& { (Get-Content .env) -replace 'SESSION_DRIVER=.*', 'SESSION_DRIVER=file' | Set-Content .env }" 2>nul
    powershell -Command "& { (Get-Content .env) -replace 'QUEUE_CONNECTION=.*', 'QUEUE_CONNECTION=sync' | Set-Content .env }" 2>nul
    powershell -Command "& { (Get-Content .env) -replace 'LOG_LEVEL=.*', 'LOG_LEVEL=debug' | Set-Content .env }" 2>nul

    call :print_success "تم تحديث إعدادات التطوير"
    set FIXES_APPLIED=true
)

echo.

REM ===============================================
REM إصلاح الصلاحيات
REM ===============================================
call :print_header "🔐 إصلاح الصلاحيات / Permissions Fix"

REM إصلاح صلاحيات مجلد storage
if exist "storage" (
    call :print_info "إصلاح صلاحيات مجلد storage..."
    icacls "storage" /grant Users:F /T /Q >nul 2>&1
    if !errorlevel! equ 0 (
        call :print_success "تم إصلاح صلاحيات مجلد storage"
        set FIXES_APPLIED=true
    ) else (
        call :print_error "فشل في إصلاح صلاحيات مجلد storage"
        set FIXES_FAILED=true
    )
)

REM إصلاح صلاحيات مجلد bootstrap/cache
if exist "bootstrap\cache" (
    call :print_info "إصلاح صلاحيات مجلد bootstrap/cache..."
    icacls "bootstrap\cache" /grant Users:F /T /Q >nul 2>&1
    if !errorlevel! equ 0 (
        call :print_success "تم إصلاح صلاحيات مجلد bootstrap/cache"
        set FIXES_APPLIED=true
    ) else (
        call :print_error "فشل في إصلاح صلاحيات مجلد bootstrap/cache"
        set FIXES_FAILED=true
    )
)

REM إنشاء المجلدات المفقودة
call :print_info "إنشاء المجلدات المطلوبة..."
mkdir "storage\logs" 2>nul
mkdir "storage\framework\sessions" 2>nul
mkdir "storage\framework\views" 2>nul
mkdir "storage\framework\cache" 2>nul
mkdir "storage\app\public" 2>nul
mkdir "bootstrap\cache" 2>nul

call :print_success "تم إنشاء المجلدات المطلوبة"
set FIXES_APPLIED=true

echo.

REM ===============================================
REM إصلاح التبعيات
REM ===============================================
call :print_header "📦 إصلاح التبعيات / Dependencies Fix"

REM إصلاح Composer dependencies
if exist "composer.json" (
    call :print_info "إصلاح تبعيات Composer..."

    REM إنشاء نسخة احتياطية
    call :create_backup

    REM تنظيف cache Composer
    composer clear-cache >nul 2>&1

    REM إعادة تثبيت التبعيات
    composer install --no-dev --optimize-autoloader --no-scripts >nul 2>&1
    if !errorlevel! equ 0 (
        call :print_success "تم إصلاح تبعيات Composer بنجاح"
        set FIXES_APPLIED=true
    ) else (
        call :print_warning "تحذير: مشكلة في تبعيات Composer - جرب composer install يدوياً"
        set FIXES_FAILED=true
    )
)

REM إصلاح npm dependencies
if exist "package.json" (
    call :print_info "إصلاح تبعيات npm..."

    REM إنشاء نسخة احتياطية
    call :create_backup

    REM تنظيف cache npm
    if exist "node_modules" (
        rmdir /s /q "node_modules" 2>nul
    )
    if exist "package-lock.json" (
        del "package-lock.json" 2>nul
    )

    REM إعادة تثبيت التبعيات
    npm cache clean --force >nul 2>&1
    npm install >nul 2>&1
    if !errorlevel! equ 0 (
        call :print_success "تم إصلاح تبعيات npm بنجاح"
        set FIXES_APPLIED=true
    ) else (
        call :print_warning "تحذير: مشكلة في تبعيات npm - جرب npm install يدوياً"
        set FIXES_FAILED=true
    )
)

echo.

REM ===============================================
REM إصلاح Laravel
REM ===============================================
call :print_header "🎯 إصلاح Laravel / Laravel Fix"

if exist "artisan" (
    REM توليد مفتاح التطبيق
    call :print_info "توليد مفتاح التطبيق..."
    php artisan key:generate --force >nul 2>&1
    if !errorlevel! equ 0 (
        call :print_success "تم توليد مفتاح التطبيق"
        set FIXES_APPLIED=true
    ) else (
        call :print_error "فشل في توليد مفتاح التطبيق"
        set FIXES_FAILED=true
    )

    REM إنشاء رابط التخزين
    call :print_info "إنشاء رابط التخزين..."
    php artisan storage:link >nul 2>&1
    if !errorlevel! equ 0 (
        call :print_success "تم إنشاء رابط التخزين"
        set FIXES_APPLIED=true
    ) else (
        call :print_warning "تحذير: مشكلة في إنشاء رابط التخزين"
    )

    REM مسح الكاش
    call :print_info "مسح الكاش..."
    php artisan cache:clear >nul 2>&1
    php artisan config:clear >nul 2>&1
    php artisan route:clear >nul 2>&1
    php artisan view:clear >nul 2>&1
    call :print_success "تم مسح الكاش"
    set FIXES_APPLIED=true

    REM محاولة تشغيل migrations
    call :print_info "فحص قاعدة البيانات وتشغيل migrations..."
    php artisan migrate:status >nul 2>&1
    if !errorlevel! neq 0 (
        call :print_warning "قاعدة البيانات غير متوفرة - سيتم تجاهل migrations"
    ) else (
        php artisan migrate --force >nul 2>&1
        if !errorlevel! equ 0 (
            call :print_success "تم تشغيل migrations بنجاح"
            set FIXES_APPLIED=true
        ) else (
            call :print_warning "تحذير: مشكلة في migrations - تأكد من إعدادات قاعدة البيانات"
            set FIXES_FAILED=true
        )
    )
)

echo.

REM ===============================================
REM إصلاح المنافذ
REM ===============================================
call :print_header "🔌 إصلاح المنافذ / Port Fix"

REM إيقاف العمليات على المنافذ المطلوبة
call :print_info "إيقاف العمليات على المنافذ المطلوبة..."

REM إيقاف العمليات على المنفذ 8000
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8000 "') do (
    taskkill /PID %%a /F >nul 2>&1
)
call :print_success "تم إيقاف العمليات على المنفذ 8000"

REM إيقاف العمليات على المنفذ 5173
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":5173 "') do (
    taskkill /PID %%a /F >nul 2>&1
)
call :print_success "تم إيقاف العمليات على المنفذ 5173"

set FIXES_APPLIED=true

echo.

REM ===============================================
REM تنظيف الملفات المؤقتة
REM ===============================================
call :print_header "🧹 تنظيف الملفات المؤقتة / Cleanup Temporary Files"

call :print_info "حذف ملفات الكاش المؤقتة..."
if exist "storage\framework\cache\data" (
    del /q "storage\framework\cache\data\*" 2>nul
    call :print_success "تم حذف ملفات cache المؤقتة"
)

if exist "storage\logs" (
    REM حذف ملفات السجل القديمة (أقدم من 7 أيام)
    forfiles /p "storage\logs" /m "*.log" /d -7 /c "cmd /c del @path" 2>nul
    call :print_success "تم حذف ملفات السجل القديمة"
)

set FIXES_APPLIED=true

echo.

REM ===============================================
REM تقرير النتائج النهائي
REM ===============================================
call :print_header "📊 تقرير الإصلاحات / Fix Report"

if !FIXES_APPLIED! equ true (
    echo.
    call :print_success "تم تطبيق الإصلاحات بنجاح! ✅"
    call :print_success "Fixes applied successfully!"
    echo.
    call :print_info "🚀 جرب الآن تشغيل: run-local.bat"
    echo.
) else (
    echo.
    call :print_warning "لم يتم تطبيق أي إصلاحات ⚠️"
    call :print_warning "No fixes were applied"
    echo.
)

if !FIXES_FAILED! equ true (
    echo.
    call :print_warning "بعض الإصلاحات فشلت - قد تحتاج إلى التدخل اليدوي"
    call :print_warning "Some fixes failed - manual intervention may be needed"
    echo.
    call :print_info "💡 شغّل diagnose.bat لفحص المشاكل المتبقية"
    echo.
)

if !BACKUP_CREATED! equ true (
    echo.
    call :print_info "📁 تم إنشاء نسخ احتياطية في مجلد backup_*"
    call :print_info "Backup created in backup_* folder"
    echo.
)

echo 📖 للمزيد من المساعدة راجع: TROUBLESHOOTING.md
echo For more help see: TROUBLESHOOTING.md
echo.

echo 💡 نصيحة: شغّل diagnose.bat مرة أخرى للتأكد من حل جميع المشاكل
echo Tip: Run diagnose.bat again to verify all issues are resolved
echo.

pause