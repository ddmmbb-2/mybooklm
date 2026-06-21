<?php
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/../lib/FileManager.php';

$fm = new FileManager();   // 自動指向目前使用者的資料目錄

$note = $_POST['note'] ?? '';

$note = $fm->sanitizeName($note);
if (!$note || !$fm->noteExists($note)) {
    echo json_encode(['error' => '筆記不存在']);
    exit;
}

// 筆記的完整路徑 (data/{使用者}/{筆記名稱})
$notePath = $fm->getNotePath($note);          // 例如 data/user1/我的研究/note/
$noteDir  = dirname($notePath);               // 例如 data/user1/我的研究/

// 遞迴刪除整個筆記目錄
function rrmdir($dir) {
    if (!is_dir($dir)) return false;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

if (rrmdir($noteDir)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => '刪除失敗，可能權限不足']);
}