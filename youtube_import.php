<?php
require_once __DIR__ . '/config.php';

// 互換用: 古い変数名($youtube_api_key / $youtube_channel_id)にも対応する
if (!isset($youtubeApiKey) && isset($youtube_api_key)) {
    $youtubeApiKey = $youtube_api_key;
}
if (!isset($youtubeChannelId) && isset($youtube_channel_id)) {
    $youtubeChannelId = $youtube_channel_id;
}

/**
 * YouTube Data API から JSON を取得するヘルパー
 */
function fetchYoutubeJson(string $url): array
{
    $json = @file_get_contents($url);
    if ($json === false) {
        throw new RuntimeException('YouTube APIリクエストに失敗しました: ' . $url);
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new RuntimeException('YouTube APIレスポンスのJSONデコードに失敗しました。');
    }

    if (isset($data['error'])) {
        $msg = $data['error']['message'] ?? 'Unknown error';
        throw new RuntimeException('YouTube APIエラー: ' . $msg);
    }

    return $data;
}

/**
 * ISO8601の長さ表記（PT4M20S など）を秒数に変換
 */
function parseDurationToSeconds(?string $isoDuration): ?int
{
    if (empty($isoDuration)) {
        return null;
    }

    // 例: PT1H2M3S, PT4M20S, PT30S, PT2H など
    $pattern = '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/';
    if (!preg_match($pattern, $isoDuration, $matches)) {
        return null;
    }

    $hours   = isset($matches[1]) ? (int)$matches[1] : 0;
    $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
    $seconds = isset($matches[3]) ? (int)$matches[3] : 0;

    return $hours * 3600 + $minutes * 60 + $seconds;
}

/**
 * 動画タイプを決定する
 * - liveStreamingDetails があれば live
 * - duration_seconds <= 60 なら short
 * - それ以外は long
 */
function decideVideoType(?array $liveDetails, ?int $durationSeconds): string
{
    // ライブ配信判定
    if (!empty($liveDetails) && (isset($liveDetails['actualStartTime']) || isset($liveDetails['scheduledStartTime']))) {
        return 'live';
    }

    // ショート判定（60秒以下）
    if ($durationSeconds !== null && $durationSeconds > 0 && $durationSeconds <= 60) {
        return 'short';
    }

    // 長さ不明の場合
    if ($durationSeconds === null || $durationSeconds <= 0) {
        return 'unknown';
    }

    return 'long';
}

// --------------------------------------------------
// メイン処理
// --------------------------------------------------
echo "ChannelScope YouTube インポート開始..." . PHP_EOL . PHP_EOL;

if (empty($youtubeApiKey) || empty($youtubeChannelId)) {
    echo "[ERROR] config.php で \$youtubeApiKey / \$youtubeChannelId が設定されていません。" . PHP_EOL;
    exit(1);
}

