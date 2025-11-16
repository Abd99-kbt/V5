# دليل البدء السريع - نظام V5
## نظام إدارة المبيعات المتقدم

---

### 🚀 مرحباً بك في نظام V5!

هذا الدليل سيساعدك في البدء السريع مع نظام V5. دعنا نبدأ!

---

## 📋 المتطلبات الأساسية

### 1. متطلبات النظام
```bash
# الحد الأدنى
- PHP 8.2+
- MySQL 8.0+
- Redis 6.0+
- Node.js 18+
- Composer 2.5+

# المستحسن
- Ubuntu 20.04+
- 4GB RAM
- 50GB مساحة فارغة
```

### 2. تحميل النظام
```bash
# استنساخ المستودع
git clone [repository-url] v5-system
cd v5-system

# تثبيت التبعيات
composer install
npm install

# نسخ ملف البيئة
cp .env.example .env

# توليد مفتاح التطبيق
php artisan key:generate
```

---

## ⚡ البدء السريع في 5 دقائق

### الخطوة 1: إعداد قاعدة البيانات
```bash
# تعديل إعدادات قاعدة البيانات في .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=v5_system
DB_USERNAME=your_username
DB_PASSWORD=your_password

# تشغيل migrations
php artisan migrate

# إنشاء بيانات تجريبية (اختياري)
php artisan db:seed
```

### الخطوة 2: إعداد التخزين
```bash
# إنشاء رابط التخزين
php artisan storage:link

# تعيين الصلاحيات
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### الخطوة 3: تشغيل الاختبارات الأساسية
```bash
# تشغيل اختبارات شاملة
bash scripts/comprehensive_test_suite.sh

# أو تشغيل يدوياً
php artisan test
```

### الخطوة 4: إنشاء حساب المدير
```bash
# إنشاء مستخدم جديد
php artisan make:user

# أو استخدام seeding
php artisan db:seed --class=AdminUserSeeder
```

### الخطوة 5: تشغيل الخادم
```bash
# تشغيل خادم التطوير
php artisan serve

# الوصول للنظام
# http://localhost:8000
```

---

## 🔧 الأوامر الأساسية

### أوامر Laravel الأساسية
```bash
# إدارة الخادم
php artisan serve                    # تشغيل خادم التطوير
php artisan config:cache            # تخزين إعدادات الكاش
php artisan route:cache            # تخزين routes الكاش
php artisan view:cache             # تخزين views الكاش
php artisan cache:clear            # مسح الكاش

# إدارة قاعدة البيانات
php artisan migrate                # تشغيل migrations
php artisan migrate:status        # عرض حالة migrations
php artisan db:seed               # تشغيل seeders
php artisan db:seed --class=AdminUserSeeder

# إدارة المستخدمين
php artisan make:user            # إنشاء مستخدم
php artisan tinker               # محاكي Laravel

# إدارة التخزين
php artisan storage:link         # إنشاء رابط التخزين
```

### أوامر الأمان
```bash
# فحص الأمان
php artisan security:check

# فحص الثغرات
php artisan security:scan

# إعدادات الأمان
php artisan security:configure
```

### أوامر الاختبار
```bash
# تشغيل جميع الاختبارات
php artisan test

# اختبارات محددة
php artisan test tests/Security/
php artisan test tests/Performance/

# مع التغطية
php artisan test --coverage

# تقرير مفصل
php artisan test --teamcity
```

### أوامر الأتمتة
```bash
# تشغيل المهام المجدولة
php artisan schedule:work

# تشغيل workers
php artisan queue:work

# تشغيل المهام النارية
php artisan queue:work --once

