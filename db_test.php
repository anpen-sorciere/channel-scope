<?php
// DB設定 & 環境切り替え読み込み
require_once __DIR__ . '/config.php';

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ChannelScope DB接続テスト</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .card {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            padding: 16px 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        h1 {
            font-size: 20px;
            margin-top: 0;
        }
        .env {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }
        pre {
            background: #111;
            color: #0f0;
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            overflow-x: auto;
        }
        .ok {
            color: #0a7f2e;
            font-weight: bold;
        }
        .error {
            color: #c62828;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>ChannelScope DB接続テスト</h1>

    <div class="env">
        現在の環境：
        <strong>
            <?php
            if (defined('ENVIRONMENT')) {
                echo htmlspecialchars(ENVIRONMENT, ENT_QUOTES, 'UTF-8');
            } else {
                echo '（ENVIRONMENT 未定義）';
            }
            ?>
        </strong><br>
        サーバー名：
        <code><?php echo htmlspecialchars($_SERVER['SERVER_NAME'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code>
    </div>

    <?php
    echo '<pre>';

    echo "DB接続テスト開始...\n\n";

    try {
        // $pdo が存在するかチェック
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new Exception('PDOインスタンス($pdo)が見つかりません。configファイルの設定を確認してください。');
        }

        // 接続確認を兼ねてテーブル一覧を取得
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_NUM);

        echo "✅ <接続成功> PDOでデータベースに接続できました。\n\n";
        echo "テーブル一覧:\n";

        if (count($tables) === 0) {
            echo "（テーブルが1つもありません）\n";
        } else {
            foreach ($tables as $row) {
                // SHOW TABLES の結果は 0番目にテーブル名が入る
                echo " - " . $row[0] . "\n";
            }
        }

        echo "\nテスト完了。\n";

    } catch (Throwable $e) {
        echo "❌ <エラー発生>\n";
        echo $e->getMessage() . "\n\n";

        // デバッグ用にスタックトレース（必要なければコメントアウトOK）
        // echo $e->getTraceAsString() . "\n";
    }

    echo '</pre>';
    ?>
</div>
</body>
</html>
