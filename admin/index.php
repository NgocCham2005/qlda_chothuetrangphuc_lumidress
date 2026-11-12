<?php
include 'header.php';
$conn->query("UPDATE rentals SET status='overdue' WHERE status='picked_up' AND return_date<CURDATE()");
$active=(int)$conn->query("SELECT COUNT(*) total FROM rentals WHERE status IN ('reserved','picked_up','overdue')")->fetch_assoc()['total'];
$todayPickup=(int)$conn->query("SELECT COUNT(*) total FROM rentals WHERE receive_date=CURDATE() AND status='reserved'")->fetch_assoc()['total'];
$todayReturn=(int)$conn->query("SELECT COUNT(*) total FROM rentals WHERE return_date=CURDATE() AND status IN ('picked_up','overdue')")->fetch_assoc()['total'];
//sửa
$revenue=(float)$conn->query("
    SELECT COALESCE(
        SUM(CASE WHEN payment_type='refund' THEN -amount ELSE amount END),0
    ) total
    FROM payments
    WHERE MONTH(
        CASE WHEN payment_type='refund' THEN refunded_at ELSE paid_at END
    )=MONTH(CURDATE())
    AND YEAR(
        CASE WHEN payment_type='refund' THEN refunded_at ELSE paid_at END
    )=YEAR(CURDATE())
")->fetch_assoc()['total'];
//$revenue=(float)$conn->query("SELECT COALESCE(SUM(CASE WHEN payment_type='refund' THEN -amount ELSE amount END),0) total FROM payments WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetch_assoc()['total'];
$monthLabels=[];$monthRevenue=[];
//sửa
$q=$conn->query("
    SELECT
        DATE_FORMAT(
            CASE WHEN payment_type='refund' THEN refunded_at ELSE paid_at END,
            '%m/%Y'
        ) label,
        SUM(CASE WHEN payment_type='refund' THEN -amount ELSE amount END) total,
        DATE_FORMAT(
            CASE WHEN payment_type='refund' THEN refunded_at ELSE paid_at END,
            '%Y-%m'
        ) sort_key
    FROM payments
    WHERE (
        CASE WHEN payment_type='refund' THEN refunded_at ELSE paid_at END
    ) >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY sort_key,label
    ORDER BY sort_key
");
//$q=$conn->query("SELECT DATE_FORMAT(created_at,'%m/%Y') label,SUM(CASE WHEN payment_type='refund' THEN -amount ELSE amount END) total,DATE_FORMAT(created_at,'%Y-%m') sort_key FROM payments WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL 5 MONTH) GROUP BY sort_key,label ORDER BY sort_key");while($q&&$r=$q->fetch_assoc()){$monthLabels[]=$r['label'];$monthRevenue[]=(float)$r['total'];}if(!$monthLabels){$monthLabels=['T2','T3','T4','T5','T6','T7'];$monthRevenue=array_fill(0,6,0);}
$statusLabels=['Đã đặt','Đã nhận','Đã trả','Quá hạn','Đã hủy'];$statusData=[];foreach(['reserved','picked_up','returned','overdue','cancelled'] as $s){$stmt=$conn->prepare("SELECT COUNT(*) total FROM rentals WHERE status=?");$stmt->bind_param('s',$s);$stmt->execute();$statusData[]=(int)$stmt->get_result()->fetch_assoc()['total'];}
$categoryLabels=[];$categoryData=[];$q=$conn->query("SELECT c.name,COUNT(co.id) total FROM categories c LEFT JOIN costumes co ON co.category_id=c.id GROUP BY c.id,c.name ORDER BY total DESC");while($q&&$r=$q->fetch_assoc()){$categoryLabels[]=$r['name'];$categoryData[]=(int)$r['total'];}
$recent=$conn->query("SELECT r.*,c.fullname,c.phone FROM rentals r JOIN customers c ON c.id=r.customer_id ORDER BY r.created_at DESC LIMIT 7");
$due=$conn->query("SELECT r.*,c.fullname,c.phone FROM rentals r JOIN customers c ON c.id=r.customer_id WHERE r.status IN ('picked_up','overdue') ORDER BY r.return_date ASC LIMIT 7");
?>
<section class="welcome-panel">
<div><span>Trung tâm vận hành</span><h2>Xin chào, <?= htmlspecialchars($_SESSION['admin']['fullname']) ?></h2><p>Theo dõi lịch nhận trả, trang phục và thanh toán trong một màn hình.</p></div>
<a href="rentals.php?add=1"><i class="fas fa-plus"></i>Tạo phiếu thuê</a>
</section>
<div class="kpi-grid">
<article class="kpi-card"><span class="kpi-icon icon-violet"><i class="fas fa-file-signature"></i></span><div><small>Phiếu đang hoạt động</small><strong><?= number_format($active) ?></strong></div></article>
<article class="kpi-card"><span class="kpi-icon icon-blue"><i class="fas fa-bag-shopping"></i></span><div><small>Nhận đồ hôm nay</small><strong><?= number_format($todayPickup) ?></strong></div></article>
<article class="kpi-card"><span class="kpi-icon icon-orange"><i class="fas fa-arrow-rotate-left"></i></span><div><small>Trả đồ hôm nay</small><strong><?= number_format($todayReturn) ?></strong></div></article>
<article class="kpi-card"><span class="kpi-icon icon-green"><i class="fas fa-wallet"></i></span><div><small>Thu trong tháng</small><strong><?= format_price($revenue) ?>đ</strong></div></article>
</div>
<div class="chart-grid">
<section class="panel chart-wide"><div class="panel-head"><div><h3>Doanh thu 6 tháng</h3><span>Tiền thuê, cọc và phát sinh</span></div><i class="fas fa-chart-line"></i></div><div class="chart-box"><canvas id="revenueChart"></canvas></div></section>
<section class="panel"><div class="panel-head"><div><h3>Trạng thái phiếu thuê</h3><span>Tổng số phiếu theo tiến độ</span></div><i class="fas fa-chart-pie"></i></div><div class="chart-box"><canvas id="statusChart"></canvas></div></section>
</div>
<div class="dashboard-bottom">
<section class="panel"><div class="panel-head"><div><h3>Trang phục theo danh mục</h3><span>Số mẫu đang quản lý</span></div><i class="fas fa-chart-column"></i></div><div class="chart-box small"><canvas id="categoryChart"></canvas></div></section>
<section class="panel table-panel">
    <div class="panel-head">
        <div>
            <h3>Phiếu thuê gần đây</h3>
            <span>Cập nhật mới nhất</span>
        </div>
        <a href="rentals.php">Xem tất cả</a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Mã phiếu</th>
                    <th>Khách hàng</th>
                    <th>Ngày thuê</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php if($recent && $recent->num_rows): ?>
                    <?php while($r=$recent->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong>PT<?= str_pad($r['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                        </td>
                        <td>
                            <?= htmlspecialchars($r['fullname']) ?>
                            <small class="d-block text-muted">
                                <?= htmlspecialchars($r['phone']) ?>
                            </small>
                        </td>
                        <td>
                            <?= date('d/m/Y',strtotime($r['receive_date'])) ?>
                            -
                            <?= date('d/m/Y',strtotime($r['return_date'])) ?>
                        </td>
                        <td>
                            <span class="status status-<?= htmlspecialchars($r['status']) ?>">
                                <?= rental_status_label($r['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="empty">Chưa có dữ liệu.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<section class="panel due-panel">
    <div class="panel-head">
        <div>
            <h3>Lịch cần trả gần nhất</h3>
            <span>Ưu tiên xử lý các phiếu quá hạn</span>
        </div>
        <a href="schedule.php">Mở lịch thuê</a>
    </div>

    <div class="due-list">
        <?php if($due && $due->num_rows): ?>
            <?php while($r=$due->fetch_assoc()): ?>
                <article>
                    <div class="due-date">
                        <strong><?= date('d',strtotime($r['return_date'])) ?></strong>
                        <span><?= date('m/Y',strtotime($r['return_date'])) ?></span>
                    </div>

                    <div>
                        <strong>
                            PT<?= str_pad($r['id'], 5, '0', STR_PAD_LEFT) ?>
                            · <?= htmlspecialchars($r['fullname']) ?>
                        </strong>
                        <span><?= htmlspecialchars($r['phone']) ?></span>
                    </div>

                    <span class="status status-<?= htmlspecialchars($r['status']) ?>">
                        <?= rental_status_label($r['status']) ?>
                    </span>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty">Không có phiếu cần trả.</div>
        <?php endif; ?>
    </div>
</section>
<script>
const colors=['#6f63d9','#4f9fe6','#f0a15d','#51ad83','#d66c7a','#e1c052','#7b8da8','#4cb7b1'];
new Chart(document.getElementById('revenueChart'),{type:'line',data:{labels:<?= json_encode($monthLabels,JSON_UNESCAPED_UNICODE) ?>,datasets:[{label:'Doanh thu',data:<?= json_encode($monthRevenue) ?>,borderColor:'#6f63d9',backgroundColor:'rgba(111,99,217,.12)',fill:true,tension:.38,pointRadius:4,pointBackgroundColor:'#fff',pointBorderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{callback:v=>Number(v).toLocaleString('en-US')}}}}});
new Chart(document.getElementById('statusChart'),{type:'doughnut',data:{labels:<?= json_encode($statusLabels,JSON_UNESCAPED_UNICODE) ?>,datasets:[{data:<?= json_encode($statusData) ?>,backgroundColor:colors,borderWidth:3,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,cutout:'64%',plugins:{legend:{position:'bottom',labels:{boxWidth:10,usePointStyle:true}}}}});
new Chart(document.getElementById('categoryChart'),{type:'bar',data:{labels:<?= json_encode($categoryLabels,JSON_UNESCAPED_UNICODE) ?>,datasets:[{data:<?= json_encode($categoryData) ?>,backgroundColor:colors,borderRadius:9}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0}}}}});
</script>
<?php include 'footer.php'; ?>