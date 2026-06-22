<?php
session_start();
require_once __DIR__ . '/lib/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/lib/FileManager.php';
$fm = new FileManager();

// 從網址取得筆記名稱
$note = $_GET['note'] ?? '';
$note = $fm->sanitizeName($note);
if (!$note || !$fm->noteExists($note)) {
    header('Location: index.php');
    exit;
}

$files = $fm->getFiles($note);
$urls = $fm->getUrls($note);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($note) ?> - 筆記</title>
    <script src="lib/marked.min.js"></script>
    <style>
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

        /* 手機切換按鈕（桌面隱藏） */
        .mobile-tool-toggle {
            display: none;
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 1.4rem;
            box-shadow: 0 4px 16px rgba(79,110,247,0.35);
            z-index: 999;
            cursor: pointer;
            transition: all var(--transition);
            align-items: center;
            justify-content: center;
        }
        .mobile-tool-toggle:active { transform: scale(0.95); }

        /* === 左側工具欄 === */
        .sidebar {
            width: 35%;
            min-width: 300px;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            overflow-y: auto;
            padding-right: 0.25rem;
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
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .sidebar .section h3 {
            font-size: 0.95rem;
            margin: 0.75rem 0 0.5rem;
        }
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .note-header h1 {
            font-size: 1.5rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .back-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .back-link:hover { text-decoration: underline; }

        /* 表單元素 */
        form input[type="file"],
        form input[type="text"],
        form input[type="url"],
        form textarea {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            background: #fafbfc;
            transition: all var(--transition);
            outline: none;
        }
        form input:focus,
        form textarea:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(79,110,247,0.15);
        }
        .btn {
            padding: 0.55rem 1.1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background var(--transition), box-shadow var(--transition);
            box-shadow: 0 2px 6px rgba(79,110,247,0.2);
        }
        .btn:hover { background: var(--primary-hover); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }

        /* 檔案列表 */
        .file-list {
            list-style: none;
            padding: 0;
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.6rem;
            margin-top: 0.5rem;
            background: #fafbfc;
        }
        .file-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0;
            border-bottom: 1px solid #edf2f7;
        }
        .file-list li:last-child { border-bottom: none; }
        .file-actions button {
            background: none;
            border: 1px solid var(--border);
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            margin-left: 0.25rem;
            transition: all var(--transition);
            color: var(--text-secondary);
        }
        .file-actions button:hover { background: #f1f5f9; border-color: #cbd5e0; }
        .delete-btn { color: #e53e3e; border-color: #fed7d7 !important; }
        .delete-btn:hover { background: #fff5f5 !important; }

        /* 預覽區塊 */
        #file-preview {
            background: #f8fafc;
            border-radius: 10px;
            margin: 0.5rem 0;
            padding: 0.75rem;
            border: 1px solid var(--border);
        }
        #preview-content {
            max-height: 180px;
            overflow-y: auto;
            background: white;
            padding: 0.6rem;
            border-radius: 8px;
            font-size: 0.85rem;
            white-space: pre-wrap;
            font-family: monospace;
        }

        /* 擷取狀態 */
        #fetch-status { font-size: 0.85rem; margin-top: 0.5rem; color: var(--text-secondary); }

        /* === 右側對話區塊 === */
        .main-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .chat-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            background: #fafbfc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-log {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .chat-msg {
            max-width: 85%;
            padding: 0.65rem 0.9rem;
            border-radius: 14px;
            line-height: 1.55;
            word-break: break-word;
            font-size: 0.95rem;
        }
        .chat-msg.user {
            align-self: flex-end;
            background: #eff6ff;
            border-bottom-right-radius: 4px;
            color: #1e3a8a;
        }
        .chat-msg.assistant {
            align-self: flex-start;
            background: #f8fafc;
            border-bottom-left-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        /* Markdown 內容樣式 */
        .chat-msg.assistant p { margin: 0.25rem 0; }
        .chat-msg.assistant ul, .chat-msg.assistant ol { margin: 0.25rem 0; padding-left: 1.5rem; }
        .chat-msg.assistant li { margin: 0.1rem 0; }
        .chat-msg.assistant pre {
            background: #f1f5f9;
            padding: 0.6rem;
            border-radius: 8px;
            overflow-x: auto;
            margin: 0.5rem 0;
            font-size: 0.85rem;
        }
        .chat-msg.assistant code {
            background: #f1f5f9;
            padding: 0.15rem 0.35rem;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .chat-msg.assistant pre code { background: none; padding: 0; }
        .chat-msg.assistant blockquote {
            border-left: 3px solid #cbd5e0;
            padding-left: 0.75rem;
            color: #4a5568;
            margin: 0.5rem 0;
        }
        .chat-msg.assistant table {
            border-collapse: collapse;
            width: 100%;
            margin: 0.5rem 0;
        }
        .chat-msg.assistant th, .chat-msg.assistant td {
            border: 1px solid #e2e8f0;
            padding: 0.3rem 0.6rem;
            text-align: left;
        }
        .chat-msg.assistant th { background: #f8fafc; font-weight: 600; }

        .chat-input-area {
            padding: 0.75rem;
            border-top: 1px solid var(--border);
            background: #fafbfc;
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }
        /* 多行輸入框樣式 */
        #user-input {
            flex: 1;
            padding: 0.7rem 1rem;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            background: white;
            resize: none;
            min-height: 44px;
            max-height: 120px;
            overflow-y: auto;
            line-height: 1.4;
            font-family: inherit;
        }
        #user-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,110,247,0.15);
        }

        /* ===== 手機模式 ===== */
        @media (max-width: 768px) {
            .app-container {
                flex-direction: column;
                height: 100vh;
                padding: 0;
                gap: 0;
            }
            /* 預設隱藏側邊欄，全屏覆蓋 */
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1000;
                background: var(--bg);
                padding: 1rem;
                overflow-y: auto;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                min-width: unset;
                box-shadow: 2px 0 20px rgba(0,0,0,0.15);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-chat {
                flex: 1;
                border-radius: 0;
                box-shadow: none;
                border: none;
                height: 100%;
                /* 增加底部留白，避免被浮動按鈕遮蓋 */
                padding-bottom: 70px;
            }
            .chat-header {
                padding: 0.5rem 0.75rem;
            }
            /* 對話記錄區域少顯示兩行高度（約 3rem） */
            .chat-log {
                max-height: calc(100vh - 180px);
            }
            .chat-input-area {
                position: sticky;
                bottom: 0;
                background: #fafbfc;
            }
            /* 顯示手機工具切換按鈕，並調整位置 */
            .mobile-tool-toggle {
                display: flex;
                bottom: 1rem;
                right: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- 左側：工具與檔案管理 -->
        <div class="sidebar" id="sidebar">
            <!-- 手機關閉按鈕 -->
            <button onclick="closeSidebar()" style="display:none; align-self:flex-end; background:none; border:none; font-size:1.5rem; cursor:pointer;" id="close-sidebar-btn">✕</button>

            <div class="section note-header">
                <h1>📝 <?= htmlspecialchars($note) ?></h1>
                <a href="index.php" class="back-link">← 返回</a>
            </div>

            <!-- 檔案管理區塊 -->
            <div class="section">
                <h2>📄 文件管理</h2>
                <h3>上傳 .txt 檔案</h3>
                <form id="upload-form">
                    <input type="file" name="file" accept=".txt">
                    <button type="submit" class="btn">上傳</button>
                </form>

                <h3>貼上文字</h3>
                <form id="paste-form">
                    <textarea name="text" placeholder="在此貼上文字內容..." rows="4"></textarea>
                    <button type="submit" class="btn">新增筆記文件</button>
                </form>

                <!-- 預覽區塊 -->
                <div id="file-preview" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong id="preview-title"></strong>
                        <button onclick="document.getElementById('file-preview').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:1.2rem;">✖</button>
                    </div>
                    <pre id="preview-content"></pre>
                </div>

                <h3>現有文件</h3>
                <ul class="file-list" id="file-list">
                    <li>載入中...</li>
                </ul>
            </div>

            <!-- 從網址建立筆記 -->
            <div class="section">
                <h2>🌐 從網址建立筆記</h2>
                <p style="font-size:0.85rem; color:#6b7280;">貼上網址，AI 會自動抓取內容並整理成筆記文件。</p>
                <div style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                    <input type="url" id="fetch-url" placeholder="https://..." style="flex:1;">
                    <button id="fetch-btn" class="btn">擷取</button>
                </div>
                <div id="fetch-status" style="margin-top:0.5rem; color:#555;"></div>
            </div>


<div class="section">
    <h2>🔗 分享設定</h2>
    <p style="font-size:0.85rem; color:#6b7280;" id="share-status-text">載入中...</p>
    <div style="margin-top:0.5rem;">
        <button id="toggle-share-btn" class="btn">啟用分享</button>
    </div>
    <div id="share-link-box" style="margin-top:0.75rem; display:none;">
        <label style="font-size:0.85rem;">分享連結：</label>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="share-url" readonly style="flex:1; cursor:pointer;"
                   value="<?= htmlspecialchars('https://you-ip/sharenote.php?note=' . urlencode($note)) ?>">
            <button id="copy-share-btn" class="btn" style="padding:0.4rem 0.8rem;">複製</button>
        </div>
        <small style="color:#6b7280;">任何人透過此連結即可唯讀瀏覽筆記並使用 AI 問答</small>
    </div>
</div>




        </div>

        <!-- 右側：對話區塊 -->
        <div class="main-chat">
            <div class="chat-header">
                <span>💬 AI 問答</span>
                <button id="clear-btn" class="btn" style="padding:0.3rem 0.8rem; font-size:0.8rem;">清除對話</button>
            </div>
            <div class="chat-log" id="chat-log"></div>
            <div class="chat-input-area">
                <textarea id="user-input" rows="1" placeholder="輸入問題，基於筆記內容回答...（Shift+Enter 換行）"></textarea>
                <button id="send-btn" class="btn">發送</button>
            </div>
        </div>
    </div>

    <!-- 手機浮動工具按鈕 -->
    <button class="mobile-tool-toggle" id="tool-toggle-btn" onclick="toggleSidebar()">📋</button>

    <script>
        // ----- 手機工具欄切換 -----
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('tool-toggle-btn');
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
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
        // 手機模式下點擊側邊欄內部連結時自動關閉（可選）
        sidebar.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') {
                // 稍微延遲關閉，讓操作先觸發
                setTimeout(closeSidebar, 300);
            }
        });

        // ---------- Markdown 轉換 ----------
        const noteName = <?= json_encode($note) ?>;
        const CHAT_STORAGE = 'nb_chat_' + noteName;
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
            const maxMsgs = MAX_ROUNDS * 2;
            if (history.length > maxMsgs) {
                history = history.slice(-maxMsgs);
            }
            localStorage.setItem(CHAT_STORAGE, JSON.stringify(history));
        }
        function clearHistory() {
            localStorage.removeItem(CHAT_STORAGE);
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
            return div; // 回傳元素，方便串流更新
        }

        // 初始化歷史
        (function() {
            const history = loadHistory();
            history.forEach(msg => addMessage(msg.role, msg.content));
        })();

        // --- 傳送訊息（串流版） ---
        const userInput = document.getElementById('user-input');
        const sendBtn = document.getElementById('send-btn');

        async function sendMessage() {
            const message = userInput.value.trim();
            if (!message) return;

            // 顯示使用者訊息
            addMessage('user', message);
            userInput.value = '';
            userInput.style.height = 'auto';

            // 停用按鈕，避免重複發送
            sendBtn.disabled = true;
            userInput.disabled = true;

            let history = loadHistory();
            history.push({ role: 'user', content: message });

            // 建立一個空的 assistant 訊息容器，稍後逐步填入
            const assistantDiv = addMessage('assistant', '⏳ 思考中...');
            let fullContent = '';

            try {
                const response = await fetch('api/chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        note: noteName,
                        message: message,
                        history: history
                    })
                });

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                // 讀取串流
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    // 保留最後一個可能不完整的行
                    buffer = lines.pop() || '';

                    for (let line of lines) {
                        line = line.trim();
                        if (line === '' || !line.startsWith('data: ')) continue;

                        const jsonStr = line.slice(6);
                        if (jsonStr === '[DONE]') {
                            continue; // 有些 API 會送結束標記，我們稍後統一處理
                        }

                        try {
                            const data = JSON.parse(jsonStr);
                            if (data.token) {
                                fullContent += data.token;
                                assistantDiv.innerHTML = marked.parse(fullContent, { breaks: true, gfm: true });
                                chatLog.scrollTop = chatLog.scrollHeight;
                            } else if (data.error) {
                                fullContent += `\n\n❌ ${data.error}`;
                                assistantDiv.innerHTML = marked.parse(fullContent, { breaks: true, gfm: true });
                            } else if (data.done) {
                                // 串流正常結束
                                break;
                            }
                        } catch (e) {
                            // 略過解析錯誤的行
                            console.warn('SSE parse error:', e, 'line:', line);
                        }
                    }
                }

                // 串流結束後，若內容為空（或只有錯誤），處理顯示
                if (fullContent === '') {
                    assistantDiv.innerHTML = marked.parse('❌ 未收到回應', { breaks: true });
                    fullContent = '❌ 未收到回應';
                }

            } catch (e) {
                assistantDiv.innerHTML = marked.parse('❌ 請求失敗: ' + e.message, { breaks: true });
                fullContent = '❌ 請求失敗: ' + e.message;
            } finally {
                // 儲存歷史（包含 assistant 最終內容）
                history.push({ role: 'assistant', content: fullContent });
                saveHistory(history);

                // 恢復按鈕狀態
                sendBtn.disabled = false;
                userInput.disabled = false;
                userInput.focus();
            }
        }

        // 監聽鍵盤事件：Enter 傳送，Shift+Enter 換行
        userInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // 自動調整 textarea 高度
        userInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        sendBtn.addEventListener('click', sendMessage);

        document.getElementById('clear-btn').addEventListener('click', function() {
            if (confirm('確定清除所有對話歷史？')) {
                clearHistory();
                chatLog.innerHTML = '';
            }
        });

        // ---------- 檔案列表渲染 ----------
        async function loadFiles() {
            try {
                const resp = await fetch('api/get_files.php?note=' + encodeURIComponent(noteName));
                const data = await resp.json();
                if (data.files) {
                    renderFileList(data.files);
                }
            } catch (e) {
                console.error('載入檔案列表失敗', e);
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

                const rewriteBtn = document.createElement('button');
                rewriteBtn.textContent = '🤖';
                rewriteBtn.title = 'AI 重寫';
                rewriteBtn.addEventListener('click', () => {
                    if (!confirm(`確定請 AI 重新整理「${file}」？\n原始檔案不受影響，會另存新檔。`)) return;
                    rewriteFile(file);
                });
                actions.appendChild(rewriteBtn);

                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'delete-btn';
                deleteBtn.textContent = '🗑️';
                deleteBtn.title = '刪除';
                deleteBtn.addEventListener('click', () => {
                    if (!confirm(`確定刪除「${file}」？`)) return;
                    deleteFile(file);
                });
                actions.appendChild(deleteBtn);

                li.appendChild(actions);
                fileList.appendChild(li);
            });
        }

        // ---------- 預覽 ----------
        async function previewFile(filename) {
            try {
                const resp = await fetch(`api/get_file_content.php?note=${encodeURIComponent(noteName)}&file=${encodeURIComponent(filename)}`);
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


// ---------- 分享功能 ----------
const toggleShareBtn = document.getElementById('toggle-share-btn');
const shareLinkBox = document.getElementById('share-link-box');
const shareStatusText = document.getElementById('share-status-text');
const copyShareBtn = document.getElementById('copy-share-btn');

// 初始化檢查目前分享狀態
async function checkShareStatus() {
    try {
        // 這裡直接讀取一個小 API 來確認 share.txt 是否存在
        const resp = await fetch('api/check_share.php?note=' + encodeURIComponent(noteName));
        const data = await resp.json();
        updateShareUI(data.shared);
    } catch (e) {
        shareStatusText.textContent = '無法讀取分享狀態';
    }
}

function updateShareUI(shared) {
    if (shared) {
        shareStatusText.textContent = '✅ 已公開分享';
        toggleShareBtn.textContent = '停用分享';
        shareLinkBox.style.display = 'block';
    } else {
        shareStatusText.textContent = '🔒 尚未分享';
        toggleShareBtn.textContent = '啟用分享';
        shareLinkBox.style.display = 'none';
    }
}

toggleShareBtn.addEventListener('click', async () => {
    const isCurrentlyShared = toggleShareBtn.textContent === '停用分享';
    const action = isCurrentlyShared ? 'disable' : 'enable';
    if (isCurrentlyShared && !confirm('停用後分享連結將失效，確定？')) return;

    try {
        const formData = new FormData();
        formData.append('note', noteName);
        formData.append('action', action);
        const resp = await fetch('api/toggle_share.php', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        if (data.success !== undefined) {
            updateShareUI(data.shared);
        } else {
            alert('操作失敗');
        }
    } catch (e) {
        alert('請求失敗：' + e.message);
    }
});

// 複製連結
copyShareBtn.addEventListener('click', () => {
    const shareUrl = document.getElementById('share-url');
    shareUrl.select();
    navigator.clipboard.writeText(shareUrl.value).then(() => {
        alert('連結已複製到剪貼簿');
    }).catch(() => {
        alert('手動複製：' + shareUrl.value);
    });
});

// 頁面載入時檢查
checkShareStatus();



        // ---------- AI 重寫 ----------
        async function rewriteFile(filename) {
            const formData = new FormData();
            formData.append('note', noteName);
            formData.append('filename', filename);
            try {
                const resp = await fetch('api/rewrite_file.php', { method: 'POST', body: formData });
                const data = await resp.json();
                if (data.error) {
                    alert(data.error);
                } else {
                    alert('整理完成！新檔案：' + data.new_filename);
                    loadFiles();
                }
            } catch (e) {
                alert('請求失敗：' + e.message);
            }
        }

        // ---------- 刪除 ----------
        async function deleteFile(filename) {
            const formData = new FormData();
            formData.append('note', noteName);
            formData.append('filename', filename);
            const resp = await fetch('api/delete_file.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.error) {
                alert(data.error);
            } else {
                loadFiles();
            }
        }

        // ---------- 檔案上傳與貼上 ----------
        document.getElementById('upload-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('note', noteName);
            formData.append('action', 'upload');
            const fileInput = this.querySelector('input[type="file"]');
            if (!fileInput.files.length) return alert('請選擇檔案');
            formData.append('file', fileInput.files[0]);
            const resp = await fetch('api/upload_text.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.error) {
                alert(data.error);
            } else {
                loadFiles();
            }
        });

        document.getElementById('paste-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('note', noteName);
            formData.append('action', 'paste');
            formData.append('text', this.querySelector('textarea').value);
            const resp = await fetch('api/upload_text.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.error) {
                alert(data.error);
            } else {
                this.querySelector('textarea').value = '';
                loadFiles();
            }
        });

        // ---------- 從網址抓取摘要 ----------
        const fetchBtn = document.getElementById('fetch-btn');
        const fetchUrlInput = document.getElementById('fetch-url');
        const fetchStatus = document.getElementById('fetch-status');

        fetchBtn.addEventListener('click', async function() {
            const url = fetchUrlInput.value.trim();
            if (!url) return;
            if (!url.startsWith('http://') && !url.startsWith('https://')) {
                alert('請輸入完整網址（包含 http:// 或 https://）');
                return;
            }
            fetchBtn.disabled = true;
            fetchStatus.textContent = '正在抓取網頁並請 AI 整理⋯';
            try {
                const formData = new FormData();
                formData.append('note', noteName);
                formData.append('url', url);
                const resp = await fetch('api/fetch_and_summarize.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();
                if (data.error) {
                    fetchStatus.textContent = '❌ ' + data.error;
                } else {
                    fetchStatus.textContent = '✅ 已存為 ' + data.filename;
                    fetchUrlInput.value = '';
                    loadFiles();
                }
            } catch (e) {
                fetchStatus.textContent = '❌ 請求失敗：' + e.message;
            } finally {
                fetchBtn.disabled = false;
            }
        });

        // 初次載入檔案列表
        loadFiles();
    </script>
</body>
</html>
