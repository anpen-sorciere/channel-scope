-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: 127.0.0.1
-- 生成日時: 2025-11-05 21:29:21
-- サーバのバージョン： 10.4.32-MariaDB
-- PHP のバージョン: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `purplelion51_channel_scope_db`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `channels`
--

CREATE TABLE `channels` (
  `id` int(11) NOT NULL,
  `platform` enum('youtube','tiktok') NOT NULL,
  `external_channel_id` varchar(100) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `channels`
--

INSERT INTO `channels` (`id`, `platform`, `external_channel_id`, `name`, `created_at`) VALUES
(2, 'youtube', 'UCQ3vl4KwgBgStc0yFCqXwgg', 'ソルシエールちゃんねる(Sorciere Channel)', '2025-11-05 19:58:17');

-- --------------------------------------------------------

--
-- テーブルの構造 `videos`
--

CREATE TABLE `videos` (
  `id` int(11) NOT NULL,
  `external_video_id` varchar(50) NOT NULL,
  `channel_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `upload_time` datetime DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `likes` int(11) DEFAULT 0,
  `comments` int(11) DEFAULT 0,
  `shares` int(11) DEFAULT 0,
  `tags` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `videos`
--

INSERT INTO `videos` (`id`, `external_video_id`, `channel_id`, `title`, `upload_time`, `views`, `likes`, `comments`, `shares`, `tags`, `created_at`) VALUES
(4, '-8OepnIDijo', 2, 'コンカフェ　大阪日本橋　ソルシエール　20251105 #コスプレ #ライブ #コンカフェ #魔法少女  ＃大阪', '2025-11-05 16:18:39', 1564, 10, 1, 0, '', '2025-11-05 19:58:18'),
(5, 'kOak8Ioa53Y', 2, '11/5コンカフェキャスト配信', '2025-11-05 15:19:42', 6731, 177, 0, 0, '', '2025-11-05 19:58:18'),
(6, 'mcQSe8vDoOw', 2, 'ユニバでミャクミャク！！？🤣#ユニバ #USJ #ミャクミャク #ハロウィン #爆笑動画', '2025-11-05 08:35:49', 1195, 12, 3, 0, '', '2025-11-05 19:58:18'),
(7, 'u3QfE_4mJcg', 2, 'コンカフェキャスト配信', '2025-11-04 15:13:57', 3332, 115, 0, 0, '', '2025-11-05 19:58:18'),
(8, 'Vkio1i33Aos', 2, '「これがチェンソーマンの味！？ユニバ限定フードが想像以上😂」#ユニバ #USJ #ユニバハロウィン #チェンソーマン #ハロウィン', '2025-11-04 12:45:32', 5417, 59, 0, 0, '', '2025-11-05 19:58:18'),
(9, '0weygBPEhKs', 2, 'コンカフェキャスト配信', '2025-11-03 15:48:33', 15690, 270, 2, 0, '', '2025-11-05 19:58:18'),
(10, 'W9P_o3MzmyM', 2, 'コンカフェキャスト配信2025.10.02', '2025-11-03 03:27:26', 3739, 115, 1, 0, '', '2025-11-05 19:58:18'),
(11, 'JheKwUy0Q1Y', 2, 'ライブ ハイライト', '2025-11-03 00:44:06', 1534, 16, 3, 0, '', '2025-11-05 19:58:18'),
(12, 'r7JsvkOEekA', 2, 'ライブ ハイライト', '2025-11-03 00:43:34', 417, 7, 1, 0, '', '2025-11-05 19:58:18'),
(13, '4NY2qMqPCaE', 2, 'ライブ ハイライト', '2025-11-03 00:43:47', 1282, 10, 2, 0, '', '2025-11-05 19:58:18'),
(14, 'AdoyS3m39yk', 2, 'コンカフェキャスト配信2025.11.02', '2025-11-02 10:57:31', 748, 25, 0, 0, '', '2025-11-05 19:58:18'),
(15, 'ACZ1Jm1DUFY', 2, 'コンカフェキャスト配信', '2025-11-01 15:58:56', 11209, 217, 1, 0, '', '2025-11-05 19:58:18'),
(16, 'VdupBofbtcI', 2, 'コンカフェキャスト配信', '2025-11-01 03:27:09', 1905, 62, 0, 0, '', '2025-11-05 19:58:18'),
(17, 'o4N0oeUnyYc', 2, 'コンカフェキャスト配信', '2025-11-01 01:51:53', 1200, 60, 0, 0, '', '2025-11-05 19:58:18'),
(18, 'CMgVrcOCGMg', 2, 'コンカフェキャスト配信', '2025-11-01 00:34:44', 987, 30, 0, 0, '', '2025-11-05 19:58:18'),
(19, 'LuqrwiX0sFc', 2, 'コンカフェキャスト配信', '2025-10-31 23:51:28', 2744, 101, 0, 0, '', '2025-11-05 19:58:18'),
(20, 'ghREJhsWqxY', 2, 'コンカフェキャスト配信', '2025-10-31 23:44:45', 558, 22, 0, 0, '', '2025-11-05 19:58:18'),
(21, '6fItZNsNWGw', 2, 'ライブ ハイライト　コンカフェ　ソルシエール　　2025 10/31 ①', '2025-10-31 16:03:08', 1367, 23, 2, 0, '', '2025-11-05 19:58:18'),
(22, 'eKEHb9P8C0E', 2, 'コンカフェ　魔法少女ソルシエール　2025  10/31', '2025-10-31 15:58:27', 2494, 24, 2, 0, '', '2025-11-05 19:58:18'),
(23, 'XISt_5VPbEU', 2, '「ハロウィンユニバで手つなぎ縛りしたらまさかの展開!?🎃」', '2025-10-31 09:15:45', 1844, 38, 1, 0, '', '2025-11-05 19:58:18');

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `channels`
--
ALTER TABLE `channels`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_videos_external_video_id` (`external_video_id`),
  ADD KEY `channel_id` (`channel_id`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `channels`
--
ALTER TABLE `channels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- テーブルの AUTO_INCREMENT `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `videos`
--
ALTER TABLE `videos`
  ADD CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
