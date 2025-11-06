<?php
// ===========================================
// ChannelScope - YouTube動画インポートスクリプト
// API → DB(videosテーブル) に保存
// ===========================================

require_once __DIR__ . '/config.php'; // $pdo / $youtube_api_key 読み込み

// -------------------------------------------------
// 設定
// -------------------------------------------------

// TODO: あなたのチャンネルIDをここに入れてください
// 例）UCxxxxxxx のような文字列
$channelId = 'UCQ3vl4KwgBgStc0yFCqXwgg';

// 1回の実行で何件までインポートするか（安全のため上限）
$maxImportCount = 500;

// -------------------------------------------------
// YouTube API 呼び出し共通関数
// -------------------------------------------------

/**
 * シンプルなHTTP GET
 */
function http_get_json(string $url): array
{
    $json = @file_get_contents($url);

    if ($json === false) {
        throw new Exception('YouTube API呼び出しに失敗しました: ' . $url);
    }

    $data = json_decode($json, true);

    if (!is_array($data)) {
        throw new Exception('YouTube APIのレスポンスが不正です: ' . $url);
    }

    return $data;
}

// -------------------------------------------------
// 1. チャンネルの「アップロード動画」プレイリストID取得
// -------------------------------------------------

/**
 * チャンネルの uploads プレイリストIDを取得
 */
function get_uploads_playlist_id(string $channelId, string $apiKey): string
{
    $url = sprintf(
        'https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id=%s&key=%s',
        urlencode($channelId),
        urlencode($apiKey)
    );

    $data = http_get_json($url);

    if (empty($data['items'][0]['contentDetails']['relatedPlaylists']['uploads'])) {
        throw new Exception('uploadsプレイリストIDが取得できませんでした。チャンネルIDを確認してください。');
    }

    return $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'];
}

// -------------------------------------------------
// 2. uploads プレイリストから動画一覧を取得
// -------------------------------------------------

/**
 * プレイリストから動画の基本情報一覧を取得
 * - videoId
 * - title
 * - description
 * - publishedAt
 * - tags
 * - thumbnail_url
 */
function fetch_videos_from_playlist(string $playlistId, string $apiKey, int $maxImportCount): array
{
    $videos = [];
    $pageToken = null;

    while (true) {
        $url = 'https://www.googleapis.com/youtube/v3/playlistItems'
            . '?part=snippet'
            . '&playlistId=' . urlencode($playlistId)
            . '&maxResults=50'
            . '&key=' . urlencode($apiKey);

        if ($pageToken) {
            $url .= '&pageToken=' . urlencode($pageToken);
        }

        $data = http_get_json($url);

        if (empty($data['items'])) {
            break;
        }

        foreach ($data['items'] as $item) {
            $snippet = $item['snippet'] ?? null;
            if (!$snippet) {
                continue;
            }

            $resource = $snippet['resourceId'] ?? [];
            if (($resource['kind'] ?? '') !== 'youtube#video') {
                continue;
            }

            $videoId = $resource['videoId'] ?? null;
            if (!$videoId) {
                continue;
            }

            $videos[$videoId] = [
                'video_id'      => $videoId,
                'title'         => $snippet['title'] ?? '',
                'description'   => $snippet['description'] ?? '',
                'published_at'  => $snippet['publishedAt'] ?? null,
                'tags'          => $snippet['tags'] ?? [],
                'thumbnail_url' => $snippet['thumbnails']['high']['url']
                    ?? ($snippet['thumbnails']['default']['url'] ?? null),
            ];

            if (count($videos) >= $maxImportCount) {
                break 2; // 2重ループ脱出
            }
        }

        // 次ページがあれば継続
        if (!empty($data['nextPageToken'])) {
            $pageToken = $data['nextPageToken'];
        } else {
            break;
        }
    }

    return $videos;
}

// -------------------------------------------------
// 3. 複数動画の統計情報を取得（views / likes / comments）
// -------------------------------------------------

/**
 * 統計情報をまとめて取得
 */
function fetch_statistics_for_videos(array $videoIds, string $apiKey): array
{
    $stats = [];

    // YouTube APIはidを50件まで一括取得可能
    $chunks = array_chunk($videoIds, 50);

    foreach ($chunks as $chunk) {
        $url = 'https://www.googleapis.com/youtube/v3/videos'
            . '?part=statistics'
            . '&id=' . urlencode(implode(',', $chunk))
            . '&key=' . urlencode($apiKey);

        $data = http_get_json($url);

        if (empty($data['items'])) {
            continue;
        }

        foreach ($data['items'] as $item) {
            $videoId = $item['id'] ?? null;
            if (!$videoId) {
                continue;
            }

            $statistics = $item['statistics'] ?? [];

            $stats[$videoId] = [
                'view_count'    => isset($statistics['viewCount']) ? (int)$statistics['viewCount'] : null,
                'like_count'    => isset($statistics['likeCount']) ? (int)$statistics['likeCount'] : null,
                'comment_count' => isset($statistics['commentCount']) ? (int)$statistics['commentCount'] : null,
            ];
        }
    }

    return $stats;
}

// -------------------------------------------------
// 4. DBへの保存（重複チェック付き）
// -------------------------------------------------

