<?php
header('Content-Type: application/json; charset=utf-8');

$shared = ($_GET['shared'] ?? '') === '1';

if (!$shared) {
    require_once __DIR__ . '/check_auth.php';
    require_once __DIR__ . '/../lib/FileManager.php';
    $fm = new FileManager();
    $note = $_GET['note'] ?? '';
    $file = $_GET['file'] ?? '';
    $note = $fm->sanitizeName($note);
    if (!$note || !$fm->noteExists($note)) {
        echo json_encode(['error' => '筆記不存在']);
        exit;
    }
    // 安全過濾
    $file = basename($file);
    $file = str_replace(["\0", '/', '\\'], '', $file);
    if (empty($file)) { echo json_encode(['error' => '無效的檔名']); exit; }
    $notePath = $fm->getNotePath($note);
    $filePath = $notePath . $file;
    if (!file_exists($filePath)) { echo json_encode(['error' => '檔案不存在']); exit; }
    echo json_encode(['content' => file_get_contents($filePath)]);
    exit;
}

// 分享模式：從索引查路徑
$note = $_GET['note'] ?? '';
$note = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fff}]/u', '', $note);
if (empty($note)) {
    echo json_encode(['error' => '無效的筆記名稱']);
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
    echo json_encode(['error' => '筆記不存在或未分享']);
    exit;
}

if (!file_exists($noteDir . 'share.txt')) {
    echo json_encode(['error' => '此筆記未分享']);
    exit;
}

$file = $_GET['file'] ?? '';
$file = basename($file);
$file = str_replace(["\0", '/', '\\'], '', $file);
if (empty($file)) { echo json_encode(['error' => '無效的檔名']); exit; }

$filePath = $noteDir . $file;
if (!file_exists($filePath)) { echo json_encode(['error' => '檔案不存在']); exit; }

echo json_encode(['content' => file_get_contents($filePath)]);