<?php
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/../lib/FileManager.php';

$fm = new FileManager();

$note = $_POST['note'] ?? '';
$note = $fm->sanitizeName($note);
if (!$note || !$fm->noteExists($note)) {
    echo json_encode(['error' => '筆記不存在']);
    exit;
}

$action = $_POST['action'] ?? '';

// ----- 貼上文字 -----
if ($action === 'paste') {
    $text = $_POST['text'] ?? '';
    if (trim($text) === '') {
        echo json_encode(['error' => '請輸入文字']);
        exit;
    }
    try {
        $noteDir = $fm->ensureNoteDir($note);
        $filename = 'paste_' . date('Ymd_His') . '.txt';
        if (file_put_contents($noteDir . '/' . $filename, $text, LOCK_EX)) {
            echo json_encode(['success' => true, 'filename' => $filename]);
        } else {
            echo json_encode(['error' => '寫入失敗，請檢查權限']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// ----- 上傳檔案 -----
elseif ($action === 'upload') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => '檔案上傳失敗']);
        exit;
    }
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'txt') {
        echo json_encode(['error' => '只允許 .txt 檔案']);
        exit;
    }
    $origName = basename($_FILES['file']['name']);
    // 保留中文與安全字元，僅移除危險符號
    $safeName = str_replace(["\0", '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $origName);
    if (empty(trim($safeName))) {
        $safeName = 'uploaded_' . date('Ymd_His') . '.txt';
    }
    try {
        $noteDir = $fm->ensureNoteDir($note);
        $dest = $noteDir . '/' . $safeName;
        if (file_exists($dest)) {
            $dest = $noteDir . '/' . pathinfo($safeName, PATHINFO_FILENAME) . '_' . date('Ymd_His') . '.txt';
        }
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            echo json_encode(['success' => true, 'filename' => basename($dest)]);
        } else {
            echo json_encode(['error' => '檔案移動失敗']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

else {
    echo json_encode(['error' => '未知操作']);
}