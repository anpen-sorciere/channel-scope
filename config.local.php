<?php
// =======================================
// ChannelScope - ローカル環境設定 (XAMPP)
// =======================================

// DB接続設定
$host = "localhost";
$user = "root";
$password = "";
$dbname = "purplelion51_channel_scope_db";

// 環境識別
define('ENVIRONMENT', 'local');

// ベースURL
$baseUrl = 'http://localhost/ChannelScope/';

// APIキー設定（既存仕様に合わせてスネークケース）
$youtube_api_key = 'AIzaSyBzCzdW-ohPfzH7ZUeb10MRKp7DCvNwwrA';

// PDO接続
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB接続エラー（ローカル）: " . $e->getMessage());
}
