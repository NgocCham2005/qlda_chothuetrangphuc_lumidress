<?php
include 'header.php';
$conn->query("
    UPDATE rentals
    SET status = 'overdue',
        updated_at = NOW()
    WHERE status = 'picked_up'
      AND return_date < CURDATE()
");
@$conn->query("UPDATE rentals SET status='cancelled' WHERE status='pending' AND created_at < (NOW() - INTERVAL 15 MINUTE)");
$error='';
try{
if(isset($_POST['save_rental'])){
    $id=(int)($_POST['id']??0);
    $customerId=(int)($_POST['customer_id']??0);
    $receiveDate=$_POST['receive_date']??'';
    $returnDate=$_POST['return_date']??'';
    $discount=normalize_price($_POST['discount']??0);
    $depositPaidNow=normalize_price($_POST['deposit_paid_now']??0);
    $depositMethod=$_POST['deposit_method']??'cash';
    $depositTxn=trim($_POST['deposit_txn']??'');
    $note=trim($_POST['note']??'');
    $costumeIds=$_POST['costume_id']??[];
    $quantities=$_POST['item_quantity']??[];
    $sizes=$_POST['item_size']??[];
    $conditions=$_POST['condition_out']??[];
    if($customerId<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$receiveDate)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$returnDate))throw new Exception('Vui lòng chọn khách hàng và ngày thuê');
    if(strtotime($returnDate)<strtotime($receiveDate))throw new Exception('Ngày trả phải bằng hoặc sau ngày nhận');
    if(!$costumeIds)throw new Exception('Vui lòng chọn ít nhất một trang phục');
    $days=rental_days_count($receiveDate,$returnDate);
    $items=[];$rentalTotal=0;$depositTotal=0;$seen=[];
    foreach($costumeIds as $index=>$costumeIdRaw){
        $costumeId=(int)$costumeIdRaw;$qty=max(1,(int)($quantities[$index]??1));
        if($costumeId<=0||isset($seen[$costumeId]))continue;
        $seen[$costumeId]=true;
        $stmt=$conn->prepare("SELECT id,name,size,rental_price,deposit,status FROM costumes WHERE id=?");$stmt->bind_param('i',$costumeId);$stmt->execute();$costume=$stmt->get_result()->fetch_assoc();
        if(!$costume)throw new Exception('Trang phục không tồn tại');
        $available=available_quantity($conn,$costumeId,$receiveDate,$returnDate,$id);
        if($available<$qty)throw new Exception($costume['name'].' chỉ còn '.$available.' sản phẩm trống trong thời gian đã chọn');
        $unitPrice=(float)$costume['rental_price'];$subtotal=$unitPrice*$qty*$days;$itemDeposit=(float)$costume['deposit']*$qty;
        $items[]=['costume_id'=>$costumeId,'quantity'=>$qty,'size'=>trim($sizes[$index]??$costume['size']),'unit_price'=>$unitPrice,'days'=>$days,'subtotal'=>$subtotal,'deposit'=>$itemDeposit,'condition_out'=>trim($conditions[$index]??'Tốt')];
        $rentalTotal+=$subtotal;$depositTotal+=$itemDeposit;
    }
    if(!$items)throw new Exception('Danh sách trang phục không hợp lệ');
    if($discount>$rentalTotal)throw new Exception('Giảm giá không được lớn hơn tiền thuê');
    if($depositPaidNow>$depositTotal)throw new Exception('Tiền cọc thu trước không được lớn hơn tổng tiền cọc');
    $final=$rentalTotal-$discount+$depositTotal;
    $conn->begin_transaction();
if ($id > 0) {
    $stmt = $conn->prepare("
        SELECT status
        FROM rentals
        WHERE id = ?
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception('Lỗi truy vấn: ' . $conn->error);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();

    $old = $stmt->get_result()->fetch_assoc();

    $old = $stmt->get_result()->fetch_assoc();

        if (!$old || !in_array($old['status'], ['reserved', 'pending'], true)) {
            throw new Exception('Chỉ được sửa phiếu chưa xác nhận cọc và chưa bàn giao');
        }

        $check = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM payments
            WHERE rental_id = ?
              AND status IN ('completed', 'paid')
        ");

        $check->bind_param('i', $id);
        $check->execute();

        $paymentCount = (int)$check->get_result()->fetch_assoc()['total'];

        if ($paymentCount > 0) {
            throw new Exception('Phiếu đã có giao dịch thanh toán, không thể sửa');
        }
        $stmt=$conn->prepare("UPDATE rentals SET customer_id=?,receive_date=?,return_date=?,rental_amount=?,deposit_amount=?,discount=?,final_amount=?,note=? WHERE id=?");
        if(!$stmt) throw new Exception('Không thể cập nhật phiếu: '.$conn->error);
        $stmt->bind_param('issddddsi',$customerId,$receiveDate,$returnDate,$rentalTotal,$depositTotal,$discount,$final,$note,$id);$stmt->execute();
        $stmt=$conn->prepare("DELETE FROM rental_items WHERE rental_id=?");
        if(!$stmt) throw new Exception('Không thể cập nhật chi tiết: '.$conn->error);
        $stmt->bind_param('i',$id);$stmt->execute();
        $rentalId=$id;
    }else{
        // Nếu chọn chuyển khoản QR tại quầy và chưa thu tiền: tạo phiếu 'pending' (chờ xác nhận cọc)
        if ($depositPaidNow <= 0 && $depositMethod === 'bank') {
            $initStatus = 'pending';
        } elseif ($depositPaidNow >= $depositTotal - 0.01 && $depositTotal > 0) {
            $initStatus = 'deposited';
        } else {
            $initStatus = 'reserved';
        }
        $pstatus='unpaid';
        $stmt=$conn->prepare("INSERT INTO rentals(customer_id,receive_date,return_date,rental_amount,deposit_amount,discount,final_amount,note,status,payment_status) VALUES(?,?,?,?,?,?,?,?,?,?)");
        if(!$stmt) throw new Exception('Không thể tạo phiếu thuê: '.$conn->error);
        $stmt->bind_param('issddddsss',$customerId,$receiveDate,$returnDate,$rentalTotal,$depositTotal,$discount,$final,$note,$initStatus,$pstatus);        $stmt->execute();
        $rentalId=$conn->insert_id;
    }
    $stmt=$conn->prepare("INSERT INTO rental_items(rental_id,costume_id,quantity,selected_size,unit_price,rental_days,subtotal,deposit_amount,condition_out) VALUES(?,?,?,?,?,?,?,?,?)");
    if(!$stmt) throw new Exception('Không thể lưu chi tiết phiếu: '.$conn->error);
    foreach ($items as $item) {
        $stmt->bind_param(
            'iiisiddds',
            $rentalId,
            $item['costume_id'],
            $item['quantity'],
            $item['size'],
            $item['unit_price'],
            $item['days'],
            $item['subtotal'],
            $item['deposit'],
            $item['condition_out']
        );

        $stmt->execute();
    }
        if($depositPaidNow>0 && $id==0){
        if(!in_array($depositMethod,['cash','bank','card'],true))$depositMethod='cash';
        $txn=$depositTxn!==''?$depositTxn:('DEP'.date('ymdHis').$rentalId);
        $ptype='deposit';$pnote='Thu cọc khi tạo phiếu (quầy)';
        $pstmt=$conn->prepare("INSERT INTO payments(rental_id,amount,payment_type,payment_method,note,transaction_code,status,paid_at) VALUES(?,?,?,?,?,?, 'completed',NOW())");
        if($pstmt){
            $pstmt->bind_param('idssss',$rentalId,$depositPaidNow,$ptype,$depositMethod,$pnote,$txn);
        }else{
            $pnote2=$pnote.($depositTxn!==''?' | Mã TT: '.$depositTxn:'');
            $pstmt=$conn->prepare("INSERT INTO payments(rental_id,amount,payment_type,payment_method,note) VALUES(?,?,?,?,?)");            if(!$pstmt) throw new Exception('Không thể ghi nhận cọc: '.$conn->error);
            $pstmt->bind_param('idsss',$rentalId,$depositPaidNow,$ptype,$depositMethod,$pnote2);
        }
        $pstmt->execute();
        @$conn->query("UPDATE rentals SET deposit_paid=".(float)$depositPaidNow." WHERE id=".(int)$rentalId);
    }
    sync_rental_payment($conn,$rentalId);
    if(function_exists('sync_rental_deposit_paid')) sync_rental_deposit_paid($conn,$rentalId);
    $conn->commit();header('Location: rentals.php');exit;
}
if(isset($_POST['status_action'])){
    $id=(int)($_POST['rental_id']??0);$action=$_POST['status_action'];
    $conn->begin_transaction();
    $stmt=$conn->prepare("SELECT r.id,r.status,r.deposit_amount,r.customer_id,c.phone FROM rentals r JOIN customers c ON c.id=r.customer_id WHERE r.id=? FOR UPDATE");
    if(!$stmt) throw new Exception('Lỗi truy vấn: '.$conn->error);
    $stmt->bind_param('i',$id);$stmt->execute();$rental=$stmt->get_result()->fetch_assoc();
    if(!$rental)throw new Exception('Không tìm thấy phiếu thuê');
    $allowed=[
        'pickup'=>[['deposited'],'picked_up'],
        'return'=>[['picked_up','overdue'],'returned'],
        'cancel'=>[['reserved','pending','deposited'],'cancelled'],
        'deposit'=>[['reserved','pending'],'deposited'],
    ];
    if (!isset($allowed[$action])) {
    throw new Exception('Action không hợp lệ: ' . $action);
}

if (!in_array(trim($rental['status']), $allowed[$action][0], true)) {
    throw new Exception(
        'Không thể chuyển trạng thái. ID: ' . $id .
        ' | Action nhận được: ' . $action .
        ' | Trạng thái hiện tại: ' . $rental['status']
    );
}
    //if(!isset($allowed[$action])||!in_array($rental['status'],$allowed[$action][0],true))throw new Exception('Không thể chuyển trạng thái phiếu thuê');
    $newStatus=$allowed[$action][1];
    if($action==='pickup'){
    $stmt = $conn->prepare("
        UPDATE rentals
        SET status = 'picked_up',
            actual_pickup_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
          AND status = 'deposited'
    ");        if(!$stmt) throw new Exception('Không thể cập nhật trạng thái: '.$conn->error);
        $stmt->bind_param('i',$id);$stmt->execute();
        // Ghi nhật ký bàn giao (tương thích activity_logs: admin_id,action,description,ip_address,created_at)
        $adminId=(int)($_SESSION['admin']['id'] ?? 0);
        $ip=$_SERVER['REMOTE_ADDR'] ?? null;
        $desc='BAN_GIAO_DO phieu_thue_id='.$id.' ma=PT'.str_pad((string)$id,5,'0',STR_PAD_LEFT).' thoi_gian='.date('Y-m-d H:i:s');
        $log=$conn->prepare("INSERT INTO activity_logs(admin_id,action,description,ip_address,created_at) VALUES(?,?,?,?,NOW())");
        if($log){ $act='BAN_GIAO_DO'; $log->bind_param('isss',$adminId,$act,$desc,$ip); @$log->execute(); }
    
        }elseif($action === 'return') {
            $stmt = $conn->prepare("
                UPDATE rentals
                SET status = 'returned',
                    updated_at = NOW()
                WHERE id = ?
                  AND status IN ('picked_up', 'overdue')
            ");

            if (!$stmt) {
                throw new Exception('Không thể cập nhật trạng thái: ' . $conn->error);
            }

            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new Exception('Không thể xác nhận trả trang phục');
            }
    }elseif($action==='deposit'){
        // Xác nhận đã nhận cọc (tiền mặt/chuyển khoản) từ pending/reserved
        $stmt=$conn->prepare("UPDATE rentals SET status='deposited', payment_status=CASE WHEN payment_status='paid' THEN 'paid' ELSE 'partial' END WHERE id=?");
        if(!$stmt) throw new Exception('Không thể cập nhật trạng thái: '.$conn->error);
        $stmt->bind_param('i',$id);$stmt->execute();
        // ghi nhận payment cọc nếu chưa có
        $depAmt=(float)($rental['deposit_amount']??0);
        $chk=$conn->prepare("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE rental_id=? AND payment_type='deposit' AND status IN ('completed','paid')");
        if($chk){ $chk->bind_param('i',$id); $chk->execute(); $paidDep=(float)$chk->get_result()->fetch_assoc()['s']; } else { $paidDep=0; }
        $need=max(0,$depAmt-$paidDep);
        if($need>0.01){
            $rentalCode='PT'.str_pad((string)$id,5,'0',STR_PAD_LEFT);
            $phone=preg_replace('/\D+/','',(string)($rental['phone']??''));
            $pm=in_array($_POST['deposit_method']??'bank',['cash','bank'],true)?$_POST['deposit_method']:'bank';
            $txn=($pm==='bank') ? ('COC_'.$rentalCode.'_'.($phone!==''?$phone:$rental['customer_id'])) : ('COC_TM_'.$rentalCode.'_'.date('ymdHis'));
            $ptype='deposit';
            $pnote=($pm==='cash')?'DAT_COC Tiền mặt (POS) - Xác nhận đã thu cọc':'DAT_COC CK/QR (POS) - Xác nhận đã nhận cọc chuyển khoản';
$p = $conn->prepare("
    INSERT INTO payments(
        rental_id,
        amount,
        payment_type,
        payment_method,
        note,
        transaction_code,
        status,
        paid_at
    )
    VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
");

if (!$p) {
    throw new Exception('Không thể ghi nhận cọc: ' . $conn->error);
}
        $p->bind_param(
            'idssss',
            $id,
            $need,
            $ptype,
            $pm,
            $pnote,
            $txn
        );

        $p->execute();
        }
        if(function_exists('sync_rental_payment')) sync_rental_payment($conn,$id);
        if(function_exists('sync_rental_deposit_paid')) @sync_rental_deposit_paid($conn,$id);
    }else{
        $stmt=$conn->prepare("UPDATE rentals SET status=? WHERE id=?");
        if(!$stmt) throw new Exception('Không thể cập nhật trạng thái: '.$conn->error);
        $stmt->bind_param('si',$newStatus,$id);$stmt->execute();
    }
    $conn->commit();
    header('Location: rentals.php');exit;
}
if(isset($_POST['settle_rental'])){
    $id=(int)($_POST['rental_id']??0);
    $actualReturnAt=trim($_POST['actual_return_at']??'');
    if($actualReturnAt==='') $actualReturnAt=date('Y-m-d H:i:s');
    $itemDamages=$_POST['item_damage'] ?? [];
    $itemLevels=$_POST['item_level'] ?? [];
    $settleMethod=in_array($_POST['settle_method']??'cash',['cash','bank'],true)?($_POST['settle_method']):'cash';
    $refundBank=trim($_POST['refund_bank_account']??'');
    $conn->begin_transaction();
    $stmt=$conn->prepare("SELECT r.*, c.fullname, c.phone, COALESCE(SUM(ri.unit_price*ri.quantity),0) daily_rate FROM rentals r JOIN customers c ON c.id=r.customer_id LEFT JOIN rental_items ri ON ri.rental_id=r.id WHERE r.id=? GROUP BY r.id FOR UPDATE");
    if(!$stmt) throw new Exception('Không thể quyết toán (cần chạy migration DB): '.$conn->error);
    $stmt->bind_param('i',$id);$stmt->execute();$rental=$stmt->get_result()->fetch_assoc();
    if(!$rental)throw new Exception('Không tìm thấy phiếu thuê');
    if(in_array($rental['status'],['cancelled'],true))throw new Exception('Phiếu thuê đã hủy, không thể quyết toán');
    // idempotent: nếu đã hoàn tất thì bỏ qua
    if($rental['status']==='completed' || !empty($rental['settled_at'])){
        $conn->commit(); header('Location: rentals.php'); exit;
    }
    $dailyRate=(float)($rental['daily_rate']??0);
    $lateFee=function_exists('calculate_late_fee')? (float)calculate_late_fee($rental['return_date'],$actualReturnAt,$dailyRate) : 0.0;
    // cho phép nhân viên điều chỉnh phí trả muộn
    if(isset($_POST['late_fee_override']) && $_POST['late_fee_override']!==''){
        $lateFee=max(0.0,(float)normalize_price($_POST['late_fee_override']));
    }
    // tiền cọc đã thu (server-side)
    $depositPaid=(float)($rental['deposit_paid']??0);
    $p=$conn->prepare("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE rental_id=? AND payment_type='deposit' AND status IN ('completed','paid','refunded')");
    if($p){ $p->bind_param('i',$id); $p->execute(); $depositPaid=max($depositPaid,(float)$p->get_result()->fetch_assoc()['s']); }
    // tính phí hư hỏng theo từng dòng (nếu có), fallback mức chung
    $damageFee=0.0; $maxLevel=0;
    $it=$conn->prepare("SELECT ri.id, ri.quantity, ri.unit_price, ri.deposit_amount, ri.condition_in, ri.note, co.name, co.rental_price, co.id AS costume_id FROM rental_items ri JOIN costumes co ON co.id=ri.costume_id WHERE ri.rental_id=? FOR UPDATE");
    $itemRows=[];
    if($it){ $it->bind_param('i',$id); $it->execute(); $itemsRes=$it->get_result();
        while($row=$itemsRes->fetch_assoc()){
            $itemRows[]=$row;
            $iid=(int)$row['id']; $level=(int)($itemLevels[$iid] ?? 0);
            $perItemDeposit=(float)($row['deposit_amount']??0); $qty=max(1,(int)$row['quantity']);
            $listedItem=max((float)$row['rental_price']*10, (float)$row['unit_price']*10);
            $fee=0.0;
            if($level===1){ $fee = min(100000.0, max(50000.0, (float)($itemDamages[$iid] ?? 100000.0))); }
            elseif($level===2){ $fee = min(300000.0, max(150000.0, (float)($itemDamages[$iid] ?? 300000.0))); }
            elseif($level===3){ $ratio=min(1.0,max(0.7,(float)($itemDamages[$iid] ?? 1.0))); $base=$perItemDeposit>0?$perItemDeposit:max(0,$depositPaid); $fee=round($base*$ratio, -2); }
            elseif($level===4){ $fee = round($listedItem*$qty, -2); }
            $damageFee += $fee; if($level>$maxLevel) $maxLevel=$level;
            // lưu vết tình trạng (không đổi schema)
            $condIn = $level>0 ? ('Hư hỏng Mức '.$level) : ($row['condition_in'] ?: 'Tốt');
            $noteAdd = $level>0 ? ('Phí hư hỏng: '.number_format($fee).'đ') : '';
            $newNote = trim(($row['note']??'').($noteAdd?(' | '.$noteAdd):''));
            $u=$conn->prepare("UPDATE rental_items SET condition_in=?, note=? WHERE id=?");
            if($u){ $u->bind_param('ssi',$condIn,$newNote,$iid); $u->execute(); }
        }
    } else {
        // fallback theo mức chung (tương thích cũ)
        $damageLevel=(int)($_POST['damage_level']??0);
        $listed=(float)($rental['rental_amount']??0)*5 + $depositPaid;
        $damageFee=function_exists('damage_fee_by_level')?damage_fee_by_level($damageLevel,$depositPaid,$listed):0.0;
        $maxLevel=$damageLevel;
    }
    $rentalAmount = (float)($rental['rental_amount'] ?? 0);
$discount = (float)($rental['discount'] ?? 0);

// Tiền thuê thực tế sau giảm giá
$netRental = max(0, $rentalAmount - $discount);

// Tổng chi phí thực tế
$totalDue = $netRental + $lateFee + $damageFee;

// Lấy tiền thuê đã thanh toán trước
$paidStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS paid
    FROM payments
    WHERE rental_id = ?
      AND payment_type IN ('rental', 'extra')
      AND status IN ('completed', 'paid')
");

    $paidStmt->bind_param('i', $id);
    $paidStmt->execute();

    $rentalPaid = (float)$paidStmt->get_result()->fetch_assoc()['paid'];

    // Tổng tiền khách đã thanh toán gồm tiền thuê và tiền cọc
    $totalPaidBefore = $rentalPaid + $depositPaid;

    // Chênh lệch quyết toán
    $soTienQuyetToan = $totalDue - $totalPaidBefore;

    $extra = $soTienQuyetToan > 0
        ? round($soTienQuyetToan, 2)
        : 0.0;

    $refund = $soTienQuyetToan < 0
        ? round(abs($soTienQuyetToan), 2)
        : 0.0;
    $dmgLevelStr=$maxLevel>0?(string)$maxLevel:null;
    $stmt=$conn->prepare("UPDATE rentals SET actual_return_at=?, late_fee=?, damage_fee=?, damage_level=?, extra_due_amount=?, refund_amount=?, settled_at=NOW(), status='completed', final_amount=? WHERE id=?");
    if(!$stmt) throw new Exception('Không thể cập nhật quyết toán (cần chạy migration DB): '.$conn->error);
    $stmt->bind_param('sddsdddi',$actualReturnAt,$lateFee,$damageFee,$dmgLevelStr,$extra,$refund,$totalDue,$id);    $stmt->execute();
    $rentalCode='PT'.str_pad((string)$id,5,'0',STR_PAD_LEFT);
    if($extra>0){
        $pm=$settleMethod;
        $txn = ($pm==='bank') ? ('TATTOAN_'.$rentalCode) : ('TATTOAN_TM_'.$rentalCode.'_'.date('ymdHis'));
        $ptype='extra';$pnote='TAT_TOAN: Khách trả thêm khi tất toán (trễ/hư hỏng)';$st='completed';
$p = $conn->prepare("
    INSERT INTO payments(
        rental_id,
        amount,
        payment_type,
        payment_method,
        note,
        transaction_code,
        status,
        paid_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
");

if (!$p) {
    throw new Exception('Không thể ghi nhận thu thêm: ' . $conn->error);
}
      $p->bind_param(
          'idsssss',
          $id,
          $extra,
          $ptype,
          $pm,
          $pnote,
          $txn,
          $st
      );

      $p->execute();
    }
    if($refund>0){
        $pm=$settleMethod;
        $stkNote=$refundBank!==''?(' | STK khách: '.$refundBank):'';
        $txn = ($pm==='bank') ? ('HOANCOC_'.$rentalCode) : ('HOANCOC_TM_'.$rentalCode.'_'.date('ymdHis'));
        $ptype='refund';$pnote='HOAN_COC: Hoàn cọc thừa sau tất toán'.$stkNote;$st='completed';
        $p = $conn->prepare("
            INSERT INTO payments(
                rental_id,
                amount,
                payment_type,
                payment_method,
                note,
                transaction_code,
                status,
                refunded_at,
                paid_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        if (!$p) {
            throw new Exception('Không thể ghi nhận hoàn cọc: ' . $conn->error);
        }

        $p->bind_param(
            'idsssss',
            $id,
            $refund,
            $ptype,
            $pm,
            $pnote,
            $txn,
            $st
        );

        $p->execute();
    }
    // Lưu ý: không cộng costumes.quantity vì hệ thống hiện không trừ tồn khi thuê (tránh sai lệch). Lịch được giải phóng khi rentals.status='completed'.
    if(function_exists('sync_rental_payment')) sync_rental_payment($conn,$id);
    if(function_exists('sync_rental_deposit_paid')) sync_rental_deposit_paid($conn,$id);
    $conn->commit();header('Location: rentals.php');exit;
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("
            SELECT r.status, COUNT(p.id) AS payment_count
            FROM rentals r
            LEFT JOIN payments p ON p.rental_id = r.id
            WHERE r.id = ?
            GROUP BY r.id
        ");

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (
            !$row ||
            $row['status'] !== 'cancelled' ||
            (int)$row['payment_count'] > 0
        ) {
            throw new Exception(
                'Chỉ xóa được phiếu đã hủy và chưa có thanh toán'
            );
        }

        // Xóa chi tiết phiếu thuê trước
        $stmt = $conn->prepare("
            DELETE FROM rental_items
            WHERE rental_id = ?
        ");

        $stmt->bind_param('i', $id);
        $stmt->execute();

        // Sau đó mới xóa phiếu thuê
        $stmt = $conn->prepare("
            DELETE FROM rentals
            WHERE id = ?
        ");

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $conn->commit();

        header('Location: rentals.php');
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

} catch(Throwable $e) {
    if($conn->errno) {
        $conn->rollback();
    }
    $error=$e->getMessage();
}
$page=max(1,(int)($_GET['page']??1));
$limit=10;$offset=($page-1)*$limit;$status=$_GET['status']??'';$keyword=trim($_GET['q']??'');$todayReceive=isset($_GET['today']) && $_GET['today']=='1';
$conditions=[];$types='';$values=[];
if($todayReceive){ $conditions[]="r.status='deposited'"; $conditions[]="r.receive_date=CURDATE()"; $status='deposited'; }
elseif(in_array($status,[
    'pending',
    'reserved',
    'deposited',
    'picked_up',
    'returned',
    'completed',
    'overdue',
    'cancelled'
], true)) {

    $conditions[] = 'r.status = ?';
    $types .= 's';
    $values[] = $status;
}
if($keyword!==''){$conditions[]='(CAST(r.id AS CHAR) LIKE ? OR c.fullname LIKE ? OR c.phone LIKE ?)';$like='%'.$keyword.'%';$types.='sss';array_push($values,$like,$like,$like);}
$where=$conditions?'WHERE '.implode(' AND ',$conditions):'';
$stmt=$conn->prepare("SELECT r.*,c.fullname,c.phone FROM rentals r JOIN customers c ON c.id=r.customer_id $where ORDER BY r.id DESC LIMIT ?,?");$types2=$types.'ii';$values2=array_merge($values,[$offset,$limit]);$stmt->bind_param($types2,...$values2);$stmt->execute();$data=$stmt->get_result();
$stmt=$conn->prepare("SELECT COUNT(*) total FROM rentals r JOIN customers c ON c.id=r.customer_id $where");if($types)$stmt->bind_param($types,...$values);$stmt->execute();$total=(int)$stmt->get_result()->fetch_assoc()['total'];$pages=max(1,(int)ceil($total/$limit));
$customers=$conn->query("SELECT id,fullname,phone FROM customers ORDER BY fullname");
$costumes=$conn->query("SELECT id,name,size,rental_price,deposit,quantity FROM costumes WHERE status='available' ORDER BY name");
$costumeArray=[];
while($co=$costumes->fetch_assoc()){
  $co['code'] = $co['code'] ?? ('SP'.str_pad((string)$co['id'], 4, '0', STR_PAD_LEFT));
  $costumeArray[]=$co;
}
$openAdd=isset($_GET['add']);
?>
<?php if($error):?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif;?>
<div class="page-head"><div><h2>Phiếu thuê trang phục</h2><p>Quản lý lịch nhận, ngày trả, tiền thuê và tiền cọc.</p></div><button class="btn-primary-soft" data-bs-toggle="modal" data-bs-target="#rentalModal" onclick="openRental()"><i class="fas fa-plus"></i>Tạo phiếu thuê</button></div>
<form class="filter-bar"><div class="search-box"><i class="fas fa-search"></i><input name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tìm mã phiếu, tên hoặc số điện thoại"></div><select name="status"><option value="">Tất cả trạng thái</option>
<?php foreach([
    'pending',
    'reserved',
    'deposited',
    'picked_up',
    'returned',
    'completed',
    'overdue',
    'cancelled'
] as $s):?>

    <option value="<?= $s ?>"
        <?= $status === $s ? 'selected' : '' ?>>
        <?= function_exists('rental_status_label_v2')
            ? rental_status_label_v2($s)
            : rental_status_label($s) ?>
    </option>

<?php endforeach; ?>
</select><button class="filter-submit">Lọc</button><a class="filter-submit" href="rentals.php?today=1" style="background:#e8fff1;color:#1b7f4b;<?= $todayReceive?'font-weight:700;':'' ?>">Đã cọc - nhận hôm nay</a><a href="rentals.php">Đặt lại</a></form>
<section class="panel table-panel"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Mã phiếu</th><th>Khách hàng</th><th>Thời gian thuê</th><th>Tiền thuê</th><th>Tổng cần thu</th><th>Thanh toán</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody>
<?php if($data&&$data->num_rows):while($row=$data->fetch_assoc()):?>
<tr><td><strong>PT<?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></strong><small class="d-block text-muted"><?= date('d/m/Y H:i',strtotime($row['created_at'])) ?></small></td><td><?= htmlspecialchars($row['fullname']) ?><small class="d-block text-muted"><?= htmlspecialchars($row['phone']) ?></small></td><td><?= date('d/m/Y',strtotime($row['receive_date'])) ?><span class="date-arrow"><i class="fas fa-arrow-right"></i></span><?= date('d/m/Y',strtotime($row['return_date'])) ?></td><td><?= format_price($row['rental_amount']) ?>đ</td><td><strong><?= format_price($row['final_amount']) ?>đ</strong></td><td><span class="payment payment-<?= $row['payment_status'] ?>"><?= payment_status_label($row['payment_status']) ?></span><small class="d-block text-muted"><?= format_price($row['paid_amount']) ?>đ</small></td><td><span class="status status-<?= $row['status'] ?>"><?= function_exists('rental_status_label_v2')?rental_status_label_v2($row['status']):rental_status_label($row['status']) ?></span></td><td class="text-end"><button class="icon-btn" onclick="viewRental(<?= $row['id'] ?>)" title="Chi tiết"><i class="fas fa-eye"></i></button><?php if($row['status']==='pending'):?><button class="icon-btn" onclick="openQrDeposit(<?= $row['id'] ?>)" title="Thanh toán Cọc (tiền mặt/chuyển khoản)"><i class="fas fa-coins"></i></button><button class="icon-btn danger" onclick="changeStatus(<?= $row['id'] ?>,'cancel','Hủy phiếu thuê này?')" title="Hủy"><i class="fas fa-ban"></i></button><?php elseif($row['status']==='deposited'):?><button class="icon-btn success" onclick="changeStatus(<?= $row['id'] ?>,'pickup','Xác nhận bàn giao trang phục cho khách?')" title="Bàn giao đồ cho khách"><i class="fas fa-person-walking-arrow-right"></i></button><button class="icon-btn danger" onclick="changeStatus(<?= $row['id'] ?>,'cancel','Hủy phiếu thuê này?')" title="Hủy"><i class="fas fa-ban"></i></button><a class="icon-btn" href="print_rental.php?id=<?= $row['id'] ?>&type=handover" target="_blank" title="In biên bản bàn giao"><i class="fas fa-file-signature"></i></a><?php elseif($row['status']==='reserved'):?><button class="icon-btn" onclick="openQrDeposit(<?= $row['id'] ?>)" title="Thanh toán Cọc (tiền mặt/chuyển khoản)"><i class="fas fa-coins"></i></button><button class="icon-btn" onclick="editRental(<?= $row['id'] ?>)" title="Sửa"><i class="fas fa-pen"></i></button><button class="icon-btn danger" onclick="changeStatus(<?= $row['id'] ?>,'cancel','Hủy phiếu thuê này?')" title="Hủy"><i class="fas fa-ban"></i></button><?php elseif($row['status']==='confirmed'):?><button class="icon-btn" onclick="editRental(<?= $row['id'] ?>)" title="Sửa"><i class="fas fa-pen"></i></button><button class="icon-btn danger" onclick="changeStatus(<?= $row['id'] ?>,'cancel','Hủy phiếu thuê này?')" title="Hủy"><i class="fas fa-ban"></i></button><?php elseif(in_array($row['status'],['picked_up','overdue'],true)):?><button class="icon-btn success" onclick="changeStatus(<?= $row['id'] ?>,'return','Xác nhận khách đã trả trang phục?')" title="Trả đồ"><i class="fas fa-rotate-left"></i></button><a class="icon-btn" href="print_rental.php?id=<?= $row['id'] ?>&type=handover" target="_blank" title="In biên bản bàn giao"><i class="fas fa-file-signature"></i></a><?php elseif($row['status']==='returned'):?><button class="icon-btn success" onclick="openSettle(<?= $row['id'] ?>)" title="Quyết toán"><i class="fas fa-file-invoice-dollar"></i></button>
  <?php elseif($row['status']==='completed'):?>

    <span class="text-success" title="Đã hoàn tất">
        <i class="fas fa-check-circle"></i>
    </span>
  <?php elseif($row['status']==='cancelled'):?><button class="icon-btn danger" onclick="deleteRental(<?= $row['id'] ?>)" title="Xóa"><i class="fas fa-trash"></i></button><?php endif;?><a class="icon-btn" href="print_rental.php?id=<?= $row['id'] ?>" target="_blank" title="In phiếu"><i class="fas fa-print"></i></a></td></tr>
<?php endwhile;else:?><tr><td colspan="8" class="empty">Không tìm thấy phiếu thuê.</td></tr><?php endif;?>
</tbody></table></div><?php if($pages>1):?><div class="pagination-wrap"><?php for($i=1;$i<=$pages;$i++):?><a class="<?= $i===$page?'active':'' ?>" href="?page=<?= $i ?>&q=<?= urlencode($keyword) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a><?php endfor;?></div><?php endif;?></section>
<div class="modal fade" id="rentalModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form method="post" class="modal-content" id="rentalForm"><input type="hidden" name="id" id="rental_id"><div class="modal-header"><h5 id="rental_title">Tạo phiếu thuê</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<div class="rental-form-grid"><section><h6>Thông tin phiếu thuê</h6><div class="row g-3"><div class="col-md-6"><label>Khách hàng</label><select class="form-select" name="customer_id" id="rental_customer" required><option value="">Chọn khách hàng</option><?php while($c=$customers->fetch_assoc()):?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['fullname']) ?> · <?= htmlspecialchars($c['phone']) ?></option><?php endwhile;?></select></div><div class="col-md-3"><label>Ngày nhận</label><input type="date" class="form-control" name="receive_date" id="rental_receive" min="<?= date('Y-m-d') ?>" required></div><div class="col-md-3"><label>Ngày trả</label><input type="date" class="form-control" name="return_date" id="rental_return" min="<?= date('Y-m-d') ?>" required></div><div class="col-md-4"><label>Giảm giá</label><input class="form-control money-input" inputmode="numeric" name="discount" id="rental_discount" value="0"></div><div class="col-md-8"><label>Ghi chú</label><input class="form-control" name="note" id="rental_note"></div><div class="col-12"><hr style="margin:4px 0;"><small class="text-muted">Thanh toán cọc tại quầy (khi tạo phiếu mới)</small></div><div class="col-md-4"><label>Tiền cọc thu trước</label><input class="form-control money-input" inputmode="numeric" name="deposit_paid_now" id="rental_deposit_paid" value="0"></div><div class="col-md-4"><label>Phương thức cọc</label><select class="form-select" name="deposit_method" id="rental_deposit_method"><option value="cash">Tiền mặt</option><option value="bank">Chuyển khoản</option><option value="card">Thẻ/QR</option></select></div><div class="col-md-4"><label>Mã thanh toán cọc</label><input class="form-control" name="deposit_txn" id="rental_deposit_txn" placeholder="Mã CK/QR, để trống sẽ tự sinh"></div></div></section>
<section class="rental-items-section"><div class="rental-items-head"><div><h6>Trang phục thuê</h6><span>Giá thuê được tính theo số ngày</span></div><button type="button" onclick="addRentalItem()"><i class="fas fa-plus"></i>Thêm dòng</button></div><div id="rentalItems"></div><div class="rental-summary"><span>Tạm tính được cập nhật khi chọn trang phục và ngày thuê.</span><strong id="rentalEstimate">0đ</strong></div></section></div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button><button class="btn-primary-soft" name="save_rental">Lưu phiếu thuê</button></div></form></div></div>
<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5>Chi tiết phiếu thuê</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="detailContent"><div class="loading"><i class="fas fa-spinner fa-spin"></i>Đang tải...</div></div></div></div></div>
<div class="modal fade" id="qrDepositModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5>Thanh toán Cọc</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" style="text-align:center;">
  <div id="qr_deposit_meta" style="margin-bottom:10px;color:#555;">—</div>
  <div style="max-width:320px;margin:0 auto 12px;text-align:left;">
    <label style="font-size:13px;color:#444;margin-bottom:6px;display:block;">Phương thức thanh toán cọc</label>
    <select class="form-select" id="qr_deposit_method">
      <option value="bank">Chuyển khoản (hiển thị mã QR)</option>
      <option value="cash">Tiền mặt</option>
    </select>
  </div>
  <div id="qr_deposit_bank_area">
    <img id="qr_deposit_img" src="" alt="QR thanh toán cọc" style="width:320px;max-width:100%;height:auto;border:1px solid #eee;border-radius:12px;padding:8px;background:#fff;">
    <div style="margin-top:10px;font-size:14px;">Nội dung CK: <strong id="qr_deposit_content">—</strong></div>
  </div>
  <div style="margin-top:8px;">Số tiền cọc: <strong id="qr_deposit_amount" class="text-danger">0đ</strong></div>
  <div id="qr_deposit_hint" style="margin-top:6px;color:#777;font-size:12px;">Quét QR để chuyển khoản đúng nội dung & số tiền. Sau khi nhận tiền, bấm "Xác nhận đã nhận cọc".</div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button><button type="button" class="btn-primary-soft" id="qr_deposit_confirm_btn"><i class="fas fa-check"></i> Xác nhận đã nhận cọc</button></div></div></div></div>
<div class="modal fade" id="settleModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><form method="post" class="modal-content" id="settleForm"><input type="hidden" name="rental_id" id="settle_rental_id"><div class="modal-header"><h5>Trả đồ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<div class="row g-3 mb-3"><div class="col-md-4"><label>Thời gian trả thực tế (NOW)</label><input type="datetime-local" class="form-control" name="actual_return_at" id="settle_actual"></div><div class="col-md-4"><label>Phí trả muộn (cho phép điều chỉnh)</label><input class="form-control money-input" inputmode="numeric" name="late_fee_override" id="settle_late_override" placeholder="Tự tính theo giờ trễ"></div><div class="col-md-4"><label>Phiếu thuê</label><div id="settle_meta" class="form-control" style="background:#f8f9fb;">—</div></div></div>
<div class="table-responsive mb-3"><table class="table align-middle"><thead><tr><th>Trang phục</th><th>SL</th><th>Mức hư hỏng</th><th>Phí hư hỏng (ước tính)</th></tr></thead><tbody id="settle_items"><tr><td colspan="4" class="text-muted">Chọn phiếu để tải chi tiết…</td></tr></tbody></table></div>
<div class="info-card" id="settle_breakdown"><h4>Bảng tính chi tiết (minh bạch, cập nhật realtime)</h4>
<div class="detail-money">
<div><span>Tiền thuê gốc</span><strong id="sb_rental">0đ</strong></div>
<div><span>Phí trả muộn</span><strong id="sb_late">0đ</strong></div>
<div><span>Tổng phí hư hỏng</span><strong id="sb_damage">0đ</strong></div>
<div class="total"><span>Tổng chi phí thực tế</span><strong id="sb_total">0đ</strong></div>
<div><span>Tiền cọc đã thanh toán</span><strong id="sb_deposit">0đ</strong></div>
<div><span>Quyết toán (thực tế - cọc)</span><strong id="sb_settle">0đ</strong></div>
<div><span>Kết quả</span><strong id="sb_result">—</strong></div>
</div>
<div class="row g-3 mt-2">
  <div class="col-md-4"><label>Phương thức tất toán</label><select class="form-select" name="settle_method" id="settle_method"><option value="cash">Tiền mặt</option><option value="bank">Chuyển khoản / QR</option></select></div>
  <div class="col-md-8" id="refund_bank_wrap" style="display:none;"><label>STK ngân hàng khách (khi hoàn cọc chuyển khoản)</label><input class="form-control" name="refund_bank_account" id="refund_bank_account" placeholder="Ngân hàng - Số TK - Chủ TK"></div>
</div>
<div id="tattoan_qr_area" style="display:none;margin-top:12px;padding:12px;border:1px dashed #d6c7ff;border-radius:10px;background:#faf8ff;text-align:center;">
  <div style="font-weight:600;color:#4b2fb6;margin-bottom:6px;"><i class="fas fa-qrcode"></i> QR tất toán (VietQR) - Khách trả thêm</div>
  <img id="tattoan_qr_img" src="" alt="QR tất toán" style="width:220px;max-width:100%;height:auto;border:1px solid #eee;border-radius:10px;padding:6px;background:#fff;">
  <div style="margin-top:6px;font-size:13px;">Nội dung: <strong id="tattoan_qr_content">—</strong></div>
  <div>Số tiền thiếu: <strong id="tattoan_qr_amount" class="text-danger">0đ</strong></div>
  <div style="color:#777;font-size:12px;margin-top:4px;">Khách quét QR xong, nhấn nút xác nhận để hoàn tất tất toán.</div>
</div>
<div class="alert alert-info" id="settle_hint" style="font-size:13px;margin:10px 0 0;">Chọn phương thức và nhấn xác nhận để hoàn tất tất toán (phiếu chuyển Đã hoàn tất, ghi nhận giao dịch).</div>
</div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button><button class="btn-primary-soft" name="settle_rental" id="settle_submit_btn">Xác nhận hoàn tất tất toán</button></div></form></div></div>
<form method="post" id="statusForm"><input type="hidden" name="rental_id" id="status_rental_id"><input type="hidden" name="status_action" id="status_action"><input type="hidden" name="deposit_method" id="status_deposit_method"></form>
<script>
const costumes=<?= json_encode($costumeArray,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
let itemIndex=0;
function costumeOptions(selected=''){return '<option value="">Chọn trang phục</option>'+costumes.map(c=>{const code=c.code||('SP'+String(c.id).padStart(4,'0'));const name=c.name||'';const size=c.size||'';return `<option value="${c.id}" ${String(selected)===String(c.id)?'selected':''}>${code} · ${escapeHtml(name)} · Size ${escapeHtml(size)}</option>`;}).join('');}
function addRentalItem(item=null){
const box=document.getElementById('rentalItems');const row=document.createElement('div');row.className='rental-item-row';row.dataset.index=itemIndex++;
row.innerHTML=`<div><label>Trang phục</label><select name="costume_id[]" class="form-select costume-select" required>${costumeOptions(item?item.costume_id:'')}</select></div><div><label>Size</label><input name="item_size[]" class="form-control item-size" value="${item?escapeHtml(item.selected_size||''):''}" required></div><div><label>Số lượng</label><input type="number" min="1" name="item_quantity[]" class="form-control item-qty" value="${item?item.quantity:1}" required></div><div><label>Tình trạng giao</label><input name="condition_out[]" class="form-control" value="${item?escapeHtml(item.condition_out||'Tốt'):'Tốt'}"></div><button type="button" class="remove-row"><i class="fas fa-xmark"></i></button>`;
row.querySelector('.remove-row').onclick=()=>{row.remove();calculateEstimate();};
row.querySelector('.costume-select').addEventListener('change',e=>{const c=costumes.find(x=>String(x.id)===e.target.value);if(c&&!row.querySelector('.item-size').value)row.querySelector('.item-size').value=c.size;calculateEstimate();});
row.querySelector('.item-qty').addEventListener('input',calculateEstimate);box.appendChild(row);calculateEstimate();
}
function calculateEstimate(){const start=document.getElementById('rental_receive').value,end=document.getElementById('rental_return').value;let days=1;if(start&&end){days=Math.max(1,Math.floor((new Date(end)-new Date(start))/86400000)+1);}let total=0,deposit=0;document.querySelectorAll('.rental-item-row').forEach(row=>{const c=costumes.find(x=>String(x.id)===row.querySelector('.costume-select').value);const qty=Number(row.querySelector('.item-qty').value)||0;if(c){total+=Number(c.rental_price)*qty*days;deposit+=Number(c.deposit)*qty;}});const discount=Number(document.getElementById('rental_discount').value.replace(/\D/g,''))||0;document.getElementById('rentalEstimate').textContent=(Math.max(0,total-discount)+deposit).toLocaleString('en-US')+'đ';}
function escapeHtml(v){return String(v).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
function genDepositTxn(){const d=new Date();const p=n=>String(n).padStart(2,'0');return 'COC'+d.getFullYear()+p(d.getMonth()+1)+p(d.getDate())+p(d.getHours())+p(d.getMinutes())+p(d.getSeconds())+Math.floor(Math.random()*90+10);}
function openRental(data=null){
document.getElementById('rental_id').value='';document.getElementById('rental_customer').value='';document.getElementById('rental_receive').value='';document.getElementById('rental_return').value='';document.getElementById('rental_discount').value='0';document.getElementById('rental_note').value='';if(document.getElementById('rental_deposit_paid'))document.getElementById('rental_deposit_paid').value='0';if(document.getElementById('rental_deposit_method'))document.getElementById('rental_deposit_method').value='cash';if(document.getElementById('rental_deposit_txn'))document.getElementById('rental_deposit_txn').value=genDepositTxn();document.getElementById('rentalItems').innerHTML='';document.getElementById('rental_title').textContent='Tạo phiếu thuê';itemIndex=0;addRentalItem();
}
async function editRental(id){const res=await fetch('ajax_get_rental.php?id='+id);const data=await res.json();if(!data.success){Swal.fire('Lỗi',data.message,'error');return;}openRental();const r=data.rental;document.getElementById('rental_id').value=r.id;document.getElementById('rental_customer').value=r.customer_id;document.getElementById('rental_receive').value=r.receive_date;document.getElementById('rental_return').value=r.return_date;document.getElementById('rental_discount').value=Number(r.discount).toLocaleString('en-US');document.getElementById('rental_note').value=r.note||'';document.getElementById('rentalItems').innerHTML='';data.items.forEach(addRentalItem);document.getElementById('rental_title').textContent='Cập nhật phiếu thuê';calculateEstimate();new bootstrap.Modal(document.getElementById('rentalModal')).show();}
async function viewRental(id){const modal=new bootstrap.Modal(document.getElementById('detailModal'));modal.show();document.getElementById('detailContent').innerHTML='<div class="loading"><i class="fas fa-spinner fa-spin"></i>Đang tải...</div>';const html=await fetch('ajax_get_rental.php?id='+id+'&html=1').then(r=>r.text());document.getElementById('detailContent').innerHTML=html;}
function submitStatus(id,action,extra){document.getElementById('status_rental_id').value=id;document.getElementById('status_action').value=action;document.getElementById('status_deposit_method').value=(extra&&extra.deposit_method)?extra.deposit_method:'';document.getElementById('statusForm').submit();}
function changeStatus(id,action,message,extra){Swal.fire({title:message,icon:'question',showCancelButton:true,confirmButtonText:'Xác nhận',cancelButtonText:'Hủy'}).then(r=>{if(r.isConfirmed){submitStatus(id,action,extra);}});}
function deleteRental(id){Swal.fire({title:'Xóa phiếu đã hủy?',icon:'warning',showCancelButton:true,confirmButtonText:'Xóa',cancelButtonText:'Đóng',confirmButtonColor:'#d66c7a'}).then(r=>{if(r.isConfirmed)location.href='?delete='+id;});}
let settleData=null;
function fmtVnd(n){return (Number(n)||0).toLocaleString('en-US')+'đ';}
function calcLateFeeClient(returnDate, actualStr, daily){
  if(!actualStr) return 0; const due=new Date(returnDate+'T23:59:59'); const act=new Date(actualStr); let h=(act-due)/36e5; if(h<=0) return 0;
  if(h<2) return 0; if(h<=6) return Math.round(daily*0.3/100)*100; if(h<=24) return Math.round(daily*1.0/100)*100; const extra=Math.max(1,Math.ceil(h/24)-1); return Math.round(daily*1.5*extra/100)*100;
}
function itemDamageFee(it, level, val){
  level=Number(level)||0; val=Number(val)||0; const dep=Number(it.deposit_amount||0); const qty=Number(it.quantity||1); const listed=Math.max(Number(it.rental_price||0)*10, Number(it.unit_price||0)*10)*qty;
  if(level===1) return Math.min(100000,Math.max(50000,val||100000));
  if(level===2) return Math.min(300000,Math.max(150000,val||300000));
  if(level===3){ let ratio=val||1; ratio=Math.min(1,Math.max(0.7,ratio)); const base=dep>0?dep:(settleData?Number(settleData.rental.deposit_paid||0):0); return Math.round(base*ratio/100)*100; }
  if(level===4) return Math.round(listed/100)*100; return 0;
}
function renderSettleBreakdown(){
  if(!settleData) return; const r=settleData.rental; const daily=Number(r.daily_rate||0); const actual=document.getElementById('settle_actual').value; const rentalAmt=Number(r.rental_amount||0);
  let lateAuto=calcLateFeeClient(r.return_date, actual, daily);
  const ovEl=document.getElementById('settle_late_override');
  const ovRaw=ovEl?String(ovEl.value||'').replace(/\D/g,''):'';
  const late = ovRaw!=='' ? Number(ovRaw||0) : lateAuto;
  let damage=0;
  (settleData.items||[]).forEach(it=>{ const lv=document.getElementById('dmg_lv_'+it.id); const amt=document.getElementById('dmg_amt_'+it.id); const fee=itemDamageFee(it, lv?lv.value:0, amt?amt.value:0); damage+=fee; const out=document.getElementById('dmg_fee_'+it.id); if(out) out.textContent=fmtVnd(fee); });
  const discount = Number(r.discount || 0);
const total = Math.max(0, rentalAmt - discount) + late + damage;
  const dep=Number(r.deposit_paid||0); const settle=total-dep;
  document.getElementById('sb_rental').textContent=fmtVnd(rentalAmt); document.getElementById('sb_late').textContent=fmtVnd(late)+(ovRaw===''? ' (tự tính)':' (đã điều chỉnh)'); document.getElementById('sb_damage').textContent=fmtVnd(damage);
  document.getElementById('sb_total').textContent=fmtVnd(total); document.getElementById('sb_deposit').textContent=fmtVnd(dep); document.getElementById('sb_settle').textContent=(settle>0?'+':'')+fmtVnd(settle);
  const res=document.getElementById('sb_result'); const method=(document.getElementById('settle_method')||{}).value||'cash';
  const qrArea=document.getElementById('tattoan_qr_area'); const refundWrap=document.getElementById('refund_bank_wrap'); const btn=document.getElementById('settle_submit_btn');
  if(settle>0){
    res.textContent='Thu tiền khách'+fmtVnd(settle)+''; res.className='text-danger';
    if(refundWrap) refundWrap.style.display='none';
    const code='PT'+String(r.id).padStart(5,'0'); const content='TATTOAN_'+code;
    if(method==='bank'){
      if(qrArea) qrArea.style.display='block';
      const bank='MB', acc='0000000000', accName=encodeURIComponent('LUMIDRESS');
      const qr='https://img.vietqr.io/image/'+bank+'-'+acc+'-compact2.png?amount='+encodeURIComponent(Math.round(settle))+'&addInfo='+encodeURIComponent(content)+'&accountName='+accName;
      const img=document.getElementById('tattoan_qr_img'); if(img) img.src=qr;
      const c=document.getElementById('tattoan_qr_content'); if(c) c.textContent=content;
      const a=document.getElementById('tattoan_qr_amount'); if(a) a.textContent=fmtVnd(settle);
      if(btn) btn.textContent='Xác nhận đã nhận chuyển khoản & hoàn tất';
    } else {
      if(qrArea) qrArea.style.display='none';
      if(btn) btn.textContent='Xác nhận đã thu tiền mặt & hoàn tất';
    }
  } else if(settle<0){
    const back=Math.abs(settle);
    res.textContent='Hoàn cọc thừa '+fmtVnd(back)+''; res.className='text-success';
    if(qrArea) qrArea.style.display='none';
    if(refundWrap) refundWrap.style.display = (method==='bank'?'block':'none');
    if(btn) btn.textContent = (method==='bank'?'Xác nhận đã chuyển khoản hoàn cọc':'Xác nhận đã hoàn cọc tiền mặt');
  } else {
    res.textContent='Đủ tiền: không thu thêm/không hoàn'; res.className='';
    if(qrArea) qrArea.style.display='none'; if(refundWrap) refundWrap.style.display='none';
    if(btn) btn.textContent='Xác nhận hoàn tất tất toán';
  }
}
async function openSettle(id){document.getElementById('settle_rental_id').value=id;const now=new Date();const p=n=>String(n).padStart(2,'0');document.getElementById('settle_actual').value=now.getFullYear()+"-"+p(now.getMonth()+1)+"-"+p(now.getDate())+"T"+p(now.getHours())+":"+p(now.getMinutes());
if(document.getElementById('settle_late_override')) document.getElementById('settle_late_override').value='';
if(document.getElementById('settle_method')) document.getElementById('settle_method').value='cash';
if(document.getElementById('refund_bank_account')) document.getElementById('refund_bank_account').value='';
document.getElementById('settle_items').innerHTML='<tr><td colspan="4">Đang tải chi tiết…</td></tr>';
const res=await fetch('ajax_get_rental.php?id='+id); settleData=await res.json();
if(!settleData||!settleData.success){ Swal.fire('Lỗi',settleData&&settleData.message?settleData.message:'Không tải được phiếu','error'); return; }
const r=settleData.rental; document.getElementById('settle_meta').textContent='PT'+String(r.id).padStart(5,'0')+' · '+(r.fullname||'')+' · Hạn trả: '+(r.return_date||'')+' · Cọc đã trả: '+fmtVnd(Number(r.deposit_paid||0));
let rows=''; (settleData.items||[]).forEach(it=>{
  rows+=`<tr><td><strong>${escapeHtml(it.name||'')}</strong><div class="text-muted" style="font-size:12px;">Size ${escapeHtml(it.selected_size||'')} · ${it.quantity||1} bộ</div></td><td>${it.quantity||1}</td><td>
  <select class="form-select form-select-sm" name="item_level[${it.id}]" id="dmg_lv_${it.id}">
    <option value="0">Không hư hỏng</option>
    <option value="1">Mức 1 - Nhẹ (50k-100k)</option>
    <option value="2">Mức 2 - Trung bình (150k-300k)</option>
    <option value="3">Mức 3 - Nặng (70-100% cọc)</option>
    <option value="4">Mức 4 - Mất/Hỏng (100% giá trị)</option>
  </select>
  <input type="hidden" name="item_damage[${it.id}]" id="dmg_amt_${it.id}" value="0">
  </td><td id="dmg_fee_${it.id}">0đ</td></tr>`;
});
document.getElementById('settle_items').innerHTML=rows||'<tr><td colspan="4">Không có trang phục.</td></tr>';
(settleData.items||[]).forEach(it=>{ const sel=document.getElementById('dmg_lv_'+it.id); if(sel) sel.addEventListener('change',()=>{ const amt=document.getElementById('dmg_amt_'+it.id); if(sel.value==='1') amt.value=100000; else if(sel.value==='2') amt.value=300000; else if(sel.value==='3') amt.value=1.0; else amt.value=0; renderSettleBreakdown(); }); });
document.getElementById('settle_actual').addEventListener('change',renderSettleBreakdown);
if(document.getElementById('settle_late_override')) document.getElementById('settle_late_override').addEventListener('input',renderSettleBreakdown);
if(document.getElementById('settle_method')) document.getElementById('settle_method').addEventListener('change',renderSettleBreakdown);
renderSettleBreakdown();
new bootstrap.Modal(document.getElementById('settleModal')).show();}
document.getElementById('rental_receive').addEventListener('change',calculateEstimate);document.getElementById('rental_return').addEventListener('change',calculateEstimate);document.getElementById('rental_discount').addEventListener('input',calculateEstimate);
<?php if($openAdd):?>document.addEventListener('DOMContentLoaded',()=>{openRental();new bootstrap.Modal(document.getElementById('rentalModal')).show();});<?php endif;?>
function renderDepositMethodUI(method, code){
  const bankArea=document.getElementById('qr_deposit_bank_area');
  const hint=document.getElementById('qr_deposit_hint');
  const btn=document.getElementById('qr_deposit_confirm_btn');
  if(method==='cash'){
    if(bankArea) bankArea.style.display='none';
    if(hint) hint.textContent='Thanh toán tiền mặt: xác nhận sau khi đã thu đủ tiền cọc.';
    if(btn) btn.innerHTML='<i class="fas fa-check"></i> Xác nhận đã thu cọc tiền mặt';
  } else {
    if(bankArea) bankArea.style.display='block';
    if(hint) hint.textContent='Quét QR để chuyển khoản đúng nội dung & số tiền. Sau khi nhận tiền, bấm "Xác nhận đã nhận cọc".';
    if(btn) btn.innerHTML='<i class="fas fa-check"></i> Xác nhận đã nhận cọc chuyển khoản';
  }
}
async function openQrDeposit(id){
  const res=await fetch('ajax_get_rental.php?id='+id); const data=await res.json();
  if(!data||!data.success){ Swal.fire('Lỗi',data&&data.message?data.message:'Không tải được phiếu','error'); return; }
  const r=data.rental; const code='PT'+String(r.id).padStart(5,'0'); const phone=String(r.phone||'').replace(/\D+/g,'') || String(r.customer_id||'');
  const depositAmt=Math.max(0, Number(r.deposit_amount||0) - Number(r.deposit_paid||0));
  const content='COC_'+code+'_'+phone;
  // VietQR ảnh (cấu hình tĩnh; có thể thay bằng STK thật của cửa hàng)
  const bank='MB'; const acc='0000000000'; const accName=encodeURIComponent('LUMIDRESS');
  const qr='https://img.vietqr.io/image/'+bank+'-'+acc+'-compact2.png?amount='+encodeURIComponent(Math.round(depositAmt))+'&addInfo='+encodeURIComponent(content)+'&accountName='+accName;
  document.getElementById('qr_deposit_meta').textContent=code+' · '+(r.fullname||'')+' · '+(r.phone||'');
  document.getElementById('qr_deposit_img').src=qr;
  document.getElementById('qr_deposit_content').textContent=content;
  document.getElementById('qr_deposit_amount').textContent=fmtVnd(depositAmt);
  const methodSel=document.getElementById('qr_deposit_method');
  if(methodSel){ methodSel.value='bank'; methodSel.onchange=function(){ renderDepositMethodUI(methodSel.value, code); }; }
  renderDepositMethodUI('bank', code);
  const btn=document.getElementById('qr_deposit_confirm_btn');
  btn.style.display='';
  btn.disabled=false;
  btn.onclick=function(){
    const m=(document.getElementById('qr_deposit_method')||{}).value||'bank';
    const modalEl=document.getElementById('qrDepositModal');
    const inst=bootstrap.Modal.getInstance(modalEl); if(inst) inst.hide();
    Swal.fire({title:'Thành công',text:'Đã ghi nhận thanh toán cọc. Phiếu thuê sẽ chuyển sang "Đã cọc".',icon:'success',timer:1200,showConfirmButton:false}).then(function(){ submitStatus(r.id,'deposit',{deposit_method:m}); });
  };
  new bootstrap.Modal(document.getElementById('qrDepositModal')).show();
}
</script>
<?php include 'footer.php'; ?>