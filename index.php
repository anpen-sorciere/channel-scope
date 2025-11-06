<?php
require_once __DIR__ . '/config.php';

// -------------------------------------
// 0. 期間フィルタ（7日 / 30日 / 全期間）
// -------------------------------------
$range = $_GET['range'] ?? '7d'; // デフォルトは直近7日

// 想定外の値が来たら7dに戻す
if (!in_array($range, ['7d', '30d', 'all'], true)) {
    $range = '7d';
}

$days = null; // null = 全期間
if ($range === '7d') {
    $days = 7;
} elseif ($range === '30d') {
    $days = 30;
}
$daysInt = $days !== null ? (int)$days : null;

// -------------------------------------
// 1. サマリー情報取得（全期間）
// -------------------------------------
$sqlSummary = "
    SELECT 
        COUNT(*) AS video_count,
        COALESCE(SUM(view_count), 0) AS total_views,
        MAX(published_at) AS last_published_at
    FROM videos
";
$stmt = $pdo->query($sqlSummary);
$summary = $stmt->fetch() ?: [
    'video_count' => 0,
    'total_views' => 0,
    'last_published_at' => null,
];

$lastPublishedDisplay = $summary['last_published_at']
    ? date('Y-m-d H:i', strtotime($summary['last_published_at']))
    : 'データなし';

// -------------------------------------
// 2. 最新動画一覧（20件・全期間）
// -------------------------------------
$sqlLatest = "
    SELECT 
        external_video_id,
        title,
        view_count,
        like_count,
        comment_count,
        published_at,
        tags,
        thumbnail_url
    FROM videos
    ORDER BY published_at DESC
    LIMIT 20
";
$stmt = $pdo->query($sqlLatest);
$latestVideos = $stmt->fetchAll();

// -------------------------------------
// 2-a. ランキング：再生数TOP10（期間フィルタ反映）
// -------------------------------------
$sqlTopViews = "
    SELECT 
        external_video_id,
        title,
        view_count,
        like_count,
        comment_count,
        published_at,
        thumbnail_url
    FROM videos
    WHERE published_at IS NOT NULL
";
if ($daysInt !== null) {
    $sqlTopViews .= " AND published_at >= DATE_SUB(CURDATE(), INTERVAL {$daysInt} DAY) ";
}
$sqlTopViews .= "
    ORDER BY view_count DESC
    LIMIT 10
";
$stmt = $pdo->query($sqlTopViews);
$topViewsVideos = $stmt->fetchAll();

// -------------------------------------
// 2-b. ランキング：伸び率TOP10（期間フィルタ反映）
// 伸び率 = view_count ÷ 公開からの日数（最低1日で計算）
// -------------------------------------
$sqlTopGrowth = "
    SELECT
        external_video_id,
        title,
        view_count,
        like_count,
        comment_count,
        published_at,
        thumbnail_url,
        GREATEST(DATEDIFF(CURDATE(), published_at), 1) AS days_since,
        (view_count / GREATEST(DATEDIFF(CURDATE(), published_at), 1)) AS growth_score
    FROM videos
    WHERE published_at IS NOT NULL
";
if ($daysInt !== null) {
    $sqlTopGrowth .= " AND published_at >= DATE_SUB(CURDATE(), INTERVAL {$daysInt} DAY) ";
}
$sqlTopGrowth .= "
    ORDER BY growth_score DESC
    LIMIT 10
";
$stmt = $pdo->query($sqlTopGrowth);
$topGrowthVideos = $stmt->fetchAll();

// -------------------------------------
// 3. グラフ用データ（日別集計）
// -------------------------------------
$sqlDaily = "
    SELECT
        DATE(published_at) AS date,
        SUM(view_count) AS total_views,
        SUM(like_count)  AS total_likes
    FROM videos
    WHERE published_at IS NOT NULL
";
if ($daysInt !== null) {
    $sqlDaily .= " AND published_at >= DATE_SUB(CURDATE(), INTERVAL {$daysInt} DAY) ";
}
$sqlDaily .= "
    GROUP BY DATE(published_at)
    ORDER BY date ASC
";
$stmt = $pdo->query($sqlDaily);
$dailyRows = $stmt->fetchAll();

$labels = [];
$viewSeries = [];
$likeSeries = [];

