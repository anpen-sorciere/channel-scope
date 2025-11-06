<?php
/**
 * 本番環境（さくらレンタルサーバー）用設定ファイル
 * ChannelScope - デプロイ環境用
 */

// ----------------------------------------
// 🔧 データベース接続設定
// ----------------------------------------
$dbHost = 'mysql2103.db.sakura.ne.jp';
$dbName = 'purplelion51_channel_scope_db';
$dbUser = 'purplelion51';
$dbPass = '-6r_am73';  // ← あなたの設定どおり

// ----------------------------------------
// 🔑 YouTube API設定
// ----------------------------------------
// ※ ローカルと同じAPIキーを使用可能
$youtubeApiKey    = 'AIzaSyBzCzdW-ohPfzH7ZUeb10MRKp7DCvNwwrA';
$youtubeChannelId = 'UCQ3vl4KwgBgStc0yFCqXwgg';

// ----------------------------------------
// 🧩 互換用（旧変数名対応）
// ----------------------------------------
$youtube_api_key    = $youtubeApiKey;
$youtube_channel_id = $youtubeChannelId;
