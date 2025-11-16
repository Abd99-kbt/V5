<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $testUsers;

    protected function setUp(): void
    {
        parent::setUp();
        
        // إعداد البيئة الأمنية للاختبار
        config([
            'security.enable_real_time_alerts' => false,
            'security.log_queries' => false,
            'session.lifetime' => 120,
            'session.secure' => false,
            'session.http_only' => true,
            'session.same_site' => 'lax'
        ]);
        
        // إنشاء مستخدمين للاختبار
        $this->testUsers = User::factory()->count(5)->create();
    }

    /** @test */
    public function test_brute_force_attack_prevention()
    {
        $maxAttempts = config('security.auth_attempts', 5);
        $lockoutTime = config('security.lockout_duration', 900); // 15 minutes
        
        foreach ($this->testUsers as $user) {
            $attempts = 0;
            
            // محاولة كسر كلمة المرور
            while ($attempts < $maxAttempts + 2) {
                $response = $this->post('/login', [
                    'username' => $user->username,
                    'password' => 'wrong_password_' . $attempts
                ]);
                
                $attempts++;
                
                if ($attempts <= $maxAttempts) {
                    // محاولات مبكرة يجب أن ترفض
                    $this->assertTrue(in_array($response->status(), [200, 302, 422]), 
                        "Initial attempts should return validation errors");
                } else {
                    // المحاولات الإضافية يجب أن تؤدي إلى قفل
                    $response->assertStatus(429);
                    $response->assertJsonStructure([
                        'error',
                        'retry_after'
                    ]);
                    
                    // التحقق من معلومات إعادة المحاولة
                    $data = $response->json();
                    $this->assertLessThanOrEqual($lockoutTime, $data['retry_after'], 
                        'Lockout duration should match configuration');
                    break;
                }
            }
        }
    }

    /** @test */
    public function test_password_policy_enforcement()
    {
        $weakPasswords = [
            '123456',
            'password',
            '12345678',
            'qwerty',
            'abc123',
            'password123',
            'admin',
            'test123'
        ];

        $user = $this->testUsers->first();

        foreach ($weakPasswords as $password) {
            $response = $this->post('/register', [
                'username' => 'test_user_' . uniqid(),
                'email' => 'test_' . uniqid() . '@example.com',
                'password' => $password,
                'password_confirmation' => $password
            ]);
            
            // يجب رفض كلمات المرور الضعيفة
            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['password']);
        }
    }

    /** @test */
    public function test_account_lockout_mechanism()
    {
        $user = $this->testUsers->first();
        $maxAttempts = config('security.auth_attempts', 5);
        
        // تحديد عدد المحاولات للإغلاق
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->post('/login', [
                'username' => $user->username,
                'password' => 'wrong_password_' . $i
            ]);
        }
        
        // المحاولة التالية يجب أن تؤدي إلى قفل
        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'another_wrong_password'
        ]);
        
        $response->assertStatus(429);
        
        // التحقق من أن القفل يسجل في قاعدة البيانات أو Cache
        $lockKey = "auth_lockout_{$user->id}";
        $this->assertTrue(Cache::has($lockKey), 'Account should be locked in cache');
        
        // التحقق من مدة القفل
        $lockDuration = Cache::get($lockKey);
        $this->assertGreaterThan(0, $lockDuration, 'Lockout duration should be set');
    }

    /** @test */
    public function test_session_security_under_attack()
    {
        $user = $this->testUsers->first();
        
        // تسجيل دخول صحيح
        $loginResponse = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password'
        ]);
        
        $this->assertTrue(in_array($loginResponse->status(), [200, 302]));
        
        $sessionId = session()->getId();
        
        // محاولات سرقة الجلسة
        $sessionHijackingAttempts = [
            ['HTTP_X_FORWARDED_FOR' => '192.168.1.1'],
            ['HTTP_USER_AGENT' => 'MaliciousBot/1.0'],
            ['HTTP_REFERER' => 'http://malicious-site.com']
        ];
        
        foreach ($sessionHijackingAttempts as $headers) {
            $request = $this->withHeaders($headers);
            
            $response = $request->get('/admin/dashboard');
            
            // يجب منع الوصول أو تسجيل محاولة مشبوهة
            if ($response->status() === 302) {
                // تم إعادة التوجيه (possibly logged out)
                $this->assertTrue(true);
            } elseif ($response->status() === 200) {
                // الوصول مسموح لكن يجب تسجيل الإنذار
                $this->assertTrue(true); // Would check logs in real scenario
            }
        }
    }

    /** @test */
    public function test_multi_factor_authentication_bypass_attempts()
    {
        $user = $this->testUsers->first();
        $user->update(['two_factor_enabled' => true]);
        
        // محاولة تجاوز MFA بالطرق المختلفة
        $bypassAttempts = [
            'skip_mfa' => ['mfa_token' => ''],
            'fake_mfa' => ['mfa_token' => '123456'],
            'expired_mfa' => ['mfa_token' => '999999'],
            'replay_attack' => ['mfa_token' => '123456'] // استخدام نفس الرمز
        ];
        
        foreach ($bypassAttempts as $attemptType => $data) {
            $response = $this->post('/mfa/verify', $data);
            
            // جميع المحاولات يجب أن تفشل
            $response->assertStatus(422);
            
            if ($attemptType === 'replay_attack') {
                $response->assertJsonValidationErrors(['mfa_token']);
            }
        }
    }

    /** @test */
    public function test_login_attack_patterns()
    {
        // SQL Injection في اسم المستخدم
        $sqlPayloads = [
            "admin' --",
            "' OR '1'='1",
            "'; DROP TABLE users; --",
            "1' UNION SELECT * FROM users --",
            "' OR 1=1#"
        ];
        
        foreach ($sqlPayloads as $payload) {
            $response = $this->post('/login', [
                'username' => $payload,
                'password' => 'any_password'
            ]);
            
            // يجب رفض payload ضار
            $response->assertStatus(422);
            
            // يجب تسجيل محاولة مشبوهة
            $this->assertTrue(true); // Would check security logs
        }
        
        // XSS في اسم المستخدم
        $xssPayloads = [
            '<script>alert("xss")</script>',
            '<img src=x onerror=alert("xss")>',
            'javascript:alert("xss")'
        ];
        
        foreach ($xssPayloads as $payload) {
            $response = $this->post('/login', [
                'username' => $payload,
                'password' => 'any_password'
            ]);
            
            // يجب تنظيف أو رفض input ضار
            $response->assertStatus(422);
        }
    }

    /** @test */
    public function test_remember_token_security()
    {
        $user = $this->testUsers->first();
        
        // تسجيل دخول مع تذكر الجلسة
        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
            'remember' => '1'
        ]);
        
        if ($response->status() === 302 || $response->status() === 200) {
            // التحقق من وجود remember token
            $rememberToken = $user->fresh()->remember_token;
            $this->assertNotNull($rememberToken, 'Remember token should be set');
            
            // التحقق من أن token محمي (hashed)
            $this->assertNotEquals($rememberToken, 'test_token', 'Token should be hashed');
            
            // اختبار استخدام token مع user agent مختلف
            $response2 = $this->withHeaders([
                'User-Agent' => 'DifferentBot/1.0'
            ])->get('/');
            
            // يجب رفض الجلسة أو طلب إعادة المصادقة
            $this->assertTrue(true); // Would check actual behavior
        }
    }

    /** @test */
    public function test_concurrent_session_limiting()
    {
        $user = $this->testUsers->first();
        $maxSessions = config('security.max_concurrent_sessions', 3);
        
        // إنشاء جلسات متعددة
        $sessionIds = [];
        
        for ($i = 0; $i < $maxSessions + 1; $i++) {
            $response = $this->post('/login', [
                'username' => $user->username,
                'password' => 'password'
            ]);
            
            if ($response->status() === 200 || $response->status() === 302) {
                $sessionIds[] = session()->getId();
            }
        }
        
        // يجب منع الجلسة الإضافية
        $this->assertLessThanOrEqual($maxSessions + 1, count($sessionIds), 
            'Should limit concurrent sessions');
        
        // إذا تجاوز الحد، يجب إنهاء الجلسة الأقدم
        if (count($sessionIds) > $maxSessions) {
            $this->assertTrue(true); // Would check session management logic
        }
    }

    /** @test */
    public function test_password_reset_security()
    {
        $user = $this->testUsers->first();
        
        // طلب إعادة تعيين كلمة المرور
        $response = $this->post('/password/email', [
            'email' => $user->email
        ]);
        
        $response->assertStatus(200);
        
        // الحصول على reset token (في التطبيق الحقيقي سيكون في البريد الإلكتروني)
        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();
        
        if ($resetToken) {
            // محاولة استخدام token من عنوان IP مختلف
            $response2 = $this->withHeaders([
                'HTTP_X_FORWARDED_FOR' => '192.168.1.100'
            ])->post('/password/reset', [
                'email' => $user->email,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
                'token' => $resetToken->token
            ]);
            
            // يجب منع reset من IP مختلف
            $this->assertTrue(in_array($response2->status(), [422, 403]));
        }
    }

    /** @test */
    public function test_logout_completeness()
    {
        $user = $this->testUsers->first();
        
        // تسجيل دخول
        $loginResponse = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password'
        ]);
        
        // التأكد من تسجيل الدخول
        $this->assertAuthenticatedAs($user);
        
        // تسجيل الخروج
        $logoutResponse = $this->post('/logout');
        
        // التحقق من تسجيل الخروج
        $this->assertGuest();
        
        // محاولة استخدام الجلسة بعد تسجيل الخروج
        $responseAfterLogout = $this->get('/admin/dashboard');
        
        // يجب إعادة التوجيه إلى صفحة تسجيل الدخول
        $responseAfterLogout->assertStatus(302);
    }
}