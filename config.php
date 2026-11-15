<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','thuetrangphuc');
define('BASE_URL','http://localhost/qlda_chothuetrangphuc_lumidress/');
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
function sync_rental_payment(mysqli $conn, int $rentalId): void {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN payment_type = 'refund' THEN -amount
                ELSE amount
            END
        ), 0) AS paid
        FROM payments
        WHERE rental_id = ?
          AND status IN ('completed', 'paid', 'refunded')
    ");

    $stmt->bind_param('i', $rentalId);
    $stmt->execute();

    $paid = (float)$stmt->get_result()->fetch_assoc()['paid'];

    $stmt = $conn->prepare("
        SELECT final_amount
        FROM rentals
        WHERE id = ?
    ");

    $stmt->bind_param('i', $rentalId);
    $stmt->execute();

    $rental = $stmt->get_result()->fetch_assoc();

    if (!$rental) return;

    $final = (float)$rental['final_amount'];

    $status = $paid <= 0
        ? 'unpaid'
        : ($paid + 0.01 >= $final ? 'paid' : 'partial');

    $stmt = $conn->prepare("
        UPDATE rentals
        SET paid_amount = ?,
            payment_status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param('dsi', $paid, $status, $rentalId);
    $stmt->execute();
}
function available_quantity(mysqli $conn,int $costumeId,string $receiveDate,string $returnDate,int $excludeRentalId=0):int{
    $stmt=$conn->prepare("SELECT quantity,status FROM costumes WHERE id=?");
    $stmt->bind_param('i',$costumeId);
    $stmt->execute();
    $costume=$stmt->get_result()->fetch_assoc();
    if(!$costume||$costume['status']!=='available')return 0;
    $sql="SELECT COALESCE(SUM(ri.quantity),0) used_qty FROM rental_items ri JOIN rentals r ON r.id=ri.rental_id WHERE ri.costume_id=? AND r.status IN ('reserved','picked_up','overdue','deposited','confirmed','pending') AND NOT(r.return_date<? OR r.receive_date>?)";
    if($excludeRentalId>0)$sql.=" AND r.id<>?";
    $stmt=$conn->prepare($sql);
    if($excludeRentalId>0)$stmt->bind_param('issi',$costumeId,$receiveDate,$returnDate,$excludeRentalId);
    else$stmt->bind_param('iss',$costumeId,$receiveDate,$returnDate);
    $stmt->execute();
    $used=(int)$stmt->get_result()->fetch_assoc()['used_qty'];
    return max(0,(int)$costume['quantity']-$used);
}

/* ===== Bổ sung: tính toán cọc/phí/quyết toán (theo Mo_ta_dat_thue.docx) ===== */
function rental_status_label_v2(string $status):string{
    $labels=['pending'=>'Chờ xác nhận','deposited'=>'Đã cọc','confirmed'=>'Đã xác nhận','reserved'=>'Đã đặt','picked_up'=>'Đang thuê','returned'=>'Đã trả','completed'=>'Hoàn tất','cancelled'=>'Đã hủy','overdue'=>'Quá hạn'];
    return $labels[$status]??rental_status_label($status);
}
function payment_type_label(string $type):string{
    $labels=['deposit'=>'Tiền cọc','rental'=>'Tiền thuê','refund'=>'Hoàn cọc','extra'=>'Thu thêm'];
    return $labels[$type]??$type;
}
function suggest_deposit_by_category(string $categoryName, float $listedValue, float $defaultDeposit=0):float{
    $name=mb_strtolower(trim($categoryName));
    $isWedding=str_contains($name,'váy cưới')||str_contains($name,'cưới');
    $isVest=str_contains($name,'vest');
    $isAoDai=str_contains($name,'áo dài');
    $isDaHoi=str_contains($name,'dạ hội');
    $isPerformance=str_contains($name,'biểu diễn');
    if($listedValue>3000000 || $isWedding){
        $rate=0.7; $min=1500000; $max=3000000;
    }elseif($listedValue>=1000000 || $isVest || $isDaHoi){
        $rate=0.5; $min=500000; $max=1000000;
    }else{ // phổ thông & biểu diễn, áo dài phổ thông
        return $defaultDeposit>0?$defaultDeposit:200000;
    }
    $calc=$listedValue*$rate;
    if($calc<$min) $calc=$min;
    if($calc>$max) $calc=$max;
    return round($calc, -3);
}
function rental_days_count(string $receiveDate,string $returnDate):int{
    $days=(int)((strtotime($returnDate)-strtotime($receiveDate))/86400)+1;
    return max(1,$days);
}
function calculate_late_fee(string $scheduledReturnDate,string $actualReturnAt,float $dailyRentalPrice):float{
    if(!$actualReturnAt) return 0.0;
    $due=strtotime($scheduledReturnDate.' 23:59:59');
    $actual=strtotime($actualReturnAt);
    if($actual===false || $actual<=$due) return 0.0;
    $hoursLate=($actual-$due)/3600.0;
    if($hoursLate<2) return 0.0; // < 2 giờ: ân hạn
    if($hoursLate<=6) return round($dailyRentalPrice*0.3, -2); // 2-6 giờ: 30%
    if($hoursLate<=24) return round($dailyRentalPrice*1.0, -2); // 6-24 giờ: 100%
    $extraDays=(int)ceil($hoursLate/24.0)-1; // số ngày trễ sau 24h đầu
    if($extraDays<1) $extraDays=1;
    return round($dailyRentalPrice*1.5*$extraDays, -2); // >24h: 150%/ngày trễ
}
function damage_fee_by_level(int $level,float $depositAmount,float $listedValue):float{
    switch($level){
        case 1: return 100000.0; // Mức 1 (50k-100k): lấy mức chuẩn 100k, có thể điều chỉnh thủ công nếu cần
        case 2: return 300000.0; // Mức 2 (150k-300k): 300k
        case 3: return round($depositAmount>0?$depositAmount:min($listedValue,$listedValue), -2); // Mức 3: 70-100% tiền cọc (mặc định 100% cọc)
        case 4: return round($listedValue, -2); // Mức 4: 100% giá trị niêm yết
        default: return 0.0;
    }
}
function calculate_settlement(float $rentalAmount,float $lateFee,float $damageFee,float $depositPaid):array{
    $total=$rentalAmount+$lateFee+$damageFee;
    $balance=$total-$depositPaid;
    return [
        'total'=>$total,
        'extra_due'=>$balance>0?round($balance,2):0.0,
        'refund'=>$balance<0?round(abs($balance),2):0.0,
    ];
}
function sync_rental_deposit_paid(mysqli $conn, int $rentalId): void {
    $stmt = $conn->prepare("
    SELECT COALESCE(SUM(
        CASE
            WHEN payment_type = 'deposit' THEN amount
            WHEN payment_type = 'refund' THEN -amount
            ELSE 0
        END
    ), 0) AS paid
    FROM payments
    WHERE rental_id = ?
    AND payment_type IN ('deposit', 'refund')
    AND status IN ('completed', 'paid', 'refunded')
    ");

    $stmt->bind_param('i', $rentalId);
    $stmt->execute();

    $paid = (float)$stmt->get_result()->fetch_assoc()['paid'];

    $stmt = $conn->prepare("
        UPDATE rentals
        SET deposit_paid = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param('di', $paid, $rentalId);
    $stmt->execute();
}
?>