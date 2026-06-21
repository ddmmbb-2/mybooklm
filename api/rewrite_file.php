<?php
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/FileManager.php';
require_once __DIR__ . '/../lib/OpenAIClient.php';

$fm = new FileManager();   // 自動指向目前使用者的資料目錄

$note = $_POST['note'] ?? '';
$filename = $_POST['filename'] ?? '';

$note = $fm->sanitizeName($note);
if (!$note || !$fm->noteExists($note)) {
    echo json_encode(['error' => '筆記不存在']);
    exit;
}

// 安全過濾檔名
$filename = basename($filename);
$filename = str_replace(["\0", '/', '\\'], '', $filename);
if (empty($filename)) {
    echo json_encode(['error' => '無效的檔名']);
    exit;
}

// 取得使用者筆記的 note 目錄路徑
$notePath = $fm->getNotePath($note);
$filePath = $notePath . $filename;

if (!file_exists($filePath)) {
    echo json_encode(['error' => '檔案不存在']);
    exit;
}

$content = file_get_contents($filePath);
if (trim($content) === '') {
    echo json_encode(['error' => '檔案內容為空']);
    exit;
}

// 限制內容長度（避免請求過大）
$maxChars = 6000;
if (mb_strlen($content) > $maxChars) {
    $content = mb_substr($content, 0, $maxChars) . '...（內容已截斷，僅整理前段）';
}

$config = getConfig();
$client = new OpenAIClient($config['api_url'], $config['api_key']);

$prompt = <<<EOT
你是一位專業的知識整理師。請將以下文件重新整理成結構化筆記，保留所有重要細節，並讓結構更清晰易讀。
請使用繁體中文，可包含以下元素：
- 標題（若可判斷）
- 重點摘要
- 分類要點（條列式，務必保留細節與數據）
- 補充說明或結論
僅整理內容，不要加入個人意見。原始內容如下：

$content
EOT;

$messages = [
    ['role' => 'system', 'content' => '你是專業的筆記整理助手。'],
    ['role' => 'user', 'content' => $prompt],
];
$options = [
    'model'       => $config['model'],
    'max_tokens'  => 4096,
    'temperature' => 0.4,
];

try {
    $rewritten = $client->chat($messages, $options);
} catch (Exception $e) {
    echo json_encode(['error' => 'AI 整理失敗：' . $e->getMessage()]);
    exit;
}

// 儲存為新檔案（加 _rewritten 與時間戳）
$ext = pathinfo($filename, PATHINFO_EXTENSION);
$base = pathinfo($filename, PATHINFO_FILENAME);
$newFilename = $base . '_rewritten_' . date('Ymd_His') . '.' . $ext;
$newPath = $notePath . $newFilename;

if (file_put_contents($newPath, $rewritten, LOCK_EX)) {
    echo json_encode(['success' => true, 'new_filename' => $newFilename]);
} else {
    echo json_encode(['error' => '無法寫入新檔案']);
}