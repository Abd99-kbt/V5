<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied - {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .security-error-container {
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
            color: #dc2626;
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

        .security-info {
            background: #fef2f2;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .security-info h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #dc2626;
            margin-bottom: 1rem;
        }

        .security-info ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .security-info li {
            color: #6b7280;
            margin-bottom: 0.5rem;
            padding-left: 1.5rem;
            position: relative;
        }

        .security-info li:before {
            content: "⚠️";
            position: absolute;
            left: 0;
        }

        .contact-info {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .contact-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .contact-info p {
            color: #6b7280;
            margin: 0;
            font-size: 0.875rem;
        }

        .client-info {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 1rem;
            font-size: 0.75rem;
            color: #9ca3af;
            text-align: left;
        }

        .client-info strong {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="security-error-container">
        <div class="error-icon">🚫</div>
        <h1 class="error-title">Access Denied</h1>

        <div class="error-message">
            <p>{{ $message ?? 'Your access to this resource has been blocked for security reasons.' }}</p>
            @if(isset($reason))
                <p><strong>Reason:</strong> {{ $reason }}</p>
            @endif
        </div>

        <div class="security-info">
            <h3>Security Notice</h3>
            <ul>
                <li>This incident has been logged for security review</li>
                <li>Repeated violations may result in permanent blocking</li>
                <li>If you believe this is an error, contact system administrator</li>
                <li>Access attempts are monitored and recorded</li>
            </ul>
        </div>

        <div class="contact-info">
            <h4>Need Assistance?</h4>
            <p>If you believe this block is in error, please contact:</p>
            <p><strong>Security Team</strong></p>
            <p>Email: security@yourcompany.com</p>
            <p>Phone: +1 (555) 123-4567</p>
        </div>

        <div class="client-info">
            <strong>Request Details:</strong><br>
            IP: {{ request()->ip() }}<br>
            Time: {{ now()->format('Y-m-d H:i:s T') }}<br>
            User-Agent: {{ substr(request()->userAgent() ?? 'Unknown', 0, 50) }}
        </div>
    </div>

    <script>
        // Prevent common security bypass attempts
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });

        document.addEventListener('keydown', function(e) {
            // Prevent F12, Ctrl+Shift+I, Ctrl+U
            if (e.key === 'F12' ||
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                (e.ctrlKey && e.key === 'U') ||
                (e.ctrlKey && e.shiftKey && e.key === 'J')) {
                e.preventDefault();
                return false;
            }
        });

        // Prevent drag and drop
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
        });

        // Prevent selection
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
        });
    </script>
</body>
</html>