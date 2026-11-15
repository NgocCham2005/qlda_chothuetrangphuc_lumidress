<?php
session_start();
require_once '../config.php';
if(!isset($_SESSION['admin'])){header('Location: login.php');exit;}
$id=(int)($_GET['id']??0);
$isHandover=(($_GET['type']??'')==='handover');
$stmt=$conn->prepare("SELECT r.*,c.fullname,c.phone,c.email,c.identity_number,c.address FROM rentals r JOIN customers c ON c.id=r.customer_id WHERE r.id=?");
if(!$stmt){ die('Lỗi truy vấn phiếu thuê: '.htmlspecialchars($conn->error)); }
$stmt->bind_param('i',$id);$stmt->execute();$rental=$stmt->get_result()->fetch_assoc();
if(!$rental)die('Không tìm thấy phiếu thuê');
$stmt=$conn->prepare("SELECT ri.*,co.name,co.color FROM rental_items ri JOIN costumes co ON co.id=ri.costume_id WHERE ri.rental_id=? ORDER BY ri.id");
if(!$stmt){ die('Lỗi truy vấn chi tiết phiếu thuê: '.htmlspecialchars($conn->error)); }
$stmt->bind_param('i',$id);$stmt->execute();$items=$stmt->get_result();
$docTitle=$isHandover?'Biên bản bàn giao trang phục':'Phiếu thuê trang phục';
$rentalCode='PT'.str_pad((string)$rental['id'],5,'0',STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($docTitle.' '.$rentalCode) ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#eee;font-family:"Times New Roman",Times,serif;color:#222}.sheet{width:210mm;min-height:297mm;margin:12px auto;padding:16mm 15mm;background:#fff}.top{display:flex;justify-content:space-between;gap:30px;border-bottom:2px solid #443d75;padding-bottom:14px}.brand h1{margin:0;color:#443d75;font-size:28px}.brand p,.meta p{margin:4px 0}.meta{text-align:right}.title{text-align:center;margin:22px 0 18px}.title h2{margin:0;font-size:25px}.title p{margin:5px 0}.info{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}.card{border:1px solid #d9d7e5;border-radius:10px;padding:12px}.card h3{margin:0 0 8px;color:#443d75;font-size:16px}.card p{margin:4px 0}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d9d7e5;padding:9px 7px}th{background:#f2f0f8;text-align:left}td.num,th.num{text-align:right}.money{width:46%;margin:16px 0 0 auto}.money div{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed #d9d7e5}.money .total{font-size:18px;font-weight:bold;color:#443d75;border-top:2px solid #443d75;border-bottom:0;margin-top:6px;padding-top:10px}.note{margin-top:18px;border:1px solid #d9d7e5;border-radius:10px;padding:12px}.signatures{display:grid;grid-template-columns:1fr 1fr 1fr;text-align:center;margin-top:42px;gap:20px}.signatures strong{display:block}.signatures span{display:block;margin-top:72px}.actions{width:210mm;margin:0 auto 15px;text-align:right}.actions button{border:0;border-radius:8px;padding:10px 16px;background:#443d75;color:#fff;font-family:inherit;font-size:16px}@media print{body{background:#fff}.sheet{margin:0;width:auto;min-height:auto}.actions{display:none}} 
</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">In <?= $isHandover?'biên bản bàn giao':'phiếu thuê' ?></button></div>
<div class="sheet">
<div class="top"><div class="brand"><h1>Lumi Dress</h1><p>Cho thuê váy cưới, vest và áo dài</p><p>Điện thoại: 0988 668 886</p></div><div class="meta"><p><strong>Mã phiếu:</strong> <?= htmlspecialchars($rentalCode) ?></p><p><strong>Ngày lập:</strong> <?= date('d/m/Y H:i',strtotime($rental['created_at'])) ?></p><p><strong>Trạng thái:</strong> <?= function_exists('rental_status_label_v2')?rental_status_label_v2($rental['status']):rental_status_label($rental['status']) ?></p></div></div>
<div class="title"><h2><?= htmlspecialchars($docTitle) ?></h2><p><?= $isHandover?'Xác nhận bàn giao trang phục cho khách hàng (đã cọc/đủ điều kiện nhận đồ)':'Khách hàng kiểm tra kỹ trang phục trước khi nhận' ?></p></div>
<div class="info"><div class="card"><h3>Thông tin khách hàng</h3><p><strong>Họ tên:</strong> <?= htmlspecialchars($rental['fullname']) ?></p><p><strong>Điện thoại:</strong> <?= htmlspecialchars($rental['phone']) ?></p><p><strong>CCCD:</strong> <?= htmlspecialchars($rental['identity_number']?:'Chưa cập nhật') ?></p><p><strong>Địa chỉ:</strong> <?= htmlspecialchars($rental['address']?:'Chưa cập nhật') ?></p></div><div class="card"><h3>Thời gian thuê & bàn giao</h3><p><strong>Ngày nhận (dự kiến):</strong> <?= date('d/m/Y',strtotime($rental['receive_date'])) ?></p><p><strong>Ngày trả (dự kiến):</strong> <?= date('d/m/Y',strtotime($rental['return_date'])) ?></p><?php if(!empty($rental['actual_pickup_at'])):?><p><strong>Bàn giao thực tế:</strong> <?= date('d/m/Y H:i',strtotime($rental['actual_pickup_at'])) ?></p><?php endif;?><?php if(!empty($rental['actual_return_at'])):?><p><strong>Trả thực tế:</strong> <?= date('d/m/Y H:i',strtotime($rental['actual_return_at'])) ?></p><?php endif;?><p><strong>Thanh toán:</strong> <?= payment_status_label($rental['payment_status']) ?></p><p><strong>Đã thu:</strong> <?= format_price($rental['paid_amount']) ?>đ</p></div></div>
<table><thead><tr><th>Mã</th><th>Trang phục</th><th>Size</th><th>SL</th><th>Số ngày</th><th class="num">Đơn giá</th><th class="num">Thành tiền</th></tr></thead><tbody>
<?php while($item=$items->fetch_assoc()): $code = $item['code'] ?? ('SP'.str_pad((string)($item['costume_id'] ?? 0), 4, '0', STR_PAD_LEFT)); ?><tr><td><?= htmlspecialchars($code) ?></td><td><?= htmlspecialchars($item['name']) ?><br><small>Tình trạng: <?= htmlspecialchars($item['condition_out'] ?? '') ?></small></td><td><?= htmlspecialchars($item['selected_size'] ?? '') ?></td><td><?= number_format((float)($item['quantity'] ?? 0)) ?></td><td><?= number_format((float)($item['rental_days'] ?? 0)) ?></td><td class="num"><?= format_price($item['unit_price'] ?? 0) ?>đ</td><td class="num"><?= format_price($item['subtotal'] ?? 0) ?>đ</td></tr><?php endwhile;?>
</tbody></table>
<?php $late=(float)($rental['late_fee']??0);$dmg=(float)($rental['damage_fee']??0);$extra=(float)($rental['extra_due_amount']??0);$refund=(float)($rental['refund_amount']??0);$depPaid=(float)($rental['deposit_paid']??0);$totalCost=(float)$rental['rental_amount']+$late+$dmg; ?>
<div class="money"><div><span>Tiền thuê gốc</span><strong><?= format_price($rental['rental_amount']) ?>đ</strong></div><div><span>Giảm giá</span><strong>-<?= format_price($rental['discount']) ?>đ</strong></div><?php if($late>0):?><div><span>Phí trả muộn</span><strong><?= format_price($late) ?>đ</strong></div><?php endif;?><?php if($dmg>0):?><div><span>Phí hư hỏng</span><strong><?= format_price($dmg) ?>đ</strong></div><?php endif;?><div><span>Tổng chi phí đơn thuê</span><strong><?= format_price($totalCost) ?>đ</strong></div><div><span>Tiền cọc yêu cầu</span><strong><?= format_price($rental['deposit_amount']) ?>đ</strong></div><?php if($depPaid>0):?><div><span>Tiền cọc đã thu</span><strong><?= format_price($depPaid) ?>đ</strong></div><?php endif;?><?php if($extra>0):?><div><span>Khách cần thanh toán thêm</span><strong><?= format_price($extra) ?>đ</strong></div><?php endif;?><?php if($refund>0):?><div><span>Hoàn lại khách</span><strong><?= format_price($refund) ?>đ</strong></div><?php endif;?><div class="total"><span>Quyết toán</span><strong><?= format_price($totalCost - ($depPaid>0?$depPaid:(float)$rental['deposit_amount'])) ?>đ</strong></div></div>
<div class="note"><strong>Ghi chú:</strong> <?= nl2br(htmlspecialchars($rental['note']?:'Không có')) ?><br><br><strong>Điều khoản bảo quản & bồi thường:</strong>
<ul style="margin:6px 0 0 18px;">
<li>Khách hàng có trách nhiệm bảo quản trang phục trong suốt thời gian thuê; không tự ý giặt/tẩy/sửa chữa.</li>
<li>Trả trễ: tính phí theo thời gian trễ thực tế (có thể phát sinh phụ phí theo quy định).</li>
<li>Hư hỏng/ố bẩn: bồi thường theo mức độ hư hại (từ vệ sinh/tuột chỉ đến sửa chữa/thay thế hoặc bồi thường theo giá trị/cọc).</li>
<li>Thất lạc/hỏng hoàn toàn: bồi thường theo giá trị trang phục/quy định của cửa hàng. Tiền cọc được cấn trừ khi tất toán.</li>
</ul>
</div>
<div class="signatures"><div><strong>Người lập phiếu</strong><span>________________</span></div><div><strong>Khách hàng</strong><span>________________</span></div><div><strong><?= $isHandover?'Nhân viên bàn giao':'Người nhận lại' ?></strong><span>________________</span></div></div>
</div>
</body>
</html>