foreach ($dailyRows as $row) {
    $labels[]      = $row['date']; // 'YYYY-MM-DD'
    $viewSeries[]  = (int)($row['total_views'] ?? 0);
    $likeSeries[]  = (int)($row['total_likes'] ?? 0);
}

$chartData = [
    'labels' => $labels,
    'views'  => $viewSeries,
    'likes'  => $likeSeries,
];

// -------------------------------------
// 4. 曜日別集計（期間フィルタ反映）
// -------------------------------------
$sqlWeekday = "
    SELECT
        DAYOFWEEK(published_at) AS dow,  -- 1=日曜, 7=土曜
        COUNT(*) AS video_count,
        AVG(view_count) AS avg_views,
        SUM(view_count) AS total_views
    FROM videos
    WHERE published_at IS NOT NULL
";
if ($daysInt !== null) {
    $sqlWeekday .= " AND published_at >= DATE_SUB(CURDATE(), INTERVAL {$daysInt} DAY) ";
}
$sqlWeekday .= "
    GROUP BY DAYOFWEEK(published_at)
    ORDER BY dow
";
$stmt = $pdo->query($sqlWeekday);
$weekdayRows = $stmt->fetchAll();

$dowLabels       = ['日', '月', '火', '水', '木', '金', '土'];
$weekdayLabels   = [];
$weekdayAvgViews = [];
$weekdayCounts   = [];

foreach ($weekdayRows as $row) {
    $dowIndex = (int)$row['dow'] - 1; // 1〜7 → 0〜6
    $label = $dowLabels[$dowIndex] ?? ('?(' . $row['dow'] . ')');
    $weekdayLabels[]   = $label;
    $weekdayAvgViews[] = (float)$row['avg_views'];
    $weekdayCounts[]   = (int)$row['video_count'];
}

$weekdayData = [
    'labels'      => $weekdayLabels,
    'avgViews'    => $weekdayAvgViews,
    'videoCounts' => $weekdayCounts,
];

// -------------------------------------
// 5. 時間帯別集計（期間フィルタ反映）
// -------------------------------------
$sqlTimeBand = "
    SELECT
        CASE
            WHEN HOUR(published_at) BETWEEN 0 AND 5  THEN 0
            WHEN HOUR(published_at) BETWEEN 6 AND 11 THEN 1
            WHEN HOUR(published_at) BETWEEN 12 AND 17 THEN 2
            WHEN HOUR(published_at) BETWEEN 18 AND 23 THEN 3
        END AS band,
        COUNT(*) AS video_count,
        AVG(view_count) AS avg_views,
        SUM(view_count) AS total_views
    FROM videos
    WHERE published_at IS NOT NULL
";
if ($daysInt !== null) {
    $sqlTimeBand .= " AND published_at >= DATE_SUB(CURDATE(), INTERVAL {$daysInt} DAY) ";
}
$sqlTimeBand .= "
    GROUP BY band
    ORDER BY band
";
$stmt = $pdo->query($sqlTimeBand);
$timeBandRows = $stmt->fetchAll();

$timeBandLabels   = ['深夜(0-5)', '朝〜午前(6-11)', '昼〜夕方(12-17)', '夜(18-23)'];
$timeBandAvgViews = [0, 0, 0, 0];
$timeBandCounts   = [0, 0, 0, 0];

foreach ($timeBandRows as $row) {
    $band = $row['band'];
    if ($band === null) continue;
    $idx = (int)$band;
    if (!isset($timeBandLabels[$idx])) continue;
    $timeBandAvgViews[$idx] = (float)$row['avg_views'];
    $timeBandCounts[$idx]   = (int)$row['video_count'];
}

$timeBandData = [
    'labels'      => $timeBandLabels,
    'avgViews'    => $timeBandAvgViews,
    'videoCounts' => $timeBandCounts,
];

// -------------------------------------
// 6. 曜日×時間帯ヒートマップ用集計
// -------------------------------------
$sqlHeatmap = "
    SELECT
        DAYOFWEEK(published_at) AS dow,
        CASE
            WHEN HOUR(published_at) BETWEEN 0 AND 5  THEN 0
            WHEN HOUR(published_at) BETWEEN 6 AND 11 THEN 1
            WHEN HOUR(published_at) BETWEEN 12 AND 17 THEN 2
            WHEN HOUR(published_at) BETWEEN 18 AND 23 THEN 3
        END AS band,
        COUNT(*) AS video_count,
        AVG(view_count) AS avg_views
    FROM videos
    WHERE published_at IS NOT NULL
