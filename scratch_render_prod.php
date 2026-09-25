<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['name'] = 'Admin';
$_SESSION['role'] = 'Administrator';

ob_start();
include __DIR__ . '/production.php';
$html = ob_get_clean();

echo "HTML length: " . strlen($html) . "\n";
// Check if any PHP warnings or errors are inside
if (strpos($html, 'Fatal error') !== false || strpos($html, 'Warning') !== false || strpos($html, 'Notice') !== false) {
    echo "Found PHP issues:\n";
    preg_match_all('/(Fatal error|Warning|Notice):.*/i', $html, $matches);
    print_r($matches[0]);
} else {
    echo "No PHP errors or warnings found in output.\n";
}
