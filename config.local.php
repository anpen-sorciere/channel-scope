<?php
/**
 * ローカル環境（XAMPP）用設定ファイル
 * ChannelScope - 開発環境用
 */

// ----------------------------------------
// 🔧 データベース接続設定
// ----------------------------------------
$dbHost = 'localhost';
$dbName = 'purplelion51_channel_scope_db';
$dbUser = 'root';
$dbPass = ''; // XAMPPのデフォルトはパスワードなし

// ----------------------------------------
// 🔑 YouTube API設定
// ----------------------------------------
// ※ 実際のAPIキーを入れてください
$youtubeApiKey    = 'AIzaSyBzCzdW-ohPfzH7ZUeb10MRKp7DCvNwwrA';
$youtubeChannelId = 'UCQ3vl4KwgBgStc0yFCqXwgg';

// ----------------------------------------
// 🧩 互換用（旧変数名対応）
// ----------------------------------------
$youtube_api_key    = $youtubeApiKey;
$youtube_channel_id = $youtubeChannelId;
