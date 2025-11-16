<x-filament-panels::page>
    @php
        $currentLocale = app()->getLocale();
        $isRTL = in_array($currentLocale, ['ar', 'he', 'fa', 'ur']);
        $dir = $isRTL ? 'rtl' : 'ltr';
    @endphp

    <div class="login-container" dir="{{ $dir }}">
        <div class="login-background">
            <div class="floating-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
            </div>
        </div>

        <div class="login-form-container">
            <div class="login-card">
                <div class="login-header">
                    <div class="brand-logo">
                        <span class="brand-text">{{ config('filament.brand') ?: 'Warehouse Management' }}</span>
                        <div class="header-controls">
                            <!-- Language Switcher -->
                            <div class="language-switcher">
                                <button type="button" id="language-toggle" class="language-toggle" aria-label="Switch language" title="Switch language">
                                    <span class="current-lang">{{ strtoupper($currentLocale) }}</span>
                                    <svg class="lang-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 0 1 9-9m-9 9a9 9 0 0 0 9 9"/>
                                    </svg>
                                </button>
                                <div id="language-menu" class="language-menu">
                                    <a href="?lang=en" class="language-option {{ $currentLocale === 'en' ? 'active' : '' }}">
                                        <span class="flag">🇺🇸</span> English
                                    </a>
                                    <a href="?lang=ar" class="language-option {{ $currentLocale === 'ar' ? 'active' : '' }}">
                                        <span class="flag">🇸🇦</span> العربية
                                    </a>
                                </div>
                            </div>

                            <!-- Theme Toggle -->
                            <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
                                <svg class="theme-icon sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="5"/>
                                    <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                                </svg>
                                <svg class="theme-icon moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <h1 class="login-title">{{ __('filament-panels::auth.login.title') }}</h1>
                </div>

                <form method="post" action="{{ filament()->getLoginUrl() }}" class="login-form">
                    @csrf

                    <div class="form-group">
                        <label for="login" class="form-label">{{ __('filament-panels::auth.login.form.email.label') ?: ($isRTL ? 'اسم المستخدم' : 'Username') }}</label>
                        <div class="input-wrapper">
                            <input
                                type="text"
                                id="login"
                                name="login"
                                value="{{ old('login') }}"
                                required
                                autocomplete="username"
                                class="form-input {{ $isRTL ? 'rtl-input' : '' }}"
                                placeholder="{{ __('filament-panels::auth.login.form.email.placeholder') ?: ($isRTL ? 'أدخل اسم المستخدم' : 'Enter username') }}"
                                aria-describedby="login-help"
                                aria-label="{{ __('filament-panels::auth.login.form.email.label') ?: ($isRTL ? 'اسم المستخدم' : 'Username') }}"
                                tabindex="1"
                                dir="{{ $isRTL ? 'rtl' : 'ltr' }}"
                            >
                            <div class="input-icon {{ $isRTL ? 'rtl-icon' : '' }}">
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                        </div>
                        <small id="login-help" class="form-help">{{ __('filament-panels::auth.login.form.email.help') ?: ($isRTL ? 'أدخل اسم المستخدم الخاص بك' : 'Enter your username') }}</small>
                        @error('login')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">{{ __('filament-panels::auth.login.form.password.label') ?: ($isRTL ? 'كلمة المرور' : 'Password') }}</label>
                        <div class="input-wrapper">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="form-input {{ $isRTL ? 'rtl-input' : '' }}"
                                placeholder="{{ __('filament-panels::auth.login.form.password.placeholder') ?: ($isRTL ? 'أدخل كلمة المرور' : 'Enter password') }}"
                                aria-label="{{ __('filament-panels::auth.login.form.password.label') ?: ($isRTL ? 'كلمة المرور' : 'Password') }}"
                                tabindex="2"
                                dir="{{ $isRTL ? 'rtl' : 'ltr' }}"
                            >
                            <div class="input-icon {{ $isRTL ? 'rtl-icon' : '' }}">
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <circle cx="12" cy="16" r="1"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>
                            <button type="button" class="password-toggle {{ $isRTL ? 'rtl-password-toggle' : '' }}" onclick="togglePassword()">
                                <svg class="icon eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-options">
                        <label class="checkbox-wrapper {{ $isRTL ? 'rtl-checkbox' : '' }}">
                            <input type="checkbox" name="remember" class="checkbox-input" id="remember" tabindex="3">
                            <span class="checkbox-checkmark {{ $isRTL ? 'rtl-checkmark' : '' }}"></span>
                            <span class="checkbox-label">{{ __('filament-panels::auth.login.form.remember.label') ?: ($isRTL ? 'تذكرني' : 'Remember me') }}</span>
                        </label>
                    </div>

                    <button type="submit" class="login-button" tabindex="4">
                        <span class="button-text">{{ __('filament-panels::auth.login.form.actions.authenticate.label') ?: ($isRTL ? 'تسجيل الدخول' : 'Sign in') }}</span>
                        <div class="button-shine"></div>
                    </button>
                </form>

                <div class="login-footer">
                    <p class="footer-text">{{ __('filament-panels::auth.login.messages.password_forgotten') ?: ($isRTL ? 'نسيت كلمة المرور؟' : 'Forgot your password?') }}</p>
                </div>
            </div>
        </div>
    </div>

    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            overflow: hidden;
        }

        .login-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
        }

        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }

        .shape-1 {
            width: 100px;
            height: 100px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 150px;
            height: 150px;
            top: 60%;
            right: 15%;
            animation-delay: 2s;
        }

        .shape-3 {
            width: 80px;
            height: 80px;
            bottom: 30%;
            left: 60%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .login-form-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 420px;
            padding: 2rem;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
            background-size: 200% 100%;
            animation: gradientShift 3s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-logo {
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-text {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
            animation: slideInLeft 0.6s ease-out;
            animation-fill-mode: both;
        }

        .form-group:nth-child(1) { animation-delay: 0.2s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }
        .form-group:nth-child(3) { animation-delay: 0.6s; }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            padding-right: 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(5px);
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .form-input::placeholder {
            color: #9ca3af;
        }

        .input-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: color 0.3s ease;
        }

        .form-input:focus + .input-icon {
            color: #667eea;
        }

        .password-toggle {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .form-error {
            display: block;
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .form-help {
            display: block;
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        /* Enhanced accessibility */
        .form-input:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        .form-label {
            font-weight: 600;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .form-input {
                border-width: 3px;
            }

            .form-input:focus {
                border-color: #000;
                outline: 3px solid #000;
            }

            .login-button {
                border: 2px solid #000;
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .login-form-container,
            .form-group,
            .login-button,
            .shape {
                animation: none !important;
            }

            .login-button,
            .form-input,
            .theme-toggle {
                transition: none !important;
            }
        }

        /* Better contrast for dark mode */
        @media (prefers-color-scheme: dark) {
            .form-help {
                color: #9ca3af;
            }
        }

        /* RTL improvements */
        [dir="rtl"] .form-help {
            text-align: right;
        }

        /* Focus management */
        .form-input:focus + .input-icon {
            color: #667eea;
        }

        /* Loading state improvements */
        .login-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .checkbox-input {
            display: none;
        }

        .checkbox-checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 4px;
            margin-left: 0.5rem;
            position: relative;
            transition: all 0.3s ease;
        }

        .checkbox-input:checked + .checkbox-checkmark {
            background: #667eea;
            border-color: #667eea;
        }

        .checkbox-input:checked + .checkbox-checkmark::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
        }

        .checkbox-label {
            font-size: 0.875rem;
            color: #374151;
        }

        .login-button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: bounceIn 0.8s ease-out;
            animation-delay: 0.8s;
            animation-fill-mode: both;
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .button-shine {
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .login-button:hover .button-shine {
            left: 100%;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.4);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .footer-text {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0;
        }

        .footer-text a {
            color: #667eea;
            text-decoration: none;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        /* Language Switcher Styles */
        .header-controls {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .language-switcher {
            position: relative;
        }

        .language-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }

        .language-toggle:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .current-lang {
            font-size: 0.75rem;
            font-weight: 600;
        }

        .lang-icon {
            width: 16px;
            height: 16px;
        }

        .language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            border: 1px solid #e2e8f0;
            min-width: 140px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            margin-top: 0.5rem;
        }

        .language-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .language-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: #374151;
            transition: background-color 0.2s ease;
            border-radius: var(--radius-md);
            margin: 0.25rem;
        }

        .language-option:hover,
        .language-option.active {
            background: #f8fafc;
            color: #667eea;
        }

        .flag {
            font-size: 1rem;
        }

        /* RTL Support */
        [dir="rtl"] .input-icon {
            right: auto;
            left: 1rem;
        }

        [dir="rtl"] .password-toggle {
            left: auto;
            right: 1rem;
        }

        [dir="rtl"] .checkbox-checkmark {
            margin-left: auto;
            margin-right: 0.5rem;
        }

        [dir="rtl"] .language-menu {
            right: auto;
            left: 0;
        }

        /* RTL Input Styles */
        .rtl-input {
            text-align: right;
            direction: rtl;
        }

        .rtl-icon {
            right: auto !important;
            left: 1rem !important;
        }

        .rtl-password-toggle {
            left: auto !important;
            right: 1rem !important;
        }

        .rtl-checkbox {
            flex-direction: row-reverse;
        }

        .rtl-checkmark {
            margin-right: auto !important;
            margin-left: 0.5rem !important;
        }

        /* Arabic Font Support */
        [dir="rtl"] {
            font-family: 'Cairo', 'Noto Sans Arabic', 'Arial Unicode MS', sans-serif;
        }

        [dir="rtl"] .form-input,
        [dir="rtl"] .form-label,
        [dir="rtl"] .checkbox-label {
            font-family: 'Cairo', 'Noto Sans Arabic', 'Arial Unicode MS', sans-serif;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-form-container {
                padding: 1rem;
            }

            .login-card {
                padding: 2rem 1.5rem;
            }

            .login-title {
                font-size: 1.5rem;
            }
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            .login-card {
                background: rgba(17, 24, 39, 0.95);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }

            .form-input {
                background: rgba(31, 41, 59, 0.8);
                border-color: #374151;
                color: #f9fafb;
            }

            .form-input:focus {
                background: #1f2937;
                border-color: #667eea;
            }

            .form-label, .checkbox-label {
                color: #d1d5db;
            }

            .login-title {
                color: #f9fafb;
            }

            .footer-text {
                color: #9ca3af;
            }
        }

        /* Manual Dark Mode Toggle */
        .dark-mode {
            .login-container {
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            }

            .login-card {
                background: rgba(17, 24, 39, 0.95);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }

            .form-input {
                background: rgba(31, 41, 59, 0.8);
                border-color: #374151;
                color: #f9fafb;
            }

            .form-input:focus {
                background: #1f2937;
                border-color: #667eea;
            }

            .form-label, .checkbox-label {
                color: #d1d5db;
            }

            .login-title {
                color: #f9fafb;
            }

            .footer-text {
                color: #9ca3af;
            }

            .brand-text {
                background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .login-card::before {
                background: linear-gradient(90deg, #60a5fa, #a78bfa, #60a5fa);
                background-size: 200% 100%;
            }

            .login-button {
                background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
            }

            .login-button:hover {
                box-shadow: 0 10px 25px -5px rgba(96, 165, 250, 0.4);
            }
        }

        /* Theme Toggle Button */
        .theme-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .theme-toggle:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .dark-mode .theme-toggle:hover {
            background: rgba(96, 165, 250, 0.1);
            color: #60a5fa;
        }

        .theme-icon {
            width: 20px;
            height: 20px;
        }

        .moon-icon {
            display: none;
        }

        .dark-mode .sun-icon {
            display: none;
        }

        .dark-mode .moon-icon {
            display: block;
        }
    </style>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.querySelector('.eye-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>';
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
            }
        }

        // Language switcher functionality
        function toggleLanguageMenu() {
            const menu = document.getElementById('language-menu');
            menu.classList.toggle('show');
        }

        // Close language menu when clicking outside
        document.addEventListener('click', function(e) {
            const languageToggle = document.getElementById('language-toggle');
            const languageMenu = document.getElementById('language-menu');

            if (!languageToggle.contains(e.target)) {
                languageMenu.classList.remove('show');
            }
        });

        // Language toggle click handler
        document.addEventListener('DOMContentLoaded', function() {
            const languageToggle = document.getElementById('language-toggle');
            if (languageToggle) {
                languageToggle.addEventListener('click', toggleLanguageMenu);
            }
        });

        // Dark mode toggle functionality
        function toggleTheme() {
            const body = document.body;
            const themeToggle = document.getElementById('theme-toggle');
            const isDark = body.classList.contains('dark-mode');

            if (isDark) {
                body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
                themeToggle.setAttribute('aria-label', 'Switch to dark mode');
            } else {
                body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
                themeToggle.setAttribute('aria-label', 'Switch to light mode');
            }
        }

        // Initialize theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const themeToggle = document.getElementById('theme-toggle');

            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.body.classList.add('dark-mode');
                themeToggle.setAttribute('aria-label', 'Switch to light mode');
            } else {
                themeToggle.setAttribute('aria-label', 'Switch to dark mode');
            }

            // Add click event to theme toggle
            themeToggle.addEventListener('click', toggleTheme);
        });

        // Add loading state to form submission
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const button = document.querySelector('.login-button');
            const buttonText = document.querySelector('.button-text');

            button.style.opacity = '0.7';
            button.style.pointerEvents = 'none';
            buttonText.textContent = 'جاري تسجيل الدخول...';

            // Add loading spinner
            const spinner = document.createElement('div');
            spinner.className = 'loading-spinner';
            spinner.style.cssText = 'width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-top: 2px solid white; border-radius: 50%; animation: spin 1s linear infinite; margin-left: 0.5rem;';
            button.appendChild(spinner);
        });

        // Add input validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const loginInput = document.getElementById('login');

            // Static username placeholder
            loginInput.setAttribute('placeholder', '{{ $isRTL ? "أدخل اسم المستخدم" : "Enter username" }}');

            // Keyboard navigation enhancements
            document.addEventListener('keydown', function(e) {
                // Enter key on inputs submits form
                if (e.key === 'Enter' && (e.target.id === 'login' || e.target.id === 'password' || e.target.id === 'remember')) {
                    e.preventDefault();
                    document.querySelector('.login-form').submit();
                }
            });

            // Focus management
            const inputs = document.querySelectorAll('input, button');
            inputs.forEach((input, index) => {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Tab') {
                        const nextIndex = e.shiftKey ? index - 1 : index + 1;
                        if (nextIndex >= 0 && nextIndex < inputs.length) {
                            e.preventDefault();
                            inputs[nextIndex].focus();
                        }
                    }
                });
            });

            // Screen reader announcements
            const announce = (message) => {
                const announcement = document.createElement('div');
                announcement.setAttribute('aria-live', 'polite');
                announcement.setAttribute('aria-atomic', 'true');
                announcement.style.position = 'absolute';
                announcement.style.left = '-10000px';
                announcement.style.width = '1px';
                announcement.style.height = '1px';
                announcement.style.overflow = 'hidden';
                announcement.textContent = message;
                document.body.appendChild(announcement);
                setTimeout(() => document.body.removeChild(announcement), 1000);
            };

            // Announce theme changes
            const themeToggle = document.getElementById('theme-toggle');
            themeToggle.addEventListener('click', function() {
                const isDark = document.body.classList.contains('dark-mode');
                announce(isDark ? 'Switched to light mode' : 'Switched to dark mode');
            });
        });
    </script>
</x-filament-panels::page>