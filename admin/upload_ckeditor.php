<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
if(!isset($_SESSION['admin'])){echo json_encode(['uploaded'=>0,'error'=>['message'=>'Không có quyền truy cập']]);exit;}
if(!isset($_FILES['upload'])||$_FILES['upload']['error']!==UPLOAD_ERR_OK){echo json_encode(['uploaded'=>0,'error'=>['message'=>'Không nhận được ảnh']]);exit;}
$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
$mime=mime_content_type($_FILES['upload']['tmp_name']);
if(!isset($allowed[$mime])||$_FILES['upload']['size']>5*1024*1024){echo json_encode(['uploaded'=>0,'error'=>['message'=>'Ảnh không hợp lệ']]);exit;}
$name='editor-'.date('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.$allowed[$mime];
if(!move_uploaded_file($_FILES['upload']['tmp_name'],'../uploads/'.$name)){echo json_encode(['uploaded'=>0,'error'=>['message'=>'Không thể lưu ảnh']]);exit;}
echo json_encode(['uploaded'=>1,'fileName'=>$name,'url'=>BASE_URL.'uploads/'.$name],JSON_UNESCAPED_SLASHES);
?>