<?php
// =======================================
// ChannelScope - 本番環境設定 (さくらサーバー)
// =======================================

// DB接続設定
$host = "mysql2103.db.sakura.ne.jp";
$user = "purplelion51";
$password = "-6r_am73";
$dbname = "purplelion51_channel_scope_db";

// 環境識別
define('ENVIRONMENT', 'production');

// ベースURL
$baseUrl = 'https://purplelion51.sakura.ne.jp/ChannelScope/';

// APIキー設定（既存仕様に合わせてスネークケース）
$youtube_api_key = 'AIzaSyBzCzdW-ohPfzH7ZUeb10MRKp7DCvNwwrA';

// PDO接続
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB接続エラー（本番）: " . $e->getMessage());
}
