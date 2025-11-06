<?php
// デバッグ用: logs への書き込み状況を確認するラッパー

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<pre>\n";

$logDir  = __DIR__ . '/logs';
echo "logDir: {$logDir}\n";

// ディレクトリ存在チェック
if (!is_dir($logDir)) {
    echo "logs directory does not exist. trying to create...\n";
    $mk = @mkdir($logDir, 0777, true);
    echo "mkdir result: " . var_export($mk, true) . "\n";
} else {
    echo "logs directory exists.\n";
}

// 書き込み可能かどうか
echo "is_writable(logDir): " . (is_writable($logDir) ? 'YES' : 'NO') . "\n";

// テストファイル書き込み
$testFile = $logDir . '/test_' . date('Ymd_His') . '.txt';
echo "testFile: {$testFile}\n";

$result = @file_put_contents($testFile, "test log\n");
echo "file_put_contents result: " . var_export($result, true) . "\n";

if ($result === false) {
    echo "error_get_last():\n";
    var_dump(error_get_last());
}

// ここから実際のインポート処理（これは動いているかの確認用）
echo "\n=== RUNNING youtube_import.php ===\n";

require __DIR__ . '/youtube_import.php';

echo "=== END youtube_import.php ===\n";

echo "</pre>\n";
