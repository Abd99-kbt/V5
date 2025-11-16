<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>License Required - {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .license-error-container {
            background: white;
            border-radius: 12px;
            padding: 3rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .error-icon {
            font-size: 4rem;
            color: #ef4444;
            margin-bottom: 1rem;
        }

        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
        }

        .error-message {
            color: #6b7280;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .license-info {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .license-info h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
        }

        .license-info ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .license-info li {
            color: #6b7280;
            margin-bottom: 0.5rem;
            padding-left: 1.5rem;
            position: relative;
        }

        .license-info li:before {
            content: "•";
            color: #3b82f6;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        .contact-info {
            background: #eff6ff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .contact-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 0.5rem;
        }

        .contact-info p {
            color: #3730a3;
            margin: 0;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 72px;
            color: rgba(239, 68, 68, 0.1);
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
            user-select: none;
        }
    </style>
</head>
<body>
    <div class="license-error-container">
        <div class="error-icon">🔒</div>
        <h1 class="error-title">License Required</h1>

        <div class="error-message">
            <p>{{ $message ?? 'This application requires a valid license to operate.' }}</p>
            @if(isset($reason))
                <p><strong>Reason:</strong> {{ $reason }}</p>
            @endif
        </div>

        <div class="license-info">
            <h3>License Information</h3>
            <ul>
                <li>This is a licensed software product</li>
                <li>Valid license key is required for operation</li>
                <li>Contact your system administrator for license details</li>
                <li>Unauthorized use is prohibited</li>
            </ul>
        </div>

        <div class="contact-info">
            <h4>Need Help?</h4>
            <p>Contact our support team for licensing assistance</p>
            <p>Email: support@yourcompany.com</p>
            <p>Phone: +1 (555) 123-4567</p>
        </div>

        <div class="watermark">
            UNLICENSED
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Prevent right-click
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });

            // Prevent F12 and other dev tools shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I') || (e.ctrlKey && e.key === 'U')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>