/**
 * videosテーブルに動画をINSERT/UPDATEする
 *
 * テーブル想定例:
 *  - id (INT AUTO_INCREMENT, PK)
 *  - external_video_id (VARCHAR, YouTubeのvideoId)
 *  - title (VARCHAR)
 *  - description (TEXT)
 *  - published_at (DATETIME)
 *  - view_count (INT)
 *  - like_count (INT)
 *  - comment_count (INT)
 *  - tags (TEXT)  カンマ区切り or JSON
 *  - thumbnail_url (VARCHAR)
 *  - created_at (DATETIME)
 *  - updated_at (DATETIME)
 */
function upsert_video(PDO $pdo, array $video): void
{
    // 既存確認（external_video_idで検索）
    $sql = "SELECT id FROM videos WHERE external_video_id = :external_video_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':external_video_id' => $video['external_video_id'],
    ]);
    $existing = $stmt->fetch();

    if ($existing) {
        // 更新
        $sql = "
            UPDATE videos SET
                title          = :title,
                description    = :description,
                published_at   = :published_at,
                view_count     = :view_count,
                like_count     = :like_count,
                comment_count  = :comment_count,
                tags           = :tags,
                thumbnail_url  = :thumbnail_url,
                updated_at     = NOW()
            WHERE id = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title'         => $video['title'],
            ':description'   => $video['description'],
            ':published_at'  => $video['published_at'],
            ':view_count'    => $video['view_count'],
            ':like_count'    => $video['like_count'],
            ':comment_count' => $video['comment_count'],
            ':tags'          => $video['tags'],
            ':thumbnail_url' => $video['thumbnail_url'],
            ':id'            => $existing['id'],
        ]);
    } else {
        // 新規INSERT
        $sql = "
            INSERT INTO videos (
                external_video_id,
                title,
                description,
                published_at,
                view_count,
                like_count,
                comment_count,
                tags,
                thumbnail_url,
                created_at,
                updated_at
            ) VALUES (
                :external_video_id,
                :title,
                :description,
                :published_at,
                :view_count,
                :like_count,
                :comment_count,
                :tags,
                :thumbnail_url,
                NOW(),
                NOW()
            )
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':external_video_id' => $video['external_video_id'],
            ':title'             => $video['title'],
            ':description'       => $video['description'],
            ':published_at'      => $video['published_at'],
            ':view_count'        => $video['view_count'],
            ':like_count'        => $video['like_count'],
            ':comment_count'     => $video['comment_count'],
            ':tags'              => $video['tags'],
            ':thumbnail_url'     => $video['thumbnail_url'],
        ]);
    }
}

// -------------------------------------------------
// メイン処理
// -------------------------------------------------

header('Content-Type: text/plain; charset=utf-8');

echo "ChannelScope YouTubeインポート開始...\n\n";

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('PDOインスタンス($pdo)が正しく初期化されていません。config.php / config.*.php を確認してください。');
    }

    if (empty($youtube_api_key) || $youtube_api_key === 'YOUR_API_KEY_HERE') {
        throw new Exception('YouTube APIキーが設定されていません。configファイルの $youtube_api_key を確認してください。');
    }

    if (empty($channelId) || $channelId === 'YOUR_CHANNEL_ID_HERE') {
        throw new Exception('チャンネルIDが設定されていません。$channelId を自分のチャンネルIDに設定してください。');
    }

    echo "チャンネルID: {$channelId}\n";

    // 1. uploadsプレイリストID取得
    $uploadsPlaylistId = get_uploads_playlist_id($channelId, $youtube_api_key);
    echo "uploadsプレイリストID: {$uploadsPlaylistId}\n\n";

    // 2. プレイリストから動画一覧取得
    $videos = fetch_videos_from_playlist($uploadsPlaylistId, $youtube_api_key, $maxImportCount);
    $videoCount = count($videos);
    echo "取得した動画件数: {$videoCount}\n";

    if ($videoCount === 0) {
        echo "インポートする動画がありません。\n";
        exit;
    }

    // 3. 統計情報を取得
    $videoIds = array_keys($videos);
    $stats = fetch_statistics_for_videos($videoIds, $youtube_api_key);

    // 4. DBへ保存
    $imported = 0;

    foreach ($videos as $videoId => $info) {
        $stat = $stats[$videoId] ?? [
            'view_count'    => null,
            'like_count'    => null,
            'comment_count' => null,
        ];

        $video = [
            'external_video_id' => $videoId,
            'title'             => $info['title'],
            'description'       => $info['description'],
            'published_at'      => $info['published_at']
                ? date('Y-m-d H:i:s', strtotime($info['published_at']))
                : null,
            'view_count'        => $stat['view_count'],
            'like_count'        => $stat['like_count'],
            'comment_count'     => $stat['comment_count'],
            'tags'              => !empty($info['tags']) ? implode(',', $info['tags']) : '',
            'thumbnail_url'     => $info['thumbnail_url'],
        ];

        upsert_video($pdo, $video);
        $imported++;

        echo " - {$videoId} を保存しました ({$imported}/{$videoCount})\n";
    }

    echo "\nインポート完了: {$imported} 件の動画をDBに保存しました。\n";

} catch (Throwable $e) {
    echo "\n[エラー]\n";
    echo $e->getMessage() . "\n";
}
