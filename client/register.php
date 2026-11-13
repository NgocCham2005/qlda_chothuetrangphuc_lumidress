
<?php
require_once '../customer_auth.php';

if (customer_logged_in()) {
    customer_redirect('profile.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_customer_csrf()) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $fullname = trim($_POST['fullname'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $identity = trim($_POST['identity_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($fullname === '' || $phone === '' || $password === '') {
            $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
        } elseif (!preg_match('/^[0-9]{10,11}$/', $phone)) {
            $error = 'Số điện thoại không hợp lệ.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email không hợp lệ.';
        } elseif ($identity !== '' && !preg_match('/^[0-9]{9,12}$/', $identity)) {
            $error = 'Số CCCD không hợp lệ.';
        } elseif (
            strlen($password) < 8 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $error = 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, chữ số và ký tự đặc biệt.';
        } elseif ($password !== $confirm) {
            $error = 'Mật khẩu xác nhận không khớp.';
        } else {
            $check = $conn->prepare(
                "SELECT id FROM customers
                 WHERE phone = ?
                    OR (email = ? AND ? <> '')
                    OR (identity_number = ? AND ? <> '')
                 LIMIT 1"
            );

            $check->bind_param(
                "sssss",
                $phone, $email, $email, $identity, $identity
            );
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $error = 'Số điện thoại, email hoặc CCCD đã được sử dụng.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $emailValue = $email !== '' ? $email : null;
                $identityValue = $identity !== '' ? $identity : null;
                $addressValue = $address !== '' ? $address : null;

                $stmt = $conn->prepare(
                    "INSERT INTO customers
                    (fullname, phone, email, identity_number, address, password, status)
                    VALUES (?, ?, ?, ?, ?, ?, 1)"
                );

                $stmt->bind_param(
                    "ssssss",
                    $fullname,
                    $phone,
                    $emailValue,
                    $identityValue,
                    $addressValue,
                    $hashedPassword
                );

                if ($stmt->execute()) {
                    $customerId = $conn->insert_id;

                    session_regenerate_id(true);
                    $_SESSION['customer_id'] = $customerId;
                    $_SESSION['customer_name'] = $fullname;
                    unset($_SESSION['customer_csrf']);

                    customer_redirect('index.php');
                } else {
                    $error = 'Đăng ký thất bại. Vui lòng thử lại.';
                }
            }
        }
    }
}

include 'header.php';
?>

<div class="account-container">
    <div class="account-card">
        <h2>Đăng ký tài khoản</h2>
        <p>Tạo tài khoản để trải nghiệm dịch vụ tại Lumi Dress.</p>

        <?php if ($error): ?>
            <div class="account-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= customer_csrf_field() ?>

            <label>Họ và tên *</label>
            <input type="text" name="fullname" maxlength="100"
                   value="<?= e($_POST['fullname'] ?? '') ?>" required>

            <label>Số điện thoại *</label>
            <input type="tel" name="phone" maxlength="11"
                   value="<?= e($_POST['phone'] ?? '') ?>" required>

            <label>Email</label>
            <input type="email" name="email" maxlength="100"
                   value="<?= e($_POST['email'] ?? '') ?>">

            <label>Số CCCD</label>
            <input type="text" name="identity_number" maxlength="20"
                   value="<?= e($_POST['identity_number'] ?? '') ?>">

            <label>Địa chỉ</label>
            <input type="text" name="address" maxlength="255"
                   value="<?= e($_POST['address'] ?? '') ?>">

            <label>Mật khẩu *</label>
            <input
                type="password"
                name="password"
                id="register_password"
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
                    <p>Mật khẩu phải đáp ứng đầy đủ các điều kiện:</p>
                    <ul>
                        <li>Ít nhất 8 ký tự.</li>
                        <li>Có chữ cái viết hoa (A–Z).</li>
                        <li>Có chữ cái viết thường (a–z).</li>
                        <li>Có ít nhất một chữ số (0–9).</li>
                        <li>Có ít nhất một ký tự đặc biệt (!@#$%^&*).</li>
                    </ul>
                </div>
            </details>

            <label>Xác nhận mật khẩu *</label>
            <input
                type="password"
                name="confirm_password"
                id="register_confirm_password"
                autocomplete="new-password"
                required
            >
            <button type="submit" class="account-button">
                Đăng ký ngay
            </button>
        </form>

        <p class="account-link">
            Đã có tài khoản?
            <a href="login.php">Đăng nhập</a>
        </p>
    </div>
</div>

<?php include 'footer.php'; ?>
