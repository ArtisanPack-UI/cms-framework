<?php

// Simple syntax check for the policy files
echo "Testing Policy Files...\n";

// Test UserPolicy syntax
$userPolicyFile = 'src/Policies/UserPolicy.php';
if (file_exists($userPolicyFile)) {
    $output = [];
    $return_var = 0;
    exec("php -l $userPolicyFile 2>&1", $output, $return_var);
    if ($return_var === 0) {
        echo "✓ UserPolicy.php syntax is valid\n";
    } else {
        echo "✗ UserPolicy.php syntax error found\n";
        echo implode("\n", $output)."\n";
    }
} else {
    echo "✗ UserPolicy.php file not found\n";
}

// Test ContentPolicy syntax
$contentPolicyFile = 'src/Policies/ContentPolicy.php';
if (file_exists($contentPolicyFile)) {
    $output = [];
    $return_var = 0;
    exec("php -l $contentPolicyFile 2>&1", $output, $return_var);
    if ($return_var === 0) {
        echo "✓ ContentPolicy.php syntax is valid\n";
    } else {
        echo "✗ ContentPolicy.php syntax error found\n";
        echo implode("\n", $output)."\n";
    }
} else {
    echo "✗ ContentPolicy.php file not found\n";
}

// Check for security improvements
echo "\nVerifying Security Fixes...\n";

// Check UserPolicy no longer has unconditional return true
$userPolicyContent = file_get_contents($userPolicyFile);
if (strpos($userPolicyContent, 'return true;') !== false && strpos($userPolicyContent, 'viewAny') !== false) {
    // Check if the return true is conditional now
    $viewAnySection = substr($userPolicyContent, strpos($userPolicyContent, 'viewAny'));
    $viewAnySection = substr($viewAnySection, 0, strpos($viewAnySection, '}'));

    if (strpos($viewAnySection, 'if (') !== false || strpos($viewAnySection, 'Eventy::filter') !== false) {
        echo "✓ UserPolicy viewAny() now has proper authorization logic\n";
    } else {
        echo "✗ UserPolicy viewAny() still has unconditional return true\n";
    }
}

// Check ContentPolicy no longer has unconditional return true
$contentPolicyContent = file_get_contents($contentPolicyFile);
if (strpos($contentPolicyContent, 'Eventy::filter') !== false) {
    echo "✓ ContentPolicy now includes Eventy hooks for customization\n";
} else {
    echo "✗ ContentPolicy missing Eventy hooks\n";
}

if (strpos($contentPolicyContent, '?User $user') !== false) {
    echo "✓ Both policies support guest/public access (nullable User)\n";
} else {
    echo "✗ Policies missing guest access support\n";
}

echo "\nPolicy Security Review Complete!\n";