";
if ($daysInt !== null) {
    $sqlHeatmap .= " AND published_at >= DATE_SUB(CURDATE(), INTERVAL {$daysInt} DAY) ";
}
$sqlHeatmap .= "
    GROUP BY DAYOFWEEK(published_at), band
    ORDER BY dow, band
";
$stmt = $pdo->query($sqlHeatmap);
$heatmapRows = $stmt->fetchAll();

// ヒートマップ用配列 [dow][band]
$heatmap = [];
$heatmapMaxAvg = 0.0;

// 初期化
for ($d = 1; $d <= 7; $d++) {
    for ($b = 0; $b <= 3; $b++) {
        $heatmap[$d][$b] = [
            'avg'   => 0.0,
            'count' => 0,
        ];
    }
}

foreach ($heatmapRows as $row) {
    $dow  = (int)$row['dow'];     // 1〜7
    $band = (int)$row['band'];    // 0〜3
    if ($dow < 1 || $dow > 7) continue;
    if ($band < 0 || $band > 3) continue;

    $avg   = (float)$row['avg_views'];
    $count = (int)$row['video_count'];

    $heatmap[$dow][$band]['avg']   = $avg;
    $heatmap[$dow][$band]['count'] = $count;

    if ($avg > $heatmapMaxAvg) {
        $heatmapMaxAvg = $avg;
    }
}

if ($heatmapMaxAvg <= 0) {
    $heatmapMaxAvg = 1; // ゼロ除算回避
}

// 期間内の総動画本数（アドバイス用）
$totalVideosInRange = 0;
for ($d = 1; $d <= 7; $d++) {
    for ($b = 0; $b <= 3; $b++) {
        $totalVideosInRange += $heatmap[$d][$b]['count'];
    }
}

// -------------------------------------
// 7. タグ別パフォーマンス集計（期間フィルタ反映）
// -------------------------------------
$sqlTags = "
    SELECT
        tags,
        view_count
    FROM videos
    WHERE tags IS NOT NULL
      AND tags <> ''
      AND published_at IS NOT NULL
";
if ($daysInt !== null) {
    $sqlTags .= " AND published_at >= DATE_SUB(CURDATE(), INTERVAL {$daysInt} DAY) ";
}
$stmt = $pdo->query($sqlTags);
$tagRows = $stmt->fetchAll();

$tagStats = []; // tag => ['count' => n, 'total_views' => x]

foreach ($tagRows as $row) {
    $tagsRaw = $row['tags'] ?? '';
    $views   = (int)$row['view_count'];

    $tagsArray = array_filter(array_map('trim', explode(',', $tagsRaw)));
    foreach ($tagsArray as $tag) {
        if ($tag === '') continue;
        $key = $tag;
        if (!isset($tagStats[$key])) {
            $tagStats[$key] = [
                'tag'         => $tag,
                'count'       => 0,
                'total_views' => 0,
            ];
        }
        $tagStats[$key]['count']++;
        $tagStats[$key]['total_views'] += $views;
    }
}

$topTags = [];
if (!empty($tagStats)) {
    foreach ($tagStats as $t) {
        $count = max(1, (int)$t['count']);
        $t['avg_views'] = $t['total_views'] / $count;
        $topTags[] = $t;
    }
    usort($topTags, function ($a, $b) {
        if ($a['avg_views'] == $b['avg_views']) return 0;
        return ($a['avg_views'] > $b['avg_views']) ? -1 : 1;
    });
    $topTags = array_slice($topTags, 0, 10);
}

$bestTag = $topTags[0] ?? null;

// -------------------------------------
// 8. アドバイス生成
// -------------------------------------
$adviceLines = [];

