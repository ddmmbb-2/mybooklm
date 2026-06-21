<?php
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/../lib/FileManager.php';

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

if (unlink($filePath)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => '刪除失敗，可能權限不足']);
}