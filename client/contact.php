<?php
require_once '../customer_auth.php';

$pageTitle = 'Liên hệ & Đặt lịch tư vấn – Lumi Dress';

$successMsg = '';
$errorMsg   = '';

// Lấy thông tin khách nếu đã đăng nhập để điền sẵn form
$defaultName  = '';
$defaultPhone = '';
$defaultEmail = '';

if (customer_logged_in() && !empty($_SESSION['customer_id'])) {
    $cId = (int)$_SESSION['customer_id'];
    $stmtC = $conn->prepare("SELECT fullname, phone, email FROM customers WHERE id = ? LIMIT 1");
    if ($stmtC) {
        $stmtC->bind_param('i', $cId);
        $stmtC->execute();
        $cRow = $stmtC->get_result()->fetch_assoc();
        if ($cRow) {
            $defaultName  = $cRow['fullname'] ?? '';
            $defaultPhone = $cRow['phone'] ?? '';
            $defaultEmail = $cRow['email'] ?? '';
        }
    }
}

// Xử lý gửi biểu mẫu liên hệ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $service  = trim($_POST['service'] ?? 'tu-van');
    $date     = trim($_POST['visit_date'] ?? '');
    $message  = trim($_POST['message'] ?? '');

    if ($fullname === '') {
        $errorMsg = 'Vui lòng nhập họ và tên của bạn.';
    } elseif ($phone === '' || !preg_match('/^[0-9]{9,11}$/', preg_replace('/\s+/', '', $phone))) {
        $errorMsg = 'Vui lòng nhập số điện thoại hợp lệ (9 - 11 chữ số).';
    } elseif ($message === '') {
        $errorMsg = 'Vui lòng để lại nội dung yêu cầu hoặc thông điệp cho chúng tôi.';
    } else {
        // Form gửi thành công
        $successMsg = 'Cảm ơn bạn đã liên hệ! Lumi Dress đã tiếp nhận thông tin và sẽ gọi điện tư vấn cho bạn sớm nhất.';
        // Reset trường nhập lại nếu gửi thành công
        if (!customer_logged_in()) {
            $defaultName  = '';
            $defaultPhone = '';
            $defaultEmail = '';
        }
    }
}

include 'header.php';
?>

<!-- ============================================================
     BREADCRUMB & HERO BANNER
     ============================================================ -->
<div class="contact-hero">
    <div class="container">
        <div class="contact-hero-content">
            <span class="contact-badge"><i class="fas fa-headset"></i> Hỗ trợ 24/7 &amp; Đặt lịch thử đồ</span>
            <h1>Liên hệ với Lumi Dress</h1>
            <p>Hãy để chúng tôi đồng hành cùng bạn lựa chọn những trang phục lộng lẫy và hoàn hảo nhất cho ngày trọng đại.</p>
        </div>
    </div>
</div>