# مراقبة النظام
php artisan monitor:run
```

---

## 🌐 الروابط المهمة

### روابط التطبيق
| الخدمة | الرابط | الوصف |
|---------|--------|--------|
| **الصفحة الرئيسية** | http://localhost:8000 | صفحة الدخول الرئيسية |
| **لوحة الإدارة** | http://localhost:8000/admin | لوحة تحكم الإدارة |
| **واجهة API** | http://localhost:8000/api | واجهات برمجة التطبيقات |
| **مراقبة النظام** | http://localhost:8000/monitoring | صفحة مراقبة الأداء |
| **التقارير** | http://localhost:8000/reports | صفحة التقارير |
| **إعدادات الأمان** | http://localhost:8000/admin/security | إعدادات الأمان |

### روابط التطوير
| الخدمة | الرابط | الوصف |
|---------|--------|--------|
| **PMA** | http://localhost:8080 | phpMyAdmin |
| **Redis Commander** | http://localhost:8081 | إدارة Redis |
| **DebugBar** | متاح عند DEBUG=true | Laravel DebugBar |
| **Telescope** | http://localhost:8000/telescope | Laravel Telescope |

### ملفات الإعدادات
| الملف | المسار | الوصف |
|------|--------|--------|
| **ملف البيئة** | `.env` | متغيرات البيئة |
| **إعدادات Laravel** | `config/app.php` | إعدادات التطبيق |
| **إعدادات قاعدة البيانات** | `config/database.php` | إعدادات DB |
| **إعدادات التخزين** | `config/filesystems.php` | إعدادات التخزين |

---

## 🔑 بيانات الدخول الافتراضية

### حساب المدير
```
البريد الإلكتروني: admin@v5-system.com
كلمة المرور: admin123
الدور: مدير النظام
```

### حساب المطور
```
البريد الإلكتروني: dev@v5-system.com
كلمة المرور: dev123
الدور: مطور
```

### حساب المستخدم
```
البريد الإلكتروني: user@v5-system.com
كلمة المرور: user123
الدور: مستخدم
```

> **⚠️ مهمة**: يرجى تغيير كلمات المرور الافتراضية فوراً بعد التشغيل الأول!

---

## 🚨 فحص سريع للنظام

### تحقق من الخدمات
```bash
# فحص حالة الخوادم
sudo systemctl status mysql
sudo systemctl status redis-server
sudo systemctl status nginx

# فحص المنافذ
netstat -tulpn | grep :80
netstat -tulpn | grep :3306
netstat -tulpn | grep :6379
```

### فحص التطبيق
```bash
# فحص صحة النظام
curl http://localhost:8000/health

# فحص قاعدة البيانات
php artisan migrate:status

# فحص الكاش
php artisan cache:clear
php artisan config:clear
```

### فحص الأداء
```bash
# تشغيل اختبار الأداء
php artisan test tests/Performance/

# فحص استخدام الذاكرة
php artisan tinker
>>> memory_get_usage(true) / 1024 / 1024
```

---

## 🔧 نصائح سريعة

### لتحسين الأداء
```bash
# تخزين إعدادات الإنتاج
php artisan config:cache
php artisan route:cache
php artisan view:cache

# تحسين قاعدة البيانات
php artisan db:optimize
```

### للتطوير
```bash
# تشغيل في وضع التطوير
php artisan serve --host=0.0.0.0

# مشاهدة التغييرات
npm run watch

# إعادة تحميل تلقائي
php artisan serve --host=0.0.0.0 --port=8000
```

### للأمان
```bash
# فحص الثغرات الأمنية
composer audit
composer security-check

# تحديث كلمات المرور
php artisan password:expire

# فحص محاولات الاختراق
tail -f storage/logs/security.log
```

---

## 🆘 حل المشاكل الشائعة

### مشكلة: لا يمكن الاتصال بقاعدة البيانات
```bash
# الحل
1. تأكد من تشغيل MySQL
   sudo systemctl start mysql

2. تأكد من إعدادات .env
   cat .env | grep DB_

3. اختبار الاتصال
   php artisan tinker
   >>> DB::connection()->getPdo();
```

### مشكلة: خطأ في الصلاحيات
```bash
# الحل
sudo chown -R www-data:www-data /path/to/project
sudo chmod -R 775 storage bootstrap/cache
```

### مشكلة: Redis غير متصل
```bash
# الحل
sudo systemctl start redis-server
# أو
redis-cli ping
# يجب أن يعيد: PONG
```

### مشكلة: بطء في الأداء
```bash
# الحل
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### مشكلة: خطأ في Authentication
```bash
# الحل
php artisan config:clear
php artisan cache:clear
php artisan session:table
php artisan migrate
```

---

## 📞 جهات الاتصال والدعم

