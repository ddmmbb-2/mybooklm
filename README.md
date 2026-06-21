# mybooklm

一款 **NotebookLM 風格** 的 PHP 知識管理工具。讓你的筆記與檔案透過 AI 即時對話、重寫與結構化整理。

---

## 📖 簡介

mybooklm 是一個輕量級、自架式的知識庫系統，專為個人或團隊設計。你可以：

- 建立多本「筆記」，每本筆記內可上傳 `.txt` 檔案、貼上文字、或從網頁抓取內容。
- 所有筆記內容都會成為 AI 的上下文，讓你**與筆記對話**，快速提取重點或進行結構化整理。
- 內建**管理員 / 使用者**角色，可開放分享筆記給他人（唯讀），實現協作閱讀。

它適用於：
- 個人學習筆記與研究
- 團隊內部知識庫
- 課程資料彙整與問答
- 任何需要「文件 + AI 問答」的情境

---

## ✨ 功能特色

- **📝 筆記管理** – 建立、刪除筆記，支援中文名稱。
- **📄 檔案管理** – 上傳 `.txt`、貼上文字、從網址抓取網頁內容，自動生成摘要筆記。
- **🤖 AI 整合** – 支援 **OpenAI 相容 API**（如 Ollama、LM Studio、GPT-4），可：
  - 即時問答（串流輸出，打字機效果）
  - 重新整理檔案內容（AI 重寫為結構化筆記）
- **🔗 公開分享** – 每個筆記可獨立開啟／關閉分享，產生唯讀連結，適合外部閱讀與問答。
- **👥 使用者系統** – 基於 SQLite，支援普通使用者與管理員，管理員可管理使用者與 API 設定。
- **📱 響應式設計** – 手機、平板、桌面皆可流暢操作。
- **🔒 安全設計** – `.htaccess` 保護敏感檔案，密碼使用 `password_hash` 儲存。

---

## 🗂️ 目錄結構

```
mybooklm/
├── .htaccess                  # 根目錄安全設定
├── config.php                 # 設定讀寫函式
├── config.json                # API 設定（自動生成）
├── index.php                  # 筆記列表（登入後首頁）
├── note.php                   # 單本筆記操作介面
├── sharenote.php              # 公開分享頁（唯讀）
├── login.php                  # 登入頁
├── logout.php                 # 登出
├── admin.php                  # 使用者管理（僅管理員）
├── settings.php               # API 設定（僅管理員）
├── install.php                # 安裝腳本（執行後請立即刪除）
│
├── api/                       # 後端 API
│   ├── chat.php               # 流式 AI 問答（SSE）
│   ├── upload_text.php        # 上傳 / 貼上檔案
│   ├── delete_file.php        # 刪除檔案
│   ├── delete_note.php        # 刪除筆記
│   ├── get_files.php          # 取得檔案清單
│   ├── get_file_content.php   # 讀取檔案內容
│   ├── rewrite_file.php       # AI 重新整理檔案
│   ├── fetch_and_summarize.php # 抓取網頁並摘要
│   ├── toggle_share.php       # 切換分享狀態
│   ├── check_share.php        # 查詢分享狀態
│   ├── check_auth.php         # API 認證（已修正）
│   └── test_sse.php           # SSE 測試工具
│
├── lib/                       # 核心函式庫
│   ├── Auth.php               # 使用者認證（SQLite）
│   ├── FileManager.php        # 筆記與檔案操作
│   ├── OpenAIClient.php       # OpenAI 相容 API 客戶端
│   └── marked.min.js          # Markdown 渲染（前端）
│
├── data/                      # 使用者資料（受 .htaccess 保護）
│   ├── .htaccess              # 禁止直接存取
│   ├── users.sqlite           # 使用者帳號資料庫
│   ├── shared_index.txt       # 分享筆記索引
│   └── {username}/            # 每位使用者的筆記目錄
│       └── {note_name}/
│           ├── note/          # 存放所有 .txt 檔案
│           ├── url.txt        # 存放網址（保留擴充）
│           └── share.txt      # 存在表示公開分享
│
└── README.md                  # 本文件
```

