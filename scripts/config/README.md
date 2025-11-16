# Production Configuration Summary

هذا المجلد يحتوي على ملفات التكوين المحسنة للإنتاج. جميع الملفات مخصصة للاستخدام في بيئة الإنتاج وتحتاج إلى تعديل قبل التطبيق.

## 📁 هيكل الملفات

```
config/
├── nginx/
│   ├── nginx.conf           # الإعدادات العامة لـ Nginx
│   ├── site.conf            # إعدادات موقع Laravel
│   └── ssl.conf             # إعدادات SSL/HTTPS
├── php/
│   ├── php.ini              # إعدادات PHP العامة
│   ├── fpm.conf             # إعدادات PHP-FPM
│   └── opcache.ini          # إعدادات OPcache
├── mysql/
│   ├── my.cnf               # الإعدادات العامة لـ MySQL
│   └── mysql-production.cnf # إعدادات الإنتاج
└── redis/
    └── redis.conf           # إعدادات Redis
```

## ⚙️ كيفية الاستخدام

### 1. إعدادات Nginx
```bash
# النسخ الاحتياطي للإعدادات الحالية
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup

# تطبيق الإعدادات الجديدة
sudo cp config/nginx/nginx.conf /etc/nginx/nginx.conf

# اختبار الإعدادات
sudo nginx -t

# إعادة تحميل Nginx
sudo systemctl reload nginx
```

### 2. إعدادات PHP
```bash
# نسخ إعدادات PHP
sudo cp config/php/php.ini /etc/php/8.2/fpm/php.ini
sudo cp config/php/fpm.conf /etc/php/8.2/fpm/pool.d/www.conf
sudo cp config/php/opcache.ini /etc/php/8.2/fpm/conf.d/99-opcache.ini

# إعادة تشغيل PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 3. إعدادات MySQL
```bash
# نسخ إعدادات MySQL
sudo cp config/mysql/mysql-production.cnf /etc/mysql/conf.d/mysql-production.cnf

# إعادة تشغيل MySQL
sudo systemctl restart mysql
```

### 4. إعدادات Redis
```bash
# نسخ إعدادات Redis
sudo cp config/redis/redis.conf /etc/redis/redis.conf

# إعادة تشغيل Redis
sudo systemctl restart redis
```

## ⚠️ تحذيرات مهمة

### قبل التطبيق
- **دائماً** قم بعمل نسخ احتياطية للإعدادات الحالية
- **اختبر** الإعدادات في بيئة التطوير أولاً
- **راجع** المتغيرات والتأكد من توافقها مع نظامك

### المتغيرات التي تحتاج تعديل
- مسارات الملفات
- أسماء قاعدة البيانات والمستخدمين
- عناوين IP والمنافذ
- كلمات المرور

## 📊 مؤشرات الأداء

### Nginx
- حافظ على CPU usage أقل من 80%
- Response time أقل من 2 ثانية
- 99.9% uptime

### PHP-FPM
- Process pool sizing حسب الذاكرة
- Memory limit مناسب (256MB-512MB)
- OPcache命中率 أعلى من 95%

### MySQL
- Connection pool محسن
- Query cache enabled
- Slow query log مفعل

### Redis
- Memory usage تحت السيطرة
- Eviction policy مناسب
- Persistence enabled

## 🔍 مراقبة التكوينات

### فحص يومي
```bash
# فحص حالة Nginx
nginx -t

# فحص حالة PHP-FPM
systemctl status php8.2-fpm

# فحص حالة MySQL
mysql -e "SHOW STATUS LIKE 'Connections';"

# فحص حالة Redis
redis-cli ping
```

### فحص أسبوعي
```bash
# مراجعة السجلات
tail -f /var/log/nginx/error.log
tail -f /var/log/php8.2-fpm.log
tail -f /var/log/mysql/error.log

# مراجعة الأداء
mysql -e "SHOW PROCESSLIST;"
redis-cli info
```

## 🛠️ استكشاف الأخطاء

### مشاكل شائعة
1. **403 Forbidden**: تحقق من أذونات الملفات
2. **502 Bad Gateway**: تحقق من PHP-FPM
3. **Connection refused**: تحقق من قاعدة البيانات
4. **Memory exhausted**: زيادة memory_limit

تأكد من مراجعة الوثائق الشاملة في `../docs/production/` للحصول على تفاصيل أكثر حول كل إعداد.