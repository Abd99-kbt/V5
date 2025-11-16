@echo off
REM ===============================================
REM سكريبت إعداد البيئة - نظام V5 (Windows)
REM Environment Setup Script - V5 System (Windows)
REM ===============================================

echo ================================================
echo      إعداد البيئة لنظام V5
echo      V5 System Environment Setup
echo ================================================
echo.

setlocal enabledelayedexpansion

REM متغيرات
set SETUP_SUCCESS=false
set PHP_INSTALLED=false
set COMPOSER_INSTALLED=false
set NODE_INSTALLED=false
set MYSQL_INSTALLED=false

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

REM دالة للتحقق من وجود الأداة
:check_tool
where %~1 >nul 2>nul
if !errorlevel! equ 0 (
    call :print_success "%~2 مثبت - %~2 is installed"
    set %~3=true
) else (
    call :print_warning "%~2 غير مثبت - %~2 not installed"
    set %~3=false
)
goto :eof

REM دالة لتحميل الملف
:download_file
call :print_info "تحميل %~2..."
powershell -Command "& {Invoke-WebRequest -Uri '%~1' -OutFile '%~2'}"
if !errorlevel! equ 0 (
    call :print_success "تم تحميل %~2"
) else (
    call :print_error "فشل في تحميل %~2"
)
goto :eof

REM ===============================================
REM فحص الأدوات المثبتة حالياً
REM ===============================================
call :print_header "🔍 فحص الأدوات المثبتة حالياً / Checking Currently Installed Tools"

call :check_tool "php" "PHP" "PHP_INSTALLED"
call :check_tool "composer" "Composer" "COMPOSER_INSTALLED"
call :check_tool "node" "Node.js" "NODE_INSTALLED"
call :check_tool "mysql" "MySQL" "MYSQL_INSTALLED"

echo.

REM ===============================================
REM إعداد PHP
REM ===============================================
if !PHP_INSTALLED! equ false (
    call :print_header "🐘 إعداد PHP / PHP Setup"

    call :print_info "PHP غير مثبت. سيتم توفير تعليمات التثبيت..."
    echo.
    echo 📋 خطوات تثبيت PHP يدوياً:
    echo ================================
    echo 1. اذهب إلى: https://windows.php.net/download/
    echo 2. حمل PHP 8.2+ (نسخة x64 Thread Safe)
    echo 3. استخرج الملفات إلى مجلد (مثل: C:\php)
    echo 4. أضف مجلد PHP إلى متغير البيئة PATH
    echo 5. انسخ php.ini-production إلى php.ini
    echo 6. فعّل الامتدادات المطلوبة في php.ini:
    echo    - extension=mysqli
    echo    - extension=pdo_mysql
    echo    - extension=mbstring
    echo    - extension=xml
    echo    - extension=curl
    echo    - extension=zip
    echo    - extension=openssl
    echo.

    REM محاولة التحميل التلقائي (اختياري)
    set /p DOWNLOAD_PHP="هل تريد تحميل PHP تلقائياً؟ (y/n): "
    if /i "!DOWNLOAD_PHP!"=="y" (
        call :download_file "https://windows.php.net/downloads/releases/php-8.2.13-Win32-vs16-x64.zip" "php-8.2.13.zip"
        call :print_info "تم تحميل PHP. يرجى استخراجه وتثبيته يدوياً"
    )

    call :print_warning "PHP لم يتم تثبيته تلقائياً - يحتاج تدخل يدوي"
) else (
    call :print_success "PHP مثبت بالفعل"
)

echo.

REM ===============================================
REM إعداد Composer
REM ===============================================
if !COMPOSER_INSTALLED! equ false (
    call :print_header "📦 إعداد Composer / Composer Setup"

    call :print_info "تثبيت Composer..."
    echo.

    REM تحميل وتثبيت Composer
    call :download_file "https://getcomposer.org/Composer-Setup.exe" "Composer-Setup.exe"

    if exist "Composer-Setup.exe" (
        call :print_info "تشغيل مثبت Composer..."
        start /wait Composer-Setup.exe /SILENT /NORESTART
        if !errorlevel! equ 0 (
            call :print_success "تم تثبيت Composer بنجاح"
            set COMPOSER_INSTALLED=true
        ) else (
            call :print_error "فشل في تثبيت Composer"
        )
    ) else (
        call :print_error "فشل في تحميل Composer"
        echo.
        echo 📋 تثبيت Composer يدوياً:
        echo ============================
        echo 1. اذهب إلى: https://getcomposer.org/download/
        echo 2. حمل Composer-Setup.exe
        echo 3. شغّل المثبت كمدير
    )
) else (
    call :print_success "Composer مثبت بالفعل"
)

