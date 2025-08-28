# Application Performance Monitoring (APM) - Architecture Design

## Overview
This document outlines the design for implementing comprehensive application performance monitoring and alerting in the ArtisanPack UI CMS Framework. The system will support multiple APM providers (New Relic, DataDog, etc.) and provide custom metrics collection, performance dashboards, automated alerting, error tracking, and user experience monitoring.

## Architecture Components

### 1. APM Provider System

#### Provider Interface
The system uses a provider pattern to support multiple APM services:

```php
interface APMProviderInterface
{
    public function trackMetric(string $name, float $value, array $tags = []): void;
    public function startTransaction(string $name): string;
    public function endTransaction(string $transactionId): void;
    public function recordError(\Throwable $exception, array $context = []): void;
    public function recordCustomEvent(string $eventType, array $attributes): void;
    public function isEnabled(): bool;
    public function getProviderName(): string;
}
```

#### Supported Providers
- **New Relic**: Full APM integration with transaction tracing
- **DataDog**: Metrics and APM monitoring
- **Custom Internal**: Database-based metrics for self-hosted solutions
- **Sentry**: Error tracking and performance monitoring
- **Null Provider**: No-op provider for disabled monitoring

### 2. Custom Metrics Collection System

#### Metrics Storage
```sql
CREATE TABLE performance_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(255) NOT NULL,
    metric_value DECIMAL(10,4) NOT NULL,
    metric_unit VARCHAR(50) DEFAULT 'ms',
    tags JSON,
    recorded_at TIMESTAMP NOT NULL,
    INDEX idx_metric_name (metric_name),
    INDEX idx_recorded_at (recorded_at)
);

CREATE TABLE performance_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(255) UNIQUE NOT NULL,
    transaction_name VARCHAR(255) NOT NULL,
    duration_ms DECIMAL(8,2),
    memory_usage_mb DECIMAL(8,2),
    db_query_count INT DEFAULT 0,
    db_query_time_ms DECIMAL(8,2) DEFAULT 0,
    http_status_code INT,
    user_id BIGINT UNSIGNED NULL,
    request_path VARCHAR(500),
    request_method VARCHAR(10),
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    metadata JSON,
    INDEX idx_transaction_name (transaction_name),
    INDEX idx_started_at (started_at),
    INDEX idx_duration (duration_ms),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

#### Error Tracking Storage
```sql
CREATE TABLE error_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    error_hash VARCHAR(64) NOT NULL, -- Hash of error signature
    exception_class VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    file VARCHAR(500),
    line INT,
    stack_trace TEXT,
    context JSON,
    occurrence_count INT DEFAULT 1,
    first_seen_at TIMESTAMP NOT NULL,
    last_seen_at TIMESTAMP NOT NULL,
    resolved_at TIMESTAMP NULL,
    severity_level VARCHAR(20) DEFAULT 'error',
    user_id BIGINT UNSIGNED NULL,
    request_url VARCHAR(500),
    INDEX idx_error_hash (error_hash),
    INDEX idx_exception_class (exception_class),
    INDEX idx_first_seen (first_seen_at),
    INDEX idx_resolved (resolved_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

### 3. Performance Dashboards

#### Dashboard Widgets
- Response Time Trends
- Memory Usage Graphs  
- Database Query Performance
- Error Rate Monitoring
- Throughput Metrics
- User Experience Scores

#### Real-Time Metrics
- Active Users
- Current Response Times
- System Health Status
- Alert Status

### 4. Automated Alerting System

#### Alert Rules
```php
interface AlertRuleInterface
{
    public function evaluate(array $metrics): bool;
    public function getThreshold(): float;
    public function getMetricName(): string;
    public function getSeverityLevel(): string;
}
```

#### Notification Channels
- Email notifications
- Slack webhooks
- Discord webhooks
- SMS notifications (via third-party)
- Custom webhook endpoints

#### Alert Conditions
- Response time threshold exceeded
- Error rate threshold exceeded
- Memory usage threshold exceeded
- Database query time threshold exceeded
- Custom metric thresholds

### 5. User Experience Monitoring (UX)

#### Real User Monitoring (RUM)
- Page load times
- Time to first byte (TTFB)
- Time to interactive (TTI)
- Core Web Vitals (LCP, FID, CLS)
- API response times

#### Client-Side Integration
JavaScript snippet for browser monitoring:
```javascript
window.CmsApm = {
    trackPageLoad: function(metrics) { ... },
    trackApiCall: function(url, duration, status) { ... },
    trackCustomEvent: function(eventName, data) { ... }
};
```

### 6. Configuration Management

#### APM Configuration in cms.php
```php
'apm' => [
    'enabled' => env('CMS_APM_ENABLED', true),
    'default_provider' => env('CMS_APM_PROVIDER', 'internal'),
    
    'providers' => [
        'newrelic' => [
            'enabled' => env('NEWRELIC_ENABLED', false),
            'app_name' => env('NEWRELIC_APP_NAME', config('app.name')),
            'license_key' => env('NEWRELIC_LICENSE_KEY'),
        ],
        'datadog' => [
            'enabled' => env('DATADOG_ENABLED', false),
            'api_key' => env('DATADOG_API_KEY'),
            'app_key' => env('DATADOG_APP_KEY'),
            'service' => env('DATADOG_SERVICE', config('app.name')),
            'env' => env('DATADOG_ENV', env('APP_ENV')),
        ],
        'sentry' => [
            'enabled' => env('SENTRY_ENABLED', false),
            'dsn' => env('SENTRY_LARAVEL_DSN'),
            'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
        ],
        'internal' => [
            'enabled' => env('CMS_INTERNAL_APM_ENABLED', true),
            'retention_days' => env('CMS_APM_RETENTION_DAYS', 90),
            'sample_rate' => env('CMS_APM_SAMPLE_RATE', 1.0),
        ],
    ],
    
    'metrics' => [
        'enabled' => env('CMS_METRICS_ENABLED', true),
        'sample_rate' => env('CMS_METRICS_SAMPLE_RATE', 1.0),
        'batch_size' => env('CMS_METRICS_BATCH_SIZE', 100),
        'flush_interval' => env('CMS_METRICS_FLUSH_INTERVAL', 60), // seconds
    ],
    
    'alerts' => [
        'enabled' => env('CMS_ALERTS_ENABLED', true),
        'response_time_threshold' => env('CMS_ALERT_RESPONSE_TIME', 2000), // ms
        'error_rate_threshold' => env('CMS_ALERT_ERROR_RATE', 5.0), // percentage
        'memory_usage_threshold' => env('CMS_ALERT_MEMORY_USAGE', 80), // percentage
        'notification_channels' => ['email', 'slack'],
    ],
    
    'error_tracking' => [
        'enabled' => env('CMS_ERROR_TRACKING_ENABLED', true),
        'capture_unhandled' => env('CMS_CAPTURE_UNHANDLED_ERRORS', true),
        'capture_handled' => env('CMS_CAPTURE_HANDLED_ERRORS', false),
        'ignore_exceptions' => [
            \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
        ],
        'context_lines' => env('CMS_ERROR_CONTEXT_LINES', 5),
    ],
    
    'user_experience' => [
        'enabled' => env('CMS_UX_MONITORING_ENABLED', true),
        'track_page_loads' => env('CMS_TRACK_PAGE_LOADS', true),
        'track_api_calls' => env('CMS_TRACK_API_CALLS', true),
        'track_user_interactions' => env('CMS_TRACK_USER_INTERACTIONS', false),
        'sample_rate' => env('CMS_UX_SAMPLE_RATE', 0.1),
    ],
    
    'performance' => [
        'slow_query_threshold' => env('CMS_SLOW_QUERY_THRESHOLD', 1000), // ms
        'memory_limit_warning' => env('CMS_MEMORY_WARNING_THRESHOLD', 128), // MB
        'track_queue_jobs' => env('CMS_TRACK_QUEUE_JOBS', true),
        'track_console_commands' => env('CMS_TRACK_CONSOLE_COMMANDS', false),
    ],
]
```

## Implementation Strategy

### Phase 1: Core Infrastructure
1. Create APM provider interfaces and base classes
2. Implement internal metrics collection system
3. Build performance measurement middleware
4. Add basic configuration management

### Phase 2: External Provider Integration
1. New Relic integration
2. DataDog integration  
3. Sentry integration
4. Provider factory and management system

### Phase 3: Dashboards and UI
1. Performance metrics dashboard
2. Error tracking dashboard
3. Alert management interface
4. Real-time monitoring widgets

### Phase 4: Advanced Features
1. Automated alerting system
2. User experience monitoring
3. Advanced analytics and reporting
4. Performance optimization recommendations

## Technical Considerations

### Performance Impact
- Minimal overhead through sampling and async processing
- Configurable sample rates for different environments
- Batch processing for metric collection
- Memory-efficient data structures

### Scalability
- Database partitioning for large datasets
- Metric aggregation for long-term storage
- Configurable retention policies
- Support for multiple APM providers

### Security
- Sensitive data filtering in error reports
- Secure credential management
- Rate limiting for APM endpoints
- Access control for monitoring dashboards

### Monitoring Best Practices
- Health checks for monitoring system itself
- Graceful degradation when APM is unavailable
- Comprehensive logging of APM operations
- Regular cleanup and maintenance tasks