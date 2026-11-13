
<?php
require_once '../customer_auth.php';

if (empty($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerId = (int)$_SESSION['customer_id'];
$error = '';
$success = '';

if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

// Lấy thông tin khách hàng
$stmt = $conn->prepare(
    "SELECT id, fullname, phone, email, identity_number,
            address, created_at
     FROM customers
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $customerId);
$stmt->execute();

$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Xử lý cập nhật hồ sơ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['profile_csrf'], $csrf)) {
        $error = 'Yêu cầu không hợp lệ.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $fullname = trim($_POST['fullname'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $identity = trim($_POST['identity_number'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($fullname === '' || $phone === '') {
                $error = 'Họ tên và số điện thoại không được để trống.';
            } elseif (!preg_match('/^[\p{L}\s]+$/u', $fullname)) {
                $error = 'Họ tên không hợp lệ.';
            } elseif (!preg_match('/^0[0-9]{9,10}$/', $phone)) {
                $error = 'Số điện thoại phải có 10-11 chữ số và bắt đầu bằng 0.';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email không đúng định dạng.';
            } elseif (strlen($fullname) > 100 || strlen($phone) > 11 ||
                      strlen($email) > 100 || strlen($identity) > 20 ||
                      strlen($address) > 255) {
                $error = 'Thông tin vượt quá độ dài cho phép.';
            } else {
                $emailDb = $email !== '' ? $email : null;
                $identityDb = $identity !== '' ? $identity : null;

                $check = $conn->prepare(
                    "SELECT id FROM customers
                     WHERE id <> ?
                     AND (
                        phone = ?
                        OR (email IS NOT NULL AND email = ?)
                        OR (identity_number IS NOT NULL AND identity_number = ?)
                     )
                     LIMIT 1"
                );

                $check->bind_param(
                    "isss",
                    $customerId,
                    $phone,
                    $emailDb,
                    $identityDb
                );

                $check->execute();

                if ($check->get_result()->num_rows > 0) {
                    $error = 'Số điện thoại, email hoặc CCCD đã được tài khoản khác sử dụng.';
                } else {
                    $update = $conn->prepare(
                        "UPDATE customers
                         SET fullname = ?, phone = ?, email = ?,
                             identity_number = ?, address = ?
                         WHERE id = ?"
                    );

                    $update->bind_param(
                        "sssssi",
                        $fullname,
                        $phone,
                        $emailDb,
                        $identityDb,
                        $address,
                        $customerId
                    );

                    if ($update->execute()) {
                        $_SESSION['customer_name'] = $fullname;
                        $success = 'Cập nhật thông tin cá nhân thành công!';

                        $stmt = $conn->prepare(
                            "SELECT id, fullname, phone, email,
                                    identity_number, address, created_at
                             FROM customers WHERE id = ?"
                        );

                        $stmt->bind_param("i", $customerId);
                        $stmt->execute();
                        $customer = $stmt->get_result()->fetch_assoc();
                    } else {
                        $error = 'Không thể cập nhật thông tin.';
                    }
                }
            }
        }

        // Đổi mật khẩu
        if ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $error = 'Vui lòng nhập đầy đủ thông tin mật khẩu.';
            }  elseif (
                strlen($newPassword) < 8 ||
                !preg_match('/[A-Z]/', $newPassword) ||
                !preg_match('/[a-z]/', $newPassword) ||
                !preg_match('/[0-9]/', $newPassword) ||
                !preg_match('/[^A-Za-z0-9]/', $newPassword)
            ) {
                $error = 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, chữ số và ký tự đặc biệt.';
                        } elseif ($newPassword !== $confirmPassword) {
                $error = 'Mật khẩu xác nhận không khớp.';
            } else {
                $checkPassword = $conn->prepare(
                    "SELECT password FROM customers WHERE id = ?"
                );

                $checkPassword->bind_param("i", $customerId);
                $checkPassword->execute();

                $passwordRow = $checkPassword->get_result()->fetch_assoc();
                $storedPassword = $passwordRow['password'];

                $valid = password_verify(
                    $currentPassword,
                    $storedPassword
                ) || hash_equals($storedPassword, $currentPassword);

                if (!$valid) {
                    $error = 'Mật khẩu hiện tại không chính xác.';
                } else {
                    $newHash = password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                    $updatePassword = $conn->prepare(
                        "UPDATE customers SET password = ? WHERE id = ?"
                    );

                    $updatePassword->bind_param(
                        "si",
                        $newHash,
                        $customerId
                    );

                    if ($updatePassword->execute()) {
                        $success = 'Đổi mật khẩu thành công!';
                    } else {
                        $error = 'Không thể đổi mật khẩu.';
                    }
                }
            }
        }
    }
}