// 1. チャンネルの「uploads」プレイリストIDを取得
try {
    $channelUrl = sprintf(
        'https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id=%s&key=%s',
        urlencode($youtubeChannelId),
        urlencode($youtubeApiKey)
    );
    $channelData = fetchYoutubeJson($channelUrl);

    if (empty($channelData['items'][0]['contentDetails']['relatedPlaylists']['uploads'])) {
        throw new RuntimeException('uploadsプレイリストIDが取得できませんでした。');
    }

    $uploadsPlaylistId = $channelData['items'][0]['contentDetails']['relatedPlaylists']['uploads'];

    echo "チャンネルID: {$youtubeChannelId}" . PHP_EOL;
    echo "uploadsプレイリストID: {$uploadsPlaylistId}" . PHP_EOL . PHP_EOL;

} catch (Exception $e) {
    echo "[ERROR] チャンネル情報の取得に失敗: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// 2. uploads プレイリストから全動画の videoId を取得しながら、詳細情報を取得
$totalFetched   = 0;
$totalInserted  = 0;
$totalUpdated   = 0;

$nextPageToken = null;

do {
    $playlistUrl = sprintf(
        'https://www.googleapis.com/youtube/v3/playlistItems?part=contentDetails&playlistId=%s&maxResults=50&key=%s%s',
        urlencode($uploadsPlaylistId),
        urlencode($youtubeApiKey),
        $nextPageToken ? '&pageToken=' . urlencode($nextPageToken) : ''
    );

    try {
        $playlistData = fetchYoutubeJson($playlistUrl);
    } catch (Exception $e) {
        echo "[ERROR] playlistItems の取得に失敗: " . $e->getMessage() . PHP_EOL;
        break;
    }

    if (empty($playlistData['items'])) {
        break;
    }

    $videoIds = [];
    foreach ($playlistData['items'] as $item) {
        $vid = $item['contentDetails']['videoId'] ?? null;
        if ($vid) {
            $videoIds[] = $vid;
        }
    }

    if (empty($videoIds)) {
        break;
    }

    // 3. videos.list で詳細情報をまとめて取得
    $videosUrl = sprintf(
        'https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails,statistics,liveStreamingDetails&id=%s&maxResults=50&key=%s',
        urlencode(implode(',', $videoIds)),
        urlencode($youtubeApiKey)
    );

    try {
        $videosData = fetchYoutubeJson($videosUrl);
    } catch (Exception $e) {
        echo "[ERROR] videos.list の取得に失敗: " . $e->getMessage() . PHP_EOL;
        break;
    }

    if (empty($videosData['items'])) {
        break;
    }

    foreach ($videosData['items'] as $video) {
        $totalFetched++;

        $videoId   = $video['id'] ?? null;
        $snippet   = $video['snippet'] ?? [];
        $stats     = $video['statistics'] ?? [];
        $details   = $video['contentDetails'] ?? [];
        $liveInfo  = $video['liveStreamingDetails'] ?? [];

        if (!$videoId) {
            continue;
        }

        $title       = $snippet['title'] ?? '';
        $description = $snippet['description'] ?? '';
        $publishedAt = $snippet['publishedAt'] ?? null;
        $tagsArray   = $snippet['tags'] ?? [];
        $tags        = implode(',', $tagsArray);

        // サムネイル（標準サイズ→なければ高解像度→デフォルト）
        $thumb = '';
        if (!empty($snippet['thumbnails']['standard']['url'])) {
            $thumb = $snippet['thumbnails']['standard']['url'];
        } elseif (!empty($snippet['thumbnails']['high']['url'])) {
            $thumb = $snippet['thumbnails']['high']['url'];
        } elseif (!empty($snippet['thumbnails']['default']['url'])) {
            $thumb = $snippet['thumbnails']['default']['url'];
        }

        $viewCount    = isset($stats['viewCount'])    ? (int)$stats['viewCount']    : 0;
        $likeCount    = isset($stats['likeCount'])    ? (int)$stats['likeCount']    : 0;
        $commentCount = isset($stats['commentCount']) ? (int)$stats['commentCount'] : 0;

        // 長さとタイプの判定
        $durationIso     = $details['duration'] ?? null;
        $durationSeconds = parseDurationToSeconds($durationIso);
        $videoType       = decideVideoType($liveInfo, $durationSeconds);

        // DB登録（INSERT or UPDATE）
        try {
            // 既存レコードチェック
            $stmt = $pdo->prepare("SELECT id FROM videos WHERE external_video_id = :vid LIMIT 1");
            $stmt->execute([':vid' => $videoId]);
            $existing = $stmt->fetch();

            if ($existing) {
                // UPDATE
                $sql = "
                    UPDATE videos
                    SET
                        title            = :title,
                        description      = :description,
                        view_count       = :view_count,
                        like_count       = :like_count,
                        comment_count    = :comment_count,
                        duration_seconds = :duration_seconds,
                        video_type       = :video_type,
                        published_at     = :published_at,
                        tags             = :tags,
                        thumbnail_url    = :thumb
                    WHERE external_video_id = :vid
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':title'            => $title,
                    ':description'      => $description,
                    ':view_count'       => $viewCount,
                    ':like_count'       => $likeCount,
                    ':comment_count'    => $commentCount,
                    ':duration_seconds' => $durationSeconds,
                    ':video_type'       => $videoType,
                    ':published_at'     => $publishedAt,
                    ':tags'             => $tags,
                    ':thumb'            => $thumb,
                    ':vid'              => $videoId,
                ]);
                $totalUpdated++;
            } else {
                // INSERT
                $sql = "
                    INSERT INTO videos (
                        external_video_id,
                        title,
                        description,
                        view_count,
                        like_count,
                        comment_count,
                        duration_seconds,
                        video_type,
                        published_at,
                        tags,
                        thumbnail_url
                    ) VALUES (
                        :external_video_id,
                        :title,
                        :description,
                        :view_count,
                        :like_count,
                        :comment_count,
                        :duration_seconds,
                        :video_type,
                        :published_at,
                        :tags,
                        :thumbnail_url
                    )
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':external_video_id' => $videoId,
                    ':title'             => $title,
                    ':description'       => $description,
                    ':view_count'        => $viewCount,
                    ':like_count'        => $likeCount,
                    ':comment_count'     => $commentCount,
                    ':duration_seconds'  => $durationSeconds,
                    ':video_type'        => $videoType,
                    ':published_at'      => $publishedAt,
                    ':tags'              => $tags,
                    ':thumbnail_url'     => $thumb,
                ]);
                $totalInserted++;
            }
        } catch (Exception $e) {
            echo "[ERROR] DB登録エラー (videoId={$videoId}): " . $e->getMessage() . PHP_EOL;
        }
    }

    $nextPageToken = $playlistData['nextPageToken'] ?? null;

} while (!empty($nextPageToken));

echo PHP_EOL;
echo "----------------------------------------" . PHP_EOL;
echo "  インポート処理完了" . PHP_EOL;
echo "----------------------------------------" . PHP_EOL;
echo "取得対象動画数: {$totalFetched}" . PHP_EOL;
echo "新規追加:        {$totalInserted}" . PHP_EOL;
echo "更新:            {$totalUpdated}" . PHP_EOL;
echo PHP_EOL;
echo "※ 既存動画も UPDATE しているので、duration_seconds / video_type も更新済みです。" . PHP_EOL;
