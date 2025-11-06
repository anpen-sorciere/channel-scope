<?php
// cronから実行する用のラッパー（実行結果を logs に保存）

// エラー表示（テスト時は1、本番で気になるなら0にしてもOK）
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ログディレクトリ & ファイル
$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/youtube_import_' . date('Ymd_His') . '.log';

// ログ用ディレクトリがなければ作成
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// 出力を全部バッファにためる
ob_start();

// ここから先の echo は「画面にも出る」＆「ログにも残る」
echo "=== CRON YOUTUBE IMPORT START: " . date('Y-m-d H:i:s') . " ===" . PHP_EOL;

// 実際のインポート処理を呼び出し
require __DIR__ . '/youtube_import.php';

echo "=== CRON YOUTUBE IMPORT END: " . date('Y-m-d H:i:s') . " ===" . PHP_EOL;

// 出力内容を取得
$buffer = ob_get_contents();

// ブラウザやcronの標準出力に流す
ob_end_flush();

// ログファイルに書き出し
file_put_contents($logFile, $buffer, FILE_APPEND);
