<?php
/**
 * OpenAI 相容 API 客戶端（修正版）
 */

class OpenAIClient {
    private $apiUrl;
    private $apiKey;

    public function __construct($apiUrl, $apiKey = '') {
        // 直接儲存完整端點，不額外處理
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
    }

    public function chat($messages, $options = []) {
        $payload = [
            'model'       => isset($options['model']) ? $options['model'] : 'gemma4:e4b',
            'messages'    => $messages,
            'max_tokens'  => isset($options['max_tokens']) ? (int)$options['max_tokens'] : 8912,
            'temperature' => isset($options['temperature']) ? (float)$options['temperature'] : 0.7,
            'stream'      => false,
        ];

        $ch = curl_init($this->apiUrl);  // 直接使用完整 URL
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('API 連線失敗: ' . $error);
        }
        if ($httpCode !== 200) {
            $errMsg = "HTTP $httpCode";
            $body = json_decode($response, true);
            if (isset($body['error']['message'])) {
                $errMsg .= ' - ' . $body['error']['message'];
            }
            throw new RuntimeException('API 回應錯誤: ' . $errMsg);
        }

        $data = json_decode($response, true);
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new RuntimeException('API 回應格式不符預期');
        }
        return $data['choices'][0]['message']['content'];
    }
}