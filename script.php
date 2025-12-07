<?php
// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 定义文件路径
$logFile = 'wj.txt';
$outputFile = 'sbip.txt';
$lockFile = 'ip_check.lock';
$queueFile = 'ip_check.queue';

// 检查是否是GET请求
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 检查是否已经有进程在运行
    if (file_exists($lockFile)) {
        // 检查锁文件时间，如果超过1小时则视为过期
        $lockTime = filemtime($lockFile);
        if (time() - $lockTime < 3600) {
            // 将请求加入队列
            file_put_contents($queueFile, time() . PHP_EOL, FILE_APPEND);
            echo "请求已加入队列，将在稍后处理";
            exit;
        } else {
            // 锁已过期，删除旧锁文件
            unlink($lockFile);
        }
    }
    
    // 创建锁文件
    touch($lockFile);
    
    try {
        // 执行IP检查
        checkSuspiciousIPs();
        
        // 处理队列中的请求（如果有）
        if (file_exists($queueFile)) {
            $queueRequests = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!empty($queueRequests)) {
                // 只保留1小时内的请求
                $oneHourAgo = time() - 3600;
                $validRequests = array_filter($queueRequests, function($time) use ($oneHourAgo) {
                    return $time >= $oneHourAgo;
                });
                
                if (!empty($validRequests)) {
                    // 重新处理这些请求
                    checkSuspiciousIPs();
                }
                
                // 清空队列文件
                file_put_contents($queueFile, '');
            }
        }
        
        echo "IP检查完成";
    } finally {
        // 释放锁
        unlink($lockFile);
    }
} else {
    echo "请使用GET请求访问此脚本";
}

function checkSuspiciousIPs() {
    global $logFile, $outputFile;
    
    // 获取当前时间并计算上个小时的时间范围
    $currentHour = date('Y-m-d H');
    $previousHour = date('Y-m-d H', strtotime('-1 hour'));
    $startTime = strtotime($previousHour . ':00:00');
    $endTime = strtotime($currentHour . ':00:00') - 1;

    // 读取日志文件
    $ipCounts = [];
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // 假设每行格式为: [时间] IP地址 操作 (例如: [2023-05-01 12:30:45] 192.168.1.1 获取账号)
            if (preg_match('/^\[([^\]]+)\]\s+(\S+)/', $line, $matches)) {
                $timeStr = $matches[1];
                $ip = $matches[2];
                $time = strtotime($timeStr);
                
                // 检查时间是否在上个小时内
                if ($time >= $startTime && $time <= $endTime) {
                    if (!isset($ipCounts[$ip])) {
                        $ipCounts[$ip] = 0;
                    }
                    $ipCounts[$ip]++;
                }
            }
        }
    }

    // 筛选获取10个或以上账号的IP
    $suspiciousIPs = [];
    foreach ($ipCounts as $ip => $count) {
        if ($count >= 10) {
            $suspiciousIPs[] = $ip;
        }
    }

    // 将可疑IP写入输出文件
    if (!empty($suspiciousIPs)) {
        // 读取已存在的IP以避免重复
        $existingIPs = [];
        if (file_exists($outputFile)) {
            $existingLines = file($outputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($existingLines as $line) {
                $existingIPs[trim($line)] = true;
            }
        }
        
        // 追加新IP
        $fileHandle = fopen($outputFile, 'a');
        foreach ($suspiciousIPs as $ip) {
            $ip = trim($ip);
            if (!isset($existingIPs[$ip])) {
                fwrite($fileHandle, $ip . PHP_EOL);
                $existingIPs[$ip] = true; // 避免本次循环中的重复
            }
        }
        fclose($fileHandle);
    }

    // 记录执行时间（可选）
    file_put_contents('ip_check.log', '[' . date('Y-m-d H:i:s') . '] 检查完成，发现 ' . count($suspiciousIPs) . ' 个可疑IP' . PHP_EOL, FILE_APPEND);
}
?>