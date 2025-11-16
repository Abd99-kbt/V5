# نظام المصادقة والأذونات المتقدم

## نظرة عامة

تم تطوير نظام شامل للمصادقة والأذونات يدعم اللغة العربية ويوفر حماية أمنية متقدمة مع سهولة الاستخدام. النظام يتضمن ميزات أمان متعددة الطبقات مع دعم كامل للمصادقة متعددة العوامل (MFA).

## المكونات الرئيسية

### 1. نماذج البيانات المحسّنة

#### User Model
- **المزايا الجديدة:**
  - دعم أسماء المستخدمين العربية
  - تتبع آخر تسجيل دخول
  - قفل الحساب التلقائي
  - تتبع بصمة الجهاز
  - دعم MFA
  - دعم OAuth
  - سياسات كلمات المرور
  - مراقبة المخاطر

#### الحقول الأمنية الجديدة
```php
// الحقول الأساسية
'name', 'username', 'email', 'password'
'phone', 'avatar', 'is_active', 'is_email_verified'
'email_verified_at', 'last_login_at', 'last_login_ip'

// الحقول المتقدمة
'failed_login_attempts', 'locked_until', 'mfa_enabled'
'mfa_secret', 'mfa_backup_codes', 'oauth_provider'
'oauth_id', 'oauth_token', 'device_fingerprint'
'security_questions', 'password_changed_at', 'account_type'
```

### 2. خدمات المصادقة المتقدمة

#### AuthenticationService
- **المسؤوليات:**
  - المصادقة الآمنة مع فحص المخاطر
  - كشف النشاط المشبوه
  - حماية ضد هجمات القوة الغاشمة
  - مراقبة محاولات الدخول
  - تسجيل الأحداث الأمنية

```php
// مثال على الاستخدام
$authService = new AuthenticationService();
$user = $authService->authenticate($request, $identifier, $password, $remember);
```

#### MfaService
- **المسؤوليات:**
  - إعداد MFA مع QR codes
  - التحقق من رموز التحقق
  - إدارة رموز الاحتياط
  - إدارة الأجهزة الموثوقة
  - القفل المؤقت عند المحاولات الخاطئة

```php
// مثال على إعداد MFA
$mfaService = new MfaService();
$qrData = $mfaService->generateQrCode($secret, $user);
$isValid = $mfaService->verifyToken($secret, $token);
```

#### PasswordPolicyService
- **المسؤوليات:**
  - فحص قوة كلمات المرور
  - فرض سياسات كلمات المرور
  - منع كلمات المرور الشائعة
  - توليد كلمات مرور قوية
  - إدارة انتهاء صلاحية كلمات المرور

```php
// مثال على فحص كلمة المرور
$result = PasswordPolicyService::validatePassword($password, $user);
$isValid = $result['valid'];
$strength = $result['strength'];
```

#### LoginAttemptService
- **المسؤوليات:**
  - تتبع محاولات الدخول
  - كشف أنماط الهجمات
  - حظر العناوين المشبوهة
  - تقييم المخاطر
  - إحصائيات تسجيل الدخول

#### SecurityAuditService
- **المسؤوليات:**
  - تسجيل الأحداث الأمنية
  - إرسال التنبيهات
  - مراقبة المخاطر
  - تصدير السجلات
  - إحصائيات الأمان

#### UserActivityService
- **المسؤوليات:**
  - تسجيل أنشطة المستخدمين
  - تتبع أنماط الاستخدام
  - كشف النشاط المشبوه
  - تصدير البيانات

### 3. قواعد البيانات والمراجع

#### جداول الأمان الجديدة

1. **security_audit_logs**
   - تسجيل الأحداث الأمنية
   - مستويات الخطورة (info, warning, critical)
   - فهرسة محسّنة للأداء

2. **user_activities**
   - تسجيل جميع الأنشطة
   - تتبع معلومات الطلب
   - إحصائيات الاستخدام

3. **mfa_trusted_devices**
   - إدارة الأجهزة الموثوقة
   - تحديد البصمات الرقمية
   - إدارة انتهاء الصلاحية

## الميزات الأمنية المتقدمة

### 1. المصادقة متعددة العوامل (MFA)

