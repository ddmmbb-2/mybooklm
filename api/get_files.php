<?php
header('Content-Type: application/json; charset=utf-8');

$shared = ($_GET['shared'] ?? '') === '1';

if (!$shared) {
    require_once __DIR__ . '/check_auth.php';
    require_once __DIR__ . '/../lib/FileManager.php';
    $fm = new FileManager();
    $note = $_GET['note'] ?? '';
    $note = $fm->sanitizeName($note);
    if (!$note || !$fm->noteExists($note)) {
        echo json_encode(['error' => '筆記不存在']);
        exit;
    }
    $files = $fm->getFiles($note);
    echo json_encode(['files' => $files]);
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

// 檢查 share.txt
if (!file_exists($noteDir . 'share.txt')) {
    echo json_encode(['error' => '此筆記未分享']);
    exit;
}

// 列出檔案
$allFiles = scandir($noteDir);
$files = [];
foreach ($allFiles as $f) {
    $fullPath = $noteDir . $f;
    if (is_file($fullPath) && pathinfo($f, PATHINFO_EXTENSION) === 'txt' && $f[0] !== '.' && $f !== 'share.txt') {
        $files[] = $f;
    }
}
sort($files);
echo json_encode(['files' => $files]);