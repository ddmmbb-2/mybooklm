<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/lib/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/lib/FileManager.php';
$fm = new FileManager();
$notes = $fm->listNotes();

// 處理建立表單提交
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_note'])) {
    $newName = $_POST['new_note'];
    $result = $fm->createNote($newName);
    if ($result) {
        header('Location: index.php');
        exit;
    } else {
        $message = '<div class="alert alert-danger">建立失敗，名稱不合法或已存在。</div>';
    }
}

function getNoteCardInfo($noteName) {
    $dataDir = __DIR__ . '/data/' . $noteName;
    $info = ['name' => $noteName, 'file_count' => 0, 'last_modified' => '無'];
    if (is_dir($dataDir)) {
        $noteDir = $dataDir . '/note';
        if (is_dir($noteDir)) {
            $files = glob($noteDir . '/*.txt');
            $info['file_count'] = count($files);
            $latest = 0;
            foreach ($files as $file) {
                $mtime = filemtime($file);
                if ($mtime > $latest) $latest = $mtime;
            }
            if ($latest > 0) {
                $info['last_modified'] = date('Y-m-d H:i', $latest);
            }
        }
    }
    return $info;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>知識庫 · 筆記列表</title>
    <style>
        :root {
            --bg: #f4f6fb;
            --surface: #ffffff;
            --primary: #4f6ef7;
            --primary-hover: #3b55e6;
            --danger: #e53e3e;
            --danger-bg: #fff5f5;
            --text: #1a202c;
            --text-secondary: #5a6978;
            --border: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
            --radius: 16px;
            --radius-sm: 10px;
            --transition: 0.2s ease;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* 頂部導航 */
        .top-bar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow-sm);
        }
        .top-bar .brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .top-bar .nav-links {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .top-bar .nav-links a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            transition: color var(--transition);
        }
        .top-bar .nav-links a:hover { color: var(--primary); }
        .settings-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px; height: 38px;
            border-radius: 50%;
            background: #edf2f7;
            color: #4a5568;
            transition: all var(--transition);
        }
        .settings-icon:hover {
            background: #e2e8f0;
            transform: rotate(30deg);
        }

        /* 主容器 */
        .container {
            max-width: 1100px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1.5rem 3rem;
            flex: 1;
        }

        /* 訊息提示 */
        .alert {
            padding: 0.9rem 1.2rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-danger {
            background: var(--danger-bg);
            color: #c53030;
            border: 1px solid #fed7d7;
        }

        /* 建立筆記區塊 */
        .new-note-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1.75rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
            transition: box-shadow var(--transition);
        }
        .new-note-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .new-note-card h2 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text);
        }
        .new-note-form {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        .new-note-form input[type="text"] {
            flex: 1;
            min-width: 220px;
            padding: 0.85rem 1.2rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            background: #fafbfc;
            transition: all var(--transition);
            outline: none;
        }
        .new-note-form input[type="text"]:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(79,110,247,0.15);
        }
        .btn-primary {
            padding: 0.85rem 1.8rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background var(--transition), box-shadow var(--transition);
            box-shadow: 0 2px 6px rgba(79,110,247,0.3);
            white-space: nowrap;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            box-shadow: 0 4px 12px rgba(79,110,247,0.4);
        }

        /* 筆記列表標題 */
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            color: var(--text);
        }

        /* 筆記網格 */
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.25rem;
        }

        /* 筆記卡片 */
        .note-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1.5rem 1.25rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            position: relative;
            transition: all var(--transition);
            display: flex;
            flex-direction: column;
        }
        .note-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: #cbd5e0;
        }
        .card-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
        }
        .card-icon {
            font-size: 2.2rem;
            margin-bottom: 0.2rem;
        }
        .card-name {
            font-size: 1.15rem;
            font-weight: 650;
            word-break: break-word;
            color: var(--text);
        }
        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.3rem;
        }
        .card-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: #f7fafc;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            white-space: nowrap;
        }
        .delete-note-btn {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: rgba(255,255,255,0.9);
            border: 1px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0.25rem 0.5rem;
            transition: all var(--transition);
            color: #a0aec0;
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
        }
        .note-card:hover .delete-note-btn {
            opacity: 1;
            pointer-events: auto;
        }
        .delete-note-btn:hover {
            color: var(--danger);
            background: white;
            border-color: #fed7d7;
            box-shadow: 0 2px 8px rgba(229,62,62,0.15);
        }

        /* 空狀態 */
        .empty-state {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 3rem 2rem;
            text-align: center;
            color: var(--text-secondary);
            border: 2px dashed var(--border);
            font-size: 1.05rem;
        }
        .empty-state .empty-icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 0.75rem;
            opacity: 0.6;
        }

        /* 響應式調整 */
        @media (max-width: 600px) {
            .top-bar { flex-direction: column; align-items: flex-start; }
            .new-note-form input[type="text"] { min-width: 100%; }
            .notes-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- 頂部導航 -->
    <header class="top-bar">
        <a href="index.php" class="brand">📚 我的知識庫</a>
        <div class="nav-links">
            <?php if (Auth::isAdmin()): ?>
                <a href="admin.php"><span>👥</span> 管理使用者</a>
                <a href="settings.php" class="settings-icon" title="API 設定">⚙️</a>
            <?php endif; ?>
            <a href="logout.php"><span>🚪</span> 登出</a>
        </div>
    </header>

    <main class="container">
        <?= $message ?>

        <!-- 建立新筆記 -->
        <section class="new-note-card">
            <h2>✨ 建立新筆記</h2>
            <form method="post" class="new-note-form">
                <input type="text" name="new_note" placeholder="輸入筆記名稱（支援中英文）" required>
                <button type="submit" class="btn-primary">建立筆記</button>
            </form>
        </section>

        <!-- 現有筆記 -->
        <section>
            <h2 class="section-title">📁 所有筆記</h2>
            <?php if (empty($notes)): ?>
                <div class="empty-state">
                    <span class="empty-icon">📭</span>
                    尚無筆記，點擊上方建立第一本筆記吧！
                </div>
            <?php else: ?>
                <div class="notes-grid">
                    <?php foreach ($notes as $note):
                        $info = getNoteCardInfo($note);
                    ?>
                        <div class="note-card">
                            <a href="note.php?note=<?= urlencode($note) ?>" class="card-link">
                                <div class="card-icon">📁</div>
                                <div class="card-name"><?= htmlspecialchars($note) ?></div>
                                <div class="card-meta">
                                    <span>📄 <?= $info['file_count'] ?> 個檔案</span>
                                    <span>🕒 <?= $info['last_modified'] ?></span>
                                </div>
                            </a>
                            <button class="delete-note-btn" title="刪除筆記" data-note="<?= htmlspecialchars($note) ?>">🗑️</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        // 刪除筆記功能
        document.querySelectorAll('.delete-note-btn').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.stopPropagation();
                e.preventDefault();
                const noteName = this.dataset.note;
                if (!confirm(`確定要刪除筆記「${noteName}」嗎？\n此操作無法復原，所有文件將永久刪除。`)) return;
                const formData = new FormData();
                formData.append('note', noteName);
                try {
                    const resp = await fetch('api/delete_note.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await resp.json();
                    if (data.error) {
                        alert(data.error);
                    } else {
                        location.reload();
                    }
                } catch (err) {
                    alert('請求失敗：' + err.message);
                }
            });
        });
    </script>
</body>
</html>