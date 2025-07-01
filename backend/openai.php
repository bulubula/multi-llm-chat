<?php

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
	
  $postData = array_merge([
    'model' => $config['model'],
    'messages' => $messages
  ], $config['params'] ?? []);

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
?>