### فريق التطوير
| الدور | البريد الإلكتروني | الوصف |
|-------|-----------------|--------|
| **مدير المشروع** | project@v5-system.com | إدارة عامة للمشروع |
| **مهندس النظام** | system@v5-system.com | مشاكل تقنية عامة |
| **مطور الواجهة** | frontend@v5-system.com | مشاكل واجهة المستخدم |
| **مطور الخلفية** | backend@v5-system.com | مشاكل API وقاعدة البيانات |

### فريق الأمان
| الدور | البريد الإلكتروني | الوصف |
|-------|-----------------|--------|
| **مهندس الأمان** | security@v5-system.com | مشاكل أمنية |
| **مدير الأمان** | security-lead@v5-system.com | مشاكل أمنية حرجة |
| **مراقب الأمان** | security-monitor@v5-system.com | مراقبة أمنية |

### فريق الدعم
| الدور | البريد الإلكتروني | الهاتف | الوصف |
|-------|-----------------|--------|--------|
| **دعم عام** | support@v5-system.com | +963-XXX-XXXX | دعم يومي |
| **دعم فني** | technical@v5-system.com | +963-XXX-XXXX | دعم تقني |
| **طوارئ** | emergency@v5-system.com | +963-XXX-XXXX | حالات طوارئ |

### أرقام الطوارئ
- **الطوارئ الأمنية**: +963-XXX-XXXX
- **الطوارئ التقنية**: +963-XXX-XXXX
- **الطوارئ العامة**: +963-XXX-XXXX

---

## 📚 موارد إضافية

### الوثائق
- [📖 التوثيق الشامل](COMPREHENSIVE_TESTING_SYSTEM_GUIDE.md)
- [🔒 دليل الأمان](security/COMPREHENSIVE_SECURITY_GUIDE.md)
- [🚀 دليل النشر](production/1-BASIC_DEPLOYMENT_GUIDE.md)
- [📊 تقرير الأداء](PERFORMANCE_METRICS_REPORT.md)

### أدوات مفيدة
- [🔧 Laravel DebugBar](https://github.com/barryvdh/laravel-debugbar)
- [🔍 Laravel Telescope](https://laravel.com/docs/telescope)
- [📊 MySQL Workbench](https://www.mysql.com/products/workbench/)
- [🌐 Postman](https://www.postman.com/) - لاختبار APIs

### مجتمعات Laravel
- [Laravel官方网站](https://laravel.com)
- [Laravel中文网](https://laravel-china.org)
- [Laravel社区论坛](https://laravel.com/community)

---

## ✅ قائمة فحص سريعة

### بعد التثبيت
- [ ] تشغيل `composer install` بنجاح
- [ ] تشغيل `npm install` بنجاح
- [ ] إعداد `.env` بشكل صحيح
- [ ] تشغيل `php artisan migrate` بنجاح
- [ ] تشغيل `php artisan key:generate` بنجاح
- [ ] إنشاء رابط التخزين `storage:link`
- [ ] تعيين الصلاحيات الصحيحة

### قبل الإنتاج
- [ ] تشغيل جميع الاختبارات
- [ ] فحص الأمان
- [ ] إعداد HTTPS
- [ ] نسخ احتياطية
- [ ] مراقبة النظام
- [ ] إعدادات الأداء

### الصيانة اليومية
- [ ] مراقبة السجلات
- [ ] فحص الأداء
- [ ] تحديث التبعيات
- [ ] نسخ احتياطية
- [ ] فحص الأمان

---

**🎉 مبروك! أنت الآن جاهز للبدء مع نظام V5**

### الخطوات التالية:
1. 🌐 [تصفح لوحة الإدارة](http://localhost:8000/admin)
2. 👥 [أنشئ مستخدمين جدد](http://localhost:8000/admin/users)
3. 📊 [استعرض التقارير](http://localhost:8000/admin/reports)
4. 🔒 [راجع إعدادات الأمان](http://localhost:8000/admin/security)
5. ⚙️ [خصص النظام](http://localhost:8000/admin/settings)

**💡 نصيحة**: لا تنس قراءة [دليل الاستخدام الشامل](COMPREHENSIVE_TESTING_SYSTEM_GUIDE.md) للاستفادة القصوى من النظام!

---

**آخر تحديث**: 2025-11-06  
**رقم الإصدار**: 1.0  
**بواسطة**: فريق تطوير V5