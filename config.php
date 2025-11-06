<?php
/**
 * 共通設定ファイル
 * 
 * - 環境判定（local / production）
 * - 各環境ごとの config.*.php を読み込み
 * - PDO($pdo) の生成
 * - $baseUrl の設定
 */

// CLI（cron, SSH）かどうか
$isCli = (PHP_SAPI === 'cli');

if ($isCli) {
    // cron や SSH から実行される場合は本番環境として扱う
    define('ENVIRONMENT', 'production');
    require_once __DIR__ . '/config.production.php';

} else {
    // Web（ブラウザ）経由のアクセス
    $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

    if ($serverName === 'localhost' || $serverName === '127.0.0.1') {
        // ローカル環境（XAMPP）
        define('ENVIRONMENT', 'local');
        require_once __DIR__ . '/config.local.php';
    } else {
        // 本番環境（さくら）
        define('ENVIRONMENT', 'production');
        require_once __DIR__ . '/config.production.php';
    }
}

// ここまでで、各環境の config.*.php から
// $dbHost, $dbName, $dbUser, $dbPass
// $youtubeApiKey, $youtubeChannelId
// がセットされている前提

// PDO生成
try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    echo 'DB接続エラー: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

// ベースURL（リンク表示用）
if (ENVIRONMENT === 'local') {
    $baseUrl = 'http://localhost/ChannelScope';
} else {
    $baseUrl = 'https://purplelion51.sakura.ne.jp/ChannelScope';
}