---

## 🚀 安裝步驟

### 系統需求

- PHP 7.4 以上（建議 8.0+）
- SQLite 3
- cURL 擴展
- JSON 擴展
- Apache / Nginx + PHP-FPM（需支援 `.htaccess` 或手動設定）

### 1. 下載原始碼

```bash
git clone https://github.com/yourusername/mybooklm.git
cd mybooklm
```

### 2. 設定目錄權限

```bash
chmod -R 755 data/
chmod 640 data/users.sqlite   # 若已存在
touch config.json
chmod 640 config.json
```

### 3. 建立管理員帳號

複製 `install.txt` 為 `install.php`，並編輯內容：

```php
<?php
require_once __DIR__ . '/lib/Auth.php';
Auth::createUser('admin', '你的密碼', 'admin');
echo '管理員建立完成，請立即刪除 install.php';
```

執行此檔案（瀏覽器訪問），建立完成後**務必立即刪除 `install.php`**。

### 4. 設定 API（至少需要一個 AI 後端）

編輯 `settings.php`（需管理員登入）或直接編輯 `config.json`，填入：

```json
{
    "api_url": "http://localhost:11434/v1/chat/completions",
    "api_key": "not-needed",
    "model": "gemma2:9b",
    "max_context_tokens": 128000,
    "max_output_tokens": 4096,
    "temperature": 0.7,
    "use_multimodal": false
}
```

若使用 **Ollama**，請確保已啟動並下載對應模型。  
若使用 OpenAI，請填入正確的 `api_url`（如 `https://api.openai.com/v1/chat/completions`）及 `api_key`。

### 5. 網頁伺服器設定

- **Apache**：直接使用專案內 `.htaccess`（需啟用 `mod_rewrite`、`mod_headers`）。
- **Nginx**：需手動設定類似規則（禁止存取 `data/`、`config.json` 等）。

### 6. 訪問

開啟瀏覽器，進入網站根目錄，使用剛才建立的管理員帳號登入。

---

## 🧑‍💻 使用說明

### 一般使用者

- 登入後看到「我的知識庫」→ 可建立新筆記。
- 點擊筆記進入，左側為管理區：
  - **文件管理**：上傳 `.txt`、貼上文字、預覽、刪除、AI 重寫。
  - **從網址建立筆記**：輸入網址，AI 自動抓取並生成摘要檔案。
  - **分享設定**：開啟後產生公開連結。
- 右側為 **AI 問答區**：輸入問題，AI 會基於所有檔案內容回答（串流輸出）。

### 管理員

- 可進入 **使用者管理**（`admin.php`）新增、刪除、變更角色。
- 可進入 **API 設定**（`settings.php`）調整模型參數。

---

## 🔐 安全性提醒

- 部署後請**務必刪除** `install.php`。
- 確保 `data/` 目錄無法透過網頁直接存取（`.htaccess` 已設定，Nginx 請手動拒絕）。
- 定期備份 `data/` 目錄。
- 建議將 `config.json` 權限設為 640，僅 Web 使用者可讀寫。
- 若使用公開網路，建議啟用 HTTPS。

---

## 🧩 技術棧

- **後端**：PHP 7.4+、SQLite、cURL
- **前端**：原生 JavaScript、CSS、`marked.js`（Markdown 渲染）
- **AI 通訊**：OpenAI 相容 API（SSE 串流）
- **認證**：Session + `password_hash`

---

## 🤝 貢獻

歡迎提交 Issue 或 Pull Request。  
請確保代碼風格與現有保持一致，並為新功能撰寫簡單說明。

---

## 📄 授權

本專案採用 [MIT 授權](LICENSE)。可自由使用、修改、商用，但需保留版權聲明。

---

## 🌟 致謝

- [marked.js](https://marked.js.org/) 提供 Markdown 解析
- [Ollama](https://ollama.com/) 提供本地 AI 模型

---

**開始你的知識管理之旅！** 🚀
