<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
$cart =& $_SESSION['cart'];

function cart_count($cart){ $c=0; foreach($cart as $l){ $c+=(int)($l['qty']??0);} return $c; }
function cart_summary($conn,$cart){
  $items=[];$rent=0;$deposit=0;
  foreach($cart as $key=>$line){
    $id=(int)($line['id']??0); $qty=max(1,(int)($line['qty']??1));
    $receive=$line['receive']??''; $ret=$line['return']??'';
    if($id<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$receive) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$ret)) continue;
    $stmt=$conn->prepare("SELECT c.id,c.name,c.rental_price,c.deposit,c.image,c.size,cat.name AS cat_name FROM costumes c LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.id=? AND c.status<>'retired'");
    $stmt->bind_param('i',$id);$stmt->execute();$p=$stmt->get_result()->fetch_assoc();
    if(!$p) continue;
    $days=rental_days_count($receive,$ret);
    $lineRent=(float)$p['rental_price']*$qty*$days;
    $lineDep=(float)$p['deposit']*$qty;
    $rent+=$lineRent; $deposit+=$lineDep;
    $items[]=['key'=>$key,'id'=>$id,'name'=>$p['name'],'cat'=>$p['cat_name'],'size'=>$p['size'],'image'=>$p['image'],'qty'=>$qty,'receive'=>$receive,'return'=>$ret,'days'=>$days,'rent'=>$lineRent,'deposit'=>$lineDep];
  }
  return ['items'=>$items,'rent'=>$rent,'deposit'=>$deposit,'total'=>$rent+$deposit,'count'=>cart_count($cart)];
}

$action=$_POST['action'] ?? $_GET['action'] ?? 'get';

if($action==='add'){
  $id=(int)($_POST['id']??0); $qty=max(1,(int)($_POST['qty']??1));
  $receive=$_POST['receive']??''; $ret=$_POST['return']??'';
  if($id<=0) { echo json_encode(['ok'=>false,'message'=>'Sản phẩm không hợp lệ']); exit; }
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$receive) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$ret) || strtotime($ret)<strtotime($receive)){
    echo json_encode(['ok'=>false,'message'=>'Vui lòng chọn ngày nhận/trả hợp lệ']); exit;
  }
  $avail=available_quantity($conn,$id,$receive,$ret,0);
  if($avail < $qty){ echo json_encode(['ok'=>false,'message'=>'Sản phẩm không đủ số lượng trống trong thời gian đã chọn']); exit; }
  $key=$id.'_'.$receive.'_'.$ret;
  if(isset($cart[$key])){ $cart[$key]['qty'] += $qty; } else { $cart[$key]=['id'=>$id,'qty'=>$qty,'receive'=>$receive,'return'=>$ret]; }
  if($cart[$key]['qty'] > $avail) $cart[$key]['qty']=$avail;
  echo json_encode(['ok'=>true,'message'=>'Đã thêm vào giỏ thuê','cart'=>cart_summary($conn,$cart)], JSON_UNESCAPED_UNICODE); exit;
}

if($action==='update'){
  $key=$_POST['key']??'';
  if(!isset($cart[$key])){ echo json_encode(['ok'=>false,'message'=>'Mục giỏ thuê không tồn tại'], JSON_UNESCAPED_UNICODE); exit; }
  $line=$cart[$key];
  $id=(int)($line['id']??0);
  $qty=max(1,(int)($_POST['qty']??($line['qty']??1)));
  $receive=$_POST['receive']??($line['receive']??'');
  $ret=$_POST['return']??($line['return']??'');
  if($id<=0) { echo json_encode(['ok'=>false,'message'=>'Sản phẩm không hợp lệ'], JSON_UNESCAPED_UNICODE); exit; }
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$receive) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$ret) || strtotime($ret)<strtotime($receive)){
    echo json_encode(['ok'=>false,'message'=>'Vui lòng chọn ngày nhận/trả hợp lệ'], JSON_UNESCAPED_UNICODE); exit;
  }
  $avail=available_quantity($conn,$id,$receive,$ret,0);
  if($avail < 1){ echo json_encode(['ok'=>false,'message'=>'Sản phẩm không còn trống trong thời gian đã chọn'], JSON_UNESCAPED_UNICODE); exit; }
  if($qty > $avail) $qty=$avail;
  $newKey=$id.'_'.$receive.'_'.$ret;
  unset($cart[$key]);
  if(isset($cart[$newKey])){ $cart[$newKey]['qty']=min($avail, ((int)$cart[$newKey]['qty'])+$qty); }
  else { $cart[$newKey]=['id'=>$id,'qty'=>$qty,'receive'=>$receive,'return'=>$ret]; }
  echo json_encode(['ok'=>true,'message'=>'Đã cập nhật giỏ thuê','cart'=>cart_summary($conn,$cart)], JSON_UNESCAPED_UNICODE); exit;
}

if($action==='remove'){
  $key=$_POST['key']??''; if(isset($cart[$key])) unset($cart[$key]);
  echo json_encode(['ok'=>true,'cart'=>cart_summary($conn,$cart)], JSON_UNESCAPED_UNICODE); exit;
}

if($action==='clear'){
  $cart=[];
  echo json_encode(['ok'=>true,'cart'=>cart_summary($conn,$cart)], JSON_UNESCAPED_UNICODE); exit;
}

echo json_encode(['ok'=>true,'cart'=>cart_summary($conn,$cart)], JSON_UNESCAPED_UNICODE);
