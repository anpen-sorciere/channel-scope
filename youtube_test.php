<?php
require_once __DIR__ . '/config.php';

// ★テスト用チャンネルID（Google Developers公式チャンネル）
$channelId = 'UCQ3vl4KwgBgStc0yFCqXwgg';

// YouTube Data API のエンドポイントURLを作成
$params = [
    'part'       => 'snippet',
    'channelId'  => $channelId,
    'maxResults' => 5,
    'order'      => 'date',
    'key'        => $youtube_api_key,
];

$url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query($params);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>YouTube API テスト - ChannelScope</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 20px; background: #f5f5f5; }
        h1 { margin-bottom: 10px; }
        .video {
            background: #fff;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        img { vertical-align: middle; }
        .title { font-weight: bold; margin-bottom: 4px; }
        .date { font-size: 12px; color: #555; }
        pre {
            background: #222;
            color: #0f0;
            padding: 10px;
            border-radius: 6px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>YouTube API テスト（cURL版）</h1>
    <p>チャンネルID：<?= htmlspecialchars($channelId, ENT_QUOTES, 'UTF-8') ?></p>
    <p>リクエストURL：</p>
    <pre><?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?></pre>

<?php
// ---------- cURL でHTTPリクエスト ----------
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,  // SSL検証
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 15,
]);

$responseJson = curl_exec($ch);

if ($responseJson === false) {
    $err = curl_error($ch);
    curl_close($ch);
    echo "<p>❌ cURLエラーが発生しました。</p>";
    echo "<pre>" . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</pre>";
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTPステータスコード：<b>{$httpCode}</b></p>";

if ($httpCode !== 200) {
    echo "<p>❌ API がエラーを返しています。生レスポンスを表示します。</p>";
    echo "<pre>" . htmlspecialchars($responseJson, ENT_QUOTES, 'UTF-8') . "</pre>";
    exit;
}

$data = json_decode($responseJson, true);
?>

<?php if (!isset($data['items'])): ?>
    <p>❌ items が見つかりません。レスポンスを確認してください。</p>
    <pre><?= htmlspecialchars($responseJson, ENT_QUOTES, 'UTF-8') ?></pre>
<?php else: ?>
    <h2>取得した最新動画一覧</h2>
    <?php foreach ($data['items'] as $item): ?>
        <?php
            $snippet = $item['snippet'];
            $title = $snippet['title'] ?? '';
            $thumb = $snippet['thumbnails']['default']['url'] ?? '';
            $publishedAt = isset($snippet['publishedAt'])
                ? date('Y-m-d H:i', strtotime($snippet['publishedAt']))
                : '';
        ?>
        <div class="video">
            <div class="title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ($thumb): ?>
                <img src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php endif; ?>
            <div class="date">公開日：<?= htmlspecialchars($publishedAt, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
