<?php
session_start();
require_once __DIR__ . '/../lib/Auth.php';
Auth::requireLogin();   // 只有已登入使用者可查詢自己筆記的分享狀態

require_once __DIR__ . '/../lib/FileManager.php';
$fm = new FileManager();

$note = $_GET['note'] ?? '';
$note = $fm->sanitizeName($note);
if (!$note || !$fm->noteExists($note)) {
    echo json_encode(['shared' => false]);
    exit;
}

// 與 toggle_share.php 相同的 share.txt 路徑
$shareFile = $fm->getNotePath($note) . 'share.txt';
$shared = file_exists($shareFile);

echo json_encode(['shared' => $shared]);