echo.

REM ===============================================
REM إعداد Node.js
REM ===============================================
if !NODE_INSTALLED! equ false (
    call :print_header "📦 إعداد Node.js / Node.js Setup"

    call :print_info "تثبيت Node.js..."
    echo.

    REM تحميل مثبت Node.js
    call :download_file "https://nodejs.org/dist/v20.10.0/node-v20.10.0-x64.msi" "nodejs-installer.msi"

    if exist "nodejs-installer.msi" (
        call :print_info "تشغيل مثبت Node.js..."
        msiexec /i "nodejs-installer.msi" /quiet /norestart
        if !errorlevel! equ 0 (
            call :print_success "تم تثبيت Node.js بنجاح"
            set NODE_INSTALLED=true
        ) else (
            call :print_error "فشل في تثبيت Node.js"
        )
    ) else (
        call :print_error "فشل في تحميل Node.js"
        echo.
        echo 📋 تثبيت Node.js يدوياً:
        echo ========================
        echo 1. اذهب إلى: https://nodejs.org/
        echo 2. حمل النسخة LTS (20.x)
        echo 3. شغّل المثبت
    )
) else (
    call :print_success "Node.js مثبت بالفعل"
)

echo.

REM ===============================================
REM إعداد MySQL
REM ===============================================
if !MYSQL_INSTALLED! equ false (
    call :print_header "🗄️ إعداد MySQL / MySQL Setup"

    call :print_info "MySQL غير مثبت. سيتم توفير تعليمات التثبيت..."
    echo.
    echo 📋 خطوات تثبيت MySQL يدوياً:
    echo ================================
    echo 1. اذهب إلى: https://dev.mysql.com/downloads/mysql/
    echo 2. حمل MySQL Installer
    echo 3. شغّل المثبت واختر:
    echo    - MySQL Server
    echo    - MySQL Workbench (اختياري)
    echo 4. أثناء التثبيت:
    echo    - اختر Standalone MySQL Server
    echo    - اضبط كلمة مرور root
    echo    - فعّل TCP/IP على المنفذ 3306
    echo 5. تأكد من تشغيل خدمة MySQL
    echo.

    REM محاولة التحميل التلقائي (اختياري)
    set /p DOWNLOAD_MYSQL="هل تريد تحميل MySQL تلقائياً؟ (y/n): "
    if /i "!DOWNLOAD_MYSQL!"=="y" (
        call :download_file "https://dev.mysql.com/get/Downloads/MySQLInstaller/mysql-installer-web-community-8.0.35.0.msi" "mysql-installer.msi"
        call :print_info "تم تحميل MySQL. يرجى تثبيته يدوياً"
    )

    call :print_warning "MySQL لم يتم تثبيته تلقائياً - يحتاج تدخل يدوي"
) else (
    call :print_success "MySQL مثبت بالفعل"
)

echo.

REM ===============================================
REM إعداد Git Bash (اختياري)
REM ===============================================
call :print_header "🐚 إعداد Git Bash / Git Bash Setup (Optional)"

where bash >nul 2>nul
if !errorlevel! neq 0 (
    call :print_info "Git Bash غير مثبت. مُستحسن للأوامر المتقدمة..."
    echo.
    echo 📋 تثبيت Git Bash:
    echo ===================
    echo 1. اذهب إلى: https://git-scm.com/download/win
    echo 2. حمل Git for Windows
    echo 3. أثناء التثبيت:
    echo    - اختر Git Bash
    echo    - اختر Windows Command Prompt
    echo    - اختر Use Windows default console
    echo.

    set /p DOWNLOAD_GIT="هل تريد تحميل Git تلقائياً؟ (y/n): "
    if /i "!DOWNLOAD_GIT!"=="y" (
        call :download_file "https://github.com/git-for-windows/git/releases/download/v2.43.0.windows.1/Git-2.43.0-64-bit.exe" "Git-Installer.exe"
        call :print_info "تم تحميل Git. يرجى تثبيته يدوياً"
    )
) else (
    call :print_success "Git Bash متوفر"
)

echo.

REM ===============================================
REM إعداد متغيرات البيئة
REM ===============================================
call :print_header "🔧 إعداد متغيرات البيئة / Environment Variables Setup"

