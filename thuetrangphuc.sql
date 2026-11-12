-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 10, 2026 lúc 06:07 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `thuetrangphuc`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'LOGIN', 'Quản trị viên đăng nhập hệ thống.', '127.0.0.1', '2026-11-10 23:38:35'),
(2, 1, 'CREATE', 'Thêm danh mục Váy cưới.', '127.0.0.1', '2026-11-10 23:38:35'),
(3, 2, 'CREATE_RENTAL', 'Tạo phiếu thuê cho khách Trần Hoàng Nam.', '192.168.1.10', '2026-11-10 23:38:35'),
(4, 2, 'PAYMENT', 'Ghi nhận thanh toán tiền thuê.', '192.168.1.10', '2026-11-10 23:38:35'),
(5, 3, 'UPDATE_RENTAL', 'Cập nhật tình trạng phiếu thuê quá hạn.', '192.168.1.12', '2026-11-10 23:38:35'),
(6, 1, 'UPDATE_COSTUME', 'Cập nhật tình trạng áo dài trắng đính ngọc trai.', '127.0.0.1', '2026-11-10 23:38:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `admins`
--

INSERT INTO `admins` (`id`, `fullname`, `username`, `password`, `status`, `created_at`) VALUES
(1, 'Quản trị viên', 'admin', '$2a$07$asxx54ahjppf45sd87a5auFL5K1.Cmt9ZheoVVuudOi5BCi10qWly', 1, '2026-11-10 23:38:35'),
(2, 'Nguyễn Thu Hà', 'thuhanguyen', '$2a$07$asxx54ahjppf45sd87a5auFL5K1.Cmt9ZheoVVuudOi5BCi10qWly', 1, '2026-11-10 23:38:35'),
(3, 'Trần Minh Đức', 'minhduc', '$2a$07$asxx54ahjppf45sd87a5auFL5K1.Cmt9ZheoVVuudOi5BCi10qWly', 1, '2026-11-10 23:38:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `admin_roles`
--

CREATE TABLE `admin_roles` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `admin_roles`
--

INSERT INTO `admin_roles` (`id`, `admin_id`, `role_id`) VALUES
(1, 1, 1),
(2, 2, 2),
(3, 3, 3);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Váy cưới', 'Váy công chúa, váy đuôi cá và váy cưới tối giản', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(2, 'Vest nam', 'Vest cưới, vest công sở và vest sự kiện', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(3, 'Áo dài', 'Áo dài cưới, áo dài truyền thống và áo dài cách tân', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(4, 'Váy dạ hội', 'Váy dự tiệc, váy phù dâu và váy biểu diễn', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(5, 'Trang phục biểu diễn', 'Trang phục văn nghệ và biểu diễn sân khấu', '2026-11-10 23:38:35', '2026-11-10 23:38:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `costumes`
--

CREATE TABLE `costumes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `rental_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` varchar(50) NOT NULL,
  `condition_note` varchar(500) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `costumes`
--

INSERT INTO `costumes` (`id`, `name`, `category_id`, `size`, `color`, `rental_price`, `deposit`, `quantity`, `status`, `condition_note`, `description`, `image`, `created_at`, `updated_at`) VALUES
(1, 'Váy cưới công chúa ren Pháp', 1, 'M', 'Trắng', 1200000.00, 5000000.00, 2, 'available', 'Mới 95%, đã vệ sinh', 'Váy cưới dáng công chúa, phần thân ren và tùng váy nhiều lớp.', 'sample-1.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(2, 'Vest nam xanh navy hai khuy', 2, 'L', 'Xanh navy', 450000.00, 2000000.00, 3, 'available', 'Tốt, đủ áo và quần', 'Vest nam dáng slim fit, phù hợp tiệc cưới và sự kiện.', 'sample-2.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(3, 'Áo dài đỏ thêu hoa sen', 3, 'M', 'Đỏ', 350000.00, 1500000.00, 4, 'available', 'Mới 90%', 'Áo dài truyền thống thêu hoa sen.', 'sample-3.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(4, 'Váy cưới đuôi cá trễ vai', 1, 'S', 'Trắng kem', 900000.00, 4000000.00, 2, 'available', 'Mới 92%', 'Váy cưới đuôi cá tôn dáng.', 'sample-4.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(5, 'Vest nam đen cổ điển', 2, 'XL', 'Đen', 380000.00, 1800000.00, 4, 'available', 'Tốt', 'Vest đen cổ điển, phù hợp sự kiện trang trọng.', 'sample-5.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(6, 'Áo dài trắng đính ngọc trai', 3, 'S', 'Trắng', 320000.00, 1200000.00, 3, 'maintenance', 'Đang thay khóa kéo', 'Áo dài trắng đính ngọc trai thủ công.', 'sample-6.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(7, 'Váy phù dâu pastel', 4, 'M', 'Hồng phấn', 280000.00, 1000000.00, 6, 'available', 'Tốt', 'Váy phù dâu dáng dài, chất liệu voan nhẹ.', 'sample-7.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(8, 'Áo dài nam cách tân', 3, 'L', 'Xanh rêu', 300000.00, 1200000.00, 4, 'available', 'Tốt', 'Áo dài nam cách tân, đi kèm quần đen.', 'sample-8.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(9, 'Váy dạ hội ánh kim', 4, 'M', 'Vàng champagne', 700000.00, 3000000.00, 2, 'available', 'Mới 90%', 'Váy dạ hội ánh kim.', 'sample-9.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(10, 'Váy dạ hội đỏ đô lệch vai', 4, 'L', 'Đỏ đô', 650000.00, 2500000.00, 2, 'available', 'Tốt', 'Váy dạ hội dáng dài, thiết kế lệch vai.', 'sample-10.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(11, 'Đầm biểu diễn trắng', 5, 'S', 'Trắng', 250000.00, 800000.00, 8, 'available', 'Tốt', 'Đầm biểu diễn phù hợp chương trình văn nghệ.', 'sample-11.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(12, 'Áo vest nữ be thanh lịch', 2, 'M', 'Be', 400000.00, 1500000.00, 3, 'retired', 'Thanh lý do cũ mẫu', 'Vest nữ dáng suông, phù hợp chụp ảnh.', 'sample-12.svg', '2026-11-10 23:38:35', '2026-11-10 23:38:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `phone` varchar(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `identity_number` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `customers`
--

INSERT INTO `customers` (`id`, `fullname`, `phone`, `email`, `identity_number`, `address`, `password`, `status`, `created_at`) VALUES
(1, 'Nguyễn Minh Anh', '0987654321', 'minhanh@gmail.com', '038202001234', 'Thanh Xuân, Hà Nội', '123456', 1, '2026-11-10 23:38:35'),
(2, 'Trần Hoàng Nam', '0912345678', 'hoangnam@gmail.com', '036199008765', 'Cầu Giấy, Hà Nội', '123456', 1, '2026-11-10 23:38:35'),
(3, 'Lê Thu Hương', '0966888999', 'thuhuong@gmail.com', '034203002468', 'Đống Đa, Hà Nội', '123456', 1, '2026-11-10 23:38:35'),
(4, 'Phạm Quốc Bảo', '0905123456', 'quocbao@gmail.com', '037198001357', 'Hoàng Mai, Hà Nội', '123456', 1, '2026-11-10 23:38:35'),
(5, 'Đỗ Ngọc Lan', '0934567890', 'ngoclan@gmail.com', '001203004567', 'Ba Đình, Hà Nội', '123456', 1, '2026-11-10 23:38:35'),
(6, 'Vũ Đức Anh', '0977123456', 'ducanh@gmail.com', '001198007654', 'Long Biên, Hà Nội', '123456', 1, '2026-11-10 23:38:35'),
(7, 'Hoàng Khánh Linh', '0945678123', 'khanhlinh@gmail.com', '001204006789', 'Hà Đông, Hà Nội', '123456', 1, '2026-11-10 23:38:35'),
(8, 'Bùi Thành Đạt', '0923456789', 'thanhdat@gmail.com', '001199003210', 'Nam Từ Liêm, Hà Nội', '123456', 1, '2026-11-10 23:38:35'),
(9, 'Đặng Mai Phương', '0911222333', 'maiphuong@gmail.com', '001205009876', 'Tây Hồ, Hà Nội', '123456', 1, '2026-11-10 23:38:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `rental_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_code` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `payments`
--

INSERT INTO `payments` (`id`, `rental_id`, `amount`, `payment_type`, `payment_method`, `transaction_code`, `status`, `paid_at`, `refunded_at`, `note`) VALUES
(1, 1, 3000000.00, 'deposit', 'bank', 'TXN260001', 'completed', '2026-11-09 23:38:35', NULL, 'Khách chuyển khoản đặt cọc'),
(2, 2, 4000000.00, 'deposit', 'cash', 'TXN260002', 'completed', '2026-11-07 23:38:35', NULL, 'Thu tiền cọc tại cửa hàng'),
(3, 2, 1800000.00, 'rental', 'bank', 'TXN260003', 'completed', '2026-11-09 23:38:35', NULL, 'Thanh toán tiền thuê vest'),
(4, 3, 3000000.00, 'deposit', 'bank', 'TXN260004', 'completed', '2026-10-29 23:38:35', NULL, 'Thu tiền cọc'),
(5, 3, 2800000.00, 'rental', 'cash', 'TXN260005', 'completed', '2026-10-31 23:38:35', NULL, 'Thanh toán đủ tiền thuê'),
(6, 3, 3000000.00, 'refund', 'bank', 'TXN260006', 'refunded', '2026-11-03 23:38:35', '2026-11-03 23:38:35', 'Hoàn cọc sau khi kiểm tra trang phục'),
(7, 4, 2000000.00, 'deposit', 'cash', 'TXN260007', 'completed', '2026-11-03 23:38:35', NULL, 'Khách mới thanh toán một phần'),
(8, 6, 2400000.00, 'deposit', 'bank', 'TXN260008', 'completed', '2026-11-05 23:38:35', NULL, 'Thu tiền cọc'),
(9, 6, 900000.00, 'rental', 'bank', 'TXN260009', 'completed', '2026-11-07 23:38:35', NULL, 'Thanh toán tiền thuê'),
(10, 6, 2400000.00, 'refund', 'bank', 'TXN260010', 'refunded', '2026-11-09 23:38:35', '2026-11-09 23:38:35', 'Hoàn tiền cọc sau khi nhận lại đồ'),
(11, 7, 3000000.00, 'deposit', 'card', 'TXN260011', 'completed', '2026-11-06 23:38:35', NULL, 'Thu tiền cọc'),
(12, 7, 800000.00, 'rental', 'card', 'TXN260012', 'completed', '2026-11-08 23:38:35', NULL, 'Thanh toán phần tiền thuê còn lại sau ưu đãi'),
(13, 9, 1300000.00, 'rental', 'bank', 'TXN260013', 'completed', '2026-11-09 23:38:35', NULL, 'Thanh toán trước một phần tiền thuê');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`) VALUES
(1, 'dashboard.view', 'Xem trang tổng quan'),
(2, 'costumes.view', 'Xem trang phục'),
(3, 'costumes.create', 'Thêm trang phục'),
(4, 'costumes.update', 'Cập nhật trang phục'),
(5, 'costumes.delete', 'Xóa trang phục'),
(6, 'categories.manage', 'Quản lý danh mục'),
(7, 'customers.view', 'Xem khách hàng'),
(8, 'customers.manage', 'Quản lý khách hàng'),
(9, 'rentals.view', 'Xem phiếu thuê'),
(10, 'rentals.create', 'Tạo phiếu thuê'),
(11, 'rentals.update', 'Cập nhật phiếu thuê'),
(12, 'payments.manage', 'Quản lý thanh toán'),
(13, 'reviews.manage', 'Quản lý đánh giá'),
(14, 'reports.view', 'Xem báo cáo'),
(15, 'admins.manage', 'Quản lý nhân viên'),
(16, 'activity_logs.view', 'Xem nhật ký hoạt động');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rentals`
--

CREATE TABLE `rentals` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `receive_date` date NOT NULL,
  `return_date` date NOT NULL,
  `rental_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) NOT NULL,
  `payment_status` varchar(50) NOT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `cancelled_at` date DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `rentals`
