# 🚀 دليل التشغيل السريع - نظام V5
# Quick Run Guide - V5 System

## ⚡ البدء في 3 دقائق فقط!
## Start in Just 3 Minutes!

### 📋 المتطلبات الأساسية / Prerequisites

#### برمجيات مطلوبة / Required Software:
- ✅ **PHP 8.2+** - [تحميل من الموقع الرسمي](https://www.php.net/downloads)
- ✅ **Composer** - [تحميل من getcomposer.org](https://getcomposer.org/download/)
- ✅ **Node.js 18+** - [تحميل من nodejs.org](https://nodejs.org/)
- ✅ **MySQL 8.0+** - [تحميل من mysql.com](https://dev.mysql.com/downloads/mysql/)
- ✅ **Git Bash** أو **WSL** (لـ Windows) - [تحميل Git](https://git-scm.com/download/win)

#### اختبار المتطلبات / Test Requirements:
```bash
# فحص الإصدارات / Check versions
php --version      # يجب أن يكون 8.2+
composer --version # أي إصدار حديث
node --version     # يجب أن يكون 18+
npm --version      # أي إصدار حديث
mysql --version    # يجب أن يكون 8.0+
```

---

## 🔥 خطوات التشغيل السريع / Quick Start Steps

### الخطوة 1: إعداد قاعدة البيانات / Database Setup (30 ثانية)
```sql
-- في MySQL Workbench أو Command Line:
CREATE DATABASE v5_development CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- تأكد من أن المستخدم root لديه صلاحيات كاملة
```

### الخطوة 2: تشغيل النظام / Run System (2 دقائق)
```bash
# في مجلد المشروع / In project folder:
run-local.bat
```
**هذا كل شيء!** سيتم تلقائياً:
- ✅ فحص المتطلبات
- ✅ تثبيت التبعيات
- ✅ إعداد قاعدة البيانات
- ✅ تشغيل الخوادم
- ✅ فتح المتصفح

### الخطوة 3: الوصول للنظام / Access System
- 🌐 **النظام الرئيسي**: http://localhost:8000
- ⚡ **خادم التطوير**: http://localhost:5173
- 📧 **بريد إلكتروني تجريبي**: http://localhost:1025 (Mailpit)

---

## 👥 بيانات الدخول / Login Credentials

### حسابات المستخدمين التجريبية / Test User Accounts:

#### 🔑 المدير العام / Super Admin:
- **البريد الإلكتروني**: admin@v5.local
- **كلمة المرور**: password

#### 👨‍💼 مدير المبيعات / Sales Manager:
- **البريد الإلكتروني**: sales@v5.local
- **كلمة المرور**: password

#### 👷 مدير الإنتاج / Production Manager:
- **البريد الإلكتروني**: production@v5.local
- **كلمة المرور**: password

#### 🏭 مشرف المستودع / Warehouse Supervisor:
- **البريد الإلكتروني**: warehouse@v5.local
- **كلمة المرور**: password

---

## 🛠️ حل المشاكل الشائعة / Common Issues & Solutions

### ❌ "PHP version must be 8.2+"
**الحل**: قم بتحديث PHP أو استخدم XAMPP الجديد

### ❌ "Composer not found"
**الحل**: قم بتثبيت Composer من الموقع الرسمي

### ❌ "MySQL connection failed"
**الحل**:
```sql
-- تأكد من تشغيل MySQL
sudo service mysql start
-- أو في Windows: ابدأ خدمة MySQL من services.msc
```

### ❌ "Port 8000 already in use"
**الحل**:
```bash
# قتل العملية على المنفذ / Kill process on port
npx kill-port 8000
# أو استخدم منفذ آخر
php artisan serve --port=8001
```

### ❌ "Node modules not found"
**الحل**:
```bash
# حذف وإعادة تثبيت
rm -rf node_modules package-lock.json
npm install
```

---

## 📁 هيكل المشروع / Project Structure

```
V5-System/
├── 📁 app/                 # منطق التطبيق / Application Logic
├── 📁 resources/           # الموارد والقوالب / Resources & Views
├── 📁 routes/              # المسارات / Routes
├── 📁 database/            # قاعدة البيانات / Database
├── 📁 public/              # الملفات العامة / Public Files
├── 📁 scripts/             # سكريپتات مساعدة / Helper Scripts
├── 📄 run-local.bat        # تشغيل سريع / Quick Run
├── 📄 stop-local.bat       # إيقاف النظام / Stop System
├── 📄 check-requirements.bat # فحص المتطلبات / Check Requirements
└── 📄 .env.local           # إعدادات محلية / Local Config
```

---

## 🎯 المميزات الرئيسية / Key Features

- ✅ **نظام إدارة المخزون المتقدم** - Advanced Inventory Management
- ✅ **تتبع الطلبات التلقائي** - Automatic Order Tracking
- ✅ **نظام المستخدمين متعدد المستويات** - Multi-level User System
- ✅ **واجهة إدارة حديثة** - Modern Admin Interface
- ✅ **دعم اللغة العربية** - Arabic Language Support
- ✅ **نظام الأمان المتقدم** - Advanced Security System

---

## 🛑 إيقاف النظام / Stop System

```bash
# إيقاف جميع الخوادم / Stop all servers
stop-local.bat
```

---

## 📞 الدعم والمساعدة / Support & Help

### 📋 فحص المتطلبات التفصيلي / Detailed Requirements Check:
```bash
check-requirements.bat
```

### 📖 الوثائق الكاملة / Full Documentation:
- `docs/QUICK_START_GUIDE.md` - دليل البدء الشامل
- `docs/COMPREHENSIVE_SETUP_GUIDE.md` - دليل الإعداد المفصل
- `README.md` - الملف الرئيسي

### 🐛 الإبلاغ عن المشاكل / Report Issues:
1. تحقق من `storage/logs/laravel.log`
2. راجع `docs/TROUBLESHOOTING_GUIDE.md`
3. تحقق من إصدارات البرمجيات

---

## ⚡ نصائح سريعة / Quick Tips

- 🔄 **إعادة التشغيل**: استخدم `run-local.bat` لإعادة التشغيل
- 🧹 **تنظيف الكاش**: `php artisan cache:clear`
- 📊 **البيانات التجريبية**: `php artisan db:seed`
- 🔍 **البحث في السجلات**: `tail -f storage/logs/laravel.log`

---

**🎉 استمتع باستخدام نظام V5!**
**Enjoy using V5 System!**