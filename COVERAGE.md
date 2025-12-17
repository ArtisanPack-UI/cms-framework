# Test Coverage for CMS Framework

This document explains how to set up and use test coverage reporting for the CMS Framework package.

## Prerequisites

Test coverage requires a coverage driver to be installed. You have two options:

### Option 1: Xdebug (Recommended for development)
```bash
# Install Xdebug via PECL
pecl install xdebug

# Or via package manager on Ubuntu/Debian
sudo apt-get install php-xdebug

# Or via Homebrew on macOS
brew install php-xdebug
```

### Option 2: PCOV (Recommended for CI/production)
```bash
# Install PCOV via PECL
pecl install pcov

# Or via package manager on Ubuntu/Debian
sudo apt-get install php-pcov
```

## Running Coverage Reports

Once you have a coverage driver installed, you can generate coverage reports using the following commands:

### Basic Coverage Report
```bash
vendor/bin/pest --coverage
```

### Coverage with Minimum Threshold
```bash
vendor/bin/pest --coverage --min=80
```

### Coverage for Specific Test Suite
```bash
vendor/bin/pest tests/Unit --coverage
vendor/bin/pest tests/Feature --coverage
```

## Coverage Output

The coverage configuration is set up to generate the following reports when using `--coverage` with additional options:

### HTML Report
- **Location**: `tests/coverage/html/index.html`
- **Command**: Use PestPHP's built-in coverage options or configure via phpunit.xml
- **Description**: Interactive HTML report showing detailed coverage information

### Text Report
- **Location**: Console output and `tests/coverage/coverage.txt`
- **Description**: Summary coverage information in text format

### Clover XML Report
- **Location**: `tests/coverage/clover.xml`
- **Description**: XML format suitable for CI/CD integration

## Coverage Configuration

The coverage is configured in `phpunit.xml` with the following settings:

### Included Directories
- `src/` - All source code in the main source directory

### Excluded Files/Directories
- `src/Modules/*/routes/` - Route definition files
- `src/Modules/Users/helpers.php` - Helper functions
- `src/Modules/index.php` - Index files

### Coverage Thresholds
- **Low**: 50% (files with coverage below this are highlighted in red)
- **High**: 90% (files with coverage above this are highlighted in green)

## CI/CD Integration

### GitHub Actions Example
```yaml
- name: Run Tests with Coverage
  run: |
    vendor/bin/pest --coverage --min=80
```

### GitLab CI Example
```yaml
test:coverage:
  script:
    - vendor/bin/pest --coverage --min=80
  artifacts:
    reports:
      coverage_report:
        coverage_format: clover
        path: tests/coverage/clover.xml
```

## Troubleshooting

### "No code coverage driver available"
This error occurs when neither Xdebug nor PCOV is installed. Install one of the coverage drivers mentioned in the Prerequisites section.

### Coverage Reports Not Generated
Ensure that:
1. A coverage driver (Xdebug or PCOV) is properly installed and enabled
2. The `tests/coverage/` directory has write permissions
3. You're using the `--coverage` flag with your test commands

### Low Coverage Numbers
- Review the excluded files/directories in `phpunit.xml`
- Ensure your tests are actually exercising the code paths
- Consider adding more comprehensive test cases

## Best Practices

1. **Set Coverage Thresholds**: Use `--min` flag to enforce minimum coverage levels
2. **Regular Monitoring**: Include coverage checks in your CI/CD pipeline
3. **Focus on Critical Code**: Prioritize coverage for business logic and critical components
4. **Exclude Non-Testable Code**: Configure exclusions for generated code, configuration files, etc.

## Available Commands

```bash
# Run all tests with coverage
vendor/bin/pest --coverage

# Run tests with minimum 80% coverage requirement
vendor/bin/pest --coverage --min=80

# Run specific test directory with coverage
vendor/bin/pest tests/Unit --coverage

# Run all tests normally (without coverage)
vendor/bin/pest
```

For more information about PestPHP coverage options, visit: https://pestphp.com/docs/test-coverage
