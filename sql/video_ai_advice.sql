-- =========================================================
-- Table: video_ai_advice
-- Purpose: 動画ごとのAIアドバイス結果を保存する
-- Author: ChannelScope v2
-- Date: 2025-11-07
-- =========================================================

CREATE TABLE `video_ai_advice` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `video_id` VARCHAR(64) NOT NULL COMMENT 'YouTubeのvideoId。既存動画テーブルと紐付け',
  `provider` VARCHAR(32) NOT NULL COMMENT 'chatgpt / gemini などAIプロバイダ名',
  `model` VARCHAR(64) NOT NULL COMMENT '使用モデル名（例: gpt-4.1-mini 等）',
  `title_suggestions` TEXT NULL COMMENT 'タイトル案（JSON文字列）',
  `tag_suggestions` TEXT NULL COMMENT 'タグ案（JSON文字列）',
  `short_script` TEXT NULL COMMENT 'ショート動画用構成案',
  `improvement_advice` TEXT NULL COMMENT 'この動画単体の改善ポイント',
  `strategy_advice` TEXT NULL COMMENT 'チャンネル全体の戦略提案',
  `raw_response` LONGTEXT NULL COMMENT 'AIからの生レスポンス(JSON)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_video_provider` (`video_id`, `provider`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci
COMMENT='動画ごとのAIアドバイスを保存するテーブル';
