<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

// 检测是否是支持流式请求的模型
function is_stream_enabled($config) {
    return isset($config['stream']) && $config['stream'] === true;
}

// 1. 统一输出头（根据是否流式响应判断）
function send_stream_headers() {
    header("Content-Type: text/event-stream");
    header("Cache-Control: no-cache");
    header("X-Accel-Buffering: no");
    while (ob_get_level()) {
        ob_end_clean(); // 确保没有缓冲层
    }
    @ob_implicit_flush(true);
}


function send_json_headers() {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json");
}

// 2. 错误处理（统一用 JSON 输出）
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function(Throwable $e) {
    http_response_code(500);
    if (!headers_sent()) send_json_headers();
    echo json_encode(["error" => "Internal Server Error", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) send_json_headers();
        http_response_code(500);
        echo json_encode(["error" => "Fatal Error", "message" => $err['message']], JSON_UNESCAPED_UNICODE);
    }
});

// 3. 主处理流程
try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON 解码错误: " . json_last_error_msg());
    }

    $provider     = $data['provider']     ?? '';
    $conversation = $data['conversation'] ?? [];

    if (!$provider || !is_array($conversation)) {
        http_response_code(400);
        send_json_headers();
        echo json_encode(["error" => "缺少 provider 或 conversation 参数"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $configPath = __DIR__ . "/../config/{$provider}.json";
    if (!file_exists($configPath)) {
        http_response_code(404);
        send_json_headers();
        echo json_encode(["error" => "未找到配置文件"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $config = json_decode(file_get_contents($configPath), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("配置文件 JSON 解析失败: " . json_last_error_msg());
    }

    require_once $config['template'];

    // 🔀 判断是否为流模式
    if (is_stream_enabled($config)) {
        send_stream_headers();
        stream_response($config, $conversation);
    } else {
        send_json_headers();
        $res = make_api_call($config, $conversation);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }
}
catch (Throwable $e) {
    http_response_code(500);
    if (!headers_sent()) send_json_headers();
    echo json_encode(["error" => "Unhandled Exception", "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}


?>
