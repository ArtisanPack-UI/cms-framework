# Performance Testing Guide for CMS Framework

This guide provides comprehensive instructions for using the CMS Framework performance testing suite, interpreting results, and integrating performance monitoring into your development workflow.

## Table of Contents

1. [Overview](#overview)
2. [Installation and Setup](#installation-and-setup)
3. [Running Performance Tests](#running-performance-tests)
4. [Test Types and Benchmarks](#test-types-and-benchmarks)
5. [Interpreting Results](#interpreting-results)
6. [Baseline and Regression Testing](#baseline-and-regression-testing)
7. [CI/CD Integration](#cicd-integration)
8. [Performance Optimization Guidelines](#performance-optimization-guidelines)
9. [Troubleshooting](#troubleshooting)

---

## Overview

The CMS Framework performance testing suite provides comprehensive benchmarking capabilities to ensure your application maintains optimal performance under various load conditions. The suite includes:

- **Database Performance Benchmarks**: Query optimization, relationship loading, large dataset handling
- **API Performance Benchmarks**: Response times, authentication overhead, payload size impact
- **Load Testing**: Concurrent user simulation, high-volume operations, stress testing
- **Memory Profiling**: Memory leak detection, peak usage analysis, garbage collection impact

## Installation and Setup

### Prerequisites

Ensure you have the following packages installed:

```bash
composer require --dev phpbench/phpbench spatie/laravel-ray symfony/stopwatch
```

### Directory Structure

The performance testing suite is organized as follows:

```
tests/Performance/
├── DatabasePerformanceBench.php    # Database operation benchmarks
├── ApiPerformanceBench.php          # API endpoint benchmarks
├── LoadTestingBench.php             # Load and stress testing
├── MemoryProfilingBench.php         # Memory usage profiling
└── bootstrap.php                    # Test environment setup

config/
└── phpbench.json                    # PHPBench configuration

storage/performance/                 # Test results and reports
├── benchmark_results.csv
├── benchmark_report.html
├── memory_metrics.json
├── gc_stats.json
└── memory_leak_analysis.json
```

## Running Performance Tests

### Basic Usage

Run all performance tests with default settings:

```bash
php artisan cms:performance-test
```

### Test Type Selection

Run specific types of performance tests:

```bash
# Database performance tests only
php artisan cms:performance-test --type=database

# API performance tests only
php artisan cms:performance-test --type=api

# Load testing scenarios
php artisan cms:performance-test --type=load

# Memory profiling tests
php artisan cms:performance-test --type=memory
```

### Group Filtering

Run tests for specific functional groups:

```bash
# User-related performance tests
php artisan cms:performance-test --group=user

# Content management tests
php artisan cms:performance-test --group=content

# Authentication tests
php artisan cms:performance-test --group=auth
```

### Output Formats

Generate reports in different formats:

```bash
# HTML report for detailed analysis
php artisan cms:performance-test --format=html

# CSV export for data analysis
php artisan cms:performance-test --format=csv

# JSON format for programmatic processing
php artisan cms:performance-test --format=json
```

### Advanced Options

```bash
# Custom iterations and output directory
php artisan cms:performance-test --iterations=10 --output=/path/to/results

# Establish baseline performance metrics
php artisan cms:performance-test --baseline --type=database

# Compare with existing baseline
php artisan cms:performance-test --compare=/path/to/baseline.json
```

## Test Types and Benchmarks

### Database Performance Benchmarks

**File**: `DatabasePerformanceBench.php`

#### Available Benchmarks:
- `benchUserQuery`: Single user retrieval performance
- `benchUserCreation`: User creation operation timing
- `benchBulkUserQuery`: Multiple user queries efficiency
- `benchUserWithRole`: Relationship loading performance
- `benchContentCreation`: Content creation benchmarks
- `benchContentQuery`: Content filtering and sorting
- `benchContentWithRelationships`: Complex relationship loading
- `benchLargeDatasetQuery`: N+1 query prevention validation
- `benchTaxonomyQuery`: Taxonomy and term operations
- `benchComplexAggregation`: Complex joins and aggregations

#### Example Usage:
```bash
# Run all database benchmarks
php artisan cms:performance-test --type=database

# Focus on user-related operations
php artisan cms:performance-test --type=database --group=user

# Test relationship loading performance
php artisan cms:performance-test --type=database --group=relationship
```

### API Performance Benchmarks

**File**: `ApiPerformanceBench.php`

#### Available Benchmarks:
- `benchAuthLogin`: Authentication endpoint performance
- `benchUsersIndex`: User listing API response times
- `benchUserShow`: Single user retrieval via API
- `benchUserCreate`: User creation API performance
- `benchContentIndex`: Content listing performance
- `benchContentCreateLargePayload`: Large payload handling
- `benchContentUpdate`: Update operation performance
- `benchMultipleApiCalls`: Concurrent request simulation
- `benchAuthenticationOverhead`: Auth middleware impact
- `benchLargeResponsePayload`: Large response handling
- `benchValidationOverhead`: Validation performance impact

#### Example Usage:
```bash
# Test API authentication performance
php artisan cms:performance-test --type=api --group=auth

# Measure content API performance
php artisan cms:performance-test --type=api --group=content

# Test large payload handling
php artisan cms:performance-test --type=api --group=large-payload
```

### Load Testing Scenarios

**File**: `LoadTestingBench.php`

#### Available Benchmarks:
- `benchConcurrentAuthentication`: 50 simultaneous logins
- `benchBulkContentCreation`: High-volume content creation
- `benchConcurrentApiRequests`: Multiple user API simulation
- `benchDatabaseConnectionStress`: Connection pool testing
- `benchReadIntensiveLoad`: High-frequency read operations
- `benchMemoryIntensiveOperations`: Memory-heavy processing
- `benchBurstTrafficPattern`: Traffic spike simulation
- `benchAuthTokenValidationLoad`: Token validation under load

#### Example Usage:
```bash
# Simulate concurrent user load
php artisan cms:performance-test --type=load --group=concurrent

# Test database under stress
php artisan cms:performance-test --type=load --group=database

# Simulate traffic bursts
php artisan cms:performance-test --type=load --group=burst-patterns
```

### Memory Profiling Tests

**File**: `MemoryProfilingBench.php`

#### Available Benchmarks:
- `benchUserCreationMemoryUsage`: Memory usage during user operations
- `benchContentMemoryLeakDetection`: Memory leak detection
- `benchPeakMemoryUsage`: Peak memory consumption analysis
- `benchGarbageCollectionImpact`: GC performance impact
- `benchLargeObjectCollections`: Large dataset memory usage
- `benchStringMemoryUsage`: String operation memory efficiency

#### Example Usage:
```bash
# Detect memory leaks
php artisan cms:performance-test --type=memory --group=leak-detection

# Analyze peak memory usage
php artisan cms:performance-test --type=memory --group=peak-usage

# Test garbage collection impact
php artisan cms:performance-test --type=memory --group=garbage-collection
```

## Interpreting Results

### Understanding Benchmark Metrics

#### Time Metrics:
- **Mean**: Average execution time across iterations
- **Mode**: Most common execution time
- **Best**: Fastest execution time recorded
- **Worst**: Slowest execution time recorded
- **Standard Deviation (stdev)**: Consistency of performance

#### Memory Metrics:
- **Peak Memory**: Maximum memory usage during test
- **Memory Growth**: Net memory increase after operation
- **Memory Freed**: Amount reclaimed by garbage collection

#### Example Output:
```
+----------------------------------+--------+--------+--------+--------+--------+--------+
| benchmark                        | subject| revs   | its    | mem_peak| mean   | stdev  |
+----------------------------------+--------+--------+--------+--------+--------+--------+
| DatabasePerformanceBench         | benchUserQuery | 100 | 5  | 2.5MB   | 1.2ms  | ±0.1ms |
| DatabasePerformanceBench         | benchContentQuery | 50 | 5 | 3.1MB  | 2.8ms  | ±0.3ms |
+----------------------------------+--------+--------+--------+--------+--------+--------+
```

### Performance Thresholds

#### Recommended Response Time Thresholds:
- **Database Queries**: < 10ms for simple queries, < 50ms for complex
- **API Endpoints**: < 200ms for standard operations, < 500ms for complex
- **Memory Usage**: < 50MB growth per 1000 operations
- **Memory Leaks**: 0 detected leaks in production code

### Red Flags to Watch For:
- High standard deviation (inconsistent performance)
- Memory growth without corresponding cleanup
- Response times increasing with dataset size (O(n²) algorithms)
- Excessive garbage collection overhead (> 10% of total time)

## Baseline and Regression Testing

### Establishing Baselines

Create baseline performance metrics for comparison:

```bash
# Establish comprehensive baseline
php artisan cms:performance-test --baseline --type=all --iterations=10

# Database-specific baseline
php artisan cms:performance-test --baseline --type=database --iterations=20
```

Baselines are stored in `storage/performance/baseline_YYYY-MM-DD_HH-MM-SS.json`

### Regression Testing

Compare current performance against established baselines:

```bash
# Compare with specific baseline
php artisan cms:performance-test --compare=storage/performance/baseline_2025-08-26_10-00-00.json

# Compare specific test type
php artisan cms:performance-test --type=api --compare=storage/performance/api_baseline.json
```

### Regression Analysis

The system automatically detects performance regressions:
- **Green**: Performance improved or maintained (< 5% degradation)
- **Yellow**: Minor regression detected (5-15% degradation)
- **Red**: Significant regression detected (> 15% degradation)

## CI/CD Integration

### GitLab CI Integration

Add performance testing to your `.gitlab-ci.yml`:

```yaml
performance_tests:
  stage: test
  script:
    - composer install --no-interaction --prefer-dist --optimize-autoloader
    - php artisan migrate:fresh --force
    - php artisan cms:performance-test --type=all --format=json --output=performance_results
  artifacts:
    reports:
      performance: performance_results/benchmark_*.json
    expire_in: 30 days
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
    - if: $CI_MERGE_REQUEST_TARGET_BRANCH_NAME == "main"
```

### GitHub Actions Integration

```yaml
name: Performance Tests

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  performance:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mbstring, xml, ctype, iconv, intl, pdo_sqlite
        
    - name: Install dependencies
      run: composer install --no-progress --no-interaction --prefer-dist --optimize-autoloader
      
    - name: Run performance tests
      run: php artisan cms:performance-test --type=all --format=json
      
    - name: Upload results
      uses: actions/upload-artifact@v3
      with:
        name: performance-results
        path: storage/performance/
```

### Automated Regression Detection

Set up automated alerts for performance regressions:

```yaml
performance_regression_check:
  stage: test
  script:
    - php artisan cms:performance-test --compare=baselines/production_baseline.json
    - if [ $? -ne 0 ]; then echo "Performance regression detected!"; exit 1; fi
  allow_failure: false
  only:
    - merge_requests
```

## Performance Optimization Guidelines

### Database Optimization

#### Query Optimization:
```php
// Bad: N+1 query problem
$users = User::all();
foreach ($users as $user) {
    echo $user->role->name; // Triggers additional query per user
}

// Good: Eager loading
$users = User::with('role')->get();
foreach ($users as $user) {
    echo $user->role->name; // No additional queries
}
```

#### Index Usage:
```sql
-- Add indexes for frequently queried columns
CREATE INDEX idx_content_status ON content(status);
CREATE INDEX idx_content_published_at ON content(published_at);
CREATE INDEX idx_content_author_status ON content(author_id, status);
```

### API Performance

#### Response Optimization:
```php
// Use API resources for consistent, optimized responses
class ContentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'published_at' => $this->published_at?->toISOString(),
            'author' => new UserResource($this->whenLoaded('author')),
        ];
    }
}
```

#### Caching Strategies:
```php
// Cache expensive operations
public function getPopularContent()
{
    return Cache::remember('popular_content', 3600, function () {
        return Content::withCount('views')
                     ->orderBy('views_count', 'desc')
                     ->limit(10)
                     ->get();
    });
}
```

### Memory Management

#### Efficient Collection Handling:
```php
// Bad: Loading all records into memory
$allUsers = User::all();
$processedUsers = $allUsers->map(function ($user) {
    return $this->processUser($user);
});

// Good: Use chunking for large datasets
User::chunk(100, function ($users) {
    $users->each(function ($user) {
        $this->processUser($user);
    });
});
```

#### Memory-Conscious Queries:
```php
// Use select() to limit loaded columns
$users = User::select('id', 'username', 'email')
             ->where('active', true)
             ->get();

// Use cursor() for memory-efficient iteration
User::cursor()->each(function ($user) {
    $this->processUser($user);
});
```

## Troubleshooting

### Common Issues

#### 1. High Memory Usage
**Symptoms**: Memory usage grows continuously, out-of-memory errors
**Solutions**:
- Use chunking for large datasets
- Implement proper eager loading
- Clear variables after use (`unset()`)
- Force garbage collection in long-running processes

#### 2. Slow Database Queries
**Symptoms**: High database query times, timeouts
**Solutions**:
- Add appropriate database indexes
- Optimize query structure
- Use database query logging to identify slow queries
- Consider database connection pooling

#### 3. API Response Timeouts
**Symptoms**: 504 Gateway Timeout, slow API responses
**Solutions**:
- Implement response caching
- Optimize database queries
- Reduce payload sizes
- Use asynchronous processing for heavy operations

#### 4. Memory Leaks
**Symptoms**: Memory usage grows over time, doesn't decrease
**Solutions**:
- Review event listeners for circular references
- Check for unclosed resources (files, connections)
- Implement proper cleanup in long-running processes
- Use memory profiling to identify leak sources

### Debugging Performance Issues

#### Enable Query Logging:
```php
// In AppServiceProvider or test setup
DB::listen(function ($query) {
    Log::info('Query executed', [
        'sql' => $query->sql,
        'bindings' => $query->bindings,
        'time' => $query->time
    ]);
});
```

#### Memory Profiling:
```php
// Track memory usage in your code
$memoryBefore = memory_get_usage(true);
// ... your code here ...
$memoryAfter = memory_get_usage(true);
$memoryUsed = $memoryAfter - $memoryBefore;

Log::info('Memory usage', [
    'operation' => 'user_creation',
    'memory_used' => $memoryUsed,
    'memory_peak' => memory_get_peak_usage(true)
]);
```

#### Performance Monitoring:
```php
use Symfony\Component\Stopwatch\Stopwatch;

$stopwatch = new Stopwatch();
$stopwatch->start('expensive_operation');

// ... your expensive operation ...

$event = $stopwatch->stop('expensive_operation');
Log::info('Operation completed', [
    'duration' => $event->getDuration(),
    'memory' => $event->getMemory()
]);
```

### Getting Help

If you encounter issues with the performance testing suite:

1. Check the `storage/logs/laravel.log` file for error details
2. Verify PHPBench is properly installed: `./vendor/bin/phpbench --version`
3. Ensure proper database connection in test environment
4. Review test data setup in benchmark classes
5. Check memory limits in `php.ini` (recommend 2G for testing)

For additional support, consult the project documentation or open an issue on the repository.

---

## Best Practices Summary

1. **Run tests regularly**: Integrate into CI/CD pipeline
2. **Establish baselines**: Create reference points for comparison
3. **Monitor trends**: Track performance over time
4. **Test realistic scenarios**: Use production-like data volumes
5. **Profile memory usage**: Detect leaks early
6. **Optimize incrementally**: Make small improvements and measure impact
7. **Document findings**: Keep records of optimizations and their results

The performance testing suite is a powerful tool for maintaining and improving your CMS Framework application's performance. Regular use will help ensure your application scales effectively and provides a great user experience.