// Thống kê lịch sử thuê
$statsStmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'returned') AS returned,
        SUM(status IN ('reserved', 'picked_up', 'overdue')) AS ongoing
     FROM rentals
     WHERE customer_id = ?"
);

$statsStmt->bind_param("i", $customerId);
$statsStmt->execute();
$rentalStats = $statsStmt->get_result()->fetch_assoc();

// Lấy 10 đơn thuê gần nhất
$rentalStmt = $conn->prepare(
    "SELECT *
     FROM rentals
     WHERE customer_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 10"
);

$rentalStmt->bind_param("i", $customerId);
$rentalStmt->execute();
$rentals = $rentalStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Lấy chi tiết trang phục của từng đơn
$rentalItems = [];

if (!empty($rentals)) {
    $itemStmt = $conn->prepare(
        "SELECT ri.*, c.name, c.image
         FROM rental_items ri
         LEFT JOIN costumes c ON c.id = ri.costume_id
         WHERE ri.rental_id = ?
         ORDER BY ri.id"
    );

    foreach ($rentals as $rental) {
        $rentalId = (int)$rental['id'];

        $itemStmt->bind_param("i", $rentalId);
        $itemStmt->execute();

        $rentalItems[$rentalId] = $itemStmt
            ->get_result()
            ->fetch_all(MYSQLI_ASSOC);
    }
}

function profile_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rental_status($status) {
    $statuses = [
        'reserved' => ['Chờ nhận trang phục', 'warning'],
        'picked_up' => ['Đang thuê', 'info'],
        'returned' => ['Đã hoàn trả', 'success'],
        'overdue' => ['Quá hạn trả', 'danger'],
        'cancelled' => ['Đã hủy', 'secondary']
    ];

    return $statuses[$status] ?? ['Chưa xác định', 'secondary'];
}

include 'header.php';
?>

