<?php

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
    // 否则，视为错误
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


function make_api_call($config, $conversation) {
	
	$endpoint = $config['endpoint'] . '?model=' . $config['model'];
	$messages = transform_conversation($conversation);
	$postData = [
	  'contents' => $messages
	];

  $headers = [];
  foreach ($config['headers'] as $key => $value) {
    $headers[] = "$key: $value";
  }

  $ch = curl_init($config['endpoint']);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($postData)
  ]);

  $response = curl_exec($ch);

  if (curl_errno($ch)) {
    return ['error' => curl_error($ch)];
  }
  curl_close($ch);
  //return json_decode($response, true);
  return json_decode(convertGeminiToOpenAI($response));
}
?>