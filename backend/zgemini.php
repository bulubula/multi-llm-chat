<?php

/*
transform_conversation
前端对话转换函数
convertGeminiToOpenAI
返回对话转换函数
make_api_call
非流式请求函数
stream_response
流式请求函数
*/

ini_set('display_errors', '0');              // 生产环境关掉页面输出
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/zgemini_error.log');
error_reporting(E_ALL);
function debug($msg) {
    file_put_contents(__DIR__.'/zgemini_debug.log', date('c').' '.$msg."\n", FILE_APPEND);
}


function transform_conversation($conversation) {
  $result = [];
  foreach ($conversation as $msg) {
	$role = $msg['role'] === 'assistant' ? 'model' : $msg['role'];
    $result[] = [
      'role'  => $role,
      'parts' => [
        ['text' => $msg['text']]
      ]
    ];
  }

  return $result;
}


function convertGeminiToOpenAI(string $geminiJson): string {
    $data = json_decode($geminiJson, true);
    // 构建基础结构
    $openai = [
        "id"      => uniqid(),
        "object"  => "chat.completion",
        "created" => time(),
        "model"   => $data['modelVersion'] ?? "unknown-model",
        "choices" => [],
        "usage"   => []
    ];
    // 如果有 candidates，就正常解析
    if (!empty($data['candidates']) && is_array($data['candidates'])) {
        foreach ($data['candidates'] as $idx => $cand) {
            $role    = $cand['content']['role']           ?? 'assistant';
            $message = $cand['content']['parts'][0]['text'] ?? '';
            $finish  = strtolower($cand['finishReason']    ?? 'stop');

            $openai['choices'][] = [
                "index"         => $idx,
                "message"       => [
                    "role"    => $role,
                    "content" => $message
                ],
                "logprobs"      => null,
                "finish_reason" => $finish
            ];
        }
    }
    else {
        // 从 Gemini 响应里抓 error 信息
        $errInfo = $data['error'] 
                 ?? ["message" => "Gemini 未返回 candidates，也未提供 error"];
        $errorMsg = is_array($errInfo) 
                  ? ($errInfo['message'] ?? json_encode($errInfo)) 
                  : $errInfo;

        // 返回一个只含 error 和空 choices 的结构
        $openai = [
            "id"      => uniqid(),
            "object"  => "chat.completion",
            "created" => time(),
            "model"   => $data['modelVersion'] ?? "unknown-model",
            "choices" => [],
            "error"   => $errorMsg,
            "usage"   => []
        ];

        return json_encode($openai, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    // 解析 usageMetadata（如果有的话）
    $usage = $data['usageMetadata'] ?? [];
    $openai['usage'] = [
        "prompt_tokens"            => $usage['promptTokenCount']    ?? 0,
        "completion_tokens"        => $usage['candidatesTokenCount'] ?? 0,
        "total_tokens"             => $usage['totalTokenCount']      ?? 0,
        "prompt_tokens_details"    => ["cached_tokens" => 0],
        "prompt_cache_hit_tokens"  => 0,
        "prompt_cache_miss_tokens" => $usage['promptTokenCount']     ?? 0
    ];
    // 最终返回字符串
    return json_encode($openai, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function convertGeminiChunkToOpenAIChunk(string $geminiJson): string {
    $data = json_decode($geminiJson, true);
    $now  = time();
    $model = $data['modelVersion'] ?? ($data['model'] ?? 'unknown-model');
    // 取第一个 candidate 的第一个文本段
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    $chunk = [
        "id"      => uniqid('', true),
        "object"  => "chat.completion.chunk",
        "created" => $now,
        "model"   => $model,
        "choices" => [
            [
                "index" => 0,
                "delta" => ["content" => $text],
                "finish_reason" => null
            ]
        ]
    ];

    return json_encode($chunk, JSON_UNESCAPED_UNICODE);
}



function make_api_call($config, $conversation) {

    $endpoint = $config['endpoint'] . '?model=' . urlencode($config['model']);
    if ($isStream) {
        $endpoint .= '&stream=true';
    }

    $messages = transform_conversation($conversation);
    $postData = [
        'contents' => $messages
    ];

    $headers = [];
    foreach ($config['headers'] as $key => $value) {
        $headers[] = "$key: $value";
    }
	
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return ['error' => curl_error($ch)];
    }

    curl_close($ch);
    return json_decode(convertGeminiToOpenAI($response), true);
}

function stream_response($config, $conversation) {
    // 1. 把前端的 conversation 转成 Gemini 需要的 contents 格式
    $messages = transform_conversation($conversation);
    $postData = [
        'contents' => $messages
    ];
    // 如果有额外 body（如 temperature 等），也合并进来
    if (isset($config['params']['extra_body'])) {
        $postData = array_merge($postData, $config['params']['extra_body']);
    }
    $headers = [];
    foreach ($config['headers'] as $k => $v) {
        $headers[] = "$k: $v";
    }
    // 3. 发起 CURL 并且把底层 SSE 逐条透传给浏览器
    $ch = curl_init($config['endpoint']."?stream=true");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_WRITEFUNCTION  => function($ch, $raw) {
            // 按行分解原生 SSE
            foreach (explode("\n", trim($raw)) as $line) {
                $line = trim($line);
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

                $jsonStr = substr($line, strlen('data:'));
                // 把 Gemini chunk 转成 OpenAI chunk
                $oas = convertGeminiChunkToOpenAIChunk($jsonStr);
                // 再包装成 SSE 发出去
                echo "data: {$oas}\n\n";
                flush();
            }
            return strlen($raw);
        }
    ]);
    curl_exec($ch);
    curl_close($ch);
    echo "data: [DONE]\n\n";
    flush();
    exit;
}


?>
