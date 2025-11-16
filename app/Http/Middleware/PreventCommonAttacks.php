<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PreventCommonAttacks
{
    protected $exemptRoutes = [
        'health-check',
        'ping',
        'api/v1/health',
        'webhook' => ['github', 'stripe', 'paypal']
    ];

    protected $attackLog = [];
    
    /**
     * Handle an incoming request with enhanced protection.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip security checks for exempt routes
        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        // Enhanced rate limiting
        if (!$this->checkRateLimit($request)) {
            $this->logSecurityEvent($request, 'RATE_LIMIT_EXCEEDED', 'Rate limit exceeded');
            return $this->rateLimitResponse($request);
        }

        // Sanitize and check all input data
        $sanitizedData = $this->sanitizeInput($request);
        $request->merge($sanitizedData);

        // Comprehensive attack detection
        if ($this->detectAttacks($request)) {
            return $this->securityViolationResponse($request, 'ATTACK_DETECTED');
        }

        // CSRF protection check
        if (!$this->verifyCsrfToken($request) && $this->requiresCsrfProtection($request)) {
            return $this->securityViolationResponse($request, 'CSRF_TOKEN_INVALID');
        }

        // Check for suspicious user agents
        if ($this->hasSuspiciousUserAgent($request)) {
            return $this->securityViolationResponse($request, 'SUSPICIOUS_USER_AGENT');
        }

        // Check for SQL injection
        if ($this->detectSqlInjection($request)) {
            return $this->securityViolationResponse($request, 'SQL_INJECTION_ATTEMPT');
        }

        // Check for XSS attacks
        if ($this->detectXssAttack($request)) {
            return $this->securityViolationResponse($request, 'XSS_ATTACK_ATTEMPT');
        }

        // Check for command injection
        if ($this->detectCommandInjection($request)) {
            return $this->securityViolationResponse($request, 'COMMAND_INJECTION_ATTEMPT');
        }

        // Check for path traversal
        if ($this->detectPathTraversal($request)) {
            return $this->securityViolationResponse($request, 'PATH_TRAVERSAL_ATTEMPT');
        }

        // Check for file inclusion attacks
        if ($this->detectFileInclusion($request)) {
            return $this->securityViolationResponse($request, 'FILE_INCLUSION_ATTEMPT');
        }

        // Check for LDAP injection
        if ($this->detectLdapInjection($request)) {
            return $this->securityViolationResponse($request, 'LDAP_INJECTION_ATTEMPT');
        }

        // Check for NoSQL injection
        if ($this->detectNoSqlInjection($request)) {
            return $this->securityViolationResponse($request, 'NOSQL_INJECTION_ATTEMPT');
        }

        // Monitor for automation/bot behavior
        if ($this->detectBotBehavior($request)) {
            return $this->securityViolationResponse($request, 'AUTOMATED_BEHAVIOR_DETECTED');
        }

        // Store security context for response
        $request->attributes->set('security_context', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->timestamp,
            'fingerprint' => $this->generateFingerprint($request)
        ]);

        $response = $next($request);

        // Add security headers
        $this->addSecurityHeaders($response);

        // Log successful request (for monitoring)
        $this->logSecurityEvent($request, 'REQUEST_SUCCESS', 'Request processed successfully');

        return $response;
    }

    protected function isExemptRoute(Request $request): bool
    {
        $path = $request->path();
        
        // Check direct exempt routes
        if (in_array($path, $this->exemptRoutes)) {
            return true;
        }

        // Check webhook exempt routes
        if (isset($this->exemptRoutes['webhook'])) {
            foreach ($this->exemptRoutes['webhook'] as $webhook) {
                if (str_contains($path, $webhook)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function checkRateLimit(Request $request): bool
    {
        $key = $this->getRateLimitKey($request);
        $maxAttempts = $this->getMaxAttempts($request);
        $decayMinutes = $this->getDecayMinutes($request);

        return !RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    protected function getRateLimitKey(Request $request): string
    {
        // Use IP + User Agent + Path for more granular rate limiting
        return 'security_limit:' . hash('sha256',
            $request->ip() . $request->userAgent() . $request->path()
        );
    }

    protected function getMaxAttempts(Request $request): int
    {
        if ($this->isAuthEndpoint($request)) {
            return config('security.auth_rate_limit', 5);
        }
        
        if ($request->is('api/*')) {
            return config('security.api_rate_limit', 60);
        }
        
        if ($this->isSensitiveEndpoint($request)) {
            return config('security.sensitive_rate_limit', 10);
        }

        return config('security.general_rate_limit', 100);
    }

    protected function getDecayMinutes(Request $request): int
    {
        if ($this->isAuthEndpoint($request)) {
            return 15; // 15 minutes for auth endpoints
        }
        
        return 1; // 1 minute default
    }

    protected function sanitizeInput(Request $request): array
    {
        $data = $request->all();
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Remove null bytes
                $value = str_replace("\0", '', $value);
                
                // Trim excessive whitespace
                $value = preg_replace('/\s+/', ' ', $value);
                
                // Remove control characters
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
                
                // HTML entity encode
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (is_array($value)) {
                $value = $this->sanitizeNestedArray($value);
            }
            
            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Sanitize nested array recursively
     */
    protected function sanitizeNestedArray(array $array): array
    {
        $sanitized = [];
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                // Remove null bytes
                $value = str_replace("\0", '', $value);
                
                // Trim excessive whitespace
                $value = preg_replace('/\s+/', ' ', $value);
                
                // Remove control characters
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
                
                // HTML entity encode
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (is_array($value)) {
                $value = $this->sanitizeNestedArray($value);
            }
            $sanitized[$key] = $value;
        }
        return $sanitized;
    }

    protected function detectAttacks(Request $request): bool
    {
        $attackDetectors = [
            'sqlInjection' => $this->detectSqlInjection($request),
            'xssAttack' => $this->detectXssAttack($request),
            'commandInjection' => $this->detectCommandInjection($request),
            'pathTraversal' => $this->detectPathTraversal($request),
            'fileInclusion' => $this->detectFileInclusion($request),
            'ldapInjection' => $this->detectLdapInjection($request),
            'nosqlInjection' => $this->detectNoSqlInjection($request),
            'botBehavior' => $this->detectBotBehavior($request)
        ];

        return in_array(true, $attackDetectors);
    }

    protected function detectSqlInjection(Request $request): bool
    {
        $patterns = [
            '/\b(union|select|insert|update|delete|drop|create|alter|exec|execute|sp_)\b/i',
            '/\b(or|and)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i',
            '/\b(union|select)\s+\*/i',
            '/--|\/\*|\*\/|;--|\bchr\b|\bchar\b/i',
            '/\bxp_cmdshell\b|\bsp_executesql\b/i',
            '/information_schema|sys\./i',
            '/load_file\s*\(|into\s+outfile/i',
            '/benchmark\s*\(|sleep\s*\(/i'
        ];

        return $this->scanForPatterns($request, $patterns);
    }

    protected function detectXssAttack(Request $request): bool
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/<object[^>]*>.*?<\/object>/is',
            '/<embed[^>]*>.*?<\/embed>/is',
            '/<form[^>]*>.*?<\/form>/is',
            '/vbscript:/i',
            '/data:text\/html/i',
            '/<svg[^>]*onload[^>]*>/i'
        ];

        return $this->scanForPatterns($request, $patterns);
    }

    protected function detectCommandInjection(Request $request): bool
    {
        $patterns = [
            '/[;&|`$(){}\[\]]/', // Shell metacharacters
            '/(;|\||&|`|\$\(|\$\{)/',
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/proc_open\s*\(/i',
            '/popen\s*\(/i',
            '/base64_decode\s*\(/i'
        ];

        return $this->scanForPatterns($request, $patterns);
    }

    protected function detectPathTraversal(Request $request): bool
    {
        $patterns = [
            '/\.\.\/|\.\.\\\/',
            '/\.\.%2f|%5c/i',
            '/%2e%2e%2f|%2e%2e%5c/i',
            '/\.\.%252f/i',
            '/\.\.\\\\|\.\.\//i'
        ];

        $path = $request->path();
        $queryString = $request->getQueryString() ?? '';
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path) || preg_match($pattern, $queryString)) {
                return true;
            }
        }

        return false;
    }

    protected function detectFileInclusion(Request $request): bool
    {
        $patterns = [
            '/\.\./', // Path traversal
            '/php:\/\/|file:\/\/|ftp:\/\//i',
            '/data:/i',
            '/expect:\/\//i',
            '/input:/i',
            '/filter:/i',
            '/zip:/i',
            '/glob:/i'
        ];

        return $this->scanForPatterns($request, $patterns);
    }

    protected function detectLdapInjection(Request $request): bool
    {
        $patterns = [
            '/\)\s*\(\s*\|\s*\|\s*\(/i',
            '/\)\s*\(\s*&\s*&\s*\(/i',
            '/\*\)/i',
            '/\(\s*\|\s*\|\s*\(/i',
            '/\(\s*&\s*&\s*\(/i'
        ];

        return $this->scanForPatterns($request, $patterns);
    }

    protected function detectNoSqlInjection(Request $request): bool
    {
        // MongoDB/NoSQL specific patterns
        $patterns = [
            '/\$ne|\$gt|\$lt|\$where|\$regex/i',
            '/\{\s*\$where/i',
            '/\bsleep\s*\(|benchmark\s*\(/i',
            '/new\s+date\s*\(/i',
            '/require\s*\(/i',
            '/include\s*\(/i'
        ];

        return $this->scanForPatterns($request, $patterns);
    }

    protected function detectBotBehavior(Request $request): bool
    {
        $userAgent = strtolower($request->userAgent() ?? '');
        
        // Suspicious bot patterns
        $botPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python',
            'requests', 'scrapy', 'mechanize', 'selenium', 'phantom',
            'hack', 'exploit', 'scanner', 'vulnerability'
        ];

        foreach ($botPatterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        // Check for missing user agent
        if (empty($userAgent)) {
            return true;
        }

        // Check for suspicious request patterns
        if ($this->hasSuspiciousRequestPattern($request)) {
            return true;
        }

        return false;
    }

    protected function hasSuspiciousUserAgent(Request $request): bool
    {
        $userAgent = $request->userAgent() ?? '';
        $suspiciousAgents = [
            'sqlmap', 'nikto', 'nessus', 'nmap', 'openvas',
            'w3af', 'burp', 'zap', 'commix', 'arachni'
        ];

        foreach ($suspiciousAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function hasSuspiciousRequestPattern(Request $request): bool
    {
        // Check for too many parameters
        if (count($request->all()) > 100) {
            return true;
        }

        // Check for extremely long parameter values
        foreach ($request->all() as $value) {
            if (is_string($value) && strlen($value) > 10000) {
                return true;
            }
        }

        // Check for suspicious parameter names
        $suspiciousParams = ['cmd', 'exec', 'eval', 'system', 'shell', 'passthru'];
        foreach ($suspiciousParams as $param) {
            if ($request->has($param)) {
                return true;
            }
        }

        return false;
    }

    protected function scanForPatterns(Request $request, array $patterns): bool
    {
        // Check path
        if (preg_match('/(' . implode('|', $patterns) . ')/i', $request->path())) {
            return true;
        }

        // Check query string
        $queryString = $request->getQueryString() ?? '';
        if (preg_match('/(' . implode('|', $patterns) . ')/i', $queryString)) {
            return true;
        }

        // Check request data
        $data = array_merge($request->all(), $request->query());
        return $this->scanArrayForPatterns($data, $patterns);
    }

    protected function scanArrayForPatterns(array $data, array $patterns): bool
    {
        foreach ($data as $value) {
            if (is_string($value) && $this->stringContainsPatterns($value, $patterns)) {
                return true;
            }
            
            if (is_array($value) && $this->scanArrayForPatterns($value, $patterns)) {
                return true;
            }
        }

        return false;
    }

    protected function stringContainsPatterns(string $string, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $string)) {
                return true;
            }
        }

        return false;
    }

    protected function verifyCsrfToken(Request $request): bool
    {
        if (!$this->requiresCsrfProtection($request)) {
            return true;
        }

        $token = $request->header('X-CSRF-TOKEN') ?? $request->input('_token');
        
        return $token && hash_equals(session()->token(), $token);
    }

    protected function requiresCsrfProtection(Request $request): bool
    {
        // Only check for state-changing methods
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return false;
        }

        // Skip CSRF for API endpoints with proper authentication
        if ($request->is('api/*')) {
            return false;
        }

        // Skip for exempt routes
        if ($this->isExemptRoute($request)) {
            return false;
        }

        return true;
    }

    protected function isAuthEndpoint(Request $request): bool
    {
        $authEndpoints = [
            'login', 'logout', 'register', 'password', 'verification',
            'two-factor', 'auth', 'admin/login', 'filament/auth'
        ];

        foreach ($authEndpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                return true;
            }
        }

        return false;
    }

    protected function isSensitiveEndpoint(Request $request): bool
    {
        $sensitiveEndpoints = [
            'admin', 'dashboard', 'profile', 'settings', 'payment',
            'billing', 'account', 'upload', 'file', 'download'
        ];

        foreach ($sensitiveEndpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                return true;
            }
        }

        return false;
    }

    protected function securityViolationResponse(Request $request, string $violation): Response
    {
        // Log the security violation
        $this->logSecurityEvent($request, $violation, 'Security violation detected');

        // Increment security violation counter
        $this->incrementViolationCounter($request);

        // Return generic error response
        return response()->json([
            'error' => 'Invalid request',
            'timestamp' => now()->toISOString(),
            'request_id' => $this->generateRequestId($request)
        ], 400);
    }

    protected function rateLimitResponse(Request $request): Response
    {
        $key = $this->getRateLimitKey($request);
        $seconds = RateLimiter::availableIn($key);
        
        return response()->json([
            'error' => 'Too many requests',
            'retry_after' => $seconds,
            'timestamp' => now()->toISOString()
        ], 429);
    }

    protected function logSecurityEvent(Request $request, string $event, string $message): void
    {
        Log::channel('security')->warning($message, [
            'event' => $event,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
            'timestamp' => now()->toISOString(),
            'fingerprint' => $this->generateFingerprint($request)
        ]);
    }

    protected function incrementViolationCounter(Request $request): void
    {
        $key = 'security_violations:' . $request->ip();
        $violations = Cache::get($key, 0) + 1;
        Cache::put($key, $violations, now()->addDay());

        // Auto-block if too many violations
        if ($violations >= config('security.max_violations', 10)) {
            Cache::put('blocked_ip:' . $request->ip(), true, now()->addHour());
        }
    }

    protected function generateRequestId(Request $request): string
    {
        return hash('sha256',
            $request->ip() .
            $request->path() .
            now()->timestamp .
            random_bytes(16)
        );
    }

    protected function generateFingerprint(Request $request): string
    {
        return hash('sha256',
            $request->ip() .
            $request->userAgent() .
            $request->path()
        );
    }

    protected function addSecurityHeaders(Response $response): void
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('X-Request-ID', uniqid());
    }
}