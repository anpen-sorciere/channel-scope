<?php
/**
 * AIアドバイス保存API
 * 
 * POSTリクエストでAIアドバイスデータを受け取り、video_ai_adviceテーブルに保存する
 * 
 * リクエスト例:
 * POST /api/ai_advice_api.php
 * Content-Type: application/json
 * 
 * {
 *   "video_id": "dQw4w9WgXcQ",
 *   "provider": "chatgpt",
 *   "model": "gpt-4o-mini",
 *   "title_suggestions": ["タイトル案1", "タイトル案2"],
 *   "tag_suggestions": ["タグ1", "タグ2"],
 *   "short_script": "ショート動画の構成案...",
 *   "improvement_advice": "改善ポイント...",
 *   "strategy_advice": "戦略提案...",
 *   "raw_response": {...}
 * }
 */

require_once __DIR__ . '/../config.php';

// JSONリクエストのみ受け付ける
header('Content-Type: application/json; charset=utf-8');

// POSTメソッドのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'POSTメソッドのみサポートしています。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// JSONボディを取得
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'JSONの解析に失敗しました: ' . json_last_error_msg()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 必須パラメータチェック
$required = ['video_id', 'provider', 'model'];
foreach ($required as $key) {
    if (empty($data[$key])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "必須パラメータ '{$key}' が指定されていません。"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$videoId = trim($data['video_id']);
$provider = trim($data['provider']);
$model = trim($data['model']);

// バリデーション
if (strlen($videoId) > 64) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'video_id は64文字以内で指定してください。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($provider) > 32) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'provider は32文字以内で指定してください。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($model) > 64) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'model は64文字以内で指定してください。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// オプションパラメータの処理
$titleSuggestions = null;
if (isset($data['title_suggestions'])) {
    if (is_array($data['title_suggestions'])) {
        $titleSuggestions = json_encode($data['title_suggestions'], JSON_UNESCAPED_UNICODE);
    } elseif (is_string($data['title_suggestions'])) {
        // 既にJSON文字列の場合
        $titleSuggestions = $data['title_suggestions'];
    }
}

$tagSuggestions = null;
if (isset($data['tag_suggestions'])) {
    if (is_array($data['tag_suggestions'])) {
        $tagSuggestions = json_encode($data['tag_suggestions'], JSON_UNESCAPED_UNICODE);
    } elseif (is_string($data['tag_suggestions'])) {
        $tagSuggestions = $data['tag_suggestions'];
    }
}

$shortScript = isset($data['short_script']) ? trim($data['short_script']) : null;
$improvementAdvice = isset($data['improvement_advice']) ? trim($data['improvement_advice']) : null;
$strategyAdvice = isset($data['strategy_advice']) ? trim($data['strategy_advice']) : null;

$rawResponse = null;
if (isset($data['raw_response'])) {
    if (is_array($data['raw_response']) || is_object($data['raw_response'])) {
        $rawResponse = json_encode($data['raw_response'], JSON_UNESCAPED_UNICODE);
    } elseif (is_string($data['raw_response'])) {
        $rawResponse = $data['raw_response'];
    }
}

try {
    // 既存レコードのチェック（UNIQUE KEY: video_id + provider）
    $checkSql = "
        SELECT id FROM video_ai_advice
        WHERE video_id = :video_id AND provider = :provider
        LIMIT 1
    ";
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute([
        ':video_id' => $videoId,
        ':provider' => $provider
    ]);
    $existing = $stmt->fetch();

    if ($existing) {
        // UPDATE
        $sql = "
            UPDATE video_ai_advice
            SET
                model = :model,
                title_suggestions = :title_suggestions,
                tag_suggestions = :tag_suggestions,
                short_script = :short_script,
                improvement_advice = :improvement_advice,
                strategy_advice = :strategy_advice,
                raw_response = :raw_response,
                updated_at = CURRENT_TIMESTAMP
            WHERE video_id = :video_id AND provider = :provider
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':video_id' => $videoId,
            ':provider' => $provider,
            ':model' => $model,
            ':title_suggestions' => $titleSuggestions,
            ':tag_suggestions' => $tagSuggestions,
            ':short_script' => $shortScript,
            ':improvement_advice' => $improvementAdvice,
            ':strategy_advice' => $strategyAdvice,
            ':raw_response' => $rawResponse,
        ]);
        $action = 'updated';
        $recordId = $existing['id'];
    } else {
        // INSERT
        $sql = "
            INSERT INTO video_ai_advice (
                video_id,
                provider,
                model,
                title_suggestions,
                tag_suggestions,
                short_script,
                improvement_advice,
                strategy_advice,
                raw_response
            ) VALUES (
                :video_id,
                :provider,
                :model,
                :title_suggestions,
                :tag_suggestions,
                :short_script,
                :improvement_advice,
                :strategy_advice,
                :raw_response
            )
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':video_id' => $videoId,
            ':provider' => $provider,
            ':model' => $model,
            ':title_suggestions' => $titleSuggestions,
            ':tag_suggestions' => $tagSuggestions,
            ':short_script' => $shortScript,
            ':improvement_advice' => $improvementAdvice,
            ':strategy_advice' => $strategyAdvice,
            ':raw_response' => $rawResponse,
        ]);
        $action = 'created';
        $recordId = $pdo->lastInsertId();
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'action' => $action,
        'id' => (int)$recordId,
        'video_id' => $videoId,
        'provider' => $provider,
        'model' => $model,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'データベースエラー: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log('ai_advice_api.php DB Error: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '予期しないエラーが発生しました: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log('ai_advice_api.php Error: ' . $e->getMessage());
}

