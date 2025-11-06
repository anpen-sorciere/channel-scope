<?php
// =======================================
// ChannelScope - 環境自動切替設定
// =======================================

// 実行環境を自動判定
if (file_exists(__DIR__ . '/config.production.php')) {
    $serverHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // 本番環境のドメインであれば production.php を読み込む
    if (str_contains($serverHost, 'purplelion51.sakura.ne.jp')) {
        require_once __DIR__ . '/config.production.php';
    } else {
        require_once __DIR__ . '/config.local.php';
    }
} else {
    // production設定が存在しない場合はローカル設定を優先
    require_once __DIR__ . '/config.local.php';
}