<div class="profile-page">
    <div class="container">

        <!-- PROFILE HEADER -->
        <div class="profile-welcome">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>

            <div class="profile-welcome-info">
                <h2><?= profile_h($customer['fullname']) ?></h2>
                <p>
                    <i class="fas fa-user-check"></i>
                    Thành viên Lumi Dress
                </p>
            </div>

            <div class="profile-active">
                <i class="fas fa-circle"></i> Đang hoạt động
            </div>
        </div>

        <?php if ($error): ?>
            <div class="account-alert account-alert-error">
                <?= profile_h($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="account-alert account-alert-success">
                <?= profile_h($success) ?>
            </div>
        <?php endif; ?>

        <div class="profile-layout">

            <!-- SIDEBAR -->
            <aside class="profile-sidebar">
                <a href="#personal-info" class="profile-menu active">
                    <i class="fas fa-user"></i>
                    Hồ sơ cá nhân
                </a>

                <a href="#change-password" class="profile-menu">
                    <i class="fas fa-lock"></i>
                    Đổi mật khẩu
                </a>

                <a href="#rental-history" class="profile-menu">
                    <i class="fas fa-history"></i>
                    Lịch sử thuê
                </a>

                <a href="logout.php" class="profile-menu profile-menu-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Đăng xuất
                </a>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="profile-content">

                <!-- THÔNG TIN CÁ NHÂN -->
                <section class="profile-section" id="personal-info">

                    <div class="profile-section-heading">
                        <div>
                            <h2>Thông tin cá nhân</h2>
                            <p>Quản lý thông tin tài khoản của bạn</p>
                        </div>

                        <i class="fas fa-user-edit"></i>
                    </div>

                    <form method="POST" class="profile-form">

                        <input type="hidden" name="csrf_token"
                               value="<?= profile_h($_SESSION['profile_csrf']) ?>">

                        <input type="hidden" name="action"
                               value="update_profile">

                        <div class="profile-form-grid">

                            <div class="account-form-group">
                                <label>Họ và tên *</label>
                                <input type="text" name="fullname"
                                    value="<?= profile_h($customer['fullname']) ?>"
                                    maxlength="100" required>
                            </div>

                            <div class="account-form-group">
                                <label>Số điện thoại *</label>
                                <input type="tel" name="phone"
                                    value="<?= profile_h($customer['phone']) ?>"
                                    maxlength="11" required>
                            </div>

                            <div class="account-form-group">
                                <label>Email</label>
                                <input type="email" name="email"
                                    value="<?= profile_h($customer['email']) ?>"
                                    maxlength="100">
                            </div>

                            <div class="account-form-group">
                                <label>Số CCCD</label>
                                <input type="text" name="identity_number"
                                    value="<?= profile_h($customer['identity_number']) ?>"
                                    maxlength="20">
                            </div>

                            <div class="account-form-group profile-full-width">
                                <label>Địa chỉ</label>
                                <textarea name="address" rows="3"
                                    maxlength="255"
                                    placeholder="Nhập địa chỉ của bạn"><?= profile_h($customer['address']) ?></textarea>
                            </div>

                        </div>

                        <div class="profile-form-footer">
                            <span>
                                <i class="fas fa-calendar-alt"></i>
                                Thành viên từ:
                                <?= date('d/m/Y', strtotime($customer['created_at'])) ?>
                            </span>

                            <button type="submit" class="account-submit profile-save">
                                <i class="fas fa-save"></i>
                                Lưu thay đổi
                            </button>
                        </div>

                    </form>
                </section>

                <!-- ĐỔI MẬT KHẨU -->
                <section class="profile-section" id="change-password">

                    <div class="profile-section-heading">
                        <div>
                            <h2>Bảo mật tài khoản</h2>
                            <p>Thay đổi mật khẩu đăng nhập</p>
                        </div>

                        <i class="fas fa-shield-alt"></i>
                    </div>

                    <form method="POST" class="profile-form">

                        <input type="hidden" name="csrf_token"
                               value="<?= profile_h($_SESSION['profile_csrf']) ?>">

                        <input type="hidden" name="action"
                               value="change_password">

                        <div class="profile-form-grid">

                            <div class="account-form-group profile-full-width">
                                <label>Mật khẩu hiện tại</label>
                                <input type="password" name="current_password"
                                    autocomplete="current-password" required>
                            </div>
                            <div class="account-form-group">
                                
                                <label>Mật khẩu mới</label>

                                <input
                                    type="password"
                                    name="new_password"
                                    id="new_password"
                                    minlength="8"
                                    pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                                    title="Mật khẩu cần ít nhất 8 ký tự, gồm chữ hoa, chữ thường, chữ số và ký tự đặc biệt."
                                    autocomplete="new-password"
                                    required
                                >

                                <details class="password-guide">
                                    <summary>
                                        <i class="fas fa-info-circle"></i>
                                        Xem yêu cầu mật khẩu
                                    </summary>

                                    <div class="password-guide-content">
                                        <p>Mật khẩu cần đáp ứng đầy đủ các điều kiện:</p>
                                        <ul>
                                            <li>Ít nhất 8 ký tự.</li>
                                            <li>Có chữ cái viết hoa (A–Z).</li>
                                            <li>Có chữ cái viết thường (a–z).</li>
                                            <li>Có ít nhất một chữ số (0–9).</li>
                                            <li>Có ít nhất một ký tự đặc biệt (!@#$%^&*).</li>
                                        </ul>
                                    </div>
                                </details>
                            </div>
                            <div class="account-form-group">
                                <label>Xác nhận mật khẩu mới</label>
                                <input type="password" name="confirm_password"
                                    minlength="8" autocomplete="new-password" required>
                            </div>

                        </div>

                        <button type="submit" class="account-submit profile-save">
                            <i class="fas fa-key"></i>
                            Đổi mật khẩu
                        </button>

                    </form>
                </section>

                <!-- LỊCH SỬ THUÊ -->
                <section class="profile-section" id="rental-history">

                    <div class="profile-section-heading">
                        <div>
                            <h2>Lịch sử thuê trang phục</h2>
                            <p>Theo dõi các đơn thuê của bạn</p>
                        </div>

                        <i class="fas fa-history"></i>
                    </div>

                    <div class="rental-stat-grid">

                        <div class="rental-stat-card">
                            <i class="fas fa-file-invoice"></i>
                            <div>
                                <strong><?= (int)($rentalStats['total'] ?? 0) ?></strong>
                                <span>Tổng đơn thuê</span>
                            </div>
                        </div>

                        <div class="rental-stat-card">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong><?= (int)($rentalStats['returned'] ?? 0) ?></strong>
                                <span>Đã hoàn trả</span>
                            </div>
                        </div>

                        <div class="rental-stat-card">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong><?= (int)($rentalStats['ongoing'] ?? 0) ?></strong>
                                <span>Đơn đang xử lý</span>
                            </div>
                        </div>

                    </div>

                    <div class="rental-list">

                        <?php if (empty($rentals)): ?>

                            <div class="rental-empty">
                                <i class="fas fa-box-open"></i>
                                <h3>Chưa có lịch sử thuê</h3>
                                <p>Các đơn thuê của bạn sẽ xuất hiện tại đây.</p>
                                <a href="products.php" class="account-submit">
                                    Khám phá trang phục
                                </a>
                            </div>

                        <?php else: ?>

                            <?php foreach ($rentals as $rental): ?>

                                <?php
                                [$statusText, $statusClass] =
                                    rental_status($rental['status']);
                                ?>

                                <article class="rental-card">

                                    <div class="rental-card-header">
                                        <div>
                                            <strong>
                                                Mã phiếu thuê #<?= (int)$rental['id'] ?>
                                            </strong>

                                            <span>
                                                Ngày tạo:
                                                <?= date('d/m/Y', strtotime($rental['created_at'])) ?>
                                            </span>
                                        </div>

                                        <span class="rental-status <?= profile_h($statusClass) ?>">
                                            <?= profile_h($statusText) ?>
                                        </span>
                                    </div>

                                    <div class="rental-products">

                                        <?php foreach ($rentalItems[$rental['id']] ?? [] as $item): ?>

                                            <div class="rental-product">

                                                <div class="rental-product-image">

                                                    <?php if (!empty($item['image'])): ?>
                                                        <img
                                                            src="../uploads/<?= rawurlencode(basename($item['image'])) ?>"
                                                            alt="<?= profile_h($item['name']) ?>"
                                                        >
                                                    <?php else: ?>
                                                        <i class="fas fa-shirt"></i>
                                                    <?php endif; ?>

                                                </div>

                                                <div class="rental-product-info">
                                                    <strong>
                                                        <?= profile_h($item['name'] ?? 'Sản phẩm không còn tồn tại') ?>
                                                    </strong>

                                                    <span>
                                                        Số lượng: <?= (int)$item['quantity'] ?>
                                                        · Size: <?= profile_h($item['selected_size'] ?? '—') ?>
                                                    </span>

                                                    <span>
                                                        <?= number_format((float)$item['unit_price'], 0, ',', '.') ?>đ
                                                        / sản phẩm
                                                    </span>
                                                </div>

                                                <strong class="rental-product-price">
                                                    <?= number_format((float)$item['subtotal'], 0, ',', '.') ?>đ
                                                </strong>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>

                                    <div class="rental-card-footer">

                                        <div class="rental-dates">
                                            <span>
                                                <i class="fas fa-calendar-check"></i>
                                                Nhận:
                                                <?= date('d/m/Y', strtotime($rental['receive_date'])) ?>
                                            </span>

                                            <span>
                                                <i class="fas fa-calendar-times"></i>
                                                Trả:
                                                <?= date('d/m/Y', strtotime($rental['return_date'])) ?>
                                            </span>
                                        </div>

                                        <div class="rental-total">
                                            <span>Tổng thanh toán</span>
                                            <strong>
                                                <?= number_format((float)$rental['final_amount'], 0, ',', '.') ?>đ
                                            </strong>
                                        </div>

                                    </div>

                                    <div class="rental-payment">
                                        Trạng thái thanh toán:
                                        <strong>
                                            <?php
                                            $paymentLabels = [
                                                'unpaid' => 'Chưa thanh toán',
                                                'partial' => 'Thanh toán một phần',
                                                'paid' => 'Đã thanh toán'
                                            ];
                                            echo profile_h(
                                                $paymentLabels[$rental['payment_status']] ?? 'Chưa xác định'
                                            );
                                            ?>
                                        </strong>
                                    </div>

                                </article>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>
                </section>

            </main>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.profile-menu[href^="#"]').forEach(link => {
    link.addEventListener('click', function () {
        document.querySelectorAll('.profile-menu').forEach(item => {
            item.classList.remove('active');
        });

        this.classList.add('active');
    });
});
</script>

<?php include 'footer.php'; ?>
