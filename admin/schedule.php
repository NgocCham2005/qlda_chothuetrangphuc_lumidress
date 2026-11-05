<?php
include 'header.php';
$conn->query("UPDATE rentals SET status='overdue' WHERE status='picked_up' AND return_date<CURDATE()");
$month=$_GET['month']??date('Y-m');
if(!preg_match('/^\d{4}-\d{2}$/',$month))$month=date('Y-m');
$start=$month.'-01';$end=date('Y-m-t',strtotime($start));
$stmt=$conn->prepare("SELECT r.*,c.fullname,c.phone FROM rentals r JOIN customers c ON c.id=r.customer_id WHERE r.status<>'cancelled' AND (r.receive_date BETWEEN ? AND ? OR r.return_date BETWEEN ? AND ? OR (r.receive_date<? AND r.return_date>?)) ORDER BY r.receive_date,r.return_date");
$stmt->bind_param('ssssss',$start,$end,$start,$end,$start,$end);$stmt->execute();$rentals=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$events=[];foreach($rentals as $r){if($r['receive_date']>=$start&&$r['receive_date']<=$end)$events[$r['receive_date']][]=['type'=>'receive','data'=>$r];if($r['return_date']>=$start&&$r['return_date']<=$end)$events[$r['return_date']][]=['type'=>'return','data'=>$r];}
$firstWeekday=(int)date('N',strtotime($start));$daysInMonth=(int)date('t',strtotime($start));$prev=date('Y-m',strtotime($start.' -1 month'));$next=date('Y-m',strtotime($start.' +1 month'));
?>
<div class="page-head"><div><h2>Lịch thuê trang phục</h2><p>Theo dõi ngày nhận, ngày trả và các phiếu quá hạn.</p></div><a class="btn-primary-soft" href="rentals.php?add=1"><i class="fas fa-plus"></i>Tạo phiếu thuê</a></div>
<div class="calendar-toolbar"><a href="?month=<?= $prev ?>"><i class="fas fa-chevron-left"></i></a><h3>Tháng <?= date('m/Y',strtotime($start)) ?></h3><a href="?month=<?= $next ?>"><i class="fas fa-chevron-right"></i></a><form><input type="month" name="month" value="<?= htmlspecialchars($month) ?>" onchange="this.form.submit()"></form></div>
<section class="panel calendar-panel">
<div class="calendar-week"><span>Thứ hai</span><span>Thứ ba</span><span>Thứ tư</span><span>Thứ năm</span><span>Thứ sáu</span><span>Thứ bảy</span><span>Chủ nhật</span></div>
<div class="calendar-grid">
<?php for($blank=1;$blank<$firstWeekday;$blank++):?><div class="calendar-day blank"></div><?php endfor;?>
<?php for($day=1;$day<=$daysInMonth;$day++):$date=$month.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT);$isToday=$date===date('Y-m-d');?>
<div class="calendar-day <?= $isToday?'today':'' ?>"><div class="day-number"><?= $day ?></div><div class="day-events">
<?php foreach($events[$date]??[] as $event):$r=$event['data'];?>
<button type="button" class="calendar-event <?= $event['type'] ?> <?= $r['status']==='overdue'?'overdue':'' ?>" onclick="viewRental(<?= $r['id'] ?>)">
<i class="fas <?= $event['type']==='receive'?'fa-arrow-down':'fa-arrow-up' ?>"></i><span><?= $event['type']==='receive'?'Nhận':'Trả' ?> · <?= htmlspecialchars($r['rental_code']) ?></span><small><?= htmlspecialchars($r['fullname']) ?></small>
</button>
<?php endforeach;?>
</div></div>
<?php endfor;?>
</div>
</section>
<div class="calendar-legend"><span><i class="legend-receive"></i>Ngày nhận</span><span><i class="legend-return"></i>Ngày trả</span><span><i class="legend-overdue"></i>Quá hạn</span></div>
<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5>Chi tiết phiếu thuê</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="detailContent"></div></div></div></div>
<script>
async function viewRental(id){const modal=new bootstrap.Modal(document.getElementById('detailModal'));modal.show();document.getElementById('detailContent').innerHTML='<div class="loading"><i class="fas fa-spinner fa-spin"></i>Đang tải...</div>';document.getElementById('detailContent').innerHTML=await fetch('ajax_get_rental.php?id='+id+'&html=1').then(r=>r.text());}
</script>
<?php include 'footer.php'; ?>