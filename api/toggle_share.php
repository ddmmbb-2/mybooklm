<?php
session_start();
require_once __DIR__ . '/../lib/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../lib/FileManager.php';
$fm = new FileManager();

$note = $_POST['note'] ?? '';
$note = $fm->sanitizeName($note);
if (!$note || !$fm->noteExists($note)) {
    http_response_code(400);
    echo json_encode(['error' => '無效的筆記名稱']);
    exit;
}

$shareFile = $fm->getNotePath($note) . 'share.txt';   // getNotePath 回傳 .../note/，share.txt 放此目錄下
$action = $_POST['action'] ?? '';

if ($action === 'enable') {
    if (!file_exists($shareFile)) {
        file_put_contents($shareFile, 'shared');
    }
    // ★ 寫入全域分享索引
    $indexFile = __DIR__ . '/../data/shared_index.txt';
    $realNotePath = $fm->getNotePath($note);   // 例如 data/user/alice/我的筆記/note/
    $line = $note . '=' . $realNotePath . PHP_EOL;

    // 避免重複寫入
    $existing = file_exists($indexFile) ? file($indexFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $found = false;
    foreach ($existing as $entry) {
        if (strpos($entry, $note . '=') === 0) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        file_put_contents($indexFile, $line, FILE_APPEND | LOCK_EX);
    }

    echo json_encode(['success' => true, 'shared' => true]);

} elseif ($action === 'disable') {
    if (file_exists($shareFile)) {
        unlink($shareFile);
    }
    // 可選：從索引中移除該行，但留著無害，此處略
    echo json_encode(['success' => true, 'shared' => false]);

} else {
    http_response_code(400);
    echo json_encode(['error' => '未知操作']);
}