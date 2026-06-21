<?php
session_start();
require_once __DIR__ . '/lib/Auth.php';
Auth::requireLogin();
Auth::requireAdmin();

require_once __DIR__ . '/config.php';

// 處理儲存
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 簡單驗證 token（可選）
    $newConfig = [
        'api_url' => $_POST['api_url'] ?? '',
        'api_key' => $_POST['api_key'] ?? '',
        'model' => $_POST['model'] ?? '',
        'max_context_tokens' => $_POST['max_context_tokens'] ?? 128000,
        'max_output_tokens' => $_POST['max_output_tokens'] ?? 4096,
        'temperature' => $_POST['temperature'] ?? 0.7,
        'use_multimodal' => isset($_POST['use_multimodal']) ? true : false,
    ];
    if (saveConfig($newConfig)) {
        $message = '<div class="alert alert-success">設定已儲存。</div>';
    } else {
        $message = '<div class="alert alert-danger">儲存失敗，請檢查檔案權限。</div>';
    }
}

$config = getConfig();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API 設定</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 2rem auto; }
        label { display: block; margin-top: 1rem; font-weight: bold; }
        input, select { width: 100%; padding: 0.5rem; margin-top: 0.25rem; }
        .alert { padding: 0.75rem; margin-bottom: 1rem; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        button { margin-top: 1.5rem; padding: 0.75rem 1.5rem; cursor: pointer; }
    </style>
</head>
<body>
    <h1>本地模型 API 設定</h1>
    <?= $message ?>
    <form method="post">
        <label>API 網址 (OpenAI 相容)</label>
        <input type="url" name="api_url" value="<?= htmlspecialchars($config['api_url']) ?>" required>

        <label>API Key</label>
        <input type="text" name="api_key" value="<?= htmlspecialchars($config['api_key']) ?>">

        <label>模型名稱</label>
        <input type="text" name="model" value="<?= htmlspecialchars($config['model']) ?>" required>

        <label>最大上下文 Token 數</label>
        <input type="number" name="max_context_tokens" value="<?= (int)$config['max_context_tokens'] ?>" min="1024" required>

        <label>最大輸出 Token 數</label>
        <input type="number" name="max_output_tokens" value="<?= (int)$config['max_output_tokens'] ?>" min="1" required>

        <label>溫度 (0~2)</label>
        <input type="number" step="0.1" min="0" max="2" name="temperature" value="<?= (float)$config['temperature'] ?>" required>

        <label>
            <input type="checkbox" name="use_multimodal" value="1" <?= !empty($config['use_multimodal']) ? 'checked' : '' ?>>
            啟用多模態（圖片支援）
        </label>

        <button type="submit">儲存設定</button>
    </form>
    <p><a href="index.php">← 回到筆記列表</a></p>
</body>
</html>