<?php
/*
transform_conversation
前端对话转换函数
make_api_call
非流式请求函数
stream_response
流式请求函数
*/

function transform_conversation($conversation) {
  $result = [];
  foreach ($conversation as $msg) {
    $result[] = [
      'role' => $msg['role'],
      'content' => $msg['text']
    ];
  }
  return $result;
}

function make_api_call($config, $conversation) {
	
	$messages = transform_conversation($conversation);
	
  $postData = [
    'model' => $config['model'],
    'messages' => $messages
  ];
  
    // 将 extra_body 提升为顶层字段
  if (isset($config['params']['extra_body'])) {
    $postData['extra_body'] = $config['params']['extra_body'];
  }

  $headers = [];
  foreach ($config['headers'] as $key => $value) {
    $headers[] = "$key: $value";
  }

  $ch = curl_init($config['endpoint']);
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
  return json_decode($response, true);
}
// 流式转发函数
function stream_response($config, $conversation) {
    $messages = transform_conversation($conversation);
    $postData = [
        'model' => $config['model'],
        'messages' => $messages,
        'stream' => true
    ];

    if (isset($config['params']['extra_body'])) {
        $postData = array_merge($postData, $config['params']['extra_body']);
    }

    $headers = [];
    foreach ($config['headers'] as $key => $value) {
        $headers[] = "$key: $value";
    }

    $ch = curl_init($config['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_WRITEFUNCTION => function($ch, $data) {
			// 避免出现 data: data: {...}
			foreach (explode("\n", $data) as $line) {
				$line = trim($line);
				if ($line === '') continue;
					echo $line . "\n";
			}

			flush();
			return strlen($data);
		}
    ]);

    curl_exec($ch);
    curl_close($ch);

    // 标记结束
    echo "data: [DONE]\n\n";
    flush();
}
?>
