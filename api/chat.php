<?php
// 無時間限制、關閉緩衝
set_time_limit(0);
ignore_user_abort(true);
while (ob_get_level()) ob_end_clean();
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

function sse($data) {
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    ob_flush();
    flush();
}

// 先取得請求內容
$input = json_decode(file_get_contents('php://input'), true);
$note    = $input['note'] ?? '';
$message = $input['message'] ?? '';
$history = $input['history'] ?? [];
$shared  = $input['shared'] ?? false;

// 權限判斷
if (!$shared) {
    require_once __DIR__ . '/check_auth.php';
}
// 注意：分享模式下稍後檢查 share.txt

require_once __DIR__ . '/../config.php';

// 分享模式使用索引取得筆記路徑與內容
if ($shared) {
    $note = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fff}]/u', '', $note);
    if (empty($note)) {
        sse(['error' => '無效的筆記名稱']);
        exit;
    }

    $indexFile = __DIR__ . '/../data/shared_index.txt';
    $noteDir = null;
    if (file_exists($indexFile)) {
        $lines = file($indexFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            list($name, $path) = explode('=', $line, 2);
            if ($name === $note) {
                $noteDir = rtrim($path, '/') . '/';
                break;
            }
        }
    }

    if (!$noteDir || !is_dir($noteDir)) {
        sse(['error' => '筆記不存在或未分享']);
        exit;
    }

    if (!file_exists($noteDir . 'share.txt')) {
        sse(['error' => '此筆記未分享']);
        exit;
    }

    // 讀取所有 .txt 檔案組合成系統提示
    $allFiles = scandir($noteDir);
    $documents = '';
    foreach ($allFiles as $f) {
        $fullPath = $noteDir . $f;
        if (is_file($fullPath) && pathinfo($f, PATHINFO_EXTENSION) === 'txt' && $f[0] !== '.' && $f !== 'share.txt') {
            $documents .= "[文件：$f]\n" . file_get_contents($fullPath) . "\n\n";
        }
    }
} else {
    // 一般登入模式：使用 FileManager
    require_once __DIR__ . '/../lib/FileManager.php';
    $fm = new FileManager();
    $note = $fm->sanitizeName($note);
    if (!$note || !$fm->noteExists($note)) {
        sse(['error' => '筆記不存在']);
        exit;
    }
    $documents = $fm->getNoteContent($note);
}

if (trim($message) === '') {
    sse(['error' => '請輸入問題']);
    exit;
}

$config = getConfig();

$systemContent = "你是專業的知識助手，以下是使用者提供的筆記內容與參考資料，請根據這些資料回答問題。\n\n";
$systemContent .= $documents;
$systemContent .= "\n請依據以上資料回答，若資料不足，請誠實說明。回答時盡量引用文件來源。";

$messages = [];
$messages[] = ['role' => 'system', 'content' => $systemContent];
if (is_array($history)) {
    foreach ($history as $msg) {
        if (isset($msg['role'], $msg['content']) && in_array($msg['role'], ['user', 'assistant'])) {
            $messages[] = $msg;
        }
    }
}

// Token 截斷
$maxContextTokens = (int)($config['max_context_tokens'] ?? 128000);
$estimated = 0;
foreach ($messages as $m) {
    $estimated += ceil(strlen($m['content']) / 4);
}
while ($estimated > $maxContextTokens * 0.9 && count($messages) > 2) {
    if ($messages[1]['role'] !== 'system') {
        $removed = array_splice($messages, 1, 2);
        $estimated -= (ceil(strlen($removed[0]['content']) / 4) + ceil(strlen($removed[1]['content']) / 4));
    } else break;
}

// 組合 API URL
$baseUrl = rtrim($config['api_url'], '/');
if (preg_match('#/chat/completions$#', $baseUrl)) {
    $apiUrl = $baseUrl;
} else {
    $apiUrl = $baseUrl . '/chat/completions';
}
$apiKey = $config['api_key'];

$postData = [
    'model'       => $config['model'],
    'messages'    => $messages,
    'max_tokens'  => $config['max_output_tokens'],
    'temperature' => $config['temperature'],
    'stream'      => true,
];

$rawResponse = '';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($postData),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$rawResponse) {
        $rawResponse .= $data;

        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, 'data: ') !== 0) continue;

            $jsonStr = substr($line, 6);
            if ($jsonStr === '[DONE]') {
                sse(['done' => true]);
                return strlen($data);
            }

            $chunk = json_decode($jsonStr, true);
            if (!$chunk) continue;

            $delta = $chunk['choices'][0]['delta'] ?? [];
            $content = $delta['content'] ?? null;
            if ($content !== null && $content !== '') {
                sse(['token' => $content]);
                usleep(30000); // 可選：打字機效果
            }
        }
        return strlen($data);
    },
    CURLOPT_TIMEOUT        => 300,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

curl_exec($ch);

if (curl_errno($ch)) {
    sse(['error' => 'API 請求失敗: ' . curl_error($ch)]);
} else {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode < 200 || $httpCode >= 300) {
        $cleanResponse = mb_substr($rawResponse, 0, 1000);
        sse(['error' => "API HTTP $httpCode\n\n回應內容：\n$cleanResponse"]);
    }
}

curl_close($ch);
sse(['done' => true]);