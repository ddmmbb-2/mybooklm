<?php
// 強制關閉 PHP 壓縮與緩衝
ini_set('zlib.output_compression', 'Off');
ini_set('output_buffering', 'Off');
// 關閉所有輸出緩衝層
while (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

for ($i = 0; $i < 10; $i++) {
    echo "data: " . json_encode(['token' => "測試 $i "]) . "\n\n";
    ob_flush();
    flush();
    sleep(1);
}
echo "data: " . json_encode(['done' => true]) . "\n\n";