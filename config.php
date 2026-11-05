<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','thue_trang_phuc_2026');
define('BASE_URL','http://localhost/trangphuc/');
define('ADMIN_URL',BASE_URL.'admin/');
$conn=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
if($conn->connect_error){die('Kết nối thất bại: '.$conn->connect_error);}
$conn->set_charset('utf8mb4');
function format_price($value):string{return number_format((float)$value,0,'.',',');}
function normalize_price($value):float{return (float)preg_replace('/[^0-9]/','',(string)$value);}
function rental_status_label(string $status):string{
    $labels=['reserved'=>'Đã đặt','picked_up'=>'Đã nhận','returned'=>'Đã trả','cancelled'=>'Đã hủy','overdue'=>'Quá hạn'];
    return $labels[$status]??$status;
}
function payment_status_label(string $status):string{
    $labels=['unpaid'=>'Chưa thanh toán','partial'=>'Thanh toán một phần','paid'=>'Đã thanh toán'];
    return $labels[$status]??$status;
}
function costume_status_label(string $status):string{
    $labels=['available'=>'Sẵn sàng','maintenance'=>'Đang bảo dưỡng','retired'=>'Ngừng cho thuê'];
    return $labels[$status]??$status;
}
function generate_rental_code(mysqli $conn):string{
    $prefix='TP'.date('ymd');
    $result=$conn->query("SELECT COUNT(*) total FROM rentals WHERE DATE(created_at)=CURDATE()");
    $next=(int)($result->fetch_assoc()['total']??0)+1;
    return $prefix.str_pad((string)$next,3,'0',STR_PAD_LEFT);
}
function sync_rental_payment(mysqli $conn,int $rentalId):void{
    $stmt=$conn->prepare("SELECT COALESCE(SUM(CASE WHEN payment_type='refund' THEN -amount ELSE amount END),0) paid FROM payments WHERE rental_id=?");
    $stmt->bind_param('i',$rentalId);
    $stmt->execute();
    $paid=(float)$stmt->get_result()->fetch_assoc()['paid'];
    $stmt=$conn->prepare("SELECT final_amount FROM rentals WHERE id=?");
    $stmt->bind_param('i',$rentalId);
    $stmt->execute();
    $rental=$stmt->get_result()->fetch_assoc();
    if(!$rental)return;
    $final=(float)$rental['final_amount'];
    $status=$paid<=0?'unpaid':($paid+0.01>=$final?'paid':'partial');
    $stmt=$conn->prepare("UPDATE rentals SET paid_amount=?,payment_status=? WHERE id=?");
    $stmt->bind_param('dsi',$paid,$status,$rentalId);
    $stmt->execute();
}
function available_quantity(mysqli $conn,int $costumeId,string $receiveDate,string $returnDate,int $excludeRentalId=0):int{
    $stmt=$conn->prepare("SELECT quantity,status FROM costumes WHERE id=?");
    $stmt->bind_param('i',$costumeId);
    $stmt->execute();
    $costume=$stmt->get_result()->fetch_assoc();
    if(!$costume||$costume['status']!=='available')return 0;
    $sql="SELECT COALESCE(SUM(ri.quantity),0) used_qty FROM rental_items ri JOIN rentals r ON r.id=ri.rental_id WHERE ri.costume_id=? AND r.status IN ('reserved','picked_up','overdue') AND NOT(r.return_date<? OR r.receive_date>?)";
    if($excludeRentalId>0)$sql.=" AND r.id<>?";
    $stmt=$conn->prepare($sql);
    if($excludeRentalId>0)$stmt->bind_param('issi',$costumeId,$receiveDate,$returnDate,$excludeRentalId);
    else$stmt->bind_param('iss',$costumeId,$receiveDate,$returnDate);
    $stmt->execute();
    $used=(int)$stmt->get_result()->fetch_assoc()['used_qty'];
    return max(0,(int)$costume['quantity']-$used);
}
?>