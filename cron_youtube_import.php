<?php
// cronから実行する用のラッパー

// ログファイルパス（必要に応じて変更OK）
$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/youtube_import_' . date('Ymd') . '.log';

// ログ用ディレクトリがなければ作成
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// 出力をバッファリング
ob_start();

echo "=== CRON YOUTUBE IMPORT START: " . date('Y-m-d H:i:s') . " ===\n";

// 実際のインポート処理を呼び出し
require __DIR__ . '/youtube_import.php';

echo "=== CRON YOUTUBE IMPORT END: " . date('Y-m-d H:i:s') . " ===\n";

$output = ob_get_clean();

// ログファイルに追記
file_put_contents($logFile, $output . "\n", FILE_APPEND);