#### الإعداد
```php
// تفعيل MFA للمستخدم
$user->enableMfa();

// إعداد QR Code
$qrData = app(MfaService::class)->generateQrCode($user->mfa_secret, $user);

// التحقق من الرمز
$isValid = app(MfaService::class)->verifyToken($user->mfa_secret, $token);
```

#### إدارة رموز الاحتياط
```php
// توليد رموز احتياطية جديدة
$backupCodes = app(MfaService::class)->regenerateBackupCodes($user, $mfaToken);

// استخدام رمز احتياطي
$isValid = app(MfaService::class)->verifyBackupCode($user, $backupCode);
```

### 2. حماية قفل الحساب

#### آليات الحماية
- قفل تلقائي بعد محاولات فاشلة متكررة
- حظر العناوين المشبوهة
- قفل مؤقت للمحاولات غير الصحيحة
- إشعارات أمنية تلقائية

```php
// فحص حالة القفل
if ($user->isAccountLocked()) {
    $expiresAt = $user->locked_until;
}

// تسجيل محاولة فاشلة
$user->recordFailedLogin();
```

### 3. كشف النشاط المشبوه

#### مؤشرات المخاطر
- محاولات دخول متتالية فاشلة
- تغييرات في عنوان IP
- استخدام أجهزة جديدة
- أوقات دخول غير عادية
- محاولات متعددة من عناوين مختلفة

```php
// فحص مستوى المخاطر
if ($user->isHighRisk()) {
    app(SecurityAuditService::class)->logEvent('high_risk_user', [
        'user_id' => $user->id,
        'risk_factors' => ['multiple_ips', 'rapid_attempts']
    ]);
}
```

### 4. إدارة الجلسات المتقدمة

#### ميزات الجلسة
- تتبع الجلسات النشطة
- إبطال جميع الجلسات
- تحديد بصمة الجهاز
- مراقبة نشاط الجلسة
- انتهاء صلاحية تلقائي

```php
// إحصائيات الجلسة
$sessionCount = $user->getActiveSessionsCount();

// إبطال جميع الجلسات
$user->invalidateAllSessions();
```

### 5. سياسات كلمات المرور المتقدمة

#### متطلبات القوة
- الحد الأدنى للطول (12 حرف)
- أحرف كبيرة وصغيرة
- أرقام ورموز خاصة
- منع الكلمات الشائعة
- منع الأنماط المتكررة
- فحص تاريخ كلمات المرور

```php
// فحص كلمة المرور
$validation = app(PasswordPolicyService::class)->validatePassword($password, $user);
$strength = $validation['strength'];
$errors = $validation['errors'];

// توليد كلمة مرور قوية
$strongPassword = app(PasswordPolicyService::class)->generatePassword(16);
```

## التكامل مع Spatie Permissions

### 1. تحسين الأذونات

#### الأدوار المحسّنة
```php
// الأدوار المتاحة
'super_admin' => 10  // صلاحيات كاملة
'admin' => 9         // إدارة النظام
'manager' => 8       // إدارة العمليات
'operator' => 7      // تشغيل النظام
'viewer' => 6        // عرض فقط
'guest' => 5         // ضيف
```

#### فحص الصلاحيات المتقدم
```php
// فحص متدرج للصلاحيات
$user->can('permission.name');

// فحص حسب مستوى الدور
$user->hasRoleLevel(['admin', 'super_admin']);

// فحص العمليات الحساسة
if ($user->canPerformSensitiveOperations()) {
    // يتطلب MFA للعمليات الحساسة
}
```

### 2. السياسات المتقدمة

#### Permission Policies
```php
// سياسات متدرجة
UserPolicy::class
RolePolicy::class
PermissionPolicy::class

// فحص متعدد المستويات
$user->canAccessAccount($targetUser)
$user->canManageUser($targetUser)
$user->canViewAuditLogs()
```

### 3. الأذونات الديناميكية

#### نظام الأذونات المرنة
```php
// أذونات مشروطة
'edit-order' => function ($user, $order) {
    return $user->hasRole('manager') || $user->id === $order->created_by;
},

// أذونات الوقت
'admin-panel' => function ($user) {
    return $user->hasRole('admin') && now()->isWeekday() && now()->between('08:00', '18:00');
}
```

## التكامل مع Filament

### 1. صفحات المصادقة المخصصة