// 8-1. 一番強い曜日
$bestWeekdayLabel = null;
$bestWeekdayAvg   = 0;
if (!empty($weekdayData['labels'])) {
    foreach ($weekdayData['labels'] as $i => $label) {
        $avg   = $weekdayData['avgViews'][$i] ?? 0;
        $count = $weekdayData['videoCounts'][$i] ?? 0;
        if ($count <= 0) continue;
        if ($avg > $bestWeekdayAvg) {
            $bestWeekdayAvg   = $avg;
            $bestWeekdayLabel = $label;
        }
    }
    if ($bestWeekdayLabel !== null) {
        $adviceLines[] = "この期間では<strong>{$bestWeekdayLabel}曜日</strong>に公開した動画が平均再生数で最も強い傾向があります。新作はまず {$bestWeekdayLabel}曜日 の投稿を優先してみましょう。";
    }
}

// 8-2. 一番強い時間帯
$bestBandLabel = null;
$bestBandAvg   = 0;
foreach ($timeBandData['labels'] as $i => $label) {
    $avg   = $timeBandData['avgViews'][$i] ?? 0;
    $count = $timeBandData['videoCounts'][$i] ?? 0;
    if ($count <= 0) continue;
    if ($avg > $bestBandAvg) {
        $bestBandAvg   = $avg;
        $bestBandLabel = $label;
    }
}
if ($bestBandLabel !== null) {
    $adviceLines[] = "時間帯では<strong>{$bestBandLabel}</strong>に公開した動画の伸びが良いようです。同じテイストの動画はこの時間帯に集中させるとパフォーマンスが安定しやすくなります。";
}

// 8-3. 投稿頻度
if ($daysInt !== null && $daysInt > 0 && $totalVideosInRange > 0) {
    $perWeek = $totalVideosInRange / $daysInt * 7;
    $perWeekRounded = round($perWeek, 1);
    if ($perWeek < 1) {
        $adviceLines[] = "この期間の投稿ペースは<strong>週あたり {$perWeekRounded} 本程度</strong>です。まずは週1本以上を目標に、少しずつ本数を増やしていけると分析の精度も上がります。";
    } elseif ($perWeek < 3) {
        $adviceLines[] = "この期間の投稿ペースは<strong>週あたり {$perWeekRounded} 本</strong>です。視聴維持を考えると、週2〜3本ペースをキープできると安定して視聴者にリーチしやすくなります。";
    } else {
        $adviceLines[] = "この期間の投稿ペースは<strong>週あたり {$perWeekRounded} 本</strong>と十分な頻度です。次のステップとして、サムネイルやタイトルのテストを行い、1本あたりの伸びを高めるフェーズに入っても良さそうです。";
    }
}

// 8-4. タグの傾向
if ($bestTag !== null) {
    $tagName  = $bestTag['tag'];
    $tagCount = (int)$bestTag['count'];
    $tagAvg   = (int)round($bestTag['avg_views']);
    $adviceLines[] = "タグでは<strong>#{$tagName}</strong> が強く、{$tagCount} 本の動画で平均 {$tagAvg} 回再生されています。今後も同じコンセプトの動画には、このタグを軸に関連タグを組み合わせると良さそうです。";
}

