<?php
$note = $_GET['note'] ?? '';

// 簡單安全過濾（與 FileManager::sanitizeName 保持一致）
$note = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fff}]/u', '', $note);
if (empty($note)) {
    die('無效的筆記名稱');
}

// 從索引檔找出筆記的真實路徑
$indexFile = __DIR__ . '/data/shared_index.txt';
$noteDir = null;
if (file_exists($indexFile)) {
    $lines = file($indexFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        list($name, $path) = explode('=', $line, 2);
        if ($name === $note) {
            $noteDir = rtrim($path, '/') . '/';   // 保證末尾有 /
            break;
        }
    }
}

if (!$noteDir || !is_dir($noteDir)) {
    die('筆記不存在');
}

// 檢查分享檔
$shareFile = $noteDir . 'share.txt';
if (!file_exists($shareFile)) {
    die('此筆記尚未公開分享，或分享已關閉。');
}

// 取得檔案列表（僅 .txt，排除隱藏檔與 share.txt）
$allFiles = scandir($noteDir);
$files = [];
foreach ($allFiles as $f) {
    $fullPath = $noteDir . $f;
    if (is_file($fullPath) && pathinfo($f, PATHINFO_EXTENSION) === 'txt' && $f[0] !== '.' && $f !== 'share.txt') {
        $files[] = $f;
    }
}
sort($files);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($note) ?> - 分享筆記</title>
    <script src="lib/marked.min.js"></script>
    <style>
        /* 直接複製 note.php 的完整 CSS，維持相同外觀 */
        :root {
            --primary: #4f6ef7;
            --primary-hover: #3b55e6;
            --surface: #ffffff;
            --bg: #f4f6fb;
            --text: #1a202c;
            --text-secondary: #5a6978;
            --border: #e2e8f0;
            --radius: 12px;
            --shadow: 0 4px 12px rgba(0,0,0,0.06);
            --transition: 0.2s ease;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .app-container {
            display: flex;
            height: 100vh;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0.5rem;
            gap: 0.5rem;
        }
        .mobile-tool-toggle {
            display: none;
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            width: 50px; height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white; border: none;
            font-size: 1.4rem;
            box-shadow: 0 4px 16px rgba(79,110,247,0.35);
            z-index: 999; cursor: pointer;
            transition: all var(--transition);
            align-items: center; justify-content: center;
        }
        .mobile-tool-toggle:active { transform: scale(0.95); }
        .sidebar {
            width: 35%; min-width: 300px;
            display: flex; flex-direction: column; gap: 0.5rem;
            overflow-y: auto; padding-right: 0.25rem;
            transition: transform var(--transition);
        }
        .sidebar .section {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid var(--border);
        }
        .sidebar .section h2 {
            font-size: 1.1rem; margin-bottom: 0.75rem;
            display: flex; align-items: center; gap: 0.4rem;
        }
        .note-header {
            display: flex; justify-content: space-between; align-items: center;
        }
        .note-header h1 {
            font-size: 1.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .back-link {
            color: var(--primary); text-decoration: none;
            font-size: 0.9rem; font-weight: 500;
        }
        .back-link:hover { text-decoration: underline; }
        .file-list {
            list-style: none; padding: 0;
            max-height: 220px; overflow-y: auto;
            border: 1px solid var(--border); border-radius: 10px;
            padding: 0.6rem; margin-top: 0.5rem;
            background: #fafbfc;
        }
        .file-list li {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.4rem 0; border-bottom: 1px solid #edf2f7;
        }
        .file-list li:last-child { border-bottom: none; }
        .file-actions button {
            background: none; border: 1px solid var(--border);
            padding: 0.2rem 0.5rem; border-radius: 6px;
            cursor: pointer; font-size: 0.85rem; margin-left: 0.25rem;
            transition: all var(--transition); color: var(--text-secondary);
        }
        .file-actions button:hover { background: #f1f5f9; border-color: #cbd5e0; }
        #file-preview {
            background: #f8fafc; border-radius: 10px;
            margin: 0.5rem 0; padding: 0.75rem;
            border: 1px solid var(--border);
        }
        #preview-content {
            max-height: 180px; overflow-y: auto;
            background: white; padding: 0.6rem; border-radius: 8px;
            font-size: 0.85rem; white-space: pre-wrap; font-family: monospace;
        }
        .main-chat {
            flex: 1; display: flex; flex-direction: column;
            background: var(--surface); border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid var(--border); overflow: hidden;
        }
        .chat-header {
            padding: 0.75rem 1rem; border-bottom: 1px solid var(--border);
            font-weight: 600; background: #fafbfc;
            display: flex; justify-content: space-between; align-items: center;
        }
        .chat-log {
            flex: 1; padding: 1rem; overflow-y: auto;
            background: #ffffff; display: flex; flex-direction: column; gap: 0.75rem;
        }
        .chat-msg {
            max-width: 85%; padding: 0.65rem 0.9rem;
            border-radius: 14px; line-height: 1.55;
            word-break: break-word; font-size: 0.95rem;
        }
        .chat-msg.user {
            align-self: flex-end; background: #eff6ff;
            border-bottom-right-radius: 4px; color: #1e3a8a;
        }
        .chat-msg.assistant {
            align-self: flex-start; background: #f8fafc;
            border-bottom-left-radius: 4px; border: 1px solid #e2e8f0;
        }
        .chat-msg.assistant p { margin: 0.25rem 0; }
        .chat-msg.assistant ul, .chat-msg.assistant ol { margin: 0.25rem 0; padding-left: 1.5rem; }
        .chat-msg.assistant li { margin: 0.1rem 0; }
        .chat-msg.assistant pre {
            background: #f1f5f9; padding: 0.6rem; border-radius: 8px;
            overflow-x: auto; margin: 0.5rem 0; font-size: 0.85rem;
        }
        .chat-msg.assistant code {
            background: #f1f5f9; padding: 0.15rem 0.35rem;
            border-radius: 4px; font-size: 0.9em;
        }
        .chat-msg.assistant pre code { background: none; padding: 0; }
        .chat-msg.assistant blockquote {
            border-left: 3px solid #cbd5e0; padding-left: 0.75rem;
            color: #4a5568; margin: 0.5rem 0;
        }
        .chat-msg.assistant table {
            border-collapse: collapse; width: 100%; margin: 0.5rem 0;
        }
        .chat-msg.assistant th, .chat-msg.assistant td {
            border: 1px solid #e2e8f0; padding: 0.3rem 0.6rem; text-align: left;
        }
        .chat-msg.assistant th { background: #f8fafc; font-weight: 600; }
        .chat-input-area {
            padding: 0.75rem; border-top: 1px solid var(--border);
            background: #fafbfc; display: flex; gap: 0.5rem; align-items: flex-end;
        }
        #user-input {
            flex: 1; padding: 0.7rem 1rem;
            border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 0.95rem; background: white; resize: none;
            min-height: 44px; max-height: 120px; overflow-y: auto;
            line-height: 1.4; font-family: inherit;
        }
        #user-input:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,110,247,0.15);
        }
        .btn {
            padding: 0.55rem 1.1rem; background: var(--primary);
            color: white; border: none; border-radius: 10px;
            cursor: pointer; font-size: 0.9rem; font-weight: 500;
            transition: background var(--transition), box-shadow var(--transition);
            box-shadow: 0 2px 6px rgba(79,110,247,0.2);
        }
        .btn:hover { background: var(--primary-hover); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }
        @media (max-width: 768px) {
            .app-container { flex-direction: column; height: 100vh; padding: 0; gap: 0; }
            .sidebar {
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                z-index: 1000; background: var(--bg); padding: 1rem;
                overflow-y: auto; transform: translateX(-100%);
                transition: transform 0.3s ease; min-width: unset;
                box-shadow: 2px 0 20px rgba(0,0,0,0.15);
            }
            .sidebar.open { transform: translateX(0); }
            .main-chat {
                flex: 1; border-radius: 0; box-shadow: none; border: none;
                height: 100%; padding-bottom: 70px;
            }
            .chat-header { padding: 0.5rem 0.75rem; }
            .chat-log { max-height: calc(100vh - 180px); }
            .chat-input-area { position: sticky; bottom: 0; background: #fafbfc; }
            .mobile-tool-toggle { display: flex; bottom: 1rem; right: 1rem; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="sidebar" id="sidebar">
            <button onclick="closeSidebar()" style="display:none; align-self:flex-end; background:none; border:none; font-size:1.5rem; cursor:pointer;" id="close-sidebar-btn">✕</button>
            <div class="section note-header">
                <h1>📝 <?= htmlspecialchars($note) ?></h1>
                <span style="font-size:0.85rem; color:#4f6ef7;">公開分享</span>
            </div>

            <div class="section">
                <h2>📄 文件列表</h2>
                <ul class="file-list" id="file-list">
                    <li>載入中...</li>
                </ul>
                <div id="file-preview" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong id="preview-title"></strong>
                        <button onclick="document.getElementById('file-preview').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:1.2rem;">✖</button>
                    </div>
                    <pre id="preview-content"></pre>
                </div>
            </div>
        </div>

        <div class="main-chat">
            <div class="chat-header">
    <span>💬 AI 問答（基於分享筆記）</span>
    <button id="clear-btn" class="btn" style="padding:0.3rem 0.8rem; font-size:0.8rem;">清除對話</button>
</div>
            <div class="chat-log" id="chat-log"></div>
            <div class="chat-input-area">
                <textarea id="user-input" rows="1" placeholder="輸入問題...（Shift+Enter 換行）"></textarea>
                <button id="send-btn" class="btn">發送</button>
            </div>
        </div>
    </div>

    <button class="mobile-tool-toggle" id="tool-toggle-btn" onclick="toggleSidebar()">📋</button>

    <script>
// ----- 手機工具欄切換 -----
const sidebar = document.getElementById('sidebar');
const closeBtn = document.getElementById('close-sidebar-btn');
function openSidebar() {
    sidebar.classList.add('open');
    if (closeBtn) closeBtn.style.display = 'block';
}
function closeSidebar() {
    sidebar.classList.remove('open');
    if (closeBtn) closeBtn.style.display = 'none';
}
function toggleSidebar() {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
}
sidebar.addEventListener('click', function(e) {
    if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') setTimeout(closeSidebar, 300);
});

// ---------- 基本設定 ----------
const noteName = <?= json_encode($note) ?>;
const CHAT_STORAGE = 'nb_share_chat_' + noteName;
const MAX_ROUNDS = 10;

// --- localStorage 工具 ---
function loadHistory() {
    const raw = localStorage.getItem(CHAT_STORAGE);
    if (raw) {
        try { return JSON.parse(raw); } catch (e) { return []; }
    }
    return [];
}
function saveHistory(history) {
    if (history.length > MAX_ROUNDS * 2) {
        history = history.slice(-MAX_ROUNDS * 2);
    }
    localStorage.setItem(CHAT_STORAGE, JSON.stringify(history));
}

// --- UI 顯示訊息 ---
const chatLog = document.getElementById('chat-log');
function addMessage(role, text) {
    const div = document.createElement('div');
    div.className = 'chat-msg ' + (role === 'user' ? 'user' : 'assistant');
    if (role === 'user') {
        div.textContent = text;
    } else {
        div.innerHTML = marked.parse(text, { breaks: true, gfm: true });
    }
    chatLog.appendChild(div);
    chatLog.scrollTop = chatLog.scrollHeight;
    return div;
}

// 初始化對話歷史
(function() {
    const history = loadHistory();
    history.forEach(msg => addMessage(msg.role, msg.content));
})();

// ---------- 對話功能 ----------
const userInput = document.getElementById('user-input');
const sendBtn = document.getElementById('send-btn');

async function sendMessage() {
    const message = userInput.value.trim();
    if (!message) return;

    addMessage('user', message);
    userInput.value = '';
    userInput.style.height = 'auto';
    sendBtn.disabled = true;
    userInput.disabled = true;

    let history = loadHistory();
    history.push({ role: 'user', content: message });
    const assistantDiv = addMessage('assistant', '⏳ 思考中...');
    let fullContent = '';

    try {
        const response = await fetch('api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                note: noteName,
                message: message,
                history: history,
                shared: true
            })
        });

        if (!response.ok) throw new Error('HTTP ' + response.status);

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (let line of lines) {
                line = line.trim();
                if (line === '' || !line.startsWith('data: ')) continue;
                const jsonStr = line.slice(6);
                if (jsonStr === '[DONE]') continue;

                try {
                    const data = JSON.parse(jsonStr);
                    if (data.token) {
                        fullContent += data.token;
                        assistantDiv.innerHTML = marked.parse(fullContent, { breaks: true, gfm: true });
                        chatLog.scrollTop = chatLog.scrollHeight;
                    } else if (data.error) {
                        fullContent += `\n\n❌ ${data.error}`;
                        assistantDiv.innerHTML = marked.parse(fullContent, { breaks: true, gfm: true });
                    }
                } catch (e) {
                    // 略過無法解析的行
                }
            }
        }

        if (fullContent === '') {
            assistantDiv.innerHTML = marked.parse('❌ 未收到回應', { breaks: true });
            fullContent = '❌ 未收到回應';
        }

    } catch (e) {
        assistantDiv.innerHTML = marked.parse('❌ 請求失敗: ' + e.message, { breaks: true });
        fullContent = '❌ 請求失敗: ' + e.message;
    } finally {
        history.push({ role: 'assistant', content: fullContent });
        saveHistory(history);
        sendBtn.disabled = false;
        userInput.disabled = false;
        userInput.focus();
    }
}

userInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});
userInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
sendBtn.addEventListener('click', sendMessage);

// ---------- 唯讀檔案列表 ----------
async function loadFiles() {
    const fileList = document.getElementById('file-list');
    try {
        const resp = await fetch('api/get_files.php?note=' + encodeURIComponent(noteName) + '&shared=1');
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const data = await resp.json();
        if (data.error) {
            fileList.innerHTML = '<li style="color:red;">❌ ' + data.error + '</li>';
            return;
        }
        if (data.files) {
            renderFileList(data.files);
        } else {
            fileList.innerHTML = '<li>沒有檔案資料</li>';
        }
    } catch (e) {
        console.error('載入檔案列表失敗:', e);
        fileList.innerHTML = '<li style="color:red;">❌ 載入失敗: ' + e.message + '</li>';
    }
}

function renderFileList(files) {
    const fileList = document.getElementById('file-list');
    fileList.innerHTML = '';
    if (files.length === 0) {
        fileList.innerHTML = '<li>尚無文件</li>';
        return;
    }
    files.forEach(file => {
        const li = document.createElement('li');
        const span = document.createElement('span');
        span.textContent = '📄 ' + file;
        li.appendChild(span);

        const actions = document.createElement('div');
        actions.className = 'file-actions';

        const previewBtn = document.createElement('button');
        previewBtn.textContent = '👁️';
        previewBtn.title = '預覽';
        previewBtn.addEventListener('click', () => previewFile(file));
        actions.appendChild(previewBtn);

        li.appendChild(actions);
        fileList.appendChild(li);
    });
}

async function previewFile(filename) {
    try {
        const resp = await fetch(`api/get_file_content.php?note=${encodeURIComponent(noteName)}&file=${encodeURIComponent(filename)}&shared=1`);
        const data = await resp.json();
        if (data.error) {
            alert(data.error);
            return;
        }
        document.getElementById('preview-title').textContent = filename;
        document.getElementById('preview-content').textContent = data.content;
        document.getElementById('file-preview').style.display = 'block';
    } catch (e) {
        alert('預覽失敗：' + e.message);
    }
}



// 清除對話按鈕
document.getElementById('clear-btn').addEventListener('click', function() {
    if (confirm('確定清除所有對話歷史？（無法復原）')) {
        localStorage.removeItem(CHAT_STORAGE);
        chatLog.innerHTML = '';
    }
});
// 初次載入
loadFiles();



    </script>
</body>
</html>
</html>