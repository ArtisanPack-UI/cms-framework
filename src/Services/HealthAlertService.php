<?php

namespace ArtisanPackUI\CMSFramework\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Exception;

/**
 * Health Alert Service
 *
 * Handles automated alerts for system health issues, performance threshold
 * violations, and dependency failures in the ArtisanPack UI CMS Framework.
 *
 * @package    ArtisanPackUI\CMSFramework\Services
 * @since      1.4.0
 */
class HealthAlertService
{
    /**
     * Alert severity levels.
     */
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_INFO = 'info';

    /**
     * Send health alert based on severity and type.
     *
     * @since 1.4.0
     *
     * @param string $severity Alert severity (critical, warning, info)
     * @param string $service Service name (database, cache, queue, etc.)
     * @param string $message Alert message
     * @param array $details Additional alert details
     * @param array $context Additional context data
     * @return bool Whether alert was sent successfully
     */
    public function sendAlert(
        string $severity,
        string $service,
        string $message,
        array $details = [],
        array $context = []
    ): bool {
        try {
            // Check if we should throttle this alert
            if ($this->shouldThrottleAlert($service, $severity, $message)) {
                Log::debug("Alert throttled for {$service}: {$message}");
                return false;
            }

            $alertData = [
                'severity' => $severity,
                'service' => $service,
                'message' => $message,
                'details' => $details,
                'context' => $context,
                'timestamp' => now()->toISOString(),
                'hostname' => gethostname() ?: 'unknown',
                'environment' => config('app.env'),
            ];

            // Record the alert
            $this->recordAlert($alertData);

            // Send notifications based on configuration
            $sent = false;
            
            if ($this->shouldSendEmailAlert($severity)) {
                $sent = $this->sendEmailAlert($alertData) || $sent;
            }
            
            if ($this->shouldSendWebhookAlert($severity)) {
                $sent = $this->sendWebhookAlert($alertData) || $sent;
            }
            
            if ($this->shouldSendSlackAlert($severity)) {
                $sent = $this->sendSlackAlert($alertData) || $sent;
            }

            // Always log the alert
            $this->logAlert($alertData);
            
            // Update throttling cache
            $this->updateAlertThrottling($service, $severity, $message);
            
            return $sent;
            
        } catch (Exception $e) {
            Log::error('Failed to send health alert', [
                'service' => $service,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Send critical alert for immediate attention.
     *
     * @since 1.4.0
     *
     * @param string $service Service name
     * @param string $message Alert message
     * @param array $details Additional details
     * @return bool Whether alert was sent
     */
    public function sendCriticalAlert(string $service, string $message, array $details = []): bool
    {
        return $this->sendAlert(self::SEVERITY_CRITICAL, $service, $message, $details);
    }

    /**
     * Send warning alert for non-critical issues.
     *
     * @since 1.4.0
     *
     * @param string $service Service name
     * @param string $message Alert message
     * @param array $details Additional details
     * @return bool Whether alert was sent
     */
    public function sendWarningAlert(string $service, string $message, array $details = []): bool
    {
        return $this->sendAlert(self::SEVERITY_WARNING, $service, $message, $details);
    }

    /**
     * Send informational alert.
     *
     * @since 1.4.0
     *
     * @param string $service Service name
     * @param string $message Alert message
     * @param array $details Additional details
     * @return bool Whether alert was sent
     */
    public function sendInfoAlert(string $service, string $message, array $details = []): bool
    {
        return $this->sendAlert(self::SEVERITY_INFO, $service, $message, $details);
    }

    /**
     * Check if alert should be throttled.
     *
     * @since 1.4.0
     *
     * @param string $service Service name
     * @param string $severity Alert severity
     * @param string $message Alert message
     * @return bool Whether to throttle the alert
     */
    protected function shouldThrottleAlert(string $service, string $severity, string $message): bool
    {
        $throttleKey = $this->getThrottleKey($service, $severity, $message);
        $throttleWindow = $this->getThrottleWindow($severity);
        
        return Cache::has($throttleKey) && $throttleWindow > 0;
    }

    /**
     * Update alert throttling cache.
     *
     * @since 1.4.0
     *
     * @param string $service Service name
     * @param string $severity Alert severity
     * @param string $message Alert message
     * @return void
     */
    protected function updateAlertThrottling(string $service, string $severity, string $message): void
    {
        $throttleKey = $this->getThrottleKey($service, $severity, $message);
        $throttleWindow = $this->getThrottleWindow($severity);
        
        if ($throttleWindow > 0) {
            Cache::put($throttleKey, now()->toISOString(), now()->addMinutes($throttleWindow));
        }
    }

    /**
     * Get throttle cache key.
     *
     * @since 1.4.0
     *
     * @param string $service Service name
     * @param string $severity Alert severity
     * @param string $message Alert message
     * @return string Throttle key
     */
    protected function getThrottleKey(string $service, string $severity, string $message): string
    {
        return 'health_alert_throttle:' . md5($service . $severity . $message);
    }

    /**
     * Get throttle window in minutes for severity level.
     *
     * @since 1.4.0
     *
     * @param string $severity Alert severity
     * @return int Throttle window in minutes
     */
    protected function getThrottleWindow(string $severity): int
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => config('health.alerts.throttle.critical', 5),
            self::SEVERITY_WARNING => config('health.alerts.throttle.warning', 15),
            self::SEVERITY_INFO => config('health.alerts.throttle.info', 30),
            default => 15,
        };
    }

    /**
     * Record alert in storage/logs.
     *
     * @since 1.4.0
     *
     * @param array $alertData Alert data
     * @return void
     */
    protected function recordAlert(array $alertData): void
    {
        try {
            // Store in cache for recent alerts display
            $recentAlerts = Cache::get('health_alerts_recent', []);
            array_unshift($recentAlerts, $alertData);
            
            // Keep only last 50 alerts
            $recentAlerts = array_slice($recentAlerts, 0, 50);
            
            Cache::put('health_alerts_recent', $recentAlerts, now()->addHours(24));
            
            // Increment alert counters
            $counterKey = 'health_alerts_count_' . $alertData['severity'];
            Cache::increment($counterKey, 1);
            Cache::expire($counterKey, 3600); // 1 hour expiry
            
        } catch (Exception $e) {
            Log::error('Failed to record health alert', [
                'error' => $e->getMessage(),
                'alert' => $alertData,
            ]);
        }
    }

    /**
     * Log alert to Laravel logs.
     *
     * @since 1.4.0
     *
     * @param array $alertData Alert data
     * @return void
     */
    protected function logAlert(array $alertData): void
    {
        $message = "Health Alert [{$alertData['severity']}] {$alertData['service']}: {$alertData['message']}";
        
        match ($alertData['severity']) {
            self::SEVERITY_CRITICAL => Log::critical($message, $alertData),
            self::SEVERITY_WARNING => Log::warning($message, $alertData),
            self::SEVERITY_INFO => Log::info($message, $alertData),
            default => Log::notice($message, $alertData),
        };
    }

    /**
     * Check if email alerts should be sent for severity level.
     *
     * @since 1.4.0
     *
     * @param string $severity Alert severity
     * @return bool Whether to send email
     */
    protected function shouldSendEmailAlert(string $severity): bool
    {
        if (!config('health.alerts.email.enabled', false)) {
            return false;
        }
        
        $allowedSeverities = config('health.alerts.email.severities', ['critical']);
        return in_array($severity, $allowedSeverities);
    }

    /**
     * Send email alert.
     *
     * @since 1.4.0
     *
     * @param array $alertData Alert data
     * @return bool Whether email was sent successfully
     */
    protected function sendEmailAlert(array $alertData): bool
    {
        try {
            $recipients = config('health.alerts.email.recipients', []);
            
            if (empty($recipients)) {
                Log::warning('No email recipients configured for health alerts');
                return false;
            }
            
            $subject = "[{$alertData['environment']}] Health Alert: {$alertData['service']} - {$alertData['severity']}";
            
            foreach ($recipients as $recipient) {
                Mail::raw(
                    $this->formatEmailBody($alertData),
                    function ($message) use ($recipient, $subject) {
                        $message->to($recipient)
                               ->subject($subject);
                    }
                );
            }
            
            Log::info('Health alert email sent', [
                'service' => $alertData['service'],
                'severity' => $alertData['severity'],
                'recipients' => count($recipients),
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Failed to send health alert email', [
                'error' => $e->getMessage(),
                'alert' => $alertData,
            ]);
            
            return false;
        }
    }

    /**
     * Check if webhook alerts should be sent for severity level.
     *
     * @since 1.4.0
     *
     * @param string $severity Alert severity
     * @return bool Whether to send webhook
     */
    protected function shouldSendWebhookAlert(string $severity): bool
    {
        if (!config('health.alerts.webhook.enabled', false)) {
            return false;
        }
        
        $allowedSeverities = config('health.alerts.webhook.severities', ['critical', 'warning']);
        return in_array($severity, $allowedSeverities);
    }

    /**
     * Send webhook alert.
     *
     * @since 1.4.0
     *
     * @param array $alertData Alert data
     * @return bool Whether webhook was sent successfully
     */
    protected function sendWebhookAlert(array $alertData): bool
    {
        try {
            $webhookUrl = config('health.alerts.webhook.url');
            
            if (!$webhookUrl) {
                return false;
            }
            
            $response = Http::timeout(10)
                ->post($webhookUrl, [
                    'text' => $this->formatWebhookMessage($alertData),
                    'alert' => $alertData,
                ]);
            
            if ($response->successful()) {
                Log::info('Health alert webhook sent', [
                    'service' => $alertData['service'],
                    'severity' => $alertData['severity'],
                    'url' => $webhookUrl,
                ]);
                return true;
            } else {
                Log::warning('Health alert webhook failed', [
                    'status' => $response->status(),
                    'url' => $webhookUrl,
                    'alert' => $alertData,
                ]);
                return false;
            }
            
        } catch (Exception $e) {
            Log::error('Failed to send health alert webhook', [
                'error' => $e->getMessage(),
                'alert' => $alertData,
            ]);
            
            return false;
        }
    }

    /**
     * Check if Slack alerts should be sent for severity level.
     *
     * @since 1.4.0
     *
     * @param string $severity Alert severity
     * @return bool Whether to send Slack alert
     */
    protected function shouldSendSlackAlert(string $severity): bool
    {
        if (!config('health.alerts.slack.enabled', false)) {
            return false;
        }
        
        $allowedSeverities = config('health.alerts.slack.severities', ['critical', 'warning']);
        return in_array($severity, $allowedSeverities);
    }

    /**
     * Send Slack alert.
     *
     * @since 1.4.0
     *
     * @param array $alertData Alert data
     * @return bool Whether Slack alert was sent successfully
     */
    protected function sendSlackAlert(array $alertData): bool
    {
        try {
            $webhookUrl = config('health.alerts.slack.webhook_url');
            
            if (!$webhookUrl) {
                return false;
            }
            
            $color = match ($alertData['severity']) {
                self::SEVERITY_CRITICAL => 'danger',
                self::SEVERITY_WARNING => 'warning',
                default => 'good',
            };
            
            $payload = [
                'text' => "Health Alert: {$alertData['service']}",
                'attachments' => [
                    [
                        'color' => $color,
                        'fields' => [
                            [
                                'title' => 'Service',
                                'value' => $alertData['service'],
                                'short' => true,
                            ],
                            [
                                'title' => 'Severity',
                                'value' => strtoupper($alertData['severity']),
                                'short' => true,
                            ],
                            [
                                'title' => 'Message',
                                'value' => $alertData['message'],
                                'short' => false,
                            ],
                            [
                                'title' => 'Environment',
                                'value' => $alertData['environment'],
                                'short' => true,
                            ],
                            [
                                'title' => 'Timestamp',
                                'value' => $alertData['timestamp'],
                                'short' => true,
                            ],
                        ],
                    ],
                ],
            ];
            
            $response = Http::timeout(10)->post($webhookUrl, $payload);
            
            if ($response->successful()) {
                Log::info('Health alert Slack message sent', [
                    'service' => $alertData['service'],
                    'severity' => $alertData['severity'],
                ]);
                return true;
            } else {
                Log::warning('Health alert Slack message failed', [
                    'status' => $response->status(),
                    'alert' => $alertData,
                ]);
                return false;
            }
            
        } catch (Exception $e) {
            Log::error('Failed to send health alert Slack message', [
                'error' => $e->getMessage(),
                'alert' => $alertData,
            ]);
            
            return false;
        }
    }

    /**
     * Format email body for alert.
     *
     * @since 1.4.0
     *
     * @param array $alertData Alert data
     * @return string Email body
     */
    protected function formatEmailBody(array $alertData): string
    {
        $body = "Health Alert Notification\n";
        $body .= "========================\n\n";
        $body .= "Service: {$alertData['service']}\n";
        $body .= "Severity: " . strtoupper($alertData['severity']) . "\n";
        $body .= "Message: {$alertData['message']}\n";
        $body .= "Environment: {$alertData['environment']}\n";
        $body .= "Hostname: {$alertData['hostname']}\n";
        $body .= "Timestamp: {$alertData['timestamp']}\n\n";
        
        if (!empty($alertData['details'])) {
            $body .= "Details:\n";
            $body .= "--------\n";
            foreach ($alertData['details'] as $key => $value) {
                $body .= "{$key}: {$value}\n";
            }
            $body .= "\n";
        }
        
        if (!empty($alertData['context'])) {
            $body .= "Additional Context:\n";
            $body .= "------------------\n";
            $body .= json_encode($alertData['context'], JSON_PRETTY_PRINT) . "\n";
        }
        
        return $body;
    }

    /**
     * Format webhook message for alert.
     *
     * @since 1.4.0
     *
     * @param array $alertData Alert data
     * @return string Webhook message
     */
    protected function formatWebhookMessage(array $alertData): string
    {
        return "[{$alertData['environment']}] {$alertData['severity']}: {$alertData['service']} - {$alertData['message']}";
    }

    /**
     * Get recent alerts from cache.
     *
     * @since 1.4.0
     *
     * @param int $limit Maximum number of alerts to return
     * @return array Recent alerts
     */
    public function getRecentAlerts(int $limit = 20): array
    {
        $alerts = Cache::get('health_alerts_recent', []);
        return array_slice($alerts, 0, $limit);
    }

    /**
     * Get alert statistics.
     *
     * @since 1.4.0
     *
     * @return array Alert statistics
     */
    public function getAlertStats(): array
    {
        return [
            'critical_count' => Cache::get('health_alerts_count_critical', 0),
            'warning_count' => Cache::get('health_alerts_count_warning', 0),
            'info_count' => Cache::get('health_alerts_count_info', 0),
            'recent_alerts' => count($this->getRecentAlerts()),
        ];
    }

    /**
     * Clear alert history and counters.
     *
     * @since 1.4.0
     *
     * @return bool Whether clearing was successful
     */
    public function clearAlertHistory(): bool
    {
        try {
            Cache::forget('health_alerts_recent');
            Cache::forget('health_alerts_count_critical');
            Cache::forget('health_alerts_count_warning');
            Cache::forget('health_alerts_count_info');
            
            Log::info('Health alert history cleared');
            return true;
            
        } catch (Exception $e) {
            Log::error('Failed to clear health alert history', [
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
}