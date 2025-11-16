<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordPolicyService
{
    /**
     * Validate password against policy.
     */
    public static function validatePassword(string $password, ?User $user = null): array
    {
        $errors = [];
        $strength = 0;

        // Check minimum length
        $minLength = config('auth.password_min_length', 8);
        if (strlen($password) < $minLength) {
            $errors[] = "كلمة المرور يجب أن تكون {$minLength} أحرف على الأقل";
        } else {
            $strength += 20;
        }

        // Check for uppercase letters
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "كلمة المرور يجب أن تحتوي على حرف كبير واحد على الأقل";
        } else {
            $strength += 20;
        }

        // Check for lowercase letters
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "كلمة المرور يجب أن تحتوي على حرف صغير واحد على الأقل";
        } else {
            $strength += 20;
        }

        // Check for numbers
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "كلمة المرور يجب أن تحتوي على رقم واحد على الأقل";
        } else {
            $strength += 20;
        }

        // Check for special characters
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "كلمة المرور يجب أن تحتوي على رمز خاص واحد على الأقل";
        } else {
            $strength += 20;
        }

        // Check against common passwords
        if (self::isCommonPassword($password)) {
            $errors[] = "كلمة المرور شائعة جداً، يرجى اختيار كلمة مرور أقوى";
            $strength = 0;
        }

        // Check password history
        if ($user && self::isPasswordInHistory($password, $user)) {
            $errors[] = "لا يمكن استخدام كلمة مرور سابقة";
            $strength = 0;
        }

        // Check for sequential characters
        if (self::hasSequentialCharacters($password)) {
            $errors[] = "كلمة المرور لا يجب أن تحتوي على أحرف متتالية";
            $strength -= 10;
        }

        // Check for repeated characters
        if (self::hasRepeatedCharacters($password)) {
            $errors[] = "كلمة المرور لا يجب أن تحتوي على أحرف متكررة";
            $strength -= 5;
        }

        // Normalize strength score
        $strength = max(0, min(100, $strength));

        $strengthLevel = match (true) {
            $strength >= 80 => 'strong',
            $strength >= 60 => 'medium',
            default => 'weak'
        };

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $strength,
            'strength_level' => $strengthLevel,
            'strength_text' => match ($strengthLevel) {
                'strong' => 'قوية',
                'medium' => 'متوسطة',
                default => 'ضعيفة'
            }
        ];
    }

    /**
     * Check if password is in user's history.
     */
    protected static function isPasswordInHistory(string $password, User $user): bool
    {
        $passwordHistoryCount = config('auth.password_history_count', 5);
        
        // Get last N passwords (would need to store password hash history)
        // For now, we'll return false as we don't have password history
        // In a real implementation, you would store previous password hashes
        
        return false;
    }

    /**
     * Check if password is commonly used.
     */
    protected static function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            '123456', 'password', '123456789', '12345678', '12345',
            '1234567', '1234567890', 'qwerty', 'abc123', 'password1',
            'admin', 'letmein', 'welcome', 'monkey', '123123',
            '111111', 'iloveyou', '1234', '000000', 'password123',
            // Arabic common passwords
            'كلمة المرور', '123456789', 'كلمه مرور', 'مستخدم', 'كلمة سر'
        ];

        return in_array(strtolower($password), array_map('strtolower', $commonPasswords));
    }

    /**
     * Check for sequential characters.
     */
    protected static function hasSequentialCharacters(string $password): bool
    {
        $sequences = [
            '0123456789',
            'abcdefghijklmnopqrstuvwxyz',
            'qwertyuiopasdfghjklzxcvbnm',
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
        ];

        $lowerPassword = strtolower($password);
        
        foreach ($sequences as $sequence) {
            for ($i = 0; $i <= strlen($sequence) - 3; $i++) {
                $substring = substr($sequence, $i, 3);
                if (strpos($lowerPassword, $substring) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check for repeated characters.
     */
    protected static function hasRepeatedCharacters(string $password): bool
    {
        // Check for 3 or more consecutive same characters
        return preg_match('/(.)\1\1/', $password) > 0;
    }

    /**
     * Generate a random strong password.
     */
    public static function generatePassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $allChars = $uppercase . $lowercase . $numbers . $symbols;
        
        // Ensure at least one character from each category
        $password = $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        
        // Fill the rest randomly
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Shuffle the password
        return str_shuffle($password);
    }

    /**
     * Check if password needs to be changed.
     */
    public static function needsPasswordChange(User $user): bool
    {
        if (!$user->password_changed_at) {
            return true; // Never changed password
        }

        $expiryDays = config('auth.password_expiry_days', 90);
        return $user->password_changed_at->addDays($expiryDays)->isPast();
    }

    /**
     * Get days until password expires.
     */
    public static function getDaysUntilExpiry(User $user): ?int
    {
        if (!$user->password_changed_at) {
            return null;
        }

        $expiryDays = config('auth.password_expiry_days', 90);
        $expiryDate = $user->password_changed_at->addDays($expiryDays);
        
        if ($expiryDate->isPast()) {
            return 0;
        }

        return now()->diffInDays($expiryDate, false);
    }

    /**
     * Check password complexity requirements.
     */
    public static function checkComplexity(string $password): array
    {
        $requirements = [
            'min_length' => [
                'requirement' => strlen($password) >= config('auth.password_min_length', 8),
                'message' => 'الحد الأدنى لطول كلمة المرور: ' . config('auth.password_min_length', 8) . ' أحرف'
            ],
            'uppercase' => [
                'requirement' => preg_match('/[A-Z]/', $password) > 0,
                'message' => 'يجب أن تحتوي على حرف كبير واحد على الأقل'
            ],
            'lowercase' => [
                'requirement' => preg_match('/[a-z]/', $password) > 0,
                'message' => 'يجب أن تحتوي على حرف صغير واحد على الأقل'
            ],
            'numbers' => [
                'requirement' => preg_match('/[0-9]/', $password) > 0,
                'message' => 'يجب أن تحتوي على رقم واحد على الأقل'
            ],
            'special_chars' => [
                'requirement' => preg_match('/[^a-zA-Z0-9]/', $password) > 0,
                'message' => 'يجب أن تحتوي على رمز خاص واحد على الأقل'
            ]
        ];

        $metRequirements = collect($requirements)->where('requirement', true)->count();
        $totalRequirements = count($requirements);
        $complexity = ($metRequirements / $totalRequirements) * 100;

        return [
            'requirements' => $requirements,
            'met_count' => $metRequirements,
            'total_count' => $totalRequirements,
            'complexity_percentage' => round($complexity),
            'all_met' => $metRequirements === $totalRequirements
        ];
    }

    /**
     * Get password strength suggestions.
     */
    public static function getStrengthSuggestions(string $password): array
    {
        $suggestions = [];

        if (strlen($password) < 12) {
            $suggestions[] = 'استخدم كلمة مرور أطول (12 حرف على الأقل)';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $suggestions[] = 'أضف أحرف كبيرة';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $suggestions[] = 'أضف أحرف صغيرة';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $suggestions[] = 'أضف أرقام';
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $suggestions[] = 'أضف رموز خاصة';
        }

        if (self::hasSequentialCharacters($password)) {
            $suggestions[] = 'تجنب الأحرف المتتالية';
        }

        if (self::hasRepeatedCharacters($password)) {
            $suggestions[] = 'تجنب الأحرف المتكررة';
        }

        if (self::isCommonPassword($password)) {
            $suggestions[] = 'تجنب الكلمات الشائعة';
        }

        return $suggestions;
    }

    /**
     * Store password change in history.
     */
    public static function storePasswordHistory(User $user, string $hashedPassword): void
    {
        $passwordHistoryCount = config('auth.password_history_count', 5);
        
        // In a real implementation, you would store the hash in a password history table
        // For now, we'll just log the change
        SecurityAuditService::logEvent('password_changed', [
            'user_id' => $user->id,
            'changed_at' => now(),
        ], $user);
    }

    /**
     * Check if password meets enterprise requirements.
     */
    public static function checkEnterpriseCompliance(string $password): array
    {
        $compliance = [
            'meets_requirements' => true,
            'compliance_level' => 'basic',
            'issues' => [],
            'recommendations' => []
        ];

        $minLength = 14; // Enterprise minimum
        $requiresSpecialChars = true;
        $requiresNumbers = true;
        $requiresMixedCase = true;
        $maxAge = 90; // days

        if (strlen($password) < $minLength) {
            $compliance['issues'][] = "كلمة المرور قصيرة جداً للمتطلبات المؤسسية (الحد الأدنى: {$minLength})";
            $compliance['meets_requirements'] = false;
        }

        if ($requiresSpecialChars && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $compliance['issues'][] = 'يجب أن تحتوي على رموز خاصة';
            $compliance['meets_requirements'] = false;
        }

        if ($requiresNumbers && !preg_match('/[0-9]/', $password)) {
            $compliance['issues'][] = 'يجب أن تحتوي على أرقام';
            $compliance['meets_requirements'] = false;
        }

        if ($requiresMixedCase) {
            $hasUpper = preg_match('/[A-Z]/', $password) > 0;
            $hasLower = preg_match('/[a-z]/', $password) > 0;
            
            if (!$hasUpper || !$hasLower) {
                $compliance['issues'][] = 'يجب أن تحتوي على أحرف كبيرة وصغيرة';
                $compliance['meets_requirements'] = false;
            }
        }

        if ($compliance['meets_requirements']) {
            $compliance['compliance_level'] = 'enterprise';
            $compliance['recommendations'][] = 'كلمة المرور تلبي المتطلبات المؤسسية';
        } else {
            $compliance['recommendations'][] = 'راجع المشاكل المذكورة أعلاه';
        }

        return $compliance;
    }

    /**
     * Generate a password reset token.
     */
    public static function generateResetToken(User $user): string
    {
        $token = \Illuminate\Support\Str::random(64);
        
        // Store token with expiration
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );

        return $token;
    }

    /**
     * Verify password reset token.
     */
    public static function verifyResetToken(string $token, string $email): bool
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('created_at', '>=', now()->subHours(2))
            ->first();

        if (!$record) {
            return false;
        }

        return Hash::check($token, $record->token);
    }

    /**
     * Invalidate password reset token.
     */
    public static function invalidateResetToken(string $email): void
    {
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();
    }
}