<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
// 统一输出 JSON，并允许跨域
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// 1. 将 PHP 错误转为 Exception
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// 2. 捕获未被 try/catch 的 Exception
set_exception_handler(function(Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "error"   => "Internal Server Error",
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// 3. 捕获致命错误（比如 parse error、memory limit 等）
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            "error"   => "Fatal Error",
            "message" => $err['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

// 主流程放入 try/catch，保证任何 throw 都能被捕获
try {
    // 读取输入
    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON 解码错误: " . json_last_error_msg());
    }

    $provider     = $data['provider']     ?? '';
    $conversation = $data['conversation'] ?? [];

    if (!$provider || !is_array($conversation)) {
        http_response_code(400);
        echo json_encode(["error" => "缺少 provider 或 conversation 参数"]);
        exit;
    }

    // 加载配置
    $configPath = __DIR__ . "/../config/{$provider}.json";
    if (!file_exists($configPath)) {
        http_response_code(404);
        echo json_encode(["error" => "未找到对应的配置文件"]);
        exit;
    }
    $config = json_decode(file_get_contents($configPath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("配置文件 JSON 解析失败: " . json_last_error_msg());
    }

    // 包含适配层
    require_once $config['template'];

    // 调用适配层
    $response = make_api_call($config, $conversation);

    // 最终输出
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
catch (Throwable $e) {
    // 已由 set_exception_handler 处理，这里仅作保险
    http_response_code(500);
    echo json_encode([
        "error"   => "Unhandled Exception",
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