// 8-5. データが少ないときの補足
if (empty($adviceLines)) {
    $adviceLines[] = "まだ十分なデータがたまっていないため、明確な傾向は出ていません。まずは継続して動画を投稿しつつ、直近30日間の傾向を少しずつ見ていきましょう。";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ChannelScope ダッシュボード</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSS 外部ファイル -->
    <link rel="stylesheet" href="css/style.css">

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="layout">

    <header>
        <h1>
            <span class="logo-dot"></span>
            ChannelScope Dashboard
        </h1>
        <div class="env">
            環境:
            <strong>
                <?php
                if (defined('ENVIRONMENT')) {
                    echo htmlspecialchars(ENVIRONMENT, ENT_QUOTES, 'UTF-8');
                } else {
                    echo 'unknown';
                }
                ?>
            </strong>
            <?php if (!empty($baseUrl)): ?>
                <br>
                ベースURL:
                <a class="yt-link"
                   href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>"
                   target="_blank" rel="noopener noreferrer">
                    <?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- サマリー -->
    <section class="grid-summary">
        <div class="card">
            <h2>
                <span class="label">VIDEOS</span>
                <span class="badge">総本数</span>
            </h2>
            <div class="value">
                <?php echo number_format((int)$summary['video_count']); ?>
            </div>
            <div class="sub">インポート済みの動画本数</div>
        </div>
        <div class="card">
            <h2>
                <span class="label">VIEWS</span>
                <span class="badge">総再生数</span>
            </h2>
            <div class="value">
                <?php echo number_format((int)$summary['total_views']); ?>
            </div>
            <div class="sub">videosテーブルに保存されている合計再生数</div>
        </div>
        <div class="card">
            <h2>
                <span class="label">LAST UPLOAD</span>
                <span class="badge">最終投稿日</span>
            </h2>
            <div class="value" style="font-size:16px;">
                <?php echo htmlspecialchars($lastPublishedDisplay, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="sub">最新動画の公開日時</div>
        </div>
    </section>

    <!-- 推移グラフ -->
    <section class="panel card">
        <div class="panel-header">
            <div>
                <div class="panel-title">再生数 / 高評価 推移</div>
                <div class="panel-sub">
                    日付ごとの合計値（全動画）  
                    （表示範囲:
                    <?php
                    if ($daysInt === 7) {
                        echo '直近7日';
                    } elseif ($daysInt === 30) {
                        echo '直近30日';
                    } else {
                        echo '全期間';
                    }
                    ?>）
                </div>
            </div>
            <div class="filter-group">
                <a class="filter-button <?php echo ($range === '7d') ? 'active' : ''; ?>"
                   href="?range=7d">直近7日</a>
                <a class="filter-button <?php echo ($range === '30d') ? 'active' : ''; ?>"
                   href="?range=30d">直近30日</a>
                <a class="filter-button <?php echo ($range === 'all') ? 'active' : ''; ?>"
                   href="?range=all">全期間</a>
            </div>
        </div>
        <div class="chart-wrapper">
            <canvas id="trendChart"></canvas>
        </div>
    </section>

    <!-- 曜日別分析 -->
    <section class="panel card">
        <div class="panel-header">
            <div>
                <div class="panel-title">曜日別 平均再生数</div>
                <div class="panel-sub">
                    公開日の曜日ごとの平均再生数  
                    （現在の期間フィルタ:
                    <?php
                    if ($daysInt === 7) {
                        echo '直近7日';
                    } elseif ($daysInt === 30) {
                        echo '直近30日';
                    } else {
                        echo '全期間';
                    }
                    ?>）
                </div>
            </div>
        </div>
        <div class="weekday-chart-wrapper">
            <canvas id="weekdayChart"></canvas>
        </div>
    </section>

    <!-- 時間帯分析 ＋ ヒートマップ -->
    <section class="panel card">
        <div class="panel-header">
            <div>
                <div class="panel-title">投稿時間帯 分析</div>
                <div class="panel-sub">
                    時間帯別の平均再生数 と 曜日×時間帯ヒートマップ  
                    （現在の期間フィルタ:
                    <?php
                    if ($daysInt === 7) {
                        echo '直近7日';
                    } elseif ($daysInt === 30) {
                        echo '直近30日';
                    } else {
                        echo '全期間';
                    }
                    ?>）
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: minmax(0,1.2fr) minmax(0,1.3fr); gap:16px;">
            <!-- 時間帯別バーグラフ -->
            <div>
                <div class="timeband-chart-wrapper">
                    <canvas id="timeBandChart"></canvas>
                </div>
            </div>
            <!-- ヒートマップ（曜日×時間帯） -->
            <div style="overflow-x:auto;">
                <table class="heatmap-table">
                    <thead>
                        <tr>
                            <th>曜日＼時間帯</th>
                            <?php foreach ($timeBandLabels as $label): ?>
                                <th><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($d = 1; $d <= 7; $d++):
                            $dowLabel = $dowLabels[$d - 1];
                        ?>
                            <tr>
                                <th><?php echo htmlspecialchars($dowLabel, ENT_QUOTES, 'UTF-8'); ?></th>
                                <?php for ($b = 0; $b <= 3; $b++):
                                    $cell = $heatmap[$d][$b];
                                    $avg   = $cell['avg'];
                                    $count = $cell['count'];
                                    $ratio = $avg > 0 ? $avg / $heatmapMaxAvg : 0;
                                    $alpha = 0.05 + 0.8 * $ratio;
                                    $alpha = max(0.05, min(0.85, $alpha));
                                ?>
                                    <td class="heatmap-cell"
                                        style="background-color: rgba(56, 189, 248, <?php echo number_format($alpha, 2); ?>);">
                                        <div>
                                            <?php echo $avg > 0 ? number_format($avg, 0) . '回' : '-'; ?>
                                        </div>
                                        <div class="heatmap-meta">
                                            <?php echo $count; ?>本
                                        </div>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- タグ別パフォーマンス -->
    <section class="panel card">
        <div class="panel-header">
            <div>
                <div class="panel-title">タグ別パフォーマンス</div>
                <div class="panel-sub">
                    この期間に使用されたタグごとの平均再生数（上位10タグ）  
                    （期間フィルタ:
                    <?php
                    if ($daysInt === 7) {
                        echo '直近7日';
                    } elseif ($daysInt === 30) {
                        echo '直近30日';
                    } else {
                        echo '全期間';
                    }
                    ?>）
                </div>
            </div>
        </div>

        <?php if (empty($topTags)): ?>
            <div style="font-size:12px; color:var(--text-sub);">
                この期間にタグ情報を持つ動画がほとんどないため、タグ別の分析結果を表示できません。
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="tag-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>タグ</th>
                            <th>動画本数</th>
                            <th>合計再生数</th>
                            <th>平均再生数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topTags as $i => $tagInfo): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo '#' . htmlspecialchars($tagInfo['tag'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo number_format((int)$tagInfo['count']); ?>本</td>
                                <td><?php echo number_format((int)$tagInfo['total_views']); ?>回</td>
                                <td><?php echo number_format((int)$tagInfo['avg_views']); ?>回</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- 最新動画一覧 -->
    <section class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">最新動画（20件）</div>
                <div class="panel-sub">インポートしたデータから最新順に表示</div>
            </div>
        </div>

        <div class="videos-grid">
            <div class="video-list">
                <?php if (empty($latestVideos)): ?>
                    <div class="card">
                        まだ動画データがありません。youtube_import.php を実行してください。
                    </div>
                <?php else: ?>
                    <?php foreach ($latestVideos as $video): ?>
                        <div class="video-item">
                            <div class="video-thumb">
                                <?php if (!empty($video['thumbnail_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($video['thumbnail_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                <?php endif; ?>
                            </div>
                            <div class="video-meta">
                                <div class="video-title" title="<?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="video-stats">
                                    <span>再生: <?php echo number_format((int)$video['view_count']); ?></span>
                                    <span>高評価: <?php echo number_format((int)$video['like_count']); ?></span>
                                    <span>コメント: <?php echo number_format((int)$video['comment_count']); ?></span>
                                </div>
                                <div class="video-tags">
                                    <?php
                                    if (!empty($video['tags'])) {
                                        $tags = array_filter(array_map('trim', explode(',', $video['tags'])));
                                        foreach ($tags as $tag) {
                                            if ($tag === '') continue;
                                            echo '<span class="tag">#' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="video-date">
                                    公開日:
                                    <?php
                                    echo $video['published_at']
                                        ? htmlspecialchars(date('Y-m-d H:i', strtotime($video['published_at'])), ENT_QUOTES, 'UTF-8')
                                        : '不明';
                                    ?>
                                    <?php if (!empty($video['external_video_id'])): ?>
                                        ・
                                        <a class="yt-link"
                                           href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($video['external_video_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                           target="_blank" rel="noopener noreferrer">
                                            YouTubeで開く
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- サイド：簡易メモ -->
            <div class="card">
                <h2>
                    <span class="label">NOTES</span>
                    <span class="badge">使い方</span>
                </h2>
                <div style="font-size:12px; color: var(--text-sub); line-height:1.6;">
                    ・上部のボタンで表示期間（7日 / 30日 / 全期間）を切り替えできます。<br>
                    ・この期間フィルタは「推移グラフ」「曜日分析」「時間帯分析」「タグ分析」「ランキング」に反映されています。<br>
                    ・ヒートマップは「色が濃いほど平均再生数が高い」ことを示します（数字は平均再生数と動画本数）。<br>
                    ・タグ別パフォーマンスでは、この期間によく使われたタグの強さを比較できます。<br>
                    ・新しい動画を追加したら、<code>youtube_import.php</code> を再実行すると反映されます。<br>
                </div>
            </div>
        </div>
    </section>

    <!-- ランキング -->
    <section class="panel card" style="margin-top: 12px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">チャンネルランキング</div>
                <div class="panel-sub">
                    再生数TOP10 と 1日あたりの伸び率TOP10  
                    （現在の期間フィルタ:
                    <?php
                    if ($daysInt === 7) {
                        echo '直近7日';
                    } elseif ($daysInt === 30) {
                        echo '直近30日';
                    } else {
                        echo '全期間';
                    }
                    ?> の公開動画を対象）
                </div>
            </div>
        </div>

        <div class="ranking-grid">
            <!-- 再生数TOP10 -->
            <div>
                <div class="ranking-block-title">🏆 再生数TOP10</div>
                <?php if (empty($topViewsVideos)): ?>
                    <div class="video-date">データがありません。</div>
                <?php else: ?>
                    <ol class="ranking-list">
                        <?php foreach ($topViewsVideos as $index => $video): ?>
                            <li class="ranking-item">
                                <div class="ranking-rank">
                                    <span><?php echo $index + 1; ?></span>
                                </div>
                                <div class="ranking-main">
                                    <div class="ranking-title" title="<?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div class="ranking-meta">
                                        再生: <?php echo number_format((int)$video['view_count']); ?>
                                        ／ 高評価: <?php echo number_format((int)$video['like_count']); ?><br>
                                        <?php if (!empty($video['published_at'])): ?>
                                            公開日: <?php echo htmlspecialchars(date('Y-m-d', strtotime($video['published_at'])), ENT_QUOTES, 'UTF-8'); ?> ・
                                        <?php endif; ?>
                                        <?php if (!empty($video['external_video_id'])): ?>
                                            <a class="yt-link"
                                               href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($video['external_video_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                               target="_blank" rel="noopener noreferrer">
                                                YouTubeで開く
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

            <!-- 伸び率TOP10 -->
            <div>
                <div class="ranking-block-title">📈 伸び率TOP10（1日あたり）</div>
                <?php if (empty($topGrowthVideos)): ?>
                    <div class="video-date">データがありません。</div>
                <?php else: ?>
                    <ol class="ranking-list">
                        <?php foreach ($topGrowthVideos as $index => $video): ?>
                            <li class="ranking-item">
                                <div class="ranking-rank">
                                    <span><?php echo $index + 1; ?></span>
                                </div>
                                <div class="ranking-main">
                                    <div class="ranking-title" title="<?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div class="ranking-meta">
                                        再生: <?php echo number_format((int)$video['view_count']); ?>
                                        ／ 1日あたり: 
                                        <?php
                                        $daysSince = (int)($video['days_since'] ?? 1);
                                        $growth = $daysSince > 0 ? $video['growth_score'] : 0;
                                        echo number_format($growth, 1);
                                        ?>
                                        回/日<br>
                                        <?php if (!empty($video['published_at'])): ?>
                                            公開日: <?php echo htmlspecialchars(date('Y-m-d', strtotime($video['published_at'])), ENT_QUOTES, 'UTF-8'); ?> ・
                                        <?php endif; ?>
                                        <?php if (!empty($video['external_video_id'])): ?>
                                            <a class="yt-link"
                                               href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($video['external_video_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                               target="_blank" rel="noopener noreferrer">
                                                YouTubeで開く
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- アドバイスボックス -->
    <section class="panel card" style="margin-top:12px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">ChannelScope からのひとことアドバイス</div>
                <div class="panel-sub">
                    現在の期間設定にもとづいて、簡易的な改善ポイントをコメントします。
                </div>
            </div>
        </div>
        <ul class="advice-list">
            <?php foreach ($adviceLines as $line): ?>
                <li><?php echo $line; ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <footer>
        ChannelScope &copy; <?php echo date('Y'); ?>
    </footer>
</div>

<!-- Chart用データを data-* 属性に埋め込む -->
<div id="js-data"
     data-chart="<?php echo htmlspecialchars(json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
     data-weekday="<?php echo htmlspecialchars(json_encode($weekdayData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
     data-timeband="<?php echo htmlspecialchars(json_encode($timeBandData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
     style="display:none;"></div>

<!-- 外部JS -->
<script src="js/dashboard.js"></script>
</body>
</html>
