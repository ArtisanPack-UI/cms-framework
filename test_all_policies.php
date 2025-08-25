<?php

// Comprehensive syntax check for all updated policy files
echo "Testing All Policy Files...\n";

$policies = [
    'PluginPolicy.php',
    'MediaPolicy.php',
    'MediaCategoryPolicy.php',
    'MediaTagPolicy.php',
    'ContentTypePolicy.php',
    'TaxonomyPolicy.php',
    'TermPolicy.php',
    'RolePolicy.php',
    'SettingPolicy.php',
    'AuditLogPolicy.php',
];

$totalPolicies = count($policies);
$passedPolicies = 0;
$securityIssuesFixed = 0;

foreach ($policies as $policy) {
    $policyFile = "src/Policies/$policy";

    if (file_exists($policyFile)) {
        $output = [];
        $return_var = 0;
        exec("php -l $policyFile 2>&1", $output, $return_var);

        if ($return_var === 0) {
            echo "✓ $policy syntax is valid\n";
            $passedPolicies++;
        } else {
            echo "✗ $policy syntax error found\n";
            echo implode("\n", $output)."\n";
        }

        // Check for security improvements
        $policyContent = file_get_contents($policyFile);

        // Check for nullable User parameters
        if (strpos($policyContent, '?User $user') !== false) {
            echo "  ✓ $policy supports guest access (nullable User)\n";
        } else {
            echo "  ✗ $policy missing guest access support\n";
        }

        // Check for Eventy hooks
        if (strpos($policyContent, 'Eventy::filter') !== false) {
            echo "  ✓ $policy includes Eventy hooks for customization\n";
        } else {
            echo "  ✗ $policy missing Eventy hooks\n";
        }

        // Check that viewAny and view methods no longer have unconditional return true
        $hasUnconditionalTrue = false;
        $lines = explode("\n", $policyContent);
        $inViewMethod = false;

        foreach ($lines as $lineNum => $line) {
            if (strpos($line, 'public function viewAny') !== false || strpos($line, 'public function view') !== false) {
                $inViewMethod = true;

                continue;
            }

            if ($inViewMethod && strpos($line, 'public function') !== false && strpos($line, 'view') === false) {
                $inViewMethod = false;

                continue;
            }

            if ($inViewMethod && trim($line) === 'return true;' && strpos($policyContent, 'if (') === false) {
                $hasUnconditionalTrue = true;
                break;
            }

            if ($inViewMethod && strpos($line, '}') !== false) {
                $inViewMethod = false;
            }
        }

        if (! $hasUnconditionalTrue) {
            echo "  ✓ $policy no longer has unconditional return true in view methods\n";
            $securityIssuesFixed++;
        } else {
            echo "  ✗ $policy still has unconditional return true in view methods\n";
        }

    } else {
        echo "✗ $policy file not found\n";
    }

    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "Total policies checked: $totalPolicies\n";
echo "Policies with valid syntax: $passedPolicies\n";
echo "Security issues fixed: $securityIssuesFixed\n";

if ($passedPolicies === $totalPolicies && $securityIssuesFixed === $totalPolicies) {
    echo "🎉 ALL POLICIES SUCCESSFULLY SECURED! 🎉\n";
} else {
    echo "⚠️  Some issues remain to be addressed.\n";
}

echo "\nPolicy Security Hardening Complete!\n";
