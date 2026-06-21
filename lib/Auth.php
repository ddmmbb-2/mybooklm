<?php
class Auth {
    private static $dbPath = __DIR__ . '/../data/users.sqlite';

    // 初始化資料庫
    public static function initDB() {
        $db = new SQLite3(self::$dbPath);
        // 建立表格（若不存在）
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user'
        )");
        // 自動補上 role 欄位（若從舊版升級）
        $columns = $db->query("PRAGMA table_info(users)");
        $hasRole = false;
        while ($col = $columns->fetchArray(SQLITE3_ASSOC)) {
            if ($col['name'] === 'role') {
                $hasRole = true;
                break;
            }
        }
        if (!$hasRole) {
            $db->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'user'");
        }
        return $db;
    }

    // 登入
    public static function login($username, $password) {
        $db = self::initDB();
        $stmt = $db->prepare("SELECT password, role FROM users WHERE username = :u");
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user'] = $username;
            $_SESSION['role'] = $row['role'];
            return true;
        }
        return false;
    }

    // 登出
    public static function logout() {
        session_destroy();
    }

    // 是否已登入
    public static function isLoggedIn() {
        return isset($_SESSION['user']);
    }

    // 是否為管理員
    public static function isAdmin() {
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    // 取得目前使用者的資料根目錄
    public static function getUserDataPath() {
        $user = $_SESSION['user'] ?? '';
        if (empty($user)) return null;
        $dir = __DIR__ . '/../data/' . $user . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    // 強制登入（頁面用）
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    // 強制管理員身分
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('HTTP/1.1 403 Forbidden');
            echo '權限不足';
            exit;
        }
    }

    // API 用驗證
    public static function requireLoginForApi() {
        if (!self::isLoggedIn()) {
            echo json_encode(['error' => '未登入']);
            exit;
        }
    }

    // 建立使用者
    public static function createUser($username, $password, $role = 'user') {
        $db = self::initDB();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (:u, :p, :r)");
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        $stmt->bindValue(':p', $hash, SQLITE3_TEXT);
        $stmt->bindValue(':r', $role, SQLITE3_TEXT);
        if ($stmt->execute()) {
            $userDir = __DIR__ . '/../data/' . $username;
            if (!is_dir($userDir)) {
                mkdir($userDir, 0755, true);
            }
            return true;
        }
        return false;
    }

    /**
     * 計算管理員人數
     */
    public static function countAdmins() {
        $db = self::initDB();
        $result = $db->querySingle("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        return (int)$result;
    }

    /**
     * 刪除使用者（任何人皆可刪，包含自己）
     * 若刪除的是自己，執行後會自動登出
     */
    public static function deleteUser($username) {
        $db = self::initDB();
        $stmt = $db->prepare("DELETE FROM users WHERE username = :u");
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        if ($stmt->execute()) {
            // 刪除使用者資料目錄
            $userDir = __DIR__ . '/../data/' . $username;
            if (is_dir($userDir)) {
                self::rrmdir($userDir);
            }
            // 如果刪除的是自己，登出
            if (isset($_SESSION['user']) && $_SESSION['user'] === $username) {
                self::logout();
            }
            return ['success' => true];
        }
        return ['error' => '刪除失敗'];
    }

    /**
     * 更新使用者角色（任何人皆可，包含自己）
     */
    public static function updateRole($username, $newRole) {
        if (!in_array($newRole, ['admin', 'user'])) {
            return ['error' => '無效的角色'];
        }
        $db = self::initDB();
        $stmt = $db->prepare("UPDATE users SET role = :r WHERE username = :u");
        $stmt->bindValue(':r', $newRole, SQLITE3_TEXT);
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        if ($stmt->execute()) {
            // 如果是修改自己，立即更新 session 角色
            if (isset($_SESSION['user']) && $_SESSION['user'] === $username) {
                $_SESSION['role'] = $newRole;
            }
            return ['success' => true];
        }
        return ['error' => '更新失敗'];
    }

    // 遞迴刪除目錄
    private static function rrmdir($dir) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? self::rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}