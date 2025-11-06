<?php
require_once __DIR__ . '/config.php';

// --------------------------------------------------
// 1. パラメータ取得
// --------------------------------------------------
$videoId = $_GET['video_id'] ?? '';
$video   = null;
$error   = null;

if ($videoId === '') {
    $error = 'video_id パラメータが指定されていません。';
} else {
    // --------------------------------------------------
    // 2. 対象動画情報を DB から取得
    // --------------------------------------------------
    $sql = "
        SELECT
            external_video_id,
            title,
            description,
            tags,
            view_count,
            like_count,
            comment_count,
            published_at,
            duration_seconds,
            video_type,
            thumbnail_url
        FROM videos
        WHERE external_video_id = :vid
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':vid' => $videoId]);
    $video = $stmt->fetch();

    if (!$video) {
        $error = '指定された動画が見つかりませんでした。（video_id=' . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8') . '）';
    }
}

// --------------------------------------------------
// 3. 指標から簡易インサイト生成（PHPだけでやる軽いコメント）
// --------------------------------------------------
$insights = [];
$promptText = '';

if ($video && !$error) {
    $views    = (int)($video['view_count'] ?? 0);
    $likes    = (int)($video['like_count'] ?? 0);
    $comments = (int)($video['comment_count'] ?? 0);

    $likeRate     = $views > 0 ? $likes / $views : 0;
    $commentRate  = $views > 0 ? $comments / $views : 0;
    $engagement   = $views > 0 ? ($likes + $comments) / $views : 0;

    // 再生数レベル
    if ($views < 100) {
        $insights[] = '再生数はまだ少なめです。タイトルとサムネイルでのクリック率改善を意識しつつ、「誰のどんな悩みを解決する動画か」を明確に打ち出すと伸びやすくなります。';
    } elseif ($views < 1000) {
        $insights[] = '一定の視聴は取れています。関連動画からの流入や検索キーワードとのマッチを意識して、同じテーマでシリーズ化するのも有効です。';
    } else {
        $insights[] = 'この動画は十分な再生数を獲得しています。同じ視聴者層が喜びそうな切り口や続編企画を検討してみましょう。';
    }

    // 高評価率
    if ($likeRate >= 0.06) {
        $insights[] = '高評価率が高く、視聴者満足度はかなり高い印象です。内容の方向性は良さそうなので、タイトル・サムネのABテストでさらなる露出アップを狙えます。';
    } elseif ($likeRate >= 0.03) {
        $insights[] = '高評価率は平均的です。導入部分やサムネ・タイトルで「何が得られる動画か」をもう一段わかりやすくすると、満足度が上がりやすくなります。';
    } else {
        if ($views > 0) {
            $insights[] = '高評価率がやや低めです。サムネやタイトルで期待させている内容と、実際の中身のギャップがないか振り返ってみると改善のヒントが得られます。';
        }
    }

    // コメント率
    if ($commentRate >= 0.01) {
        $insights[] = 'コメント率が高く、視聴者との双方向コミュニケーションがよく機能しています。動画内での「問いかけ」や「意見募集」を引き続き強めていきましょう。';
    } elseif ($views > 200 && $commentRate < 0.003) {
        $insights[] = 'コメントは少なめです。動画の最後に「一言で感想を教えてください」「あなたならどうしますか？」など、具体的なアクションを促してみると良いかもしれません。';
    }

    // 動画タイプ別の一言
    $typeLabel = $video['video_type'] ?? '';
    if ($typeLabel === 'short') {
        $insights[] = 'ショート動画は最初の1〜2秒で引き込めるかが勝負です。最初のカットで「結論」や「一番強いシーン」を見せる構成も検討してみてください。';
    } elseif ($typeLabel === 'long') {
        $insights[] = '通常動画では、導入の30秒で「この動画を見ると何が得られるか」をはっきり提示すると、離脱率を下げやすくなります。';
    } elseif ($typeLabel === 'live') {
        $insights[] = 'ライブ配信はアーカイブ視聴者向けに、概要欄にチャプターや見どころタイムスタンプを追記すると、後からも伸びやすくなります。';
    }

    // --------------------------------------------------
    // 4. LLM（ChatGPT / Geminiなど）に渡すプロンプトテンプレ
    // --------------------------------------------------
    $title       = $video['title'] ?? '';
    $description = $video['description'] ?? '';
    $tags        = $video['tags'] ?? '';
    $publishedAt = $video['published_at'] ?? '';
    $youtubeUrl  = $video['external_video_id']
        ? 'https://www.youtube.com/watch?v=' . $video['external_video_id']
        : '';

    $promptLines = [];
    $promptLines[] = 'あなたはYouTubeチャンネルの成長をサポートするプロのコンサルタントです。';
    $promptLines[] = '以下の動画について、タイトル・サムネイル・タグ・ショート化アイデアなどの改善提案を日本語で具体的に出してください。';
    $promptLines[] = '';
    if ($youtubeUrl !== '') {
        $promptLines[] = '【動画URL】';
        $promptLines[] = $youtubeUrl;
        $promptLines[] = '';
    }
    $promptLines[] = '【現タイトル】';
    $promptLines[] = $title !== '' ? $title : '(タイトル情報なし)';
    $promptLines[] = '';
    $promptLines[] = '【概要欄】';
    $promptLines[] = $description !== '' ? $description : '(概要欄情報なし)';
    $promptLines[] = '';
    $promptLines[] = '【タグ一覧】';
    $promptLines[] = $tags !== '' ? $tags : '(タグ情報なし)';
    $promptLines[] = '';
    $promptLines[] = '【主要な指標（インポート時点）】';
    $promptLines[] = '・再生数: ' . number_format($views) . ' 回';
    $promptLines[] = '・高評価: ' . number_format($likes) . ' 件';
    $promptLines[] = '・コメント: ' . number_format($comments) . ' 件';
    if ($publishedAt) {
        $promptLines[] = '・公開日時: ' . $publishedAt;
    }
    $promptLines[] = '';
    $promptLines[] = '【あなたに依頼したいこと】';
    $promptLines[] = '1. クリック率を上げることを重視した「日本語タイトル案」を5案。';
    $promptLines[] = '2. ターゲット視聴者が検索しそうな「日本語キーワード・タグ案」を10個。';
    $promptLines[] = '3. この動画の中から、60秒以内のショートに切り出せそうな「シーン構成案」を3つ。';
    $promptLines[] = '4. 同じ視聴者層に刺さりそうな「次に作るべき動画ネタ」を3つ。';
    $promptLines[] = '';
    $promptLines[] = '出力は箇条書きで、できるだけ具体的にお願いします。';

    $promptText = implode("\n", $promptLines);
}

