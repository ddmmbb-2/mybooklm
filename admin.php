<?php
session_start();
require_once __DIR__ . '/lib/Auth.php';
Auth::requireAdmin();

$message = '';
$currentAdmin = $_SESSION['user'];
$isLastAdmin = (Auth::countAdmins() <= 1);

// 處理新增使用者
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'create') {
    $u = trim($_POST['new_user'] ?? '');
    $p = $_POST['new_pass'] ?? '';
    if (strlen($u) < 2 || strlen($p) < 4) {
        $message = '<div class="alert alert-danger">帳號至少2字，密碼至少4字</div>';
    } else {
        if (Auth::createUser($u, $p)) {
            $message = '<div class="alert alert-success">使用者 ' . htmlspecialchars($u) . ' 建立成功</div>';
        } else {
            $message = '<div class="alert alert-danger">建立失敗（可能已存在）</div>';
        }
    }
}

// 處理刪除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'delete') {
    $target = $_POST['target_user'] ?? '';
    // 檢查是否為最後一個管理員且刪除的是管理員
    if ($isLastAdmin && Auth::isAdmin()) {
        // 可以直接阻止，或僅給警告（此處選擇阻止，但可自行調整）
        $message = '<div class="alert alert-danger">無法刪除最後一個管理員。請先建立另一個管理員。</div>';
    } else {
        $result = Auth::deleteUser($target);
        if (isset($result['error'])) {
            $message = '<div class="alert alert-danger">' . $result['error'] . '</div>';
        } else {
            $message = '<div class="alert alert-success">使用者 ' . htmlspecialchars($target) . ' 已刪除</div>';
            // 如果刪除的是自己，Auth::deleteUser 已登出，頁面會自動跳轉
        }
    }
}

// 處理角色更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'changerole') {
    $target = $_POST['target_user'] ?? '';
    $newRole = $_POST['new_role'] ?? '';
    // 如果是最後一個管理員，且將自己降級為 user，則阻止
    if ($isLastAdmin && $target === $currentAdmin && $newRole === 'user') {
        $message = '<div class="alert alert-danger">無法降級最後一個管理員。請先建立另一個管理員。</div>';
    } else {
        $result = Auth::updateRole($target, $newRole);
        if (isset($result['error'])) {
            $message = '<div class="alert alert-danger">' . $result['error'] . '</div>';
        } else {
            $message = '<div class="alert alert-success">角色已更新</div>';
        }
    }
}

// 取得使用者列表
$db = Auth::initDB();
$users = $db->query("SELECT username, role FROM users ORDER BY username");
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>使用者管理</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 2rem auto; background: #f9fafb; color: #1f2937; }
        h1 { font-size: 1.8rem; }
        .alert { padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        th, td { padding: 0.75rem; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; }
        tr:last-child td { border-bottom: none; }
        form.inline { display: inline-block; margin-right: 0.5rem; }
        button, .btn { padding: 0.4rem 0.8rem; border: 1px solid #d1d5db; background: white; border-radius: 6px; cursor: pointer; font-size: 0.9rem; }
        .btn-danger { color: #dc2626; border-color: #fecaca; background: #fff5f5; }
        .btn-primary { background: #3b82f6; color: white; border: none; }
        select { padding: 0.3rem; border-radius: 4px; border: 1px solid #d1d5db; }
        .section { background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .flex { display: flex; gap: 0.5rem; align-items: center; }
        .flex input { flex: 1; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; }
        .warning { color: #d97706; font-weight: bold; }
    </style>
</head>
<body>
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>👥 使用者管理</h1>
        <a href="index.php">← 回到知識庫</a>
    </div>
    <?= $message ?>
    <?php if ($isLastAdmin): ?>
        <div class="warning">⚠️ 您是唯一的管理員，刪除或降級自己將導致系統沒有管理員，請謹慎操作。</div>
    <?php endif; ?>

    <div class="section">
        <h2>新增使用者</h2>
        <form method="post" class="flex">
            <input type="hidden" name="action" value="create">
            <input type="text" name="new_user" placeholder="帳號" required>
            <input type="password" name="new_pass" placeholder="密碼（至少4位）" required>
            <button type="submit" class="btn-primary">建立</button>
        </form>
    </div>

    <div class="section">
        <h2>現有使用者</h2>
        <table>
            <tr><th>帳號</th><th>角色</th><th>變更角色</th><th>操作</th></tr>
            <?php while ($row = $users->fetchArray(SQLITE3_ASSOC)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= $row['role'] === 'admin' ? '管理員' : '使用者' ?></td>
                    <td>
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="changerole">
                            <input type="hidden" name="target_user" value="<?= htmlspecialchars($row['username']) ?>">
                            <select name="new_role" onchange="if(this.value !== '<?= $row['role'] ?>') confirm('確定變更角色？') ? this.form.submit() : this.value='<?= $row['role'] ?>'">
                                <option value="user" <?= $row['role'] === 'user' ? 'selected' : '' ?>>使用者</option>
                                <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>管理員</option>
                            </select>
                            <button type="submit" style="display:none;">更新</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" class="inline" onsubmit="return confirm('<?= $row['username'] === $currentAdmin ? '⚠️ 您即將刪除自己的帳號，此操作無法復原！\n確定要繼續嗎？' : '確定要刪除使用者 ' . htmlspecialchars($row['username']) . ' 嗎？' ?>')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="target_user" value="<?= htmlspecialchars($row['username']) ?>">
                            <button type="submit" class="btn-danger">🗑️ 刪除</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>