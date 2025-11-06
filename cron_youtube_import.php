<?php
// デバッグ用の超シンプル版ラッパー

// エラーを全部表示（ブラウザでテストしやすくする）
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== WRAPPER START: " . date('Y-m-d H:i:s') . " ===<br>\n";

require __DIR__ . '/youtube_import.php';

echo "<br>\n=== WRAPPER END: " . date('Y-m-d H:i:s') . " ===\n";
