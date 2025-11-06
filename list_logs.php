<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$logDir = __DIR__ . '/logs';

echo "<pre>\n";
echo "logDir: {$logDir}\n";

if (!is_dir($logDir)) {
    echo "logs directory does NOT exist.\n";
    exit;
}

echo "logs directory exists.\n";
echo "is_writable(logDir): " . (is_writable($logDir) ? 'YES' : 'NO') . "\n\n";

echo "scandir(logDir):\n";
$files = scandir($logDir);
var_dump($files);

echo "\nDetail:\n";
foreach ($files as $f) {
    if ($f === '.' || $f === '..') continue;
    $path = $logDir . '/' . $f;
    echo $f . "  size=" . filesize($path) . " bytes\n";
}

echo "</pre>\n";
