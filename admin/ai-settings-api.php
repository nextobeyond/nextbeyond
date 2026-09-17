<?php
declare(strict_types=1);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
require_once __DIR__.'/../includes/db.php';require_once __DIR__.'/../includes/ai-settings.php';
require_once __DIR__.'/includes/access.php';
try{
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
  $savedKey=aiSettingsGetKey($pdo);
  $maskedKey=$savedKey!==''?'••••••••'.substr($savedKey,-4):null;
  echo json_encode(['configured'=>$savedKey!=='','maskedKey'=>$maskedKey],JSON_UNESCAPED_UNICODE);
  exit;
 }
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){$d=json_decode(file_get_contents('php://input'),true);$key=trim((string)($d['apiKey']??''));if($key===''){http_response_code(422);echo json_encode(['error'=>'กรุณากรอก API Key']);exit;}aiSettingsSetKey($key);echo json_encode(['success'=>true,'configured'=>true]);exit;}
 http_response_code(405);echo json_encode(['error'=>'Method not allowed']);
}catch(Throwable $e){error_log('AI settings: '.$e->getMessage());http_response_code(500);echo json_encode(['error'=>'บันทึก API Key ลงฐานข้อมูลไม่สำเร็จ']);}
