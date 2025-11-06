<?php
// cronから実行する用のラッパー（実行結果を logs に保存）

// エラーはログ側に乗るので画面には出さなくてOK（デバッグ時は 1 にしてもよい）
error_reporting(E_ALL);
ini_set('display_errors', '0');

// ログディレクトリ & ログファイル名
$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/youtube_import_' . date('Ymd_His') . '.log';

// ログ用ディレクトリがなければ作成
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// 出力をすべてバッファリング
ob_start();

echo "=== CRON YOUTUBE IMPORT START: " . date('Y-m-d H:i:s') . " ===" . PHP_EOL;

// 実際のインポート処理（本体）
require __DIR__ . '/youtube_import.php';

echo "=== CRON YOUTUBE IMPORT END: " . date('Y-m-d H:i:s') . " ===" . PHP_EOL;

// バッファ内容を取得
$buffer = ob_get_contents();

// ブラウザ/cron標準出力にもそのまま出す
ob_end_flush();

// ログファイルに書き出し（追記）
file_put_contents($logFile, $buffer, FILE_APPEND);
