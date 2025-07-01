<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$storagePath = "../config/conversations.json";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  echo file_exists($storagePath) ? file_get_contents($storagePath) : '[]';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = file_get_contents("php://input");
  if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => '未收到数据']);
    exit;
  }

  $result = file_put_contents($storagePath, $data, LOCK_EX);
  echo json_encode([
    'status' => $result !== false ? 'success' : 'error',
    'message' => $result !== false ? '会话保存成功' : '保存失败'
  ]);
  exit;
}

http_response_code(405);
echo json_encode(['error' => '不支持的请求方法']);