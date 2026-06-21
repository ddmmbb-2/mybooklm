<?php
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/FileManager.php';
require_once __DIR__ . '/../lib/OpenAIClient.php';

$fm = new FileManager();   // 自動指向目前使用者的資料目錄

$note = $_POST['note'] ?? '';
$url  = $_POST['url'] ?? '';

$note = $fm->sanitizeName($note);
if (!$note || !$fm->noteExists($note)) {
    echo json_encode(['error' => '筆記不存在']);
    exit;
}

// 基本網址驗證
$isHttp = (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0);
if (!filter_var($url, FILTER_VALIDATE_URL) || !$isHttp) {
    echo json_encode(['error' => '無效的網址']);
    exit;
}

// ----- 1. 抓取網頁 -----
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: zh-TW,zh;q=0.9,en;q=0.8',
    ],
    CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_ENCODING       => '',
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error || $httpCode < 200 || $httpCode >= 300 || empty($html)) {
    echo json_encode(['error' => "無法讀取網頁 (HTTP $httpCode)"]);
    exit;
}

// ----- 2. 提取文字 -----
$html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
$html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
$html = preg_replace('/<head[^>]*>.*?<\/head>/si', '', $html);
$text = strip_tags($html);
$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
$text = preg_replace('/\n\s*\n/', "\n\n", $text);
$text = trim($text);

if (strlen($text) < 100) {
    echo json_encode(['error' => '網頁內文不足，無法摘要']);
    exit;
}

$maxChars = 8000;
if (mb_strlen($text) > $maxChars) {
    $text = mb_substr($text, 0, $maxChars) . '...（內容已截斷）';
}

// ----- 3. 呼叫 AI -----
$config = getConfig();
$client = new OpenAIClient($config['api_url'], $config['api_key']);

$prompt = <<<EOT
你是一位專業的研究助理。請根據以下網頁內容，為使用者整理成一份結構化筆記。
請用繁體中文輸出，格式可包含：
- 標題
- 重點摘要
- 關鍵要點（條列式）
- 重要數據或引用
- 結論或啟發
僅整理內容，不要加入個人意見。以下為網頁內文：

$text
EOT;

$messages = [
    ['role' => 'system', 'content' => '你是一個專業的筆記整理助手。'],
    ['role' => 'user', 'content' => $prompt],
];
$options = [
    'model'       => $config['model'],
    'max_tokens'  => 4096,
    'temperature' => 0.5,
];

try {
    $summary = $client->chat($messages, $options);
} catch (Exception $e) {
    echo json_encode(['error' => 'AI 摘要失敗：' . $e->getMessage()]);
    exit;
}

// ----- 4. 儲存為筆記檔案（使用使用者目錄） -----
try {
    $noteDir = $fm->ensureNoteDir($note);   // 確保 note/ 存在
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$domain = parse_url($url, PHP_URL_HOST);
$safeDomain = preg_replace('/[^a-zA-Z0-9.-]/', '_', $domain);
$filename = 'url_' . $safeDomain . '_' . date('Ymd_His') . '.txt';
$filePath = $noteDir . $filename;

if (file_put_contents($filePath, $summary, LOCK_EX)) {
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['error' => '檔案寫入失敗']);
}