call :print_info "فحص متغير PATH..."

REM فحص وجود PHP في PATH
echo %PATH% | findstr /i "php" >nul
if !errorlevel! neq 0 (
    call :print_warning "PHP غير موجود في PATH"
    call :print_info "💡 تأكد من إضافة مجلد PHP إلى PATH في إعدادات النظام"
) else (
    call :print_success "PHP موجود في PATH"
)

REM فحص وجود Composer في PATH
echo %PATH% | findstr /i "composer" >nul
if !errorlevel! neq 0 (
    call :print_warning "Composer غير موجود في PATH"
    call :print_info "💡 تأكد من إضافة مجلد Composer إلى PATH")
) else (
    call :print_success "Composer موجود في PATH"
)

REM فحص وجود Node.js في PATH
echo %PATH% | findstr /i "nodejs" >nul
if !errorlevel! neq 0 (
    echo %PATH% | findstr /i "node" >nul
    if !errorlevel! neq 0 (
        call :print_warning "Node.js غير موجود في PATH"
        call :print_info "💡 Node.js يجب أن يضيف نفسه تلقائياً إلى PATH")
    ) else (
        call :print_success "Node.js موجود في PATH"
    )
) else (
    call :print_success "Node.js موجود في PATH"
)

echo.

REM ===============================================
REM إعداد قاعدة البيانات
REM ===============================================
call :print_header "🗄️ إعداد قاعدة البيانات / Database Setup"

where mysql >nul 2>nul
if !errorlevel! equ 0 (
    call :print_info "إنشاء قاعدة البيانات v5_development..."

    REM محاولة إنشاء قاعدة البيانات
    echo CREATE DATABASE IF NOT EXISTS v5_development CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; | mysql -u root -p 2>nul
    if !errorlevel! equ 0 (
        call :print_success "تم إنشاء قاعدة البيانات v5_development"
    ) else (
        call :print_warning "تعذر إنشاء قاعدة البيانات تلقائياً"
        call :print_info "💡 قم بإنشاء قاعدة البيانات يدوياً:")
        echo CREATE DATABASE v5_development CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    )
) else (
    call :print_warning "MySQL غير متوفر - سيتم تجاهل إعداد قاعدة البيانات"
)

echo.

REM ===============================================
REM تشغيل فحص المتطلبات
REM ===============================================
call :print_header "✅ تشغيل فحص المتطلبات / Running Requirements Check"

if exist "check-requirements.bat" (
    call :print_info "تشغيل فحص المتطلبات..."
    call check-requirements.bat
) else (
    call :print_warning "ملف check-requirements.bat غير موجود"
)

echo.

REM ===============================================
REM تقرير النتائج النهائي
REM ===============================================
call :print_header "📊 تقرير إعداد البيئة / Environment Setup Report"

echo تم إعداد البيئة مع المكونات التالية:
echo Environment setup completed with the following components:
echo.

if !PHP_INSTALLED! equ true (
    call :print_success "PHP: مثبت ✓"
) else (
    call :print_warning "PHP: غير مثبت ⚠️"
)

if !COMPOSER_INSTALLED! equ true (
    call :print_success "Composer: مثبت ✓"
) else (
    call :print_warning "Composer: غير مثبت ⚠️"
)

if !NODE_INSTALLED! equ true (
    call :print_success "Node.js: مثبت ✓"
) else (
    call :print_warning "Node.js: غير مثبت ⚠️"
)

if !MYSQL_INSTALLED! equ true (
    call :print_success "MySQL: مثبت ✓"
) else (
    call :print_warning "MySQL: غير مثبت ⚠️"
)

echo.
call :print_info "الخطوات التالية / Next Steps:"
echo ================================
echo 1. أعد تشغيل Command Prompt لتحديث PATH
echo 2. شغّل: diagnose.bat
echo 3. شغّل: fix-issues.bat
echo 4. شغّل: run-simple.bat
echo.

call :print_info "📖 للمساعدة راجع: TROUBLESHOOTING.md"
echo.

if !PHP_INSTALLED! equ true (
    if !COMPOSER_INSTALLED! equ true (
        if !NODE_INSTALLED! equ true (
            call :print_success "البيئة جاهزة للاستخدام! 🎉"
            set SETUP_SUCCESS=true
        )
    )
)

if !SETUP_SUCCESS! equ false (
    call :print_warning "قد تحتاج إلى تثبيت بعض المكونات يدوياً ⚠️"
)

echo.
pause