--

INSERT INTO `rentals` (`id`, `customer_id`, `created_by`, `receive_date`, `return_date`, `rental_amount`, `deposit_amount`, `discount`, `final_amount`, `paid_amount`, `status`, `payment_status`, `cancel_reason`, `cancelled_at`, `note`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-11-12', '2026-11-14', 2850000.00, 7000000.00, 0.00, 9850000.00, 3000000.00, 'reserved', 'partial', NULL, NULL, 'Khách hẹn đến thử và nhận trang phục', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(2, 2, 2, '2026-11-09', '2026-11-11', 1800000.00, 4000000.00, 0.00, 5800000.00, 5800000.00, 'picked_up', 'paid', NULL, NULL, 'Khách đã nhận vest', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(3, 3, 2, '2026-10-31', '2026-11-03', 2800000.00, 3000000.00, 100000.00, 5700000.00, 5700000.00, 'returned', 'paid', NULL, NULL, 'Đã trả đủ trang phục, không hư hỏng', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(4, 4, 3, '2026-11-05', '2026-11-08', 1800000.00, 4000000.00, 0.00, 5800000.00, 2000000.00, 'overdue', 'partial', NULL, NULL, 'Đã liên hệ nhắc khách trả trang phục', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(5, 5, 1, '2026-11-15', '2026-11-17', 1400000.00, 3000000.00, 50000.00, 4350000.00, 0.00, 'reserved', 'unpaid', NULL, NULL, 'Đặt trước váy dạ hội, chưa thanh toán', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(6, 6, 2, '2026-11-07', '2026-11-09', 900000.00, 2400000.00, 0.00, 3300000.00, 3300000.00, 'returned', 'paid', NULL, NULL, 'Đã hoàn tất và hoàn cọc cho khách', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(7, 7, 3, '2026-11-08', '2026-11-11', 840000.00, 3000000.00, 40000.00, 3800000.00, 3800000.00, 'picked_up', 'paid', NULL, NULL, 'Khách thuê váy phù dâu', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(8, 8, 1, '2026-10-21', '2026-10-24', 750000.00, 1600000.00, 0.00, 2350000.00, 0.00, 'cancelled', 'unpaid', 'Khách thay đổi lịch sự kiện', '2026-10-22', 'Phiếu đã hủy trước ngày nhận', '2026-11-10 23:38:35', '2026-11-10 23:38:35'),
(9, 9, 2, '2026-11-11', '2026-11-13', 1300000.00, 2500000.00, 0.00, 3800000.00, 1300000.00, 'reserved', 'partial', NULL, NULL, 'Đã thanh toán trước một phần tiền thuê', '2026-11-10 23:38:35', '2026-11-10 23:38:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rental_items`
--

CREATE TABLE `rental_items` (
  `id` int(11) NOT NULL,
  `rental_id` int(11) NOT NULL,
  `costume_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `selected_size` varchar(20) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rental_days` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `condition_out` varchar(500) DEFAULT NULL,
  `condition_in` varchar(500) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `rental_items`
--

INSERT INTO `rental_items` (`id`, `rental_id`, `costume_id`, `quantity`, `selected_size`, `unit_price`, `rental_days`, `subtotal`, `deposit_amount`, `condition_out`, `condition_in`, `note`) VALUES
(1, 1, 1, 1, 'M', 1200000.00, 2, 2400000.00, 5000000.00, 'Tốt, đủ phụ kiện', NULL, 'Váy cưới chính'),
(2, 1, 2, 1, 'L', 450000.00, 1, 450000.00, 2000000.00, 'Tốt, đủ áo và quần', NULL, 'Vest đi kèm chụp ảnh'),
(3, 2, 2, 2, 'L', 450000.00, 2, 1800000.00, 4000000.00, 'Tốt, đủ áo và quần', NULL, 'Thuê 2 bộ vest'),
(4, 3, 3, 2, 'M', 350000.00, 4, 2800000.00, 3000000.00, 'Tốt', 'Đã trả đủ, không hư hỏng', 'Thuê áo dài cho gia đình'),
(5, 4, 4, 1, 'S', 900000.00, 2, 1800000.00, 4000000.00, 'Tốt, đủ phụ kiện', NULL, 'Khách chưa hoàn trả'),
(6, 5, 9, 1, 'M', 700000.00, 2, 1400000.00, 3000000.00, 'Tốt', NULL, 'Váy dạ hội đặt trước'),
(7, 6, 8, 3, 'L', 300000.00, 1, 900000.00, 2400000.00, 'Tốt', 'Đã kiểm tra, không hư hỏng', 'Thuê áo dài nhóm'),
(8, 7, 7, 3, 'M', 280000.00, 1, 840000.00, 3000000.00, 'Tốt', NULL, 'Ba váy phù dâu'),
(9, 8, 11, 3, 'S', 250000.00, 1, 750000.00, 1600000.00, 'Tốt', 'Chưa giao do phiếu bị hủy', 'Đã hủy đơn'),
(10, 9, 10, 2, 'L', 650000.00, 1, 1300000.00, 2500000.00, 'Tốt, đã kiểm tra', NULL, 'Thuê váy dạ hội cho nhóm');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `rental_id` int(11) NOT NULL,
  `rental_item_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `costume_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` varchar(500) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `reviews`
--

INSERT INTO `reviews` (`id`, `rental_id`, `rental_item_id`, `customer_id`, `costume_id`, `rating`, `comment`, `status`, `created_at`) VALUES
(1, 3, 4, 3, 3, 5, 'Áo dài đẹp, vừa vặn và được chuẩn bị sạch sẽ.', 1, '2026-11-10 23:38:35'),
(2, 6, 7, 6, 8, 4, 'Trang phục đúng mô tả, nhân viên hỗ trợ nhiệt tình.', 1, '2026-11-10 23:38:35'),
(3, 7, 8, 7, 7, 5, 'Váy phù dâu đẹp, chất liệu nhẹ và dễ di chuyển.', 1, '2026-11-10 23:38:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Quản trị viên', 'Quản lý toàn bộ hệ thống'),
(2, 'Thu ngân', 'Tạo phiếu thuê và xử lý thanh toán'),
(3, 'Nhân viên kiểm đồ', 'Kiểm tra tình trạng trang phục');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `roles_permissions`
--

CREATE TABLE `roles_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `roles_permissions`
--

INSERT INTO `roles_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(2, 1),
(2, 2),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(3, 1),
(3, 2),
(3, 9),
(3, 11);

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_logs_admin_fk` (`admin_id`);

--
-- Chỉ mục cho bảng `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UQ_admins_username` (`username`);

--
-- Chỉ mục cho bảng `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_roles_admin_fk` (`admin_id`),
  ADD KEY `admin_roles_role_fk` (`role_id`);

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `costumes`
--
ALTER TABLE `costumes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `costumes_category_fk` (`category_id`);

--
-- Chỉ mục cho bảng `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UQ_customers_phone` (`phone`),
  ADD UNIQUE KEY `UQ_customers_email` (`email`),
  ADD UNIQUE KEY `UQ_customers_identity_number` (`identity_number`);

--
-- Chỉ mục cho bảng `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payments_rental_fk` (`rental_id`);

--
-- Chỉ mục cho bảng `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `rentals`
--
ALTER TABLE `rentals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rentals_customer_fk` (`customer_id`),
  ADD KEY `rentals_admin_fk` (`created_by`);

--
-- Chỉ mục cho bảng `rental_items`
--
ALTER TABLE `rental_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rental_items_rental_fk` (`rental_id`),
  ADD KEY `rental_items_costume_fk` (`costume_id`);

--
-- Chỉ mục cho bảng `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviews_rental_fk` (`rental_id`),
  ADD KEY `reviews_rental_item_fk` (`rental_item_id`),
  ADD KEY `reviews_customer_fk` (`customer_id`),
  ADD KEY `reviews_costume_fk` (`costume_id`);

--
-- Chỉ mục cho bảng `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `roles_permissions`
--
ALTER TABLE `roles_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `roles_permissions_permission_fk` (`permission_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `admin_roles`
--
ALTER TABLE `admin_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `costumes`
--
ALTER TABLE `costumes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT cho bảng `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT cho bảng `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT cho bảng `rentals`
--
ALTER TABLE `rentals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `rental_items`
--
ALTER TABLE `rental_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`);

--
-- Các ràng buộc cho bảng `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD CONSTRAINT `admin_roles_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `admin_roles_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Các ràng buộc cho bảng `costumes`
--
ALTER TABLE `costumes`
  ADD CONSTRAINT `costumes_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Các ràng buộc cho bảng `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_rental_fk` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`id`);

--
-- Các ràng buộc cho bảng `rentals`
--
ALTER TABLE `rentals`
  ADD CONSTRAINT `rentals_admin_fk` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `rentals_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Các ràng buộc cho bảng `rental_items`
--
ALTER TABLE `rental_items`
  ADD CONSTRAINT `rental_items_costume_fk` FOREIGN KEY (`costume_id`) REFERENCES `costumes` (`id`),
  ADD CONSTRAINT `rental_items_rental_fk` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`id`);

--
-- Các ràng buộc cho bảng `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_costume_fk` FOREIGN KEY (`costume_id`) REFERENCES `costumes` (`id`),
  ADD CONSTRAINT `reviews_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `reviews_rental_fk` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`id`),
  ADD CONSTRAINT `reviews_rental_item_fk` FOREIGN KEY (`rental_item_id`) REFERENCES `rental_items` (`id`);

--
-- Các ràng buộc cho bảng `roles_permissions`
--
ALTER TABLE `roles_permissions`
  ADD CONSTRAINT `roles_permissions_permission_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`),
  ADD CONSTRAINT `roles_permissions_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
