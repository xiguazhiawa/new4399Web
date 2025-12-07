<?php
$chatFile = "sbip.txt";  // 存储聊天记录的文件

// 检查是否有新消息提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = htmlspecialchars($_POST["message"]);  // 防止XSS攻击
    if (!empty($message)) {
        // 将消息写入文件
        file_put_contents($chatFile, $message . "\n", FILE_APPEND);
    }
}

// 读取聊天记录并显示
if (file_exists($chatFile)) {
    $messages = file($chatFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($messages as $msg) {
        echo "<div class='message'><span class='text'>$msg</span></div>";
    }
}
?>