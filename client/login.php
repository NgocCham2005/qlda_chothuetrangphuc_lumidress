<?php
require_once '../customer_auth.php';

$error = '';

if (!empty($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['login_csrf'], $csrf)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($identifier === '' || $password === '') {
            $error = 'Vui lòng nhập đầy đủ thông tin.';
        } else {
            $stmt = $conn->prepare(
                "SELECT id, fullname, phone, email, password, status
                 FROM customers
                 WHERE phone = ? OR email = ?
                 LIMIT 1"
            );

            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();

            $customer = $stmt->get_result()->fetch_assoc();

            if (!$customer || (int)$customer['status'] !== 1) {
                $error = 'Thông tin đăng nhập không chính xác hoặc tài khoản đã bị khóa.';
            } else {
                $storedPassword = $customer['password'];

                $validPassword = password_verify(
                    $password,
                    $storedPassword
                );

                // Hỗ trợ chuyển mật khẩu cũ dạng văn bản sang password_hash
                if (!$validPassword && hash_equals($storedPassword, $password)) {
                    $newHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $update = $conn->prepare(
                        "UPDATE customers SET password = ? WHERE id = ?"
                    );

                    $update->bind_param(
                        "si",
                        $newHash,
                        $customer['id']
                    );

                    $update->execute();
                    $validPassword = true;
                }

                if ($validPassword) {
                    session_regenerate_id(true);

                    $_SESSION['customer_id'] = (int)$customer['id'];
                    $_SESSION['customer_name'] = $customer['fullname'];

                    unset($_SESSION['login_csrf']);

                    header('Location: index.php');
                    exit;
                }

                $error = 'Thông tin đăng nhập không chính xác hoặc tài khoản đã bị khóa.';
            }
        }
    }
}

include 'header.php';
?>

<div class="account-page">
    <div class="account-card">
        <div class="account-logo">
            <h1>Lumi Dress</h1>
            <span>CHO THUÊ TRANG PHỤC</span>
        </div>

        <h2>Đăng nhập</h2>

        <p class="account-description">
            Chào mừng bạn quay trở lại Lumi Dress!
        </p>

        <?php if ($error): ?>
            <div class="account-alert account-alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['login_csrf']) ?>"
            >

            <div class="account-form-group">
                <label>Số điện thoại hoặc Email</label>

                <input
                    type="text"
                    name="identifier"
                    placeholder="Nhập số điện thoại hoặc email"
                    value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="account-form-group">
                <label>Mật khẩu</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Nhập mật khẩu"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="account-forgot">
                <a href="forgot_password.php">Quên mật khẩu?</a>
            </div>

            <button type="submit" class="account-submit">
                <i class="fas fa-sign-in-alt"></i>
                Đăng nhập
            </button>
        </form>

        <div class="account-bottom">
            Chưa có tài khoản?
            <a href="register.php">Đăng ký ngay</a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
