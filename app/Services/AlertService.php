<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AlertService
{
    /**
     * Cache key prefix for alerts
     */
    protected string $cachePrefix = 'alerts_';

    /**
     * Get notification channels configuration
     */
    protected function getChannels(): array
    {
        return [
            'email' => [
                'enabled' => env('ALERT_EMAIL_ENABLED', false),
                'recipients' => explode(',', env('ALERT_EMAIL_RECIPIENTS', '')),
                'from' => env('ALERT_EMAIL_FROM', 'alerts@company.com'),
            ],
            'slack' => [
                'enabled' => env('SLACK_ALERTS_ENABLED', false),
                'webhook_url' => env('SLACK_WEBHOOK_URL'),
                'channel' => env('SLACK_CHANNEL', '#alerts'),
            ],
            'sms' => [
                'enabled' => env('SMS_ALERTS_ENABLED', false),
                'provider' => env('SMS_PROVIDER', 'twilio'),
                'numbers' => explode(',', env('SMS_NUMBERS', '')),
            ],
            'webhook' => [
                'enabled' => env('WEBHOOK_ALERTS_ENABLED', false),
                'url' => env('WEBHOOK_URL'),
                'secret' => env('WEBHOOK_SECRET'),
            ]
        ];
    }

    /**
     * Get alert severity levels
     */
    protected function getSeverityLevels(): array
    {
        return [
            'info' => 1,
            'warning' => 2,
            'critical' => 3,
            'emergency' => 4,
        ];
    }

    /**
     * Send alert through multiple channels
     */
    public function sendAlert(string $type, string $severity, string $message, array $context = []): bool
    {
        try {
            $alert = [
                'id' => uniqid('alert_'),
                'type' => $type,
                'severity' => $severity,
                'message' => $message,
                'context' => $context,
                'timestamp' => now()->toISOString(),
                'status' => 'sent',
                'channels' => []
            ];

            // Store alert in cache for history
            $this->storeAlert($alert);

            // Send through enabled channels
            foreach ($this->getChannels() as $channel => $config) {
                if ($config['enabled'] ?? false) {
                    $success = false;
                    switch ($channel) {
                        case 'email':
                            $success = $this->sendEmailAlert($alert, $config);
                            break;
                        case 'slack':
                            $success = $this->sendSlackAlert($alert, $config);
                            break;
                        case 'sms':
                            $success = $this->sendSMSAlert($alert, $config);
                            break;
                        case 'webhook':
                            $success = $this->sendWebhookAlert($alert, $config);
                            break;
                    }
                    
                    if ($success) {
                        $alert['channels'][] = $channel;
                    }
                }
            }

            // Log the alert
            $logLevel = $this->getLogLevel($severity);
            Log::channel('alerts')->log($logLevel, "Alert sent: {$type}", $alert);

            return !empty($alert['channels']);

        } catch (\Exception $e) {
            Log::error('Failed to send alert', [
                'type' => $type,
                'severity' => $severity,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get active alerts
     */
    public function getActiveAlerts(): array
    {
        $cacheKey = $this->cachePrefix . 'active';
        return Cache::remember($cacheKey, now()->addMinutes(5), function() {
            // This would typically read from a database or cache
            return [];
        });
    }

    /**
     * Get alert history
     */
    public function getAlertHistory(int $limit = 100): array
    {
        // This would typically read from a database
        return [];
    }

    /**
     * Acknowledge alert
     */
    public function acknowledgeAlert(string $alertId, string $userId): bool
    {
        try {
            $alert = $this->getAlert($alertId);
            if ($alert) {
                $alert['acknowledged'] = true;
                $alert['acknowledged_by'] = $userId;
                $alert['acknowledged_at'] = now()->toISOString();
                
                $this->storeAlert($alert);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to acknowledge alert', [
                'alert_id' => $alertId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Resolve alert
     */
    public function resolveAlert(string $alertId, string $userId, string $resolution = null): bool
    {
        try {
            $alert = $this->getAlert($alertId);
            if ($alert) {
                $alert['status'] = 'resolved';
                $alert['resolved_by'] = $userId;
                $alert['resolved_at'] = now()->toISOString();
                $alert['resolution'] = $resolution;
                
                $this->storeAlert($alert);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to resolve alert', [
                'alert_id' => $alertId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    // Protected methods

    protected function sendEmailAlert(array $alert, array $config): bool
    {
        try {
            if (empty($config['recipients'])) {
                return false;
            }

            $subject = "[{$alert['severity']}] {$alert['type']} Alert";
            $body = $this->formatEmailBody($alert);

            Mail::raw($body, function($mail) use ($config, $subject) {
                $mail->to($config['recipients'])
                     ->from($config['from'])
                     ->subject($subject);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email alert', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function sendSlackAlert(array $alert, array $config): bool
    {
        try {
            if (empty($config['webhook_url'])) {
                return false;
            }

            $payload = [
                'channel' => $config['channel'],
                'username' => 'Alert Bot',
                'text' => $this->formatSlackMessage($alert),
                'attachments' => [[
                    'color' => $this->getSlackColor($alert['severity']),
                    'fields' => [
                        [
                            'title' => 'Type',
                            'value' => $alert['type'],
                            'short' => true
                        ],
                        [
                            'title' => 'Severity',
                            'value' => $alert['severity'],
                            'short' => true
                        ],
                        [
                            'title' => 'Time',
                            'value' => $alert['timestamp'],
                            'short' => true
                        ]
                    ]
                ]]
            ];

            $response = Http::post($config['webhook_url'], $payload);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function sendSMSAlert(array $alert, array $config): bool
    {
        try {
            if (empty($config['numbers']) || empty($config['provider'])) {
                return false;
            }

            $message = $this->formatSMSMessage($alert);

            // This would integrate with actual SMS provider (Twilio, etc.)
            // For now, just log the SMS
            Log::channel('sms_alerts')->info("SMS Alert: {$alert['type']}", [
                'to' => $config['numbers'],
                'message' => $message
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send SMS alert', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function sendWebhookAlert(array $alert, array $config): bool
    {
        try {
            if (empty($config['url'])) {
                return false;
            }

            $payload = [
                'alert' => $alert,
                'signature' => hash_hmac('sha256', json_encode($alert), $config['secret'])
            ];

            $response = Http::post($config['url'], $payload);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send webhook alert', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function storeAlert(array $alert): void
    {
        $cacheKey = $this->cachePrefix . 'alert_' . $alert['id'];
        Cache::put($cacheKey, $alert, now()->addDays(7));
    }

    protected function getAlert(string $alertId): ?array
    {
        $cacheKey = $this->cachePrefix . 'alert_' . $alertId;
        return Cache::get($cacheKey);
    }

    protected function formatEmailBody(array $alert): string
    {
        $body = "Alert Details:\n\n";
        $body .= "Type: {$alert['type']}\n";
        $body .= "Severity: {$alert['severity']}\n";
        $body .= "Message: {$alert['message']}\n";
        $body .= "Time: {$alert['timestamp']}\n\n";
        
        if (!empty($alert['context'])) {
            $body .= "Context:\n";
            foreach ($alert['context'] as $key => $value) {
                $body .= "- {$key}: {$value}\n";
            }
        }
        
        return $body;
    }

    protected function formatSlackMessage(array $alert): string
    {
        return "🚨 *" . strtoupper($alert['severity']) . " " . strtoupper($alert['type']) . "*\n";
    }

    protected function formatSMSMessage(array $alert): string
    {
        return "ALERT: {$alert['type']} - {$alert['message']}";
    }

    protected function getSlackColor(string $severity): string
    {
        return match(strtolower($severity)) {
            'info' => 'good',
            'warning' => 'warning',
            'critical' => 'danger',
            'emergency' => 'danger',
            default => '#439FE0',
        };
    }

    protected function getLogLevel(string $severity): string
    {
        return match(strtolower($severity)) {
            'emergency' => 'emergency',
            'critical' => 'critical',
            'warning' => 'warning',
            'info' => 'info',
            default => 'warning',
        };
    }
}