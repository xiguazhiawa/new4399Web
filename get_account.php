<?php
header('Content-Type: application/json');
$filename = 'zhanghao.txt';
$ipFile = 'ip_records.json';
$logFile = 'wj.txt';
$banFile = 'sbip.txt'; // 封禁IP列表文件
$cooldown = 300;

function getClientIP() {
    $ip = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    elseif (isset($_SERVER['REMOTE_ADDR']))
        $ip = $_SERVER['REMOTE_ADDR'];
    return $ip;
}

// 检查IP是否被封禁
function isIPBanned($ip) {
    global $banFile;
    if (!file_exists($banFile)) {
        return false;
    }
    $bannedIPs = file($banFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($ip, $bannedIPs);
}

function readIPRecords() {
    global $ipFile;
    if (!file_exists($ipFile)) {
        file_put_contents($ipFile, '{}');
        return [];
    }
    $content = file_get_contents($ipFile);
    return json_decode($content, true) ?: [];
}

function writeIPRecords($data) {
    global $ipFile;
    $fp = fopen($ipFile, 'w');
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function logSuccessfulAccount($ip, $account) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $fp = fopen($logFile, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, "{$timestamp} - {$ip}  {$account}" . PHP_EOL);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

try {
    $clientIP = getClientIP();
    
    // 检查IP是否被封禁
    if (isIPBanned($clientIP)) {
        die(json_encode([
            'success' => false,
            'error' => 'banned',
            'message' => '你已被封禁，无法获取账号'
        ]));
    }

    $ipRecords = readIPRecords();
    $now = time();

    if (isset($ipRecords[$clientIP])) {
        $lastTime = $ipRecords[$clientIP];
        $elapsed = $now - $lastTime;
        
        if ($elapsed < $cooldown) {
            $remaining = $cooldown - $elapsed;
            die(json_encode([
                'success' => false,
                'error' => 'cooldown',
                'message' => "请等待 {$remaining} 秒后再试",
                'cooldown_end' => $lastTime + $cooldown
            ]));
        }
    }

    if (!file_exists($filename)) {
        die(json_encode(['success' => false, 'message' => '账号获取失败110']));
    }

    $handle = fopen($filename, 'r+');
    if (!$handle) {
        die(json_encode(['success' => false, 'message' => '账号获取失败101']));
    }
    flock($handle, LOCK_EX);

    $lines = [];
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    if (empty($lines)) {
        flock($handle, LOCK_UN);
        fclose($handle);
        die(json_encode(['success' => false, 'message' => '没有更多账号了']));
    }

    $account = array_shift($lines);

    ftruncate($handle, 0);
    rewind($handle);
    foreach ($lines as $line) {
        fwrite($handle, $line . PHP_EOL);
    }

    flock($handle, LOCK_UN);
    fclose($handle);

    $ipRecords[$clientIP] = $now;
    writeIPRecords($ipRecords);

    logSuccessfulAccount($clientIP, $account);

    echo json_encode([
        'success' => true,
        'account' => $account,
        'cooldown_end' => $now + $cooldown
    ]);

} catch (Exception $e) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => '服务器错误: ' . $e->getMessage()
    ]));
}
?>