#### تسجيل الدخول المحسّن
```php
// في FilamentResource
public static function form(Form $form): Form
{
    return $form
        ->schema([
            // دعم أسماء المستخدمين العربية
            TextInput::make('username')
                ->label('اسم المستخدم')
                ->required()
                ->validationMessages([
                    'required' => 'اسم المستخدم مطلوب',
                ]),
            
            // دعم MFA
            TextInput::make('mfa_code')
                ->label('رمز التحقق')
                ->visible(fn ($context) => $context === 'login'),
        ]);
}
```

### 2. إدارة المستخدمين

#### لوحة إدارة محسّنة
```php
// UserResource مع ميزات أمنية
UserResource::class
    ->navigationIcon('heroicon-o-shield-check')
    ->navigationLabel('إدارة المستخدمين')
    ->columns([
        // معلومات الأمان
        TextColumn::make('mfa_enabled')
            ->label('MFA')
            ->boolean(),
        
        TextColumn::make('last_login_at')
            ->label('آخر دخول')
            ->dateTime('j/m/Y H:i'),
        
        TextColumn::make('status')
            ->label('الحالة')
            ->getStateUsing(fn ($record) => $record->getStatusText()),
    ]);
```

### 3. إدارة الأذونات

#### واجهة إدارة متقدمة
```php
// RoleResource مع ميزات محسّنة
RoleResource::class
    ->navigationIcon('heroicon-o-lock-closed')
    ->navigationLabel('الأدوار والصلاحيات')
    
    ->form([
        // معلومات الدور
        TextInput::make('name')
            ->label('اسم الدور')
            ->required(),
        
        // مستوى الصلاحيات
        Select::make('level')
            ->label('مستوى الصلاحية')
            ->options([
                10 => 'مدير عام',
                9 => 'مدير',
                8 => 'مشرف',
                7 => 'عامل',
                6 => 'مراقب',
                5 => 'ضيف',
            ])
            ->required(),
        
        // الأذونات
        CheckboxList::make('permissions')
            ->label('الصلاحيات')
            ->relationship('permissions', 'name')
            ->columns(3),
    ]);
```

## الملفات والتكوين

### 1. ملفات التكوين

#### config/auth.php - محسّن
```php
return [
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'username' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],
    
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],
    
    // إعدادات الأمان المتقدمة
    'max_login_attempts' => env('AUTH_MAX_ATTEMPTS', 5),
    'lockout_duration' => env('AUTH_LOCKOUT_DURATION', 30),
    'password_expiry_days' => env('AUTH_PASSWORD_EXPIRY', 90),
    'password_min_length' => env('AUTH_PASSWORD_MIN_LENGTH', 12),
    'password_history_count' => env('AUTH_PASSWORD_HISTORY', 5),
];
```

### 2. ملفات البحث (Routes)

#### routes/auth.php - جديد
```php
// تسجيل الدخول
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// MFA
Route::get('/mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
Route::post('/mfa/verify', [MfaController::class, 'verify'])->name('mfa.verify');

// إعدادات الأمان
Route::get('/security/dashboard', [SecurityController::class, 'dashboard'])->name('security.dashboard');
Route::post('/security/trust-device', [SecurityController::class, 'trustDevice'])->name('security.trust-device');
```

### 3. مراجع قاعدة البيانات

#### migrations/2025_11_06_053000_add_advanced_security_fields_to_users.php
```php
Schema::table('users', function (Blueprint $table) {
    // معلومات الاتصال
    $table->string('phone')->nullable();
    $table->string('avatar')->nullable();
    
    // حالة الأمان
    $table->boolean('is_active')->default(true);
    $table->boolean('is_email_verified')->default(false);
    
    // تتبع تسجيل الدخول
    $table->timestamp('last_login_at')->nullable();
    $table->string('last_login_ip')->nullable();
    $table->integer('failed_login_attempts')->default(0);
    $table->timestamp('locked_until')->nullable();
    
    // MFA
    $table->boolean('mfa_enabled')->default(false);
    $table->text('mfa_secret')->nullable();
    $table->text('mfa_backup_codes')->nullable();
    
    // OAuth
    $table->string('oauth_provider')->nullable();
    $table->string('oauth_id')->nullable();
    
    // أجهزة وتتبع
    $table->string('device_fingerprint')->nullable();
    $table->enum('account_type', ['admin', 'manager', 'operator', 'viewer', 'guest'])
          ->default('viewer');
});
```

