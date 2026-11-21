<?php
require_once '../config.php';
require_once '../customer_auth.php';

$pageTitle = 'Chính sách & Điều khoản – Lumi Dress';
include 'header.php';
?>

<section class="policy-hero">
  <div class="container">
    <nav class="policy-breadcrumb" aria-label="Breadcrumb">
      <a href="index.php">Trang chủ</a>
      <i class="fas fa-chevron-right" aria-hidden="true"></i>
      <span>Chính sách &amp; Điều khoản</span>
    </nav>
    <span class="section-eyebrow">Lumi Dress</span>
    <h1>Chính sách &amp; Điều khoản</h1>
    <p>Thông tin rõ ràng giúp bạn thuê trang phục an tâm, thuận tiện và nhận đúng bộ trang phục phù hợp nhất.</p>
  </div>
</section>

<section class="policy-page">
  <div class="container policy-layout">
    <aside class="policy-sidebar" aria-label="Mục lục chính sách">
      <h2>Nội dung</h2>
      <nav>
        <a href="#rental">Chính sách thuê</a>
        <a href="#deposit">Chính sách đặt cọc</a>
        <a href="#return">Quy định trả đồ</a>
        <a href="#care">Bảo quản &amp; vệ sinh</a>
        <a href="#cancel">Hủy &amp; đổi lịch</a>
        <a href="#privacy">Bảo mật thông tin</a>
        <a href="#terms">Điều khoản sử dụng</a>
      </nav>
    </aside>

    <main class="policy-content">
      <article class="policy-section" id="rental">
        <div class="policy-section-title">
          <i class="fas fa-shirt" aria-hidden="true"></i>
          <h2>Chính sách thuê trang phục</h2>
        </div>
        <ul>
          <li>Khách hàng có thể đặt thuê trực tuyến hoặc trực tiếp tại cửa hàng Lumi Dress.</li>
          <li>Vui lòng đặt trước ít nhất 1 ngày để cửa hàng kiểm tra kích cỡ, tình trạng và khả dụng của trang phục.</li>
          <li>Khách hàng cần cung cấp họ tên, số điện thoại, CMND/CCCD và địa chỉ nhận đồ khi thuê tại cửa hàng hoặc giao tận nơi.</li>
          <li>Đơn thuê chỉ được xác nhận sau khi cửa hàng kiểm tra tình trạng sản phẩm và thanh toán tiền cọc.</li>
          <li>Khách hàng nên thử trang phục trước ngày nhận để kịp đổi kích cỡ hoặc chọn mẫu thay thế.</li>
        </ul>
      </article>

      <article class="policy-section" id="deposit">
        <div class="policy-section-title">
          <i class="fas fa-coins" aria-hidden="true"></i>
          <h2>Chính sách đặt cọc</h2>
        </div>
        <ul>
          <li>Mức đặt cọc tùy thuộc loại trang phục, chất lượng và giá trị từng bộ.</li>
          <li>Tiền cọc có thể thanh toán trực tuyến hoặc tại cửa hàng Lumi Dress.</li>
          <li>Tiền cọc được hoàn lại sau khi trả đúng trang phục, đúng hạn và không phát sinh lỗi hư hỏng.</li>
          <li>Thời gian hoàn cọc có thể dao động tùy hình thức thanh toán và tình trạng kiểm tra sản phẩm.</li>
          <li>Đối với đồ giao tận nơi, khách hàng cần xuất trình giấy tờ đã cung cấp khi nhận đồ.</li>
        </ul>
        <div class="policy-note">
          <i class="fas fa-circle-info" aria-hidden="true"></i>
          <span>Mức cọc cụ thể sẽ hiển thị trong giỏ thuê và trang thanh toán trước khi bạn xác nhận đơn.</span>
        </div>
      </article>

      <article class="policy-section" id="return">
        <div class="policy-section-title">
          <i class="fas fa-calendar-check" aria-hidden="true"></i>
          <h2>Quy định trả đồ</h2>
        </div>
        <ul>
          <li>Khách hàng vui lòng trả trang phục đúng thời gian ghi trên phiếu thuê.</li>
          <li>Trả trễ có thể phát sinh phí tính theo ngày và ảnh hưởng đến đơn thuê tiếp theo.</li>
          <li>Trả đồ kèm phụ kiện, túi đựng và hóa đơn đi kèm nếu có.</li>
          <li>Trang phục cần được giữ nguyên tình trạng ban đầu; không giặt, cắt, tháo hoặc sửa đồ.</li>
          <li>Với giao hàng tận nơi, vui lòng liên hệ cửa hàng trước để thống nhất địa điểm và thời gian nhận lại đồ.</li>
        </ul>
      </article>

      <article class="policy-section" id="care">
        <div class="policy-section-title">
          <i class="fas fa-sparkles" aria-hidden="true"></i>
          <h2>Bảo quản &amp; vệ sinh trang phục</h2>
        </div>
        <ul>
          <li>Không giặt hoặc giặt khô trang phục trong thời gian thuê trừ khi Lumi Dress hướng dẫn.</li>
          <li>Tránh tiếp xúc với nước hoa, mỹ phẩm, hóa chất, nguồn nhiệt và vật sắc nhọn.</li>
          <li>Nếu trang phục bị bẩn nhẹ, hãy thông báo cho cửa hàng thay vì tự vệ sinh bằng chất tẩy mạnh.</li>
          <li>Khách hàng chịu trách nhiệm theo mức độ hư hỏng khi làm rách, cháy, mất phụ kiện, ố màu hoặc bong trễ kết cấu trang phục.</li>
        </ul>
      </article>

      <article class="policy-section" id="cancel">
        <div class="policy-section-title">
          <i class="fas fa-rotate" aria-hidden="true"></i>
          <h2>Hủy &amp; đổi lịch thuê</h2>
        </div>
        <ul>
          <li>Yêu cầu hủy hoặc đổi lịch nên được thông báo sớm nhất có thể qua hotline hoặc trang liên hệ.</li>
          <li>Đơn chưa xác nhận có thể hủy mà không phát sinh chi phí.</li>
          <li>Đơn đã xác nhận có thể áp dụng phí hủy tùy thời điểm và tình trạng giữ lịch của trang phục.</li>
          <li>Lumi Dress có quyền đề xuất sản phẩm thay thế nếu trang phục phát sinh sự cố bất khả kháng.</li>
        </ul>
      </article>

      <article class="policy-section" id="privacy">
        <div class="policy-section-title">
          <i class="fas fa-shield-halved" aria-hidden="true"></i>
          <h2>Bảo mật thông tin khách hàng</h2>
        </div>
        <ul>
          <li>Lumi Dress chỉ thu thập thông tin cần thiết để xử lý đặt thuê, thanh toán và hỗ trợ khách hàng.</li>
          <li>Thông tin cá nhân không được chia sẻ cho bên thứ ba vì mục đích thương mại trái phép.</li>
          <li>Khách hàng có thể yêu cầu xem, cập nhật hoặc xóa dữ liệu cá nhân bằng cách liên hệ qua hotline hoặc email.</li>
          <li>Nền tảng có thể sử dụng cookie để duy trì phiên đăng nhập và cải thiện trải nghiệm sử dụng.</li>
        </ul>
      </article>

      <article class="policy-section" id="terms">
        <div class="policy-section-title">
          <i class="fas fa-file-contract" aria-hidden="true"></i>
          <h2>Điều khoản sử dụng</h2>
        </div>
        <ul>
          <li>Khách hàng tự chịu trách nhiệm cung cấp thông tin chính xác và giữ an toàn cho tài khoản của mình.</li>
          <li>Không sử dụng website cho mục đích gian lận, phá hoại hoặc gây xung đột lợi ích với dịch vụ.</li>
          <li>Hình ảnh trang phục mang tính minh họa; màu sắc thực tế có thể chênh lệch nhỏ tùy thiết bị và điều kiện ánh sáng.</li>
          <li>Lumi Dress có thể cập nhật chính sách để phù hợp quy trình vận hành và quy định pháp luật hiện hành.</li>
        </ul>
      </article>

      <div class="policy-contact">
        <div>
          <span>Cần hỗ trợ thêm?</span>
          <h2>Liên hệ Lumi Dress</h2>
        </div>
        <a href="contact.php" class="btn-rent-now">
          <i class="fas fa-phone" aria-hidden="true"></i>
          Liên hệ &amp; đặt lịch
        </a>
      </div>
    </main>
  </div>
</section>

<?php include 'footer.php'; ?>
