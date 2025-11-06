<?php
// cronから実行する用のラッパー

$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/youtube_import_' . date('Ymd_His') . '.log';

// ログ用ディレクトリがなければ作成
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// まずは「ここまでは来た」を即ログに書く
file_put_contents($logFile, "=== CRON SCRIPT ENTER: " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);

// 出力をバッファリング
ob_start();

echo "=== CRON YOUTUBE IMPORT START: " . date('Y-m-d H:i:s') . " ===\n";

try {
    // 実際のインポート処理を呼び出し
    require __DIR__ . '/youtube_import.php';
    echo "=== CRON YOUTUBE IMPORT END: " . date('Y-m-d H:i:s') . " ===\n";
} catch (Throwable $e) {
    // 例外が飛んできた場合だけここにくる
    echo "[FATAL ERROR] " . $e->getMessage() . "\n";
}

$output = ob_get_clean();

// ログファイルに追記
file_put_contents($logFile, $output . "\n", FILE_APPEND);
