<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Unavailable - {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .emergency-container {
            background: white;
            border-radius: 12px;
            padding: 3rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .emergency-icon {
            font-size: 4rem;
            color: #dc2626;
            margin-bottom: 1rem;
        }

        .emergency-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
        }

        .emergency-message {
            color: #6b7280;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .shutdown-info {
            background: #fef2f2;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .shutdown-info h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #dc2626;
            margin-bottom: 1rem;
        }

        .shutdown-info p {
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .shutdown-info strong {
            color: #1f2937;
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

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            background: #dc2626;
            border-radius: 50%;
            animation: pulse 2s infinite;
            margin-right: 0.5rem;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .retry-button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }

        .retry-button:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <div class="emergency-container">
        <div class="emergency-icon">🚨</div>
        <h1 class="emergency-title">
            <span class="status-indicator"></span>
            System Unavailable
        </h1>

        <div class="emergency-message">
            <p>{{ $message ?? 'The system is currently undergoing emergency maintenance or has been temporarily disabled.' }}</p>
        </div>

        @if(isset($shutdown_info))
        <div class="shutdown-info">
            <h3>Emergency Shutdown Details</h3>
            <p><strong>Reason:</strong> {{ $shutdown_info['reason'] ?? 'Emergency maintenance' }}</p>
            <p><strong>Initiated:</strong> {{ isset($shutdown_info['initiated_at']) ? \Carbon\Carbon::parse($shutdown_info['initiated_at'])->format('Y-m-d H:i:s T') : 'Unknown' }}</p>
            @if(isset($shutdown_info['delay']) && $shutdown_info['delay'] > 0)
                <p><strong>Delay:</strong> {{ $shutdown_info['delay'] }} seconds</p>
            @endif
        </div>
        @endif

        <div class="contact-info">
            <h4>Need Assistance?</h4>
            <p>This shutdown was initiated remotely for security reasons.</p>
            <p>Please contact system administration for more information.</p>
            <p>Email: emergency@yourcompany.com</p>
            <p>Phone: +1 (555) 123-4567 (Emergency Line)</p>
        </div>

        <button class="retry-button" onclick="window.location.reload()">
            Check Status
        </button>
    </div>

    <script>
        // Auto-retry every 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);

        // Prevent navigation
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = 'The system is currently unavailable. Please try again later.';
        });
    </script>
</body>
</html>