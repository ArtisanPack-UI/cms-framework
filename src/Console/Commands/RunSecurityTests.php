<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Run Security Tests Command
 * 
 * This command provides an easy interface to run comprehensive security tests
 * including penetration testing, SQL injection, XSS, CSRF, authentication bypass,
 * and authorization testing scenarios.
 * 
 * @package ArtisanPackUI\CMSFramework\Console\Commands
 */
class RunSecurityTests extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:security-test 
                          {--type=all : Type of security test to run (penetration, sql-injection, xss, csrf, auth-bypass, authorization, all)}
                          {--report : Generate detailed security report}
                          {--output= : Output directory for test results}
                          {--verbose : Show detailed test output}
                          {--stop-on-failure : Stop execution on first test failure}';

    /**
     * The console command description.
     */
    protected $description = 'Run comprehensive security tests for the CMS Framework';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();
        
        $type = $this->option('type');
        $report = $this->option('report');
        $outputDir = $this->option('output') ?? storage_path('security');
        $verbose = $this->option('verbose');
        $stopOnFailure = $this->option('stop-on-failure');

        // Ensure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            $testResults = $this->runSecurityTests($type, $verbose, $stopOnFailure);
            
            if ($report) {
                $this->generateSecurityReport($testResults, $outputDir);
            }

            $this->displayResults($testResults);
            
            return $this->determineExitCode($testResults);

        } catch (\Exception $e) {
            $this->error('❌ Security test execution failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display command header
     */
    protected function displayHeader(): void
    {
        $this->newLine();
        $this->line('🔒 <info>CMS Framework Security Testing Suite</info>');
        $this->line('════════════════════════════════════════');
        $this->newLine();
    }

    /**
     * Run security tests based on type
     */
    protected function runSecurityTests(string $type, bool $verbose, bool $stopOnFailure): array
    {
        $testSuites = $this->getTestSuites($type);
        $results = [];

        foreach ($testSuites as $suiteName => $suiteClass) {
            $this->info("🧪 Running {$suiteName} tests...");
            
            $command = $this->buildTestCommand($suiteClass, $verbose, $stopOnFailure);
            
            if ($verbose) {
                $this->line("   Command: {$command}");
            }

            $process = Process::fromShellCommandline($command);
            $process->setTimeout(300); // 5 minutes per test suite

            $output = '';
            $process->run(function ($type, $buffer) use (&$output, $verbose) {
                $output .= $buffer;
                if ($verbose && $type === Process::OUT) {
                    $this->line($buffer);
                }
            });

            $results[$suiteName] = [
                'exit_code' => $process->getExitCode(),
                'output' => $output,
                'success' => $process->isSuccessful(),
                'execution_time' => $this->parseExecutionTime($output),
                'test_count' => $this->parseTestCount($output),
                'failure_count' => $this->parseFailureCount($output),
                'vulnerabilities' => $this->parseVulnerabilities($output),
            ];

            if ($process->isSuccessful()) {
                $this->line("   ✅ {$suiteName} tests passed");
            } else {
                $this->line("   ❌ {$suiteName} tests failed");
                
                if ($stopOnFailure) {
                    $this->error("Stopping execution due to test failure in {$suiteName}");
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Get test suites based on type
     */
    protected function getTestSuites(string $type): array
    {
        $allSuites = [
            'Penetration Testing' => 'Tests\\Security\\PenetrationTestSuite',
            'SQL Injection Testing' => 'Tests\\Security\\SqlInjectionTestSuite',
            'XSS Vulnerability Testing' => 'Tests\\Security\\XssVulnerabilityTestSuite',
            'CSRF Protection Testing' => 'Tests\\Security\\CsrfProtectionTestSuite',
            'Authentication Bypass Testing' => 'Tests\\Security\\AuthenticationBypassTestSuite',
            'Authorization Testing' => 'Tests\\Security\\AuthorizationTestSuite',
        ];

        return match($type) {
            'penetration' => ['Penetration Testing' => $allSuites['Penetration Testing']],
            'sql-injection' => ['SQL Injection Testing' => $allSuites['SQL Injection Testing']],
            'xss' => ['XSS Vulnerability Testing' => $allSuites['XSS Vulnerability Testing']],
            'csrf' => ['CSRF Protection Testing' => $allSuites['CSRF Protection Testing']],
            'auth-bypass' => ['Authentication Bypass Testing' => $allSuites['Authentication Bypass Testing']],
            'authorization' => ['Authorization Testing' => $allSuites['Authorization Testing']],
            'all' => $allSuites,
            default => $allSuites
        };
    }

    /**
     * Build test command for specific suite
     */
    protected function buildTestCommand(string $suiteClass, bool $verbose, bool $stopOnFailure): string
    {
        $command = './vendor/bin/pest';
        
        // Add specific test class
        $command .= " --class=\"{$suiteClass}\"";
        
        // Add verbosity
        if ($verbose) {
            $command .= ' -v';
        }
        
        // Add stop on failure
        if ($stopOnFailure) {
            $command .= ' --stop-on-failure';
        }
        
        // Add coverage if available
        $command .= ' --coverage-text';
        
        return $command;
    }

    /**
     * Generate detailed security report
     */
    protected function generateSecurityReport(array $testResults, string $outputDir): void
    {
        $this->info('📊 Generating security report...');
        
        $reportData = [
            'timestamp' => now()->toISOString(),
            'framework_version' => '0.1.0',
            'test_results' => $testResults,
            'security_summary' => $this->generateSecuritySummary($testResults),
            'recommendations' => $this->generateSecurityRecommendations($testResults),
            'vulnerability_matrix' => $this->generateVulnerabilityMatrix($testResults),
        ];

        // Generate JSON report
        $jsonReport = json_encode($reportData, JSON_PRETTY_PRINT);
        file_put_contents("{$outputDir}/security_report_" . date('Y-m-d_H-i-s') . '.json', $jsonReport);

        // Generate HTML report
        $htmlReport = $this->generateHtmlReport($reportData);
        file_put_contents("{$outputDir}/security_report_" . date('Y-m-d_H-i-s') . '.html', $htmlReport);

        $this->line('   📄 Reports generated in: ' . $outputDir);
    }

    /**
     * Generate security summary
     */
    protected function generateSecuritySummary(array $testResults): array
    {
        $totalTests = 0;
        $totalFailures = 0;
        $totalVulnerabilities = 0;
        $passedSuites = 0;
        $totalSuites = count($testResults);

        foreach ($testResults as $result) {
            $totalTests += $result['test_count'];
            $totalFailures += $result['failure_count'];
            $totalVulnerabilities += count($result['vulnerabilities']);
            
            if ($result['success']) {
                $passedSuites++;
            }
        }

        return [
            'total_test_suites' => $totalSuites,
            'passed_test_suites' => $passedSuites,
            'failed_test_suites' => $totalSuites - $passedSuites,
            'total_security_tests' => $totalTests,
            'total_failures' => $totalFailures,
            'total_vulnerabilities_found' => $totalVulnerabilities,
            'security_score' => $this->calculateSecurityScore($totalTests, $totalFailures, $totalVulnerabilities),
            'risk_level' => $this->calculateRiskLevel($totalVulnerabilities, $totalFailures),
        ];
    }

    /**
     * Generate security recommendations
     */
    protected function generateSecurityRecommendations(array $testResults): array
    {
        $recommendations = [
            'immediate_actions' => [],
            'security_improvements' => [],
            'best_practices' => [],
        ];

        foreach ($testResults as $suiteName => $result) {
            if (!$result['success']) {
                $recommendations['immediate_actions'][] = "Address failures in {$suiteName}";
            }

            if (!empty($result['vulnerabilities'])) {
                foreach ($result['vulnerabilities'] as $vulnerability) {
                    $recommendations['immediate_actions'][] = "Fix {$vulnerability} vulnerability";
                }
            }
        }

        // Add general security improvements
        $recommendations['security_improvements'] = [
            'Implement Content Security Policy (CSP) headers',
            'Enable HTTP Strict Transport Security (HSTS)',
            'Configure secure cookie settings',
            'Implement proper rate limiting for all endpoints',
            'Add comprehensive input validation and sanitization',
            'Regular security audits and penetration testing',
        ];

        // Add best practices
        $recommendations['best_practices'] = [
            'Keep all dependencies updated to latest secure versions',
            'Implement security logging and monitoring',
            'Regular backup and disaster recovery testing',
            'Staff security training and awareness programs',
            'Implement principle of least privilege',
            'Regular security code reviews',
        ];

        return $recommendations;
    }

    /**
     * Generate vulnerability matrix
     */
    protected function generateVulnerabilityMatrix(array $testResults): array
    {
        $testSuiteKeys = array_keys($testResults);
        
        $matrix = [
            'OWASP_Top_10' => [
                'A01_Broken_Access_Control' => in_array('Authorization Testing', $testSuiteKeys) ? 'TESTED' : 'NOT_TESTED',
                'A02_Cryptographic_Failures' => 'PARTIAL', // Covered by various tests
                'A03_Injection' => in_array('SQL Injection Testing', $testSuiteKeys) ? 'TESTED' : 'NOT_TESTED',
                'A04_Insecure_Design' => 'PARTIAL', // Architecture review needed
                'A05_Security_Misconfiguration' => 'PARTIAL', // Some coverage
                'A06_Vulnerable_Components' => 'NOT_TESTED', // Requires dependency scan
                'A07_Authentication_Failures' => in_array('Authentication Bypass Testing', $testSuiteKeys) ? 'TESTED' : 'NOT_TESTED',
                'A08_Software_Integrity_Failures' => 'NOT_TESTED', // Requires supply chain analysis
                'A09_Logging_Failures' => 'PARTIAL', // Some coverage
                'A10_Server_Side_Request_Forgery' => 'PARTIAL', // Some coverage
            ],
            'Additional_Security_Areas' => [
                'Cross_Site_Scripting' => in_array('XSS Vulnerability Testing', $testSuiteKeys) ? 'TESTED' : 'NOT_TESTED',
                'Cross_Site_Request_Forgery' => in_array('CSRF Protection Testing', $testSuiteKeys) ? 'TESTED' : 'NOT_TESTED',
                'Session_Management' => 'TESTED',
                'Input_Validation' => 'TESTED',
                'Error_Handling' => 'PARTIAL',
                'Data_Protection' => 'PARTIAL',
            ]
        ];

        return $matrix;
    }

    /**
     * Calculate security score
     */
    protected function calculateSecurityScore(int $totalTests, int $totalFailures, int $vulnerabilities): int
    {
        if ($totalTests === 0) {
            return 0;
        }

        $baseScore = (($totalTests - $totalFailures) / $totalTests) * 100;
        $vulnerabilityPenalty = min($vulnerabilities * 5, 50); // Max 50 point penalty
        
        return max(0, intval($baseScore - $vulnerabilityPenalty));
    }

    /**
     * Calculate risk level
     */
    protected function calculateRiskLevel(int $vulnerabilities, int $failures): string
    {
        if ($vulnerabilities > 10 || $failures > 20) {
            return 'HIGH';
        } elseif ($vulnerabilities > 5 || $failures > 10) {
            return 'MEDIUM';
        } elseif ($vulnerabilities > 0 || $failures > 0) {
            return 'LOW';
        }
        
        return 'MINIMAL';
    }

    /**
     * Generate HTML report
     */
    protected function generateHtmlReport(array $reportData): string
    {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Framework Security Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #2563eb; color: white; padding: 20px; border-radius: 8px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; }
        .success { border-left: 4px solid #10b981; }
        .warning { border-left: 4px solid #f59e0b; }
        .error { border-left: 4px solid #ef4444; }
        .vulnerability-matrix { margin: 20px 0; }
        .matrix-table { width: 100%; border-collapse: collapse; }
        .matrix-table th, .matrix-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .tested { background-color: #dcfce7; }
        .partial { background-color: #fef3c7; }
        .not-tested { background-color: #fee2e2; }
    </style>
</head>
<body>';

        $html .= '<div class="header">';
        $html .= '<h1>🔒 CMS Framework Security Report</h1>';
        $html .= '<p>Generated: ' . $reportData['timestamp'] . '</p>';
        $html .= '<p>Framework Version: ' . $reportData['framework_version'] . '</p>';
        $html .= '</div>';

        $summary = $reportData['security_summary'];
        $html .= '<div class="summary">';
        $html .= '<div class="card ' . ($summary['security_score'] >= 80 ? 'success' : ($summary['security_score'] >= 60 ? 'warning' : 'error')) . '">';
        $html .= '<h3>Security Score</h3>';
        $html .= '<h2>' . $summary['security_score'] . '/100</h2>';
        $html .= '<p>Risk Level: ' . $summary['risk_level'] . '</p>';
        $html .= '</div>';

        $html .= '<div class="card">';
        $html .= '<h3>Test Suites</h3>';
        $html .= '<p>Passed: ' . $summary['passed_test_suites'] . '/' . $summary['total_test_suites'] . '</p>';
        $html .= '</div>';

        $html .= '<div class="card">';
        $html .= '<h3>Security Tests</h3>';
        $html .= '<p>Total: ' . $summary['total_security_tests'] . '</p>';
        $html .= '<p>Failures: ' . $summary['total_failures'] . '</p>';
        $html .= '</div>';

        $html .= '<div class="card ' . ($summary['total_vulnerabilities_found'] > 0 ? 'error' : 'success') . '">';
        $html .= '<h3>Vulnerabilities</h3>';
        $html .= '<h2>' . $summary['total_vulnerabilities_found'] . '</h2>';
        $html .= '</div>';
        $html .= '</div>';

        // Add vulnerability matrix
        $html .= '<div class="vulnerability-matrix">';
        $html .= '<h2>Security Coverage Matrix</h2>';
        $html .= '<table class="matrix-table">';
        $html .= '<tr><th>Security Area</th><th>Status</th></tr>';
        
        foreach ($reportData['vulnerability_matrix']['OWASP_Top_10'] as $area => $status) {
            $statusClass = strtolower(str_replace('_', '-', $status));
            $html .= '<tr><td>' . str_replace('_', ' ', $area) . '</td><td class="' . $statusClass . '">' . $status . '</td></tr>';
        }
        
        $html .= '</table>';
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Display test results
     */
    protected function displayResults(array $testResults): void
    {
        $this->newLine();
        $this->info('📋 Security Test Results Summary:');
        $this->line('═══════════════════════════════════');
        
        $totalTests = 0;
        $totalFailures = 0;
        $totalVulnerabilities = 0;

        foreach ($testResults as $suiteName => $result) {
            $status = $result['success'] ? '✅' : '❌';
            $this->line("   {$status} {$suiteName}");
            $this->line("      Tests: {$result['test_count']}, Failures: {$result['failure_count']}");
            
            if (!empty($result['vulnerabilities'])) {
                $this->line("      Vulnerabilities: " . implode(', ', $result['vulnerabilities']));
            }
            
            $totalTests += $result['test_count'];
            $totalFailures += $result['failure_count'];
            $totalVulnerabilities += count($result['vulnerabilities']);
        }

        $this->newLine();
        $this->info("📊 Overall Statistics:");
        $this->line("   Total Security Tests: {$totalTests}");
        $this->line("   Total Failures: {$totalFailures}");
        $this->line("   Total Vulnerabilities Found: {$totalVulnerabilities}");
        
        $securityScore = $this->calculateSecurityScore($totalTests, $totalFailures, $totalVulnerabilities);
        $riskLevel = $this->calculateRiskLevel($totalVulnerabilities, $totalFailures);
        
        $this->line("   Security Score: {$securityScore}/100");
        $this->line("   Risk Level: {$riskLevel}");
    }

    /**
     * Determine exit code based on results
     */
    protected function determineExitCode(array $testResults): int
    {
        foreach ($testResults as $result) {
            if (!$result['success']) {
                return Command::FAILURE;
            }
        }
        
        return Command::SUCCESS;
    }

    /**
     * Parse execution time from test output
     */
    protected function parseExecutionTime(string $output): float
    {
        if (preg_match('/Time: ([0-9.]+)/', $output, $matches)) {
            return floatval($matches[1]);
        }
        return 0.0;
    }

    /**
     * Parse test count from test output
     */
    protected function parseTestCount(string $output): int
    {
        if (preg_match('/Tests: (\d+)/', $output, $matches)) {
            return intval($matches[1]);
        }
        return 0;
    }

    /**
     * Parse failure count from test output
     */
    protected function parseFailureCount(string $output): int
    {
        if (preg_match('/Failures: (\d+)/', $output, $matches)) {
            return intval($matches[1]);
        }
        return 0;
    }

    /**
     * Parse vulnerabilities from test output
     */
    protected function parseVulnerabilities(string $output): array
    {
        $vulnerabilities = [];
        
        // Look for common vulnerability indicators in test output
        $patterns = [
            'SQL Injection',
            'XSS',
            'CSRF',
            'Authentication Bypass',
            'Authorization Bypass',
            'Session Fixation',
            'Privilege Escalation',
        ];

        foreach ($patterns as $pattern) {
            if (stripos($output, $pattern . ' vulnerability') !== false) {
                $vulnerabilities[] = $pattern;
            }
        }

        return $vulnerabilities;
    }
}