<main class="contact-main">
    <div class="container">
        
        <?php if (!empty($successMsg)): ?>
            <div class="contact-alert contact-alert-success" role="alert">
                <i class="fas fa-circle-check"></i>
                <div>
                    <strong>Gửi yêu cầu thành công!</strong>
                    <span><?= htmlspecialchars($successMsg) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="contact-alert contact-alert-error" role="alert">
                <i class="fas fa-circle-exclamation"></i>
                <div>
                    <strong>Đã có lỗi xảy ra!</strong>
                    <span><?= htmlspecialchars($errorMsg) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- GRID 2 CỘT: FORM GỬI LIÊN HỆ & THÔNG TIN SHOWROOM -->
        <div class="contact-grid">
            
            <!-- CỘT 1: FORM GỬI TIN NHẮN / ĐẶT LỊCH HẸN -->
            <div class="contact-card contact-form-card">
                <div class="contact-card-header">
                    <span class="contact-sub">Gửi tin nhắn</span>
                    <h2>Đặt lịch thử trang phục hoặc nhận tư vấn</h2>
                    <p>Điền thông tin bên dưới, chuyên viên tư vấn của Lumi Dress sẽ liên hệ hỗ trợ bạn chọn size và thử đồ trực tiếp.</p>
                </div>

                <form action="contact.php" method="POST" class="contact-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullname">Họ và tên <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-user"></i>
                                <input type="text" name="fullname" id="fullname" class="form-input" 
                                       placeholder="Ví dụ: Nguyễn Phương Linh" 
                                       value="<?= htmlspecialchars($_POST['fullname'] ?? $defaultName) ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="phone">Số điện thoại <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-phone"></i>
                                <input type="tel" name="phone" id="phone" class="form-input" 
                                       placeholder="Ví dụ: 0987654321" 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? $defaultPhone) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Địa chỉ Email</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" id="email" class="form-input" 
                                       placeholder="Ví dụ: phuonglinh@gmail.com" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? $defaultEmail) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="service">Dịch vụ quan tâm</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-tag"></i>
                                <select name="service" id="service" class="form-select">
                                    <option value="vay-cuoi" <?= (($_POST['service'] ?? '') === 'vay-cuoi') ? 'selected' : '' ?>>Thuê Váy Cưới / Váy Cô Dâu</option>
                                    <option value="ao-dai" <?= (($_POST['service'] ?? '') === 'ao-dai') ? 'selected' : '' ?>>Thuê Áo Dài (Cưới, kỷ yếu, lễ hội)</option>
                                    <option value="vest" <?= (($_POST['service'] ?? '') === 'vest') ? 'selected' : '' ?>>Thuê Vest Nam cao cấp</option>
                                    <option value="da-hoi" <?= (($_POST['service'] ?? '') === 'da-hoi') ? 'selected' : '' ?>>Thuê Váy Dạ Hội / Sự Kiện</option>
                                    <option value="tu-van" <?= (($_POST['service'] ?? '') === 'tu-van' || empty($_POST['service'])) ? 'selected' : '' ?>>Tư vấn phong cách &amp; phối đồ</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="visit_date">Dự kiến ngày ghé Showroom thử đồ (nếu có)</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-calendar-day"></i>
                            <input type="date" name="visit_date" id="visit_date" class="form-input" 
                                   min="<?= date('Y-m-d') ?>" 
                                   value="<?= htmlspecialchars($_POST['visit_date'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message">Nội dung ghi chú / Yêu cầu chi tiết <span class="required">*</span></label>
                        <textarea name="message" id="message" rows="4" class="form-textarea" 
                                  placeholder="Bạn cần thuê mẫu trang phục nào, ngày dùng, số đo chiều cao/cân nặng hoặc câu hỏi khác..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-contact-submit">
                        <i class="fas fa-paper-plane"></i> Gửi thông tin liên hệ
                    </button>
                </form>
            </div>

            <!-- CỘT 2: THÔNG TIN SHOWROOM & LỢI ÍCH -->
            <div class="contact-info-col">
                
                <div class="contact-card info-card-body">
                    <h3>Thông tin Showroom</h3>
                    <p class="info-desc">Lumi Dress hoan nghênh quý khách ghé thăm không gian showroom để được thử đồ miễn phí và phục vụ chu đáo nhất.</p>

                    <div class="info-list">
                        <div class="info-item">
                            <div class="info-icon"><i class="fas fa-location-dot"></i></div>
                            <div>
                                <h4>Địa chỉ cửa hàng</h4>
                                <p>12 Chùa Bộc, Phường Kim Liên, Đống Đa, Hà Nội</p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon"><i class="fas fa-phone-volume"></i></div>
                            <div>
                                <h4>Hotline hỗ trợ &amp; CSKH</h4>
                                <p><a href="tel:0987654321">0987 654 321</a> (Zalo / Call trực tiếp)</p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon"><i class="fas fa-envelope-open-text"></i></div>
                            <div>
                                <h4>Email liên hệ</h4>
                                <p><a href="mailto:hello@lumidress.vn">hello@lumidress.vn</a></p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon"><i class="fas fa-clock"></i></div>
                            <div>
                                <h4>Giờ mở cửa</h4>
                                <p>8:00 – 21:00 (Tất cả các ngày trong tuần)</p>
                            </div>
                        </div>
                    </div>

                    <div class="social-connect">
                        <h4>Theo dõi chúng tôi</h4>
                        <div class="social-pills">
                            <a href="https://facebook.com" target="_blank" rel="noopener" class="social-pill fb"><i class="fab fa-facebook-f"></i> Facebook</a>
                            <a href="https://instagram.com" target="_blank" rel="noopener" class="social-pill ig"><i class="fab fa-instagram"></i> Instagram</a>
                            <a href="https://tiktok.com" target="_blank" rel="noopener" class="social-pill tt"><i class="fab fa-tiktok"></i> TikTok</a>
                        </div>
                    </div>
                </div>

                <!-- CAM KẾT / TIỆN ÍCH -->
                <div class="contact-card perks-card">
                    <h3>Đặc quyền tại Lumi Dress</h3>
                    <ul class="contact-perks-list">
                        <li>
                            <i class="fas fa-wand-magic-sparkles"></i>
                            <div>
                                <strong>Thử đồ hoàn toàn miễn phí</strong>
                                <span>Phòng thử đồ riêng tư, trang bị gương lớn chuẩn ánh sáng tự nhiên.</span>
                            </div>
                        </li>
                        <li>
                            <i class="fas fa-scissors"></i>
                            <div>
                                <strong>Chỉnh sửa vừa vặn form dáng</strong>
                                <span>Hỗ trợ bóp/nới eo nhẹ nhàng theo số đo của từng khách hàng.</span>
                            </div>
                        </li>
                        <li>
                            <i class="fas fa-sparkles"></i>
                            <div>
                                <strong>Giặt hấp sấy kháng khuẩn</strong>
                                <span>100% trang phục được hấp tiệt trùng và là phẳng tinh tươm trước khi giao.</span>
                            </div>
                        </li>
                    </ul>
                </div>

            </div>

        </div><!-- /contact-grid -->

        <!-- BẢN ĐỒ VỊ TRÍ GOOGLE MAPS -->
        <div class="contact-map-section">
            <div class="map-card">
                <div class="map-header">
                    <div>
                        <h3><i class="fas fa-map-location-dot"></i> Vị trí Showroom Lumi Dress trên bản đồ</h3>
                        <p>12 Chùa Bộc, Kim Liên, Đống Đa, Hà Nội (Có chỗ đỗ ô tô và xe máy thuận tiện)</p>
                    </div>
                    <a href="https://maps.google.com/?q=12+Chùa+Bộc,+Kim+Liên,+Đống+Đa,+Hà+Nội" target="_blank" rel="noopener" class="btn-directions">
                        <i class="fas fa-arrow-up-right-from-square"></i> Chỉ đường Google Maps
                    </a>
                </div>
                <div class="map-frame-wrapper">
                    <iframe 
                        title="Bản đồ Lumi Dress 12 Chùa Bộc Hà Nội"
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3724.5935391696236!2d105.82855167587023!3d21.008923388457783!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3135ac8040d7c711%3A0xc6fb90fb8c5828aa!2zMTIgUC4gQ2jDuWEgQuG7mWMsIEtltj0gTGnDqm4sIMSQ4buRbmcgxJBhLCBIw6AgTuG7mWksIFZp4buHdCBOYW0!5e0!3m2!1svi!2s!4v1700000000000!5m2!1svi!2s" 
                        width="100%" 
                        height="420" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
        </div>

    </div><!-- /container -->
</main>

<?php include 'footer.php'; ?>
