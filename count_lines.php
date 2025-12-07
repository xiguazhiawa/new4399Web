<?php
// 定义文件名
$filename = 'zhanghao.txt';

// 检查文件是否存在
if (file_exists($filename)) {
    // 获取文件行数
    $lineCount = count(file($filename));
    // 直接返回行数
    echo $lineCount;
} else {
    // 如果文件不存在，返回错误信息
    echo "Error: File not found";
}
?>