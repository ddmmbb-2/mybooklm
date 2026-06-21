<?php
/**
 * 檔案系統管理：筆記建立、列表、檔案掃描
 */
class FileManager {
    private $dataDir;

    public function __construct($dataDir = null) {
        if ($dataDir === null) {
            // 從 session 取目前使用者路徑
            $this->dataDir = Auth::getUserDataPath();
        } else {
            $this->dataDir = rtrim($dataDir, '/');
        }
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    /**
     * 過濾筆記名稱（只允許中英數、底線、連字號）
     */
    public function sanitizeName($name) {
        $name = preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fff}\-]/u', '', $name);
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            return false;
        }
        return $name;
    }

    /**
     * 建立新筆記
     */
    public function createNote($name) {
        $name = $this->sanitizeName($name);
        if (!$name) {
            return false;
        }
        $notePath = $this->dataDir . '/' . $name;
        if (is_dir($notePath)) {
            return false;
        }
        if (!mkdir($notePath . '/note', 0755, true)) {
            return false;
        }
        file_put_contents($notePath . '/url.txt', '', LOCK_EX);
        return $name;
    }

    /**
     * 列出所有筆記名稱
     */
    public function listNotes() {
        $notes = [];
        if (!is_dir($this->dataDir)) return $notes;
        $items = scandir($this->dataDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $this->dataDir . '/' . $item;
            if (is_dir($path)) {
                $notes[] = $item;
            }
        }
        sort($notes);
        return $notes;
    }

    /**
     * 檢查筆記是否存在
     */
    public function noteExists($noteName) {
        $noteName = $this->sanitizeName($noteName);
        if (!$noteName) return false;
        return is_dir($this->dataDir . '/' . $noteName);
    }

    /**
     * 取得筆記下的文字檔案列表
     */
    public function getFiles($noteName) {
        $noteName = $this->sanitizeName($noteName);
        if (!$noteName) return [];
        $notePath = $this->dataDir . '/' . $noteName . '/note';
        if (!is_dir($notePath)) return [];
        $files = glob($notePath . '/*.txt');
        return array_map('basename', $files);
    }

    /**
     * 取得筆記內所有文字內容（組合成單一字串）
     */
    public function getNoteContent($noteName) {
        $noteName = $this->sanitizeName($noteName);
        if (!$noteName) return '';
        $notePath = $this->dataDir . '/' . $noteName . '/note';
        $files = glob($notePath . '/*.txt');
        $content = '';
        foreach ($files as $file) {
            $filename = basename($file);
            $content .= "[文件：$filename]\n" . file_get_contents($file) . "\n\n";
        }
        return $content;
    }

    /**
     * 取得網址清單字串
     */
    public function getUrls($noteName) {
        $noteName = $this->sanitizeName($noteName);
        if (!$noteName) return '';
        $urlFile = $this->dataDir . '/' . $noteName . '/url.txt';
        if (file_exists($urlFile)) {
            return file_get_contents($urlFile);
        }
        return '';
    }

    /**
     * 取得筆記的 note 目錄路徑
     */
    public function getNotePath($noteName) {
        $noteName = $this->sanitizeName($noteName);
        return $this->dataDir . '/' . $noteName . '/note/';
    }

    /**
     * 確保筆記的 note 目錄存在，並回傳路徑
     */
    public function ensureNoteDir($noteName) {
        $path = $this->getNotePath($noteName);
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new RuntimeException('無法建立目錄：' . $path);
            }
        }
        return $path;
    }
}