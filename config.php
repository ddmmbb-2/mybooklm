<?php
/**
 * 設定管理：讀取/儲存 config.json
 */

define('CONFIG_FILE', __DIR__ . '/config.json');

/**
 * 讀取完整設定
 * @return array
 */
function getConfig(): array {
    if (!file_exists(CONFIG_FILE)) {
        // 若不存在，回傳預設值
        return [
            'api_url' => 'http://localhost:11434/v1/chat/completions',
            'api_key' => 'not-needed',
            'model' => 'gemma4:e4b',
            'max_context_tokens' => 128000,
            'max_output_tokens' => 4096,
            'temperature' => 0.7,
            'use_multimodal' => false,
        ];
    }
    $content = file_get_contents(CONFIG_FILE);
    $config = json_decode($content, true);
    if (!is_array($config)) {
        return []; // 或丟出錯誤
    }
    return $config;
}

/**
 * 儲存設定
 * @param array $config
 * @return bool
 */
function saveConfig(array $config): bool {
    // 只保留允許的欄位
    $allowed = ['api_url', 'api_key', 'model', 'max_context_tokens', 'max_output_tokens', 'temperature', 'use_multimodal'];
    $clean = [];
    foreach ($allowed as $key) {
        $clean[$key] = $config[$key] ?? null;
    }
    // 強制轉型
    $clean['max_context_tokens'] = (int)($clean['max_context_tokens'] ?? 128000);
    $clean['max_output_tokens'] = (int)($clean['max_output_tokens'] ?? 4096);
    $clean['temperature'] = (float)($clean['temperature'] ?? 0.7);
    $clean['use_multimodal'] = !empty($clean['use_multimodal']);

    $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents(CONFIG_FILE, $json, LOCK_EX) !== false;
}