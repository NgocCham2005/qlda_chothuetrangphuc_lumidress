
<?php
require_once '../customer_auth.php';

$error = '';
$success = false;
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_customer_csrf()) {
        $error = 'Yêu cầu không hợp lệ.';
    } elseif (($_POST['action'] ?? '') === 'verify') {
        $phone = trim($_POST['phone'] ?? '');
        $identity = trim($_POST['identity_number'] ?? '');

        $stmt = $conn->prepare(
            "SELECT id FROM customers
             WHERE phone = ?
             AND identity_number = ?
             AND status = 1
             LIMIT 1"
        );

        $stmt->bind_param("ss", $phone, $identity);
        $stmt->execute();

        $customer = $stmt->get_result()->fetch_assoc();

        if ($customer) {
            $_SESSION['reset_customer_id'] = (int)$customer['id'];
            $_SESSION['reset_verified_at'] = time();
            $step = 2;
        } else {
            $error = 'Số điện thoại hoặc CCCD không chính xác.';
        }
    } elseif (($_POST['action'] ?? '') === 'reset') {
        $customerId = (int)($_SESSION['reset_customer_id'] ?? 0);
        $verifiedAt = (int)($_SESSION['reset_verified_at'] ?? 0);

        if (!$customerId || time() - $verifiedAt > 300) {
            unset(
                $_SESSION['reset_customer_id'],
                $_SESSION['reset_verified_at']
            );
            $error = 'Phiên xác minh đã hết hạn. Vui lòng thực hiện lại.';
        } else {
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (strlen($password) < 8) {
                $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
                $step = 2;
            } elseif ($password !== $confirm) {
                $error = 'Mật khẩu xác nhận không khớp.';
                $step = 2;
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    "UPDATE customers
                     SET password = ?
                     WHERE id = ? AND status = 1"
                );

                $stmt->bind_param("si", $hash, $customerId);

                if ($stmt->execute() && $stmt->affected_rows === 1) {
                    unset(
                        $_SESSION['reset_customer_id'],
                        $_SESSION['reset_verified_at']
                    );
                    $success = true;
                } else {
                    $error = 'Không thể cập nhật mật khẩu.';
                    $step = 2;
                }
            }
        }
    }
}

include 'header.php';
?>

<div class="account-container">
    <div class="account-card">
        <h2>Quên mật khẩu</h2>

        <?php if ($error): ?>
            <div class="account-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>

            <div class="account-message">
                Đặt lại mật khẩu thành công!
                <a href="login.php">Đăng nhập ngay</a>
            </div>

        <?php elseif ($step === 1): ?>

            <p>
                Nhập số điện thoại và CCCD đã đăng ký
                để xác minh tài khoản.
            </p>

            <form method="POST">
                <?= customer_csrf_field() ?>

                <input type="hidden" name="action" value="verify">

                <label>Số điện thoại</label>
                <input type="tel" name="phone" required>

                <label>Số CCCD</label>
                <input type="text" name="identity_number" required>

                <button class="account-button" type="submit">
                    Xác minh tài khoản
                </button>
            </form>

        <?php else: ?>

            <p>Nhập mật khẩu mới cho tài khoản của bạn.</p>

            <form method="POST">
                <?= customer_csrf_field() ?>

                <input type="hidden" name="action" value="reset">

                <label>Mật khẩu mới</label>
                <input type="password" name="password" required>

                <label>Xác nhận mật khẩu</label>
                <input type="password" name="confirm_password" required>

                <button class="account-button" type="submit">
                    Đặt lại mật khẩu
                </button>
            </form>

        <?php endif; ?>

        <p class="account-link">
            <a href="login.php">Quay lại đăng nhập</a>
        </p>
    </div>
</div>

<?php include 'footer.php'; ?>