## الاختبارات

### 1. اختبارات وحدة المصادقة

#### tests/Unit/AuthenticationTest.php
```php
public function test_user_can_authenticate_with_username()
{
    $user = User::factory()->create([
        'username' => 'محمد_الأول',
        'password' => bcrypt('StrongPassword123!')
    ]);

    $result = app(AuthenticationService::class)
        ->authenticate(request(), 'محمد_الأول', 'StrongPassword123!');

    $this->assertInstanceOf(User::class, $result);
    $this->assertEquals($user->id, $result->id);
}

public function test_mfa_protection()
{
    $user = User::factory()->create(['mfa_enabled' => true]);
    
    $result = app(MfaService::class)->verifyToken($user->mfa_secret, '123456');
    
    $this->assertIsBool($result);
}
```

### 2. اختبارات الأمان

#### tests/Security/SecurityTest.php
```php
public function test_account_lockout_after_failed_attempts()
{
    $user = User::factory()->create();
    
    for ($i = 0; $i < 6; $i++) {
        $user->recordFailedLogin();
    }
    
    $this->assertTrue($user->fresh()->isAccountLocked());
}

public function test_suspicious_activity_detection()
{
    $user = User::factory()->create();
    
    // محاكاة نشاط مشبوه
    $this->assertTrue($user->isHighRisk());
}
```

## أفضل الممارسات

### 1. أمان كلمات المرور
```php
// فرض كلمات مرور قوية
'password' => 'required|string|min:12|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&].+$/'

// فحص دوري لانتهاء الصلاحية
if ($user->needsPasswordChange()) {
    // إجبار تغيير كلمة المرور
    return redirect()->route('password.change');
}
```

### 2. MFA المطلوب للأدوار الحساسة
```php
// إجبار MFA للمديرين
if ($user->hasRole(['admin', 'super_admin']) && !$user->mfa_enabled) {
    return redirect()->route('mfa.setup')
                     ->with('warning', 'يجب تفعيل المصادقة متعددة العوامل');
}
```

### 3. مراقبة النشاط
```php
// تسجيل جميع الأنشطة الأمنية
UserActivityService::logActivity('user_action', [
    'action' => 'created_order',
    'order_id' => $order->id,
    'ip_address' => request()->ip(),
]);

// مراقبة الأنشطة المشبوهة
SecurityAuditService::logEvent('suspicious_activity', [
    'user_id' => $user->id,
    'activity_type' => 'multiple_failed_logins',
    'severity' => 'high',
]);
```

## المتطلبات

### 1. الحزم المطلوبة
```json
{
    "require": {
        "spatie/laravel-permission": "^5.10",
        "pragmarx/google2fa": "^8.0",
        "bacon/bacon-qr-code": "^2.0"
    }
}
```

### 2. متغيرات البيئة
```env
# إعدادات المصادقة
AUTH_MAX_ATTEMPTS=5
AUTH_LOCKOUT_DURATION=30
AUTH_PASSWORD_EXPIRY=90
AUTH_PASSWORD_MIN_LENGTH=12
AUTH_PASSWORD_HISTORY=5

# OAuth (اختياري)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
MICROSOFT_CLIENT_ID=your_microsoft_client_id
MICROSOFT_CLIENT_SECRET=your_microsoft_client_secret
```

## الدعم والصيانة

### 1. النسخ الاحتياطي
- نسخ احتياطية منتظمة لجداول الأمان
- تصدير دوري لسجلات التدقيق
- نسخ احتياطية لإعدادات MFA

### 2. المراقبة
- مراقبة محاولات الدخول المشبوهة
- تنبيهات الأحداث الأمنية الحرجة
- تقارير الأمان الدورية

### 3. التحديثات
- تحديث دوري لسياسات كلمات المرور
- مراجعة الأذونات والصلاحيات
- تحديث قوائم كلمات المرور المحظورة

---

هذا النظام يوفر حماية أمنية شاملة مع دعم كامل للغة العربية وسهولة الاستخدام. جميع المكونات مصممة للعمل معاً لتوفير تجربة آمنة وموثوقة للمستخدمين.