// --------------------------------------------------
// 5. AIアドバイスデータをDBから取得（video_ai_adviceテーブル）
// --------------------------------------------------
$aiAdviceList = [];
if ($video && !$error && !empty($videoId)) {
    $sqlAiAdvice = "
        SELECT
            id,
            provider,
            model,
            title_suggestions,
            tag_suggestions,
            short_script,
            improvement_advice,
            strategy_advice,
            raw_response,
            created_at,
            updated_at
        FROM video_ai_advice
        WHERE video_id = :vid
        ORDER BY updated_at DESC
    ";
    $stmt = $pdo->prepare($sqlAiAdvice);
    $stmt->execute([':vid' => $videoId]);
    $aiAdviceList = $stmt->fetchAll();

    // JSON文字列を配列に変換
    foreach ($aiAdviceList as &$advice) {
        if (!empty($advice['title_suggestions'])) {
            $decoded = json_decode($advice['title_suggestions'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $advice['title_suggestions_array'] = $decoded;
            } else {
                $advice['title_suggestions_array'] = [];
            }
        } else {
            $advice['title_suggestions_array'] = [];
        }

        if (!empty($advice['tag_suggestions'])) {
            $decoded = json_decode($advice['tag_suggestions'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $advice['tag_suggestions_array'] = $decoded;
            } else {
                $advice['tag_suggestions_array'] = [];
            }
        } else {
            $advice['tag_suggestions_array'] = [];
        }
    }
    unset($advice);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>動画別AIアドバイス - ChannelScope</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="layout">

    <header>
        <h1>
            <span class="logo-dot"></span>
            ChannelScope - 動画別AIアドバイス
        </h1>
        <div class="env">
            <a href="index.php" class="yt-link">&larr; ダッシュボードに戻る</a>
        </div>
    </header>

    <?php if ($error): ?>
        <section class="panel card">
            <h2>
                <span class="label">ERROR</span>
                <span class="badge">動画取得エラー</span>
            </h2>
            <div style="font-size:13px; color: var(--text-sub);">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </section>
    <?php else: ?>
        <!-- 動画の概要 -->
        <section class="panel card">
            <h2>
                <span class="label">VIDEO</span>
                <span class="badge">対象動画</span>
            </h2>
            <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                <div style="width:220px; max-width:100%;">
                    <?php if (!empty($video['thumbnail_url'])): ?>
                        <img src="<?php echo htmlspecialchars($video['thumbnail_url'], ENT_QUOTES, 'UTF-8'); ?>"
                             alt=""
                             style="width:100%; border-radius:10px; display:block;">
                    <?php endif; ?>
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:15px; font-weight:600; margin-bottom:6px;">
                        <?php echo htmlspecialchars($video['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div style="font-size:12px; color:var(--text-sub); margin-bottom:4px;">
                        再生: <?php echo number_format((int)$video['view_count']); ?> 回 ／
                        高評価: <?php echo number_format((int)$video['like_count']); ?> 件 ／
                        コメント: <?php echo number_format((int)$video['comment_count']); ?> 件
                    </div>
                    <div style="font-size:11px; color:var(--text-sub); margin-bottom:4px;">
                        <?php if (!empty($video['published_at'])): ?>
                            公開日:
                            <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($video['published_at'])), ENT_QUOTES, 'UTF-8'); ?>
                            ／
                        <?php endif; ?>
                        種別:
                        <?php
                        $t = $video['video_type'] ?? '';
                        $typeLabelMap = [
                            'short'   => 'ショート',
                            'long'    => '通常動画',
                            'live'    => 'ライブ配信',
                            'other'   => 'その他',
                            'unknown' => '不明',
                        ];
                        echo htmlspecialchars($typeLabelMap[$t] ?? ($t ?: '不明'), ENT_QUOTES, 'UTF-8');
                        ?>
                    </div>
                    <?php if (!empty($video['external_video_id'])): ?>
                        <div style="font-size:11px; margin-bottom:6px;">
                            <a class="yt-link"
                               href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($video['external_video_id'], ENT_QUOTES, 'UTF-8'); ?>"
                               target="_blank" rel="noopener noreferrer">
                                YouTubeで動画を開く
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($video['tags'])): ?>
                        <div style="font-size:11px; color:var(--text-sub); margin-top:4px;">
                            タグ:
                            <?php
                            $tags = array_filter(array_map('trim', explode(',', $video['tags'])));
                            foreach ($tags as $tag) {
                                if ($tag === '') continue;
                                echo '<span class="tag">#' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</span> ';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ChannelScope からの簡易インサイト -->
        <section class="panel card">
            <h2>
                <span class="label">INSIGHTS</span>
                <span class="badge">ChannelScopeの見立て</span>
            </h2>
            <?php if (empty($insights)): ?>
                <div style="font-size:12px; color:var(--text-sub);">
                    この動画について自動生成できるコメントがありません。指標が少ないか、まだ十分なデータがない可能性があります。
                </div>
            <?php else: ?>
                <ul class="advice-list">
                    <?php foreach ($insights as $line): ?>
                        <li><?php echo $line; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- 保存済みAIアドバイス -->
        <?php if (!empty($aiAdviceList)): ?>
            <?php foreach ($aiAdviceList as $advice): ?>
                <section class="panel card">
                    <h2>
                        <span class="label">AI ADVICE</span>
                        <span class="badge">
                            <?php echo htmlspecialchars(strtoupper($advice['provider']), ENT_QUOTES, 'UTF-8'); ?>
                            (<?php echo htmlspecialchars($advice['model'], ENT_QUOTES, 'UTF-8'); ?>)
                        </span>
                    </h2>
                    <div style="font-size:11px; color:var(--text-sub); margin-bottom:12px;">
                        最終更新: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($advice['updated_at'])), ENT_QUOTES, 'UTF-8'); ?>
                    </div>

                    <?php if (!empty($advice['title_suggestions_array'])): ?>
                        <div style="margin-bottom:16px;">
                            <h3 style="font-size:13px; font-weight:600; margin-bottom:6px; color:var(--accent-strong);">
                                📝 タイトル案
                            </h3>
                            <ul class="advice-list">
                                <?php foreach ($advice['title_suggestions_array'] as $title): ?>
                                    <li><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($advice['tag_suggestions_array'])): ?>
                        <div style="margin-bottom:16px;">
                            <h3 style="font-size:13px; font-weight:600; margin-bottom:6px; color:var(--accent-strong);">
                                🏷️ タグ案
                            </h3>
                            <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                <?php foreach ($advice['tag_suggestions_array'] as $tag): ?>
                                    <span class="tag">#<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($advice['short_script'])): ?>
                        <div style="margin-bottom:16px;">
                            <h3 style="font-size:13px; font-weight:600; margin-bottom:6px; color:var(--accent-strong);">
                                🎬 ショート動画構成案
                            </h3>
                            <div style="font-size:12px; color:var(--text-main); white-space:pre-wrap; line-height:1.6;">
                                <?php echo nl2br(htmlspecialchars($advice['short_script'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($advice['improvement_advice'])): ?>
                        <div style="margin-bottom:16px;">
                            <h3 style="font-size:13px; font-weight:600; margin-bottom:6px; color:var(--accent-strong);">
                                💡 改善ポイント
                            </h3>
                            <div style="font-size:12px; color:var(--text-main); white-space:pre-wrap; line-height:1.6;">
                                <?php echo nl2br(htmlspecialchars($advice['improvement_advice'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($advice['strategy_advice'])): ?>
                        <div style="margin-bottom:16px;">
                            <h3 style="font-size:13px; font-weight:600; margin-bottom:6px; color:var(--accent-strong);">
                                🎯 チャンネル戦略提案
                            </h3>
                            <div style="font-size:12px; color:var(--text-main); white-space:pre-wrap; line-height:1.6;">
                                <?php echo nl2br(htmlspecialchars($advice['strategy_advice'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($advice['title_suggestions_array']) && empty($advice['tag_suggestions_array']) && empty($advice['short_script']) && empty($advice['improvement_advice']) && empty($advice['strategy_advice'])): ?>
                        <div style="font-size:12px; color:var(--text-sub);">
                            このAIアドバイスにはまだ詳細な内容が保存されていません。
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- LLM向けプロンプトテンプレ -->
        <section class="panel card">
            <h2>
                <span class="label">AI PROMPT</span>
                <span class="badge">ChatGPT / Gemini 用</span>
            </h2>
            <div style="font-size:12px; color:var(--text-sub); margin-bottom:6px;">
                下のテキストを <strong>そのままコピーして、ChatGPT や Gemini</strong> に貼り付けると、<br>
                「タイトル案 / タグ案 / ショート化アイデア / 次の動画ネタ」まで含めた提案を一気に受け取れます。<br>
                取得した結果は <code>api/ai_advice_api.php</code> にPOSTして保存できます。
            </div>
            <textarea
                style="width:100%; min-height:260px; font-size:12px; font-family:monospace; padding:8px; border-radius:8px; border:1px solid var(--border-soft); background:#020617; color:var(--text-main);"
                readonly
                onclick="this.select();"
            ><?php echo htmlspecialchars($promptText, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div style="font-size:11px; color:var(--text-sub); margin-top:4px;">
                ※クリックすると全選択されます。Ctrl+C / Cmd+C でコピーしてご利用ください。
            </div>
        </section>

    <?php endif; ?>

    <footer>
        ChannelScope &copy; <?php echo date('Y'); ?>
    </footer>
</div>
</body>
</html>
